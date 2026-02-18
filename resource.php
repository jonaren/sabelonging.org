<?php
require_once 'admin/resources-config.php';

// Get resource by slug
$slug = $_GET['slug'] ?? '';

if (!$slug) {
    header('HTTP/1.0 404 Not Found');
    include '404.php';
    exit;
}

$stmt = $resources_pdo->prepare("
    SELECT r.*,
           rt.name as resource_type_name,
           rt.icon as resource_type_icon,
           c.name as category_name,
           l.name as language_name,
           m.name as ministry_name,
           au.username as created_by_name
    FROM resources r
    LEFT JOIN resource_types rt ON r.resource_type_id = rt.id
    LEFT JOIN categories c ON r.category_id = c.id
    LEFT JOIN languages l ON r.language_id = l.id
    LEFT JOIN ministries m ON r.ministry_id = m.id
    LEFT JOIN admin_users au ON r.created_by = au.id
    WHERE r.slug = ? AND r.is_published = 1
");

$stmt->execute([$slug]);
$resource = $stmt->fetch();

if (!$resource) {
    header('HTTP/1.0 404 Not Found');
    include '404.php';
    exit;
}

// Increment view count
$update_views = $resources_pdo->prepare("UPDATE resources SET view_count = view_count + 1 WHERE id = ?");
$update_views->execute([$resource['id']]);

// Get additional files
$files_stmt = $resources_pdo->prepare("SELECT * FROM resource_files WHERE resource_id = ? ORDER BY created_at ASC");
$files_stmt->execute([$resource['id']]);
$files = $files_stmt->fetchAll();

// Get related resources (same category or type)
$related_stmt = $resources_pdo->prepare("
    SELECT r.*, rt.name as resource_type_name, rt.icon as resource_type_icon,
           c.name as category_name
    FROM resources r
    LEFT JOIN resource_types rt ON r.resource_type_id = rt.id
    LEFT JOIN categories c ON r.category_id = c.id
    WHERE r.id != ?
    AND r.is_published = 1
    AND (r.category_id = ? OR r.resource_type_id = ?)
    ORDER BY r.created_at DESC
    LIMIT 3
");

$related_stmt->execute([$resource['id'], $resource['category_id'], $resource['resource_type_id']]);
$related_resources = $related_stmt->fetchAll();

// Resource type icons mapping
$resourceTypeIcons = [
    'Article' => 'fas fa-file-alt',
    'Video' => 'fas fa-video',
    'Book' => 'fas fa-book',
    'Course' => 'fas fa-graduation-cap',
    'Tool' => 'fas fa-tools',
    'Website' => 'fas fa-globe',
    'Podcast' => 'fas fa-podcast',
    'Document' => 'fas fa-file-text',
    'Guide' => 'fas fa-book-open',
    'Template' => 'fas fa-file-code',
    'PDF' => 'fas fa-file-pdf',
    'Presentation' => 'fas fa-presentation',
    'Workshop' => 'fas fa-chalkboard-teacher',
    'Webinar' => 'fas fa-desktop',
    'Tutorial' => 'fas fa-play-circle',
    'Study' => 'fas fa-clipboard-list',
    'Research' => 'fas fa-search',
    'Report' => 'fas fa-chart-bar'
];

$typeIcon = $resourceTypeIcons[$resource['resource_type_name']] ?? 'fas fa-bookmark';

// Placeholder images for when ThumbnailImage is empty
$placeholderImages = [
    'https://sabelonging.org/images/tn-1.jpg',
    'https://sabelonging.org/images/tn-2.jpg',
    'https://sabelonging.org/images/tn-3.jpg',
    'https://sabelonging.org/images/tn-4.jpg',
    'https://sabelonging.org/images/tn-5.jpg',
    'https://sabelonging.org/images/tn-6.jpg',
    'https://sabelonging.org/images/tn-7.jpg',
    'https://sabelonging.org/images/tn-8.jpg'
];

function getRandomPlaceholder($placeholders) {
    return $placeholders[array_rand($placeholders)];
}

$thumbnailSrc = $resource['thumbnail_image'] ?: getRandomPlaceholder($placeholderImages);

// Set page meta for header
$page_title = htmlspecialchars($resource['resource_name']) . ' - Belonging Resources';
$meta_description = $resource['description'];
$meta_keywords = $resource['meta_data'];
$og_title = $resource['resource_name'];
$og_description = $resource['description'];
$og_image = $thumbnailSrc;
$og_url = RESOURCES_SITE_URL . '/resource.php?slug=' . urlencode($slug);
$og_type = 'article';
?>
<?php include 'includes/header.php'; ?>
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="resources.html">Resources</a></li>
                    <?php if ($resource['category_name']): ?>
                        <li class="breadcrumb-item"><?= htmlspecialchars($resource['category_name']) ?></li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($resource['resource_name']) ?></li>
                </ol>
            </nav>
            
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-4 mb-3"><?= htmlspecialchars($resource['resource_name']) ?></h1>
                    <p class="lead mb-4"><?= htmlspecialchars($resource['description']) ?></p>
                    
                    <?php if ($resource['button_link']): ?>
                        <a href="<?= htmlspecialchars($resource['button_link']) ?>" target="_blank" class="btn btn-primary btn-lg me-3">
                            <i class="fas fa-external-link-alt"></i> Access Resource
                        </a>
                    <?php endif; ?>
                    
                    <a href="resources.html" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left"></i> Back to Resources
                    </a>
                </div>
                
                <div class="col-lg-4">
                    <img src="<?= htmlspecialchars($thumbnailSrc) ?>" 
                         alt="<?= htmlspecialchars($resource['resource_name']) ?>" 
                         class="hero-image">
                </div>
            </div>
        </div>
    </section>

    <div class="container mt-5 mb-5">
        <!-- Resource Meta Information -->
        <div class="resource-meta mb-3">
            <div class="row">
                <div class="col-md-8">
                    <h5 class="mb-3">Resource Details</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($resource['resource_type_name']): ?>
                            <span class="badge tag tag-resource-type">
                                <i class="<?= $typeIcon ?>"></i> <?= htmlspecialchars($resource['resource_type_name']) ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($resource['category_name']): ?>
                            <span class="badge tag tag-category">
                                <i class="fas fa-tag"></i> <?= htmlspecialchars($resource['category_name']) ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($resource['ministry_name']): ?>
                            <span class="badge tag tag-ministry">
                                <i class="fas fa-church"></i> <?= htmlspecialchars($resource['ministry_name']) ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($resource['language_name']): ?>
                            <span class="badge tag tag-language">
                                <i class="fas fa-globe"></i> <?= htmlspecialchars($resource['language_name']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-4 text-md-end">
                    <small class="text-muted">
                        <i class="fas fa-calendar"></i> 
                        Added <?= date('F j, Y', strtotime($resource['created_at'])) ?>
                    </small>
                </div>
            </div>
        </div>

        <!-- About Content -->
        <?php if ($resource['about_content']): ?>
            <div class="content-section">
                <h4 class="mb-4">About This Resource</h4>
                <div class="content-body">
                    <?= $resource['about_content'] ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Additional Files -->
        <?php if (!empty($files)): ?>
            <div class="content-section">
                <h4 class="mb-4">
                    <i class="fas fa-paperclip"></i> 
                    Additional Files (<?= count($files) ?>)
                </h4>
                
                <div class="row">
                    <?php foreach ($files as $file): ?>
                        <?php
                        $extension = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                        $iconClass = 'file-default';
                        $icon = 'fas fa-file';
                        
                        switch ($extension) {
                            case 'pdf':
                                $iconClass = 'file-pdf';
                                $icon = 'fas fa-file-pdf';
                                break;
                            case 'doc':
                            case 'docx':
                                $iconClass = 'file-doc';
                                $icon = 'fas fa-file-word';
                                break;
                            case 'xls':
                            case 'xlsx':
                                $iconClass = 'file-xls';
                                $icon = 'fas fa-file-excel';
                                break;
                            case 'ppt':
                            case 'pptx':
                                $iconClass = 'file-ppt';
                                $icon = 'fas fa-file-powerpoint';
                                break;
                            case 'jpg':
                            case 'jpeg':
                            case 'png':
                            case 'gif':
                                $iconClass = 'file-img';
                                $icon = 'fas fa-file-image';
                                break;
                        }
                        ?>
                        <div class="col-12 col-md-6 mb-3">
                            <div class="file-item">
                                <div class="d-flex align-items-center">
                                    <div class="file-icon <?= $iconClass ?> me-3">
                                        <i class="<?= $icon ?>"></i>
                                    </div>
                                    <div class="flex-grow-1 text-truncate">
                                        <h6 class="mb-1 text-truncate"><?= htmlspecialchars($file['display_name']) ?></h6>
                                        <small class="text-muted">
                                            <?= formatFileSize($file['file_size']) ?> •
                                            <?= strtoupper($extension) ?>
                                        </small>
                                    </div>
                                    <a href="<?= htmlspecialchars($file['file_path']) ?>"
                                       class="btn btn-outline-primary btn-sm ms-2 flex-shrink-0" download>
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Related Resources -->
        <?php if (!empty($related_resources)): ?>
            <div class="content-section">
                <h4 class="mb-4">
                    <i class="fas fa-lightbulb"></i> 
                    Related Resources
                </h4>
                
                <div class="row">
                    <?php foreach ($related_resources as $related): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card related-card">
                                <img src="<?= htmlspecialchars($related['thumbnail_image'] ?: getRandomPlaceholder($placeholderImages)) ?>" 
                                     class="card-img-top" alt="<?= htmlspecialchars($related['resource_name']) ?>">
                                <div class="card-body">
                                    <h6 class="card-title"><?= htmlspecialchars($related['resource_name']) ?></h6>
                                    <p class="card-text text-muted small">
                                        <?= htmlspecialchars(substr($related['description'], 0, 80)) ?>
                                        <?= strlen($related['description']) > 80 ? '...' : '' ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <?= htmlspecialchars($related['resource_type_name']) ?>
                                        </small>
                                        <a href="resource.php?slug=<?= urlencode($related['slug']) ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            View
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="text-center mt-4 mb-5">
            <?php if ($resource['button_link']): ?>
                <a href="<?= htmlspecialchars($resource['button_link']) ?>" target="_blank"
                   class="btn btn-primary btn-lg me-3 mb-3">
                    <i class="fas fa-external-link-alt"></i> Access Resource
                </a>
            <?php endif; ?>

            <a href="resources.html" class="btn btn-outline-primary btn-lg mb-3">
                <i class="fas fa-search"></i> Browse More Resources
            </a>
        </div>
    </div>
<?php include 'includes/footer.php'; ?>