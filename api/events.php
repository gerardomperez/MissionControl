<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
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
                'INSERT INTO calendar_events (title, scheduled_at, event_status, source, cron_expression)
                 VALUES (:title, :scheduled_at, :event_status, :source, :cron_expression)'
            );
            $stmt->execute([
                ':title'           => $data['title'],
                ':scheduled_at'    => $data['scheduled_at'],
                ':event_status'    => $data['event_status'] ?? 'pending',
                ':source'          => $data['source'] ?? '',
                ':cron_expression' => $data['cron_expression'] ?? '',
            ]);
            $id  = $pdo->lastInsertId();
            $row = $pdo->query("SELECT * FROM calendar_events WHERE id = $id")->fetch();
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
            $id      = (int) $data['id'];
            $allowed = ['success', 'failed', 'pending'];
            if (empty($data['event_status']) || !in_array($data['event_status'], $allowed)) {
                http_response_code(400);
                echo json_encode(['error' => 'valid event_status is required']);
                exit;
            }
            $stmt = $pdo->prepare('UPDATE calendar_events SET event_status = :s WHERE id = :id');
            $stmt->execute([':s' => $data['event_status'], ':id' => $id]);
            $row = $pdo->query("SELECT * FROM calendar_events WHERE id = $id")->fetch();
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
