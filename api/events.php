<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {

        case 'GET':
            $start = $_GET['start'] ?? null;
            $end   = $_GET['end']   ?? null;
            if ($start && $end) {
                $stmt = $pdo->prepare(
                    'SELECT * FROM calendar_events
                     WHERE date(scheduled_at) >= date(:start)
                       AND date(scheduled_at) <= date(:end)
                     ORDER BY scheduled_at ASC'
                );
                $stmt->execute([':start' => $start, ':end' => $end]);
            } else {
                $stmt = $pdo->query('SELECT * FROM calendar_events ORDER BY scheduled_at ASC');
            }
            echo json_encode($stmt->fetchAll());
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['title']) || empty($data['scheduled_at'])) {
                http_response_code(400);
                echo json_encode(['error' => 'title and scheduled_at are required']);
                exit;
            }
            $stmt = $pdo->prepare(
                'INSERT INTO calendar_events (title, description, scheduled_at, status, source, cron_expression, model)
                 VALUES (:title, :description, :scheduled_at, :status, :source, :cron_expression, :model)'
            );
            $stmt->execute([
                ':title'           => $data['title'],
                ':description'     => $data['description'] ?? '',
                ':scheduled_at'    => $data['scheduled_at'],
                ':status'          => $data['status'] ?? 'pending',
                ':source'          => $data['source'] ?? '',
                ':cron_expression' => $data['cron_expression'] ?? '',
                ':model'           => $data['model'] ?? '',
            ]);
            $id  = $pdo->lastInsertId();
            $row = $pdo->query("SELECT * FROM calendar_events WHERE id = $id")->fetch();
            http_response_code(201);
            echo json_encode($row);
            break;

        case 'PATCH':
            $data = json_decode(file_get_contents('php://input'), true);
            $allowed = ['success', 'failed', 'pending'];

            // Bulk update: { "bulk": true, "status": "success", "before": "2026-03-24" }
            if (!empty($data['bulk'])) {
                if (empty($data['status']) || !in_array($data['status'], $allowed)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'valid status is required']);
                    exit;
                }
                $newStatus = $data['status'];
                if (!empty($data['before'])) {
                    // Mark all past-pending events before a given date
                    $stmt = $pdo->prepare(
                        "UPDATE calendar_events
                         SET status = :s
                         WHERE status = 'pending'
                           AND datetime(scheduled_at) < datetime(:before)"
                    );
                    $stmt->execute([':s' => $newStatus, ':before' => $data['before']]);
                } else {
                    // Mark ALL pending events (use with caution)
                    $stmt = $pdo->prepare("UPDATE calendar_events SET status = :s WHERE status = 'pending'");
                    $stmt->execute([':s' => $newStatus]);
                }
                echo json_encode(['updated' => $stmt->rowCount(), 'status' => $newStatus]);
                break;
            }

            // Single update: { "id": 42, "status": "success" } or { "id": 42, "model": "gpt-4o" }
            if (empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'id is required']);
                exit;
            }
            $id = (int) $data['id'];

            // Allow updating model field
            if (isset($data['model'])) {
                $stmt = $pdo->prepare('UPDATE calendar_events SET model = :m WHERE id = :id');
                $stmt->execute([':m' => $data['model'], ':id' => $id]);
                $row = $pdo->query("SELECT * FROM calendar_events WHERE id = $id")->fetch();
                echo json_encode($row);
                break;
            }

            if (empty($data['status']) || !in_array($data['status'], $allowed)) {
                http_response_code(400);
                echo json_encode(['error' => 'valid status is required']);
                exit;
            }
            $stmt = $pdo->prepare('UPDATE calendar_events SET status = :s WHERE id = :id');
            $stmt->execute([':s' => $data['status'], ':id' => $id]);
            $row = $pdo->query("SELECT * FROM calendar_events WHERE id = $id")->fetch();
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
            $pdo->prepare('DELETE FROM calendar_events WHERE id = :id')->execute([':id' => $id]);
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
