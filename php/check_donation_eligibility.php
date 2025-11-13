<?php
/**
 * HopeDrops Blood Bank Management System
 * Check Donation Eligibility API
 * 
 * Checks if a user is eligible to donate blood based on:
 * - Last donation date (56 days interval)
 * - Age requirements (18-65 years)
 * - Medical conditions
 * - General health status
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'User not authenticated'
        ]);
        exit();
    }

    $user_id = $_SESSION['user_id'];

    // Include database connection
    include_once 'db_connect.php';
    
    if (!$conn) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
        ]);
        exit();
    }

    // Get user information
    $sql = "SELECT u.*, ur.donations_count, ur.last_updated as last_reward_update
            FROM users u 
            LEFT JOIN user_rewards ur ON u.id = ur.user_id 
            WHERE u.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit();
    }

    // Initialize eligibility check results
    $eligibility = [
        'is_eligible' => true,
        'reasons' => [],
        'requirements' => []
    ];

    // Check age requirement (18-65 years)
    if ($user['date_of_birth']) {
        $dob = new DateTime($user['date_of_birth']);
        $now = new DateTime();
        $age = $now->diff($dob)->y;
        
        if ($age < 18) {
            $eligibility['is_eligible'] = false;
            $eligibility['reasons'][] = 'Must be at least 18 years old';
        } elseif ($age > 65) {
            $eligibility['is_eligible'] = false;
            $eligibility['reasons'][] = 'Must be under 65 years old';
        } else {
            $eligibility['requirements'][] = "Age: $age years ✓";
        }
    } else {
        $eligibility['reasons'][] = 'Date of birth required for eligibility check';
    }

    // Check if user is marked as eligible in database
    if (!$user['is_eligible']) {
        $eligibility['is_eligible'] = false;
        $eligibility['reasons'][] = 'Account marked as ineligible';
    }

    // Check last donation date (56 days interval)
    $sql_last_donation = "SELECT MAX(donation_date) as last_donation_date 
                          FROM donations 
                          WHERE donor_id = ? AND status = 'completed'";
    
    $stmt = $conn->prepare($sql_last_donation);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $donation_row = $result->fetch_assoc();

    if ($donation_row && $donation_row['last_donation_date']) {
        $last_donation = new DateTime($donation_row['last_donation_date']);
        $next_eligible = clone $last_donation;
        $next_eligible->add(new DateInterval('P56D')); // Add 56 days
        
        $now = new DateTime();
        if ($now < $next_eligible) {
            $days_remaining = $now->diff($next_eligible)->days;
            $eligibility['is_eligible'] = false;
            $eligibility['reasons'][] = "Must wait $days_remaining more days since last donation";
        } else {
            $eligibility['requirements'][] = "Donation interval: OK ✓";
        }
    } else {
        $eligibility['requirements'][] = "First time donor: OK ✓";
    }

    // Check medical conditions
    if (!empty($user['medical_conditions']) && $user['medical_conditions'] !== 'None') {
        $eligibility['reasons'][] = 'Medical conditions may affect eligibility - consult with medical staff';
    } else {
        $eligibility['requirements'][] = "Medical conditions: None ✓";
    }

    // Check if blood type is set
    if (empty($user['blood_type'])) {
        $eligibility['reasons'][] = 'Blood type must be specified';
    } else {
        $eligibility['requirements'][] = "Blood type: {$user['blood_type']} ✓";
    }

    // Prepare response
    $response = [
        'success' => true,
        'data' => [
            'user_id' => $user_id,
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'blood_type' => $user['blood_type'],
            'total_donations' => $user['donations_count'] ?? 0,
            'eligibility' => $eligibility,
            'last_donation_date' => $donation_row['last_donation_date'] ?? null
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error checking donation eligibility: ' . $e->getMessage()
    ]);
}
?>