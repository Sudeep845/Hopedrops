<?php
/**
 * HopeDrops Blood Bank Management System
 * Get Next Donation Date API - Robust Version
 * 
 * Calculates the next eligible donation date for a donor
 * Donors must wait 56 days (8 weeks) between blood donations
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors in response
ini_set('log_errors', 1); // Log errors instead

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

    // Include database connection
    include_once 'db_connect.php';
    
    if (!$conn) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
        ]);
        exit();
    }

    // Check if donations table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'donations'");
    if ($table_check->num_rows === 0) {
        // No donations table - user is eligible
        echo json_encode([
            'success' => true,
            'data' => [
                'last_donation_date' => null,
                'next_eligible_date' => date('Y-m-d'),
                'next_eligible_timestamp' => time(),
                'is_currently_eligible' => true,
                'days_until_eligible' => 0,
                'message' => 'No donation history available'
            ]
        ]);
        exit();
    }

    // Get the last donation date for this user
    $sql = "SELECT MAX(donation_date) as last_donation_date 
            FROM donations 
            WHERE donor_id = ? AND status = 'completed'";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Database query preparation failed: ' . $conn->error
        ]);
        exit();
    }
    
    $stmt->bind_param("i", $user_id);
    
    if (!$stmt->execute()) {
        echo json_encode([
            'success' => false,
            'message' => 'Database query execution failed: ' . $stmt->error
        ]);
        exit();
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row && $row['last_donation_date']) {
        // Calculate next eligible date (56 days from last donation)
        $last_donation = new DateTime($row['last_donation_date']);
        $next_eligible = clone $last_donation;
        $next_eligible->add(new DateInterval('P56D')); // Add 56 days
        
        $now = new DateTime();
        $is_eligible = $now >= $next_eligible;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'last_donation_date' => $row['last_donation_date'],
                'next_eligible_date' => $next_eligible->format('Y-m-d'),
                'next_eligible_timestamp' => $next_eligible->getTimestamp(),
                'is_currently_eligible' => $is_eligible,
                'days_until_eligible' => $is_eligible ? 0 : $now->diff($next_eligible)->days
            ]
        ]);
    } else {
        // No previous donations - user is eligible to donate
        echo json_encode([
            'success' => true,
            'data' => [
                'last_donation_date' => null,
                'next_eligible_date' => date('Y-m-d'),
                'next_eligible_timestamp' => time(),
                'is_currently_eligible' => true,
                'days_until_eligible' => 0
            ]
        ]);
    }

} catch (Exception $e) {
    error_log("get_next_donation_date.php error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error calculating next donation date'
    ]);
}
?>