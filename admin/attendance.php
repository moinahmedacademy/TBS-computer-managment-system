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

            $existing = db()->fetchOne("SELECT id FROM attendance WHERE student_id=? AND course_id=? AND date=?", [$studentId, $courseId, $date]);
            if ($existing) {
                db()->execute("UPDATE attendance SET status=?, marked_by=? WHERE id=?", [$status, $_SESSION['user_id'], $existing['id']]);
            } else {
                db()->execute("INSERT INTO attendance (student_id,course_id,date,status,marked_by) VALUES (?,?,?,?,?)",
                    [$studentId, $courseId, $date, $status, $_SESSION['user_id']]);
            }
        }
        flashMessage('success', 'Attendance saved for ' . formatDate($date));
        header("Location: attendance.php?course_id=$courseId&date=$date");
        exit;
    }
}

$courses = db()->fetchAll("SELECT id,name FROM courses WHERE status='active' ORDER BY name");
$selectedCourse = (int)($_GET['course_id'] ?? ($_POST['course_id'] ?? 0));
$selectedDate   = $_GET['date'] ?? date('Y-m-d');

$students = [];
$existing = [];

if ($selectedCourse) {
    $students = db()->fetchAll(
        "SELECT id,name,roll_number FROM students WHERE course_id=? AND status='active' ORDER BY name",
        [$selectedCourse]
    );
    $att = db()->fetchAll(
        "SELECT student_id, status FROM attendance WHERE course_id=? AND date=?",
        [$selectedCourse, $selectedDate]
    );
    foreach ($att as $a) $existing[$a['student_id']] = $a['status'];
}

// Monthly summary
$monthlySummary = [];
if ($selectedCourse) {
    $monthlySummary = db()->fetchAll(
        "SELECT s.name, s.roll_number,
            SUM(a.status='present') as present,
            SUM(a.status='absent') as absent,
            SUM(a.status='leave') as onleave,
            COUNT(a.id) as total
         FROM students s
         LEFT JOIN attendance a ON a.student_id=s.id AND a.course_id=?
         WHERE s.course_id=? AND s.status='active'
         GROUP BY s.id ORDER BY s.name",
        [$selectedCourse, $selectedCourse]
    );
}
?>

<div class="section-header">
    <div>
        <div class="section-title">Attendance Management</div>
        <div class="section-subtitle">Mark and track student attendance</div>
    </div>
</div>

<!-- Filter -->
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
            <div>
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="<?= $selectedDate ?>" onchange="this.form.submit()">
            </div>
        </form>
    </div>
</div>

<?php if ($selectedCourse && $students): ?>
<!-- Mark Attendance -->
<div class="data-card mb-3">
    <div class="data-card-header">
        <div>
            <div class="data-card-title">Mark Attendance – <?= formatDate($selectedDate) ?></div>
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
                    $status = $existing[$s['id']] ?? 'absent';
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
        <div style="padding:1rem;border-top:1px solid var(--border)">
            <button type="submit" class="btn-primary-academy"><i class="bi bi-check-lg"></i> Save Attendance</button>
        </div>
    </form>
</div>

<!-- Monthly Summary -->
<div class="data-card">
    <div class="data-card-header">
        <div class="data-card-title">Attendance Summary (All Time)</div>
    </div>
    <div class="table-wrap">
        <table class="table-academy">
            <thead><tr><th>Student</th><th>Roll No</th><th>Present</th><th>Absent</th><th>Leave</th><th>Total</th><th>%</th></tr></thead>
            <tbody>
            <?php foreach ($monthlySummary as $ms):
                $pct = $ms['total'] > 0 ? round(($ms['present'] / $ms['total']) * 100, 1) : 0;
                $color = $pct >= 75 ? '#10b981' : ($pct >= 50 ? '#f59e0b' : '#ef4444');
            ?>
            <tr>
                <td><?= sanitize($ms['name']) ?></td>
                <td><span class="badge-academy badge-info"><?= sanitize($ms['roll_number']) ?></span></td>
                <td style="color:#10b981;font-weight:600"><?= $ms['present'] ?></td>
                <td style="color:#ef4444;font-weight:600"><?= $ms['absent'] ?></td>
                <td style="color:#f59e0b;font-weight:600"><?= $ms['onleave'] ?></td>
                <td><?= $ms['total'] ?></td>
                <td><strong style="color:<?= $color ?>"><?= $pct ?>%</strong></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($selectedCourse && !$students): ?>
<div class="data-card" style="text-align:center;padding:3rem;color:var(--text-muted)">
    <i class="bi bi-people" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
    No active students in this course.
</div>
<?php else: ?>
<div class="data-card" style="text-align:center;padding:3rem;color:var(--text-muted)">
    <i class="bi bi-calendar-check" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
    Please select a course to mark attendance.
</div>
<?php endif; ?>

<style>
.att-label.att-present { background:rgba(16,185,129,.2); color:#10b981; border-color:rgba(16,185,129,.3) !important; }
.att-label.att-absent  { background:rgba(239,68,68,.2);  color:#ef4444; border-color:rgba(239,68,68,.3)  !important; }
.att-label.att-leave   { background:rgba(245,158,11,.2); color:#f59e0b; border-color:rgba(245,158,11,.3) !important; }
</style>
<script>
function updateAttLabel(radio) {
    const wrap = radio.closest('.att-radios');
    wrap.querySelectorAll('.att-label').forEach(l => {
        l.classList.remove('att-present','att-absent','att-leave');
    });
    const label = radio.closest('.att-label');
    label.classList.add('att-' + radio.value);
}
function markAll(status) {
    document.querySelectorAll('input[type="radio"][value="' + status + '"]').forEach(r => {
        r.checked = true;
        updateAttLabel(r);
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
