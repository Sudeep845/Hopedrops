<?php
// setup_database.php - One-click database setup
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Connect to MySQL without specifying database
    $pdo = new PDO("mysql:host=localhost", "root", "");
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS bloodbank_db");
    
    // Now connect to bloodbank_db
    $pdo = new PDO("mysql:host=localhost;dbname=bloodbank_db", "root", "");
    
    // Check if appointments table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'appointments'");
    $appointmentsExists = $stmt->rowCount() > 0;
    
    if (!$appointmentsExists) {
        // Create appointments table
        $createAppointments = "
        CREATE TABLE IF NOT EXISTS appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            donor_id INT NOT NULL,
            hospital_id INT NOT NULL,
            appointment_date DATE NOT NULL,
            appointment_time TIME NOT NULL,
            blood_type VARCHAR(5) NOT NULL,
            status ENUM('scheduled', 'confirmed', 'completed', 'cancelled') DEFAULT 'scheduled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
            INDEX idx_donor_id (donor_id),
            INDEX idx_hospital_id (hospital_id),
            INDEX idx_appointment_date (appointment_date),
            INDEX idx_status (status)
        )";
        
        $pdo->exec($createAppointments);
    }
    
    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $usersExists = $stmt->rowCount() > 0;
    
    if (!$usersExists) {
        // Create basic users table
        $createUsers = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            blood_type VARCHAR(5),
            phone VARCHAR(15),
            role ENUM('donor', 'hospital', 'admin') DEFAULT 'donor',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE
        )";
        
        $pdo->exec($createUsers);
        
        // Insert a test donor
        $insertDonor = "
        INSERT INTO users (username, email, password, full_name, blood_type, phone, role) 
        VALUES ('testdonor', 'test@donor.com', '$2y$10\$example', 'Test Donor', 'O+', '1234567890', 'donor')
        ON DUPLICATE KEY UPDATE username = username";
        
        $pdo->exec($insertDonor);
    }
    
    // Check if hospitals table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'hospitals'");
    $hospitalsExists = $stmt->rowCount() > 0;
    
    if (!$hospitalsExists) {
        // Create basic hospitals table
        $createHospitals = "
        CREATE TABLE IF NOT EXISTS hospitals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            hospital_name VARCHAR(255) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            contact_person VARCHAR(100),
            phone VARCHAR(15),
            address TEXT,
            city VARCHAR(100),
            state VARCHAR(100),
            pincode VARCHAR(10),
            license_number VARCHAR(100),
            is_approved BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $pdo->exec($createHospitals);
        
        // Insert test hospitals
        $insertHospitals = "
        INSERT INTO hospitals (hospital_name, email, password, contact_person, phone, address, city, state, pincode, license_number, is_approved) 
        VALUES 
        ('City General Hospital', 'admin@citygeneral.com', '$2y$10\$example', 'Dr. Smith', '9876543210', '123 Main St', 'Mumbai', 'Maharashtra', '400001', 'LIC001', TRUE),
        ('Metro Blood Center', 'contact@metroblood.com', '$2y$10\$example', 'Dr. Johnson', '9876543211', '456 Oak Ave', 'Delhi', 'Delhi', '110001', 'LIC002', TRUE),
        ('Regional Medical Center', 'info@regionalmed.com', '$2y$10\$example', 'Dr. Brown', '9876543212', '789 Pine Rd', 'Bangalore', 'Karnataka', '560001', 'LIC003', TRUE)
        ON DUPLICATE KEY UPDATE hospital_name = hospital_name";
        
        $pdo->exec($insertHospitals);
    }
    
    // Insert sample appointments if none exist
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM appointments");
    $appointmentCount = $stmt->fetch()['count'];
    
    if ($appointmentCount == 0) {
        // Get actual hospital and donor IDs
        $hospitalIds = $pdo->query("SELECT id FROM hospitals ORDER BY id LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
        $donorIds = $pdo->query("SELECT id FROM users WHERE role = 'donor' ORDER BY id LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($hospitalIds) && !empty($donorIds)) {
            $insertAppointments = "
            INSERT INTO appointments (donor_id, hospital_id, appointment_date, appointment_time, blood_type, status) 
            VALUES 
            (?, ?, '2024-12-28', '10:00:00', 'O+', 'scheduled'),
            (?, ?, '2024-12-30', '11:30:00', 'A+', 'confirmed')";
            
            $stmt = $pdo->prepare($insertAppointments);
            $stmt->execute([
                $donorIds[0], $hospitalIds[0],
                isset($donorIds[1]) ? $donorIds[1] : $donorIds[0], 
                isset($hospitalIds[1]) ? $hospitalIds[1] : $hospitalIds[0]
            ]);
        }
    }
    
    // Final verification
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM appointments");
    $finalAppointmentCount = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'donor'");
    $donorCount = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM hospitals WHERE is_approved = 1");
    $hospitalCount = $stmt->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'message' => 'Database setup completed successfully!',
        'tables_created' => [
            'appointments' => !$appointmentsExists ? 'created' : 'already_exists',
            'users' => !$usersExists ? 'created' : 'already_exists',
            'hospitals' => !$hospitalsExists ? 'created' : 'already_exists'
        ],
        'data_summary' => [
            'appointments' => $finalAppointmentCount,
            'donors' => $donorCount,
            'hospitals' => $hospitalCount
        ],
        'ready_for_use' => true
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'recommendations' => [
            'Check if XAMPP MySQL service is running',
            'Verify MySQL port 3306 is available',
            'Ensure no other MySQL instances are running'
        ]
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Setup error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>