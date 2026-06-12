<?php
$pageTitle = 'Monthly Reports';
require_once __DIR__ . '/layout.php';

$courseId = $student['course_id'] ?? 0;
$selectedMonth = (int)($_GET['month'] ?? date('n'));
$selectedYear  = (int)($_GET['year']  ?? date('Y'));

$report = db()->fetchOne(
    "SELECT * FROM monthly_reports WHERE student_id=? AND course_id=? AND month=? AND year=?",
    [$studentId, $courseId, $selectedMonth, $selectedYear]
);
?>

<div class="section-header">
    <div class="section-title">Monthly Performance Report</div>
    <?php if ($report): ?>
    <a href="../admin/result_card.php?student_id=<?= $studentId ?>&course_id=<?= $courseId ?>&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>"
        target="_blank" class="btn-primary-academy">
        <i class="bi bi-file-earmark-pdf"></i> Download PDF
    </a>
    <?php endif; ?>
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

<?php if ($report): ?>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-4"><div class="stat-card"><div class="stat-value" style="color:var(--accent)"><?= $report['overall_grade'] ?></div><div class="stat-label">Overall Grade</div></div></div>
    <div class="col-6 col-md-4"><div class="stat-card"><div class="stat-value" style="color:#10b981"><?= $report['attendance_percentage'] ?>%</div><div class="stat-label">Attendance</div></div></div>
    <div class="col-6 col-md-4"><div class="stat-card"><div class="stat-value"><?= $report['average_marks'] ?>%</div><div class="stat-label">Avg Marks</div></div></div>
</div>

<div class="data-card" style="padding:1.5rem">
    <div class="form-section-title"><i class="bi bi-file-earmark-text"></i> <?= getMonthName($selectedMonth) ?> <?= $selectedYear ?> Report</div>

    <div class="row g-3">
        <div class="col-md-6">
            <div style="background:var(--surface2);border-radius:10px;padding:1rem">
                <div style="font-size:.8rem;color:var(--text-muted);margin-bottom.5rem;text-transform:uppercase;letter-spacing:.5px;font-weight:700">Attendance</div>
                <?php foreach ([
                    ['Total Classes',$report['total_classes'],'#3b82f6'],
                    ['Present',$report['present_days'],'#10b981'],
                    ['Absent',$report['absent_days'],'#ef4444'],
                    ['Leave',$report['leave_days'],'#f59e0b'],
                    ['Percentage',$report['attendance_percentage'].'%','var(--accent)'],
                ] as [$label,$val,$color]): ?>
                <div style="display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid var(--border)">
                    <span style="color:var(--text-muted);font-size:.85rem"><?= $label ?></span>
                    <strong style="color:<?= $color ?>"><?= $val ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-md-6">
            <div style="background:var(--surface2);border-radius:10px;padding:1rem">
                <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.5px;font-weight:700">Performance</div>
                <?php foreach ([
                    ['Tests Taken',$report['tests_taken'],'var(--text)'],
                    ['Average Marks',$report['average_marks'].'%','#10b981'],
                    ['Overall Grade',$report['overall_grade'],'var(--accent)'],
                ] as [$label,$val,$color]): ?>
                <div style="display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid var(--border)">
                    <span style="color:var(--text-muted);font-size:.85rem"><?= $label ?></span>
                    <strong style="color:<?= $color ?>;font-size:<?= $label==='Overall Grade'?'1.2rem':'inherit' ?>"><?= $val ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if ($report['teacher_remarks']): ?>
        <div class="col-12">
            <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:10px;padding:1rem">
                <div style="font-size:.78rem;color:var(--accent);font-weight:700;margin-bottom:.5rem;text-transform:uppercase">Teacher Remarks</div>
                <p style="margin:0;font-size:.88rem"><?= nl2br(sanitize($report['teacher_remarks'])) ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="data-card" style="text-align:center;padding:3rem;color:var(--text-muted)">
    <i class="bi bi-file-earmark-text" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
    No report generated for <?= getMonthName($selectedMonth) ?> <?= $selectedYear ?> yet.<br>
    <small>Contact the institute to request the monthly report.</small>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
