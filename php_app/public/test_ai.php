<?php
phpinfo();
?>
<?php
require_once '../lib/config.php';
require_once '../lib/utils.php';

// Test data for prediction
$test_data = [
    'exam_type' => 'WAEC',
    'subjects' => [
        ['name' => 'Mathematics', 'grade' => 2],
        ['name' => 'English', 'grade' => 3],
        ['name' => 'Physics', 'grade' => 4],
        ['name' => 'Chemistry', 'grade' => 3],
        ['name' => 'Biology', 'grade' => 4],
        ['name' => 'Geography', 'grade' => 5]
    ],
    'parent_education_level' => 'secondary',
    'study_hours_per_week' => 20,
    'access_to_learning_materials' => 'good',
    'school_type' => 'public',
    'family_income_level' => 'middle'
];

try {
    echo "<h2>Testing AI Prediction System</h2>";
    echo "<pre>Input Data: " . json_encode($test_data, JSON_PRETTY_PRINT) . "</pre>";
    
    $result = Utils::callPythonModel($test_data);
    
    echo "<h3>Prediction Result:</h3>";
    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
    
    if ($result['success']) {
        echo "<div style='background: green; color: white; padding: 10px;'>";
        echo "✅ AI Prediction Successful!";
        echo "</div>";
    } else {
        echo "<div style='background: red; color: white; padding: 10px;'>";
        echo "❌ AI Prediction Failed: " . $result['error'];
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: red; color: white; padding: 10px;'>";
    echo "❌ System Error: " . $e->getMessage();
    echo "</div>";
}
?>