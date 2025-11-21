<?php
// get_appointments.php - Get user appointments
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db_connect.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
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
    $status = isset($_GET['status']) ? $_GET['status'] : 'all';
    
    // Get database connection (PDO)
    $db = getDBConnection();
    
    // Verify user exists and is active
    $stmt = $db->prepare("SELECT id, full_name FROM users WHERE id = ? AND role = 'donor' AND is_active = 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found or not active']);
        exit();
    }
    
    // Build query based on status filter
    $where_clause = "WHERE a.donor_id = ?";
    $params = [$user_id];
    
    if ($status !== 'all') {
        $valid_statuses = ['scheduled', 'confirmed', 'completed', 'cancelled', 'rescheduled', 'no_show'];
        if (in_array($status, $valid_statuses)) {
            $where_clause .= " AND a.status = ?";
            $params[] = $status;
        }
    }
    
    // Get appointments with hospital details
    $query = "
        SELECT 
            a.id,
            a.appointment_date,
            a.appointment_time,
            a.blood_type,
            a.status,
            a.notes,
            a.contact_person,
            a.contact_phone,
            a.reminder_sent,
            a.created_at,
            a.updated_at,
            h.id as hospital_id,
            h.hospital_name,
            h.address as hospital_address,
            h.city as hospital_city,
            h.contact_phone as hospital_phone,
            h.contact_email as hospital_email
        FROM appointments a
        JOIN hospitals h ON a.hospital_id = h.id
        $where_clause
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ";
    
    $stmt = $db->prepare($query);
    
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format appointments for frontend
    $formatted_appointments = [];
    foreach ($appointments as $appointment) {
        $formatted_appointments[] = [
            'id' => $appointment['id'],
            'appointment_date' => $appointment['appointment_date'],
            'appointment_time' => $appointment['appointment_time'],
            'blood_type' => $appointment['blood_type'],
            'status' => $appointment['status'],
            'notes' => $appointment['notes'],
            'contact_person' => $appointment['contact_person'],
            'contact_phone' => $appointment['contact_phone'],
            'reminder_sent' => (bool)$appointment['reminder_sent'],
            'created_at' => $appointment['created_at'],
            'updated_at' => $appointment['updated_at'],
            // Hospital info at root level for easier access
            'hospital_id' => $appointment['hospital_id'],
            'hospital_name' => $appointment['hospital_name'],
            'hospital_address' => $appointment['hospital_address'],
            'hospital_city' => $appointment['hospital_city'],
            'hospital_phone' => $appointment['hospital_phone'],
            'hospital_email' => $appointment['hospital_email'],
            // Nested hospital object for backward compatibility
            'hospital' => [
                'id' => $appointment['hospital_id'],
                'name' => $appointment['hospital_name'],
                'address' => $appointment['hospital_address'],
                'city' => $appointment['hospital_city'],
                'phone' => $appointment['hospital_phone'],
                'email' => $appointment['hospital_email']
            ],
            // Add formatted display values
            'formatted_date' => date('F j, Y', strtotime($appointment['appointment_date'])),
            'formatted_time' => date('g:i A', strtotime($appointment['appointment_time'])),
            'is_upcoming' => strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']) > time(),
            'status_class' => [
                'scheduled' => 'badge-primary',
                'confirmed' => 'badge-success', 
                'completed' => 'badge-secondary',
                'cancelled' => 'badge-danger',
                'rescheduled' => 'badge-warning',
                'no_show' => 'badge-dark'
            ][$appointment['status']] ?? 'badge-light'
        ];
    }
    
    // Separate upcoming and past appointments
    $upcoming = array_filter($formatted_appointments, function($app) {
        return $app['is_upcoming'] && !in_array($app['status'], ['completed', 'cancelled', 'no_show']);
    });
    
    $past = array_filter($formatted_appointments, function($app) {
        return !$app['is_upcoming'] || in_array($app['status'], ['completed', 'cancelled', 'no_show']);
    });
    
    // Calculate detailed stats
    $stats = [
        'total' => count($formatted_appointments),
        'upcoming' => count($upcoming),
        'past' => count($past),
        'completed' => count(array_filter($formatted_appointments, function($app) { 
            return $app['status'] === 'completed'; 
        })),
        'cancelled' => count(array_filter($formatted_appointments, function($app) { 
            return $app['status'] === 'cancelled'; 
        })),
        'scheduled' => count(array_filter($formatted_appointments, function($app) { 
            return $app['status'] === 'scheduled'; 
        })),
        'confirmed' => count(array_filter($formatted_appointments, function($app) { 
            return $app['status'] === 'confirmed'; 
        }))
    ];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'appointments' => $formatted_appointments,
            'stats' => $stats
        ],
        'message' => 'Appointments retrieved successfully'
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_appointments.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Database error occurred', 'error' => $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error in get_appointments.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while fetching appointments', 'error' => $e->getMessage()]);
}
?>