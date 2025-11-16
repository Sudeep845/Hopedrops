<?php
require_once 'php/db_connect.php';

try {
    $stmt = $pdo->query('SELECT id, username, email FROM users LIMIT 5');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($users) . " users:\n";
    foreach ($users as $user) {
        echo "ID: " . $user['id'] . ", Username: " . $user['username'] . ", Email: " . $user['email'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>