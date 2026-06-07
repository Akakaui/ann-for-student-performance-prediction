<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';
require_once '../lib/recommendations.php';

session_start();
Auth::requireAuth();

// Only students can view prediction results
if ($_SESSION['role'] !== 'student') {
    $_SESSION['error'] = "Access denied. This page is for students only.";
    Utils::redirect('dashboard.php');
}

$db = Database::getInstance();
$recommendationsEngine = new Recommendations($db);

// Get prediction ID from URL
$prediction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verify the prediction belongs to the current user
$prediction = $db->fetchOne(
    "SELECT * FROM predictions WHERE id = ? AND user_id = ?",
    [$prediction_id, $_SESSION['user_id']]
);

if (!$prediction) {
    $_SESSION['error'] = "Prediction not found or access denied.";
    Utils::redirect('my_predictions.php');
}

// Mark recommendations as viewed
$db->query(
    "UPDATE predictions SET recommendations_viewed = TRUE WHERE id = ?",
    [$prediction_id]
);

// Get or generate recommendations
$saved_recommendations = $recommendationsEngine->getSavedRecommendations($prediction_id);
if (!$saved_recommendations) {
    $saved_recommendations = $recommendationsEngine->generateRecommendations($prediction_id, $_SESSION['user_id']);
}

// Calculate subject counts and averages
$subject_count = 0;
$total_score = 0;
$subjects = [];

for ($i = 1; $i <= 9; $i++) {
    if (!empty($prediction["subject{$i}_name"]) && !empty($prediction["subject{$i}_grade"])) {
        $subject_count++;
        $total_score += $prediction["subject{$i}_grade"];
        $subjects[] = [
            'name' => $prediction["subject{$i}_name"],
            'score' => $prediction["subject{$i}_grade"],
            'grade' => convertToGrade($prediction["subject{$i}_grade"])
        ];
    }
}

$average_score = $subject_count > 0 ? round($total_score / $subject_count, 2) : 0;

