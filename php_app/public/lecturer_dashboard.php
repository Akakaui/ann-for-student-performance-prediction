<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';

session_start();
Auth::requireAuth();

// Only lecturers can access this dashboard
if ($_SESSION['role'] !== 'lecturer') {
    $_SESSION['error'] = "Access denied. This page is for lecturers only.";
    Utils::redirect('dashboard.php');
}

$db = Database::getInstance();
$lecturer_id = $_SESSION['user_id'];

// Get lecturer's groups
$groups = $db->fetchAll(
    "SELECT * FROM lecturer_groups WHERE lecturer_id = ? ORDER BY created_at DESC",
    [$lecturer_id]
);

// Get comprehensive statistics
$total_students = $db->fetchOne(
    "SELECT COUNT(DISTINCT gs.student_id) as total 
     FROM group_students gs 
     JOIN lecturer_groups lg ON gs.group_id = lg.id 
     WHERE lg.lecturer_id = ?",
    [$lecturer_id]
)['total'] ?? 0;

$total_predictions = $db->fetchOne(
    "SELECT COUNT(p.id) as total 
     FROM predictions p
     JOIN group_students gs ON p.user_id = gs.student_id
     JOIN lecturer_groups lg ON gs.group_id = lg.id
     WHERE lg.lecturer_id = ?",
    [$lecturer_id]
)['total'] ?? 0;

// Get performance statistics
$performance_stats = $db->fetchOne(
    "SELECT 
        AVG(p.predicted_performance) as avg_performance,
        AVG(p.confidence_level) as avg_confidence,
        MAX(p.predicted_performance) as best_performance,
        MIN(p.predicted_performance) as worst_performance,
        COUNT(DISTINCT p.user_id) as active_students
     FROM predictions p
     JOIN group_students gs ON p.user_id = gs.student_id
     JOIN lecturer_groups lg ON gs.group_id = lg.id
     WHERE lg.lecturer_id = ?",
    [$lecturer_id]
);

// Get recent predictions
$recent_predictions = $db->fetchAll(
    "SELECT p.*, u.username, u.email, lg.group_name
     FROM predictions p
     JOIN users u ON p.user_id = u.id
     JOIN group_students gs ON u.id = gs.student_id
     JOIN lecturer_groups lg ON gs.group_id = lg.id
     WHERE lg.lecturer_id = ?
     ORDER BY p.created_at DESC
     LIMIT 10",
    [$lecturer_id]
);

// Get performance trends by month
$monthly_trends = $db->fetchAll(
    "SELECT 
        DATE_FORMAT(p.created_at, '%Y-%m') as month,
        AVG(p.predicted_performance) as avg_performance,
        COUNT(p.id) as prediction_count
     FROM predictions p
     JOIN group_students gs ON p.user_id = gs.student_id
     JOIN lecturer_groups lg ON gs.group_id = lg.id
     WHERE lg.lecturer_id = ?
     GROUP BY DATE_FORMAT(p.created_at, '%Y-%m')
     ORDER BY month DESC
     LIMIT 6",
    [$lecturer_id]
);

// Get subject performance analysis
$subject_performance = $db->fetchAll(
    "SELECT 
        subject_name,
        AVG(grade_score) as avg_score,
        COUNT(*) as count
     FROM (
         SELECT subject1_name as subject_name, subject1_grade as grade_score FROM predictions p
         JOIN group_students gs ON p.user_id = gs.student_id
         JOIN lecturer_groups lg ON gs.group_id = lg.id
         WHERE lg.lecturer_id = ? AND subject1_name IS NOT NULL
         UNION ALL
         SELECT subject2_name, subject2_grade FROM predictions p
         JOIN group_students gs ON p.user_id = gs.student_id
         JOIN lecturer_groups lg ON gs.group_id = lg.id
         WHERE lg.lecturer_id = ? AND subject2_name IS NOT NULL
         UNION ALL
         SELECT subject3_name, subject3_grade FROM predictions p
         JOIN group_students gs ON p.user_id = gs.student_id
         JOIN lecturer_groups lg ON gs.group_id = lg.id
         WHERE lg.lecturer_id = ? AND subject3_name IS NOT NULL
         UNION ALL
         SELECT subject4_name, subject4_grade FROM predictions p
         JOIN group_students gs ON p.user_id = gs.student_id
         JOIN lecturer_groups lg ON gs.group_id = lg.id
         WHERE lg.lecturer_id = ? AND subject4_name IS NOT NULL
         UNION ALL
         SELECT subject5_name, subject5_grade FROM predictions p
         JOIN group_students gs ON p.user_id = gs.student_id
         JOIN lecturer_groups lg ON gs.group_id = lg.id
         WHERE lg.lecturer_id = ? AND subject5_name IS NOT NULL
         UNION ALL
         SELECT subject6_name, subject6_grade FROM predictions p
         JOIN group_students gs ON p.user_id = gs.student_id
         JOIN lecturer_groups lg ON gs.group_id = lg.id
         WHERE lg.lecturer_id = ? AND subject6_name IS NOT NULL
         UNION ALL
         SELECT subject7_name, subject7_grade FROM predictions p
         JOIN group_students gs ON p.user_id = gs.student_id
         JOIN lecturer_groups lg ON gs.group_id = lg.id
         WHERE lg.lecturer_id = ? AND subject7_name IS NOT NULL
         UNION ALL
         SELECT subject8_name, subject8_grade FROM predictions p
         JOIN group_students gs ON p.user_id = gs.student_id
         JOIN lecturer_groups lg ON gs.group_id = lg.id
         WHERE lg.lecturer_id = ? AND subject8_name IS NOT NULL
         UNION ALL
         SELECT subject9_name, subject9_grade FROM predictions p
         JOIN group_students gs ON p.user_id = gs.student_id
         JOIN lecturer_groups lg ON gs.group_id = lg.id
         WHERE lg.lecturer_id = ? AND subject9_name IS NOT NULL
     ) as subjects
     GROUP BY subject_name
     HAVING count >= 3
     ORDER BY avg_score DESC
     LIMIT 10",
    array_fill(0, 9, $lecturer_id)
);

