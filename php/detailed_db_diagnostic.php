<?php
/**
 * Detailed Database Error Diagnostic
 * This will show exactly what database error is occurring
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

header('Content-Type: text/plain');

echo "=== DETAILED DATABASE ERROR DIAGNOSTIC ===\n\n";

try {
    // Step 1: Test basic database connection
    echo "Step 1: Testing database connection...\n";
    $db = getDBConnection();
    echo "✅ Database connection successful\n";
    
    // Step 2: Check current database
    $stmt = $db->query("SELECT DATABASE() as db_name");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Current database: " . ($row['db_name'] ?? 'None') . "\n\n";
    
    // Step 3: Check if required tables exist
    echo "Step 3: Checking required tables...\n";
    $requiredTables = ['users', 'hospitals', 'blood_inventory'];
    $missingTables = [];
    
    foreach ($requiredTables as $table) {
        try {
            $stmt = $db->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "✅ Table '$table' exists\n";
                
                // Check table structure
                $desc = $db->query("DESCRIBE $table");
                $columns = $desc->fetchAll(PDO::FETCH_COLUMN);
                echo "   Columns: " . implode(', ', $columns) . "\n";
            } else {
                echo "❌ Table '$table' missing\n";
                $missingTables[] = $table;
            }
        } catch (Exception $e) {
            echo "❌ Error checking table '$table': " . $e->getMessage() . "\n";
            $missingTables[] = $table;
        }
    }
    
    if (!empty($missingTables)) {
        echo "\n🔧 Creating missing tables...\n";
        
        // Create users table
        if (in_array('users', $missingTables)) {
            echo "Creating users table...\n";
            $usersSQL = "
                CREATE TABLE users (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    username VARCHAR(50) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    role ENUM('donor', 'hospital', 'admin') NOT NULL DEFAULT 'donor',
                    full_name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL UNIQUE,
                    phone VARCHAR(15) NOT NULL,
                    address TEXT NOT NULL,
                    date_of_birth DATE DEFAULT NULL,
                    gender ENUM('Male', 'Female', 'Other') DEFAULT NULL,
                    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') DEFAULT NULL,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ";
            $db->exec($usersSQL);
            echo "✅ Users table created\n";
        }
        
        // Create hospitals table
        if (in_array('hospitals', $missingTables)) {
            echo "Creating hospitals table...\n";
            $hospitalsSQL = "
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
            $db->exec($hospitalsSQL);
            echo "✅ Hospitals table created\n";
        }
        
        // Create blood_inventory table
        if (in_array('blood_inventory', $missingTables)) {
            echo "Creating blood_inventory table...\n";
            $inventorySQL = "
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
            $db->exec($inventorySQL);
            echo "✅ Blood inventory table created\n";
        }
    }
    
    // Step 4: Test actual registration simulation
    echo "\nStep 4: Testing hospital registration simulation...\n";
    
    $timestamp = time();
    $testData = [
        'role' => 'hospital',
        'fullName' => 'Dr. Test Administrator',
        'username' => "test_hospital_$timestamp",
        'email' => "test$timestamp@hospital.com",
        'phone' => '9876543210',
        'address' => '123 Medical Street, New Delhi, 110001',
        'password' => 'TestPass123!',
        'confirmPassword' => 'TestPass123!',
        'hospitalName' => 'Test General Hospital',
        'licenseNumber' => "LIC$timestamp",
        'contactPerson' => 'Dr. Test Administrator',
        'city' => 'New Delhi',
        'registrationNumber' => "MED$timestamp",
        'establishedDate' => '2015-06-15',
        'bedCapacity' => '250',
        'hospitalType' => 'Private',
        'services' => 'Emergency Care, Surgery, ICU'
    ];
    
    echo "Test data prepared:\n";
    foreach ($testData as $key => $value) {
        echo "  $key: $value\n";
    }
    
    // Simulate the exact registration process
    echo "\nSimulating registration process...\n";
    
    $db->beginTransaction();
    
    try {
        // Insert user
        echo "Inserting user...\n";
        $userStmt = $db->prepare("
            INSERT INTO users (username, password, role, full_name, email, phone, address, date_of_birth, gender, blood_type, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $hashedPassword = password_hash($testData['password'], PASSWORD_DEFAULT);
        
        $userStmt->execute([
            $testData['username'],
            $hashedPassword,
            $testData['role'],
            $testData['fullName'],
            $testData['email'],
            $testData['phone'],
            $testData['address'],
            null, // date_of_birth
            null, // gender
            null  // blood_type
        ]);
        
        $userId = $db->lastInsertId();
        echo "✅ User inserted with ID: $userId\n";
        
        // Insert hospital
        echo "Inserting hospital...\n";
        $hospitalStmt = $db->prepare("
            INSERT INTO hospitals (
                user_id, hospital_name, license_number, address, city, state, 
                pincode, contact_person, contact_phone, contact_email, 
                registration_number, established_date, bed_capacity, 
                hospital_type, services, is_approved
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ");
        
        $hospitalStmt->execute([
            $userId,
            $testData['hospitalName'],
            $testData['licenseNumber'],
            $testData['address'],
            $testData['city'],
            'Not specified',
            '000000',
            $testData['contactPerson'],
            $testData['phone'],
            $testData['email'],
            $testData['registrationNumber'],
            $testData['establishedDate'],
            $testData['bedCapacity'],
            $testData['hospitalType'],
            $testData['services'],
            0
        ]);
        
        $hospitalId = $db->lastInsertId();
        echo "✅ Hospital inserted with ID: $hospitalId\n";
        
        // Initialize blood inventory
        echo "Initializing blood inventory...\n";
        $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        $inventoryStmt = $db->prepare("
            INSERT INTO blood_inventory (hospital_id, blood_type, units_available, units_required) 
            VALUES (?, ?, 0, 0)
        ");
        
        foreach ($bloodTypes as $bloodType) {
            $inventoryStmt->execute([$hospitalId, $bloodType]);
        }
        echo "✅ Blood inventory initialized\n";
        
        // Commit transaction
        $db->commit();
        
        echo "\n🎉 SUCCESS! Hospital registration simulation completed!\n";
        echo "User ID: $userId\n";
        echo "Hospital ID: $hospitalId\n";
        echo "Username: {$testData['username']}\n";
        echo "Email: {$testData['email']}\n";
        
    } catch (Exception $e) {
        $db->rollBack();
        echo "\n❌ REGISTRATION SIMULATION FAILED!\n";
        echo "Error: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . "\n";
        echo "Line: " . $e->getLine() . "\n";
        echo "\nFull error details:\n";
        echo $e->getTraceAsString() . "\n";
        
        // Check if it's a specific SQL error
        if ($e instanceof PDOException) {
            echo "\nPDO Error Info:\n";
            echo "SQL State: " . $e->getCode() . "\n";
            echo "Error Info: " . print_r($db->errorInfo(), true) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "\n💥 DIAGNOSTIC FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    
    if ($e instanceof PDOException) {
        echo "\nPDO Connection Error:\n";
        echo "SQL State: " . $e->getCode() . "\n";
    }
}

echo "\n=== DIAGNOSTIC COMPLETE ===\n";
echo "If registration simulation succeeded, the backend should work.\n";
echo "If it failed, the error details above show what needs to be fixed.\n";
?>