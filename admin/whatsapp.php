<?php
$pageTitle = 'WhatsApp Messages';
require_once __DIR__ . '/layout.php';

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type    = $_POST['msg_type'] ?? 'custom';
    $phone   = sanitize($_POST['phone'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    if ($phone && $message) {
        $result = sendWhatsAppMessage($phone, $message, $type, $_SESSION['user_id']);
        flashMessage('success', 'Message sent via WhatsApp! <a href="' . $result['wa_url'] . '" target="_blank" class="alert-link">Open WhatsApp</a>');
    } else {
        flashMessage('danger', 'Phone and message are required.');
    }
    header('Location: whatsapp.php'); exit;
}

$logs = db()->fetchAll(
    "SELECT w.*, u.name as sent_by_name FROM whatsapp_logs w LEFT JOIN users u ON w.sent_by=u.id ORDER BY w.sent_at DESC LIMIT 50"
);

$students = db()->fetchAll(
    "SELECT s.id, s.name, s.roll_number, s.course_id, c.name as course_name,
            p.name as parent_name, p.whatsapp as parent_wa, p.phone as parent_phone
     FROM students s
     LEFT JOIN courses c ON s.course_id=c.id
     LEFT JOIN parents p ON p.student_id=s.id
     WHERE s.status='active' AND (p.whatsapp IS NOT NULL OR p.phone IS NOT NULL)
     ORDER BY s.name"
);
?>

<div class="section-header">
    <div>
        <div class="section-title">WhatsApp Communication</div>
        <div class="section-subtitle">Send messages to parents via WhatsApp</div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-5">
        <!-- Compose -->
        <div class="data-card" style="padding:1.5rem">
            <div class="form-section-title"><i class="bi bi-whatsapp"></i> Compose Message</div>
            <form method="POST" id="waForm">
                <div class="mb-3">
                    <label class="form-label">Message Type</label>
                    <select name="msg_type" id="msgType" class="form-select" onchange="loadTemplate(this.value)">
                        <option value="custom">Custom Message</option>
                        <option value="result">Test Result</option>
                        <option value="attendance">Attendance Alert</option>
                        <option value="report">Monthly Report</option>
                        <option value="announcement">Announcement</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Quick Select Student</label>
                    <select id="studentSelect" class="form-select" onchange="fillPhone(this)">
                        <option value="">Select student to auto-fill...</option>
                        <?php foreach ($students as $s): ?>
                        <option value="<?= $s['id'] ?>"
                            data-phone="<?= sanitize($s['parent_wa'] ?: $s['parent_phone'] ?: '') ?>"
                            data-name="<?= sanitize($s['name']) ?>"
                            data-parent="<?= sanitize($s['parent_name'] ?? '') ?>"
                            data-course="<?= sanitize($s['course_name'] ?? '') ?>">
                            <?= sanitize($s['name']) ?> – <?= sanitize($s['parent_name'] ?? 'No Parent') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">WhatsApp Number</label>
                    <input type="text" name="phone" id="waPhone" class="form-control" placeholder="03XX-XXXXXXX" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Message</label>
                    <textarea name="message" id="waMessage" class="form-control" rows="7" required placeholder="Type your message..."></textarea>
                    <div style="font-size:.75rem;color:var(--text-muted);margin-top:.25rem" id="charCount">0 characters</div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn-whatsapp flex-grow-1">
                        <i class="bi bi-whatsapp"></i> Send via WhatsApp
                    </button>
                    <button type="button" class="btn-primary-academy" onclick="openDirectWA()" style="background:var(--surface3);color:var(--text)">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <!-- Templates -->
        <div class="data-card mb-3" style="padding:1.25rem">
            <div class="form-section-title"><i class="bi bi-layout-text-window"></i> Message Templates</div>
            <div class="row g-2">
                <?php
                $templates = [
                    ['title'=>'Test Result','icon'=>'bi-bar-chart','preview'=>"*The Brighten Stars Academy*\n\n📊 *Test Result Notification*\n\nDear Parent,\n\nYour child {Student Name} has appeared in:\n*Test:* {Test Name}\n*Marks:* {Obtained}/{Total}\n*Grade:* {Grade}\n*Position:* {Position}\n\nPlease encourage your child. Thank you!"],
                    ['title'=>'Attendance Alert','icon'=>'bi-calendar-x','preview'=>"*The Brighten Stars Academy*\n\n⚠️ *Attendance Alert*\n\nDear Parent,\n\nYour child *{Student Name}* was marked *ABSENT* on {Date}.\n\nPlease ensure regular attendance.\n\nFor queries, contact us.\nThank you!"],
                    ['title'=>'Monthly Report','icon'=>'bi-file-text','preview'=>"*The Brighten Stars Academy*\n\n📈 *Monthly Performance Report*\n\nStudent: {Name}\nCourse: {Course}\nMonth: {Month}\n\n📅 Attendance: {Present}/{Total} ({%})\n📝 Tests: {Tests Taken}\n📊 Avg Marks: {Average}%\n🎓 Grade: {Grade}\n\nTeacher Remarks: {Remarks}\n\nThank you!"],
                    ['title'=>'Holiday Notice','icon'=>'bi-calendar-event','preview'=>"*The Brighten Stars Academy*\n\n🎉 *Holiday Notice*\n\nDear Student/Parent,\n\nPlease note that the academy will remain *CLOSED* on {Date} due to {Reason}.\n\nClasses will resume on {Resume Date}.\n\nApologies for any inconvenience.\nThank you!"],
                ];
                foreach ($templates as $tpl):
                ?>
                <div class="col-6">
                    <div onclick="useTemplate(`<?= addslashes($tpl['preview']) ?>`)"
                        style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:.75rem;cursor:pointer;transition:all .15s"
                        onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
                        <i class="bi <?= $tpl['icon'] ?>" style="color:var(--accent);margin-bottom:.35rem;font-size:1rem;display:block"></i>
                        <div style="font-size:.82rem;font-weight:600"><?= $tpl['title'] ?></div>
                        <div style="font-size:.72rem;color:var(--text-muted);margin-top:.2rem">Click to use template</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Sent Log -->
        <div class="data-card">
            <div class="data-card-header">
                <div class="data-card-title">Sent Messages Log</div>
            </div>
            <div class="table-wrap">
                <table class="table-academy">
                    <thead><tr><th>Phone</th><th>Type</th><th>Status</th><th>Sent At</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php if ($logs): foreach ($logs as $log): ?>
                    <tr>
                        <td style="font-size:.83rem"><?= sanitize($log['phone']) ?></td>
                        <td>
                            <span class="badge-academy badge-info" style="font-size:.7rem"><?= ucfirst($log['message_type']) ?></span>
                        </td>
                        <td>
                            <span class="badge-academy <?= $log['status']==='sent'?'badge-success':($log['status']==='failed'?'badge-danger':'badge-warning') ?>">
                                <?= ucfirst($log['status']) ?>
                            </span>
                        </td>
                        <td style="font-size:.78rem;color:var(--text-muted)"><?= formatDate($log['sent_at'], 'd M Y H:i') ?></td>
                        <td>
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$log['phone']) ?>?text=<?= urlencode($log['message']) ?>"
                                target="_blank" class="btn-icon btn-icon-wa" title="Open WhatsApp">
                                <i class="bi bi-whatsapp"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:2rem">No messages sent yet</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const msgTemplates = {
    result: "The Brighten Stars Academy\n\n📊 Test Result Notification\n\nDear Parent,\n\nYour child has appeared in a test.\nMarks: {Obtained}/{Total}\nGrade: {Grade}\n\nThank you!",
    attendance: "The Brighten Stars Academy\n\n⚠️ Attendance Alert\n\nDear Parent,\n\nYour child was marked ABSENT today ({Date}).\n\nPlease ensure regular attendance.\n\nThank you!",
    report: "The Brighten Stars Academy\n\n📈 Monthly Performance Report\n\nStudent: {Name}\nMonth: {Month}\n\nAttendance: {%}%\nAvg Marks: {Average}%\nGrade: {Grade}\n\nThank you!",
    announcement: "The Brighten Stars Academy\n\n📢 Important Announcement\n\n{Message}\n\nThank you!",
    custom: ""
};

