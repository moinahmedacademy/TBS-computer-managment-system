<?php
$pageTitle = 'Lectures';
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title       = sanitize($_POST['title'] ?? '');
        $courseId    = (int)$_POST['course_id'];
        $description = sanitize($_POST['description'] ?? '');
        $allowDl     = isset($_POST['allow_download']) ? 1 : 0;

        if (!$title || !$courseId) { flashMessage('danger','Title and course required.'); header('Location: lectures.php'); exit; }

        $fileData = ['file_name'=>null,'file_path'=>null,'file_type'=>null,'file_size'=>null];
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['file'], SECURE_FILES_PATH . 'lectures/');
            if (isset($upload['error'])) { flashMessage('danger', $upload['error']); header('Location: lectures.php'); exit; }
            $fileData = ['file_name'=>$upload['file_name'],'file_path'=>$upload['file_path'],'file_type'=>$upload['file_type'],'file_size'=>$upload['file_size']];
        }

        db()->execute(
            "INSERT INTO lectures (course_id,title,description,file_name,file_path,file_type,file_size,allow_download,uploaded_by) VALUES (?,?,?,?,?,?,?,?,?)",
            [$courseId,$title,$description,$fileData['file_name'],$fileData['file_path'],$fileData['file_type'],$fileData['file_size'],$allowDl,$_SESSION['user_id']]
        );
        flashMessage('success', "Lecture '$title' added.");
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $lec = db()->fetchOne("SELECT file_path FROM lectures WHERE id=?", [$id]);
        if ($lec && $lec['file_path'] && file_exists($lec['file_path'])) @unlink($lec['file_path']);
        db()->execute("DELETE FROM lectures WHERE id=?", [$id]);
        flashMessage('success', 'Lecture deleted.');
    }
    header('Location: lectures.php'); exit;
}

$filterCourse = (int)($_GET['course'] ?? 0);
$sql = "SELECT l.*, c.name as course_name FROM lectures l JOIN courses c ON l.course_id=c.id WHERE 1=1";
$params = [];
if ($filterCourse) { $sql .= " AND l.course_id=?"; $params[] = $filterCourse; }
$sql .= " ORDER BY l.created_at DESC";
$lectures = db()->fetchAll($sql, $params);
$courses  = db()->fetchAll("SELECT id,name FROM courses WHERE status='active' ORDER BY name");

$fileIcons = ['pdf'=>'bi-file-earmark-pdf','doc'=>'bi-file-earmark-word','docx'=>'bi-file-earmark-word',
    'ppt'=>'bi-file-earmark-ppt','pptx'=>'bi-file-earmark-ppt','jpg'=>'bi-file-earmark-image',
    'jpeg'=>'bi-file-earmark-image','png'=>'bi-file-earmark-image','gif'=>'bi-file-earmark-image'];
$fileClasses = ['pdf'=>'file-pdf','doc'=>'file-doc','docx'=>'file-doc','ppt'=>'file-ppt','pptx'=>'file-ppt',
    'jpg'=>'file-img','jpeg'=>'file-img','png'=>'file-img','gif'=>'file-img'];
?>

<div class="section-header">
    <div>
        <div class="section-title">Lectures & Study Materials</div>
        <div class="section-subtitle"><?= count($lectures) ?> files uploaded</div>
    </div>
    <button class="btn-primary-academy" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-upload"></i> Upload Lecture
    </button>
</div>

<div class="data-card mb-3">
    <div style="padding:.75rem 1rem">
        <form method="GET" class="d-flex gap-2">
            <select name="course" class="form-select" style="width:200px" onchange="this.form.submit()">
                <option value="">All Courses</option>
                <?php foreach ($courses as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $filterCourse==$c['id']?'selected':'' ?>><?= sanitize($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<div class="data-card">
    <div class="table-wrap">
        <table class="table-academy">
            <thead><tr><th>File</th><th>Title</th><th>Course</th><th>Size</th><th>Download</th><th>Uploaded</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($lectures): foreach ($lectures as $l):
                $ext  = strtolower($l['file_type'] ?? '');
                $icon = $fileIcons[$ext] ?? 'bi-file-earmark';
                $cls  = $fileClasses[$ext] ?? 'file-other';
            ?>
            <tr>
                <td>
                    <div class="file-icon <?= $cls ?>">
                        <i class="bi <?= $icon ?>"></i>
                    </div>
                </td>
                <td>
                    <div style="font-weight:500"><?= sanitize($l['title']) ?></div>
                    <?php if ($l['description']): ?>
                    <div style="font-size:.75rem;color:var(--text-muted)"><?= sanitize(substr($l['description'],0,60)) ?>...</div>
                    <?php endif; ?>
                </td>
                <td style="font-size:.82rem"><?= sanitize($l['course_name']) ?></td>
                <td style="font-size:.82rem;color:var(--text-muted)"><?= $l['file_size'] ? formatFileSize($l['file_size']) : '—' ?></td>
                <td>
                    <span class="badge-academy <?= $l['allow_download']?'badge-success':'badge-danger' ?>">
                        <?= $l['allow_download'] ? 'Allowed' : 'Blocked' ?>
                    </span>
                </td>
                <td style="font-size:.78rem;color:var(--text-muted)"><?= formatDate($l['created_at']) ?></td>
                <td>
                    <div class="d-flex gap-1">
                        <?php if ($l['file_path']): ?>
                        <a href="<?= BASE_URL ?>/admin/serve_file.php?type=lecture&id=<?= $l['id'] ?>" target="_blank"
                            class="btn-icon btn-icon-view" title="View/Download">
                            <i class="bi bi-eye"></i>
                        </a>
                        <?php endif; ?>
                        <form method="POST" onsubmit="return confirmDelete(this)">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $l['id'] ?>">
                            <button type="submit" class="btn-icon btn-icon-delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:3rem">No lectures uploaded yet</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-upload me-2"></i>Upload Lecture</h5>
                    <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required placeholder="Lecture title">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Course *</label>
                        <select name="course_id" class="form-select" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Optional description..."></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">File <small style="color:var(--text-muted)">(PDF, DOC, PPT, Images – max 50MB)</small></label>
                        <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.gif">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="allow_download" id="allowDl" class="form-check-input" checked>
                            <label for="allowDl" class="form-check-label form-label">Allow students to download</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-academy"><i class="bi bi-upload"></i> Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
