<?php
// find_donor.php - Find donor by phone number or email
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db_connect.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit();
}

try {
    // Check if database connection exists
    if (!isset($pdo)) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    }

    // Get identifier from query parameters
    $identifier = isset($_GET['identifier']) ? trim($_GET['identifier']) : '';
    
    if (empty($identifier)) {
        echo json_encode(['success' => false, 'message' => 'Phone number or email is required']);
        exit();
    }

    // Search for donor by phone or email
    $stmt = $pdo->prepare("
        SELECT id, username, email, full_name, phone, blood_type, is_eligible, is_active 
        FROM users 
        WHERE role = 'donor' 
        AND is_active = 1 
        AND (phone = ? OR email = ?)
    ");
    $stmt->execute([$identifier, $identifier]);
    $donor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$donor) {
        echo json_encode([
            'success' => false, 
            'message' => 'Donor not found with provided phone number or email'
        ]);
        exit();
    }

    // Check if donor is eligible
    if (!$donor['is_eligible']) {
        echo json_encode([
            'success' => false, 
            'message' => 'Donor is not currently eligible for blood donation'
        ]);
        exit();
    }

    // Return donor information
    echo json_encode([
        'success' => true,
        'message' => 'Donor found successfully',
        'donor' => [
            'id' => $donor['id'],
            'full_name' => $donor['full_name'],
            'email' => $donor['email'],
            'phone' => $donor['phone'],
            'blood_type' => $donor['blood_type'],
            'username' => $donor['username'],
            'is_eligible' => $donor['is_eligible']
        ]
    ]);

} catch (PDOException $e) {
    error_log("Database error in find_donor.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Error in find_donor.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while searching for donor']);
}
?>