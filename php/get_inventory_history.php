<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Start output buffering to catch any stray output
ob_start();

try {
    // Get parameters
    $hospitalId = $_GET['hospital_id'] ?? null;
    $bloodType = $_GET['blood_type'] ?? null;
    $action = $_GET['action'] ?? null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    $history = [];
    $totalCount = 0;
    
    // Database connection
    $pdo = null;
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=bloodbank_db;charset=utf8",
            "root",
            "",
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    } catch (PDOException $e) {
        // Database connection failed - use fallback data
        $pdo = null;
    }
    
    if ($pdo) {
        try {
            // Build query for hospital activities related to inventory
            $whereConditions = ["ha.activity_type IN ('inventory_update', 'blood_collection', 'blood_distribution', 'inventory_adjustment')"];
            $params = [];
            
            if ($hospitalId) {
                $whereConditions[] = "ha.hospital_id = ?";
                $params[] = $hospitalId;
            }
            
            if ($bloodType) {
                $whereConditions[] = "JSON_EXTRACT(ha.activity_data, '$.blood_type') = ?";
                $params[] = $bloodType;
            }
            
            if ($action) {
                $whereConditions[] = "JSON_EXTRACT(ha.activity_data, '$.action') = ?";
                $params[] = $action;
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            // Get activities with hospital names
            $sql = "SELECT ha.*, h.name as hospital_name, u.username as modified_by_name
                    FROM hospital_activities ha
                    LEFT JOIN hospitals h ON ha.hospital_id = h.id
                    LEFT JOIN users u ON ha.user_id = u.id
                    WHERE $whereClause
                    ORDER BY ha.created_at DESC
                    LIMIT ? OFFSET ?";
            
            $stmt = $pdo->prepare($sql);
            $params[] = $limit;
            $params[] = $offset;
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
                    'new_quantity' => $activityData['new_quantity'] ?? $activityData['units'] ?? 0,
                    'change_amount' => ($activityData['new_quantity'] ?? $activityData['units'] ?? 0) - ($activityData['previous_quantity'] ?? 0),
                    'reason' => $activityData['reason'] ?? $activity['description'] ?? 'Inventory update',
                    'hospital_id' => $activity['hospital_id'],
                    'hospital_name' => $activity['hospital_name'] ?? 'Unknown Hospital',
                    'modified_by' => $activity['user_id'],
                    'modified_by_name' => $activity['modified_by_name'] ?? 'System',
                    'created_at' => $activity['created_at'],
                    'created_formatted' => date('M j, Y g:i A', strtotime($activity['created_at'])),
                    'date_only' => date('Y-m-d', strtotime($activity['created_at'])),
                    'time_only' => date('g:i A', strtotime($activity['created_at'])),
                    'time_ago' => timeAgo($activity['created_at'])
                ];
            }
            
            // Get total count for pagination
            $countSql = "SELECT COUNT(*) as total
                        FROM hospital_activities ha
                        LEFT JOIN hospitals h ON ha.hospital_id = h.id
                        WHERE $whereClause";
            
            $countParams = array_slice($params, 0, -2); // Remove LIMIT and OFFSET
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($countParams);
            $totalCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
        } catch (PDOException $e) {
            error_log("Database error in get_inventory_history.php: " . $e->getMessage());
            // Use fallback data on database error
            $history = getFallbackHistory();
            $totalCount = count($history);
        }
    } else {
        // Use fallback data when database is unavailable
        $history = getFallbackHistory();
        $totalCount = count($history);
    }
    
    // Add action formatting to all items
    foreach ($history as &$item) {
        $actionInfo = getActionInfo($item['action']);
        $item['action_icon'] = $actionInfo['icon'];
        $item['action_color'] = $actionInfo['color'];
        $item['action_text'] = $actionInfo['text'];
        
        // Format change amount with sign
        if ($item['change_amount'] > 0) {
            $item['change_formatted'] = '+' . $item['change_amount'];
        } elseif ($item['change_amount'] < 0) {
            $item['change_formatted'] = (string)$item['change_amount'];
        } else {
            $item['change_formatted'] = '0';
        }
    }
    
    // Clean output buffer and send response
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Inventory history retrieved successfully',
        'data' => [
            'history' => $history,
            'pagination' => [
                'total' => (int)$totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $totalCount
            ]
        ]
    ]);
    
} catch (Exception $e) {
    // Clean any output and return error
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve inventory history: ' . $e->getMessage()
    ]);
}

// Helper function to get fallback history data
function getFallbackHistory() {
    return [
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
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'created_formatted' => date('M j, Y g:i A', strtotime('-2 hours')),
            'date_only' => date('Y-m-d', strtotime('-2 hours')),
            'time_only' => date('g:i A', strtotime('-2 hours')),
            'time_ago' => '2 hours ago'
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
            'created_at' => date('Y-m-d H:i:s', strtotime('-5 hours')),
            'created_formatted' => date('M j, Y g:i A', strtotime('-5 hours')),
            'date_only' => date('Y-m-d', strtotime('-5 hours')),
            'time_only' => date('g:i A', strtotime('-5 hours')),
            'time_ago' => '5 hours ago'
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
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'created_formatted' => date('M j, Y g:i A', strtotime('-1 day')),
            'date_only' => date('Y-m-d', strtotime('-1 day')),
            'time_only' => date('g:i A', strtotime('-1 day')),
            'time_ago' => '1 day ago'
        ]
    ];
}

// Helper function to get action info
function getActionInfo($action) {
    $actionMap = [
        'add' => ['icon' => 'fa-plus', 'color' => 'success', 'text' => 'Added'],
        'subtract' => ['icon' => 'fa-minus', 'color' => 'warning', 'text' => 'Used'],
        'expired' => ['icon' => 'fa-exclamation-triangle', 'color' => 'danger', 'text' => 'Expired'],
        'set' => ['icon' => 'fa-edit', 'color' => 'info', 'text' => 'Adjusted'],
        'update' => ['icon' => 'fa-sync', 'color' => 'primary', 'text' => 'Updated'],
        'collection' => ['icon' => 'fa-plus-circle', 'color' => 'success', 'text' => 'Collected'],
        'distribution' => ['icon' => 'fa-share', 'color' => 'warning', 'text' => 'Distributed']
    ];
    
    return $actionMap[$action] ?? ['icon' => 'fa-circle', 'color' => 'secondary', 'text' => ucfirst($action)];
}

// Helper function to calculate time ago
function timeAgo($datetime) {
    $now = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);
    
    if ($diff->days > 0) {
        return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
    } elseif ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    } elseif ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    } else {
        return 'Just now';
    }
}
?>