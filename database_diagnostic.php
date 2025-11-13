<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HopeDrops Database Diagnostic</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .diagnostic-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        .success {
            border-left: 5px solid #28a745;
            background-color: #d4edda;
        }
        .error {
            border-left: 5px solid #dc3545;
            background-color: #f8d7da;
        }
        .warning {
            border-left: 5px solid #ffc107;
            background-color: #fff3cd;
        }
        .info {
            border-left: 5px solid #17a2b8;
            background-color: #d1ecf1;
        }
        h2 {
            margin-top: 0;
            color: #333;
        }
        pre {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .test-result {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <h1>🩸 HopeDrops Database Diagnostic Tool</h1>
    
    <?php
    // Database configuration
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $database = 'bloodbank_db';
    
    echo "<div class='diagnostic-box info'>";
    echo "<h2>🔧 System Information</h2>";
    echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
    echo "<p><strong>Server:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
    echo "<p><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
    echo "</div>";
    
    // Test 1: Check if MySQL extension is loaded
    echo "<div class='diagnostic-box'>";
    echo "<h2>📦 MySQL Extensions Check</h2>";
    
    if (extension_loaded('pdo_mysql')) {
        echo "<div class='test-result success'>✅ PDO MySQL Extension: LOADED</div>";
    } else {
        echo "<div class='test-result error'>❌ PDO MySQL Extension: NOT LOADED</div>";
    }
    
    if (extension_loaded('mysqli')) {
        echo "<div class='test-result success'>✅ MySQLi Extension: LOADED</div>";
    } else {
        echo "<div class='test-result error'>❌ MySQLi Extension: NOT LOADED</div>";
    }
    echo "</div>";
    
    // Test 2: Basic MySQL connection
    echo "<div class='diagnostic-box'>";
    echo "<h2>🔌 MySQL Connection Test</h2>";
    
    try {
        $conn = new mysqli($host, $username, $password);
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        echo "<div class='test-result success'>✅ MySQL Server Connection: SUCCESS</div>";
        echo "<p><strong>MySQL Version:</strong> " . $conn->server_info . "</p>";
        
        // Check if database exists
        $result = $conn->query("SHOW DATABASES LIKE '$database'");
        if ($result && $result->num_rows > 0) {
            echo "<div class='test-result success'>✅ Database '$database': EXISTS</div>";
            
            // Connect to the specific database
            $conn->select_db($database);
            
            // Check tables
            $tables = [];
            $result = $conn->query("SHOW TABLES");
            if ($result) {
                while ($row = $result->fetch_array()) {
                    $tables[] = $row[0];
                }
                echo "<div class='test-result success'>✅ Total Tables: " . count($tables) . "</div>";
                if (!empty($tables)) {
                    echo "<p><strong>Tables:</strong> " . implode(', ', $tables) . "</p>";
                }
            }
            
            // Check users table specifically
            if (in_array('users', $tables)) {
                $result = $conn->query("SELECT COUNT(*) as count FROM users");
                if ($result) {
                    $row = $result->fetch_assoc();
                    echo "<div class='test-result info'>📊 Users Table: " . $row['count'] . " records</div>";
                }
                
                // Check admin user
                $result = $conn->query("SELECT username, role FROM users WHERE role = 'admin' LIMIT 1");
                if ($result && $result->num_rows > 0) {
                    $admin = $result->fetch_assoc();
                    echo "<div class='test-result success'>👤 Admin User: Found (" . $admin['username'] . ")</div>";
                } else {
                    echo "<div class='test-result warning'>⚠️ Admin User: NOT FOUND</div>";
                }
            } else {
                echo "<div class='test-result error'>❌ Users Table: MISSING</div>";
            }
            
            // Check hospitals table
            if (in_array('hospitals', $tables)) {
                $result = $conn->query("SELECT COUNT(*) as count FROM hospitals");
                if ($result) {
                    $row = $result->fetch_assoc();
                    echo "<div class='test-result info'>📊 Hospitals Table: " . $row['count'] . " records</div>";
                }
            } else {
                echo "<div class='test-result warning'>⚠️ Hospitals Table: MISSING</div>";
            }
            
        } else {
            echo "<div class='test-result error'>❌ Database '$database': NOT FOUND</div>";
            echo "<p><strong>Available Databases:</strong></p>";
            $result = $conn->query("SHOW DATABASES");
            if ($result) {
                while ($row = $result->fetch_array()) {
                    echo "<li>" . $row[0] . "</li>";
                }
            }
        }
        
        $conn->close();
    } catch (Exception $e) {
        echo "<div class='test-result error'>❌ Connection Error: " . $e->getMessage() . "</div>";
    }
    echo "</div>";
    
    // Test 3: PDO Connection
    echo "<div class='diagnostic-box'>";
    echo "<h2>🔗 PDO Connection Test</h2>";
    
    try {
        $dsn = "mysql:host=$host;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        echo "<div class='test-result success'>✅ PDO Connection: SUCCESS</div>";
        
        // Try to connect to specific database
        $dsn_db = "mysql:host=$host;dbname=$database;charset=utf8mb4";
        $pdo_db = new PDO($dsn_db, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        echo "<div class='test-result success'>✅ PDO Database Connection: SUCCESS</div>";
        
    } catch (PDOException $e) {
        echo "<div class='test-result error'>❌ PDO Error: " . $e->getMessage() . "</div>";
    }
    echo "</div>";
    
    // Test 4: File Permissions
    echo "<div class='diagnostic-box'>";
    echo "<h2>📁 File System Check</h2>";
    
    $files_to_check = [
        '../php/db_connect.php',
        '../php/login.php',
        '../php/register.php',
        '../sql/bloodbank_complete.sql'
    ];
    
    foreach ($files_to_check as $file) {
        if (file_exists($file)) {
            echo "<div class='test-result success'>✅ File exists: " . basename($file) . "</div>";
        } else {
            echo "<div class='test-result error'>❌ File missing: " . basename($file) . "</div>";
        }
    }
    echo "</div>";
    
    // Test 5: Error Log Check
    echo "<div class='diagnostic-box'>";
    echo "<h2>📋 Quick Solutions</h2>";
    echo "<div class='test-result info'>";
    echo "<h3>If database doesn't exist:</h3>";
    echo "<ol>";
    echo "<li>Go to phpMyAdmin (http://localhost/phpmyadmin)</li>";
    echo "<li>Create new database named 'bloodbank_db'</li>";
    echo "<li>Import the bloodbank_complete.sql file</li>";
    echo "</ol>";
    
    echo "<h3>If connection fails:</h3>";
    echo "<ol>";
    echo "<li>Make sure XAMPP Apache and MySQL are running</li>";
    echo "<li>Check if MySQL is running on port 3306</li>";
    echo "<li>Verify MySQL root password is empty (default XAMPP)</li>";
    echo "</ol>";
    
    echo "<h3>If tables are missing:</h3>";
    echo "<ol>";
    echo "<li>Run the bloodbank_complete.sql file in phpMyAdmin</li>";
    echo "<li>Check for any SQL errors during import</li>";
    echo "</ol>";
    echo "</div>";
    echo "</div>";
    ?>
    
    <div class='diagnostic-box info'>
        <h2>🔄 Refresh Test</h2>
        <p><a href="database_diagnostic.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">Run Diagnostic Again</a></p>
    </div>
</body>
</html>