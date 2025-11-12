<?php
/**
 * HopeDrops Blood Bank Management System
 * Emergency Blood Request Handler
 * 
 * Handles emergency blood requests from the public
 * Created: November 11, 2025
 */

require_once 'db_connect.php';

header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Invalid request method');
}

// Check rate limiting
if (!checkRateLimit('emergency_request', 3, 300)) {
    sendJsonResponse(false, 'Too many emergency requests. Please try again later.');
}

try {
    $db = getDBConnection();
    
    // Get and sanitize input
    $patientName = sanitizeInput($_POST['patientName'] ?? '');
    $bloodTypeNeeded = sanitizeInput($_POST['bloodTypeNeeded'] ?? '');
    $unitsNeeded = (int)($_POST['unitsNeeded'] ?? 1);
    $urgencyLevel = sanitizeInput($_POST['urgencyLevel'] ?? 'medium');
    $hospitalId = (int)($_POST['hospitalSelect'] ?? 0);
    $contactPerson = sanitizeInput($_POST['contactPerson'] ?? '');
    $contactPhone = sanitizeInput($_POST['contactPhone'] ?? '');
    $medicalReason = sanitizeInput($_POST['medicalReason'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($patientName)) {
        $errors['patientName'] = 'Patient name is required';
    }
    
    if (empty($bloodTypeNeeded)) {
        $errors['bloodTypeNeeded'] = 'Blood type is required';
    } elseif (!in_array($bloodTypeNeeded, ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])) {
        $errors['bloodTypeNeeded'] = 'Invalid blood type';
    }
    
    if ($unitsNeeded < 1 || $unitsNeeded > 10) {
        $errors['unitsNeeded'] = 'Units needed must be between 1 and 10';
    }
    
    if (!in_array($urgencyLevel, ['low', 'medium', 'high', 'critical'])) {
        $errors['urgencyLevel'] = 'Invalid urgency level';
    }
    
    if ($hospitalId <= 0) {
        $errors['hospitalSelect'] = 'Please select a hospital';
    }
    
    if (empty($contactPerson)) {
        $errors['contactPerson'] = 'Contact person is required';
    }
    
    if (empty($contactPhone)) {
        $errors['contactPhone'] = 'Contact phone is required';
    } elseif (!validatePhone($contactPhone)) {
        $errors['contactPhone'] = 'Please enter a valid phone number';
    }
    
    // Verify hospital exists and is approved
    $stmt = $db->prepare("SELECT id, hospital_name FROM hospitals WHERE id = ? AND is_approved = 1");
    $stmt->execute([$hospitalId]);
    $hospital = $stmt->fetch();
    
    if (!$hospital) {
        $errors['hospitalSelect'] = 'Selected hospital is not available';
    }
    
    // Return validation errors if any
    if (!empty($errors)) {
        sendJsonResponse(false, 'Please correct the errors below', $errors);
    }
    
    // Insert emergency blood request
    $stmt = $db->prepare("
        INSERT INTO blood_requests (
            requester_id, hospital_id, blood_type, units_needed, urgency, 
            patient_name, required_date, reason, status, created_at
        ) VALUES (NULL, ?, ?, ?, ?, ?, CURDATE(), ?, 'pending', NOW())
    ");
    
    $stmt->execute([
        $hospitalId,
        $bloodTypeNeeded,
        $unitsNeeded,
        $urgencyLevel,
        $patientName,
        $medicalReason
    ]);
    
    $requestId = $db->lastInsertId();
    
    // Create notifications for hospital staff
    $hospitalUsersStmt = $db->prepare("
        SELECT u.id, u.full_name 
        FROM users u 
        JOIN hospitals h ON u.id = h.user_id 
        WHERE h.id = ?
    ");
    $hospitalUsersStmt->execute([$hospitalId]);
    $hospitalUsers = $hospitalUsersStmt->fetchAll();
    
    foreach ($hospitalUsers as $user) {
        createNotification(
            $user['id'],
            'Emergency Blood Request',
            "New {$urgencyLevel} priority request for {$unitsNeeded} units of {$bloodTypeNeeded} blood for patient {$patientName}. Contact: {$contactPerson} ({$contactPhone})",
            $urgencyLevel === 'critical' ? 'error' : ($urgencyLevel === 'high' ? 'warning' : 'info'),
            null,
            null
        );
    }
    
    // Also notify admin users
    $adminStmt = $db->prepare("SELECT id FROM users WHERE role = 'admin' AND is_active = 1");
    $adminStmt->execute();
    $admins = $adminStmt->fetchAll();
    
    foreach ($admins as $admin) {
        createNotification(
            $admin['id'],
            'Emergency Blood Request',
            "New emergency blood request submitted to {$hospital['hospital_name']} - {$unitsNeeded} units of {$bloodTypeNeeded} needed urgently.",
            'warning'
        );
    }
    
    // Check blood availability at the selected hospital
    $availabilityStmt = $db->prepare("
        SELECT units_available 
        FROM blood_inventory 
        WHERE hospital_id = ? AND blood_type = ?
    ");
    $availabilityStmt->execute([$hospitalId, $bloodTypeNeeded]);
    $availability = $availabilityStmt->fetch();
    
    $availableUnits = $availability['units_available'] ?? 0;
    $isAvailable = $availableUnits >= $unitsNeeded;
    
    // If blood is not available, check compatible donors at the hospital
    if (!$isAvailable) {
        $compatibleTypes = getBloodTypeCompatibility($bloodTypeNeeded);
        $compatibleAvailabilityStmt = $db->prepare("
            SELECT blood_type, units_available 
            FROM blood_inventory 
            WHERE hospital_id = ? AND blood_type IN (" . str_repeat('?,', count($compatibleTypes) - 1) . "?) AND units_available > 0
        ");
        $params = array_merge([$hospitalId], $compatibleTypes);
        $compatibleAvailabilityStmt->execute($params);
        $compatibleBlood = $compatibleAvailabilityStmt->fetchAll();
    }
    
    // Log the emergency request
    logActivity(null, 'emergency_request', "Emergency blood request created - Request ID: {$requestId}");
    
    // Prepare response message
    $responseMessage = "Emergency blood request submitted successfully! ";
    if ($isAvailable) {
        $responseMessage .= "Good news: {$availableUnits} units of {$bloodTypeNeeded} are available at {$hospital['hospital_name']}.";
    } else {
        $responseMessage .= "The hospital will be notified immediately to check availability and arrange for the required blood.";
    }
    $responseMessage .= " Hospital staff will contact you shortly at {$contactPhone}.";
    
    // Response data
    $responseData = [
        'request_id' => $requestId,
        'hospital_name' => $hospital['hospital_name'],
        'blood_available' => $isAvailable,
        'available_units' => $availableUnits,
        'contact_info' => [
            'person' => $contactPerson,
            'phone' => $contactPhone
        ]
    ];
    
    if (!$isAvailable && isset($compatibleBlood)) {
        $responseData['compatible_blood'] = $compatibleBlood;
    }
    
    sendJsonResponse(true, $responseMessage, $responseData);
    
} catch (PDOException $e) {
    error_log("Emergency request database error: " . $e->getMessage());
    sendJsonResponse(false, 'Database error occurred. Please try again or contact the hospital directly.');
} catch (Exception $e) {
    error_log("Emergency request error: " . $e->getMessage());
    sendJsonResponse(false, 'An error occurred while processing your request. Please try again or contact the hospital directly.');
}
?>