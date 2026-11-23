<?php
require_once 'resources-config.php';
requireLogin();

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = sanitizeInput($_POST['name'] ?? '');
        $icon = sanitizeInput($_POST['icon'] ?? 'fa-bookmark');
        $slug = generateSlug($name);

        if ($name) {
            try {
                $stmt = $resources_pdo->prepare("INSERT INTO resource_types (name, icon, slug) VALUES (?, ?, ?)");
                $stmt->execute([$name, $icon, $slug]);
                flashMessage('success', 'Resource type added successfully');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    flashMessage('error', 'Resource type name already exists');
                } else {
                    flashMessage('error', 'Error adding resource format: ' . $e->getMessage());
                }
            }
        } else {
            flashMessage('error', 'Resource type name is required');
        }
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = sanitizeInput($_POST['name'] ?? '');
        $icon = sanitizeInput($_POST['icon'] ?? 'fa-bookmark');
        $slug = generateSlug($name);

        if ($id && $name) {
            try {
                $stmt = $resources_pdo->prepare("UPDATE resource_types SET name = ?, icon = ?, slug = ? WHERE id = ?");
                $stmt->execute([$name, $icon, $slug, $id]);
                flashMessage('success', 'Resource type updated successfully');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    flashMessage('error', 'Resource type name already exists');
                } else {
                    flashMessage('error', 'Error updating resource format: ' . $e->getMessage());
                }
            }
        } else {
            flashMessage('error', 'Resource type name is required');
        }
    }

    header('Location: resource-types.php');
    exit;
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    try {
        // Check if resource format is being used
        $stmt = $resources_pdo->prepare("SELECT COUNT(*) as count FROM resources WHERE resource_type_id = ?");
        $stmt->execute([$id]);
        $usage = $stmt->fetch();

        if ($usage['count'] > 0) {
            flashMessage('error', 'Cannot delete resource format. It is being used by ' . $usage['count'] . ' resource(s).');
        } else {
            $stmt = $resources_pdo->prepare("DELETE FROM resource_types WHERE id = ?");
            $stmt->execute([$id]);
            flashMessage('success', 'Resource type deleted successfully');
        }
    } catch (Exception $e) {
        flashMessage('error', 'Error deleting resource format: ' . $e->getMessage());
    }

    header('Location: resource-types.php');
    exit;
}

