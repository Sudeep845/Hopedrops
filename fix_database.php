<?php
echo "<h2>🔧 Database Connection Fix</h2>";

// Step 1: Test basic MySQL connection
echo "<h3>Step 1: Testing MySQL Connection</h3>";
try {
    $conn = new mysqli('localhost', 'root', '');
    if ($conn->connect_error) {
        echo "❌ Cannot connect to MySQL: " . $conn->connect_error . "<br>";
        exit;
    }
    echo "✅ MySQL connection successful<br>";
} catch (Exception $e) {
    echo "❌ MySQL connection failed: " . $e->getMessage() . "<br>";
    exit;
}

// Step 2: Check if database exists
echo "<h3>Step 2: Checking Database</h3>";
$result = $conn->query("SHOW DATABASES LIKE 'bloodbank_db'");
if ($result->num_rows == 0) {
    echo "⚠️ Database 'bloodbank_db' does not exist<br>";
    
    // Step 3: Create database
    echo "<h3>Step 3: Creating Database</h3>";
    if ($conn->query("CREATE DATABASE IF NOT EXISTS bloodbank_db")) {
        echo "✅ Database 'bloodbank_db' created successfully<br>";
    } else {
        echo "❌ Error creating database: " . $conn->error . "<br>";
        exit;
    }
} else {
    echo "✅ Database 'bloodbank_db' already exists<br>";
}

// Step 4: Select database and check tables
echo "<h3>Step 4: Checking Tables</h3>";
$conn->select_db('bloodbank_db');
$result = $conn->query("SHOW TABLES");
$tables = [];
if ($result) {
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
}

if (empty($tables)) {
    echo "⚠️ No tables found in database<br>";
    echo "<strong>🔄 Next Step: Import SQL file</strong><br>";
    echo "<ol>";
    echo "<li>Go to <a href='http://localhost/phpmyadmin' target='_blank'>phpMyAdmin</a></li>";
    echo "<li>Select 'bloodbank_db' database</li>";
    echo "<li>Click 'Import' tab</li>";
    echo "<li>Choose 'bloodbank_complete.sql' file</li>";
    echo "<li>Click 'Go' to import</li>";
    echo "</ol>";
} else {
    echo "✅ Found " . count($tables) . " tables: " . implode(', ', $tables) . "<br>";
    
    // Check for admin user
    if (in_array('users', $tables)) {
        $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
        if ($result) {
            $row = $result->fetch_assoc();
            if ($row['count'] > 0) {
                echo "✅ Admin user found<br>";
                echo "<strong>🎉 Database is ready!</strong><br>";
                echo "<p><a href='login.html' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Go to Login Page</a></p>";
            } else {
                echo "⚠️ No admin user found - may need to re-import SQL<br>";
            }
        }
    }
}

$conn->close();
?>