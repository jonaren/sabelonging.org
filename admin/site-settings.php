<?php
require_once 'resources-config.php';
requireAdmin();

$page_title = 'Site Settings';
$current_page = 'site-settings';

// Check if table exists and has data
$tableExists = false;
$errorMessage = '';

try {
    $stmt = $resources_pdo->query("SELECT COUNT(*) as count FROM site_settings");
    $result = $stmt->fetch();
    $tableExists = ($result['count'] > 0);
} catch (PDOException $e) {
    $errorMessage = $e->getMessage();
    $tableExists = false;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    try {
        $resources_pdo->beginTransaction();

        foreach ($_POST as $key => $value) {
            if ($key !== 'csrf_token') {
                $stmt = $resources_pdo->prepare("UPDATE site_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?");
                $stmt->execute([$value, $_SESSION['admin_id'], $key]);
            }
        }

        $resources_pdo->commit();
        flashMessage('success', 'Site settings updated successfully!');
        header('Location: site-settings.php');
        exit;
    } catch (Exception $e) {
        $resources_pdo->rollBack();
        flashMessage('danger', 'Error updating settings: ' . $e->getMessage());
    }
}

// Fetch all settings if table exists
$settings = [];
$grouped_settings = [
    'General' => [],
    'Navigation' => [],
    'Footer' => [],
    'Social Media' => []
];