// Get all resource formats with resource counts
$stmt = $resources_pdo->query("
    SELECT rt.*, COUNT(r.id) as resource_count
    FROM resource_types rt
    LEFT JOIN resources r ON rt.id = r.resource_type_id
    GROUP BY rt.id
    ORDER BY rt.display_order, rt.name
");
$resourceTypes = $stmt->fetchAll();

// Get resource format for editing
$editResourceType = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $resources_pdo->prepare("SELECT * FROM resource_types WHERE id = ?");
    $stmt->execute([$editId]);
    $editResourceType = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Formats - Admin Panel</title>
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

        .form-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border: none;
        }

        .form-card-header {
            background: var(--primary-blue);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1rem 1.5rem;
        }

        .resource-type-item:hover {
            background-color: #f8f9fa;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border: none;
            padding: 1.5rem;
            text-align: center;
        }

        .stats-icon {
            font-size: 2rem;
            color: var(--primary-blue);
            margin-bottom: 0.5rem;
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
                        <small>Welcome, <?= htmlspecialchars($_SESSION['admin_username']) ?></small>
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
                            <a class="nav-link active" href="resource-types.php">
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
                        <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users"></i> Admin Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="site-settings.php">
                                <i class="fas fa-cog"></i> Site Settings
                            </a>
                        </li>
                        <?php endif; ?>
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
                        <div>
                            <h1 class="h2">Resource Formats</h1>
                            <p class="text-muted">Define types of resources (Article, Video, Book, etc.)</p>
                        </div>
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-list"></i>
                            </div>
                            <h3 class="mb-0"><?= count($resourceTypes) ?></h3>
                            <small class="text-muted">Total Types</small>
                        </div>
                    </div>
                </div>

                <?php foreach (getFlashMessages() as $message): ?>
                    <div class="alert alert-<?= $message['type'] ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?= $message['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                        <?= htmlspecialchars($message['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>

                <div class="row">
                    <!-- Add/Edit Form -->
                    <div class="col-md-4">
                        <div class="form-card">
                            <div class="form-card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-<?= $editResourceType ? 'edit' : 'plus' ?>"></i>
                                    <?= $editResourceType ? 'Edit Resource Format' : 'Add New Resource Format' ?>
                                </h5>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST">
                                    <input type="hidden" name="action" value="<?= $editResourceType ? 'edit' : 'add' ?>">
                                    <?php if ($editResourceType): ?>
                                        <input type="hidden" name="id" value="<?= $editResourceType['id'] ?>">
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <label for="name" class="form-label">Type Name *</label>
                                        <input type="text" class="form-control" id="name" name="name"
                                               value="<?= htmlspecialchars($editResourceType['name'] ?? '') ?>"
                                               placeholder="e.g., Article, Video, Book" required>
                                    </div>

                                    <div class="mb-4">
                                        <label for="icon" class="form-label">Font Awesome Icon</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas <?= htmlspecialchars($editResourceType['icon'] ?? 'fa-bookmark') ?>"></i></span>
                                            <input type="text" class="form-control" id="icon" name="icon"
                                                   value="<?= htmlspecialchars($editResourceType['icon'] ?? 'fa-bookmark') ?>"
                                                   placeholder="fa-bookmark">
                                        </div>
                                        <div class="form-text">Find icons at <a href="https://fontawesome.com/icons" target="_blank">fontawesome.com</a></div>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-<?= $editResourceType ? 'save' : 'plus' ?>"></i>
                                            <?= $editResourceType ? 'Update Type' : 'Add Type' ?>
                                        </button>

                                        <?php if ($editResourceType): ?>
                                            <a href="resource-types.php" class="btn btn-outline-secondary">
                                                <i class="fas fa-times"></i> Cancel
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Resource Formats List -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list"></i> All Resource Formats (<?= count($resourceTypes) ?>)
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($resourceTypes)): ?>
                                    <div class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="fas fa-list fa-4x mb-3"></i>
                                            <h4>No resource formats yet</h4>
                                            <p>Create your first resource format.</p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th width="60">Icon</th>
                                                    <th>Name</th>
                                                    <th>Slug</th>
                                                    <th>Resources</th>
                                                    <th width="120">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($resourceTypes as $type): ?>
                                                <tr class="resource-type-item">
                                                    <td>
                                                        <i class="fas <?= htmlspecialchars($type['icon']) ?> fa-2x text-primary"></i>
                                                    </td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($type['name']) ?></strong>
                                                    </td>
                                                    <td>
                                                        <code><?= htmlspecialchars($type['slug']) ?></code>
                                                    </td>
                                                    <td>
                                                        <?php if ($type['resource_count'] > 0): ?>
                                                            <span class="badge bg-primary"><?= $type['resource_count'] ?></span>
                                                        <?php else: ?>
                                                            <span class="badge bg-light text-muted">0</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <a href="?edit=<?= $type['id'] ?>" class="btn btn-outline-primary" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>

                                                            <?php if ($type['resource_count'] == 0): ?>
                                                                <a href="?action=delete&id=<?= $type['id'] ?>"
                                                                   class="btn btn-outline-danger"
                                                                   onclick="return confirm('Are you sure?')"
                                                                   title="Delete">
                                                                    <i class="fas fa-trash"></i>
                                                                </a>
                                                            <?php else: ?>
                                                                <button class="btn btn-outline-secondary" disabled title="In use">
                                                                    <i class="fas fa-lock"></i>
                                                                </button>
                                                            <?php endif; ?>
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
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
