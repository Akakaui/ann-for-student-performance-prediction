<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $auth = new Auth();
        $result = $auth->login($username, $password);
        if ($result['success']) {
            header("Location: dashboard.php");
            exit;
        } else {
            $error = $result['message'] ?? 'Invalid credentials. Please try again.';
        }
    }
}

if (isset($_GET['registered'])) {
    $success = 'Account created successfully! Please log in.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — PredictEd</title>
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

        /* Left panel - form */
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
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(circle at 20% 80%, rgba(249,115,22,0.04), transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(139,92,246,0.03), transparent 50%);
        }

        .auth-form-wrapper {
            max-width: 420px;
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
            margin-bottom: 3rem;
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
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
        }

        .auth-subtitle {
            color: var(--text-muted);
            font-size: 1rem;
            margin-bottom: 2.5rem;
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

        .auth-alert-success {
            background: rgba(16,185,129,0.1);
            border: 1px solid rgba(16,185,129,0.2);
            color: #6ee7b7;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
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

        .form-input {
            width: 100%;
            padding: 0.85rem 1rem 0.85rem 2.8rem;
            background: var(--glass);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            font-size: 0.95rem;
            transition: all 0.2s;
            outline: none;
        }

        .form-input::placeholder { color: rgba(148,163,184,0.5); }

        .form-input:focus {
            border-color: var(--accent);
            background: rgba(249,115,22,0.03);
            box-shadow: 0 0 0 3px rgba(249,115,22,0.08);
        }

        .form-input:focus + i,
        .form-input:focus ~ i {
            color: var(--accent);
        }

        .form-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .form-check input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--accent);
            cursor: pointer;
        }

        .form-check label {
            font-size: 0.85rem;
            color: var(--text-muted);
            cursor: pointer;
        }

        .form-forgot {
            font-size: 0.85rem;
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.2s;
        }

        .form-forgot:hover { opacity: 0.8; }

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
            position: relative;
            overflow: hidden;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 30px var(--accent-glow);
        }

        .btn-submit:active { transform: translateY(0); }

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
            transition: opacity 0.2s;
        }

        .auth-switch a:hover { opacity: 0.8; }

        /* Right panel - visual */
        .auth-visual-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(249,115,22,0.05), rgba(139,92,246,0.03));
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

        .auth-orb-1 { width: 400px; height: 400px; background: var(--accent); top: -100px; right: -100px; }
        .auth-orb-2 { width: 300px; height: 300px; background: var(--teal); bottom: -80px; left: -80px; animation-delay: -5s; }
        .auth-orb-3 { width: 200px; height: 200px; background: var(--violet); top: 50%; left: 50%; animation-delay: -10s; }

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
            background: linear-gradient(135deg, var(--accent), var(--rose));
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
            border: 2px dashed rgba(249,115,22,0.3);
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

        .auth-visual-features {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-top: 2rem;
            text-align: left;
        }

        .auth-visual-feature {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: var(--glass);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .auth-visual-feature i {
            color: var(--accent);
            font-size: 1rem;
        }

        /* Responsive */
        @media (max-width: 968px) {
            .auth-layout { grid-template-columns: 1fr; }
            .auth-visual-panel { display: none; }
            .auth-form-panel { padding: 2rem 1.5rem; }
        }

        @media (max-width: 480px) {
            .auth-form-panel { padding: 1.5rem 1rem; }
            .auth-title { font-size: 1.6rem; }
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

            <h1 class="auth-title">Welcome back</h1>
            <p class="auth-subtitle">Sign in to access your predictions and academic insights.</p>

            <?php if ($error): ?>
                <div class="auth-alert auth-alert-error">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="auth-alert auth-alert-success">
                    <i class="bi bi-check-circle-fill"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" id="loginForm">
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <div class="form-input-wrap">
                        <input
                            type="text"
                            id="username"
                            name="username"
                            class="form-input"
                            placeholder="Enter your username"
                            required
                            autocomplete="username"
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        >
                        <i class="bi bi-person"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="form-input-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="Enter your password"
                            required
                            autocomplete="current-password"
                        >
                        <i class="bi bi-lock"></i>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-check">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="#" class="form-forgot">Forgot password?</a>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    Sign In <i class="bi bi-arrow-right" style="margin-left:0.5rem"></i>
                </button>
            </form>

            <div class="auth-divider">or</div>

            <p class="auth-switch">
                Don't have an account? <a href="register.php">Create one free</a>
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
            <div class="auth-visual-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <h2 class="auth-visual-title">Your Academic Future Awaits</h2>
            <p class="auth-visual-desc">Join thousands of students using AI-powered predictions to plan their academic success.</p>
            <div class="auth-visual-features">
                <div class="auth-visual-feature">
                    <i class="bi bi-check-circle-fill"></i>
                    AI-powered WAEC/NECO predictions
                </div>
                <div class="auth-visual-feature">
                    <i class="bi bi-check-circle-fill"></i>
                    Personalized study recommendations
                </div>
                <div class="auth-visual-feature">
                    <i class="bi bi-check-circle-fill"></i>
                    Track performance over time
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat" style="animation:spin 1s linear infinite"></i> Signing in...';
});
</script>

</body>
</html>
