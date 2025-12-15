<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';

session_start();
Auth::requireAuth();

// Only admins can access analytics
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access denied. This page is for administrators only.";
    Utils::redirect('dashboard.php');
}

$db = Database::getInstance();

// Get date range from request or default to last 30 days
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : '30days';
$start_date = '';
$end_date = date('Y-m-d');

switch ($date_range) {
    case '7days':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        break;
    case '30days':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        break;
    case '90days':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        break;
    case '1year':
        $start_date = date('Y-m-d', strtotime('-1 year'));
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-30 days'));
}

// Platform Overview Metrics
$total_users = $db->fetchOne("SELECT COUNT(*) as count FROM users")['count'];
$total_predictions = $db->fetchOne("SELECT COUNT(*) as count FROM predictions")['count'];
$total_groups = $db->fetchOne("SELECT COUNT(*) as count FROM lecturer_groups")['count'];
$active_lecturers = $db->fetchOne("SELECT COUNT(DISTINCT lecturer_id) as count FROM lecturer_groups")['count'];

// User Registration Trends
$registration_trends = $db->fetchAll("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as registrations,
        SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students,
        SUM(CASE WHEN role = 'lecturer' THEN 1 ELSE 0 END) as lecturers
    FROM users 
    WHERE created_at >= ?
    GROUP BY DATE(created_at)
    ORDER BY date DESC
    LIMIT 30
", [$start_date]);

// User Role Distribution
$role_distribution = $db->fetchAll("
    SELECT 
        role,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM users), 2) as percentage
    FROM users 
    GROUP BY role
    ORDER BY count DESC
");

// Prediction Activity
$prediction_activity = $db->fetchAll("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as predictions,
        AVG(predicted_performance) as avg_performance,
        AVG(confidence_level) as avg_confidence
    FROM predictions 
    WHERE created_at >= ?
    GROUP BY DATE(created_at)
    ORDER BY date DESC
    LIMIT 30
", [$start_date]);

// Exam Type Distribution
$exam_type_distribution = $db->fetchAll("
    SELECT 
        exam_type,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM predictions), 2) as percentage,
        AVG(predicted_performance) as avg_performance
    FROM predictions 
    GROUP BY exam_type
    ORDER BY count DESC
");

