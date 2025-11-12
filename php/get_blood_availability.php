<?php
/**
 * HopeDrops Blood Bank Management System
 * Blood Availability Data Provider
 * 
 * Returns current blood availability across all hospitals
 * Created: November 11, 2025
 */

require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    $db = getDBConnection();
    
    // Get total blood availability across all approved hospitals
    $stmt = $db->prepare("
        SELECT 
            bi.blood_type,
            SUM(bi.units_available) as total_units,
            SUM(bi.units_required) as total_required,
            COUNT(DISTINCT h.id) as hospitals_count
        FROM blood_inventory bi
        JOIN hospitals h ON bi.hospital_id = h.id
        WHERE h.is_approved = 1
        GROUP BY bi.blood_type
        ORDER BY bi.blood_type
    ");
    
    $stmt->execute();
    $bloodData = $stmt->fetchAll();
    
    // If no data found, return default structure
    if (empty($bloodData)) {
        $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        $bloodData = [];
        foreach ($bloodTypes as $type) {
            $bloodData[] = [
                'blood_type' => $type,
                'total_units' => 0,
                'total_required' => 0,
                'hospitals_count' => 0
            ];
        }
    }
    
    sendJsonResponse(true, 'Blood availability data retrieved successfully', $bloodData);
    
} catch (PDOException $e) {
    error_log("Blood availability database error: " . $e->getMessage());
    sendJsonResponse(false, 'Database error occurred');
} catch (Exception $e) {
    error_log("Blood availability error: " . $e->getMessage());
    sendJsonResponse(false, 'An error occurred while retrieving blood data');
}
?>