<?php
$pageTitle = 'Students';
require_once __DIR__ . '/layout.php';

$CLASS_TIMINGS = [
    '08:00-09:00' => '8:00 AM – 9:00 AM',
    '09:00-10:00' => '9:00 AM – 10:00 AM',
    '10:00-11:00' => '10:00 AM – 11:00 AM',
    '11:00-12:00' => '11:00 AM – 12:00 PM',
    '14:00-15:00' => '2:00 PM – 3:00 PM',
    '16:00-17:00' => '4:00 PM – 5:00 PM',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name        = sanitize($_POST['name'] ?? '');
        $fatherName  = sanitize($_POST['father_name'] ?? '');
        $phone       = sanitize($_POST['phone'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $cnic        = sanitize($_POST['cnic'] ?? '');
        $dob         = $_POST['dob'] ?? null;
        $gender      = $_POST['gender'] ?? 'male';
        $address     = sanitize($_POST['address'] ?? '');
        $courseId    = (int)($_POST['course_id'] ?? 0);
        $batch       = sanitize($_POST['batch'] ?? '');
        $timing      = sanitize($_POST['timing'] ?? '');
        $enrollDate  = $_POST['enrollment_date'] ?? date('Y-m-d');
        $password    = $_POST['password'] ?? '';

        if (!$name || !$courseId) {
            flashMessage('danger', 'Name and course are required.');
            header('Location: students.php'); exit;
        }

        // Handle photo upload
        $photoPath = null;
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(__DIR__) . '/assets/uploads/students/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp'];
            // Verify actual file content, not just extension
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $realMime = $finfo->file($_FILES['photo']['tmp_name']);
            $imgMimes = ['image/jpeg','image/png','image/webp'];
            if (in_array($ext, $allowed) && in_array($realMime, $imgMimes) && $_FILES['photo']['size'] < 2 * 1024 * 1024) {
                $fname = 'stu_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $fname)) {
                    $photoPath = 'assets/uploads/students/' . $fname;
                }
            }
        }

        if ($action === 'add') {
            // Auto-generate email from roll number pattern
            $courseRow = db()->fetchOne("SELECT code FROM courses WHERE id=?", [$courseId]);
            $rollNo    = generateRollNumber($courseRow['code'] ?? 'STU');

            // Generate login email: rollno@brightenstars.edu.pk (overridden if user provides one)
            $loginEmail = $email ?: (strtolower($rollNo) . '@brightenstars.edu.pk');
            $existing   = db()->fetchOne("SELECT id FROM users WHERE email=?", [$loginEmail]);
            if ($existing) $loginEmail = strtolower($rollNo) . '.' . time() . '@brightenstars.edu.pk';

            $hashedPw = password_hash($password ?: 'Student@123', PASSWORD_DEFAULT);
            $userId   = db()->insert(
                "INSERT INTO users (name,email,password,role,phone) VALUES (?,?,?,'student',?)",
                [$name, $loginEmail, $hashedPw, $phone]
            );

            db()->execute(
                "INSERT INTO students (user_id,roll_number,name,father_name,phone,email,cnic,dob,gender,address,course_id,batch,timing,enrollment_date,photo)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$userId,$rollNo,$name,$fatherName,$phone,$email,$cnic,$dob?:null,$gender,$address,$courseId,$batch,$timing,$enrollDate,$photoPath]
            );
            // Do NOT show the password on screen — send it via WhatsApp or share privately
            flashMessage('success', "✅ Student <strong>" . sanitize($name) . "</strong> added. Roll: <strong>" . sanitize($rollNo) . "</strong> | Login email: <strong>" . sanitize($loginEmail) . "</strong>. Please share login credentials privately.");

        } else {
            $id = (int)($_POST['id'] ?? 0);
            $st = db()->fetchOne("SELECT user_id, photo FROM students WHERE id=?", [$id]);
            if (!$st) { flashMessage('danger','Student not found.'); header('Location: students.php'); exit; }

            $setParams = [$name,$fatherName,$phone,$email,$cnic,$dob?:null,$gender,$address,$courseId,$batch,$timing,$enrollDate,$id];
            if ($photoPath) {
                db()->execute(
                    "UPDATE students SET name=?,father_name=?,phone=?,email=?,cnic=?,dob=?,gender=?,address=?,course_id=?,batch=?,timing=?,enrollment_date=?,photo=? WHERE id=?",
                    array_merge(array_slice($setParams,0,12), [$photoPath, $id])
                );
            } else {
                db()->execute(
                    "UPDATE students SET name=?,father_name=?,phone=?,email=?,cnic=?,dob=?,gender=?,address=?,course_id=?,batch=?,timing=?,enrollment_date=? WHERE id=?",
                    $setParams
                );
            }

            if ($st['user_id']) db()->execute("UPDATE users SET name=?,phone=? WHERE id=?", [$name,$phone,$st['user_id']]);
            flashMessage('success', "Student updated successfully.");
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $st = db()->fetchOne("SELECT user_id FROM students WHERE id=?", [$id]);
        if ($st && $st['user_id']) db()->execute("DELETE FROM users WHERE id=?", [$st['user_id']]);
        db()->execute("DELETE FROM students WHERE id=?", [$id]);
        flashMessage('success', "Student deleted.");

    } elseif ($action === 'toggle') {
        $id  = (int)($_POST['id'] ?? 0);
        $cur = db()->fetchOne("SELECT status FROM students WHERE id=?", [$id]);
        $new = $cur['status'] === 'active' ? 'inactive' : 'active';
        db()->execute("UPDATE students SET status=? WHERE id=?", [$new, $id]);
        flashMessage('success', "Status changed to $new.");

    } elseif ($action === 'complete_enroll') {
        // Mark current course complete, assign new course
        $id        = (int)($_POST['id'] ?? 0);
        $newCourse = (int)($_POST['new_course_id'] ?? 0);
        if ($id && $newCourse) {
            db()->execute("UPDATE students SET status='completed', completion_date=CURDATE() WHERE id=? AND status='active'", [$id]);
            // Clone student into new course
            $stu = db()->fetchOne("SELECT * FROM students WHERE id=?", [$id]);
            if ($stu) {
                $courseRow = db()->fetchOne("SELECT code FROM courses WHERE id=?", [$newCourse]);
                $newRoll   = generateRollNumber($courseRow['code'] ?? 'STU');
                db()->execute(
                    "INSERT INTO students (user_id,roll_number,name,father_name,phone,email,cnic,dob,gender,address,course_id,batch,timing,enrollment_date,photo)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,CURDATE(),?)",
                    [$stu['user_id'],$newRoll,$stu['name'],$stu['father_name'] ?? null,$stu['phone'] ?? null,$stu['email'] ?? null,
                     $stu['cnic'] ?? null,$stu['dob'] ?? null,$stu['gender'] ?? 'male',$stu['address'] ?? null,
                     $newCourse,$stu['batch'] ?? null,$stu['timing'] ?? null,$stu['photo'] ?? null]
                );
                flashMessage('success', "Course completed. Student re-enrolled in new course. New Roll: <strong>$newRoll</strong>");
            }
        }
    }
    header('Location: students.php'); exit;
}

