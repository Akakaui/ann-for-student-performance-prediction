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
$role = $_SESSION['role'] ?? 'student';
$full_name = $_SESSION['full_name'] ?? 'User';

$prediction_count = 0;
$avg_score = 0;
$group_count = 0;

try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM predictions WHERE user_id = :uid");
    $stmt->execute([':uid' => $user_id]);
    $prediction_count = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT AVG(predicted_score) FROM predictions WHERE user_id = :uid");
    $stmt->execute([':uid' => $user_id]);
    $avg_score = $stmt->fetchColumn() ?: 0;

    if ($role === 'lecturer' || $role === 'admin') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM student_groups WHERE created_by = :uid");
        $stmt->execute([':uid' => $user_id]);
        $group_count = $stmt->fetchColumn();
    }
} catch (Exception $e) {
    // silent
}

$recent_predictions = [];
try {
    $stmt = $db->prepare("SELECT * FROM predictions WHERE user_id = :uid ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([':uid' => $user_id]);
    $recent_predictions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // silent
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — PredictEd</title>
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
        <a href="dashboard.php" class="sidebar-link active">
            <i class="bi bi-grid-1x2-fill"></i> Dashboard
        </a>
        <a href="prediction_form.php" class="sidebar-link">
            <i class="bi bi-magic"></i> New Prediction
        </a>
        <?php if ($role === 'lecturer' || $role === 'admin'): ?>
        <a href="manage_groups.php" class="sidebar-link">
            <i class="bi bi-people-fill"></i> Groups
        </a>
        <?php endif; ?>
        <?php if ($role === 'admin'): ?>
        <a href="manage_users.php" class="sidebar-link">
            <i class="bi bi-person-gear"></i> Users
        </a>
        <a href="system_settings.php" class="sidebar-link">
            <i class="bi bi-sliders"></i> Settings
        </a>
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
        <a href="logout.php" class="sidebar-logout"><i class="bi bi-box-arrow-left"></i></a>
    </div>
</aside>

<!-- Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Main -->
<div class="main-wrapper">
    <!-- Top bar -->
    <header class="topbar">
        <button class="topbar-menu" id="topbarMenu"><i class="bi bi-list"></i></button>
        <div class="topbar-title">Dashboard</div>
        <div class="topbar-actions">
            <a href="prediction_form.php" class="topbar-btn">
                <i class="bi bi-plus-lg"></i> New Prediction
            </a>
        </div>
    </header>

    <!-- Content -->
    <div class="main-content">
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card stat-card-orange">
                <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="stat-value"><?= $prediction_count ?></div>
                <div class="stat-label">Total Predictions</div>
            </div>
            <div class="stat-card stat-card-teal">
                <div class="stat-icon"><i class="bi bi-bullseye"></i></div>
                <div class="stat-value"><?= number_format($avg_score, 1) ?>%</div>
                <div class="stat-label">Average Score</div>
            </div>
            <?php if ($role === 'lecturer' || $role === 'admin'): ?>
            <div class="stat-card stat-card-violet">
                <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                <div class="stat-value"><?= $group_count ?></div>
                <div class="stat-label">Groups</div>
            </div>
            <?php endif; ?>
            <div class="stat-card stat-card-rose">
                <div class="stat-icon"><i class="bi bi-trophy-fill"></i></div>
                <div class="stat-value"><?= $prediction_count > 0 ? 'A' : '—' ?></div>
                <div class="stat-label">Top Grade</div>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="section-header">
            <h2>Quick Actions</h2>
        </div>
        <div class="actions-grid">
            <a href="prediction_form.php" class="action-card">
                <div class="action-icon action-icon-orange"><i class="bi bi-magic"></i></div>
                <h3>New Prediction</h3>
                <p>Get an AI-powered forecast of your academic performance.</p>
            </a>
            <a href="view_history.php" class="action-card">
                <div class="action-icon action-icon-teal"><i class="bi bi-clock-history"></i></div>
                <h3>View History</h3>
                <p>Review your past predictions and track improvement.</p>
            </a>
            <a href="view_recommendations.php" class="action-card">
                <div class="action-icon action-icon-violet"><i class="bi bi-lightbulb-fill"></i></div>
                <h3>Recommendations</h3>
                <p>Get personalized study tips and improvement plans.</p>
            </a>
        </div>

        <!-- Recent predictions -->
        <?php if (!empty($recent_predictions)): ?>
        <div class="section-header">
            <h2>Recent Predictions</h2>
            <a href="view_history.php" class="section-link">View All <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="table-card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Predicted Score</th>
                            <th>Confidence</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_predictions as $pred): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($pred['created_at'])) ?></td>
                            <td><strong><?= number_format($pred['predicted_score'], 1) ?>%</strong></td>
                            <td><?= number_format($pred['confidence'] ?? 85, 0) ?>%</td>
                            <td><span class="badge badge-success">Complete</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="bi bi-inbox"></i></div>
            <h3>No predictions yet</h3>
            <p>Start by creating your first prediction to see your results here.</p>
            <a href="prediction_form.php" class="btn-primary">
                <i class="bi bi-plus-lg"></i> Create First Prediction
            </a>
        </div>
        <?php endif; ?>
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
</script>

</body>
</html>
