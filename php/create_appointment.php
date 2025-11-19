<?php
// create_appointment.php - Create new appointment
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Debug mode - remove in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once 'db_connect.php';
    
    // Test database connection immediately
    if (!isset($pdo)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Database Setup Required - Issue: Database connection failed',
            'debug' => 'PDO connection not established in db_connect.php',
            'recommendations' => [
                'Check if XAMPP MySQL service is running',
                'Verify database bloodbank_db exists',
                'Run database_setup.html to create required tables'
            ]
        ]);
        exit();
    }
    
    // Test if we can query the database
    $pdo->query("SELECT 1");
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database Setup Required - Issue: Database connection failed',
        'debug' => $e->getMessage(),
        'error_code' => $e->getCode(),
        'recommendations' => [
            'Start XAMPP MySQL service',
            'Create bloodbank_db database',
            'Import required database schema',
            'Check MySQL port 3306 availability'
        ]
    ]);
    exit();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database Setup Required - Issue: ' . $e->getMessage(),
        'debug' => 'Connection error',
        'recommendations' => ['Check database configuration']
    ]);
    exit();
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required_fields = ['donor_id', 'hospital_id', 'appointment_date', 'appointment_time', 'blood_type'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            exit();
        }
    }
    
    // Extract and sanitize input
    $donor_id = intval($input['donor_id']);
    $hospital_id = intval($input['hospital_id']);
    $appointment_date = $input['appointment_date'];
    $appointment_time = $input['appointment_time'];
    $blood_type = $input['blood_type'];
    $notes = isset($input['notes']) ? $input['notes'] : '';
    $contact_person = isset($input['contact_person']) ? $input['contact_person'] : '';
    $contact_phone = isset($input['contact_phone']) ? $input['contact_phone'] : '';
    
    // Validate blood type
    $valid_blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    if (!in_array($blood_type, $valid_blood_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid blood type']);
        exit();
    }
    
    // Validate date (must be future date) - use DateTime for better validation
    $appointment_datetime = $appointment_date . ' ' . $appointment_time;
    $appointment_dt = DateTime::createFromFormat('Y-m-d H:i:s', $appointment_datetime);
    $current_dt = new DateTime();
    
    if (!$appointment_dt || $appointment_dt <= $current_dt) {
        // More lenient validation - allow appointments within the next 6 months
        $min_date = new DateTime();
        $max_date = new DateTime('+6 months');
        
        if (!$appointment_dt) {
            echo json_encode(['success' => false, 'message' => 'Invalid appointment date/time format']);
            exit();
        } elseif ($appointment_dt < $min_date) {
            echo json_encode(['success' => false, 'message' => 'Appointment must be scheduled for a future date and time']);
            exit();
        } elseif ($appointment_dt > $max_date) {
            echo json_encode(['success' => false, 'message' => 'Appointments can only be scheduled up to 6 months in advance']);
            exit();
        }
    }
    
    // Check if donor exists and is eligible
    $stmt = $pdo->prepare("SELECT id, full_name, phone, blood_type, is_eligible FROM users WHERE id = ? AND role = 'donor' AND is_active = 1");
    $stmt->execute([$donor_id]);
    $donor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$donor) {
        echo json_encode(['success' => false, 'message' => 'Donor not found or inactive']);
        exit();
    }
    
    if (!$donor['is_eligible']) {
        echo json_encode(['success' => false, 'message' => 'Donor is not currently eligible for donation']);
        exit();
    }
    
    // Check if hospital exists and is approved
    $stmt = $pdo->prepare("SELECT id, hospital_name, contact_person, contact_phone FROM hospitals WHERE id = ? AND is_approved = 1 AND is_active = 1");
    $stmt->execute([$hospital_id]);
    $hospital = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$hospital) {
        echo json_encode(['success' => false, 'message' => 'Hospital not found or not approved']);
        exit();
    }
    
    // Use hospital contact info if not provided
    if (empty($contact_person)) {
        $contact_person = $hospital['contact_person'];
    }
    if (empty($contact_phone)) {
        $contact_phone = $hospital['contact_phone'];
    }
    
    // Check for existing appointment at the same time (prevent double booking)
    $stmt = $pdo->prepare("SELECT id FROM appointments WHERE hospital_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT IN ('cancelled', 'completed')");
    $stmt->execute([$hospital_id, $appointment_date, $appointment_time]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'This time slot is already booked. Please choose a different time.']);
        exit();
    }
    
    // Check if donor already has an appointment on the same date
    $stmt = $pdo->prepare("SELECT id FROM appointments WHERE donor_id = ? AND appointment_date = ? AND status NOT IN ('cancelled', 'completed')");
    $stmt->execute([$donor_id, $appointment_date]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You already have an appointment scheduled for this date. Please choose a different date.']);
        exit();
    }
    
    // Create the appointment
    $stmt = $pdo->prepare("
        INSERT INTO appointments (donor_id, hospital_id, appointment_date, appointment_time, blood_type, notes, contact_person, contact_phone, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())
    ");
    
    $result = $stmt->execute([
        $donor_id,
        $hospital_id, 
        $appointment_date,
        $appointment_time,
        $blood_type,
        $notes,
        $contact_person,
        $contact_phone
    ]);
    
    if ($result) {
        $appointment_id = $pdo->lastInsertId();
        
        // Try to log the activity (skip if table doesn't exist)
        try {
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (?, 'appointment_created', ?, NOW())");
            $stmt->execute([$donor_id, json_encode([
                'appointment_id' => $appointment_id,
                'hospital_id' => $hospital_id,
                'hospital_name' => $hospital['hospital_name'],
                'appointment_date' => $appointment_date,
                'appointment_time' => $appointment_time,
                'blood_type' => $blood_type
            ])]);
        } catch (PDOException $e) {
            // Ignore if activity_logs table doesn't exist
            error_log("Activity log failed (table may not exist): " . $e->getMessage());
        }
        
        // Try to create notification for donor (skip if table doesn't exist)
        try {
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, 'appointment', NOW())");
            $stmt->execute([
                $donor_id,
                'Appointment Scheduled',
                "Your blood donation appointment has been scheduled for {$appointment_date} at {$appointment_time} at {$hospital['hospital_name']}."
            ]);
        } catch (PDOException $e) {
            // Ignore if notifications table doesn't exist
            error_log("Notification creation failed (table may not exist): " . $e->getMessage());
        }
        
        // Get the created appointment details
        $stmt = $pdo->prepare("
            SELECT a.*, h.hospital_name, u.full_name as donor_name 
            FROM appointments a 
            JOIN hospitals h ON a.hospital_id = h.id 
            JOIN users u ON a.donor_id = u.id 
            WHERE a.id = ?
        ");
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Appointment created successfully',
            'appointment_id' => $appointment_id,
            'appointment' => $appointment
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create appointment']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in create_appointment.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Error in create_appointment.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while creating the appointment']);
}
?>