<?php
$pageTitle = 'Results';
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_marks') {
        $testId  = (int)$_POST['test_id'];
        $marks   = $_POST['marks'] ?? [];
        $remarks = $_POST['remarks'] ?? [];

        $test = db()->fetchOne("SELECT total_marks FROM tests WHERE id=?", [$testId]);
        if (!$test) { flashMessage('danger','Invalid test.'); header('Location: results.php'); exit; }

        foreach ($marks as $studentId => $obtained) {
            $studentId = (int)$studentId;
            $obtained  = min((float)$obtained, $test['total_marks']);
            $pct       = round(($obtained / $test['total_marks']) * 100, 2);
            $grade     = getGrade($pct);
            $remark    = sanitize($remarks[$studentId] ?? '');

            $existing = db()->fetchOne("SELECT id FROM test_results WHERE test_id=? AND student_id=?", [$testId, $studentId]);
            if ($existing) {
                db()->execute(
                    "UPDATE test_results SET obtained_marks=?,percentage=?,grade=?,teacher_remarks=?,entered_by=? WHERE id=?",
                    [$obtained, $pct, $grade, $remark, $_SESSION['user_id'], $existing['id']]
                );
            } else {
                db()->execute(
                    "INSERT INTO test_results (test_id,student_id,obtained_marks,percentage,grade,teacher_remarks,entered_by) VALUES (?,?,?,?,?,?,?)",
                    [$testId, $studentId, $obtained, $pct, $grade, $remark, $_SESSION['user_id']]
                );
            }
        }
        calculatePositions($testId);
        flashMessage('success', 'Marks saved and positions calculated.');
        header("Location: results.php?test_id=$testId");
        exit;
    }
}

$testId = (int)($_GET['test_id'] ?? 0);
$tests  = db()->fetchAll("SELECT t.*, c.name as course_name FROM tests t JOIN courses c ON t.course_id=c.id ORDER BY t.date DESC");

$currentTest = null;
$students = [];
$existingResults = [];

if ($testId) {
    $currentTest = db()->fetchOne("SELECT t.*, c.name as course_name FROM tests t JOIN courses c ON t.course_id=c.id WHERE t.id=?", [$testId]);
    if ($currentTest) {
        $students = db()->fetchAll(
            "SELECT s.id, s.name, s.roll_number FROM students s WHERE s.course_id=? AND s.status='active' ORDER BY s.name",
            [$currentTest['course_id']]
        );
        $results = db()->fetchAll("SELECT * FROM test_results WHERE test_id=?", [$testId]);
        foreach ($results as $r) $existingResults[$r['student_id']] = $r;
    }
}
?>

<div class="section-header">
    <div>
        <div class="section-title">Results & Marks</div>
        <div class="section-subtitle">Enter and manage test results</div>
    </div>
    <?php if ($currentTest): ?>
    <a href="reports.php?generate=result_card&course_id=<?= $currentTest['course_id'] ?>&test_id=<?= $testId ?>" class="btn-primary-academy">
        <i class="bi bi-file-earmark-pdf"></i> Generate Cards
    </a>
    <?php endif; ?>
</div>

