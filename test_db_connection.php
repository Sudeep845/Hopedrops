<?php
/**
 * Database Connection Test
 * This file tests the database connection for HopeDrops
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>HopeDrops Database Connection Test</h2>";

// Include the database connection file
require_once 'php/db_connect.php';

try {
    // Test database connection
    echo "<h3>1. Testing Database Connection...</h3>";
    $pdo = getDBConnection();
    
    if ($pdo) {
        echo "<p style='color: green;'>✅ Database connection successful!</p>";
        
        // Test a simple query
        echo "<h3>2. Testing Database Query...</h3>";
        $stmt = $pdo->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'bloodbank_db'");
        $result = $stmt->fetch();
        
        echo "<p style='color: green;'>✅ Database query successful!</p>";
        echo "<p>Number of tables in bloodbank_db: " . $result['table_count'] . "</p>";
        
        // Test specific tables
        echo "<h3>3. Testing Specific Tables...</h3>";
        $tables = ['users', 'hospitals', 'blood_inventory', 'donations', 'blood_requests'];
        
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                $result = $stmt->fetch();
                echo "<p style='color: green;'>✅ Table '$table': " . $result['count'] . " records</p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Error with table '$table': " . $e->getMessage() . "</p>";
            }
        }
        
        // Test database configuration
        echo "<h3>4. Database Configuration:</h3>";
        echo "<p>Host: " . DB_HOST . "</p>";
        echo "<p>Database: " . DB_NAME . "</p>";
        echo "<p>Username: " . DB_USERNAME . "</p>";
        echo "<p>Charset: " . DB_CHARSET . "</p>";
        
    } else {
        echo "<p style='color: red;'>❌ Failed to get database connection!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed!</p>";
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    
    // Additional debugging information
    echo "<h3>Debugging Information:</h3>";
    echo "<p>PHP Version: " . phpversion() . "</p>";
    echo "<p>PDO Available: " . (extension_loaded('pdo') ? 'Yes' : 'No') . "</p>";
    echo "<p>PDO MySQL Available: " . (extension_loaded('pdo_mysql') ? 'Yes' : 'No') . "</p>";
    
    // Check if MySQL is running
    $connection = @fsockopen('localhost', 3306, $errno, $errstr, 5);
    if ($connection) {
        echo "<p>MySQL Server: Running on port 3306</p>";
        fclose($connection);
    } else {
        echo "<p style='color: red;'>MySQL Server: Not responding on port 3306</p>";
    }
}

echo "<hr>";
echo "<p><a href='index.html'>← Back to HopeDrops</a></p>";
?>