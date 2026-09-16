<?php
header('Content-Type: text/plain');
try {
    echo "Step 1: Including db.php...\n";
    require_once __DIR__ . '/../includes/db.php';
    echo "Step 2: db.php included successfully\n";
    
    echo "Step 3: Calling getDB()...\n";
    $pdo = getDB();
    echo "Step 4: Database connection successful\n";
    
    echo "Step 5: Querying tasks...\n";
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM tasks');
    $result = $stmt->fetch();
    echo "Step 6: Query successful. Task count: " . $result['count'] . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
