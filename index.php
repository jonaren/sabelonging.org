<?php
// App Engine Standard PHP uses index.php as a router script.
// Route to the correct PHP file if it exists.
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__ . $uri;
if ($uri !== '/' && $uri !== '/index.php' && file_exists($file) && !is_dir($file) && substr($uri, -4) === '.php') {
    chdir(dirname($file));
    require $file;
    exit;
}

require_once 'admin/config.php';

// Get all resources from database
try {
    $stmt = $pdo->query("
        SELECT r.*, 
               rt.name as resource_type_name, 
               rt.icon as resource_type_icon,
               c.name as category_name,
               l.name as language_name,
               m.name as ministry_name
        FROM resources r 
        LEFT JOIN resource_types rt ON r.resource_type_id = rt.id 
        LEFT JOIN categories c ON r.category_id = c.id 
        LEFT JOIN languages l ON r.language_id = l.id 
        LEFT JOIN ministries m ON r.ministry_id = m.id 
        WHERE r.is_published = 1
        ORDER BY r.created_at DESC
    ");
    $resources = $stmt->fetchAll();
    
    // Get filter options
    $resource_types = $pdo->query("
        SELECT DISTINCT rt.name 
        FROM resource_types rt 
        INNER JOIN resources r ON rt.id = r.resource_type_id 
        WHERE r.is_published = 1 
        ORDER BY rt.name
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    $categories = $pdo->query("
        SELECT DISTINCT c.name 
        FROM categories c 
        INNER JOIN resources r ON c.id = r.category_id 
        WHERE r.is_published = 1 
        ORDER BY c.name
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    $languages = $pdo->query("
        SELECT DISTINCT l.name 
        FROM languages l 
        INNER JOIN resources r ON l.id = r.language_id 
        WHERE r.is_published = 1 
        ORDER BY l.name
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    $ministries = $pdo->query("
        SELECT DISTINCT m.name 
        FROM ministries m 
        INNER JOIN resources r ON m.id = r.ministry_id 
        WHERE r.is_published = 1 
        ORDER BY m.name
    ")->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    $resources = [];
    $resource_types = [];
    $categories = [];
    $languages = [];
    $ministries = [];
    $database_error = "Error loading resources: " . $e->getMessage();
}

// Set page title for header
$page_title = 'Belonging Resources';
?>
<?php include 'includes/header.php'; ?>

<?php include 'includes/banner.php'; ?>
  

    <div class="container my-5">

        <!-- Database Error Alert -->
        <?php if (isset($database_error)): ?>
            <div class="alert alert-warning" role="alert">
                <h4 class="alert-heading">Database Connection Issue</h4>
                <p><?= htmlspecialchars($database_error) ?></p>
                <hr>
                <p class="mb-0">Please check your database configuration in admin/config.php</p>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card filter-card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search Resources</label>
                        <input type="text" class="form-control" id="search" placeholder="Search by name, description, or keywords...">
                    </div>
                    <div class="col-md-2">
                        <label for="resourceType" class="form-label">Resource Format</label>
                        <select class="form-select" id="resourceType">
                            <option value="">All Types</option>
                            <?php foreach ($resource_types as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="category" class="form-label">Category</label>
                        <select class="form-select" id="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="language" class="form-label">Language</label>
                        <select class="form-select" id="language">
                            <option value="">All Languages</option>
                            <?php foreach ($languages as $language): ?>
                                <option value="<?= htmlspecialchars($language) ?>"><?= htmlspecialchars($language) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="ministry" class="form-label">Ministry</label>
                        <select class="form-select" id="ministry">
                            <option value="">All Ministries</option>
                            <?php foreach ($ministries as $ministry): ?>
                                <option value="<?= htmlspecialchars($ministry) ?>"><?= htmlspecialchars($ministry) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Info -->
        <div class="results-info mb-3" id="resultsCount"></div>
        
        <!-- Loading State -->
        <div class="text-center py-5" id="loading" style="display: none;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Loading resources...</p>
        </div>
        
       
        <!-- Resources Grid -->
        <div class="row g-4" id="resourcesGrid"></div>
        
        <!-- Pagination -->
        <div class="text-center mt-5" id="paginationContainer" style="display: none;">
            <div class="pagination-info mb-3" id="paginationInfo"></div>
            <button class="btn btn-primary btn-lg load-more-btn" id="loadMoreButton">Load More Resources</button>
        </div>
        
        <!-- No Results -->
        <div class="text-center py-5" id="noResults" style="display: none;">
            <div class="display-6 text-muted mb-3"><i class="fas fa-search"></i></div>
            <h3 class="text-muted">No resources found</h3>
            <p class="text-muted">Try adjusting your filters or search terms.</p>
        </div>
    </div>

    <script>
        let allResources = <?= json_encode($resources) ?>;
        let filteredResources = [];
        let displayedCount = 0;
        const resourcesPerPage = 9;

        // Placeholder images for when ThumbnailImage is empty
        const placeholderImages = [
            '/images/tn-1.jpg',
            '/images/tn-2.jpg',
            '/images/tn-3.jpg',
            '/images/tn-4.jpg',
            '/images/tn-5.jpg',
            '/images/tn-6.jpg',
            '/images/tn-7.jpg',
            '/images/tn-8.jpg'
        ];

        // Resource type icons mapping (Font Awesome icons)
        const resourceTypeIcons = {
            'Article': '<i class="fas fa-file-alt"></i>',
            'Video': '<i class="fas fa-video"></i>',
            'Book': '<i class="fas fa-book"></i>',
            'Course': '<i class="fas fa-graduation-cap"></i>',
            'Tool': '<i class="fas fa-tools"></i>',
            'Website': '<i class="fas fa-globe"></i>',
            'Podcast': '<i class="fas fa-podcast"></i>',
            'Document': '<i class="fas fa-file-text"></i>',
            'Guide': '<i class="fas fa-book-open"></i>',
            'Template': '<i class="fas fa-file-code"></i>',
            'PDF': '<i class="fas fa-file-pdf"></i>',
            'Presentation': '<i class="fas fa-presentation"></i>',
            'Workshop': '<i class="fas fa-chalkboard-teacher"></i>',
            'Webinar': '<i class="fas fa-desktop"></i>',
            'Tutorial': '<i class="fas fa-play-circle"></i>',
            'Study': '<i class="fas fa-clipboard-list"></i>',
            'Research': '<i class="fas fa-search"></i>',
            'Report': '<i class="fas fa-chart-bar"></i>',
            'default': '<i class="fas fa-bookmark"></i>'
        };

        function getRandomPlaceholder() {
            return placeholderImages[Math.floor(Math.random() * placeholderImages.length)];
        }

        function filterResources() {
            const search = document.getElementById('search').value.toLowerCase();
            const resourceType = document.getElementById('resourceType').value;
            const category = document.getElementById('category').value;
            const language = document.getElementById('language').value;
            const ministry = document.getElementById('ministry').value;

            filteredResources = allResources.filter(resource => {
                const matchesSearch = !search || 
                    resource.resource_name.toLowerCase().includes(search) ||
                    resource.description.toLowerCase().includes(search) ||
                    (resource.meta_data && resource.meta_data.toLowerCase().includes(search));
                
                const matchesType = !resourceType || resource.resource_type_name === resourceType;
                const matchesCategory = !category || resource.category_name === category;
                const matchesLanguage = !language || resource.language_name === language;
                const matchesMinistry = !ministry || resource.ministry_name === ministry;

                return matchesSearch && matchesType && matchesCategory && matchesLanguage && matchesMinistry;
            });

            displayedCount = 0;
            renderResources();
        }

        function renderResources(append = false) {
            const grid = document.getElementById('resourcesGrid');
            const noResults = document.getElementById('noResults');
            const resultsCount = document.getElementById('resultsCount');
            const paginationContainer = document.getElementById('paginationContainer');
            const paginationInfo = document.getElementById('paginationInfo');
            const loadMoreButton = document.getElementById('loadMoreButton');
            
            if (!append) {
                displayedCount = 0;
                grid.innerHTML = '';
            }

            if (filteredResources.length === 0) {
                grid.style.display = 'none';
                noResults.style.display = 'block';
                paginationContainer.style.display = 'none';
                resultsCount.textContent = 'No resources found';
                return;
            }

            grid.style.display = 'flex';
            noResults.style.display = 'none';

            const startIndex = displayedCount;
            const endIndex = Math.min(displayedCount + resourcesPerPage, filteredResources.length);
            const resourcesToShow = filteredResources.slice(startIndex, endIndex);

            const newResourcesHTML = resourcesToShow.map(resource => {
                const icon = resourceTypeIcons[resource.resource_type_name] || resourceTypeIcons.default;
                const thumbnailSrc = resource.thumbnail_image || getRandomPlaceholder();
                
                // Mirror API priority: external_url > button_link > internal resource page
                const resolvedLink = (resource.is_external_resource && resource.external_url)
                    ? resource.external_url
                    : (resource.button_link || null);
                const resourceLink = resolvedLink || `resource.php?slug=${encodeURIComponent(resource.slug)}`;
                const linkTarget = resolvedLink ? '_blank' : '_self';
                const linkRel = resolvedLink ? 'rel="noopener noreferrer"' : '';
                
                return `
                    <div class="col-lg-4 col-md-6">
                        <div class="card resource-card h-100">
                            <div class="card-img-container">
                                <img src="${thumbnailSrc}" alt="${resource.resource_name}" class="card-img-top" onerror="this.src='${getRandomPlaceholder()}'">
                                <div class="card-img-overlay">
                                    ${resource.resource_type_name ? `<span class="badge tag overlay-tag tag-resource-type">${icon} ${resource.resource_type_name}</span>` : ''}
                                    ${resource.category_name ? `<span class="badge tag overlay-tag tag-category"><i class="fas fa-tag"></i> ${resource.category_name}</span>` : ''}
                                    ${resource.ministry_name ? `<span class="badge tag overlay-tag tag-ministry"><i class="fas fa-church"></i> ${resource.ministry_name}</span>` : ''}
                                </div>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">${resource.resource_name}</h5>
                                <p class="card-text card-description">${resource.description}</p>
                                <button class="read-more-btn" onclick="toggleDescription(this)"><i class="fas fa-chevron-down"></i></button>
                                <a href="${resourceLink}" target="${linkTarget}" ${linkRel} class="btn btn-primary mt-auto">View Resource</a>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            if (append) {
                grid.innerHTML += newResourcesHTML;
            } else {
                grid.innerHTML = newResourcesHTML;
            }

            displayedCount = endIndex;
            initReadMoreButtons();

            // Update pagination info and controls
            if (filteredResources.length > resourcesPerPage) {
                paginationContainer.style.display = 'block';
                paginationInfo.textContent = `Displaying ${displayedCount} of ${filteredResources.length} resources`;
                
                if (displayedCount >= filteredResources.length) {
                    loadMoreButton.style.display = 'none';
                } else {
                    loadMoreButton.style.display = 'inline-block';
                }
            } else {
                paginationContainer.style.display = 'none';
            }

            resultsCount.textContent = `Found ${filteredResources.length} of ${allResources.length} total resources`;
        }

        function loadMoreResources() {
            renderResources(true);
        }

        // Event listeners
        document.getElementById('search').addEventListener('input', filterResources);
        document.getElementById('resourceType').addEventListener('change', filterResources);
        document.getElementById('category').addEventListener('change', filterResources);
        document.getElementById('language').addEventListener('change', filterResources);
        document.getElementById('ministry').addEventListener('change', filterResources);
        document.getElementById('loadMoreButton').addEventListener('click', loadMoreResources);

        function toggleDescription(btn) {
            const p = btn.previousElementSibling;
            const expanded = p.classList.toggle('expanded');
            btn.classList.toggle('expanded', expanded);
        }

        function initReadMoreButtons() {
            requestAnimationFrame(() => {
                document.querySelectorAll('.card-description').forEach(p => {
                    const btn = p.nextElementSibling;
                    if (btn && btn.classList.contains('read-more-btn')) {
                        if (p.scrollHeight > p.clientHeight) {
                            btn.classList.add('visible');
                        } else {
                            btn.classList.remove('visible');
                        }
                    }
                });
            });
        }

        // Initialize resources on page load
        filteredResources = [...allResources];
        renderResources();
    </script>
<?php include 'includes/footer.php'; ?>