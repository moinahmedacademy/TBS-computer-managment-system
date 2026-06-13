<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Ensure extra columns exist
db()->execute("ALTER TABLE announcements ADD COLUMN IF NOT EXISTS priority     ENUM('normal','important','urgent','critical') NOT NULL DEFAULT 'normal'");
db()->execute("ALTER TABLE announcements ADD COLUMN IF NOT EXISTS attachment   VARCHAR(255) NULL");
db()->execute("ALTER TABLE announcements ADD COLUMN IF NOT EXISTS attachment_name VARCHAR(255) NULL");
db()->execute("ALTER TABLE announcements ADD COLUMN IF NOT EXISTS publish_at   DATETIME NULL");
db()->execute("ALTER TABLE announcements ADD COLUMN IF NOT EXISTS target_ref   VARCHAR(100) NULL");
db()->execute("ALTER TABLE announcements ADD COLUMN IF NOT EXISTS send_whatsapp TINYINT(1) NOT NULL DEFAULT 0");

// Upload directory
$uploadDir = UPLOADS_PATH . 'announcements/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $title      = sanitize($_POST['title']           ?? '');
        $content    = sanitize($_POST['content']         ?? '');
        $type       = $_POST['type']                     ?? 'general';
        $audience   = $_POST['target_audience']          ?? 'all';
        $targetRef  = sanitize($_POST['target_ref']      ?? '');
        $courseId   = ($audience === 'course') ? ((int)($targetRef) ?: null) : null;
        $pinned     = isset($_POST['is_pinned'])          ? 1 : 0;
        $priority   = $_POST['priority']                 ?? 'normal';
        $expires    = $_POST['expires_at']               ?: null;
        $publishAt  = $_POST['publish_at']               ?: null;
        $sendWa     = 0; // WA sending handled from WhatsApp page

        if (!$title || !$content) {
            flashMessage('danger', 'Title and content are required.');
            header('Location: announcements.php'); exit;
        }

        // Handle file upload
        $attachPath = null; $attachName = null;
        if (!empty($_FILES['attachment']['name'])) {
            $allowed = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png','gif'];
            $ext     = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                flashMessage('danger', 'File type not allowed. Allowed: PDF, DOC, DOCX, XLS, XLSX, Images.');
                header('Location: announcements.php'); exit;
            }
            if ($_FILES['attachment']['size'] > 5 * 1024 * 1024) {
                flashMessage('danger', 'File too large. Max 5MB.');
                header('Location: announcements.php'); exit;
            }
            $attachName = sanitize($_FILES['attachment']['name']);
            $attachPath = 'ann_' . time() . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $attachPath);
        }

        if ($action === 'add') {
            db()->execute(
                "INSERT INTO announcements (title,content,type,target_audience,course_id,is_pinned,priority,expires_at,publish_at,target_ref,send_whatsapp,attachment,attachment_name,published_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$title,$content,$type,$audience,$courseId,$pinned,$priority,$expires,$publishAt,$targetRef,$sendWa,$attachPath,$attachName,$_SESSION['user_id']]
            );
            flashMessage('success', 'Announcement published.');
        } else {
            $id = (int)$_POST['id'];
            if ($attachPath) {
                db()->execute(
                    "UPDATE announcements SET title=?,content=?,type=?,target_audience=?,course_id=?,is_pinned=?,priority=?,expires_at=?,publish_at=?,target_ref=?,send_whatsapp=?,attachment=?,attachment_name=? WHERE id=?",
                    [$title,$content,$type,$audience,$courseId,$pinned,$priority,$expires,$publishAt,$targetRef,$sendWa,$attachPath,$attachName,$id]
                );
            } else {
                db()->execute(
                    "UPDATE announcements SET title=?,content=?,type=?,target_audience=?,course_id=?,is_pinned=?,priority=?,expires_at=?,publish_at=?,target_ref=?,send_whatsapp=? WHERE id=?",
                    [$title,$content,$type,$audience,$courseId,$pinned,$priority,$expires,$publishAt,$targetRef,$sendWa,$id]
                );
            }
            flashMessage('success', 'Announcement updated.');
        }
    } elseif ($action === 'delete') {
        $id  = (int)$_POST['id'];
        $row = db()->fetchOne("SELECT attachment FROM announcements WHERE id=?", [$id]);
        if ($row && $row['attachment'] && file_exists($uploadDir . $row['attachment'])) {
            unlink($uploadDir . $row['attachment']);
        }
        db()->execute("DELETE FROM announcements WHERE id=?", [$id]);
        flashMessage('success', 'Announcement deleted.');
    } elseif ($action === 'pin') {
        $id   = (int)$_POST['id'];
        $curr = db()->fetchOne("SELECT is_pinned FROM announcements WHERE id=?", [$id]);
        db()->execute("UPDATE announcements SET is_pinned=? WHERE id=?", [$curr['is_pinned'] ? 0 : 1, $id]);
        flashMessage('success', 'Pin status updated.');
    }
    header('Location: announcements.php'); exit;
}

