<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
requireRole('admin');
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$instituteName = getSetting('institute_name') ?: SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= sanitize($pageTitle ?? 'Dashboard') ?> – <?= sanitize($instituteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <span class="brand-icon">⭐</span>
        <div class="brand-text">
            <span class="brand-name">Brighten Stars</span>
            <span class="brand-sub">Academy</span>
        </div>
    </div>

    <div class="sidebar-section">MAIN</div>
    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="sidebar-link <?= $currentPage==='dashboard'?'active':'' ?>">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    <div class="sidebar-section">ACADEMICS</div>
    <a href="<?= BASE_URL ?>/admin/students.php" class="sidebar-link <?= $currentPage==='students'?'active':'' ?>">
        <i class="bi bi-people"></i> Students
    </a>
    <a href="<?= BASE_URL ?>/admin/parents.php" class="sidebar-link <?= $currentPage==='parents'?'active':'' ?>">
        <i class="bi bi-person-hearts"></i> Parents
    </a>
    <a href="<?= BASE_URL ?>/admin/courses.php" class="sidebar-link <?= $currentPage==='courses'?'active':'' ?>">
        <i class="bi bi-book"></i> Courses
    </a>
    <a href="<?= BASE_URL ?>/admin/lectures.php" class="sidebar-link <?= $currentPage==='lectures'?'active':'' ?>">
        <i class="bi bi-play-circle"></i> Lectures
    </a>
    <a href="<?= BASE_URL ?>/admin/files.php" class="sidebar-link <?= $currentPage==='files'?'active':'' ?>">
        <i class="bi bi-folder2"></i> Course Files
    </a>

    <div class="sidebar-section">ASSESSMENT</div>
    <a href="<?= BASE_URL ?>/admin/attendance.php" class="sidebar-link <?= $currentPage==='attendance'?'active':'' ?>">
        <i class="bi bi-calendar-check"></i> Attendance
    </a>
    <a href="<?= BASE_URL ?>/admin/tests.php" class="sidebar-link <?= $currentPage==='tests'?'active':'' ?>">
        <i class="bi bi-pencil-square"></i> Tests
    </a>
    <a href="<?= BASE_URL ?>/admin/results.php" class="sidebar-link <?= $currentPage==='results'?'active':'' ?>">
        <i class="bi bi-bar-chart"></i> Results
    </a>
    <a href="<?= BASE_URL ?>/admin/reports.php" class="sidebar-link <?= $currentPage==='reports'?'active':'' ?>">
        <i class="bi bi-file-earmark-text"></i> Reports
    </a>

    <div class="sidebar-section">COMMUNICATION</div>
    <a href="<?= BASE_URL ?>/admin/announcements.php" class="sidebar-link <?= $currentPage==='announcements'?'active':'' ?>">
        <i class="bi bi-megaphone"></i> Announcements
    </a>
    <a href="<?= BASE_URL ?>/admin/whatsapp.php" class="sidebar-link <?= $currentPage==='whatsapp'?'active':'' ?>">
        <i class="bi bi-whatsapp"></i> WhatsApp
    </a>

    <div class="sidebar-section">SYSTEM</div>
    <a href="<?= BASE_URL ?>/admin/settings.php" class="sidebar-link <?= $currentPage==='settings'?'active':'' ?>">
        <i class="bi bi-gear"></i> Settings
    </a>
    <a href="<?= BASE_URL ?>/logout.php" class="sidebar-link text-danger">
        <i class="bi bi-box-arrow-left"></i> Logout
    </a>
</div>

<!-- Main Content -->
<div class="main-wrapper" id="mainWrapper">
    <!-- Topbar -->
    <div class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn-toggle" id="sidebarToggle" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
            <div class="page-title"><?= sanitize($pageTitle ?? 'Dashboard') ?></div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="topbar-date d-none d-md-block">
                <i class="bi bi-calendar3 me-1"></i><?= date('l, d M Y') ?>
            </div>
            <div class="user-badge">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?></div>
                <div class="d-none d-sm-block">
                    <div class="user-name"><?= sanitize($_SESSION['user_name']) ?></div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Page Content -->
    <div class="page-content">
        <?php showFlash(); ?>
