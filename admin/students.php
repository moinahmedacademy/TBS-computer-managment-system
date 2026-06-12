<?php
$pageTitle = 'Students';
require_once __DIR__ . '/layout.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name     = sanitize($_POST['name'] ?? '');
        $fatherName = sanitize($_POST['father_name'] ?? '');
        $phone    = sanitize($_POST['phone'] ?? '');
        $email    = sanitize($_POST['email'] ?? '');
        $cnic     = sanitize($_POST['cnic'] ?? '');
        $dob      = $_POST['dob'] ?? null;
        $gender   = $_POST['gender'] ?? 'male';
        $address  = sanitize($_POST['address'] ?? '');
        $courseId = (int)($_POST['course_id'] ?? 0);
        $batch    = sanitize($_POST['batch'] ?? '');
        $enrollDate = $_POST['enrollment_date'] ?? date('Y-m-d');
        $password = $_POST['password'] ?? '';

        if (!$name || !$courseId) {
            flashMessage('danger', 'Name and course are required.');
            header('Location: students.php');
            exit;
        }

        if ($action === 'add') {
            // Create user account
            $userEmail = $email ?: strtolower(str_replace(' ', '.', $name)) . rand(100,999) . '@student.local';
            $existing = db()->fetchOne("SELECT id FROM users WHERE email=?", [$userEmail]);
            if ($existing) {
                $userEmail = 'student.' . time() . '@student.local';
            }
            $hashedPw = password_hash($password ?: 'Student@123', PASSWORD_DEFAULT);

            $userId = db()->insert(
                "INSERT INTO users (name,email,password,role,phone) VALUES (?,?,?,'student',?)",
                [$name, $userEmail, $hashedPw, $phone]
            );

            $courseRow = db()->fetchOne("SELECT code FROM courses WHERE id=?", [$courseId]);
            $rollNo = generateRollNumber($courseRow['code'] ?? 'STU');

            db()->execute(
                "INSERT INTO students (user_id,roll_number,name,father_name,phone,email,cnic,dob,gender,address,course_id,batch,enrollment_date)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$userId, $rollNo, $name, $fatherName, $phone, $email, $cnic, $dob ?: null, $gender, $address, $courseId, $batch, $enrollDate]
            );
            flashMessage('success', "Student $name added. Roll: $rollNo | Login: $userEmail | Pass: " . ($password ?: 'Student@123'));
        } else {
            $id = (int)($_POST['id'] ?? 0);
            db()->execute(
                "UPDATE students SET name=?,father_name=?,phone=?,email=?,cnic=?,dob=?,gender=?,address=?,course_id=?,batch=?,enrollment_date=? WHERE id=?",
                [$name, $fatherName, $phone, $email, $cnic, $dob ?: null, $gender, $address, $courseId, $batch, $enrollDate, $id]
            );
            // Update user name/phone
            $st = db()->fetchOne("SELECT user_id FROM students WHERE id=?", [$id]);
            if ($st) db()->execute("UPDATE users SET name=?,phone=? WHERE id=?", [$name, $phone, $st['user_id']]);
            flashMessage('success', "Student updated successfully.");
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $st = db()->fetchOne("SELECT user_id FROM students WHERE id=?", [$id]);
        if ($st) db()->execute("DELETE FROM users WHERE id=?", [$st['user_id']]);
        db()->execute("DELETE FROM students WHERE id=?", [$id]);
        flashMessage('success', "Student deleted.");
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $curr = db()->fetchOne("SELECT status FROM students WHERE id=?", [$id]);
        $new = $curr['status'] === 'active' ? 'inactive' : 'active';
        db()->execute("UPDATE students SET status=? WHERE id=?", [$new, $id]);
        flashMessage('success', "Status updated.");
    }
    header('Location: students.php');
    exit;
}

$filter_course = (int)($_GET['course'] ?? 0);
$filter_status = $_GET['status'] ?? 'active';
$search = sanitize($_GET['q'] ?? '');

