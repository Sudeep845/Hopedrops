<?php
/**
 * Setup Hospital Database Tables
 * Creates or fixes hospital-related database tables
 */

require_once 'db_connect.php';

header('Content-Type: text/plain');

try {
    $db = getDBConnection();
    
    echo "=== HOSPITAL DATABASE SETUP ===\n\n";
    
    // Create hospitals table
    $hospitalTableSQL = "
        CREATE TABLE IF NOT EXISTS hospitals (
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
    echo "✅ hospitals table created/verified\n";
    
    // Add new columns to existing hospitals table if they don't exist
    $alterStatements = [
        "ALTER TABLE hospitals ADD COLUMN IF NOT EXISTS registration_number VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE hospitals ADD COLUMN IF NOT EXISTS established_date DATE DEFAULT NULL", 
        "ALTER TABLE hospitals ADD COLUMN IF NOT EXISTS bed_capacity INT DEFAULT 0",
        "ALTER TABLE hospitals ADD COLUMN IF NOT EXISTS hospital_type VARCHAR(50) DEFAULT NULL",
        "ALTER TABLE hospitals ADD COLUMN IF NOT EXISTS services TEXT DEFAULT NULL"
    ];
    
    foreach ($alterStatements as $sql) {
        try {
            $db->exec($sql);
        } catch (Exception $e) {
            // Column might already exist, that's ok
            if (!strpos($e->getMessage(), 'Duplicate column name')) {
                echo "⚠️  Column update: " . $e->getMessage() . "\n";
            }
        }
    }
    echo "✅ hospitals table columns updated\n";
    
    // Create blood_inventory table
    $inventoryTableSQL = "
        CREATE TABLE IF NOT EXISTS blood_inventory (
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
    echo "✅ blood_inventory table created/verified\n";
    
    // Add indexes for better performance
    try {
        $db->exec("CREATE INDEX IF NOT EXISTS idx_hospitals_user_id ON hospitals(user_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_hospitals_city ON hospitals(city)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_hospitals_approved ON hospitals(is_approved)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_blood_inventory_hospital_id ON blood_inventory(hospital_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_blood_inventory_blood_type ON blood_inventory(blood_type)");
        echo "✅ Database indexes created/verified\n";
    } catch (Exception $e) {
        echo "⚠️  Index creation (non-critical): " . $e->getMessage() . "\n";
    }
    
    // Check existing data
    $hospitalCount = $db->query("SELECT COUNT(*) FROM hospitals")->fetchColumn();
    $inventoryCount = $db->query("SELECT COUNT(*) FROM blood_inventory")->fetchColumn();
    
    echo "\n=== CURRENT STATUS ===\n";
    echo "Registered hospitals: $hospitalCount\n";
    echo "Blood inventory records: $inventoryCount\n";
    
    // Test registration readiness
    echo "\n=== REGISTRATION TEST ===\n";
    
    // Check if we can insert a test record
    $testUsername = 'test_hospital_' . time();
    
    $db->beginTransaction();
    
    try {
        // Test user insert
        $userStmt = $db->prepare("
            INSERT INTO users (username, password, role, full_name, email, phone, address) 
            VALUES (?, ?, 'hospital', 'Test Hospital Admin', 'test@hospital.com', '9999999999', 'Test Address')
        ");
        $userStmt->execute([$testUsername, password_hash('test123', PASSWORD_DEFAULT)]);
        $userId = $db->lastInsertId();
        echo "✅ Test user insert successful (ID: $userId)\n";
        
        // Test hospital insert
        $hospitalStmt = $db->prepare("
            INSERT INTO hospitals (
                user_id, hospital_name, license_number, address, city, 
                contact_person, contact_phone, contact_email, registration_number,
                established_date, bed_capacity, hospital_type, services
            ) VALUES (?, 'Test Hospital', 'TEST123', 'Test Address', 'Test City', 
                     'Test Person', '9999999999', 'test@hospital.com', 'REG123',
                     '2020-01-01', 100, 'Government', 'Emergency Care, Surgery')
        ");
        $hospitalStmt->execute([$userId]);
        $hospitalId = $db->lastInsertId();
        echo "✅ Test hospital insert successful (ID: $hospitalId)\n";
        
        // Test blood inventory insert
        $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        $inventoryStmt = $db->prepare("
            INSERT INTO blood_inventory (hospital_id, blood_type, units_available, units_required) 
            VALUES (?, ?, 0, 0)
        ");
        
        foreach ($bloodTypes as $bloodType) {
            $inventoryStmt->execute([$hospitalId, $bloodType]);
        }
        echo "✅ Test blood inventory insert successful (8 blood types)\n";
        
        // Rollback test data
        $db->rollBack();
        echo "✅ Test data rolled back (cleanup successful)\n";
        
    } catch (Exception $e) {
        $db->rollBack();
        echo "❌ Test failed: " . $e->getMessage() . "\n";
        throw $e;
    }
    
    echo "\n=== SETUP COMPLETE ===\n";
    echo "Hospital registration system is ready!\n";
    echo "You can now register hospitals through the registration form.\n";
    
} catch (Exception $e) {
    echo "❌ Setup Error: " . $e->getMessage() . "\n";
    echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
}
?>