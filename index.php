<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    header("Location: " . BASE_URL . "/$role/dashboard.php");
} else {
    header("Location: " . BASE_URL . "/login.php");
}
exit;
