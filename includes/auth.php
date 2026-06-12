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

    // Server-side idle timeout (not just JS)
    if (isset($_SESSION['last_activity'])) {
        $idle = time() - $_SESSION['last_activity'];
        if ($idle > SESSION_LIFETIME * 60) {
            logout();
        }
    }
    $_SESSION['last_activity'] = time();

    if ($role && $_SESSION['user_role'] !== $role) {
        $r = $_SESSION['user_role'];
        header('Location: ' . BASE_URL . "/$r/dashboard.php");
        exit;
    }
}

function requireRole($role) {
    requireLogin($role);
}

function login($email, $password) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // Brute-force: block IP after 5 failed attempts in 15 minutes
    $attempts = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM audit_logs
         WHERE action='login_failed' AND ip_address=?
           AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
        [$ip]
    );
    if (($attempts['cnt'] ?? 0) >= 5) {
        return 'rate_limited';
    }

    $user = db()->fetchOne("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]);
    if (!$user || !password_verify($password, $user['password'])) {
        // Log the failed attempt (used for rate limiting above)
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        db()->execute(
            "INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) VALUES (?,?,?,?,?)",
            [null, 'login_failed', 'Failed login for: ' . $email, $ip, $ua]
        );
        return false;
    }

    // Prevent session fixation
    session_regenerate_id(true);

    $_SESSION['user_id']      = $user['id'];
    $_SESSION['user_name']    = $user['name'];
    $_SESSION['user_email']   = $user['email'];
    $_SESSION['user_role']    = $user['role'];
    $_SESSION['last_activity'] = time();

    if ($user['role'] === 'student') {
        $student = db()->fetchOne("SELECT id, roll_number, course_id FROM students WHERE user_id = ?", [$user['id']]);
        if ($student) {
            $_SESSION['student_id'] = $student['id'];
            $_SESSION['roll_number'] = $student['roll_number'];
            $_SESSION['course_id']  = $student['course_id'];
        }
    } elseif ($user['role'] === 'parent') {
        $parent = db()->fetchOne("SELECT id, student_id FROM parents WHERE user_id = ?", [$user['id']]);
        if ($parent) {
            $_SESSION['parent_id']  = $parent['id'];
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
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role'  => $_SESSION['user_role'],
    ];
}

function isAllowedIP() {
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    $allowed  = db()->fetchAll("SELECT ip_address FROM allowed_ips");
    foreach ($allowed as $row) {
        if ($row['ip_address'] === $clientIP) return true;
    }
    return false;
}
