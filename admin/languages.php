<?php
require_once 'resources-config.php';
requireLogin();

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = sanitizeInput($_POST['name'] ?? '');
        $code = strtolower(sanitizeInput($_POST['code'] ?? ''));

        if ($name && $code) {
            try {
                $stmt = $resources_pdo->prepare("INSERT INTO languages (name, code) VALUES (?, ?)");
                $stmt->execute([$name, $code]);
                flashMessage('success', 'Language added successfully');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    flashMessage('error', 'Language name or code already exists');
                } else {
                    flashMessage('error', 'Error adding language: ' . $e->getMessage());
                }
            }
        } else {
            flashMessage('error', 'Language name and code are required');
        }
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = sanitizeInput($_POST['name'] ?? '');
        $code = strtolower(sanitizeInput($_POST['code'] ?? ''));

        if ($id && $name && $code) {
            try {
                $stmt = $resources_pdo->prepare("UPDATE languages SET name = ?, code = ? WHERE id = ?");
                $stmt->execute([$name, $code, $id]);
                flashMessage('success', 'Language updated successfully');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    flashMessage('error', 'Language name or code already exists');
                } else {
                    flashMessage('error', 'Error updating language: ' . $e->getMessage());
                }
            }
        } else {
            flashMessage('error', 'Language name and code are required');
        }
    }

    header('Location: languages.php');
    exit;
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    try {
        // Check if language is being used
        $stmt = $resources_pdo->prepare("SELECT COUNT(*) as count FROM resources WHERE language_id = ?");
        $stmt->execute([$id]);
        $usage = $stmt->fetch();

        if ($usage['count'] > 0) {
            flashMessage('error', 'Cannot delete language. It is being used by ' . $usage['count'] . ' resource(s).');
        } else {
            $stmt = $resources_pdo->prepare("DELETE FROM languages WHERE id = ?");
            $stmt->execute([$id]);
            flashMessage('success', 'Language deleted successfully');
        }
    } catch (Exception $e) {
        flashMessage('error', 'Error deleting language: ' . $e->getMessage());
    }

    header('Location: languages.php');
    exit;
}

// Get all languages with resource counts
$stmt = $resources_pdo->query("
    SELECT l.*, COUNT(r.id) as resource_count
    FROM languages l
    LEFT JOIN resources r ON l.id = r.language_id
    GROUP BY l.id
    ORDER BY l.name
");
$languages = $stmt->fetchAll();

// Get language for editing
$editLanguage = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $resources_pdo->prepare("SELECT * FROM languages WHERE id = ?");
    $stmt->execute([$editId]);
    $editLanguage = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Languages - Admin Panel</title>
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

        .language-item:hover {
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
                            <a class="nav-link active" href="languages.php">
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
                            <h1 class="h2">Languages</h1>
                            <p class="text-muted">Manage available languages for resources</p>
                        </div>
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-globe"></i>
                            </div>
                            <h3 class="mb-0"><?= count($languages) ?></h3>
                            <small class="text-muted">Total Languages</small>
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
                                    <i class="fas fa-<?= $editLanguage ? 'edit' : 'plus' ?>"></i>
                                    <?= $editLanguage ? 'Edit Language' : 'Add New Language' ?>
                                </h5>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST">
                                    <input type="hidden" name="action" value="<?= $editLanguage ? 'edit' : 'add' ?>">
                                    <?php if ($editLanguage): ?>
                                        <input type="hidden" name="id" value="<?= $editLanguage['id'] ?>">
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <label for="name" class="form-label">Language Name *</label>
                                        <input type="text" class="form-control" id="name" name="name"
                                               value="<?= htmlspecialchars($editLanguage['name'] ?? '') ?>"
                                               placeholder="e.g., English, Spanish" required>
                                    </div>

                                    <div class="mb-4">
                                        <label for="code" class="form-label">Language Code *</label>
                                        <input type="text" class="form-control" id="code" name="code"
                                               value="<?= htmlspecialchars($editLanguage['code'] ?? '') ?>"
                                               placeholder="e.g., en, es, fr"
                                               maxlength="10"
                                               pattern="[a-z]{2,10}"
                                               required>
                                        <div class="form-text">Use ISO 639-1 two-letter code (lowercase)</div>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-<?= $editLanguage ? 'save' : 'plus' ?>"></i>
                                            <?= $editLanguage ? 'Update Language' : 'Add Language' ?>
                                        </button>

                                        <?php if ($editLanguage): ?>
                                            <a href="languages.php" class="btn btn-outline-secondary">
                                                <i class="fas fa-times"></i> Cancel
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Quick Reference -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-lightbulb"></i> Common Language Codes</h6>
                            </div>
                            <div class="card-body">
                                <small>
                                    <ul class="list-unstyled mb-0">
                                        <li><strong>en</strong> - English</li>
                                        <li><strong>es</strong> - Spanish</li>
                                        <li><strong>fr</strong> - French</li>
                                        <li><strong>de</strong> - German</li>
                                        <li><strong>pt</strong> - Portuguese</li>
                                        <li><strong>zh</strong> - Chinese</li>
                                        <li><strong>ja</strong> - Japanese</li>
                                        <li><strong>ko</strong> - Korean</li>
                                    </ul>
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Languages List -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list"></i> All Languages (<?= count($languages) ?>)
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($languages)): ?>
                                    <div class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="fas fa-globe fa-4x mb-3"></i>
                                            <h4>No languages yet</h4>
                                            <p>Add your first language.</p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Language</th>
                                                    <th>Code</th>
                                                    <th>Resources</th>
                                                    <th width="120">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($languages as $language): ?>
                                                <tr class="language-item">
                                                    <td>
                                                        <strong><?= htmlspecialchars($language['name']) ?></strong>
                                                    </td>
                                                    <td>
                                                        <code><?= htmlspecialchars($language['code']) ?></code>
                                                    </td>
                                                    <td>
                                                        <?php if ($language['resource_count'] > 0): ?>
                                                            <span class="badge bg-primary"><?= $language['resource_count'] ?></span>
                                                        <?php else: ?>
                                                            <span class="badge bg-light text-muted">0</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <a href="?edit=<?= $language['id'] ?>" class="btn btn-outline-primary" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>

                                                            <?php if ($language['resource_count'] == 0): ?>
                                                                <a href="?action=delete&id=<?= $language['id'] ?>"
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
    <script>
        // Auto-convert code to lowercase
        document.getElementById('code').addEventListener('input', function(e) {
            e.target.value = e.target.value.toLowerCase();
        });
    </script>
</body>
</html>
