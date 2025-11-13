<?php
/**
 * HopeDrops Blood Bank Management System
 * Get User Activities API
 * 
 * Retrieves recent activities/actions performed by the user
 * Includes donations, rewards earned, badge achievements, etc.
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
    
    // Get limit parameter (default 10)
    $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 50) : 10;

    // Check database connection
    if (!$conn) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
        ]);
        exit();
    }

    // Get user activities from multiple sources
    $activities = [];

    // 1. Get donation activities (with error handling)
    try {
        $sql_donations = "SELECT 
                            'donation' as activity_type,
                            'Donated blood' as activity_title,
                            CONCAT('Donated ', d.blood_type, ' blood at hospital') as description,
                            d.donation_date as activity_date,
                            d.status
                          FROM donations d
                          WHERE d.donor_id = ?
                          ORDER BY d.donation_date DESC
                          LIMIT ?";
        
        $stmt = $conn->prepare($sql_donations);
        if ($stmt) {
            $stmt->bind_param("ii", $user_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $activities[] = $row;
            }
        }
    } catch (Exception $e) {
        // Continue if donations table query fails
        error_log("Error querying donations: " . $e->getMessage());
    }

    // 2. Get activity logs (with error handling)
    try {
        $sql_logs = "SELECT 
                        'activity' as activity_type,
                        action as activity_title,
                        description,
                        created_at as activity_date,
                        'completed' as status
                     FROM activity_logs 
                     WHERE user_id = ?
                     ORDER BY created_at DESC
                     LIMIT ?";
        
        $stmt = $conn->prepare($sql_logs);
        if ($stmt) {
            $stmt->bind_param("ii", $user_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $activities[] = $row;
            }
        }
    } catch (Exception $e) {
        // Continue if activity_logs table query fails
        error_log("Error querying activity_logs: " . $e->getMessage());
    }

    // 3. Get reward redemptions (with error handling)
    try {
        $sql_rewards = "SELECT 
                            'reward' as activity_type,
                            'Redeemed reward' as activity_title,
                            CONCAT('Redeemed reward for ', rr.points_used, ' points') as description,
                            rr.redeemed_at as activity_date,
                            'completed' as status
                        FROM reward_redemptions rr
                        WHERE rr.user_id = ?
                        ORDER BY rr.redeemed_at DESC
                        LIMIT ?";
        
        $stmt = $conn->prepare($sql_rewards);
        if ($stmt) {
            $stmt->bind_param("ii", $user_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $activities[] = $row;
            }
        }
    } catch (Exception $e) {
        // Continue if reward_redemptions table query fails
        error_log("Error querying reward_redemptions: " . $e->getMessage());
    }

    // 4. Get badge achievements (with error handling)
    try {
        $sql_badges = "SELECT 
                           'badge' as activity_type,
                           'Earned badge' as activity_title,
                           'Earned a new achievement badge' as description,
                           ub.earned_at as activity_date,
                           'completed' as status
                       FROM user_badges ub
                       WHERE ub.user_id = ?
                       ORDER BY ub.earned_at DESC
                       LIMIT ?";
        
        $stmt = $conn->prepare($sql_badges);
        if ($stmt) {
            $stmt->bind_param("ii", $user_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $activities[] = $row;
            }
        }
    } catch (Exception $e) {
        // Continue if user_badges table query fails
        error_log("Error querying user_badges: " . $e->getMessage());
    }

    // Sort all activities by date (most recent first)
    usort($activities, function($a, $b) {
        return strtotime($b['activity_date']) - strtotime($a['activity_date']);
    });

    // Limit to requested number of activities
    $activities = array_slice($activities, 0, $limit);

    // Format dates and add icons
    foreach ($activities as &$activity) {
        // Add appropriate icon based on activity type
        switch ($activity['activity_type']) {
            case 'donation':
                $activity['icon'] = '🩸';
                $activity['color'] = 'text-danger';
                break;
            case 'reward':
                $activity['icon'] = '🎁';
                $activity['color'] = 'text-success';
                break;
            case 'badge':
                $activity['icon'] = '🏆';
                $activity['color'] = 'text-warning';
                break;
            default:
                $activity['icon'] = '📝';
                $activity['color'] = 'text-info';
        }
        
        // Format date for display
        $activity['formatted_date'] = date('M j, Y g:i A', strtotime($activity['activity_date']));
        $activity['relative_time'] = getRelativeTime($activity['activity_date']);
    }

    // If no activities found, add a welcome message
    if (empty($activities)) {
        $activities[] = [
            'activity_type' => 'welcome',
            'activity_title' => 'Welcome to HopeDrops!',
            'description' => 'Start your blood donation journey to see your activities here',
            'activity_date' => date('Y-m-d H:i:s'),
            'status' => 'info',
            'icon' => '👋',
            'color' => 'text-info',
            'formatted_date' => 'Just now',
            'relative_time' => 'Welcome!'
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $activities,
        'total_count' => count($activities)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading user activities: ' . $e->getMessage()
    ]);
}

/**
 * Helper function to get relative time (e.g., "2 hours ago")
 */
function getRelativeTime($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31104000) return floor($time/2592000) . ' months ago';
    
    return floor($time/31104000) . ' years ago';
}
?>