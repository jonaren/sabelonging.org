<?php
// Resource library section: filter bar, priority switcher, results grid, "show more" and empty state.
// Set $library_priority to a category name (e.g. 'Youth') to limit it to one priority.
$library_priority = $library_priority ?? '';
$filters = [
    ['field' => 'ResourceType', 'param' => 'format',   'label' => 'All formats'],
    ['field' => 'Language',     'param' => 'language', 'label' => 'All languages'],
    ['field' => 'Ministry',     'param' => 'ministry', 'label' => 'All ministries'],
];
$switcher = [
    ''                       => ['label' => 'All priorities', 'class' => ''],
    'Youth'                  => ['label' => 'Youth', 'class' => 'youth'],
    'Spiritual Life'         => ['label' => 'Spiritual Life', 'class' => 'spirit'],
    'Leadership Development' => ['label' => 'Leadership', 'class' => 'lead'],
];
?>
<section id="library" data-priority="<?= htmlspecialchars($library_priority) ?>" aria-label="Resources">
    <div class="libbar">
        <div class="container">
            <div class="libbar-inner" role="search">
                <label class="lib-search">
                    <span class="visually-hidden">Search resources</span>
                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" id="search" placeholder="Search resources" autocomplete="off" enterkeyhint="search">
                    <kbd aria-hidden="true" title="Press / to search">/</kbd>
                    <button type="button" class="lib-search-clear" id="searchClear" aria-label="Clear search"><i class="fas fa-xmark" aria-hidden="true"></i></button>
                </label>
                <?php foreach ($filters as $f): ?>
                    <select class="form-select lib-select" data-field="<?= $f['field'] ?>" data-param="<?= $f['param'] ?>" aria-label="<?= htmlspecialchars($f['label']) ?>">
                        <option value=""><?= htmlspecialchars($f['label']) ?></option>
                    </select>
                <?php endforeach; ?>
                <button type="button" class="saved-toggle" id="savedToggle" aria-pressed="false" hidden>
                    <i class="fas fa-heart" aria-hidden="true"></i> Saved <span class="n">0</span>
                </button>
                <button type="button" class="lib-clear" id="clearFilters" hidden>Clear all</button>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="lib-status">
            <?php if (!$library_priority): ?>
            <div class="pseg" id="prioritySwitch" role="radiogroup" aria-label="Filter by priority" data-active="">
                <span class="pseg-ind" aria-hidden="true"></span>
                <?php foreach ($switcher as $value => $s): ?>
                <button type="button" role="radio" aria-checked="<?= $value === '' ? 'true' : 'false' ?>" data-value="<?= htmlspecialchars($value) ?>">
                    <?php if ($s['class']): ?><span class="swatch <?= $s['class'] ?>" aria-hidden="true"></span><?php endif; ?><?= htmlspecialchars($s['label']) ?>
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <p class="lib-count" id="resultsCount" aria-live="polite"></p>
        </div>

        <div class="rgrid" id="resourcesGrid"></div>

        <div class="lib-empty" id="noResults" hidden>
            <div class="ico"><i class="fas fa-magnifying-glass" aria-hidden="true"></i></div>
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
