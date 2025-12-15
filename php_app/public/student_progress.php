<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';

session_start();
Auth::requireAuth();

// Only lecturers can access student progress
if ($_SESSION['role'] !== 'lecturer') {
    $_SESSION['error'] = "Access denied. This page is for lecturers only.";
    Utils::redirect('dashboard.php');
}

$db = Database::getInstance();
$lecturer_id = $_SESSION['user_id'];

// Get selected group or all groups
$selected_group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;

// Build query for students
$query = "
    SELECT DISTINCT u.id, u.username, u.email, u.created_at as joined_date
    FROM users u
    JOIN group_students gs ON u.id = gs.student_id
    JOIN lecturer_groups lg ON gs.group_id = lg.id
    WHERE lg.lecturer_id = ?
";

$params = [$lecturer_id];

if ($selected_group_id > 0) {
    $query .= " AND lg.id = ?";
    $params[] = $selected_group_id;
    
    // Verify lecturer owns this group
    $group = $db->fetchOne(
        "SELECT * FROM lecturer_groups WHERE id = ? AND lecturer_id = ?",
        [$selected_group_id, $lecturer_id]
    );
    
    if (!$group) {
        $_SESSION['error'] = "Group not found or access denied.";
        Utils::redirect('student_progress.php');
    }
}

$query .= " ORDER BY u.username";

$students = $db->fetchAll($query, $params);

// Get lecturer's groups for filter
$groups = $db->fetchAll(
    "SELECT * FROM lecturer_groups WHERE lecturer_id = ? ORDER BY group_name",
    [$lecturer_id]
);

// Calculate progress metrics for each student
$student_progress = [];
foreach ($students as $student) {
    $progress_data = calculateStudentProgress($db, $student['id']);
    $student_progress[] = [
        'student' => $student,
        'progress' => $progress_data
    ];
}

// Helper function to calculate student progress
function calculateStudentProgress($db, $student_id) {
    // Get all predictions for student
    $predictions = $db->fetchAll(
        "SELECT * FROM predictions 
         WHERE user_id = ? 
         ORDER BY created_at ASC",
        [$student_id]
    );
    
    $total_predictions = count($predictions);
    
    if ($total_predictions === 0) {
        return [
            'total_predictions' => 0,
            'improvement' => 0,
            'current_performance' => 0,
            'starting_performance' => 0,
            'progress_status' => 'no_data',
            'prediction_trend' => [],
            'consistency_score' => 0
        ];
    }
    
    // Calculate improvement
    $first_prediction = $predictions[0];
    $latest_prediction = end($predictions);
    $improvement = $latest_prediction['predicted_performance'] - $first_prediction['predicted_performance'];
    
    // Calculate trend data (last 6 predictions)
    $recent_predictions = array_slice($predictions, -6);
    $trend_data = [];
    foreach ($recent_predictions as $prediction) {
        $trend_data[] = [
            'date' => $prediction['created_at'],
            'performance' => $prediction['predicted_performance'],
            'confidence' => $prediction['confidence_level']
        ];
    }
    
    // Calculate consistency (standard deviation of recent performances)
    $recent_performances = array_column($recent_predictions, 'predicted_performance');
    $consistency_score = calculateConsistency($recent_performances);
    
    // Determine progress status
    if ($total_predictions < 2) {
        $progress_status = 'insufficient_data';
    } elseif ($improvement > 5) {
        $progress_status = 'improving';
    } elseif ($improvement > -2) {
        $progress_status = 'stable';
    } else {
        $progress_status = 'declining';
    }
    
    return [
        'total_predictions' => $total_predictions,
        'improvement' => round($improvement, 1),
        'current_performance' => round($latest_prediction['predicted_performance'], 1),
        'starting_performance' => round($first_prediction['predicted_performance'], 1),
        'progress_status' => $progress_status,
        'prediction_trend' => $trend_data,
        'consistency_score' => $consistency_score,
        'last_prediction_date' => $latest_prediction['created_at']
    ];
}

