<?php
// websites-lib.php — shared helpers for the Website Index (public page + admin).
// Tables are created on demand (CREATE TABLE IF NOT EXISTS), so no manual migration is needed.

const WEBSITE_TAG_GROUPS = [
    'type'  => ['label' => 'Site Type', 'help' => 'What the website is (e.g. Event, Ministry Site, Resource Hub)'],
    'owner' => ['label' => 'Owner',     'help' => 'Who owns or runs the website (e.g. Territorial HQ, a division)'],
];

function ensureWebsiteTables(PDO $pdo): void {
    static $done = false;
    if ($done) return;

    $pdo->exec("CREATE TABLE IF NOT EXISTS websites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        url VARCHAR(1000) NOT NULL,
        description TEXT,
        button_label VARCHAR(100) DEFAULT NULL,
        screenshot_url VARCHAR(1000) DEFAULT NULL,
        screenshot_source ENUM('auto','upload') DEFAULT NULL,
        screenshot_updated_at DATETIME DEFAULT NULL,
        screenshot_error VARCHAR(500) DEFAULT NULL,
        display_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_active_order (is_active, display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS website_tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tag_group ENUM('type','owner') NOT NULL,
        name VARCHAR(100) NOT NULL,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_group_name (tag_group, name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS website_tag_map (
        website_id INT NOT NULL,
        tag_id INT NOT NULL,
        PRIMARY KEY (website_id, tag_id),
        FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES website_tags(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $done = true;
}

/** Tags grouped by tag_group: ['type' => [...], 'owner' => [...]] */
function getWebsiteTags(PDO $pdo): array {
    $out = array_fill_keys(array_keys(WEBSITE_TAG_GROUPS), []);
    $rows = $pdo->query("
        SELECT t.*, COUNT(m.website_id) AS site_count
        FROM website_tags t
        LEFT JOIN website_tag_map m ON m.tag_id = t.id
        GROUP BY t.id
        ORDER BY t.display_order, t.name
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) $out[$r['tag_group']][] = $r;
    return $out;
}

/** Websites with their tags attached as ['tags' => ['type' => [..], 'owner' => [..]], 'tag_ids' => [..]] */
function getWebsites(PDO $pdo, bool $activeOnly = false): array {
    $sql = "SELECT * FROM websites" . ($activeOnly ? " WHERE is_active = 1" : "") . " ORDER BY display_order, name";
    $sites = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    if (!$sites) return [];

    $byId = [];
    foreach ($sites as &$s) {
        $s['tags'] = array_fill_keys(array_keys(WEBSITE_TAG_GROUPS), []);
        $s['tag_ids'] = [];
        $byId[$s['id']] = &$s;
    }
    unset($s);

    $rows = $pdo->query("
        SELECT m.website_id, t.id, t.name, t.tag_group
        FROM website_tag_map m JOIN website_tags t ON t.id = m.tag_id
        ORDER BY t.display_order, t.name
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        if (!isset($byId[$r['website_id']])) continue;
        $byId[$r['website_id']]['tags'][$r['tag_group']][] = $r['name'];
        $byId[$r['website_id']]['tag_ids'][] = (int)$r['id'];
    }
    return $sites;
}

function setWebsiteTags(PDO $pdo, int $websiteId, array $tagIds): void {
    $pdo->prepare("DELETE FROM website_tag_map WHERE website_id = ?")->execute([$websiteId]);
    $ins = $pdo->prepare("INSERT IGNORE INTO website_tag_map (website_id, tag_id) VALUES (?, ?)");
    foreach (array_unique(array_map('intval', $tagIds)) as $tid) {
        if ($tid > 0) $ins->execute([$websiteId, $tid]);
    }
}

/** Normalises a user-entered URL; returns null if it isn't a valid http(s) URL. */
function normalizeWebsiteUrl(string $url): ?string {
    $url = trim($url);
    if ($url === '') return null;
    // Bare domains get https://; anything with another scheme (javascript:, ftp:) is rejected below
    if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) && !preg_match('#^[a-z]+:#i', $url)) $url = 'https://' . $url;
    if (!filter_var($url, FILTER_VALIDATE_URL)) return null;
    $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');
    return in_array($scheme, ['http', 'https'], true) ? $url : null;
}

/** "https://www.example.org/path/" -> "example.org/path" for the pseudo address bar. */
function websiteDisplayAddress(string $url): string {
    $host = parse_url($url, PHP_URL_HOST) ?: $url;
    $host = preg_replace('/^www\./i', '', $host);
    $path = rtrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
    return $host . $path;
}

function getWebsiteSetting(PDO $pdo, string $key): string {
    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    return (string)($stmt->fetchColumn() ?: '');
}

function setWebsiteSetting(PDO $pdo, string $key, string $value): void {
    $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$key, $value]);
}

/**
 * Captures a desktop screenshot of $url with the Google PageSpeed Insights API.
 * Returns raw JPEG bytes. Throws on failure.
 */
function captureWebsiteScreenshot(string $url, string $apiKey): string {
    if ($apiKey === '') {
        throw new Exception('No PageSpeed API key is set. Add one under "Screenshot settings" on this page, or upload a screenshot manually.');
    }
    $endpoint = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?' . http_build_query([
        'url' => $url, 'strategy' => 'desktop', 'category' => 'performance', 'key' => $apiKey,
    ]);
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_CONNECTTIMEOUT => 10]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    if ($body === false) throw new Exception('Could not reach the screenshot service: ' . $err);

    $data = json_decode($body, true);
    if (isset($data['error'])) {
        throw new Exception('Screenshot service error: ' . ($data['error']['message'] ?? 'unknown error'));
    }
    $shot = $data['lighthouseResult']['audits']['final-screenshot']['details']['data'] ?? null;
    if (!$shot || !str_contains($shot, ',')) {
        $why = $data['lighthouseResult']['runtimeError']['message'] ?? 'the page could not be loaded';
        throw new Exception('No screenshot returned: ' . $why);
    }
    $bytes = base64_decode(explode(',', $shot, 2)[1], true);
    if ($bytes === false || strlen($bytes) < 1000) throw new Exception('Screenshot data was empty.');
    return $bytes;
}

/** Uploads image bytes to the site's GCS bucket and returns the public URL. */
function storeWebsiteScreenshot(int $websiteId, string $bytes, string $ext = 'jpg'): string {
    $name = 'websites/site-' . $websiteId . '-' . time() . '.' . $ext;
    $storage = new Google\Cloud\Storage\StorageClient();
    $storage->bucket(RESOURCES_GCS_BUCKET)->upload($bytes, [
        'name' => $name,
        'metadata' => ['contentType' => $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg')],
    ]);
    return RESOURCES_UPLOAD_DIR . $name;
}

/** Fetches <title> and meta description from a page, for auto-filling the admin form. */
function fetchWebsiteMeta(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SACentralEquip-WebsiteIndex/1.0)',
        CURLOPT_ENCODING => '', // accept + transparently decode gzip/br
    ]);
    $html = curl_exec($ch);
    if (!$html) throw new Exception('Could not load that page.');

    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
    $xp = new DOMXPath($doc);
    $pick = function (array $queries) use ($xp) {
        foreach ($queries as $q) {
            $n = $xp->query($q)->item(0);
            $v = $n ? trim($n instanceof DOMAttr ? $n->value : $n->textContent) : '';
            if ($v !== '') return html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return '';
    };
    return [
        'title' => $pick(['//meta[@property="og:site_name"]/@content', '//meta[@property="og:title"]/@content', '//title']),
        'description' => $pick(['//meta[@name="description"]/@content', '//meta[@property="og:description"]/@content']),
    ];
}
