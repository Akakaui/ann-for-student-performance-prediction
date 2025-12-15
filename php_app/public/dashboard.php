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

// Check if user is logged in
if (!Utils::isLoggedIn()) {
    $_SESSION['error'] = "Please log in to access the dashboard.";
    Utils::redirect('login.php');
}

// Verify session variables are set
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    session_destroy();
    $_SESSION['error'] = "Session invalid. Please log in again.";
    Utils::redirect('login.php');
}

$db = Database::getInstance();
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Get user data from database to ensure it exists
$user = (new Auth())->getUser($user_id);
if (!$user) {
    session_destroy();
    $_SESSION['error'] = "User account not found. Please log in again.";
    Utils::redirect('login.php');
}

// Get statistics based on user role
if ($role === 'student') {
    $predictionCount = $db->fetchOne(
        "SELECT COUNT(*) as count FROM predictions WHERE user_id = ?",
        [$user_id]
    )['count'];

    $recentPredictions = $db->fetchAll(
        "SELECT exam_type, predicted_performance, created_at 
         FROM predictions 
         WHERE user_id = ? 
         ORDER BY created_at DESC 
         LIMIT 5",
        [$user_id]
    );

} elseif ($role === 'lecturer') {
    $studentCount = $db->fetchOne(
        "SELECT COUNT(DISTINCT student_id) as count 
         FROM group_students gs 
         JOIN lecturer_groups lg ON gs.group_id = lg.id 
         WHERE lg.lecturer_id = ?",
        [$user_id]
    )['count'];

    $groupCount = $db->fetchOne(
        "SELECT COUNT(*) as count FROM lecturer_groups WHERE lecturer_id = ?",
        [$user_id]
    )['count'];

    $recentGroups = $db->fetchAll(
        "SELECT group_name, description, created_at 
         FROM lecturer_groups 
         WHERE lecturer_id = ? 
         ORDER BY created_at DESC 
         LIMIT 5",
        [$user_id]
    );

} elseif ($role === 'admin') {
    $userCount = $db->fetchOne("SELECT COUNT(*) as count FROM users")['count'];
    $lecturerCount = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'lecturer'")['count'];
    $studentCount = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'student'")['count'];
    $pendingLecturers = $db->fetchOne(
        "SELECT COUNT(*) as count FROM lecturers WHERE verification_status = 'pending'"
    )['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - University Performance Predictor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-graph-up"></i> University Predictor
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
                    </li>
                    <?php if ($role === 'student'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="prediction_form.php">University Performance Prediction</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_predictions.php">My Predictions</a>
                        </li>
                    <?php elseif ($role === 'lecturer'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="prediction_form.php">Batch Prediction</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="lecturer_groups.php">Student Groups</a>
                        </li>
                    <?php elseif ($role === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_users.php">User Management</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_lecturers.php">Lecturer Verification</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($username); ?> (<?php echo ucfirst($role); ?>)
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
        <!-- Welcome Message -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="welcome-card card bg-light">
                    <div class="card-body">
                        <h1 class="h3 card-title">Welcome back, <?php echo htmlspecialchars($username); ?>!</h1>
                        <p class="card-text text-muted">
                            <?php
                            $roleMessages = [
                                'student' => 'Get AI-powered predictions for your university performance based on your WAEC/NECO grades, current CGPA, and socio-economic factors.',
                                'lecturer' => 'Manage student groups and analyze university performance predictions using WAEC/NECO data and academic metrics.',
                                'admin' => 'Manage system users and verify lecturer accounts for university performance prediction system access.'
                            ];
                            echo $roleMessages[$role] ?? 'Welcome to University Performance Predictor.';
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <?php if ($role === 'student'): ?>
                <div class="col-md-4">
                    <div class="card stat-card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $predictionCount; ?></h4>
                                    <p class="card-text">University Predictions</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-graph-up-arrow display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title">WAEC/NECO</h4>
                                    <p class="card-text">Input Data Source</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-journal-text display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card text-white bg-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title">AI Powered</h4>
                                    <p class="card-text">Performance Analysis</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-cpu display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($role === 'lecturer'): ?>
                <div class="col-md-4">
                    <div class="card stat-card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $studentCount; ?></h4>
                                    <p class="card-text">University Students</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-people display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $groupCount; ?></h4>
                                    <p class="card-text">Student Groups</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-collection display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card text-white bg-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title">Batch</h4>
                                    <p class="card-text">University Predictions</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-graph-up display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($role === 'admin'): ?>
                <div class="col-md-3">
                    <div class="card stat-card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $userCount; ?></h4>
                                    <p class="card-text">Total Users</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-people display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $studentCount; ?></h4>
                                    <p class="card-text">University Students</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-person display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $lecturerCount; ?></h4>
                                    <p class="card-text">Lecturers</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-person-badge display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card text-white bg-danger">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo $pendingLecturers; ?></h4>
                                    <p class="card-text">Pending Verification</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-clock display-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-clock-history"></i> Recent Activity
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($role === 'student'): ?>
                            <?php if (empty($recentPredictions)): ?>
                                <p class="text-muted">No university performance predictions yet. <a href="prediction_form.php">Create your first prediction!</a></p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Data Source</th>
                                                <th>Predicted Performance</th>
                                                <th>Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentPredictions as $prediction): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($prediction['exam_type']); ?> Grades</td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $prediction['predicted_performance'] >= 70 ? 'success' : ($prediction['predicted_performance'] >= 50 ? 'warning' : 'danger'); ?>">
                                                            <?php echo htmlspecialchars($prediction['predicted_performance']); ?>%
                                                        </span>
                                                    </td>
                                                    <td><?php echo Utils::formatDate($prediction['created_at'], 'M j, Y g:i A'); ?></td>
                                                    <td>
                                                        <a href="my_predictions.php" class="btn btn-sm btn-outline-primary">View Details</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                        <?php elseif ($role === 'lecturer'): ?>
                            <?php if (empty($recentGroups)): ?>
                                <p class="text-muted">No groups created yet. <a href="lecturer_groups.php">Create your first student group!</a></p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Group Name</th>
                                                <th>Description</th>
                                                <th>Created Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentGroups as $group): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($group['group_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($group['description'] ?: 'No description'); ?></td>
                                                    <td><?php echo Utils::formatDate($group['created_at'], 'M j, Y'); ?></td>
                                                    <td>
                                                        <a href="lecturer_groups.php" class="btn btn-sm btn-outline-primary">Manage</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                        <?php elseif ($role === 'admin'): ?>
                            <p class="text-muted">Admin dashboard overview. Use the navigation to manage users and verify lecturers for university performance prediction system access.</p>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="d-grid gap-2">
                                        <a href="admin_users.php" class="btn btn-outline-primary">
                                            <i class="bi bi-people"></i> Manage Users
                                        </a>
                                        <a href="admin_lecturers.php" class="btn btn-outline-warning">
                                            <i class="bi bi-person-check"></i> Verify Lecturers
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>