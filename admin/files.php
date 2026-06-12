<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title       = sanitize($_POST['title'] ?? '');
        $courseId    = (int)$_POST['course_id'];
        $description = sanitize($_POST['description'] ?? '');
        $allowDl     = isset($_POST['allow_download']) ? 1 : 0;

        if (!$title || !$courseId) { flashMessage('danger','Title and course required.'); header('Location: files.php'); exit; }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            flashMessage('danger','Please select a file to upload.');
            header('Location: files.php'); exit;
        }

        $upload = uploadFile($_FILES['file'], SECURE_FILES_PATH . 'course_files/');
        if (isset($upload['error'])) { flashMessage('danger', $upload['error']); header('Location: files.php'); exit; }

        db()->execute(
            "INSERT INTO course_files (course_id,title,description,file_name,file_path,file_type,file_size,allow_download,uploaded_by) VALUES (?,?,?,?,?,?,?,?,?)",
            [$courseId,$title,$description,$upload['file_name'],$upload['file_path'],$upload['file_type'],$upload['file_size'],$allowDl,$_SESSION['user_id']]
        );
        flashMessage('success', "File '$title' uploaded securely.");
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $f = db()->fetchOne("SELECT file_path FROM course_files WHERE id=?", [$id]);
        if ($f && $f['file_path'] && file_exists($f['file_path'])) @unlink($f['file_path']);
        db()->execute("DELETE FROM course_files WHERE id=?", [$id]);
        flashMessage('success', 'File deleted.');
    }
    header('Location: files.php'); exit;
}

$pageTitle = 'Course Files';
require_once __DIR__ . '/layout.php';


$filterCourse = (int)($_GET['course'] ?? 0);
$sql = "SELECT cf.*, c.name as course_name FROM course_files cf JOIN courses c ON cf.course_id=c.id WHERE 1=1";
$params = [];
if ($filterCourse) { $sql .= " AND cf.course_id=?"; $params[] = $filterCourse; }
$sql .= " ORDER BY cf.created_at DESC";
$files   = db()->fetchAll($sql, $params);
$courses = db()->fetchAll("SELECT id,name FROM courses WHERE status='active' ORDER BY name");
$fileIcons = ['pdf'=>'bi-file-earmark-pdf','doc'=>'bi-file-earmark-word','docx'=>'bi-file-earmark-word',
    'ppt'=>'bi-file-earmark-ppt','pptx'=>'bi-file-earmark-ppt','jpg'=>'bi-file-earmark-image',
    'jpeg'=>'bi-file-earmark-image','png'=>'bi-file-earmark-image'];
$fileClasses = ['pdf'=>'file-pdf','doc'=>'file-doc','docx'=>'file-doc','ppt'=>'file-ppt','pptx'=>'file-ppt',
    'jpg'=>'file-img','jpeg'=>'file-img','png'=>'file-img'];
?>

<div class="section-header">
    <div>
        <div class="section-title">Course Files (Secure)</div>
        <div class="section-subtitle">Files stored outside public directory â€“ IP-restricted access</div>
    </div>
    <button class="btn-primary-academy" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-upload"></i> Upload File
    </button>
</div>

<!-- IP Notice -->
<div class="alert alert-info mb-3">
    <i class="bi bi-shield-lock me-2"></i>
    <strong>File Security Active:</strong> Students can only download files when connected to the institute network (allowed IPs). Files are served through secure PHP, never directly accessible via URL.
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
            <thead><tr><th>File</th><th>Title</th><th>Course</th><th>Size</th><th>Restriction</th><th>Uploaded</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($files): foreach ($files as $f):
                $ext  = strtolower($f['file_type'] ?? '');
                $icon = $fileIcons[$ext] ?? 'bi-file-earmark';
                $cls  = $fileClasses[$ext] ?? 'file-other';
            ?>
            <tr>
                <td><div class="file-icon <?= $cls ?>"><i class="bi <?= $icon ?>"></i></div></td>
                <td>
                    <div style="font-weight:500"><?= sanitize($f['title']) ?></div>
                    <?php if ($f['description']): ?>
                    <div style="font-size:.75rem;color:var(--text-muted)"><?= sanitize(substr($f['description'],0,60)) ?></div>
                    <?php endif; ?>
                </td>
                <td style="font-size:.82rem"><?= sanitize($f['course_name']) ?></td>
                <td style="font-size:.82rem;color:var(--text-muted)"><?= formatFileSize($f['file_size'] ?? 0) ?></td>
                <td>
                    <?php if (!$f['allow_download']): ?>
                    <span class="badge-academy badge-danger"><i class="bi bi-lock me-1"></i>IP Restricted</span>
                    <?php else: ?>
                    <span class="badge-academy badge-warning"><i class="bi bi-shield me-1"></i>Auth Required</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:.78rem;color:var(--text-muted)"><?= formatDate($f['created_at']) ?></td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="serve_file.php?type=course_file&id=<?= $f['id'] ?>" target="_blank" class="btn-icon btn-icon-view" title="Download">
                            <i class="bi bi-download"></i>
                        </a>
                        <form method="POST" onsubmit="return confirmDelete(this)">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $f['id'] ?>">
                            <?= csrfField() ?>
                            <button type="submit" class="btn-icon btn-icon-delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:3rem">No files uploaded yet</td></tr>
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
                <?= csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-shield-lock me-2"></i>Upload Secure File</h5>
                    <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12"><label class="form-label">Title *</label><input type="text" name="title" class="form-control" required placeholder="File title"></div>
                    <div class="col-12"><label class="form-label">Course *</label><select name="course_id" class="form-select" required><option value="">Select Course</option><?php foreach ($courses as $c): ?><option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                    <div class="col-12"><label class="form-label">File * <small style="color:var(--text-muted)">(Max 50MB)</small></label><input type="file" name="file" class="form-control" required accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.zip,.rar"></div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="allow_download" id="adl" class="form-check-input">
                            <label for="adl" class="form-check-label form-label">Allow download (if unchecked, only viewable inside institute)</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-academy"><i class="bi bi-upload"></i> Upload Securely</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>