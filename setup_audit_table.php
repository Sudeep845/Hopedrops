<?php
try {
    // Connect to MySQL (without specifying database)
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo->exec('CREATE DATABASE IF NOT EXISTS hopedrops');
    echo "Database 'hopedrops' created or already exists\n";
    
    // Now connect to the hopedrops database
    $pdo = new PDO('mysql:host=localhost;dbname=hopedrops', 'root', '');
    
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