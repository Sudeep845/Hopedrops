<?php
/**
 * Get Recent Activities for Admin Dashboard
 * Returns recent system activities and user actions
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
    
    // Get limit from query parameters
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $limit = max(1, min($limit, 100)); // Ensure limit is between 1 and 100
    
    $activities = [];
    
    // Get recent user registrations
    $userActivitiesStmt = $db->prepare("
        SELECT 
            u.id,
            u.username,
            u.full_name,
            u.role,
            u.created_at,
            'user_registration' as activity_type
        FROM users u
        ORDER BY u.created_at DESC
        LIMIT ?
    ");
    $userActivitiesStmt->execute([$limit]);
    $userActivities = $userActivitiesStmt->fetchAll();
    
    foreach ($userActivities as $activity) {
        $activities[] = [
            'id' => 'user_' . $activity['id'],
            'type' => 'user_registration',
            'title' => ucfirst($activity['role']) . ' Registration',
            'description' => "New {$activity['role']} '{$activity['full_name']}' ({$activity['username']}) registered",
            'user' => $activity['username'],
            'timestamp' => $activity['created_at'],
            'icon' => $activity['role'] === 'hospital' ? 'fas fa-hospital' : 'fas fa-user-plus',
            'priority' => $activity['role'] === 'hospital' ? 'high' : 'normal'
        ];
    }
    
    // Get recent hospital approvals/updates
    $hospitalActivitiesStmt = $db->prepare("
        SELECT 
            h.id,
            h.hospital_name,
            h.is_approved,
            h.created_at,
            h.updated_at,
            u.username
        FROM hospitals h
        JOIN users u ON h.user_id = u.id
        ORDER BY h.updated_at DESC
        LIMIT ?
    ");
    $hospitalActivitiesStmt->execute([$limit]);
    $hospitalActivities = $hospitalActivitiesStmt->fetchAll();
    
    foreach ($hospitalActivities as $activity) {
        $isNew = $activity['created_at'] === $activity['updated_at'];
        $activities[] = [
            'id' => 'hospital_' . $activity['id'],
            'type' => $isNew ? 'hospital_registration' : 'hospital_update',
            'title' => $isNew ? 'Hospital Registration' : 'Hospital Update',
            'description' => $isNew 
                ? "Hospital '{$activity['hospital_name']}' submitted registration"
                : "Hospital '{$activity['hospital_name']}' updated - " . ($activity['is_approved'] ? 'Approved' : 'Pending'),
            'user' => $activity['username'],
            'timestamp' => $activity['updated_at'],
            'icon' => 'fas fa-hospital',
            'priority' => $activity['is_approved'] ? 'high' : 'normal'
        ];
    }
    
    // Check if activity_logs table exists and get activities from there
    try {
        $activityLogsStmt = $db->prepare("
            SELECT 
                al.id,
                al.action,
                al.description,
                al.created_at,
                u.username
            FROM activity_logs al
            JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT ?
        ");
        $activityLogsStmt->execute([$limit]);
        $activityLogs = $activityLogsStmt->fetchAll();
        
        foreach ($activityLogs as $activity) {
            $activities[] = [
                'id' => 'log_' . $activity['id'],
                'type' => 'system_activity',
                'title' => $activity['action'],
                'description' => $activity['description'] ?: "User performed: {$activity['action']}",
                'user' => $activity['username'],
                'timestamp' => $activity['created_at'],
                'icon' => 'fas fa-cog',
                'priority' => 'normal'
            ];
        }
    } catch (Exception $e) {
        // activity_logs table might not exist, that's okay
    }
    
    // If no activities found, add some sample data
    if (empty($activities)) {
        $activities[] = [
            'id' => 'system_start',
            'type' => 'system_info',
            'title' => 'System Initialized',
            'description' => 'HopeDrops Blood Bank Management System is running',
            'user' => 'system',
            'timestamp' => date('Y-m-d H:i:s'),
            'icon' => 'fas fa-power-off',
            'priority' => 'normal'
        ];
    }
    
    // Sort activities by timestamp (newest first)
    usort($activities, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    // Limit the final result
    $activities = array_slice($activities, 0, $limit);
    
    echo json_encode([
        'success' => true,
        'data' => $activities,
        'count' => count($activities),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Recent activities error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load recent activities',
        'data' => []
    ]);
}
?>