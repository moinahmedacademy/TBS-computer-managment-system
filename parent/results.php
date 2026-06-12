<?php
$pageTitle = "Child's Results";
require_once __DIR__ . '/layout.php';

$courseId = $student['course_id'] ?? 0;
$results = db()->fetchAll(
    "SELECT tr.*, t.name as test_name, t.total_marks, t.test_type, t.date, t.subject
     FROM test_results tr JOIN tests t ON tr.test_id=t.id
     WHERE tr.student_id=? ORDER BY t.date DESC",
    [$studentId]
);
$totalObtained = array_sum(array_column($results,'obtained_marks'));
$totalPossible = array_sum(array_column($results,'total_marks'));
$overall = $totalPossible > 0 ? round(($totalObtained/$totalPossible)*100,2) : 0;
?>

<div class="section-header">
    <div class="section-title"><?= sanitize($student['name'] ?? '') ?>'s Results</div>
    <a href="../admin/result_card.php?student_id=<?= $studentId ?>&course_id=<?= $courseId ?>" target="_blank" class="btn-primary-academy">
        <i class="bi bi-file-earmark-person"></i> Download Result Card
    </a>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="stat-card"><div class="stat-value" style="color:var(--accent)"><?= getGrade($overall) ?></div><div class="stat-label">Overall Grade</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-value"><?= count($results) ?></div><div class="stat-label">Tests Taken</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-value" style="color:#10b981"><?= $overall ?>%</div><div class="stat-label">Overall %</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-value"><?= $totalObtained ?>/<?= $totalPossible ?></div><div class="stat-label">Total Marks</div></div></div>
</div>

<div class="data-card">
    <div class="table-wrap">
        <table class="table-academy">
            <thead><tr><th>Test</th><th>Subject</th><th>Type</th><th>Date</th><th>Marks</th><th>%</th><th>Grade</th><th>Position</th></tr></thead>
            <tbody>
            <?php if ($results): foreach ($results as $r):
                $gc = 'grade-' . strtolower(str_replace('+','plus',$r['grade']));
            ?>
            <tr>
                <td style="font-weight:500"><?= sanitize($r['test_name']) ?></td>
                <td style="font-size:.82rem;color:var(--text-muted)"><?= sanitize($r['subject'] ?: '—') ?></td>
                <td><span class="badge-academy badge-info" style="font-size:.7rem"><?= ucfirst($r['test_type']) ?></span></td>
                <td style="font-size:.82rem"><?= formatDate($r['date']) ?></td>
                <td style="font-weight:600"><?= $r['obtained_marks'] ?>/<?= $r['total_marks'] ?></td>
                <td><?= $r['percentage'] ?>%</td>
                <td><span class="<?= $gc ?>"><?= $r['grade'] ?></span></td>
                <td><?= $r['position'] ? '#'.$r['position'] : '—' ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:3rem">No results yet</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
