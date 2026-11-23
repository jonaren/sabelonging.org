<?php
require_once 'resources-config.php';
requireAdmin(); // Only admins can manage users

$errors = [];
$success = false;

// Handle add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = sanitizeInput($_POST['role'] ?? 'editor');

    // Validation
    if (empty($username)) {
        $errors[] = 'Username is required';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    }

    if (empty($password) || strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }

    if (empty($errors)) {
        try {
            // Check if username or email already exists
            $stmt = $resources_pdo->prepare("SELECT id FROM admin_users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $errors[] = 'Username or email already exists';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $resources_pdo->prepare("
                    INSERT INTO admin_users (username, email, password_hash, full_name, role)
                    VALUES (?, ?, ?, ?, ?)
                ");

                $stmt->execute([$username, $email, $password_hash, $full_name, $role]);
                flashMessage('success', 'User created successfully');
                header('Location: users.php');
                exit;
            }
        } catch (Exception $e) {
            $errors[] = 'Error creating user: ' . $e->getMessage();
        }
    }
}

// Handle update role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_role') {
    $id = (int)($_POST['id'] ?? 0);
    $newRole = sanitizeInput($_POST['role'] ?? '');
    $allowedRoles = ['content_creator', 'editor', 'admin'];

    if ($id === $_SESSION['admin_id']) {
        flashMessage('error', 'You cannot change your own role');
    } elseif (!in_array($newRole, $allowedRoles, true)) {
        flashMessage('error', 'Invalid role selected');
    } else {
        try {
            $stmt = $resources_pdo->prepare("SELECT role FROM admin_users WHERE id = ?");
            $stmt->execute([$id]);
            $target = $stmt->fetch();

            if (!$target) {
                flashMessage('error', 'User not found');
            } elseif ($target['role'] === 'admin') {
                flashMessage('error', 'Cannot change the role of an admin user');
            } else {
                $stmt = $resources_pdo->prepare("UPDATE admin_users SET role = ? WHERE id = ?");
                $stmt->execute([$newRole, $id]);
                flashMessage('success', 'User role updated successfully');
            }
        } catch (Exception $e) {
            flashMessage('error', 'Error updating user role: ' . $e->getMessage());
        }
    }

    header('Location: users.php');
    exit;
}

// Handle delete user
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // Don't allow deleting yourself
    if ($id === $_SESSION['admin_id']) {
        flashMessage('error', 'You cannot delete your own account');
    } else {
        try {
            $stmt = $resources_pdo->prepare("DELETE FROM admin_users WHERE id = ?");
            $stmt->execute([$id]);
            flashMessage('success', 'User deleted successfully');
        } catch (Exception $e) {
            flashMessage('error', 'Error deleting user: ' . $e->getMessage());
        }
    }

    header('Location: users.php');
    exit;
}

