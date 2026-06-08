<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'student';

$subjects = [
    'Mathematics' => 'math',
    'English' => 'english',
    'Physics' => 'physics',
    'Chemistry' => 'chemistry',
    'Biology' => 'biology',
    'Economics' => 'economics',
    'Government' => 'government',
    'Literature' => 'literature',
    'Yoruba' => 'yoruba',
    'Civic Education' => 'civic_education',
];

$grades = ['A1', 'B2', 'B3', 'C4', 'C5', 'C6', 'D7', 'E8', 'F9'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_grades = $_POST['subject_grades'] ?? [];
    $jamb_score = intval($_POST['jamb_score'] ?? 0);
    $study_hours = intval($_POST['study_hours'] ?? 0);
    $parent_education = $_POST['parent_education'] ?? 'secondary';
    $school_type = $_POST['school_type'] ?? 'public';

    if (empty($subject_grades) || $jamb_score < 0) {
        $error = 'Please fill in all required fields.';
    } else {
        $python_path = getenv('PYTHON_PATH', 'python3');
        $script_path = getenv('PREDICTION_SCRIPT_PATH', __DIR__ . '/../python_model/scripts/predict.py');

        $grade_json = json_encode($subject_grades);
        $cmd = sprintf(
            '%s %s --jamb_score %d --study_hours %d --parent_education %s --school_type %s --subject_grades \'%s\' 2>&1',
            escapeshellcmd($python_path),
            escapeshellarg($script_path),
            $jamb_score,
            $study_hours,
            escapeshellarg($parent_education),
            escapeshellarg($school_type),
            addslashes($grade_json)
        );

        $output = shell_exec($cmd);
        $result = json_decode(trim($output), true);

        if ($result && isset($result['predicted_score'])) {
            $predicted_score = $result['predicted_score'];
            $confidence = $result['confidence'] ?? 85;
            $recommended_grade = $result['recommended_grade'] ?? 'B2';
            $weak_subjects = $result['weak_subjects'] ?? [];
            $strong_subjects = $result['strong_subjects'] ?? [];

            try {
                $stmt = $db->prepare("
                    INSERT INTO predictions (user_id, jamb_score, study_hours, parent_education, school_type, subject_grades, predicted_score, confidence, recommended_grade, weak_subjects, strong_subjects)
                    VALUES (:uid, :jamb, :hours, :parent_edu, :school_type, :grades, :score, :conf, :rec_grade, :weak, :strong)
                ");
                $stmt->execute([
                    ':uid' => $user_id,
                    ':jamb' => $jamb_score,
                    ':hours' => $study_hours,
                    ':parent_edu' => $parent_education,
                    ':school_type' => $school_type,
                    ':grades' => json_encode($subject_grades),
                    ':score' => $predicted_score,
                    ':conf' => $confidence,
                    ':rec_grade' => $recommended_grade,
                    ':weak' => json_encode($weak_subjects),
                    ':strong' => json_encode($strong_subjects),
                ]);

                $prediction_id = $db->lastInsertId();

                header("Location: prediction_result.php?id=" . $prediction_id);
                exit;
            } catch (Exception $e) {
                $error = 'Failed to save prediction. Please try again.';
            }
        } else {
            $error = 'Prediction failed. Please check your inputs and try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Prediction — PredictEd</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="index.php" class="sidebar-logo">
            <div class="sidebar-logo-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <span>PredictEd</span>
        </a>
        <button class="sidebar-close" id="sidebarClose"><i class="bi bi-x-lg"></i></button>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="sidebar-link"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
        <a href="prediction_form.php" class="sidebar-link active"><i class="bi bi-magic"></i> New Prediction</a>
        <?php if ($role === 'lecturer' || $role === 'admin'): ?>
        <a href="manage_groups.php" class="sidebar-link"><i class="bi bi-people-fill"></i> Groups</a>
        <?php endif; ?>
        <?php if ($role === 'admin'): ?>
        <a href="manage_users.php" class="sidebar-link"><i class="bi bi-person-gear"></i> Users</a>
        <a href="system_settings.php" class="sidebar-link"><i class="bi bi-sliders"></i> Settings</a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= htmlspecialchars($full_name) ?></div>
                <div class="sidebar-user-role"><?= ucfirst(htmlspecialchars($role)) ?></div>
            </div>
        </div>
        <a href="logout.php" class="sidebar-logout-btn" title="Sign Out"><i class="bi bi-box-arrow-left"></i> Sign Out</a>
    </div>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Main -->
<div class="main-wrapper">
    <header class="topbar">
        <button class="topbar-menu" id="topbarMenu"><i class="bi bi-list"></i></button>
        <div class="topbar-title">New Prediction</div>
    </header>

    <div class="main-content">
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-icon"><i class="bi bi-magic"></i></div>
                <div>
                    <h2>Performance Prediction</h2>
                    <p>Fill in your details below. Our AI will analyze and predict your exam performance.</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="auth-alert auth-alert-error">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="prediction_form.php" id="predictionForm">
                <!-- Academic Info -->
                <div class="form-section">
                    <h3><i class="bi bi-info-circle"></i> Academic Information</h3>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label" for="jamb_score">JAMB Score</label>
                            <div class="form-input-wrap">
                                <input type="number" id="jamb_score" name="jamb_score" class="form-input" placeholder="e.g. 250" min="0" max="400" required value="<?= htmlspecialchars($_POST['jamb_score'] ?? '') ?>">
                                <i class="bi bi-award"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="study_hours">Study Hours/Day</label>
                            <div class="form-input-wrap">
                                <input type="number" id="study_hours" name="study_hours" class="form-input" placeholder="e.g. 4" min="0" max="24" required value="<?= htmlspecialchars($_POST['study_hours'] ?? '') ?>">
                                <i class="bi bi-clock"></i>
                            </div>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label" for="parent_education">Parent Education</label>
                            <div class="form-input-wrap">
                                <select id="parent_education" name="parent_education" class="form-select form-input">
                                    <option value="primary">Primary</option>
                                    <option value="secondary" selected>Secondary</option>
                                    <option value="bachelors">Bachelor's</option>
                                    <option value="masters">Master's</option>
                                    <option value="phd">PhD</option>
                                </select>
                                <i class="bi bi-mortarboard"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="school_type">School Type</label>
                            <div class="form-input-wrap">
                                <select id="school_type" name="school_type" class="form-select form-input">
                                    <option value="public" selected>Public</option>
                                    <option value="private">Private</option>
                                    <option value="federal">Federal</option>
                                </select>
                                <i class="bi bi-building"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subject Grades -->
                <div class="form-section">
                    <h3><i class="bi bi-journal-text"></i> Subject Grades</h3>
                    <div class="grades-grid">
                        <?php foreach ($subjects as $name => $key): ?>
                        <div class="grade-item">
                            <label class="form-label"><?= $name ?></label>
                            <div class="form-input-wrap">
                                <select name="subject_grades[<?= $key ?>]" class="form-select form-input grade-select" data-subject="<?= $key ?>">
                                    <option value="">—</option>
                                    <?php foreach ($grades as $g): ?>
                                    <option value="<?= $g ?>" <?= (($_POST['subject_grades'][$key] ?? '') === $g) ? 'selected' : '' ?>><?= $g ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary btn-full" id="submitBtn">
                        <i class="bi bi-cpu"></i> Run Prediction
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="assets/js/script.js"></script>
<script>
document.getElementById('topbarMenu')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sidebarOverlay').classList.add('open');
});
document.getElementById('sidebarClose')?.addEventListener('click', closeSidebar);
document.getElementById('sidebarOverlay')?.addEventListener('click', closeSidebar);
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
}
document.getElementById('predictionForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat" style="animation:spin 1s linear infinite"></i> Analyzing...';
});
</script>

</body>
</html>
