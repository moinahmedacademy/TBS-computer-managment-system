<?php
$pageTitle = 'Study Files';
require_once __DIR__ . '/layout.php';

$studentId = $_SESSION['student_id'] ?? 0;
$student   = db()->fetchOne("SELECT course_id FROM students WHERE id=?", [$studentId]);
$courseId  = $student['course_id'] ?? 0;

$files = db()->fetchAll("SELECT * FROM course_files WHERE course_id=? ORDER BY created_at DESC", [$courseId]);
$isAllowed = isAllowedIP();
?>

<div class="section-header">
    <div>
        <div class="section-title">Study Files & Notes</div>
        <div class="section-subtitle"><?= count($files) ?> files available</div>
    </div>
    <?php if ($isAllowed): ?>
    <span class="badge-academy badge-success"><i class="bi bi-shield-check me-1"></i>Institute Network</span>
    <?php else: ?>
    <span class="badge-academy badge-warning"><i class="bi bi-shield me-1"></i>Outside Network</span>
    <?php endif; ?>
</div>

<?php if (!$isAllowed): ?>
<div class="alert alert-warning mb-3">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Restricted Access:</strong> File downloads are only available when connected to the institute network.
    You can view files but cannot download them from outside.
</div>
<?php endif; ?>

<div class="row g-3">
<?php if ($files): foreach ($files as $f):
    $ext = strtolower($f['file_type'] ?? '');
    $icons = ['pdf'=>'bi-file-earmark-pdf file-pdf','doc'=>'bi-file-earmark-word file-doc','docx'=>'bi-file-earmark-word file-doc',
        'ppt'=>'bi-file-earmark-ppt file-ppt','pptx'=>'bi-file-earmark-ppt file-ppt','jpg'=>'bi-file-earmark-image file-img',
        'jpeg'=>'bi-file-earmark-image file-img','png'=>'bi-file-earmark-image file-img'];
    [$icon,$cls] = array_pad(explode(' ',$icons[$ext] ?? 'bi-file-earmark file-other'),2,'');
    $canDl = $f['allow_download'] && $isAllowed;
?>
<div class="col-12 col-md-6 col-xl-4">
    <div class="data-card" style="padding:1.25rem">
        <div class="d-flex gap-3 align-items-start">
            <div class="file-icon <?= $cls ?>" style="width:44px;height:44px;flex-shrink:0;font-size:1.2rem">
                <i class="bi <?= $icon ?>"></i>
            </div>
            <div style="flex:1;min-width:0">
                <div style="font-weight:600;font-size:.88rem;margin-bottom:.15rem"><?= sanitize($f['title']) ?></div>
                <?php if ($f['description']): ?>
                <div style="font-size:.77rem;color:var(--text-muted);margin-bottom:.4rem"><?= sanitize($f['description']) ?></div>
                <?php endif; ?>
                <div style="font-size:.72rem;color:var(--text-muted)">
                    <?= $f['file_size'] ? formatFileSize($f['file_size']) : '' ?>
                    <?php if ($f['file_type']): ?> · <?= strtoupper($f['file_type']) ?><?php endif; ?>
                </div>
                <div class="d-flex gap-2 mt-2 flex-wrap">
                    <?php if ($isAllowed): ?>
                    <a href="<?= BASE_URL ?>/admin/serve_file.php?type=course_file&id=<?= $f['id'] ?>" target="_blank"
                        class="btn-primary-academy" style="font-size:.77rem;padding:.3rem .7rem">
                        <i class="bi bi-eye"></i> View
                    </a>
                    <?php if ($canDl): ?>
                    <a href="<?= BASE_URL ?>/admin/serve_file.php?type=course_file&id=<?= $f['id'] ?>"
                        download class="btn-primary-academy" style="font-size:.77rem;padding:.3rem .7rem;background:var(--surface3);color:var(--text)">
                        <i class="bi bi-download"></i> Download
                    </a>
                    <?php endif; ?>
                    <?php else: ?>
                    <span style="font-size:.77rem;color:#f59e0b;display:flex;align-items:center;gap:.3rem">
                        <i class="bi bi-lock"></i> Connect to institute WiFi to access
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endforeach; else: ?>
<div class="col-12">
    <div class="data-card" style="text-align:center;padding:3rem;color:var(--text-muted)">
        <i class="bi bi-folder2-open" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
        No study files uploaded yet.
    </div>
</div>
<?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
