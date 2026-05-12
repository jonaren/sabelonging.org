<?php
$page_title = 'Spiritual Life Resources';
$meta_description = 'Discover resources for deepening spiritual growth and faith practices';
include 'includes/header.php';
?>

    <div class="container my-5">
        <!-- Header -->
        <header class="text-center mb-5">
            <h1 class="display-4 main-header mb-3">Spiritual Life</h1>
            <p class="lead subtitle">Resources for deepening spiritual growth and faith practices</p>
        </header>

        <!-- Filters -->
        <div class="card filter-card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search Resources</label>
                        <input type="text" class="form-control" id="search" placeholder="Search by name, description, or keywords...">
                    </div>
                    <div class="col-md-3">
                        <label for="resourceType" class="form-label">Resource Format</label>
                        <select class="form-select" id="resourceType">
                            <option value="">All Types</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="language" class="form-label">Language</label>
                        <select class="form-select" id="language">
                            <option value="">All Languages</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="ministry" class="form-label">Ministry</label>
                        <select class="form-select" id="ministry">
                            <option value="">All Ministries</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Back to All Resources -->
        <div class="mb-3">
            <a href="resources.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> View All Resources
            </a>
        </div>

        <!-- Results Info -->
        <div class="results-info mb-3" id="resultsCount"></div>

        <!-- Loading State -->
        <div class="text-center py-5" id="loading">
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
        const CATEGORY_FILTER = 'Spiritual Life'; // Fixed category for this page

        let allResources = [];
        let filteredResources = [];
        let displayedCount = 0;
        const resourcesPerPage = 9;

        // Placeholder images for when ThumbnailImage is empty
        const placeholderImages = [
            'https://sabelonging.org/images/tn-1.jpg',
            'https://sabelonging.org/images/tn-2.jpg',
            'https://sabelonging.org/images/tn-3.jpg',
            'https://sabelonging.org/images/tn-4.jpg',
            'https://sabelonging.org/images/tn-5.jpg',
            'https://sabelonging.org/images/tn-6.jpg',
            'https://sabelonging.org/images/tn-7.jpg',
            'https://sabelonging.org/images/tn-8.jpg'
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

        async function loadResources() {
            try {
                const response = await fetch('/api/get-resources.php');
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.error || 'Failed to load resources');
                }

                // Filter resources by category immediately
                allResources = data.resources.filter(r => r.Category === CATEGORY_FILTER);

                populateFilters();
                filteredResources = [...allResources];
                renderResources();
                document.getElementById('loading').style.display = 'none';

            } catch (error) {
                console.error('Error loading resources:', error);
                document.getElementById('loading').innerHTML = `
                    <div class="alert alert-danger" role="alert">
                        <h4 class="alert-heading">Error loading resources</h4>
                        <p>${error.message || 'Please try again later.'}</p>
                    </div>
                `;
            }
        }

        function populateFilters() {
            const resourceTypes = [...new Set(allResources.map(r => r.ResourceType).filter(Boolean))].sort();
            const languages = [...new Set(allResources.map(r => r.Language).filter(Boolean))].sort();
            const ministries = [...new Set(allResources.map(r => r.Ministry).filter(Boolean))].sort();

            populateSelect('resourceType', resourceTypes);
            populateSelect('language', languages);
            populateSelect('ministry', ministries);
        }

        function populateSelect(selectId, options) {
            const select = document.getElementById(selectId);
            options.forEach(option => {
                const optionElement = document.createElement('option');
                optionElement.value = option;
                optionElement.textContent = option;
                select.appendChild(optionElement);
            });
        }

        function filterResources() {
            const search = document.getElementById('search').value.toLowerCase();
            const resourceType = document.getElementById('resourceType').value;
            const language = document.getElementById('language').value;
            const ministry = document.getElementById('ministry').value;

            filteredResources = allResources.filter(resource => {
                const matchesSearch = !search ||
                    resource.ResourceName.toLowerCase().includes(search) ||
                    resource.Description.toLowerCase().includes(search) ||
                    (resource.MetaData && resource.MetaData.toLowerCase().includes(search));

                const matchesType = !resourceType || resource.ResourceType === resourceType;
                const matchesLanguage = !language || resource.Language === language;
                const matchesMinistry = !ministry || resource.Ministry === ministry;

                return matchesSearch && matchesType && matchesLanguage && matchesMinistry;
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
                const icon = resourceTypeIcons[resource.ResourceType] || resourceTypeIcons.default;
                const thumbnailSrc = resource.ThumbnailImage || getRandomPlaceholder();
                const target = resource.is_external_resource ? '_blank' : '_self';
                const rel = resource.is_external_resource ? 'noopener noreferrer' : '';

                return `
                    <div class="col-lg-4 col-md-6">
                        <div class="card resource-card h-100">
                            <div class="card-img-container">
                                <img src="${thumbnailSrc}" alt="${resource.ResourceName}" class="card-img-top" onerror="this.src='${getRandomPlaceholder()}'">
                                <div class="card-img-overlay">
                                    ${resource.ResourceType ? `<span class="badge tag overlay-tag tag-resource-type">${icon} ${resource.ResourceType}</span>` : ''}
                                    ${resource.Ministry ? `<span class="badge tag overlay-tag tag-ministry"><i class="fas fa-church"></i> ${resource.Ministry}</span>` : ''}
                                </div>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">${resource.ResourceName}</h5>
                                <p class="card-text flex-grow-1">${resource.Description}</p>
                                ${resource.ButtonLink ? `<a href="${resource.ButtonLink}" target="${target}" ${rel ? `rel="${rel}"` : ''} class="btn btn-primary mt-auto">View Resource</a>` : ''}
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

            resultsCount.textContent = `Found ${filteredResources.length} Spiritual Life resources`;
        }

        function loadMoreResources() {
            renderResources(true);
        }

        // Event listeners
        document.getElementById('search').addEventListener('input', filterResources);
        document.getElementById('resourceType').addEventListener('change', filterResources);
        document.getElementById('language').addEventListener('change', filterResources);
        document.getElementById('ministry').addEventListener('change', filterResources);
        document.getElementById('loadMoreButton').addEventListener('click', loadMoreResources);

        // Load resources on page load
        loadResources();
    </script>

<?php include 'includes/footer.php'; ?>
