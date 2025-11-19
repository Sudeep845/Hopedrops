<?php
// Test hospital appointment creation flow
echo "=== TESTING HOSPITAL APPOINTMENT CREATION ===\n\n";

// Step 1: Find a donor
$donorIdentifier = "9800000000"; // Known donor phone
echo "1. Finding donor with identifier: $donorIdentifier\n";

$findResponse = file_get_contents("http://localhost/HopeDrops/php/find_donor.php?identifier=" . urlencode($donorIdentifier));
echo "Find Donor Response: $findResponse\n\n";

$donorResult = json_decode($findResponse, true);

if ($donorResult && $donorResult['success']) {
    echo "✅ Donor found: " . $donorResult['donor']['full_name'] . " (ID: " . $donorResult['donor']['id'] . ")\n\n";
    
    // Step 2: Create appointment for this donor
    $future_date = new DateTime('+6 days');
    $appointmentData = [
        'donor_id' => $donorResult['donor']['id'],
        'hospital_id' => 3, // Sherpa Hospital
        'appointment_date' => $future_date->format('Y-m-d'),
        'appointment_time' => '11:30:00',
        'blood_type' => $donorResult['donor']['blood_type'],
        'notes' => 'Appointment created by hospital staff',
        'contact_person' => 'Dr. Smith',
        'contact_phone' => '9811111111'
    ];
    
    echo "2. Creating appointment with data:\n";
    echo json_encode($appointmentData, JSON_PRETTY_PRINT) . "\n\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/HopeDrops/php/create_appointment.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($appointmentData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $createResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Create Appointment Response (HTTP $httpCode):\n$createResponse\n\n";
    
    $createResult = json_decode($createResponse, true);
    
    if ($createResult && $createResult['success']) {
        echo "✅ Appointment created successfully! ID: " . $createResult['appointment_id'] . "\n\n";
        
        // Step 3: Verify appointment appears in hospital's appointment list
        echo "3. Verifying appointment appears in hospital's list...\n";
        $hospitalAppointments = file_get_contents('http://localhost/HopeDrops/php/get_hospital_appointments.php?hospital_id=3');
        
        $hospitalData = json_decode($hospitalAppointments, true);
        if ($hospitalData && $hospitalData['success']) {
            $found = false;
            foreach ($hospitalData['appointments']['all'] as $appointment) {
                if ($appointment['id'] == $createResult['appointment_id']) {
                    $found = true;
                    echo "✅ Appointment found in hospital's list!\n";
                    echo "   - Donor: " . $appointment['donorName'] . "\n";
                    echo "   - Date: " . $appointment['formattedDate'] . "\n";
                    echo "   - Time: " . $appointment['formattedTime'] . "\n";
                    echo "   - Status: " . $appointment['status'] . "\n";
                    break;
                }
            }
            
            if (!$found) {
                echo "❌ Appointment NOT found in hospital's list!\n";
            }
        } else {
            echo "❌ Failed to get hospital appointments: " . ($hospitalData['message'] ?? 'Unknown error') . "\n";
        }
        
    } else {
        echo "❌ Failed to create appointment: " . ($createResult['message'] ?? 'Unknown error') . "\n";
    }
    
} else {
    echo "❌ Failed to find donor: " . ($donorResult['message'] ?? 'Unknown error') . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>