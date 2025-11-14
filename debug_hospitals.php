<?php
// Simple debug script to check hospital data
$pdo = new PDO('mysql:host=localhost;dbname=bloodbank_db;charset=utf8', 'root', '');

echo "<h3>Hospital Debug Information</h3>";

// Check hospitals
$stmt = $pdo->query("SELECT COUNT(*) as count FROM hospitals");
$hospitalCount = $stmt->fetch()['count'];
echo "<p>Total hospitals: {$hospitalCount}</p>";

// Check approved hospitals
$stmt = $pdo->query("SELECT COUNT(*) as count FROM hospitals WHERE is_approved = 1");
$approvedCount = $stmt->fetch()['count'];
echo "<p>Approved hospitals: {$approvedCount}</p>";

// Check blood inventory
$stmt = $pdo->query("SELECT COUNT(*) as count FROM blood_inventory");
$inventoryCount = $stmt->fetch()['count'];
echo "<p>Blood inventory records: {$inventoryCount}</p>";

// Show sample hospital data
echo "<h4>Sample Hospital Data:</h4>";
$stmt = $pdo->query("SELECT id, hospital_name, city, is_approved FROM hospitals LIMIT 5");
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Name</th><th>City</th><th>Approved</th></tr>";
while ($row = $stmt->fetch()) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['hospital_name']}</td>";
    echo "<td>{$row['city']}</td>";
    echo "<td>" . ($row['is_approved'] ? 'Yes' : 'No') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check blood inventory sample
echo "<h4>Sample Blood Inventory:</h4>";
$stmt = $pdo->query("
    SELECT h.hospital_name, bi.blood_type, bi.units_available 
    FROM hospitals h 
    JOIN blood_inventory bi ON h.id = bi.hospital_id 
    LIMIT 10
");
echo "<table border='1'>";
echo "<tr><th>Hospital</th><th>Blood Type</th><th>Units</th></tr>";
while ($row = $stmt->fetch()) {
    echo "<tr>";
    echo "<td>{$row['hospital_name']}</td>";
    echo "<td>{$row['blood_type']}</td>";
    echo "<td>{$row['units_available']}</td>";
    echo "</tr>";
}
echo "</table>";
?>