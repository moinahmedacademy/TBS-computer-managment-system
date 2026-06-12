<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name      = sanitize($_POST['name'] ?? '');
        $studentId = (int)$_POST['student_id'];
        $relation  = $_POST['relation'] ?? 'father';
        $phone     = sanitize($_POST['phone'] ?? '');
        $whatsapp  = sanitize($_POST['whatsapp'] ?? '');
        $email     = sanitize($_POST['email'] ?? '');
        $cnic      = sanitize($_POST['cnic'] ?? '');
        $address   = sanitize($_POST['address'] ?? '');
        $password  = $_POST['password'] ?? '';
        $createLogin = isset($_POST['create_login']);

        if (!$name || !$studentId || !$phone) {
            flashMessage('danger', 'Name, student, and phone required.');
            header('Location: parents.php'); exit;
        }

        if ($action === 'add') {
            $userId = null;
            if ($createLogin && $email) {
                $existing = db()->fetchOne("SELECT id FROM users WHERE email=?", [$email]);
                if (!$existing) {
                    $userId = db()->insert(
                        "INSERT INTO users (name,email,password,role,phone,status) VALUES (?,?,?,'parent',?,'active')",
                        [$name, $email, password_hash($password ?: 'Parent@123', PASSWORD_DEFAULT), $phone]
                    );
                }
            }
            db()->execute(
                "INSERT INTO parents (user_id,student_id,name,relation,phone,whatsapp,email,cnic,address) VALUES (?,?,?,?,?,?,?,?,?)",
                [$userId, $studentId, $name, $relation, $phone, $whatsapp, $email, $cnic, $address]
            );
            $msg = "Parent '" . sanitize($name) . "' added.";
            if ($userId) $msg .= " Login email: <strong>" . sanitize($email) . "</strong>. Share credentials privately.";
            flashMessage('success', $msg);
        } else {
            $id = (int)$_POST['id'];
            db()->execute(
                "UPDATE parents SET name=?,student_id=?,relation=?,phone=?,whatsapp=?,email=?,cnic=?,address=? WHERE id=?",
                [$name, $studentId, $relation, $phone, $whatsapp, $email, $cnic, $address, $id]
            );
            flashMessage('success', 'Parent updated.');
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $p = db()->fetchOne("SELECT user_id FROM parents WHERE id=?", [$id]);
        if ($p && $p['user_id']) db()->execute("DELETE FROM users WHERE id=?", [$p['user_id']]);
        db()->execute("DELETE FROM parents WHERE id=?", [$id]);
        flashMessage('success', 'Parent deleted.');
    }
    header('Location: parents.php'); exit;
}

$pageTitle = 'Parents';
require_once __DIR__ . '/layout.php';


$search = sanitize($_GET['q'] ?? '');
$sql = "SELECT p.*, s.name as student_name, s.roll_number FROM parents p JOIN students s ON p.student_id=s.id WHERE 1=1";
$params = [];
if ($search) { $sql .= " AND (p.name LIKE ? OR s.name LIKE ? OR p.phone LIKE ?)"; $params = array_fill(0, 3, "%$search%"); }
$sql .= " ORDER BY p.created_at DESC";
$parents = db()->fetchAll($sql, $params);
$students = db()->fetchAll("SELECT id,name,roll_number FROM students WHERE status='active' ORDER BY name");
?>

<div class="section-header">
    <div>
        <div class="section-title">Parents Management</div>
        <div class="section-subtitle"><?= count($parents) ?> parent(s)</div>
    </div>
    <button class="btn-primary-academy" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg"></i> Add Parent
    </button>
</div>

<div class="data-card mb-3">
    <div style="padding:.75rem 1rem">
        <form method="GET" class="d-flex gap-2">
            <div class="search-wrap">
                <i class="bi bi-search"></i>
                <input type="text" name="q" class="search-input" placeholder="Search parents..." value="<?= sanitize($search) ?>">
            </div>
            <button type="submit" class="btn-primary-academy"><i class="bi bi-funnel"></i></button>
        </form>
    </div>
</div>