// Handle toggle active status
if (isset($_GET['action']) && $_GET['action'] === 'toggle_active' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    if ($id === $_SESSION['admin_id']) {
        flashMessage('error', 'You cannot deactivate your own account');
    } else {
        try {
            $stmt = $resources_pdo->prepare("UPDATE admin_users SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$id]);
            flashMessage('success', 'User status updated successfully');
        } catch (Exception $e) {
            flashMessage('error', 'Error updating user status: ' . $e->getMessage());
        }
    }

    header('Location: users.php');
    exit;
}

// Get all users
$stmt = $resources_pdo->query("SELECT * FROM admin_users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #1d486b;
            --dark-blue: #102a41;
        }

        .sidebar {
            background: var(--dark-blue);
            min-height: 100vh;
        }

        .sidebar .nav-link {
            color: #fff;
            padding: 0.75rem 1rem;
        }

        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: var(--primary-blue);
            color: #fff;
        }

        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }

        .main-content {
            background-color: #f8f9fa;
            min-height: 100vh;
        }

        .btn-primary {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }

        .btn-primary:hover {
            background-color: var(--dark-blue);
            border-color: var(--dark-blue);
        }

        .badge-admin {
            background-color: #dc3545;
        }

        .badge-editor {
            background-color: #6c757d;
        }

        .badge-content-creator {
            background-color: #6f42c1;
        }

        .badge-active {
            background-color: #28a745;
        }

        .badge-inactive {
            background-color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center text-white mb-4">
                        <?php $site_logo_url = getSiteLogoUrl(); ?>
                        <?php if ($site_logo_url): ?>
                        <div style="width: 70px; height: 70px; background: #fff; border-radius: 50%; margin: 0 auto 0.75rem; display: flex; align-items: center; justify-content: center; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                            <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="Logo" style="max-width: 75%; max-height: 75%; object-fit: contain;">
                        </div>
                        <?php endif; ?>
                        <h4><i class="fas fa-cogs"></i> Admin Panel</h4>
                        <small>Welcome, <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?></small>
                    </div>

                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="resources.php">
                                <i class="fas fa-book"></i> Resources
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="websites.php">
                                <i class="fas fa-window-maximize"></i> Websites
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="categories.php">
                                <i class="fas fa-tags"></i> Categories
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="resource-types.php">
                                <i class="fas fa-list"></i> Resource Formats
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="ministries.php">
                                <i class="fas fa-church"></i> Ministries
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="languages.php">
                                <i class="fas fa-globe"></i> Languages
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="users.php">
                                <i class="fas fa-users"></i> Admin Users
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link" href="../resources.html" target="_blank">
                                <i class="fas fa-external-link-alt"></i> View Site
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="pt-3 pb-2 mb-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h1 class="h2">Manage Admin Users</h1>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-plus"></i> Add New User
                        </button>
                    </div>
                </div>

                <!-- Flash Messages -->
                <?php foreach (getFlashMessages() as $flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show">
                        <?= htmlspecialchars($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Full Name</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th>Created</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($user['username']) ?></strong>
                                                <?php if ($user['id'] === $_SESSION['admin_id']): ?>
                                                    <span class="badge bg-primary ms-2">You</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td><?= htmlspecialchars($user['full_name'] ?? 'N/A') ?></td>
                                            <td>
                                                <?php if ($user['role'] === 'admin'): ?>
                                                    <span class="badge badge-admin">Admin</span>
                                                <?php elseif ($user['role'] === 'content_creator'): ?>
                                                    <span class="badge badge-content-creator">Content Creator</span>
                                                <?php else: ?>
                                                    <span class="badge badge-editor">Editor</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($user['is_active']): ?>
                                                    <span class="badge badge-active">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-inactive">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($user['last_login']): ?>
                                                    <small><?= date('M d, Y H:i', strtotime($user['last_login'])) ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Never</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?= date('M d, Y', strtotime($user['created_at'])) ?></small>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($user['id'] !== $_SESSION['admin_id']): ?>
                                                    <?php if ($user['role'] !== 'admin'): ?>
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-primary"
                                                            title="Change Role"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editRoleModal<?= $user['id'] ?>">
                                                        <i class="fas fa-user"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <a href="?action=toggle_active&id=<?= $user['id'] ?>"
                                                       class="btn btn-sm btn-outline-secondary"
                                                       title="<?= $user['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                                        <i class="fas fa-<?= $user['is_active'] ? 'ban' : 'check' ?>"></i>
                                                    </a>
                                                    <a href="?action=delete&id=<?= $user['id'] ?>"
                                                       class="btn btn-sm btn-outline-danger"
                                                       title="Delete"
                                                       onclick="return confirm('Are you sure you want to delete this user?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">Current User</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit Role Modals -->
    <?php foreach ($users as $user): ?>
        <?php if ($user['id'] !== $_SESSION['admin_id'] && $user['role'] !== 'admin'): ?>
        <div class="modal fade" id="editRoleModal<?= $user['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_role">
                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Change Role — <?= htmlspecialchars($user['username']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="role<?= $user['id'] ?>" class="form-label">Role *</label>
                                <select class="form-select" id="role<?= $user['id'] ?>" name="role" required>
                                    <option value="content_creator" <?= $user['role'] === 'content_creator' ? 'selected' : '' ?>>Content Creator</option>
                                    <option value="editor" <?= $user['role'] === 'editor' ? 'selected' : '' ?>>Editor</option>
                                    <option value="admin">Admin</option>
                                </select>
                                <div class="form-text">
                                    <strong>Content Creator:</strong> Can create resources, awaits admin approval to publish<br>
                                    <strong>Editor:</strong> Can manage and publish resources<br>
                                    <strong>Admin:</strong> Full access including user management
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Role</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="form-text">Minimum 6 characters</div>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role *</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="content_creator">Content Creator</option>
                                <option value="editor">Editor</option>
                                <option value="admin">Admin</option>
                            </select>
                            <div class="form-text">
                                <strong>Content Creator:</strong> Can create resources, awaits admin approval to publish<br>
                                <strong>Editor:</strong> Can manage and publish resources<br>
                                <strong>Admin:</strong> Full access including user management
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
