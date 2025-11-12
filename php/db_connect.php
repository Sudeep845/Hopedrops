<?php
/**
 * HopeDrops Blood Bank Management System
 * Database Connection Configuration
 * 
 * This file handles the MySQL database connection for XAMPP localhost
 * Created: November 11, 2025
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration for XAMPP localhost
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', ''); // Default XAMPP MySQL password is empty
define('DB_NAME', 'bloodbank_db');
define('DB_CHARSET', 'utf8mb4');

// Application configuration
define('APP_NAME', 'HopeDrops');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/HopeDrops/');

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('PASSWORD_HASH_COST', 12);

class DatabaseConnection {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->connection = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
            
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("Connection failed. Please check your database configuration.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Prevent cloning of the instance
    private function __clone() {}
    
    // Prevent unserialization of the instance
    public function __wakeup() {}
}

// Get database connection instance
function getDBConnection() {
    return DatabaseConnection::getInstance()->getConnection();
}

// Utility functions
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/\D/', '', $phone);
    // Check if it's 10 digits
    return strlen($phone) === 10 && is_numeric($phone);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_ARGON2ID, ['cost' => PASSWORD_HASH_COST]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']) && isset($_SESSION['role']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.html');
        exit();
    }
}

function checkSessionTimeout() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

function hasRole($requiredRole) {
    return isLoggedIn() && $_SESSION['role'] === $requiredRole;
}

function requireRole($requiredRole) {
    if (!hasRole($requiredRole)) {
        http_response_code(403);
        die('Access denied. Insufficient permissions.');
    }
}

function redirectToRoleDashboard($role) {
    switch ($role) {
        case 'admin':
            header('Location: ' . BASE_URL . 'admin/dashboard.html');
            break;
        case 'hospital':
            header('Location: ' . BASE_URL . 'hospital/dashboard.html');
            break;
        case 'donor':
            header('Location: ' . BASE_URL . 'user/dashboard.html');
            break;
        default:
            header('Location: ' . BASE_URL . 'index.html');
    }
    exit();
}

function sendJsonResponse($success, $message, $data = null, $field = null, $errors = null) {
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    if ($field !== null) {
        $response['field'] = $field;
    }
    
    if ($errors !== null) {
        $response['errors'] = $errors;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

function logActivity($userId, $action, $details = null) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->execute([$userId, $action, $details, $ipAddress, $userAgent]);
    } catch (PDOException $e) {
        error_log("Activity logging error: " . $e->getMessage());
    }
}

function createNotification($userId, $title, $message, $type = 'info', $relatedDonationId = null, $relatedCampaignId = null) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, related_donation_id, related_campaign_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$userId, $title, $message, $type, $relatedDonationId, $relatedCampaignId]);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Notification creation error: " . $e->getMessage());
        return false;
    }
}

function getUserNotifications($userId, $limit = 10) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get notifications error: " . $e->getMessage());
        return [];
    }
}

function markNotificationAsRead($notificationId, $userId) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE id = ? AND user_id = ?
        ");
        
        $stmt->execute([$notificationId, $userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Mark notification read error: " . $e->getMessage());
        return false;
    }
}

function getBloodTypeCompatibility($bloodType) {
    $compatibility = [
        'A+' => ['A+', 'A-', 'O+', 'O-'],
        'A-' => ['A-', 'O-'],
        'B+' => ['B+', 'B-', 'O+', 'O-'],
        'B-' => ['B-', 'O-'],
        'AB+' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
        'AB-' => ['A-', 'B-', 'AB-', 'O-'],
        'O+' => ['O+', 'O-'],
        'O-' => ['O-']
    ];
    
    return $compatibility[$bloodType] ?? [];
}

function calculateRewardPoints($donationType = 'whole_blood', $isFirstTime = false, $isEmergency = false) {
    $basePoints = [
        'whole_blood' => 100,
        'platelets' => 150,
        'plasma' => 120,
        'red_cells' => 130
    ];
    
    $points = $basePoints[$donationType] ?? 100;
    
    if ($isFirstTime) {
        $points += 50; // Bonus for first-time donors
    }
    
    if ($isEmergency) {
        $points += 25; // Bonus for emergency donations
    }
    
    return $points;
}

function formatBloodType($bloodType) {
    return strtoupper($bloodType);
}

function formatDate($date, $format = 'Y-m-d') {
    if ($date instanceof DateTime) {
        return $date->format($format);
    }
    
    try {
        return (new DateTime($date))->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

function formatDateTime($datetime, $format = 'Y-m-d H:i:s') {
    if ($datetime instanceof DateTime) {
        return $datetime->format($format);
    }
    
    try {
        return (new DateTime($datetime))->format($format);
    } catch (Exception $e) {
        return $datetime;
    }
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    
    return floor($time/31536000) . ' years ago';
}

// Error handling
function handleDatabaseError($e, $customMessage = 'Database operation failed') {
    error_log("Database Error: " . $e->getMessage());
    
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        sendJsonResponse(false, $customMessage . ': ' . $e->getMessage());
    } else {
        sendJsonResponse(false, $customMessage);
    }
}

// CSRF protection
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken();
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Rate limiting (simple implementation)
function checkRateLimit($action, $limit = 5, $window = 300) {
    $key = $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    if (!isset($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }
    
    $now = time();
    
    if (!isset($_SESSION['rate_limits'][$key])) {
        $_SESSION['rate_limits'][$key] = ['count' => 1, 'reset_time' => $now + $window];
        return true;
    }
    
    $rateLimit = $_SESSION['rate_limits'][$key];
    
    if ($now > $rateLimit['reset_time']) {
        $_SESSION['rate_limits'][$key] = ['count' => 1, 'reset_time' => $now + $window];
        return true;
    }
    
    if ($rateLimit['count'] >= $limit) {
        return false;
    }
    
    $_SESSION['rate_limits'][$key]['count']++;
    return true;
}

// Initialize error reporting based on environment
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Test database connection on first load
try {
    $testConnection = getDBConnection();
    // Optionally log successful connection
    // error_log("Database connection successful");
} catch (Exception $e) {
    error_log("Database connection test failed: " . $e->getMessage());
}

?>