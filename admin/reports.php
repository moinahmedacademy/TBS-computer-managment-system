<?php
$pageTitle = 'Reports';
require_once __DIR__ . '/layout.php';

// Generate Monthly Report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_monthly') {
    $courseId  = (int)$_POST['course_id'];
    $month     = (int)$_POST['month'];
    $year      = (int)$_POST['year'];
    $remarks   = $_POST['remarks'] ?? [];

    $students = db()->fetchAll("SELECT id FROM students WHERE course_id=? AND status='active'", [$courseId]);
    foreach ($students as $s) {
        $sid = $s['id'];
        $att = getAttendancePercentage($sid, $courseId, $month, $year);

        $testResults = db()->fetchAll(
            "SELECT tr.obtained_marks, t.total_marks FROM test_results tr
             JOIN tests t ON tr.test_id=t.id
             WHERE tr.student_id=? AND t.course_id=? AND MONTH(t.date)=? AND YEAR(t.date)=?",
            [$sid, $courseId, $month, $year]
        );

        $totalObtained = array_sum(array_column($testResults, 'obtained_marks'));
        $totalPossible = array_sum(array_column($testResults, 'total_marks'));
        $avgMarks      = count($testResults) > 0 && $totalPossible > 0
            ? round(($totalObtained / $totalPossible) * 100, 2) : 0;
        $overallGrade  = getGrade(($att['percentage'] + $avgMarks) / 2);

        $existing = db()->fetchOne(
            "SELECT id FROM monthly_reports WHERE student_id=? AND course_id=? AND month=? AND year=?",
            [$sid, $courseId, $month, $year]
        );

        $remark = sanitize($remarks[$sid] ?? '');
        if ($existing) {
            db()->execute(
                "UPDATE monthly_reports SET total_classes=?,present_days=?,absent_days=?,leave_days=?,attendance_percentage=?,tests_taken=?,average_marks=?,overall_grade=?,teacher_remarks=?,generated_by=? WHERE id=?",
                [$att['total'],$att['present'],$att['absent'],$att['leave'],$att['percentage'],count($testResults),$avgMarks,$overallGrade,$remark,$_SESSION['user_id'],$existing['id']]
            );
        } else {
            db()->execute(
                "INSERT INTO monthly_reports (student_id,course_id,month,year,total_classes,present_days,absent_days,leave_days,attendance_percentage,tests_taken,average_marks,overall_grade,teacher_remarks,generated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$sid,$courseId,$month,$year,$att['total'],$att['present'],$att['absent'],$att['leave'],$att['percentage'],count($testResults),$avgMarks,$overallGrade,$remark,$_SESSION['user_id']]
            );
        }
    }
    flashMessage('success', "Monthly reports generated for " . count($students) . " students.");
    header("Location: reports.php?view_month=$month&view_year=$year&view_course=$courseId");
    exit;
}

$courses = db()->fetchAll("SELECT id,name FROM courses WHERE status='active' ORDER BY name");

// View reports
$viewMonth  = (int)($_GET['view_month'] ?? date('n'));
$viewYear   = (int)($_GET['view_year']  ?? date('Y'));
$viewCourse = (int)($_GET['view_course'] ?? 0);

$reports = [];
if ($viewCourse) {
    $reports = db()->fetchAll(
        "SELECT mr.*, s.name, s.roll_number FROM monthly_reports mr
         JOIN students s ON mr.student_id=s.id
         WHERE mr.course_id=? AND mr.month=? AND mr.year=?
         ORDER BY mr.average_marks DESC",
        [$viewCourse, $viewMonth, $viewYear]
    );
}
?>

<div class="section-header">
    <div>
        <div class="section-title">Monthly Reports</div>
        <div class="section-subtitle">Generate and view student performance reports</div>
    </div>
</div>

