<?php
/**
 * Test Hospitals API Endpoint
 */

echo "<h2>🏥 Testing Hospitals API</h2>";

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

// Test hospitals table structure
echo "<h3>2. Testing Hospitals Table Structure</h3>";
try {
    $columnsStmt = $db->query("DESCRIBE hospitals");
    $columns = $columnsStmt->fetchAll();
    echo "✅ Hospitals table has " . count($columns) . " columns<br>";
    echo "<details><summary>View Columns</summary><ul>";
    foreach ($columns as $column) {
        echo "<li><strong>{$column['Field']}</strong> ({$column['Type']})</li>";
    }
    echo "</ul></details><br>";
} catch (Exception $e) {
    echo "❌ Table structure error: " . $e->getMessage() . "<br>";
}

// Test hospitals query
echo "<h3>3. Testing Hospitals Query</h3>";
try {
    // Test the main query from get_hospitals.php
    $stmt = $db->prepare("
        SELECT 
            h.id,
            h.user_id,
            h.hospital_name,
            h.license_number,
            h.address,
            h.city,
            h.state,
            h.pincode,
            h.contact_person,
            h.contact_phone,
            h.contact_email,
            h.emergency_contact,
            h.latitude,
            h.longitude,
            h.hospital_type,
            h.phone,
            h.email,
            h.is_approved,
            h.is_active,
            h.created_at,
            h.updated_at,
            u.full_name as contact_person_name,
            u.email as user_email,
            u.phone as user_phone,
            CASE 
                WHEN h.is_approved = 1 THEN 'active'
                WHEN h.is_approved = 0 THEN 'pending'
                ELSE 'inactive'
            END as status,
            (
                SELECT SUM(bi.units_available) 
                FROM blood_inventory bi 
                WHERE bi.hospital_id = h.id
            ) as total_blood_units
        FROM hospitals h
        JOIN users u ON h.user_id = u.id
        WHERE 1=1
        ORDER BY h.created_at DESC
        LIMIT ?
    ");
    
    $stmt->execute([50]);
    $hospitals = $stmt->fetchAll();
    
    echo "✅ Query executed successfully<br>";
    echo "📊 Found " . count($hospitals) . " hospitals<br>";
    
    if (count($hospitals) > 0) {
        echo "<details><summary>View Hospital Data</summary>";
        echo "<pre>" . print_r($hospitals[0], true) . "</pre>";
        echo "</details>";
    }
    
} catch (Exception $e) {
    echo "❌ Query error: " . $e->getMessage() . "<br>";
}

// Test blood inventory
echo "<h3>4. Testing Blood Inventory</h3>";
try {
    $inventoryStmt = $db->query("SELECT COUNT(*) as count FROM blood_inventory");
    $inventoryCount = $inventoryStmt->fetch();
    echo "📊 Blood inventory records: " . $inventoryCount['count'] . "<br>";
} catch (Exception $e) {
    echo "❌ Blood inventory error: " . $e->getMessage() . "<br>";
}

// Test the actual API endpoint
echo "<h3>5. Testing API Endpoint</h3>";
echo "<p><a href='php/get_hospitals.php' target='_blank'>Click to test get_hospitals.php directly</a></p>";

echo "<h3>6. AJAX Test</h3>";
?>
<div id="ajax-result" style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; background: #f9f9f9;">
    <button onclick="testHospitalsAjax()" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">Test AJAX Call</button>
    <div id="ajax-output" style="margin-top: 10px;"></div>
</div>

<script>
function testHospitalsAjax() {
    const output = document.getElementById('ajax-output');
    output.innerHTML = '🔄 Testing hospitals API...';
    
    fetch('php/get_hospitals.php', {
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (response.status === 500) {
            return response.text().then(text => {
                throw new Error(`Server Error: ${text}`);
            });
        }
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
                        <p><strong>Hospitals found:</strong> ${data.data.length}</p>
                        <p><strong>Total count:</strong> ${data.total_count || 'N/A'}</p>
                        <details>
                            <summary>View Response</summary>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </details>
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
details { margin: 10px 0; }
</style>