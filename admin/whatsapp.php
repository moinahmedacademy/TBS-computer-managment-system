<?php
// ── All POST actions MUST run before layout.php outputs any HTML ──────────
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Ensure extra columns exist (safe to run every request — IF NOT EXISTS is a no-op)
db()->execute("ALTER TABLE whatsapp_logs ADD COLUMN IF NOT EXISTS recipient_name VARCHAR(150) NULL");
db()->execute("ALTER TABLE whatsapp_logs ADD COLUMN IF NOT EXISTS student_roll  VARCHAR(30)  NULL");
db()->execute("ALTER TABLE whatsapp_logs ADD COLUMN IF NOT EXISTS father_name   VARCHAR(150) NULL");
db()->execute("ALTER TABLE whatsapp_logs ADD COLUMN IF NOT EXISTS parent_name   VARCHAR(150) NULL");
db()->execute("ALTER TABLE whatsapp_logs ADD COLUMN IF NOT EXISTS course_name   VARCHAR(150) NULL");
db()->execute("ALTER TABLE whatsapp_logs ADD COLUMN IF NOT EXISTS batch_code    VARCHAR(80)  NULL");
db()->execute("ALTER TABLE whatsapp_logs ADD COLUMN IF NOT EXISTS class_timing  VARCHAR(30)  NULL");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'send';

    // Save custom template
    if ($action === 'save_template') {
        $tplTitle = sanitize($_POST['tpl_title'] ?? '');
        $tplBody  = sanitize($_POST['tpl_body']  ?? '');
        if ($tplTitle && $tplBody) {
            $existing = db()->fetchOne("SELECT setting_value as value FROM settings WHERE setting_key='wa_templates'");
            $templates = $existing ? json_decode($existing['value'], true) : [];
            $templates[] = ['title' => $tplTitle, 'body' => $tplBody, 'custom' => true];
            $val = json_encode($templates, JSON_UNESCAPED_UNICODE);
            if ($existing) {
                db()->execute("UPDATE settings SET setting_value=? WHERE setting_key='wa_templates'", [$val]);
            } else {
                db()->execute("INSERT INTO settings (setting_key,setting_value) VALUES ('wa_templates',?)", [$val]);
            }
            flashMessage('success', "Template \"$tplTitle\" saved.");
        }
        header('Location: whatsapp.php'); exit;
    }

    // Delete custom template
    if ($action === 'delete_template') {
        $idx = (int)($_POST['tpl_index'] ?? -1);
        $existing = db()->fetchOne("SELECT setting_value as value FROM settings WHERE setting_key='wa_templates'");
        $templates = $existing ? json_decode($existing['value'], true) : [];
        if (isset($templates[$idx])) {
            array_splice($templates, $idx, 1);
            db()->execute("UPDATE settings SET setting_value=? WHERE setting_key='wa_templates'", [json_encode($templates, JSON_UNESCAPED_UNICODE)]);
            flashMessage('success', 'Template deleted.');
        }
        header('Location: whatsapp.php'); exit;
    }

    // Delete selected log entries
    if ($action === 'delete_logs') {
        $ids = array_filter(array_map('intval', (array)($_POST['log_ids'] ?? [])));
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            db()->execute("DELETE FROM whatsapp_logs WHERE id IN ($placeholders)", $ids);
            flashMessage('success', count($ids) . ' log ' . (count($ids) === 1 ? 'entry' : 'entries') . ' deleted.');
        }
        header('Location: whatsapp.php'); exit;
    }

    // Log message (AJAX call from JS after WA window opens)
    if ($action === 'log') {
        $phone     = sanitize($_POST['phone'] ?? '');
        $message   = sanitize($_POST['message'] ?? '');
        $msgType   = sanitize($_POST['msg_type'] ?? 'custom');
        $recName   = sanitize($_POST['recipient_name'] ?? '');
        $rollNo    = sanitize($_POST['roll_number']    ?? '');
        $fatherN   = sanitize($_POST['father_name']    ?? '');
        $parentN   = sanitize($_POST['parent_name']    ?? '');
        $courseN   = sanitize($_POST['course_name']    ?? '');
        $batchC    = sanitize($_POST['batch_code']     ?? '');
        $timing    = sanitize($_POST['class_timing']   ?? '');

        if ($phone) {
            db()->execute(
                "INSERT INTO whatsapp_logs (sent_by,phone,message,message_type,status,recipient_name,student_roll,father_name,parent_name,course_name,batch_code,class_timing,sent_at)
                 VALUES (?,?,?,?,'sent',?,?,?,?,?,?,?,NOW())",
                [$_SESSION['user_id'], $phone, $message, $msgType, $recName, $rollNo, $fatherN, $parentN, $courseN, $batchC, $timing]
            );
            echo json_encode(['ok' => true]); exit;
        }
        echo json_encode(['ok' => false]); exit;
    }
}

