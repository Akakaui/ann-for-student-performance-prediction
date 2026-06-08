<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';
require_once '../lib/recommendations.php';

session_start();
Auth::requireAuth();

if ($_SESSION['role'] !== 'student') {
    Utils::redirect('dashboard.php');
}

$db = Database::getInstance();
$recommendationsEngine = new Recommendations($db);

$prediction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$prediction = $db->fetchOne(
    "SELECT * FROM predictions WHERE id = ? AND user_id = ?",
    [$prediction_id, $_SESSION['user_id']]
);

if (!$prediction) {
    Utils::redirect('dashboard.php');
}

$db->query("UPDATE predictions SET recommendations_viewed = TRUE WHERE id = ?", [$prediction_id]);

$saved_recommendations = $recommendationsEngine->getSavedRecommendations($prediction_id);
if (!$saved_recommendations) {
    $saved_recommendations = $recommendationsEngine->generateRecommendations($prediction_id, $_SESSION['user_id']);
}

$subject_count = 0;
$total_score = 0;
$subjects = [];

for ($i = 1; $i <= 9; $i++) {
    if (!empty($prediction["subject{$i}_name"]) && !empty($prediction["subject{$i}_grade"])) {
        $subject_count++;
        $total_score += $prediction["subject{$i}_grade"];
        $subjects[] = [
            'name' => $prediction["subject{$i}_name"],
            'score' => $prediction["subject{$i}_grade"],
            'grade' => convertToGrade($prediction["subject{$i}_grade"])
        ];
    }
}

$average_score = $subject_count > 0 ? round($total_score / $subject_count, 2) : 0;
$predicted = $prediction['predicted_performance'] ?? $prediction['predicted_score'] ?? 0;
$confidence = $prediction['confidence_level'] ?? $prediction['confidence'] ?? 85;

function convertToGrade($grade) {
    if ($grade <= 1) return 'A1';
    if ($grade <= 2) return 'B2';
    if ($grade <= 3) return 'B3';
    if ($grade <= 4) return 'C4';
    if ($grade <= 5) return 'C5';
    if ($grade <= 6) return 'C6';
    if ($grade <= 7) return 'D7';
    if ($grade <= 8) return 'E8';
    return 'F9';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prediction Results — PredictEd</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php $full_name = $_SESSION['full_name'] ?? 'User'; $role = $_SESSION['role'] ?? 'student'; ?>
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
        <a href="prediction_form.php" class="sidebar-link"><i class="bi bi-magic"></i> New Prediction</a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= htmlspecialchars($full_name) ?></div>
                <div class="sidebar-user-role">Student</div>
            </div>
        </div>
        <a href="logout.php" class="sidebar-logout-btn" title="Sign Out"><i class="bi bi-box-arrow-left"></i> Sign Out</a>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="main-wrapper">
    <header class="topbar">
        <button class="topbar-menu" id="topbarMenu"><i class="bi bi-list"></i></button>
        <div class="topbar-title">Prediction Results</div>
        <div class="topbar-actions">
            <a href="prediction_form.php" class="topbar-btn"><i class="bi bi-plus-lg"></i> New</a>
        </div>
    </header>

    <div class="main-content">
        <!-- Score -->
        <div class="result-card">
            <div class="result-score">
                <div class="result-score-circle">
                    <span class="result-score-value"><?= number_format($predicted, 0) ?>%</span>
                </div>
                <div class="result-score-label">Predicted Performance</div>
            </div>
            <div class="stats-grid" style="margin-top:1.5rem; margin-bottom:0">
                <div class="stat-card stat-card-orange">
                    <div class="stat-icon"><i class="bi bi-bullseye"></i></div>
                    <div class="stat-value"><?= number_format($confidence, 0) ?>%</div>
                    <div class="stat-label">Confidence</div>
                </div>
                <div class="stat-card stat-card-teal">
                    <div class="stat-icon"><i class="bi bi-journal-text"></i></div>
                    <div class="stat-value"><?= $subject_count ?></div>
                    <div class="stat-label">Subjects</div>
                </div>
                <div class="stat-card stat-card-violet">
                    <div class="stat-icon"><i class="bi bi-graph-up"></i></div>
                    <div class="stat-value"><?= number_format($average_score, 1) ?></div>
                    <div class="stat-label">Avg Score</div>
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem">
            <!-- Subjects -->
            <div class="result-card">
                <div class="result-header">
                    <div class="result-icon" style="background:rgba(249,115,22,0.1);color:var(--accent)"><i class="bi bi-journal-text"></i></div>
                    <div>
                        <h3 style="font-family:'Space Grotesk',sans-serif;font-size:1.1rem;font-weight:700">Subject Performance</h3>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Subject</th><th>Score</th><th>Grade</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['name']) ?></td>
                                <td><strong><?= $s['score'] ?>%</strong></td>
                                <td><span class="badge badge-success"><?= $s['grade'] ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recommendations -->
            <div class="result-card">
                <div class="result-header">
                    <div class="result-icon" style="background:rgba(20,184,166,0.1);color:var(--teal)"><i class="bi bi-lightbulb-fill"></i></div>
                    <div>
                        <h3 style="font-family:'Space Grotesk',sans-serif;font-size:1.1rem;font-weight:700">Recommendations</h3>
                    </div>
                </div>
                <?php if (!empty($saved_recommendations['general'])): ?>
                    <?php foreach (array_slice($saved_recommendations['general'], 0, 4) as $rec): ?>
                    <div style="padding:0.75rem;background:var(--glass);border:1px solid var(--border);border-radius:10px;margin-bottom:0.75rem">
                        <div style="font-weight:600;font-size:0.9rem;margin-bottom:0.25rem"><?= htmlspecialchars($rec['title']) ?></div>
                        <div style="font-size:0.8rem;color:var(--text-muted);line-height:1.6"><?= htmlspecialchars($rec['message']) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:var(--text-muted);font-size:0.9rem">No specific recommendations generated yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Actions -->
        <div class="form-actions" style="margin-top:0">
            <a href="dashboard.php" class="btn-secondary"><i class="bi bi-arrow-left"></i> Dashboard</a>
            <a href="prediction_form.php" class="btn-primary"><i class="bi bi-plus-lg"></i> New Prediction</a>
        </div>
    </div>
</div>

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
</script>

</body>
</html>
