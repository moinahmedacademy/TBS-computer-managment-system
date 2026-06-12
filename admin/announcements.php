<?php
$pageTitle = 'Announcements';
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $title    = sanitize($_POST['title'] ?? '');
        $content  = sanitize($_POST['content'] ?? '');
        $type     = $_POST['type'] ?? 'general';
        $audience = $_POST['target_audience'] ?? 'all';
        $courseId = (int)($_POST['course_id'] ?? 0) ?: null;
        $pinned   = isset($_POST['is_pinned']) ? 1 : 0;
        $expires  = $_POST['expires_at'] ?: null;

        if (!$title || !$content) { flashMessage('danger','Title and content required.'); header('Location: announcements.php'); exit; }

        if ($action === 'add') {
            db()->execute(
                "INSERT INTO announcements (title,content,type,target_audience,course_id,is_pinned,expires_at,published_by) VALUES (?,?,?,?,?,?,?,?)",
                [$title,$content,$type,$audience,$courseId,$pinned,$expires,$_SESSION['user_id']]
            );
            flashMessage('success', 'Announcement published.');
        } else {
            $id = (int)$_POST['id'];
            db()->execute(
                "UPDATE announcements SET title=?,content=?,type=?,target_audience=?,course_id=?,is_pinned=?,expires_at=? WHERE id=?",
                [$title,$content,$type,$audience,$courseId,$pinned,$expires,$id]
            );
            flashMessage('success', 'Announcement updated.');
        }
    } elseif ($action === 'delete') {
        db()->execute("DELETE FROM announcements WHERE id=?", [(int)$_POST['id']]);
        flashMessage('success', 'Deleted.');
    } elseif ($action === 'pin') {
        $id = (int)$_POST['id'];
        $curr = db()->fetchOne("SELECT is_pinned FROM announcements WHERE id=?", [$id]);
        db()->execute("UPDATE announcements SET is_pinned=? WHERE id=?", [$curr['is_pinned']?0:1, $id]);
        flashMessage('success', 'Pin status updated.');
    }
    header('Location: announcements.php'); exit;
}

$announcements = db()->fetchAll(
    "SELECT a.*, c.name as course_name FROM announcements a LEFT JOIN courses c ON a.course_id=c.id ORDER BY a.is_pinned DESC, a.created_at DESC"
);
$courses = db()->fetchAll("SELECT id,name FROM courses WHERE status='active' ORDER BY name");

$typeColors = ['holiday'=>'danger','test'=>'warning','course'=>'info','event'=>'success','notice'=>'warning','general'=>'secondary'];
?>

<div class="section-header">
    <div>
        <div class="section-title">Announcements</div>
        <div class="section-subtitle"><?= count($announcements) ?> announcements</div>
    </div>
    <button class="btn-primary-academy" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg"></i> New Announcement
    </button>
</div>

<div class="row g-3">
<?php foreach ($announcements as $ann):
    $color = $typeColors[$ann['type']] ?? 'secondary';
    $expired = $ann['expires_at'] && strtotime($ann['expires_at']) < time();
