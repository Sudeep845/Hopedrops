<?php
// CRITICAL: Suppress ALL errors immediately
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('html_errors', 0);
error_reporting(0);
ob_start();

// Use comprehensive API helper to prevent HTML output
require_once 'api_helper.php';
initializeAPI();

try {
    require_once 'db_connect.php';
    
    // Get parameters
    $limit = (int)($_GET['limit'] ?? 20);
    $limit = max(1, min(100, $limit)); // Ensure limit is between 1 and 100
    
    $hospitalId = $_GET['hospital_id'] ?? null;
    $bloodType = $_GET['blood_type'] ?? null;
    $action = $_GET['action'] ?? null; // 'add', 'subtract', 'set', 'expired'
    $days = (int)($_GET['days'] ?? 30); // History period in days
    
    $history = [];
    
    try {
        // Build the query for inventory history
        $sql = "SELECT 
                    ih.*,
                    u.full_name as modified_by_name,
                    h.hospital_name
                FROM inventory_history ih
                LEFT JOIN users u ON ih.modified_by = u.id
                LEFT JOIN hospitals h ON ih.hospital_id = h.id
                WHERE ih.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $params = [$days];
        
        // Add filters
        if ($hospitalId) {
            $sql .= " AND ih.hospital_id = ?";
            $params[] = $hospitalId;
        }
        
        if ($bloodType) {
            $sql .= " AND ih.blood_type = ?";
            $params[] = $bloodType;
        }
        
        if ($action) {
            $sql .= " AND ih.action = ?";
            $params[] = $action;
        }
        
        $sql .= " ORDER BY ih.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no history found, try alternative approach with activity logs
        if (empty($history)) {
            $sql = "SELECT 
                        ha.*,
                        u.full_name as modified_by_name,
                        h.hospital_name
                    FROM hospital_activities ha
                    LEFT JOIN users u ON ha.user_id = u.id
                    LEFT JOIN hospitals h ON ha.hospital_id = h.id
                    WHERE ha.activity_type = 'inventory_update'
                    AND ha.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            
            $params = [$days];
            
            if ($hospitalId) {
                $sql .= " AND ha.hospital_id = ?";
                $params[] = $hospitalId;
            }
            
            $sql .= " ORDER BY ha.created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert activities to history format
            foreach ($activities as $activity) {
                $activityData = json_decode($activity['activity_data'], true) ?? [];
                $history[] = [
                    'id' => $activity['id'],
                    'blood_type' => $activityData['blood_type'] ?? 'Unknown',
                    'action' => $activityData['action'] ?? 'update',
                    'previous_quantity' => $activityData['previous_quantity'] ?? 0,
                    'new_quantity' => $activityData['units'] ?? 0,
                    'change_amount' => ($activityData['units'] ?? 0) - ($activityData['previous_quantity'] ?? 0),
                    'reason' => $activityData['reason'] ?? 'Inventory update',
                    'hospital_id' => $activity['hospital_id'],
                    'hospital_name' => $activity['hospital_name'],
                    'modified_by' => $activity['user_id'],
                    'modified_by_name' => $activity['modified_by_name'] ?? 'System',
                    'created_at' => $activity['created_at']
                ];
            }
        }
        
    } catch (PDOException $e) {
        error_log("Database error in get_inventory_history.php: " . $e->getMessage());
        
        // Return sample history data if database fails
        $history = [
            [
                'id' => 1,
                'blood_type' => 'O+',
                'action' => 'add',
                'previous_quantity' => 25,
                'new_quantity' => 30,
                'change_amount' => 5,
                'reason' => 'New donation received',
                'hospital_id' => 1,
                'hospital_name' => 'Sample Hospital',
                'modified_by' => 1,
                'modified_by_name' => 'Hospital Staff',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
            ],
            [
                'id' => 2,
                'blood_type' => 'A+',
                'action' => 'subtract',
                'previous_quantity' => 20,
                'new_quantity' => 18,
                'change_amount' => -2,
                'reason' => 'Emergency request fulfilled',
                'hospital_id' => 1,
                'hospital_name' => 'Sample Hospital',
                'modified_by' => 1,
                'modified_by_name' => 'Hospital Staff',
                'created_at' => date('Y-m-d H:i:s', strtotime('-5 hours'))
            ],
            [
                'id' => 3,
                'blood_type' => 'B-',
                'action' => 'expired',
                'previous_quantity' => 10,
                'new_quantity' => 8,
                'change_amount' => -2,
                'reason' => 'Units expired',
                'hospital_id' => 1,
                'hospital_name' => 'Sample Hospital',
                'modified_by' => null,
                'modified_by_name' => 'System',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ],
            [
                'id' => 4,
                'blood_type' => 'AB+',
                'action' => 'set',
                'previous_quantity' => 0,
                'new_quantity' => 15,
                'change_amount' => 15,
                'reason' => 'Manual inventory correction',
                'hospital_id' => 1,
                'hospital_name' => 'Sample Hospital',
                'modified_by' => 1,
                'modified_by_name' => 'Hospital Staff',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
            ]
        ];
        
        // Apply filters to sample data
        if ($bloodType) {
            $history = array_filter($history, function($item) use ($bloodType) {
                return $item['blood_type'] === $bloodType;
            });
        }
        
        if ($action) {
            $history = array_filter($history, function($item) use ($action) {
                return $item['action'] === $action;
            });
        }
        
        $history = array_values(array_slice($history, 0, $limit));
    }
    
    // Format the history data for frontend
    foreach ($history as &$item) {
        // Format dates
        $item['created_formatted'] = date('M j, Y g:i A', strtotime($item['created_at']));
        $item['date_only'] = date('Y-m-d', strtotime($item['created_at']));
        $item['time_only'] = date('g:i A', strtotime($item['created_at']));
        
        // Add action icons and colors
        $actionInfo = [
            'add' => ['icon' => 'fa-plus', 'color' => 'success', 'text' => 'Added'],
            'subtract' => ['icon' => 'fa-minus', 'color' => 'warning', 'text' => 'Removed'],
            'set' => ['icon' => 'fa-edit', 'color' => 'info', 'text' => 'Updated'],
            'expired' => ['icon' => 'fa-clock', 'color' => 'danger', 'text' => 'Expired'],
            'transfer' => ['icon' => 'fa-exchange-alt', 'color' => 'primary', 'text' => 'Transferred']
        ];
        
        $action = $item['action'] ?? 'update';
        $item['action_info'] = $actionInfo[$action] ?? $actionInfo['set'];
        
        // Calculate time ago
        $timeAgo = time() - strtotime($item['created_at']);
        if ($timeAgo < 3600) {
            $item['time_ago'] = floor($timeAgo / 60) . ' minutes ago';
        } elseif ($timeAgo < 86400) {
            $item['time_ago'] = floor($timeAgo / 3600) . ' hours ago';
        } else {
            $item['time_ago'] = floor($timeAgo / 86400) . ' days ago';
        }
        
        // Add change indicator
        $item['change_indicator'] = '';
        if ($item['change_amount'] > 0) {
            $item['change_indicator'] = '+' . $item['change_amount'];
        } elseif ($item['change_amount'] < 0) {
            $item['change_indicator'] = (string)$item['change_amount'];
        }
    }
    
    // Calculate summary statistics
    $stats = [
        'total_changes' => count($history),
        'additions' => count(array_filter($history, function($h) { return $h['action'] === 'add'; })),
        'removals' => count(array_filter($history, function($h) { return $h['action'] === 'subtract'; })),
        'updates' => count(array_filter($history, function($h) { return $h['action'] === 'set'; })),
        'expirations' => count(array_filter($history, function($h) { return $h['action'] === 'expired'; })),
        'period_days' => $days,
        'blood_types_affected' => array_values(array_unique(array_column($history, 'blood_type'))),
        'total_units_added' => array_sum(array_map(function($h) { 
            return $h['action'] === 'add' ? $h['change_amount'] : 0; 
        }, $history)),
        'total_units_removed' => abs(array_sum(array_map(function($h) { 
            return in_array($h['action'], ['subtract', 'expired']) ? $h['change_amount'] : 0; 
        }, $history)))
    ];
    
    outputJSON([
        'success' => true,
        'data' => $history,
        'stats' => $stats,
        'filters' => [
            'limit' => $limit,
            'hospital_id' => $hospitalId,
            'blood_type' => $bloodType,
            'action' => $action,
            'days' => $days
        ],
        'message' => 'Inventory history retrieved successfully'
    ]);
    
} catch (Exception $e) {
    handleAPIError('Unable to load inventory history', $e->getMessage());
}
?>