// Show ALL students by default (no status filter)
$filter_course = (int)($_GET['course'] ?? 0);
$filter_status = $_GET['status'] ?? '';          // empty = all
$search        = sanitize($_GET['q'] ?? '');

$sql    = "SELECT s.*, c.name as course_name FROM students s LEFT JOIN courses c ON s.course_id=c.id WHERE 1=1";
$params = [];
if ($filter_course) { $sql .= " AND s.course_id=?"; $params[] = $filter_course; }
if ($filter_status) { $sql .= " AND s.status=?";    $params[] = $filter_status; }
if ($search)        { $sql .= " AND (s.name LIKE ? OR s.roll_number LIKE ? OR s.phone LIKE ?)";
                      $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
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
        <i class="bi bi-person-plus"></i> Add Student
    </button>
</div>

<!-- Filters -->
<div class="data-card mb-3">
    <div style="padding:.85rem 1rem">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            <div class="search-wrap">
                <i class="bi bi-search"></i>
                <input type="text" name="q" class="search-input" placeholder="Search by name / roll / phone…" value="<?= sanitize($search) ?>">
            </div>
            <select name="course" class="form-select" style="width:200px">
                <option value="">All Courses</option>
                <?php foreach ($courses as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $filter_course==$c['id']?'selected':'' ?>><?= sanitize($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-select" style="width:150px">
                <option value="" <?= $filter_status===''?'selected':'' ?>>All Status</option>
                <option value="active"    <?= $filter_status==='active'    ?'selected':'' ?>>Active</option>
                <option value="inactive"  <?= $filter_status==='inactive'  ?'selected':'' ?>>Inactive</option>
                <option value="completed" <?= $filter_status==='completed' ?'selected':'' ?>>Completed</option>
            </select>
            <button type="submit" class="btn-primary-academy"><i class="bi bi-funnel"></i> Filter</button>
            <a href="students.php" class="btn-primary-academy" style="background:var(--surface3);color:var(--text)">
                <i class="bi bi-x-circle"></i> Reset
            </a>
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
                    <th>Timing</th>
                    <th>Phone</th>
                    <th>Batch</th>
                    <th>Status</th>
                    <th style="min-width:200px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($students): foreach ($students as $i => $s): ?>
                <tr>
                    <td style="color:var(--text-muted);font-size:.82rem"><?= $i+1 ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if (!empty($s['photo']) && file_exists(dirname(__DIR__) . '/' . $s['photo'])): ?>
                                <img src="<?= BASE_URL ?>/<?= sanitize($s['photo']) ?>" alt="" class="stu-photo">
                            <?php else: ?>
                                <div class="avatar-circle"><?= strtoupper(substr($s['name'],0,1)) ?></div>
                            <?php endif; ?>
                            <div>
                                <div style="font-weight:500"><?= sanitize($s['name']) ?></div>
                                <div style="font-size:.75rem;color:var(--text-muted)"><?= sanitize($s['father_name'] ?? '') ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge-academy badge-info"><?= sanitize($s['roll_number']) ?></span></td>
                    <td style="font-size:.83rem"><?= sanitize($s['course_name'] ?? 'N/A') ?></td>
                    <td style="font-size:.78rem;color:var(--text-muted)"><?= sanitize($s['timing'] ?? '—') ?></td>
                    <td style="font-size:.83rem"><?= sanitize($s['phone'] ?? '') ?></td>
                    <td style="font-size:.83rem"><?= sanitize($s['batch'] ?? '') ?></td>
                    <td>
                        <span class="badge-academy <?= $s['status']==='active'?'badge-success':($s['status']==='completed'?'badge-info':'badge-danger') ?>">
                            <?= ucfirst($s['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <!-- Edit -->
                            <button class="btn-icon btn-icon-edit" title="Edit"
                                onclick="editStudent(<?= htmlspecialchars(json_encode($s)) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <!-- Attendance -->
                            <a href="<?= BASE_URL ?>/admin/attendance.php?course_id=<?= $s['course_id'] ?>" class="btn-icon btn-icon-view" title="Attendance">
                                <i class="bi bi-calendar-check"></i>
                            </a>
                            <!-- Results -->
                            <a href="<?= BASE_URL ?>/admin/results.php?student=<?= $s['id'] ?>" class="btn-icon" title="Results"
                               style="background:rgba(139,92,246,.15);color:#8b5cf6;border:none;border-radius:7px;width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer">
                                <i class="bi bi-bar-chart"></i>
                            </a>
                            <!-- New course enrollment -->
                            <?php if ($s['status'] === 'active'): ?>
                            <button class="btn-icon" title="Enroll in new course"
                                style="background:rgba(245,158,11,.15);color:#f59e0b;border:none;border-radius:7px;width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer"
                                onclick="showEnrollModal(<?= $s['id'] ?>, '<?= sanitize($s['name']) ?>')">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                            <?php endif; ?>
                            <!-- WhatsApp -->
                            <?php
                            $parentRow = db()->fetchOne("SELECT whatsapp, phone FROM parents WHERE student_id=?", [$s['id']]);
                            $waNum     = $parentRow ? ($parentRow['whatsapp'] ?: $parentRow['phone']) : '';
                            ?>
                            <?php if ($waNum): ?>
                            <button class="btn-icon btn-icon-wa" title="WhatsApp Parent"
                                onclick="openWhatsApp('<?= sanitize($waNum) ?>', 'Dear Parent, regarding your child <?= sanitize($s['name']) ?> (Roll: <?= sanitize($s['roll_number']) ?>)')">
                                <i class="bi bi-whatsapp"></i>
                            </button>
                            <?php endif; ?>
                            <!-- Delete -->
                            <?php $delMsg = "Student " . ($s['name'] ?? '') . " will be permanently deleted along with all records."; ?>
                            <form method="POST" style="display:inline"
                                  onsubmit="return confirmDelete(this, <?= json_encode($delMsg) ?>)">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <?= csrfField() ?>
                                <button type="submit" class="btn-icon btn-icon-delete" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:3rem">
                    <i class="bi bi-people" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
                    No students found
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── ADD MODAL ─────────────────────────────────────────────────────────── -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <?= csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New Student</h5>
                    <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Photo -->
                        <div class="col-12">
                            <label class="form-label">Student Photo</label>
                            <input type="file" name="photo" class="form-control" accept="image/*">
                            <small style="color:var(--text-muted)">Max 2MB. JPG/PNG/WEBP</small>
                        </div>
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
                            <label class="form-label">Email <small style="color:var(--text-muted)">(auto-generated if empty)</small></label>
                            <input type="email" name="email" class="form-control" placeholder="Will be auto-generated from roll no.">
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
                        <div class="col-md-6">
                            <label class="form-label">Class Timing *</label>
                            <select name="timing" class="form-select" required>
                                <option value="">Select Timing</option>
                                <?php foreach ($CLASS_TIMINGS as $val => $label): ?>
                                <option value="<?= $val ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Batch</label>
                            <input type="text" name="batch" class="form-control" placeholder="e.g. Batch 2024-A">
                        </div>
                        <div class="col-md-6">
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
                    <button type="submit" class="btn-primary-academy"
                        onclick="return confirmSave(this.closest('form'), 'Add this student and create their login account?')">
                        <i class="bi bi-check-lg"></i> Add Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── EDIT MODAL ─────────────────────────────────────────────────────────── -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <?= csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Student</h5>
                    <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Update Photo</label>
                            <input type="file" name="photo" class="form-control" accept="image/*">
                            <small style="color:var(--text-muted)">Leave empty to keep existing photo</small>
                        </div>
                        <div class="col-md-6"><label class="form-label">Full Name *</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Father's Name</label><input type="text" name="father_name" id="edit_father_name" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" id="edit_phone" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" id="edit_email" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">CNIC</label><input type="text" name="cnic" id="edit_cnic" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Date of Birth</label><input type="date" name="dob" id="edit_dob" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Gender</label>
                            <select name="gender" id="edit_gender" class="form-select">
                                <option value="male">Male</option><option value="female">Female</option><option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Course *</label>
                            <select name="course_id" id="edit_course_id" class="form-select" required>
                                <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Class Timing</label>
                            <select name="timing" id="edit_timing" class="form-select">
                                <option value="">No Timing Set</option>
                                <?php foreach ($CLASS_TIMINGS as $val => $label): ?>
                                <option value="<?= $val ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Batch</label><input type="text" name="batch" id="edit_batch" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Enrollment Date</label><input type="date" name="enrollment_date" id="edit_enrollment_date" class="form-control"></div>
                        <div class="col-12"><label class="form-label">Address</label><textarea name="address" id="edit_address" class="form-control" rows="2"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-academy"
                        onclick="return confirmSave(this.closest('form'), 'Save changes to this student\'s profile?')">
                        <i class="bi bi-check-lg"></i> Update Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── NEW COURSE ENROLLMENT MODAL ──────────────────────────────────────── -->
<div class="modal fade" id="enrollModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="complete_enroll">
                <input type="hidden" name="id" id="enroll_id">
                <?= csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-repeat me-2"></i>New Course Enrollment</h5>
                    <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div style="background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);border-radius:10px;padding:.85rem 1rem;margin-bottom:1rem;font-size:.85rem;color:#f59e0b">
                        <i class="bi bi-info-circle me-1"></i>
                        Current course will be marked <strong>Completed</strong>. Student will be re-enrolled in the new course with a new roll number.
                    </div>
                    <p style="color:var(--text-muted);font-size:.85rem">Student: <strong id="enroll_name" style="color:var(--text)"></strong></p>
                    <label class="form-label">Select New Course *</label>
                    <select name="new_course_id" class="form-select" required>
                        <option value="">Choose Course</option>
                        <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-academy"
                        onclick="return confirmSave(this.closest('form'),'Complete current course and enroll student in new one?')">
                        <i class="bi bi-check-lg"></i> Enroll in New Course
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.stu-photo { width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid var(--border); }
</style>
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
    document.getElementById('edit_timing').value = s.timing || '';
    document.getElementById('edit_batch').value = s.batch || '';
    document.getElementById('edit_enrollment_date').value = s.enrollment_date || '';
    document.getElementById('edit_address').value = s.address || '';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
function showEnrollModal(id, name) {
    document.getElementById('enroll_id').value = id;
    document.getElementById('enroll_name').textContent = name;
    new bootstrap.Modal(document.getElementById('enrollModal')).show();
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
