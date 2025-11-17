<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'hopedrops';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

/**
 * Log an audit event
 * 
 * @param string $userId User ID or null for system events
 * @param string $userName User name or 'System' for automated events
 * @param string $category Category of the event
 * @param string $action Action performed
 * @param string $resource Resource affected
 * @param string $status Status (success, warning, error)
 * @param string $ipAddress IP address of the user
 * @param string $location Geographic location
 * @param array $details Additional details as JSON
 */
function logAuditEvent($userId, $userName, $category, $action, $resource, $status, $ipAddress, $location, $details = []) {
    global $pdo;
    
    $query = "INSERT INTO audit_logs (user_id, user_name, category, action, resource, status, ip_address, location, details, timestamp) 
              VALUES (:user_id, :user_name, :category, :action, :resource, :status, :ip_address, :location, :details, NOW())";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'user_id' => $userId,
        'user_name' => $userName,
        'category' => $category,
        'action' => $action,
        'resource' => $resource,
        'status' => $status,
        'ip_address' => $ipAddress,
        'location' => $location,
        'details' => json_encode($details)
    ]);
    
    return $pdo->lastInsertId();
}

// Handle POST request to log new audit event
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $userId = $input['userId'] ?? null;
    $userName = $input['userName'] ?? 'Unknown';
    $category = $input['category'] ?? 'general';
    $action = $input['action'] ?? '';
    $resource = $input['resource'] ?? '';
    $status = $input['status'] ?? 'success';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $location = $input['location'] ?? 'Unknown';
    $details = $input['details'] ?? [];
    
    try {
        $logId = logAuditEvent($userId, $userName, $category, $action, $resource, $status, $ipAddress, $location, $details);
        
        echo json_encode([
            'success' => true,
            'logId' => $logId,
            'message' => 'Audit event logged successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to log audit event: ' . $e->getMessage()
        ]);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>