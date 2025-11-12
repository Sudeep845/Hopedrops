<?php
/**
 * HopeDrops Blood Bank Management System
 * Session Check Handler
 * 
 * Checks if user is currently logged in and returns session status
 * Created: November 11, 2025
 */

require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    // Check if session is active and valid
    if (isLoggedIn() && checkSessionTimeout()) {
        // Return user session information
        $sessionData = [
            'logged_in' => true,
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role'],
            'login_time' => $_SESSION['login_time'] ?? null,
            'last_activity' => $_SESSION['last_activity'] ?? null
        ];
        
        // Add hospital-specific data if applicable
        if ($_SESSION['role'] === 'hospital' && isset($_SESSION['hospital_id'])) {
            $sessionData['hospital_id'] = $_SESSION['hospital_id'];
            $sessionData['hospital_name'] = $_SESSION['hospital_name'] ?? null;
        }
        
        sendJsonResponse(true, 'Session is active', $sessionData);
    } else {
        // Check for remember me token
        if (isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            $hashedToken = hash('sha256', $token);
            
            $db = getDBConnection();
            $stmt = $db->prepare("
                SELECT ut.user_id, u.username, u.full_name, u.email, u.role, h.id as hospital_id, h.hospital_name
                FROM user_tokens ut
                JOIN users u ON ut.user_id = u.id
                LEFT JOIN hospitals h ON u.id = h.user_id
                WHERE ut.token = ? AND ut.type = 'remember_me' AND ut.expires_at > NOW() AND u.is_active = 1
            ");
            $stmt->execute([$hashedToken]);
            
            if ($user = $stmt->fetch()) {
                // Restore session
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                
                if ($user['role'] === 'hospital' && $user['hospital_id']) {
                    $_SESSION['hospital_id'] = $user['hospital_id'];
                    $_SESSION['hospital_name'] = $user['hospital_name'];
                }
                
                // Update last login
                $updateStmt = $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                $updateStmt->execute([$user['user_id']]);
                
                // Log auto-login
                logActivity($user['user_id'], 'auto_login', 'Logged in via remember token');
                
                $sessionData = [
                    'logged_in' => true,
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'auto_login' => true
                ];
                
                if ($user['role'] === 'hospital' && $user['hospital_id']) {
                    $sessionData['hospital_id'] = $user['hospital_id'];
                    $sessionData['hospital_name'] = $user['hospital_name'];
                }
                
                sendJsonResponse(true, 'Session restored from remember token', $sessionData);
            } else {
                // Invalid or expired token
                setcookie('remember_token', '', time() - 3600, '/', '', false, true);
                sendJsonResponse(false, 'No active session', ['logged_in' => false]);
            }
        } else {
            sendJsonResponse(false, 'No active session', ['logged_in' => false]);
        }
    }
    
} catch (PDOException $e) {
    error_log("Session check database error: " . $e->getMessage());
    sendJsonResponse(false, 'Database error occurred');
} catch (Exception $e) {
    error_log("Session check error: " . $e->getMessage());
    sendJsonResponse(false, 'An error occurred while checking session');
}
?>