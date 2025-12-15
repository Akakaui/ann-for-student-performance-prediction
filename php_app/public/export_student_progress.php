<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';

session_start();
Auth::requireAuth();

// Only lecturers can export student progress
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

// Prepare CSV data
$csv_data = [];
$csv_data[] = [
    'Student Progress Report - ' . $student['username'],
    '', '', '', '', '' // Empty cells for formatting
];

$csv_data[] = []; // Empty row
$csv_data[] = [
    'Student Information',
    '', '', '', '', ''
];
$csv_data[] = [
    'Name:', $student['username'],
    'Email:', $student['email'],
    'Group:', $student['group_name']
];
$csv_data[] = [
    'Joined:', date('Y-m-d', strtotime($student['created_at'])),
    'Total Predictions:', count($predictions),
    '', ''
];

$csv_data[] = []; // Empty row

if (count($predictions) > 0) {
    $first_prediction = $predictions[0];
    $latest_prediction = end($predictions);
    $improvement = $latest_prediction['predicted_performance'] - $first_prediction['predicted_performance'];
    
    $csv_data[] = [
        'Progress Summary',
        '', '', '', '', ''
    ];
    $csv_data[] = [
        'Starting Performance:', $first_prediction['predicted_performance'] . '%',
        'Current Performance:', $latest_prediction['predicted_performance'] . '%',
        'Improvement:', $improvement . '%'
    ];
    
    $csv_data[] = []; // Empty row
}

$csv_data[] = [
    'Detailed Prediction History',
    '', '', '', '', ''
];
$csv_data[] = [
    'Date',
    'Exam Type',
    'Current CGPA',
    'Predicted Performance',
    'Confidence Level',
    'Study Hours/Week',
    'Subjects Count'
];

foreach ($predictions as $prediction) {
    $subject_count = 0;
    for ($i = 1; $i <= 9; $i++) {
        if (!empty($prediction["subject{$i}_name"])) {
            $subject_count++;
        }
    }
    
    $csv_data[] = [
        date('Y-m-d H:i', strtotime($prediction['created_at'])),
        $prediction['exam_type'],
        $prediction['current_cgpa'] ?? 'N/A',
        $prediction['predicted_performance'] . '%',
        $prediction['confidence_level'] . '%',
        $prediction['study_hours_per_week'],
        $subject_count
    ];
}

// Set headers for CSV download
$filename = "student_progress_" . str_replace(' ', '_', $student['username']) . ".csv";
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