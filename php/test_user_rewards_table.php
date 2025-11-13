<?php
// Test user_rewards table accessibility for registration fix verification
header('Content-Type: application/json');

try {
    // Database connection
    include_once 'db_connect.php';
    
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    }
    
    // Test if user_rewards table exists and is accessible
    $sql = "SHOW TABLES LIKE 'user_rewards'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        // Test table structure
        $sql = "DESCRIBE user_rewards";
        $result = $conn->query($sql);
        
        if ($result) {
            $columns = [];
            while ($row = $result->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'user_rewards table exists with columns: ' . implode(', ', $columns)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not describe user_rewards table']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'user_rewards table does not exist']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>