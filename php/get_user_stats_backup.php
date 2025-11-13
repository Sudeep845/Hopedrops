<?php
/**
 * HopeDrops Blood Bank Management System
 * Get User Statistics API
 * 
 * Retrieves comprehensive statistics for the logged-in user
 * Including donation count, rewards, badges, and activity metrics
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
include_once 'db_connect.php';

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

    // Get user basic info
    $sql_user = "SELECT username, full_name, email, blood_type, created_at FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql_user);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user = $user_result->fetch_assoc();

    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit();
    }

    // Get donation statistics
    $sql_donations = "SELECT 
                        COUNT(*) as total_donations,
                        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_donations,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_donations,
                        MAX(donation_date) as last_donation_date,
                        MIN(donation_date) as first_donation_date
                     FROM donations 
                     WHERE donor_id = ?";
    
    $stmt = $conn->prepare($sql_donations);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $donation_result = $stmt->get_result();
    $donation_stats = $donation_result->fetch_assoc();

    // Get reward statistics
    $sql_rewards = "SELECT 
                      total_points,
                      current_points,
                      level,
                      donations_count
                    FROM user_rewards 
                    WHERE user_id = ?";
    
    $stmt = $conn->prepare($sql_rewards);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $reward_result = $stmt->get_result();
    $reward_stats = $reward_result->fetch_assoc();

    // Get badge count
    $sql_badges = "SELECT COUNT(*) as badge_count FROM user_badges WHERE user_id = ?";
    $stmt = $conn->prepare($sql_badges);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $badge_result = $stmt->get_result();
    $badge_stats = $badge_result->fetch_assoc();

    // Get recent activity count
    $sql_activity = "SELECT COUNT(*) as activity_count 
                     FROM activity_logs 
                     WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    
    $stmt = $conn->prepare($sql_activity);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $activity_result = $stmt->get_result();
    $activity_stats = $activity_result->fetch_assoc();

    // Calculate next donation eligibility
    $next_donation_eligible = null;
    $days_until_eligible = 0;
    
    if ($donation_stats['last_donation_date']) {
        $last_donation = new DateTime($donation_stats['last_donation_date']);
        $next_eligible = clone $last_donation;
        $next_eligible->add(new DateInterval('P56D')); // Add 56 days
        
        $now = new DateTime();
        if ($now < $next_eligible) {
            $days_until_eligible = $now->diff($next_eligible)->days;
            $next_donation_eligible = $next_eligible->format('Y-m-d');
        }
    }

    // Compile statistics
    $stats = [
        'user_info' => [
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'blood_type' => $user['blood_type'],
            'member_since' => $user['created_at']
        ],
        'donations' => [
            'total_donations' => intval($donation_stats['total_donations'] ?? 0),
            'completed_donations' => intval($donation_stats['completed_donations'] ?? 0),
            'pending_donations' => intval($donation_stats['pending_donations'] ?? 0),
            'last_donation_date' => $donation_stats['last_donation_date'],
            'first_donation_date' => $donation_stats['first_donation_date'],
            'next_donation_eligible' => $next_donation_eligible,
            'days_until_eligible' => $days_until_eligible
        ],
        'rewards' => [
            'total_points' => intval($reward_stats['total_points'] ?? 0),
            'current_points' => intval($reward_stats['current_points'] ?? 0),
            'level' => intval($reward_stats['level'] ?? 1),
            'donations_count' => intval($reward_stats['donations_count'] ?? 0)
        ],
        'achievements' => [
            'badges_earned' => intval($badge_stats['badge_count'] ?? 0),
            'recent_activities' => intval($activity_stats['activity_count'] ?? 0)
        ]
    ];

    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading user statistics: ' . $e->getMessage()
    ]);
}
?>