<div class="row g-3 mb-3">
    <!-- Generate -->
    <div class="col-12 col-lg-5">
        <div class="data-card" style="padding:1.5rem">
            <div class="form-section-title"><i class="bi bi-gear"></i> Generate Monthly Reports</div>
            <form method="POST">
                <input type="hidden" name="action" value="generate_monthly">
                <div class="mb-3">
                    <label class="form-label">Course</label>
                    <select name="course_id" class="form-select" required>
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">Month</label>
                        <select name="month" class="form-select">
                            <?php for ($m=1; $m<=12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m==date('n')?'selected':'' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Year</label>
                        <select name="year" class="form-select">
                            <?php for ($y=date('Y'); $y>=date('Y')-2; $y--): ?>
                            <option value="<?= $y ?>"><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-primary-academy w-100">
                    <i class="bi bi-file-earmark-text"></i> Generate Reports
                </button>
            </form>
        </div>
    </div>

    <!-- View Filter -->
    <div class="col-12 col-lg-7">
        <div class="data-card" style="padding:1.5rem">
            <div class="form-section-title"><i class="bi bi-eye"></i> View Reports</div>
            <form method="GET" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Course</label>
                    <select name="view_course" class="form-select">
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $viewCourse==$c['id']?'selected':'' ?>><?= sanitize($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Month</label>
                    <select name="view_month" class="form-select">
                        <?php for ($m=1; $m<=12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m==$viewMonth?'selected':'' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Year</label>
                    <select name="view_year" class="form-select">
                        <?php for ($y=date('Y'); $y>=date('Y')-2; $y--): ?>
                        <option value="<?= $y ?>" <?= $y==$viewYear?'selected':'' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn-primary-academy w-100"><i class="bi bi-search"></i></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($viewCourse && $reports): ?>
<!-- Reports Table -->
<div class="data-card">
    <div class="data-card-header">
        <div>
            <div class="data-card-title">
                <?= getMonthName($viewMonth) ?> <?= $viewYear ?> Reports
            </div>
            <div style="font-size:.78rem;color:var(--text-muted)"><?= count($reports) ?> students</div>
        </div>
        <button class="btn-primary-academy" onclick="window.print()" style="font-size:.8rem;padding:.4rem .8rem">
            <i class="bi bi-printer"></i> Print
        </button>
    </div>
    <div class="table-wrap">
        <table class="table-academy" id="reportsTable">
            <thead>
                <tr>
                    <th>Pos</th>
                    <th>Student</th>
                    <th>Attendance</th>
                    <th>Present/Total</th>
                    <th>Tests</th>
                    <th>Avg Marks</th>
                    <th>Grade</th>
                    <th>Remarks</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($reports as $i => $r):
                $attColor = $r['attendance_percentage'] >= 75 ? '#10b981' : ($r['attendance_percentage'] >= 50 ? '#f59e0b' : '#ef4444');
                $grade = $r['overall_grade'];
                $gradeClass = 'grade-' . strtolower(str_replace('+','plus',$grade));
            ?>
            <tr>
                <td style="font-weight:700;color:var(--accent);font-size:1rem"><?= $i+1 ?></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-circle" style="width:30px;height:30px;font-size:.75rem"><?= strtoupper(substr($r['name'],0,1)) ?></div>
                        <div>
                            <div style="font-weight:500;font-size:.88rem"><?= sanitize($r['name']) ?></div>
                            <div style="font-size:.72rem;color:var(--text-muted)"><?= sanitize($r['roll_number']) ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <div style="display:flex;align-items:center;gap:.5rem">
                        <div style="font-weight:600;color:<?= $attColor ?>"><?= $r['attendance_percentage'] ?>%</div>
                        <div class="progress-bar-academy" style="width:60px">
                            <div class="progress-bar-academy-fill" style="width:<?= $r['attendance_percentage'] ?>%;background:<?= $attColor ?>"></div>
                        </div>
                    </div>
                </td>
                <td style="font-size:.82rem"><?= $r['present_days'] ?>/<?= $r['total_classes'] ?></td>
                <td style="font-size:.82rem"><?= $r['tests_taken'] ?></td>
                <td style="font-weight:600"><?= $r['average_marks'] ?>%</td>
                <td><span class="<?= $gradeClass ?>"><?= $grade ?></span></td>
                <td style="font-size:.8rem;color:var(--text-muted)"><?= sanitize($r['teacher_remarks'] ?: '—') ?></td>
                <td>
                    <a href="result_card.php?student_id=<?= $r['student_id'] ?>&course_id=<?= $viewCourse ?>&month=<?= $viewMonth ?>&year=<?= $viewYear ?>"
                        target="_blank" class="btn-icon btn-icon-view" title="View Result Card">
                        <i class="bi bi-file-earmark-person"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($viewCourse && !$reports): ?>
<div class="data-card" style="text-align:center;padding:3rem;color:var(--text-muted)">
    No reports found for the selected period. <a href="#" onclick="document.querySelector('form[method=POST]').submit()" style="color:var(--accent)">Generate now?</a>
</div>
<?php elseif (!$viewCourse): ?>
<div class="data-card" style="text-align:center;padding:3rem;color:var(--text-muted)">
    <i class="bi bi-file-earmark-bar-graph" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
    Select a course and period to view reports.
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
