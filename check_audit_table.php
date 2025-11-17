<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=hopedrops', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if audit_logs table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'audit_logs'");
    
    if ($stmt->rowCount() > 0) {
        echo "Audit logs table exists\n";
        
        // Show table structure
        $stmt = $pdo->query('DESCRIBE audit_logs');
        echo "Table structure:\n";
        while ($row = $stmt->fetch()) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "Table not found. Creating...\n";
        
        // Read and execute SQL file
        $sql = file_get_contents('sql/audit_logs_table.sql');
        $pdo->exec($sql);
        
        echo "Table created successfully!\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>