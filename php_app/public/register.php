<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$old = ['username' => '', 'email' => '', 'full_name' => '', 'role' => 'student'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'student';

    $old = compact('username', 'email', 'full_name', 'role');

    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $auth = new Auth();
        try {
            $auth->register($username, $email, $full_name, $password, $role);
            header("Location: login.php?registered=1");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — PredictEd</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg-deep: #0a0a0f;
            --accent: #f97316;
            --accent-glow: rgba(249,115,22,0.3);
            --teal: #14b8a6;
            --violet: #8b5cf6;
            --rose: #f43f5e;
            --text: #f1f5f9;
            --text-muted: #94a3b8;
            --border: rgba(255,255,255,0.06);
            --glass: rgba(255,255,255,0.04);
            --error: #ef4444;
            --success: #10b981;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-deep);
            color: var(--text);
            overflow-x: hidden;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .auth-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 100vh;
        }

        .auth-form-panel {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem;
            position: relative;
        }

        .auth-form-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 20% 80%, rgba(249,115,22,0.04), transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(139,92,246,0.03), transparent 50%);
        }

        .auth-form-wrapper {
            max-width: 440px;
            width: 100%;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .auth-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--text);
            margin-bottom: 2.5rem;
        }

        .auth-logo-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--accent), var(--rose));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 800;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .auth-logo-icon::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, transparent 40%, rgba(255,255,255,0.2));
        }

        .auth-logo span {
            font-weight: 700;
            font-size: 1.15rem;
        }

        .auth-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
        }

        .auth-subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 2rem;
        }

        .auth-alert {
            padding: 0.8rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: fadeUp 0.4s ease-out;
        }

        .auth-alert-error {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.2);
            color: #fca5a5;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1.1rem;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 0.4rem;
        }

        .form-input-wrap {
            position: relative;
        }

        .form-input-wrap i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1rem;
            transition: color 0.2s;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 0.8rem 1rem 0.8rem 2.8rem;
            background: var(--glass);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            font-size: 0.9rem;
            transition: all 0.2s;
            outline: none;
        }

        .form-select {
            appearance: none;
            cursor: pointer;
        }

        .form-input::placeholder { color: rgba(148,163,184,0.5); }

        .form-input:focus, .form-select:focus {
            border-color: var(--accent);
            background: rgba(249,115,22,0.03);
            box-shadow: 0 0 0 3px rgba(249,115,22,0.08);
        }

        .form-input:focus + i,
        .form-select:focus + i {
            color: var(--accent);
        }

        .btn-submit {
            width: 100%;
            padding: 0.9rem;
            background: linear-gradient(135deg, var(--accent), #ea580c);
            color: white;
            border: none;
            border-radius: 10px;
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 0.5rem;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 30px var(--accent-glow);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .auth-divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1.5rem 0;
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        .auth-divider::before,
        .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .auth-switch {
            text-align: center;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .auth-switch a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }

        .auth-switch a:hover { opacity: 0.8; }

        /* Right panel */
        .auth-visual-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(20,184,166,0.05), rgba(139,92,246,0.03));
        }

        .auth-visual-bg {
            position: absolute;
            inset: 0;
            overflow: hidden;
        }

        .auth-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            opacity: 0.3;
            animation: float 15s ease-in-out infinite;
        }

        .auth-orb-1 { width: 400px; height: 400px; background: var(--teal); top: -100px; left: -100px; }
        .auth-orb-2 { width: 300px; height: 300px; background: var(--violet); bottom: -80px; right: -80px; animation-delay: -5s; }
        .auth-orb-3 { width: 200px; height: 200px; background: var(--accent); top: 50%; left: 50%; animation-delay: -10s; }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(20px, -20px) scale(1.05); }
            66% { transform: translate(-15px, 15px) scale(0.95); }
        }

        .auth-visual-content {
            position: relative;
            z-index: 1;
            text-align: center;
            padding: 3rem;
            max-width: 400px;
        }

        .auth-visual-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--teal), var(--violet));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin: 0 auto 2rem;
            position: relative;
        }

        .auth-visual-icon::after {
            content: '';
            position: absolute;
            inset: -8px;
            border-radius: 24px;
            border: 2px dashed rgba(20,184,166,0.3);
            animation: spin 20s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .auth-visual-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .auth-visual-desc {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.7;
        }

        .auth-visual-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 2rem;
        }

        .auth-stat {
            padding: 1rem;
            background: var(--glass);
            border: 1px solid var(--border);
            border-radius: 12px;
            text-align: center;
        }

        .auth-stat-value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent);
        }

        .auth-stat-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 0.25rem;
        }

        @media (max-width: 968px) {
            .auth-layout { grid-template-columns: 1fr; }
            .auth-visual-panel { display: none; }
            .auth-form-panel { padding: 2rem 1.5rem; }
        }

        @media (max-width: 480px) {
            .auth-form-panel { padding: 1.5rem 1rem; }
            .auth-title { font-size: 1.5rem; }
            .form-row-2 { grid-template-columns: 1fr; gap: 0; }
        }
    </style>
