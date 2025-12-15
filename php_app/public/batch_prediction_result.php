<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';

session_start();
Auth::requireAuth();

// Only lecturers can access batch prediction results
if ($_SESSION['role'] !== 'lecturer') {
    $_SESSION['error'] = "Access denied. This page is for lecturers only.";
    Utils::redirect('dashboard.php');
}

$db = Database::getInstance();
$lecturer_id = $_SESSION['user_id'];

// Get group ID from session (set during batch prediction)
$group_id = $_SESSION['batch_group_id'] ?? 0;

if (!$group_id) {
    $_SESSION['error'] = "No batch prediction data found. Please run a batch prediction first.";
    Utils::redirect('lecturer_groups.php');
}

// Verify the lecturer owns this group
$group = $db->fetchOne(
    "SELECT * FROM lecturer_groups WHERE id = ? AND lecturer_id = ?",
    [$group_id, $lecturer_id]
);

if (!$group) {
    $_SESSION['error'] = "Group not found or access denied.";
    Utils::redirect('lecturer_groups.php');
}

// Get students in this group
$students = $db->fetchAll("
    SELECT u.id, u.username, u.email 
    FROM users u 
    JOIN group_students gs ON u.id = gs.student_id 
    WHERE gs.group_id = ? 
    ORDER BY u.username
", [$group_id]);

// Get batch prediction results from session
$batch_results = $_SESSION['batch_results'] ?? [];
$prediction_parameters = $_SESSION['prediction_parameters'] ?? [];

if (empty($batch_results)) {
    $_SESSION['error'] = "Batch prediction results not found. Please run the analysis again.";
    Utils::redirect('lecturer_groups.php');
}

// Calculate batch statistics
$total_students = count($students);
$students_with_predictions = count($batch_results);
$average_performance = 0;
$average_confidence = 0;
$performance_distribution = [
    'excellent' => 0, // 80-100%
    'good' => 0,      // 70-79%
    'average' => 0,   // 60-69%
    'needs_improvement' => 0, // 50-59%
    'poor' => 0       // 0-49%
];

if (!empty($batch_results)) {
    $total_performance = 0;
    $total_confidence = 0;
    
    foreach ($batch_results as $result) {
        $performance = $result['predicted_performance'];
        $confidence = $result['confidence_level'];
        
        $total_performance += $performance;
        $total_confidence += $confidence;
        
        // Categorize performance
        if ($performance >= 80) {
            $performance_distribution['excellent']++;
        } elseif ($performance >= 70) {
            $performance_distribution['good']++;
        } elseif ($performance >= 60) {
            $performance_distribution['average']++;
        } elseif ($performance >= 50) {
            $performance_distribution['needs_improvement']++;
        } else {
            $performance_distribution['poor']++;
        }
    }
    
    $average_performance = round($total_performance / count($batch_results), 2);
    $average_confidence = round($total_confidence / count($batch_results), 2);
}

// Get students without predictions
$students_without_predictions = array_filter($students, function($student) use ($batch_results) {
    foreach ($batch_results as $result) {
        if ($result['student_id'] == $student['id']) {
            return false;
        }
    }
    return true;
});

// Prepare data for charts
$performance_data = array_column($batch_results, 'predicted_performance');
$confidence_data = array_column($batch_results, 'confidence_level');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Prediction Results - Student Performance Predictor</title>
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
                        <a class="nav-link" href="lecturer_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="lecturer_groups.php">My Groups</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="batch_prediction_result.php">Batch Analysis</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_progress.php">Student Progress</a>
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
                        <h1 class="fw-bold">Batch Prediction Results</h1>
                        <p class="text-muted mb-0">
                            Group: <strong><?php echo htmlspecialchars($group['group_name']); ?></strong> • 
                            Analysis completed on <?php echo date('F j, Y \a\t g:i A'); ?>
                        </p>
                    </div>
                    <div class="btn-group">
                        <a href="export_batch_results.php?group_id=<?php echo $group_id; ?>" class="btn btn-success">
                            <i class="bi bi-download"></i> Export Full Report
                        </a>
                        <a href="lecturer_groups.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Groups
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Batch Summary -->
        <div class="row mb-4">
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo $total_students; ?></h3>
                        <p class="card-text">Total Students</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo $students_with_predictions; ?></h3>
                        <p class="card-text">Analyzed</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo $average_performance; ?>%</h3>
                        <p class="card-text">Avg Performance</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo $average_confidence; ?>%</h3>
                        <p class="card-text">Avg Confidence</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo count($students_without_predictions); ?></h3>
                        <p class="card-text">No Data</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card bg-secondary text-white">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo $performance_distribution['excellent']; ?></h3>
                        <p class="card-text">Excellent</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <!-- Performance Distribution -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Performance Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="performanceChart" height="250"></canvas>
                    </div>
                </div>
            </div>

            <!-- Confidence vs Performance -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Performance vs Confidence</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="scatterChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Breakdown -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Performance Breakdown</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-2">
                                <div class="border-end">
                                    <h4 class="text-success"><?php echo $performance_distribution['excellent']; ?></h4>
                                    <small class="text-muted">Excellent (80-100%)</small>
                                    <div class="progress mt-2" style="height: 8px;">
                                        <div class="progress-bar bg-success" style="width: <?php echo ($performance_distribution['excellent'] / $students_with_predictions) * 100; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-2">
                                <div class="border-end">
                                    <h4 class="text-primary"><?php echo $performance_distribution['good']; ?></h4>
                                    <small class="text-muted">Good (70-79%)</small>
                                    <div class="progress mt-2" style="height: 8px;">
                                        <div class="progress-bar bg-primary" style="width: <?php echo ($performance_distribution['good'] / $students_with_predictions) * 100; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-2">
                                <div class="border-end">
                                    <h4 class="text-warning"><?php echo $performance_distribution['average']; ?></h4>
                                    <small class="text-muted">Average (60-69%)</small>
                                    <div class="progress mt-2" style="height: 8px;">
                                        <div class="progress-bar bg-warning" style="width: <?php echo ($performance_distribution['average'] / $students_with_predictions) * 100; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-2">
                                <div class="border-end">
                                    <h4 class="text-orange"><?php echo $performance_distribution['needs_improvement']; ?></h4>
                                    <small class="text-muted">Needs Improvement (50-59%)</small>
                                    <div class="progress mt-2" style="height: 8px;">
                                        <div class="progress-bar bg-orange" style="width: <?php echo ($performance_distribution['needs_improvement'] / $students_with_predictions) * 100; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-2">
                                <div class="border-end">
                                    <h4 class="text-danger"><?php echo $performance_distribution['poor']; ?></h4>
                                    <small class="text-muted">Poor (<50%)</small>
                                    <div class="progress mt-2" style="height: 8px;">
                                        <div class="progress-bar bg-danger" style="width: <?php echo ($performance_distribution['poor'] / $students_with_predictions) * 100; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-2">
                                <div>
                                    <h4 class="text-secondary"><?php echo count($students_without_predictions); ?></h4>
                                    <small class="text-muted">No Data</small>
                                    <div class="progress mt-2" style="height: 8px;">
                                        <div class="progress-bar bg-secondary" style="width: <?php echo (count($students_without_predictions) / $total_students) * 100; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Results -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Detailed Prediction Results</h5>
                        <div class="btn-group">
                            <a href="export_batch_results.php?group_id=<?php echo $group_id; ?>&type=detailed" class="btn btn-sm btn-success">
                                <i class="bi bi-download"></i> Export Detailed
                            </a>
                            <a href="export_batch_results.php?group_id=<?php echo $group_id; ?>&type=summary" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-download"></i> Export Summary
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Predicted Performance</th>
                                        <th>Confidence Level</th>
                                        <th>Performance Category</th>
                                        <th>Recommendation Priority</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($batch_results as $result): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($result['student_name']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($result['student_email']); ?></small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="badge bg-<?php 
                                                        echo $result['predicted_performance'] >= 70 ? 'success' : 
                                                             ($result['predicted_performance'] >= 50 ? 'warning' : 'danger'); 
                                                    ?> me-2">
                                                        <?php echo $result['predicted_performance']; ?>%
                                                    </span>
                                                    <div class="progress flex-grow-1" style="height: 6px; width: 80px;">
                                                        <div class="progress-bar bg-<?php 
                                                            echo $result['predicted_performance'] >= 70 ? 'success' : 
                                                                 ($result['predicted_performance'] >= 50 ? 'warning' : 'danger'); 
                                                        ?>" style="width: <?php echo $result['predicted_performance']; ?>%">
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $result['confidence_level'] >= 80 ? 'success' : 
                                                         ($result['confidence_level'] >= 60 ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo $result['confidence_level']; ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $performance = $result['predicted_performance'];
                                                if ($performance >= 80) {
                                                    $category = 'Excellent';
                                                    $badge_class = 'success';
                                                } elseif ($performance >= 70) {
                                                    $category = 'Good';
                                                    $badge_class = 'primary';
                                                } elseif ($performance >= 60) {
                                                    $category = 'Average';
                                                    $badge_class = 'warning';
                                                } elseif ($performance >= 50) {
                                                    $category = 'Needs Improvement';
                                                    $badge_class = 'orange';
                                                } else {
                                                    $category = 'Poor';
                                                    $badge_class = 'danger';
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $badge_class; ?>"><?php echo $category; ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                if ($performance >= 70 && $result['confidence_level'] >= 70) {
                                                    $priority = 'Low';
                                                    $priority_class = 'success';
                                                } elseif ($performance >= 60 || $result['confidence_level'] >= 60) {
                                                    $priority = 'Medium';
                                                    $priority_class = 'warning';
                                                } else {
                                                    $priority = 'High';
                                                    $priority_class = 'danger';
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $priority_class; ?>">
                                                    <i class="bi bi-<?php echo $priority === 'High' ? 'exclamation-triangle' : ($priority === 'Medium' ? 'info-circle' : 'check-circle'); ?>"></i>
                                                    <?php echo $priority; ?> Priority
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="individual_progress.php?student_id=<?php echo $result['student_id']; ?>" 
                                                       class="btn btn-outline-primary">
                                                        <i class="bi bi-graph-up"></i> Progress
                                                    </a>
                                                    <button class="btn btn-outline-info view-recommendations" 
                                                            data-student-id="<?php echo $result['student_id']; ?>"
                                                            data-student-name="<?php echo htmlspecialchars($result['student_name']); ?>">
                                                        <i class="bi bi-lightbulb"></i> Tips
                                                    </button>
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
        </div>

        <!-- Students Without Predictions -->
        <?php if (!empty($students_without_predictions)): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-exclamation-triangle"></i> Students Without Prediction Data
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">The following students don't have sufficient data for performance prediction:</p>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Email</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students_without_predictions as $student): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student['username']); ?></td>
                                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                                            <td>
                                                <a href="#" class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-envelope"></i> Request Data
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
    </div>

    <!-- Recommendations Modal -->
    <div class="modal fade" id="recommendationsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Study Recommendations</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="recommendationsContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Performance Distribution Chart
        const performanceDist = <?php echo json_encode($performance_distribution); ?>;
        new Chart(document.getElementById('performanceChart'), {
            type: 'doughnut',
            data: {
                labels: ['Excellent (80-100%)', 'Good (70-79%)', 'Average (60-69%)', 'Needs Improvement (50-59%)', 'Poor (<50%)'],
                datasets: [{
                    data: [
                        performanceDist.excellent,
                        performanceDist.good,
                        performanceDist.average,
                        performanceDist.needs_improvement,
                        performanceDist.poor
                    ],
                    backgroundColor: ['#27ae60', '#3498db', '#f39c12', '#e67e22', '#e74c3c']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // Scatter Chart - Performance vs Confidence
        const scatterData = <?php echo json_encode($batch_results); ?>;
        const scatterPoints = scatterData.map(result => ({
            x: result.predicted_performance,
            y: result.confidence_level
        }));
        
        new Chart(document.getElementById('scatterChart'), {
            type: 'scatter',
            data: {
                datasets: [{
                    label: 'Students',
                    data: scatterPoints,
                    backgroundColor: scatterData.map(result => 
                        result.predicted_performance >= 70 ? '#27ae60' :
                        result.predicted_performance >= 50 ? '#f39c12' : '#e74c3c'
                    ),
                    pointRadius: 8,
                    pointHoverRadius: 10
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Predicted Performance (%)'
                        },
                        min: 0,
                        max: 100
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Confidence Level (%)'
                        },
                        min: 0,
                        max: 100
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const result = scatterData[context.dataIndex];
                                return [
                                    `Student: ${result.student_name}`,
                                    `Performance: ${result.predicted_performance}%`,
                                    `Confidence: ${result.confidence_level}%`
                                ];
                            }
                        }
                    }
                }
            }
        });

        // View Recommendations
        document.querySelectorAll('.view-recommendations').forEach(button => {
            button.addEventListener('click', function() {
                const studentId = this.getAttribute('data-student-id');
                const studentName = this.getAttribute('data-student-name');
                
                document.getElementById('recommendationsContent').innerHTML = `
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading recommendations for ${studentName}...</p>
                    </div>
                `;
                
                const modal = new bootstrap.Modal(document.getElementById('recommendationsModal'));
                modal.show();
                
                fetch('batch_recommendations.php?student_id=' + studentId)
                    .then(response => response.text())
                    .then(html => {
                        document.getElementById('recommendationsContent').innerHTML = html;
                    })
                    .catch(error => {
                        document.getElementById('recommendationsContent').innerHTML = `
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i>
                                Error loading recommendations. Please try again.
                            </div>
                        `;
                    });
            });
        });
    </script>
</body>
</html>