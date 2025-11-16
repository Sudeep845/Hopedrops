<?php
// Quick test to check user_id 19
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=bloodbank_db;charset=utf8",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    echo "<h3>Testing user_id 19</h3>";

    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([19]);
    $user = $stmt->fetch();

    if ($user) {
        echo "<p>✅ User 19 exists:</p>";
        echo "<pre>" . print_r($user, true) . "</pre>";
        
        // Check for associated hospital
        $stmt = $pdo->prepare("SELECT * FROM hospitals WHERE user_id = ?");
        $stmt->execute([19]);
        $hospital = $stmt->fetch();
        
        if ($hospital) {
            echo "<p>✅ Hospital found for user 19:</p>";
            echo "<pre>" . print_r($hospital, true) . "</pre>";
        } else {
            echo "<p>❌ No hospital found for user 19</p>";
        }
    } else {
        echo "<p>❌ User 19 does not exist</p>";
        
        // Show available users
        echo "<h4>Available users:</h4>";
        $stmt = $pdo->query("SELECT id, username, email, role FROM users ORDER BY id");
        while ($row = $stmt->fetch()) {
            echo "ID: {$row['id']}, Username: {$row['username']}, Email: {$row['email']}, Role: {$row['role']}<br>";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>