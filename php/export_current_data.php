<?php
/**
 * Export current hospital data to SQL format
 */

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

    echo "-- Current Hospital Data Export\n";
    echo "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";

    // Get all users first
    $stmt = $pdo->query("SELECT * FROM users ORDER BY id");
    $users = $stmt->fetchAll();

    echo "-- Users data\n";
    foreach ($users as $user) {
        $values = [
            "'" . addslashes($user['username']) . "'",
            "'" . addslashes($user['email']) . "'",
            "'" . addslashes($user['password']) . "'",
            "'" . addslashes($user['role']) . "'",
            "'" . addslashes($user['full_name']) . "'",
            $user['phone'] ? "'" . addslashes($user['phone']) . "'" : 'NULL',
            $user['date_of_birth'] ? "'" . $user['date_of_birth'] . "'" : 'NULL',
            $user['gender'] ? "'" . addslashes($user['gender']) . "'" : 'NULL',
            $user['blood_type'] ? "'" . addslashes($user['blood_type']) . "'" : 'NULL',
            $user['address'] ? "'" . addslashes($user['address']) . "'" : 'NULL',
            $user['city'] ? "'" . addslashes($user['city']) . "'" : 'NULL',
            $user['state'] ? "'" . addslashes($user['state']) . "'" : "'Not specified'",
            $user['pincode'] ? "'" . addslashes($user['pincode']) . "'" : "'000000'",
            $user['emergency_contact'] ? "'" . addslashes($user['emergency_contact']) . "'" : 'NULL',
            $user['medical_conditions'] ? "'" . addslashes($user['medical_conditions']) . "'" : 'NULL',
            $user['is_eligible'] ? '1' : '0',
            $user['is_active'] ? '1' : '0'
        ];

        echo "INSERT INTO users (username, email, password, role, full_name, phone, date_of_birth, gender, blood_type, address, city, state, pincode, emergency_contact, medical_conditions, is_eligible, is_active) VALUES (" . implode(', ', $values) . ") ON DUPLICATE KEY UPDATE username = username;\n";
    }

    echo "\n-- Hospitals data\n";
    $stmt = $pdo->query("SELECT * FROM hospitals ORDER BY id");
    $hospitals = $stmt->fetchAll();

    foreach ($hospitals as $hospital) {
        $values = [
            $hospital['user_id'],
            "'" . addslashes($hospital['hospital_name']) . "'",
            "'" . addslashes($hospital['license_number']) . "'",
            "'" . addslashes($hospital['address']) . "'",
            "'" . addslashes($hospital['city']) . "'",
            $hospital['state'] ? "'" . addslashes($hospital['state']) . "'" : "'Not specified'",
            $hospital['pincode'] ? "'" . addslashes($hospital['pincode']) . "'" : "'000000'",
            "'" . addslashes($hospital['contact_person']) . "'",
            "'" . addslashes($hospital['contact_phone']) . "'",
            "'" . addslashes($hospital['contact_email']) . "'",
            $hospital['emergency_contact'] ? "'" . addslashes($hospital['emergency_contact']) . "'" : 'NULL',
            $hospital['latitude'] ? $hospital['latitude'] : 'NULL',
            $hospital['longitude'] ? $hospital['longitude'] : 'NULL',
            $hospital['hospital_type'] ? "'" . addslashes($hospital['hospital_type']) . "'" : "'General'",
            $hospital['phone'] ? "'" . addslashes($hospital['phone']) . "'" : 'NULL',
            $hospital['email'] ? "'" . addslashes($hospital['email']) . "'" : 'NULL',
            $hospital['is_approved'] ? '1' : '0',
            $hospital['is_active'] ? '1' : '0'
        ];

        echo "INSERT INTO hospitals (user_id, hospital_name, license_number, address, city, state, pincode, contact_person, contact_phone, contact_email, emergency_contact, latitude, longitude, hospital_type, phone, email, is_approved, is_active) VALUES (" . implode(', ', $values) . ") ON DUPLICATE KEY UPDATE hospital_name = VALUES(hospital_name);\n";
    }

    echo "\n-- Blood inventory data\n";
    $stmt = $pdo->query("SELECT * FROM blood_inventory ORDER BY hospital_id, blood_type");
    $inventory = $stmt->fetchAll();

    foreach ($inventory as $item) {
        $values = [
            $item['hospital_id'],
            "'" . addslashes($item['blood_type']) . "'",
            $item['units_available'],
            $item['units_required'] ?? 0
        ];

        echo "INSERT INTO blood_inventory (hospital_id, blood_type, units_available, units_required) VALUES (" . implode(', ', $values) . ") ON DUPLICATE KEY UPDATE units_available = VALUES(units_available), units_required = VALUES(units_required);\n";
    }

    echo "\n-- Export completed\n";

} catch (Exception $e) {
    echo "-- Error: " . $e->getMessage() . "\n";
}
?>