<?php
// Test appointment creation
// Use a date 3 days from now to avoid conflicts
$future_date = new DateTime('+3 days');

$testData = [
    'donor_id' => 4,
    'hospital_id' => 3,
    'appointment_date' => $future_date->format('Y-m-d'),
    'appointment_time' => '16:45:00',
    'blood_type' => 'O+',
    'notes' => 'Test appointment via API'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/HopeDrops/php/create_appointment.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";
?>