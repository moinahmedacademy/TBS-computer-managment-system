<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
requireLogin();

$type = $_GET['type'] ?? '';
$id   = (int)($_GET['id'] ?? 0);

if (!$id) { http_response_code(400); die('Invalid request.'); }

$isAdmin = $_SESSION['user_role'] === 'admin';
$isAllowed = isAllowedIP();

if ($type === 'lecture') {
    $file = db()->fetchOne("SELECT * FROM lectures WHERE id=?", [$id]);
    if (!$file) { http_response_code(404); die('File not found.'); }

    // Check course access for students
    if (!$isAdmin) {
        $studentCourse = $_SESSION['course_id'] ?? 0;
        if ($studentCourse != $file['course_id']) { http_response_code(403); die('Access denied.'); }

        // Download restriction
        if (!$file['allow_download'] && !$isAllowed) {
            http_response_code(403);
            die('<h3>Access Restricted</h3><p>File download is only allowed from within the institute network.</p>');
        }
    }
} elseif ($type === 'course_file') {
    $file = db()->fetchOne("SELECT * FROM course_files WHERE id=?", [$id]);
    if (!$file) { http_response_code(404); die('File not found.'); }

    if (!$isAdmin) {
        // Check IP for course files
        if (!$isAllowed) {
            http_response_code(403);
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
            </head><body style="background:#0a0a0f;color:#f0f0f5;display:flex;align-items:center;justify-content:center;min-height:100vh">
            <div style="text-align:center;max-width:400px;padding:2rem">
                <div style="font-size:3rem;margin-bottom:1rem">🔒</div>
                <h4>Access Restricted</h4>
                <p style="color:#8888aa">This file can only be accessed from within the institute network.</p>
                <p style="color:#8888aa;font-size:.85rem">Please connect to the institute WiFi and try again.</p>
                <a href="javascript:history.back()" style="background:#f59e0b;color:#000;padding:.5rem 1rem;border-radius:8px;text-decoration:none;font-weight:600">← Go Back</a>
            </div></body></html>';
            exit;
        }
    }
} else {
    http_response_code(400); die('Invalid file type.');
}

$filePath = $file['file_path'];
if (!$filePath || !file_exists($filePath)) {
    http_response_code(404); die('File not found on server.');
}

$ext = strtolower($file['file_type'] ?? pathinfo($filePath, PATHINFO_EXTENSION));
$mimeTypes = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'zip'  => 'application/zip',
];

$mime = $mimeTypes[$ext] ?? 'application/octet-stream';
$displayName = $file['file_name'] ?? basename($filePath);

// For PDFs and images, display inline; others force download
$inline = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif']);
$disposition = $inline ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . $displayName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

readfile($filePath);
exit;
