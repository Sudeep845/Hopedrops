<?php
/**
 * Simple POST test for hospital registration
 */

// Test data
$testData = [
    'role' => 'hospital',
    'fullName' => 'Dr. Test Administrator',
    'username' => 'test_hospital_' . time(),
    'email' => 'test' . time() . '@hospital.com',
    'phone' => '9876543210',
    'address' => '123 Medical Street, New Delhi, 110001',
    'password' => 'TestPass123!',
    'confirmPassword' => 'TestPass123!',
    'hospitalName' => 'Test General Hospital',
    'licenseNumber' => 'LIC' . time(),
    'contactPerson' => 'Dr. Test Administrator',
    'city' => 'New Delhi',
    'registrationNumber' => 'MED' . time(),
    'establishedDate' => '2015-06-15',
    'bedCapacity' => '250',
    'hospitalType' => 'Private',
    'services' => 'Emergency Care, Surgery, ICU'
];

// Create POST request using curl
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/HopeDrops/php/register.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($testData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'User-Agent: HopeDrops Test Client'
]);

echo "=== HOSPITAL REGISTRATION TEST ===\n\n";
echo "Test Data:\n";
print_r($testData);

echo "\nSending POST request to register.php...\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "HTTP Code: $httpCode\n";

if ($error) {
    echo "cURL Error: $error\n";
} else {
    echo "Response:\n";
    echo $response . "\n";
    
    // Try to decode JSON
    $json = json_decode($response, true);
    if ($json) {
        echo "\nParsed JSON:\n";
        print_r($json);
    } else {
        echo "\nFailed to parse as JSON. Raw response above.\n";
    }
}

echo "\n=== TEST COMPLETE ===\n";
?>