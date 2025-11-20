<?php
/**
 * HopeDrops Blood Bank Management System
 * Get User Statistics API - Simplified Robust Version
 */

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
    require_once 'db_connect.php';
    
    $db = getDBConnection();
    
    if (!$db) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
        ]);
        exit();
    }

    // Initialize statistics with default values
    $stats = [
        'user_info' => [
            'username' => $_SESSION['username'] ?? 'Unknown',
            'full_name' => 'Unknown',
            'blood_type' => 'Not specified',
            'member_since' => date('Y-m-d')
        ],
        'donations' => [
            'total_donations' => 0,
            'completed_donations' => 0,
            'pending_donations' => 0,
            'last_donation_date' => null,
            'first_donation_date' => null,
            'next_donation_eligible' => null,
            'days_until_eligible' => 0
        ],
        'rewards' => [
            'total_points' => 0,
            'current_points' => 0,
            'level' => 1,
            'donations_count' => 0
        ],
        'achievements' => [
            'badges_earned' => 0,
            'recent_activities' => 0
        ]
    ];

    // Get user basic info
    try {
        $sql_user = "SELECT username, full_name, email, blood_type, created_at FROM users WHERE id = ?";
        $stmt = $db->prepare($sql_user);
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user) {
            $stats['user_info'] = [
                'username' => $user['username'],
                'full_name' => $user['full_name'] ?? 'Not specified',
                'blood_type' => $user['blood_type'] ?? 'Not specified',
                'member_since' => $user['created_at']
            ];
        }
    } catch (Exception $e) {
        // Continue with default user info
        error_log("User info query failed: " . $e->getMessage());
    }

    // Get donation statistics (if donations table exists)
    try {
        $sql_donations = "SELECT 
                            COUNT(*) as total_donations,
                            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_donations,
                            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_donations,
                            MAX(donation_date) as last_donation_date,
                            MIN(donation_date) as first_donation_date
                         FROM donations 
                         WHERE donor_id = ?";
        
        $stmt = $db->prepare($sql_donations);
        $stmt->execute([$user_id]);
        $donation_stats = $stmt->fetch();

            if ($donation_stats) {
                $stats['donations'] = [
                    'total_donations' => intval($donation_stats['total_donations'] ?? 0),
                    'completed_donations' => intval($donation_stats['completed_donations'] ?? 0),
                    'pending_donations' => intval($donation_stats['pending_donations'] ?? 0),
                    'last_donation_date' => $donation_stats['last_donation_date'],
                    'first_donation_date' => $donation_stats['first_donation_date'],
                    'next_donation_eligible' => null,
                    'days_until_eligible' => 0
                ];

            // Calculate next donation eligibility if there's a last donation
            if ($donation_stats['last_donation_date']) {
                $last_donation = new DateTime($donation_stats['last_donation_date']);
                $next_eligible = clone $last_donation;
                $next_eligible->add(new DateInterval('P56D')); // Add 56 days
                
                $now = new DateTime();
                if ($now < $next_eligible) {
                    $stats['donations']['next_donation_eligible'] = $next_eligible->format('Y-m-d');
                    $stats['donations']['days_until_eligible'] = $now->diff($next_eligible)->days;
                }
            }
        }
    } catch (Exception $e) {
        // Continue with default donation stats
        error_log("Donation stats query failed: " . $e->getMessage());
    }

    // Get reward statistics (if user_rewards table exists)
    try {
        $sql_rewards = "SELECT total_points, current_points, level, donations_count
                        FROM user_rewards 
                        WHERE user_id = ?";
        
        $stmt = $db->prepare($sql_rewards);
        $stmt->execute([$user_id]);
        $reward_stats = $stmt->fetch();

        if ($reward_stats) {
            $stats['rewards'] = [
                'total_points' => intval($reward_stats['total_points'] ?? 0),
                'current_points' => intval($reward_stats['current_points'] ?? 0),
                'level' => intval($reward_stats['level'] ?? 1),
                'donations_count' => intval($reward_stats['donations_count'] ?? 0)
            ];
        }
    } catch (Exception $e) {
        // Continue with default reward stats
        error_log("Reward stats query failed: " . $e->getMessage());
    }

    // Get badge count (if user_badges table exists)
    try {
        $sql_badges = "SELECT COUNT(*) as badge_count FROM user_badges WHERE user_id = ?";
        $stmt = $db->prepare($sql_badges);
        $stmt->execute([$user_id]);
        $badge_stats = $stmt->fetch();

        if ($badge_stats) {
            $stats['achievements']['badges_earned'] = intval($badge_stats['badge_count'] ?? 0);
        }
    } catch (Exception $e) {
        // Continue with default badge count
        error_log("Badge stats query failed: " . $e->getMessage());
    }

    // Get recent activity count (if activity_logs table exists)
    try {
        $sql_activity = "SELECT COUNT(*) as activity_count 
                         FROM activity_logs 
                         WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stmt = $db->prepare($sql_activity);
        $stmt->execute([$user_id]);
        $activity_stats = $stmt->fetch();

        if ($activity_stats) {
            $stats['achievements']['recent_activities'] = intval($activity_stats['activity_count'] ?? 0);
        }
    } catch (Exception $e) {
        // Continue with default activity count
        error_log("Activity stats query failed: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);

} catch (Exception $e) {
    error_log("get_user_stats.php error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading user statistics'
    ]);
}
?>