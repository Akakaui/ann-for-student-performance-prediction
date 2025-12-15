<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';

session_start();
Auth::requireAuth();

// Only students can make individual predictions
if ($_SESSION['role'] !== 'student') {
    $_SESSION['error'] = "Only students can make individual predictions.";
    Utils::redirect('dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_type = Utils::sanitize($_POST['exam_type'] ?? '');
    $current_cgpa = floatval($_POST['current_cgpa'] ?? 0);
    $subjects = $_POST['subjects'] ?? [];
    $parent_education_level = Utils::sanitize($_POST['parent_education_level'] ?? '');
    $study_hours_per_week = intval($_POST['study_hours_per_week'] ?? 0);
    $access_to_learning_materials = Utils::sanitize($_POST['access_to_learning_materials'] ?? '');
    $school_type = Utils::sanitize($_POST['school_type'] ?? '');
    $family_income_level = Utils::sanitize($_POST['family_income_level'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';

    try {
        if (!Utils::verifyCSRFToken($csrf_token)) {
            throw new Exception("Invalid CSRF token");
        }

        // Validate exam type
        if (!in_array($exam_type, ['WAEC', 'NECO'])) {
            throw new Exception("Please select a valid exam type");
        }

        // Validate CGPA
        if ($current_cgpa < 0 || $current_cgpa > 5.0) {
            throw new Exception("Please enter a valid CGPA (0.0 - 5.0)");
        }

        // Validate subjects (minimum 5 subjects required)
        if (!Utils::validateSubjectData($subjects)) {
            throw new Exception("Please provide at least 5 subjects with valid grades");
        }

        // Validate socio-economic factors
        if (empty($parent_education_level) || empty($access_to_learning_materials) || 
            empty($school_type) || empty($family_income_level)) {
            throw new Exception("Please fill in all socio-economic factors");
        }

        if ($study_hours_per_week < 1 || $study_hours_per_week > 168) {
            throw new Exception("Please enter valid study hours per week (1-168)");
        }

        // Prepare data for Python model
        $prediction_data = [
            'exam_type' => $exam_type,
            'current_cgpa' => $current_cgpa,
            'subjects' => $subjects,
            'parent_education_level' => $parent_education_level,
            'study_hours_per_week' => $study_hours_per_week,
            'access_to_learning_materials' => $access_to_learning_materials,
            'school_type' => $school_type,
            'family_income_level' => $family_income_level
        ];

        // Call Python model for prediction with timeout
        $prediction_result = Utils::callPythonModel($prediction_data);

        if (!$prediction_result['success']) {
            throw new Exception("Prediction failed: " . ($prediction_result['error'] ?? 'Unknown error'));
        }

        // Save prediction to database
        $db = Database::getInstance();
        
        // Prepare subject data for database
        $subject_fields = [];
        $subject_values = [];
        for ($i = 0; $i < 9; $i++) {
            $subject_fields[] = "subject" . ($i + 1) . "_name";
            $subject_fields[] = "subject" . ($i + 1) . "_grade";
            
            if (isset($subjects[$i])) {
                $subject_values[] = $subjects[$i]['name'] ?? null;
                $subject_values[] = $subjects[$i]['grade'] ?? null;
            } else {
                $subject_values[] = null;
                $subject_values[] = null;
            }
        }

        $subject_fields_sql = implode(', ', $subject_fields);
        $subject_placeholders = implode(', ', array_fill(0, count($subject_values), '?'));

        $sql = "INSERT INTO predictions (
                    user_id, exam_type, current_cgpa, $subject_fields_sql,
                    parent_education_level, study_hours_per_week, 
                    access_to_learning_materials, school_type, family_income_level,
                    predicted_performance, confidence_level, feature_contributions
                ) VALUES (
                    ?, ?, ?, $subject_placeholders, ?, ?, ?, ?, ?, ?, ?, ?
                )";

        $params = array_merge(
            [$_SESSION['user_id'], $exam_type, $current_cgpa],
            $subject_values,
            [
                $parent_education_level,
                $study_hours_per_week,
                $access_to_learning_materials,
                $school_type,
                $family_income_level,
                $prediction_result['predicted_performance'],
                $prediction_result['confidence_level'],
                json_encode($prediction_result['feature_contributions'])
            ]
        );

        $db->query($sql, $params);
        $prediction_id = $db->lastInsertId();

        // Store prediction result in session for result page
        $_SESSION['last_prediction'] = [
            'id' => $prediction_id,
            'exam_type' => $exam_type,
            'current_cgpa' => $current_cgpa,
            'predicted_performance' => $prediction_result['predicted_performance'],
            'confidence_level' => $prediction_result['confidence_level'],
            'feature_contributions' => $prediction_result['feature_contributions'],
            'interpretation' => $prediction_result['interpretation'] ?? '',
            'subjects' => $subjects
        ];

        Utils::redirect('prediction_result.php');

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$csrf_token = Utils::generateCSRFToken();

// Common Nigerian subjects for pre-filling
$common_subjects = [
    'Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology',
    'Further Mathematics', 'Geography', 'Economics', 'Government',
    'Literature in English', 'Christian Religious Studies', 'Islamic Religious Studies',
    'History', 'Agricultural Science', 'Technical Drawing', 'Food and Nutrition',
    'Commerce', 'Accounting', 'Yoruba', 'Igbo', 'Hausa', 'French'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Prediction - Student Performance Predictor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
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
                        <a class="nav-link active" href="prediction_form.php">WAEC/NECO Prediction</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_predictions.php">My Predictions</a>
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
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="card-title mb-0">
                            <i class="bi bi-calculator"></i> WAEC/NECO Performance Prediction
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="" onsubmit="return handlePredictionFormSubmit(this)">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                            <!-- Exam Type Selection -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="exam_type" class="form-label fw-bold">Exam Type *</label>
                                    <select class="form-select" id="exam_type" name="exam_type" required>
                                        <option value="">Select Exam Type</option>
                                        <option value="WAEC" <?php echo ($_POST['exam_type'] ?? '') === 'WAEC' ? 'selected' : ''; ?>>WAEC (West African Examination Council)</option>
                                        <option value="NECO" <?php echo ($_POST['exam_type'] ?? '') === 'NECO' ? 'selected' : ''; ?>>NECO (National Examination Council)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="current_cgpa" class="form-label fw-bold">Current CGPA (0.0 - 5.0) *</label>
                                    <input type="number" class="form-control" id="current_cgpa" name="current_cgpa" 
                                           step="0.1" min="0" max="5.0" 
                                           value="<?php echo $_POST['current_cgpa'] ?? ''; ?>" required>
                                    <div class="form-text">Enter your current cumulative grade point average</div>
                                </div>
                            </div>

                            <!-- Subjects Section -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="fw-bold">Subject Grades (WAEC/NECO Grading System)</h5>
                                    <div>
                                        <button type="button" class="btn btn-success btn-sm" onclick="addSubject()">
                                            <i class="bi bi-plus-circle"></i> Add Subject
                                        </button>
                                        <button type="button" class="btn btn-info btn-sm" onclick="prefillCommonSubjects()">
                                            <i class="bi bi-magic"></i> Prefill Common Subjects
                                        </button>
                                    </div>
                                </div>
                                <p class="text-muted">Enter at least 5 subjects. Use the WAEC/NECO grading system: A1=1, B2=2, B3=3, C4=4, C5=5, C6=6, D7=7, E8=8, F9=9</p>
                                
                                <div id="subjectContainer">
                                    <!-- Subject rows will be added here by JavaScript -->
                                </div>
                            </div>

                            <!-- Socio-economic Factors -->
                            <div class="mb-4">
                                <h5 class="fw-bold mb-3">Academic & Socio-Economic Factors</h5>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="parent_education_level" class="form-label">Parent Education Level *</label>
                                        <select class="form-select" id="parent_education_level" name="parent_education_level" required>
                                            <option value="">Select Education Level</option>
                                            <option value="none" <?php echo ($_POST['parent_education_level'] ?? '') === 'none' ? 'selected' : ''; ?>>No Formal Education</option>
                                            <option value="primary" <?php echo ($_POST['parent_education_level'] ?? '') === 'primary' ? 'selected' : ''; ?>>Primary Education</option>
                                            <option value="secondary" <?php echo ($_POST['parent_education_level'] ?? '') === 'secondary' ? 'selected' : ''; ?>>Secondary Education</option>
                                            <option value="tertiary" <?php echo ($_POST['parent_education_level'] ?? '') === 'tertiary' ? 'selected' : ''; ?>>Tertiary Education</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="study_hours_per_week" class="form-label">Study Hours Per Week *</label>
                                        <input type="number" class="form-control" id="study_hours_per_week" 
                                               name="study_hours_per_week" min="1" max="168" 
                                               value="<?php echo $_POST['study_hours_per_week'] ?? '15'; ?>" required>
                                        <div class="form-text">Enter average hours spent studying per week</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="access_to_learning_materials" class="form-label">Access to Learning Materials *</label>
                                        <select class="form-select" id="access_to_learning_materials" name="access_to_learning_materials" required>
                                            <option value="">Select Access Level</option>
                                            <option value="poor" <?php echo ($_POST['access_to_learning_materials'] ?? '') === 'poor' ? 'selected' : ''; ?>>Poor (Limited resources)</option>
                                            <option value="average" <?php echo ($_POST['access_to_learning_materials'] ?? '') === 'average' ? 'selected' : ''; ?>>Average (Basic resources)</option>
                                            <option value="good" <?php echo ($_POST['access_to_learning_materials'] ?? '') === 'good' ? 'selected' : ''; ?>>Good (Adequate resources)</option>
                                            <option value="excellent" <?php echo ($_POST['access_to_learning_materials'] ?? '') === 'excellent' ? 'selected' : ''; ?>>Excellent (Comprehensive resources)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="school_type" class="form-label">School Type *</label>
                                        <select class="form-select" id="school_type" name="school_type" required>
                                            <option value="">Select School Type</option>
                                            <option value="public" <?php echo ($_POST['school_type'] ?? '') === 'public' ? 'selected' : ''; ?>>Public School</option>
                                            <option value="private" <?php echo ($_POST['school_type'] ?? '') === 'private' ? 'selected' : ''; ?>>Private School</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="family_income_level" class="form-label">Family Income Level *</label>
                                        <select class="form-select" id="family_income_level" name="family_income_level" required>
                                            <option value="">Select Income Level</option>
                                            <option value="low" <?php echo ($_POST['family_income_level'] ?? '') === 'low' ? 'selected' : ''; ?>>Low Income</option>
                                            <option value="middle" <?php echo ($_POST['family_income_level'] ?? '') === 'middle' ? 'selected' : ''; ?>>Middle Income</option>
                                            <option value="high" <?php echo ($_POST['family_income_level'] ?? '') === 'high' ? 'selected' : ''; ?>>High Income</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-calculator"></i> Generate WAEC/NECO Prediction
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    
    <script>
        // Common subjects for pre-filling
        const commonSubjects = <?php echo json_encode($common_subjects); ?>;
        
        // Initialize with 1 empty subject row
        document.addEventListener('DOMContentLoaded', function() {
            addSubject();
        });

        // Prefill common subjects function
        function prefillCommonSubjects() {
            const subjectContainer = document.getElementById('subjectContainer');
            subjectContainer.innerHTML = '';
            
            // Add 8 common subjects
            const commonToAdd = commonSubjects.slice(0, 8);
            commonToAdd.forEach((subject, index) => {
                addSubjectWithName(subject);
            });
            
            // Add one empty row for additional subjects
            addSubject();
        }

        function addSubjectWithName(subjectName) {
            const subjectContainer = document.getElementById('subjectContainer');
            const subjectCount = subjectContainer.querySelectorAll('.subject-row').length;
            
            if (subjectCount >= 9) {
                alert('Maximum of 9 subjects allowed');
                return;
            }
            
            const newSubjectRow = document.createElement('div');
            newSubjectRow.className = 'subject-row';
            newSubjectRow.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Subject Name</label>
                        <input type="text" class="form-control" name="subjects[${subjectCount}][name]" value="${subjectName}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Grade</label>
                        <div class="input-group">
                            <select class="form-select" name="subjects[${subjectCount}][grade]" required>
                                <option value="">Select Grade</option>
                                <option value="1">A1 (Excellent)</option>
                                <option value="2">B2 (Very Good)</option>
                                <option value="3">B3 (Good)</option>
                                <option value="4">C4 (Credit)</option>
                                <option value="5">C5 (Credit)</option>
                                <option value="6">C6 (Credit)</option>
                                <option value="7">D7 (Pass)</option>
                                <option value="8">E8 (Pass)</option>
                                <option value="9">F9 (Fail)</option>
                            </select>
                            <div class="input-group-text p-0">
                                <div class="btn-group btn-group-sm" role="group">
                                    <span class="grade-badge btn btn-outline-secondary" data-grade="1">A1</span>
                                    <span class="grade-badge btn btn-outline-secondary" data-grade="2">B2</span>
                                    <span class="grade-badge btn btn-outline-secondary" data-grade="3">B3</span>
                                    <span class="grade-badge btn btn-outline-secondary" data-grade="4">C4</span>
                                    <span class="grade-badge btn btn-outline-secondary" data-grade="5">C5</span>
                                    <span class="grade-badge btn btn-outline-secondary" data-grade="6">C6</span>
                                    <span class="grade-badge btn btn-outline-secondary" data-grade="7">D7</span>
                                    <span class="grade-badge btn btn-outline-secondary" data-grade="8">E8</span>
                                    <span class="grade-badge btn btn-outline-secondary" data-grade="9">F9</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-danger remove-subject" onclick="removeSubject(this)">
                        <i class="bi bi-trash"></i> Remove Subject
                    </button>
                </div>
            `;
            
            subjectContainer.appendChild(newSubjectRow);
            initializeGradeSelection(); // Re-initialize for new badges
        }
    </script>
</body>
</html>