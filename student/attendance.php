<?php
$pageTitle = 'My Attendance';
require_once __DIR__ . '/layout.php';

$studentId = $_SESSION['student_id'] ?? 0;
$student   = db()->fetchOne("SELECT * FROM students WHERE id=?", [$studentId]);
$courseId  = $student['course_id'] ?? 0;

$selectedMonth = (int)($_GET['month'] ?? date('n'));
$selectedYear  = (int)($_GET['year']  ?? date('Y'));

$attendance = getAttendancePercentage($studentId, $courseId, $selectedMonth, $selectedYear);
$allTime    = getAttendancePercentage($studentId, $courseId);

$records = db()->fetchAll(
    "SELECT * FROM attendance WHERE student_id=? AND course_id=? AND MONTH(date)=? AND YEAR(date)=? ORDER BY date",
    [$studentId, $courseId, $selectedMonth, $selectedYear]
);

$attColor = $attendance['percentage']>=75?'#10b981':($attendance['percentage']>=50?'#f59e0b':'#ef4444');
?>

<div class="section-header">
    <div class="section-title">My Attendance</div>
</div>

<!-- Filter -->
<form method="GET" class="data-card mb-3" style="padding:.75rem 1rem">
    <div class="d-flex flex-wrap gap-2">
        <select name="month" class="form-select" style="width:140px" onchange="this.form.submit()">
            <?php for ($m=1; $m<=12; $m++): ?>
            <option value="<?= $m ?>" <?= $m==$selectedMonth?'selected':'' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
            <?php endfor; ?>
        </select>
        <select name="year" class="form-select" style="width:100px" onchange="this.form.submit()">
            <?php for ($y=date('Y'); $y>=date('Y')-2; $y--): ?>
            <option value="<?= $y ?>" <?= $y==$selectedYear?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </div>
</form>

<!-- Stats -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.15);color:#3b82f6"><i class="bi bi-calendar3"></i></div>
            <div class="stat-value"><?= $attendance['total'] ?></div>
            <div class="stat-label">Total Classes</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.15);color:#10b981"><i class="bi bi-check-circle"></i></div>
            <div class="stat-value" style="color:#10b981"><?= $attendance['present'] ?></div>
            <div class="stat-label">Present</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,68,68,.15);color:#ef4444"><i class="bi bi-x-circle"></i></div>
            <div class="stat-value" style="color:#ef4444"><?= $attendance['absent'] ?></div>
            <div class="stat-label">Absent</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.15);color:var(--accent)"><i class="bi bi-calendar-x"></i></div>
            <div class="stat-value" style="color:<?= $attColor ?>"><?= $attendance['percentage'] ?>%</div>
            <div class="stat-label">This Month</div>
        </div>
    </div>
</div>

<!-- Calendar view -->
<div class="data-card mb-3">
    <div class="data-card-header">
        <div class="data-card-title"><?= date('F Y', mktime(0,0,0,$selectedMonth,1,$selectedYear)) ?> – Attendance Details</div>
    </div>
    <?php if ($records): ?>
    <div class="table-wrap">
        <table class="table-academy">
            <thead><tr><th>Date</th><th>Day</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($records as $r): ?>
            <tr>
                <td><?= formatDate($r['date']) ?></td>
                <td style="color:var(--text-muted)"><?= date('l', strtotime($r['date'])) ?></td>
                <td>
                    <span class="badge-academy <?= $r['status']==='present'?'badge-success':($r['status']==='leave'?'badge-warning':'badge-danger') ?>">
                        <i class="bi bi-<?= $r['status']==='present'?'check-circle':($r['status']==='leave'?'calendar-x':'x-circle') ?> me-1"></i>
                        <?= ucfirst($r['status']) ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:2.5rem;color:var(--text-muted)">No attendance records for this period.</div>
    <?php endif; ?>
</div>

<!-- All Time -->
<div class="data-card">
    <div class="data-card-header"><div class="data-card-title">All-Time Summary</div></div>
    <div style="padding:1.25rem">
        <div class="row g-3">
            <div class="col-md-4">
                <div style="text-align:center">
                    <div style="font-size:2.5rem;font-weight:800;color:<?= $allTime['percentage']>=75?'#10b981':($allTime['percentage']>=50?'#f59e0b':'#ef4444') ?>">
                        <?= $allTime['percentage'] ?>%
                    </div>
                    <div style="font-size:.82rem;color:var(--text-muted)">Overall Attendance</div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="row g-2">
                    <?php foreach (['Total'=>$allTime['total'],'Present'=>$allTime['present'],'Absent'=>$allTime['absent'],'Leave'=>$allTime['leave']] as $label=>$val): ?>
                    <div class="col-6">
                        <div style="background:var(--surface2);border-radius:8px;padding:.75rem;text-align:center">
                            <div style="font-size:1.4rem;font-weight:700"><?= $val ?></div>
                            <div style="font-size:.75rem;color:var(--text-muted)"><?= $label ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