$sql = "SELECT s.*, c.name as course_name FROM students s LEFT JOIN courses c ON s.course_id=c.id WHERE 1=1";
$params = [];
if ($filter_course) { $sql .= " AND s.course_id=?"; $params[] = $filter_course; }
if ($filter_status) { $sql .= " AND s.status=?"; $params[] = $filter_status; }
if ($search)        { $sql .= " AND (s.name LIKE ? OR s.roll_number LIKE ? OR s.phone LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= " ORDER BY s.created_at DESC";

$students = db()->fetchAll($sql, $params);
$courses  = db()->fetchAll("SELECT id, name FROM courses WHERE status='active' ORDER BY name");
?>

<div class="section-header">
    <div>
        <div class="section-title">Students Management</div>
        <div class="section-subtitle"><?= count($students) ?> student(s) found</div>
    </div>
    <button class="btn-primary-academy" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg"></i> Add Student
    </button>
</div>

<!-- Filters -->
<div class="data-card mb-3">
    <div style="padding:.85rem 1rem">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            <div class="search-wrap">
                <i class="bi bi-search"></i>
                <input type="text" name="q" class="search-input" placeholder="Search students..." value="<?= sanitize($search) ?>">
            </div>
            <select name="course" class="form-select" style="width:180px">
                <option value="">All Courses</option>
                <?php foreach ($courses as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $filter_course==$c['id']?'selected':'' ?>><?= sanitize($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-select" style="width:140px">
                <option value="" <?= $filter_status===''?'selected':'' ?>>All Status</option>
                <option value="active" <?= $filter_status==='active'?'selected':'' ?>>Active</option>
                <option value="inactive" <?= $filter_status==='inactive'?'selected':'' ?>>Inactive</option>
                <option value="completed" <?= $filter_status==='completed'?'selected':'' ?>>Completed</option>
            </select>
            <button type="submit" class="btn-primary-academy"><i class="bi bi-funnel"></i> Filter</button>
            <a href="students.php" class="btn-primary-academy" style="background:var(--surface3);color:var(--text)">Reset</a>
        </form>
    </div>
</div>

<!-- Students Table -->
<div class="data-card">
    <div class="table-wrap">
        <table class="table-academy" id="studentsTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Roll No</th>
                    <th>Course</th>
                    <th>Phone</th>
                    <th>Batch</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($students): foreach ($students as $i => $s): ?>
                <tr>
                    <td style="color:var(--text-muted);font-size:.82rem"><?= $i+1 ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-circle"><?= strtoupper(substr($s['name'],0,1)) ?></div>
                            <div>
                                <div style="font-weight:500"><?= sanitize($s['name']) ?></div>
                                <div style="font-size:.75rem;color:var(--text-muted)"><?= sanitize($s['father_name'] ?? '') ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge-academy badge-info"><?= sanitize($s['roll_number']) ?></span></td>
                    <td><?= sanitize($s['course_name'] ?? 'N/A') ?></td>
                    <td style="font-size:.83rem"><?= sanitize($s['phone'] ?? '') ?></td>
                    <td style="font-size:.83rem"><?= sanitize($s['batch'] ?? '') ?></td>
                    <td>
                        <span class="badge-academy <?= $s['status']==='active'?'badge-success':($s['status']==='completed'?'badge-info':'badge-danger') ?>">
                            <?= ucfirst($s['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn-icon btn-icon-edit" title="Edit"
                                onclick="editStudent(<?= htmlspecialchars(json_encode($s)) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <a href="<?= BASE_URL ?>/admin/attendance.php?student=<?= $s['id'] ?>" class="btn-icon btn-icon-view" title="Attendance">
                                <i class="bi bi-calendar-check"></i>
                            </a>
                            <a href="<?= BASE_URL ?>/admin/results.php?student=<?= $s['id'] ?>" class="btn-icon btn-icon-view" title="Results" style="background:rgba(139,92,246,.15);color:#8b5cf6">
                                <i class="bi bi-bar-chart"></i>
                            </a>
                            <form method="POST" style="display:inline" onsubmit="return confirmDelete(this)">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn-icon btn-icon-delete" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:3rem">
                    <i class="bi bi-people" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
                    No students found
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New Student</h5>
                    <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="Student full name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Father's Name</label>
                            <input type="text" name="father_name" class="form-control" placeholder="Father's name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" placeholder="03XX-XXXXXXX">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="student@email.com">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">CNIC</label>
                            <input type="text" name="cnic" class="form-control" placeholder="XXXXX-XXXXXXX-X">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="dob" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Course *</label>
                            <select name="course_id" class="form-select" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Batch</label>
                            <input type="text" name="batch" class="form-control" placeholder="e.g. Batch 2024-A">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Enrollment Date</label>
                            <input type="date" name="enrollment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Portal Password <small style="color:var(--text-muted)">(default: Student@123)</small></label>
                            <input type="password" name="password" class="form-control" placeholder="Leave empty for default">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Student address"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-academy"><i class="bi bi-check-lg"></i> Add Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Student</h5>
                    <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Father's Name</label>
                            <input type="text" name="father_name" id="edit_father_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">CNIC</label>
                            <input type="text" name="cnic" id="edit_cnic" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="dob" id="edit_dob" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Gender</label>
                            <select name="gender" id="edit_gender" class="form-select">
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Course *</label>
                            <select name="course_id" id="edit_course_id" class="form-select" required>
                                <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Batch</label>
                            <input type="text" name="batch" id="edit_batch" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Enrollment Date</label>
                            <input type="date" name="enrollment_date" id="edit_enrollment_date" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-academy"><i class="bi bi-check-lg"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editStudent(s) {
    document.getElementById('edit_id').value = s.id;
    document.getElementById('edit_name').value = s.name;
    document.getElementById('edit_father_name').value = s.father_name || '';
    document.getElementById('edit_phone').value = s.phone || '';
    document.getElementById('edit_email').value = s.email || '';
    document.getElementById('edit_cnic').value = s.cnic || '';
    document.getElementById('edit_dob').value = s.dob || '';
    document.getElementById('edit_gender').value = s.gender || 'male';
    document.getElementById('edit_course_id').value = s.course_id || '';
    document.getElementById('edit_batch').value = s.batch || '';
    document.getElementById('edit_enrollment_date').value = s.enrollment_date || '';
    document.getElementById('edit_address').value = s.address || '';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
