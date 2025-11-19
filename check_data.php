<?php
require_once 'php/db_connect.php';

echo "=== HOSPITAL DATA CHECK ===\n";
$stmt = $pdo->query('SELECT id, hospital_name FROM hospitals ORDER BY id');
while($row = $stmt->fetch()) {
    echo "ID: " . $row['id'] . " - " . $row['hospital_name'] . "\n";
}

echo "\n=== USER DATA CHECK ===\n";
$stmt = $pdo->query("SELECT id, username, role FROM users WHERE role = 'donor' ORDER BY id LIMIT 5");
while($row = $stmt->fetch()) {
    echo "ID: " . $row['id'] . " - " . $row['username'] . " (" . $row['role'] . ")\n";
}

echo "Done.\n";
?>