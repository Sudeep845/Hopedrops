<?php
/**
 * HopeDrops Blood Bank Management System
 * Login Authentication Handler
 * 
 * Handles user authentication for different roles (admin, hospital, donor)
 * Created: November 11, 2025
 */

require_once 'db_connect.php';

header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Invalid request method');
}

// Check rate limiting
if (!checkRateLimit('login', 5, 300)) {
    sendJsonResponse(false, 'Too many login attempts. Please try again later.');
}

try {
    // Get and sanitize input
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = sanitizeInput($_POST['role'] ?? 'donor');
    $rememberMe = isset($_POST['rememberMe']) && $_POST['rememberMe'] === 'on';
    
    // Validate input
    if (empty($username)) {
        sendJsonResponse(false, 'Username or email is required', null, 'username');
    }
    
    if (empty($password)) {
        sendJsonResponse(false, 'Password is required', null, 'password');
    }
    
    if (!in_array($role, ['admin', 'hospital', 'donor'])) {
        sendJsonResponse(false, 'Invalid user role');
    }
    
    $db = getDBConnection();
    
    // Build query based on login input (username or email)
    $loginField = validateEmail($username) ? 'email' : 'username';
    
    $query = "
        SELECT u.*, h.hospital_name, h.id as hospital_id 
        FROM users u 
        LEFT JOIN hospitals h ON u.id = h.user_id 
        WHERE u.{$loginField} = ? AND u.role = ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$username, $role]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Log failed login attempt
        logActivity(null, 'failed_login', "Failed login attempt for {$username} as {$role}");
        sendJsonResponse(false, 'Invalid credentials or account not found');
    }
    
    // Verify password
    if (!verifyPassword($password, $user['password'])) {
        // Log failed login attempt
        logActivity($user['id'], 'failed_login', 'Invalid password');
        sendJsonResponse(false, 'Invalid credentials');
    }
    
    // Check if hospital account is approved (for hospital role)
    if ($role === 'hospital') {
        $stmt = $db->prepare("SELECT is_approved FROM hospitals WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $hospital = $stmt->fetch();
        
        if (!$hospital || !$hospital['is_approved']) {
            sendJsonResponse(false, 'Hospital account is pending approval. Please contact administration.');
        }
    }
    
    // Successful login - create session
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    
    // Hospital-specific session data
    if ($role === 'hospital' && isset($user['hospital_id'])) {
        $_SESSION['hospital_id'] = $user['hospital_id'];
        $_SESSION['hospital_name'] = $user['hospital_name'];
    }
    
    // Set remember me cookie if requested (simplified version)
    if ($rememberMe) {
        $expiry = time() + (30 * 24 * 60 * 60); // 30 days
        setcookie('remember_user', $user['username'], $expiry, '/', '', false, true);
    }
    
    // Log successful login
    logActivity($user['id'], 'login', "Successful login as {$role}");
    
    // Create welcome notification for non-admin users
    if ($role !== 'admin') {
        $welcomeMessage = "Welcome back to HopeDrops! Thank you for being part of our life-saving community.";
        createNotification($user['id'], 'Welcome Back!', $welcomeMessage, 'success');
    }
    
    // Return success response with user data
    $responseData = [
        'user_id' => $user['id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'login_time' => date('Y-m-d H:i:s')
    ];
    
    if ($role === 'hospital' && isset($user['hospital_id'])) {
        $responseData['hospital_id'] = $user['hospital_id'];
        $responseData['hospital_name'] = $user['hospital_name'];
    }
    
    sendJsonResponse(true, 'Login successful', $responseData);
    
} catch (PDOException $e) {
    error_log("Login database error: " . $e->getMessage());
    sendJsonResponse(false, 'Database error occurred. Please try again.');
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    sendJsonResponse(false, 'An error occurred. Please try again.');
}

// sendJsonResponse function is now defined in db_connect.php
?>