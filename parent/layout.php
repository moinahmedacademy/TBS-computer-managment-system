<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
requireRole('parent');
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$instituteName = getSetting('institute_name') ?: SITE_NAME;

$parentId  = $_SESSION['parent_id'] ?? 0;
$studentId = $_SESSION['student_id'] ?? 0;
$parent    = db()->fetchOne("SELECT * FROM parents WHERE id=?", [$parentId]);
$student   = db()->fetchOne("SELECT s.*, c.name as course_name FROM students s LEFT JOIN courses c ON s.course_id=c.id WHERE s.id=?", [$studentId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= sanitize($pageTitle ?? 'Parent Portal') ?> – <?= sanitize($instituteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
<style>
.sidebar-link.active { background: rgba(16,185,129,.15); color: #10b981; }
.sidebar-link.active::before { background: #10b981; }
.brand-icon { background: linear-gradient(135deg, #10b981, #059669) !important; }
.btn-primary-academy { background: linear-gradient(135deg, #10b981, #059669) !important; }
</style>
</head>
<body>
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <span class="brand-icon">👨‍👩‍👦</span>
        <div class="brand-text">
            <span class="brand-name">Parent Portal</span>
            <span class="brand-sub" style="color:#10b981"><?= sanitize($student['name'] ?? '') ?></span>
        </div>
    </div>

    <div class="sidebar-section">MY CHILD</div>
    <a href="<?= BASE_URL ?>/parent/dashboard.php" class="sidebar-link <?= $currentPage==='dashboard'?'active':'' ?>">
        <i class="bi bi-house"></i> Dashboard
    </a>
    <a href="<?= BASE_URL ?>/parent/attendance.php" class="sidebar-link <?= $currentPage==='attendance'?'active':'' ?>">
        <i class="bi bi-calendar-check"></i> Attendance
    </a>
    <a href="<?= BASE_URL ?>/parent/results.php" class="sidebar-link <?= $currentPage==='results'?'active':'' ?>">
        <i class="bi bi-bar-chart"></i> Results
    </a>
    <a href="<?= BASE_URL ?>/parent/reports.php" class="sidebar-link <?= $currentPage==='reports'?'active':'' ?>">
        <i class="bi bi-file-earmark-text"></i> Reports
    </a>
    <a href="<?= BASE_URL ?>/parent/profile.php" class="sidebar-link <?= $currentPage==='profile'?'active':'' ?>">
        <i class="bi bi-person-circle"></i> Profile
    </a>

    <div style="flex:1"></div>
    <a href="<?= BASE_URL ?>/logout.php" class="sidebar-link text-danger">
        <i class="bi bi-box-arrow-left"></i> Logout
    </a>
</div>

<div class="main-wrapper" id="mainWrapper">
    <div class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
            <div class="page-title"><?= sanitize($pageTitle ?? 'Dashboard') ?></div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="user-badge">
                <div class="user-avatar" style="background:linear-gradient(135deg,#10b981,#059669)"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
                <div class="d-none d-sm-block">
                    <div class="user-name"><?= sanitize($_SESSION['user_name']) ?></div>
                    <div class="user-role" style="color:#10b981">Parent</div>
                </div>
            </div>
        </div>
    </div>
    <div class="page-content">
        <?php showFlash(); ?>
