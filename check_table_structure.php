<?php
$pdo = new PDO('mysql:host=localhost;dbname=bloodbank_db;charset=utf8', 'root', '');
$stmt = $pdo->query('DESCRIBE appointments');
echo "<pre>";
while($row = $stmt->fetch()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
echo "</pre>";
?>