<!-- Test Selector -->
<div class="data-card mb-3">
    <div style="padding:.85rem 1rem">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label">Select Test</label>
                <select name="test_id" class="form-select" style="width:320px" onchange="this.form.submit()">
                    <option value="">Choose a test...</option>
                    <?php foreach ($tests as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $testId==$t['id']?'selected':'' ?>>
                        <?= sanitize($t['name']) ?> – <?= sanitize($t['course_name']) ?> (<?= formatDate($t['date']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($currentTest && $students): ?>
<!-- Test Info -->
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="stat-card">
            <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:.25rem">Test</div>
            <div style="font-size:.95rem;font-weight:600"><?= sanitize($currentTest['name']) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:.25rem">Course</div>
            <div style="font-size:.95rem;font-weight:600"><?= sanitize($currentTest['course_name']) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:.25rem">Total Marks</div>
            <div style="font-size:1.4rem;font-weight:700;color:var(--accent)"><?= $currentTest['total_marks'] ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:.25rem">Students</div>
            <div style="font-size:1.4rem;font-weight:700"><?= count($students) ?></div>
        </div>
    </div>
</div>

<!-- Marks Entry -->
<div class="data-card">
    <div class="data-card-header">
        <div class="data-card-title">Enter Marks</div>
        <div style="font-size:.78rem;color:var(--text-muted)">Grading: A+(90+) A(80+) B(70+) C(60+) D(50+) F(&lt;50)</div>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="save_marks">
        <input type="hidden" name="test_id" value="<?= $testId ?>">
        <div class="table-wrap">
            <table class="table-academy">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Roll No</th>
                        <th>Marks / <?= $currentTest['total_marks'] ?></th>
                        <th>%</th>
                        <th>Grade</th>
                        <th>Position</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $i => $s):
                    $r = $existingResults[$s['id']] ?? null;
                    $obtained = $r ? $r['obtained_marks'] : '';
                    $pct      = $r ? $r['percentage'] : '';
                    $grade    = $r ? $r['grade'] : '';
                    $pos      = $r ? $r['position'] : '';
                    $remark   = $r ? $r['teacher_remarks'] : '';
                ?>
                <tr id="row_<?= $s['id'] ?>">
                    <td style="color:var(--text-muted)"><?= $i+1 ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-circle" style="width:30px;height:30px;font-size:.75rem"><?= strtoupper(substr($s['name'],0,1)) ?></div>
                            <?= sanitize($s['name']) ?>
                        </div>
                    </td>
                    <td><span class="badge-academy badge-info"><?= sanitize($s['roll_number']) ?></span></td>
                    <td style="width:120px">
                        <input type="number" name="marks[<?= $s['id'] ?>]" class="form-control marks-input"
                            data-student="<?= $s['id'] ?>" data-total="<?= $currentTest['total_marks'] ?>"
                            value="<?= $obtained ?>" min="0" max="<?= $currentTest['total_marks'] ?>"
                            step="0.5" placeholder="0" style="width:100px"
                            oninput="calcGrade(this)">
                    </td>
                    <td id="pct_<?= $s['id'] ?>" style="font-weight:600;color:var(--accent)"><?= $pct ? $pct.'%' : '—' ?></td>
                    <td id="grade_<?= $s['id'] ?>">
                        <?php if ($grade): ?>
                        <span class="grade-<?= strtolower(str_replace('+','plus',$grade)) ?>"><?= $grade ?></span>
                        <?php else: ?><span style="color:var(--text-muted)">—</span><?php endif; ?>
                    </td>
                    <td id="pos_<?= $s['id'] ?>" style="font-weight:600"><?= $pos ?: '—' ?></td>
                    <td>
                        <input type="text" name="remarks[<?= $s['id'] ?>]" class="form-control" value="<?= sanitize($remark) ?>" placeholder="Optional remark" style="width:160px">
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="padding:1rem;border-top:1px solid var(--border)">
            <button type="submit" class="btn-primary-academy"><i class="bi bi-save"></i> Save Marks & Calculate Positions</button>
        </div>
    </form>
</div>

<?php elseif ($currentTest && !$students): ?>
<div class="data-card" style="text-align:center;padding:3rem;color:var(--text-muted)">
    No students enrolled in this course.
</div>
<?php elseif (!$testId): ?>
<div class="data-card" style="text-align:center;padding:3rem;color:var(--text-muted)">
    <i class="bi bi-bar-chart" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
    Select a test to enter marks.
</div>
<?php endif; ?>

<script>
const gradeMap = [
    [90,'A+','grade-aplus'],
    [80,'A','grade-a'],
    [70,'B','grade-b'],
    [60,'C','grade-c'],
    [50,'D','grade-d'],
    [0,'F','grade-f']
];

function calcGrade(input) {
    const sid = input.dataset.student;
    const total = parseFloat(input.dataset.total);
    const obtained = parseFloat(input.value);

    if (isNaN(obtained) || input.value === '') {
        document.getElementById('pct_' + sid).textContent = '—';
        document.getElementById('grade_' + sid).innerHTML = '<span style="color:var(--text-muted)">—</span>';
        return;
    }

    const pct = Math.round((obtained / total) * 1000) / 10;
    document.getElementById('pct_' + sid).textContent = pct + '%';

    let grade = 'F', cls = 'grade-f';
    for (const [min, g, c] of gradeMap) {
        if (pct >= min) { grade = g; cls = c; break; }
    }
    document.getElementById('grade_' + sid).innerHTML = `<span class="${cls}">${grade}</span>`;
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
