<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Auto-add columns (safe no-op if they exist)
db()->execute("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS remarks  VARCHAR(255) NULL");
db()->execute("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS is_late  TINYINT(1) NOT NULL DEFAULT 0");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'mark') {
        $courseId = (int)$_POST['course_id'];
        $date     = $_POST['date'] ?? date('Y-m-d');
        $statuses = $_POST['status']  ?? [];
        $remarks  = $_POST['remarks'] ?? [];
        $lates    = $_POST['late']    ?? [];

        foreach ($statuses as $studentId => $status) {
            $studentId = (int)$studentId;
            if (!in_array($status, ['present','absent','leave'])) continue;
            $remark = sanitize($remarks[$studentId] ?? '');
            $isLate = isset($lates[$studentId]) ? 1 : 0;
            $existing = db()->fetchOne(
                "SELECT id FROM attendance WHERE student_id=? AND course_id=? AND date=?",
                [$studentId, $courseId, $date]
            );
            if ($existing) {
                db()->execute(
                    "UPDATE attendance SET status=?,remarks=?,is_late=?,marked_by=? WHERE id=?",
                    [$status, $remark, $isLate, $_SESSION['user_id'], $existing['id']]
                );
            } else {
                db()->execute(
                    "INSERT INTO attendance (student_id,course_id,date,status,remarks,is_late,marked_by) VALUES (?,?,?,?,?,?,?)",
                    [$studentId, $courseId, $date, $status, $remark, $isLate, $_SESSION['user_id']]
                );
            }
        }
        flashMessage('success', 'Attendance saved for ' . date('d M Y', strtotime($date)));
        header("Location: attendance.php?course_id=$courseId&date=$date&view=daily"); exit;
    }
}

$pageTitle = 'Attendance';
require_once __DIR__ . '/layout.php';

// ── Filters ────────────────────────────────────────────────────────────────
$courses        = db()->fetchAll("SELECT id,name FROM courses WHERE status='active' ORDER BY name");
$selectedCourse = (int)($_GET['course_id'] ?? 0);
$selectedDate   = $_GET['date']  ?? date('Y-m-d');
$viewMode       = $_GET['view']  ?? 'daily';
$selectedMonth  = $_GET['month'] ?? date('Y-m');
$today          = date('Y-m-d');

// ── Dashboard stats (today, filtered by course if selected) ────────────────
$statCourse = $selectedCourse ? "AND s.course_id = $selectedCourse" : '';
$statCourseAtt = $selectedCourse ? "AND a.course_id = $selectedCourse" : '';

$totalStudents = (int)(db()->fetchOne(
    "SELECT COUNT(*) as c FROM students s WHERE s.status='active' $statCourse"
)['c'] ?? 0);

$todayRow = db()->fetchOne(
    "SELECT
        SUM(a.status='present') as present_today,
        SUM(a.status='absent')  as absent_today,
        SUM(a.status='leave')   as leave_today
     FROM attendance a
     JOIN students s ON s.id=a.student_id
     WHERE a.date=? AND s.status='active' $statCourseAtt",
    [$today]
);
$presentToday = (int)($todayRow['present_today'] ?? 0);
$absentToday  = (int)($todayRow['absent_today']  ?? 0);
$leaveToday   = (int)($todayRow['leave_today']   ?? 0);
$attPct       = $totalStudents > 0 ? round(($presentToday / $totalStudents) * 100, 1) : 0;
$pctColor     = $attPct >= 75 ? '#10b981' : ($attPct >= 50 ? '#f59e0b' : '#ef4444');

// ── Students + existing attendance for selected date ───────────────────────
$students = $existingAtt = [];
if ($selectedCourse) {
    $students = db()->fetchAll(
        "SELECT id, name, roll_number FROM students WHERE course_id=? AND status='active' ORDER BY name",
        [$selectedCourse]
    );
    $attRows = db()->fetchAll(
        "SELECT student_id, status, remarks, is_late FROM attendance WHERE course_id=? AND date=?",
        [$selectedCourse, $selectedDate]
    );
    foreach ($attRows as $a) $existingAtt[$a['student_id']] = $a;
}

