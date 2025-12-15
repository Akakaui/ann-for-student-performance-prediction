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

// Get student ID from URL
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

// Verify student is in lecturer's groups
$student = $db->fetchOne(
    "SELECT u.*, lg.group_name 
     FROM users u
     JOIN group_students gs ON u.id = gs.student_id
     JOIN lecturer_groups lg ON gs.group_id = lg.id
     WHERE u.id = ? AND lg.lecturer_id = ?",
    [$student_id, $lecturer_id]
);

if (!$student) {
    $_SESSION['error'] = "Student not found or access denied.";
    Utils::redirect('student_progress.php');
}

// Get all predictions for the student
$predictions = $db->fetchAll(
    "SELECT * FROM predictions 
     WHERE user_id = ? 
     ORDER BY created_at ASC",
    [$student_id]
);

// Calculate detailed progress metrics
$total_predictions = count($predictions);
$performance_data = [];
$monthly_trend = [];
$exam_type_comparison = ['WAEC' => [], 'NECO' => []];

if ($total_predictions > 0) {
    // Prepare data for charts
    foreach ($predictions as $prediction) {
        $performance_data[] = [
            'date' => $prediction['created_at'],
            'performance' => $prediction['predicted_performance'],
            'confidence' => $prediction['confidence_level'],
            'exam_type' => $prediction['exam_type']
        ];
        
        // Monthly trend
        $month = date('Y-m', strtotime($prediction['created_at']));
        if (!isset($monthly_trend[$month])) {
            $monthly_trend[$month] = [
                'performance' => 0,
                'count' => 0
            ];
        }
        $monthly_trend[$month]['performance'] += $prediction['predicted_performance'];
        $monthly_trend[$month]['count']++;
        
        // Exam type comparison
        $exam_type_comparison[$prediction['exam_type']][] = $prediction['predicted_performance'];
    }
    
    // Calculate monthly averages
    foreach ($monthly_trend as $month => $data) {
        $monthly_trend[$month] = round($data['performance'] / $data['count'], 2);
    }
    
    // Calculate exam type averages
    foreach ($exam_type_comparison as $type => $scores) {
        if (!empty($scores)) {
            $exam_type_comparison[$type] = [
                'average' => round(array_sum($scores) / count($scores), 2),
                'count' => count($scores)
            ];
        }
    }
    
    // Calculate improvement metrics
    $first_prediction = $predictions[0];
    $latest_prediction = end($predictions);
    $improvement = $latest_prediction['predicted_performance'] - $first_prediction['predicted_performance'];
    
    // Calculate consistency
    $performances = array_column($predictions, 'predicted_performance');
    $consistency_score = calculateConsistency($performances);
    
    // Determine progress status
    if ($improvement > 5) {
        $progress_status = 'improving';
        $progress_icon = 'arrow-up';
        $progress_color = 'success';
    } elseif ($improvement > -2) {
        $progress_status = 'stable';
        $progress_icon = 'arrow-right';
        $progress_color = 'warning';
    } else {
        $progress_status = 'declining';
        $progress_icon = 'arrow-down';
        $progress_color = 'danger';
    }
    
    // Get subject performance
    $subject_performance = analyzeSubjectPerformance($predictions);
    
    // Get study habit analysis
    $study_habits = analyzeStudyHabits($predictions);
}

// Helper function for consistency calculation
function calculateConsistency($performances) {
    if (count($performances) < 2) return 100;
    
    $mean = array_sum($performances) / count($performances);
    $variance = 0.0;
    foreach ($performances as $performance) {
        $variance += pow($performance - $mean, 2);
    }
    $variance /= count($performances);
    $std_dev = sqrt($variance);
    
    return max(0, 100 - ($std_dev * 2));
}

// Helper function to analyze subject performance
function analyzeSubjectPerformance($predictions) {
    $subjects = [];
    
    foreach ($predictions as $prediction) {
        for ($i = 1; $i <= 9; $i++) {
            $subject_name = $prediction["subject{$i}_name"];
            $subject_grade = $prediction["subject{$i}_grade"];
            
            if (!empty($subject_name) && !empty($subject_grade)) {
                if (!isset($subjects[$subject_name])) {
                    $subjects[$subject_name] = [
                        'grades' => [],
                        'count' => 0
                    ];
                }
                $subjects[$subject_name]['grades'][] = $subject_grade;
                $subjects[$subject_name]['count']++;
            }
        }
    }
    
    // Calculate averages and trends
    $subject_analysis = [];
    foreach ($subjects as $subject => $data) {
        if ($data['count'] >= 2) {
            $average_grade = array_sum($data['grades']) / count($data['grades']);
            $first_grade = $data['grades'][0];
            $latest_grade = end($data['grades']);
            $improvement = $latest_grade - $first_grade;
            
            $subject_analysis[$subject] = [
                'average_grade' => round($average_grade, 2),
                'improvement' => round($improvement, 2),
                'count' => $data['count'],
                'trend' => $improvement > 0.5 ? 'improving' : ($improvement < -0.5 ? 'declining' : 'stable')
            ];
        }
    }
    
    // Sort by improvement (best first)
    uasort($subject_analysis, function($a, $b) {
        return $b['improvement'] <=> $a['improvement'];
    });
    
    return $subject_analysis;
}

