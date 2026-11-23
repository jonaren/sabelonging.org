<?php
// Shared template for the three priority pages. Set $priority_id ('youth', 'spiritual-life', 'leadership') first.
require_once __DIR__ . '/priorities.php';
$priority = TERRITORIAL_PRIORITIES[$priority_id];
$band_class = ['youth' => 'youth', 'spiritual-life' => 'spirit', 'leadership' => 'lead'];

$page_title = $priority['key'] . ' Resources';
$meta_description = $priority['blurb'];
include __DIR__ . '/header.php';
$counts = priorityCounts();
?>
<section class="phero">
    <div class="container">
        <a class="phero-back" href="/"><i class="fas fa-arrow-left" aria-hidden="true"></i> All priorities</a>
        <div class="phero-band <?= $band_class[$priority_id] ?>">
            <h1><?= htmlspecialchars($priority['key']) ?></h1>
            <p><?= htmlspecialchars($priority['blurb']) ?><?php if (isset($counts[$priority['key']])): ?> <?= $counts[$priority['key']] ?> resources.<?php endif; ?></p>
        </div>
        <nav class="phero-switch" aria-label="Other priorities">
            <?php foreach (TERRITORIAL_PRIORITIES as $id => $p): if ($id === $priority_id) continue; ?>
                <a href="<?= $p['page'] ?>"><span class="swatch <?= $band_class[$id] ?>" aria-hidden="true"></span><?= htmlspecialchars($p['key']) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</section>

<?php $library_priority = $priority['key']; include __DIR__ . '/library.php'; ?>

<?php include __DIR__ . '/footer.php'; ?>
