<?php
/**
 * Hospital Registration Error Test
 * This will show the exact database error
 */

require_once 'db_connect.php';

header('Content-Type: text/plain');

try {
    $db = getDBConnection();
    echo "=== TESTING HOSPITAL TABLE STRUCTURE ===\n\n";
    
    // Test if table exists and show structure
    echo "Hospitals table structure:\n";
    $result = $db->query("DESCRIBE hospitals");
    if ($result) {
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo sprintf("%-25s %-20s %s\n", $row['Field'], $row['Type'], $row['Null']);
        }
    } else {
        echo "❌ hospitals table does not exist!\n";
    }
    
    echo "\n=== TESTING HOSPITAL INSERT ===\n\n";
    
    // Test the exact INSERT statement from register.php
    $stmt = $db->prepare("
        INSERT INTO hospitals (
            user_id, hospital_name, license_number, address, city, state, 
            pincode, contact_person, contact_phone, contact_email, 
            registration_number, established_date, bed_capacity, 
            hospital_type, services, is_approved
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
    ");
    
    if ($stmt) {
        echo "✅ INSERT statement prepared successfully\n";
        echo "This means all columns exist in the hospitals table\n";
    } else {
        $error = $db->errorInfo();
        echo "❌ INSERT statement failed to prepare:\n";
        echo "Error: " . $error[2] . "\n";
    }
    
    echo "\n=== TESTING BLOOD_INVENTORY TABLE ===\n\n";
    
    echo "Blood inventory table structure:\n";
    $result = $db->query("DESCRIBE blood_inventory");
    if ($result) {
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo sprintf("%-25s %-20s %s\n", $row['Field'], $row['Type'], $row['Null']);
        }
    } else {
        echo "❌ blood_inventory table does not exist!\n";
    }
    
    echo "\n=== RUNNING ACTUAL REGISTRATION TEST ===\n\n";
    
    // Now test a real registration
    $testData = [
        'role' => 'hospital',
        'fullName' => 'Dr. Test User',
        'username' => 'test_' . time(),
        'email' => 'test@example.com',
        'phone' => '1234567890',
        'address' => 'Test Address, Test City, 123456',
        'password' => 'TestPass123!',
        'confirmPassword' => 'TestPass123!',
        'hospitalName' => 'Test Hospital',
        'licenseNumber' => 'LIC' . time(),
        'contactPerson' => 'Dr. Test User',
        'city' => 'Test City',
        'registrationNumber' => 'REG' . time(),
        'establishedDate' => '2020-01-01',
        'bedCapacity' => 100,
        'hospitalType' => 'Private',
        'services' => 'Test Services'
    ];
    
    // Simulate the POST data
    $_POST = $testData;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    echo "Attempting registration with test data...\n";
    
    // Capture any errors from register.php
    ob_start();
    try {
        include 'register.php';
        $output = ob_get_contents();
    } catch (Exception $e) {
        $output = "Exception: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine();
    } catch (Error $e) {
        $output = "Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine();
    }
    ob_end_clean();
    
    echo "Registration result:\n";
    echo $output;
    
} catch (Exception $e) {
    echo "❌ Database connection error: " . $e->getMessage() . "\n";
}

echo "\n\n=== TEST COMPLETE ===\n";
?>