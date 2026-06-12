<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/layout.php';

$totalStudents  = db()->fetchOne("SELECT COUNT(*) as c FROM students WHERE status='active'")['c'] ?? 0;
$totalCourses   = db()->fetchOne("SELECT COUNT(*) as c FROM courses WHERE status='active'")['c'] ?? 0;
$totalTests     = db()->fetchOne("SELECT COUNT(*) as c FROM tests")['c'] ?? 0;
$totalParents   = db()->fetchOne("SELECT COUNT(*) as c FROM parents")['c'] ?? 0;

$todayAttendance = db()->fetchOne(
    "SELECT COUNT(*) as c FROM attendance WHERE date=CURDATE() AND status='present'"
)['c'] ?? 0;

$recentStudents = db()->fetchAll(
    "SELECT s.name, s.roll_number, c.name as course_name, s.enrollment_date
     FROM students s LEFT JOIN courses c ON s.course_id=c.id
     ORDER BY s.created_at DESC LIMIT 5"
);

$recentTests = db()->fetchAll(
    "SELECT t.name, t.date, t.test_type, c.name as course_name, t.total_marks
     FROM tests t JOIN courses c ON t.course_id=c.id
     ORDER BY t.date DESC LIMIT 5"
);

$announcements = db()->fetchAll(
    "SELECT * FROM announcements ORDER BY is_pinned DESC, created_at DESC LIMIT 4"
);

$monthlyEnrollment = db()->fetchAll(
    "SELECT MONTH(enrollment_date) as m, COUNT(*) as cnt
     FROM students WHERE YEAR(enrollment_date)=YEAR(CURDATE())
     GROUP BY MONTH(enrollment_date) ORDER BY m"
);

