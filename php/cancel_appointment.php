<?php
// cancel_appointment.php - Cancel an appointment
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db_connect.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    // Check if user is logged in via session
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        http_response_code(401);
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    $appointment_id = isset($input['appointment_id']) ? intval($input['appointment_id']) : 0;
    
    if (!$appointment_id) {
        echo json_encode(['success' => false, 'message' => 'Appointment ID is required']);
        exit();
    }
    
    // Get database connection (PDO)
    $db = getDBConnection();
    
    // Verify the appointment belongs to this user and is not already cancelled
    $stmt = $db->prepare("
        SELECT id, status, appointment_date, appointment_time 
        FROM appointments 
        WHERE id = ? AND donor_id = ?
    ");
    $stmt->execute([$appointment_id, $user_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found or does not belong to you']);
        exit();
    }
    
    if ($appointment['status'] === 'cancelled') {
        echo json_encode(['success' => false, 'message' => 'Appointment is already cancelled']);
        exit();
    }
    
    if ($appointment['status'] === 'completed') {
        echo json_encode(['success' => false, 'message' => 'Cannot cancel a completed appointment']);
        exit();
    }
    
    // Check if appointment is in the past
    $appointment_datetime = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
    if ($appointment_datetime < time()) {
        echo json_encode(['success' => false, 'message' => 'Cannot cancel past appointments']);
        exit();
    }
    
    // Update appointment status to cancelled
    $stmt = $db->prepare("
        UPDATE appointments 
        SET status = 'cancelled', 
            updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$appointment_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Appointment cancelled successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to cancel appointment'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Database error in cancel_appointment.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Error in cancel_appointment.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while cancelling appointment']);
}
?>