// Convert grade to percentage for display (assuming 1=A1=100%, 9=F9=0%)
function gradeToPercentage($grade) {
    $grade_map = [
        1 => 100, // A1
        2 => 87,  // B2
        3 => 75,  // B3
        4 => 70,  // C4
        5 => 65,  // C5
        6 => 60,  // C6
        7 => 55,  // D7
        8 => 50,  // E8
        9 => 0    // F9
    ];
    return $grade_map[$grade] ?? 0;
}

// Prepare subject data for chart
$subject_chart_data = [];
foreach ($subject_performance as $subject) {
    $subject_chart_data[] = [
        'subject' => $subject['subject_name'],
        'score' => gradeToPercentage($subject['avg_score']),
        'count' => $subject['count']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard - Student Performance Predictor</title>
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
                        <a class="nav-link active" href="lecturer_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="lecturer_groups.php">My Groups</a>
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
                        <h1 class="fw-bold">Lecturer Dashboard</h1>
                        <p class="text-muted mb-0">Comprehensive overview of your students' performance</p>
                    </div>
                    <div class="btn-group">
                        <a href="export_predictions.php" class="btn btn-success">
                            <i class="bi bi-download"></i> Export All Data
                        </a>
                        <a href="lecturer_groups.php" class="btn btn-primary">
                            <i class="bi bi-people"></i> Manage Groups
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="card-title"><?php echo count($groups); ?></h3>
                                <p class="card-text">Groups</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-collection display-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="card-title"><?php echo $total_students; ?></h3>
                                <p class="card-text">Students</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-people display-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="card-title"><?php echo $total_predictions; ?></h3>
                                <p class="card-text">Predictions</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-graph-up display-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="card-title"><?php echo round($performance_stats['avg_performance'] ?? 0, 1); ?>%</h3>
                                <p class="card-text">Avg Performance</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-speedometer2 display-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="card-title"><?php echo $performance_stats['active_students'] ?? 0; ?></h3>
                                <p class="card-text">Active Students</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-person-check display-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card bg-secondary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="card-title"><?php echo round($performance_stats['avg_confidence'] ?? 0, 1); ?>%</h3>
                                <p class="card-text">Avg Confidence</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-shield-check display-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <!-- Performance Trend -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Performance Trends</h5>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-secondary active" data-chart="performance">Performance</button>
                            <button class="btn btn-sm btn-outline-secondary" data-chart="volume">Volume</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="trendChart" height="250"></canvas>
                    </div>
                </div>
            </div>

            <!-- Subject Performance -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Top Performing Subjects</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="subjectChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Groups and Recent Activity -->
        <div class="row">
            <!-- Groups Overview -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Your Groups</h5>
                        <a href="lecturer_groups.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($groups)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-people display-1 text-muted"></i>
                                <h5 class="text-muted mt-3">No Groups Yet</h5>
                                <p class="text-muted">Create your first group to start monitoring students.</p>
                                <a href="lecturer_groups.php" class="btn btn-primary">Create Group</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Group Name</th>
                                            <th>Students</th>
                                            <th>Predictions</th>
                                            <th>Avg Performance</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($groups as $group): ?>
                                            <?php
                                            $group_stats = $db->fetchOne(
                                                "SELECT 
                                                    COUNT(DISTINCT gs.student_id) as student_count,
                                                    COUNT(p.id) as prediction_count,
                                                    AVG(p.predicted_performance) as avg_performance
                                                 FROM lecturer_groups lg
                                                 LEFT JOIN group_students gs ON lg.id = gs.group_id
                                                 LEFT JOIN predictions p ON gs.student_id = p.user_id
                                                 WHERE lg.id = ?
                                                 GROUP BY lg.id",
                                                [$group['id']]
                                            );
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($group['group_name']); ?></strong>
                                                    <?php if ($group['description']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($group['description']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $group_stats['student_count'] ?? 0; ?></td>
                                                <td><?php echo $group_stats['prediction_count'] ?? 0; ?></td>
                                                <td>
                                                    <?php if ($group_stats['avg_performance']): ?>
                                                        <span class="badge bg-<?php echo $group_stats['avg_performance'] >= 70 ? 'success' : ($group_stats['avg_performance'] >= 50 ? 'warning' : 'danger'); ?>">
                                                            <?php echo round($group_stats['avg_performance'], 1); ?>%
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">No data</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="lecturer_group_details.php?id=<?php echo $group['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i> View
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
            </div>

            <!-- Recent Predictions -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Recent Predictions</h5>
                        <a href="export_predictions.php" class="btn btn-sm btn-success">
                            <i class="bi bi-download"></i> Export
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_predictions)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-graph-up display-1 text-muted"></i>
                                <h5 class="text-muted mt-3">No Predictions Yet</h5>
                                <p class="text-muted">Student predictions will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Group</th>
                                            <th>Performance</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_predictions as $prediction): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($prediction['username']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($prediction['email']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($prediction['group_name']); ?></span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="badge bg-<?php 
                                                            echo $prediction['predicted_performance'] >= 70 ? 'success' : 
                                                                 ($prediction['predicted_performance'] >= 50 ? 'warning' : 'danger'); 
                                                        ?> me-2">
                                                            <?php echo $prediction['predicted_performance']; ?>%
                                                        </span>
                                                        <div class="progress flex-grow-1" style="height: 6px; width: 60px;">
                                                            <div class="progress-bar bg-<?php 
                                                                echo $prediction['predicted_performance'] >= 70 ? 'success' : 
                                                                     ($prediction['predicted_performance'] >= 50 ? 'warning' : 'danger'); 
                                                            ?>" style="width: <?php echo $prediction['predicted_performance']; ?>%">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <small><?php echo Utils::formatDate($prediction['created_at'], 'M j, Y'); ?></small>
                                                    <br><small class="text-muted"><?php echo Utils::formatDate($prediction['created_at'], 'g:i A'); ?></small>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary view-prediction" 
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
        // Performance Trends Chart
        const monthlyTrends = <?php echo json_encode($monthly_trends); ?>;
        const trendLabels = monthlyTrends.map(t => {
            const date = new Date(t.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        }).reverse();
        
        const performanceData = monthlyTrends.map(t => t.avg_performance).reverse();
        const volumeData = monthlyTrends.map(t => t.prediction_count).reverse();
        
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Average Performance',
                    data: performanceData,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y'
                }]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        min: 0,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Performance (%)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    }
                }
            }
        });

        // Subject Performance Chart
        const subjectData = <?php echo json_encode($subject_chart_data); ?>;
        const subjectLabels = subjectData.map(s => s.subject);
        const subjectScores = subjectData.map(s => s.score);
        
        new Chart(document.getElementById('subjectChart'), {
            type: 'bar',
            data: {
                labels: subjectLabels,
                datasets: [{
                    label: 'Average Score (%)',
                    data: subjectScores,
                    backgroundColor: subjectScores.map(score => 
                        score >= 80 ? '#27ae60' :
                        score >= 70 ? '#2ecc71' :
                        score >= 60 ? '#f39c12' :
                        score >= 50 ? '#e74c3c' : '#c0392b'
                    )
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Score (%)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Chart toggle functionality
        document.querySelectorAll('[data-chart]').forEach(button => {
            button.addEventListener('click', function() {
                const chartType = this.getAttribute('data-chart');
                
                // Update button states
                document.querySelectorAll('[data-chart]').forEach(btn => {
                    btn.classList.remove('active');
                });
                this.classList.add('active');
                
                // Update chart data
                if (chartType === 'performance') {
                    trendChart.data.datasets = [{
                        label: 'Average Performance',
                        data: performanceData,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y'
                    }];
                    trendChart.options.scales.y.title.text = 'Performance (%)';
                } else {
                    trendChart.data.datasets = [{
                        label: 'Prediction Volume',
                        data: volumeData,
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y'
                    }];
                    trendChart.options.scales.y.title.text = 'Number of Predictions';
                }
                
                trendChart.update();
            });
        });

        // View Prediction Details
        document.querySelectorAll('.view-prediction').forEach(button => {
            button.addEventListener('click', function() {
                const predictionId = this.getAttribute('data-prediction-id');
                
                fetch('prediction_details.php?id=' + predictionId + '&lecturer_view=true')
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