$pageTitle = 'Announcements';
require_once __DIR__ . '/layout.php';

$announcements = db()->fetchAll(
    "SELECT a.*, c.name as course_name, u.name as publisher_name
     FROM announcements a
     LEFT JOIN courses c ON a.course_id = c.id
     LEFT JOIN users   u ON a.published_by = u.id
     ORDER BY a.is_pinned DESC, a.created_at DESC"
);
$courses  = db()->fetchAll("SELECT id,name FROM courses WHERE status='active' ORDER BY name");
$batches  = db()->fetchAll("SELECT DISTINCT batch FROM students WHERE batch IS NOT NULL AND batch != '' ORDER BY batch");
$students = db()->fetchAll("SELECT id,name,roll_number FROM students WHERE status='active' ORDER BY name");

// Stats
$total     = count($announcements);
$now       = time();
$published = 0; $scheduled = 0; $expired = 0;
foreach ($announcements as $a) {
    if ($a['expires_at'] && strtotime($a['expires_at']) < $now) { $expired++; continue; }
    if ($a['publish_at'] && strtotime($a['publish_at']) > $now) { $scheduled++; continue; }
    $published++;
}

$priorityColors = ['normal'=>'secondary','important'=>'info','urgent'=>'warning','critical'=>'danger'];
$priorityLabels = ['normal'=>'Normal','important'=>'Important','urgent'=>'Urgent','critical'=>'Critical'];
$typeColors     = ['academic'=>'info','exam'=>'warning','result'=>'success','attendance'=>'warning',
                   'event'=>'success','holiday'=>'danger','fee'=>'warning','general'=>'secondary'];
$typeIcons      = ['academic'=>'bi-mortarboard','exam'=>'bi-pencil-square','result'=>'bi-bar-chart',
                   'attendance'=>'bi-calendar-check','event'=>'bi-calendar-event','holiday'=>'bi-umbrella',
                   'fee'=>'bi-credit-card','general'=>'bi-megaphone'];

?>

<div class="section-header">
    <div>
        <div class="section-title"><i class="bi bi-megaphone me-2" style="color:var(--accent)"></i>Announcements</div>
        <div class="section-subtitle"><?= $total ?> total announcements</div>
    </div>
    <button class="btn-primary-academy" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg"></i> New Announcement
    </button>
</div>

<!-- Stats -->
<div class="row g-3 mb-3">
    <?php foreach ([
        ['label'=>'Total',     'val'=>$total,     'icon'=>'bi-megaphone',       'color'=>'#f59e0b'],
        ['label'=>'Published', 'val'=>$published, 'icon'=>'bi-check-circle',    'color'=>'#10b981'],
        ['label'=>'Scheduled', 'val'=>$scheduled, 'icon'=>'bi-clock',           'color'=>'#3b82f6'],
        ['label'=>'Expired',   'val'=>$expired,   'icon'=>'bi-calendar-x',      'color'=>'#6b7280'],
    ] as $s): ?>
    <div class="col-6 col-md-3">
        <div class="data-card" style="padding:1rem;display:flex;align-items:center;gap:.85rem">
            <div style="width:44px;height:44px;border-radius:10px;background:<?= $s['color'] ?>22;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi <?= $s['icon'] ?>" style="color:<?= $s['color'] ?>;font-size:1.2rem"></i>
            </div>
            <div>
                <div style="font-size:1.4rem;font-weight:700;line-height:1"><?= $s['val'] ?></div>
                <div style="font-size:.72rem;color:var(--text-muted);margin-top:.15rem"><?= $s['label'] ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>


