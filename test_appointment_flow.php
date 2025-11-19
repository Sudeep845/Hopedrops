<?php
// Test complete appointment flow
echo "=== TESTING COMPLETE APPOINTMENT FLOW ===\n\n";

// Step 1: Create a new appointment
$future_date = new DateTime('+5 days');
$testData = [
    'donor_id' => 4,
    'hospital_id' => 3,
    'appointment_date' => $future_date->format('Y-m-d'),
    'appointment_time' => '13:15:00',
    'blood_type' => 'O+',
    'notes' => 'Test appointment from user dashboard'
];

echo "1. Creating new appointment...\n";
echo "Data: " . json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/HopeDrops/php/create_appointment.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Create API Response (HTTP $httpCode):\n";
echo $response . "\n\n";

$createResult = json_decode($response, true);

if ($createResult && $createResult['success']) {
    echo "✅ Appointment created successfully! ID: " . $createResult['appointment_id'] . "\n\n";
    
    // Step 2: Fetch appointments for user 4
    echo "2. Fetching appointments for user ID 4...\n";
    $appointmentsResponse = file_get_contents('http://localhost/HopeDrops/php/get_appointments.php?user_id=4');
    echo "Get Appointments API Response:\n";
    
    $appointmentsData = json_decode($appointmentsResponse, true);
    if ($appointmentsData && $appointmentsData['success']) {
        echo "✅ Found " . count($appointmentsData['appointments']['all']) . " total appointments\n";
        echo "✅ Found " . count($appointmentsData['appointments']['upcoming']) . " upcoming appointments\n\n";
        
        // Check if our new appointment is in the list
        $newAppointmentFound = false;
        foreach ($appointmentsData['appointments']['all'] as $appointment) {
            if ($appointment['id'] == $createResult['appointment_id']) {
                $newAppointmentFound = true;
                echo "✅ New appointment found in the list!\n";
                echo "   - Date: " . $appointment['formatted_date'] . "\n";
                echo "   - Time: " . $appointment['formatted_time'] . "\n";
                echo "   - Status: " . $appointment['status'] . "\n";
                echo "   - Hospital: " . $appointment['hospital']['name'] . "\n";
                break;
            }
        }
        
        if (!$newAppointmentFound) {
            echo "❌ New appointment NOT found in the list!\n";
        }
        
    } else {
        echo "❌ Failed to fetch appointments: " . ($appointmentsData['message'] ?? 'Unknown error') . "\n";
    }
    
} else {
    echo "❌ Failed to create appointment: " . ($createResult['message'] ?? 'Unknown error') . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>