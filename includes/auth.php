<?php
require_once __DIR__ . '/db.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin($role = null) {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
    if ($role && $_SESSION['user_role'] !== $role) {
        if ($_SESSION['user_role'] === 'admin') {
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
        } elseif ($_SESSION['user_role'] === 'student') {
            header('Location: ' . BASE_URL . '/student/dashboard.php');
        } elseif ($_SESSION['user_role'] === 'parent') {
            header('Location: ' . BASE_URL . '/parent/dashboard.php');
        }
        exit;
    }
}

function requireRole($role) {
    requireLogin($role);
}

function login($email, $password) {
    $user = db()->fetchOne("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]);
    if (!$user) return false;
    if (!password_verify($password, $user['password'])) return false;

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];

    // Get extra info based on role
    if ($user['role'] === 'student') {
        $student = db()->fetchOne("SELECT id, roll_number, course_id FROM students WHERE user_id = ?", [$user['id']]);
        if ($student) {
            $_SESSION['student_id'] = $student['id'];
            $_SESSION['roll_number'] = $student['roll_number'];
            $_SESSION['course_id'] = $student['course_id'];
        }
    } elseif ($user['role'] === 'parent') {
        $parent = db()->fetchOne("SELECT id, student_id FROM parents WHERE user_id = ?", [$user['id']]);
        if ($parent) {
            $_SESSION['parent_id'] = $parent['id'];
            $_SESSION['student_id'] = $parent['student_id'];
        }
    }

    db()->execute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
    auditLog($user['id'], 'login', 'User logged in');
    return $user['role'];
}

function logout() {
    if (isLoggedIn()) {
        auditLog($_SESSION['user_id'], 'logout', 'User logged out');
    }
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

function auditLog($userId, $action, $description = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    db()->execute(
        "INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) VALUES (?,?,?,?,?)",
        [$userId, $action, $description, $ip, $ua]
    );
}

function currentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role'],
    ];
}

function isAllowedIP() {
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    $allowed = db()->fetchAll("SELECT ip_address FROM allowed_ips");
    foreach ($allowed as $row) {
        if ($row['ip_address'] === $clientIP) return true;
    }
    return false;
}
