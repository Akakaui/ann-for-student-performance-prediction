<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';

session_start();
Auth::requireAuth();

// Only lecturers can access this
if ($_SESSION['role'] !== 'lecturer') {
    http_response_code(403);
    exit('Access denied');
}

$db = Database::getInstance();
$lecturer_id = $_SESSION['user_id'];

// Get student ID from request
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

// Verify student is in lecturer's groups
$student = $db->fetchOne(
    "SELECT u.* FROM users u
     JOIN group_students gs ON u.id = gs.student_id
     JOIN lecturer_groups lg ON gs.group_id = lg.id
     WHERE u.id = ? AND lg.lecturer_id = ?",
    [$student_id, $lecturer_id]
);

if (!$student) {
    http_response_code(404);
    exit('Student not found');
}

// Get all predictions for the student
$predictions = $db->fetchAll(
    "SELECT * FROM predictions 
     WHERE user_id = ? 
     ORDER BY created_at DESC",
    [$student_id]
);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Predictions for <?php echo htmlspecialchars($student['username']); ?></h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>

    <?php if (empty($predictions)): ?>
        <div class="text-center py-4">
            <i class="bi bi-graph-up display-1 text-muted"></i>
            <p class="text-muted mt-3">No predictions found for this student.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Exam Type</th>
                        <th>Current CGPA</th>
                        <th>Performance</th>
                        <th>Confidence</th>
                        <th>Subjects</th>
                        <th>Study Hours</th>
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
                                <?php if (!empty($prediction['current_cgpa'])): ?>
                                    <span class="badge bg-info"><?php echo $prediction['current_cgpa']; ?>/5.0</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">N/A</span>
                                <?php endif; ?>
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
                                <span class="badge bg-secondary"><?php echo $prediction['study_hours_per_week']; ?> hrs/wk</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-3 text-center">
            <small class="text-muted">Showing <?php echo count($predictions); ?> predictions</small>
        </div>
    <?php endif; ?>
</div>