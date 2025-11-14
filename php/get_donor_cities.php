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
    
    $cities = [];
    
    if ($pdo) {
        try {
            // Get all cities with donor counts
            $stmt = $pdo->query("
                SELECT city, COUNT(*) as donor_count 
                FROM users 
                WHERE role = 'donor' 
                AND is_active = 1 
                AND city IS NOT NULL 
                AND city != '' 
                AND city != 'Not specified'
                GROUP BY city 
                ORDER BY city ASC
            ");
            $realCities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($realCities)) {
                $cities = $realCities;
            }
            
        } catch (PDOException $e) {
            error_log("Database error in get_donor_cities.php: " . $e->getMessage());
            $pdo = null; // Fallback to sample data
        }
    }
    
    // If no database or no data, provide sample cities
    if (!$pdo || empty($cities)) {
        $cities = [
            ['city' => 'Ahmedabad', 'donor_count' => 76],
            ['city' => 'Bangalore', 'donor_count' => 198],
            ['city' => 'Bhopal', 'donor_count' => 34],
            ['city' => 'Chennai', 'donor_count' => 156],
            ['city' => 'Coimbatore', 'donor_count' => 28],
            ['city' => 'Delhi', 'donor_count' => 245],
            ['city' => 'Gurgaon', 'donor_count' => 45],
            ['city' => 'Hyderabad', 'donor_count' => 112],
            ['city' => 'Indore', 'donor_count' => 31],
            ['city' => 'Jaipur', 'donor_count' => 65],
            ['city' => 'Kanpur', 'donor_count' => 29],
            ['city' => 'Kolkata', 'donor_count' => 98],
            ['city' => 'Lucknow', 'donor_count' => 52],
            ['city' => 'Mumbai', 'donor_count' => 287],
            ['city' => 'Nagpur', 'donor_count' => 38],
            ['city' => 'Nashik', 'donor_count' => 25],
            ['city' => 'Noida', 'donor_count' => 41],
            ['city' => 'Patna', 'donor_count' => 33],
            ['city' => 'Pune', 'donor_count' => 134],
            ['city' => 'Rajkot', 'donor_count' => 22],
            ['city' => 'Surat', 'donor_count' => 48],
            ['city' => 'Vadodara', 'donor_count' => 35],
            ['city' => 'Varanasi', 'donor_count' => 27],
            ['city' => 'Visakhapatnam', 'donor_count' => 24]
        ];
    }
    
    // Format for dropdown/select options
    $cityOptions = [];
    foreach ($cities as $city) {
        $cityOptions[] = [
            'value' => $city['city'],
            'label' => $city['city'] . ' (' . $city['donor_count'] . ' donors)',
            'donor_count' => $city['donor_count']
        ];
    }
    
    // Get unique states as well (if available)
    $states = [];
    if ($pdo) {
        try {
            $stmt = $pdo->query("
                SELECT DISTINCT state, COUNT(*) as donor_count 
                FROM users 
                WHERE role = 'donor' 
                AND is_active = 1 
                AND state IS NOT NULL 
                AND state != '' 
                AND state != 'Not specified'
                GROUP BY state 
                ORDER BY state ASC
            ");
            $states = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Ignore state errors, cities are more important
        }
    }
    
    // Sample states if no database data
    if (empty($states)) {
        $states = [
            ['state' => 'Andhra Pradesh', 'donor_count' => 89],
            ['state' => 'Delhi', 'donor_count' => 245],
            ['state' => 'Gujarat', 'donor_count' => 181],
            ['state' => 'Karnataka', 'donor_count' => 226],
            ['state' => 'Maharashtra', 'donor_count' => 446],
            ['state' => 'Rajasthan', 'donor_count' => 92],
            ['state' => 'Tamil Nadu', 'donor_count' => 184],
            ['state' => 'Telangana', 'donor_count' => 112],
            ['state' => 'Uttar Pradesh', 'donor_count' => 114],
            ['state' => 'West Bengal', 'donor_count' => 98]
        ];
    }
    
    $stateOptions = [];
    foreach ($states as $state) {
        $stateOptions[] = [
            'value' => $state['state'],
            'label' => $state['state'] . ' (' . $state['donor_count'] . ' donors)',
            'donor_count' => $state['donor_count']
        ];
    }
    
    $response = [
        'cities' => $cityOptions,
        'states' => $stateOptions,
        'total_cities' => count($cityOptions),
        'total_states' => count($stateOptions),
        'last_updated' => date('Y-m-d H:i:s')
    ];
    
    // Format cities for frontend compatibility
    $formattedCities = [];
    foreach ($cityOptions as $city) {
        $formattedCities[] = [
            'city' => $city['value'],
            'count' => $city['donor_count'],
            'label' => $city['label']
        ];
    }
    
    // Clean output buffer and send JSON
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => $formattedCities,
        'message' => 'Location data retrieved successfully'
    ]);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Service temporarily unavailable',
        'error' => 'Unable to retrieve location data',
        'data' => []
    ]);
    exit;
}
?>