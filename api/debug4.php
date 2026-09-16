<?php
header('Content-Type: text/plain');
$tmpDb = '/tmp/henry.db';
echo "Testing with /tmp/henry.db\n";
echo "File exists: " . (file_exists($tmpDb) ? 'yes' : 'no') . "\n";
echo "File readable: " . (is_readable($tmpDb) ? 'yes' : 'no') . "\n";
echo "File writable: " . (is_writable($tmpDb) ? 'yes' : 'no') . "\n";
echo "Directory writable: " . (is_writable('/tmp') ? 'yes' : 'no') . "\n";

try {
    $pdo = new PDO('sqlite:' . $tmpDb);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected!\n";
    
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM tasks');
    $result = $stmt->fetch();
    echo "Task count: " . $result['count'] . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
