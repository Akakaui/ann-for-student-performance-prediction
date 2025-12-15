<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';

session_start();
Auth::requireAuth();

$auth = new Auth();
$user = $auth->getUser($_SESSION['user_id']);
$db = Database::getInstance();

$error = '';
$success = '';
$active_tab = $_GET['tab'] ?? 'profile';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = Utils::sanitize($_POST['email'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';

    try {
        if (!Utils::verifyCSRFToken($csrf_token)) {
            throw new Exception("Invalid CSRF token");
        }

        if (empty($email)) {
            throw new Exception("Email is required");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        // Check if email already exists (excluding current user)
        $existingUser = $db->fetchOne(
            "SELECT id FROM users WHERE email = ? AND id != ?",
            [$email, $_SESSION['user_id']]
        );

        if ($existingUser) {
            throw new Exception("Email already exists");
        }

        $auth->updateProfile($_SESSION['user_id'], ['email' => $email]);
        $success = "Profile updated successfully!";
        $user['email'] = $email; // Update local user data

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    try {
        if (!Utils::verifyCSRFToken($csrf_token)) {
            throw new Exception("Invalid CSRF token");
        }

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            throw new Exception("All password fields are required");
        }

        if ($new_password !== $confirm_password) {
            throw new Exception("New passwords do not match");
        }

        if (strlen($new_password) < 6) {
            throw new Exception("New password must be at least 6 characters long");
        }

        $auth->changePassword($_SESSION['user_id'], $current_password, $new_password);
        $success = "Password changed successfully!";

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get user statistics
if ($_SESSION['role'] === 'student') {
    $prediction_stats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_predictions,
            AVG(predicted_performance) as avg_performance,
            MAX(predicted_performance) as best_performance,
            MIN(predicted_performance) as worst_performance
         FROM predictions 
         WHERE user_id = ?",
        [$_SESSION['user_id']]
    );
} elseif ($_SESSION['role'] === 'lecturer') {
    $lecturer_stats = $db->fetchOne(
        "SELECT 
            COUNT(DISTINCT gs.student_id) as total_students,
            COUNT(DISTINCT lg.id) as total_groups
         FROM lecturer_groups lg
         LEFT JOIN group_students gs ON lg.id = gs.group_id
         WHERE lg.lecturer_id = ?",
        [$_SESSION['user_id']]
    );
}

$csrf_token = Utils::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Student Performance Predictor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-graph-up"></i> Student Predictor
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <?php if ($_SESSION['role'] === 'student'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="prediction_form.php">New Prediction</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_predictions.php">My Predictions</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo $_SESSION['username']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <!-- Profile Header -->
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-person-circle display-1 text-primary"></i>
                        </div>
                        <h3 class="card-title"><?php echo $user['username']; ?></h3>
                        <p class="card-text">
                            <span class="badge bg-<?php 
                                echo $user['role'] === 'admin' ? 'danger' : 
                                     ($user['role'] === 'lecturer' ? 'warning' : 'primary'); 
                            ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                            <?php if ($user['role'] === 'lecturer'): ?>
                                <span class="badge bg-<?php 
                                    echo $user['verification_status'] === 'approved' ? 'success' : 
                                         ($user['verification_status'] === 'pending' ? 'warning' : 'danger'); 
                                ?> ms-1">
                                    <?php echo ucfirst($user['verification_status']); ?>
                                </span>
                            <?php endif; ?>
                        </p>
                        <p class="text-muted">Member since <?php echo Utils::formatDate($user['created_at'], 'F Y'); ?></p>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" id="profileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_tab === 'profile' ? 'active' : ''; ?>" 
                                id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button">
                            <i class="bi bi-person"></i> Profile Information
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_tab === 'password' ? 'active' : ''; ?>" 
                                id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button">
                            <i class="bi bi-shield-lock"></i> Change Password
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_tab === 'stats' ? 'active' : ''; ?>" 
                                id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button">
                            <i class="bi bi-graph-up"></i> Statistics
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="profileTabsContent">
                    <!-- Profile Information Tab -->
                    <div class="tab-pane fade <?php echo $active_tab === 'profile' ? 'show active' : ''; ?>" 
                         id="profile" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Profile Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="update_profile" value="1">
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="username" class="form-label">Username</label>
                                            <input type="text" class="form-control" id="username" 
                                                   value="<?php echo $user['username']; ?>" readonly>
                                            <div class="form-text">Username cannot be changed</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">Email Address *</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo $user['email']; ?>" required>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Account Role</label>
                                            <input type="text" class="form-control" 
                                                   value="<?php echo ucfirst($user['role']); ?>" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Account Status</label>
                                            <input type="text" class="form-control" 
                                                   value="<?php echo $user['is_verified'] ? 'Verified' : 'Not Verified'; ?>" readonly>
                                        </div>
                                    </div>

                                    <?php if ($user['role'] === 'lecturer'): ?>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Institution</label>
                                                <input type="text" class="form-control" 
                                                       value="<?php echo $user['institution'] ?? 'Not specified'; ?>" readonly>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Department</label>
                                                <input type="text" class="form-control" 
                                                       value="<?php echo $user['department'] ?? 'Not specified'; ?>" readonly>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">Update Profile</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password Tab -->
                    <div class="tab-pane fade <?php echo $active_tab === 'password' ? 'show active' : ''; ?>" 
                         id="password" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="change_password" value="1">
                                    
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password *</label>
                                        <input type="password" class="form-control" id="current_password" 
                                               name="current_password" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password *</label>
                                        <input type="password" class="form-control" id="new_password" 
                                               name="new_password" required>
                                        <div class="form-text">Minimum 6 characters</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                        <input type="password" class="form-control" id="confirm_password" 
                                               name="confirm_password" required>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">Change Password</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Tab -->
                    <div class="tab-pane fade <?php echo $active_tab === 'stats' ? 'show active' : ''; ?>" 
                         id="stats" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Usage Statistics</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($_SESSION['role'] === 'student' && $prediction_stats): ?>
                                    <div class="row text-center">
                                        <div class="col-md-3 mb-3">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h3 class="text-primary"><?php echo $prediction_stats['total_predictions']; ?></h3>
                                                    <p class="text-muted mb-0">Total Predictions</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h3 class="text-success"><?php echo $prediction_stats['avg_performance'] ? round($prediction_stats['avg_performance'], 1) : 0; ?>%</h3>
                                                    <p class="text-muted mb-0">Average Performance</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h3 class="text-warning"><?php echo $prediction_stats['best_performance'] ? round($prediction_stats['best_performance'], 1) : 0; ?>%</h3>
                                                    <p class="text-muted mb-0">Best Performance</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h3 class="text-danger"><?php echo $prediction_stats['worst_performance'] ? round($prediction_stats['worst_performance'], 1) : 0; ?>%</h3>
                                                    <p class="text-muted mb-0">Worst Performance</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php elseif ($_SESSION['role'] === 'lecturer' && $lecturer_stats): ?>
                                    <div class="row text-center">
                                        <div class="col-md-6 mb-3">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h3 class="text-primary"><?php echo $lecturer_stats['total_students']; ?></h3>
                                                    <p class="text-muted mb-0">Total Students</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h3 class="text-success"><?php echo $lecturer_stats['total_groups']; ?></h3>
                                                    <p class="text-muted mb-0">Student Groups</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php elseif ($_SESSION['role'] === 'admin'): ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-graph-up display-1 text-muted"></i>
                                        <p class="text-muted mt-3">Admin statistics are available on the admin dashboard.</p>
                                        <a href="dashboard.php" class="btn btn-primary">Go to Admin Dashboard</a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-graph-up display-1 text-muted"></i>
                                        <p class="text-muted mt-3">No statistics available yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>