<?php
// Home page hero: headline + the Territorial Priorities ribbon (links to each priority).
require_once __DIR__ . '/priorities.php';
$counts = priorityCounts();
$ribbon_class = ['youth' => 'youth', 'spiritual-life' => 'spirit', 'leadership' => 'lead'];
?>
<section class="hero">
    <div class="container">
        <div class="hero-grid">
            <div class="hero-copy">
                <h1>Resources for leaders, faith and young people</h1>
                <p class="hero-lede">A curated collection of tools for The Salvation Army USA Central Territory’s three priorities. Start with a priority, or search everything below.</p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="#library">Browse <?= !empty($counts['all']) ? 'all ' . $counts['all'] : 'all' ?> resources <i class="fas fa-arrow-down ms-1" aria-hidden="true"></i></a>
                </div>
            </div>

            <nav aria-label="Territorial Priorities">
                <ul class="ribbon" id="priorityRibbon">
                    <?php foreach (TERRITORIAL_PRIORITIES as $id => $p): $n = $counts[$p['key']] ?? null; ?>
                    <li class="<?= $ribbon_class[$id] ?>">
                        <a class="band" href="<?= $p['page'] ?>">
                            <span class="band-name"><?= htmlspecialchars($p['key']) ?></span>
                            <span class="band-count">
                                <?php if ($n !== null): ?><b data-count="<?= $n ?>"><?= $n ?></b>resources<?php endif; ?>
                                <i class="fas fa-arrow-right go" aria-hidden="true"></i>
                            </span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>
        <p class="hero-note">
            <strong>A note on discernment.</strong>
            These resources have been prayerfully and thoughtfully reviewed. While aligned with Salvation Army doctrine and values,
            some materials may reflect theological differences not specifically identified. Engage with discernment, guided by Scripture
            and trusted spiritual leadership. Inclusion does not imply full endorsement of all views.
        </p>
    </div>
</section>
<script>
    // Play the ribbon's draw-in once per visit (not on every navigation back to home)
    (() => {
        const r = document.getElementById('priorityRibbon');
        let seen = false;
        try { seen = sessionStorage.getItem('ribbonDrawn') === '1'; sessionStorage.setItem('ribbonDrawn', '1'); } catch (e) {}
        if (seen || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        r.classList.add('is-drawing');

        // Counts tick up as each band draws in
        r.querySelectorAll('[data-count]').forEach((el, i) => {
            const target = Number(el.dataset.count);
            el.textContent = '0';
            const start = performance.now() + 250 + i * 500;
            const tick = (now) => {
                const p = Math.min(1, Math.max(0, (now - start) / 700));
                el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
                if (p < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        });
    })();
</script>
