<?php
/**
 * Complete Database Setup for Hospital Registration
 * This will create all required tables and functions
 */

require_once 'db_connect.php';

header('Content-Type: text/plain');

echo "=== COMPLETE HOSPITAL REGISTRATION SETUP ===\n\n";

try {
    $db = getDBConnection();
    echo "✅ Database connection successful\n";
    
    // Create users table if it doesn't exist
    echo "\nStep 1: Setting up users table...\n";
    
    $usersTableSQL = "
        CREATE TABLE IF NOT EXISTS users (
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
    
    $db->exec($usersTableSQL);
    echo "✅ Users table created/verified\n";
    
    // Create hospitals table
    echo "\nStep 2: Setting up hospitals table...\n";
    
    $db->exec("DROP TABLE IF EXISTS blood_inventory");
    $db->exec("DROP TABLE IF EXISTS hospitals");
    
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
    echo "✅ Hospitals table created\n";
    
    // Create blood_inventory table
    echo "\nStep 3: Setting up blood inventory table...\n";
    
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
    echo "✅ Blood inventory table created\n";
    
    // Create notifications table if needed
    echo "\nStep 4: Setting up notifications table...\n";
    
    $notificationsTableSQL = "
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
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ";
    
    $db->exec($notificationsTableSQL);
    echo "✅ Notifications table created/verified\n";
    
    // Create activity_logs table if needed
    echo "\nStep 5: Setting up activity logs table...\n";
    
    $activityLogsTableSQL = "
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ";
    
    $db->exec($activityLogsTableSQL);
    echo "✅ Activity logs table created/verified\n";
    
    // Create rewards table if needed for donors
    echo "\nStep 6: Setting up rewards table...\n";
    
    $rewardsTableSQL = "
        CREATE TABLE IF NOT EXISTS rewards (
            id INT PRIMARY KEY AUTO_INCREMENT,
            donor_id INT NOT NULL,
            points_earned INT DEFAULT 0,
            points_spent INT DEFAULT 0,
            total_points INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ";
    
    $db->exec($rewardsTableSQL);
    echo "✅ Rewards table created/verified\n";
    
    echo "\nStep 7: Testing hospital registration...\n";
    
    // Test registration with sample data
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
    
    // Backup original data
    $originalPost = $_POST;
    $originalMethod = $_SERVER['REQUEST_METHOD'] ?? '';
    
    // Set test data
    $_POST = $testData;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    echo "Testing registration for user: " . $testData['username'] . "\n";
    
    // Capture registration output
    ob_start();
    try {
        include 'register.php';
        $output = ob_get_contents();
    } catch (Exception $e) {
        $output = json_encode([
            'success' => false,
            'message' => 'Registration Error: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
    ob_end_clean();
    
    // Restore original data
    $_POST = $originalPost;
    $_SERVER['REQUEST_METHOD'] = $originalMethod;
    
    echo "Registration Result:\n";
    echo str_repeat("-", 40) . "\n";
    echo $output . "\n";
    echo str_repeat("-", 40) . "\n";
    
    $json = json_decode($output, true);
    if ($json && $json['success']) {
        echo "✅ Hospital registration is working correctly!\n";
        echo "User ID: " . $json['data']['user_id'] . "\n";
        echo "Username: " . $json['data']['username'] . "\n";
    } else {
        echo "❌ Registration test failed\n";
        if ($json && isset($json['message'])) {
            echo "Error: " . $json['message'] . "\n";
        }
    }
    
    echo "\n=== SETUP COMPLETE ===\n";
    echo "Hospital registration should now work at:\n";
    echo "http://localhost/HopeDrops/register.html?role=hospital\n";
    
} catch (Exception $e) {
    echo "❌ Setup failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>