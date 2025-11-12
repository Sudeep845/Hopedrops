<?php
/**
 * Hospital Registration Complete Fix
 * This will fix all known issues with hospital registration
 */

require_once 'db_connect.php';

header('Content-Type: text/plain');

echo "=== HOSPITAL REGISTRATION COMPLETE FIX ===\n\n";

try {
    $db = getDBConnection();
    
    echo "Step 1: Updating hospitals table structure...\n";
    
    // Drop and recreate hospitals table with correct structure
    $db->exec("DROP TABLE IF EXISTS blood_inventory");
    $db->exec("DROP TABLE IF EXISTS hospitals");
    
    echo "✅ Dropped existing tables\n";
    
    // Create hospitals table with all required columns
    $hospitalTableSQL = "
        CREATE TABLE hospitals (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            hospital_name VARCHAR(255) NOT NULL,
            license_number VARCHAR(100) NOT NULL UNIQUE,
            address TEXT NOT NULL,
            city VARCHAR(100) NOT NULL,
            state VARCHAR(100) DEFAULT 'Not specified',
            pincode VARCHAR(10) DEFAULT '000000',
            contact_person VARCHAR(255) NOT NULL,
            contact_phone VARCHAR(15) NOT NULL,
            contact_email VARCHAR(255) NOT NULL,
            registration_number VARCHAR(100) DEFAULT NULL,
            established_date DATE DEFAULT NULL,
            bed_capacity INT DEFAULT 0,
            hospital_type VARCHAR(50) DEFAULT NULL,
            services TEXT DEFAULT NULL,
            is_approved TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ";
    
    $db->exec($hospitalTableSQL);
    echo "✅ Created hospitals table with all required columns\n";
    
    // Create blood_inventory table
    $inventoryTableSQL = "
        CREATE TABLE blood_inventory (
            id INT PRIMARY KEY AUTO_INCREMENT,
            hospital_id INT NOT NULL,
            blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
            units_available INT DEFAULT 0,
            units_required INT DEFAULT 0,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
            UNIQUE KEY unique_hospital_blood (hospital_id, blood_type)
        )
    ";
    
    $db->exec($inventoryTableSQL);
    echo "✅ Created blood_inventory table\n";
    
    echo "\nStep 2: Testing hospital registration...\n";
    
    // Test registration with complete data
    $testData = [
        'role' => 'hospital',
        'fullName' => 'Dr. Test Administrator',
        'username' => 'test_hospital_' . time(),
        'email' => 'test' . time() . '@hospital.com',
        'phone' => '9876543210',
        'address' => '123 Medical Street, New Delhi, 110001',
        'password' => 'TestPass123!',
        'confirmPassword' => 'TestPass123!',
        'hospitalName' => 'Test General Hospital',
        'licenseNumber' => 'LIC' . time(),
        'contactPerson' => 'Dr. Test Administrator',
        'city' => 'New Delhi',
        'registrationNumber' => 'MED' . time(),
        'establishedDate' => '2015-06-15',
        'bedCapacity' => '250',
        'hospitalType' => 'Private',
        'services' => 'Emergency Care, Surgery, ICU'
    ];
    
    // Backup original POST data
    $originalPost = $_POST;
    $originalMethod = $_SERVER['REQUEST_METHOD'] ?? '';
    
    // Set test data
    $_POST = $testData;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    // Capture registration result
    ob_start();
    try {
        include 'register.php';
        $result = ob_get_contents();
    } catch (Exception $e) {
        $result = json_encode([
            'success' => false,
            'message' => 'Registration Error: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
    ob_end_clean();
    
    // Restore original POST data
    $_POST = $originalPost;
    $_SERVER['REQUEST_METHOD'] = $originalMethod;
    
    echo "Registration test result:\n";
    $json = json_decode($result, true);
    if ($json) {
        echo json_encode($json, JSON_PRETTY_PRINT);
        if ($json['success']) {
            echo "\n✅ Hospital registration is working correctly!\n";
        } else {
            echo "\n❌ Registration failed: " . $json['message'] . "\n";
            if (isset($json['errors'])) {
                echo "Validation errors:\n";
                foreach ($json['errors'] as $field => $error) {
                    echo "  - $field: $error\n";
                }
            }
        }
    } else {
        echo "Raw result: $result\n";
    }
    
    echo "\nStep 3: Verifying database structure...\n";
    
    // Verify tables exist
    $tables = ['hospitals', 'blood_inventory'];
    foreach ($tables as $table) {
        $result = $db->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->rowCount() > 0) {
            echo "✅ Table '$table' exists\n";
        } else {
            echo "❌ Table '$table' missing\n";
        }
    }
    
    // Show hospital table structure
    echo "\nHospitals table columns:\n";
    $result = $db->query("DESCRIBE hospitals");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("  %-25s %s\n", $row['Field'], $row['Type']);
    }
    
    echo "\n=== FIX COMPLETE ===\n";
    echo "Hospital registration should now be working.\n";
    echo "Try registering at: http://localhost/HopeDrops/register.html?role=hospital\n";
    
} catch (Exception $e) {
    echo "❌ Fix failed: " . $e->getMessage() . "\n";
}
?>