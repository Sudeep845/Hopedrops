<?php
/**
 * Test Admin Stats API
 * This will test the get_admin_stats.php endpoint directly
 */

echo "<h2>🔧 Testing Admin Stats API</h2>";

// Start session to simulate admin login
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';

echo "<p><strong>Session set for admin user</strong></p>";

// Test database connection first
echo "<h3>1. Testing Database Connection</h3>";
try {
    require_once 'php/db_connect.php';
    $db = getDBConnection();
    echo "✅ Database connection successful<br>";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    exit;
}

// Test individual queries
echo "<h3>2. Testing Individual Queries</h3>";

try {
    // Test users query
    echo "<strong>Users Query:</strong><br>";
    $userStatsStmt = $db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $userStats = $userStatsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    echo "✅ Users by role: ";
    print_r($userStats);
    echo "<br><br>";
    
    // Test hospitals query
    echo "<strong>Hospitals Query:</strong><br>";
    $hospitalStatsStmt = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as pending
        FROM hospitals
    ");
    $hospitalStats = $hospitalStatsStmt->fetch();
    echo "✅ Hospital stats: ";
    print_r($hospitalStats);
    echo "<br><br>";
    
    // Test blood inventory query
    echo "<strong>Blood Inventory Query:</strong><br>";
    $bloodStatsStmt = $db->query("
        SELECT 
            SUM(units_available) as total_available,
            SUM(units_required) as total_required,
            COUNT(DISTINCT hospital_id) as hospitals_with_inventory
        FROM blood_inventory
    ");
    $bloodStats = $bloodStatsStmt->fetch();
    echo "✅ Blood stats: ";
    print_r($bloodStats);
    echo "<br><br>";
    
} catch (Exception $e) {
    echo "❌ Query error: " . $e->getMessage() . "<br>";
}

// Test the actual API endpoint
echo "<h3>3. Testing Full API Endpoint</h3>";
echo "<p><a href='php/get_admin_stats.php' target='_blank'>Click to test get_admin_stats.php directly</a></p>";

echo "<h3>4. AJAX Test</h3>";
?>
<div id="ajax-result" style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; background: #f9f9f9;">
    <button onclick="testAjax()" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">Test AJAX Call</button>
    <div id="ajax-output" style="margin-top: 10px;"></div>
</div>

<script>
function testAjax() {
    const output = document.getElementById('ajax-output');
    output.innerHTML = '🔄 Testing AJAX call...';
    
    fetch('php/get_admin_stats.php')
    .then(response => {
        console.log('Response status:', response.status);
        return response.text();
    })
    .then(text => {
        console.log('Raw response:', text);
        try {
            const data = JSON.parse(text);
            if (data.success) {
                output.innerHTML = `
                    <div style="color: green;">
                        <h4>✅ API Success!</h4>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    </div>
                `;
            } else {
                output.innerHTML = `
                    <div style="color: red;">
                        <h4>❌ API Failed</h4>
                        <p>Error: ${data.message}</p>
                    </div>
                `;
            }
        } catch (e) {
            output.innerHTML = `
                <div style="color: red;">
                    <h4>❌ Parse Error</h4>
                    <p>Raw response:</p>
                    <pre>${text}</pre>
                </div>
            `;
        }
    })
    .catch(error => {
        output.innerHTML = `
            <div style="color: red;">
                <h4>❌ Network Error</h4>
                <p>${error.message}</p>
            </div>
        `;
    });
}
</script>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
</style>