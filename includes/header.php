<?php
// Load site settings from database
require_once __DIR__ . '/settings.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-2T2ES6X8PG"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-2T2ES6X8PG');
    </script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'Belonging Resources' ?></title>

    <?php if (isset($meta_description)): ?>
    <meta name="description" content="<?= htmlspecialchars($meta_description) ?>">
    <?php endif; ?>

    <?php if (isset($meta_keywords)): ?>
    <meta name="keywords" content="<?= htmlspecialchars($meta_keywords) ?>">
    <?php endif; ?>

    <?php if (isset($og_title)): ?>
    <!-- Open Graph -->
    <meta property="og:title" content="<?= htmlspecialchars($og_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($og_description ?? '') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($og_image ?? '') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($og_url ?? '') ?>">
    <meta property="og:type" content="<?= htmlspecialchars($og_type ?? 'website') ?>">
    <?php endif; ?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&family=Barlow+Semi+Condensed:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/site.css?v=<?= @filemtime(__DIR__ . '/../assets/css/site.css') ?>">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="/">
                <img style="height: 90px !important;" src="/images/newlogo.png" alt="<?= htmlspecialchars(get_setting('site_name', 'SAbelonging')) ?> Logo">
                <span><?= htmlspecialchars(get_setting('site_name', 'SAbelonging')) ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="<?= htmlspecialchars(get_setting('nav_link_1_url', '/')) ?>">
                            <i class="<?= htmlspecialchars(get_setting('nav_link_1_icon', 'fas fa-home')) ?>"></i> <?= htmlspecialchars(get_setting('nav_link_1_text', 'Resources')) ?>
                        </a>
                    </li>
                    <?php if (get_setting('nav_link_2_text') || get_setting('nav_link_3_text') || get_setting('nav_link_4_text') || get_setting('nav_link_5_text') || get_setting('nav_link_6_text')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-globe"></i> Other Resources
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if (get_setting('nav_link_2_text')): ?>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars(get_setting('nav_link_2_url')) ?>" <?= get_setting('nav_link_2_external') == '1' ? 'target="_blank"' : '' ?>>
                                    <i class="<?= htmlspecialchars(get_setting('nav_link_2_icon')) ?>"></i> <?= htmlspecialchars(get_setting('nav_link_2_text')) ?>
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if (get_setting('nav_link_3_text')): ?>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars(get_setting('nav_link_3_url')) ?>" <?= get_setting('nav_link_3_external') == '1' ? 'target="_blank"' : '' ?>>
                                    <i class="<?= htmlspecialchars(get_setting('nav_link_3_icon')) ?>"></i> <?= htmlspecialchars(get_setting('nav_link_3_text')) ?>
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if (get_setting('nav_link_4_text')): ?>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars(get_setting('nav_link_4_url')) ?>" <?= get_setting('nav_link_4_external') == '1' ? 'target="_blank"' : '' ?>>
                                    <i class="<?= htmlspecialchars(get_setting('nav_link_4_icon')) ?>"></i> <?= htmlspecialchars(get_setting('nav_link_4_text')) ?>
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if (get_setting('nav_link_5_text')): ?>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars(get_setting('nav_link_5_url')) ?>" <?= get_setting('nav_link_5_external') == '1' ? 'target="_blank"' : '' ?>>
                                    <i class="<?= htmlspecialchars(get_setting('nav_link_5_icon')) ?>"></i> <?= htmlspecialchars(get_setting('nav_link_5_text')) ?>
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if (get_setting('nav_link_6_text')): ?>
                            <li>
                                <a class="dropdown-item" href="<?= htmlspecialchars(get_setting('nav_link_6_url')) ?>" <?= get_setting('nav_link_6_external') == '1' ? 'target="_blank"' : '' ?>>
                                    <i class="<?= htmlspecialchars(get_setting('nav_link_6_icon')) ?>"></i> <?= htmlspecialchars(get_setting('nav_link_6_text')) ?>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
