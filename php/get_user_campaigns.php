<?php
/**
 * HopeDrops Blood Bank Management System
 * Get User Campaigns API
 * 
 * Retrieves upcoming blood donation campaigns that the user might be interested in
 * Based on location, blood type compatibility, and user preferences
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
include_once 'db_connect.php';

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
    
    // Get limit parameter (default 10)
    $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 50) : 10;

    // Include database connection
    include_once 'db_connect.php';
    
    if (!$conn) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
        ]);
        exit();
    }

    // Get user information for campaign matching
    $user = ['city' => 'Dhaka', 'blood_type' => 'O+']; // Default values
    
    try {
        $sql_user = "SELECT city, blood_type FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql_user);
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user_result = $stmt->get_result();
            $user_data = $user_result->fetch_assoc();
            
            if ($user_data) {
                $user = $user_data;
            }
        }
    } catch (Exception $e) {
        // Use default values if query fails
        error_log("User info query failed in campaigns: " . $e->getMessage());
    }

    // Since we don't have a campaigns table yet, we'll create sample data
    // In a real implementation, this would query from a campaigns table
    
    // Create sample campaigns based on user data
    $campaigns = [];
    
    // Sample campaign data - in real implementation, this would come from database
    $sample_campaigns = [
        [
            'id' => 1,
            'title' => 'Emergency Blood Drive - ' . ($user['city'] ?? 'Dhaka'),
            'description' => 'Urgent need for blood donations at local hospitals',
            'location' => ($user['city'] ?? 'Dhaka') . ' Medical Center',
            'date' => date('Y-m-d', strtotime('+3 days')),
            'time' => '09:00 AM - 5:00 PM',
            'blood_types_needed' => ['O+', 'O-', 'A+', 'B+'],
            'urgency' => 'high',
            'expected_donors' => 150,
            'registered_donors' => 87,
            'organizer' => 'Red Cross Bangladesh',
            'contact' => '+880-1234-567890',
            'type' => 'emergency'
        ],
        [
            'id' => 2,
            'title' => 'Monthly Community Blood Camp',
            'description' => 'Regular monthly blood donation camp for community health',
            'location' => 'Community Center, ' . ($user['city'] ?? 'Dhaka'),
            'date' => date('Y-m-d', strtotime('+1 week')),
            'time' => '10:00 AM - 4:00 PM',
            'blood_types_needed' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
            'urgency' => 'medium',
            'expected_donors' => 100,
            'registered_donors' => 45,
            'organizer' => 'Local Health Department',
            'contact' => '+880-1234-567891',
            'type' => 'regular'
        ],
        [
            'id' => 3,
            'title' => 'University Blood Donation Drive',
            'description' => 'Special blood donation campaign for students and faculty',
            'location' => 'University of Dhaka Campus',
            'date' => date('Y-m-d', strtotime('+2 weeks')),
            'time' => '11:00 AM - 6:00 PM',
            'blood_types_needed' => ['O+', 'A+', 'B+'],
            'urgency' => 'low',
            'expected_donors' => 200,
            'registered_donors' => 23,
            'organizer' => 'Student Health Services',
            'contact' => '+880-1234-567892',
            'type' => 'educational'
        ]
    ];

    // Filter campaigns based on user's blood type if available
    $filtered_campaigns = [];
    foreach ($sample_campaigns as $campaign) {
        // Check if user's blood type is needed
        $is_compatible = true;
        if ($user['blood_type'] && !empty($campaign['blood_types_needed'])) {
            $is_compatible = in_array($user['blood_type'], $campaign['blood_types_needed']);
        }
        
        if ($is_compatible) {
            // Add compatibility and priority info
            $campaign['is_compatible'] = true;
            $campaign['user_blood_type'] = $user['blood_type'];
            
            // Calculate days until campaign
            $campaign_date = new DateTime($campaign['date']);
            $now = new DateTime();
            $days_until = $now->diff($campaign_date)->days;
            $campaign['days_until'] = $days_until;
            
            // Add status
            if ($days_until <= 3) {
                $campaign['status'] = 'upcoming';
            } elseif ($days_until <= 7) {
                $campaign['status'] = 'soon';
            } else {
                $campaign['status'] = 'future';
            }
            
            // Calculate registration percentage
            $campaign['registration_percentage'] = round(
                ($campaign['registered_donors'] / $campaign['expected_donors']) * 100, 1
            );
            
            $filtered_campaigns[] = $campaign;
        }
    }

    // Sort campaigns by urgency and date
    usort($filtered_campaigns, function($a, $b) {
        $urgency_priority = ['high' => 3, 'medium' => 2, 'low' => 1];
        $a_priority = $urgency_priority[$a['urgency']] ?? 1;
        $b_priority = $urgency_priority[$b['urgency']] ?? 1;
        
        if ($a_priority === $b_priority) {
            return strtotime($a['date']) - strtotime($b['date']);
        }
        
        return $b_priority - $a_priority;
    });

    // Limit results
    $filtered_campaigns = array_slice($filtered_campaigns, 0, $limit);

    echo json_encode([
        'success' => true,
        'data' => $filtered_campaigns,
        'total_count' => count($filtered_campaigns),
        'user_city' => $user['city'] ?? 'Not specified',
        'user_blood_type' => $user['blood_type'] ?? 'Not specified'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading campaigns: ' . $e->getMessage()
    ]);
}
?>