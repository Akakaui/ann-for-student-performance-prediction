<?php
require_once 'config.php';

class Utils {
    public static function redirect($url) {
        // Use relative redirects to avoid BASE_URL path issues
        header("Location: " . $url);
        exit();
    }

    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public static function sanitize($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }

    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function formatDate($date, $format = 'Y-m-d H:i:s') {
        $timestamp = strtotime($date);
        return $timestamp ? date($format, $timestamp) : '';
    }

    public static function getGradeText($grade) {
        $grades = [
            1 => 'A1 (Excellent)',
            2 => 'B2 (Very Good)',
            3 => 'B3 (Good)',
            4 => 'C4 (Credit)',
            5 => 'C5 (Credit)',
            6 => 'C6 (Credit)',
            7 => 'D7 (Pass)',
            8 => 'E8 (Pass)',
            9 => 'F9 (Fail)'
        ];
        return $grades[$grade] ?? 'Unknown';
    }

    public static function calculateAverageGrade($grades) {
        $validGrades = array_filter($grades, function($grade) {
            return $grade !== null && $grade >= 1 && $grade <= 9;
        });
        
        if (empty($validGrades)) {
            return null;
        }
        
        return array_sum($validGrades) / count($validGrades);
    }

    public static function callPythonModel($inputData) {
        $jsonInput = json_encode($inputData);
        $command = '"' . PYTHON_PATH . '" "' . MODEL_SCRIPT_PATH . '" "' . addslashes($jsonInput) . '"';
        
        // Add timeout to prevent hanging (30 seconds max)
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows command with timeout
            $command = 'timeout 30 ' . $command;
        } else {
            // Linux/Mac command with timeout
            $command = 'timeout 30s ' . $command;
        }
        
        $output = shell_exec($command . ' 2>&1');
        
        if ($output === null) {
            error_log("Python script execution failed or timed out");
            // Use fallback prediction
            return self::fallbackPrediction($inputData);
        }
        
        $result = json_decode(trim($output), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Invalid JSON response from Python script: " . $output);
            // If JSON decode fails, use fallback calculation
            return self::fallbackPrediction($inputData);
        }
        
        return $result;
    }

    // Fallback prediction if Python fails or times out
    public static function fallbackPrediction($inputData) {
        $subjects = $inputData['subjects'] ?? [];
        $grades = [];
        
        foreach ($subjects as $subject) {
            if (!empty($subject['grade'])) {
                $grades[] = $subject['grade'];
            }
        }
        
        if (count($grades) < 5) {
            return [
                'success' => false,
                'error' => 'Insufficient subject data (minimum 5 subjects required)',
                'predicted_performance' => 0,
                'confidence_level' => 0,
                'feature_contributions' => [],
                'interpretation' => 'Please provide at least 5 subjects with valid grades.'
            ];
        }
        
        // Simple calculation based on WAEC grades and CGPA
        $avgGrade = array_sum($grades) / count($grades);
        $cgpa = floatval($inputData['current_cgpa'] ?? 3.0);
        
        // Convert WAEC grades to percentage (lower grades = better performance)
        // A1(1) = 90%, B2(2) = 80%, B3(3) = 70%, C4(4) = 60%, C5(5) = 55%, C6(6) = 50%, D7(7) = 45%, E8(8) = 40%, F9(9) = 30%
        $gradeScore = (9 - $avgGrade) * 8 + 30;
        
        // CGPA contribution (assuming 5.0 scale)
        $cgpaScore = $cgpa * 8;
        
        // Socio-economic factors
        $parentEducationMap = ['none' => 0, 'primary' => 2, 'secondary' => 4, 'tertiary' => 6];
        $learningMaterialsMap = ['poor' => 0, 'average' => 2, 'good' => 4, 'excellent' => 6];
        $schoolTypeMap = ['public' => 0, 'private' => 3];
        $familyIncomeMap = ['low' => 0, 'middle' => 2, 'high' => 4];
        
        $socioEconomicScore = 
            ($parentEducationMap[$inputData['parent_education_level'] ?? 'secondary'] ?? 2) +
            ($learningMaterialsMap[$inputData['access_to_learning_materials'] ?? 'average'] ?? 2) +
            ($schoolTypeMap[$inputData['school_type'] ?? 'public'] ?? 0) +
            ($familyIncomeMap[$inputData['family_income_level'] ?? 'middle'] ?? 2);
        
        // Study hours impact (diminishing returns)
        $studyHours = intval($inputData['study_hours_per_week'] ?? 15);
        $studyScore = min($studyHours * 0.5, 10); // Max 10 points
        
        $prediction = 20 + $gradeScore + $cgpaScore + $socioEconomicScore + $studyScore;
        $prediction = max(0, min(100, round($prediction, 1)));
        
        // Calculate confidence based on input quality
        $confidence = 60.0;
        if (count($grades) >= 7) $confidence += 10;
        if ($cgpa >= 3.0 && $cgpa <= 4.5) $confidence += 5;
        if ($studyHours >= 15 && $studyHours <= 35) $confidence += 5;
        
        $confidence = max(50, min(85, $confidence));
        
        $interpretation = self::getFallbackInterpretation($prediction);
        
        return [
            'success' => true,
            'predicted_performance' => $prediction,
            'confidence_level' => $confidence,
            'feature_contributions' => [
                'current_cgpa' => ['importance' => 30.0, 'value' => $cgpa, 'impact' => $cgpa >= 3.0 ? 'positive' : 'negative'],
                'average_grade' => ['importance' => 25.0, 'value' => $avgGrade, 'impact' => $avgGrade <= 4.5 ? 'positive' : 'negative'],
                'study_hours' => ['importance' => 15.0, 'value' => $studyHours, 'impact' => $studyHours >= 15 ? 'positive' : 'negative'],
                'parent_education' => ['importance' => 10.0, 'value' => 2, 'impact' => 'positive'],
                'learning_materials' => ['importance' => 10.0, 'value' => 2, 'impact' => 'positive'],
                'school_type' => ['importance' => 5.0, 'value' => 0, 'impact' => 'neutral'],
                'family_income' => ['importance' => 5.0, 'value' => 2, 'impact' => 'positive']
            ],
            'interpretation' => $interpretation,
            'model_type' => 'fallback_calculation'
        ];
    }

    public static function getFallbackInterpretation($performance) {
        if ($performance >= 80) {
            return "Excellent potential! Your academic profile shows strong readiness for WAEC/NECO exams.";
        } elseif ($performance >= 65) {
            return "Good performance potential! You're well positioned for success with current preparation.";
        } elseif ($performance >= 50) {
            return "Average potential. Solid foundation with opportunities for improvement in key areas.";
        } else {
            return "Needs improvement. Focus on core subjects and consider additional academic support.";
        }
    }

    public static function validateSubjectData($subjects) {
        $validSubjects = [];
        
        foreach ($subjects as $subject) {
            if (!empty($subject['name']) && !empty($subject['grade']) && 
                $subject['grade'] >= 1 && $subject['grade'] <= 9) {
                $validSubjects[] = $subject;
            }
        }
        
        return count($validSubjects) >= 5; // Minimum 5 subjects required
    }
}
?>