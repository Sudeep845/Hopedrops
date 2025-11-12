<?php
/**
 * HopeDrops Blood Bank Management System
 * Logout Handler
 * 
 * Handles user logout and session cleanup
 * Created: November 11, 2025
 */

require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (isLoggedIn()) {
        $userId = $_SESSION['user_id'];
        $username = $_SESSION['username'];
        
        // Log the logout activity
        logActivity($userId, 'logout', 'User logged out');
        
        // Clear remember me token if exists
        if (isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            $hashedToken = hash('sha256', $token);
            
            $db = getDBConnection();
            $stmt = $db->prepare("DELETE FROM user_tokens WHERE token = ? AND type = 'remember_me'");
            $stmt->execute([$hashedToken]);
            
            // Clear the cookie
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }
        
        // Destroy session
        session_unset();
        session_destroy();
        
        // Start a new session for potential immediate re-login
        session_start();
        
        sendJsonResponse(true, 'Logged out successfully');
        
    } else {
        sendJsonResponse(false, 'No active session to logout');
    }
    
} catch (PDOException $e) {
    error_log("Logout database error: " . $e->getMessage());
    sendJsonResponse(false, 'Database error occurred during logout');
} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());
    sendJsonResponse(false, 'An error occurred during logout');
}
?>