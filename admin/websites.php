<?php
require_once 'resources-config.php';
require_once __DIR__ . '/../includes/websites-lib.php';
requireLogin();
ensureWebsiteTables($resources_pdo);

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// ---------- POST handlers ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'Your session expired. Please try again.');
        header('Location: websites.php');
        exit;
    }
    $action = $_POST['action'] ?? '';
    $redirect = 'websites.php';

    try {
        if ($action === 'save_site') {
            $id = (int)($_POST['id'] ?? 0);
            $name = sanitizeInput($_POST['name'] ?? '');
            $url = normalizeWebsiteUrl($_POST['url'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $button_label = sanitizeInput($_POST['button_label'] ?? '') ?: null;
            $display_order = (int)($_POST['display_order'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $tag_ids = array_merge($_POST['tags_type'] ?? [], $_POST['tags_owner'] ?? []);

            if ($name === '' || !$url) {
                flashMessage('danger', $name === '' ? 'Website name is required.' : 'Please enter a valid website address (https://…).');
                header('Location: websites.php' . ($id ? '?edit=' . $id : ''));
                exit;
            }

            $old_url = null;
            if ($id) {
                $stmt = $resources_pdo->prepare("SELECT url FROM websites WHERE id = ?");
                $stmt->execute([$id]);
                $old_url = $stmt->fetchColumn();
                $resources_pdo->prepare("UPDATE websites SET name=?, url=?, description=?, button_label=?, display_order=?, is_active=? WHERE id=?")
                    ->execute([$name, $url, $description, $button_label, $display_order, $is_active, $id]);
            } else {
                $resources_pdo->prepare("INSERT INTO websites (name, url, description, button_label, display_order, is_active, created_by) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$name, $url, $description, $button_label, $display_order, $is_active, $_SESSION['admin_id'] ?? null]);
                $id = (int)$resources_pdo->lastInsertId();
            }
            setWebsiteTags($resources_pdo, $id, $tag_ids);

            // Manual screenshot upload overrides the automatic one
            $uploaded = false;
            if (!empty($_FILES['screenshot']['name']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) throw new Exception('Screenshot must be a JPG, PNG or WebP image.');
                if ($_FILES['screenshot']['size'] > RESOURCES_MAX_FILE_SIZE) throw new Exception('Screenshot is larger than 10MB.');
                $shot = storeWebsiteScreenshot($id, file_get_contents($_FILES['screenshot']['tmp_name']), $ext === 'jpeg' ? 'jpg' : $ext);
                $resources_pdo->prepare("UPDATE websites SET screenshot_url=?, screenshot_source='upload', screenshot_updated_at=NOW(), screenshot_error=NULL WHERE id=?")
                    ->execute([$shot, $id]);
                $uploaded = true;
            }

            flashMessage('success', '"' . $name . '" saved.');
            // New site or changed address: take a fresh screenshot automatically
            if (!$uploaded && ($old_url === null || $old_url !== $url)) {
                $redirect = 'websites.php?capture=' . $id;
            }
        }

        if ($action === 'delete_site') {
            $resources_pdo->prepare("DELETE FROM websites WHERE id = ?")->execute([(int)$_POST['id']]);
            flashMessage('success', 'Website removed from the index.');
        }

        if ($action === 'toggle_site') {
            $resources_pdo->prepare("UPDATE websites SET is_active = 1 - is_active WHERE id = ?")->execute([(int)$_POST['id']]);
        }

        if ($action === 'remove_screenshot') {
            $resources_pdo->prepare("UPDATE websites SET screenshot_url=NULL, screenshot_source=NULL, screenshot_updated_at=NULL WHERE id=?")->execute([(int)$_POST['id']]);
            $redirect = 'websites.php?edit=' . (int)$_POST['id'];
        }

        if ($action === 'add_tag') {
            $group = $_POST['tag_group'] ?? '';
            $tname = sanitizeInput($_POST['tag_name'] ?? '');
            if (isset(WEBSITE_TAG_GROUPS[$group]) && $tname !== '') {
                $resources_pdo->prepare("INSERT INTO website_tags (tag_group, name) VALUES (?, ?)")->execute([$group, $tname]);
                flashMessage('success', 'Tag "' . $tname . '" added.');
            }
            $redirect = 'websites.php#tags';
        }

        if ($action === 'rename_tag') {
            $tname = sanitizeInput($_POST['tag_name'] ?? '');
            if ($tname !== '') {
                $resources_pdo->prepare("UPDATE website_tags SET name = ? WHERE id = ?")->execute([$tname, (int)$_POST['tag_id']]);
                flashMessage('success', 'Tag renamed.');
            }
            $redirect = 'websites.php#tags';
        }

        if ($action === 'delete_tag') {
            $resources_pdo->prepare("DELETE FROM website_tags WHERE id = ?")->execute([(int)$_POST['tag_id']]);
            flashMessage('success', 'Tag deleted.');
            $redirect = 'websites.php#tags';
        }

        if ($action === 'save_screenshot_settings' && isAdmin()) {
            $key = trim($_POST['pagespeed_api_key'] ?? '');
            if ($key !== '' && !str_contains($key, '•')) {
                setWebsiteSetting($resources_pdo, 'pagespeed_api_key', $key);
                flashMessage('success', 'Screenshot API key saved.');
            } elseif (isset($_POST['clear_key'])) {
                setWebsiteSetting($resources_pdo, 'pagespeed_api_key', '');
                flashMessage('success', 'Screenshot API key removed.');
            }
            $redirect = 'websites.php#screenshot-settings';
        }
    } catch (PDOException $e) {
        flashMessage('danger', $e->getCode() == 23000 ? 'That name already exists.' : 'Database error: ' . $e->getMessage());
    } catch (Exception $e) {
        flashMessage('danger', $e->getMessage());
    }

    header('Location: ' . $redirect);
    exit;
}

// ---------- Page data ----------
$sites = getWebsites($resources_pdo);
$tags = getWebsiteTags($resources_pdo);
$has_api_key = getWebsiteSetting($resources_pdo, 'pagespeed_api_key') !== '';

$edit = null;
if (isset($_GET['edit'])) {
    foreach ($sites as $s) if ((int)$s['id'] === (int)$_GET['edit']) $edit = $s;
}
$capture_id = (int)($_GET['capture'] ?? 0);
$active_count = count(array_filter($sites, fn($s) => $s['is_active']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Websites - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-blue: #1d486b; --dark-blue: #102a41; }
        .sidebar { background: var(--dark-blue); min-height: 100vh; }
        .sidebar .nav-link { color: #fff; padding: 0.75rem 1rem; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: var(--primary-blue); color: #fff; }
        .sidebar .nav-link i { width: 20px; margin-right: 10px; }
        .main-content { background-color: #f8f9fa; min-height: 100vh; }
        .btn-primary { background-color: var(--primary-blue); border-color: var(--primary-blue); }
        .btn-primary:hover { background-color: var(--dark-blue); border-color: var(--dark-blue); }
        .form-card { background: white; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); border: none; }
        .form-card-header { background: var(--primary-blue); color: white; border-radius: 15px 15px 0 0; padding: 1rem 1.5rem; }
        .table th { border-top: none; font-weight: 600; color: var(--dark-blue); }
        .form-control:focus, .form-select:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 0.2rem rgba(29, 72, 107, 0.25); }
        .stats-card { background: white; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); padding: 1rem 1.5rem; text-align: center; }
        .stats-icon { font-size: 1.6rem; color: var(--primary-blue); }

        .thumb { width: 120px; aspect-ratio: 16/10; border-radius: 6px; border: 1px solid #dee2e6; background: #eef1f4; overflow: hidden; position: relative; flex: none; }
        .thumb img { width: 100%; height: 100%; object-fit: cover; object-position: top center; display: block; }
        .thumb .ph { position: absolute; inset: 0; display: grid; place-items: center; color: #8a96a3; font-size: 0.75rem; text-align: center; padding: 4px; }
        .addr { font-family: ui-monospace, Menlo, monospace; font-size: 0.8rem; color: #6c757d; word-break: break-all; }
        .tag-badge { font-weight: 500; }
        .tag-type { background: #e7eef5; color: var(--primary-blue); border: 1px solid #c6d6e5; }
        .tag-owner { background: #f6ecee; color: #7a2a35; border: 1px solid #e5c9ce; }
        .tag-checks { max-height: 180px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 6px; padding: 0.5rem 0.75rem; background: #fff; }
        .site-row.inactive td { opacity: 0.55; }
        .preview-shot { width: 100%; aspect-ratio: 16/10; object-fit: cover; object-position: top; border-radius: 6px; border: 1px solid #dee2e6; }
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
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="resources.php"><i class="fas fa-book"></i> Resources</a></li>
                    <li class="nav-item"><a class="nav-link active" href="websites.php"><i class="fas fa-window-maximize"></i> Websites</a></li>
                    <li class="nav-item"><a class="nav-link" href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
                    <li class="nav-item"><a class="nav-link" href="resource-types.php"><i class="fas fa-list"></i> Resource Formats</a></li>
                    <li class="nav-item"><a class="nav-link" href="ministries.php"><i class="fas fa-church"></i> Ministries</a></li>
                    <li class="nav-item"><a class="nav-link" href="languages.php"><i class="fas fa-globe"></i> Languages</a></li>
                    <?php if (isAdmin()): ?>
                    <li class="nav-item"><a class="nav-link" href="users.php"><i class="fas fa-users"></i> Admin Users</a></li>
                    <li class="nav-item"><a class="nav-link" href="site-settings.php"><i class="fas fa-cog"></i> Site Settings</a></li>
                    <?php endif; ?>
                    <li class="nav-item mt-4"><a class="nav-link" href="../websites.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Websites Page</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="pt-3 pb-2 mb-3 border-bottom">
                <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center">
                    <div>
                        <h1 class="h2">Websites</h1>
                        <p class="text-muted mb-0">Choose which websites appear on the public <a href="../websites.php" target="_blank">Websites</a> page.</p>
                    </div>
                    <div class="d-flex gap-3">
                        <div class="stats-card"><div class="stats-icon"><i class="fas fa-window-maximize"></i></div><h3 class="mb-0"><?= $active_count ?></h3><small class="text-muted">Shown publicly</small></div>
                        <div class="stats-card"><div class="stats-icon"><i class="fas fa-eye-slash"></i></div><h3 class="mb-0"><?= count($sites) - $active_count ?></h3><small class="text-muted">Hidden</small></div>
                    </div>
                </div>
            </div>

            <?php foreach (getFlashMessages() as $m): ?>
                <div class="alert alert-<?= $m['type'] === 'error' ? 'danger' : htmlspecialchars($m['type']) ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($m['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>

            <?php if (!$has_api_key): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-camera"></i> <strong>Automatic screenshots are off.</strong>
                    <?= isAdmin() ? 'Add a Google PageSpeed API key in <a href="#screenshot-settings">Screenshot settings</a> below.' : 'Ask a site admin to add a PageSpeed API key.' ?>
                    You can still upload screenshots by hand.
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Add / Edit form -->
                <div class="col-xl-5">
                    <div class="card form-card" id="site-form">
                        <div class="form-card-header">
                            <h5 class="mb-0"><i class="fas fa-<?= $edit ? 'edit' : 'plus' ?>"></i> <?= $edit ? 'Edit Website' : 'Add a Website' ?></h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="action" value="save_site">
                                <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">

                                <div class="mb-3">
                                    <label class="form-label" for="url">Website address *</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="url" name="url" required placeholder="https://example.org" value="<?= htmlspecialchars($edit['url'] ?? '') ?>">
                                        <button type="button" class="btn btn-outline-secondary" id="fetchMeta" title="Fill in the name and description from the website itself"><i class="fas fa-magic"></i> Auto-fill</button>
                                    </div>
                                    <div class="form-text" id="fetchMetaStatus">Paste the address, then click Auto-fill to pull the title and description from the site.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="name">Title *</label>
                                    <input type="text" class="form-control" id="name" name="name" required maxlength="255" value="<?= htmlspecialchars($edit['name'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="description">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($edit['description'] ?? '') ?></textarea>
                                </div>

                                <?php foreach (WEBSITE_TAG_GROUPS as $group => $meta): ?>
                                <div class="mb-3">
                                    <label class="form-label mb-1"><?= $meta['label'] ?></label>
                                    <?php if (empty($tags[$group])): ?>
                                        <div class="form-text">No <?= strtolower($meta['label']) ?> tags yet — <a href="#tags">add some below</a>.</div>
                                    <?php else: ?>
                                    <div class="tag-checks">
                                        <?php foreach ($tags[$group] as $t): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="tags_<?= $group ?>[]" value="<?= $t['id'] ?>" id="t<?= $t['id'] ?>" <?= in_array((int)$t['id'], $edit['tag_ids'] ?? [], true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="t<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>

                                <div class="row g-3 mb-3">
                                    <div class="col-sm-7">
                                        <label class="form-label" for="button_label">Button text</label>
                                        <input type="text" class="form-control" id="button_label" name="button_label" maxlength="100" placeholder="Visit Website" value="<?= htmlspecialchars($edit['button_label'] ?? '') ?>">
                                    </div>
                                    <div class="col-sm-5">
                                        <label class="form-label" for="display_order">Sort order</label>
                                        <input type="number" class="form-control" id="display_order" name="display_order" value="<?= (int)($edit['display_order'] ?? 0) ?>">
                                        <div class="form-text">Lower shows first.</div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="screenshot">Screenshot</label>
                                    <?php if (!empty($edit['screenshot_url'])): ?>
                                        <img class="preview-shot mb-2" src="<?= htmlspecialchars($edit['screenshot_url']) ?>" alt="">
                                    <?php endif; ?>
                                    <input type="file" class="form-control" id="screenshot" name="screenshot" accept=".jpg,.jpeg,.png,.webp">
                                    <div class="form-text">Optional. Leave empty to capture one automatically. Upload one for sites behind a login or that block screenshots.</div>
                                </div>

                                <div class="form-check form-switch mb-4">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?= ($edit['is_active'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active">Show on the public Websites page</label>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $edit ? 'Save Changes' : 'Add Website' ?></button>
                                    <?php if ($edit): ?><a href="websites.php" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
                                </div>
                            </form>
                            <?php if (!empty($edit['screenshot_url'])): ?>
                            <form method="POST" class="mt-2" onsubmit="return confirm('Remove this screenshot?')">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="action" value="remove_screenshot">
                                <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
                                <button class="btn btn-link btn-sm text-danger p-0">Remove current screenshot</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Site list -->
                <div class="col-xl-7">
                    <div class="card form-card">
                        <div class="form-card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-list"></i> Indexed Websites (<?= count($sites) ?>)</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!$sites): ?>
                                <p class="text-muted p-4 mb-0">No websites yet. Add the first one with the form.</p>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <tbody>
                                    <?php foreach ($sites as $s): ?>
                                        <tr class="site-row <?= $s['is_active'] ? '' : 'inactive' ?>" data-site-id="<?= $s['id'] ?>">
                                            <td style="width:136px">
                                                <div class="thumb">
                                                    <?php if ($s['screenshot_url']): ?>
                                                        <img src="<?= htmlspecialchars($s['screenshot_url']) ?>" alt="" loading="lazy">
                                                    <?php else: ?>
                                                        <div class="ph"><?= $s['screenshot_error'] ? '<span class="text-danger">Capture failed</span>' : 'No screenshot' ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($s['name']) ?>
                                                    <?php if (!$s['is_active']): ?><span class="badge bg-secondary ms-1">Hidden</span><?php endif; ?>
                                                </div>
                                                <div class="addr"><?= htmlspecialchars(websiteDisplayAddress($s['url'])) ?></div>
                                                <div class="mt-1">
                                                    <?php foreach ($s['tags'] as $group => $names): foreach ($names as $n): ?>
                                                        <span class="badge tag-badge tag-<?= $group ?>"><?= htmlspecialchars($n) ?></span>
                                                    <?php endforeach; endforeach; ?>
                                                </div>
                                                <?php if ($s['screenshot_error'] && !$s['screenshot_url']): ?>
                                                    <div class="small text-danger mt-1" title="<?= htmlspecialchars($s['screenshot_error']) ?>"><?= htmlspecialchars(mb_strimwidth($s['screenshot_error'], 0, 90, '…')) ?></div>
                                                <?php endif; ?>
                                                <div class="small text-muted capture-status"></div>
                                            </td>
                                            <td class="text-end text-nowrap">
                                                <a href="?edit=<?= $s['id'] ?>#site-form" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                                <button type="button" class="btn btn-sm btn-outline-secondary capture-btn" data-id="<?= $s['id'] ?>" title="Retake screenshot" <?= $has_api_key ? '' : 'disabled' ?>><i class="fas fa-camera"></i></button>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                                    <input type="hidden" name="action" value="toggle_site">
                                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-secondary" title="<?= $s['is_active'] ? 'Hide from public page' : 'Show on public page' ?>"><i class="fas fa-<?= $s['is_active'] ? 'eye-slash' : 'eye' ?>"></i></button>
                                                </form>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Remove <?= htmlspecialchars(addslashes($s['name']), ENT_QUOTES) ?> from the index?')">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                                    <input type="hidden" name="action" value="delete_site">
                                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                                </form>
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

            <!-- Tags -->
            <div class="card form-card mt-4" id="tags">
                <div class="form-card-header"><h5 class="mb-0"><i class="fas fa-tags"></i> Website Tags</h5></div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <?php foreach (WEBSITE_TAG_GROUPS as $group => $meta): ?>
                        <div class="col-lg-6">
                            <h6 class="mb-1"><?= $meta['label'] ?></h6>
                            <p class="small text-muted"><?= $meta['help'] ?></p>
                            <?php foreach ($tags[$group] as $t): ?>
                            <div class="d-flex gap-2 mb-2 align-items-center">
                                <form method="POST" class="d-flex gap-2 flex-grow-1">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="action" value="rename_tag">
                                    <input type="hidden" name="tag_id" value="<?= $t['id'] ?>">
                                    <input type="text" name="tag_name" class="form-control form-control-sm" value="<?= htmlspecialchars($t['name']) ?>" maxlength="100" aria-label="Tag name">
                                    <button class="btn btn-sm btn-outline-primary" title="Save name"><i class="fas fa-check"></i></button>
                                </form>
                                <span class="small text-muted text-nowrap"><?= (int)$t['site_count'] ?> site<?= $t['site_count'] == 1 ? '' : 's' ?></span>
                                <form method="POST" onsubmit="return confirm('Delete this tag? It will be removed from <?= (int)$t['site_count'] ?> site(s).')">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="action" value="delete_tag">
                                    <input type="hidden" name="tag_id" value="<?= $t['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" title="Delete tag"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                            <form method="POST" class="d-flex gap-2 mt-3">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="action" value="add_tag">
                                <input type="hidden" name="tag_group" value="<?= $group ?>">
                                <input type="text" name="tag_name" class="form-control form-control-sm" placeholder="New <?= strtolower($meta['label']) ?> tag" maxlength="100" required>
                                <button class="btn btn-sm btn-primary text-nowrap"><i class="fas fa-plus"></i> Add</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php if (isAdmin()): ?>
            <!-- Screenshot settings -->
            <div class="card form-card mt-4 mb-5" id="screenshot-settings">
                <div class="form-card-header"><h5 class="mb-0"><i class="fas fa-camera"></i> Screenshot settings</h5></div>
                <div class="card-body p-4">
                    <p class="small text-muted">Screenshots are taken with Google's free PageSpeed Insights API. It needs an API key from Google Cloud
                        (APIs &amp; Services → Credentials → Create API key, restricted to the "PageSpeed Insights API").</p>
                    <form method="POST" class="d-flex flex-wrap gap-2">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="save_screenshot_settings">
                        <input type="password" name="pagespeed_api_key" class="form-control" style="max-width: 420px" autocomplete="off"
                               placeholder="<?= $has_api_key ? '•••••••• (key saved — paste a new one to replace)' : 'Paste PageSpeed API key' ?>">
                        <button class="btn btn-primary">Save key</button>
                        <?php if ($has_api_key): ?><button class="btn btn-outline-danger" name="clear_key" value="1" onclick="return confirm('Remove the API key? Automatic screenshots will stop.')">Remove key</button><?php endif; ?>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    const csrf = <?= json_encode($csrf) ?>;
    const post = (data) => fetch('website-actions.php', {
        method: 'POST',
        body: new URLSearchParams({ csrf_token: csrf, ...data }),
    }).then(async (r) => { const j = await r.json(); if (!r.ok || !j.success) throw new Error(j.error || 'Request failed'); return j; });

    // Screenshot capture (takes 15–60 seconds per site)
    function capture(id) {
        const row = document.querySelector(`tr[data-site-id="${id}"]`);
        if (!row) return;
        const btn = row.querySelector('.capture-btn');
        const status = row.querySelector('.capture-status');
        const thumb = row.querySelector('.thumb');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        status.className = 'small text-muted capture-status';
        status.textContent = 'Taking screenshot… this can take up to a minute.';
        post({ action: 'capture', id })
            .then((j) => {
                thumb.innerHTML = '';
                const img = new Image(); img.src = j.screenshot_url; img.alt = '';
                thumb.appendChild(img);
                status.textContent = 'Screenshot updated.';
            })
            .catch((e) => { status.className = 'small text-danger capture-status'; status.textContent = e.message; })
            .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-camera"></i>'; });
    }
    document.querySelectorAll('.capture-btn').forEach((b) => b.addEventListener('click', () => capture(b.dataset.id)));
    <?php if ($capture_id && $has_api_key): ?>capture(<?= $capture_id ?>);<?php endif; ?>

    // Auto-fill title + description from the site
    const fetchBtn = document.getElementById('fetchMeta');
    const fetchStatus = document.getElementById('fetchMetaStatus');
    fetchBtn.addEventListener('click', () => {
        const url = document.getElementById('url').value.trim();
        if (!url) { fetchStatus.textContent = 'Enter an address first.'; return; }
        fetchBtn.disabled = true;
        fetchStatus.textContent = 'Reading the site…';
        post({ action: 'meta', url })
            .then((j) => {
                const name = document.getElementById('name'), desc = document.getElementById('description');
                if (j.title && !name.value) name.value = j.title;
                if (j.description && !desc.value) desc.value = j.description;
                document.getElementById('url').value = j.url;
                fetchStatus.textContent = (j.title || j.description) ? 'Filled in empty fields from the site. Edit as needed.' : 'The site has no title or description to use.';
            })
            .catch((e) => { fetchStatus.textContent = e.message; })
            .finally(() => { fetchBtn.disabled = false; });
    });
})();
</script>
</body>
</html>
