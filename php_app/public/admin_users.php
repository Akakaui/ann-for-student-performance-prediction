<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';

session_start();
Auth::requireRole(['admin']);

$db = Database::getInstance();
$error = '';
$success = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = intval($_POST['user_id'] ?? 0);
    $csrf_token = $_POST['csrf_token'] ?? '';

    try {
        if (!Utils::verifyCSRFToken($csrf_token)) {
            throw new Exception("Invalid CSRF token");
        }

        if ($action === 'delete_user' && $user_id > 0) {
            // Prevent admin from deleting themselves
            if ($user_id === $_SESSION['user_id']) {
                throw new Exception("You cannot delete your own account");
            }

            $db->query("DELETE FROM users WHERE id = ?", [$user_id]);
            $success = "User deleted successfully";

        } elseif ($action === 'toggle_verification' && $user_id > 0) {
            $current_status = $db->fetchOne("SELECT is_verified FROM users WHERE id = ?", [$user_id]);
            $new_status = $current_status['is_verified'] ? 0 : 1;

            $db->query("UPDATE users SET is_verified = ? WHERE id = ?", [$new_status, $user_id]);
            $status_text = $new_status ? 'verified' : 'unverified';
            $success = "User {$status_text} successfully";

        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all users with their details
$users = $db->fetchAll("
    SELECT u.*, l.verification_status, l.institution, l.department 
    FROM users u 
    LEFT JOIN lecturers l ON u.id = l.user_id 
    ORDER BY u.created_at DESC
");

// Get user statistics
$user_stats = $db->fetchAll("
    SELECT 
        role,
        COUNT(*) as count,
        AVG(is_verified) * 100 as verified_percentage
    FROM users 
    GROUP BY role
");

$csrf_token = Utils::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Student Performance Predictor</title>
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
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_users.php">User Management</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_lecturers.php">Lecturer Verification</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo $_SESSION['username']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="fw-bold">User Management</h2>
                </div>
                <p class="text-muted">Manage all system users and their permissions.</p>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- User Statistics -->
        <div class="row mb-4">
            <?php foreach ($user_stats as $stat): ?>
                <div class="col-md-4">
                    <div class="card text-white bg-<?php 
                        echo $stat['role'] === 'admin' ? 'danger' : 
                             ($stat['role'] === 'lecturer' ? 'warning' : 'primary'); 
                    ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $stat['count']; ?></h4>
                                    <p class="card-text"><?php echo ucfirst($stat['role']); ?>s</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-<?php 
                                        echo $stat['role'] === 'admin' ? 'person-gear' : 
                                             ($stat['role'] === 'lecturer' ? 'person-badge' : 'person'); 
                                    ?> display-6"></i>
                                </div>
                            </div>
                            <?php if ($stat['role'] !== 'admin'): ?>
                                <div class="mt-2">
                                    <small>Verified: <?php echo round($stat['verified_percentage']); ?>%</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">All Users</h5>
            </div>
            <div class="card-body">
                <?php if (empty($users)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-people display-1 text-muted"></i>
                        <h4 class="text-muted mt-3">No Users Found</h4>
                        <p class="text-muted">There are no users in the system yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>User ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Registration Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td>
                                            <?php echo $user['username']; ?>
                                            <?php if ($user['id'] === $_SESSION['user_id']): ?>
                                                <span class="badge bg-info">You</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $user['email']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $user['role'] === 'admin' ? 'danger' : 
                                                     ($user['role'] === 'lecturer' ? 'warning' : 'primary'); 
                                            ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['role'] === 'lecturer'): ?>
                                                <span class="badge bg-<?php 
                                                    echo $user['verification_status'] === 'approved' ? 'success' : 
                                                         ($user['verification_status'] === 'pending' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo ucfirst($user['verification_status']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-<?php echo $user['is_verified'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $user['is_verified'] ? 'Verified' : 'Unverified'; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo Utils::formatDate($user['created_at'], 'M j, Y'); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                                    <?php if ($user['role'] !== 'lecturer'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <input type="hidden" name="action" value="toggle_verification">
                                                            <button type="submit" class="btn btn-<?php echo $user['is_verified'] ? 'warning' : 'success'; ?> btn-sm">
                                                                <i class="bi bi-<?php echo $user['is_verified'] ? 'x-circle' : 'check-circle'; ?>"></i>
                                                                <?php echo $user['is_verified'] ? 'Unverify' : 'Verify'; ?>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <button type="submit" class="btn btn-danger btn-sm">
                                                            <i class="bi bi-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted">Current User</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- System Information -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">System Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Database Information</h6>
                        <ul class="list-unstyled">
                            <?php
                            $total_predictions = $db->fetchOne("SELECT COUNT(*) as count FROM predictions")['count'];
                            $total_groups = $db->fetchOne("SELECT COUNT(*) as count FROM lecturer_groups")['count'];
                            ?>
                            <li><strong>Total Predictions:</strong> <?php echo $total_predictions; ?></li>
                            <li><strong>Student Groups:</strong> <?php echo $total_groups; ?></li>
                            <li><strong>System Version:</strong> <?php echo APP_VERSION; ?></li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Quick Actions</h6>
                        <div class="d-grid gap-2">
                            <a href="admin_lecturers.php" class="btn btn-outline-warning">
                                <i class="bi bi-person-check"></i> Manage Lecturer Verifications
                            </a>
                            <button class="btn btn-outline-info" onclick="exportUserData()">
                                <i class="bi bi-download"></i> Export User Data
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportUserData() {
            alert('Export feature would be implemented here. This would generate a CSV file of all user data.');
            // In a real implementation, this would redirect to an export script
            // window.location.href = 'export_users.php';
        }
    </script>
</body>
</html>