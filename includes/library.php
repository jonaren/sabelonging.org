<?php
// Resource library section: filter bar, results grid, "show more" and empty state.
// Set $library_priority to a category name (e.g. 'Youth') to limit it to one priority.
$library_priority = $library_priority ?? '';
$filters = [
    ['field' => 'ResourceType', 'param' => 'format',   'label' => 'All formats'],
    ['field' => 'Categories',   'param' => 'priority', 'label' => 'All priorities'],
    ['field' => 'Language',     'param' => 'language', 'label' => 'All languages'],
    ['field' => 'Ministry',     'param' => 'ministry', 'label' => 'All ministries'],
];
?>
<section id="library" data-priority="<?= htmlspecialchars($library_priority) ?>" aria-label="Resources">
    <div class="libbar">
        <div class="container">
            <div class="libbar-inner" role="search">
                <label class="lib-search">
                    <span class="visually-hidden">Search resources</span>
                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" id="search" placeholder="Search resources" autocomplete="off">
                </label>
                <?php foreach ($filters as $f):
                    if ($library_priority && $f['field'] === 'Categories') continue; ?>
                    <select class="form-select lib-select" data-field="<?= $f['field'] ?>" data-param="<?= $f['param'] ?>" aria-label="<?= htmlspecialchars($f['label']) ?>">
                        <option value=""><?= htmlspecialchars($f['label']) ?></option>
                    </select>
                <?php endforeach; ?>
                <button type="button" class="lib-clear" id="clearFilters" hidden>Clear filters</button>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="lib-status">
            <p class="lib-count" id="resultsCount" aria-live="polite"></p>
        </div>

        <div class="rgrid" id="resourcesGrid"></div>

        <div class="lib-empty" id="noResults" hidden>
            <h2>No resources match</h2>
            <p>Try a different search term, or clear the filters to see everything.</p>
            <button type="button" class="btn btn-outline-primary" id="noResultsClear">Clear filters</button>
        </div>

        <div class="lib-more">
            <button type="button" class="btn btn-outline-primary" id="loadMore" hidden>Show more resources</button>
            <p id="loadMoreInfo" hidden></p>
        </div>
    </div>
</section>
<script src="/assets/js/library.js?v=<?= @filemtime(__DIR__ . '/../assets/js/library.js') ?>" defer></script>
