<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

echo "Testing get_user_details API...\n";

try {
    require_once 'php/db_connect.php';
    echo "✅ db_connect.php loaded\n";
    
    $pdo = getDBConnection();
    echo "✅ Database connection established\n";

    $input = json_decode(file_get_contents('php://input'), true);
    echo "✅ Input decoded: " . json_encode($input) . "\n";
    
    if (!isset($input['user_id'])) {
        echo "❌ User ID not provided\n";
        exit;
    }

    $user_id = (int)$input['user_id'];
    echo "✅ User ID: $user_id\n";

    // Test full user query from get_user_details.php
    $stmt = $pdo->prepare("
        SELECT 
            id,
            username,
            email,
            full_name,
            role,
            is_active,
            created_at,
            updated_at
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ User not found with ID: $user_id\n";
    } else {
        echo "✅ User found: " . json_encode($user) . "\n";
        
        // Test the role-specific queries
        if ($user['role'] === 'hospital') {
            echo "Testing hospital query...\n";
            $stmt = $pdo->prepare("
                SELECT 
                    hospital_name,
                    license_number,
                    address as hospital_address,
                    phone as hospital_phone,
                    contact_person,
                    approval_status,
                    approved_at
                FROM hospitals 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $hospital_info = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "Hospital info: " . json_encode($hospital_info) . "\n";
        } elseif ($user['role'] === 'donor') {
            echo "Testing donor query...\n";
            $stmt = $pdo->prepare("
                SELECT 
                    blood_type,
                    date_of_birth,
                    gender,
                    weight,
                    medical_conditions,
                    last_donation_date,
                    total_donations
                FROM donors 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $donor_info = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "Donor info: " . json_encode($donor_info) . "\n";
        }
        
        // Test admin_logs query
        echo "Testing admin_logs query...\n";
        $stmt = $pdo->prepare("
            SELECT 
                action,
                details,
                ip_address,
                user_agent,
                created_at
            FROM admin_logs 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Recent activity count: " . count($recent_activity) . "\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "❌ Stack trace: " . $e->getTraceAsString() . "\n";
}
?>