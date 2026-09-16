<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$pdo = getDB();
$action = $_GET['action'] ?? 'tables';

try {
    switch ($action) {

        case 'tables':
            // List all tables with row counts
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $result = [];
            foreach ($tables as $table) {
                $count = $pdo->query("SELECT COUNT(*) FROM " . $pdo->quote($table))->fetchColumn();
                $result[] = ['name' => $table, 'rows' => (int)$count];
            }
            echo json_encode($result);
            break;

        case 'schema':
            $table = $_GET['table'] ?? '';
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid table name']);
                exit;
            }
            $stmt = $pdo->query("PRAGMA table_info(" . $pdo->quote($table) . ")");
            echo json_encode($stmt->fetchAll());
            break;

        case 'rows':
            $table = $_GET['table'] ?? '';
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid table name']);
                exit;
            }
            $limit  = min((int)($_GET['limit']  ?? 50), 200);
            $offset = max((int)($_GET['offset'] ?? 0), 0);
            $stmt = $pdo->prepare("SELECT * FROM \"$table\" LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            $total = (int)$pdo->query("SELECT COUNT(*) FROM \"$table\"")->fetchColumn();
            echo json_encode(['rows' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