$courseStats = db()->fetchAll(
    "SELECT c.name, COUNT(s.id) as total
     FROM courses c LEFT JOIN students s ON s.course_id=c.id AND s.status='active'
     GROUP BY c.id ORDER BY total DESC LIMIT 6"
);
?>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.15);color:var(--accent)">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="stat-value" data-count="<?= $totalStudents ?>"><?= $totalStudents ?></div>
            <div class="stat-label">Active Students</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.15);color:#3b82f6">
                <i class="bi bi-book-fill"></i>
            </div>
            <div class="stat-value" data-count="<?= $totalCourses ?>"><?= $totalCourses ?></div>
            <div class="stat-label">Active Courses</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.15);color:#10b981">
                <i class="bi bi-calendar-check-fill"></i>
            </div>
            <div class="stat-value" data-count="<?= $todayAttendance ?>"><?= $todayAttendance ?></div>
            <div class="stat-label">Present Today</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(139,92,246,.15);color:#8b5cf6">
                <i class="bi bi-pencil-square"></i>
            </div>
            <div class="stat-value" data-count="<?= $totalTests ?>"><?= $totalTests ?></div>
            <div class="stat-label">Total Tests</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Recent Students -->
    <div class="col-12 col-lg-7">
        <div class="data-card">
            <div class="data-card-header">
                <div>
                    <div class="data-card-title">Recent Students</div>
                    <div style="font-size:.78rem;color:var(--text-muted)">Latest enrollments</div>
                </div>
                <a href="<?= BASE_URL ?>/admin/students.php" class="btn-primary-academy" style="font-size:.8rem;padding:.4rem .8rem">
                    View All <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="table-wrap">
                <table class="table-academy">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Roll No</th>
                            <th>Course</th>
                            <th>Enrolled</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($recentStudents): foreach ($recentStudents as $s): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-circle" style="width:32px;height:32px;font-size:.8rem">
                                        <?= strtoupper(substr($s['name'],0,1)) ?>
                                    </div>
                                    <?= sanitize($s['name']) ?>
                                </div>
                            </td>
                            <td><span class="badge-academy badge-info"><?= sanitize($s['roll_number']) ?></span></td>
                            <td><?= sanitize($s['course_name'] ?? 'N/A') ?></td>
                            <td style="color:var(--text-muted);font-size:.82rem"><?= formatDate($s['enrollment_date']) ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center" style="color:var(--text-muted);padding:2rem">No students yet</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Course Distribution -->
    <div class="col-12 col-lg-5">
        <div class="data-card h-100">
            <div class="data-card-header">
                <div class="data-card-title">Students by Course</div>
            </div>
            <div style="padding:1rem">
            <?php foreach ($courseStats as $cs):
                $max = max(array_column($courseStats,'total') ?: [1]);
                $pct = $max > 0 ? ($cs['total'] / $max) * 100 : 0;
            ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span style="font-size:.82rem"><?= sanitize($cs['name']) ?></span>
                        <span style="font-size:.82rem;color:var(--accent);font-weight:600"><?= $cs['total'] ?></span>
                    </div>
                    <div class="progress-bar-academy">
                        <div class="progress-bar-academy-fill" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$courseStats): ?>
                <p style="color:var(--text-muted);font-size:.85rem;text-align:center;padding:2rem 0">No course data available</p>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Upcoming Tests -->
    <div class="col-12 col-lg-7">
        <div class="data-card">
            <div class="data-card-header">
                <div class="data-card-title">Recent Tests</div>
                <a href="<?= BASE_URL ?>/admin/tests.php" class="btn-primary-academy" style="font-size:.8rem;padding:.4rem .8rem">
                    Manage <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="table-wrap">
                <table class="table-academy">
                    <thead><tr><th>Test</th><th>Course</th><th>Type</th><th>Date</th><th>Marks</th></tr></thead>
                    <tbody>
                    <?php if ($recentTests): foreach ($recentTests as $t): ?>
                        <tr>
                            <td><?= sanitize($t['name']) ?></td>
                            <td style="font-size:.82rem;color:var(--text-muted)"><?= sanitize($t['course_name']) ?></td>
                            <td>
                                <span class="badge-academy <?= $t['test_type']==='final'?'badge-danger':($t['test_type']==='monthly'?'badge-warning':'badge-info') ?>">
                                    <?= ucfirst($t['test_type']) ?>
                                </span>
                            </td>
                            <td style="font-size:.82rem;color:var(--text-muted)"><?= formatDate($t['date']) ?></td>
                            <td><?= $t['total_marks'] ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center" style="color:var(--text-muted);padding:2rem">No tests yet</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Announcements -->
    <div class="col-12 col-lg-5">
        <div class="data-card">
            <div class="data-card-header">
                <div class="data-card-title">Announcements</div>
                <a href="<?= BASE_URL ?>/admin/announcements.php" class="btn-primary-academy" style="font-size:.8rem;padding:.4rem .8rem">
                    Manage <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div style="padding:.5rem">
            <?php if ($announcements): foreach ($announcements as $ann):
                $typeColors = ['holiday'=>'danger','test'=>'warning','course'=>'info','event'=>'success','notice'=>'warning','general'=>'secondary'];
                $color = $typeColors[$ann['type']] ?? 'secondary';
            ?>
                <div style="padding:.75rem;border-bottom:1px solid var(--border)">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div>
                            <div style="font-size:.875rem;font-weight:500;margin-bottom:.2rem">
                                <?php if ($ann['is_pinned']): ?><i class="bi bi-pin-fill text-warning me-1" style="font-size:.75rem"></i><?php endif; ?>
                                <?= sanitize($ann['title']) ?>
                            </div>
                            <div style="font-size:.75rem;color:var(--text-muted)"><?= formatDate($ann['created_at'], 'd M Y') ?></div>
                        </div>
                        <span class="badge-academy badge-<?= $color ?>" style="white-space:nowrap;font-size:.68rem"><?= ucfirst($ann['type']) ?></span>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <p style="color:var(--text-muted);font-size:.85rem;text-align:center;padding:2rem">No announcements</p>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
