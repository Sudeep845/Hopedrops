<?php
/**
 * Debug API Test - Tests all dashboard APIs with proper error reporting
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Set up a test session
$_SESSION['user_id'] = 1; // Test with user ID 1
$_SESSION['username'] = 'testuser';
$_SESSION['role'] = 'donor';

echo "<h2>API Debug Test</h2>";

// Test each API endpoint
$apis = [
    'get_next_donation_date.php',
    'check_donation_eligibility.php',
    'get_user_activities.php?limit=5',
    'get_user_stats.php',
    'get_user_campaigns.php?limit=5'
];

foreach ($apis as $api) {
    echo "<h3>Testing: $api</h3>";
    
    ob_start();
    include $api;
    $output = ob_get_clean();
    
    echo "<pre>$output</pre>";
    echo "<hr>";
}
?>