if ($tableExists) {
    $stmt = $resources_pdo->query("SELECT * FROM site_settings ORDER BY id");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($settings as $setting) {
        if (strpos($setting['setting_key'], 'nav_') === 0) {
            $grouped_settings['Navigation'][] = $setting;
        } elseif (strpos($setting['setting_key'], 'footer_') === 0) {
            $grouped_settings['Footer'][] = $setting;
        } elseif (strpos($setting['setting_key'], 'social_') === 0) {
            $grouped_settings['Social Media'][] = $setting;
        } else {
            $grouped_settings['General'][] = $setting;
        }
    }
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Belonging Resources</title>
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

        .btn-primary {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }

        .btn-primary:hover {
            background-color: var(--dark-blue);
            border-color: var(--dark-blue);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.2rem rgba(29, 72, 107, 0.25);
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .table th {
            border-top: none;
            font-weight: 600;
            color: var(--dark-blue);
        }

        .alert {
            border-radius: 10px;
        }

        .form-label strong {
            color: #102a41;
        }

        h3 {
            color: #1d486b;
        }

        .border-bottom {
            border-color: #dee2e6 !important;
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
                        <?php if (isset($_SESSION['admin_role'])): ?>
                            <br><small class="text-muted"><?= ucfirst($_SESSION['admin_role']) ?></small>
                        <?php endif; ?>
                    </div>

                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $current_page === 'resources' ? 'active' : '' ?>" href="resources.php">
                                <i class="fas fa-book"></i> Resources
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="websites.php">
                                <i class="fas fa-window-maximize"></i> Websites
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $current_page === 'categories' ? 'active' : '' ?>" href="categories.php">
                                <i class="fas fa-tags"></i> Categories
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $current_page === 'resource-types' ? 'active' : '' ?>" href="resource-types.php">
                                <i class="fas fa-list"></i> Resource Formats
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $current_page === 'ministries' ? 'active' : '' ?>" href="ministries.php">
                                <i class="fas fa-church"></i> Ministries
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $current_page === 'languages' ? 'active' : '' ?>" href="languages.php">
                                <i class="fas fa-globe"></i> Languages
                            </a>
                        </li>

                        <?php if (function_exists('isAdmin') && isAdmin()): ?>
                        <!-- Admin-only menu items -->
                        <li class="nav-item">
                            <a class="nav-link <?= $current_page === 'users' ? 'active' : '' ?>" href="users.php">
                                <i class="fas fa-users"></i> Admin Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $current_page === 'site-settings' ? 'active' : '' ?>" href="site-settings.php">
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
                    <?php if (!$tableExists): ?>
                        <div class="alert alert-warning" role="alert">
                            <h4 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Setup Required</h4>
                            <p>The site settings table has not been created yet or contains no data. Please run the setup script first.</p>
                            <?php if ($errorMessage): ?>
                                <p class="mb-0"><small class="text-danger">Error: <?= htmlspecialchars($errorMessage) ?></small></p>
                            <?php endif; ?>
                            <hr>
                            <a href="setup_site_settings.php" class="btn btn-primary">
                                <i class="fas fa-cog"></i> Run Setup Now
                            </a>
                            <p class="mt-3 mb-0"><small>Or manually run the SQL from <code>admin/create_site_settings_table.sql</code> in phpMyAdmin using the <strong>usnsan5_belongingResources</strong> database.</small></p>
                        </div>
                    <?php else: ?>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h1><i class="fas fa-cog"></i> Site Settings</h1>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>

                        <?php foreach (getFlashMessages() as $flash): ?>
                            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($flash['message']) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endforeach; ?>

                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body">
                                        <p class="text-muted mb-4">
                                            <i class="fas fa-info-circle"></i>
                                            Customize your site's header, footer, navigation, and social media links from this page.
                                        </p>

                                        <form method="POST" id="settingsForm">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

                                            <?php foreach ($grouped_settings as $category => $category_settings): ?>
                                                <?php if (!empty($category_settings)): ?>
                                                    <div class="mb-5">
                                                        <h3 class="mb-3 pb-2 border-bottom">
                                                            <?php if ($category === 'General'): ?>
                                                                <i class="fas fa-home"></i>
                                                            <?php elseif ($category === 'Navigation'): ?>
                                                                <i class="fas fa-bars"></i>
                                                            <?php elseif ($category === 'Footer'): ?>
                                                                <i class="fas fa-shoe-prints"></i>
                                                            <?php else: ?>
                                                                <i class="fas fa-share-alt"></i>
                                                            <?php endif; ?>
                                                            <?= $category ?>
                                                        </h3>

                                                        <div class="row">
                                                            <?php foreach ($category_settings as $setting): ?>
                                                                <div class="col-md-6 mb-3">
                                                                    <label for="<?= $setting['setting_key'] ?>" class="form-label">
                                                                        <strong><?= ucwords(str_replace('_', ' ', $setting['setting_key'])) ?></strong>
                                                                        <?php if ($setting['description']): ?>
                                                                            <br><small class="text-muted"><?= htmlspecialchars($setting['description']) ?></small>
                                                                        <?php endif; ?>
                                                                    </label>

                                                                    <?php if ($setting['setting_type'] === 'textarea'): ?>
                                                                        <textarea
                                                                            class="form-control"
                                                                            id="<?= $setting['setting_key'] ?>"
                                                                            name="<?= $setting['setting_key'] ?>"
                                                                            rows="3"
                                                                        ><?= htmlspecialchars($setting['setting_value']) ?></textarea>
                                                                    <?php elseif ($setting['setting_type'] === 'boolean'): ?>
                                                                        <select class="form-select" id="<?= $setting['setting_key'] ?>" name="<?= $setting['setting_key'] ?>">
                                                                            <option value="1" <?= $setting['setting_value'] == '1' ? 'selected' : '' ?>>Yes</option>
                                                                            <option value="0" <?= $setting['setting_value'] == '0' ? 'selected' : '' ?>>No</option>
                                                                        </select>
                                                                    <?php else: ?>
                                                                        <input
                                                                            type="<?= $setting['setting_type'] === 'email' ? 'email' : 'text' ?>"
                                                                            class="form-control"
                                                                            id="<?= $setting['setting_key'] ?>"
                                                                            name="<?= $setting['setting_key'] ?>"
                                                                            value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                                                            <?= $setting['setting_type'] === 'url' ? 'placeholder="https://example.com"' : '' ?>
                                                                        >
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>

                                            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                                                <button type="submit" class="btn btn-primary btn-lg">
                                                    <i class="fas fa-save"></i> Save All Settings
                                                </button>
                                                <a href="dashboard.php" class="btn btn-secondary">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($tableExists): ?>
    <script>
    // Form validation
    document.getElementById('settingsForm').addEventListener('submit', function(e) {
        const urlInputs = this.querySelectorAll('input[type="text"][placeholder*="https"]');
        let valid = true;

        urlInputs.forEach(input => {
            if (input.value && !input.value.match(/^https?:\/\/.+/)) {
                input.classList.add('is-invalid');
                valid = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });

        if (!valid) {
            e.preventDefault();
            alert('Please enter valid URLs (must start with http:// or https://)');
        }
    });
    </script>
    <?php endif; ?>
</body>
</html>