<div class="data-card">
    <div class="table-wrap">
        <table class="table-academy">
            <thead><tr><th>Parent</th><th>Relation</th><th>Student</th><th>Phone</th><th>WhatsApp</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($parents): foreach ($parents as $p): ?>
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-circle" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9)"><?= strtoupper(substr($p['name'],0,1)) ?></div>
                        <div>
                            <div style="font-weight:500"><?= sanitize($p['name']) ?></div>
                            <div style="font-size:.75rem;color:var(--text-muted)"><?= sanitize($p['email'] ?: '') ?></div>
                        </div>
                    </div>
                </td>
                <td><span class="badge-academy badge-info"><?= ucfirst($p['relation']) ?></span></td>
                <td>
                    <div style="font-weight:500;font-size:.85rem"><?= sanitize($p['student_name']) ?></div>
                    <div style="font-size:.75rem;color:var(--text-muted)"><?= sanitize($p['roll_number']) ?></div>
                </td>
                <td style="font-size:.83rem"><?= sanitize($p['phone']) ?></td>
                <td>
                    <?php if ($p['whatsapp']): ?>
                    <?php $waFormatted = preg_replace('/[^0-9]/', '', $p['whatsapp']); if (substr($waFormatted,0,1)==='0') $waFormatted='92'.substr($waFormatted,1); ?>
                    <a href="https://wa.me/<?= $waFormatted ?>"
                        target="_blank" class="btn-whatsapp" style="padding:.3rem .7rem;font-size:.78rem">
                        <i class="bi bi-whatsapp"></i> <?= sanitize($p['whatsapp']) ?>
                    </a>
                    <?php else: ?><span style="color:var(--text-muted);font-size:.82rem">â€”</span><?php endif; ?>
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <button class="btn-icon btn-icon-edit" title="Edit" onclick="editParent(<?= htmlspecialchars(json_encode($p)) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php $waNum = $p['whatsapp'] ?: $p['phone']; ?>
                        <?php $waMsg = "Dear " . ($p['name'] ?? '') . ", this is a message from The Brighten Stars Academy regarding your child " . ($p['student_name'] ?? '') . "."; ?>
                        <button class="btn-icon btn-icon-wa" title="Send WhatsApp"
                            onclick="openWhatsApp('<?= sanitize($waNum) ?>',<?= json_encode($waMsg) ?>)">
                            <i class="bi bi-whatsapp"></i>
                        </button>
                        <a href="<?= BASE_URL ?>/admin/results.php?student=<?= $p['student_id'] ?>"
                           class="btn-icon" title="View Results"
                           style="background:rgba(139,92,246,.15);color:#8b5cf6;border:none;border-radius:7px;width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center">
                            <i class="bi bi-bar-chart"></i>
                        </a>
                        <?php $delMsg = "Parent " . ($p['name'] ?? '') . " will be deleted."; ?>
                        <form method="POST" onsubmit="return confirmDelete(this, <?= json_encode($delMsg) ?>)">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <?= csrfField() ?>
                            <button type="submit" class="btn-icon btn-icon-delete" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:3rem">No parents registered</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <?= csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Add Parent</h5>
                    <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Parent Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="Parent full name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Relation</label>
                        <select name="relation" class="form-select">
                            <option value="father">Father</option>
                            <option value="mother">Mother</option>
                            <option value="guardian">Guardian</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Student *</label>
                        <select name="student_id" class="form-select" required>
                            <option value="">Select Student</option>
                            <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= sanitize($s['name']) ?> (<?= sanitize($s['roll_number']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone *</label>
                        <input type="text" name="phone" class="form-control" required placeholder="03XX-XXXXXXX">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">WhatsApp Number</label>
                        <input type="text" name="whatsapp" class="form-control" placeholder="Same as phone or different">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">CNIC</label>
                        <input type="text" name="cnic" class="form-control" placeholder="XXXXX-XXXXXXX-X">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="parent@email.com">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="create_login" id="createLogin" class="form-check-input">
                            <label for="createLogin" class="form-check-label form-label">Create portal login account</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Portal Password <small style="color:var(--text-muted)">(default: Parent@123)</small></label>
                        <input type="password" name="password" class="form-control" placeholder="Leave empty for default">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-academy">Add Parent</button>
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
                <input type="hidden" name="id" id="ep_id">
                <?= csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Edit Parent</h5>
                    <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-6"><label class="form-label">Name</label><input type="text" name="name" id="ep_name" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Relation</label><select name="relation" id="ep_relation" class="form-select"><option value="father">Father</option><option value="mother">Mother</option><option value="guardian">Guardian</option></select></div>
                    <div class="col-md-6"><label class="form-label">Student</label><select name="student_id" id="ep_student" class="form-select"><?php foreach ($students as $s): ?><option value="<?= $s['id'] ?>"><?= sanitize($s['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" id="ep_phone" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">WhatsApp</label><input type="text" name="whatsapp" id="ep_wa" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" id="ep_email" class="form-control"></div>
                    <div class="col-12"><label class="form-label">Address</label><textarea name="address" id="ep_address" class="form-control" rows="2"></textarea></div>
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
function editParent(p) {
    document.getElementById('ep_id').value = p.id;
    document.getElementById('ep_name').value = p.name;
    document.getElementById('ep_relation').value = p.relation;
    document.getElementById('ep_student').value = p.student_id;
    document.getElementById('ep_phone').value = p.phone || '';
    document.getElementById('ep_wa').value = p.whatsapp || '';
    document.getElementById('ep_email').value = p.email || '';
    document.getElementById('ep_address').value = p.address || '';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>