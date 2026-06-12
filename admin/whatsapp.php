<?php
$pageTitle = 'WhatsApp';
require_once __DIR__ . '/layout.php';

// Ensure extra columns exist (safe to run multiple times)
db()->execute("ALTER TABLE whatsapp_logs ADD COLUMN IF NOT EXISTS recipient_name VARCHAR(150) NULL");
db()->execute("ALTER TABLE whatsapp_logs ADD COLUMN IF NOT EXISTS student_roll VARCHAR(30) NULL");
db()->execute("ALTER TABLE whatsapp_logs ADD COLUMN IF NOT EXISTS father_name VARCHAR(150) NULL");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'send';

    // Save custom template
    if ($action === 'save_template') {
        $tplTitle = sanitize($_POST['tpl_title'] ?? '');
        $tplBody  = sanitize($_POST['tpl_body']  ?? '');
        if ($tplTitle && $tplBody) {
            $existing = db()->fetchOne("SELECT value FROM settings WHERE key_name='wa_templates'");
            $templates = $existing ? json_decode($existing['value'], true) : [];
            $templates[] = ['title' => $tplTitle, 'body' => $tplBody, 'custom' => true];
            $val = json_encode($templates, JSON_UNESCAPED_UNICODE);
            if ($existing) {
                db()->execute("UPDATE settings SET value=? WHERE key_name='wa_templates'", [$val]);
            } else {
                db()->execute("INSERT INTO settings (key_name,value) VALUES ('wa_templates',?)", [$val]);
            }
            flashMessage('success', "Template \"$tplTitle\" saved.");
        }
        header('Location: whatsapp.php'); exit;
    }

    // Delete custom template
    if ($action === 'delete_template') {
        $idx = (int)($_POST['tpl_index'] ?? -1);
        $existing = db()->fetchOne("SELECT value FROM settings WHERE key_name='wa_templates'");
        $templates = $existing ? json_decode($existing['value'], true) : [];
        if (isset($templates[$idx])) {
            array_splice($templates, $idx, 1);
            db()->execute("UPDATE settings SET value=? WHERE key_name='wa_templates'", [json_encode($templates, JSON_UNESCAPED_UNICODE)]);
            flashMessage('success', 'Template deleted.');
        }
        header('Location: whatsapp.php'); exit;
    }

    // Log message (AJAX call from JS after WA window opens)
    if ($action === 'log') {
        $phone     = sanitize($_POST['phone'] ?? '');
        $message   = sanitize($_POST['message'] ?? '');
        $msgType   = sanitize($_POST['msg_type'] ?? 'custom');
        $recName   = sanitize($_POST['recipient_name'] ?? '');
        $rollNo    = sanitize($_POST['roll_number'] ?? '');
        $fatherN   = sanitize($_POST['father_name'] ?? '');

        if ($phone) {
            db()->execute(
                "INSERT INTO whatsapp_logs (sent_by,phone,message,message_type,status,recipient_name,student_roll,father_name,sent_at)
                 VALUES (?,?,?,?,'sent',?,?,?,NOW())",
                [$_SESSION['user_id'], $phone, $message, $msgType, $recName, $rollNo, $fatherN]
            );
            echo json_encode(['ok' => true]); exit;
        }
        echo json_encode(['ok' => false]); exit;
    }
}

// Fetch data
$logs = db()->fetchAll(
    "SELECT w.*, u.name as sent_by_name
     FROM whatsapp_logs w LEFT JOIN users u ON w.sent_by=u.id
     ORDER BY w.sent_at DESC LIMIT 100"
);

$students = db()->fetchAll(
    "SELECT s.id, s.name, s.roll_number, s.batch,
            c.name as course_name,
            p.name as parent_name, p.relation,
            p.whatsapp as parent_wa, p.phone as parent_phone,
            p.father_name as father_name
     FROM students s
     LEFT JOIN courses c ON s.course_id = c.id
     LEFT JOIN parents p ON p.student_id = s.id
     WHERE s.status='active'
     ORDER BY s.name"
);

