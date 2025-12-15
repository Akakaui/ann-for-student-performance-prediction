<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';

session_start();
Auth::requireRole(['lecturer']);

// Check if lecturer is verified
if (!Auth::isLecturerVerified()) {
    $_SESSION['error'] = "Your lecturer account is pending verification. Please contact admin.";
    Utils::redirect('dashboard.php');
}

$db = Database::getInstance();
$error = '';
$success = '';

// Handle group actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $group_id = intval($_POST['group_id'] ?? 0);
    $group_name = Utils::sanitize($_POST['group_name'] ?? '');
    $description = Utils::sanitize($_POST['description'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';

    try {
        if (!Utils::verifyCSRFToken($csrf_token)) {
            throw new Exception("Invalid CSRF token");
        }

        if ($action === 'create_group') {
            if (empty($group_name)) {
                throw new Exception("Group name is required");
            }

            $db->query(
                "INSERT INTO lecturer_groups (lecturer_id, group_name, description) VALUES (?, ?, ?)",
                [$_SESSION['user_id'], $group_name, $description]
            );
            $success = "Group created successfully";

        } elseif ($action === 'update_group' && $group_id > 0) {
            if (empty($group_name)) {
                throw new Exception("Group name is required");
            }

            $db->query(
                "UPDATE lecturer_groups SET group_name = ?, description = ? WHERE id = ? AND lecturer_id = ?",
                [$group_name, $description, $group_id, $_SESSION['user_id']]
            );
            $success = "Group updated successfully";

        } elseif ($action === 'delete_group' && $group_id > 0) {
            $db->query(
                "DELETE FROM lecturer_groups WHERE id = ? AND lecturer_id = ?",
                [$group_id, $_SESSION['user_id']]
            );
            $success = "Group deleted successfully";

        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get lecturer's groups
$groups = $db->fetchAll("
    SELECT lg.*, 
           COUNT(gs.student_id) as student_count
    FROM lecturer_groups lg 
    LEFT JOIN group_students gs ON lg.id = gs.group_id 
    WHERE lg.lecturer_id = ? 
    GROUP BY lg.id 
    ORDER BY lg.created_at DESC
", [$_SESSION['user_id']]);

// Get all students for adding to groups
$students = $db->fetchAll("
    SELECT u.id, u.username, u.email 
    FROM users u 
    WHERE u.role = 'student' AND u.is_verified = 1
    ORDER BY u.username
");

$csrf_token = Utils::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Groups - Student Performance Predictor</title>
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
                        <a class="nav-link" href="prediction_form.php">Batch Prediction</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="lecturer_groups.php">Student Groups</a>
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
                    <h2 class="fw-bold">Student Groups</h2>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                        <i class="bi bi-plus-circle"></i> Create New Group
                    </button>
                </div>
                <p class="text-muted">Manage student groups for batch predictions and performance analysis.</p>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Groups Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo count($groups); ?></h4>
                                <p class="card-text">Total Groups</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-collection display-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title">
                                    <?php 
                                    $total_students = array_sum(array_column($groups, 'student_count'));
                                    echo $total_students;
                                    ?>
                                </h4>
                                <p class="card-text">Total Students</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-people display-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title">
                                    <?php 
                                    $avg_students = count($groups) > 0 ? round($total_students / count($groups), 1) : 0;
                                    echo $avg_students;
                                    ?>
                                </h4>
                                <p class="card-text">Avg. Students/Group</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-graph-up display-6"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Groups Grid -->
        <div class="row">
            <?php if (empty($groups)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="bi bi-collection display-1 text-muted"></i>
                        <h4 class="text-muted mt-3">No Groups Created</h4>
                        <p class="text-muted">Create your first student group to get started with batch predictions.</p>
                        <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                            <i class="bi bi-plus-circle"></i> Create Your First Group
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($groups as $group): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card group-card h-100">
                            <div class="card-header bg-<?php echo $group['student_count'] > 0 ? 'success' : 'secondary'; ?> text-white">
                                <h5 class="card-title mb-0"><?php echo $group['group_name']; ?></h5>
                            </div>
                            <div class="card-body">
                                <?php if ($group['description']): ?>
                                    <p class="card-text"><?php echo $group['description']; ?></p>
                                <?php else: ?>
                                    <p class="card-text text-muted">No description provided</p>
                                <?php endif; ?>
                                
                                <div class="group-stats">
                                    <div class="d-flex justify-content-between text-muted small">
                                        <span>Students:</span>
                                        <span class="fw-bold"><?php echo $group['student_count']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between text-muted small">
                                        <span>Created:</span>
                                        <span><?php echo Utils::formatDate($group['created_at'], 'M j, Y'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="btn-group w-100">
                                    <a href="lecturer_group_details.php?group_id=<?php echo $group['id']; ?>" 
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" 
                                            data-bs-toggle="modal" data-bs-target="#editGroupModal<?php echo $group['id']; ?>">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this group?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                        <input type="hidden" name="action" value="delete_group">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Edit Group Modal -->
                        <div class="modal fade" id="editGroupModal<?php echo $group['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Group</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label for="edit_group_name<?php echo $group['id']; ?>" class="form-label">Group Name *</label>
                                                <input type="text" class="form-control" id="edit_group_name<?php echo $group['id']; ?>" 
                                                       name="group_name" value="<?php echo $group['group_name']; ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="edit_description<?php echo $group['id']; ?>" class="form-label">Description</label>
                                                <textarea class="form-control" id="edit_description<?php echo $group['id']; ?>" 
                                                          name="description" rows="3"><?php echo $group['description']; ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                            <input type="hidden" name="action" value="update_group">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Update Group</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Group Modal -->
    <div class="modal fade" id="createGroupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="group_name" class="form-label">Group Name *</label>
                            <input type="text" class="form-control" id="group_name" name="group_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="create_group">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Group</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>