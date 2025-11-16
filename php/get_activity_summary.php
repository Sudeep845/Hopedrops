<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $limit = isset($input['limit']) ? (int)$input['limit'] : 20;
    
    // Get recent activities from activity_logs table
    $stmt = $pdo->prepare("
        SELECT 
            action,
            description,
            ip_address,
            created_at
        FROM activity_logs 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    
    $stmt->execute([$limit]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'activities' => $activities,
        'count' => count($activities)
    ]);

} catch (Exception $e) {
    error_log("Error in get_activity_summary.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while retrieving activity summary',
        'activities' => []
    ]);
}
?>