// Built-in templates
$builtinTemplates = [
    ['title'=>'Test Result',      'icon'=>'bi-bar-chart',      'color'=>'#3b82f6',
     'body' => "*The Brighten Stars Academy*\n\n📊 *Test Result*\n\nDear {Parent Name},\nYour child *{Student Name}* (Roll: {Roll No}) has appeared in:\n\n*Test:* {Test Name}\n*Subject:* {Subject}\n*Marks:* {Obtained}/{Total}\n*Percentage:* {%}%\n*Grade:* {Grade}\n\nKeep up the great work! 🌟\n\n– The Brighten Stars Academy"],
    ['title'=>'Absent Alert',     'icon'=>'bi-calendar-x',     'color'=>'#ef4444',
     'body' => "*The Brighten Stars Academy*\n\n⚠️ *Absence Notice*\n\nDear {Parent Name},\nYour child *{Student Name}* (Roll: {Roll No}) was marked *ABSENT* on {Date}.\n\nPlease ensure regular attendance.\n📞 Contact us for queries.\n\n– The Brighten Stars Academy"],
    ['title'=>'Monthly Report',   'icon'=>'bi-file-earmark-bar-chart', 'color'=>'#10b981',
     'body' => "*The Brighten Stars Academy*\n\n📈 *Monthly Report – {Month}*\n\nStudent: *{Student Name}*\nRoll No: {Roll No}\nCourse: {Course}\n\n📅 Attendance: {Present}/{Total} days ({Att%}%)\n📝 Tests Taken: {Tests}\n📊 Average Marks: {Average}%\n🎓 Grade: {Grade}\n\nTeacher Remarks: {Remarks}\n\n– The Brighten Stars Academy"],
    ['title'=>'Holiday Notice',   'icon'=>'bi-calendar-event', 'color'=>'#f59e0b',
     'body' => "*The Brighten Stars Academy*\n\n🎉 *Holiday Notice*\n\nDear Student/Parent,\n\nThe academy will remain *CLOSED* on {Date} due to {Reason}.\nClasses resume on {Resume Date}.\n\nThank you for your understanding!\n\n– The Brighten Stars Academy"],
    ['title'=>'Fee Reminder',     'icon'=>'bi-credit-card',    'color'=>'#8b5cf6',
     'body' => "*The Brighten Stars Academy*\n\n💳 *Fee Reminder*\n\nDear {Parent Name},\nThis is a reminder that the fee for *{Student Name}* (Roll: {Roll No}) for *{Month}* is due.\n\nAmount: PKR {Amount}\nDue Date: {Due Date}\n\nPlease clear dues at earliest.\n\nThank you!\n– The Brighten Stars Academy"],
    ['title'=>'New Admission',    'icon'=>'bi-person-plus',    'color'=>'#06b6d4',
     'body' => "*The Brighten Stars Academy*\n\n🎓 *Admission Confirmed!*\n\nDear {Parent Name},\n\nWe are pleased to confirm that *{Student Name}* has been successfully enrolled in:\n\n📚 Course: {Course}\n🕐 Timing: {Timing}\n🔢 Roll No: {Roll No}\n📅 Start Date: {Start Date}\n\nWelcome to the Brighten Stars family! ⭐\n\n– The Brighten Stars Academy"],
    ['title'=>'Test Schedule',    'icon'=>'bi-pencil-square',  'color'=>'#f97316',
     'body' => "*The Brighten Stars Academy*\n\n📝 *Upcoming Test*\n\nDear Student/Parent,\n\nA *{Test Type}* test is scheduled:\n\n📚 Subject: {Subject}\n📅 Date: {Date}\n🕐 Time: {Time}\n📋 Syllabus: {Syllabus}\n\nBest of luck! 🌟\n\n– The Brighten Stars Academy"],
    ['title'=>'Announcement',     'icon'=>'bi-megaphone',      'color'=>'#ec4899',
     'body' => "*The Brighten Stars Academy*\n\n📢 *Important Announcement*\n\n{Message}\n\nFor queries, please contact the academy.\n\nThank you!\n– The Brighten Stars Academy"],
];

// Load custom saved templates
$customRow = db()->fetchOne("SELECT value FROM settings WHERE key_name='wa_templates'");
$customTemplates = $customRow ? json_decode($customRow['value'], true) : [];
?>

