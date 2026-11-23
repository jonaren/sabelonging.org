<?php
// The three Territorial Priorities, in the order they appear in the logo's ribbon (top to bottom).
// 'key' matches the category name stored in the database.
const TERRITORIAL_PRIORITIES = [
    'youth' => [
        'key'   => 'Youth',
        'page'  => '/youth.php',
        'blurb' => 'Resources designed for youth ministry and the young people it serves.',
    ],
    'spiritual-life' => [
        'key'   => 'Spiritual Life',
        'page'  => '/spiritual-life.php',
        'blurb' => 'Resources for deepening spiritual growth and faith practices.',
    ],
    'leadership' => [
        'key'   => 'Leadership Development',
        'page'  => '/leadership-development.php',
        'blurb' => 'Resources for developing effective leadership skills and capabilities.',
    ],
];

/** Published resource counts per priority, plus 'all'. Returns [] if the database is unavailable. */
function priorityCounts(): array {
    global $settings_pdo;
    if (!isset($settings_pdo)) return [];
    try {
        $rows = $settings_pdo->query("
            SELECT c.name, COUNT(DISTINCT r.id) AS n
            FROM categories c
            JOIN resource_categories rc ON rc.category_id = c.id
            JOIN resources r ON r.id = rc.resource_id AND r.is_published = 1
            GROUP BY c.name
        ")->fetchAll(PDO::FETCH_KEY_PAIR);
        $rows['all'] = (int)$settings_pdo->query("SELECT COUNT(*) FROM resources WHERE is_published = 1")->fetchColumn();
        return array_map('intval', $rows);
    } catch (PDOException $e) {
        return [];
    }
}