function loadTemplate(type) {
    const msg = document.getElementById('waMessage');
    if (msgTemplates[type]) msg.value = msgTemplates[type];
    updateCount();
}

function useTemplate(text) {
    document.getElementById('waMessage').value = text.replace(/\\n/g,'\n');
    updateCount();
}

function fillPhone(sel) {
    const opt = sel.options[sel.selectedIndex];
    const phone = opt.dataset.phone;
    const name = opt.dataset.name;
    const parent = opt.dataset.parent;
    if (phone) document.getElementById('waPhone').value = phone;
    const msg = document.getElementById('waMessage').value;
    if (name) document.getElementById('waMessage').value = msg.replace('{Student Name}', name).replace('{Name}', name);
    if (parent) document.getElementById('waMessage').value = document.getElementById('waMessage').value.replace('{Parent Name}', parent);
    updateCount();
}

function openDirectWA() {
    const phone = document.getElementById('waPhone').value.replace(/[^0-9]/g,'');
    const msg = document.getElementById('waMessage').value;
    const num = phone.startsWith('0') ? '92' + phone.slice(1) : phone;
    window.open('https://wa.me/' + num + '?text=' + encodeURIComponent(msg), '_blank');
}

function updateCount() {
    const len = document.getElementById('waMessage').value.length;
    document.getElementById('charCount').textContent = len + ' characters';
}

document.getElementById('waMessage').addEventListener('input', updateCount);
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
