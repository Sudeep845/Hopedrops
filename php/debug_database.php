<?php
// debug_database.php - Debug database connection and setup
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Test basic MySQL connection first (without specifying database)
    $pdo_test = new PDO("mysql:host=localhost", "root", "");
    
    // Check if bloodbank_db exists
    $stmt = $pdo_test->query("SHOW DATABASES LIKE 'bloodbank_db'");
    $dbExists = $stmt->rowCount() > 0;
    
    $response = [
        'success' => true,
        'mysql_connection' => 'OK',
        'database_exists' => $dbExists,
        'debug_info' => []
    ];
    
    if (!$dbExists) {
        // Try to create the database
        $pdo_test->exec("CREATE DATABASE IF NOT EXISTS bloodbank_db");
        $response['database_created'] = true;
        $response['message'] = 'Database created successfully';
    }
    
    // Now test connection to bloodbank_db
    try {
        require_once 'db_connect.php';
        
        if (isset($pdo)) {
            // Test the appointments table
            $stmt = $pdo->query("SHOW TABLES LIKE 'appointments'");
            $appointmentsTableExists = $stmt->rowCount() > 0;
            
            $response['bloodbank_db_connection'] = 'OK';
            $response['appointments_table_exists'] = $appointmentsTableExists;
            
            if ($appointmentsTableExists) {
                // Count appointments
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM appointments");
                $appointmentCount = $stmt->fetch()['count'];
                $response['appointment_count'] = $appointmentCount;
            }
            
            // Check users table
            $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'donor'");
                $donorCount = $stmt->fetch()['count'];
                $response['donor_count'] = $donorCount;
            }
            
            // Check hospitals table
            $stmt = $pdo->query("SHOW TABLES LIKE 'hospitals'");
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM hospitals WHERE is_approved = 1");
                $hospitalCount = $stmt->fetch()['count'];
                $response['hospital_count'] = $hospitalCount;
            }
            
        } else {
            $response['success'] = false;
            $response['message'] = 'PDO connection not established in db_connect.php';
        }
        
    } catch (Exception $e) {
        $response['bloodbank_db_connection'] = 'FAILED';
        $response['bloodbank_db_error'] = $e->getMessage();
    }
    
    // Add recommendations
    if (!$response['appointments_table_exists']) {
        $response['recommendations'] = [
            'action' => 'run_sql_setup',
            'message' => 'Please run the bloodbank_complete.sql file to create all required tables',
            'sql_file' => 'sql/bloodbank_complete.sql'
        ];
    } elseif ($response['appointment_count'] == 0) {
        $response['recommendations'] = [
            'action' => 'tables_ready',
            'message' => 'Database is ready. You can now create appointments.',
            'status' => 'ready_for_use'
        ];
    }
    
} catch (PDOException $e) {
    $response = [
        'success' => false,
        'mysql_connection' => 'FAILED',
        'error' => $e->getMessage(),
        'recommendations' => [
            'action' => 'check_xampp',
            'message' => 'Please ensure XAMPP MySQL service is running',
            'steps' => [
                '1. Open XAMPP Control Panel',
                '2. Start MySQL service',
                '3. Refresh this page'
            ]
        ]
    ];
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'recommendations' => [
            'action' => 'general_error',
            'message' => 'An unexpected error occurred'
        ]
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>