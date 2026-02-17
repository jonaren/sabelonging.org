<?php
/**
 * Script to set up the three main categories for Belonging Resources
 * Run this once to establish the fixed categories
 */

require_once 'resources-config.php';
requireLogin();

// Only admins can run this
if (!isAdmin()) {
    die('Unauthorized. Only administrators can run this script.');
}

try {
    $resources_pdo->beginTransaction();

    // Check if slug column exists
    $stmt = $resources_pdo->query("SHOW COLUMNS FROM categories LIKE 'slug'");
    $hasSlug = $stmt->rowCount() > 0;

    // Define the three main categories
    $categories = [
        [
            'name' => 'Leadership Development',
            'slug' => 'leadership-development',
            'description' => 'Resources focused on developing effective leadership skills and capabilities',
            'display_order' => 1
        ],
        [
            'name' => 'Spiritual Life',
            'slug' => 'spiritual-life',
            'description' => 'Resources for deepening spiritual growth and faith practices',
            'display_order' => 2
        ],
        [
            'name' => 'Youth',
            'slug' => 'youth',
            'description' => 'Resources specifically designed for youth ministry and young people',
            'display_order' => 3
        ]
    ];

    // Insert or update each category
    foreach ($categories as $category) {
        if ($hasSlug) {
            // Table has slug column
            $stmt = $resources_pdo->prepare("
                INSERT INTO categories (name, slug, description, display_order, is_active)
                VALUES (?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE
                    description = ?,
                    display_order = ?,
                    is_active = 1
            ");
            $stmt->execute([
                $category['name'],
                $category['slug'],
                $category['description'],
                $category['display_order'],
                $category['description'],
                $category['display_order']
            ]);
        } else {
            // Table doesn't have slug column
            $stmt = $resources_pdo->prepare("
                INSERT INTO categories (name, description)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE
                    description = ?
            ");
            $stmt->execute([
                $category['name'],
                $category['description'],
                $category['description']
            ]);
        }
    }

    $resources_pdo->commit();

    echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Categories Setup - Success</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
    <div class='container mt-5'>
        <div class='alert alert-success'>
            <h4 class='alert-heading'>Success!</h4>
            <p>The three main categories have been set up successfully:</p>
            <ul>
                <li>Leadership Development</li>
                <li>Spiritual Life</li>
                <li>Youth</li>
            </ul>
        </div>
        <a href='categories.php' class='btn btn-primary'>View Categories</a>
        <a href='dashboard.php' class='btn btn-secondary'>Back to Dashboard</a>
    </div>
</body>
</html>";

} catch (Exception $e) {
    $resources_pdo->rollBack();
    echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Categories Setup - Error</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
    <div class='container mt-5'>
        <div class='alert alert-danger'>
            <h4 class='alert-heading'>Error!</h4>
            <p>Failed to set up categories: " . htmlspecialchars($e->getMessage()) . "</p>
        </div>
        <a href='dashboard.php' class='btn btn-secondary'>Back to Dashboard</a>
    </div>
</body>
</html>";
}
?>