// ── Monthly / Full summary ─────────────────────────────────────────────────
$monthlySummary = [];
if ($selectedCourse && in_array($viewMode, ['monthly','full'])) {
    $monthFilter = $viewMode === 'monthly' ? " AND YEAR(a.date)=? AND MONTH(a.date)=?" : "";
    $mp = [$selectedCourse, $selectedCourse];
    if ($viewMode === 'monthly') {
        [$y, $m] = explode('-', $selectedMonth);
        $mp = [$selectedCourse, $selectedCourse, (int)$y, (int)$m];
    }
    $monthlySummary = db()->fetchAll(
        "SELECT s.id, s.name, s.roll_number,
            COALESCE(SUM(a.status='present'),0) as present,
            COALESCE(SUM(a.status='absent'),0)  as absent,
            COALESCE(SUM(a.status='leave'),0)   as onleave,
            COALESCE(SUM(a.is_late=1),0)        as late_count,
            COUNT(a.id) as total
         FROM students s
         LEFT JOIN attendance a ON a.student_id=s.id AND a.course_id=? $monthFilter
         WHERE s.course_id=? AND s.status='active'
         GROUP BY s.id ORDER BY s.name",
        $mp
    );
}

$courseName = '';
if ($selectedCourse) {
    foreach ($courses as $c) { if ($c['id'] == $selectedCourse) { $courseName = $c['name']; break; } }
}
?>

<!-- ── Dashboard Stats ─────────────────────────────────────────────────── -->
<div class="section-header no-print">
    <div>
        <div class="section-title"><i class="bi bi-calendar-check me-2" style="color:var(--accent)"></i>Attendance Management</div>
        <div class="section-subtitle"><?= $selectedCourse ? sanitize($courseName) : 'All Courses' ?> · Today: <?= date('D, d M Y') ?></div>
    </div>
    <?php if ($selectedCourse && $viewMode !== 'daily'): ?>
    <button class="btn-primary-academy no-print" onclick="window.print()" style="gap:.4rem">
        <i class="bi bi-printer"></i> Print Report
    </button>
    <?php endif; ?>
</div>

<div class="row g-3 mb-3 no-print">
    <?php
    $stats = [
        ['label'=>'Total Students',     'value'=>$totalStudents, 'icon'=>'bi-people',          'color'=>'#6366f1', 'bg'=>'rgba(99,102,241,.15)'],
        ['label'=>'Present Today',      'value'=>$presentToday,  'icon'=>'bi-check-circle',    'color'=>'#10b981', 'bg'=>'rgba(16,185,129,.15)'],
        ['label'=>'Absent Today',       'value'=>$absentToday,   'icon'=>'bi-x-circle',        'color'=>'#ef4444', 'bg'=>'rgba(239,68,68,.15)'],
        ['label'=>'On Leave Today',     'value'=>$leaveToday,    'icon'=>'bi-clock-history',   'color'=>'#f59e0b', 'bg'=>'rgba(245,158,11,.15)'],
        ['label'=>'Attendance Today',   'value'=>$attPct.'%',    'icon'=>'bi-bar-chart-line',  'color'=>$pctColor,  'bg'=>'rgba(99,102,241,.1)'],
    ];
    foreach ($stats as $s): ?>
    <div class="col-6 col-md-4 col-xl">
        <div class="data-card" style="padding:1rem 1.1rem;display:flex;align-items:center;gap:.85rem">
            <div style="width:42px;height:42px;border-radius:10px;background:<?= $s['bg'] ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi <?= $s['icon'] ?>" style="font-size:1.2rem;color:<?= $s['color'] ?>"></i>
            </div>
            <div>
                <div style="font-size:1.35rem;font-weight:700;color:<?= $s['color'] ?>;line-height:1"><?= $s['value'] ?></div>
                <div style="font-size:.72rem;color:var(--text-muted);margin-top:.2rem"><?= $s['label'] ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Filters ─────────────────────────────────────────────────────────── -->
