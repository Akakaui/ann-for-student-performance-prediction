<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';

session_start();
Auth::requireAuth();

// Only students can view their predictions
if ($_SESSION['role'] !== 'student') {
    $_SESSION['error'] = "Access denied. This page is for students only.";
    Utils::redirect('dashboard.php');
}

$db = Database::getInstance();

// Get all predictions for the current user
$predictions = $db->fetchAll(
    "SELECT * FROM predictions 
     WHERE user_id = ? 
     ORDER BY created_at DESC",
    [$_SESSION['user_id']]
);

// Calculate statistics
$total_predictions = count($predictions);
$average_performance = 0;
$exam_types = ['WAEC' => 0, 'NECO' => 0];
$performance_trend = [];
$monthly_averages = [];

if ($total_predictions > 0) {
    $total_performance = 0;
    $total_confidence = 0;
    
    // Group by month for trends
    foreach ($predictions as $prediction) {
        $total_performance += $prediction['predicted_performance'];
        $total_confidence += $prediction['confidence_level'];
        $exam_types[$prediction['exam_type']]++;
        
        $month = date('Y-m', strtotime($prediction['created_at']));
        if (!isset($monthly_averages[$month])) {
            $monthly_averages[$month] = [
                'performance' => 0,
                'confidence' => 0,
                'count' => 0
            ];
        }
        $monthly_averages[$month]['performance'] += $prediction['predicted_performance'];
        $monthly_averages[$month]['confidence'] += $prediction['confidence_level'];
        $monthly_averages[$month]['count']++;
    }
    
    $average_performance = round($total_performance / $total_predictions, 2);
    $average_confidence = round($total_confidence / $total_predictions, 2);
    
    // Calculate monthly averages
    foreach ($monthly_averages as $month => $data) {
        $performance_trend[] = [
            'month' => $month,
            'performance' => round($data['performance'] / $data['count'], 2),
            'confidence' => round($data['confidence'] / $data['count'], 2)
        ];
    }
    
    // Sort by month
    usort($performance_trend, function($a, $b) {
        return strcmp($a['month'], $b['month']);
    });
}

// Get best and worst performances
$best_performance = 0;
$worst_performance = 100;
if ($total_predictions > 0) {
    $performances = array_column($predictions, 'predicted_performance');
    $best_performance = max($performances);
    $worst_performance = min($performances);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Predictions - Student Performance Predictor</title>
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
                        <a class="nav-link" href="prediction_form.php">New Prediction</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="my_predictions.php">My Predictions</a>
                    </li>
                    <?php if ($total_predictions > 0): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="performance_trends.php">
                            <i class="bi bi-graph-up-arrow"></i> Performance Trends
                        </a>
                    </li>
                    <?php endif; ?>
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
                    <h2 class="fw-bold">My Predictions</h2>
                    <div class="btn-group">
                        <?php if (!empty($predictions)): ?>
                            <a href="export_predictions.php" class="btn btn-success">
                                <i class="bi bi-download"></i> Export CSV
                            </a>
                            <a href="performance_trends.php" class="btn btn-info">
                                <i class="bi bi-graph-up-arrow"></i> View Trends
                            </a>
                        <?php endif; ?>
                        <a href="prediction_form.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> New Prediction
                        </a>
                    </div>
                </div>
                <p class="text-muted">View your performance prediction history and track your progress.</p>
            </div>
        </div>

        <!-- Enhanced Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
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
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $average_performance; ?>%</h4>
                                <p class="card-text">Average Performance</p>
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
                                <h4 class="card-title"><?php echo $best_performance; ?>%</h4>
                                <p class="card-text">Best Performance</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-trophy display-6"></i>
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

        <!-- Quick Performance Overview -->
        <?php if (!empty($predictions) && count($performance_trend) > 1): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Performance Trend Overview</h5>
                <a href="performance_trends.php" class="btn btn-sm btn-outline-primary">View Detailed Analysis</a>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <canvas id="trendChart" height="120"></canvas>
                    </div>
                    <div class="col-md-4">
                        <div class="trend-stats">
                            <h6>Progress Summary</h6>
                            <?php
                            $first_perf = $performance_trend[0]['performance'];
                            $latest_perf = end($performance_trend)['performance'];
                            $progress = $latest_perf - $first_perf;
                            $progress_class = $progress >= 0 ? 'text-success' : 'text-danger';
                            $progress_icon = $progress >= 0 ? 'bi-arrow-up' : 'bi-arrow-down';
                            ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Starting Performance:</span>
                                <strong><?php echo $first_perf; ?>%</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Current Performance:</span>
                                <strong><?php echo $latest_perf; ?>%</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Overall Progress:</span>
                                <strong class="<?php echo $progress_class; ?>">
                                    <i class="bi <?php echo $progress_icon; ?>"></i>
                                    <?php echo abs($progress); ?>%
                                </strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Time Period:</span>
                                <strong><?php echo count($performance_trend); ?> months</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Predictions Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Prediction History</h5>
            </div>
            <div class="card-body">
                <?php if (empty($predictions)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-graph-up display-1 text-muted"></i>
                        <h4 class="text-muted mt-3">No Predictions Yet</h4>
                        <p class="text-muted">Make your first prediction to see your performance analysis here.</p>
                        <a href="prediction_form.php" class="btn btn-primary mt-2">
                            <i class="bi bi-calculator"></i> Make Your First Prediction
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Exam Type</th>
                                    <th>Predicted Performance</th>
                                    <th>Confidence</th>
                                    <th>Subjects</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($predictions as $prediction): ?>
                                    <tr>
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
                                            <?php
                                            $subject_count = 0;
                                            for ($i = 1; $i <= 9; $i++) {
                                                if (!empty($prediction["subject{$i}_name"])) {
                                                    $subject_count++;
                                                }
                                            }
                                            echo $subject_count . ' subjects';
                                            ?>
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
        // Performance Trend Chart
        <?php if (!empty($performance_trend) && count($performance_trend) > 1): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const trendData = <?php echo json_encode($performance_trend); ?>;
            const labels = trendData.map(t => {
                const date = new Date(t.month + '-01');
                return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
            });
            
            const performanceData = trendData.map(t => t.performance);
            const confidenceData = trendData.map(t => t.confidence);
            
            const ctx = document.getElementById('trendChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Performance Trend',
                            data: performanceData,
                            borderColor: '#3498db',
                            backgroundColor: 'rgba(52, 152, 219, 0.1)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 3
                        },
                        {
                            label: 'Confidence Trend',
                            data: confidenceData,
                            borderColor: '#27ae60',
                            backgroundColor: 'rgba(39, 174, 96, 0.1)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: false,
                            min: 0,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Percentage'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Timeline'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    }
                }
            });
        });
        <?php endif; ?>

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