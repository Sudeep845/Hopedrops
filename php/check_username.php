<?php
/**
 * HopeDrops Blood Bank Management System
 * Username Availability Checker
 * 
 * Checks if a username is available during registration
 * Created: November 11, 2025
 */

require_once 'db_connect.php';

header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Invalid request method');
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $username = sanitizeInput($input['username'] ?? '');
    
    if (empty($username)) {
        sendJsonResponse(false, 'Username is required');
    }
    
    if (strlen($username) < 3) {
        sendJsonResponse(false, 'Username must be at least 3 characters', ['available' => false]);
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        sendJsonResponse(false, 'Username can only contain letters, numbers, and underscores', ['available' => false]);
    }
    
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    $available = !$stmt->fetch();
    
    if ($available) {
        sendJsonResponse(true, 'Username is available', ['available' => true]);
    } else {
        sendJsonResponse(false, 'Username is already taken', ['available' => false]);
    }
    
} catch (PDOException $e) {
    error_log("Username check database error: " . $e->getMessage());
    sendJsonResponse(false, 'Database error occurred');
} catch (Exception $e) {
    error_log("Username check error: " . $e->getMessage());
    sendJsonResponse(false, 'An error occurred');
}
?>