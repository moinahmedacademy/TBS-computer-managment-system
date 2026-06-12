<?php
$pageTitle = 'Attendance';
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark') {
        $courseId = (int)$_POST['course_id'];
        $date     = $_POST['date'] ?? date('Y-m-d');
        $statuses = $_POST['status'] ?? [];

        foreach ($statuses as $studentId => $status) {
            $studentId = (int)$studentId;
            if (!in_array($status, ['present','absent','leave'])) continue;
            $existing = db()->fetchOne("SELECT id FROM attendance WHERE student_id=? AND course_id=? AND date=?", [$studentId,$courseId,$date]);
            if ($existing) {
                db()->execute("UPDATE attendance SET status=?,marked_by=? WHERE id=?", [$status,$_SESSION['user_id'],$existing['id']]);
            } else {
                db()->execute("INSERT INTO attendance (student_id,course_id,date,status,marked_by) VALUES (?,?,?,?,?)",
                    [$studentId,$courseId,$date,$status,$_SESSION['user_id']]);
            }
        }

        // WhatsApp alerts for absent students
        if (!empty($_POST['send_whatsapp'])) {
            $absentIds = [];
            foreach ($statuses as $sid => $st) { if ($st === 'absent') $absentIds[] = (int)$sid; }
            foreach ($absentIds as $sid) {
                $stu = db()->fetchOne("SELECT s.name, p.whatsapp, p.phone FROM students s LEFT JOIN parents p ON p.student_id=s.id WHERE s.id=?", [$sid]);
                $num = $stu ? ($stu['whatsapp'] ?: $stu['phone']) : '';
                if ($num) {
                    $msg = "Dear Parent, " . $stu['name'] . " was ABSENT on " . date('d M Y', strtotime($date)) . ". – The Brighten Stars Academy";
                    db()->execute("INSERT INTO whatsapp_logs (recipient_name,phone,message,sent_by) VALUES (?,?,?,?)",
                        [$stu['name'], $num, $msg, $_SESSION['user_id']]);
                }
            }
            flashMessage('info', 'Attendance saved. WhatsApp alerts logged for absent students.');
        } else {
            flashMessage('success', 'Attendance saved for ' . date('d M Y', strtotime($date)));
        }
        header("Location: attendance.php?course_id=$courseId&date=$date"); exit;
    }
}

$courses        = db()->fetchAll("SELECT id,name FROM courses WHERE status='active' ORDER BY name");
$selectedCourse = (int)($_GET['course_id'] ?? 0);
$selectedDate   = $_GET['date'] ?? date('Y-m-d');
$viewMode       = $_GET['view'] ?? 'daily';          // daily | monthly | full
$selectedMonth  = $_GET['month'] ?? date('Y-m');

$students = $existingAtt = [];
if ($selectedCourse) {
    $students = db()->fetchAll(
        "SELECT id,name,roll_number FROM students WHERE course_id=? AND status='active' ORDER BY name",
        [$selectedCourse]
    );
    $attRows = db()->fetchAll(
        "SELECT student_id, status FROM attendance WHERE course_id=? AND date=?",
        [$selectedCourse, $selectedDate]
    );
    foreach ($attRows as $a) $existingAtt[$a['student_id']] = $a['status'];
}

// Monthly summary (filtered by month)
$monthlySummary = [];
if ($selectedCourse && in_array($viewMode, ['monthly','full'])) {
    $monthFilter = $viewMode === 'monthly' ? " AND YEAR(a.date)=? AND MONTH(a.date)=?" : "";
    $monthParams = [$selectedCourse, $selectedCourse];
    if ($viewMode === 'monthly') {
        [$y, $m] = explode('-', $selectedMonth);
        $monthParams = [$selectedCourse, $selectedCourse, $y, $m];
    }
    $monthlySummary = db()->fetchAll(
        "SELECT s.id, s.name, s.roll_number,
            SUM(a.status='present') as present,
            SUM(a.status='absent')  as absent,
            SUM(a.status='leave')   as onleave,
            COUNT(a.id) as total,
            MIN(a.date) as first_date, MAX(a.date) as last_date
         FROM students s
         LEFT JOIN attendance a ON a.student_id=s.id AND a.course_id=? $monthFilter
         WHERE s.course_id=? AND s.status='active'
         GROUP BY s.id ORDER BY s.name",
        $monthParams
    );
}
?>

<div class="section-header">
    <div>
        <div class="section-title">Attendance Management</div>
        <div class="section-subtitle">Mark daily & view monthly attendance</div>
    </div>
    <?php if ($selectedCourse && $students): ?>
    <a href="whatsapp.php?type=attendance&course_id=<?= $selectedCourse ?>&date=<?= $selectedDate ?>"
       class="btn-primary-academy" style="background:rgba(37,211,102,.2);color:#25d366;border:1px solid rgba(37,211,102,.3)">
        <i class="bi bi-whatsapp me-1"></i> WhatsApp Alerts
    </a>
    <?php endif; ?>
