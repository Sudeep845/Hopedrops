<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    require_once 'db_connect.php';
    
    // Get database connection
    $pdo = getDBConnection();
    
    // Get user_id from request
    $user_id = $_GET['user_id'] ?? null;
    
    if (!$user_id) {
        echo json_encode([
            'success' => false,
            'message' => 'User ID is required'
        ]);
        exit;
    }

    // Validate that user_id is numeric
    if (!is_numeric($user_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid user ID format'
        ]);
        exit;
    }
    
    // Get hospital details
    $stmt = $pdo->prepare("SELECT * FROM hospitals WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $hospital = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$hospital) {
        // Check if user exists at all
        $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_check = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user_check) {
            echo json_encode([
                'success' => false,
                'message' => "User with ID {$user_id} does not exist"
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => "No hospital found for user '{$user_check['username']}' (Role: {$user_check['role']})"
            ]);
        }
        exit;
    }
    
    // Get user information if available
    try {
        $stmt = $pdo->prepare("SELECT username, email, role, created_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $hospital['username'] = $user['username'];
            $hospital['user_email'] = $user['email'];
            $hospital['role'] = $user['role'];
            $hospital['user_created_at'] = $user['created_at'];
        }
    } catch (Exception $e) {
        // Set defaults if user query fails
        $hospital['username'] = 'Unknown';
        $hospital['user_email'] = 'unknown@example.com';
        $hospital['role'] = 'hospital';
        $hospital['user_created_at'] = $hospital['created_at'] ?? date('Y-m-d H:i:s');
    }
    
    // Set default statistics (can be enhanced later)
    $hospital['total_requests'] = 0;
    $hospital['pending_requests'] = 0;
    $hospital['fulfilled_requests'] = 0;
    $hospital['blood_inventory'] = [];
    
    // Map fields to match frontend expectations with comprehensive safety checks
    $hospital['postal_code'] = $hospital['pincode'] ?? 'N/A';
    $hospital['full_name'] = $hospital['contact_person'] ?? 'N/A';
    $hospital['email'] = $hospital['contact_email'] ?? 'N/A';
    $hospital['phone'] = $hospital['contact_phone'] ?? 'N/A';
    
    // Ensure required fields exist with defaults
    $hospital['is_approved'] = isset($hospital['is_approved']) ? (int)$hospital['is_approved'] : 0;
    $hospital['is_active'] = isset($hospital['is_active']) ? (int)$hospital['is_active'] : 1;
    
    // Set status based on approval and active status
    if ($hospital['is_approved'] && $hospital['is_active']) {
        $hospital['status'] = 'active';
    } elseif ($hospital['is_approved'] && !$hospital['is_active']) {
        $hospital['status'] = 'inactive';
    } else {
        $hospital['status'] = 'pending';
    }
    
    // Set verification status
    $hospital['verification_status'] = $hospital['is_approved'] ? 'verified' : 'pending';
    
    // Add empty arrays for optional data
    $hospital['documents'] = [];
    
    echo json_encode([
        'success' => true,
        'data' => $hospital
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_hospital_details.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("Error in get_hospital_details.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching hospital details'
    ]);
}
?>