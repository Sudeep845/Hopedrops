<?php
require_once 'php/db_connect.php';

try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT id, username, email, role FROM users ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Users in database:\n";
    foreach ($users as $user) {
        echo "ID: {$user['id']}, Username: {$user['username']}, Email: {$user['email']}, Role: {$user['role']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>