<div class="data-card mb-3 no-print">
    <div style="padding:.85rem 1rem">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label">Course</label>
                <select name="course_id" class="form-select" style="min-width:200px" onchange="this.form.submit()">
                    <option value="">Select Course</option>
                    <?php foreach ($courses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $selectedCourse==$c['id']?'selected':'' ?>><?= sanitize($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="view"  value="<?= htmlspecialchars($viewMode) ?>">
            <input type="hidden" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">
            <?php if ($viewMode === 'daily'): ?>
            <div>
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="<?= $selectedDate ?>" onchange="this.form.submit()">
            </div>
            <?php endif; ?>
            <?php if ($viewMode === 'monthly'): ?>
            <div>
                <label class="form-label">Month</label>
                <input type="month" name="month" class="form-control" value="<?= $selectedMonth ?>" onchange="this.form.submit()" style="min-width:160px">
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ── View Tabs ───────────────────────────────────────────────────────── -->
<?php if ($selectedCourse): ?>
<div class="d-flex gap-2 mb-3 no-print" style="flex-wrap:wrap">
    <?php
    $tabs = [
        'daily'   => ['Daily Attendance',  'bi-calendar-day'],
        'monthly' => ['Monthly Summary',   'bi-calendar-month'],
        'full'    => ['Full History',       'bi-clock-history'],
    ];
    foreach ($tabs as $mode => [$label, $icon]):
        $active = $viewMode === $mode;
        $url    = "attendance.php?course_id=$selectedCourse&view=$mode"
                . ($mode==='daily'   ? "&date=$selectedDate" : '')
                . ($mode==='monthly' ? "&month=$selectedMonth" : '');
    ?>
    <a href="<?= $url ?>" class="btn-primary-academy"
       style="font-size:.82rem;padding:.4rem .9rem;<?= $active ? '' : 'background:var(--surface2);color:var(--text-muted);border:1px solid var(--border)' ?>">
        <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
        <?php if ($active): ?><i class="bi bi-check-circle-fill ms-1" style="font-size:.7rem"></i><?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── DAILY ATTENDANCE MARKING ────────────────────────────────────────── -->
<?php if ($viewMode === 'daily' && $selectedCourse): ?>
<?php if ($students): ?>
<?php $alreadySaved = count($existingAtt) > 0; ?>
<div class="data-card no-print">
    <div class="data-card-header">
        <div>
            <div class="data-card-title">
                <i class="bi bi-pencil-square me-2" style="color:var(--accent)"></i>
                <?= $alreadySaved ? 'Edit Attendance' : 'Mark Attendance' ?> — <?= date('D, d M Y', strtotime($selectedDate)) ?>
            </div>
            <div style="font-size:.75rem;color:var(--text-muted);margin-top:.2rem">
                <?= count($students) ?> students
                <?php if ($alreadySaved): ?>
                · <span style="color:#f59e0b"><i class="bi bi-pencil me-1"></i>Previously saved — editing will update records</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn-primary-academy" style="font-size:.78rem;padding:.35rem .8rem;background:rgba(16,185,129,.15);color:#10b981;border:1px solid rgba(16,185,129,.25)" onclick="markAll('present')">
                <i class="bi bi-check-all"></i> All Present
            </button>
            <button type="button" class="btn-primary-academy" style="font-size:.78rem;padding:.35rem .8rem;background:rgba(239,68,68,.15);color:#ef4444;border:1px solid rgba(239,68,68,.25)" onclick="markAll('absent')">
                <i class="bi bi-x-lg"></i> All Absent
            </button>
        </div>
    </div>

    <form method="POST" id="attForm">
        <input type="hidden" name="action"    value="mark">
        <input type="hidden" name="course_id" value="<?= $selectedCourse ?>">
        <input type="hidden" name="date"      value="<?= $selectedDate ?>">
        <?= csrfField() ?>

        <div class="table-wrap">
            <table class="table-academy">
                <thead>
                    <tr>
                        <th style="width:36px">#</th>
                        <th>Student</th>
                        <th>Roll No</th>
                        <th>Status</th>
                        <th style="width:80px;text-align:center">Late</th>
                        <th>Remarks <span style="font-weight:400;color:var(--text-muted)">(optional)</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $i => $s):
                    $att    = $existingAtt[$s['id']] ?? null;
                    $status = $att['status']  ?? 'present';
                    $remark = $att['remarks'] ?? '';
                    $isLate = $att['is_late'] ?? 0;
                ?>
                <tr class="att-row">
                    <td style="color:var(--text-muted);font-size:.82rem"><?= $i+1 ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-circle" style="width:30px;height:30px;font-size:.78rem;flex-shrink:0"><?= strtoupper(substr($s['name'],0,1)) ?></div>
                            <span style="font-weight:500;font-size:.85rem"><?= sanitize($s['name']) ?></span>
                        </div>
                    </td>
                    <td><span class="badge-academy badge-info" style="font-size:.7rem"><?= sanitize($s['roll_number']) ?></span></td>
                    <td>
                        <div class="d-flex gap-1 att-radios" data-student="<?= $s['id'] ?>">
                            <?php foreach (['present'=>['Present','#10b981'],'absent'=>['Absent','#ef4444'],'leave'=>['Leave','#f59e0b']] as $val=>[$lbl,$clr]): ?>
                            <label class="att-pill att-pill-<?= $val ?> <?= $status===$val?'att-active-'.$val:'' ?>">
                                <input type="radio" name="status[<?= $s['id'] ?>]" value="<?= $val ?>" <?= $status===$val?'checked':'' ?> style="display:none" onchange="updatePill(this)">
                                <?= $lbl ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <td style="text-align:center">
                        <label class="late-toggle" title="Mark as Late">
                            <input type="checkbox" name="late[<?= $s['id'] ?>]" value="1" <?= $isLate?'checked':'' ?> onchange="updateLate(this)">
                            <span class="late-dot <?= $isLate?'late-on':'' ?>"><i class="bi bi-clock"></i></span>
                        </label>
                    </td>
                    <td>
                        <input type="text" name="remarks[<?= $s['id'] ?>]" class="form-control"
                               style="font-size:.78rem;padding:.3rem .6rem;min-width:160px"
                               placeholder="e.g. medical leave…" value="<?= sanitize($remark) ?>">
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="padding:.85rem 1rem;border-top:1px solid var(--border);display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
            <div style="font-size:.8rem;color:var(--text-muted)">
                <span id="presentCount" style="color:#10b981;font-weight:600">0</span> present ·
                <span id="absentCount"  style="color:#ef4444;font-weight:600">0</span> absent ·
                <span id="leaveCount"   style="color:#f59e0b;font-weight:600">0</span> leave
            </div>
            <button type="submit" class="btn-primary-academy ms-auto" style="padding:.5rem 1.4rem">
                <i class="bi bi-check-lg me-1"></i><?= $alreadySaved ? 'Update Attendance' : 'Save Attendance' ?>
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<div class="data-card no-print" style="text-align:center;padding:3rem;color:var(--text-muted)">
    <i class="bi bi-people" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>No active students in this course.
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ── MONTHLY / FULL SUMMARY ──────────────────────────────────────────── -->
<?php if (in_array($viewMode, ['monthly','full']) && $selectedCourse): ?>

<!-- Print header (only visible when printing) -->
<div class="print-only" style="display:none;margin-bottom:1.5rem">
    <h2 style="margin:0;font-size:1.3rem">The Brighten Stars Academy</h2>
    <p style="margin:.3rem 0 0;font-size:.9rem;color:#555">
        Attendance Report — <?= sanitize($courseName) ?> —
        <?= $viewMode==='monthly' ? date('F Y', strtotime($selectedMonth.'-01')) : 'Full History' ?>
    </p>
    <hr>
</div>

<?php if ($monthlySummary): ?>
<div class="data-card">
    <div class="data-card-header no-print">
        <div class="data-card-title">
            <i class="bi bi-table me-2" style="color:var(--accent)"></i>
            <?= $viewMode==='monthly'
                ? 'Monthly Summary — '.date('F Y', strtotime($selectedMonth.'-01'))
                : 'Full Attendance History' ?>
        </div>
        <div class="d-flex gap-2 no-print">
            <button class="btn-primary-academy" onclick="window.print()" style="font-size:.8rem;padding:.35rem .8rem">
                <i class="bi bi-printer me-1"></i>Print / PDF
            </button>
        </div>
    </div>

    <div class="table-wrap">
        <table class="table-academy" id="summaryTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Roll No</th>
                    <th style="color:#10b981">Present</th>
                    <th style="color:#ef4444">Absent</th>
                    <th style="color:#f59e0b">Leave</th>
                    <th style="color:#f97316">Late</th>
                    <th>Total Days</th>
                    <th>Attendance %</th>
                    <th class="no-print">WhatsApp</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($monthlySummary as $i => $ms):
                $pct    = $ms['total'] > 0 ? round(($ms['present'] / $ms['total']) * 100, 1) : 0;
                $clr    = $pct >= 75 ? '#10b981' : ($pct >= 50 ? '#f59e0b' : '#ef4444');
                $parent = db()->fetchOne("SELECT whatsapp, phone, name as pname FROM parents WHERE student_id=?", [$ms['id']]);
                $waNum  = $parent ? ($parent['whatsapp'] ?: $parent['phone']) : '';
                $pName  = $parent ? $parent['pname'] : 'Parent';
                $period = $viewMode === 'monthly'
                    ? date('F Y', strtotime($selectedMonth.'-01'))
                    : 'this period';
                $waMsg  = "Dear $pName,\n\nAttendance Report for ".$ms['name']." — $period\n\nPresent: ".$ms['present']." days\nAbsent: ".$ms['absent']." days\nLeave: ".$ms['onleave']." days\nTotal Days: ".$ms['total']."\nAttendance: $pct%\n\nThank you.\nThe Brighten Stars Academy";
            ?>
            <tr>
                <td style="color:var(--text-muted);font-size:.8rem"><?= $i+1 ?></td>
                <td style="font-weight:500;font-size:.85rem"><?= sanitize($ms['name']) ?></td>
                <td><span class="badge-academy badge-info" style="font-size:.68rem"><?= sanitize($ms['roll_number']) ?></span></td>
                <td style="color:#10b981;font-weight:700"><?= $ms['present'] ?></td>
                <td style="color:#ef4444;font-weight:700"><?= $ms['absent'] ?></td>
                <td style="color:#f59e0b;font-weight:700"><?= $ms['onleave'] ?></td>
                <td style="color:#f97316;font-size:.82rem"><?= $ms['late_count'] ?: '—' ?></td>
                <td style="font-size:.85rem"><?= $ms['total'] ?></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div style="flex:1;height:5px;background:var(--surface3);border-radius:3px;min-width:55px">
                            <div style="width:<?= $pct ?>%;height:100%;background:<?= $clr ?>;border-radius:3px;transition:width .3s"></div>
                        </div>
                        <strong style="color:<?= $clr ?>;font-size:.82rem;white-space:nowrap"><?= $pct ?>%</strong>
                    </div>
                </td>
                <td class="no-print">
                    <?php if ($waNum):
                        $cleanNum = preg_replace('/[^0-9]/', '', $waNum);
                        if ($cleanNum && $cleanNum[0] === '0') $cleanNum = '92'.substr($cleanNum,1);
                    ?>
                    <a href="https://wa.me/<?= $cleanNum ?>?text=<?= urlencode($waMsg) ?>"
                       target="_blank" class="btn-icon btn-icon-wa" title="Open WhatsApp with attendance report">
                        <i class="bi bi-whatsapp"></i>
                    </a>
                    <?php else: ?>
                    <span style="color:var(--text-muted);font-size:.75rem">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="data-card" style="text-align:center;padding:3rem;color:var(--text-muted)">
    <i class="bi bi-calendar-x" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>No attendance records found for this period.
</div>
<?php endif; ?>
<?php endif; ?>

<!-- No course selected -->
<?php if (!$selectedCourse): ?>
<div class="data-card no-print" style="text-align:center;padding:3.5rem;color:var(--text-muted)">
    <i class="bi bi-calendar-check" style="font-size:2.5rem;display:block;margin-bottom:.75rem;color:var(--accent);opacity:.5"></i>
    <div style="font-size:1rem;font-weight:500;margin-bottom:.35rem">Select a course to get started</div>
    <div style="font-size:.82rem">Choose a course from the filter above to mark or view attendance</div>
</div>
<?php endif; ?>

<!-- ── Styles ────────────────────────────────────────────────────────────── -->
<style>
/* Attendance pills */
.att-pill {
    cursor:pointer;padding:.28rem .7rem;border-radius:20px;font-size:.75rem;
    font-weight:500;border:1px solid var(--border);transition:all .15s;
    background:var(--surface2);color:var(--text-muted);user-select:none;
}
.att-pill:hover { background:var(--surface3); }
.att-active-present { background:rgba(16,185,129,.2)!important;color:#10b981!important;border-color:rgba(16,185,129,.35)!important; }
.att-active-absent  { background:rgba(239,68,68,.2)!important; color:#ef4444!important;border-color:rgba(239,68,68,.35)!important;  }
.att-active-leave   { background:rgba(245,158,11,.2)!important;color:#f59e0b!important;border-color:rgba(245,158,11,.35)!important; }

/* Late toggle */
.late-toggle { cursor:pointer;display:inline-flex;align-items:center }
.late-toggle input { display:none }
.late-dot { width:28px;height:28px;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;
    background:var(--surface2);border:1px solid var(--border);color:var(--text-muted);font-size:.8rem;transition:all .15s; }
.late-on  { background:rgba(249,115,22,.2)!important;border-color:rgba(249,115,22,.4)!important;color:#f97316!important; }

/* Print styles */
@media print {
    .no-print { display:none!important; }
    .print-only { display:block!important; }
    .data-card { box-shadow:none!important;border:1px solid #ddd!important;background:#fff!important; }
    body,td,th { color:#000!important; }
    th { background:#f5f5f5!important;color:#333!important; }
    .badge-academy { border:1px solid #ccc!important;background:#f0f0f0!important;color:#333!important; }
    .table-wrap { overflow:visible!important; }
    a[href]:after { content:none!important; }
}
@media (max-width:576px) {
    .att-radios { flex-wrap:wrap; }
}
</style>

<!-- ── Script ───────────────────────────────────────────────────────────── -->
<script>
function updatePill(radio) {
    const radios = radio.closest('.att-radios');
    radios.querySelectorAll('.att-pill').forEach(p =>
        p.classList.remove('att-active-present','att-active-absent','att-active-leave'));
    radio.closest('.att-pill').classList.add('att-active-' + radio.value);
    updateCounts();
}

function markAll(status) {
    document.querySelectorAll('input[type="radio"][value="' + status + '"]').forEach(r => {
        r.checked = true; updatePill(r);
    });
}

function updateLate(cb) {
    cb.nextElementSibling.classList.toggle('late-on', cb.checked);
}

function updateCounts() {
    const p = document.querySelectorAll('input[type="radio"][value="present"]:checked').length;
    const a = document.querySelectorAll('input[type="radio"][value="absent"]:checked').length;
    const l = document.querySelectorAll('input[type="radio"][value="leave"]:checked').length;
    const pc = document.getElementById('presentCount');
    const ac = document.getElementById('absentCount');
    const lc = document.getElementById('leaveCount');
    if (pc) pc.textContent = p;
    if (ac) ac.textContent = a;
    if (lc) lc.textContent = l;
}

// Init counts on load
document.addEventListener('DOMContentLoaded', updateCounts);
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
