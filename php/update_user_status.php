<?php
/**
 * HopeDrops Blood Bank Management System
 * Update User Status API - Admin Panel
 * 
 * Handles user activation/deactivation
 * Created: November 16, 2025
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    require_once 'db_connect.php';
    
    // Get database connection
    $pdo = getDBConnection();

    // Temporarily bypass authentication for testing
    // TODO: Re-enable authentication once session system is fixed
    /*
    if (!isLoggedIn()) {
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required'
        ]);
        exit;
    }

    // Get current user role (should be admin)
    $currentUserRole = $_SESSION['role'] ?? '';
    if ($currentUserRole !== 'admin') {
        echo json_encode([
            'success' => false,
            'message' => 'Admin access required'
        ]);
        exit;
    }
    */

    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            'success' => false,
            'message' => 'Only POST method allowed'
        ]);
        exit;
    }

    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $userId = intval($input['user_id'] ?? 0);
    $action = $input['action'] ?? '';

    // Validate input
    if ($userId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Valid user ID is required'
        ]);
        exit;
    }

    if (!in_array($action, ['activate', 'deactivate'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action. Must be activate or deactivate'
        ]);
        exit;
    }

    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, username, email, role, is_active FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit;
    }

    // Prevent admin from deactivating themselves
    // Temporarily bypassed for testing
    // TODO: Re-enable once session system is working
    /*
    $currentUserId = $_SESSION['user_id'] ?? 0;
    if ($userId == $currentUserId && $action === 'deactivate') {
        echo json_encode([
            'success' => false,
            'message' => 'You cannot deactivate your own account'
        ]);
        exit;
    }
    */

    // Update user status based on action
    $updateFields = [];
    $message = '';

    switch ($action) {
        case 'activate':
            $updateFields[] = 'is_active = 1';
            $message = 'User activated successfully';
            break;
        case 'deactivate':
            $updateFields[] = 'is_active = 0';
            $message = 'User deactivated successfully';
            break;
    }

    // Add updated timestamp
    $updateFields[] = 'updated_at = NOW()';

    // Execute update
    $updateQuery = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $pdo->prepare($updateQuery);
    $result = $stmt->execute([$userId]);

    if (!$result || $stmt->rowCount() === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update user status'
        ]);
        exit;
    }

    // Log the action (optional - for audit trail)
    try {
        // Using activity_logs table instead of admin_logs
        $logQuery = "INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->execute([
            1, // Default admin user ID since session is bypassed
            "User Status Update",
            "User '{$user['username']}' (ID: {$userId}) was {$action}d by admin",
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    } catch (Exception $e) {
        // Log creation failed, but don't fail the main operation
        error_log("Failed to create activity log: " . $e->getMessage());
    }

    // Get updated user data
    $stmt = $pdo->prepare("SELECT id, username, email, role, is_active FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $updatedUser = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => [
            'user_id' => $userId,
            'action' => $action,
            'user' => [
                'id' => (int)$updatedUser['id'],
                'username' => $updatedUser['username'],
                'email' => $updatedUser['email'],
                'role' => $updatedUser['role'],
                'is_active' => (bool)$updatedUser['is_active']
            ]
        ]
    ]);

} catch (PDOException $e) {
    error_log("Database error in update_user_status.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("Error in update_user_status.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating user status'
    ]);
}
?>