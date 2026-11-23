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
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const canMorph = !!document.startViewTransition && !reduceMotion;

    // Logo order, top to bottom: [category name, CSS class, short tag label]
    const PRIORITIES = [
        ['Youth', 'youth', 'Youth'],
        ['Spiritual Life', 'spirit', 'Spiritual Life'],
        ['Leadership Development', 'lead', 'Leadership'],
    ];
    // Ministries take the color of the priority they serve
    const MINISTRY_COLOR = [
        [/spiritual/i, 'spirit'],
        [/youth|teen|child|young/i, 'youth'],
        [/leader|officer|training/i, 'lead'],
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
    const searchWrap = search.closest('.lib-search');
    const clear = $('clearFilters');
    const selects = [...root.querySelectorAll('select[data-field]')];
    const pswitch = $('prioritySwitch');

    let all = [];
    let filtered = [];
    let shown = 0;
    let priority = '';      // value of the priority switcher (home only)
    let shownCount = 0;     // number currently displayed in the count, for the roll animation

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    // Stable placeholder per resource, so it doesn't change on every re-render
    const placeholderFor = (r) => PLACEHOLDERS[(Number(r.id) || 0) % PLACEHOLDERS.length];

    function linkFor(r) {
        // The API returns absolute production URLs for internal pages; keep visitors on this host
        const href = (r.ButtonLink || `/resource.php?slug=${encodeURIComponent(r.slug)}`).replace(SITE_ORIGIN, '');
        return { href, external: /^https?:\/\//i.test(href) };
    }

    const prioritiesOf = (r) => PRIORITIES.filter(([name]) => (r.Categories || []).includes(name));

    function ribbon(mine) {
        if (!mine.length) return '';
        return `<div class="pribbon" aria-hidden="true">${mine.map(([, cls]) => `<span class="${cls}"></span>`).join('')}</div>`;
    }

    function tags(r, mine) {
        const out = mine.map(([name, cls, label]) => `<span class="tag ${cls}" title="Priority: ${esc(name)}">${esc(label)}</span>`);
        if (r.Ministry) {
            const color = (MINISTRY_COLOR.find(([re]) => re.test(r.Ministry)) || [, 'neutral'])[1];
            out.push(`<span class="tag ministry ${color}" title="Ministry: ${esc(r.Ministry)}">${esc(r.Ministry)}</span>`);
        }
        return out.length ? `<div class="tags">${out.join('')}</div>` : '<span></span>';
    }

    function card(r, i, entering) {
        const { href, external } = linkFor(r);
        const icon = FORMAT_ICONS[r.ResourceType] || 'fa-bookmark';
        const img = r.ThumbnailImage || placeholderFor(r);
        const mine = prioritiesOf(r);
        const style = `--i:${i};${canMorph ? `view-transition-name:r-${Number(r.id) || i}` : ''}`;
        return `
        <a class="rcard${entering ? ' is-entering' : ''}" href="${esc(href)}" style="${style}"${external ? ' target="_blank" rel="noopener noreferrer"' : ''}>
            <div class="rcard-media">
                <img src="${esc(img)}" alt="" loading="lazy" decoding="async" data-fallback="${esc(placeholderFor(r))}">
                ${ribbon(mine)}
            </div>
            <div class="rcard-body">
                ${r.ResourceType ? `<div class="rcard-format"><i class="fas ${icon}" aria-hidden="true"></i>${esc(r.ResourceType)}</div>` : ''}
                <h3 class="rcard-title">${esc(r.ResourceName)}</h3>
                ${r.Description ? `<p class="rcard-desc">${esc(r.Description)}</p>` : ''}
                <div class="rcard-foot">
                    ${tags(r, mine)}
                    <span class="rcard-go">${external
                        ? 'Visit <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i><span class="visually-hidden">(opens in a new tab)</span>'
                        : 'Details <i class="fas fa-arrow-right" aria-hidden="true"></i>'}</span>
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

    function populateFilters() {
        selects.forEach((s) => {
            const field = s.dataset.field;
            [...new Set(all.map((r) => r[field]).filter(Boolean))]
                .sort((a, b) => a.localeCompare(b))
                .forEach((v) => s.add(new Option(v, v)));
        });
        // Hide priorities with no resources from the switcher
        pswitch?.querySelectorAll('button[data-value]').forEach((b) => {
            if (b.dataset.value && !all.some((r) => (r.Categories || []).includes(b.dataset.value))) b.hidden = true;
        });
    }

    // ---------- Priority switcher ----------
    function moveIndicator() {
        if (!pswitch) return;
        const active = pswitch.querySelector('[aria-checked="true"]');
        const ind = pswitch.querySelector('.pseg-ind');
        if (!active || !ind) return;
        ind.style.width = `${active.offsetWidth}px`;
        ind.style.transform = `translateX(${active.offsetLeft}px)`;
    }
    function setPriority(value) {
        priority = value;
        if (!pswitch) return;
        pswitch.dataset.active = value;
        pswitch.querySelectorAll('button[data-value]').forEach((b) => b.setAttribute('aria-checked', String(b.dataset.value === value)));
        moveIndicator();
    }
    if (pswitch) {
        pswitch.addEventListener('click', (e) => {
            const b = e.target.closest('button[data-value]');
            if (!b || b.dataset.value === priority) return;
            setPriority(b.dataset.value);
            applyFilters();
        });
        // Arrow keys move between options (radiogroup pattern)
        pswitch.addEventListener('keydown', (e) => {
            if (!['ArrowLeft', 'ArrowRight'].includes(e.key)) return;
            const opts = [...pswitch.querySelectorAll('button[data-value]:not([hidden])')];
            const i = opts.findIndex((b) => b.dataset.value === priority);
            const next = opts[(i + (e.key === 'ArrowRight' ? 1 : -1) + opts.length) % opts.length];
            setPriority(next.dataset.value);
            next.focus();
            applyFilters();
        });
        window.addEventListener('resize', moveIndicator);
        document.fonts?.ready.then(moveIndicator);
    }

    // ---------- Filtering ----------
    function applyFilters() {
        const q = search.value.trim().toLowerCase();
        const active = selects.filter((s) => s.value);
        filtered = all.filter((r) => {
            if (priority && !(r.Categories || []).includes(priority)) return false;
            if (q && ![r.ResourceName, r.Description, r.MetaData, r.Ministry, r.ResourceType].some((v) => (v || '').toLowerCase().includes(q))) return false;
            return active.every((s) => r[s.dataset.field] === s.value);
        });
        selects.forEach((s) => s.classList.toggle('has-value', !!s.value));
        searchWrap.classList.toggle('has-value', !!search.value);
        clear.hidden = !(q || active.length || priority);
        updateUrl(q);

        // Cards glide to their new positions where the browser supports it
        if (canMorph && grid.children.length && !grid.querySelector('.is-skeleton')) {
            document.startViewTransition(() => render());
        } else {
            render(false, true);
        }
    }

    function render(append = false, animateIn = false) {
        if (!append) { shown = 0; grid.innerHTML = ''; }
        const next = filtered.slice(shown, shown + PAGE_SIZE);
        grid.insertAdjacentHTML('beforeend', next.map((r, i) => card(r, i, append || animateIn)).join(''));
        shown += next.length;

        rollCount(filtered.length);
        empty.hidden = filtered.length > 0;
        grid.hidden = filtered.length === 0;
        more.hidden = shown >= filtered.length;
        moreInfo.hidden = more.hidden;
        moreInfo.textContent = `Showing ${shown} of ${filtered.length}`;
    }

    // The result count rolls to its new value
    function rollCount(target) {
        const total = all.length;
        const suffix = target === total ? ' resources' : ` of ${total} resources`;
        const from = shownCount;
        shownCount = target;
        if (reduceMotion || from === target) { count.innerHTML = `<b>${target}</b>${suffix}`; return; }
        const start = performance.now();
        const step = (now) => {
            const p = Math.min(1, (now - start) / 350);
            const v = Math.round(from + (target - from) * (1 - Math.pow(1 - p, 3)));
            count.innerHTML = `<b>${v}</b>${suffix}`;
            if (p < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    }

    // Keep the URL shareable: ?q=…&priority=…&format=…
    function updateUrl(q) {
        const params = new URLSearchParams();
        if (q) params.set('q', search.value.trim());
        if (priority) params.set('priority', priority);
        selects.forEach((s) => { if (s.value) params.set(s.dataset.param, s.value); });
        const qs = params.toString();
        history.replaceState(null, '', qs ? `?${qs}` : location.pathname);
    }

    // ---------- Events ----------
    // Broken thumbnails fall back to a placeholder (error doesn't bubble, so capture)
    grid.addEventListener('error', (e) => {
        const img = e.target;
        if (img.tagName === 'IMG' && img.dataset.fallback && img.src.indexOf(img.dataset.fallback) === -1) img.src = img.dataset.fallback;
    }, true);

    let typing;
    search.addEventListener('input', () => {
        searchWrap.classList.toggle('has-value', !!search.value);
        clearTimeout(typing);
        typing = setTimeout(applyFilters, 140);
    });
    search.addEventListener('keydown', (e) => { if (e.key === 'Escape' && search.value) { search.value = ''; applyFilters(); } });
    $('searchClear').addEventListener('click', () => { search.value = ''; applyFilters(); search.focus(); });
    selects.forEach((s) => s.addEventListener('change', applyFilters));
    more.addEventListener('click', () => render(true));

    const resetFilters = () => {
        search.value = '';
        selects.forEach((s) => { s.value = ''; });
        setPriority('');
        applyFilters();
    };
    clear.addEventListener('click', resetFilters);
    $('noResultsClear').addEventListener('click', resetFilters);

    // Press "/" anywhere to jump to search
    document.addEventListener('keydown', (e) => {
        if (e.key !== '/' || e.metaKey || e.ctrlKey || e.altKey) return;
        const t = e.target;
        if (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName)) return;
        e.preventDefault();
        search.focus();
        search.select();
        searchWrap.scrollIntoView({ block: 'nearest', behavior: reduceMotion ? 'auto' : 'smooth' });
    });

    // Load the next page automatically when "Show more" scrolls into view
    if ('IntersectionObserver' in window) {
        new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && !more.hidden) render(true);
        }, { rootMargin: '400px 0px' }).observe(more);
    }

    // Hairline under the filter bar once it is stuck to the header
    const bar = root.querySelector('.libbar');
    if (bar && 'IntersectionObserver' in window) {
        const sentinel = document.createElement('div');
        bar.before(sentinel);
        new IntersectionObserver(([e]) => bar.classList.toggle('is-stuck', !e.isIntersecting), { rootMargin: '-80px 0px 0px 0px' }).observe(sentinel);
    }

    // ---------- Load ----------
    skeletons(6);
    fetch('/api/get-resources.php')
        .then((res) => res.json())
        .then((data) => {
            if (!data.success) throw new Error(data.error || 'The resource list is unavailable.');
            all = ONLY_PRIORITY ? data.resources.filter((r) => (r.Categories || []).includes(ONLY_PRIORITY)) : data.resources;
            search.placeholder = `Search ${all.length} resources`;
            populateFilters();
            // Deep links: /?q=prayer&priority=Youth&format=Book
            const params = new URLSearchParams(location.search);
            if (params.get('q')) search.value = params.get('q');
            selects.forEach((s) => { const v = params.get(s.dataset.param); if (v) s.value = v; });
            const p = params.get('priority');
            setPriority(pswitch && PRIORITIES.some(([name]) => name === p) ? p : '');
            grid.innerHTML = '';
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
