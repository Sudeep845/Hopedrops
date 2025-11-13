<?php
/**
 * Debug test for get_user_campaigns.php with session
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Testing get_user_campaigns.php with session</h3>";

// Start session and set test data (simulating logged in user)
session_start();
$_SESSION['user_id'] = 4; // User Aayu has ID 4
$_SESSION['username'] = 'Aayu';
$_SESSION['role'] = 'donor';

echo "Session set: user_id = 4, username = Aayu<br><br>";

// Test the API directly
try {
    ob_start();
    include 'get_user_campaigns.php';
    $output = ob_get_clean();
    
    echo "API Output:<br>";
    echo "<pre>$output</pre>";
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}
?>