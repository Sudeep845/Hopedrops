<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db_connect.php';
$pdo = getDBConnection();

// Temporarily bypass authentication for testing
// TODO: Re-enable authentication once session system is fixed

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'User ID is required'
        ]);
        exit;
    }

    $user_id = (int)$input['user_id'];

    // Get user details
    $stmt = $pdo->prepare("
        SELECT 
            id,
            username,
            email,
            full_name,
            role,
            is_active,
            created_at,
            updated_at
        FROM users 
        WHERE id = ?
    ");
    
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit;
    }

    // Convert boolean values and add defaults for missing columns
    $user['is_active'] = (bool)$user['is_active'];
    $user['is_verified'] = false; // Default since column doesn't exist
    $user['login_count'] = 0; // Default since column doesn't exist
    $user['last_login'] = null; // Default since column doesn't exist
    $user['phone'] = null; // Default since column doesn't exist
    $user['address'] = null; // Default since column doesn't exist

    // Get additional role-specific information
    $additional_info = [];
    
    if ($user['role'] === 'hospital') {
        $stmt = $pdo->prepare("
            SELECT 
                hospital_name,
                license_number,
                address,
                city,
                state,
                contact_person,
                contact_phone,
                contact_email,
                hospital_type,
                is_approved,
                is_active as hospital_is_active,
                created_at as hospital_created_at
            FROM hospitals 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $hospital_info = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($hospital_info) {
            $additional_info['hospital'] = $hospital_info;
        }
    } elseif ($user['role'] === 'donor') {
        // Donor information is stored in the users table
        // No additional donor-specific table exists
        $additional_info['donor'] = [
            'note' => 'Basic donor information is stored in the main users table'
        ];
    }

    // Get recent activity
    $stmt = $pdo->prepare("
        SELECT 
            action,
            description,
            ip_address,
            user_agent,
            created_at
        FROM activity_logs 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'user' => $user,
            'additional_info' => $additional_info,
            'recent_activity' => $recent_activity
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in get_user_details.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while retrieving user details'
    ]);
}
?>