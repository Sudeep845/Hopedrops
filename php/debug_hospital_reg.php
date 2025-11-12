<?php
/**
 * Hospital Registration Debug Test
 * This will attempt a hospital registration and show detailed error information
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

header('Content-Type: text/plain');

echo "=== HOSPITAL REGISTRATION DEBUG TEST ===\n\n";

try {
    echo "Step 1: Testing database connection...\n";
    $db = getDBConnection();
    echo "✅ Database connection successful\n\n";
    
    // Test data based on the registration that's failing
    $testData = [
        'role' => 'hospital',
        'fullName' => 'Dr. Test Administrator',
        'username' => 'test_hospital_' . time(),
        'email' => 'test' . time() . '@hospital.com',
        'phone' => '9876543210',
        'address' => '123 Medical Street, New Delhi, 110001',
        'password' => 'TestPass123!',
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
    
    echo "Step 2: Checking required tables...\n";
    
    // Check if tables exist
    $tables = ['users', 'hospitals', 'blood_inventory'];
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Table '$table' exists\n";
        } else {
            echo "❌ Table '$table' missing - creating it...\n";
            
            if ($table === 'users') {
                $sql = "CREATE TABLE users (
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
                )";
                $db->exec($sql);
                echo "✅ Users table created\n";
            }
            
            if ($table === 'hospitals') {
                $sql = "CREATE TABLE hospitals (
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
                )";
                $db->exec($sql);
                echo "✅ Hospitals table created\n";
            }
            
            if ($table === 'blood_inventory') {
                $sql = "CREATE TABLE blood_inventory (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    hospital_id INT NOT NULL,
                    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
                    units_available INT DEFAULT 0,
                    units_required INT DEFAULT 0,
                    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_hospital_blood (hospital_id, blood_type)
                )";
                $db->exec($sql);
                echo "✅ Blood inventory table created\n";
            }
        }
    }
    
    echo "\nStep 3: Testing hospital registration process...\n";
    
    $db->beginTransaction();
    
    try {
        // Step 3a: Insert user
        echo "Inserting user record...\n";
        $userStmt = $db->prepare("
            INSERT INTO users (username, password, role, full_name, email, phone, address, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $hashedPassword = password_hash($testData['password'], PASSWORD_DEFAULT);
        
        $userResult = $userStmt->execute([
            $testData['username'],
            $hashedPassword,
            $testData['role'],
            $testData['fullName'],
            $testData['email'],
            $testData['phone'],
            $testData['address']
        ]);
        
        if (!$userResult) {
            throw new Exception("Failed to insert user: " . implode(", ", $userStmt->errorInfo()));
        }
        
        $userId = $db->lastInsertId();
        echo "✅ User inserted successfully with ID: $userId\n";
        
        // Step 3b: Insert hospital
        echo "Inserting hospital record...\n";
        $hospitalStmt = $db->prepare("
            INSERT INTO hospitals (
                user_id, hospital_name, license_number, address, city, state, 
                pincode, contact_person, contact_phone, contact_email, 
                registration_number, established_date, bed_capacity, 
                hospital_type, services, is_approved
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ");
        
        $hospitalResult = $hospitalStmt->execute([
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
            $testData['services']
        ]);
        
        if (!$hospitalResult) {
            throw new Exception("Failed to insert hospital: " . implode(", ", $hospitalStmt->errorInfo()));
        }
        
        $hospitalId = $db->lastInsertId();
        echo "✅ Hospital inserted successfully with ID: $hospitalId\n";
        
        // Step 3c: Initialize blood inventory
        echo "Initializing blood inventory...\n";
        $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        $inventoryStmt = $db->prepare("
            INSERT INTO blood_inventory (hospital_id, blood_type, units_available, units_required) 
            VALUES (?, ?, 0, 0)
        ");
        
        foreach ($bloodTypes as $bloodType) {
            $inventoryResult = $inventoryStmt->execute([$hospitalId, $bloodType]);
            if (!$inventoryResult) {
                throw new Exception("Failed to insert inventory for $bloodType: " . implode(", ", $inventoryStmt->errorInfo()));
            }
            echo "  ✅ Blood type $bloodType inventory created\n";
        }
        
        // Commit transaction
        $db->commit();
        
        echo "\n🎉 SUCCESS! Hospital registration completed successfully!\n";
        echo "User ID: $userId\n";
        echo "Hospital ID: $hospitalId\n";
        echo "Username: {$testData['username']}\n";
        echo "Email: {$testData['email']}\n";
        
        // Clean up test data
        echo "\nCleaning up test data...\n";
        $db->beginTransaction();
        $db->prepare("DELETE FROM blood_inventory WHERE hospital_id = ?")->execute([$hospitalId]);
        $db->prepare("DELETE FROM hospitals WHERE id = ?")->execute([$hospitalId]);
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
        $db->commit();
        echo "✅ Test data cleaned up\n";
        
    } catch (Exception $e) {
        $db->rollBack();
        echo "\n❌ REGISTRATION FAILED!\n";
        echo "Error: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . "\n";
        echo "Line: " . $e->getLine() . "\n";
        
        if ($e instanceof PDOException) {
            echo "\nSQL Error Details:\n";
            echo "SQLSTATE: " . $e->getCode() . "\n";
            echo "Error Info: " . print_r($e->errorInfo ?? [], true) . "\n";
        }
        
        echo "\nStack trace:\n";
        echo $e->getTraceAsString() . "\n";
    }
    
} catch (Exception $e) {
    echo "\n💥 DIAGNOSTIC FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    
    if ($e instanceof PDOException) {
        echo "\nConnection Error Details:\n";
        echo "SQLSTATE: " . $e->getCode() . "\n";
    }
}

echo "\n=== DEBUG TEST COMPLETE ===\n";
?>