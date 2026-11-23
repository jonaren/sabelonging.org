/*
 * Site-wide behavior: light/dark theme toggle.
 * The initial theme is applied by an inline script in <head> (no flash); this adds the button.
 */
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