$pageTitle = 'WhatsApp';
require_once __DIR__ . '/layout.php';

// Fetch data
$logs = db()->fetchAll(
    "SELECT w.*, u.name as sent_by_name
     FROM whatsapp_logs w LEFT JOIN users u ON w.sent_by=u.id
     ORDER BY w.sent_at DESC LIMIT 100"
);

$students = db()->fetchAll(
    "SELECT s.id, s.name, s.roll_number, s.batch, s.timing,
            c.name as course_name,
            p.name as parent_name, p.relation,
            p.whatsapp as parent_wa, p.phone as parent_phone
     FROM students s
     LEFT JOIN courses c ON s.course_id = c.id
     LEFT JOIN parents p ON p.student_id = s.id
     WHERE s.status='active'
     ORDER BY s.name"
);

// Built-in templates
$builtinTemplates = [
    ['type'=>'result',        'title'=>'Test Result',    'icon'=>'bi-bar-chart',              'color'=>'#3b82f6',
     'body' => "*The Brighten Stars Academy*\n\n*Test Result*\n\nDear {Parent Name},\nYour child {Student Name} (Roll: {Roll No}) has appeared in:\n\nTest: {Test Name}\nSubject: {Subject}\nMarks: {Obtained}/{Total}\nPercentage: {%}%\nGrade: {Grade}\n\nThank you.\n\nThe Brighten Stars Academy"],
    ['type'=>'attendance',    'title'=>'Absent Alert',   'icon'=>'bi-calendar-x',             'color'=>'#ef4444',
     'body' => "*The Brighten Stars Academy*\n\n*Absence Notice*\n\nDear {Parent Name},\nYour child {Student Name} (Roll: {Roll No}) was marked Absent on {Date}.\n\nPlease ensure regular attendance.\nContact us for any queries.\n\nThe Brighten Stars Academy"],
    ['type'=>'report',        'title'=>'Monthly Report', 'icon'=>'bi-file-earmark-bar-chart', 'color'=>'#10b981',
     'body' => "*The Brighten Stars Academy*\n\n*Monthly Report*\nMonth: {Month}\n\nStudent: {Student Name}\nRoll No: {Roll No}\nCourse: {Course}\n\nAttendance: {Present}/{Total} days ({Att%}%)\nTests Taken: {Tests}\nAverage Marks: {Average}%\nGrade: {Grade}\n\nTeacher Remarks: {Remarks}\n\nThe Brighten Stars Academy"],
    ['type'=>'holiday',       'title'=>'Holiday Notice', 'icon'=>'bi-calendar-event',         'color'=>'#f59e0b',
     'body' => "*The Brighten Stars Academy*\n\n*Holiday Notice*\n\nDear Parent / Student,\n\nThe academy will remain closed on {Date} due to {Reason}.\nClasses will resume on {Resume Date}.\n\nThank you.\n\nThe Brighten Stars Academy"],
    ['type'=>'fee',           'title'=>'Fee Reminder',   'icon'=>'bi-credit-card',            'color'=>'#8b5cf6',
     'body' => "*The Brighten Stars Academy*\n\n*Fee Reminder*\n\nDear {Parent Name},\nThis is a reminder that the fee for {Student Name} (Roll: {Roll No}) for {Month} is due.\n\nAmount: PKR {Amount}\nDue Date: {Due Date}\n\nPlease clear dues at the earliest.\n\nThe Brighten Stars Academy"],
    ['type'=>'admission',     'title'=>'New Admission',  'icon'=>'bi-person-plus',            'color'=>'#06b6d4',
     'body' => "*The Brighten Stars Academy*\n\n*Admission Confirmed*\n\nDear {Parent Name},\n\nWe are pleased to confirm that {Student Name} has been enrolled in:\n\nCourse: {Course}\nTiming: {Timing}\nRoll No: {Roll No}\nStart Date: {Start Date}\n\nWelcome to The Brighten Stars Academy."],
    ['type'=>'test_schedule', 'title'=>'Test Schedule',  'icon'=>'bi-pencil-square',          'color'=>'#f97316',
     'body' => "*The Brighten Stars Academy*\n\n*Upcoming Test*\n\nDear Parent / Student,\n\nTest Type: {Test Type}\nSubject: {Subject}\nDate: {Date}\nTime: {Time}\nSyllabus: {Syllabus}\n\nBest of luck.\n\nThe Brighten Stars Academy"],
    ['type'=>'announcement',  'title'=>'Announcement',   'icon'=>'bi-megaphone',              'color'=>'#ec4899',
     'body' => "*The Brighten Stars Academy*\n\n*Announcement*\n\n{Message}\n\nFor queries, please contact the academy.\n\nThank you.\nThe Brighten Stars Academy"],
];

