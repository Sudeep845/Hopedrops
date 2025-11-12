<?php
/**
 * Quick Database Error Checker
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

header('Content-Type: text/plain');

echo "=== QUICK DATABASE ERROR CHECK ===\n\n";

try {
    $db = getDBConnection();
    echo "✅ Database connection: OK\n";
    
    // Check if bloodbank_db database exists and is selected
    $result = $db->query("SELECT DATABASE() as current_db");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    echo "✅ Current database: " . ($row['current_db'] ?? 'None') . "\n";
    
    // Check users table
    try {
        $result = $db->query("DESCRIBE users");
        echo "✅ Users table: EXISTS\n";
    } catch (Exception $e) {
        echo "❌ Users table: MISSING - " . $e->getMessage() . "\n";
    }
    
    // Check hospitals table
    try {
        $result = $db->query("DESCRIBE hospitals");
        echo "✅ Hospitals table: EXISTS\n";
    } catch (Exception $e) {
        echo "❌ Hospitals table: MISSING - " . $e->getMessage() . "\n";
    }
    
    // Test simple insert
    echo "\n--- Testing Simple User Insert ---\n";
    
    $timestamp = time();
    $username = "test_user_$timestamp";
    
    try {
        $stmt = $db->prepare("INSERT INTO users (username, password, role, full_name, email, phone, address, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([
            $username,
            password_hash('test123', PASSWORD_DEFAULT),
            'hospital',
            'Test User',
            "test$timestamp@test.com",
            '1234567890',
            'Test Address'
        ]);
        
        echo "✅ User insert: SUCCESS (ID: " . $db->lastInsertId() . ")\n";
        
        // Clean up
        $db->prepare("DELETE FROM users WHERE username = ?")->execute([$username]);
        echo "✅ Cleanup: SUCCESS\n";
        
    } catch (Exception $e) {
        echo "❌ User insert FAILED: " . $e->getMessage() . "\n";
        echo "Error Code: " . $e->getCode() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
}

echo "\n=== CHECK COMPLETE ===\n";
?>