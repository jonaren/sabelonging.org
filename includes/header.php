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
    <style>
        :root {
            --primary-blue: #1d486b;
            --dark-blue: #102a41;
            --accent-red: #ee3a43;
        }

        body {
            background-color: #f8f9fa;
        }

        .main-header {
            color: var(--dark-blue);
        }

        .subtitle {
            color: #6c757d;
        }

        .filter-card {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: none;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.2rem rgba(29, 72, 107, 0.25);
        }

        .resource-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            height: 100%;
        }

        .resource-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .card-img-container {
            position: relative;
            overflow: hidden;
        }

        .card-img-top {
            height: 200px;
            object-fit: cover;
            background-color: #f8f9fa;
            transition: transform 0.3s ease;
        }

        .resource-card:hover .card-img-top {
            transform: scale(1.05);
        }

.card-img-overlay {
    /* position: absolute; */
    /* bottom: 0; */
    /* left: 0; */
    /* right: 0; */
    background: linear-gradient(to top, rgba(0, 0, 0, 0.7) 0%, rgba(0, 0, 0, 0.3) 40%, transparent 100%);
    padding: 0.5rem 0.75rem;
    display: flex;
    flex-wrap: wrap;
    flex-basis: fit-content;
    align-items: flex-end;
    height: auto;
    align-content: end;
    /* text-align: right !important; */
    /* float: right; */
    flex-direction: column-reverse;
}

        .overlay-tag {
            background-color: rgba(255,255,255,0.95);
            backdrop-filter: blur(4px);
            border: none;
            color: inherit;
            font-weight: 600;
            text-shadow: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .overlay-tag.tag-resource-type {
            color: var(--primary-blue);
        }

        .overlay-tag.tag-category {
            color: var(--accent-red);
        }

        .overlay-tag.tag-ministry {
            color: var(--dark-blue);
        }

        .card-title {
            color: var(--dark-blue);
            font-weight: 600;
            font-size: 1.25rem;
        }

        .card-text {
            color: #6c757d;
            line-height: 1.5;
        }

        .card-description {
            display: -webkit-box;
            -webkit-line-clamp: 5;
            -webkit-box-orient: vertical;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .card-description.expanded {
            display: block;
            -webkit-line-clamp: unset;
        }

        .read-more-btn {
            display: none;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            margin: 6px auto 10px;
            background: #fff;
            border: 1.5px solid #1d486b;
            border-radius: 50%;
            color: #1d486b;
            cursor: pointer;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            flex-shrink: 0;
        }

        .read-more-btn i {
            font-size: 0.75rem;
            transition: transform 0.3s ease;
            pointer-events: none;
        }

        .read-more-btn.expanded i {
            transform: rotate(180deg);
        }

        .read-more-btn:hover {
            background: #1d486b;
            color: #fff;
            box-shadow: 0 2px 8px rgba(29,72,107,0.25);
        }

        .read-more-btn.visible {
            display: flex;
        }

        .tag {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            margin: 2px 0px; 
        }

        .tag i {
            font-size: 0.75rem;
        }

        .tag-resource-type {
            background-color: #e8f0f7;
            border: 1px solid var(--primary-blue);
            color: var(--primary-blue);
        }

        .tag-category {
            background-color: #f8e8e9;
            border: 1px solid var(--accent-red);
            color: var(--accent-red);
        }

        .tag-ministry {
            background-color: #e6ecf1;
            border: 1px solid var(--dark-blue);
            color: var(--dark-blue);
        }

        .tag-language {
            background-color: #f0f8f0;
            border: 1px solid #28a745;
            color: #28a745;
        }

        .btn-primary {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }

        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--dark-blue);
            border-color: var(--dark-blue);
        }

        .load-more-btn {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }

        .load-more-btn:hover {
            background-color: var(--dark-blue);
            border-color: var(--dark-blue);
        }

        .results-info {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .pagination-info {
            color: #6c757d;
            font-size: 0.9rem;
        }

        /* Navbar Styles */
        .navbar {
            background: #ffffff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-blue) !important;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .navbar-brand img {
            height: 50px;
            width: auto;
        }

        .navbar-nav .nav-link {
            color: #4b5563 !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            transition: all 0.3s ease;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: var(--primary-blue) !important;
            background-color: #f3f4f6;
            border-radius: 5px;
        }

        .navbar-toggler {
            border-color: #d1d5db;
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%2875, 85, 99, 0.9%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        /* Footer Styles */
        .site-footer {
            background: linear-gradient(135deg, var(--dark-blue) 0%, var(--primary-blue) 100%);
            color: white;
            padding: 3rem 0 1rem;
            margin-top: 4rem;
        }

        .site-footer h5 {
            color: white;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .site-footer a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .site-footer a:hover {
            color: white;
            padding-left: 5px;
        }

        .site-footer .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: rgba(255,255,255,0.1);
            border-radius: 50%;
            margin-right: 10px;
            transition: all 0.3s ease;
        }

        .site-footer .social-links a:hover {
            background-color: var(--accent-red);
            transform: translateY(-3px);
            padding-left: 0;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 1.5rem;
            margin-top: 2rem;
            text-align: center;
            color: rgba(255,255,255,0.6);
        }

        .admin-link {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1050;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        /* Resource Detail Page Styles */
        .hero-section {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            color: white;
            padding: 4rem 0 4rem 0;
        }

        .hero-image {
            max-height: 300px;
            width: 100%;
            object-fit: cover;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .resource-meta {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-top: -2rem;
            position: relative;
            z-index: 10;
        }

        .content-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .btn-outline-primary {
            border-color: var(--primary-blue);
            color: var(--primary-blue);
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
            color: white;
        }

        .btn-outline-light {
            border-color: rgba(255,255,255,0.8);
            color: white;
        }

        .btn-outline-light:hover {
            background-color: white;
            border-color: white;
            color: var(--primary-blue);
        }

        .file-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s ease;
        }

        .file-item:hover {
            background: #e9ecef;
            transform: translateY(-1px);
        }

        .file-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }

         .file-pdf { background-color: #1b4567  !important; }
        .file-doc { background-color: #1b4567 !important; }
        .file-xls { background-color: #1b4567 !important; }
        .file-ppt { background-color: #1b4567 !important; }
        .file-img { background-color: #1b4567 !important; }
        .file-default { background-color: #1b4567 !important; }


        .related-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: 100%;
        }

        .related-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 25px rgba(0,0,0,0.15);
        }

        .related-card .card-img-top {
            height: 180px;
            object-fit: cover;
        }

        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 1rem;
        }

        .breadcrumb-item a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
        }

        .breadcrumb-item a:hover {
            color: white;
        }

        .breadcrumb-item.active {
            color: rgba(255,255,255,0.6);
        }

        @media (max-width: 768px) {
            .navbar-brand {
                font-size: 1.2rem;
            }

            .navbar-brand img {
                height: 40px;
            }

            .site-footer {
                padding: 2rem 0 1rem;
                margin-top: 3rem;
            }

            .site-footer h5 {
                font-size: 1.1rem;
                margin-bottom: 1rem;
            }

            .admin-link {
                bottom: 15px;
                right: 15px;
            }

            .card-img-top {
                height: 180px;
            }

            .card-title {
                font-size: 1.1rem;
            }

            .card-img-overlay {
                padding: 0.375rem 0.5rem;
                gap: 0.25rem;
            }

            .overlay-tag {
                font-size: 0.65rem;
                padding: 0.2rem 0.4rem;
            }

            .hero-section {
                padding: 2rem 0 3rem 0;
            }

            .hero-image {
                margin-bottom: 1rem;
            }

            .resource-meta {
                margin-top: 0;
                padding: 1.5rem;
            }

            .content-section {
                padding: 1.5rem;
            }

            .btn-outline-light {
                margin-bottom: 1rem;
            }
        }
    </style>
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
                    <li class="nav-item">
                        <a class="nav-link" href="/websites.php">
                            <i class="fas fa-window-maximize"></i> Websites
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
