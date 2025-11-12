<?php
/**
 * HopeDrops Blood Bank Management System
 * Search Hospitals API
 * 
 * Searches for hospitals/blood banks based on location, blood type, etc.
 * Created: November 12, 2025
 */

require_once 'db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    sendJsonResponse(false, 'Authentication required');
    exit;
}

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        // Try form data
        $input = $_POST;
    }
    
    $location = $input['location'] ?? '';
    $bloodType = $input['blood_type'] ?? '';
    $radius = intval($input['radius'] ?? 50);
    $hospitalType = $input['hospital_type'] ?? '';
    $emergencyMode = ($input['emergency_mode'] ?? false) === true;
    
    $db = getDBConnection();
    
    // Base query to get hospitals with blood inventory
    $query = "
        SELECT DISTINCT
            h.id,
            h.hospital_name,
            h.address,
            h.city,
            h.state,
            h.contact_phone as phone,
            h.contact_email as email,
            NULL as latitude,
            NULL as longitude,
            'General' as hospital_type,
            h.emergency_contact,
            bi.blood_type,
            bi.units_available,
            bi.last_updated,
            CASE 
                WHEN bi.units_available > 10 THEN 'High'
                WHEN bi.units_available > 5 THEN 'Medium'
                WHEN bi.units_available > 0 THEN 'Low'
                ELSE 'Out of Stock'
            END as availability_status
        FROM hospitals h
        LEFT JOIN blood_inventory bi ON h.id = bi.hospital_id
        WHERE h.is_approved = 1
    ";
    
    $params = [];
    
    // Filter by blood type if specified
    if (!empty($bloodType)) {
        $query .= " AND bi.blood_type = ?";
        $params[] = $bloodType;
    }
    
    // Filter by hospital type if specified (currently not supported)
    // TODO: Add hospital_type column to hospitals table
    // if (!empty($hospitalType)) {
    //     $query .= " AND h.hospital_type = ?";
    //     $params[] = $hospitalType;
    // }
    
    // Filter by location (city/state) if specified
    if (!empty($location)) {
        $query .= " AND (h.city LIKE ? OR h.state LIKE ? OR h.address LIKE ?)";
        $locationParam = "%{$location}%";
        $params[] = $locationParam;
        $params[] = $locationParam;
        $params[] = $locationParam;
    }
    
    // Emergency mode - prioritize hospitals with emergency contact
    if ($emergencyMode) {
        $query .= " AND h.emergency_contact IS NOT NULL AND h.emergency_contact != ''";
        $query .= " ORDER BY bi.units_available DESC, h.hospital_name ASC";
    } else {
        $query .= " ORDER BY 
            CASE 
                WHEN bi.units_available > 10 THEN 1
                WHEN bi.units_available > 5 THEN 2
                WHEN bi.units_available > 0 THEN 3
                ELSE 4
            END,
            h.hospital_name ASC";
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude'],
                'hospital_type' => $row['hospital_type'],
                'emergency_contact' => $row['emergency_contact'],
                'blood_inventory' => [],
                'total_units' => 0,
                'has_requested_type' => false
            ];
        }
        
        // Add blood inventory data
        if ($row['blood_type']) {
            $hospitals[$hospitalId]['blood_inventory'][] = [
                'blood_type' => $row['blood_type'],
                'units_available' => intval($row['units_available']),
                'availability_status' => $row['availability_status'],
                'last_updated' => $row['last_updated']
            ];
            
            $hospitals[$hospitalId]['total_units'] += intval($row['units_available']);
            
            // Check if this hospital has the requested blood type
            if ($bloodType && $row['blood_type'] === $bloodType) {
                $hospitals[$hospitalId]['has_requested_type'] = true;
            }
        }
    }
    
    // Convert to indexed array and apply additional filters
    $hospitalList = array_values($hospitals);
    
    // If emergency mode, limit to hospitals with available blood
    if ($emergencyMode) {
        $hospitalList = array_filter($hospitalList, function($hospital) {
            return $hospital['total_units'] > 0;
        });
    }
    
    // Limit results for performance
    $hospitalList = array_slice($hospitalList, 0, 50);
    
    // Add search metadata
    $searchInfo = [
        'total_results' => count($hospitalList),
        'search_criteria' => [
            'location' => $location,
            'blood_type' => $bloodType,
            'radius' => $radius,
            'hospital_type' => $hospitalType,
            'emergency_mode' => $emergencyMode
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    sendJsonResponse(true, 'Search completed successfully', [
        'hospitals' => $hospitalList,
        'search_info' => $searchInfo
    ]);
    
} catch (Exception $e) {
    error_log("Search hospitals error: " . $e->getMessage());
    sendJsonResponse(false, 'Search failed: ' . $e->getMessage());
}
?>