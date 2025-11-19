<?php
// test_appointments_api.php - Simple test to debug appointment API issues
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    require_once 'db_connect.php';
    
    // Test database connection
    if (!isset($pdo)) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    }
    
    // Check if appointments table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'appointments'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo json_encode([
            'success' => false, 
            'message' => 'Appointments table does not exist. Please run the database setup first.',
            'debug' => [
                'table_exists' => false,
                'suggestion' => 'Run the updated bloodbank_complete.sql file to create the appointments table'
            ]
        ]);
        exit();
    }
    
    // Check if there are any appointments
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM appointments");
    $appointmentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Check if there are hospitals
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM hospitals WHERE is_approved = 1 AND is_active = 1");
    $hospitalCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get sample data
    $stmt = $pdo->query("SELECT id, hospital_name FROM hospitals WHERE is_approved = 1 LIMIT 3");
    $hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful',
        'debug' => [
            'appointments_table_exists' => true,
            'appointment_count' => $appointmentCount,
            'hospital_count' => $hospitalCount,
            'sample_hospitals' => $hospitals,
            'database_ready' => $appointmentCount >= 0 && $hospitalCount > 0
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage(),
        'debug' => [
            'error_type' => 'PDO Exception',
            'suggestion' => 'Check database connection and table structure'
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'General error: ' . $e->getMessage(),
        'debug' => [
            'error_type' => 'General Exception'
        ]
    ]);
}
?>