<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';

session_start();
Auth::requireAuth();

// Only students can view their trends
if ($_SESSION['role'] !== 'student') {
    $_SESSION['error'] = "Access denied. This page is for students only.";
    Utils::redirect('dashboard.php');
}

$db = Database::getInstance();

// Get all predictions for the current user with detailed data
$predictions = $db->fetchAll(
    "SELECT * FROM predictions 
     WHERE user_id = ? 
     ORDER BY created_at ASC",
    [$_SESSION['user_id']]
);

$total_predictions = count($predictions);

if ($total_predictions === 0) {
    $_SESSION['error'] = "No predictions found. Make some predictions first to view trends.";
    Utils::redirect('my_predictions.php');
}

// Calculate comprehensive trend data
$monthly_data = [];
$weekly_data = [];
$exam_type_data = ['WAEC' => [], 'NECO' => []];
$subject_performance = [];
$performance_ranges = [
    'excellent' => ['min' => 80, 'max' => 100, 'count' => 0, 'color' => '#27ae60'],
    'good' => ['min' => 70, 'max' => 79, 'count' => 0, 'color' => '#2ecc71'],
    'average' => ['min' => 60, 'max' => 69, 'count' => 0, 'color' => '#f39c12'],
    'needs_improvement' => ['min' => 50, 'max' => 59, 'count' => 0, 'color' => '#e74c3c'],
    'poor' => ['min' => 0, 'max' => 49, 'count' => 0, 'color' => '#c0392b']
];

foreach ($predictions as $prediction) {
    // Monthly trends
    $month = date('Y-m', strtotime($prediction['created_at']));
    if (!isset($monthly_data[$month])) {
        $monthly_data[$month] = [
            'performance' => 0,
            'confidence' => 0,
            'count' => 0,
            'predictions' => []
        ];
    }
    $monthly_data[$month]['performance'] += $prediction['predicted_performance'];
    $monthly_data[$month]['confidence'] += $prediction['confidence_level'];
    $monthly_data[$month]['count']++;
    $monthly_data[$month]['predictions'][] = $prediction;
    
    // Weekly trends (last 12 weeks)
    $week = date('Y-W', strtotime($prediction['created_at']));
    if (!isset($weekly_data[$week])) {
        $weekly_data[$week] = [
            'performance' => 0,
            'confidence' => 0,
            'count' => 0
        ];
    }
    $weekly_data[$week]['performance'] += $prediction['predicted_performance'];
    $weekly_data[$week]['confidence'] += $prediction['confidence_level'];
    $weekly_data[$week]['count']++;
    
    // Exam type trends
    $exam_type_data[$prediction['exam_type']][] = $prediction['predicted_performance'];
    
    // Performance range counting
    foreach ($performance_ranges as $range => $criteria) {
        if ($prediction['predicted_performance'] >= $criteria['min'] && $prediction['predicted_performance'] <= $criteria['max']) {
            $performance_ranges[$range]['count']++;
        }
    }
    
    // Subject analysis (get best performing subjects)
    for ($i = 1; $i <= 9; $i++) {
        $subject_name = $prediction["subject{$i}_name"] ?? '';
        $subject_grade = $prediction["subject{$i}_grade"] ?? '';
        
        if (!empty($subject_name) && !empty($subject_grade)) {
            if (!isset($subject_performance[$subject_name])) {
                $subject_performance[$subject_name] = [
                    'total_score' => 0,
                    'count' => 0,
                    'grades' => []
                ];
            }
            $subject_performance[$subject_name]['total_score'] += $subject_grade;
            $subject_performance[$subject_name]['count']++;
            $subject_performance[$subject_name]['grades'][] = $subject_grade;
        }
    }
} // CLOSING BRACE FOR MAIN FOREACH LOOP

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

// Calculate averages
$monthly_trend = [];
foreach ($monthly_data as $month => $data) {
    $monthly_trend[] = [
        'month' => $month,
        'performance' => round($data['performance'] / $data['count'], 2),
        'confidence' => round($data['confidence'] / $data['count'], 2),
        'count' => $data['count']
    ];
}

$weekly_trend = [];
$week_keys = array_slice(array_keys($weekly_data), -12); // Last 12 weeks
foreach ($week_keys as $week) {
    $data = $weekly_data[$week];
    $weekly_trend[] = [
        'week' => $week,
        'performance' => round($data['performance'] / $data['count'], 2),
        'confidence' => round($data['confidence'] / $data['count'], 2)
    ];
}

