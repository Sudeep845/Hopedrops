<?php
/**
 * Test Search Hospitals without authentication
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

    // Simple query to get all approved hospitals with blood inventory
    $query = "
        SELECT DISTINCT
            h.id,
            h.hospital_name,
            h.address,
            h.city,
            h.state,
            h.contact_phone as phone,
            h.contact_email as email,
            h.hospital_type,
            bi.blood_type,
            bi.units_available,
            bi.last_updated
        FROM hospitals h
        LEFT JOIN blood_inventory bi ON h.id = bi.hospital_id
        WHERE h.is_approved = 1
        ORDER BY h.hospital_name, bi.blood_type
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll();

    // Group results by hospital
    $hospitals = [];
    foreach ($results as $row) {
        $hospitalId = $row['id'];
        
        if (!isset($hospitals[$hospitalId])) {
            $hospitals[$hospitalId] = [
                'id' => $row['id'],
                'hospital_name' => $row['hospital_name'],
                'address' => $row['address'],
                'city' => $row['city'],
                'state' => $row['state'],
                'phone' => $row['phone'],
                'email' => $row['email'],
                'hospital_type' => $row['hospital_type'] ?? 'General',
                'blood_inventory' => [],
                'total_units' => 0
            ];
        }

        if ($row['blood_type']) {
            $hospitals[$hospitalId]['blood_inventory'][] = [
                'blood_type' => $row['blood_type'],
                'units_available' => $row['units_available'],
                'last_updated' => $row['last_updated']
            ];
            $hospitals[$hospitalId]['total_units'] += $row['units_available'];
        }
    }

    // Convert to indexed array
    $hospitalsList = array_values($hospitals);

    echo json_encode([
        'success' => true,
        'message' => 'Hospitals retrieved successfully',
        'data' => [
            'hospitals' => $hospitalsList,
            'total_count' => count($hospitalsList),
            'search_info' => [
                'location' => 'all',
                'blood_type' => 'all',
                'total_results' => count($hospitalsList)
            ]
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>