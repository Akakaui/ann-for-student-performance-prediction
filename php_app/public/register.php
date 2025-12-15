<?php
// Session configuration must be set BEFORE session_start()
// Include config first to set session settings
require_once '../lib/config.php';

// Now start session with proper settings
session_start();

// Include other files after session start
require_once '../lib/database.php';
require_once '../lib/utils.php';
require_once '../lib/auth.php';

// Redirect if already logged in
if (Utils::isLoggedIn()) {
    Utils::redirect('dashboard.php');
}

$error = '';
$success = '';

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = Utils::sanitize($_POST['username'] ?? '');
    $email = Utils::sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = Utils::sanitize($_POST['role'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';

    try {
        // Validate CSRF token
        if (!Utils::verifyCSRFToken($csrf_token)) {
            throw new Exception("Security error: Invalid form submission.");
        }

        // Validate required fields
        if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
            throw new Exception("All fields are required.");
        }

        // Validate password match
        if ($password !== $confirm_password) {
            throw new Exception("Passwords do not match.");
        }

        // Validate password length
        if (strlen($password) < 6) {
            throw new Exception("Password must be at least 6 characters long.");
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        // Validate role
        if (!in_array($role, ['student', 'lecturer'])) {
            throw new Exception("Please select a valid role.");
        }

        // Create user account
        $auth = new Auth();
        $userId = $auth->register($username, $email, $password, $role);

        // Set success message based on role
        if ($role === 'student') {
            $successMessage = "Registration successful! You can now login.";
        } else {
            $successMessage = "Registration successful! Your lecturer account is pending admin verification.";
        }

        $_SESSION['success'] = $successMessage;
        Utils::redirect('login.php');

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Generate CSRF token
$csrf_token = Utils::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Student Performance Predictor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .auth-card {
            border: none;
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.95);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="auth-card card shadow">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <h2 class="card-title">Create Account</h2>
                            <p class="text-muted">Join Student Performance Predictor</p>
                        </div>

                        <!-- Display Errors -->
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <!-- Display Success Messages -->
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="role" class="form-label">Account Type *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select account type</option>
                                    <option value="student" <?php echo ($_POST['role'] ?? '') === 'student' ? 'selected' : ''; ?>>Student</option>
                                    <option value="lecturer" <?php echo ($_POST['role'] ?? '') === 'lecturer' ? 'selected' : ''; ?>>Lecturer</option>
                                </select>
                                <div class="form-text">
                                    Students: Immediate access. Lecturers: Require admin verification.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password *</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="form-text">Minimum 6 characters</div>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-lg">Create Account</button>
                            </div>
                        </form>

                        <div class="text-center">
                            <p class="mb-0">Already have an account? 
                                <a href="login.php" class="text-decoration-none">Sign in here</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>