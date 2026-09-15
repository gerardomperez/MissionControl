<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = getDB();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query(
            "SELECT id, name, role, description, specializations, status, avatar_emoji, model
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
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required field: id']);
            exit;
        }
        
        $allowedFields = ['role', 'description', 'specializations', 'status', 'avatar_emoji', 'model'];
        $updates = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No valid fields to update']);
            exit;
        }
        
        $params[] = $data['id'];
        $sql = "UPDATE subagents SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
        exit;
    }
    
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