// Active Users (users with predictions in last 30 days)
$active_users = $db->fetchOne("
    SELECT COUNT(DISTINCT user_id) as count 
    FROM predictions 
    WHERE created_at >= ?
", [date('Y-m-d', strtotime('-30 days'))])['count'];

// Prediction Accuracy Sample (you would replace this with actual accuracy data)
$accuracy_metrics = [
    'total_predictions' => $total_predictions,
    'high_confidence_predictions' => $db->fetchOne("
        SELECT COUNT(*) as count FROM predictions WHERE confidence_level >= 80
    ")['count'],
    'avg_confidence' => $db->fetchOne("SELECT AVG(confidence_level) as avg FROM predictions")['avg'] ?? 0,
    'performance_distribution' => [
        'excellent' => $db->fetchOne("SELECT COUNT(*) as count FROM predictions WHERE predicted_performance >= 80")['count'],
        'good' => $db->fetchOne("SELECT COUNT(*) as count FROM predictions WHERE predicted_performance >= 70 AND predicted_performance < 80")['count'],
        'average' => $db->fetchOne("SELECT COUNT(*) as count FROM predictions WHERE predicted_performance >= 60 AND predicted_performance < 70")['count'],
        'needs_improvement' => $db->fetchOne("SELECT COUNT(*) as count FROM predictions WHERE predicted_performance >= 50 AND predicted_performance < 60")['count'],
        'poor' => $db->fetchOne("SELECT COUNT(*) as count FROM predictions WHERE predicted_performance < 50")['count']
    ]
];

// System Performance Metrics (simplified - in production you'd use monitoring tools)
$system_metrics = [
    'uptime' => '99.9%', // This would come from your monitoring system
    'avg_response_time' => '245ms',
    'error_rate' => '0.2%',
    'peak_usage_hours' => '10:00-14:00',
    'database_size' => round($db->fetchOne("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size FROM information_schema.TABLES WHERE table_schema = DATABASE()")['size'] ?? 0, 2) . ' MB'
];

// Feature Usage (simplified tracking)
$feature_usage = [
    'predictions_made' => $total_predictions,
    'groups_created' => $total_groups,
    'exports_generated' => $db->fetchOne("SELECT COUNT(*) as count FROM audit_logs WHERE action_type = 'export'")['count'] ?? 0,
    'recommendations_viewed' => $db->fetchOne("SELECT COUNT(*) as count FROM predictions WHERE recommendations_viewed = TRUE")['count']
];

// Prepare data for charts
$registration_dates = array_reverse(array_column($registration_trends, 'date'));
$registration_counts = array_reverse(array_column($registration_trends, 'registrations'));
$student_registrations = array_reverse(array_column($registration_trends, 'students'));
$lecturer_registrations = array_reverse(array_column($registration_trends, 'lecturers'));

$prediction_dates = array_reverse(array_column($prediction_activity, 'date'));
$prediction_counts = array_reverse(array_column($prediction_activity, 'predictions'));
$performance_trends = array_reverse(array_column($prediction_activity, 'avg_performance'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Analytics - Student Performance Predictor</title>
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
                        <a class="nav-link" href="admin_dashboard.php">Admin Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_users.php">User Management</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_lecturers.php">Lecturer Verification</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_analytics.php">System Analytics</a>
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
                        <h1 class="fw-bold">System Analytics Dashboard</h1>
                        <p class="text-muted mb-0">Comprehensive insights into platform performance and user engagement</p>
                    </div>
                    <div class="btn-group">
                        <a href="export_analytics.php" class="btn btn-success">
                            <i class="bi bi-download"></i> Export Full Report
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-center">
                            <div class="col-md-3">
                                <label for="date_range" class="form-label">Date Range:</label>
                                <select name="date_range" id="date_range" class="form-select" onchange="this.form.submit()">
                                    <option value="7days" <?php echo $date_range === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                                    <option value="30days" <?php echo $date_range === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                                    <option value="90days" <?php echo $date_range === '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
                                    <option value="1year" <?php echo $date_range === '1year' ? 'selected' : ''; ?>>Last Year</option>
                                </select>
                            </div>
                            <div class="col-md-9">
                                <div class="d-flex gap-2 flex-wrap">
                                    <span class="badge bg-primary">
                                        <i class="bi bi-calendar"></i> 
                                        <?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>
                                    </span>
                                    <span class="badge bg-info">Data updates in real-time</span>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Platform Overview -->
        <div class="row mb-4">
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="card-title"><?php echo $total_users; ?></h3>
                                <p class="card-text">Total Users</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-people display-6"></i>
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
                                <h3 class="card-title"><?php echo $total_predictions; ?></h3>
                                <p class="card-text">Predictions Made</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-graph-up display-6"></i>
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
                                <h3 class="card-title"><?php echo $active_users; ?></h3>
                                <p class="card-text">Active Users</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-person-check display-6"></i>
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
                                <h3 class="card-title"><?php echo $total_groups; ?></h3>
                                <p class="card-text">Student Groups</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-collection display-6"></i>
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
                                <h3 class="card-title"><?php echo $active_lecturers; ?></h3>
                                <p class="card-text">Active Lecturers</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-person-badge display-6"></i>
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
                                <h3 class="card-title"><?php echo round($accuracy_metrics['avg_confidence'], 1); ?>%</h3>
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

        <!-- Charts Row 1 -->
        <div class="row mb-4">
            <!-- User Registration Trends -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">User Registration Trends</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="registrationChart" height="250"></canvas>
                    </div>
                </div>
            </div>

            <!-- User Role Distribution -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">User Role Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="roleDistributionChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="row mb-4">
            <!-- Prediction Activity -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Prediction Activity & Performance Trends</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="predictionChart" height="250"></canvas>
                    </div>
                </div>
            </div>

            <!-- Exam Type Distribution -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Exam Type Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="examTypeChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance & System Metrics -->
        <div class="row mb-4">
            <!-- Performance Distribution -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Prediction Performance Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4 mb-3">
                                <div class="border-end">
                                    <h4 class="text-success"><?php echo $accuracy_metrics['performance_distribution']['excellent']; ?></h4>
                                    <small class="text-muted">Excellent (80-100%)</small>
                                </div>
                            </div>
                            <div class="col-4 mb-3">
                                <div class="border-end">
                                    <h4 class="text-primary"><?php echo $accuracy_metrics['performance_distribution']['good']; ?></h4>
                                    <small class="text-muted">Good (70-79%)</small>
                                </div>
                            </div>
                            <div class="col-4 mb-3">
                                <div>
                                    <h4 class="text-warning"><?php echo $accuracy_metrics['performance_distribution']['average']; ?></h4>
                                    <small class="text-muted">Average (60-69%)</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border-end">
                                    <h4 class="text-orange"><?php echo $accuracy_metrics['performance_distribution']['needs_improvement']; ?></h4>
                                    <small class="text-muted">Needs Improvement (50-59%)</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div>
                                    <h4 class="text-danger"><?php echo $accuracy_metrics['performance_distribution']['poor']; ?></h4>
                                    <small class="text-muted">Poor (<50%)</small>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-success" style="width: <?php echo ($accuracy_metrics['performance_distribution']['excellent'] / $total_predictions) * 100; ?>%"></div>
                                <div class="progress-bar bg-primary" style="width: <?php echo ($accuracy_metrics['performance_distribution']['good'] / $total_predictions) * 100; ?>%"></div>
                                <div class="progress-bar bg-warning" style="width: <?php echo ($accuracy_metrics['performance_distribution']['average'] / $total_predictions) * 100; ?>%"></div>
                                <div class="progress-bar bg-orange" style="width: <?php echo ($accuracy_metrics['performance_distribution']['needs_improvement'] / $total_predictions) * 100; ?>%"></div>
                                <div class="progress-bar bg-danger" style="width: <?php echo ($accuracy_metrics['performance_distribution']['poor'] / $total_predictions) * 100; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Performance -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">System Performance Metrics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-check-circle text-success fs-2 me-3"></i>
                                    <div>
                                        <h5 class="mb-0"><?php echo $system_metrics['uptime']; ?></h5>
                                        <small class="text-muted">System Uptime</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-speedometer2 text-info fs-2 me-3"></i>
                                    <div>
                                        <h5 class="mb-0"><?php echo $system_metrics['avg_response_time']; ?></h5>
                                        <small class="text-muted">Avg Response Time</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-exclamation-triangle text-warning fs-2 me-3"></i>
                                    <div>
                                        <h5 class="mb-0"><?php echo $system_metrics['error_rate']; ?></h5>
                                        <small class="text-muted">Error Rate</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-clock text-primary fs-2 me-3"></i>
                                    <div>
                                        <h5 class="mb-0"><?php echo $system_metrics['peak_usage_hours']; ?></h5>
                                        <small class="text-muted">Peak Usage</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-database text-secondary fs-2 me-3"></i>
                                    <div>
                                        <h5 class="mb-0"><?php echo $system_metrics['database_size']; ?></h5>
                                        <small class="text-muted">Database Size</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feature Usage & Quick Stats -->
        <div class="row">
            <!-- Feature Usage -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Feature Usage Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-3">
                                <div class="border-end">
                                    <h3 class="text-primary"><?php echo $feature_usage['predictions_made']; ?></h3>
                                    <small class="text-muted">Predictions Made</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="border-end">
                                    <h3 class="text-success"><?php echo $feature_usage['groups_created']; ?></h3>
                                    <small class="text-muted">Groups Created</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="border-end">
                                    <h3 class="text-info"><?php echo $feature_usage['exports_generated']; ?></h3>
                                    <small class="text-muted">Exports Generated</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div>
                                    <h3 class="text-warning"><?php echo $feature_usage['recommendations_viewed']; ?></h3>
                                    <small class="text-muted">Recommendations Viewed</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="export_analytics.php" class="btn btn-success">
                                <i class="bi bi-download"></i> Export Full Report
                            </a>
                            <a href="admin_users.php" class="btn btn-outline-primary">
                                <i class="bi bi-people"></i> Manage Users
                            </a>
                            <a href="system_settings.php" class="btn btn-outline-secondary">
                                <i class="bi bi-gear"></i> System Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // User Registration Trends Chart
        const regCtx = document.getElementById('registrationChart').getContext('2d');
        new Chart(regCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($registration_dates); ?>,
                datasets: [
                    {
                        label: 'Total Registrations',
                        data: <?php echo json_encode($registration_counts); ?>,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Students',
                        data: <?php echo json_encode($student_registrations); ?>,
                        borderColor: '#27ae60',
                        backgroundColor: 'rgba(39, 174, 96, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Lecturers',
                        data: <?php echo json_encode($lecturer_registrations); ?>,
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Registrations'
                        }
                    }
                }
            }
        });

        // User Role Distribution Chart
        const roleCtx = document.getElementById('roleDistributionChart').getContext('2d');
        new Chart(roleCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($role_distribution, 'role')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($role_distribution, 'count')); ?>,
                    backgroundColor: ['#3498db', '#27ae60', '#e74c3c']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const percentage = <?php echo json_encode(array_column($role_distribution, 'percentage')); ?>[context.dataIndex];
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Prediction Activity Chart
        const predCtx = document.getElementById('predictionChart').getContext('2d');
        new Chart(predCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($prediction_dates); ?>,
                datasets: [
                    {
                        label: 'Predictions Made',
                        data: <?php echo json_encode($prediction_counts); ?>,
                        backgroundColor: 'rgba(52, 152, 219, 0.8)',
                        yAxisID: 'y'
                    },
                    {
                        label: 'Avg Performance',
                        data: <?php echo json_encode($performance_trends); ?>,
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        type: 'line',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y1'
                    }
                ]
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
                        title: {
                            display: true,
                            text: 'Predictions Count'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        min: 0,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Performance (%)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // Exam Type Distribution Chart
        const examCtx = document.getElementById('examTypeChart').getContext('2d');
        new Chart(examCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($exam_type_distribution, 'exam_type')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($exam_type_distribution, 'count')); ?>,
                    backgroundColor: ['#3498db', '#9b59b6']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const percentage = <?php echo json_encode(array_column($exam_type_distribution, 'percentage')); ?>[context.dataIndex];
                                const avgPerformance = <?php echo json_encode(array_column($exam_type_distribution, 'avg_performance')); ?>[context.dataIndex];
                                return `${label}: ${value} (${percentage}%) - Avg: ${avgPerformance}%`;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>