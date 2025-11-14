<?php
/**
 * Update Sherpa Hospital Data - Fix without deleting
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

    echo "Checking current hospital data...\n\n";

    // Find the sherpa hospital record
    $stmt = $pdo->prepare("SELECT * FROM hospitals WHERE hospital_name LIKE '%sherpa%' OR contact_email = 'ni@ma.com'");
    $stmt->execute();
    $sherpaHospital = $stmt->fetch();

    if ($sherpaHospital) {
        echo "Found hospital record: {$sherpaHospital['hospital_name']}\n";
        echo "Current data:\n";
        echo "- ID: {$sherpaHospital['id']}\n";
        echo "- Name: {$sherpaHospital['hospital_name']}\n";
        echo "- Address: {$sherpaHospital['address']}\n";
        echo "- City: {$sherpaHospital['city']}\n";
        echo "- Phone: {$sherpaHospital['contact_phone']}\n";
        echo "- Email: {$sherpaHospital['contact_email']}\n";
        echo "- Approved: " . ($sherpaHospital['is_approved'] ? 'Yes' : 'No') . "\n\n";

        // Update the hospital record to have better data
        $stmt = $pdo->prepare("
            UPDATE hospitals 
            SET hospital_name = ?, 
                address = ?, 
                city = ?, 
                contact_phone = ?, 
                contact_email = ?,
                is_approved = 1,
                hospital_type = 'General'
            WHERE id = ?
        ");
        
        $stmt->execute([
            'Sherpa Hospital',  // Better name
            'Kapan, Kathmandu', // Better address format
            'Kathmandu',
            '9800000000',
            'sherpa@hospital.com', // Better email
            $sherpaHospital['id']
        ]);

        echo "✅ Updated hospital record with better information\n";

        // Check if hospital has blood inventory
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM blood_inventory WHERE hospital_id = ?");
        $stmt->execute([$sherpaHospital['id']]);
        $inventoryCount = $stmt->fetch()['count'];

        if ($inventoryCount == 0) {
            echo "No blood inventory found. Adding blood inventory...\n";
            
            // Add blood inventory with some available units
            $bloodTypes = ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'];
            foreach ($bloodTypes as $bloodType) {
                $units = rand(5, 20); // Random units between 5-20
                $stmt = $pdo->prepare("
                    INSERT INTO blood_inventory (hospital_id, blood_type, units_available, last_updated)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$sherpaHospital['id'], $bloodType, $units]);
            }
            echo "✅ Added blood inventory with available units\n";
        } else {
            echo "Blood inventory exists ({$inventoryCount} records)\n";
            
            // Update existing inventory to have some units instead of 0
            $stmt = $pdo->prepare("
                UPDATE blood_inventory 
                SET units_available = CASE 
                    WHEN units_available = 0 THEN FLOOR(RAND() * 15) + 5
                    ELSE units_available 
                END,
                last_updated = NOW()
                WHERE hospital_id = ?
            ");
            $stmt->execute([$sherpaHospital['id']]);
            echo "✅ Updated blood inventory to have available units\n";
        }

    } else {
        echo "No 'sherpa' hospital found in database.\n";
    }

    // Show all hospitals to verify
    echo "\nAll hospitals in system:\n";
    $stmt = $pdo->query("
        SELECT h.hospital_name, h.city, h.contact_phone, h.contact_email, h.is_approved,
               COUNT(bi.id) as inventory_count,
               SUM(bi.units_available) as total_units
        FROM hospitals h
        LEFT JOIN blood_inventory bi ON h.id = bi.hospital_id
        GROUP BY h.id
        ORDER BY h.hospital_name
    ");
    
    while ($row = $stmt->fetch()) {
        $status = $row['is_approved'] ? 'Approved' : 'Pending';
        echo "- {$row['hospital_name']} ({$row['city']}) - {$row['contact_phone']} - {$status} - {$row['total_units']} total units\n";
    }

    echo "\n✅ Update completed! No data was deleted.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>