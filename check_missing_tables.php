<?php
require_once 'php/db_connect.php';

echo "Checking for activity_logs table...\n";
$stmt = $pdo->query("SHOW TABLES LIKE 'activity_logs'");
if ($stmt->rowCount() > 0) {
    echo "✅ activity_logs table EXISTS\n";
} else {
    echo "❌ activity_logs table DOES NOT EXIST\n";
}

echo "\nChecking for notifications table...\n";
$stmt = $pdo->query("SHOW TABLES LIKE 'notifications'");
if ($stmt->rowCount() > 0) {
    echo "✅ notifications table EXISTS\n";
} else {
    echo "❌ notifications table DOES NOT EXIST\n";
}

echo "\nAll existing tables:\n";
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch()) {
    echo "- " . array_values($row)[0] . "\n";
}
?>