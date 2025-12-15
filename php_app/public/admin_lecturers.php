<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';

session_start();
Auth::requireRole(['admin']);

$db = Database::getInstance();
$error = '';
$success = '';

// Handle lecturer verification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $lecturer_id = intval($_POST['lecturer_id'] ?? 0);
    $institution = Utils::sanitize($_POST['institution'] ?? '');
    $department = Utils::sanitize($_POST['department'] ?? '');
    $staff_id = Utils::sanitize($_POST['staff_id'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';

    try {
        if (!Utils::verifyCSRFToken($csrf_token)) {
            throw new Exception("Invalid CSRF token");
        }

        if ($action === 'approve_lecturer' && $lecturer_id > 0) {
            // Update lecturer verification status
            $db->query(
                "UPDATE lecturers SET 
                 verification_status = 'approved',
                 institution = ?,
                 department = ?,
                 staff_id = ?,
                 verified_by = ?,
                 verified_at = CURRENT_TIMESTAMP 
                 WHERE user_id = ?",
                [$institution, $department, $staff_id, $_SESSION['user_id'], $lecturer_id]
            );

            // Also verify the user account
            $db->query("UPDATE users SET is_verified = 1 WHERE id = ?", [$lecturer_id]);

            $success = "Lecturer approved successfully";

        } elseif ($action === 'reject_lecturer' && $lecturer_id > 0) {
            $db->query(
                "UPDATE lecturers SET verification_status = 'rejected' WHERE user_id = ?",
                [$lecturer_id]
            );
            $success = "Lecturer rejected successfully";

        } elseif ($action === 'update_lecturer' && $lecturer_id > 0) {
            $db->query(
                "UPDATE lecturers SET institution = ?, department = ?, staff_id = ? WHERE user_id = ?",
                [$institution, $department, $staff_id, $lecturer_id]
            );
            $success = "Lecturer information updated successfully";

        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all lecturers with their details
$lecturers = $db->fetchAll("
    SELECT u.id, u.username, u.email, u.created_at, 
           l.institution, l.department, l.staff_id, l.verification_status,
           l.verified_at, verifier.username as verified_by_name
    FROM users u 
    JOIN lecturers l ON u.id = l.user_id 
    LEFT JOIN users verifier ON l.verified_by = verifier.id 
    ORDER BY 
        CASE l.verification_status 
            WHEN 'pending' THEN 1 
            WHEN 'approved' THEN 2 
            ELSE 3 
        END,
        u.created_at DESC
");

// Get verification statistics
$verification_stats = $db->fetchOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN verification_status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM lecturers
");

$csrf_token = Utils::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Verification - Student Performance Predictor</title>
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
                        <a class="nav-link" href="admin_users.php">User Management</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_lecturers.php">Lecturer Verification</a>
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
                    <h2 class="fw-bold">Lecturer Verification</h2>
                </div>
                <p class="text-muted">Review and verify lecturer accounts for system access.</p>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Verification Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $verification_stats['total']; ?></h4>
                                <p class="card-text">Total Lecturers</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-people display-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $verification_stats['pending']; ?></h4>
                                <p class="card-text">Pending</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-clock display-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $verification_stats['approved']; ?></h4>
                                <p class="card-text">Approved</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-check-circle display-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $verification_stats['rejected']; ?></h4>
                                <p class="card-text">Rejected</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-x-circle display-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lecturers Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Lecturer Accounts</h5>
            </div>
            <div class="card-body">
                <?php if (empty($lecturers)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-person-badge display-1 text-muted"></i>
                        <h4 class="text-muted mt-3">No Lecturer Accounts</h4>
                        <p class="text-muted">There are no lecturer accounts requiring verification.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Lecturer</th>
                                    <th>Institution</th>
                                    <th>Department</th>
                                    <th>Staff ID</th>
                                    <th>Status</th>
                                    <th>Registration Date</th>
                                    <th>Verified By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lecturers as $lecturer): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $lecturer['username']; ?></strong><br>
                                            <small class="text-muted"><?php echo $lecturer['email']; ?></small>
                                        </td>
                                        <td><?php echo $lecturer['institution'] ?: 'Not specified'; ?></td>
                                        <td><?php echo $lecturer['department'] ?: 'Not specified'; ?></td>
                                        <td><?php echo $lecturer['staff_id'] ?: 'Not provided'; ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $lecturer['verification_status'] === 'approved' ? 'success' : 
                                                     ($lecturer['verification_status'] === 'pending' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($lecturer['verification_status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo Utils::formatDate($lecturer['created_at'], 'M j, Y'); ?></td>
                                        <td>
                                            <?php if ($lecturer['verified_by_name']): ?>
                                                <?php echo $lecturer['verified_by_name']; ?><br>
                                                <small class="text-muted"><?php echo Utils::formatDate($lecturer['verified_at'], 'M j, Y'); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Not verified</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($lecturer['verification_status'] === 'pending'): ?>
                                                    <!-- Approve Button with Modal -->
                                                    <button type="button" class="btn btn-success btn-sm" 
                                                            data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $lecturer['id']; ?>">
                                                        <i class="bi bi-check-circle"></i> Approve
                                                    </button>

                                                    <!-- Reject Button -->
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to reject this lecturer?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="lecturer_id" value="<?php echo $lecturer['id']; ?>">
                                                        <input type="hidden" name="action" value="reject_lecturer">
                                                        <button type="submit" class="btn btn-danger btn-sm">
                                                            <i class="bi bi-x-circle"></i> Reject
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <!-- Edit Button with Modal -->
                                                    <button type="button" class="btn btn-outline-primary btn-sm" 
                                                            data-bs-toggle="modal" data-bs-target="#editModal<?php echo $lecturer['id']; ?>">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </button>

                                                    <?php if ($lecturer['verification_status'] === 'approved'): ?>
                                                        <span class="badge bg-success ms-1">Verified</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger ms-1">Rejected</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Approve Modal -->
                                            <div class="modal fade" id="approveModal<?php echo $lecturer['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Approve Lecturer</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <div class="modal-body">
                                                                <p>Approve lecturer <strong><?php echo $lecturer['username']; ?></strong>?</p>
                                                                
                                                                <div class="mb-3">
                                                                    <label for="institution<?php echo $lecturer['id']; ?>" class="form-label">Institution *</label>
                                                                    <input type="text" class="form-control" id="institution<?php echo $lecturer['id']; ?>" 
                                                                           name="institution" value="<?php echo $lecturer['institution'] ?: ''; ?>" required>
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label for="department<?php echo $lecturer['id']; ?>" class="form-label">Department *</label>
                                                                    <input type="text" class="form-control" id="department<?php echo $lecturer['id']; ?>" 
                                                                           name="department" value="<?php echo $lecturer['department'] ?: ''; ?>" required>
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label for="staff_id<?php echo $lecturer['id']; ?>" class="form-label">Staff ID</label>
                                                                    <input type="text" class="form-control" id="staff_id<?php echo $lecturer['id']; ?>" 
                                                                           name="staff_id" value="<?php echo $lecturer['staff_id'] ?: ''; ?>">
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                                <input type="hidden" name="lecturer_id" value="<?php echo $lecturer['id']; ?>">
                                                                <input type="hidden" name="action" value="approve_lecturer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-success">Approve Lecturer</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Edit Modal -->
                                            <div class="modal fade" id="editModal<?php echo $lecturer['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Edit Lecturer Information</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <div class="modal-body">
                                                                <div class="mb-3">
                                                                    <label for="edit_institution<?php echo $lecturer['id']; ?>" class="form-label">Institution *</label>
                                                                    <input type="text" class="form-control" id="edit_institution<?php echo $lecturer['id']; ?>" 
                                                                           name="institution" value="<?php echo $lecturer['institution'] ?: ''; ?>" required>
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label for="edit_department<?php echo $lecturer['id']; ?>" class="form-label">Department *</label>
                                                                    <input type="text" class="form-control" id="edit_department<?php echo $lecturer['id']; ?>" 
                                                                           name="department" value="<?php echo $lecturer['department'] ?: ''; ?>" required>
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label for="edit_staff_id<?php echo $lecturer['id']; ?>" class="form-label">Staff ID</label>
                                                                    <input type="text" class="form-control" id="edit_staff_id<?php echo $lecturer['id']; ?>" 
                                                                           name="staff_id" value="<?php echo $lecturer['staff_id'] ?: ''; ?>">
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                                <input type="hidden" name="lecturer_id" value="<?php echo $lecturer['id']; ?>">
                                                                <input type="hidden" name="action" value="update_lecturer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-primary">Update Information</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Verification Guidelines -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Verification Guidelines</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Approval Criteria</h6>
                        <ul>
                            <li>Verify institutional email addresses when possible</li>
                            <li>Check for complete institution and department information</li>
                            <li>Ensure staff ID is provided or request it during approval</li>
                            <li>Consider the educational context and legitimacy</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Status Meanings</h6>
                        <ul>
                            <li><span class="badge bg-warning">Pending</span> - Awaiting review</li>
                            <li><span class="badge bg-success">Approved</span> - Full access granted</li>
                            <li><span class="badge bg-danger">Rejected</span> - Application denied</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>