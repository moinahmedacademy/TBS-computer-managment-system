<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update') {
        $phone    = sanitize($_POST['phone'] ?? '');
        $whatsapp = sanitize($_POST['whatsapp'] ?? '');
        $address  = sanitize($_POST['address'] ?? '');
        db()->execute("UPDATE parents SET phone=?,whatsapp=?,address=? WHERE id=?", [$phone,$whatsapp,$address,$parentId]);
        db()->execute("UPDATE users SET phone=? WHERE id=?", [$phone,$_SESSION['user_id']]);
        flashMessage('success', 'Profile updated.');
        header('Location: profile.php'); exit;
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $user    = db()->fetchOne("SELECT password FROM users WHERE id=?", [$_SESSION['user_id']]);
        if (!password_verify($current, $user['password'])) {
            flashMessage('danger', 'Current password incorrect.');
        } elseif ($new !== $confirm || strlen($new) < 6) {
            flashMessage('danger', 'Password mismatch or too short.');
        } else {
            db()->execute("UPDATE users SET password=? WHERE id=?", [password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            flashMessage('success', 'Password changed.');
        }
        header('Location: profile.php'); exit;
    }
}
?>

<div class="section-title mb-3">My Profile</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="data-card" style="padding:1.5rem;text-align:center">
            <div class="avatar-circle mx-auto mb-2" style="width:60px;height:60px;font-size:1.5rem;background:linear-gradient(135deg,#10b981,#059669)">
                <?= strtoupper(substr($parent['name']??'P',0,1)) ?>
            </div>
            <div style="font-weight:700"><?= sanitize($parent['name'] ?? '') ?></div>
            <div style="font-size:.82rem;color:#10b981"><?= ucfirst($parent['relation'] ?? '') ?></div>
            <div style="font-size:.8rem;color:var(--text-muted);margin-top:.5rem">of <?= sanitize($student['name'] ?? '') ?></div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="form-section mb-3">
            <div class="form-section-title"><i class="bi bi-pencil"></i> Update Contact</div>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= sanitize($parent['phone']??'') ?>"></div>
                    <div class="col-md-6"><label class="form-label">WhatsApp</label><input type="text" name="whatsapp" class="form-control" value="<?= sanitize($parent['whatsapp']??'') ?>"></div>
                    <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?= sanitize($parent['address']??'') ?></textarea></div>
                </div>
                <div class="mt-3"><button type="submit" class="btn-primary-academy">Update</button></div>
            </form>
        </div>
        <div class="form-section">
            <div class="form-section-title"><i class="bi bi-key"></i> Change Password</div>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Confirm</label><input type="password" name="confirm_password" class="form-control" required></div>
                </div>
                <div class="mt-3"><button type="submit" class="btn-primary-academy">Change Password</button></div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
