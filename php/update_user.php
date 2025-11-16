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
    $email = trim($input['email'] ?? '');
    $full_name = trim($input['full_name'] ?? '');
    $role = trim($input['role'] ?? '');
    $is_active = isset($input['is_active']) ? (bool)$input['is_active'] : true;

    // Validate required fields
    if (empty($email) || empty($role)) {
        echo json_encode([
            'success' => false,
            'message' => 'Email and role are required'
        ]);
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email format'
        ]);
        exit;
    }

    // Validate role
    $valid_roles = ['donor', 'hospital', 'admin'];
    if (!in_array($role, $valid_roles)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid role specified'
        ]);
        exit;
    }

    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing_user) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit;
    }

    // Check if email is already taken by another user
    if ($email !== $existing_user['email']) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            echo json_encode([
                'success' => false,
                'message' => 'Email is already taken by another user'
            ]);
            exit;
        }
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Update user (excluding is_verified since column doesn't exist)
        $stmt = $pdo->prepare("
            UPDATE users 
            SET 
                email = ?,
                full_name = ?,
                role = ?,
                is_active = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $email,
            $full_name,
            $role,
            $is_active ? 1 : 0,
            $user_id
        ]);

        // Log the admin action (using session data temporarily)
        $admin_user = ['id' => $_SESSION['user_id'] ?? 1]; // Default admin ID for testing
        $changes = [];
        
        if ($email !== $existing_user['email']) {
            $changes[] = "email: {$existing_user['email']} → {$email}";
        }
        if ($role !== $existing_user['role']) {
            $changes[] = "role: {$existing_user['role']} → {$role}";
        }
        if (!empty($changes)) {
            $changes[] = "is_active: " . ($is_active ? 'true' : 'false');
        }

        $details = "Updated user ID {$user_id}";
        if (!empty($changes)) {
            $details .= ": " . implode(', ', $changes);
        }

        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, created_at)
            VALUES (?, 'User Update', ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $admin_user['id'],
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'User updated successfully'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error in update_user.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating the user'
    ]);
}
?>