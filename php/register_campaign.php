<?php
/**
 * HopeDrops Blood Bank Management System
 * Register for Campaign API
 * 
 * Allows donors to register for blood donation campaigns
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'User not authenticated'
        ]);
        exit();
    }

    $user_id = $_SESSION['user_id'];
    
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    $campaign_id = $data['campaign_id'] ?? null;

    if (!$campaign_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Campaign ID is required'
        ]);
        exit();
    }

    $db = getDBConnection();

    // Get campaign details
    $stmt = $db->prepare("
        SELECT ha.*, h.hospital_name 
        FROM hospital_activities ha
        JOIN hospitals h ON ha.hospital_id = h.id
        WHERE ha.id = ? AND ha.activity_type = 'campaign_created'
    ");
    $stmt->execute([$campaign_id]);
    $campaign = $stmt->fetch();

    if (!$campaign) {
        echo json_encode([
            'success' => false,
            'message' => 'Campaign not found'
        ]);
        exit();
    }

    // Create campaign_registrations table if it doesn't exist
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS campaign_registrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                campaign_id INT NOT NULL,
                user_id INT NOT NULL,
                registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('registered', 'attended', 'cancelled') DEFAULT 'registered',
                notes TEXT,
                UNIQUE KEY unique_registration (campaign_id, user_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
    } catch (Exception $e) {
        error_log("Failed to create campaign_registrations table: " . $e->getMessage());
    }

    // Check if user already registered
    $checkStmt = $db->prepare("
        SELECT id FROM campaign_registrations 
        WHERE campaign_id = ? AND user_id = ?
    ");
    $checkStmt->execute([$campaign_id, $user_id]);
    
    if ($checkStmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'You are already registered for this campaign'
        ]);
        exit();
    }

    // Register user for campaign
    $insertStmt = $db->prepare("
        INSERT INTO campaign_registrations (campaign_id, user_id, status)
        VALUES (?, ?, 'registered')
    ");
    $insertStmt->execute([$campaign_id, $user_id]);

    // Log activity
    try {
        $logStmt = $db->prepare("
            INSERT INTO user_activities (user_id, activity_type, description, created_at)
            VALUES (?, 'campaign_registration', ?, NOW())
        ");
        $logStmt->execute([
            $user_id, 
            "Registered for campaign: " . ($campaign['hospital_name'] ?? 'Unknown')
        ]);
    } catch (Exception $e) {
        // Activity logging failed, but registration succeeded
        error_log("Failed to log campaign registration: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Successfully registered for the campaign',
        'registration_id' => $db->lastInsertId()
    ]);

} catch (Exception $e) {
    error_log("Campaign registration error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to register for campaign',
        'error' => $e->getMessage()
    ]);
}
?>
