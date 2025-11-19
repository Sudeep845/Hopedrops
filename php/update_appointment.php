<?php
// update_appointment.php - Update appointment status or details
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db_connect.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit();
}

// Only allow POST/PUT requests
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($input['appointment_id']) || !isset($input['action'])) {
        echo json_encode(['success' => false, 'message' => 'Missing appointment ID or action']);
        exit();
    }
    
    $appointment_id = intval($input['appointment_id']);
    $action = $input['action'];
    $user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;
    
    // Get current appointment details
    $stmt = $pdo->prepare("
        SELECT a.*, h.hospital_name, u.full_name as donor_name 
        FROM appointments a 
        JOIN hospitals h ON a.hospital_id = h.id 
        JOIN users u ON a.donor_id = u.id 
        WHERE a.id = ?
    ");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit();
    }
    
    // Verify user has permission to update this appointment
    if ($user_id && $appointment['donor_id'] != $user_id) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to update this appointment']);
        exit();
    }
    
    $success = false;
    $message = '';
    $new_status = $appointment['status'];
    
    switch ($action) {
        case 'cancel':
            if (in_array($appointment['status'], ['scheduled', 'confirmed'])) {
                $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
                $success = $stmt->execute([$appointment_id]);
                $new_status = 'cancelled';
                $message = 'Appointment cancelled successfully';
                
                // Log the cancellation
                if ($success) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (?, 'appointment_cancelled', ?, NOW())");
                        $stmt->execute([$appointment['donor_id'], json_encode([
                            'appointment_id' => $appointment_id,
                            'hospital_name' => $appointment['hospital_name'],
                            'appointment_date' => $appointment['appointment_date'],
                            'appointment_time' => $appointment['appointment_time']
                        ])]);
                    } catch (PDOException $e) {
                        error_log("Activity log failed: " . $e->getMessage());
                        // Continue without failing the main operation
                    }
                    
                    // Create notification
                    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, 'appointment', NOW())");
                    $stmt->execute([
                        $appointment['donor_id'],
                        'Appointment Cancelled',
                        "Your appointment on {$appointment['appointment_date']} at {$appointment['appointment_time']} has been cancelled."
                    ]);
                }
            } else {
                $message = 'Cannot cancel appointment with current status: ' . $appointment['status'];
            }
            break;
            
        case 'confirm':
            if ($appointment['status'] === 'scheduled') {
                $stmt = $pdo->prepare("UPDATE appointments SET status = 'confirmed', updated_at = NOW() WHERE id = ?");
                $success = $stmt->execute([$appointment_id]);
                $new_status = 'confirmed';
                $message = 'Appointment confirmed successfully';
                
                // Log the confirmation
                if ($success) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (?, 'appointment_confirmed', ?, NOW())");
                        $stmt->execute([$appointment['donor_id'], json_encode([
                            'appointment_id' => $appointment_id,
                            'hospital_name' => $appointment['hospital_name'],
                            'appointment_date' => $appointment['appointment_date'],
                            'appointment_time' => $appointment['appointment_time']
                        ])]);
                    } catch (PDOException $e) {
                        error_log("Activity log failed: " . $e->getMessage());
                        // Continue without failing the main operation
                    }
                }
            } else {
                $message = 'Cannot confirm appointment with current status: ' . $appointment['status'];
            }
            break;
            
        case 'complete':
            if (in_array($appointment['status'], ['scheduled', 'confirmed'])) {
                $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed', updated_at = NOW() WHERE id = ?");
                $success = $stmt->execute([$appointment_id]);
                $new_status = 'completed';
                $message = 'Appointment marked as completed';
                
                // Log the completion and create a donation record
                if ($success) {
                    // Create donation record
                    $stmt = $pdo->prepare("
                        INSERT INTO donations (donor_id, hospital_id, blood_type, donation_date, donation_time, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, 'completed', NOW())
                    ");
                    $stmt->execute([
                        $appointment['donor_id'],
                        $appointment['hospital_id'],
                        $appointment['blood_type'],
                        $appointment['appointment_date'],
                        $appointment['appointment_time']
                    ]);
                    
                    // Update blood inventory
                    $stmt = $pdo->prepare("
                        UPDATE blood_inventory 
                        SET units_available = units_available + 1, last_updated = NOW() 
                        WHERE hospital_id = ? AND blood_type = ?
                    ");
                    $stmt->execute([$appointment['hospital_id'], $appointment['blood_type']]);
                    
                    // Log the activity
                    try {
                        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (?, 'appointment_completed', ?, NOW())");
                        $stmt->execute([$appointment['donor_id'], json_encode([
                            'appointment_id' => $appointment_id,
                            'hospital_name' => $appointment['hospital_name'],
                            'appointment_date' => $appointment['appointment_date'],
                            'appointment_time' => $appointment['appointment_time'],
                            'blood_type' => $appointment['blood_type']
                        ])]);
                    } catch (PDOException $e) {
                        error_log("Activity log failed: " . $e->getMessage());
                        // Continue without failing the main operation
                    }
                }
            } else {
                $message = 'Cannot complete appointment with current status: ' . $appointment['status'];
            }
            break;
            
        case 'reschedule':
            // This would require new date/time in the input
            if (isset($input['new_date']) && isset($input['new_time'])) {
                $new_date = $input['new_date'];
                $new_time = $input['new_time'];
                
                // Validate future date
                if (strtotime($new_date . ' ' . $new_time) <= time()) {
                    echo json_encode(['success' => false, 'message' => 'New appointment time must be in the future']);
                    exit();
                }
                
                $stmt = $pdo->prepare("
                    UPDATE appointments 
                    SET appointment_date = ?, appointment_time = ?, status = 'rescheduled', updated_at = NOW() 
                    WHERE id = ?
                ");
                $success = $stmt->execute([$new_date, $new_time, $appointment_id]);
                $new_status = 'rescheduled';
                $message = 'Appointment rescheduled successfully';
            } else {
                $message = 'New date and time required for rescheduling';
            }
            break;
            
        default:
            $message = 'Invalid action: ' . $action;
    }
    
    if ($success) {
        // Get updated appointment details
        $stmt = $pdo->prepare("
            SELECT a.*, h.hospital_name, u.full_name as donor_name 
            FROM appointments a 
            JOIN hospitals h ON a.hospital_id = h.id 
            JOIN users u ON a.donor_id = u.id 
            WHERE a.id = ?
        ");
        $stmt->execute([$appointment_id]);
        $updated_appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'appointment' => $updated_appointment,
            'new_status' => $new_status
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $message ?: 'Failed to update appointment']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in update_appointment.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Error in update_appointment.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating the appointment']);
}
?>