// Load custom saved templates
$customRow = db()->fetchOne("SELECT setting_value as value FROM settings WHERE setting_key='wa_templates'");
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
    <div class="col-12 col-lg-4">
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
                     data-father="<?= sanitize($s['parent_name'] ?? '') ?>"
                     data-batch="<?= sanitize($s['batch'] ?? '') ?>"
                     data-timing="<?= sanitize($s['timing'] ?? '') ?>"
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
                <input type="hidden" id="waRollNo"      value="">
                <input type="hidden" id="waFatherName"  value="">
                <input type="hidden" id="waParentName"  value="">
                <input type="hidden" id="waCourseName"  value="">
                <input type="hidden" id="waBatchCode"   value="">
                <input type="hidden" id="waTiming"      value="">

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
                        <option value="holiday">Holiday Notice</option>
                        <option value="fee">Fee Reminder</option>
                        <option value="admission">New Admission</option>
                        <option value="test_schedule">Test Schedule</option>
                        <option value="announcement">Announcement</option>
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

    <!-- ── RIGHT: TEMPLATES ─────────────────────────────────────────────── -->
    <div class="col-12 col-lg-8">

        <!-- Templates -->
        <div class="data-card mb-3" style="padding:1.25rem">
            <div style="font-weight:700;font-size:.88rem;margin-bottom:.85rem;display:flex;align-items:center;gap:.5rem">
                <i class="bi bi-layout-text-window" style="color:var(--accent)"></i> Message Templates
                <span style="font-size:.72rem;color:var(--text-muted);font-weight:400">(click to use)</span>
            </div>
            <div class="row g-2">
                <?php foreach ($builtinTemplates as $tpl): ?>
                <div class="col-6 col-md-4 col-xl-3">
                    <div class="tpl-card use-tpl"
                         data-body="<?= htmlspecialchars($tpl['body'], ENT_QUOTES) ?>"
                         data-type="<?= $tpl['type'] ?>"
                         style="border-top:3px solid <?= $tpl['color'] ?>">
                        <i class="bi <?= $tpl['icon'] ?>" style="color:<?= $tpl['color'] ?>;font-size:1.1rem;margin-bottom:.3rem;display:block"></i>
                        <div style="font-size:.78rem;font-weight:600;line-height:1.3"><?= $tpl['title'] ?></div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php foreach ($customTemplates as $idx => $tpl): ?>
                <div class="col-6 col-md-4 col-xl-3" id="ctpl-<?= $idx ?>">
                    <div class="tpl-card use-tpl" data-body="<?= htmlspecialchars($tpl['body'], ENT_QUOTES) ?>"
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
                <div class="data-card-title">
                    <i class="bi bi-whatsapp me-2" style="color:#25d366"></i>Sent Messages Log
                </div>
                <span style="font-size:.75rem;color:var(--text-muted)"><?= count($logs) ?> message(s)</span>
            </div>

            <!-- Delete toolbar (hidden until a row is checked) -->
            <form method="POST" id="deleteLogsForm">
                <input type="hidden" name="action" value="delete_logs">
                <div id="deleteIdsContainer"></div>
            </form>
            <div id="logToolbar" style="display:none;padding:.6rem 1rem;background:rgba(239,68,68,.07);border-bottom:1px solid rgba(239,68,68,.2);align-items:center;gap:.75rem;flex-wrap:wrap">
                <i class="bi bi-check2-square" style="color:#ef4444"></i>
                <span id="selCount" style="font-size:.82rem;color:var(--text-muted);flex:1">0 selected</span>
                <button type="button" class="btn-icon btn-icon-delete" onclick="confirmDeleteLogs()"
                        style="width:auto;padding:.35rem .9rem;gap:.4rem;display:inline-flex;align-items:center;font-size:.82rem;font-weight:500">
                    <i class="bi bi-trash"></i> Delete Selected
                </button>
            </div>

            <div class="table-wrap" style="overflow-y:auto;max-height:480px">
                <table class="table-academy" id="logTable" style="width:100%;white-space:nowrap">
                    <thead style="position:sticky;top:0;z-index:2">
                        <tr>
                            <th style="width:36px">
                                <input type="checkbox" id="selectAll" class="form-check-input" title="Select all" style="cursor:pointer">
                            </th>
                            <th>Student</th>
                            <th>Parent / Phone</th>
                            <th>Course / Batch</th>
                            <th>Type</th>
                            <th>Message Preview</th>
                            <th>Sent</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($logs): foreach ($logs as $log): ?>
                    <tr>
                        <td style="text-align:center">
                            <input type="checkbox" class="form-check-input log-cb" value="<?= $log['id'] ?>" style="cursor:pointer">
                        </td>
                        <!-- Student name + roll -->
                        <td>
                            <?php if (!empty($log['recipient_name'])): ?>
                            <div style="font-weight:500;font-size:.83rem"><?= sanitize($log['recipient_name']) ?></div>
                            <?php if (!empty($log['student_roll'])): ?>
                            <div style="font-size:.72rem;color:var(--accent)"><?= sanitize($log['student_roll']) ?></div>
                            <?php endif; ?>
                            <?php else: ?>
                            <span style="color:var(--text-muted);font-size:.78rem">—</span>
                            <?php endif; ?>
                        </td>
                        <!-- Parent name + phone -->
                        <td>
                            <?php $parentLabel = !empty($log['parent_name']) ? $log['parent_name'] : (!empty($log['father_name']) ? $log['father_name'] : ''); ?>
                            <?php if ($parentLabel): ?>
                            <div style="font-size:.8rem;font-weight:500"><?= sanitize($parentLabel) ?></div>
                            <?php endif; ?>
                            <div style="font-size:.75rem;color:var(--text-muted)"><?= sanitize($log['phone']) ?></div>
                        </td>
                        <!-- Course + batch + timing -->
                        <td>
                            <?php if (!empty($log['course_name'])): ?>
                            <div style="font-size:.78rem;font-weight:500"><?= sanitize($log['course_name']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($log['batch_code'])): ?>
                            <div style="font-size:.72rem;color:var(--text-muted)"><?= sanitize($log['batch_code']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($log['class_timing'])): ?>
                            <div style="font-size:.72rem;color:var(--accent)"><?= sanitize($log['class_timing']) ?></div>
                            <?php endif; ?>
                            <?php if (empty($log['course_name']) && empty($log['batch_code']) && empty($log['class_timing'])): ?>
                            <span style="color:var(--text-muted);font-size:.78rem">—</span>
                            <?php endif; ?>
                        </td>
                        <!-- Type badge -->
                        <td>
                            <span class="badge-academy badge-info" style="font-size:.68rem"><?= ucfirst($log['message_type'] ?? 'custom') ?></span>
                        </td>
                        <!-- Message preview -->
                        <td style="white-space:normal;max-width:240px">
                            <div style="font-size:.75rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:230px"
                                 title="<?= sanitize($log['message'] ?? '') ?>">
                                <?= sanitize(substr($log['message'] ?? '', 0, 55)) ?><?= strlen($log['message'] ?? '') > 55 ? '…' : '' ?>
                            </div>
                        </td>
                        <!-- Sent time -->
                        <td style="font-size:.75rem;color:var(--text-muted);white-space:nowrap">
                            <?= !empty($log['sent_at']) ? date('d M, H:i', strtotime($log['sent_at'])) : '—' ?>
                            <?php if (!empty($log['sent_by_name'])): ?>
                            <div style="font-size:.68rem"><?= sanitize($log['sent_by_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <!-- Re-send -->
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
                    <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:2.5rem">
                        <i class="bi bi-chat-dots" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
                        No messages sent yet
                    </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div><!-- end row -->

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
    result:        <?= json_encode($builtinTemplates[0]['body']) ?>,
    attendance:    <?= json_encode($builtinTemplates[1]['body']) ?>,
    report:        <?= json_encode($builtinTemplates[2]['body']) ?>,
    holiday:       <?= json_encode($builtinTemplates[3]['body']) ?>,
    fee:           <?= json_encode($builtinTemplates[4]['body']) ?>,
    admission:     <?= json_encode($builtinTemplates[5]['body']) ?>,
    test_schedule: <?= json_encode($builtinTemplates[6]['body']) ?>,
    announcement:  <?= json_encode($builtinTemplates[7]['body']) ?>,
    custom:        ''
};

function loadTemplate(type) {
    if (builtinMap[type]) useTemplate(builtinMap[type]);
}

function useTemplate(text) {
    const name   = document.getElementById('waStudentName').value;
    const roll   = document.getElementById('waRollNo').value;
    const father = document.getElementById('waFatherName').value;
    const course = document.getElementById('waCourseName').value;
    const batch  = document.getElementById('waBatchCode').value;
    const timing = document.getElementById('waTiming').value;
    let msg = text;
    if (name)   { msg = msg.replace(/{Student Name}/g, name).replace(/{Name}/g, name); }
    if (roll)   { msg = msg.replace(/{Roll No}/g, roll); }
    if (father) { msg = msg.replace(/{Father Name}/g, father).replace(/{Parent Name}/g, father); }
    if (course) { msg = msg.replace(/{Course}/g, course); }
    if (batch)  { msg = msg.replace(/{Batch}/g, batch); }
    if (timing) { msg = msg.replace(/{Timing}/g, timing); }
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
    const father = el.dataset.father || el.dataset.parent || '';
    document.getElementById('waPhone').value       = el.dataset.phone  || '';
    document.getElementById('waStudentName').value = el.dataset.name   || '';
    document.getElementById('waRollNo').value      = el.dataset.roll   || '';
    document.getElementById('waFatherName').value  = father;
    document.getElementById('waParentName').value  = el.dataset.parent || '';
    document.getElementById('waCourseName').value  = el.dataset.course || '';
    document.getElementById('waBatchCode').value   = el.dataset.batch  || '';
    document.getElementById('waTiming').value      = el.dataset.timing || '';

    document.getElementById('chipText').textContent =
        el.dataset.name + ' · ' + el.dataset.roll
        + (el.dataset.course ? ' · ' + el.dataset.course : '')
        + (el.dataset.timing ? ' · ' + el.dataset.timing : '');
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
            .replace(/{Parent Name}/g, father)
            .replace(/{Father Name}/g, father)
            .replace(/{Course}/g,  el.dataset.course || '')
            .replace(/{Timing}/g,  el.dataset.timing || '')
            .replace(/{Batch}/g,   el.dataset.batch  || '');
        updateCount();
    }
}

