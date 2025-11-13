<?php
// Quick database connection test
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'bloodbank_db';

echo "<h2>Quick Database Test</h2>";

try {
    // Test basic MySQL connection
    $conn = new mysqli($host, $username, $password);
    if ($conn->connect_error) {
        echo "❌ MySQL Connection Failed: " . $conn->connect_error . "<br>";
        exit;
    }
    echo "✅ MySQL Connection: SUCCESS<br>";
    
    // Test database selection
    if (!$conn->select_db($database)) {
        echo "❌ Database '$database' not found. Error: " . $conn->error . "<br>";
        echo "📝 Solution: Create database and import bloodbank_complete.sql<br>";
        exit;
    }
    echo "✅ Database '$database': EXISTS<br>";
    
    // Test users table
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    if (!$result) {
        echo "❌ Users table error: " . $conn->error . "<br>";
        exit;
    }
    $row = $result->fetch_assoc();
    echo "✅ Users table: " . $row['count'] . " records<br>";
    
    echo "<br>🎉 <strong>Database is working correctly!</strong>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>