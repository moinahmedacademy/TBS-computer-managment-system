<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_general') {
        $keys = ['institute_name','institute_address','institute_phone','institute_email','session_year'];
        foreach ($keys as $key) {
            $val = sanitize($_POST[$key] ?? '');
            db()->execute("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?", [$key,$val,$val]);
        }
        flashMessage('success', 'General settings saved.');
    } elseif ($action === 'save_whatsapp') {
        $keys = ['whatsapp_api_key','whatsapp_instance_id'];
        foreach ($keys as $key) {
            $val = sanitize($_POST[$key] ?? '');
            db()->execute("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?", [$key,$val,$val]);
        }
        flashMessage('success', 'WhatsApp settings saved.');
    } elseif ($action === 'add_ip') {
        $ip    = sanitize($_POST['ip_address'] ?? '');
        $label = sanitize($_POST['label'] ?? '');
        if ($ip) {
            db()->execute("INSERT IGNORE INTO allowed_ips (ip_address,label) VALUES (?,?)", [$ip,$label]);
            flashMessage('success', "IP '$ip' added to allowed list.");
        }
    } elseif ($action === 'delete_ip') {
        db()->execute("DELETE FROM allowed_ips WHERE id=?", [(int)$_POST['id']]);
        flashMessage('success', 'IP removed.');
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $user = db()->fetchOne("SELECT password FROM users WHERE id=?", [$_SESSION['user_id']]);
        if (!password_verify($current, $user['password'])) {
            flashMessage('danger', 'Current password is incorrect.');
        } elseif ($new !== $confirm) {
            flashMessage('danger', 'New passwords do not match.');
        } elseif (strlen($new) < 6) {
            flashMessage('danger', 'Password must be at least 6 characters.');
        } else {
            db()->execute("UPDATE users SET password=? WHERE id=?", [password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            flashMessage('success', 'Password changed successfully.');
        }
    }
    header('Location: settings.php'); exit;
}

$allowedIPs = db()->fetchAll("SELECT * FROM allowed_ips ORDER BY created_at DESC");
$clientIP   = $_SERVER['REMOTE_ADDR'] ?? '';
?>

<div class="section-title mb-4">System Settings</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <!-- General Settings -->
        <div class="form-section mb-3">
            <div class="form-section-title"><i class="bi bi-gear"></i> General Settings</div>
            <form method="POST">
                <input type="hidden" name="action" value="save_general">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Institute Name</label>
                        <input type="text" name="institute_name" class="form-control" value="<?= sanitize(getSetting('institute_name')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" name="institute_phone" class="form-control" value="<?= sanitize(getSetting('institute_phone')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="institute_email" class="form-control" value="<?= sanitize(getSetting('institute_email')) ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Address</label>
                        <input type="text" name="institute_address" class="form-control" value="<?= sanitize(getSetting('institute_address')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Session Year</label>
                        <input type="text" name="session_year" class="form-control" value="<?= sanitize(getSetting('session_year')) ?>">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn-primary-academy"><i class="bi bi-check-lg"></i> Save Settings</button>
                </div>
            </form>
        </div>

        <!-- WhatsApp Settings -->
        <div class="form-section mb-3">
            <div class="form-section-title"><i class="bi bi-whatsapp"></i> WhatsApp API Settings</div>
            <div class="alert alert-info" style="font-size:.82rem">
                <i class="bi bi-info-circle me-1"></i>
                Uses <strong>UltraMsg API</strong>. Get your instance ID and token from <strong>ultramsg.com</strong>.
                Leave empty to use WhatsApp Web links instead (opens browser).
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="save_whatsapp">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Instance ID</label>
                        <input type="text" name="whatsapp_instance_id" class="form-control" value="<?= sanitize(getSetting('whatsapp_instance_id')) ?>" placeholder="instance12345">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">API Token</label>
                        <input type="password" name="whatsapp_api_key" class="form-control" value="<?= sanitize(getSetting('whatsapp_api_key')) ?>" placeholder="your_token_here">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn-whatsapp"><i class="bi bi-whatsapp"></i> Save WhatsApp Settings</button>
                </div>
            </form>
        </div>

        <!-- Change Password -->
        <div class="form-section">
            <div class="form-section-title"><i class="bi bi-key"></i> Change Password</div>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn-primary-academy"><i class="bi bi-lock"></i> Change Password</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <!-- IP Management -->
        <div class="form-section mb-3">
            <div class="form-section-title"><i class="bi bi-shield-lock"></i> Allowed IPs</div>
            <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:1rem">
                Only these IPs can download restricted files. Students outside these IPs will see "Access Restricted."
            </div>
            <div style="background:var(--surface2);border-radius:8px;padding:.6rem .9rem;margin-bottom:1rem;font-size:.82rem">
                <i class="bi bi-info-circle me-1" style="color:var(--accent)"></i>
                Your current IP: <strong style="color:var(--accent)"><?= sanitize($clientIP) ?></strong>
                <?php if (isAllowedIP()): ?>
                <span class="badge-academy badge-success ms-1" style="font-size:.7rem">Allowed</span>
                <?php else: ?>
                <span class="badge-academy badge-warning ms-1" style="font-size:.7rem">Not Allowed</span>
                <?php endif; ?>
            </div>

            <form method="POST" class="mb-3">
                <input type="hidden" name="action" value="add_ip">
                <div class="row g-2">
                    <div class="col-7">
                        <input type="text" name="ip_address" class="form-control" placeholder="192.168.1.0" required>
                    </div>
                    <div class="col-5">
                        <input type="text" name="label" class="form-control" placeholder="Label">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn-primary-academy w-100" style="font-size:.82rem">
                            <i class="bi bi-plus-lg"></i> Add IP
                        </button>
                    </div>
                    <div class="col-12">
                        <button type="button" class="btn-primary-academy w-100" style="background:var(--surface3);color:var(--text);font-size:.82rem"
                            onclick="document.querySelector('[name=ip_address]').value='<?= sanitize($clientIP) ?>'">
                            <i class="bi bi-pc-display"></i> Use My IP (<?= sanitize($clientIP) ?>)
                        </button>
                    </div>
                </div>
            </form>

            <?php foreach ($allowedIPs as $ip): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:.5rem .75rem;background:var(--surface2);border-radius:8px;margin-bottom:.4rem;font-size:.82rem">
                <div>
                    <strong><?= sanitize($ip['ip_address']) ?></strong>
                    <?php if ($ip['label']): ?><span style="color:var(--text-muted);margin-left:.35rem">(<?= sanitize($ip['label']) ?>)</span><?php endif; ?>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="delete_ip">
                    <input type="hidden" name="id" value="<?= $ip['id'] ?>">
                    <button type="submit" class="btn-icon btn-icon-delete" style="width:24px;height:24px;font-size:.75rem">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php if (!$allowedIPs): ?>
            <p style="color:var(--text-muted);font-size:.82rem;text-align:center">No IPs configured yet.</p>
            <?php endif; ?>
        </div>

        <!-- System Info -->
        <div class="form-section">
            <div class="form-section-title"><i class="bi bi-cpu"></i> System Info</div>
            <div style="font-size:.82rem">
                <?php
                $stats = [
                    'Total Students'    => db()->fetchOne("SELECT COUNT(*) as c FROM students")['c'],
                    'Active Students'   => db()->fetchOne("SELECT COUNT(*) as c FROM students WHERE status='active'")['c'],
                    'Total Courses'     => db()->fetchOne("SELECT COUNT(*) as c FROM courses")['c'],
                    'Total Tests'       => db()->fetchOne("SELECT COUNT(*) as c FROM tests")['c'],
                    'Total Parents'     => db()->fetchOne("SELECT COUNT(*) as c FROM parents")['c'],
                    'Files Uploaded'    => db()->fetchOne("SELECT COUNT(*) as c FROM course_files")['c'],
                ];
                foreach ($stats as $label => $val):
                ?>
                <div style="display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid var(--border)">
                    <span style="color:var(--text-muted)"><?= $label ?></span>
                    <strong style="color:var(--accent)"><?= $val ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
