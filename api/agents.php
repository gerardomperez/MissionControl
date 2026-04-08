<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->query(
        "SELECT id, name, role, description, specializations, status, avatar_emoji
         FROM subagents
         ORDER BY
             CASE WHEN name = 'Henry' THEN 0 ELSE 1 END,
             CASE status
                 WHEN 'active'  THEN 1
                 WHEN 'standby' THEN 2
                 WHEN 'idle'    THEN 3
                 WHEN 'offline' THEN 4
                 ELSE 5
             END,
             name ASC"
    );
    echo json_encode($stmt->fetchAll());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
