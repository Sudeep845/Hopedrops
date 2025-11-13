<?php
/**
 * Simple test for get_next_donation_date.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Testing get_next_donation_date.php</h3>";

// Start session and set test data
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'testuser';
$_SESSION['role'] = 'donor';

echo "Session set: user_id = 1<br>";

try {
    // Include and test the API
    ob_start();
    include 'get_next_donation_date.php';
    $output = ob_get_clean();
    
    echo "API Output:<br>";
    echo "<pre>$output</pre>";
    
    // Parse JSON to check if it's valid
    $data = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<br>✅ Valid JSON response<br>";
        echo "Success: " . ($data['success'] ? 'true' : 'false') . "<br>";
        if (!$data['success']) {
            echo "Error message: " . $data['message'] . "<br>";
        }
    } else {
        echo "<br>❌ Invalid JSON response<br>";
        echo "JSON Error: " . json_last_error_msg() . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}
?>