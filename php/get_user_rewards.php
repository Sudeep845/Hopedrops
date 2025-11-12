<?php
/**
 * HopeDrops Blood Bank Management System
 * Get User Rewards API
 * 
 * Retrieves user's rewards, points, badges, and available items
 * Created: November 12, 2025
 */

require_once 'db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    sendJsonResponse(false, 'Authentication required');
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    $db = getDBConnection();
    
    // Get user's current points and level
    $userQuery = "
        SELECT 
            u.id,
            u.username,
            u.full_name,
            COALESCE(ur.total_points, 0) as total_points,
            COALESCE(ur.current_points, 0) as current_points,
            COALESCE(ur.level, 1) as level,
            COALESCE(ur.donations_count, 0) as donations_count
        FROM users u
        LEFT JOIN user_rewards ur ON u.id = ur.user_id
        WHERE u.id = ?
    ";
    
    $stmt = $db->prepare($userQuery);
    $stmt->execute([$userId]);
    $userRewards = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userRewards) {
        // Initialize rewards for new user
        $initQuery = "
            INSERT INTO user_rewards (user_id, total_points, current_points, level, donations_count)
            VALUES (?, 0, 0, 1, 0)
            ON DUPLICATE KEY UPDATE user_id = user_id
        ";
        $stmt = $db->prepare($initQuery);
        $stmt->execute([$userId]);
        
        $userRewards = [
            'id' => $userId,
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'total_points' => 0,
            'current_points' => 0,
            'level' => 1,
            'donations_count' => 0
        ];
    }
    
    // Get user's badges
    $badgesQuery = "
        SELECT 
            b.id,
            b.name,
            b.description,
            b.icon,
            b.category,
            ub.earned_date
        FROM user_badges ub
        JOIN badges b ON ub.badge_id = b.id
        WHERE ub.user_id = ?
        ORDER BY ub.earned_date DESC
    ";
    
    $stmt = $db->prepare($badgesQuery);
    $stmt->execute([$userId]);
    $userBadges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available shop items
    $shopQuery = "
        SELECT 
            id,
            name,
            description,
            points_cost,
            category,
            image_url,
            stock_quantity,
            is_active
        FROM reward_items
        WHERE is_active = 1 AND stock_quantity > 0
        ORDER BY category, points_cost ASC
    ";
    
    $stmt = $db->prepare($shopQuery);
    $stmt->execute();
    $shopItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available badges to earn
    $availableBadgesQuery = "
        SELECT 
            b.id,
            b.name,
            b.description,
            b.icon,
            b.category,
            b.requirements,
            CASE 
                WHEN ub.badge_id IS NOT NULL THEN 1
                ELSE 0
            END as is_earned
        FROM badges b
        LEFT JOIN user_badges ub ON b.id = ub.badge_id AND ub.user_id = ?
        WHERE b.is_active = 1
        ORDER BY is_earned DESC, b.category, b.name
    ";
    
    $stmt = $db->prepare($availableBadgesQuery);
    $stmt->execute([$userId]);
    $allBadges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent achievements
    $achievementsQuery = "
        SELECT 
            'badge' as type,
            b.name as title,
            b.description,
            ub.earned_date as date_achieved
        FROM user_badges ub
        JOIN badges b ON ub.badge_id = b.id
        WHERE ub.user_id = ? AND ub.earned_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        
        UNION ALL
        
        SELECT 
            'milestone' as type,
            CONCAT('Level ', ur.level, ' Reached') as title,
            'Congratulations on reaching a new level!' as description,
            ur.last_updated as date_achieved
        FROM user_rewards ur
        WHERE ur.user_id = ? AND ur.last_updated >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        
        ORDER BY date_achieved DESC
        LIMIT 10
    ";
    
    $stmt = $db->prepare($achievementsQuery);
    $stmt->execute([$userId, $userId]);
    $recentAchievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get leaderboard (top 10 users)
    $leaderboardQuery = "
        SELECT 
            u.username,
            u.full_name,
            ur.total_points,
            ur.level,
            ur.donations_count,
            RANK() OVER (ORDER BY ur.total_points DESC) as rank_position
        FROM user_rewards ur
        JOIN users u ON ur.user_id = u.id
        WHERE u.is_active = 1
        ORDER BY ur.total_points DESC
        LIMIT 10
    ";
    
    $stmt = $db->prepare($leaderboardQuery);
    $stmt->execute();
    $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate progress to next level
    $pointsForNextLevel = ($userRewards['level'] * 100); // 100 points per level
    $progressToNextLevel = min(100, ($userRewards['current_points'] / $pointsForNextLevel) * 100);
    
    $responseData = [
        'user_rewards' => [
            'total_points' => intval($userRewards['total_points']),
            'current_points' => intval($userRewards['current_points']),
            'level' => intval($userRewards['level']),
            'donations_count' => intval($userRewards['donations_count']),
            'progress_to_next_level' => round($progressToNextLevel, 1),
            'points_for_next_level' => $pointsForNextLevel
        ],
        'badges' => $userBadges,
        'all_badges' => $allBadges,
        'shop_items' => $shopItems,
        'recent_achievements' => $recentAchievements,
        'leaderboard' => $leaderboard
    ];
    
    sendJsonResponse(true, 'Rewards data retrieved successfully', $responseData);
    
} catch (Exception $e) {
    error_log("Get user rewards error: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to retrieve rewards data: ' . $e->getMessage());
}
?>