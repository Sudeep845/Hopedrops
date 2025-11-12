<?php
/**
 * Hospital Registration Error Diagnostic
 * Identifies common issues with hospital registration
 */

require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    $db = getDBConnection();
    
    $diagnostics = [
        'database_connection' => false,
        'users_table' => false,
        'hospitals_table' => false,
        'blood_inventory_table' => false,
        'foreign_keys' => false,
        'sample_data' => false,
        'common_issues' => []
    ];
    
    // Test database connection
    $diagnostics['database_connection'] = true;
    
    // Check users table
    $result = $db->query("SHOW TABLES LIKE 'users'");
    if ($result->rowCount() > 0) {
        $diagnostics['users_table'] = true;
        
        // Check users table structure for hospital role
        $columns = $db->query("DESCRIBE users");
        $hasRoleColumn = false;
        while ($column = $columns->fetch(PDO::FETCH_ASSOC)) {
            if ($column['Field'] === 'role') {
                $hasRoleColumn = true;
                break;
            }
        }
        if (!$hasRoleColumn) {
            $diagnostics['common_issues'][] = "users table missing 'role' column";
        }
    } else {
        $diagnostics['common_issues'][] = "users table does not exist";
    }
    
    // Check hospitals table
    $result = $db->query("SHOW TABLES LIKE 'hospitals'");
    if ($result->rowCount() > 0) {
        $diagnostics['hospitals_table'] = true;
    } else {
        $diagnostics['common_issues'][] = "hospitals table does not exist";
    }
    
    // Check blood_inventory table
    $result = $db->query("SHOW TABLES LIKE 'blood_inventory'");
    if ($result->rowCount() > 0) {
        $diagnostics['blood_inventory_table'] = true;
    } else {
        $diagnostics['common_issues'][] = "blood_inventory table does not exist";
    }
    
    // Check foreign key constraints
    if ($diagnostics['users_table'] && $diagnostics['hospitals_table']) {
        try {
            $testFK = $db->query("
                SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE 
                WHERE CONSTRAINT_SCHEMA = 'bloodbank_db' 
                AND TABLE_NAME = 'hospitals' 
                AND REFERENCED_TABLE_NAME = 'users'
            ")->fetchColumn();
            
            if ($testFK > 0) {
                $diagnostics['foreign_keys'] = true;
            } else {
                $diagnostics['common_issues'][] = "Foreign key constraint missing between hospitals and users tables";
            }
        } catch (Exception $e) {
            $diagnostics['common_issues'][] = "Could not check foreign keys: " . $e->getMessage();
        }
    }
    
    // Check for sample data
    if ($diagnostics['hospitals_table']) {
        $hospitalCount = $db->query("SELECT COUNT(*) FROM hospitals")->fetchColumn();
        if ($hospitalCount > 0) {
            $diagnostics['sample_data'] = true;
        }
    }
    
    // Test a mock registration
    $mockRegistration = [
        'can_insert_user' => false,
        'can_insert_hospital' => false,
        'can_insert_inventory' => false,
        'error_message' => ''
    ];
    
    if ($diagnostics['users_table'] && $diagnostics['hospitals_table']) {
        $db->beginTransaction();
        
        try {
            // Test user insert
            $stmt = $db->prepare("
                INSERT INTO users (username, password, role, full_name, email, phone, address) 
                VALUES ('test_diagnostic', ?, 'hospital', 'Test', 'test@test.com', '1234567890', 'Test Address')
            ");
            $stmt->execute([password_hash('test', PASSWORD_DEFAULT)]);
            $userId = $db->lastInsertId();
            $mockRegistration['can_insert_user'] = true;
            
            // Test hospital insert
            $stmt = $db->prepare("
                INSERT INTO hospitals (
                    user_id, hospital_name, license_number, address, city, 
                    contact_person, contact_phone, contact_email
                ) VALUES (?, 'Test Hospital', 'DIAG123', 'Test Address', 'Test City', 
                         'Test Person', '1234567890', 'test@test.com')
            ");
            $stmt->execute([$userId]);
            $hospitalId = $db->lastInsertId();
            $mockRegistration['can_insert_hospital'] = true;
            
            // Test inventory insert
            if ($diagnostics['blood_inventory_table']) {
                $stmt = $db->prepare("
                    INSERT INTO blood_inventory (hospital_id, blood_type, units_available) 
                    VALUES (?, 'A+', 0)
                ");
                $stmt->execute([$hospitalId]);
                $mockRegistration['can_insert_inventory'] = true;
            }
            
            $db->rollBack(); // Clean up test data
            
        } catch (Exception $e) {
            $db->rollBack();
            $mockRegistration['error_message'] = $e->getMessage();
        }
    }
    
    // Final assessment
    $allGood = $diagnostics['database_connection'] && 
               $diagnostics['users_table'] && 
               $diagnostics['hospitals_table'] && 
               $diagnostics['blood_inventory_table'] && 
               empty($diagnostics['common_issues']);
    
    echo json_encode([
        'success' => $allGood,
        'message' => $allGood ? 'Hospital registration system is healthy' : 'Issues found with hospital registration system',
        'diagnostics' => $diagnostics,
        'mock_registration_test' => $mockRegistration,
        'recommended_action' => $allGood ? 'System ready for hospital registration' : 'Run setup_hospital_db.php to fix issues'
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Diagnostic failed: ' . $e->getMessage(),
        'error_trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>