<?php
require_once 'resources-config.php';
requireLogin();

// Get dashboard statistics
$stats = [];

try {
    // Total resources
    $stmt = $resources_pdo->query("SELECT COUNT(*) as total FROM resources");
    $stats['total_resources'] = $stmt->fetch()['total'];

    // Published resources
    $stmt = $resources_pdo->query("SELECT COUNT(*) as total FROM resources WHERE is_published = 1");
    $stats['published_resources'] = $stmt->fetch()['total'];

    // Total categories
    $stmt = $resources_pdo->query("SELECT COUNT(*) as total FROM categories");
    $stats['total_categories'] = $stmt->fetch()['total'];

    // Total resource formats
    $stmt = $resources_pdo->query("SELECT COUNT(*) as total FROM resource_types");
    $stats['total_types'] = $stmt->fetch()['total'];

    // Awaiting approval
    $stmt = $resources_pdo->query("SELECT COUNT(*) as total FROM resources WHERE status = 'awaiting_approval'");
    $stats['awaiting_approval'] = $stmt->fetch()['total'];

    // Recent resources
    $stmt = $resources_pdo->query("
        SELECT r.*, rt.name as resource_type_name, c.name as category_name
        FROM resources r
        LEFT JOIN resource_types rt ON r.resource_type_id = rt.id
        LEFT JOIN categories c ON r.category_id = c.id
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $recent_resources = $stmt->fetchAll();

    // Resources by type for chart
    $stmt = $resources_pdo->query("
        SELECT rt.name, COUNT(r.id) as count
        FROM resource_types rt
        LEFT JOIN resources r ON rt.id = r.resource_type_id
        GROUP BY rt.id, rt.name
        ORDER BY count DESC
        LIMIT 10
    ");
    $resources_by_type = $stmt->fetchAll();
    
} catch (Exception $e) {
    $stats = [
        'total_resources' => 0,
        'published_resources' => 0,
        'total_categories' => 0,
        'total_types' => 0,
        'awaiting_approval' => 0
    ];
    $recent_resources = [];
    $resources_by_type = [];
    $error_message = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Belonging Resources</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #1d486b;
            --dark-blue: #102a41;
            --accent-red: #ee3a43;
        }
        
        .sidebar {
            background: var(--dark-blue);
            min-height: 100vh;
            padding: 0;
        }
        
        .sidebar .nav-link {
            color: #fff;
            padding: 0.75rem 1rem;
            border-radius: 0;
            transition: all 0.3s ease;
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
        
        .stat-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            border-radius: 15px 15px 0 0;
        }
        
        .progress {
            height: 8px;
            border-radius: 10px;
        }
        
        .btn-primary {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
            border-radius: 25px;
        }
        
        .btn-primary:hover {
            background-color: var(--dark-blue);
            border-color: var(--dark-blue);
        }
        
        .table th {
            font-weight: 600;
            color: var(--dark-blue);
            border-top: none;
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
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
                        <br>
                        <small class="text-muted"><?= ucfirst($_SESSION['admin_role']) ?></small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="resources.php">
                                <i class="fas fa-book"></i> Resources
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
                        <hr class="text-white-50">
                        <li class="nav-item">
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
                <div class="pt-3 pb-2">
                    <!-- Welcome Banner -->
                    <div class="welcome-banner">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1 class="h2 mb-2">Welcome to Admin Dashboard</h1>
                                <p class="mb-0">Manage your Belonging Resources content and settings</p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <a href="add-resource.php" class="btn btn-light btn-lg">
                                    <i class="fas fa-plus"></i> Add Resource
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-warning" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php foreach (getFlashMessages() as $message): ?>
                        <div class="alert alert-<?= $message['type'] ?> alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($message['message']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card text-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h3 class="mb-1"><?= $stats['total_resources'] ?></h3>
                                        <p class="card-text mb-0">Total Resources</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-book"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card text-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h3 class="mb-1"><?= $stats['published_resources'] ?></h3>
                                        <p class="card-text mb-0">Published</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-eye"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card text-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h3 class="mb-1"><?= $stats['total_categories'] ?></h3>
                                        <p class="card-text mb-0">Categories</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-tags"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card text-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h3 class="mb-1"><?= $stats['total_types'] ?></h3>
                                        <p class="card-text mb-0">Resource Formats</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-list"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <a href="resources.php?status=awaiting_approval" class="text-decoration-none">
                            <div class="card stat-card text-white" style="background-color: #fd7e14;">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h3 class="mb-1"><?= $stats['awaiting_approval'] ?></h3>
                                            <p class="card-text mb-0">Awaiting Approval</p>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="fas fa-hourglass-half"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Recent Resources -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-clock"></i> Recent Resources</h5>
                                <a href="resources.php" class="btn btn-primary btn-sm">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_resources)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No resources found.</p>
                                        <a href="add-resource.php" class="btn btn-primary">Add Your First Resource</a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Type</th>
                                                    <th>Category</th>
                                                    <th>Status</th>
                                                    <th>Created</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_resources as $resource): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($resource['resource_name']) ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars(substr($resource['description'], 0, 60)) ?>
                                                            <?= strlen($resource['description']) > 60 ? '...' : '' ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-light text-dark">
                                                            <?= htmlspecialchars($resource['resource_type_name'] ?? 'N/A') ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary">
                                                            <?= htmlspecialchars($resource['category_name'] ?? 'Uncategorized') ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if (($resource['status'] ?? '') === 'awaiting_approval'): ?>
                                                            <span class="badge" style="background-color:#fd7e14;">Awaiting Approval</span>
                                                        <?php elseif ($resource['is_published']): ?>
                                                            <span class="badge bg-success">Published</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Draft</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small><?= date('M j, Y', strtotime($resource['created_at'])) ?></small>
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
                    
                    <!-- Resources by Type -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Resources by Type</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($resources_by_type)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-chart-bar fa-2x text-muted mb-2"></i>
                                        <p class="text-muted">No data available</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($resources_by_type as $type): ?>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="small fw-bold"><?= htmlspecialchars($type['name']) ?></span>
                                                <span class="badge bg-primary"><?= $type['count'] ?></span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar bg-primary" 
                                                     style="width: <?= $stats['total_resources'] > 0 ? ($type['count'] / $stats['total_resources']) * 100 : 0 ?>%">
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="add-resource.php" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Add Resource
                                    </a>
                                    <a href="categories.php" class="btn btn-outline-primary">
                                        <i class="fas fa-tags"></i> Manage Categories
                                    </a>
                                    <a href="../resources.html" target="_blank" class="btn btn-outline-secondary">
                                        <i class="fas fa-external-link-alt"></i> View Site
                                    </a>
                                </div>
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