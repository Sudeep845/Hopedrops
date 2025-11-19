<?php
// get_hospital_appointments.php - Get appointments for a specific hospital
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
    // Check if database connection exists
    if (!isset($pdo)) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    }

    // Check if appointments table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'appointments'");
    if ($stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Appointments table does not exist. Please run the database setup.']);
        exit();
    }

    // Get hospital ID from query parameters
    $hospital_id = isset($_GET['hospital_id']) ? intval($_GET['hospital_id']) : 0;
    $status = isset($_GET['status']) ? $_GET['status'] : 'all';
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    $date_range = isset($_GET['date_range']) ? $_GET['date_range'] : 'upcoming';
    
    if (!$hospital_id) {
        echo json_encode(['success' => false, 'message' => 'Hospital ID is required']);
        exit();
    }
    
    // Verify hospital exists and is approved
    $stmt = $pdo->prepare("SELECT id, hospital_name, contact_person FROM hospitals WHERE id = ? AND is_approved = 1 AND is_active = 1");
    $stmt->execute([$hospital_id]);
    $hospital = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$hospital) {
        echo json_encode(['success' => false, 'message' => 'Hospital not found or not approved']);
        exit();
    }
    
    // Build query based on filters
    $where_clause = "WHERE a.hospital_id = ?";
    $params = [$hospital_id];
    
    // Date range filter
    if ($date_range === 'upcoming') {
        $where_clause .= " AND a.appointment_date >= CURDATE()";
    } elseif ($date_range === 'past') {
        $where_clause .= " AND a.appointment_date < CURDATE()";
    } elseif ($date_range === 'today') {
        $where_clause .= " AND a.appointment_date = CURDATE()";
    } elseif ($date_range === 'week') {
        $where_clause .= " AND a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($date_range === 'month') {
        $where_clause .= " AND a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    }
    
    // Status filter
    if ($status !== 'all') {
        $valid_statuses = ['scheduled', 'confirmed', 'completed', 'cancelled', 'rescheduled', 'no_show'];
        if (in_array($status, $valid_statuses)) {
            $where_clause .= " AND a.status = ?";
            $params[] = $status;
        }
    }
    
    // Get appointments with donor details
    $stmt = $pdo->prepare("
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
            u.id as donor_id,
            u.full_name as donor_name,
            u.phone as donor_phone,
            u.email as donor_email,
            u.blood_type as donor_blood_type,
            u.city as donor_city,
            u.address as donor_address
        FROM appointments a
        JOIN users u ON a.donor_id = u.id
        {$where_clause}
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format appointments for frontend
    $formatted_appointments = [];
    foreach ($appointments as $appointment) {
        $formatted_appointments[] = [
            'id' => $appointment['id'],
            'donorId' => $appointment['donor_id'],
            'donorName' => $appointment['donor_name'],
            'donorPhone' => $appointment['donor_phone'],
            'donorEmail' => $appointment['donor_email'],
            'donorCity' => $appointment['donor_city'],
            'donorAddress' => $appointment['donor_address'],
            'bloodType' => $appointment['blood_type'],
            'donorBloodType' => $appointment['donor_blood_type'], // For verification
            'appointmentDate' => $appointment['appointment_date'],
            'appointmentTime' => $appointment['appointment_time'],
            'status' => $appointment['status'],
            'notes' => $appointment['notes'],
            'contactPerson' => $appointment['contact_person'],
            'contactPhone' => $appointment['contact_phone'],
            'reminderSent' => (bool)$appointment['reminder_sent'],
            'createdAt' => $appointment['created_at'],
            'updatedAt' => $appointment['updated_at'],
            // Add formatted display values
            'formattedDate' => date('F j, Y', strtotime($appointment['appointment_date'])),
            'formattedTime' => date('g:i A', strtotime($appointment['appointment_time'])),
            'formattedDateTime' => date('M j, Y g:i A', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time'])),
            'isToday' => $appointment['appointment_date'] === date('Y-m-d'),
            'isUpcoming' => strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']) > time(),
            'dayOfWeek' => date('l', strtotime($appointment['appointment_date'])),
            'statusClass' => [
                'scheduled' => 'warning',
                'confirmed' => 'success', 
                'completed' => 'secondary',
                'cancelled' => 'danger',
                'rescheduled' => 'info',
                'no_show' => 'dark'
            ][$appointment['status']] ?? 'light',
            'statusIcon' => [
                'scheduled' => 'fa-clock',
                'confirmed' => 'fa-check-circle',
                'completed' => 'fa-check-double',
                'cancelled' => 'fa-times-circle',
                'rescheduled' => 'fa-calendar-alt',
                'no_show' => 'fa-user-times'
            ][$appointment['status']] ?? 'fa-question'
        ];
    }
    
    // Calculate statistics
    $stats = [
        'total' => count($formatted_appointments),
        'scheduled' => count(array_filter($formatted_appointments, function($app) { 
            return $app['status'] === 'scheduled'; 
        })),
        'confirmed' => count(array_filter($formatted_appointments, function($app) { 
            return $app['status'] === 'confirmed'; 
        })),
        'completed' => count(array_filter($formatted_appointments, function($app) { 
            return $app['status'] === 'completed'; 
        })),
        'cancelled' => count(array_filter($formatted_appointments, function($app) { 
            return $app['status'] === 'cancelled'; 
        })),
        'today' => count(array_filter($formatted_appointments, function($app) { 
            return $app['isToday']; 
        })),
        'upcoming' => count(array_filter($formatted_appointments, function($app) { 
            return $app['isUpcoming'] && !in_array($app['status'], ['completed', 'cancelled', 'no_show']); 
        }))
    ];
    
    // Group appointments by status for easier frontend handling
    $grouped_appointments = [
        'all' => $formatted_appointments,
        'scheduled' => array_values(array_filter($formatted_appointments, function($app) { 
            return $app['status'] === 'scheduled'; 
        })),
        'confirmed' => array_values(array_filter($formatted_appointments, function($app) { 
            return $app['status'] === 'confirmed'; 
        })),
        'completed' => array_values(array_filter($formatted_appointments, function($app) { 
            return $app['status'] === 'completed'; 
        })),
        'cancelled' => array_values(array_filter($formatted_appointments, function($app) { 
            return $app['status'] === 'cancelled'; 
        })),
        'today' => array_values(array_filter($formatted_appointments, function($app) { 
            return $app['isToday']; 
        })),
        'upcoming' => array_values(array_filter($formatted_appointments, function($app) { 
            return $app['isUpcoming'] && !in_array($app['status'], ['completed', 'cancelled', 'no_show']); 
        }))
    ];
    
    echo json_encode([
        'success' => true,
        'hospital' => $hospital,
        'appointments' => $grouped_appointments,
        'stats' => $stats,
        'filters' => [
            'hospital_id' => $hospital_id,
            'status' => $status,
            'date_range' => $date_range,
            'total_found' => count($formatted_appointments)
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_hospital_appointments.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Error in get_hospital_appointments.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while fetching appointments']);
}
?>