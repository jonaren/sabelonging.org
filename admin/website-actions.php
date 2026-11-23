<?php
// JSON endpoint for the Websites admin page: screenshot capture and title/description auto-fill.
ob_start();
require_once 'resources-config.php';
require_once __DIR__ . '/../includes/websites-lib.php';
ob_end_clean();

header('Content-Type: application/json');
set_time_limit(180);

function respond(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body);
    exit;
}

if (!isLoggedIn()) respond(401, ['success' => false, 'error' => 'Please log in again.']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    respond(403, ['success' => false, 'error' => 'Your session expired. Reload the page and try again.']);
}
// Don't hold the session lock during slow external calls (keeps other admin tabs responsive)
session_write_close();

ensureWebsiteTables($resources_pdo);
$action = $_POST['action'] ?? '';

try {
    if ($action === 'meta') {
        $url = normalizeWebsiteUrl($_POST['url'] ?? '');
        if (!$url) respond(400, ['success' => false, 'error' => 'That doesn\'t look like a valid web address.']);
        $meta = fetchWebsiteMeta($url);
        respond(200, ['success' => true, 'url' => $url] + $meta);
    }

    if ($action === 'capture') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $resources_pdo->prepare("SELECT url FROM websites WHERE id = ?");
        $stmt->execute([$id]);
        $url = $stmt->fetchColumn();
        if (!$url) respond(404, ['success' => false, 'error' => 'Website not found.']);

        try {
            $bytes = captureWebsiteScreenshot($url, getWebsiteSetting($resources_pdo, 'pagespeed_api_key'));
            $shot = storeWebsiteScreenshot($id, $bytes, 'jpg');
        } catch (Exception $e) {
            // Record why it failed so the admin list can show it
            $resources_pdo->prepare("UPDATE websites SET screenshot_error = ? WHERE id = ?")->execute([mb_substr($e->getMessage(), 0, 500), $id]);
            throw $e;
        }
        $resources_pdo->prepare("UPDATE websites SET screenshot_url=?, screenshot_source='auto', screenshot_updated_at=NOW(), screenshot_error=NULL WHERE id=?")
            ->execute([$shot, $id]);
        respond(200, ['success' => true, 'screenshot_url' => $shot]);
    }

    if ($action === 'reorder') {
        $ids = array_values(array_filter(array_map('intval', explode(',', $_POST['ids'] ?? ''))));
        if (!$ids) respond(400, ['success' => false, 'error' => 'Nothing to reorder.']);
        $stmt = $resources_pdo->prepare("UPDATE websites SET display_order = ? WHERE id = ?");
        $resources_pdo->beginTransaction();
        foreach ($ids as $i => $id) $stmt->execute([$i + 1, $id]);
        $resources_pdo->commit();
        respond(200, ['success' => true]);
    }

    respond(400, ['success' => false, 'error' => 'Unknown action.']);
} catch (Exception $e) {
    respond(500, ['success' => false, 'error' => $e->getMessage()]);
}
