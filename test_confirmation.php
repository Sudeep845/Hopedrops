<?php
// Test appointment confirmation
$testData = [
    'action' => 'confirm'
];

// First, let's get an appointment ID that we can confirm
require_once 'php/db_connect.php';
$stmt = $pdo->query("SELECT id FROM appointments WHERE status = 'scheduled' LIMIT 1");
$appointment = $stmt->fetch();

if (!$appointment) {
    echo "No scheduled appointments found to test confirmation.\n";
    echo "Creating a test appointment first...\n";
    
    // Create a test appointment
    $tomorrow = new DateTime('+4 days');
    $stmt = $pdo->prepare("INSERT INTO appointments (donor_id, hospital_id, appointment_date, appointment_time, blood_type, status) VALUES (4, 3, ?, '15:30:00', 'O+', 'scheduled')");
    $stmt->execute([$tomorrow->format('Y-m-d')]);
    $appointment_id = $pdo->lastInsertId();
    echo "Created test appointment with ID: $appointment_id\n";
} else {
    $appointment_id = $appointment['id'];
    echo "Using existing appointment ID: $appointment_id\n";
}

// Now test the confirmation
$testData['appointment_id'] = $appointment_id;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/HopeDrops/php/update_appointment.php");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "\n=== CONFIRMATION TEST ===\n";
echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

// Pretty print the JSON response
$responseData = json_decode($response, true);
if ($responseData) {
    echo "\n=== PARSED RESPONSE ===\n";
    echo "Success: " . ($responseData['success'] ? 'YES' : 'NO') . "\n";
    echo "Message: " . $responseData['message'] . "\n";
    if (isset($responseData['appointment'])) {
        echo "New Status: " . $responseData['appointment']['status'] . "\n";
    }
}
?>