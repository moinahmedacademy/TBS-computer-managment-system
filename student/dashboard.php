<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/layout.php';

$studentId = $_SESSION['student_id'] ?? 0;
$student   = db()->fetchOne(
    "SELECT s.*, c.name as course_name, c.instructor FROM students s LEFT JOIN courses c ON s.course_id=c.id WHERE s.id=?",
    [$studentId]
);

if (!$student) { echo '<p style="color:red">Student profile not found. Contact admin.</p>'; require_once __DIR__ . '/../admin/footer.php'; exit; }

$attendance = getAttendancePercentage($student['id'], $student['course_id']);

// Recent test results
$recentResults = db()->fetchAll(
    "SELECT tr.*, t.name as test_name, t.total_marks, t.date FROM test_results tr
     JOIN tests t ON tr.test_id=t.id WHERE tr.student_id=? ORDER BY t.date DESC LIMIT 5",
    [$studentId]
);

// Total tests taken
$totalTests = db()->fetchOne(
    "SELECT COUNT(*) as c FROM test_results WHERE student_id=?", [$studentId]
)['c'] ?? 0;

// Recent announcements
$announcements = db()->fetchAll(
    "SELECT * FROM announcements WHERE (target_audience='all' OR target_audience='students')
     AND (expires_at IS NULL OR expires_at >= CURDATE())
     ORDER BY is_pinned DESC, created_at DESC LIMIT 5"
);

// Average marks
$avgRow = db()->fetchOne(
    "SELECT AVG(percentage) as avg FROM test_results WHERE student_id=?", [$studentId]
);
$avgMarks = round($avgRow['avg'] ?? 0, 1);
?>

<div style="margin-bottom:1.5rem">
    <div style="font-size:1.1rem;font-weight:700">Welcome back, <?= sanitize(explode(' ',$student['name'])[0]) ?>! 👋</div>
    <div style="font-size:.85rem;color:var(--text-muted)"><?= date('l, d F Y') ?></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.15);color:#3b82f6"><i class="bi bi-person-badge"></i></div>
            <div style="font-size:.78rem;color:var(--text-muted)">Roll Number</div>
            <div style="font-size:1.1rem;font-weight:700;color:#3b82f6"><?= sanitize($student['roll_number']) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.15);color:#10b981"><i class="bi bi-calendar-check"></i></div>
            <div style="font-size:.78rem;color:var(--text-muted)">Attendance</div>
            <div style="font-size:1.4rem;font-weight:700;color:<?= $attendance['percentage']>=75?'#10b981':($attendance['percentage']>=50?'#f59e0b':'#ef4444') ?>">
                <?= $attendance['percentage'] ?>%
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.15);color:#f59e0b"><i class="bi bi-bar-chart"></i></div>
            <div style="font-size:.78rem;color:var(--text-muted)">Avg Marks</div>
            <div style="font-size:1.4rem;font-weight:700;color:var(--accent)"><?= $avgMarks ?>%</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(139,92,246,.15);color:#8b5cf6"><i class="bi bi-pencil-square"></i></div>
            <div style="font-size:.78rem;color:var(--text-muted)">Tests Taken</div>
            <div style="font-size:1.4rem;font-weight:700"><?= $totalTests ?></div>
        </div>
    </div>
</div>

