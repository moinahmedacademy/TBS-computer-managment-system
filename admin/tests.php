<?php
$pageTitle = 'Tests';
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name     = sanitize($_POST['name'] ?? '');
        $courseId = (int)$_POST['course_id'];
        $subject  = sanitize($_POST['subject'] ?? '');
        $type     = $_POST['test_type'] ?? 'weekly';
        $marks    = (int)$_POST['total_marks'];
        $date     = $_POST['date'] ?? date('Y-m-d');
        $duration = (int)($_POST['duration_minutes'] ?? 60);
        $desc     = sanitize($_POST['description'] ?? '');

        if (!$name || !$courseId || !$marks) { flashMessage('danger','Name, course, marks required.'); header('Location: tests.php'); exit; }

        if ($action === 'add') {
            db()->execute(
                "INSERT INTO tests (course_id,name,subject,test_type,total_marks,date,duration_minutes,description,created_by) VALUES (?,?,?,?,?,?,?,?,?)",
                [$courseId,$name,$subject,$type,$marks,$date,$duration,$desc,$_SESSION['user_id']]
            );
            flashMessage('success', "Test '$name' created.");
        } else {
            $id = (int)$_POST['id'];
            db()->execute(
                "UPDATE tests SET course_id=?,name=?,subject=?,test_type=?,total_marks=?,date=?,duration_minutes=?,description=? WHERE id=?",
                [$courseId,$name,$subject,$type,$marks,$date,$duration,$desc,$id]
            );
            flashMessage('success', "Test updated.");
        }
    } elseif ($action === 'delete') {
        db()->execute("DELETE FROM tests WHERE id=?", [(int)$_POST['id']]);
        flashMessage('success', "Test deleted.");
    }
    header('Location: tests.php'); exit;
}

$courses = db()->fetchAll("SELECT id,name FROM courses WHERE status='active' ORDER BY name");
$tests = db()->fetchAll(
    "SELECT t.*, c.name as course_name,
     (SELECT COUNT(*) FROM test_results tr WHERE tr.test_id=t.id) as results_count
     FROM tests t JOIN courses c ON t.course_id=c.id ORDER BY t.date DESC"
);
?>

<div class="section-header">
    <div>
        <div class="section-title">Tests Management</div>
        <div class="section-subtitle"><?= count($tests) ?> tests total</div>
    </div>
    <button class="btn-primary-academy" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg"></i> Create Test
    </button>
</div>

<div class="data-card">
    <div class="table-wrap">
        <table class="table-academy">
            <thead><tr><th>Test Name</th><th>Course</th><th>Subject</th><th>Type</th><th>Date</th><th>Marks</th><th>Results</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($tests): foreach ($tests as $t): ?>
            <tr>
                <td style="font-weight:500"><?= sanitize($t['name']) ?></td>
                <td style="font-size:.82rem;color:var(--text-muted)"><?= sanitize($t['course_name']) ?></td>
                <td style="font-size:.82rem"><?= sanitize($t['subject'] ?: '—') ?></td>
                <td>
                    <span class="badge-academy <?= $t['test_type']==='final'?'badge-danger':($t['test_type']==='monthly'?'badge-warning':'badge-info') ?>">
                        <?= ucfirst($t['test_type']) ?>
                    </span>
                </td>
                <td style="font-size:.82rem"><?= formatDate($t['date']) ?></td>
                <td style="font-weight:600;color:var(--accent)"><?= $t['total_marks'] ?></td>
                <td>
                    <span class="badge-academy <?= $t['results_count']>0?'badge-success':'badge-secondary' ?>">
                        <?= $t['results_count'] ?> entered
                    </span>
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="results.php?test_id=<?= $t['id'] ?>" class="btn-icon btn-icon-view" title="Enter Marks">
                            <i class="bi bi-pencil-square"></i>
                        </a>
                        <button class="btn-icon btn-icon-edit" onclick="editTest(<?= htmlspecialchars(json_encode($t)) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" onsubmit="return confirmDelete(this)">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <button type="submit" class="btn-icon btn-icon-delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:3rem">No tests created yet</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Create Test</h5>
                    <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label">Test Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Weekly Test – Computer Basics">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Course *</label>
                        <select name="course_id" class="form-select" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-control" placeholder="e.g. Computer">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Test Type</label>
                        <select name="test_type" class="form-select">
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="final">Final</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Total Marks *</label>
                        <input type="number" name="total_marks" class="form-control" required min="1" placeholder="100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Duration (min)</label>
                        <input type="number" name="duration_minutes" class="form-control" value="60" min="5">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Test Date</label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-academy">Create Test</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="et_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Test</h5>
                    <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label">Test Name</label>
                        <input type="text" name="name" id="et_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Course</label>
                        <select name="course_id" id="et_course_id" class="form-select" required>
                            <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" id="et_subject" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Type</label>
                        <select name="test_type" id="et_type" class="form-select">
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="final">Final</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Total Marks</label>
                        <input type="number" name="total_marks" id="et_marks" class="form-control" min="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Duration (min)</label>
                        <input type="number" name="duration_minutes" id="et_duration" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" id="et_date" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-academy">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editTest(t) {
    document.getElementById('et_id').value = t.id;
    document.getElementById('et_name').value = t.name;
    document.getElementById('et_course_id').value = t.course_id;
    document.getElementById('et_subject').value = t.subject || '';
    document.getElementById('et_type').value = t.test_type;
    document.getElementById('et_marks').value = t.total_marks;
    document.getElementById('et_duration').value = t.duration_minutes;
    document.getElementById('et_date').value = t.date;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