function clearStudent() {
    ['waStudentName','waRollNo','waFatherName','waParentName','waCourseName','waBatchCode','waTiming','waPhone']
        .forEach(id => document.getElementById(id).value = '');
    document.getElementById('selectedChip').style.display = 'none';
}

// ── Send WhatsApp ─────────────────────────────────────────────────────────
function sendWhatsApp() {
    const phone   = document.getElementById('waPhone').value.trim();
    const message = document.getElementById('waMessage').value.trim();
    const msgType = document.getElementById('msgType').value;

    if (!phone)   { showToast('Please enter a WhatsApp number.', 'danger'); return; }
    if (!message) { showToast('Please write a message.', 'danger'); return; }

    // Capture all log fields BEFORE clearing the form
    const logData = {
        phone:          phone,
        message:        message,
        msg_type:       msgType,
        recipient_name: document.getElementById('waStudentName').value,
        roll_number:    document.getElementById('waRollNo').value,
        father_name:    document.getElementById('waFatherName').value,
        parent_name:    document.getElementById('waParentName').value,
        course_name:    document.getElementById('waCourseName').value,
        batch_code:     document.getElementById('waBatchCode').value,
        class_timing:   document.getElementById('waTiming').value,
    };

    // Clean phone number and open WhatsApp
    const clean = phone.replace(/[^0-9]/g, '');
    const num   = clean.startsWith('0') ? '92' + clean.slice(1) : clean;
    window.open('https://wa.me/' + num + '?text=' + encodeURIComponent(message), '_blank');

    // Ask if message was actually sent — only log if confirmed
    tbsConfirm(
        'Did you send the message in WhatsApp?',
        function() {
            // User confirmed — log it and reset form
            clearStudent();
            document.getElementById('waMessage').value = '';
            document.getElementById('msgType').value   = 'custom';
            updateCount();

            document.getElementById('sendFeedback').style.display = '';

            const fd = new FormData();
            fd.append('action', 'log');
            Object.entries(logData).forEach(([k, v]) => fd.append(k, v));

            fetch('whatsapp.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(() => location.reload())
                .catch(() => showToast('Logged locally but server save failed.', 'warning'));
        },
        {
            type:     'info',
            icon:     'bi-whatsapp',
            yesLabel: 'Yes, I Sent It',
            noLabel:  'No, I Did Not Send'
        }
    );
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

// Template cards click handler — also syncs the Message Type dropdown
document.querySelectorAll('.use-tpl').forEach(el => {
    el.addEventListener('click', function() {
        const type = this.dataset.type || 'custom';
        const sel  = document.getElementById('msgType');
        // Set dropdown if this type exists as an option
        if (sel.querySelector('option[value="' + type + '"]')) {
            sel.value = type;
        } else {
            sel.value = 'custom';
        }
        useTemplate(this.dataset.body);
    });
});

// Close student dropdown on outside click
document.addEventListener('click', e => {
    const wrap = document.getElementById('studentListWrap');
    if (!e.target.closest('#studentSearch') && !e.target.closest('#studentListWrap')) {
        wrap.style.display = 'none';
    }
});

// ── Delete selected log entries ───────────────────────────────────────────
function confirmDeleteLogs() {
    const checked = document.querySelectorAll('.log-cb:checked');
    if (!checked.length) return;
    const count = checked.length;
    tbsConfirm(
        'Delete ' + count + ' selected log ' + (count === 1 ? 'entry' : 'entries') + '? This cannot be undone.',
        function() {
            // Populate hidden inputs then submit
            const cont = document.getElementById('deleteIdsContainer');
            cont.innerHTML = '';
            checked.forEach(cb => {
                const inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = 'log_ids[]';
                inp.value = cb.value;
                cont.appendChild(inp);
            });
            document.getElementById('deleteLogsForm').submit();
        },
        { type: 'danger', icon: 'bi-trash', yesLabel: 'Yes, Delete' }
    );
}

// ── Log checkboxes ────────────────────────────────────────────────────────
const selectAll = document.getElementById('selectAll');
const toolbar   = document.getElementById('logToolbar');
const selCount  = document.getElementById('selCount');

function updateToolbar() {
    const checked = document.querySelectorAll('.log-cb:checked');
    if (checked.length > 0) {
        toolbar.style.display = 'flex';
        selCount.textContent  = checked.length + ' selected';
    } else {
        toolbar.style.display = 'none';
        if (selectAll) selectAll.checked = false;
    }
}

if (selectAll) {
    selectAll.addEventListener('change', function() {
        document.querySelectorAll('.log-cb').forEach(cb => cb.checked = this.checked);
        updateToolbar();
    });
}

document.querySelectorAll('.log-cb').forEach(cb => {
    cb.addEventListener('change', function() {
        const all  = document.querySelectorAll('.log-cb');
        const chkd = document.querySelectorAll('.log-cb:checked');
        selectAll.checked        = all.length === chkd.length;
        selectAll.indeterminate  = chkd.length > 0 && chkd.length < all.length;
        updateToolbar();
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