?>
<div class="col-12 col-lg-6">
    <div class="data-card" style="padding:1.25rem;<?= $expired ? 'opacity:.6' : '' ?>">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div class="d-flex align-items-center gap-2">
                <?php if ($ann['is_pinned']): ?>
                <i class="bi bi-pin-fill" style="color:var(--accent);font-size:1rem"></i>
                <?php endif; ?>
                <span style="font-weight:600;font-size:.9rem"><?= sanitize($ann['title']) ?></span>
            </div>
            <div class="d-flex gap-1 align-items-center">
                <span class="badge-academy badge-<?= $color ?>" style="font-size:.7rem"><?= ucfirst($ann['type']) ?></span>
                <?php if ($expired): ?><span class="badge-academy badge-secondary" style="font-size:.7rem">Expired</span><?php endif; ?>
            </div>
        </div>
        <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:.75rem;line-height:1.5"><?= nl2br(sanitize($ann['content'])) ?></p>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div style="font-size:.75rem;color:var(--text-muted)">
                <i class="bi bi-calendar3 me-1"></i><?= formatDate($ann['created_at'], 'd M Y') ?>
                &nbsp;|&nbsp;<i class="bi bi-people me-1"></i><?= ucfirst($ann['target_audience']) ?>
                <?php if ($ann['course_name']): ?>&nbsp;|&nbsp;<?= sanitize($ann['course_name']) ?><?php endif; ?>
                <?php if ($ann['expires_at']): ?>&nbsp;|&nbsp;Expires: <?= formatDate($ann['expires_at']) ?><?php endif; ?>
            </div>
            <div class="d-flex gap-1">
                <form method="POST">
                    <input type="hidden" name="action" value="pin">
                    <input type="hidden" name="id" value="<?= $ann['id'] ?>">
                    <?= csrfField() ?>
                    <button type="submit" class="btn-icon" style="background:rgba(245,158,11,.1);color:var(--accent)" title="<?= $ann['is_pinned']?'Unpin':'Pin' ?>">
                        <i class="bi bi-pin<?= $ann['is_pinned']?'-fill':'' ?>"></i>
                    </button>
                </form>
                <button class="btn-icon btn-icon-edit" onclick="editAnn(<?= htmlspecialchars(json_encode($ann)) ?>)">
                    <i class="bi bi-pencil"></i>
                </button>
                <form method="POST" onsubmit="return confirmDelete(this)">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $ann['id'] ?>">
                    <?= csrfField() ?>
                    <button type="submit" class="btn-icon btn-icon-delete"><i class="bi bi-trash"></i></button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php if (!$announcements): ?>
<div class="col-12">
    <div class="data-card" style="text-align:center;padding:3rem;color:var(--text-muted)">
        <i class="bi bi-megaphone" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
        No announcements yet.
    </div>
</div>
<?php endif; ?>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <?= csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title">New Announcement</h5>
                    <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required placeholder="Announcement title">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            <option value="general">General</option>
                            <option value="holiday">Holiday</option>
                            <option value="test">Test</option>
                            <option value="course">Course</option>
                            <option value="event">Event</option>
                            <option value="notice">Notice</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Audience</label>
                        <select name="target_audience" class="form-select">
                            <option value="all">All</option>
                            <option value="students">Students Only</option>
                            <option value="parents">Parents Only</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Course (Optional)</label>
                        <select name="course_id" class="form-select">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Expires On</label>
                        <input type="date" name="expires_at" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Content *</label>
                        <textarea name="content" class="form-control" rows="4" required placeholder="Announcement details..."></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="is_pinned" id="pin_add" class="form-check-input" style="background:var(--surface2);border-color:var(--border)">
                            <label for="pin_add" class="form-check-label form-label">Pin to top</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-academy">Publish</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="ea_id">
                <?= csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Edit Announcement</h5>
                    <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" id="ea_title" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Type</label>
                        <select name="type" id="ea_type" class="form-select">
                            <option value="general">General</option>
                            <option value="holiday">Holiday</option>
                            <option value="test">Test</option>
                            <option value="course">Course</option>
                            <option value="event">Event</option>
                            <option value="notice">Notice</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Audience</label>
                        <select name="target_audience" id="ea_audience" class="form-select">
                            <option value="all">All</option>
                            <option value="students">Students Only</option>
                            <option value="parents">Parents Only</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Course</label>
                        <select name="course_id" id="ea_course" class="form-select">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Expires On</label>
                        <input type="date" name="expires_at" id="ea_expires" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Content</label>
                        <textarea name="content" id="ea_content" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="is_pinned" id="ea_pin" class="form-check-input">
                            <label for="ea_pin" class="form-check-label form-label">Pin to top</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-academy">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editAnn(a) {
    document.getElementById('ea_id').value = a.id;
    document.getElementById('ea_title').value = a.title;
    document.getElementById('ea_type').value = a.type;
    document.getElementById('ea_audience').value = a.target_audience;
    document.getElementById('ea_course').value = a.course_id || '';
    document.getElementById('ea_expires').value = a.expires_at || '';
    document.getElementById('ea_content').value = a.content;
    document.getElementById('ea_pin').checked = a.is_pinned == 1;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
