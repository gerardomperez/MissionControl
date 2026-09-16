<?php
header('Content-Type: text/plain');
try {
    $dbPath = __DIR__ . '/../../henry.db';
    echo "Database path: $dbPath\n";
    echo "File exists: " . (file_exists($dbPath) ? 'yes' : 'no') . "\n";
    echo "File readable: " . (is_readable($dbPath) ? 'yes' : 'no') . "\n";
    echo "File writable: " . (is_writable($dbPath) ? 'yes' : 'no') . "\n";
    
    echo "\nConnecting to database...\n";
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected!\n";
    
    echo "\nQuerying without WAL...\n";
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM tasks');
    $result = $stmt->fetch();
    echo "Task count: " . $result['count'] . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
