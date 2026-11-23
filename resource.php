<?php
require_once 'admin/resources-config.php';
require_once 'includes/priorities.php';

$slug = $_GET['slug'] ?? '';
$resource = null;

if ($slug !== '') {
    $stmt = $resources_pdo->prepare("
        SELECT r.*,
               rt.name AS resource_type_name,
               l.name AS language_name,
               m.name AS ministry_name
        FROM resources r
        LEFT JOIN resource_types rt ON r.resource_type_id = rt.id
        LEFT JOIN languages l ON r.language_id = l.id
        LEFT JOIN ministries m ON r.ministry_id = m.id
        WHERE r.slug = ? AND r.is_published = 1
    ");
    $stmt->execute([$slug]);
    $resource = $stmt->fetch();
}

if (!$resource) {
    http_response_code(404);
    $page_title = 'Resource not found';
    include 'includes/header.php';
    ?>
    <section class="notfound container">
        <h1>We couldn’t find that resource</h1>
        <p class="lead text-secondary mb-4">It may have been moved or unpublished.</p>
        <a class="btn btn-primary" href="/">Browse all resources</a>
    </section>
    <?php
    include 'includes/footer.php';
    exit;
}

$resources_pdo->prepare("UPDATE resources SET view_count = view_count + 1 WHERE id = ?")->execute([$resource['id']]);

// Priorities (multi-category), in logo order
$cat_stmt = $resources_pdo->prepare("
    SELECT c.name FROM resource_categories rc JOIN categories c ON c.id = rc.category_id WHERE rc.resource_id = ?
");
$cat_stmt->execute([$resource['id']]);
$resource_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
$priority_class = ['Youth' => 'youth', 'Spiritual Life' => 'spirit', 'Leadership Development' => 'lead'];
$my_priorities = array_values(array_filter(array_keys($priority_class), fn($p) => in_array($p, $resource_categories, true)));

// Files
$files_stmt = $resources_pdo->prepare("SELECT * FROM resource_files WHERE resource_id = ? ORDER BY created_at ASC");
$files_stmt->execute([$resource['id']]);
$files = $files_stmt->fetchAll();

// Related: shares a priority, else the same format
$related_stmt = $resources_pdo->prepare("
    SELECT DISTINCT r.id, r.resource_name, r.slug, r.description, r.thumbnail_image, r.created_at, rt.name AS resource_type_name
    FROM resources r
    LEFT JOIN resource_types rt ON r.resource_type_id = rt.id
    LEFT JOIN resource_categories rc ON rc.resource_id = r.id
    WHERE r.id != ? AND r.is_published = 1
      AND (rc.category_id IN (SELECT category_id FROM resource_categories WHERE resource_id = ?) OR r.resource_type_id = ?)
    ORDER BY r.created_at DESC
    LIMIT 3
");
$related_stmt->execute([$resource['id'], $resource['id'], $resource['resource_type_id']]);
$related_resources = $related_stmt->fetchAll();

$format_icons = [
    'Article' => 'fa-file-lines', 'Video' => 'fa-video', 'Book' => 'fa-book', 'Course' => 'fa-graduation-cap',
    'Tool' => 'fa-screwdriver-wrench', 'Website' => 'fa-globe', 'Podcast' => 'fa-podcast', 'Document' => 'fa-file',
    'Guide' => 'fa-book-open', 'Template' => 'fa-file-code', 'PDF' => 'fa-file-pdf', 'Presentation' => 'fa-person-chalkboard',
    'Workshop' => 'fa-chalkboard-user', 'Webinar' => 'fa-desktop', 'Tutorial' => 'fa-circle-play', 'Study' => 'fa-clipboard-list',
    'Research' => 'fa-magnifying-glass', 'Report' => 'fa-chart-column', 'Sermon' => 'fa-church',
];
$placeholder = fn($id) => '/images/tn-' . ((int)$id % 8 + 1) . '.jpg';
$thumbnailSrc = $resource['thumbnail_image'] ?: $placeholder($resource['id']);

// Where the main button goes: external URL first, then the button link (same priority as the API)
$access_link = ($resource['is_external_resource'] && $resource['external_url']) ? $resource['external_url'] : ($resource['button_link'] ?: '');

function fileIcon(string $name): string {
    return match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
        'pdf' => 'fa-file-pdf', 'doc', 'docx' => 'fa-file-word', 'xls', 'xlsx' => 'fa-file-excel',
        'ppt', 'pptx' => 'fa-file-powerpoint', 'jpg', 'jpeg', 'png', 'gif', 'webp' => 'fa-file-image',
        default => 'fa-file',
    };
}

$page_title = $resource['resource_name'] . ' - Belonging Resources';
$meta_description = $resource['description'];
$meta_keywords = $resource['meta_data'];
$og_title = $resource['resource_name'];
$og_description = $resource['description'];
$og_image = $thumbnailSrc;
$og_url = RESOURCES_SITE_URL . '/resource.php?slug=' . urlencode($slug);
$og_type = 'article';
include 'includes/header.php';
?>
<article class="rdetail">
    <div class="container">
        <a class="phero-back" href="/"><i class="fas fa-arrow-left" aria-hidden="true"></i> All resources</a>

        <div class="rdetail-grid">
            <div class="rdetail-media">
                <img src="<?= htmlspecialchars($thumbnailSrc) ?>" alt="" onerror="this.onerror=null;this.src='<?= $placeholder($resource['id']) ?>'">
                <?php if ($my_priorities): ?>
                <div class="pribbon" aria-hidden="true">
                    <?php foreach ($my_priorities as $p): ?><span class="<?= $priority_class[$p] ?>"></span><?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div>
                <?php if ($resource['resource_type_name']): ?>
                <div class="rcard-format"><i class="fas <?= $format_icons[$resource['resource_type_name']] ?? 'fa-bookmark' ?>" aria-hidden="true"></i><?= htmlspecialchars($resource['resource_type_name']) ?></div>
                <?php endif; ?>
                <h1><?= htmlspecialchars($resource['resource_name']) ?></h1>
                <p class="rdetail-desc"><?= nl2br(htmlspecialchars($resource['description'])) ?></p>

                <div class="rdetail-actions">
                    <?php if ($access_link): ?>
                    <a class="btn btn-primary" href="<?= htmlspecialchars($access_link) ?>" target="_blank" rel="noopener noreferrer">
                        Open resource <i class="fas fa-arrow-up-right-from-square ms-1" aria-hidden="true"></i><span class="visually-hidden">(opens in a new tab)</span>
                    </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-primary heart-btn" data-save-kind="resources" data-save-id="<?= (int)$resource['id'] ?>" aria-pressed="false">
                        <i class="fas fa-heart" aria-hidden="true"></i> <span class="heart-label">Save</span>
                    </button>
                    <?php if ($files): ?>
                    <a class="btn btn-outline-primary" href="#files"><?= count($files) === 1 ? 'Download file' : 'Download files (' . count($files) . ')' ?></a>
                    <?php endif; ?>
                </div>

                <dl class="facts">
                    <?php if ($my_priorities): ?>
                    <dt>Priority</dt>
                    <dd class="tags"><?php foreach ($my_priorities as $p): ?><a class="tag <?= $priority_class[$p] ?>" href="/?priority=<?= urlencode($p) ?>#library"><?= htmlspecialchars($p) ?></a><?php endforeach; ?></dd>
                    <?php endif; ?>
                    <?php if ($resource['ministry_name']):
                        $m = $resource['ministry_name'];
                        $m_class = preg_match('/spiritual/i', $m) ? 'spirit' : (preg_match('/youth|teen|child|young/i', $m) ? 'youth' : (preg_match('/leader|officer|training/i', $m) ? 'lead' : 'neutral')); ?>
                    <dt>Ministry</dt><dd class="tags"><a class="tag ministry <?= $m_class ?>" href="/?ministry=<?= urlencode($m) ?>#library"><?= htmlspecialchars($m) ?></a></dd>
                    <?php endif; ?>
                    <?php if ($resource['language_name']): ?><dt>Language</dt><dd><?= htmlspecialchars($resource['language_name']) ?></dd><?php endif; ?>
                    <dt>Added</dt><dd><?= date('F j, Y', strtotime($resource['created_at'])) ?></dd>
                </dl>
            </div>
        </div>

        <?php if ($resource['about_content']): ?>
        <section class="rsection">
            <h2>About this resource</h2>
            <div class="prose"><?= $resource['about_content'] ?></div>
        </section>
        <?php endif; ?>

        <?php if ($files): ?>
        <section class="rsection" id="files">
            <h2>Files</h2>
            <div class="files">
                <?php foreach ($files as $file): $ext = strtoupper(pathinfo($file['file_name'], PATHINFO_EXTENSION)); ?>
                <a class="file" href="/download.php?id=<?= (int)$file['id'] ?>">
                    <span class="file-icon"><i class="fas <?= fileIcon($file['file_name']) ?>" aria-hidden="true"></i></span>
                    <span class="d-flex flex-column" style="min-width:0">
                        <span class="file-name"><?= htmlspecialchars($file['display_name'] ?: $file['file_name']) ?></span>
                        <span class="file-meta"><?= $ext ?><?= $file['file_size'] ? ', ' . formatFileSize((int)$file['file_size']) : '' ?></span>
                    </span>
                    <i class="fas fa-download" aria-hidden="true"></i><span class="visually-hidden">Download</span>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($related_resources): ?>
        <section class="rsection">
            <h2>Related resources</h2>
            <div class="rgrid">
                <?php foreach ($related_resources as $rel): ?>
                <a class="rcard" href="/resource.php?slug=<?= urlencode($rel['slug']) ?>">
                    <div class="rcard-media"><img src="<?= htmlspecialchars($rel['thumbnail_image'] ?: $placeholder($rel['id'])) ?>" alt="" loading="lazy"></div>
                    <div class="rcard-body">
                        <?php if ($rel['resource_type_name']): ?><div class="rcard-format"><i class="fas <?= $format_icons[$rel['resource_type_name']] ?? 'fa-bookmark' ?>" aria-hidden="true"></i><?= htmlspecialchars($rel['resource_type_name']) ?></div><?php endif; ?>
                        <h3 class="rcard-title"><?= htmlspecialchars($rel['resource_name']) ?></h3>
                        <p class="rcard-desc"><?= htmlspecialchars($rel['description']) ?></p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</article>
<?php include 'includes/footer.php'; ?>