<div class="section-header">
    <div>
        <div class="section-title"><i class="bi bi-whatsapp me-2" style="color:#25d366"></i>WhatsApp Communication</div>
        <div class="section-subtitle">Compose & send messages to parents/students</div>
    </div>
    <button class="btn-primary-academy" data-bs-toggle="modal" data-bs-target="#customTplModal"
            style="background:rgba(37,211,102,.2);color:#25d366;border:1px solid rgba(37,211,102,.3)">
        <i class="bi bi-plus-lg"></i> Save Custom Template
    </button>
</div>

<div class="row g-3">

    <!-- ── LEFT: COMPOSE ──────────────────────────────────────────────── -->
    <div class="col-12 col-lg-5">
        <div class="data-card" style="padding:1.5rem">
            <div style="font-weight:700;font-size:.95rem;margin-bottom:1.1rem;display:flex;align-items:center;gap:.5rem">
                <i class="bi bi-pencil-square" style="color:var(--accent)"></i> Compose Message
            </div>

            <!-- Student Search -->
            <div class="mb-3">
                <label class="form-label">Search Student / Parent</label>
                <div class="search-wrap" style="width:100%">
                    <i class="bi bi-search"></i>
                    <input type="text" id="studentSearch" class="search-input" style="width:100%"
                           placeholder="Search by name, roll no, course…" oninput="filterStudents(this.value)">
                </div>
            </div>

            <div class="mb-3" id="studentListWrap" style="display:none;max-height:200px;overflow-y:auto;border:1px solid var(--border);border-radius:10px">
                <?php foreach ($students as $s): ?>
                <div class="student-opt" data-id="<?= $s['id'] ?>"
                     data-phone="<?= sanitize($s['parent_wa'] ?: $s['parent_phone'] ?: '') ?>"
                     data-name="<?= sanitize($s['name']) ?>"
                     data-roll="<?= sanitize($s['roll_number']) ?>"
                     data-course="<?= sanitize($s['course_name'] ?? '') ?>"
                     data-parent="<?= sanitize($s['parent_name'] ?? '') ?>"
                     data-father="<?= sanitize($s['father_name'] ?? $s['parent_name'] ?? '') ?>"
                     data-batch="<?= sanitize($s['batch'] ?? '') ?>"
                     data-search="<?= strtolower(sanitize($s['name'] . ' ' . $s['roll_number'] . ' ' . $s['course_name'] . ' ' . $s['batch'])) ?>"
                     onclick="selectStudent(this)"
                     style="padding:.6rem 1rem;cursor:pointer;border-bottom:1px solid var(--border);font-size:.83rem;transition:background .1s">
                    <div style="font-weight:600"><?= sanitize($s['name']) ?>
                        <span style="color:var(--text-muted);font-weight:400"> — <?= sanitize($s['roll_number']) ?></span>
                    </div>
                    <div style="color:var(--text-muted);font-size:.75rem">
                        <?= sanitize($s['course_name'] ?? '—') ?>
                        <?= $s['parent_name'] ? ' · Parent: '.sanitize($s['parent_name']) : '' ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Selected student chip -->
            <div id="selectedChip" style="display:none;margin-bottom:.75rem">
                <div style="background:rgba(37,211,102,.1);border:1px solid rgba(37,211,102,.25);border-radius:8px;padding:.5rem .85rem;font-size:.82rem;display:flex;justify-content:space-between;align-items:center">
                    <span><i class="bi bi-person-check me-1" style="color:#25d366"></i><span id="chipText"></span></span>
                    <button onclick="clearStudent()" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:.9rem">✕</button>
                </div>
            </div>

            <form id="waForm">
                <input type="hidden" id="waStudentName" value="">
                <input type="hidden" id="waRollNo" value="">
                <input type="hidden" id="waFatherName" value="">

                <div class="mb-3">
                    <label class="form-label">WhatsApp Number *</label>
                    <input type="text" id="waPhone" class="form-control" placeholder="03XX-XXXXXXX" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Message Type</label>
                    <select id="msgType" class="form-select" onchange="loadTemplate(this.value)">
                        <option value="custom">Custom Message</option>
                        <option value="result">Test Result</option>
                        <option value="attendance">Attendance Alert</option>
                        <option value="report">Monthly Report</option>
                        <option value="announcement">Announcement</option>
                        <option value="fee">Fee Reminder</option>
                        <option value="admission">New Admission</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Message</label>
                    <textarea id="waMessage" class="form-control" rows="8" placeholder="Type message or click a template…"></textarea>
                    <div style="font-size:.75rem;color:var(--text-muted);margin-top:.3rem" id="charCount">0 characters</div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn-whatsapp flex-grow-1" onclick="sendWhatsApp()">
                        <i class="bi bi-whatsapp"></i> Open WhatsApp & Send
                    </button>
                    <button type="button" class="btn-primary-academy" title="Copy message"
                            onclick="copyMsg()" style="background:var(--surface3);color:var(--text);padding:.6rem .9rem">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
                <div id="sendFeedback" style="display:none;margin-top:.75rem;background:rgba(37,211,102,.1);border:1px solid rgba(37,211,102,.25);border-radius:8px;padding:.6rem .9rem;font-size:.83rem;color:#25d366">
                    <i class="bi bi-check-circle me-1"></i> WhatsApp opened & message logged ✓
                </div>
            </form>
        </div>
    </div>

    <!-- ── RIGHT: TEMPLATES + LOG ─────────────────────────────────────── -->
    <div class="col-12 col-lg-7">

        <!-- Templates -->
        <div class="data-card mb-3" style="padding:1.25rem">
            <div style="font-weight:700;font-size:.88rem;margin-bottom:.85rem;display:flex;align-items:center;gap:.5rem">
                <i class="bi bi-layout-text-window" style="color:var(--accent)"></i> Message Templates
                <span style="font-size:.72rem;color:var(--text-muted);font-weight:400">(click to use)</span>
            </div>
            <div class="row g-2">
                <?php foreach ($builtinTemplates as $tpl): ?>
                <div class="col-6 col-md-4 col-xl-3">
                    <div class="tpl-card" onclick="useTemplate(<?= json_encode($tpl['body']) ?>)"
                         style="border-top:3px solid <?= $tpl['color'] ?>">
                        <i class="bi <?= $tpl['icon'] ?>" style="color:<?= $tpl['color'] ?>;font-size:1.1rem;margin-bottom:.3rem;display:block"></i>
                        <div style="font-size:.78rem;font-weight:600;line-height:1.3"><?= $tpl['title'] ?></div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php foreach ($customTemplates as $idx => $tpl): ?>
                <div class="col-6 col-md-4 col-xl-3" id="ctpl-<?= $idx ?>">
                    <div class="tpl-card" onclick="useTemplate(<?= json_encode($tpl['body']) ?>)"
                         style="border-top:3px solid #8b5cf6;position:relative">
                        <i class="bi bi-star-fill" style="color:#8b5cf6;font-size:1.1rem;margin-bottom:.3rem;display:block"></i>
                        <div style="font-size:.78rem;font-weight:600;line-height:1.3"><?= sanitize($tpl['title']) ?></div>
                        <div style="font-size:.65rem;color:#8b5cf6;margin-top:.15rem">Custom</div>
                        <form method="POST" style="position:absolute;top:4px;right:4px"
                              onsubmit="return confirmDelete(this,'Delete this template?')">
                            <input type="hidden" name="action" value="delete_template">
                            <input type="hidden" name="tpl_index" value="<?= $idx ?>">
                            <button type="submit" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:.7rem;padding:2px 4px"
                                    title="Delete template">✕</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Sent Log -->
        <div class="data-card">
            <div class="data-card-header">
                <div class="data-card-title">Sent Messages Log</div>
                <span style="font-size:.75rem;color:var(--text-muted)">Last 100 messages</span>
            </div>
            <div class="table-wrap">
                <table class="table-academy">
                    <thead>
                        <tr>
                            <th>Recipient</th>
                            <th>Phone</th>
                            <th>Type</th>
                            <th>Message Preview</th>
                            <th>Sent</th>
                            <th>Re-send</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($logs): foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <?php if (!empty($log['recipient_name'])): ?>
                            <div style="font-weight:500;font-size:.83rem"><?= sanitize($log['recipient_name']) ?></div>
                            <?php if (!empty($log['student_roll'])): ?>
                            <div style="font-size:.72rem;color:var(--accent)"><?= sanitize($log['student_roll']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($log['father_name'])): ?>
                            <div style="font-size:.72rem;color:var(--text-muted)">F: <?= sanitize($log['father_name']) ?></div>
                            <?php endif; ?>
                            <?php else: ?>
                            <span style="color:var(--text-muted);font-size:.78rem">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:.82rem"><?= sanitize($log['phone']) ?></td>
                        <td>
                            <span class="badge-academy badge-info" style="font-size:.68rem"><?= ucfirst($log['message_type'] ?? 'custom') ?></span>
                        </td>
                        <td style="max-width:180px">
                            <div style="font-size:.75rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:170px"
                                 title="<?= sanitize($log['message'] ?? '') ?>">
                                <?= sanitize(substr($log['message'] ?? '', 0, 60)) ?><?= strlen($log['message'] ?? '') > 60 ? '…' : '' ?>
                            </div>
                        </td>
                        <td style="font-size:.75rem;color:var(--text-muted);white-space:nowrap">
                            <?= !empty($log['sent_at']) ? date('d M, H:i', strtotime($log['sent_at'])) : '—' ?>
                            <?php if (!empty($log['sent_by_name'])): ?>
                            <div style="font-size:.68rem;color:var(--text-muted)"><?= sanitize($log['sent_by_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $cleanNum = preg_replace('/[^0-9]/', '', $log['phone']);
                            if (strlen($cleanNum) > 0 && $cleanNum[0] === '0') $cleanNum = '92' . substr($cleanNum, 1);
                            ?>
                            <a href="https://wa.me/<?= $cleanNum ?>?text=<?= urlencode($log['message'] ?? '') ?>"
                               target="_blank" class="btn-icon btn-icon-wa" title="Re-send via WhatsApp">
                                <i class="bi bi-whatsapp"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:2.5rem">
                        <i class="bi bi-chat-dots" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
                        No messages sent yet
                    </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Custom Template Modal -->
<div class="modal fade" id="customTplModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="save_template">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-star me-2" style="color:#8b5cf6"></i>Save Custom Template</h5>
                    <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Template Title *</label>
                        <input type="text" name="tpl_title" class="form-control" required placeholder="e.g. Fee Overdue Notice">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message Body *</label>
                        <textarea name="tpl_body" class="form-control" rows="8" required
                            placeholder="Use {Student Name}, {Roll No}, {Parent Name}, {Course}, {Date} as placeholders…"></textarea>
                        <small style="color:var(--text-muted)">
                            Available placeholders: {Student Name} {Roll No} {Parent Name} {Course} {Date} {Timing} {Batch}
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-academy"><i class="bi bi-check-lg"></i> Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.tpl-card {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: .7rem .75rem;
    cursor: pointer;
    transition: all .15s;
    height: 100%;
}
.tpl-card:hover { background: var(--surface3); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,.3); }
.student-opt:hover { background: var(--surface2); }
</style>

