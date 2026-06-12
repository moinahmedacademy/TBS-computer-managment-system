<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
requireLogin();

$studentId = (int)($_GET['student_id'] ?? 0);
$courseId  = (int)($_GET['course_id']  ?? 0);
$month     = (int)($_GET['month'] ?? 0) ?: null;
$year      = (int)($_GET['year']  ?? 0) ?: null;

if (!$studentId || !$courseId) {
    die('Invalid parameters.');
}

// Students can only view their own card
if ($_SESSION['user_role'] === 'student' && ($_SESSION['student_id'] ?? 0) !== $studentId) {
    die('Access denied.');
}
if ($_SESSION['user_role'] === 'parent' && ($_SESSION['student_id'] ?? 0) !== $studentId) {
    die('Access denied.');
}

$card = generateResultCard($studentId, $courseId, $month, $year);
if (!$card) die('Unable to generate result card.');

$s = $card['student'];
$instituteName = getSetting('institute_name') ?: SITE_NAME;
$monthLabel    = $month ? getMonthName($month) . ' ' . $year : 'All Time';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Result Card – <?= sanitize($s['name']) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
@media print {
    .no-print { display: none !important; }
    body { background: white !important; }
    .card-wrap { box-shadow: none !important; border: 2px solid #333 !important; }
}
body { background: #1a1a2e; font-family: 'Segoe UI', sans-serif; padding: 2rem; }
.card-wrap {
    background: white;
    max-width: 780px;
    margin: 0 auto;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,.4);
    overflow: hidden;
}
.card-header-section {
    background: linear-gradient(135deg, #0a0a0f, #1a1a2f);
    color: white;
    padding: 2rem;
    text-align: center;
    position: relative;
}
.card-header-section::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #f59e0b, #d97706);
}
.inst-name { font-size: 1.4rem; font-weight: 800; letter-spacing: .5px; }
.card-title { font-size: .8rem; color: #f59e0b; text-transform: uppercase; letter-spacing: 2px; margin-top: .25rem; }
.result-badge {
    display: inline-block;
    padding: .5rem 1.5rem;
    border-radius: 30px;
    font-size: 2rem;
    font-weight: 900;
    margin-top: 1rem;
}
.card-body-section { padding: 1.5rem 2rem; }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
.info-item { border-bottom: 1px solid #eee; padding-bottom: .5rem; }
.info-label { font-size: .7rem; color: #888; text-transform: uppercase; font-weight: 600; letter-spacing: .5px; }
.info-value { font-size: .9rem; font-weight: 600; color: #333; margin-top: .1rem; }
table { width: 100%; border-collapse: collapse; }
th { background: #f8f9fa; padding: .6rem .8rem; text-align: left; font-size: .75rem; text-transform: uppercase; color: #666; border-bottom: 2px solid #dee2e6; }
td { padding: .6rem .8rem; border-bottom: 1px solid #f0f0f0; font-size: .85rem; color: #333; }
.summary-row { background: linear-gradient(90deg, #fff8e7, #fffbf0); font-weight: 700; }
.att-bar { height: 8px; background: #eee; border-radius: 4px; overflow: hidden; display: inline-block; width: 80px; vertical-align: middle; margin-left: .5rem; }
.att-bar-fill { height: 100%; border-radius: 4px; }
.footer-note { text-align: center; font-size: .75rem; color: #999; padding-top: 1rem; border-top: 1px solid #eee; margin-top: 1.5rem; }
.grade-good { color: #10b981; }
.grade-ok { color: #f59e0b; }
.grade-fail { color: #ef4444; }
</style>
</head>
<body>
<div class="no-print mb-3" style="max-width:780px;margin:0 auto;display:flex;gap:.5rem">
    <button onclick="window.print()" style="background:#f59e0b;border:none;color:#000;font-weight:600;padding:.5rem 1.2rem;border-radius:8px;cursor:pointer">
        🖨️ Print / Save PDF
    </button>
    <button onclick="history.back()" style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:white;padding:.5rem 1rem;border-radius:8px;cursor:pointer">
        ← Back
    </button>
</div>

<div class="card-wrap">
    <!-- Header -->
    <div class="card-header-section">
        <div style="font-size:2.5rem;margin-bottom:.5rem">⭐</div>
        <div class="inst-name"><?= sanitize($instituteName) ?></div>
        <div class="card-title">Student Result Card</div>

        <?php
        $gradeColors = ['A+'=>'#10b981','A'=>'#3b82f6','B'=>'#8b5cf6','C'=>'#f59e0b','D'=>'#f97316','F'=>'#ef4444'];
        $gc = $gradeColors[$card['grade']] ?? '#888';
        ?>
        <div class="result-badge" style="background:rgba(255,255,255,.1);color:<?= $gc ?>;border:3px solid <?= $gc ?>">
            Grade: <?= $card['grade'] ?>
        </div>
        <div style="font-size:.85rem;color:rgba(255,255,255,.6);margin-top:.5rem"><?= $monthLabel ?></div>
    </div>

    <!-- Body -->
    <div class="card-body-section">
        <!-- Student Info -->
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Student Name</div>
                <div class="info-value"><?= sanitize($s['name']) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Roll Number</div>
                <div class="info-value" style="color:#f59e0b"><?= sanitize($s['roll_number']) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Course</div>
                <div class="info-value"><?= sanitize($s['course_name']) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Father's Name</div>
                <div class="info-value"><?= sanitize($s['father_name'] ?: 'N/A') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Attendance</div>
                <div class="info-value">
                    <?php $attPct = $card['attendance']['percentage']; $attColor = $attPct>=75?'#10b981':($attPct>=50?'#f59e0b':'#ef4444'); ?>
                    <span style="color:<?= $attColor ?>"><?= $attPct ?>%</span>
                    (<?= $card['attendance']['present'] ?>/<?= $card['attendance']['total'] ?> days)
                    <span class="att-bar"><span class="att-bar-fill" style="width:<?= $attPct ?>%;background:<?= $attColor ?>"></span></span>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Overall Percentage</div>
                <div class="info-value" style="color:<?= $gc ?>;font-size:1.1rem"><?= $card['percentage'] ?>%</div>
            </div>
        </div>

        <!-- Test Results -->
        <?php if ($card['results']): ?>
        <h6 style="font-size:.8rem;text-transform:uppercase;letter-spacing:1px;color:#888;font-weight:700;margin-bottom:.75rem">Test Results</h6>
        <table>
            <thead>
                <tr><th>Test Name</th><th>Type</th><th>Marks</th><th>Total</th><th>%</th><th>Grade</th><th>Position</th></tr>
            </thead>
            <tbody>
            <?php foreach ($card['results'] as $r):
                $rpct = $r['percentage'];
                $rg   = $r['grade'];
                $rgc  = ($rpct>=80)?'grade-good':($rpct>=50?'grade-ok':'grade-fail');
            ?>
            <tr>
                <td><?= sanitize($r['test_name']) ?></td>
                <td><span style="background:#f0f0f0;padding:.2rem .6rem;border-radius:20px;font-size:.72rem"><?= ucfirst($r['test_type']) ?></span></td>
                <td style="font-weight:600"><?= $r['obtained_marks'] ?></td>
                <td><?= $r['total_marks'] ?></td>
                <td><?= $rpct ?>%</td>
                <td><strong class="<?= $rgc ?>"><?= $rg ?></strong></td>
                <td><?= $r['position'] ?? '—' ?></td>
            </tr>
            <?php endforeach; ?>
            <!-- Summary Row -->
            <tr class="summary-row">
                <td colspan="2"><strong>Total / Overall</strong></td>
                <td><strong><?= $card['obtained'] ?></strong></td>
                <td><strong><?= $card['total_marks'] ?></strong></td>
                <td><strong style="color:<?= $gc ?>"><?= $card['percentage'] ?>%</strong></td>
                <td><strong style="color:<?= $gc ?>"><?= $card['grade'] ?></strong></td>
                <td>—</td>
            </tr>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:#999;text-align:center;padding:1rem">No test results for this period.</p>
        <?php endif; ?>

        <!-- Grading Scale -->
        <div style="margin-top:1.5rem;background:#f8f9fa;border-radius:8px;padding:.75rem 1rem;display:flex;gap:1rem;flex-wrap:wrap">
            <div style="font-size:.72rem;color:#666;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.25rem;width:100%">Grading Scale</div>
            <?php foreach (['A+'=>'90+','A'=>'80+','B'=>'70+','C'=>'60+','D'=>'50+','F'=>'<50'] as $g=>$r): ?>
            <div style="font-size:.78rem;color:#333"><strong style="color:<?= $gradeColors[$g]??'#888' ?>"><?= $g ?></strong> = <?= $r ?></div>
            <?php endforeach; ?>
        </div>

        <div class="footer-note">
            Generated on <?= date('d M Y, h:i A') ?> | <?= sanitize($instituteName) ?>
        </div>
    </div>
</div>
</body>
</html>
