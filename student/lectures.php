<?php
$pageTitle = 'Lectures';
require_once __DIR__ . '/layout.php';

$studentId = $_SESSION['student_id'] ?? 0;
$student   = db()->fetchOne("SELECT course_id FROM students WHERE id=?", [$studentId]);
$courseId  = $student['course_id'] ?? 0;

$lectures = db()->fetchAll(
    "SELECT * FROM lectures WHERE course_id=? ORDER BY created_at DESC",
    [$courseId]
);

$isAllowed = isAllowedIP();
$fileIcons = ['pdf'=>'bi-file-earmark-pdf','doc'=>'bi-file-earmark-word','docx'=>'bi-file-earmark-word',
    'ppt'=>'bi-file-earmark-ppt','pptx'=>'bi-file-earmark-ppt','jpg'=>'bi-file-earmark-image',
    'jpeg'=>'bi-file-earmark-image','png'=>'bi-file-earmark-image'];
$fileClasses = ['pdf'=>'file-pdf','doc'=>'file-doc','docx'=>'file-doc','ppt'=>'file-ppt','pptx'=>'file-ppt',
    'jpg'=>'file-img','jpeg'=>'file-img','png'=>'file-img'];
?>

<div class="section-header">
    <div>
        <div class="section-title">Lecture Materials</div>
        <div class="section-subtitle"><?= count($lectures) ?> files available</div>
    </div>
    <?php if (!$isAllowed): ?>
    <div class="badge-academy badge-warning"><i class="bi bi-shield-exclamation me-1"></i>Downloads restricted to institute network</div>
    <?php else: ?>
    <div class="badge-academy badge-success"><i class="bi bi-shield-check me-1"></i>Institute network – Full access</div>
    <?php endif; ?>
</div>

<div class="row g-3">
<?php if ($lectures): foreach ($lectures as $l):
    $ext  = strtolower($l['file_type'] ?? '');
    $icon = $fileIcons[$ext] ?? 'bi-file-earmark';
    $cls  = $fileClasses[$ext] ?? 'file-other';
    $canDownload = $l['allow_download'] && $isAllowed;
?>
<div class="col-12 col-md-6">
    <div class="data-card" style="padding:1.25rem">
        <div class="d-flex gap-3 align-items-start">
            <div class="file-icon <?= $cls ?>" style="width:48px;height:48px;flex-shrink:0;font-size:1.3rem">
                <i class="bi <?= $icon ?>"></i>
            </div>
            <div style="flex:1;min-width:0">
                <div style="font-weight:600;font-size:.9rem;margin-bottom:.2rem"><?= sanitize($l['title']) ?></div>
                <?php if ($l['description']): ?>
                <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:.5rem"><?= sanitize($l['description']) ?></div>
                <?php endif; ?>
                <div style="font-size:.75rem;color:var(--text-muted)">
                    <?= $l['file_size'] ? formatFileSize($l['file_size']) : '' ?>
                    <?php if ($l['file_type']): ?> · <?= strtoupper($l['file_type']) ?><?php endif; ?>
                    · <?= formatDate($l['created_at']) ?>
                </div>
                <div class="d-flex gap-2 mt-2">
                    <?php if ($l['file_path']): ?>
                    <a href="<?= BASE_URL ?>/admin/serve_file.php?type=lecture&id=<?= $l['id'] ?>" target="_blank"
                        class="btn-primary-academy" style="font-size:.78rem;padding:.35rem .75rem">
                        <i class="bi bi-eye"></i> View
                    </a>
                    <?php if ($canDownload): ?>
                    <a href="<?= BASE_URL ?>/admin/serve_file.php?type=lecture&id=<?= $l['id'] ?>"
                        class="btn-primary-academy" style="font-size:.78rem;padding:.35rem .75rem;background:var(--surface3);color:var(--text)">
                        <i class="bi bi-download"></i> Download
                    </a>
                    <?php elseif (!$isAllowed && $l['allow_download']): ?>
                    <span style="font-size:.75rem;color:#f59e0b"><i class="bi bi-lock me-1"></i>Institute only</span>
                    <?php endif; ?>
                    <?php else: ?>
                    <span style="font-size:.8rem;color:var(--text-muted)">No file attached</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endforeach; else: ?>
<div class="col-12">
    <div class="data-card" style="text-align:center;padding:3rem;color:var(--text-muted)">
        <i class="bi bi-play-circle" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
        No lectures uploaded yet for your course.
    </div>
</div>
<?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
