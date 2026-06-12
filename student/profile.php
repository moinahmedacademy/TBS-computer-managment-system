<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/layout.php';

$studentId = $_SESSION['student_id'] ?? 0;
$student   = db()->fetchOne(
    "SELECT s.*, c.name as course_name, c.instructor, u.email as login_email
     FROM students s LEFT JOIN courses c ON s.course_id=c.id LEFT JOIN users u ON s.user_id=u.id
     WHERE s.id=?", [$studentId]
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $phone   = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        db()->execute("UPDATE students SET phone=?,address=? WHERE id=?", [$phone,$address,$studentId]);
        db()->execute("UPDATE users SET phone=? WHERE id=?", [$phone, $student['user_id']]);
        flashMessage('success', 'Profile updated.');
        header('Location: profile.php'); exit;
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $user    = db()->fetchOne("SELECT password FROM users WHERE id=?", [$student['user_id']]);
        if (!password_verify($current, $user['password'])) {
            flashMessage('danger', 'Current password is incorrect.');
        } elseif ($new !== $confirm) {
            flashMessage('danger', 'New passwords do not match.');
        } elseif (strlen($new) < 6) {
            flashMessage('danger', 'Password must be at least 6 characters.');
        } else {
            db()->execute("UPDATE users SET password=? WHERE id=?", [password_hash($new, PASSWORD_DEFAULT), $student['user_id']]);
            flashMessage('success', 'Password changed.');
        }
        header('Location: profile.php'); exit;
    }
}
?>

<div class="section-title mb-3">My Profile</div>

<div class="row g-3">
    <div class="col-12 col-md-4">
        <div class="data-card" style="padding:1.5rem;text-align:center">
            <div class="avatar-circle mx-auto mb-2" style="width:70px;height:70px;font-size:1.8rem">
                <?= strtoupper(substr($student['name'],0,1)) ?>
            </div>
            <div style="font-size:1rem;font-weight:700"><?= sanitize($student['name']) ?></div>
            <div style="font-size:.82rem;color:var(--accent);font-weight:600;margin-top:.15rem"><?= sanitize($student['roll_number']) ?></div>
            <div style="font-size:.8rem;color:var(--text-muted);margin-top:.25rem"><?= sanitize($student['course_name'] ?? '') ?></div>
            <div class="mt-3" style="background:var(--surface2);border-radius:8px;padding:.75rem">
                <?php foreach ([
                    ['bi-envelope','Login Email',$student['login_email']],
                    ['bi-person-fill','Father','father_name'],
                    ['bi-phone','Phone','phone'],
                    ['bi-calendar3','DOB','dob'],
                    ['bi-geo-alt','Batch','batch'],
                ] as [$ico,$label,$key]):
                    $val = isset($student[$key]) ? $student[$key] : $key;
                    $display = isset($student[$key]) ? sanitize($student[$key] ?: 'N/A') : sanitize($val ?: 'N/A');
                    if ($key === 'dob' && $val) $display = formatDate($val);
                ?>
                <div style="display:flex;align-items:center;gap:.5rem;padding:.35rem 0;border-bottom:1px solid var(--border);text-align:left">
                    <i class="bi <?= $ico ?>" style="color:var(--accent);font-size:.85rem;width:16px"></i>
                    <div>
                        <div style="font-size:.68rem;color:var(--text-muted)"><?= $label ?></div>
                        <div style="font-size:.82rem"><?= $display ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-8">
        <div class="form-section mb-3">
            <div class="form-section-title"><i class="bi bi-pencil"></i> Update Contact Info</div>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" value="<?= sanitize($student['phone'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="3"><?= sanitize($student['address'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn-primary-academy"><i class="bi bi-check-lg"></i> Update</button>
                </div>
            </form>
        </div>

        <div class="form-section">
            <div class="form-section-title"><i class="bi bi-key"></i> Change Password</div>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
                    <div class="col-md-6"><label class="form-label">Confirm Password</label><input type="password" name="confirm_password" class="form-control" required minlength="6"></div>
                </div>
                <div class="mt-3"><button type="submit" class="btn-primary-academy">Change Password</button></div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
