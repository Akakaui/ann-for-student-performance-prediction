<?php
require_once '../lib/config.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';

session_start();

header('Content-Type: application/json');

$health_check = [
    'status' => 'healthy',
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

try {
    // Database check
    $db = Database::getInstance();
    $db_result = $db->fetchOne("SELECT 1 as test");
    $health_check['checks']['database'] = [
        'status' => 'healthy',
        'message' => 'Database connection successful'
    ];
} catch (Exception $e) {
    $health_check['checks']['database'] = [
        'status' => 'unhealthy',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ];
    $health_check['status'] = 'unhealthy';
}

// Python check
try {
    $test_data = ['test' => true];
    $result = Utils::callPythonModel($test_data);
    $health_check['checks']['python_model'] = [
        'status' => 'healthy',
        'message' => 'Python model integration working'
    ];
} catch (Exception $e) {
    $health_check['checks']['python_model'] = [
        'status' => 'unhealthy',
        'message' => 'Python model failed: ' . $e->getMessage()
    ];
    $health_check['status'] = 'unhealthy';
}

// File permissions check
$writable_dirs = [
    '../assets/css/style.css',
    '../assets/js/script.js'
];

foreach ($writable_dirs as $dir) {
    if (is_writable($dir)) {
        $health_check['checks']['file_permissions'] = [
            'status' => 'healthy',
            'message' => 'File permissions are correct'
        ];
        break;
    }
}

// Session check
if (session_status() === PHP_SESSION_ACTIVE) {
    $health_check['checks']['sessions'] = [
        'status' => 'healthy',
        'message' => 'Session management working'
    ];
} else {
    $health_check['checks']['sessions'] = [
        'status' => 'unhealthy',
        'message' => 'Session management failed'
    ];
    $health_check['status'] = 'unhealthy';
}

echo json_encode($health_check, JSON_PRETTY_PRINT);
?>