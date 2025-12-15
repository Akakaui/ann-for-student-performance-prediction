<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';

session_start();
Auth::requireAuth();

// Only lecturers can view group details
if ($_SESSION['role'] !== 'lecturer') {
    $_SESSION['error'] = "Access denied. This page is for lecturers only.";
    Utils::redirect('dashboard.php');
}

$db = Database::getInstance();

// Get group ID from URL
$group_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verify the lecturer owns this group
$group = $db->fetchOne(
    "SELECT * FROM lecturer_groups WHERE id = ? AND lecturer_id = ?",
    [$group_id, $_SESSION['user_id']]
);

if (!$group) {
    $_SESSION['error'] = "Group not found or access denied.";
    Utils::redirect('lecturer_groups.php');
}

// Get students in this group
$students = $db->fetchAll("
    SELECT u.id, u.username, u.email, u.created_at 
    FROM users u 
    JOIN group_students gs ON u.id = gs.student_id 
    WHERE gs.group_id = ? 
    ORDER BY u.username
", [$group_id]);

// Get predictions for students in this group
$predictions = $db->fetchAll("
    SELECT p.*, u.username, u.email 
    FROM predictions p
    JOIN users u ON p.user_id = u.id
    JOIN group_students gs ON u.id = gs.student_id
    WHERE gs.group_id = ?
    ORDER BY p.created_at DESC
", [$group_id]);

// Calculate group statistics
$total_students = count($students);
$total_predictions = count($predictions);
$average_performance = 0;
$exam_types = ['WAEC' => 0, 'NECO' => 0];

if ($total_predictions > 0) {
    $total_performance = 0;
    foreach ($predictions as $prediction) {
        $total_performance += $prediction['predicted_performance'];
        $exam_types[$prediction['exam_type']]++;
    }
    $average_performance = round($total_performance / $total_predictions, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Details - Student Performance Predictor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <a class="nav-link" href="lecturer_groups.php">My Groups</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="lecturer_group_details.php?id=<?php echo $group_id; ?>">Group Details</a>
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
        <!-- Page Header with Export Button -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="fw-bold">Group: <?php echo htmlspecialchars($group['group_name']); ?></h2>
                        <p class="text-muted mb-0">Manage students and view predictions for this group.</p>
                    </div>
                    <div class="btn-group">
                        <!-- EXPORT BUTTON ADDED HERE -->
                        <?php if (!empty($predictions)): ?>
                            <a href="export_predictions.php?group_id=<?php echo $group_id; ?>" class="btn btn-success">
                                <i class="bi bi-download"></i> Export Group Data
                            </a>
                        <?php endif; ?>
                        <a href="lecturer_groups.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Groups
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $total_students; ?></h4>
                                <p class="card-text">Total Students</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-people display-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $total_predictions; ?></h4>
                                <p class="card-text">Total Predictions</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-graph-up-arrow display-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $average_performance; ?>%</h4>
                                <p class="card-text">Avg Performance</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-speedometer2 display-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $exam_types['WAEC'] + $exam_types['NECO']; ?></h4>
                                <p class="card-text">Exams Analyzed</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-journal-text display-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Students in Group -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Students in This Group</h5>
                <span class="badge bg-primary"><?php echo $total_students; ?> students</span>
            </div>
            <div class="card-body">
                <?php if (empty($students)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-people display-1 text-muted"></i>
                        <h4 class="text-muted mt-3">No Students in Group</h4>
                        <p class="text-muted">Add students to this group to view their predictions.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Email</th>
                                    <th>Join Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['username']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td><?php echo Utils::formatDate($student['created_at']); ?></td>
                                        <td>
                                            <a href="#" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> View Profile
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Group Predictions -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Group Predictions</h5>
                <?php if (!empty($predictions)): ?>
                    <span class="badge bg-success"><?php echo $total_predictions; ?> predictions</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($predictions)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-graph-up display-1 text-muted"></i>
                        <h4 class="text-muted mt-3">No Predictions Yet</h4>
                        <p class="text-muted">Students in this group haven't made any predictions yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Date</th>
                                    <th>Exam Type</th>
                                    <th>Predicted Performance</th>
                                    <th>Confidence</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($predictions as $prediction): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($prediction['username']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($prediction['email']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo Utils::formatDate($prediction['created_at'], 'M j, Y'); ?><br>
                                            <small class="text-muted"><?php echo Utils::formatDate($prediction['created_at'], 'g:i A'); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $prediction['exam_type']; ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-<?php 
                                                    echo $prediction['predicted_performance'] >= 70 ? 'success' : 
                                                         ($prediction['predicted_performance'] >= 50 ? 'warning' : 'danger'); 
                                                ?> me-2">
                                                    <?php echo $prediction['predicted_performance']; ?>%
                                                </span>
                                                <div class="progress flex-grow-1" style="height: 6px; width: 80px;">
                                                    <div class="progress-bar bg-<?php 
                                                        echo $prediction['predicted_performance'] >= 70 ? 'success' : 
                                                             ($prediction['predicted_performance'] >= 50 ? 'warning' : 'danger'); 
                                                    ?>" style="width: <?php echo $prediction['predicted_performance']; ?>%">
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $prediction['confidence_level'] >= 80 ? 'success' : 
                                                     ($prediction['confidence_level'] >= 60 ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo $prediction['confidence_level']; ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary view-details" 
                                                    data-prediction-id="<?php echo $prediction['id']; ?>">
                                                <i class="bi bi-eye"></i> View
                                            </button>
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

    <!-- Prediction Details Modal -->
    <div class="modal fade" id="predictionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Prediction Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="predictionDetails">
                    <!-- Details will be loaded here via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // View Prediction Details
        document.querySelectorAll('.view-details').forEach(button => {
            button.addEventListener('click', function() {
                const predictionId = this.getAttribute('data-prediction-id');
                
                fetch('prediction_details.php?id=' + predictionId)
                    .then(response => response.text())
                    .then(html => {
                        document.getElementById('predictionDetails').innerHTML = html;
                        const modal = new bootstrap.Modal(document.getElementById('predictionModal'));
                        modal.show();
                    })
                    .catch(error => {
                        console.error('Error loading prediction details:', error);
                    });
            });
        });
    </script>
</body>
</html>