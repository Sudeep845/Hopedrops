<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    require_once 'db_connect.php';
    
    $user_id = $_GET['user_id'] ?? null;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        exit;
    }
    
    // Simple query to test
    $stmt = $pdo->prepare("SELECT * FROM hospitals WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $hospital = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$hospital) {
        echo json_encode(['success' => false, 'message' => 'Hospital not found']);
        exit;
    }
    
    // Minimal field mapping for frontend
    $result = [
        'id' => $hospital['id'],
        'user_id' => $hospital['user_id'],
        'hospital_name' => $hospital['hospital_name'] ?? 'N/A',
        'license_number' => $hospital['license_number'] ?? 'N/A',
        'address' => $hospital['address'] ?? 'N/A',
        'city' => $hospital['city'] ?? 'N/A',
        'state' => $hospital['state'] ?? 'N/A',
        'postal_code' => $hospital['pincode'] ?? 'N/A', // Map pincode to postal_code
        'contact_person' => $hospital['contact_person'] ?? 'N/A',
        'contact_phone' => $hospital['contact_phone'] ?? 'N/A',
        'contact_email' => $hospital['contact_email'] ?? 'N/A',
        'hospital_type' => $hospital['hospital_type'] ?? 'General',
        'is_approved' => $hospital['is_approved'] ?? 0,
        'is_active' => $hospital['is_active'] ?? 1,
        'created_at' => $hospital['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => $hospital['updated_at'] ?? date('Y-m-d H:i:s'),
        
        // Frontend expected fields
        'full_name' => $hospital['contact_person'] ?? 'N/A',
        'email' => $hospital['contact_email'] ?? 'N/A',
        'phone' => $hospital['contact_phone'] ?? 'N/A',
        'status' => ($hospital['is_approved'] ?? 0) ? 'active' : 'pending',
        'verification_status' => ($hospital['is_approved'] ?? 0) ? 'verified' : 'pending',
        'documents' => []
    ];
    
    echo json_encode(['success' => true, 'data' => $result]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>