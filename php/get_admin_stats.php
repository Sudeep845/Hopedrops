<?php
/**
 * Get Admin Dashboard Statistics
 * Returns key metrics for the admin dashboard
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db_connect.php';

try {
    // Check if user is authenticated and is admin
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Access denied. Admin privileges required.'
        ]);
        exit;
    }
    
    $db = getDBConnection();
    
    // Get total statistics
    $stats = [];
    
    // Total users by role
    $userStatsStmt = $db->query("
        SELECT role, COUNT(*) as count 
        FROM users 
        WHERE is_active = 1 
        GROUP BY role
    ");
    $userStats = $userStatsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stats['total_users'] = array_sum($userStats);
    $stats['total_donors'] = $userStats['donor'] ?? 0;
    $stats['total_hospitals'] = $userStats['hospital'] ?? 0;
    $stats['total_admins'] = $userStats['admin'] ?? 0;
    
    // Hospital statistics
    $hospitalStatsStmt = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as pending
        FROM hospitals
    ");
    $hospitalStats = $hospitalStatsStmt->fetch();
    
    $stats['hospitals_approved'] = (int)$hospitalStats['approved'];
    $stats['hospitals_pending'] = (int)$hospitalStats['pending'];
    
    // Blood inventory statistics
    $bloodStatsStmt = $db->query("
        SELECT 
            SUM(units_available) as total_available,
            SUM(units_required) as total_required,
            COUNT(DISTINCT hospital_id) as hospitals_with_inventory
        FROM blood_inventory
    ");
    $bloodStats = $bloodStatsStmt->fetch();
    
    $stats['total_blood_units'] = (int)($bloodStats['total_available'] ?? 0);
    $stats['blood_requests'] = (int)($bloodStats['total_required'] ?? 0);
    $stats['active_blood_banks'] = (int)($bloodStats['hospitals_with_inventory'] ?? 0);
    
    // Recent registration statistics (last 30 days)
    $recentStatsStmt = $db->query("
        SELECT 
            COUNT(*) as recent_registrations,
            SUM(CASE WHEN role = 'donor' THEN 1 ELSE 0 END) as recent_donors,
            SUM(CASE WHEN role = 'hospital' THEN 1 ELSE 0 END) as recent_hospitals
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $recentStats = $recentStatsStmt->fetch();
    
    $stats['recent_registrations'] = (int)($recentStats['recent_registrations'] ?? 0);
    $stats['recent_donors'] = (int)($recentStats['recent_donors'] ?? 0);
    $stats['recent_hospitals'] = (int)($recentStats['recent_hospitals'] ?? 0);
    
    // Blood type distribution
    $bloodTypeStmt = $db->query("
        SELECT 
            blood_type,
            SUM(units_available) as total_units
        FROM blood_inventory 
        WHERE blood_type IS NOT NULL
        GROUP BY blood_type
        ORDER BY total_units DESC
    ");
    $bloodTypes = $bloodTypeStmt->fetchAll();
    
    $stats['blood_type_distribution'] = $bloodTypes;
    
    // System health indicators
    $stats['system_health'] = [
        'database_status' => 'online',
        'total_tables' => 5, // users, hospitals, blood_inventory, notifications, activity_logs
        'last_backup' => null, // Would need backup system
        'uptime' => '99.9%' // Placeholder
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Admin stats error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load dashboard statistics',
        'data' => []
    ]);
}
?>