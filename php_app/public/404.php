<?php require_once '../lib/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; }
        .error-card { border: none; border-radius: 15px; background: rgba(255,255,255,0.95); }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="error-card card shadow text-center">
                    <div class="card-body p-5">
                        <i class="bi bi-question-circle display-1 text-warning"></i>
                        <h2 class="mt-3">Page Not Found</h2>
                        <p class="text-muted">The page you're looking for doesn't exist or has been moved.</p>
                        <a href="login.php" class="btn btn-primary mt-2">
                            <i class="bi bi-house"></i> Go to Login
                        </a>
                        <br>
                        <a href="dashboard.php" class="btn btn-outline-secondary mt-2">
                            <i class="bi bi-speedometer2"></i> Go to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
