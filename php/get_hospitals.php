<?php
/**
 * HopeDrops Blood Bank Management System
 * Hospitals Data Provider
 * 
 * Returns list of approved hospitals
 * Created: November 11, 2025
 */

require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    $db = getDBConnection();
    
    // Get query parameters
    $city = sanitizeInput($_GET['city'] ?? '');
    $hasBloodType = sanitizeInput($_GET['blood_type'] ?? '');
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    
    // Make limit available globally for error handling
    $GLOBALS['api_limit'] = $limit;
    
    // By default include a no-op where clause. If the caller is not an admin,
    // restrict results to only approved hospitals. Admin users should see all
    // hospitals (including pending) so they can approve/reject them.
    $whereConditions = ['1=1'];
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        $whereConditions[] = 'h.is_approved = 1';
    }
    $params = [];
    
    if (!empty($city)) {
        $whereConditions[] = 'h.city LIKE ?';
        $params[] = "%{$city}%";
    }
    
    if (!empty($hasBloodType)) {
        $whereConditions[] = 'EXISTS (
            SELECT 1 FROM blood_inventory bi 
            WHERE bi.hospital_id = h.id 
            AND bi.blood_type = ? 
            AND bi.units_available > 0
        )';
        $params[] = $hasBloodType;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    $stmt = $db->prepare("
        SELECT 
            h.id,
            h.user_id,
            h.hospital_name,
            h.license_number,
            h.address,
            h.city,
            h.state,
            h.pincode,
            h.contact_person,
            h.contact_phone,
            h.contact_email,
            h.registration_number,
            h.established_date,
            h.bed_capacity,
            h.hospital_type,
            h.services,
            h.is_approved,
            h.created_at,
            u.full_name as contact_person_name,
            u.email,
            u.phone,
            CASE 
                WHEN h.is_approved = 1 THEN 'active'
                WHEN h.is_approved = 0 THEN 'pending'
                ELSE 'inactive'
            END as status,
            (
                SELECT SUM(bi.units_available) 
                FROM blood_inventory bi 
                WHERE bi.hospital_id = h.id
            ) as total_blood_units
        FROM hospitals h
        JOIN users u ON h.user_id = u.id
        {$whereClause}
        ORDER BY h.created_at DESC
        LIMIT ?
    ");
    
    $params[] = $limit;
    $stmt->execute($params);
    $hospitals = $stmt->fetchAll();
    
    // Get blood inventory for each hospital
    foreach ($hospitals as &$hospital) {
        $inventoryStmt = $db->prepare("
            SELECT blood_type, units_available, units_required 
            FROM blood_inventory 
            WHERE hospital_id = ? 
            ORDER BY blood_type
        ");
        $inventoryStmt->execute([$hospital['id']]);
        $hospital['blood_inventory'] = $inventoryStmt->fetchAll();
    }
    
    // Ensure we return an array even if empty
    $hospitals = $hospitals ?: [];
    
    // Get total count for pagination
    $countStmt = $db->prepare("
        SELECT COUNT(*) 
        FROM hospitals h
        JOIN users u ON h.user_id = u.id
        {$whereClause}
    ");
    // Remove the limit parameter for count query
    $countParams = array_slice($params, 0, -1);
    $countStmt->execute($countParams);
    $total = $countStmt->fetchColumn();
    
    // Structure response to match admin dashboard expectations
    $responseData = [
        'hospitals' => $hospitals,
        'pagination' => [
            'total' => (int)$total,
            'current_page' => 1,
            'per_page' => $limit,
            'total_pages' => ceil($total / $limit)
        ]
    ];
    
    sendJsonResponse(true, 'Hospitals retrieved successfully', $responseData);
    
} catch (PDOException $e) {
    error_log("Hospitals database error: " . $e->getMessage());
    $errorLimit = isset($GLOBALS['api_limit']) ? $GLOBALS['api_limit'] : 50;
    sendJsonResponse(false, 'Database error occurred', [
        'hospitals' => [],
        'pagination' => ['total' => 0, 'current_page' => 1, 'per_page' => $errorLimit, 'total_pages' => 0]
    ]);
} catch (Exception $e) {
    error_log("Hospitals error: " . $e->getMessage());
    $errorLimit = isset($GLOBALS['api_limit']) ? $GLOBALS['api_limit'] : 50;
    sendJsonResponse(false, 'An error occurred while retrieving hospitals', [
        'hospitals' => [],
        'pagination' => ['total' => 0, 'current_page' => 1, 'per_page' => $errorLimit, 'total_pages' => 0]
    ]);
}
?>