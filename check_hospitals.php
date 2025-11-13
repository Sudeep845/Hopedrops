<?php
require_once 'php/db_connect.php';

try {
    $stmt = $pdo->query('SELECT h.id, h.user_id, h.hospital_name, h.license_number, u.username FROM hospitals h JOIN users u ON h.user_id = u.id LIMIT 5');
    $hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($hospitals) {
        echo "Hospitals found:\n";
        foreach ($hospitals as $h) {
            echo "ID: {$h['id']}, User ID: {$h['user_id']}, Name: {$h['hospital_name']}, License: {$h['license_number']}, Username: {$h['username']}\n";
        }
    } else {
        echo "No hospitals found in database.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>