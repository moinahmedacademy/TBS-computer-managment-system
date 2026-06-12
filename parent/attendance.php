<?php
$pageTitle = "Child's Attendance";
require_once __DIR__ . '/layout.php';

$courseId = $student['course_id'] ?? 0;
$selectedMonth = (int)($_GET['month'] ?? date('n'));
$selectedYear  = (int)($_GET['year']  ?? date('Y'));

$att      = getAttendancePercentage($studentId, $courseId, $selectedMonth, $selectedYear);
$allTime  = getAttendancePercentage($studentId, $courseId);
$records  = db()->fetchAll(
    "SELECT * FROM attendance WHERE student_id=? AND course_id=? AND MONTH(date)=? AND YEAR(date)=? ORDER BY date",
    [$studentId, $courseId, $selectedMonth, $selectedYear]
);
$attColor = $att['percentage']>=75?'#10b981':($att['percentage']>=50?'#f59e0b':'#ef4444');
?>

<div class="section-header">
    <div class="section-title"><?= sanitize($student['name'] ?? '') ?>'s Attendance</div>
</div>

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

<div class="row g-3 mb-3">
    <?php foreach (['Total Classes'=>[$att['total'],'#3b82f6'],'Present'=>[$att['present'],'#10b981'],'Absent'=>[$att['absent'],'#ef4444'],'Leave'=>[$att['leave'],'#f59e0b']] as $label=>[$val,$color]): ?>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-value" style="color:<?= $color ?>"><?= $val ?></div>
            <div class="stat-label"><?= $label ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Attendance % with alert -->
<?php if ($att['percentage'] < 75 && $att['total'] > 0): ?>
<div class="alert alert-warning mb-3">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Low Attendance Alert!</strong>
    Your child's attendance this month is <?= $att['percentage'] ?>%, which is below the required 75%.
</div>
<?php endif; ?>

<div class="data-card mb-3">
    <div class="data-card-header">
        <div class="data-card-title"><?= date('F Y', mktime(0,0,0,$selectedMonth,1,$selectedYear)) ?> – Daily Records</div>
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
                        <?= ucfirst($r['status']) ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:2.5rem;color:var(--text-muted)">No records for this period.</div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
