<?php
/**
 * HopeDrops Blood Bank Management System
 * Statistics Data Provider
 * 
 * Returns system statistics for homepage
 * Created: November 11, 2025
 */

require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    $db = getDBConnection();
    
    $stats = [];
    
    // Total active donors
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'donor' AND is_active = 1");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['total_donors'] = (int)$result['count'];
    
    // Total approved hospitals
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM hospitals WHERE is_approved = 1");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['total_hospitals'] = (int)$result['count'];
    
    // Total completed donations
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM donations WHERE status = 'completed'");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['total_donations'] = (int)$result['count'];
    
    // Total blood units available
    $stmt = $db->prepare("
        SELECT SUM(bi.units_available) as total_units 
        FROM blood_inventory bi 
        JOIN hospitals h ON bi.hospital_id = h.id 
        WHERE h.is_approved = 1
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['total_blood_units'] = (int)($result['total_units'] ?? 0);
    
    // Active campaigns
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM campaigns 
        WHERE is_active = 1 AND end_date >= CURDATE()
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['active_campaigns'] = (int)$result['count'];
    
    // This month's donations
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM donations 
        WHERE status = 'completed' 
        AND MONTH(donation_date) = MONTH(CURDATE()) 
        AND YEAR(donation_date) = YEAR(CURDATE())
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['monthly_donations'] = (int)$result['count'];
    
    // Emergency requests this week
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM blood_requests 
        WHERE urgency IN ('high', 'critical') 
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['weekly_emergency_requests'] = (int)$result['count'];
    
    // Blood type distribution
    $stmt = $db->prepare("
        SELECT 
            blood_type,
            SUM(units_available) as units_available,
            SUM(units_required) as units_required
        FROM blood_inventory bi
        JOIN hospitals h ON bi.hospital_id = h.id
        WHERE h.is_approved = 1
        GROUP BY blood_type
        ORDER BY blood_type
    ");
    $stmt->execute();
    $stats['blood_type_distribution'] = $stmt->fetchAll();
    
    // Top donor cities
    $stmt = $db->prepare("
        SELECT 
            SUBSTRING_INDEX(SUBSTRING_INDEX(address, ',', -2), ',', 1) as city,
            COUNT(*) as donor_count
        FROM users 
        WHERE role = 'donor' AND is_active = 1
        GROUP BY city
        ORDER BY donor_count DESC
        LIMIT 5
    ");
    $stmt->execute();
    $stats['top_donor_cities'] = $stmt->fetchAll();
    
    // Recent activities (last 10)
    $stmt = $db->prepare("
        SELECT 
            al.action,
            al.created_at,
            u.full_name,
            u.role
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recentActivities = $stmt->fetchAll();
    
    // Format recent activities
    foreach ($recentActivities as &$activity) {
        $activity['time_ago'] = timeAgo($activity['created_at']);
        $activity['created_at'] = formatDateTime($activity['created_at']);
    }
    $stats['recent_activities'] = $recentActivities;
    
    sendJsonResponse(true, 'Statistics retrieved successfully', $stats);
    
} catch (PDOException $e) {
    error_log("Statistics database error: " . $e->getMessage());
    sendJsonResponse(false, 'Database error occurred');
} catch (Exception $e) {
    error_log("Statistics error: " . $e->getMessage());
    sendJsonResponse(false, 'An error occurred while retrieving statistics');
}
?>