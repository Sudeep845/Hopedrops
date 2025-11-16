<?php
require_once 'php/db_connect.php';

try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("DESCRIBE hospitals");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Columns in hospitals table:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>