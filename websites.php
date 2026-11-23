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

    /* Saved (hearts): stored on this device; saved sites sort first */
    .site-heart {
        flex: none; width: 28px; height: 28px; display: grid; place-items: center; margin: -4px -4px -4px 0;
        border: 0; border-radius: 50%; background: transparent; color: #8a96a3; font-size: 14px;
        transition: transform .2s cubic-bezier(.34, 1.4, .64, 1), color .15s ease, background-color .15s ease;
    }
    .site-heart:hover { color: #e0314b; background: rgba(224, 49, 75, .1); transform: scale(1.1); }
    .site-heart[aria-pressed="true"] { color: #e0314b; }
    @media (prefers-reduced-motion: no-preference) {
        .site-heart.is-popping i { animation: site-heart-pop .45s cubic-bezier(.34, 1.4, .64, 1); }
        @keyframes site-heart-pop { 35% { transform: scale(1.5); } }
    }
    .wsaved {
        display: inline-flex; align-items: center; gap: 8px; height: 40px; padding: 0 16px;
        border: 1px solid var(--line, #dbe1e8); border-radius: 999px; background: var(--surface, #fff);
        font-weight: 600; font-size: 15px; color: var(--ink-2, #3c4b5e);
    }
    .wsaved i { color: #e0314b; }
    .wsaved .n { min-width: 22px; padding: 0 6px; border-radius: 999px; background: var(--surface-2, #f2f4f7); font-size: 13px; text-align: center; }
    .wsaved[aria-pressed="true"] { background: #e0314b; border-color: #e0314b; color: #fff; }
    .wsaved[aria-pressed="true"] i { color: #fff; }
    .wsaved[aria-pressed="true"] .n { background: rgba(255, 255, 255, .22); }
    .wsaved-toast {
        position: fixed; z-index: 1100; left: 50%; bottom: 24px; transform: translate(-50%, 20px); opacity: 0; pointer-events: none;
        padding: 12px 20px; border-radius: 999px; background: var(--ink, #13243a); color: var(--canvas, #fff); font-weight: 600;
        box-shadow: 0 18px 40px -16px rgba(0, 0, 0, .5); transition: opacity .25s ease, transform .35s cubic-bezier(.34, 1.4, .64, 1);
    }
    .wsaved-toast.is-visible { opacity: 1; transform: translate(-50%, 0); }

    /* Hero: navy band with a fanned stack of real site screenshots */
    .whero {
        position: relative; isolation: isolate; overflow: hidden;
        background: var(--hero-bg, #13243a); color: #fff;
        padding: clamp(48px, 7vw, 96px) 0 clamp(56px, 8vw, 104px);
        margin-bottom: 2rem;
    }
    .whero::before {
        content: ""; position: absolute; z-index: -1; inset: -20% -10% -20% 40%;
        background:
            radial-gradient(40% 45% at 70% 25%, rgba(224, 201, 47, .20), transparent 70%),
            radial-gradient(40% 45% at 40% 60%, rgba(140, 35, 51, .32), transparent 70%),
            radial-gradient(45% 50% at 78% 85%, rgba(47, 106, 163, .40), transparent 70%);
        filter: blur(20px);
    }
    .whero-grid { display: grid; gap: clamp(40px, 6vw, 72px); grid-template-columns: minmax(0, 1fr); align-items: center; }
    @media (min-width: 992px) { .whero-grid { grid-template-columns: minmax(0, 5fr) minmax(0, 6fr); } }
    .whero h1 { color: #fff; font-size: clamp(44px, 6vw, 76px); line-height: .98; letter-spacing: -0.02em; margin: 0 0 18px; }
    .whero-lede { font-size: clamp(18px, 1.6vw, 20px); color: #c3cfdd; max-width: 42ch; margin: 0 0 28px; }
    .whero .btn-light { border-radius: 999px; font-weight: 600; padding: .75rem 1.5rem; color: #13243a; }
    .whero .btn-light:hover { background: var(--youth, #e0c92f); border-color: var(--youth, #e0c92f); color: var(--youth-ink, #2f2900); }

    .whero-stack { position: relative; aspect-ratio: 16 / 11; max-width: 640px; width: 100%; justify-self: center; }
    .whero-win {
        position: absolute; width: 74%; border-radius: 12px; overflow: hidden;
        background: #fff; box-shadow: 0 30px 60px -24px rgba(0, 0, 0, .65), 0 0 0 1px rgba(255, 255, 255, .08);
        transition: transform .5s cubic-bezier(.34, 1.4, .64, 1);
    }
    .whero-win .bar { display: flex; align-items: center; gap: 8px; padding: 7px 10px; background: #e9edf1; }
    .whero-win .bar i { width: 8px; height: 8px; border-radius: 50%; background: #c9d1d9; flex: none; }
    .whero-win .bar i:first-child { background: #8c2333; }
    .whero-win .bar i:nth-child(2) { background: #e0c92f; }
    .whero-win .bar span {
        flex: 1; min-width: 0; padding: 1px 8px; border-radius: 4px; background: #fff; color: #66748a;
        font: 11px/1.6 ui-monospace, Menlo, Consolas, monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .whero-win img { display: block; width: 100%; height: auto; aspect-ratio: 16 / 10; object-fit: cover; object-position: top; }
    .whero-win:nth-child(1) { left: 0;   top: 14%; transform: rotate(-7deg); z-index: 1; }
    .whero-win:nth-child(2) { right: 0;  top: 0;   transform: rotate(5deg);  z-index: 2; }
    .whero-win:nth-child(3) { left: 13%; top: 30%; transform: rotate(-1deg); z-index: 3; }
    .whero-stack:hover .whero-win:nth-child(1) { transform: rotate(-10deg) translate(-12px, 6px); }
    .whero-stack:hover .whero-win:nth-child(2) { transform: rotate(8deg) translate(12px, -8px); }
    .whero-stack:hover .whero-win:nth-child(3) { transform: rotate(0deg) translateY(-6px) scale(1.02); }
    /* One entrance: the windows fan out from a single stack */
    @media (prefers-reduced-motion: no-preference) {
        .whero-win { animation: whero-fan .9s cubic-bezier(.2, .8, .2, 1) backwards; }
        .whero-win:nth-child(1) { animation-delay: .15s; }
        .whero-win:nth-child(2) { animation-delay: .25s; }
        .whero-win:nth-child(3) { animation-delay: .35s; }
        .whero-copy > * { animation: whero-rise .7s cubic-bezier(.2, .8, .2, 1) backwards; }
        .whero-copy > :nth-child(2) { animation-delay: .08s; }
        .whero-copy > :nth-child(3) { animation-delay: .16s; }
        @keyframes whero-fan { from { opacity: 0; transform: translate(0, 40px) rotate(0) scale(.92); } }
        @keyframes whero-rise { from { opacity: 0; transform: translateY(14px); } }
    }
    @media (max-width: 575.98px) { .whero-win .bar span { font-size: 9px; } }
</style>

<?php $hero_shots = array_slice(array_values(array_filter($sites, fn($s) => !empty($s['screenshot_url']))), 0, 3); ?>
<section class="whero">
    <div class="container">
        <div class="whero-grid">
            <div class="whero-copy">
                <h1>Websites</h1>
                <p class="whero-lede">A directory of websites from across the territory. Search, or filter by what the site is and who runs it.</p>
                <?php if ($sites): ?>
                <a class="btn btn-light" href="#siteSearch">Browse <?= count($sites) ?> websites <i class="fas fa-arrow-down ms-1" aria-hidden="true"></i></a>
                <?php endif; ?>
            </div>
            <?php if (count($hero_shots) === 3): ?>
            <div class="whero-stack" aria-hidden="true">
                <?php foreach ($hero_shots as $s): ?>
                <div class="whero-win">
                    <div class="bar"><i></i><i></i><i></i><span><?= htmlspecialchars(websiteDisplayAddress($s['url'])) ?></span></div>
                    <img src="<?= htmlspecialchars($s['screenshot_url']) ?>" alt="" width="1280" height="800">
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="container pb-4">

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

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="results-info text-muted mb-0" id="siteCount" aria-live="polite"><?= count($sites) ?> website<?= count($sites) === 1 ? '' : 's' ?></p>
        <button type="button" class="wsaved" id="sitesSaved" aria-pressed="false" hidden>
            <i class="fas fa-heart" aria-hidden="true"></i> Saved <span class="n">0</span>
        </button>
    </div>

    <div class="site-grid mb-5" id="siteGrid">
        <?php foreach ($sites as $s):
            $address = websiteDisplayAddress($s['url']);
            $search = mb_strtolower($s['name'] . ' ' . $address . ' ' . $s['description'] . ' ' . implode(' ', array_merge(...array_values($s['tags']))));
        ?>
        <article class="site-card" data-id="<?= (int)$s['id'] ?>"
                 data-search="<?= htmlspecialchars($search) ?>"
                 <?php foreach ($s['tags'] as $g => $names): ?>data-<?= $g ?>="<?= htmlspecialchars(json_encode($names)) ?>" <?php endforeach; ?>>
            <div class="browser-bar">
                <span class="dots" aria-hidden="true"><i></i><i></i><i></i></span>
                <span class="address" aria-hidden="true"><?php if (str_starts_with($s['url'], 'https')): ?><i class="fas fa-lock"></i><?php endif; ?><?= htmlspecialchars($address) ?></span>
                <button type="button" class="site-heart" data-site-heart="<?= (int)$s['id'] ?>" aria-pressed="false"
                        aria-label="Save <?= htmlspecialchars($s['name']) ?>" title="Save"><i class="fas fa-heart" aria-hidden="true"></i></button>
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
    const grid = document.getElementById('siteGrid');
    const savedBtn = document.getElementById('sitesSaved');

    // ---------- Saved sites (this device only) ----------
    const KEY = 'sce:saved:websites';
    const getSaved = () => { try { return new Set(JSON.parse(localStorage.getItem(KEY) || '[]').map(String)); } catch (e) { return new Set(); } };
    const putSaved = (ids) => { try { localStorage.setItem(KEY, JSON.stringify([...ids])); } catch (e) {} };
    let savedOnly = false;
    let toastEl, toastTimer;
    function toast(msg) {
        if (window.SCEToast) return window.SCEToast(msg);
        if (!toastEl) { toastEl = Object.assign(document.createElement('div'), { className: 'wsaved-toast' }); toastEl.setAttribute('role', 'status'); document.body.appendChild(toastEl); }
        toastEl.textContent = msg;
        toastEl.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toastEl.classList.remove('is-visible'), 2600);
    }
    function syncHearts() {
        const ids = getSaved();
        cards.forEach((card) => card.querySelector('[data-site-heart]')?.setAttribute('aria-pressed', String(ids.has(card.dataset.id))));
        const n = cards.filter((c) => ids.has(c.dataset.id)).length;
        savedBtn.querySelector('.n').textContent = n;
        savedBtn.hidden = n === 0 && !savedOnly;
    }
    grid.addEventListener('click', (e) => {
        const b = e.target.closest('[data-site-heart]');
        if (!b) return;
        const ids = getSaved();
        const id = b.dataset.siteHeart;
        const on = !ids.has(id);
        on ? ids.add(id) : ids.delete(id);
        putSaved(ids);
        b.classList.remove('is-popping'); void b.offsetWidth; if (on) b.classList.add('is-popping');
        syncHearts();
        toast(on ? 'Saved. It will show first when you browse.' : 'Removed from saved.');
        if (savedOnly) apply();
    });
    savedBtn.addEventListener('click', () => { savedOnly = !savedOnly; savedBtn.setAttribute('aria-pressed', String(savedOnly)); apply(); });
    window.addEventListener('storage', (e) => { if (e.key === KEY) { syncHearts(); if (savedOnly) apply(); } });

    function apply() {
        const q = search.value.trim().toLowerCase();
        const saved = getSaved();
        // Saved sites first, otherwise the admin's order
        [...cards].sort((a, b) => (saved.has(b.dataset.id) - saved.has(a.dataset.id)) || (cards.indexOf(a) - cards.indexOf(b)))
            .forEach((card) => grid.appendChild(card));
        let shown = 0;
        cards.forEach((card) => {
            const ok = (!savedOnly || saved.has(card.dataset.id)) && (!q || card.dataset.search.includes(q)) &&
                filters.every((f) => !f.value || JSON.parse(card.dataset[f.dataset.group] || '[]').includes(f.value));
            card.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });
        const filtered = q || savedOnly || filters.some((f) => f.value);
        count.textContent = filtered ? `${shown} of ${cards.length} websites` : `${cards.length} website${cards.length === 1 ? '' : 's'}`;
        none.style.display = shown ? 'none' : '';
    }
    search.addEventListener('input', apply);
    filters.forEach((f) => f.addEventListener('change', apply));
    syncHearts();
    apply();
})();
</script>

<?php include 'includes/footer.php'; ?>
