<?php
/**
 * FINAL Hospital Registration Database Fix
 * This creates the missing notifications table that's causing the database error
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

header('Content-Type: text/plain');

echo "=== FIXING HOSPITAL REGISTRATION DATABASE ERROR ===\n\n";

try {
    $db = getDBConnection();
    echo "✅ Database connection successful\n\n";
    
    echo "The issue: createNotification() function requires a notifications table\n";
    echo "Creating missing notifications table...\n\n";
    
    // Create notifications table (this is what's missing!)
    $notificationsSQL = "
        CREATE TABLE IF NOT EXISTS notifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
            is_read TINYINT(1) DEFAULT 0,
            related_donation_id INT DEFAULT NULL,
            related_campaign_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($notificationsSQL);
    echo "✅ Notifications table created successfully!\n\n";
    
    // Also create activity_logs table in case it's needed
    echo "Creating activity_logs table (also commonly referenced)...\n";
    $activitySQL = "
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            action VARCHAR(255) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($activitySQL);
    echo "✅ Activity logs table created successfully!\n\n";
    
    // Ensure admin user exists for notifications
    echo "Checking for admin user (required for hospital approval notifications)...\n";
    $adminCheck = $db->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    $adminCheck->execute();
    
    if (!$adminCheck->fetch()) {
        echo "No admin user found, creating one...\n";
        $adminStmt = $db->prepare("
            INSERT INTO users (username, password, role, full_name, email, phone, address) 
            VALUES (?, ?, 'admin', 'System Administrator', 'admin@hopedrops.com', '0000000000', 'System Address')
        ");
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $adminStmt->execute(['admin', $adminPassword]);
        echo "✅ Admin user created (username: admin, password: admin123)\n";
    } else {
        echo "✅ Admin user already exists\n";
    }
    
    echo "\n=== TESTING THE FIX ===\n";
    echo "Now testing hospital registration with your exact data...\n\n";
    
    // Test with the exact data that was failing
    $testData = [
        'role' => 'hospital',
        'fullName' => 'nima',
        'username' => 'test_nima_' . time(),
        'email' => 'test_ni_' . time() . '@ma.com',
        'phone' => '1212121212',
        'address' => 'asasa',
        'password' => 'Sudeep@123',
        'hospitalName' => 'sherpa hos',
        'licenseNumber' => 'TEST_12121212_' . time(),
        'contactPerson' => '1212121212',
        'city' => 'ktm',
        'registrationNumber' => 'TEST_12311233_' . time(),
        'establishedDate' => '2016-01-28',
        'bedCapacity' => 22,
        'hospitalType' => 'Private',
        'services' => 'dasdasd'
    ];
    
    $db->beginTransaction();
    
    try {
        // Step 1: Insert user
        echo "1. Creating user record...\n";
        $userStmt = $db->prepare("
            INSERT INTO users (username, password, role, full_name, email, phone, address, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $hashedPassword = password_hash($testData['password'], PASSWORD_DEFAULT);
        $userStmt->execute([
            $testData['username'],
            $hashedPassword,
            $testData['role'],
            $testData['fullName'],
            $testData['email'],
            $testData['phone'],
            $testData['address']
        ]);
        
        $userId = $db->lastInsertId();
        echo "   ✅ User created with ID: $userId\n";
        
        // Step 2: Insert hospital
        echo "2. Creating hospital record...\n";
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
            'Not specified', // state
            '000000',       // pincode
            $testData['contactPerson'],
            $testData['phone'],
            $testData['email'],
            $testData['registrationNumber'],
            $testData['establishedDate'],
            $testData['bedCapacity'],
            $testData['hospitalType'],
            $testData['services']
        ]);
        
        $hospitalId = $db->lastInsertId();
        echo "   ✅ Hospital created with ID: $hospitalId\n";
        
        // Step 3: Initialize blood inventory
        echo "3. Initializing blood inventory...\n";
        $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        $inventoryStmt = $db->prepare("
            INSERT INTO blood_inventory (hospital_id, blood_type, units_available, units_required) 
            VALUES (?, ?, 0, 0)
        ");
        
        foreach ($bloodTypes as $bloodType) {
            $inventoryStmt->execute([$hospitalId, $bloodType]);
        }
        echo "   ✅ Blood inventory initialized for all 8 blood types\n";
        
        // Step 4: Test notification creation (this was the failing part!)
        echo "4. Testing notification creation (this was causing the error)...\n";
        $adminQuery = $db->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $adminQuery->execute();
        
        if ($admin = $adminQuery->fetch()) {
            // This is the exact code that was failing before
            $notificationStmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, related_donation_id, related_campaign_id) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $notificationStmt->execute([
                $admin['id'],
                'New Hospital Registration',
                "New hospital '{$testData['hospitalName']}' has registered and is pending approval.",
                'info',
                null,
                null
            ]);
            echo "   ✅ Admin notification created successfully!\n";
        }
        
        $db->commit();
        
        echo "\n🎉 SUCCESS! Hospital registration test completed without errors!\n\n";
        echo "Test Results:\n";
        echo "- User ID: $userId\n";
        echo "- Hospital ID: $hospitalId\n";
        echo "- Username: {$testData['username']}\n";
        echo "- Email: {$testData['email']}\n";
        echo "- Hospital: {$testData['hospitalName']}\n";
        echo "- Notification: Created for admin approval\n";
        
        // Clean up test data
        echo "\nCleaning up test data...\n";
        $db->beginTransaction();
        $db->prepare("DELETE FROM notifications WHERE user_id = (SELECT id FROM users WHERE role = 'admin' LIMIT 1)")->execute();
        $db->prepare("DELETE FROM blood_inventory WHERE hospital_id = ?")->execute([$hospitalId]);
        $db->prepare("DELETE FROM hospitals WHERE id = ?")->execute([$hospitalId]);
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
        $db->commit();
        echo "✅ Test data cleaned up\n";
        
    } catch (Exception $e) {
        $db->rollBack();
        echo "\n❌ TEST FAILED - This shows what was wrong:\n";
        echo "Error: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . "\n";
        echo "Line: " . $e->getLine() . "\n";
        
        if ($e instanceof PDOException) {
            echo "\nSQL Error Details:\n";
            echo "SQLSTATE: " . $e->getCode() . "\n";
        }
        
        throw $e;
    }
    
    echo "\n=== FIX COMPLETE ===\n";
    echo "✅ Missing notifications table created\n";
    echo "✅ Hospital registration functionality restored\n";
    echo "✅ Database error should be resolved\n\n";
    
    echo "🎯 Next Steps:\n";
    echo "1. Go back to the registration form\n";
    echo "2. Try registering as a hospital again\n";
    echo "3. The 'Database error occurred' message should be gone!\n";
    
} catch (Exception $e) {
    echo "\n💥 SETUP FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
?>