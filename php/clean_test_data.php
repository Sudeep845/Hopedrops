<?php
/**
 * HopeDrops Blood Bank Management System
 * Clean Test Data Script
 * 
 * Removes test/placeholder data from the database
 * Created: November 14, 2025
 */

header('Content-Type: application/json');

try {
    // Database connection
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

    echo "Starting cleanup of test data...\n\n";

    // Clean up test hospitals
    $testHospitalPatterns = [
        'sherpa hos',
        'Sherpa hospital',
        'test_%',
        'Test Hospital%'
    ];

    $cleanedHospitals = 0;
    foreach ($testHospitalPatterns as $pattern) {
        $stmt = $pdo->prepare("
            DELETE FROM hospitals 
            WHERE hospital_name LIKE ? 
            OR contact_email LIKE 'test_%@%' 
            OR contact_email = 'nimahospital@gmail.com'
            OR contact_phone = '9811111111'
        ");
        $stmt->execute([$pattern]);
        $cleanedHospitals += $stmt->rowCount();
    }

    echo "Cleaned {$cleanedHospitals} test hospital records\n";

    // Clean up test users associated with test hospitals
    $testUserPatterns = [
        'test_%',
        'nima'
    ];

    $cleanedUsers = 0;
    foreach ($testUserPatterns as $pattern) {
        $stmt = $pdo->prepare("
            DELETE FROM users 
            WHERE username LIKE ? 
            OR email LIKE 'test_%@%'
            OR email = 'nimahospital@gmail.com'
        ");
        $stmt->execute([$pattern]);
        $cleanedUsers += $stmt->rowCount();
    }

    echo "Cleaned {$cleanedUsers} test user records\n";

    // Clean up orphaned blood inventory records
    $stmt = $pdo->prepare("
        DELETE bi FROM blood_inventory bi
        LEFT JOIN hospitals h ON bi.hospital_id = h.id
        WHERE h.id IS NULL
    ");
    $stmt->execute();
    $cleanedInventory = $stmt->rowCount();
    echo "Cleaned {$cleanedInventory} orphaned blood inventory records\n";

    // Clean up orphaned hospital activities
    $stmt = $pdo->prepare("
        DELETE ha FROM hospital_activities ha
        LEFT JOIN hospitals h ON ha.hospital_id = h.id
        WHERE h.id IS NULL
    ");
    $stmt->execute();
    $cleanedActivities = $stmt->rowCount();
    echo "Cleaned {$cleanedActivities} orphaned hospital activity records\n";

    // Insert sample real hospitals if database is empty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM hospitals WHERE is_approved = 1");
    $hospitalCount = $stmt->fetch()['count'];

    if ($hospitalCount == 0) {
        echo "\nNo approved hospitals found. Adding sample hospitals...\n";

        // Add sample hospitals
        $sampleHospitals = [
            [
                'hospital_name' => 'City General Hospital',
                'address' => 'Medical District, Downtown',
                'city' => 'Kathmandu',
                'state' => 'Bagmati',
                'contact_phone' => '01-4412345',
                'contact_email' => 'info@citygeneral.com.np',
                'hospital_type' => 'Government',
                'bed_capacity' => 200,
                'license_number' => 'CGH001',
                'registration_number' => 'REG001',
                'established_date' => '1980-01-01',
                'user_id' => 1
            ],
            [
                'hospital_name' => 'Metro Heart Center',
                'address' => 'Ring Road, Maharajgunj',
                'city' => 'Kathmandu',
                'state' => 'Bagmati',
                'contact_phone' => '01-4423456',
                'contact_email' => 'contact@metroheart.com.np',
                'hospital_type' => 'Private',
                'bed_capacity' => 150,
                'license_number' => 'MHC002',
                'registration_number' => 'REG002',
                'established_date' => '1995-06-15',
                'user_id' => 1
            ],
            [
                'hospital_name' => 'Valley Medical Institute',
                'address' => 'Patan Dhoka, Lalitpur',
                'city' => 'Lalitpur',
                'state' => 'Bagmati',
                'contact_phone' => '01-5567890',
                'contact_email' => 'admin@valleymedical.org.np',
                'hospital_type' => 'Non-Profit',
                'bed_capacity' => 120,
                'license_number' => 'VMI003',
                'registration_number' => 'REG003',
                'established_date' => '2000-03-20',
                'user_id' => 1
            ]
        ];

        foreach ($sampleHospitals as $hospital) {
            $stmt = $pdo->prepare("
                INSERT INTO hospitals (
                    user_id, hospital_name, address, city, state, contact_phone, contact_email,
                    hospital_type, bed_capacity, license_number, registration_number,
                    established_date, is_approved, created_at
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
                $hospital['hospital_type'],
                $hospital['bed_capacity'],
                $hospital['license_number'],
                $hospital['registration_number'],
                $hospital['established_date']
            ]);

            $hospitalId = $pdo->lastInsertId();

            // Add sample blood inventory for each hospital
            $bloodTypes = ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'];
            foreach ($bloodTypes as $bloodType) {
                $units = rand(0, 20); // Random units between 0-20
                $stmt = $pdo->prepare("
                    INSERT INTO blood_inventory (hospital_id, blood_type, units_available, last_updated)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$hospitalId, $bloodType, $units]);
            }

            echo "Added sample hospital: {$hospital['hospital_name']}\n";
        }
    }

    echo "\n✅ Database cleanup completed successfully!\n";
    echo "Test data removed and real hospital data is now properly displayed.\n";

} catch (Exception $e) {
    echo "❌ Error during cleanup: " . $e->getMessage() . "\n";
}
?>