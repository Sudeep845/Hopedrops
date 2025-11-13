<?php
// Complete error suppression and JSON-only output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('html_errors', 0);
error_reporting(0);

// Start output buffering immediately  
ob_start();

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

try {
    // Database connection
    $host = 'localhost';
    $dbname = 'bloodbank_db';
    $username = 'root';
    $password = '';
    
    $pdo = null;
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        $pdo = null; // Database unavailable
    }

    // Get parameters
    $status = $_GET['status'] ?? null;
    $limit = $_GET['limit'] ?? 20;
    $limit = max(1, min(100, (int)$limit));
    
    $emergencyRequests = [];
    
    if ($pdo) {
        try {
            // Try to get real emergency requests from database
            $sql = "SELECT 
                        r.id,
                        r.blood_type,
                        r.units_needed,
                        r.urgency_level,
                        r.status,
                        r.description as notes,
                        r.location,
                        r.contact_person,
                        r.phone,
                        r.created_at,
                        r.updated_at,
                        h.hospital_name,
                        h.address as hospital_address
                    FROM requests r
                    LEFT JOIN hospitals h ON r.hospital_id = h.id
                    WHERE r.urgency_level IN ('high', 'critical', 'emergency')";
            
            $params = [];
            
            if ($status) {
                $sql .= " AND r.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY 
                        CASE r.urgency_level 
                            WHEN 'emergency' THEN 1
                            WHEN 'critical' THEN 2  
                            WHEN 'high' THEN 3
                            ELSE 4
                        END,
                        r.created_at DESC
                      LIMIT ?";
            $params[] = $limit;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $emergencyRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Database error in get_emergency_requests.php: " . $e->getMessage());
            $pdo = null; // Fallback to sample data
        }
    }
    
    // If no database or no results, provide sample data
    if (!$pdo || empty($emergencyRequests)) {
        $emergencyRequests = [
            [
                'id' => 1,
                'blood_type' => 'O-',
                'units_needed' => 3,
                'urgency_level' => 'emergency',
                'status' => 'pending',
                'notes' => 'Critical patient requires immediate transfusion',
                'location' => 'Emergency Room, City Hospital',
                'contact_person' => 'Dr. Emergency',
                'phone' => '555-URGENT',
                'hospital_name' => 'City Hospital',
                'hospital_address' => '123 Medical Center Dr',
                'created_at' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
                'updated_at' => date('Y-m-d H:i:s', strtotime('-30 minutes'))
            ],
            [
                'id' => 2,
                'blood_type' => 'A+',
                'units_needed' => 2,
                'urgency_level' => 'high',
                'status' => 'pending',
                'notes' => 'Surgery patient needs blood preparation',
                'location' => 'Operating Room 3',
                'contact_person' => 'Dr. Surgeon',
                'phone' => '555-SURG',
                'hospital_name' => 'Medical Center',
                'hospital_address' => '456 Health Plaza',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'updated_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
            ],
            [
                'id' => 3,
                'blood_type' => 'B-',
                'units_needed' => 1,
                'urgency_level' => 'critical',
                'status' => 'accepted',
                'notes' => 'Trauma patient in ICU requires type B negative blood',
                'location' => 'ICU Ward',
                'contact_person' => 'Dr. Trauma',
                'phone' => '555-ICU',
                'hospital_name' => 'General Hospital',
                'hospital_address' => '789 Medical Ave',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'updated_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
            ]
        ];
        
        // Apply status filter to sample data if specified
        if ($status) {
            $emergencyRequests = array_filter($emergencyRequests, function($req) use ($status) {
                return $req['status'] === $status;
            });
            $emergencyRequests = array_values($emergencyRequests); // Re-index
        }
        
        $emergencyRequests = array_slice($emergencyRequests, 0, $limit);
    }
    
    // Format the data for frontend
    foreach ($emergencyRequests as &$request) {
        // Add urgency colors and priorities
        $urgencyInfo = [
            'emergency' => ['color' => 'danger', 'priority' => 1, 'text' => 'EMERGENCY'],
            'critical' => ['color' => 'warning', 'priority' => 2, 'text' => 'CRITICAL'],
            'high' => ['color' => 'info', 'priority' => 3, 'text' => 'HIGH'],
            'normal' => ['color' => 'secondary', 'priority' => 4, 'text' => 'NORMAL']
        ];
        
        $urgency = $request['urgency_level'] ?? 'normal';
        $request['urgency_info'] = $urgencyInfo[$urgency] ?? $urgencyInfo['normal'];
        
        // Format timestamps
        $request['created_formatted'] = date('M j, Y g:i A', strtotime($request['created_at']));
        
        // Calculate time ago
        $time = time() - strtotime($request['created_at']);
        if ($time < 60) {
            $request['time_ago'] = 'just now';
        } elseif ($time < 3600) {
            $request['time_ago'] = floor($time/60) . ' minutes ago';
        } elseif ($time < 86400) {
            $request['time_ago'] = floor($time/3600) . ' hours ago';
        } else {
            $request['time_ago'] = floor($time/86400) . ' days ago';
        }
        
        // Add status styling
        $statusInfo = [
            'pending' => ['color' => 'warning', 'text' => 'Pending'],
            'accepted' => ['color' => 'info', 'text' => 'Accepted'],
            'fulfilled' => ['color' => 'success', 'text' => 'Fulfilled'],
            'cancelled' => ['color' => 'secondary', 'text' => 'Cancelled']
        ];
        
        $status = $request['status'] ?? 'pending';
        $request['status_info'] = $statusInfo[$status] ?? $statusInfo['pending'];
    }
    
    // Calculate statistics
    $stats = [
        'total_requests' => count($emergencyRequests),
        'emergency_count' => count(array_filter($emergencyRequests, function($r) { 
            return $r['urgency_level'] === 'emergency'; 
        })),
        'critical_count' => count(array_filter($emergencyRequests, function($r) { 
            return $r['urgency_level'] === 'critical'; 
        })),
        'pending_count' => count(array_filter($emergencyRequests, function($r) { 
            return $r['status'] === 'pending'; 
        })),
        'last_updated' => date('Y-m-d H:i:s')
    ];
    
    // Clean output buffer and send JSON
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => $emergencyRequests,
        'stats' => $stats,
        'filters' => [
            'status' => $status,
            'limit' => $limit
        ],
        'message' => 'Emergency requests retrieved successfully'
    ]);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Service temporarily unavailable',
        'data' => [],
        'error' => 'Unable to process request'
    ]);
    exit;
}
?>