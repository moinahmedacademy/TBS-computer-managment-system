<?php
$pageTitle = 'Courses';
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name       = sanitize($_POST['name'] ?? '');
        $code       = strtoupper(sanitize($_POST['code'] ?? ''));
        $duration   = sanitize($_POST['duration'] ?? '');
        $instructor = sanitize($_POST['instructor'] ?? '');
        $description= sanitize($_POST['description'] ?? '');
        $fee        = (float)($_POST['fee'] ?? 0);
        $status     = $_POST['status'] ?? 'active';

        if (!$name || !$code) { flashMessage('danger','Name and code required.'); header('Location: courses.php'); exit; }

        if ($action === 'add') {
            db()->execute(
                "INSERT INTO courses (name,code,duration,instructor,description,fee,status) VALUES (?,?,?,?,?,?,?)",
                [$name,$code,$duration,$instructor,$description,$fee,$status]
            );
            flashMessage('success', "Course '$name' added.");
        } else {
            $id = (int)$_POST['id'];
            db()->execute(
                "UPDATE courses SET name=?,code=?,duration=?,instructor=?,description=?,fee=?,status=? WHERE id=?",
                [$name,$code,$duration,$instructor,$description,$fee,$status,$id]
            );
            flashMessage('success', "Course updated.");
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        db()->execute("DELETE FROM courses WHERE id=?", [$id]);
        flashMessage('success', "Course deleted.");
    }
    header('Location: courses.php'); exit;
}

$courses = db()->fetchAll(
    "SELECT c.*, (SELECT COUNT(*) FROM students s WHERE s.course_id=c.id AND s.status='active') as student_count
     FROM courses c ORDER BY c.created_at DESC"
);
?>

<div class="section-header">
    <div>
        <div class="section-title">Courses Management</div>
        <div class="section-subtitle"><?= count($courses) ?> courses</div>
    </div>
    <button class="btn-primary-academy" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg"></i> Add Course
    </button>
</div>

<div class="row g-3">
<?php foreach ($courses as $c): ?>
<div class="col-12 col-md-6 col-xl-4">
    <div class="data-card" style="padding:1.25rem;position:relative">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div class="d-flex align-items-center gap-2">
                <div class="stat-icon" style="background:rgba(245,158,11,.12);color:var(--accent);margin:0;width:44px;height:44px;font-size:1.1rem">
                    <i class="bi bi-book"></i>
                </div>
                <div>
                    <div style="font-weight:600;font-size:.9rem"><?= sanitize($c['name']) ?></div>
                    <div style="font-size:.75rem;color:var(--accent);font-weight:600"><?= sanitize($c['code']) ?></div>
                </div>
            </div>
            <span class="badge-academy <?= $c['status']==='active'?'badge-success':'badge-danger' ?>">
                <?= ucfirst($c['status']) ?>
            </span>
        </div>

        <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:.75rem">
            <?= sanitize($c['description'] ?: 'No description') ?>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <div style="font-size:.72rem;color:var(--text-muted)">Instructor</div>
                <div style="font-size:.82rem;font-weight:500"><?= sanitize($c['instructor'] ?: 'N/A') ?></div>
            </div>
            <div class="col-3">
                <div style="font-size:.72rem;color:var(--text-muted)">Duration</div>
                <div style="font-size:.82rem;font-weight:500"><?= sanitize($c['duration'] ?: 'N/A') ?></div>
            </div>
            <div class="col-3">
                <div style="font-size:.72rem;color:var(--text-muted)">Students</div>
                <div style="font-size:.9rem;font-weight:700;color:var(--accent)"><?= $c['student_count'] ?></div>
            </div>
        </div>

        <?php if ($c['fee'] > 0): ?>
        <div style="font-size:.8rem;margin-bottom:.75rem;color:var(--text-muted)">
            Fee: <strong style="color:var(--text)">PKR <?= number_format($c['fee']) ?></strong>
        </div>
        <?php endif; ?>

        <div class="d-flex gap-2">
            <button class="btn-icon btn-icon-edit flex-grow-1" style="width:auto;padding:.4rem .8rem;font-size:.8rem;border-radius:7px"
                onclick="editCourse(<?= htmlspecialchars(json_encode($c)) ?>)">
                <i class="bi bi-pencil me-1"></i>Edit
            </button>
            <form method="POST" onsubmit="return confirmDelete(this)">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button type="submit" class="btn-icon btn-icon-delete"><i class="bi bi-trash"></i></button>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php if (!$courses): ?>
<div class="col-12">
    <div class="data-card" style="text-align:center;padding:3rem;color:var(--text-muted)">
        <i class="bi bi-book" style="font-size:2.5rem;display:block;margin-bottom:.75rem"></i>
        No courses yet. Add your first course!
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
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Course</h5>
                    <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-8">
                        <label class="form-label">Course Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Web Development">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Code *</label>
                        <input type="text" name="code" class="form-control" required placeholder="e.g. WD" maxlength="10">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Duration</label>
                        <input type="text" name="duration" class="form-control" placeholder="e.g. 6 Months">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Instructor</label>
                        <input type="text" name="instructor" class="form-control" placeholder="Instructor name">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Fee (PKR)</label>
                        <input type="number" name="fee" class="form-control" placeholder="0" min="0">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Course description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-academy"><i class="bi bi-check-lg"></i> Add</button>
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
                <input type="hidden" name="id" id="ec_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Course</h5>
                    <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-8">
                        <label class="form-label">Course Name *</label>
                        <input type="text" name="name" id="ec_name" class="form-control" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label">Code *</label>
                        <input type="text" name="code" id="ec_code" class="form-control" required maxlength="10">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Duration</label>
                        <input type="text" name="duration" id="ec_duration" class="form-control">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Instructor</label>
                        <input type="text" name="instructor" id="ec_instructor" class="form-control">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Fee (PKR)</label>
                        <input type="number" name="fee" id="ec_fee" class="form-control" min="0">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Status</label>
                        <select name="status" id="ec_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="ec_desc" class="form-control" rows="3"></textarea>
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
function editCourse(c) {
    document.getElementById('ec_id').value = c.id;
    document.getElementById('ec_name').value = c.name;
    document.getElementById('ec_code').value = c.code;
    document.getElementById('ec_duration').value = c.duration || '';
    document.getElementById('ec_instructor').value = c.instructor || '';
    document.getElementById('ec_fee').value = c.fee || 0;
    document.getElementById('ec_status').value = c.status;
    document.getElementById('ec_desc').value = c.description || '';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
