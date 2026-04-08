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
            if (isset($_GET['id'])) {
                // Single report — full record including body
                $id   = (int) $_GET['id'];
                $stmt = $pdo->prepare('SELECT * FROM status_reports WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $row = $stmt->fetch();
                if (!$row) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Report not found']);
                    exit;
                }
                echo json_encode($row);
            } else {
                // List — omit body to keep payload light
                $stmt = $pdo->query(
                    'SELECT id, title, source_agent, created_at
                     FROM status_reports
                     ORDER BY created_at DESC'
                );
                echo json_encode($stmt->fetchAll());
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['title'])) {
                http_response_code(400);
                echo json_encode(['error' => 'title is required']);
                exit;
            }
            if (!isset($data['body'])) {
                http_response_code(400);
                echo json_encode(['error' => 'body is required']);
                exit;
            }
            $stmt = $pdo->prepare(
                "INSERT INTO status_reports (title, body, source_agent)
                 VALUES (:title, :body, :source_agent)"
            );
            $stmt->execute([
                ':title'        => $data['title'],
                ':body'         => $data['body'],
                ':source_agent' => $data['source_agent'] ?? '',
            ]);
            $id  = $pdo->lastInsertId();
            $row = $pdo->query("SELECT * FROM status_reports WHERE id = $id")->fetch();
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
