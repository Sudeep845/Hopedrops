<?php
// Test username availability checking
echo "<h2>Username Availability Test</h2>\n";

// Test existing usernames
$existingUsernames = ['admin', 'hospital1', 'donor1', 'donor2'];
$newUsernames = ['newuser', 'testuser123', 'blooddonor2025'];

echo "<h3>Testing Existing Usernames (should be unavailable):</h3>\n";
foreach ($existingUsernames as $username) {
    testUsernameAvailability($username);
}

echo "<h3>Testing New Usernames (should be available):</h3>\n";
foreach ($newUsernames as $username) {
    testUsernameAvailability($username);
}

function testUsernameAvailability($username) {
    // Simulate the check_username.php request
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    // Simulate JSON input
    $jsonInput = json_encode(['username' => $username]);
    
    // Temporarily override php://input
    $tempFile = tempnam(sys_get_temp_dir(), 'username_test');
    file_put_contents($tempFile, $jsonInput);
    
    // Create a custom stream context to simulate php://input
    ob_start();
    
    // Manually include the logic from check_username.php
    try {
        require_once 'php/db_connect.php';
        
        $input = json_decode($jsonInput, true);
        $testUsername = sanitizeInput($input['username'] ?? '');
        
        if (empty($testUsername)) {
            echo "<p style='color: red'>❌ $username: Username is required</p>\n";
            return;
        }
        
        if (strlen($testUsername) < 3) {
            echo "<p style='color: red'>❌ $username: Username must be at least 3 characters</p>\n";
            return;
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $testUsername)) {
            echo "<p style='color: red'>❌ $username: Username can only contain letters, numbers, and underscores</p>\n";
            return;
        }
        
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$testUsername]);
        
        $available = !$stmt->fetch();
        
        if ($available) {
            echo "<p style='color: green'>✅ $username: Available</p>\n";
        } else {
            echo "<p style='color: orange'>⚠️ $username: Already taken</p>\n";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red'>❌ $username: Error - " . $e->getMessage() . "</p>\n";
    }
    
    ob_end_clean();
    unlink($tempFile);
}
?>