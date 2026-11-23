<?php
// Home page hero: headline + the Territorial Priorities ribbon (links to each priority).
require_once __DIR__ . '/priorities.php';
$counts = priorityCounts();
$ribbon_class = ['youth' => 'youth', 'spiritual-life' => 'spirit', 'leadership' => 'lead'];
?>
<section class="hero">
    <div class="container">
        <div class="hero-grid">
            <div>
                <h1>Resources for leaders, faith and young people</h1>
                <p class="hero-lede">A curated collection of tools for The Salvation Army USA Central Territory’s three priorities. Start with a priority, or search everything below.</p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="#library">Browse <?= !empty($counts['all']) ? 'all ' . $counts['all'] : 'all' ?> resources</a>
                </div>
            </div>

            <nav aria-label="Territorial Priorities">
                <ul class="ribbon" id="priorityRibbon">
                    <?php foreach (TERRITORIAL_PRIORITIES as $id => $p): $n = $counts[$p['key']] ?? null; ?>
                    <li class="<?= $ribbon_class[$id] ?>">
                        <a class="band" href="<?= $p['page'] ?>">
                            <span class="band-name"><?= htmlspecialchars($p['key']) ?></span>
                            <?php if ($n !== null): ?><span class="band-count"><b><?= $n ?></b>resources</span><?php endif; ?>
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
        if (!seen) r.classList.add('is-drawing');
    })();
</script>
