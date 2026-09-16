<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {

        case 'GET':
            $stmt = $pdo->query(
                'SELECT id, content, created_at
                 FROM journal_entries
                 ORDER BY created_at DESC'
            );
            echo json_encode($stmt->fetchAll());
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $content = trim($data['content'] ?? '');
            if ($content === '') {
                http_response_code(400);
                echo json_encode(['error' => 'content is required']);
                exit;
            }
            $stmt = $pdo->prepare('INSERT INTO journal_entries (content) VALUES (:content)');
            $stmt->execute([':content' => $content]);
            $id  = $pdo->lastInsertId();
            $row = $pdo->query("SELECT * FROM journal_entries WHERE id = $id")->fetch();
            http_response_code(201);
            echo json_encode($row);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
