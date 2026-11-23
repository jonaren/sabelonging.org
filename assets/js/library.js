/*
 * Resource library: one script for the home page and the three priority pages.
 * Reads its settings from #library: data-priority="Youth" limits it to one priority.
 */
(() => {
    const root = document.getElementById('library');
    if (!root) return;

    const PAGE_SIZE = 12;
    const ONLY_PRIORITY = root.dataset.priority || '';
    const SITE_ORIGIN = /^https?:\/\/(www\.)?sacentralequip\.org/i;

    // Logo order, top to bottom. Category name -> CSS class
    const PRIORITIES = [
        ['Youth', 'youth'],
        ['Spiritual Life', 'spirit'],
        ['Leadership Development', 'lead'],
    ];
    const FORMAT_ICONS = {
        Article: 'fa-file-lines', Video: 'fa-video', Book: 'fa-book', Course: 'fa-graduation-cap',
        Tool: 'fa-screwdriver-wrench', Website: 'fa-globe', Podcast: 'fa-podcast', Document: 'fa-file',
        Guide: 'fa-book-open', Template: 'fa-file-code', PDF: 'fa-file-pdf', Presentation: 'fa-person-chalkboard',
        Workshop: 'fa-chalkboard-user', Webinar: 'fa-desktop', Tutorial: 'fa-circle-play', Study: 'fa-clipboard-list',
        Research: 'fa-magnifying-glass', Report: 'fa-chart-column', Sermon: 'fa-church',
    };
    const PLACEHOLDERS = [1, 2, 3, 4, 5, 6, 7, 8].map((n) => `/images/tn-${n}.jpg`);

    const $ = (id) => document.getElementById(id);
    const grid = $('resourcesGrid');
    const count = $('resultsCount');
    const more = $('loadMore');
    const moreInfo = $('loadMoreInfo');
    const empty = $('noResults');
    const search = $('search');
    const clear = $('clearFilters');
    const selects = [...root.querySelectorAll('select[data-field]')];

    let all = [];
    let filtered = [];
    let shown = 0;

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    // Stable placeholder per resource, so it doesn't change on every re-render
    const placeholderFor = (r) => PLACEHOLDERS[(Number(r.id) || 0) % PLACEHOLDERS.length];

    function linkFor(r) {
        // The API returns absolute production URLs for internal pages; keep visitors on this host
        const href = (r.ButtonLink || `/resource.php?slug=${encodeURIComponent(r.slug)}`).replace(SITE_ORIGIN, '');
        const external = /^https?:\/\//i.test(href);
        return { href, external };
    }

    function priorityRibbon(categories) {
        const mine = PRIORITIES.filter(([name]) => (categories || []).includes(name));
        if (!mine.length) return '';
        const label = mine.map(([name]) => name).join(', ');
        return `<div class="pribbon" title="${esc(label)}">${mine.map(([, cls]) => `<span class="${cls}"></span>`).join('')}</div>
                <span class="visually-hidden">Priority: ${esc(label)}.</span>`;
    }

    function card(r) {
        const { href, external } = linkFor(r);
        const icon = FORMAT_ICONS[r.ResourceType] || 'fa-bookmark';
        const img = r.ThumbnailImage || placeholderFor(r);
        return `
        <a class="rcard" href="${esc(href)}"${external ? ' target="_blank" rel="noopener noreferrer"' : ''}>
            <div class="rcard-media">
                <img src="${esc(img)}" alt="" loading="lazy" data-fallback="${esc(placeholderFor(r))}">
                ${priorityRibbon(r.Categories)}
            </div>
            <div class="rcard-body">
                ${r.ResourceType ? `<div class="rcard-format"><i class="fas ${icon}" aria-hidden="true"></i>${esc(r.ResourceType)}</div>` : ''}
                <h3 class="rcard-title">${esc(r.ResourceName)}</h3>
                ${r.Description ? `<p class="rcard-desc">${esc(r.Description)}</p>` : ''}
                <div class="rcard-foot">
                    <span class="ministry">${esc(r.Ministry)}</span>
                    <span class="rcard-go">${external ? 'Visit <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i><span class="visually-hidden">(opens in a new tab)</span>' : 'View details'}</span>
                </div>
            </div>
        </a>`;
    }

    function skeletons(n) {
        grid.innerHTML = Array.from({ length: n }, () => `
            <div class="rcard is-skeleton" aria-hidden="true">
                <div class="rcard-media"></div>
                <div class="rcard-body">
                    <span class="sk" style="width:30%"></span>
                    <span class="sk" style="width:80%;height:22px;margin:6px 0"></span>
                    <span class="sk"></span><span class="sk" style="width:90%"></span><span class="sk" style="width:60%"></span>
                </div>
            </div>`).join('');
    }

    function fillSelect(select, values) {
        values.forEach((v) => select.add(new Option(v, v)));
    }

    function populateFilters() {
        selects.forEach((s) => {
            const field = s.dataset.field;
            const values = field === 'Categories'
                ? PRIORITIES.map(([name]) => name).filter((n) => all.some((r) => (r.Categories || []).includes(n)))
                : [...new Set(all.map((r) => r[field]).filter(Boolean))].sort((a, b) => a.localeCompare(b));
            fillSelect(s, values);
        });
    }

    function applyFilters() {
        const q = search.value.trim().toLowerCase();
        const active = selects.filter((s) => s.value);
        filtered = all.filter((r) => {
            if (q && ![r.ResourceName, r.Description, r.MetaData, r.Ministry, r.ResourceType].some((v) => (v || '').toLowerCase().includes(q))) return false;
            return active.every((s) => s.dataset.field === 'Categories'
                ? (r.Categories || []).includes(s.value)
                : r[s.dataset.field] === s.value);
        });
        selects.forEach((s) => s.classList.toggle('has-value', !!s.value));
        clear.hidden = !(q || active.length);
        render();
    }

    function render(append = false) {
        if (!append) { shown = 0; grid.innerHTML = ''; }
        const next = filtered.slice(shown, shown + PAGE_SIZE);
        grid.insertAdjacentHTML('beforeend', next.map(card).join(''));
        shown += next.length;

        const filteredBy = filtered.length !== all.length;
        count.innerHTML = filteredBy
            ? `<b>${filtered.length}</b> of ${all.length} resources`
            : `<b>${all.length}</b> resources`;
        empty.hidden = filtered.length > 0;
        grid.hidden = filtered.length === 0;
        more.hidden = shown >= filtered.length;
        moreInfo.hidden = more.hidden;
        moreInfo.textContent = `Showing ${shown} of ${filtered.length}`;
    }

    // Broken thumbnails fall back to a placeholder (event delegation; error doesn't bubble, so capture)
    grid.addEventListener('error', (e) => {
        const img = e.target;
        if (img.tagName === 'IMG' && img.dataset.fallback && img.src.indexOf(img.dataset.fallback) === -1) img.src = img.dataset.fallback;
    }, true);

    search.addEventListener('input', applyFilters);
    selects.forEach((s) => s.addEventListener('change', applyFilters));
    more.addEventListener('click', () => render(true));
    const resetFilters = () => {
        search.value = '';
        selects.forEach((s) => { s.value = ''; });
        applyFilters();
        search.focus();
    };
    clear.addEventListener('click', resetFilters);
    $('noResultsClear')?.addEventListener('click', resetFilters);

    // Show a hairline under the filter bar once it is stuck to the header
    const bar = root.querySelector('.libbar');
    if (bar && 'IntersectionObserver' in window) {
        const sentinel = document.createElement('div');
        bar.before(sentinel);
        new IntersectionObserver(([e]) => bar.classList.toggle('is-stuck', !e.isIntersecting), { rootMargin: '-80px 0px 0px 0px' }).observe(sentinel);
    }

    skeletons(6);
    fetch('/api/get-resources.php')
        .then((res) => res.json())
        .then((data) => {
            if (!data.success) throw new Error(data.error || 'The resource list is unavailable.');
            all = ONLY_PRIORITY ? data.resources.filter((r) => (r.Categories || []).includes(ONLY_PRIORITY)) : data.resources;
            search.placeholder = `Search ${all.length} resources`;
            populateFilters();
            // Allow deep links like /?priority=Youth or ?q=prayer
            const params = new URLSearchParams(location.search);
            if (params.get('q')) search.value = params.get('q');
            selects.forEach((s) => { const v = params.get(s.dataset.param); if (v) s.value = v; });
            applyFilters();
        })
        .catch((err) => {
            grid.innerHTML = '';
            count.textContent = '';
            empty.hidden = false;
            empty.querySelector('h2').textContent = 'Resources couldn’t load';
            empty.querySelector('p').textContent = `${err.message} Refresh the page to try again.`;
            $('noResultsClear').hidden = true;
        });
})();
