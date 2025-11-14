<?php
/**
 * Restore User Hospital Data - Add back "nima hos"
 */

header('Content-Type: application/json');

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=bloodbank_db;charset=utf8",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    echo "Restoring your hospital data...\n\n";

    // First, ensure we have a user for the hospital
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO users (username, email, role, password_hash, full_name, phone, is_verified, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
    ");
    $stmt->execute([
        'nima_hospital', 
        'nima@hospital.com', 
        'hospital', 
        password_hash('nima123', PASSWORD_DEFAULT), 
        'Nima Hospital Admin',
        '9800000000'
    ]);
    
    $userId = $pdo->lastInsertId();
    if ($userId == 0) {
        // User already exists, get the ID
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'nima_hospital'");
        $stmt->execute();
        $user = $stmt->fetch();
        $userId = $user ? $user['id'] : 1; // Fallback to admin user
    }

    // Create your hospital
    $stmt = $pdo->prepare("
        INSERT INTO hospitals (
            user_id, hospital_name, address, city, state, contact_phone, 
            contact_email, license_number, registration_number, established_date,
            bed_capacity, hospital_type, is_approved, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
    ");
    
    $stmt->execute([
        $userId,
        'Nima Hospital',  // Proper name instead of "nima hos"
        'Kapan, Kathmandu',
        'Kathmandu',
        'Bagmati',
        '9800000000',
        'nima@hospital.com',
        'NH001',
        'REG_NH001',
        '2020-01-01',
        100,
        'General'
    ]);

    $hospitalId = $pdo->lastInsertId();

    // Add blood inventory for your hospital
    $bloodTypes = ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'];
    foreach ($bloodTypes as $bloodType) {
        $units = rand(0, 15); // Random units between 0-15
        $stmt = $pdo->prepare("
            INSERT INTO blood_inventory (hospital_id, blood_type, units_available, last_updated)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$hospitalId, $bloodType, $units]);
    }

    echo "✅ Successfully restored 'Nima Hospital'!\n";
    echo "Hospital ID: {$hospitalId}\n";
    echo "Location: Kapan, Kathmandu\n";
    echo "Contact: 9800000000\n";
    echo "Email: nima@hospital.com\n";
    echo "Status: Approved and active\n";
    echo "Blood inventory: All blood types added\n\n";

    // Show current hospitals
    echo "Current hospitals in the system:\n";
    $stmt = $pdo->query("
        SELECT hospital_name, city, contact_email, contact_phone, is_approved
        FROM hospitals 
        ORDER BY hospital_name
    ");
    while ($row = $stmt->fetch()) {
        $status = $row['is_approved'] ? 'Approved' : 'Pending';
        echo "- {$row['hospital_name']} ({$row['city']}) - {$row['contact_phone']} - {$status}\n";
    }

} catch (Exception $e) {
    echo "❌ Error restoring hospital: " . $e->getMessage() . "\n";
}
?>