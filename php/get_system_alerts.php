<?php
/**
 * Get System Alerts for Admin Dashboard
 * Returns important system notifications and alerts
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
    
    $alerts = [];
    
    // Check for pending hospital approvals
    $pendingHospitalsStmt = $db->query("
        SELECT COUNT(*) as count 
        FROM hospitals 
        WHERE is_approved = 0
    ");
    $pendingCount = $pendingHospitalsStmt->fetchColumn();
    
    if ($pendingCount > 0) {
        $alerts[] = [
            'id' => 'pending_hospitals',
            'type' => 'warning',
            'title' => 'Pending Hospital Approvals',
            'message' => "{$pendingCount} hospital" . ($pendingCount > 1 ? 's' : '') . " waiting for approval",
            'action_url' => 'manage_hospitals.html?filter=pending',
            'priority' => 'high',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    // Check for low blood inventory
    $lowInventoryStmt = $db->query("
        SELECT 
            h.hospital_name,
            bi.blood_type,
            bi.units_available,
            bi.units_required
        FROM blood_inventory bi
        JOIN hospitals h ON bi.hospital_id = h.id
        WHERE bi.units_available < bi.units_required 
        AND bi.units_required > 0
        AND h.is_approved = 1
        ORDER BY (bi.units_required - bi.units_available) DESC
        LIMIT 5
    ");
    $lowInventory = $lowInventoryStmt->fetchAll();
    
    if ($lowInventory) {
        foreach ($lowInventory as $item) {
            $shortage = $item['units_required'] - $item['units_available'];
            $alerts[] = [
                'id' => 'low_blood_' . $item['blood_type'] . '_' . preg_replace('/[^a-zA-Z0-9]/', '', $item['hospital_name']),
                'type' => 'error',
                'title' => 'Blood Shortage Alert',
                'message' => "{$item['hospital_name']} needs {$shortage} units of {$item['blood_type']} blood",
                'action_url' => 'view_campaigns.html',
                'priority' => 'critical',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    // Check for recent registrations (last 24 hours)
    $recentRegistrationsStmt = $db->query("
        SELECT COUNT(*) as count
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $recentCount = $recentRegistrationsStmt->fetchColumn();
    
    if ($recentCount > 5) {
        $alerts[] = [
            'id' => 'high_registrations',
            'type' => 'info',
            'title' => 'High Registration Activity',
            'message' => "{$recentCount} new users registered in the last 24 hours",
            'action_url' => 'manage_hospitals.html',
            'priority' => 'low',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    // System maintenance reminders
    $alerts[] = [
        'id' => 'system_maintenance',
        'type' => 'info',
        'title' => 'System Status',
        'message' => 'All systems operational. Last updated: ' . date('Y-m-d H:i:s'),
        'action_url' => null,
        'priority' => 'low',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Sort alerts by priority (critical > high > warning > info > low)
    $priorityOrder = ['critical' => 5, 'high' => 4, 'warning' => 3, 'info' => 2, 'low' => 1];
    usort($alerts, function($a, $b) use ($priorityOrder) {
        return $priorityOrder[$b['priority']] - $priorityOrder[$a['priority']];
    });
    
    echo json_encode([
        'success' => true,
        'data' => $alerts,
        'count' => count($alerts),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("System alerts error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load system alerts',
        'data' => []
    ]);
}
?>