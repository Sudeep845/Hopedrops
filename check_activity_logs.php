<?php
require_once 'php/db_connect.php';

try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("DESCRIBE activity_logs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Columns in activity_logs table:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>