// Helper function to calculate consistency (lower is better)
function calculateConsistency($performances) {
    if (count($performances) < 2) return 100; // Perfect consistency with only one data point
    
    $mean = array_sum($performances) / count($performances);
    $variance = 0.0;
    foreach ($performances as $performance) {
        $variance += pow($performance - $mean, 2);
    }
    $variance /= count($performances);
    $std_dev = sqrt($variance);
    
    // Convert to consistency score (0-100, higher is more consistent)
    $consistency = max(0, 100 - ($std_dev * 2));
    return round($consistency, 1);
}

// Get students needing attention (declining or low performance)
$students_needing_attention = array_filter($student_progress, function($item) {
    return $item['progress']['progress_status'] === 'declining' || 
           ($item['progress']['current_performance'] < 60 && $item['progress']['total_predictions'] >= 2);
});

// Get top improving students
$top_improving_students = array_filter($student_progress, function($item) {
    return $item['progress']['progress_status'] === 'improving' && 
           $item['progress']['improvement'] >= 10;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Progress Tracking - Student Performance Predictor</title>
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
                        <a class="nav-link" href="lecturer_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="lecturer_groups.php">My Groups</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="student_progress.php">Student Progress</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="batch_prediction_result.php">Batch Analysis</a>
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

    <div class="container-fluid mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="fw-bold">Student Progress Tracking</h1>
                        <p class="text-muted mb-0">Monitor individual student improvement and provide targeted support</p>
                    </div>
                    <div class="btn-group">
                        <a href="export_progress.php?group_id=<?php echo $selected_group_id; ?>" class="btn btn-success">
                            <i class="bi bi-download"></i> Export Progress Report
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Group Filter -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-center">
                            <div class="col-md-4">
                                <label for="group_id" class="form-label">Filter by Group:</label>
                                <select name="group_id" id="group_id" class="form-select" onchange="this.form.submit()">
                                    <option value="0">All Groups</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo $group['id']; ?>" 
                                            <?php echo $selected_group_id == $group['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($group['group_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <div class="d-flex gap-2">
                                    <span class="badge bg-primary">
                                        <i class="bi bi-people"></i> <?php echo count($students); ?> Students
                                    </span>
                                    <?php if (!empty($students_needing_attention)): ?>
                                        <span class="badge bg-danger">
                                            <i class="bi bi-exclamation-triangle"></i> <?php echo count($students_needing_attention); ?> Need Attention
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($top_improving_students)): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-trophy"></i> <?php echo count($top_improving_students); ?> Top Improvers
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <?php if (!empty($student_progress)): ?>
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title">
                                    <?php echo round(array_sum(array_column(array_column($student_progress, 'progress'), 'current_performance')) / count($student_progress), 1); ?>%
                                </h4>
                                <p class="card-text">Average Performance</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-speedometer2 display-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title">
                                    <?php echo round(array_sum(array_column(array_column($student_progress, 'progress'), 'improvement')) / count($student_progress), 1); ?>%
                                </h4>
                                <p class="card-text">Average Improvement</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-graph-up-arrow display-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title">
                                    <?php echo round(array_sum(array_column(array_column($student_progress, 'progress'), 'consistency_score')) / count($student_progress), 1); ?>%
                                </h4>
                                <p class="card-text">Average Consistency</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-shield-check display-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo count($students); ?></h4>
                                <p class="card-text">Students Tracked</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-person-lines-fill display-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Students Needing Attention -->
        <?php if (!empty($students_needing_attention)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-exclamation-triangle"></i> Students Needing Attention
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Current Performance</th>
                                        <th>Improvement</th>
                                        <th>Consistency</th>
                                        <th>Status</th>
                                        <th>Last Prediction</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students_needing_attention as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['student']['username']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($item['student']['email']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $item['progress']['current_performance'] >= 70 ? 'success' : 
                                                     ($item['progress']['current_performance'] >= 50 ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo $item['progress']['current_performance']; ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-<?php echo $item['progress']['improvement'] >= 0 ? 'success' : 'danger'; ?>">
                                                <i class="bi bi-arrow-<?php echo $item['progress']['improvement'] >= 0 ? 'up' : 'down'; ?>"></i>
                                                <?php echo $item['progress']['improvement']; ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $item['progress']['consistency_score'] >= 80 ? 'success' : 
                                                     ($item['progress']['consistency_score'] >= 60 ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo $item['progress']['consistency_score']; ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $item['progress']['progress_status'] === 'improving' ? 'success' : 
                                                     ($item['progress']['progress_status'] === 'stable' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($item['progress']['progress_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo Utils::formatDate($item['progress']['last_prediction_date']); ?></small>
                                        </td>
                                        <td>
                                            <a href="individual_progress.php?student_id=<?php echo $item['student']['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-graph-up"></i> View Progress
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- All Students Progress -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">All Students Progress Overview</h5>
                        <span class="badge bg-primary"><?php echo count($students); ?> students</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($student_progress)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-people display-1 text-muted"></i>
                                <h4 class="text-muted mt-3">No Students Found</h4>
                                <p class="text-muted">
                                    <?php echo $selected_group_id > 0 ? 'No students in this group yet.' : 'No students in any of your groups yet.'; ?>
                                </p>
                                <a href="lecturer_groups.php" class="btn btn-primary">
                                    <i class="bi bi-people"></i> Manage Groups
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Predictions</th>
                                            <th>Current Performance</th>
                                            <th>Starting Performance</th>
                                            <th>Improvement</th>
                                            <th>Consistency</th>
                                            <th>Progress Status</th>
                                            <th>Last Activity</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($student_progress as $item): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($item['student']['username']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($item['student']['email']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $item['progress']['total_predictions']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $item['progress']['current_performance'] >= 70 ? 'success' : 
                                                         ($item['progress']['current_performance'] >= 50 ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo $item['progress']['current_performance']; ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo $item['progress']['starting_performance']; ?>%</small>
                                            </td>
                                            <td>
                                                <span class="text-<?php echo $item['progress']['improvement'] >= 0 ? 'success' : 'danger'; ?>">
                                                    <i class="bi bi-arrow-<?php echo $item['progress']['improvement'] >= 0 ? 'up' : 'down'; ?>"></i>
                                                    <?php echo $item['progress']['improvement']; ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $item['progress']['consistency_score'] >= 80 ? 'success' : 
                                                         ($item['progress']['consistency_score'] >= 60 ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo $item['progress']['consistency_score']; ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $item['progress']['progress_status'] === 'improving' ? 'success' : 
                                                         ($item['progress']['progress_status'] === 'stable' ? 'warning' : 
                                                         ($item['progress']['progress_status'] === 'declining' ? 'danger' : 'secondary')); 
                                                ?>">
                                                    <?php echo ucfirst($item['progress']['progress_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php if ($item['progress']['total_predictions'] > 0): ?>
                                                        <?php echo Utils::formatDate($item['progress']['last_prediction_date']); ?>
                                                    <?php else: ?>
                                                        Never
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="individual_progress.php?student_id=<?php echo $item['student']['id']; ?>" 
                                                       class="btn btn-outline-primary">
                                                        <i class="bi bi-graph-up"></i> Progress
                                                    </a>
                                                    <button class="btn btn-outline-info view-predictions" 
                                                            data-student-id="<?php echo $item['student']['id']; ?>">
                                                        <i class="bi bi-eye"></i> Predictions
                                                    </button>
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
            </div>
        </div>
    </div>

    <!-- Student Predictions Modal -->
    <div class="modal fade" id="predictionsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Student Predictions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="predictionsContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // View student predictions
        document.querySelectorAll('.view-predictions').forEach(button => {
            button.addEventListener('click', function() {
                const studentId = this.getAttribute('data-student-id');
                
                fetch('student_predictions.php?student_id=' + studentId)
                    .then(response => response.text())
                    .then(html => {
                        document.getElementById('predictionsContent').innerHTML = html;
                        const modal = new bootstrap.Modal(document.getElementById('predictionsModal'));
                        modal.show();
                    })
                    .catch(error => {
                        console.error('Error loading student predictions:', error);
                        document.getElementById('predictionsContent').innerHTML = 
                            '<div class="alert alert-danger">Error loading predictions</div>';
                    });
            });
        });
    </script>
</body>
</html>