</head>
<body>

<div class="auth-layout">
    <!-- Left: Form -->
    <div class="auth-form-panel">
        <div class="auth-form-wrapper">
            <a href="index.php" class="auth-logo">
                <div class="auth-logo-icon"><i class="bi bi-graph-up-arrow"></i></div>
                <span>PredictEd</span>
            </a>

            <h1 class="auth-title">Create your account</h1>
            <p class="auth-subtitle">Start predicting your academic performance in seconds.</p>

            <?php if ($error): ?>
                <div class="auth-alert auth-alert-error">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" id="registerForm">
                <div class="form-group">
                    <label class="form-label" for="full_name">Full Name</label>
                    <div class="form-input-wrap">
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            class="form-input"
                            placeholder="Enter your full name"
                            required
                            value="<?= htmlspecialchars($old['full_name']) ?>"
                        >
                        <i class="bi bi-person"></i>
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <div class="form-input-wrap">
                            <input
                                type="text"
                                id="username"
                                name="username"
                                class="form-input"
                                placeholder="Choose a username"
                                required
                                value="<?= htmlspecialchars($old['username']) ?>"
                            >
                            <i class="bi bi-at"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <div class="form-input-wrap">
                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-input"
                                placeholder="you@example.com"
                                required
                                value="<?= htmlspecialchars($old['email']) ?>"
                            >
                            <i class="bi bi-envelope"></i>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="role">I am a</label>
                    <div class="form-input-wrap">
                        <select id="role" name="role" class="form-select form-input" style="padding-left:2.8rem">
                            <option value="student" <?= $old['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                            <option value="lecturer" <?= $old['role'] === 'lecturer' ? 'selected' : '' ?>>Lecturer</option>
                        </select>
                        <i class="bi bi-briefcase"></i>
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="form-input-wrap">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-input"
                                placeholder="Min. 6 characters"
                                required
                                minlength="6"
                            >
                            <i class="bi bi-lock"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm</label>
                        <div class="form-input-wrap">
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                class="form-input"
                                placeholder="Repeat password"
                                required
                                minlength="6"
                            >
                            <i class="bi bi-lock-fill"></i>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    Create Account <i class="bi bi-arrow-right" style="margin-left:0.5rem"></i>
                </button>
            </form>

            <div class="auth-divider">or</div>

            <p class="auth-switch">
                Already have an account? <a href="login.php">Sign in</a>
            </p>
        </div>
    </div>

    <!-- Right: Visual -->
    <div class="auth-visual-panel">
        <div class="auth-visual-bg">
            <div class="auth-orb auth-orb-1"></div>
            <div class="auth-orb auth-orb-2"></div>
            <div class="auth-orb auth-orb-3"></div>
        </div>
        <div class="auth-visual-content">
            <div class="auth-visual-icon"><i class="bi bi-rocket-takeoff"></i></div>
            <h2 class="auth-visual-title">Start Your Journey</h2>
            <p class="auth-visual-desc">Create a free account and get instant access to AI-powered academic predictions.</p>
            <div class="auth-visual-stats">
                <div class="auth-stat">
                    <div class="auth-stat-value">5K+</div>
                    <div class="auth-stat-label">Students</div>
                </div>
                <div class="auth-stat">
                    <div class="auth-stat-value">92%</div>
                    <div class="auth-stat-label">Accuracy</div>
                </div>
                <div class="auth-stat">
                    <div class="auth-stat-value">10K+</div>
                    <div class="auth-stat-label">Predictions</div>
                </div>
                <div class="auth-stat">
                    <div class="auth-stat-value">Free</div>
                    <div class="auth-stat-label">Always</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('registerForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat" style="animation:spin 1s linear infinite"></i> Creating account...';
});
</script>

</body>
</html>
