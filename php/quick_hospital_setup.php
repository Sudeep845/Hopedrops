<?php
/**
 * Quick Hospital Setup - Ensure we have working hospital data
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

    echo "Checking hospital data...\n\n";

    // Check if we have approved hospitals
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM hospitals WHERE is_approved = 1");
    $approvedCount = $stmt->fetch()['count'];
    echo "Approved hospitals: {$approvedCount}\n";

    if ($approvedCount == 0) {
        echo "No approved hospitals found. Creating sample data...\n";

        // First ensure we have a user for the hospitals
        $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, role, password_hash, full_name, is_verified, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
        $stmt->execute(['system', 'system@hopedrops.com', 'admin', password_hash('admin123', PASSWORD_DEFAULT), 'System Admin']);
        
        $userId = $pdo->lastInsertId();
        if ($userId == 0) {
            // User already exists, get the ID
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'system'");
            $stmt->execute();
            $userId = $stmt->fetch()['id'];
        }

        // Insert hospitals
        $hospitals = [
            [
                'user_id' => $userId,
                'hospital_name' => 'Central Medical Center',
                'address' => 'New Road, Kathmandu',
                'city' => 'Kathmandu',
                'state' => 'Bagmati',
                'contact_phone' => '01-4241234',
                'contact_email' => 'info@centralmedical.com.np',
                'license_number' => 'CMC001',
                'registration_number' => 'REG001',
                'established_date' => '1985-01-01',
                'bed_capacity' => 200,
                'hospital_type' => 'Government'
            ],
            [
                'user_id' => $userId,
                'hospital_name' => 'Patan Community Hospital',
                'address' => 'Lagankhel, Lalitpur',
                'city' => 'Lalitpur', 
                'state' => 'Bagmati',
                'contact_phone' => '01-5539595',
                'contact_email' => 'contact@patancommunity.org.np',
                'license_number' => 'PCH002',
                'registration_number' => 'REG002',
                'established_date' => '1992-06-15',
                'bed_capacity' => 150,
                'hospital_type' => 'Non-Profit'
            ]
        ];

        foreach ($hospitals as $hospital) {
            $stmt = $pdo->prepare("
                INSERT INTO hospitals (
                    user_id, hospital_name, address, city, state, contact_phone, 
                    contact_email, license_number, registration_number, established_date,
                    bed_capacity, hospital_type, is_approved, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            
            $stmt->execute([
                $hospital['user_id'],
                $hospital['hospital_name'],
                $hospital['address'],
                $hospital['city'],
                $hospital['state'],
                $hospital['contact_phone'],
                $hospital['contact_email'],
                $hospital['license_number'],
                $hospital['registration_number'],
                $hospital['established_date'],
                $hospital['bed_capacity'],
                $hospital['hospital_type']
            ]);

            $hospitalId = $pdo->lastInsertId();

            // Add blood inventory
            $bloodTypes = ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'];
            foreach ($bloodTypes as $bloodType) {
                $units = rand(5, 25); // Random units between 5-25
                $stmt = $pdo->prepare("
                    INSERT INTO blood_inventory (hospital_id, blood_type, units_available, last_updated)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$hospitalId, $bloodType, $units]);
            }

            echo "Created hospital: {$hospital['hospital_name']}\n";
        }
    }

    // Verify the setup
    $stmt = $pdo->query("
        SELECT h.hospital_name, h.city, COUNT(bi.id) as inventory_count
        FROM hospitals h
        LEFT JOIN blood_inventory bi ON h.id = bi.hospital_id
        WHERE h.is_approved = 1
        GROUP BY h.id
    ");

    echo "\nFinal verification:\n";
    while ($row = $stmt->fetch()) {
        echo "- {$row['hospital_name']} ({$row['city']}) - {$row['inventory_count']} blood types\n";
    }

    echo "\n✅ Hospital setup completed!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>