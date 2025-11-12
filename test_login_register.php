<?php
// Test login functionality for HopeDrops
echo "<h2>HopeDrops Login Test</h2>\n";

// Test if we can simulate a login request
$testData = [
    'username' => 'admin',
    'password' => 'password', // Common default password
    'role' => 'admin'
];

echo "<h3>Testing Login with Default Credentials:</h3>\n";
echo "<p>Username: admin</p>\n";
echo "<p>Password: password</p>\n";
echo "<p>Role: admin</p>\n";

// Simulate POST request
$_POST = $testData;
$_SERVER['REQUEST_METHOD'] = 'POST';

ob_start();
try {
    include 'php/login.php';
} catch (Exception $e) {
    echo "<p style='color: red'>Login test failed: " . $e->getMessage() . "</p>\n";
}
$loginResult = ob_get_clean();

echo "<h3>Login Response:</h3>\n";
echo "<pre>" . htmlentities($loginResult) . "</pre>\n";

// Test registration functionality
echo "<hr><h3>Testing Registration Process:</h3>\n";

$testRegData = [
    'role' => 'donor',
    'fullName' => 'Test User',
    'username' => 'testuser' . time(),
    'email' => 'test' . time() . '@example.com',
    'phone' => '1234567890',
    'address' => '123 Test St',
    'password' => 'TestPass123!',
    'confirmPassword' => 'TestPass123!',
    'dateOfBirth' => '1990-01-01',
    'gender' => 'Male',
    'bloodType' => 'O+'
];

$_POST = $testRegData;
$_SERVER['REQUEST_METHOD'] = 'POST';

ob_start();
try {
    include 'php/register.php';
} catch (Exception $e) {
    echo "<p style='color: red'>Registration test failed: " . $e->getMessage() . "</p>\n";
}
$regResult = ob_get_clean();

echo "<h3>Registration Response:</h3>\n";
echo "<pre>" . htmlentities($regResult) . "</pre>\n";
?>