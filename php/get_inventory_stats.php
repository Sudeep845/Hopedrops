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
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

try {
    // Only allow GET method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
        exit;
    }

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
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
        ]);
        exit;
    }
    
    // Get hospital ID (default to 1 if not provided)
    $hospitalId = (int)($_GET['hospital_id'] ?? 1);
    
    $stats = [];
    
    if ($pdo) {
        try {
            // Get total units available across all blood types
            $stmt = $pdo->prepare("
                SELECT 
                    SUM(units_available) as total_units,
                    SUM(units_required) as total_required,
                    COUNT(*) as blood_types_tracked
                FROM blood_inventory 
                WHERE hospital_id = ?
            ");
            $stmt->execute([$hospitalId]);
            $totals = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get blood type breakdown
            $stmt = $pdo->prepare("
                SELECT 
                    blood_type,
                    units_available,
                    units_required,
                    (units_available - units_required) as surplus_deficit,
                    CASE 
                        WHEN units_available = 0 THEN 'empty'
                        WHEN units_available < units_required THEN 'low'
                        WHEN units_available >= units_required * 2 THEN 'high'
                        ELSE 'normal'
                    END as status_level
                FROM blood_inventory 
                WHERE hospital_id = ?
                ORDER BY blood_type
            ");
            $stmt->execute([$hospitalId]);
            $bloodTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate status counts
            $statusCounts = [
                'empty' => 0,
                'low' => 0,
                'normal' => 0,
                'high' => 0
            ];
            
            foreach ($bloodTypes as $type) {
                $statusCounts[$type['status_level']]++;
            }
            
            // Get recent activity count (last 7 days)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as recent_updates
                FROM hospital_activities 
                WHERE hospital_id = ? 
                AND activity_type LIKE '%inventory%' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$hospitalId]);
            $recentActivity = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stats = [
                'total_units_available' => (int)($totals['total_units'] ?? 0),
                'total_units_required' => (int)($totals['total_required'] ?? 0),
                'blood_types_tracked' => (int)($totals['blood_types_tracked'] ?? 0),
                'surplus_deficit' => (int)($totals['total_units'] ?? 0) - (int)($totals['total_required'] ?? 0),
                'status_counts' => $statusCounts,
                'blood_type_details' => $bloodTypes,
                'recent_updates_count' => (int)($recentActivity['recent_updates'] ?? 0),
                'last_updated' => date('Y-m-d H:i:s')
            ];
            
        } catch (PDOException $e) {
            error_log("Database error in get_inventory_stats.php: " . $e->getMessage());
            // Provide fallback data
            $stats = [
                'total_units_available' => 0,
                'total_units_required' => 0,
                'blood_types_tracked' => 8,
                'surplus_deficit' => 0,
                'status_counts' => [
                    'empty' => 4,
                    'low' => 2,
                    'normal' => 2,
                    'high' => 0
                ],
                'blood_type_details' => [],
                'recent_updates_count' => 0,
                'last_updated' => date('Y-m-d H:i:s'),
                'note' => 'Using fallback data due to database error'
            ];
        }
    } else {
        // Fallback data when database is unavailable
        $stats = [
            'total_units_available' => 45,
            'total_units_required' => 60,
            'blood_types_tracked' => 8,
            'surplus_deficit' => -15,
            'status_counts' => [
                'empty' => 2,
                'low' => 3,
                'normal' => 2,
                'high' => 1
            ],
            'blood_type_details' => [
                ['blood_type' => 'A+', 'units_available' => 8, 'units_required' => 10, 'surplus_deficit' => -2, 'status_level' => 'low'],
                ['blood_type' => 'A-', 'units_available' => 3, 'units_required' => 5, 'surplus_deficit' => -2, 'status_level' => 'low'],
                ['blood_type' => 'B+', 'units_available' => 6, 'units_required' => 8, 'surplus_deficit' => -2, 'status_level' => 'low'],
                ['blood_type' => 'B-', 'units_available' => 2, 'units_required' => 4, 'surplus_deficit' => -2, 'status_level' => 'low'],
                ['blood_type' => 'AB+', 'units_available' => 4, 'units_required' => 6, 'surplus_deficit' => -2, 'status_level' => 'low'],
                ['blood_type' => 'AB-', 'units_available' => 0, 'units_required' => 3, 'surplus_deficit' => -3, 'status_level' => 'empty'],
                ['blood_type' => 'O+', 'units_available' => 12, 'units_required' => 15, 'surplus_deficit' => -3, 'status_level' => 'normal'],
                ['blood_type' => 'O-', 'units_available' => 10, 'units_required' => 9, 'surplus_deficit' => 1, 'status_level' => 'normal']
            ],
            'recent_updates_count' => 5,
            'last_updated' => date('Y-m-d H:i:s'),
            'note' => 'Sample data - database unavailable'
        ];
    }
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Inventory statistics retrieved successfully',
        'data' => $stats
    ]);
    
} catch (Exception $e) {
    // Clean any output and return error
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve inventory statistics: ' . $e->getMessage()
    ]);
}
?>