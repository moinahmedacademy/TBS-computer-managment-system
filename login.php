<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    header("Location: " . BASE_URL . "/$role/dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Please enter email and password.';
    } else {
        $role = login($email, $password);
        if ($role) {
            header("Location: " . BASE_URL . "/$role/dashboard.php");
            exit;
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login – The Brighten Stars Academy</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
:root {
    --bg: #0a0a0f;
    --surface: #16161f;
    --surface2: #1e1e2a;
    --accent: #f59e0b;
    --accent2: #d97706;
    --text: #f0f0f5;
    --text-muted: #8888aa;
    --border: #2a2a3a;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    background: var(--bg);
    color: var(--text);
    font-family: 'Segoe UI', system-ui, sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}
.bg-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(100px);
    pointer-events: none;
}
.orb1 { width: 500px; height: 500px; background: rgba(245,158,11,.12); top: -150px; left: -150px; }
.orb2 { width: 400px; height: 400px; background: rgba(139,92,246,.08); bottom: -100px; right: -100px; }

.login-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 2.5rem;
    width: 100%;
    max-width: 420px;
    position: relative;
    z-index: 1;
    box-shadow: 0 25px 60px rgba(0,0,0,.5);
}
.logo-wrap {
    text-align: center;
    margin-bottom: 2rem;
}
.logo-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border-radius: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin-bottom: 1rem;
    box-shadow: 0 8px 24px rgba(245,158,11,.3);
}
.logo-wrap h1 { font-size: 1.4rem; font-weight: 700; color: var(--text); }
.logo-wrap p { color: var(--text-muted); font-size: .85rem; margin-top: .25rem; }
.login-logo { width: 90px; height: 90px; object-fit: contain; margin-bottom: .75rem; display: block; margin-left: auto; margin-right: auto; }

.form-label { color: var(--text-muted); font-size: .85rem; font-weight: 500; margin-bottom: .4rem; }
.form-control {
    background: var(--surface2);
    border: 1px solid var(--border);
    color: var(--text);
    border-radius: 10px;
    padding: .7rem 1rem;
    font-size: .9rem;
    transition: border-color .2s, box-shadow .2s;
}
.form-control:focus {
    background: var(--surface2);
    border-color: var(--accent);
    color: var(--text);
    box-shadow: 0 0 0 3px rgba(245,158,11,.15);
}
.form-control::placeholder { color: #555566; }
.input-group-text {
    background: var(--surface2);
    border: 1px solid var(--border);
    color: var(--text-muted);
    border-radius: 10px 0 0 10px;
}
.input-group .form-control { border-radius: 0 10px 10px 0; }

.btn-login {
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border: none;
    color: #000;
    font-weight: 700;
    padding: .75rem;
    border-radius: 10px;
    font-size: .95rem;
    width: 100%;
    transition: opacity .2s, transform .1s;
}
.btn-login:hover { opacity: .9; transform: translateY(-1px); color: #000; }
.btn-login:active { transform: translateY(0); }

.alert-danger {
    background: rgba(220,53,69,.15);
    border: 1px solid rgba(220,53,69,.3);
    color: #ff6b7a;
    border-radius: 10px;
    font-size: .88rem;
}
</style>
</head>
<body>
<div class="bg-orb orb1"></div>
<div class="bg-orb orb2"></div>

<div class="login-card">
    <div class="logo-wrap">
        <img src="assets/uploads/logo.png" alt="TBS Logo" class="login-logo"
             onerror="this.style.display='none';document.getElementById('fallbackIcon').style.display='inline-flex'">
        <div class="logo-icon" id="fallbackIcon" style="display:none">⭐</div>
        <h1>The Brighten Stars Academy</h1>
        <p>Institute Management System</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-exclamation-circle"></i>
        <?= sanitize($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <div class="mb-3">
            <label class="form-label">Email Address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input type="email" name="email" class="form-control" placeholder="your@email.com"
                    value="<?= sanitize($_POST['email'] ?? '') ?>" required autofocus>
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" id="passwordField" class="form-control" placeholder="••••••••" required>
                <button type="button" class="btn btn-outline-secondary border-0 bg-transparent position-absolute end-0 top-50 translate-middle-y px-3"
                    style="z-index:5;color:var(--text-muted)" onclick="togglePass()">
                    <i class="bi bi-eye" id="eyeIcon"></i>
                </button>
            </div>
        </div>
        <button type="submit" class="btn-login">
            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>
    </form>

</div>

<script>
function togglePass() {
    const f = document.getElementById('passwordField');
    const i = document.getElementById('eyeIcon');
    if (f.type === 'password') { f.type = 'text'; i.className = 'bi bi-eye-slash'; }
    else { f.type = 'password'; i.className = 'bi bi-eye'; }
}
</script>
</body>
</html>
