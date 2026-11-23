<?php
require_once 'resources-config.php';
requireLogin();

// Handle approve action (admin/editor only)
if (isset($_GET['action']) && $_GET['action'] === 'approve' && isset($_GET['id']) && !isContentCreator()) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $resources_pdo->prepare("UPDATE resources SET is_published = 1, status = 'published' WHERE id = ?");
        $stmt->execute([$id]);
        flashMessage('success', 'Resource approved and published');
    } catch (Exception $e) {
        flashMessage('error', 'Error approving resource: ' . $e->getMessage());
    }
    header('Location: resources.php');
    exit;
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // Content creators can only delete their own resources
    if (isContentCreator()) {
        $stmt = $resources_pdo->prepare("SELECT created_by FROM resources WHERE id = ?");
        $stmt->execute([$id]);
        $check = $stmt->fetch();
        if (!$check || $check['created_by'] != $_SESSION['admin_id']) {
            flashMessage('error', 'You can only delete your own resources');
            header('Location: resources.php');
            exit;
        }
    }

    try {
        $resources_pdo->beginTransaction();

        // Delete associated files
        $stmt = $resources_pdo->prepare("SELECT file_path FROM resource_files WHERE resource_id = ?");
        $stmt->execute([$id]);
        $files = $stmt->fetchAll();

        foreach ($files as $file) {
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
        }

        // Delete file records
        $stmt = $resources_pdo->prepare("DELETE FROM resource_files WHERE resource_id = ?");
        $stmt->execute([$id]);

        // Delete thumbnail
        $stmt = $resources_pdo->prepare("SELECT thumbnail_image FROM resources WHERE id = ?");
        $stmt->execute([$id]);
        $resource = $stmt->fetch();

        if ($resource && !empty($resource['thumbnail_image'])) {
            $thumbnail_path = str_replace(RESOURCES_SITE_URL . '/', '', $resource['thumbnail_image']);
            if (file_exists($thumbnail_path)) {
                unlink($thumbnail_path);
            }
        }

        // Delete resource
        $stmt = $resources_pdo->prepare("DELETE FROM resources WHERE id = ?");
        $stmt->execute([$id]);

        $resources_pdo->commit();
        flashMessage('success', 'Resource deleted successfully');

    } catch (Exception $e) {
        $resources_pdo->rollBack();
        flashMessage('error', 'Error deleting resource: ' . $e->getMessage());
    }

    header('Location: resources.php');
    exit;
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Search and filter
$search = $_GET['search'] ?? '';
$resource_type_filter = $_GET['resource_type'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(r.resource_name LIKE ? OR r.description LIKE ? OR r.meta_data LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($resource_type_filter)) {
    $where_clauses[] = "r.resource_type_id = ?";
    $params[] = $resource_type_filter;
}

if ($status_filter !== '') {
    if ($status_filter === 'awaiting_approval') {
        $where_clauses[] = "r.status = 'awaiting_approval'";
    } else {
        $where_clauses[] = "r.is_published = ?";
        $params[] = $status_filter;
    }
}

// Content creators only see their own resources
if (isContentCreator()) {
    $where_clauses[] = "r.created_by = ?";
    $params[] = $_SESSION['admin_id'];
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total count
$count_query = "SELECT COUNT(*) as total FROM resources r $where_sql";
$stmt = $resources_pdo->prepare($count_query);
$stmt->execute($params);
$total_resources = $stmt->fetch()['total'];
$total_pages = ceil($total_resources / $per_page);

// Get resources
$query = "
    SELECT
        r.*,
        rt.name AS resource_type_name,
        c.name AS category_name,
        l.name AS language_name,
        m.name AS ministry_name,
        au.username AS created_by_username,
        (SELECT GROUP_CONCAT(cat.name ORDER BY cat.display_order SEPARATOR '||')
         FROM resource_categories rc
         JOIN categories cat ON rc.category_id = cat.id
         WHERE rc.resource_id = r.id) AS category_names
    FROM resources r
    LEFT JOIN resource_types rt ON r.resource_type_id = rt.id
    LEFT JOIN categories c ON r.category_id = c.id
    LEFT JOIN languages l ON r.language_id = l.id
    LEFT JOIN ministries m ON r.ministry_id = m.id
    LEFT JOIN admin_users au ON r.created_by = au.id
    $where_sql
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;

$stmt = $resources_pdo->prepare($query);
$stmt->execute($params);
$resources = $stmt->fetchAll();

// Get resource formats for filter
$resource_types = $resources_pdo->query("SELECT id, name FROM resource_types WHERE is_active = 1 ORDER BY name")->fetchAll();

// Category pill styling (matches the colors used for the category buttons in includes/banner.php)
$category_pills = [
    'Leadership Development' => ['abbr' => 'LD', 'bg' => '#1d486b', 'border' => '#1d486b', 'color' => '#fff'],
    'Spiritual Life' => ['abbr' => 'SL', 'bg' => '#9a323f', 'border' => '#5c0a10', 'color' => '#fff'],
    'Youth' => ['abbr' => 'Y', 'bg' => '#ddcc3b', 'border' => '#c0a30e', 'color' => '#5c4a00'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Resources - Admin Panel</title>
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

        .resource-thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }

        .badge-published {
            background-color: #28a745;
        }

        .badge-draft {
            background-color: #6c757d;
        }

        .badge-awaiting {
            background-color: #fd7e14;
        }

        .badge-external {
            background-color: #17a2b8;
        }

        .table-actions {
            white-space: nowrap;
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
                            <a class="nav-link active" href="resources.php">
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
                        <h1 class="h2">Manage Resources</h1>
                        <a href="add-resource-new.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Resource
                        </a>
                    </div>
                </div>

                <!-- Flash Messages -->
                <?php foreach (getFlashMessages() as $flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show">
                        <?= htmlspecialchars($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search"
                                       value="<?= htmlspecialchars($search) ?>"
                                       placeholder="Search by name, description...">
                            </div>
                            <div class="col-md-3">
                                <label for="resource_type" class="form-label">Resource Format</label>
                                <select class="form-select" id="resource_type" name="resource_type">
                                    <option value="">All Types</option>
                                    <?php foreach ($resource_types as $type): ?>
                                        <option value="<?= $type['id'] ?>"
                                                <?= $resource_type_filter == $type['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($type['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Published</option>
                                    <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Draft</option>
                                    <option value="awaiting_approval" <?= $status_filter === 'awaiting_approval' ? 'selected' : '' ?>>Awaiting Approval</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Resources Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">Image</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Views</th>
                                        <th>Created</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($resources)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">
                                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                                No resources found
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($resources as $resource): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($resource['thumbnail_image'])): ?>
                                                        <img src="<?= htmlspecialchars($resource['thumbnail_image']) ?>"
                                                             alt="<?= htmlspecialchars($resource['resource_name']) ?>"
                                                             class="resource-thumbnail">
                                                    <?php else: ?>
                                                        <div class="resource-thumbnail bg-secondary d-flex align-items-center justify-content-center text-white">
                                                            <i class="fas fa-image"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($resource['resource_name']) ?></strong>
                                                    <?php if ($resource['is_external_resource']): ?>
                                                        <span class="badge badge-external ms-2">
                                                            <i class="fas fa-external-link-alt"></i> External
                                                        </span>
                                                    <?php endif; ?>
                                                    <div class="small text-muted">
                                                        <?= htmlspecialchars(substr($resource['description'], 0, 60)) ?>...
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?= htmlspecialchars($resource['resource_type_name'] ?? 'N/A') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                        $resource_categories = !empty($resource['category_names'])
                                                            ? explode('||', $resource['category_names'])
                                                            : (!empty($resource['category_name']) ? [$resource['category_name']] : []);
                                                    ?>
                                                    <?php if (empty($resource_categories)): ?>
                                                        N/A
                                                    <?php else: ?>
                                                        <?php foreach ($resource_categories as $cat_name): ?>
                                                            <?php $pill = $category_pills[$cat_name] ?? null; ?>
                                                            <?php if ($pill): ?>
                                                                <span class="badge me-1" title="<?= htmlspecialchars($cat_name) ?>" style="background-color: <?= $pill['bg'] ?>; border: 1px solid <?= $pill['border'] ?>; color: <?= $pill['color'] ?>;"><?= $pill['abbr'] ?></span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary me-1"><?= htmlspecialchars($cat_name) ?></span>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (($resource['status'] ?? '') === 'awaiting_approval'): ?>
                                                        <span class="badge badge-awaiting">Awaiting Approval</span>
                                                    <?php elseif ($resource['is_published']): ?>
                                                        <span class="badge badge-published">Published</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-draft">Draft</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <i class="fas fa-eye"></i> <?= number_format($resource['view_count']) ?>
                                                </td>
                                                <td>
                                                    <small><?= date('M d, Y', strtotime($resource['created_at'])) ?></small>
                                                    <?php if ($resource['created_by_username']): ?>
                                                        <div class="small text-muted">by <?= htmlspecialchars($resource['created_by_username']) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end table-actions">
                                                    <?php if (!isContentCreator() && ($resource['status'] ?? '') === 'awaiting_approval'): ?>
                                                    <a href="?action=approve&id=<?= $resource['id'] ?>"
                                                       class="btn btn-sm btn-success"
                                                       title="Approve & Publish"
                                                       onclick="return confirm('Approve and publish this resource?')">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    <a href="edit-resource.php?id=<?= $resource['id'] ?>"
                                                       class="btn btn-sm btn-outline-primary"
                                                       title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?action=delete&id=<?= $resource['id'] ?>"
                                                       class="btn btn-sm btn-outline-danger"
                                                       title="Delete"
                                                       onclick="return confirm('Are you sure you want to delete this resource?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&resource_type=<?= urlencode($resource_type_filter) ?>&status=<?= urlencode($status_filter) ?>">
                                                Previous
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&resource_type=<?= urlencode($resource_type_filter) ?>&status=<?= urlencode($status_filter) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&resource_type=<?= urlencode($resource_type_filter) ?>&status=<?= urlencode($status_filter) ?>">
                                                Next
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>

                            <div class="text-center text-muted small">
                                Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total_resources) ?> of <?= $total_resources ?> resources
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
