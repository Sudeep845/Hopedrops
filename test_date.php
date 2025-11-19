<?php
// Test the date/time format fix
echo "=== TESTING DATE/TIME FORMAT FIX ===\n\n";

$future_date = new DateTime('+7 days');
$testData = [
    'donor_id' => 4,
    'hospital_id' => 3,
    'appointment_date' => $future_date->format('Y-m-d'),
    'appointment_time' => '15:45:00', // Using full H:i:s format
    'blood_type' => 'A+',
    'notes' => 'Test appointment to verify date/time format fix'
];

echo "Testing with correct H:i:s format:\n";
echo "Date: " . $testData['appointment_date'] . "\n";
echo "Time: " . $testData['appointment_time'] . "\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/HopeDrops/php/create_appointment.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "API Response (HTTP $httpCode):\n";
echo $response . "\n\n";

$result = json_decode($response, true);
if ($result && $result['success']) {
    echo "✅ SUCCESS: Date/time format accepted!\n";
    echo "   Appointment ID: " . $result['appointment_id'] . "\n";
} else {
    echo "❌ FAILED: " . ($result['message'] ?? 'Unknown error') . "\n";
}

// Also test what happens with H:i format (without seconds)
echo "\n--- Testing H:i format (should fail) ---\n";
$testData2 = $testData;
$testData2['appointment_time'] = '16:30'; // Missing seconds

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/HopeDrops/php/create_appointment.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData2));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response2 = curl_exec($ch);
curl_close($ch);

echo "Time without seconds: " . $testData2['appointment_time'] . "\n";
echo "Response: " . $response2 . "\n";

echo "\n=== TEST COMPLETE ===\n";
?>