</div>

<!-- Course & Date Filter -->
<div class="data-card mb-3">
    <div style="padding:.85rem 1rem">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label">Course</label>
                <select name="course_id" class="form-select" style="width:220px" onchange="this.form.submit()">
                    <option value="">Select Course</option>
                    <?php foreach ($courses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $selectedCourse==$c['id']?'selected':'' ?>><?= sanitize($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode) ?>">
            <?php if ($viewMode === 'daily' || $viewMode === ''): ?>
            <div>
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="<?= $selectedDate ?>" onchange="this.form.submit()">
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- View Mode Tabs -->
<?php if ($selectedCourse): ?>
<div class="d-flex gap-2 mb-3">
    <?php
    $tabs = ['daily'=>'Daily Mark','monthly'=>'Monthly View','full'=>'Full History (Admission to Now)'];
    foreach ($tabs as $mode => $label):
        $active = $viewMode === $mode;
        $url    = "attendance.php?course_id=$selectedCourse&view=$mode" . ($mode==='daily'?"&date=$selectedDate":'') . ($mode==='monthly'?"&month=$selectedMonth":'');
    ?>
    <a href="<?= $url ?>" class="btn-primary-academy"
       style="<?= $active?'':'background:var(--surface2);color:var(--text-muted);border:1px solid var(--border)' ?>;font-size:.82rem;padding:.4rem .9rem">
        <?= $active ? '<i class="bi bi-check-circle me-1"></i>' : '' ?><?= $label ?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($viewMode === 'monthly' && $selectedCourse): ?>
<!-- Month Picker -->
<div class="data-card mb-3">
    <div style="padding:.75rem 1rem">
        <form method="GET" class="d-flex gap-2 align-items-end">
            <input type="hidden" name="course_id" value="<?= $selectedCourse ?>">
            <input type="hidden" name="view" value="monthly">
            <div>
                <label class="form-label">Select Month</label>
                <input type="month" name="month" class="form-control" value="<?= $selectedMonth ?>" onchange="this.form.submit()">
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ── DAILY MARK ─────────────────────────────────────────────────────── -->
<?php if ($viewMode === 'daily' && $selectedCourse && $students): ?>
<div class="data-card mb-3">
    <div class="data-card-header">
        <div>
            <div class="data-card-title">Mark Attendance – <?= date('D, d M Y', strtotime($selectedDate)) ?></div>
            <div style="font-size:.78rem;color:var(--text-muted)"><?= count($students) ?> students</div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn-primary-academy" style="font-size:.8rem;padding:.4rem .8rem;background:rgba(16,185,129,.2);color:#10b981" onclick="markAll('present')">
                <i class="bi bi-check-all"></i> All Present
            </button>
            <button class="btn-primary-academy" style="font-size:.8rem;padding:.4rem .8rem;background:rgba(239,68,68,.2);color:#ef4444" onclick="markAll('absent')">
                <i class="bi bi-x-lg"></i> All Absent
            </button>
        </div>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="mark">
        <input type="hidden" name="course_id" value="<?= $selectedCourse ?>">
        <input type="hidden" name="date" value="<?= $selectedDate ?>">
        <div class="table-wrap">
            <table class="table-academy">
                <thead><tr><th>#</th><th>Student</th><th>Roll No</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($students as $i => $s):
                    $status = $existingAtt[$s['id']] ?? 'absent';
                ?>
                <tr>
                    <td style="color:var(--text-muted)"><?= $i+1 ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-circle" style="width:32px;height:32px;font-size:.8rem"><?= strtoupper(substr($s['name'],0,1)) ?></div>
                            <?= sanitize($s['name']) ?>
                        </div>
                    </td>
                    <td><span class="badge-academy badge-info"><?= sanitize($s['roll_number']) ?></span></td>
                    <td>
                        <div class="d-flex gap-2 att-radios" data-student="<?= $s['id'] ?>">
                            <?php foreach (['present'=>'Present','absent'=>'Absent','leave'=>'Leave'] as $val=>$label): ?>
                            <label class="att-label <?= $status===$val?'att-'.$val:'' ?>" style="cursor:pointer;padding:.3rem .8rem;border-radius:20px;font-size:.78rem;font-weight:500;border:1px solid var(--border);transition:all .15s">
                                <input type="radio" name="status[<?= $s['id'] ?>]" value="<?= $val ?>" <?= $status===$val?'checked':'' ?> style="display:none" onchange="updateAttLabel(this)">
                                <?= $label ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="padding:1rem;border-top:1px solid var(--border);display:flex;gap:.75rem;align-items:center">
            <label style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;color:var(--text-muted);cursor:pointer">
                <input type="checkbox" name="send_whatsapp" value="1" style="accent-color:#25d366">
                <i class="bi bi-whatsapp" style="color:#25d366"></i> Send WhatsApp alerts to absent students' parents
            </label>
            <button type="submit" class="btn-primary-academy ms-auto"
                onclick="return confirmSave(this.closest('form'),'Save attendance for <?= date('d M Y', strtotime($selectedDate)) ?>?')">
                <i class="bi bi-check-lg"></i> Save Attendance
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- ── MONTHLY / FULL SUMMARY TABLE ──────────────────────────────────── -->
<?php if (in_array($viewMode, ['monthly','full']) && $selectedCourse && $monthlySummary): ?>
<div class="data-card">
    <div class="data-card-header">
        <div>
            <div class="data-card-title">
                <?= $viewMode==='monthly' ? 'Attendance – ' . date('F Y', strtotime($selectedMonth.'-01')) : 'Full Attendance (Admission to Now)' ?>
            </div>
        </div>
        <button class="btn-primary-academy" style="font-size:.8rem;padding:.4rem .8rem" onclick="window.print()">
            <i class="bi bi-printer"></i> Print
        </button>
    </div>
    <div class="table-wrap">
        <table class="table-academy">
            <thead>
                <tr>
                    <th>Student</th><th>Roll No</th>
                    <th style="color:#10b981">Present</th>
                    <th style="color:#ef4444">Absent</th>
                    <th style="color:#f59e0b">Leave</th>
                    <th>Total Days</th>
                    <th>Attendance %</th>
                    <th>WhatsApp</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($monthlySummary as $ms):
                $pct   = $ms['total'] > 0 ? round(($ms['present'] / $ms['total']) * 100, 1) : 0;
                $color = $pct >= 75 ? '#10b981' : ($pct >= 50 ? '#f59e0b' : '#ef4444');
                $parent = db()->fetchOne("SELECT whatsapp, phone FROM parents WHERE student_id=?", [$ms['id']]);
                $waNum  = $parent ? ($parent['whatsapp'] ?: $parent['phone']) : '';
            ?>
            <tr>
                <td style="font-weight:500"><?= sanitize($ms['name']) ?></td>
                <td><span class="badge-academy badge-info"><?= sanitize($ms['roll_number']) ?></span></td>
                <td style="color:#10b981;font-weight:600"><?= $ms['present'] ?></td>
                <td style="color:#ef4444;font-weight:600"><?= $ms['absent'] ?></td>
                <td style="color:#f59e0b;font-weight:600"><?= $ms['onleave'] ?></td>
                <td><?= $ms['total'] ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:.5rem">
                        <div style="flex:1;height:6px;background:var(--surface3);border-radius:3px;min-width:60px">
                            <div style="width:<?= $pct ?>%;height:100%;background:<?= $color ?>;border-radius:3px"></div>
                        </div>
                        <strong style="color:<?= $color ?>;font-size:.85rem"><?= $pct ?>%</strong>
                    </div>
                </td>
                <td>
                    <?php if ($waNum): ?>
                    <button class="btn-icon btn-icon-wa" title="Send WhatsApp"
                        <?php $attMsg = "Dear Parent, " . $ms['name'] . "'s attendance in " . date('F Y', strtotime($selectedMonth.'-01')) . ": Present=" . $ms['present'] . ", Absent=" . $ms['absent'] . ", Total=" . $ms['total'] . " days (" . $pct . "%). – The Brighten Stars Academy"; ?>
                        onclick="openWhatsApp('<?= sanitize($waNum) ?>', <?= json_encode($attMsg) ?>)">
                        <i class="bi bi-whatsapp"></i>
                    </button>
                    <?php else: ?><span style="color:var(--text-muted);font-size:.75rem">—</span><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif ($selectedCourse && !$students): ?>
<div class="data-card" style="text-align:center;padding:3rem;color:var(--text-muted)">
    <i class="bi bi-people" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>No active students in this course.
</div>
<?php elseif (!$selectedCourse): ?>
<div class="data-card" style="text-align:center;padding:3rem;color:var(--text-muted)">
    <i class="bi bi-calendar-check" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>Please select a course to view attendance.
</div>
<?php endif; ?>

<style>
.att-label.att-present { background:rgba(16,185,129,.2);color:#10b981;border-color:rgba(16,185,129,.3) !important; }
.att-label.att-absent  { background:rgba(239,68,68,.2); color:#ef4444;border-color:rgba(239,68,68,.3)  !important; }
.att-label.att-leave   { background:rgba(245,158,11,.2);color:#f59e0b;border-color:rgba(245,158,11,.3) !important; }
</style>
<script>
function updateAttLabel(radio) {
    radio.closest('.att-radios').querySelectorAll('.att-label').forEach(l =>
        l.classList.remove('att-present','att-absent','att-leave'));
    radio.closest('.att-label').classList.add('att-' + radio.value);
}
function markAll(status) {
    document.querySelectorAll('input[type="radio"][value="' + status + '"]').forEach(r => {
        r.checked = true; updateAttLabel(r);
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