<!-- Course Info -->
<div class="row g-3 mb-3">
    <div class="col-12 col-lg-4">
        <div class="data-card" style="padding:1.25rem">
            <div style="font-size:.78rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.6px;font-weight:700;margin-bottom:1rem">
                My Course
            </div>
            <div class="stat-icon" style="background:rgba(59,130,246,.15);color:#3b82f6;width:52px;height:52px;font-size:1.4rem;margin-bottom:.75rem">
                <i class="bi bi-book"></i>
            </div>
            <div style="font-size:1rem;font-weight:700;margin-bottom:.25rem"><?= sanitize($student['course_name'] ?? 'N/A') ?></div>
            <?php if ($student['instructor']): ?>
            <div style="font-size:.82rem;color:var(--text-muted)"><i class="bi bi-person me-1"></i><?= sanitize($student['instructor']) ?></div>
            <?php endif; ?>
            <div style="font-size:.82rem;color:var(--text-muted);margin-top:.5rem"><i class="bi bi-people me-1"></i>Batch: <?= sanitize($student['batch'] ?: 'N/A') ?></div>
            <div style="font-size:.82rem;color:var(--text-muted)"><i class="bi bi-calendar3 me-1"></i>Enrolled: <?= formatDate($student['enrollment_date']) ?></div>

            <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border)">
                <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:.4rem">Attendance</div>
                <div style="display:flex;justify-content:space-between;margin-bottom:.3rem">
                    <span style="font-size:.82rem"><?= $attendance['present'] ?> Present</span>
                    <span style="font-size:.82rem"><?= $attendance['absent'] ?> Absent</span>
                </div>
                <div class="progress-bar-academy">
                    <div class="progress-bar-academy-fill" style="width:<?= $attendance['percentage'] ?>%;background:<?= $attendance['percentage']>=75?'#10b981':($attendance['percentage']>=50?'#f59e0b':'#ef4444') ?>"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="data-card">
            <div class="data-card-header">
                <div class="data-card-title">Recent Test Results</div>
                <a href="results.php" style="font-size:.82rem;color:#3b82f6">View All →</a>
            </div>
            <div class="table-wrap">
                <table class="table-academy">
                    <thead><tr><th>Test</th><th>Date</th><th>Marks</th><th>%</th><th>Grade</th></tr></thead>
                    <tbody>
                    <?php if ($recentResults): foreach ($recentResults as $r):
                        $gradeClass = 'grade-' . strtolower(str_replace('+','plus',$r['grade']));
                    ?>
                    <tr>
                        <td style="font-weight:500;font-size:.88rem"><?= sanitize($r['test_name']) ?></td>
                        <td style="font-size:.8rem;color:var(--text-muted)"><?= formatDate($r['date']) ?></td>
                        <td><?= $r['obtained_marks'] ?>/<?= $r['total_marks'] ?></td>
                        <td style="font-weight:600"><?= $r['percentage'] ?>%</td>
                        <td><span class="<?= $gradeClass ?>"><?= $r['grade'] ?></span></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:2rem">No test results yet</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Announcements -->
<div class="data-card">
    <div class="data-card-header">
        <div class="data-card-title"><i class="bi bi-megaphone me-2"></i>Announcements</div>
    </div>
    <div style="padding:.5rem">
    <?php if ($announcements): foreach ($announcements as $ann):
        $typeColors = ['holiday'=>'danger','test'=>'warning','course'=>'info','event'=>'success','notice'=>'warning','general'=>'secondary'];
        $color = $typeColors[$ann['type']] ?? 'secondary';
    ?>
    <div style="padding:.85rem 1rem;border-bottom:1px solid var(--border)">
        <div class="d-flex justify-content-between align-items-start gap-2">
            <div style="flex:1">
                <div style="font-size:.875rem;font-weight:500">
                    <?php if ($ann['is_pinned']): ?><i class="bi bi-pin-fill text-warning me-1"></i><?php endif; ?>
                    <?= sanitize($ann['title']) ?>
                </div>
                <div style="font-size:.8rem;color:var(--text-muted);margin-top:.25rem"><?= nl2br(sanitize(substr($ann['content'],0,120))) ?><?= strlen($ann['content'])>120?'...':'' ?></div>
                <div style="font-size:.72rem;color:var(--text-muted);margin-top:.35rem"><?= formatDate($ann['created_at'],'d M Y') ?></div>
            </div>
            <span class="badge-academy badge-<?= $color ?>" style="font-size:.68rem;white-space:nowrap"><?= ucfirst($ann['type']) ?></span>
        </div>
    </div>
    <?php endforeach; else: ?>
    <p style="text-align:center;color:var(--text-muted);padding:2rem;font-size:.85rem">No announcements</p>
    <?php endif; ?>
    </div>
</div>

<?php
$footer = <<<'PHP'
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/academy/assets/js/admin.js"></script>
</body>
</html>
PHP;
echo $footer;
?>
