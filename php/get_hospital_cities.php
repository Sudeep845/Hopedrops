<?php
/**
 * Get Hospital Cities API
 * Returns list of cities where hospitals are registered
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db_connect.php';

try {
    // Check if user is authenticated and is admin
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Access denied. Admin privileges required.'
        ]);
        exit;
    }
    
    $db = getDBConnection();
    
    // Get distinct cities from hospitals table
    $stmt = $db->prepare("
        SELECT DISTINCT city 
        FROM hospitals 
        WHERE city IS NOT NULL AND city != '' 
        ORDER BY city ASC
    ");
    
    $stmt->execute();
    $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Return response
    echo json_encode([
        'success' => true,
        'data' => $cities,
        'count' => count($cities)
    ]);
    
} catch (Exception $e) {
    error_log("Get hospital cities error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load hospital cities'
    ]);
}
?>