<script>
// ── Templates ─────────────────────────────────────────────────────────────
const builtinMap = {
    result:       <?= json_encode($builtinTemplates[0]['body']) ?>,
    attendance:   <?= json_encode($builtinTemplates[1]['body']) ?>,
    report:       <?= json_encode($builtinTemplates[2]['body']) ?>,
    announcement: <?= json_encode($builtinTemplates[7]['body']) ?>,
    fee:          <?= json_encode($builtinTemplates[4]['body']) ?>,
    admission:    <?= json_encode($builtinTemplates[5]['body']) ?>,
    custom:       ''
};

function loadTemplate(type) {
    if (builtinMap[type]) useTemplate(builtinMap[type]);
}

function useTemplate(text) {
    const name   = document.getElementById('waStudentName').value;
    const roll   = document.getElementById('waRollNo').value;
    const father = document.getElementById('waFatherName').value;
    let msg = text;
    if (name)   { msg = msg.replace(/{Student Name}/g, name).replace(/{Name}/g, name); }
    if (roll)   { msg = msg.replace(/{Roll No}/g, roll); }
    if (father) { msg = msg.replace(/{Father Name}/g, father).replace(/{Parent Name}/g, father); }
    msg = msg.replace(/{Date}/g, new Date().toLocaleDateString('en-PK', {day:'2-digit',month:'short',year:'numeric'}));
    document.getElementById('waMessage').value = msg;
    updateCount();
    document.getElementById('waMessage').focus();
}

