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
            if (empty($_GET['task_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'task_id is required']);
                exit;
            }
            $stmt = $pdo->prepare(
                'SELECT id, task_id, author, body, created_at
                 FROM task_comments
                 WHERE task_id = :task_id
                 ORDER BY created_at ASC'
            );
            $stmt->execute([':task_id' => (int) $_GET['task_id']]);
            echo json_encode($stmt->fetchAll());
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['task_id']) || empty($data['body'])) {
                http_response_code(400);
                echo json_encode(['error' => 'task_id and body are required']);
                exit;
            }
            $stmt = $pdo->prepare(
                "INSERT INTO task_comments (task_id, author, body)
                 VALUES (:task_id, :author, :body)"
            );
            $stmt->execute([
                ':task_id' => (int) $data['task_id'],
                ':author'  => trim($data['author'] ?? 'G') ?: 'G',
                ':body'    => $data['body'],
            ]);
            $id  = $pdo->lastInsertId();
            $row = $pdo->query("SELECT * FROM task_comments WHERE id = $id")->fetch();
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
