<?php
/**
 * Update Hospital Status API
 * Handles hospital approval, suspension, reactivation, and rejection
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
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
    
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed. Use POST.'
        ]);
        exit;
    }
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $userId = (int)($input['user_id'] ?? 0);
    $action = sanitizeInput($input['action'] ?? '');
    $status = sanitizeInput($input['status'] ?? '');
    
    if (!$userId) {
        echo json_encode([
            'success' => false,
            'message' => 'User ID is required'
        ]);
        exit;
    }
    
    if (!in_array($action, ['approve', 'reject', 'suspend', 'reactivate'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action. Must be: approve, reject, suspend, or reactivate'
        ]);
        exit;
    }
    
    $db = getDBConnection();
    
    // Check if user exists and is a hospital
    $userStmt = $db->prepare("
        SELECT u.id, u.username, u.full_name, u.role, h.id as hospital_id, h.hospital_name 
        FROM users u 
        LEFT JOIN hospitals h ON u.id = h.user_id 
        WHERE u.id = ? AND u.role = 'hospital'
    ");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    
    if (!$user || !$user['hospital_id']) {
        echo json_encode([
            'success' => false,
            'message' => 'Hospital not found'
        ]);
        exit;
    }
    
    $db->beginTransaction();
    
    try {
        // Update hospital status based on action
        switch ($action) {
            case 'approve':
                // Approve hospital
                $updateStmt = $db->prepare("UPDATE hospitals SET is_approved = 1 WHERE user_id = ?");
                $updateStmt->execute([$userId]);
                
                // Activate user account
                $userUpdateStmt = $db->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
                $userUpdateStmt->execute([$userId]);
                
                $actionMsg = 'approved';
                break;
                
            case 'reject':
                // Reject hospital (keep as not approved)
                $updateStmt = $db->prepare("UPDATE hospitals SET is_approved = 0 WHERE user_id = ?");
                $updateStmt->execute([$userId]);
                
                // Deactivate user account
                $userUpdateStmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                $userUpdateStmt->execute([$userId]);
                
                $actionMsg = 'rejected';
                break;
                
            case 'suspend':
                // Suspend hospital (approved but inactive)
                $updateStmt = $db->prepare("UPDATE hospitals SET is_approved = 1 WHERE user_id = ?");
                $updateStmt->execute([$userId]);
                
                // Deactivate user account
                $userUpdateStmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                $userUpdateStmt->execute([$userId]);
                
                $actionMsg = 'suspended';
                break;
                
            case 'reactivate':
                // Reactivate hospital
                $updateStmt = $db->prepare("UPDATE hospitals SET is_approved = 1 WHERE user_id = ?");
                $updateStmt->execute([$userId]);
                
                // Activate user account
                $userUpdateStmt = $db->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
                $userUpdateStmt->execute([$userId]);
                
                $actionMsg = 'reactivated';
                break;
        }
        
        // Log the action in activity logs (if table exists)
        try {
            $logStmt = $db->prepare("
                INSERT INTO activity_logs (user_id, action, description, ip_address) 
                VALUES (?, ?, ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['user_id'],
                'Hospital ' . ucfirst($action),
                "Hospital '{$user['hospital_name']}' (ID: {$user['hospital_id']}) has been {$actionMsg} by admin",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            // activity_logs table might not exist, that's okay
        }
        
        // Create notification for the hospital user (if notifications table exists)
        try {
            $notificationStmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type) 
                VALUES (?, ?, ?, ?)
            ");
            
            $notificationTitle = "Hospital Registration " . ucfirst($actionMsg);
            $notificationMessage = '';
            $notificationType = 'info';
            
            switch ($action) {
                case 'approve':
                    $notificationMessage = "Congratulations! Your hospital registration has been approved. You can now access all hospital features.";
                    $notificationType = 'success';
                    break;
                case 'reject':
                    $notificationMessage = "Your hospital registration has been rejected. Please contact support for more information.";
                    $notificationType = 'error';
                    break;
                case 'suspend':
                    $notificationMessage = "Your hospital account has been suspended. Please contact support for assistance.";
                    $notificationType = 'warning';
                    break;
                case 'reactivate':
                    $notificationMessage = "Your hospital account has been reactivated. You can now access all features.";
                    $notificationType = 'success';
                    break;
            }
            
            $notificationStmt->execute([
                $userId,
                $notificationTitle,
                $notificationMessage,
                $notificationType
            ]);
        } catch (Exception $e) {
            // notifications table might not exist, that's okay
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Hospital has been {$actionMsg} successfully",
            'data' => [
                'user_id' => $userId,
                'hospital_id' => $user['hospital_id'],
                'hospital_name' => $user['hospital_name'],
                'action' => $action,
                'status' => $actionMsg
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Update hospital status error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update hospital status: ' . $e->getMessage()
    ]);
}
?>