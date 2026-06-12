<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/layout.php';

$att = getAttendancePercentage($studentId, $student['course_id'] ?? 0);
$totalTests = db()->fetchOne("SELECT COUNT(*) as c FROM test_results WHERE student_id=?", [$studentId])['c'] ?? 0;
$avgRow = db()->fetchOne("SELECT AVG(percentage) as avg FROM test_results WHERE student_id=?", [$studentId]);
$avgMarks = round($avgRow['avg'] ?? 0, 1);

$recentResults = db()->fetchAll(
    "SELECT tr.*, t.name as test_name, t.total_marks, t.date FROM test_results tr
     JOIN tests t ON tr.test_id=t.id WHERE tr.student_id=? ORDER BY t.date DESC LIMIT 5",
    [$studentId]
);

$announcements = db()->fetchAll(
    "SELECT * FROM announcements WHERE (target_audience='all' OR target_audience='parents')
     AND (expires_at IS NULL OR expires_at >= CURDATE()) ORDER BY is_pinned DESC, created_at DESC LIMIT 4"
);

$attColor = $att['percentage']>=75?'#10b981':($att['percentage']>=50?'#f59e0b':'#ef4444');
?>

<?php if ($student): ?>
<!-- Child Info -->
<div class="data-card mb-3" style="padding:1.25rem">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="avatar-circle" style="width:60px;height:60px;font-size:1.5rem;background:linear-gradient(135deg,#10b981,#059669)">
            <?= strtoupper(substr($student['name'],0,1)) ?>
        </div>
        <div style="flex:1">
            <div style="font-size:1.1rem;font-weight:700"><?= sanitize($student['name']) ?></div>
            <div style="font-size:.85rem;color:var(--text-muted)"><?= sanitize($student['course_name'] ?? 'N/A') ?></div>
            <div style="font-size:.82rem;color:var(--accent);font-weight:600"><?= sanitize($student['roll_number']) ?></div>
        </div>
        <a href="../admin/result_card.php?student_id=<?= $studentId ?>&course_id=<?= $student['course_id'] ?>" target="_blank"
            class="btn-primary-academy" style="font-size:.82rem;padding:.4rem .9rem">
            <i class="bi bi-file-earmark-person"></i> Result Card
        </a>
    </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.15);color:#10b981"><i class="bi bi-calendar-check"></i></div>
            <div class="stat-value" style="color:<?= $attColor ?>"><?= $att['percentage'] ?>%</div>
            <div class="stat-label">Attendance</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.15);color:#3b82f6"><i class="bi bi-pencil-square"></i></div>
            <div class="stat-value"><?= $totalTests ?></div>
            <div class="stat-label">Tests Taken</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.15);color:var(--accent)"><i class="bi bi-bar-chart"></i></div>
            <div class="stat-value"><?= $avgMarks ?>%</div>
            <div class="stat-label">Avg Marks</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.15);color:#10b981"><i class="bi bi-check-circle"></i></div>
            <div class="stat-value"><?= $att['present'] ?></div>
            <div class="stat-label">Days Present</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="data-card">
            <div class="data-card-header">
                <div class="data-card-title">Recent Results</div>
                <a href="results.php" style="font-size:.82rem;color:#10b981">View All →</a>
            </div>
            <div class="table-wrap">
                <table class="table-academy">
                    <thead><tr><th>Test</th><th>Date</th><th>Marks</th><th>Grade</th></tr></thead>
                    <tbody>
                    <?php if ($recentResults): foreach ($recentResults as $r):
                        $gc = 'grade-' . strtolower(str_replace('+','plus',$r['grade']));
                    ?>
                    <tr>
                        <td style="font-size:.88rem"><?= sanitize($r['test_name']) ?></td>
                        <td style="font-size:.8rem;color:var(--text-muted)"><?= formatDate($r['date']) ?></td>
                        <td><?= $r['obtained_marks'] ?>/<?= $r['total_marks'] ?></td>
                        <td><span class="<?= $gc ?>"><?= $r['grade'] ?></span></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:2rem">No results yet</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="data-card">
            <div class="data-card-header"><div class="data-card-title">Announcements</div></div>
            <div style="padding:.5rem">
            <?php foreach ($announcements as $ann):
                $colors = ['holiday'=>'danger','test'=>'warning','general'=>'secondary','notice'=>'warning','event'=>'success','course'=>'info'];
            ?>
            <div style="padding:.7rem .9rem;border-bottom:1px solid var(--border)">
                <div style="font-size:.85rem;font-weight:500"><?= sanitize($ann['title']) ?></div>
                <div style="font-size:.75rem;color:var(--text-muted)"><?= formatDate($ann['created_at'],'d M Y') ?></div>
            </div>
            <?php endforeach; if (!$announcements): ?>
            <p style="text-align:center;color:var(--text-muted);padding:2rem;font-size:.85rem">No announcements</p>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
