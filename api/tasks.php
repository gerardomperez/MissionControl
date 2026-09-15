<?php
error_log("[Mission Control] tasks.php started");
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
    error_log("[Mission Control] Method: $method");
    switch ($method) {

        case 'GET':
            error_log("[Mission Control] GET request received");
            $includeHidden = isset($_GET['include_hidden']) && $_GET['include_hidden'] == '1';
            error_log("[Mission Control] include_hidden: " . ($includeHidden ? 'yes' : 'no'));
            if ($includeHidden) {
                $stmt = $pdo->query(
                    'SELECT t.*, p.name AS project_name FROM tasks t LEFT JOIN projects p ON p.id = t.project_id ORDER BY t.status, t.sort_order, t.created_at ASC'
                );
            } else {
                $stmt = $pdo->query(
                    "SELECT t.*, p.name AS project_name FROM tasks t LEFT JOIN projects p ON p.id = t.project_id WHERE NOT (t.status = 'done' AND t.hidden = 1) ORDER BY t.status, t.sort_order, t.created_at ASC"
                );
            }
            $results = $stmt->fetchAll();
            error_log("[Mission Control] Found " . count($results) . " tasks");
            echo json_encode($results);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['title'])) {
                http_response_code(400);
                echo json_encode(['error' => 'title is required']);
                exit;
            }
            $projectId = !empty($data['project_id']) ? (int) $data['project_id'] : null;
            $predecessorId = !empty($data['predecessor_task_id']) ? (int) $data['predecessor_task_id'] : null;
            $stmt = $pdo->prepare(
                'INSERT INTO tasks (title, description, assignee, status, project_id, predecessor_task_id, status_changed_at)
                 VALUES (:title, :description, :assignee, :status, :project_id, :predecessor_task_id, strftime(\'%Y-%m-%dT%H:%M:%S\',\'now\'))'
            );
            $stmt->execute([
                ':title'               => $data['title'],
                ':description'         => $data['description'] ?? '',
                ':assignee'            => $data['assignee'] ?? '',
                ':status'              => $data['status'] ?? 'backlog',
                ':project_id'          => $projectId,
                ':predecessor_task_id' => $predecessorId,
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
                $validStatuses = ['backlog', 'on_deck', 'in_progress', 'blocked', 'review', 'done'];
                if (!in_array($data['status'], $validStatuses)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'invalid status']);
                    exit;
                }
                $stmt = $pdo->prepare(
                    "UPDATE tasks SET status = :status, status_changed_at = strftime('%Y-%m-%dT%H:%M:%S','now') WHERE id = :id"
                );
                $stmt->execute([':status' => $data['status'], ':id' => $id]);
            } elseif (array_key_exists('description', $data)) {
                $stmt = $pdo->prepare('UPDATE tasks SET description = :description WHERE id = :id');
                $stmt->execute([':description' => $data['description'], ':id' => $id]);
            } elseif (array_key_exists('notes', $data)) {
                $stmt = $pdo->prepare('UPDATE tasks SET notes = :notes WHERE id = :id');
                $stmt->execute([':notes' => $data['notes'], ':id' => $id]);
            } elseif (isset($data['hidden'])) {
                $stmt = $pdo->prepare('UPDATE tasks SET hidden = :hidden WHERE id = :id');
                $stmt->execute([':hidden' => (int) $data['hidden'], ':id' => $id]);
            } elseif (array_key_exists('project_id', $data)) {
                $projectId = !empty($data['project_id']) ? (int) $data['project_id'] : null;
                $stmt = $pdo->prepare('UPDATE tasks SET project_id = :project_id WHERE id = :id');
                $stmt->execute([':project_id' => $projectId, ':id' => $id]);
            } elseif (array_key_exists('predecessor_task_id', $data)) {
                $predecessorId = !empty($data['predecessor_task_id']) ? (int) $data['predecessor_task_id'] : null;
                $stmt = $pdo->prepare('UPDATE tasks SET predecessor_task_id = :predecessor_task_id WHERE id = :id');
                $stmt->execute([':predecessor_task_id' => $predecessorId, ':id' => $id]);
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
    error_log("[Mission Control] ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
