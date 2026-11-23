<?php
// App Engine Standard PHP uses index.php as a router script.
// Route to the correct PHP file if it exists.
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__ . $uri;
if ($uri !== '/' && $uri !== '/index.php' && file_exists($file) && !is_dir($file) && substr($uri, -4) === '.php') {
    chdir(dirname($file));
    require $file;
    exit;
}

// Home page: the priorities ribbon, then the full resource library (loaded from /api/get-resources.php).
$page_title = 'Belonging Resources';
$meta_description = 'Resources for The Salvation Army USA Central Territory’s three priorities: Youth, Spiritual Life and Leadership Development.';
include 'includes/header.php';
include 'includes/banner.php';
include 'includes/library.php';
include 'includes/footer.php';
