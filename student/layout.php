<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
requireRole('student');
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$instituteName = getSetting('institute_name') ?: SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= sanitize($pageTitle ?? 'Student Portal') ?> – <?= sanitize($instituteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
<style>
.sidebar-link.active { background: rgba(59,130,246,.15); color: #3b82f6; }
.sidebar-link.active::before { background: #3b82f6; }
.brand-icon { background: linear-gradient(135deg, #3b82f6, #1d4ed8) !important; }
.btn-primary-academy { background: linear-gradient(135deg, #3b82f6, #2563eb) !important; }
.stat-card::before { background: linear-gradient(90deg, #3b82f6, #2563eb) !important; }
</style>
</head>
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body>
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo-wrap">
            <img src="<?= BASE_URL ?>/assets/uploads/logo.png" alt="TBS" class="brand-logo"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
            <span class="brand-icon" style="display:none">🎓</span>
        </div>
        <div class="brand-text">
            <span class="brand-name">Student Portal</span>
            <span class="brand-sub" style="color:#3b82f6"><?= sanitize($_SESSION['roll_number'] ?? '') ?></span>
        </div>
    </div>

    <div class="sidebar-section">PORTAL</div>
    <a href="<?= BASE_URL ?>/student/dashboard.php" class="sidebar-link <?= $currentPage==='dashboard'?'active':'' ?>">
        <i class="bi bi-house"></i> Dashboard
    </a>
    <a href="<?= BASE_URL ?>/student/attendance.php" class="sidebar-link <?= $currentPage==='attendance'?'active':'' ?>">
        <i class="bi bi-calendar-check"></i> Attendance
    </a>
    <a href="<?= BASE_URL ?>/student/results.php" class="sidebar-link <?= $currentPage==='results'?'active':'' ?>">
        <i class="bi bi-bar-chart"></i> My Results
    </a>
    <a href="<?= BASE_URL ?>/student/lectures.php" class="sidebar-link <?= $currentPage==='lectures'?'active':'' ?>">
        <i class="bi bi-play-circle"></i> Lectures
    </a>
    <a href="<?= BASE_URL ?>/student/downloads.php" class="sidebar-link <?= $currentPage==='downloads'?'active':'' ?>">
        <i class="bi bi-folder2-open"></i> Study Files
    </a>
    <a href="<?= BASE_URL ?>/student/profile.php" class="sidebar-link <?= $currentPage==='profile'?'active':'' ?>">
        <i class="bi bi-person-circle"></i> Profile
    </a>

    <div style="flex:1"></div>
    <a href="<?= BASE_URL ?>/logout.php" class="sidebar-link text-danger">
        <i class="bi bi-box-arrow-left"></i> Logout
    </a>
</div>

<div class="overlay" id="overlay"></div>
<div class="main-wrapper" id="mainWrapper">
    <div class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
            <div class="page-title"><?= sanitize($pageTitle ?? 'Dashboard') ?></div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="user-badge">
                <div class="user-avatar" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8)"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
                <div class="d-none d-sm-block">
                    <div class="user-name"><?= sanitize($_SESSION['user_name']) ?></div>
                    <div class="user-role" style="color:#3b82f6">Student</div>
                </div>
            </div>
        </div>
    </div>
    <div class="page-content">
        <?php showFlash(); ?>
