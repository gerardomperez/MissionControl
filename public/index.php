<?php
// Detect base URL so the app works both at web root (VPS) and as a subdirectory (WAMP dev)
$_scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
define('BASE_URL', $_scriptDir === '' ? '' : $_scriptDir);

$page = $_GET['page'] ?? 'orchestrator';
$allowed = ['orchestrator', 'calendar', 'statuslog', 'team', 'skills', 'knowledge', 'journal', 'database'];
if (!in_array($page, $allowed)) {
    http_response_code(404);
    echo '404 — Page not found';
    exit;
}
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../pages/' . $page . '.php';
require_once __DIR__ . '/../includes/footer.php';
