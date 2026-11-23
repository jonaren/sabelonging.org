<?php
$page_title = 'Websites';
$meta_description = 'A directory of websites from across the territory';
include 'includes/header.php';
require_once __DIR__ . '/includes/websites-lib.php';

$sites = [];
$tags = array_fill_keys(array_keys(WEBSITE_TAG_GROUPS), []);
try {
    if (isset($settings_pdo)) {
        ensureWebsiteTables($settings_pdo);
        $sites = getWebsites($settings_pdo, true);
        // Only offer filter options that are actually used by a visible site
        foreach ($sites as $s) foreach ($s['tags'] as $g => $names) foreach ($names as $n) $tags[$g][$n] = true;
        foreach ($tags as &$list) { $list = array_keys($list); natcasesort($list); } unset($list);
    }
} catch (PDOException $e) {
    $sites = [];
}
?>
<style>
    .sites-intro h1 { color: var(--dark-blue); }
    .site-grid { display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fill, minmax(min(100%, 330px), 1fr)); }
    .site-card { display: flex; flex-direction: column; background: #fff; border-radius: 12px; overflow: hidden;
                 box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: transform .2s ease, box-shadow .2s ease; min-width: 0; }
    .site-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,0.15); }
    .browser-bar { display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: #e9edf1; border-bottom: 1px solid #dde3e9; }
    .browser-bar .dots { display: flex; gap: 5px; flex: none; }
    .browser-bar .dots i { width: 9px; height: 9px; border-radius: 50%; background: #c9d1d9; }
    .browser-bar .dots i:first-child { background: var(--accent-red); }
    .browser-bar .dots i:nth-child(2) { background: #ddcc3b; }
    .browser-bar .address { flex: 1; min-width: 0; padding: 2px 10px; border-radius: 5px; background: #fff; color: #5f6b77;
                            font: 12px/1.6 ui-monospace, Menlo, Consolas, monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .browser-bar .address .fa-lock { font-size: 10px; margin-right: 5px; color: #8a96a3; }
    .site-shot { display: block; position: relative; aspect-ratio: 16 / 10; background: #eef1f4; overflow: hidden; }
    .site-shot img { display: block; width: 100%; height: 100%; object-fit: cover; object-position: top center; transition: transform .3s ease; }
    .site-card:hover .site-shot img { transform: scale(1.03); }
    .site-shot .no-shot { position: absolute; inset: 0; display: grid; place-items: center; align-content: center; gap: .5rem; color: #8a96a3; font-size: .9rem; }
    .site-shot .no-shot i { font-size: 2rem; opacity: .5; }
    .site-body { display: flex; flex-direction: column; gap: .5rem; padding: 1.1rem 1.25rem 1.25rem; flex: 1; }
    .site-body h2 { margin: 0; font-size: 1.25rem; font-weight: 600; color: var(--dark-blue); }
    .site-body p { margin: 0; color: #6c757d; font-size: .95rem; }
    .site-tags { display: flex; flex-wrap: wrap; gap: .35rem; }
    .site-tag { font-size: .75rem; font-weight: 600; padding: .2rem .55rem; border-radius: 999px; }
    .site-tag.type { background: #e7eef5; color: var(--primary-blue); }
    .site-tag.owner { background: #fbe9ea; color: #9a323f; }
    .site-tag .label { font-weight: 400; opacity: .75; }
    .site-actions { margin-top: auto; padding-top: .5rem; }
</style>

<div class="container py-4">
    <header class="sites-intro mb-4">
        <h1 class="display-6 mb-2">Websites</h1>
        <p class="lead subtitle mb-0">A directory of websites from across the territory. Search, or filter by what the site is and who runs it.</p>
    </header>

    <div class="card filter-card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="siteSearch" class="form-label">Search websites</label>
                    <input type="search" class="form-control" id="siteSearch" placeholder="Search by name, address or description…">
                </div>
                <?php foreach (WEBSITE_TAG_GROUPS as $group => $meta): ?>
                <div class="col-md-3">
                    <label for="filter-<?= $group ?>" class="form-label"><?= $meta['label'] ?></label>
                    <select class="form-select site-filter" id="filter-<?= $group ?>" data-group="<?= $group ?>">
                        <option value="">All</option>
                        <?php foreach ($tags[$group] as $name): ?>
                            <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <p class="results-info text-muted mb-3" id="siteCount" aria-live="polite"><?= count($sites) ?> website<?= count($sites) === 1 ? '' : 's' ?></p>

    <div class="site-grid mb-5" id="siteGrid">
        <?php foreach ($sites as $s):
            $address = websiteDisplayAddress($s['url']);
            $search = mb_strtolower($s['name'] . ' ' . $address . ' ' . $s['description'] . ' ' . implode(' ', array_merge(...array_values($s['tags']))));
        ?>
        <article class="site-card"
                 data-search="<?= htmlspecialchars($search) ?>"
                 <?php foreach ($s['tags'] as $g => $names): ?>data-<?= $g ?>="<?= htmlspecialchars(json_encode($names)) ?>" <?php endforeach; ?>>
            <div class="browser-bar" aria-hidden="true">
                <span class="dots"><i></i><i></i><i></i></span>
                <span class="address"><?php if (str_starts_with($s['url'], 'https')): ?><i class="fas fa-lock"></i><?php endif; ?><?= htmlspecialchars($address) ?></span>
            </div>
            <a class="site-shot" href="<?= htmlspecialchars($s['url']) ?>" target="_blank" rel="noopener" tabindex="-1" aria-hidden="true">
                <?php if ($s['screenshot_url']): ?>
                    <img src="<?= htmlspecialchars($s['screenshot_url']) ?>" alt="" loading="lazy" width="1280" height="800">
                <?php else: ?>
                    <span class="no-shot"><i class="fas fa-window-maximize"></i><span>Preview coming soon</span></span>
                <?php endif; ?>
            </a>
            <div class="site-body">
                <h2><?= htmlspecialchars($s['name']) ?></h2>
                <?php if ($s['description']): ?><p><?= htmlspecialchars($s['description']) ?></p><?php endif; ?>
                <?php if (array_filter($s['tags'])): ?>
                <div class="site-tags">
                    <?php foreach ($s['tags'] as $g => $names): foreach ($names as $n): ?>
                        <span class="site-tag <?= $g ?>"><span class="label"><?= WEBSITE_TAG_GROUPS[$g]['label'] ?>:</span> <?= htmlspecialchars($n) ?></span>
                    <?php endforeach; endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="site-actions">
                    <a class="btn btn-primary" href="<?= htmlspecialchars($s['url']) ?>" target="_blank" rel="noopener">
                        <?= htmlspecialchars($s['button_label'] ?: 'Visit Website') ?> <i class="fas fa-external-link-alt ms-1"></i>
                    </a>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <div class="text-center py-5" id="noSites" style="<?= $sites ? 'display:none' : '' ?>">
        <div class="display-6 text-muted mb-3"><i class="fas fa-search"></i></div>
        <h3 class="text-muted"><?= $sites ? 'No websites match' : 'No websites yet' ?></h3>
        <p class="text-muted"><?= $sites ? 'Try a different search or clear the filters.' : 'Check back soon.' ?></p>
    </div>
</div>

<script>
(() => {
    const cards = [...document.querySelectorAll('.site-card')];
    if (!cards.length) return;
    const search = document.getElementById('siteSearch');
    const filters = [...document.querySelectorAll('.site-filter')];
    const count = document.getElementById('siteCount');
    const none = document.getElementById('noSites');

    function apply() {
        const q = search.value.trim().toLowerCase();
        let shown = 0;
        cards.forEach((card) => {
            const ok = (!q || card.dataset.search.includes(q)) &&
                filters.every((f) => !f.value || JSON.parse(card.dataset[f.dataset.group] || '[]').includes(f.value));
            card.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });
        const filtered = q || filters.some((f) => f.value);
        count.textContent = filtered ? `${shown} of ${cards.length} websites` : `${cards.length} website${cards.length === 1 ? '' : 's'}`;
        none.style.display = shown ? 'none' : '';
    }
    search.addEventListener('input', apply);
    filters.forEach((f) => f.addEventListener('change', apply));
})();
</script>

<?php include 'includes/footer.php'; ?>