// ── Student Search ────────────────────────────────────────────────────────
function filterStudents(q) {
    const wrap = document.getElementById('studentListWrap');
    const items = document.querySelectorAll('.student-opt');
    const lower = q.trim().toLowerCase();
    let visible = 0;
    items.forEach(el => {
        const match = !lower || el.dataset.search.includes(lower);
        el.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    wrap.style.display = (lower && visible > 0) ? '' : 'none';
}

function selectStudent(el) {
    document.getElementById('waPhone').value       = el.dataset.phone || '';
    document.getElementById('waStudentName').value = el.dataset.name  || '';
    document.getElementById('waRollNo').value      = el.dataset.roll  || '';
    document.getElementById('waFatherName').value  = el.dataset.father || el.dataset.parent || '';

    document.getElementById('chipText').textContent =
        el.dataset.name + ' · ' + el.dataset.roll + (el.dataset.course ? ' · ' + el.dataset.course : '');
    document.getElementById('selectedChip').style.display = '';
    document.getElementById('studentListWrap').style.display = 'none';
    document.getElementById('studentSearch').value = '';

    // Auto-fill placeholders in existing message
    const msg = document.getElementById('waMessage');
    if (msg.value) {
        msg.value = msg.value
            .replace(/{Student Name}/g, el.dataset.name)
            .replace(/{Name}/g, el.dataset.name)
            .replace(/{Roll No}/g, el.dataset.roll)
            .replace(/{Parent Name}/g, el.dataset.father || el.dataset.parent)
            .replace(/{Father Name}/g, el.dataset.father || el.dataset.parent)
            .replace(/{Course}/g, el.dataset.course);
        updateCount();
    }
}

function clearStudent() {
    document.getElementById('waStudentName').value = '';
    document.getElementById('waRollNo').value = '';
    document.getElementById('waFatherName').value = '';
    document.getElementById('waPhone').value = '';
    document.getElementById('selectedChip').style.display = 'none';
}

// ── Send WhatsApp ─────────────────────────────────────────────────────────
function sendWhatsApp() {
    const phone   = document.getElementById('waPhone').value.trim();
    const message = document.getElementById('waMessage').value.trim();
    const msgType = document.getElementById('msgType').value;
    const stuName = document.getElementById('waStudentName').value;
    const rollNo  = document.getElementById('waRollNo').value;
    const fatName = document.getElementById('waFatherName').value;

    if (!phone) { showToast('Please enter a WhatsApp number.', 'danger'); return; }
    if (!message) { showToast('Please write a message.', 'danger'); return; }

    // Clean phone number
    const clean = phone.replace(/[^0-9]/g, '');
    const num   = clean.startsWith('0') ? '92' + clean.slice(1) : clean;

    // Open WhatsApp
    window.open('https://wa.me/' + num + '?text=' + encodeURIComponent(message), '_blank');

    // Log via AJAX
    const fd = new FormData();
    fd.append('action', 'log');
    fd.append('phone', phone);
    fd.append('message', message);
    fd.append('msg_type', msgType);
    fd.append('recipient_name', stuName);
    fd.append('roll_number', rollNo);
    fd.append('father_name', fatName);

    fetch('whatsapp.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(() => {
            const fb = document.getElementById('sendFeedback');
            fb.style.display = '';
            setTimeout(() => fb.style.display = 'none', 4000);
            // Refresh log table after short delay
            setTimeout(() => location.reload(), 2000);
        }).catch(() => {});
}

function copyMsg() {
    const txt = document.getElementById('waMessage').value;
    if (txt) { navigator.clipboard.writeText(txt).then(() => showToast('Message copied!', 'success')); }
}

function updateCount() {
    const len = document.getElementById('waMessage').value.length;
    document.getElementById('charCount').textContent = len + ' characters';
}

document.getElementById('waMessage').addEventListener('input', updateCount);

// Close student dropdown on outside click
document.addEventListener('click', e => {
    const wrap = document.getElementById('studentListWrap');
    if (!e.target.closest('#studentSearch') && !e.target.closest('#studentListWrap')) {
        wrap.style.display = 'none';
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