// Helper function to convert WAEC grade number to letter grade
function convertToGrade($grade) {
    if ($grade <= 1) return 'A1';
    if ($grade <= 2) return 'B2';
    if ($grade <= 3) return 'B3';
    if ($grade <= 4) return 'C4';
    if ($grade <= 5) return 'C5';
    if ($grade <= 6) return 'C6';
    if ($grade <= 7) return 'D7';
    if ($grade <= 8) return 'E8';
    return 'F9';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prediction Results - Student Performance Predictor</title>
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
                        <a class="nav-link" href="prediction_form.php">New Prediction</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_predictions.php">My Predictions</a>
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
                    <div>
                        <h1 class="fw-bold">Prediction Results</h1>
                        <p class="text-muted mb-0">
                            Analysis completed on <?php echo Utils::formatDate($prediction['created_at']); ?>
                        </p>
                    </div>
                    <div class="btn-group">
                        <a href="my_predictions.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Predictions
                        </a>
                        <a href="prediction_form.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> New Prediction
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Results Card -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="card-title mb-0">
                            <i class="bi bi-graph-up"></i> Performance Prediction
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="border-end">
                                    <h2 class="text-<?php echo $prediction['predicted_performance'] >= 70 ? 'success' : ($prediction['predicted_performance'] >= 50 ? 'warning' : 'danger'); ?>">
                                        <?php echo $prediction['predicted_performance']; ?>%
                                    </h2>
                                    <p class="text-muted mb-0">Predicted Performance</p>
                                    <small class="text-muted">Based on <?php echo $prediction['exam_type']; ?> scores</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border-end">
                                    <h2 class="text-<?php echo $prediction['confidence_level'] >= 80 ? 'success' : ($prediction['confidence_level'] >= 60 ? 'warning' : 'danger'); ?>">
                                        <?php echo $prediction['confidence_level']; ?>%
                                    </h2>
                                    <p class="text-muted mb-0">Confidence Level</p>
                                    <small class="text-muted">Prediction accuracy estimate</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div>
                                    <h2 class="text-info"><?php echo $subject_count; ?></h2>
                                    <p class="text-muted mb-0">Subjects Analyzed</p>
                                    <small class="text-muted">Average score: <?php echo $average_score; ?>%</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Performance Bar -->
                        <div class="mt-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Performance Level</span>
                                <span>
                                    <?php
                                    $performance = $prediction['predicted_performance'];
                                    if ($performance >= 80) {
                                        echo '<span class="badge bg-success">Excellent</span>';
                                    } elseif ($performance >= 70) {
                                        echo '<span class="badge bg-primary">Good</span>';
                                    } elseif ($performance >= 60) {
                                        echo '<span class="badge bg-warning">Average</span>';
                                    } elseif ($performance >= 50) {
                                        echo '<span class="badge bg-orange">Needs Improvement</span>';
                                    } else {
                                        echo '<span class="badge bg-danger">Poor</span>';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-<?php 
                                    echo $prediction['predicted_performance'] >= 70 ? 'success' : 
                                         ($prediction['predicted_performance'] >= 50 ? 'warning' : 'danger'); 
                                ?>" style="width: <?php echo $prediction['predicted_performance']; ?>%">
                                    <?php echo $prediction['predicted_performance']; ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subject Performance -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-journal-text"></i> Subject Performance Analysis
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Score</th>
                                        <th>Grade</th>
                                        <th>Performance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subjects as $subject): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subject['name']); ?></td>
                                        <td>
                                            <strong><?php echo $subject['score']; ?>%</strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $subject['score'] >= 80 ? 'success' : 
                                                     ($subject['score'] >= 70 ? 'primary' : 
                                                     ($subject['score'] >= 60 ? 'warning' : 
                                                     ($subject['score'] >= 50 ? 'orange' : 'danger'))); 
                                            ?>">
                                                <?php echo $subject['grade']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-<?php 
                                                    echo $subject['score'] >= 80 ? 'success' : 
                                                         ($subject['score'] >= 70 ? 'primary' : 
                                                         ($subject['score'] >= 60 ? 'warning' : 
                                                         ($subject['score'] >= 50 ? 'orange' : 'danger'))); 
                                                ?>" style="width: <?php echo $subject['score']; ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recommendations Sidebar -->
            <div class="col-lg-4">
                <div class="card sticky-top" style="top: 20px;">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-lightbulb"></i> Personalized Recommendations
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- General Recommendations -->
                        <h6>Key Recommendations:</h6>
                        <div class="recommendations-list">
                            <?php foreach ($saved_recommendations['general'] as $rec): ?>
                            <div class="recommendation-item mb-3 p-3 border rounded bg-light">
                                <div class="d-flex align-items-start">
                                    <i class="bi bi-<?php echo $rec['icon']; ?> text-<?php 
                                        echo $rec['priority'] === 'critical' ? 'danger' : 
                                             ($rec['priority'] === 'high' ? 'warning' : 
                                             ($rec['priority'] === 'medium' ? 'info' : 'success')); 
                                    ?> me-2 mt-1"></i>
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($rec['title']); ?></h6>
                                        <p class="mb-1 small"><?php echo htmlspecialchars($rec['message']); ?></p>
                                        <span class="badge bg-<?php 
                                            echo $rec['priority'] === 'critical' ? 'danger' : 
                                                 ($rec['priority'] === 'high' ? 'warning' : 
                                                 ($rec['priority'] === 'medium' ? 'info' : 'success')); 
                                        ?>"><?php echo ucfirst($rec['priority']); ?> priority</span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Weak Subjects -->
                        <?php if (!empty($saved_recommendations['subject_specific']['weak_subjects'])): ?>
                        <h6 class="mt-4">Focus Areas:</h6>
                        <?php foreach ($saved_recommendations['subject_specific']['weak_subjects'] as $subject): ?>
                        <div class="weak-subject mb-3 p-3 border rounded bg-warning bg-opacity-10">
                            <h6 class="text-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                <?php echo htmlspecialchars($subject['name']); ?> (<?php echo $subject['score']; ?>%)
                            </h6>
                            <ul class="small mb-0">
                                <?php foreach ($subject['recommendations'] as $tip): ?>
                                <li><?php echo htmlspecialchars($tip['tip']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Study Plan Preview -->
                        <h6 class="mt-4">Study Plan Preview:</h6>
                        <div class="study-plan">
                            <?php $first_week = $saved_recommendations['study_plan'][0] ?? []; ?>
                            <?php if (!empty($first_week)): ?>
                            <div class="p-3 border rounded">
                                <h6>Week <?php echo $first_week['week']; ?>: <?php echo $first_week['focus']; ?></h6>
                                <ul class="small mb-2">
                                    <?php foreach ($first_week['activities'] as $activity): ?>
                                    <li><?php echo htmlspecialchars($activity); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <small class="text-muted">
                                    <i class="bi bi-clock"></i> <?php echo $first_week['hours_weekly']; ?> hours/week
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Improvement Timeline -->
                        <?php if (!empty($saved_recommendations['timeline'])): ?>
                        <div class="timeline mt-3 p-3 border rounded bg-info bg-opacity-10">
                            <h6>Improvement Timeline:</h6>
                            <div class="small">
                                <strong>Current:</strong> <?php echo $saved_recommendations['timeline']['current_level']; ?><br>
                                <strong>Next Goal:</strong> <?php echo $saved_recommendations['timeline']['next_milestone']; ?><br>
                                <strong>Estimated Time:</strong> <?php echo $saved_recommendations['timeline']['estimated_time']; ?><br>
                                <strong>Target:</strong> <?php echo $saved_recommendations['timeline']['target_improvement']; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="d-flex justify-content-between">
                    <div>
                        <a href="my_predictions.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to All Predictions
                        </a>
                    </div>
                    <div class="btn-group">
                        <button onclick="window.print()" class="btn btn-outline-primary">
                            <i class="bi bi-printer"></i> Print Report
                        </button>
                        <a href="prediction_form.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> New Analysis
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>