<?php
/**
 * Simple database connection test for debugging API issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Database Connection Test</h3>";

// Test database connection
try {
    include_once 'db_connect.php';
    
    if ($conn) {
        echo "✅ Database connection successful<br>";
        
        // Test a simple query
        $result = $conn->query("SELECT COUNT(*) as count FROM users");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "✅ Users table accessible, count: " . $row['count'] . "<br>";
        } else {
            echo "❌ Error querying users table: " . $conn->error . "<br>";
        }
        
        // Test donations table
        $result = $conn->query("SELECT COUNT(*) as count FROM donations");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "✅ Donations table accessible, count: " . $row['count'] . "<br>";
        } else {
            echo "❌ Error querying donations table: " . $conn->error . "<br>";
        }
        
    } else {
        echo "❌ Database connection failed<br>";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>Session Test</h3>";

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'testuser';
$_SESSION['role'] = 'donor';

echo "✅ Session variables set:<br>";
echo "user_id: " . $_SESSION['user_id'] . "<br>";
echo "username: " . $_SESSION['username'] . "<br>";
echo "role: " . $_SESSION['role'] . "<br>";

echo "<hr>";
echo "<h3>Testing get_next_donation_date.php</h3>";

// Reset output buffering
ob_clean();

// Set headers as the API does
header('Content-Type: application/json');

try {
    $user_id = $_SESSION['user_id'];

    // Get the last donation date for this user
    $sql = "SELECT MAX(donation_date) as last_donation_date 
            FROM donations 
            WHERE donor_id = ? AND status = 'completed'";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row && $row['last_donation_date']) {
            $last_donation = new DateTime($row['last_donation_date']);
            $next_eligible = clone $last_donation;
            $next_eligible->add(new DateInterval('P56D')); // Add 56 days
            
            $now = new DateTime();
            $is_eligible = $now >= $next_eligible;
            
            $response = [
                'success' => true,
                'data' => [
                    'last_donation_date' => $row['last_donation_date'],
                    'next_eligible_date' => $next_eligible->format('Y-m-d'),
                    'next_eligible_timestamp' => $next_eligible->getTimestamp(),
                    'is_currently_eligible' => $is_eligible,
                    'days_until_eligible' => $is_eligible ? 0 : $now->diff($next_eligible)->days
                ]
            ];
        } else {
            $response = [
                'success' => true,
                'data' => [
                    'last_donation_date' => null,
                    'next_eligible_date' => date('Y-m-d'),
                    'next_eligible_timestamp' => time(),
                    'is_currently_eligible' => true,
                    'days_until_eligible' => 0
                ]
            ];
        }

        echo "✅ Query successful<br>";
        echo "Response: <pre>" . json_encode($response, JSON_PRETTY_PRINT) . "</pre>";
        
    } else {
        echo "❌ Failed to prepare statement: " . $conn->error . "<br>";
    }

} catch (Exception $e) {
    echo "❌ Exception in donation date logic: " . $e->getMessage() . "<br>";
}
?>