<?php
require_once 'resources-config.php';
requireLogin();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resource_name = sanitizeInput($_POST['resource_name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $about_content = $_POST['about_content'] ?? '';
    $is_external_resource = isset($_POST['is_external_resource']) ? 1 : 0;
    $external_url = sanitizeInput($_POST['external_url'] ?? '');
    if (!empty($external_url)) {
        $external_url = 'https://' . preg_replace('#^https?://#i', '', $external_url);
    }
    $button_link = sanitizeInput($_POST['button_link'] ?? '');
    $meta_data = sanitizeInput($_POST['meta_data'] ?? '');
    $resource_type_id = (int)($_POST['resource_type_id'] ?? 0);
    $category_ids = array_filter(array_map('intval', $_POST['category_ids'] ?? []));
    $language_id = (int)($_POST['language_id'] ?? 0);
    $ministry_id = (int)($_POST['ministry_id'] ?? 0);
    // Content creators cannot publish — resources go to awaiting_approval
    if (isContentCreator()) {
        $is_published = 0;
        $status = 'awaiting_approval';
    } else {
        $is_published = isset($_POST['is_published']) ? 1 : 0;
        $status = $is_published ? 'published' : 'draft';
    }

    // Validation
    if (empty($resource_name)) {
        $errors[] = 'Resource name is required';
    }

    if (empty($description)) {
        $errors[] = 'Description is required';
    }

    if ($resource_type_id === 0) {
        $errors[] = 'Resource type is required';
    }

    if ($is_external_resource && empty($external_url)) {
        $errors[] = 'External URL is required when "External Resource" is checked';
    }

    // Generate slug
    $slug = generateSlug($resource_name);

    // Check if slug already exists
    $stmt = $resources_pdo->prepare("SELECT id FROM resources WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) {
        $slug .= '-' . time();
    }

    if (empty($errors)) {
        try {
            $resources_pdo->beginTransaction();

            // Handle thumbnail upload
            $thumbnail_path = '';
            if (isset($_FILES['thumbnail_image']) && $_FILES['thumbnail_image']['error'] === UPLOAD_ERR_OK) {
                $upload = uploadResourceFile($_FILES['thumbnail_image'], ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                $thumbnail_path = $upload['url'];
            }

            // Insert resource
            $stmt = $resources_pdo->prepare("
                INSERT INTO resources (
                    resource_name, slug, description, about_content, thumbnail_image,
                    is_external_resource, external_url, button_link, meta_data,
                    resource_type_id, language_id,
                    ministry_id, is_published, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $resource_name, $slug, $description, $about_content, $thumbnail_path,
                $is_external_resource, $external_url, $button_link, $meta_data,
                $resource_type_id ?: null,
                $language_id ?: null, $ministry_id ?: null, $is_published, $status, $_SESSION['admin_id']
            ]);

            $resource_id = $resources_pdo->lastInsertId();

            // Insert categories into junction table
            foreach ($category_ids as $cat_id) {
                $resources_pdo->prepare("INSERT IGNORE INTO resource_categories (resource_id, category_id) VALUES (?, ?)")->execute([$resource_id, $cat_id]);
            }

            // Handle additional file uploads (only if not external resource)
            if (!$is_external_resource && isset($_FILES['additional_files'])) {
                foreach ($_FILES['additional_files']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['additional_files']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_data = [
                            'name' => $_FILES['additional_files']['name'][$key],
                            'tmp_name' => $tmp_name,
                            'size' => $_FILES['additional_files']['size'][$key],
                            'type' => $_FILES['additional_files']['type'][$key],
                            'error' => $_FILES['additional_files']['error'][$key]
                        ];

                        $upload = uploadResourceFile($file_data);

                        $stmt = $resources_pdo->prepare("
                            INSERT INTO resource_files (resource_id, file_name, file_path, file_size, file_type, display_name)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");

                        $stmt->execute([
                            $resource_id,
                            $upload['filename'],
                            $upload['path'],
                            $upload['size'],
                            $upload['type'],
                            $upload['original_name']
                        ]);
                    }
                }
            }

            $resources_pdo->commit();
            if (isContentCreator()) {
                flashMessage('warning', 'Resource submitted for Approval');
                try {
                    $submitter_name = $_SESSION['admin_full_name'] ?? $_SESSION['admin_username'] ?? 'A content creator';
                    sendDraftNotification($resource_id, $resource_name, $submitter_name);
                } catch (Exception $mail_e) {
                    // Email failure should not block the user
                }
            } else {
                flashMessage('success', 'Resource created successfully');
            }
            header('Location: resources.php');
            exit;

        } catch (Exception $e) {
            $resources_pdo->rollBack();
            $errors[] = 'Error creating resource: ' . $e->getMessage();
        }
    }
}

// Get dropdown options
$resource_types = $resources_pdo->query("SELECT id, name FROM resource_types WHERE is_active = 1 ORDER BY name")->fetchAll();
$categories = $resources_pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name")->fetchAll();
$languages = $resources_pdo->query("SELECT id, name FROM languages WHERE is_active = 1 ORDER BY name")->fetchAll();
$ministries = $resources_pdo->query("SELECT id, name FROM ministries WHERE is_active = 1 ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Resource - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tiny.cloud/1/ynimyzuqoznn8azaqflupec9j1dmyn6iwd2xfbakgpq9h7ak/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
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

        .form-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .form-section-header {
            background: var(--primary-blue);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 10px 10px 0 0;
            margin: 0;
        }

        .preview-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 5px;
            border: 2px dashed #ddd;
            padding: 10px;
        }

        #external_url_group {
            display: none;
        }

        #additional_files_section {
            transition: opacity 0.3s ease;
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
                        <h1 class="h2">Add Resource</h1>
                        <a href="resources.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Resources
                        </a>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <!-- Basic Information -->
                    <div class="form-section">
                        <h5 class="form-section-header">Basic Information</h5>
                        <div class="p-4">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="resource_name" class="form-label">Resource Name *</label>
                                        <input type="text" class="form-control" id="resource_name" name="resource_name"
                                               value="<?= htmlspecialchars($_POST['resource_name'] ?? '') ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">Short Description *</label>
                                        <textarea class="form-control" id="description" name="description" rows="3" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                        <div class="form-text">This will appear in the resource cards on the main page.</div>
                                    </div>

                                    <!-- External Resource Checkbox -->
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="is_external_resource" name="is_external_resource"
                                                   <?= isset($_POST['is_external_resource']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="is_external_resource">
                                                <strong>This is an external resource</strong>
                                            </label>
                                            <div class="form-text">Check this if the resource is hosted elsewhere (e.g., YouTube, external website)</div>
                                        </div>
                                    </div>

                                    <!-- External URL Field (shows when checkbox is checked) -->
                                    <div class="mb-3" id="external_url_group">
                                        <label for="external_url" class="form-label">External Resource URL *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">https://</span>
                                            <input type="text" class="form-control" id="external_url" name="external_url"
                                                   value="<?= htmlspecialchars(preg_replace('#^https?://#i', '', $_POST['external_url'] ?? '')) ?>"
                                                   placeholder="www.example.com/resource">
                                        </div>
                                        <div class="form-text">The URL where users will be redirected when they click the resource card.</div>
                                    </div>

                                    <!-- Legacy Button Link (optional, for backward compatibility) -->
                                    <div class="mb-3">
                                        <label for="button_link" class="form-label">Additional Link (Optional)</label>
                                        <input type="url" class="form-control" id="button_link" name="button_link"
                                               value="<?= htmlspecialchars($_POST['button_link'] ?? '') ?>"
                                               placeholder="https://example.com">
                                        <div class="form-text">Optional secondary link for the resource.</div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="thumbnail_image" class="form-label">Thumbnail Image</label>
                                        <input type="file" class="form-control" id="thumbnail_image" name="thumbnail_image"
                                               accept="image/*" onchange="previewImage(this)">
                                        <div id="image_preview" class="mt-2"></div>
                                    </div>

                                    <?php if (!isContentCreator()): ?>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_published" name="is_published"
                                                   <?= isset($_POST['is_published']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="is_published">
                                                Publish immediately
                                            </label>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <div class="mb-3">
                                        <div class="alert alert-info py-2 mb-0">
                                            <i class="fas fa-info-circle"></i> Your resource will be submitted for admin approval before publishing.
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Categorization -->
                    <div class="form-section">
                        <h5 class="form-section-header">Categorization</h5>
                        <div class="p-4">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="resource_type_id" class="form-label">Resource Format *</label>
                                        <select class="form-select" id="resource_type_id" name="resource_type_id" required>
                                            <option value="">Select Type</option>
                                            <?php foreach ($resource_types as $type): ?>
                                                <option value="<?= $type['id'] ?>"
                                                        <?= ($_POST['resource_type_id'] ?? '') == $type['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($type['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Priority / Category</label>
                                        <?php
                                        $selected_cat_ids = array_map('intval', $_POST['category_ids'] ?? []);
                                        foreach ($categories as $category): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                       id="cat_<?= $category['id'] ?>"
                                                       name="category_ids[]"
                                                       value="<?= $category['id'] ?>"
                                                       <?= in_array((int)$category['id'], $selected_cat_ids) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="cat_<?= $category['id'] ?>">
                                                    <?= htmlspecialchars($category['name']) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="language_id" class="form-label">Language</label>
                                        <select class="form-select" id="language_id" name="language_id">
                                            <option value="">Select Language</option>
                                            <?php foreach ($languages as $language): ?>
                                                <option value="<?= $language['id'] ?>"
                                                        <?= ($_POST['language_id'] ?? '') == $language['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($language['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="ministry_id" class="form-label">Ministry</label>
                                        <select class="form-select" id="ministry_id" name="ministry_id">
                                            <option value="">Select Ministry</option>
                                            <?php foreach ($ministries as $ministry): ?>
                                                <option value="<?= $ministry['id'] ?>"
                                                        <?= ($_POST['ministry_id'] ?? '') == $ministry['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($ministry['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="meta_data" class="form-label">Keywords/Tags</label>
                                <input type="text" class="form-control" id="meta_data" name="meta_data"
                                       value="<?= htmlspecialchars($_POST['meta_data'] ?? '') ?>"
                                       placeholder="Enter keywords separated by commas">
                                <div class="form-text">These help with search functionality.</div>
                            </div>
                        </div>
                    </div>

                    <!-- About Content -->
                    <div class="form-section">
                        <h5 class="form-section-header">About This Resource</h5>
                        <div class="p-4">
                            <div class="mb-3">
                                <label for="about_content" class="form-label">Detailed Description</label>
                                <textarea class="form-control" id="about_content" name="about_content" rows="10"><?= htmlspecialchars($_POST['about_content'] ?? '') ?></textarea>
                                <div class="form-text">This content will appear on the resource's dedicated page.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Files (hidden when external resource is checked) -->
                    <div class="form-section" id="additional_files_section">
                        <h5 class="form-section-header">Additional Files</h5>
                        <div class="p-4">
                            <div class="mb-3">
                                <label for="additional_files" class="form-label">Upload Files</label>
                                <input type="file" class="form-control" id="additional_files" name="additional_files[]"
                                       multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif">
                                <div class="form-text">
                                    You can upload multiple files. Allowed types: PDF, Word, Excel, PowerPoint, Images
                                </div>
                            </div>

                            <div id="file_list" class="mt-3"></div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="d-flex justify-content-between mb-4">
                        <a href="resources.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <div>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Create Resource
                            </button>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize TinyMCE
        tinymce.init({
            selector: '#about_content',
            height: 400,
            menubar: false,
            api_key: 'ynimyzuqoznn8azaqflupec9j1dmyn6iwd2xfbakgpq9h7ak',
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'help', 'wordcount'
            ],
            toolbar: 'undo redo | blocks | ' +
                'bold italic backcolor | alignleft aligncenter ' +
                'alignright alignjustify | bullist numlist outdent indent | ' +
                'removeformat | help',
            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif; font-size: 14px }',
            branding: false,
            promotion: false
        });

        // Toggle external URL field based on checkbox
        const externalResourceCheckbox = document.getElementById('is_external_resource');
        const externalUrlGroup = document.getElementById('external_url_group');
        const externalUrlInput = document.getElementById('external_url');
        const additionalFilesSection = document.getElementById('additional_files_section');
        const additionalFilesInput = document.getElementById('additional_files');

        function toggleExternalResource() {
            if (externalResourceCheckbox.checked) {
                externalUrlGroup.style.display = 'block';
                externalUrlInput.setAttribute('required', 'required');
                additionalFilesSection.style.opacity = '0.5';
                additionalFilesInput.disabled = true;
            } else {
                externalUrlGroup.style.display = 'none';
                externalUrlInput.removeAttribute('required');
                additionalFilesSection.style.opacity = '1';
                additionalFilesInput.disabled = false;
            }
        }

        externalResourceCheckbox.addEventListener('change', toggleExternalResource);
        toggleExternalResource(); // Initial state

        // Image preview function
        function previewImage(input) {
            const preview = document.getElementById('image_preview');
            preview.innerHTML = '';

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'preview-image';
                    preview.appendChild(img);
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // File list display
        document.getElementById('additional_files').addEventListener('change', function(e) {
            const fileList = document.getElementById('file_list');
            fileList.innerHTML = '';

            if (e.target.files.length > 0) {
                const list = document.createElement('div');
                list.className = 'alert alert-info';
                list.innerHTML = '<strong>Selected files:</strong><ul class="mb-0 mt-2"></ul>';

                const ul = list.querySelector('ul');

                for (let i = 0; i < e.target.files.length; i++) {
                    const li = document.createElement('li');
                    li.textContent = e.target.files[i].name + ' (' + formatFileSize(e.target.files[i].size) + ')';
                    ul.appendChild(li);
                }

                fileList.appendChild(list);
            }
        });

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    </script>
</body>
</html>
