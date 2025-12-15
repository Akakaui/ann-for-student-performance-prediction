<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';

session_start();
Auth::requireAuth();

$db = Database::getInstance();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get predictions for export
if ($role === 'student') {
    $predictions = $db->fetchAll("
        SELECT * FROM predictions 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ", [$user_id]);
    
    $filename = "my_university_predictions.csv";
    
} elseif ($role === 'lecturer') {
    // Check if specific group export is requested
    $group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
    
    if ($group_id > 0) {
        // Verify lecturer owns this group
        $group = $db->fetchOne("
            SELECT * FROM lecturer_groups 
            WHERE id = ? AND lecturer_id = ?
        ", [$group_id, $user_id]);
        
        if (!$group) {
            $_SESSION['error'] = "Group not found or access denied.";
            Utils::redirect('lecturer_groups.php');
        }
        
        $predictions = $db->fetchAll("
            SELECT p.*, u.username, u.email 
            FROM predictions p
            JOIN group_students gs ON p.user_id = gs.student_id
            JOIN users u ON p.user_id = u.id
            WHERE gs.group_id = ?
            ORDER BY u.username, p.created_at DESC
        ", [$group_id]);
        
        $filename = "group_{$group['group_name']}_predictions.csv";
    } else {
        // All students for this lecturer
        $predictions = $db->fetchAll("
            SELECT p.*, u.username, u.email 
            FROM predictions p
            JOIN group_students gs ON p.user_id = gs.student_id
            JOIN lecturer_groups lg ON gs.group_id = lg.id
            JOIN users u ON p.user_id = u.id
            WHERE lg.lecturer_id = ?
            ORDER BY u.username, p.created_at DESC
        ", [$user_id]);
        
        $filename = "all_students_predictions.csv";
    }
    
} elseif ($role === 'admin') {
    $predictions = $db->fetchAll("
        SELECT p.*, u.username, u.email, u.role 
        FROM predictions p
        JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC
    ", [$user_id]);
    
    $filename = "all_system_predictions.csv";
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add CSV headers
if ($role === 'student') {
    fputcsv($output, [
        'Prediction Date', 'Exam Type', 'Current CGPA', 
        'Predicted Performance', 'Confidence Level', 'Subjects Count'
    ]);
    
    foreach ($predictions as $prediction) {
        $subject_count = 0;
        for ($i = 1; $i <= 9; $i++) {
            if (!empty($prediction["subject{$i}_name"])) {
                $subject_count++;
            }
        }
        
        fputcsv($output, [
            $prediction['created_at'],
            $prediction['exam_type'],
            $prediction['current_cgpa'],
            $prediction['predicted_performance'] . '%',
            $prediction['confidence_level'] . '%',
            $subject_count
        ]);
    }
    
} elseif ($role === 'lecturer') {
    fputcsv($output, [
        'Student', 'Email', 'Prediction Date', 'Exam Type', 'Current CGPA',
        'Predicted Performance', 'Confidence Level', 'Subjects Count'
    ]);
    
    foreach ($predictions as $prediction) {
        $subject_count = 0;
        for ($i = 1; $i <= 9; $i++) {
            if (!empty($prediction["subject{$i}_name"])) {
                $subject_count++;
            }
        }
        
        fputcsv($output, [
            $prediction['username'],
            $prediction['email'],
            $prediction['created_at'],
            $prediction['exam_type'],
            $prediction['current_cgpa'],
            $prediction['predicted_performance'] . '%',
            $prediction['confidence_level'] . '%',
            $subject_count
        ]);
    }
    
} elseif ($role === 'admin') {
    fputcsv($output, [
        'User', 'Email', 'Role', 'Prediction Date', 'Exam Type', 'Current CGPA',
        'Predicted Performance', 'Confidence Level', 'Subjects Count'
    ]);
    
    foreach ($predictions as $prediction) {
        $subject_count = 0;
        for ($i = 1; $i <= 9; $i++) {
            if (!empty($prediction["subject{$i}_name"])) {
                $subject_count++;
            }
        }
        
        fputcsv($output, [
            $prediction['username'],
            $prediction['email'],
            $prediction['role'],
            $prediction['created_at'],
            $prediction['exam_type'],
            $prediction['current_cgpa'],
            $prediction['predicted_performance'] . '%',
            $prediction['confidence_level'] . '%',
            $subject_count
        ]);
    }
}

fclose($output);
exit;