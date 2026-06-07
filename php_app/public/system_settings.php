<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';

session_start();
Auth::requireAuth();

// Only admins can access system settings
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access denied. This page is for administrators only.";
    Utils::redirect('dashboard.php');
}

$db = Database::getInstance();

// Get current settings
$settings = $db->fetchAll("SELECT * FROM system_settings");
$settings_map = [];
foreach ($settings as $setting) {
    $settings_map[$setting['setting_key']] = $setting;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updated = 0;
    
    foreach ($_POST['settings'] as $key => $value) {
        if (isset($settings_map[$key])) {
            $db->query(
                "UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?",
                [$value, $key]
            );
            $updated++;
        } else {
            $db->query(
                "INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)",
                [$key, $value, 'Custom setting']
            );
            $updated++;
        }
    }
    
    if ($updated > 0) {
        $_SESSION['success'] = "System settings updated successfully!";
        $db->query(
            "INSERT INTO audit_logs (user_id, action_type, description, ip_address) VALUES (?, ?, ?, ?)",
            [$_SESSION['user_id'], 'settings_update', 'Updated system settings', $_SERVER['REMOTE_ADDR']]
        );
    }
    
    Utils::redirect('system_settings.php');
}

// Get system information
$system_info = [
    'php_version' => PHP_VERSION,
    'postgres_version' => $db->fetchOne("SELECT version() as version")['version'] ?? 'Unknown',
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'max_upload_size' => ini_get('upload_max_filesize'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit')
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Student Performance Predictor</title>
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
                        <a class="nav-link" href="admin_dashboard.php">Admin Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_analytics.php">System Analytics</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="system_settings.php">System Settings</a>
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
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="fw-bold">System Settings</h1>
                        <p class="text-muted mb-0">Configure platform settings and preferences</p>
                    </div>
                    <div class="btn-group">
                        <a href="admin_analytics.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Analytics
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <div class="row">
            <!-- System Settings Form -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-gear"></i> Platform Configuration
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="system_version" class="form-label">System Version</label>
                                    <input type="text" class="form-control" id="system_version" name="settings[system_version]" 
                                           value="<?php echo htmlspecialchars($settings_map['system_version']['setting_value'] ?? '1.0'); ?>">
                                    <div class="form-text">Current system version number</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="prediction_model_version" class="form-label">AI Model Version</label>
                                    <input type="text" class="form-control" id="prediction_model_version" name="settings[prediction_model_version]" 
                                           value="<?php echo htmlspecialchars($settings_map['prediction_model_version']['setting_value'] ?? '1.0'); ?>">
                                    <div class="form-text">Prediction model version</div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="min_subjects_required" class="form-label">Minimum Subjects Required</label>
                                    <input type="number" class="form-control" id="min_subjects_required" name="settings[min_subjects_required]" 
                                           value="<?php echo htmlspecialchars($settings_map['min_subjects_required']['setting_value'] ?? '5'); ?>" min="1" max="9">
                                    <div class="form-text">Minimum number of subjects required for prediction</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="max_subjects_allowed" class="form-label">Maximum Subjects Allowed</label>
                                    <input type="number" class="form-control" id="max_subjects_allowed" name="settings[max_subjects_allowed]" 
                                           value="<?php echo htmlspecialchars($settings_map['max_subjects_allowed']['setting_value'] ?? '9'); ?>" min="1" max="9">
                                    <div class="form-text">Maximum number of subjects allowed for prediction</div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="default_study_hours" class="form-label">Default Study Hours</label>
                                    <input type="number" class="form-control" id="default_study_hours" name="settings[default_study_hours]" 
                                           value="<?php echo htmlspecialchars($settings_map['default_study_hours']['setting_value'] ?? '15'); ?>" min="1" max="40">
                                    <div class="form-text">Default study hours per week</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                                    <input type="number" class="form-control" id="session_timeout" name="settings[session_timeout]" 
                                           value="<?php echo htmlspecialchars($settings_map['session_timeout']['setting_value'] ?? '30'); ?>" min="5" max="1440">
                                    <div class="form-text">User session timeout in minutes</div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="enable_registration" class="form-label">User Registration</label>
                                    <select class="form-select" id="enable_registration" name="settings[enable_registration]">
                                        <option value="enabled" <?php echo ($settings_map['enable_registration']['setting_value'] ?? 'enabled') === 'enabled' ? 'selected' : ''; ?>>Enabled</option>
                                        <option value="disabled" <?php echo ($settings_map['enable_registration']['setting_value'] ?? 'enabled') === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                                    </select>
                                    <div class="form-text">Allow new user registrations</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="maintenance_mode" class="form-label">Maintenance Mode</label>
                                    <select class="form-select" id="maintenance_mode" name="settings[maintenance_mode]">
                                        <option value="disabled" <?php echo ($settings_map['maintenance_mode']['setting_value'] ?? 'disabled') === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                                        <option value="enabled" <?php echo ($settings_map['maintenance_mode']['setting_value'] ?? 'disabled') === 'enabled' ? 'selected' : ''; ?>>Enabled</option>
                                    </select>
                                    <div class="form-text">Put system in maintenance mode</div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-12">
                                    <label for="system_message" class="form-label">System Message</label>
                                    <textarea class="form-control" id="system_message" name="settings[system_message]" rows="3" 
                                              placeholder="Global message displayed to all users"><?php echo htmlspecialchars($settings_map['system_message']['setting_value'] ?? ''); ?></textarea>
                                    <div class="form-text">Optional message displayed to all users</div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="reset" class="btn btn-outline-secondary">Reset</button>
                                <button type="submit" class="btn btn-primary">Save Settings</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- System Information -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-info-circle"></i> System Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>PHP Version:</strong><br>
                            <span class="text-muted"><?php echo $system_info['php_version']; ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>PostgreSQL Version:</strong><br>
                            <span class="text-muted"><?php echo $system_info['postgres_version']; ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Server Software:</strong><br>
                            <span class="text-muted"><?php echo $system_info['server_software']; ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Max Upload Size:</strong><br>
                            <span class="text-muted"><?php echo $system_info['max_upload_size']; ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Max Execution Time:</strong><br>
                            <span class="text-muted"><?php echo $system_info['max_execution_time']; ?> seconds</span>
                        </div>
                        <div class="mb-3">
                            <strong>Memory Limit:</strong><br>
                            <span class="text-muted"><?php echo $system_info['memory_limit']; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="admin_analytics.php" class="btn btn-outline-primary">
                                <i class="bi bi-graph-up"></i> View Analytics
                            </a>
                            <a href="export_analytics.php" class="btn btn-outline-success">
                                <i class="bi bi-download"></i> Export Report
                            </a>
                            <button class="btn btn-outline-warning" onclick="clearCache()">
                                <i class="bi bi-arrow-clockwise"></i> Clear Cache
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function clearCache() {
            if (confirm('Are you sure you want to clear the system cache?')) {
                fetch('clear_cache.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Cache cleared successfully!');
                        } else {
                            alert('Error clearing cache: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('Error clearing cache: ' + error);
                    });
            }
        }
    </script>
</body>
</html>