<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';

session_start();
Auth::requireAuth();

// Only lecturers can export progress data
if ($_SESSION['role'] !== 'lecturer') {
    $_SESSION['error'] = "Access denied. This page is for lecturers only.";
    Utils::redirect('dashboard.php');
}

$db = Database::getInstance();
$lecturer_id = $_SESSION['user_id'];

// Get selected group or all groups
$selected_group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;

// Build query for students
$query = "
    SELECT DISTINCT u.id, u.username, u.email, u.created_at as joined_date,
           lg.group_name
    FROM users u
    JOIN group_students gs ON u.id = gs.student_id
    JOIN lecturer_groups lg ON gs.group_id = lg.id
    WHERE lg.lecturer_id = ?
";

$params = [$lecturer_id];

if ($selected_group_id > 0) {
    $query .= " AND lg.id = ?";
    $params[] = $selected_group_id;
}

$query .= " ORDER BY u.username";

$students = $db->fetchAll($query, $params);

// Helper function to calculate student progress
function calculateStudentProgress($db, $student_id) {
    $predictions = $db->fetchAll(
        "SELECT * FROM predictions 
         WHERE user_id = ? 
         ORDER BY created_at ASC",
        [$student_id]
    );
    
    $total_predictions = count($predictions);
    
    if ($total_predictions === 0) {
        return [
            'total_predictions' => 0,
            'improvement' => 0,
            'current_performance' => 0,
            'starting_performance' => 0,
            'progress_status' => 'no_data'
        ];
    }
    
    $first_prediction = $predictions[0];
    $latest_prediction = end($predictions);
    $improvement = $latest_prediction['predicted_performance'] - $first_prediction['predicted_performance'];
    
    if ($improvement > 5) {
        $progress_status = 'Improving';
    } elseif ($improvement > -2) {
        $progress_status = 'Stable';
    } else {
        $progress_status = 'Declining';
    }
    
    return [
        'total_predictions' => $total_predictions,
        'improvement' => round($improvement, 1),
        'current_performance' => round($latest_prediction['predicted_performance'], 1),
        'starting_performance' => round($first_prediction['predicted_performance'], 1),
        'progress_status' => $progress_status,
        'last_prediction_date' => $latest_prediction['created_at']
    ];
}

// Prepare CSV data
$csv_data = [];
$csv_data[] = [
    'Student Name',
    'Email',
    'Group',
    'Total Predictions',
    'Starting Performance (%)',
    'Current Performance (%)',
    'Improvement (%)',
    'Progress Status',
    'Last Prediction Date',
    'Joined Date'
];

foreach ($students as $student) {
    $progress = calculateStudentProgress($db, $student['id']);
    
    $csv_data[] = [
        $student['username'],
        $student['email'],
        $student['group_name'],
        $progress['total_predictions'],
        $progress['starting_performance'],
        $progress['current_performance'],
        $progress['improvement'],
        $progress['progress_status'],
        $progress['last_prediction_date'] ? date('Y-m-d', strtotime($progress['last_prediction_date'])) : 'Never',
        date('Y-m-d', strtotime($student['joined_date']))
    ];
}

// Set headers for CSV download
$filename = $selected_group_id > 0 ? "student_progress_group_{$selected_group_id}.csv" : "all_students_progress.csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel compatibility
fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

// Write CSV data
foreach ($csv_data as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;