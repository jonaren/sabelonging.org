/*
 * Site-wide behavior: saved (hearted) items, a small toast, and the light/dark theme toggle.
 */

// ---------- Saved items ----------
// Stored on this device only (localStorage), per kind: 'resources', 'websites'.
window.SCESaved = (() => {
    const key = (kind) => `sce:saved:${kind}`;
    const listeners = new Set();

    function get(kind) {
        try { return new Set((JSON.parse(localStorage.getItem(key(kind)) || '[]')).map(String)); }
        catch (e) { return new Set(); }
    }
    function set(kind, ids) {
        try { localStorage.setItem(key(kind), JSON.stringify([...ids])); } catch (e) {}
        listeners.forEach((fn) => fn(kind));
    }
    function toggle(kind, id) {
        const ids = get(kind);
        id = String(id);
        const on = !ids.has(id);
        on ? ids.add(id) : ids.delete(id);
        set(kind, ids);
        return on;
    }
    // Keep other open tabs in sync
    window.addEventListener('storage', (e) => {
        const m = e.key && e.key.match(/^sce:saved:(.+)$/);
        if (m) listeners.forEach((fn) => fn(m[1]));
    });
    return { get, toggle, has: (kind, id) => get(kind).has(String(id)), onChange: (fn) => listeners.add(fn) };
})();

// ---------- Toast ----------
window.SCEToast = (() => {
    let el, timer;
    return (message) => {
        if (!el) {
            el = document.createElement('div');
            el.className = 'toast-msg';
            el.setAttribute('role', 'status');
            el.setAttribute('aria-live', 'polite');
            document.body.appendChild(el);
        }
        el.textContent = message;
        el.classList.add('is-visible');
        clearTimeout(timer);
        timer = setTimeout(() => el.classList.remove('is-visible'), 2600);
    };
})();

// ---------- Heart buttons outside the library grid (e.g. the resource page) ----------
(() => {
    const sync = () => document.querySelectorAll('[data-save-kind][data-save-id]').forEach((b) => {
        const on = window.SCESaved.has(b.dataset.saveKind, b.dataset.saveId);
        b.setAttribute('aria-pressed', String(on));
        const label = b.querySelector('.heart-label');
        if (label) label.textContent = on ? 'Saved' : 'Save';
    });
    document.addEventListener('click', (e) => {
        const b = e.target.closest('[data-save-kind][data-save-id]');
        if (!b) return;
        const on = window.SCESaved.toggle(b.dataset.saveKind, b.dataset.saveId);
        b.classList.remove('is-popping'); void b.offsetWidth; if (on) b.classList.add('is-popping');
        window.SCEToast(on ? 'Saved. It will show first when you browse.' : 'Removed from saved.');
    });
    window.SCESaved.onChange(sync);
    sync();
})();

// ---------- Theme ----------
// The initial theme is applied by an inline script in <head> (no flash); this adds the toggle.
(() => {
    const html = document.documentElement;
    const STORAGE_KEY = 'theme';
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const current = () => html.getAttribute('data-theme') || 'light';
    const apply = (theme) => {
        html.setAttribute('data-theme', theme);
        html.setAttribute('data-bs-theme', theme);
        const btn = document.querySelector('.theme-toggle');
        if (btn) {
            btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
            btn.setAttribute('aria-pressed', String(theme === 'dark'));
        }
    };

    // Build the toggle and put it in the header (before the mobile menu button)
    const container = document.querySelector('.navbar > .container');
    if (!container) return;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'theme-toggle';
    btn.innerHTML = '<i class="fas fa-moon" aria-hidden="true"></i><i class="fas fa-sun" aria-hidden="true"></i>';
    const toggler = container.querySelector('.navbar-toggler');
    container.insertBefore(btn, toggler || null);
    apply(current());

    btn.addEventListener('click', (e) => {
        const next = current() === 'dark' ? 'light' : 'dark';
        try { localStorage.setItem(STORAGE_KEY, next); } catch (err) {}

        if (!document.startViewTransition || reduceMotion) { apply(next); return; }

        // Circle reveal from the button
        const r = btn.getBoundingClientRect();
        const x = r.left + r.width / 2;
        const y = r.top + r.height / 2;
        const radius = Math.hypot(Math.max(x, innerWidth - x), Math.max(y, innerHeight - y));
        html.style.setProperty('--tx', `${x}px`);
        html.style.setProperty('--ty', `${y}px`);
        html.style.setProperty('--tr', `${radius}px`);
        html.classList.add('theme-switching');
        const t = document.startViewTransition(() => apply(next));
        t.finished.finally(() => html.classList.remove('theme-switching'));
    });

    // Follow the OS setting live, unless the visitor chose a theme
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        let saved = null;
        try { saved = localStorage.getItem(STORAGE_KEY); } catch (err) {}
        if (!saved) apply(e.matches ? 'dark' : 'light');
    });
})();