// Helper function to analyze study habits
function analyzeStudyHabits($predictions) {
    $habits = [
        'study_hours' => [],
        'material_access' => [],
        'parent_education' => []
    ];
    
    foreach ($predictions as $prediction) {
        $habits['study_hours'][] = $prediction['study_hours_per_week'];
        $habits['material_access'][] = $prediction['access_to_learning_materials'];
        $habits['parent_education'][] = $prediction['parent_education_level'];
    }
    
    return [
        'avg_study_hours' => round(array_sum($habits['study_hours']) / count($habits['study_hours']), 1),
        'common_material_access' => array_reduce($habits['material_access'], function($carry, $item) {
            $carry[$item] = isset($carry[$item]) ? $carry[$item] + 1 : 1;
            return $carry;
        }, []),
        'common_parent_education' => array_reduce($habits['parent_education'], function($carry, $item) {
            $carry[$item] = isset($carry[$item]) ? $carry[$item] + 1 : 1;
            return $carry;
        }, [])
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($student['username']); ?> - Progress Analysis</title>
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
                        <a class="nav-link" href="student_progress.php">Student Progress</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="individual_progress.php?student_id=<?php echo $student_id; ?>">
                            <?php echo htmlspecialchars($student['username']); ?>
                        </a>
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
                        <h1 class="fw-bold">Progress Analysis: <?php echo htmlspecialchars($student['username']); ?></h1>
                        <p class="text-muted mb-0">
                            Group: <?php echo htmlspecialchars($student['group_name']); ?> • 
                            Email: <?php echo htmlspecialchars($student['email']); ?>
                        </p>
                    </div>
                    <div class="btn-group">
                        <a href="student_progress.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to All Students
                        </a>
                        <a href="export_student_progress.php?student_id=<?php echo $student_id; ?>" class="btn btn-success">
                            <i class="bi bi-download"></i> Export Report
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($total_predictions === 0): ?>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-graph-up display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">No Predictions Yet</h4>
                            <p class="text-muted">This student hasn't made any predictions yet.</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Key Metrics -->
            <div class="row mb-4">
                <div class="col-xl-2 col-md-4 col-6 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h3 class="card-title"><?php echo $total_predictions; ?></h3>
                            <p class="card-text">Total Predictions</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h3 class="card-title"><?php echo $latest_prediction['predicted_performance']; ?>%</h3>
                            <p class="card-text">Current Performance</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6 mb-3">
                    <div class="card bg-<?php echo $progress_color; ?> text-white">
                        <div class="card-body text-center">
                            <h3 class="card-title">
                                <i class="bi bi-<?php echo $progress_icon; ?>"></i>
                                <?php echo $improvement; ?>%
                            </h3>
                            <p class="card-text">Overall Improvement</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6 mb-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h3 class="card-title"><?php echo $consistency_score; ?>%</h3>
                            <p class="card-text">Consistency Score</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6 mb-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h3 class="card-title"><?php echo $first_prediction['predicted_performance']; ?>%</h3>
                            <p class="card-text">Starting Performance</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6 mb-3">
                    <div class="card bg-secondary text-white">
                        <div class="card-body text-center">
                            <h3 class="card-title"><?php echo count($monthly_trend); ?></h3>
                            <p class="card-text">Months Tracked</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <!-- Performance Trend -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Performance Trend Over Time</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="performanceChart" height="250"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Exam Type Comparison -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Exam Type Performance</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="examTypeChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subject Performance & Study Habits -->
            <div class="row mb-4">
                <!-- Subject Performance -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Subject Performance Analysis</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($subject_performance)): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Average Grade</th>
                                                <th>Improvement</th>
                                                <th>Trend</th>
                                                <th>Predictions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($subject_performance as $subject => $data): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($subject); ?></strong></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $data['average_grade'] <= 2 ? 'success' : 
                                                                 ($data['average_grade'] <= 4 ? 'warning' : 'danger'); 
                                                        ?>">
                                                            <?php echo $data['average_grade']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="text-<?php echo $data['improvement'] >= 0 ? 'success' : 'danger'; ?>">
                                                            <i class="bi bi-arrow-<?php echo $data['improvement'] >= 0 ? 'up' : 'down'; ?>"></i>
                                                            <?php echo abs($data['improvement']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $data['trend'] === 'improving' ? 'success' : 
                                                                 ($data['trend'] === 'stable' ? 'warning' : 'danger'); 
                                                        ?>">
                                                            <?php echo ucfirst($data['trend']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo $data['count']; ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-journal-text display-6"></i>
                                    <p class="mt-2">Insufficient subject data for analysis</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Study Habits -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Study Habits Analysis</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <h6>Average Study Hours per Week:</h6>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-3" style="height: 20px;">
                                            <div class="progress-bar bg-success" 
                                                 style="width: <?php echo min($study_habits['avg_study_hours'] * 5, 100); ?>%">
                                                <?php echo $study_habits['avg_study_hours']; ?> hours
                                            </div>
                                        </div>
                                        <small class="text-muted">/ week</small>
                                    </div>
                                </div>
                                
                                <div class="col-12 mb-3">
                                    <h6>Learning Materials Access:</h6>
                                    <?php 
                                    $material_access = $study_habits['common_material_access'];
                                    if (!empty($material_access)):
                                        arsort($material_access);
                                        $most_common = array_key_first($material_access);
                                    ?>
                                        <span class="badge bg-<?php 
                                            echo $most_common === 'excellent' ? 'success' : 
                                                 ($most_common === 'good' ? 'info' : 
                                                 ($most_common === 'average' ? 'warning' : 'danger')); 
                                        ?>">
                                            <?php echo ucfirst($most_common); ?> access
                                        </span>
                                        <small class="text-muted ms-2">
                                            (<?php echo $material_access[$most_common]; ?> predictions)
                                        </small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-12">
                                    <h6>Parent Education Level:</h6>
                                    <?php 
                                    $parent_education = $study_habits['common_parent_education'];
                                    if (!empty($parent_education)):
                                        arsort($parent_education);
                                        $most_common = array_key_first($parent_education);
                                    ?>
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst($most_common); ?> education
                                        </span>
                                        <small class="text-muted ms-2">
                                            (<?php echo $parent_education[$most_common]; ?> predictions)
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Predictions -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Predictions</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Exam Type</th>
                                            <th>Performance</th>
                                            <th>Confidence</th>
                                            <th>Subjects</th>
                                            <th>Study Hours</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($predictions, -10) as $prediction): ?>
                                            <tr>
                                                <td>
                                                    <?php echo Utils::formatDate($prediction['created_at'], 'M j, Y'); ?><br>
                                                    <small class="text-muted"><?php echo Utils::formatDate($prediction['created_at'], 'g:i A'); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $prediction['exam_type']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $prediction['predicted_performance'] >= 70 ? 'success' : 
                                                             ($prediction['predicted_performance'] >= 50 ? 'warning' : 'danger'); 
                                                    ?>">
                                                        <?php echo $prediction['predicted_performance']; ?>%
                                                    </span>
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
                                                    <span class="badge bg-info"><?php echo $prediction['study_hours_per_week']; ?> hrs</span>
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
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
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
                    <!-- Details loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($total_predictions > 0): ?>
        // Performance Trend Chart
        const performanceData = <?php echo json_encode($performance_data); ?>;
        const performanceLabels = performanceData.map(p => {
            const date = new Date(p.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        
        const performanceValues = performanceData.map(p => p.performance);
        const confidenceValues = performanceData.map(p => p.confidence);
        
        new Chart(document.getElementById('performanceChart'), {
            type: 'line',
            data: {
                labels: performanceLabels,
                datasets: [
                    {
                        label: 'Performance',
                        data: performanceValues,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 3
                    },
                    {
                        label: 'Confidence',
                        data: confidenceValues,
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
                scales: {
                    y: {
                        beginAtZero: false,
                        min: 0,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Percentage'
                        }
                    }
                }
            }
        });

        // Exam Type Comparison Chart
        const examTypeData = <?php echo json_encode($exam_type_comparison); ?>;
        const examLabels = Object.keys(examTypeData).filter(key => examTypeData[key].count > 0);
        const examAverages = examLabels.map(label => examTypeData[label].average);
        const examCounts = examLabels.map(label => examTypeData[label].count);
        
        new Chart(document.getElementById('examTypeChart'), {
            type: 'bar',
            data: {
                labels: examLabels,
                datasets: [{
                    label: 'Average Performance',
                    data: examAverages,
                    backgroundColor: ['#3498db', '#9b59b6']
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
                            text: 'Performance (%)'
                        }
                    }
                }
            }
        });
        <?php endif; ?>

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