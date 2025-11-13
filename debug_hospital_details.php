<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

echo "Debug: Starting script\n";

try {
    echo "Debug: About to require db_connect.php\n";
    require_once 'db_connect.php';
    echo "Debug: db_connect.php loaded successfully\n";
    
    // Get user_id from request
    $user_id = $_GET['user_id'] ?? null;
    echo "Debug: user_id = $user_id\n";
    
    if (!$user_id) {
        echo json_encode([
            'success' => false,
            'message' => 'User ID is required'
        ]);
        exit;
    }
    
    echo "Debug: About to query database\n";
    
    // Test database connection first
    $stmt = $pdo->prepare("SELECT 1");
    $stmt->execute();
    echo "Debug: Database connection test successful\n";
    
    // Get hospital details with user information
    $stmt = $pdo->prepare("
        SELECT 
            h.*,
            u.username,
            u.email as user_email,
            u.role,
            u.created_at as user_created_at,
            u.is_active as user_is_active
        FROM hospitals h
        INNER JOIN users u ON h.user_id = u.id
        WHERE h.user_id = ?
    ");
    
    echo "Debug: Query prepared\n";
    
    $stmt->execute([$user_id]);
    echo "Debug: Query executed\n";
    
    $hospital = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Debug: Fetch completed\n";
    
    if (!$hospital) {
        echo json_encode([
            'success' => false,
            'message' => 'Hospital not found'
        ]);
        exit;
    }
    
    echo "Debug: Hospital found, processing data\n";
    
    echo json_encode([
        'success' => true,
        'message' => 'Debug successful',
        'debug_info' => 'Hospital data retrieved successfully',
        'data' => $hospital
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'debug_error' => $e->getMessage(),
        'debug_trace' => $e->getTraceAsString()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching hospital details',
        'debug_error' => $e->getMessage(),
        'debug_trace' => $e->getTraceAsString()
    ]);
}
?>