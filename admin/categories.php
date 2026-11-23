<?php
require_once 'resources-config.php';
requireLogin();

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');

        if ($name) {
            try {
                $stmt = $resources_pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
                flashMessage('success', 'Category added successfully');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    flashMessage('error', 'Category name already exists');
                } else {
                    flashMessage('error', 'Error adding category: ' . $e->getMessage());
                }
            }
        } else {
            flashMessage('error', 'Category name is required');
        }
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');

        if ($id && $name) {
            try {
                $stmt = $resources_pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $description, $id]);
                flashMessage('success', 'Category updated successfully');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    flashMessage('error', 'Category name already exists');
                } else {
                    flashMessage('error', 'Error updating category: ' . $e->getMessage());
                }
            }
        } else {
            flashMessage('error', 'Category name is required');
        }
    }

    header('Location: categories.php');
    exit;
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    try {
        // Check if category is being used
        $stmt = $resources_pdo->prepare("SELECT COUNT(*) as count FROM resources WHERE category_id = ?");
        $stmt->execute([$id]);
        $usage = $stmt->fetch();

        if ($usage['count'] > 0) {
            flashMessage('error', 'Cannot delete category. It is being used by ' . $usage['count'] . ' resource(s).');
        } else {
            $stmt = $resources_pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            flashMessage('success', 'Category deleted successfully');
        }
    } catch (Exception $e) {
        flashMessage('error', 'Error deleting category: ' . $e->getMessage());
    }

    header('Location: categories.php');
    exit;
}

// Get all categories with resource counts
$stmt = $resources_pdo->query("
    SELECT c.*, COUNT(r.id) as resource_count
    FROM categories c
    LEFT JOIN resources r ON c.id = r.category_id
    GROUP BY c.id
    ORDER BY c.name
");
$categories = $stmt->fetchAll();

// Get category for editing
$editCategory = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $resources_pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$editId]);
    $editCategory = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - Admin Panel</title>
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
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: var(--dark-blue);
        }
        
        .category-item {
            transition: all 0.3s ease;
        }
        
        .category-item:hover {
            background-color: #f8f9fa;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.2rem rgba(29, 72, 107, 0.25);
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
                            <a class="nav-link active" href="categories.php">
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
                            <h1 class="h2">Categories</h1>
                            <p class="text-muted">Organize your resources into meaningful categories</p>
                        </div>
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-tags"></i>
                            </div>
                            <h3 class="mb-0"><?= count($categories) ?></h3>
                            <small class="text-muted">Total Categories</small>
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
                                    <i class="fas fa-<?= $editCategory ? 'edit' : 'plus' ?>"></i>
                                    <?= $editCategory ? 'Edit Category' : 'Add New Category' ?>
                                </h5>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST">
                                    <input type="hidden" name="action" value="<?= $editCategory ? 'edit' : 'add' ?>">
                                    <?php if ($editCategory): ?>
                                        <input type="hidden" name="id" value="<?= $editCategory['id'] ?>">
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Category Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?= htmlspecialchars($editCategory['name'] ?? '') ?>" 
                                               placeholder="Enter category name" required>
                                        <div class="form-text">Choose a clear, descriptive name for your category.</div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3" 
                                                  placeholder="Enter a brief description (optional)"><?= htmlspecialchars($editCategory['description'] ?? '') ?></textarea>
                                        <div class="form-text">Optional description to explain what this category contains.</div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-<?= $editCategory ? 'save' : 'plus' ?>"></i> 
                                            <?= $editCategory ? 'Update Category' : 'Add Category' ?>
                                        </button>
                                        
                                        <?php if ($editCategory): ?>
                                            <a href="categories.php" class="btn btn-outline-secondary">
                                                <i class="fas fa-times"></i> Cancel
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Quick Tips -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-lightbulb"></i> Quick Tips</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2"><i class="fas fa-check-circle text-success"></i> Use clear, specific names</li>
                                    <li class="mb-2"><i class="fas fa-check-circle text-success"></i> Keep categories broad but meaningful</li>
                                    <li class="mb-2"><i class="fas fa-check-circle text-success"></i> Add descriptions for clarity</li>
                                    <li class="mb-0"><i class="fas fa-info-circle text-info"></i> Categories in use cannot be deleted</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Categories List -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list"></i> All Categories (<?= count($categories) ?>)
                                </h5>
                                <?php if (!empty($categories)): ?>
                                    <div class="text-muted small">
                                        Total Resources: <?= array_sum(array_column($categories, 'resource_count')) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($categories)): ?>
                                    <div class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="fas fa-tags fa-4x mb-3"></i>
                                            <h4>No categories yet</h4>
                                            <p>Create your first category to start organizing your resources.</p>
                                            <small class="text-muted">Categories help users find and filter resources more easily.</small>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Description</th>
                                                    <th>Resources</th>
                                                    <th>Created</th>
                                                    <th width="120">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($categories as $category): ?>
                                                <tr class="category-item">
                                                    <td>
                                                        <strong><?= htmlspecialchars($category['name']) ?></strong>
                                                    </td>
                                                    <td>
                                                        <span class="text-muted">
                                                            <?= htmlspecialchars($category['description'] ?: 'No description') ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($category['resource_count'] > 0): ?>
                                                            <span class="badge bg-primary">
                                                                <?= $category['resource_count'] ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-light text-muted">0</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?= date('M j, Y', strtotime($category['created_at'])) ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <a href="?edit=<?= $category['id'] ?>" 
                                                               class="btn btn-outline-primary" 
                                                               title="Edit Category">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            
                                                            <?php if ($category['resource_count'] == 0): ?>
                                                                <a href="?action=delete&id=<?= $category['id'] ?>" 
                                                                   class="btn btn-outline-danger" 
                                                                   onclick="return confirm('Are you sure you want to delete this category? This action cannot be undone.')"
                                                                   title="Delete Category">
                                                                    <i class="fas fa-trash"></i>
                                                                </a>
                                                            <?php else: ?>
                                                                <button class="btn btn-outline-secondary" 
                                                                        disabled 
                                                                        title="Cannot delete - category has <?= $category['resource_count'] ?> resource(s)">
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
                        
                        <?php if (!empty($categories)): ?>
                        <!-- Category Usage Statistics -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-chart-bar"></i> Category Usage</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php 
                                    $topCategories = array_slice(
                                        array_filter($categories, function($cat) { return $cat['resource_count'] > 0; }),
                                        0, 
                                        4
                                    );
                                    ?>
                                    <?php foreach ($topCategories as $category): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="small fw-bold"><?= htmlspecialchars($category['name']) ?></span>
                                                <span class="badge bg-primary"><?= $category['resource_count'] ?></span>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <?php 
                                                $totalResources = array_sum(array_column($categories, 'resource_count'));
                                                $percentage = $totalResources > 0 ? ($category['resource_count'] / $totalResources) * 100 : 0;
                                                ?>
                                                <div class="progress-bar bg-primary" style="width: <?= $percentage ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($topCategories)): ?>
                                        <div class="col-12 text-center text-muted">
                                            <i class="fas fa-info-circle"></i> No categories have resources yet
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus name field when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const nameField = document.getElementById('name');
            if (nameField && !nameField.value) {
                nameField.focus();
            }
        });
        
        // Clear form after successful submission (if not editing)
        <?php if (!$editCategory && empty($errors)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.reset();
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>