// Calculate exam type averages
$exam_type_averages = [];
foreach ($exam_type_data as $type => $scores) {
    if (!empty($scores)) {
        $exam_type_averages[$type] = round(array_sum($scores) / count($scores), 2);
    }
}

// Calculate subject averages
$subject_averages = [];
foreach ($subject_performance as $subject => $data) {
    $subject_averages[$subject] = round($data['total_score'] / $data['count'], 2);
}

// Sort subjects by average score (best first)
arsort($subject_averages);

// Overall statistics
$all_performances = array_column($predictions, 'predicted_performance');
$all_confidence = array_column($predictions, 'confidence_level');
$overall_stats = [
    'avg_performance' => round(array_sum($all_performances) / count($all_performances), 2),
    'avg_confidence' => round(array_sum($all_confidence) / count($all_confidence), 2),
    'best_performance' => max($all_performances),
    'worst_performance' => min($all_performances),
    'performance_std_dev' => round(calculateStdDev($all_performances), 2),
    'total_months' => count($monthly_trend),
    'improvement' => count($monthly_trend) > 1 ? 
        round(end($monthly_trend)['performance'] - $monthly_trend[0]['performance'], 2) : 0
];

// Helper function for standard deviation
function calculateStdDev($array) {
    $n = count($array);
    if ($n <= 1) return 0;
    
    $mean = array_sum($array) / $n;
    $carry = 0.0;
    foreach ($array as $val) {
        $d = ((double) $val) - $mean;
        $carry += $d * $d;
    }
    return sqrt($carry / $n);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Trends - Student Performance Predictor</title>
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
                        <a class="nav-link" href="my_predictions.php">My Predictions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="performance_trends.php">Performance Trends</a>
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
                        <h1 class="fw-bold">Performance Trends & Analytics</h1>
                        <p class="text-muted mb-0">Deep insights into your academic performance journey</p>
                    </div>
                    <div class="btn-group">
                        <a href="my_predictions.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Predictions
                        </a>
                        <button onclick="exportCharts()" class="btn btn-success">
                            <i class="bi bi-download"></i> Export Report
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?php echo $overall_stats['avg_performance']; ?>%</h3>
                        <small>Avg Performance</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?php echo $overall_stats['best_performance']; ?>%</h3>
                        <small>Best Performance</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?php echo $total_predictions; ?></h3>
                        <small>Total Analyses</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?php echo $overall_stats['total_months']; ?></h3>
                        <small>Months Tracked</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-<?php echo $overall_stats['improvement'] >= 0 ? 'success' : 'danger'; ?> text-white">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?php echo $overall_stats['improvement']; ?>%</h3>
                        <small>Overall Progress</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-secondary text-white">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?php echo $overall_stats['avg_confidence']; ?>%</h3>
                        <small>Avg Confidence</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Charts Row -->
        <div class="row mb-4">
            <!-- Monthly Trend Chart -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Monthly Performance Trend</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="monthlyTrendChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Performance Distribution -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Performance Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="performanceDistributionChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Second Charts Row -->
        <div class="row mb-4">
            <!-- Subject Performance -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Subject Performance Analysis</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="subjectPerformanceChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Exam Type Comparison -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Exam Type Comparison</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="examTypeChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly Trend -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Weekly Performance</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="weeklyTrendChart" height="150"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recommendations Section -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-lightbulb"></i> Performance Insights & Recommendations
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Strengths:</h6>
                                <ul class="list-unstyled">
                                    <?php
                                    $top_subjects = array_slice($subject_averages, 0, 3);
                                    foreach ($top_subjects as $subject => $score):
                                        if ($score >= 70):
                                    ?>
                                    <li class="mb-2">
                                        <i class="bi bi-check-circle text-success"></i>
                                        <strong><?php echo htmlspecialchars($subject); ?>:</strong> 
                                        Excellent performance (<?php echo $score; ?>%)
                                    </li>
                                    <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Areas for Improvement:</h6>
                                <ul class="list-unstyled">
                                    <?php
                                    $bottom_subjects = array_slice($subject_averages, -3, 3);
                                    foreach ($bottom_subjects as $subject => $score):
                                        if ($score < 70):
                                    ?>
                                    <li class="mb-2">
                                        <i class="bi bi-exclamation-triangle text-warning"></i>
                                        <strong><?php echo htmlspecialchars($subject); ?>:</strong> 
                                        Needs focus (<?php echo $score; ?>%)
                                    </li>
                                    <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Overall Recommendation -->
                        <div class="mt-3 p-3 bg-light rounded">
                            <h6>Overall Recommendation:</h6>
                            <p class="mb-0">
                                <?php
                                $avg_perf = $overall_stats['avg_performance'];
                                if ($avg_perf >= 80) {
                                    echo "Outstanding performance! Maintain your current study habits and continue excelling in your strong subjects.";
                                } elseif ($avg_perf >= 70) {
                                    echo "Good performance! Focus on improving your weaker subjects while maintaining strength in your top subjects.";
                                } elseif ($avg_perf >= 60) {
                                    echo "Solid foundation! Consider additional practice in key subjects and develop consistent study routines.";
                                } else {
                                    echo "There's significant room for improvement. Focus on foundational concepts and seek additional support in challenging subjects.";
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Monthly Trend Chart
        const monthlyData = <?php echo json_encode($monthly_trend); ?>;
        const monthlyLabels = monthlyData.map(m => {
            const date = new Date(m.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        });
        
        new Chart(document.getElementById('monthlyTrendChart'), {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [
                    {
                        label: 'Performance',
                        data: monthlyData.map(m => m.performance),
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 3
                    },
                    {
                        label: 'Confidence',
                        data: monthlyData.map(m => m.confidence),
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
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    y: { 
                        min: 0, 
                        max: 100,
                        title: { display: true, text: 'Percentage' }
                    }
                }
            }
        });

        // Performance Distribution Chart
        const performanceRanges = <?php echo json_encode($performance_ranges); ?>;
        new Chart(document.getElementById('performanceDistributionChart'), {
            type: 'doughnut',
            data: {
                labels: ['Excellent (80-100%)', 'Good (70-79%)', 'Average (60-69%)', 'Needs Improvement (50-59%)', 'Poor (0-49%)'],
                datasets: [{
                    data: [
                        performanceRanges.excellent.count,
                        performanceRanges.good.count,
                        performanceRanges.average.count,
                        performanceRanges.needs_improvement.count,
                        performanceRanges.poor.count
                    ],
                    backgroundColor: [
                        performanceRanges.excellent.color,
                        performanceRanges.good.color,
                        performanceRanges.average.color,
                        performanceRanges.needs_improvement.color,
                        performanceRanges.poor.color
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // Subject Performance Chart
        const subjectData = <?php echo json_encode($subject_averages); ?>;
        const subjectLabels = Object.keys(subjectData);
        const subjectScores = Object.values(subjectData);
        
        new Chart(document.getElementById('subjectPerformanceChart'), {
            type: 'bar',
            data: {
                labels: subjectLabels,
                datasets: [{
                    label: 'Average Score',
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
                        min: 0, 
                        max: 100,
                        title: { display: true, text: 'Score (%)' }
                    }
                }
            }
        });

        // Exam Type Comparison Chart
        const examTypeData = <?php echo json_encode($exam_type_averages); ?>;
        new Chart(document.getElementById('examTypeChart'), {
            type: 'bar',
            data: {
                labels: Object.keys(examTypeData),
                datasets: [{
                    label: 'Average Performance',
                    data: Object.values(examTypeData),
                    backgroundColor: ['#3498db', '#9b59b6']
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { 
                        min: 0, 
                        max: 100,
                        title: { display: true, text: 'Performance (%)' }
                    }
                }
            }
        });

        // Weekly Trend Chart
        const weeklyData = <?php echo json_encode($weekly_trend); ?>;
        if (weeklyData.length > 0) {
            new Chart(document.getElementById('weeklyTrendChart'), {
                type: 'line',
                data: {
                    labels: weeklyData.map(w => 'Week ' + w.week.split('-')[1]),
                    datasets: [{
                        label: 'Weekly Performance',
                        data: weeklyData.map(w => w.performance),
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { 
                            min: 0, 
                            max: 100,
                            title: { display: true, text: 'Performance (%)' }
                        }
                    }
                }
            });
        }

        // Export functionality
        function exportCharts() {
            // Simple export - in real implementation, you might want to generate PDF or detailed report
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify({
                monthly_trend: monthlyData,
                performance_distribution: performanceRanges,
                subject_performance: subjectData,
                exam_type_comparison: examTypeData,
                overall_stats: <?php echo json_encode($overall_stats); ?>
            }));
            const downloadAnchorNode = document.createElement('a');
            downloadAnchorNode.setAttribute("href", dataStr);
            downloadAnchorNode.setAttribute("download", "performance_analysis_report.json");
            document.body.appendChild(downloadAnchorNode);
            downloadAnchorNode.click();
            downloadAnchorNode.remove();
            
            alert('Performance report exported successfully!');
        }
    </script>
</body>
</html>