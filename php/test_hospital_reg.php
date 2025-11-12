<?php
/**
 * Test Hospital Registration Directly
 */

// Simulate POST data for hospital registration
$_POST = [
    'role' => 'hospital',
    'fullName' => 'Dr. Test Hospital Admin',
    'username' => 'test_hospital_' . time(),
    'email' => 'test@hospital.com',
    'phone' => '9876543210',
    'address' => '123 Medical Street, Delhi, 110001',
    'password' => 'password123',
    'confirmPassword' => 'password123',
    'hospitalName' => 'Test General Hospital',
    'licenseNumber' => 'MED123456789',
    'contactPerson' => 'Dr. Test Admin',
    'city' => 'Delhi'
];

$_SERVER['REQUEST_METHOD'] = 'POST';

// Capture output
ob_start();

try {
    require_once 'register.php';
    $output = ob_get_contents();
} catch (Exception $e) {
    $output = json_encode([
        'success' => false,
        'message' => 'PHP Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

ob_end_clean();

// Display result
header('Content-Type: application/json');
echo $output;
?>