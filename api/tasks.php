<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {

        case 'GET':
            $includeHidden = isset($_GET['include_hidden']) && $_GET['include_hidden'] == '1';
            if ($includeHidden) {
                $stmt = $pdo->query(
                    'SELECT * FROM tasks ORDER BY status, sort_order, created_at ASC'
                );
            } else {
                $stmt = $pdo->query(
                    "SELECT * FROM tasks WHERE NOT (status = 'done' AND hidden = 1) ORDER BY status, sort_order, created_at ASC"
                );
            }
            echo json_encode($stmt->fetchAll());
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['title'])) {
                http_response_code(400);
                echo json_encode(['error' => 'title is required']);
                exit;
            }
            $stmt = $pdo->prepare(
                'INSERT INTO tasks (title, description, assignee, status, status_changed_at)
                 VALUES (:title, :description, :assignee, :status, strftime(\'%Y-%m-%dT%H:%M:%S\',\'now\'))'
            );
            $stmt->execute([
                ':title'       => $data['title'],
                ':description' => $data['description'] ?? '',
                ':assignee'    => $data['assignee'] ?? '',
                ':status'      => $data['status'] ?? 'backlog',
            ]);
            $id = $pdo->lastInsertId();
            $row = $pdo->query("SELECT * FROM tasks WHERE id = $id")->fetch();
            http_response_code(201);
            echo json_encode($row);
            break;

        case 'PATCH':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'id is required']);
                exit;
            }
            $id = (int) $data['id'];

            if (isset($data['status'])) {
                $validStatuses = ['backlog', 'in_progress', 'blocked', 'review', 'done'];
                if (!in_array($data['status'], $validStatuses)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'invalid status']);
                    exit;
                }
                $stmt = $pdo->prepare(
                    "UPDATE tasks SET status = :status, status_changed_at = strftime('%Y-%m-%dT%H:%M:%S','now') WHERE id = :id"
                );
                $stmt->execute([':status' => $data['status'], ':id' => $id]);
            } elseif (array_key_exists('notes', $data)) {
                $stmt = $pdo->prepare('UPDATE tasks SET notes = :notes WHERE id = :id');
                $stmt->execute([':notes' => $data['notes'], ':id' => $id]);
            } elseif (isset($data['hidden'])) {
                $stmt = $pdo->prepare('UPDATE tasks SET hidden = :hidden WHERE id = :id');
                $stmt->execute([':hidden' => (int) $data['hidden'], ':id' => $id]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'nothing to update']);
                exit;
            }

            $row = $pdo->query("SELECT * FROM tasks WHERE id = $id")->fetch();
            echo json_encode($row);
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'id is required']);
                exit;
            }
            $id = (int) $data['id'];
            $pdo->prepare('DELETE FROM tasks WHERE id = :id')->execute([':id' => $id]);
            echo json_encode(['deleted' => $id]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
