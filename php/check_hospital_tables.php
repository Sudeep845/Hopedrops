<?php
/**
 * Database Structure Check
 * Verifies if all required tables exist for hospital registration
 */

require_once 'db_connect.php';

header('Content-Type: text/plain');

try {
    $db = getDBConnection();
    
    echo "=== DATABASE STRUCTURE CHECK ===\n\n";
    
    // Check if hospitals table exists
    $result = $db->query("SHOW TABLES LIKE 'hospitals'");
    if ($result->rowCount() > 0) {
        echo "✅ hospitals table exists\n";
        
        // Check hospitals table structure
        echo "\n--- hospitals table structure ---\n";
        $columns = $db->query("DESCRIBE hospitals");
        while ($column = $columns->fetch(PDO::FETCH_ASSOC)) {
            echo sprintf("%-20s %-20s %s\n", 
                $column['Field'], 
                $column['Type'], 
                $column['Null'] === 'NO' ? 'NOT NULL' : 'NULL'
            );
        }
    } else {
        echo "❌ hospitals table does NOT exist\n";
    }
    
    // Check if blood_inventory table exists
    echo "\n";
    $result = $db->query("SHOW TABLES LIKE 'blood_inventory'");
    if ($result->rowCount() > 0) {
        echo "✅ blood_inventory table exists\n";
        
        // Check blood_inventory table structure
        echo "\n--- blood_inventory table structure ---\n";
        $columns = $db->query("DESCRIBE blood_inventory");
        while ($column = $columns->fetch(PDO::FETCH_ASSOC)) {
            echo sprintf("%-20s %-20s %s\n", 
                $column['Field'], 
                $column['Type'], 
                $column['Null'] === 'NO' ? 'NOT NULL' : 'NULL'
            );
        }
    } else {
        echo "❌ blood_inventory table does NOT exist\n";
    }
    
    // Check users table
    echo "\n";
    $result = $db->query("SHOW TABLES LIKE 'users'");
    if ($result->rowCount() > 0) {
        echo "✅ users table exists\n";
    } else {
        echo "❌ users table does NOT exist\n";
    }
    
    // List all tables
    echo "\n--- All tables in bloodbank_db ---\n";
    $tables = $db->query("SHOW TABLES");
    while ($table = $tables->fetch(PDO::FETCH_NUM)) {
        echo "- " . $table[0] . "\n";
    }
    
    echo "\n=== TEST HOSPITAL REGISTRATION ===\n";
    
    // Test if we can insert into users table
    echo "Testing users table insert... ";
    $testStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = 'test_hospital_check'");
    $testStmt->execute();
    $count = $testStmt->fetchColumn();
    
    if ($count > 0) {
        echo "Test user already exists\n";
    } else {
        echo "Ready for test insert\n";
    }
    
    echo "\n=== CHECK COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    echo "Stack Trace: " . $e->getTraceAsString() . "\n";
}
?>