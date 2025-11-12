<?php
/**
 * Direct Hospital Registration Test - Updated
 * Tests the registration endpoint with complete hospital data
 */

// Simulate a complete hospital registration POST request
$_POST = [
    'role' => 'hospital',
    'fullName' => 'Dr. Test Administrator',
    'username' => 'test_hospital_' . time(),
    'email' => 'test' . time() . '@hospital.com',
    'phone' => '9876543210',
    'address' => '123 Medical Street, New Delhi, 110001',
    'password' => 'SecurePass123!',
    'confirmPassword' => 'SecurePass123!',
    
    // Hospital specific fields
    'hospitalName' => 'Test General Hospital',
    'licenseNumber' => 'LIC' . time(),
    'contactPerson' => 'Dr. Test Administrator',
    'city' => 'New Delhi',
    
    // Hospital verification fields
    'registrationNumber' => 'MED' . time(),
    'establishedDate' => '2015-06-15',
    'bedCapacity' => '250',
    'hospitalType' => 'Private',
    'services' => 'Emergency Care, Surgery, ICU, Blood Bank',
    
    // Agreements
    'agreeTerms' => '1',
    'agreeDataProcessing' => '1',
    'agreeMarketing' => '1'
];

$_SERVER['REQUEST_METHOD'] = 'POST';

echo "=== DIRECT HOSPITAL REGISTRATION TEST ===\n\n";
echo "Testing with data:\n";
foreach ($_POST as $key => $value) {
    if ($key !== 'password' && $key !== 'confirmPassword') {
        echo "  $key: $value\n";
    } else {
        echo "  $key: [HIDDEN]\n";
    }
}
echo "\n--- REGISTRATION RESULT ---\n";

// Capture the registration response
ob_start();
try {
    include 'register.php';
    $result = ob_get_contents();
} catch (Exception $e) {
    $result = json_encode([
        'success' => false,
        'message' => 'PHP Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Error $e) {
    $result = json_encode([
        'success' => false,
        'message' => 'PHP Fatal Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
ob_end_clean();

// Pretty print JSON result
$json = json_decode($result, true);
if ($json) {
    echo json_encode($json, JSON_PRETTY_PRINT);
} else {
    echo "Raw response:\n$result";
}

echo "\n\n=== TEST COMPLETE ===\n";
?>