<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';

session_start();
Auth::requireAuth();

$db = Database::getInstance();

// Get prediction ID from request
$prediction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_lecturer_view = isset($_GET['lecturer_view']);

// Get prediction details
$prediction = $db->fetchOne(
    "SELECT p.*, u.username, u.email 
     FROM predictions p 
     JOIN users u ON p.user_id = u.id 
     WHERE p.id = ?",
    [$prediction_id]
);

if (!$prediction) {
    echo '<div class="alert alert-danger">Prediction not found.</div>';
    exit;
}

// Verify access rights
if ($is_lecturer_view) {
    // For lecturers, verify they have access to this student
    if ($_SESSION['role'] !== 'lecturer') {
        echo '<div class="alert alert-danger">Access denied.</div>';
        exit;
    }
    
    $has_access = $db->fetchOne(
        "SELECT 1 FROM group_students gs
         JOIN lecturer_groups lg ON gs.group_id = lg.id
         WHERE gs.student_id = ? AND lg.lecturer_id = ?",
        [$prediction['user_id'], $_SESSION['user_id']]
    );
    
    if (!$has_access) {
        echo '<div class="alert alert-danger">Access to this prediction denied.</div>';
        exit;
    }
} else {
    // For students, verify ownership
    if ($_SESSION['role'] !== 'student' || $prediction['user_id'] != $_SESSION['user_id']) {
        echo '<div class="alert alert-danger">Access denied.</div>';
        exit;
    }
}

// Calculate subject details
$subjects = [];
$total_score = 0;
$subject_count = 0;

for ($i = 1; $i <= 9; $i++) {
    if (!empty($prediction["subject{$i}_name"]) && !empty($prediction["subject{$i}_grade"])) {
        $subject_count++;
        $total_score += $prediction["subject{$i}_grade"];
        $subjects[] = [
            'name' => $prediction["subject{$i}_name"],
            'grade' => $prediction["subject{$i}_grade"],
            'grade_letter' => convertToGrade($prediction["subject{$i}_grade"])
        ];
    }
}

$average_score = $subject_count > 0 ? round($total_score / $subject_count, 2) : 0;

// Helper function to convert grade number to letter
function convertToGrade($grade) {
    $grade_map = [
        1 => 'A1',
        2 => 'B2', 
        3 => 'B3',
        4 => 'C4',
        5 => 'C5',
        6 => 'C6',
        7 => 'D7',
        8 => 'E8',
        9 => 'F9'
    ];
    return $grade_map[$grade] ?? 'N/A';
}
?>

<div class="prediction-details">
    <?php if ($is_lecturer_view): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> 
            Viewing prediction for student: <strong><?php echo htmlspecialchars($prediction['username']); ?></strong>
            (<?php echo htmlspecialchars($prediction['email']); ?>)
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">Prediction Summary</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <strong>Exam Type:</strong><br>
                            <span class="badge bg-primary"><?php echo $prediction['exam_type']; ?></span>
                        </div>
                        <div class="col-6">
                            <strong>Current CGPA:</strong><br>
                            <span class="badge bg-info"><?php echo $prediction['current_cgpa'] ?? 'N/A'; ?>/5.0</span>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-6">
                            <strong>Predicted Performance:</strong><br>
                            <span class="badge bg-<?php 
                                echo $prediction['predicted_performance'] >= 70 ? 'success' : 
                                     ($prediction['predicted_performance'] >= 50 ? 'warning' : 'danger'); 
                            ?> fs-6">
                                <?php echo $prediction['predicted_performance']; ?>%
                            </span>
                        </div>
                        <div class="col-6">
                            <strong>Confidence Level:</strong><br>
                            <span class="badge bg-<?php 
                                echo $prediction['confidence_level'] >= 80 ? 'success' : 
                                     ($prediction['confidence_level'] >= 60 ? 'warning' : 'danger'); 
                            ?> fs-6">
                                <?php echo $prediction['confidence_level']; ?>%
                            </span>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <strong>Analysis Date:</strong><br>
                            <?php echo Utils::formatDate($prediction['created_at'], 'F j, Y \a\t g:i A'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">Study Context</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <strong>Study Hours/Week:</strong><br>
                            <span class="badge bg-secondary"><?php echo $prediction['study_hours_per_week']; ?> hours</span>
                        </div>
                        <div class="col-6">
                            <strong>School Type:</strong><br>
                            <span class="badge bg-info"><?php echo ucfirst($prediction['school_type']); ?></span>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-6">
                            <strong>Learning Materials:</strong><br>
                            <span class="badge bg-<?php 
                                echo $prediction['access_to_learning_materials'] === 'excellent' ? 'success' : 
                                     ($prediction['access_to_learning_materials'] === 'good' ? 'info' : 
                                     ($prediction['access_to_learning_materials'] === 'average' ? 'warning' : 'danger')); 
                            ?>">
                                <?php echo ucfirst($prediction['access_to_learning_materials']); ?>
                            </span>
                        </div>
                        <div class="col-6">
                            <strong>Family Income:</strong><br>
                            <span class="badge bg-secondary"><?php echo ucfirst($prediction['family_income_level']); ?></span>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <strong>Parent Education:</strong><br>
                            <span class="badge bg-dark"><?php echo ucfirst($prediction['parent_education_level']); ?> education</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">Subject Performance (<?php echo $subject_count; ?> subjects)</h6>
                </div>
                <div class="card-body">
                    <?php if ($subject_count > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Grade</th>
                                        <th>Score</th>
                                        <th>Performance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subjects as $subject): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($subject['name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $subject['grade'] <= 2 ? 'success' : 
                                                         ($subject['grade'] <= 4 ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo $subject['grade_letter']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $subject['grade']; ?></td>
                                            <td>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar bg-<?php 
                                                        echo $subject['grade'] <= 2 ? 'success' : 
                                                             ($subject['grade'] <= 4 ? 'warning' : 'danger'); 
                                                    ?>" style="width: <?php echo (9 - $subject['grade']) * 12.5; ?>%"></div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <strong>Average Subject Score:</strong> 
                            <span class="badge bg-info"><?php echo $average_score; ?></span>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">No subject data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>