<!-- Announcements Grid -->
<div class="row g-3">
<?php if (!$announcements): ?>
<div class="col-12">
    <div class="data-card" style="text-align:center;padding:3rem;color:var(--text-muted)">
        <i class="bi bi-megaphone" style="font-size:2.5rem;display:block;margin-bottom:.75rem"></i>
        No announcements yet. Click <strong>New Announcement</strong> to publish one.
    </div>
</div>
<?php endif; ?>

<?php foreach ($announcements as $ann):
    $isExpired   = $ann['expires_at'] && strtotime($ann['expires_at']) < $now;
    $isScheduled = $ann['publish_at'] && strtotime($ann['publish_at']) > $now;
    $pColor      = $priorityColors[$ann['priority'] ?? 'normal'] ?? 'secondary';
    $tColor      = $typeColors[$ann['type'] ?? 'general'] ?? 'secondary';
    $tIcon       = $typeIcons[$ann['type']  ?? 'general'] ?? 'bi-megaphone';
    $p = $ann['priority'] ?? 'normal';
    $borderColor = $p === 'critical' ? '#ef4444' : ($p === 'urgent' ? '#f59e0b' : ($p === 'important' ? '#3b82f6' : 'var(--border)'));
?>
<div class="col-12 col-lg-6">
    <div class="data-card" style="padding:1.25rem;border-left:3px solid <?= $borderColor ?>;<?= $isExpired ? 'opacity:.55' : '' ?>">

        <!-- Header row -->
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <?php if ($ann['is_pinned']): ?>
                <i class="bi bi-pin-fill" style="color:var(--accent);font-size:.95rem" title="Pinned"></i>
                <?php endif; ?>
                <span style="font-weight:700;font-size:.92rem"><?= sanitize($ann['title']) ?></span>
            </div>
            <div class="d-flex gap-1 flex-wrap justify-content-end">
                <span class="badge-academy badge-<?= $tColor ?>" style="font-size:.68rem">
                    <i class="bi <?= $tIcon ?> me-1"></i><?= ucfirst($ann['type'] ?? 'general') ?>
                </span>
                <?php if (($ann['priority'] ?? 'normal') !== 'normal'): ?>
                <span class="badge-academy badge-<?= $pColor ?>" style="font-size:.68rem"><?= $priorityLabels[$ann['priority']] ?></span>
                <?php endif; ?>
                <?php if ($isExpired):  ?><span class="badge-academy badge-secondary" style="font-size:.68rem">Expired</span><?php endif; ?>
                <?php if ($isScheduled): ?><span class="badge-academy badge-info"      style="font-size:.68rem">Scheduled</span><?php endif; ?>
            </div>
        </div>

        <!-- Content -->
        <p style="font-size:.84rem;color:var(--text-muted);margin-bottom:.85rem;line-height:1.6;max-height:80px;overflow:hidden"><?= nl2br(sanitize($ann['content'])) ?></p>

        <!-- Attachment -->
        <?php if (!empty($ann['attachment'])): ?>
        <div style="margin-bottom:.75rem;background:var(--surface2);border-radius:8px;padding:.5rem .85rem;display:flex;align-items:center;gap:.6rem;font-size:.8rem">
            <i class="bi bi-paperclip" style="color:var(--accent)"></i>
            <span style="flex:1;color:var(--text-muted)"><?= sanitize($ann['attachment_name'] ?? $ann['attachment']) ?></span>
            <a href="<?= BASE_URL ?>/assets/uploads/announcements/<?= urlencode($ann['attachment']) ?>"
               target="_blank" class="btn-icon" style="width:auto;padding:.25rem .6rem;font-size:.75rem;background:rgba(245,158,11,.15);color:var(--accent);border:none;border-radius:6px">
                <i class="bi bi-download me-1"></i>Download
            </a>
        </div>
        <?php endif; ?>

        <!-- Meta -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div style="font-size:.73rem;color:var(--text-muted);display:flex;flex-wrap:wrap;gap:.4rem .75rem">
                <span><i class="bi bi-calendar3 me-1"></i><?= formatDate($ann['created_at'], 'd M Y') ?></span>
                <span><i class="bi bi-people me-1"></i><?= ucfirst($ann['target_audience'] ?? 'all') ?><?= $ann['target_ref'] ? ': '.$ann['target_ref'] : '' ?></span>
                <?php if (!empty($ann['course_name'])): ?><span><i class="bi bi-book me-1"></i><?= sanitize($ann['course_name']) ?></span><?php endif; ?>
                <?php if ($ann['expires_at']): ?><span><i class="bi bi-clock me-1"></i>Expires <?= formatDate($ann['expires_at']) ?></span><?php endif; ?>
                <?php if ($ann['publish_at']): ?><span><i class="bi bi-send me-1"></i>Publishes <?= formatDate($ann['publish_at']) ?></span><?php endif; ?>
                <?php if (!empty($ann['publisher_name'])): ?><span><i class="bi bi-person me-1"></i><?= sanitize($ann['publisher_name']) ?></span><?php endif; ?>
            </div>
            <div class="d-flex gap-1">
                <!-- Pin toggle -->
                <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="pin">
                    <input type="hidden" name="id"     value="<?= $ann['id'] ?>">
                    <?= csrfField() ?>
                    <button type="submit" class="btn-icon"
                            style="background:rgba(245,158,11,<?= $ann['is_pinned']?'.25':'.08' ?>);color:var(--accent)"
                            title="<?= $ann['is_pinned'] ? 'Unpin' : 'Pin to top' ?>">
                        <i class="bi bi-pin<?= $ann['is_pinned'] ? '-fill' : '' ?>"></i>
                    </button>
                </form>
                <!-- Edit -->
                <button class="btn-icon btn-icon-edit ann-edit-btn" title="Edit"
                        data-ann="<?= htmlspecialchars(json_encode($ann), ENT_QUOTES) ?>">
                    <i class="bi bi-pencil"></i>
                </button>
                <!-- Delete -->
                <form method="POST" id="delForm-<?= $ann['id'] ?>" style="display:inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id"     value="<?= $ann['id'] ?>">
                    <?= csrfField() ?>
                </form>
                <button type="button" class="btn-icon btn-icon-delete ann-del-btn" title="Delete"
                        data-id="<?= $ann['id'] ?>"
                        data-title="<?= htmlspecialchars(sanitize($ann['title']), ENT_QUOTES) ?>">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- ══ ADD MODAL ═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <?= csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-megaphone me-2" style="color:var(--accent)"></i>New Announcement</h5>
                    <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">

                    <div class="col-12">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required placeholder="Announcement title">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Category *</label>
                        <select name="type" class="form-select">
                            <option value="general">General</option>
                            <option value="academic">Academic</option>
                            <option value="exam">Exam / Test</option>
                            <option value="result">Result</option>
                            <option value="attendance">Attendance</option>
                            <option value="event">Event</option>
                            <option value="holiday">Holiday</option>
                            <option value="fee">Fee Notice</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="normal">Normal</option>
                            <option value="important">Important</option>
                            <option value="urgent">Urgent</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Target Audience *</label>
                        <select name="target_audience" class="form-select" id="add_audience" onchange="toggleTargetRef('add')">
                            <option value="all">All Users</option>
                            <option value="students">All Students</option>
                            <option value="parents">All Parents</option>
                            <option value="course">Specific Course</option>
                            <option value="batch">Specific Batch</option>
                            <option value="student">Specific Student</option>
                        </select>
                    </div>

                    <div class="col-md-6" id="add_ref_wrap" style="display:none">
                        <label class="form-label" id="add_ref_label">Select</label>
                        <select name="target_ref" id="add_ref_course" class="form-select" style="display:none">
                            <option value="">Choose course…</option>
                            <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="target_ref" id="add_ref_batch" class="form-select" style="display:none">
                            <option value="">Choose batch…</option>
                            <?php foreach ($batches as $b): ?>
                            <option value="<?= sanitize($b['batch']) ?>"><?= sanitize($b['batch']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="target_ref" id="add_ref_student" class="form-select" style="display:none">
                            <option value="">Choose student…</option>
                            <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= sanitize($s['name']) ?> (<?= sanitize($s['roll_number']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Publish Date</label>
                        <input type="datetime-local" name="publish_at" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expires_at" class="form-control">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Description *</label>
                        <textarea name="content" class="form-control" rows="5" required placeholder="Announcement details…"></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Attachment <small style="color:var(--text-muted)">(PDF, DOC, DOCX, XLS, XLSX, Images — max 5MB)</small></label>
                        <input type="file" name="attachment" class="form-control"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif">
                    </div>

                    <div class="col-md-6">
                        <div class="form-check mt-2">
                            <input type="checkbox" name="is_pinned" id="pin_add" class="form-check-input">
                            <label for="pin_add" class="form-check-label form-label">
                                <i class="bi bi-pin me-1" style="color:var(--accent)"></i> Pin to top
                            </label>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-academy"><i class="bi bi-send me-1"></i>Publish</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ EDIT MODAL ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id"     id="ea_id">
                <?= csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2" style="color:var(--accent)"></i>Edit Announcement</h5>
                    <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">

                    <div class="col-12">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" id="ea_title" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Category</label>
                        <select name="type" id="ea_type" class="form-select">
                            <option value="general">General</option>
                            <option value="academic">Academic</option>
                            <option value="exam">Exam / Test</option>
                            <option value="result">Result</option>
                            <option value="attendance">Attendance</option>
                            <option value="event">Event</option>
                            <option value="holiday">Holiday</option>
                            <option value="fee">Fee Notice</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Priority</label>
                        <select name="priority" id="ea_priority" class="form-select">
                            <option value="normal">Normal</option>
                            <option value="important">Important</option>
                            <option value="urgent">Urgent</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Target Audience</label>
                        <select name="target_audience" id="ea_audience" class="form-select" onchange="toggleTargetRef('ea')">
                            <option value="all">All Users</option>
                            <option value="students">All Students</option>
                            <option value="parents">All Parents</option>
                            <option value="course">Specific Course</option>
                            <option value="batch">Specific Batch</option>
                            <option value="student">Specific Student</option>
                        </select>
                    </div>

                    <div class="col-md-6" id="ea_ref_wrap" style="display:none">
                        <label class="form-label" id="ea_ref_label">Select</label>
                        <select name="target_ref" id="ea_ref_course" class="form-select" style="display:none">
                            <option value="">Choose course…</option>
                            <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="target_ref" id="ea_ref_batch" class="form-select" style="display:none">
                            <option value="">Choose batch…</option>
                            <?php foreach ($batches as $b): ?>
                            <option value="<?= sanitize($b['batch']) ?>"><?= sanitize($b['batch']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="target_ref" id="ea_ref_student" class="form-select" style="display:none">
                            <option value="">Choose student…</option>
                            <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= sanitize($s['name']) ?> (<?= sanitize($s['roll_number']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Publish Date</label>
                        <input type="datetime-local" name="publish_at" id="ea_publish" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expires_at" id="ea_expires" class="form-control">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="content" id="ea_content" class="form-control" rows="5"></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Replace Attachment <small style="color:var(--text-muted)">(leave empty to keep existing)</small></label>
                        <input type="file" name="attachment" class="form-control"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif">
                        <div id="ea_current_attach" style="font-size:.78rem;color:var(--text-muted);margin-top:.3rem"></div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-check mt-2">
                            <input type="checkbox" name="is_pinned" id="ea_pin" class="form-check-input">
                            <label for="ea_pin" class="form-check-label form-label">
                                <i class="bi bi-pin me-1" style="color:var(--accent)"></i> Pin to top
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-academy"><i class="bi bi-check-lg me-1"></i>Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ DELETE CONFIRM MODAL ════════════════════════════════════════════════ -->
<div class="modal fade" id="annDeleteModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
        <div class="modal-content" style="border-top:3px solid #ef4444">
            <div class="modal-body text-center" style="padding:2rem">
                <div style="width:64px;height:64px;border-radius:50%;background:rgba(239,68,68,.15);display:inline-flex;align-items:center;justify-content:center;margin-bottom:1rem">
                    <i class="bi bi-trash" style="font-size:1.8rem;color:#ef4444"></i>
                </div>
                <h5 style="font-weight:700;margin-bottom:.5rem">Delete Announcement?</h5>
                <p id="annDeleteMsg" style="color:var(--text-muted);font-size:.88rem;margin-bottom:1.5rem"></p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i> Cancel
                    </button>
                    <button type="button" id="annDeleteYes" class="btn btn-danger" style="font-weight:600">
                        <i class="bi bi-trash me-1"></i> Yes, Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ── Target audience dynamic ref field ─────────────────────────────────────
function toggleTargetRef(prefix) {
    const audience = document.getElementById(prefix + (prefix === 'add' ? '_audience' : '_audience')).value;
    const wrap    = document.getElementById(prefix + '_ref_wrap');
    const course  = document.getElementById(prefix + '_ref_course');
    const batch   = document.getElementById(prefix + '_ref_batch');
    const student = document.getElementById(prefix + '_ref_student');
    const label   = document.getElementById(prefix + '_ref_label');

    [course, batch, student].forEach(el => { el.style.display = 'none'; el.disabled = true; });

    if (audience === 'course')  { wrap.style.display = ''; course.style.display  = ''; course.disabled  = false; label.textContent = 'Course'; }
    else if (audience === 'batch')   { wrap.style.display = ''; batch.style.display   = ''; batch.disabled   = false; label.textContent = 'Batch'; }
    else if (audience === 'student') { wrap.style.display = ''; student.style.display = ''; student.disabled = false; label.textContent = 'Student'; }
    else { wrap.style.display = 'none'; }
}

// ── Edit modal population ──────────────────────────────────────────────────
function editAnn(a) {
    document.getElementById('ea_id').value       = a.id;
    document.getElementById('ea_title').value    = a.title;
    document.getElementById('ea_type').value     = a.type       || 'general';
    document.getElementById('ea_priority').value = a.priority   || 'normal';
    document.getElementById('ea_audience').value = a.target_audience || 'all';
    document.getElementById('ea_expires').value  = a.expires_at ? a.expires_at.substring(0,10) : '';
    document.getElementById('ea_publish').value  = a.publish_at ? a.publish_at.substring(0,16) : '';
    document.getElementById('ea_content').value  = a.content;
    document.getElementById('ea_pin').checked    = a.is_pinned == 1;


    // Show attachment info if exists
    const attDiv = document.getElementById('ea_current_attach');
    attDiv.textContent = a.attachment_name ? 'Current: ' + a.attachment_name : '';

    // Show target ref
    toggleTargetRef('ea');
    const audience = a.target_audience || 'all';
    if (audience === 'course')       document.getElementById('ea_ref_course').value  = a.course_id  || '';
    else if (audience === 'batch')   document.getElementById('ea_ref_batch').value   = a.target_ref || '';
    else if (audience === 'student') document.getElementById('ea_ref_student').value = a.target_ref || '';

    new bootstrap.Modal(document.getElementById('editModal')).show();
}

// ── Edit buttons ───────────────────────────────────────────────────────────
document.querySelectorAll('.ann-edit-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        editAnn(JSON.parse(this.dataset.ann));
    });
});

// ── Delete confirmation ────────────────────────────────────────────────────
let _delFormId = null;

document.querySelectorAll('.ann-del-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        _delFormId = 'delForm-' + this.dataset.id;
        document.getElementById('annDeleteMsg').textContent =
            '"' + this.dataset.title + '" will be permanently deleted. This cannot be undone.';
        new bootstrap.Modal(document.getElementById('annDeleteModal')).show();
    });
});

document.getElementById('annDeleteYes').addEventListener('click', function() {
    bootstrap.Modal.getInstance(document.getElementById('annDeleteModal')).hide();
    if (_delFormId) document.getElementById(_delFormId).submit();
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
