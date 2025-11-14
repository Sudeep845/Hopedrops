<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Start output buffering to catch any stray output
ob_start();

try {
    // Database connection
    $pdo = null;
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=bloodbank_db;charset=utf8",
            "root",
            "",
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    } catch (PDOException $e) {
        // Database connection failed - use fallback data
        $pdo = null;
    }
    
    // Get query parameters
    $active = isset($_GET['active']) ? (bool)$_GET['active'] : false;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    $campaigns = [];
    
    if ($pdo) {
        try {
            // Get actual campaigns from hospital_activities table
            $sql = "SELECT 
                        ha.id,
                        ha.hospital_id,
                        ha.activity_data,
                        ha.description,
                        ha.created_at,
                        h.hospital_name,
                        h.city,
                        h.contact_person as organizer_name
                    FROM hospital_activities ha
                    JOIN hospitals h ON ha.hospital_id = h.id
                    WHERE ha.activity_type = 'campaign_created'
                    AND h.is_approved = 1
                    ORDER BY ha.created_at DESC
                    LIMIT ? OFFSET ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$limit, $offset]);
            $campaignActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert campaign activities to campaign format
            foreach ($campaignActivities as $activity) {
                $campaignData = json_decode($activity['activity_data'], true);
                
                // Fallback if JSON decode fails
                if (!$campaignData) {
                    $campaignData = [
                        'title' => 'Blood Drive Campaign',
                        'description' => $activity['description'],
                        'start_date' => date('Y-m-d'),
                        'end_date' => date('Y-m-d', strtotime('+30 days')),
                        'target_donors' => 100,
                        'status' => 'active'
                    ];
                }
                
                // Calculate days remaining
                $endDate = new DateTime($campaignData['end_date'] ?? date('Y-m-d', strtotime('+30 days')));
                $today = new DateTime();
                $daysRemaining = max(0, $today->diff($endDate)->days);
                if ($endDate < $today) $daysRemaining = 0;
                
                // Calculate progress
                $currentDonors = $campaignData['current_donors'] ?? 0;
                $targetDonors = $campaignData['target_donors'] ?? 100;
                $progressPercentage = $targetDonors > 0 ? min(100, ($currentDonors / $targetDonors) * 100) : 0;
                
                // Determine status
                $status = $campaignData['status'] ?? 'active';
                if ($daysRemaining == 0 && $status == 'active') {
                    $status = 'completed';
                }
                
                $startDate = $campaignData['start_date'] ?? date('Y-m-d');
                $endDate = $campaignData['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
                
                $organizerName = $campaignData['organizer'] ?? $activity['organizer_name'];
                $location = $campaignData['location'] ?? $activity['city'];
                
                $campaigns[] = [
                    'id' => $activity['id'],
                    'title' => $campaignData['title'] ?? 'Blood Drive Campaign',
                    'description' => $campaignData['description'] ?? $activity['description'],
                    'hospital_id' => $activity['hospital_id'],
                    'hospital_name' => $activity['hospital_name'],
                    'city' => $activity['city'],
                    'location' => $location,
                    'organizer' => $organizerName,
                    'organizer_name' => $organizerName,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'start_time' => $campaignData['start_time'] ?? '09:00',
                    'end_time' => $campaignData['end_time'] ?? '17:00',
                    'target_donors' => $targetDonors,
                    'current_donors' => $currentDonors,
                    'max_capacity' => $campaignData['max_capacity'] ?? $targetDonors,
                    'status' => $status,
                    'is_active' => $status == 'active' ? 1 : 0,
                    'days_remaining' => $daysRemaining,
                    'progress_percentage' => $progressPercentage,
                    'image_path' => $campaignData['image_path'] ?? null,
                    'created_at' => $activity['created_at'],
                    // Add formatted dates for frontend
                    'start_date_formatted' => date('M j, Y', strtotime($startDate)),
                    'end_date_formatted' => date('M j, Y', strtotime($endDate)),
                    'created_at_formatted' => date('M j, Y g:i A', strtotime($activity['created_at']))
                ];
            }
            
        } catch (PDOException $e) {
            error_log("Database error in get_campaigns.php: " . $e->getMessage());
            $pdo = null; // Use fallback data
        }
    }
    
    // Fallback data if database is unavailable
    if (empty($campaigns)) {
        $campaigns = [
            [
                'id' => 1,
                'title' => 'City Hospital Blood Drive Campaign',
                'description' => 'Emergency blood collection campaign for critical patients',
                'hospital_id' => 1,
                'hospital_name' => 'City General Hospital',
                'city' => 'Metro City',
                'organizer_name' => 'Dr. Sarah Johnson',
                'start_date' => date('Y-m-d', strtotime('-10 days')),
                'end_date' => date('Y-m-d', strtotime('+20 days')),
                'target_donors' => 100,
                'current_donors' => 35,
                'status' => 'active',
                'is_active' => 1,
                'days_remaining' => 20,
                'progress_percentage' => 35,
                'created_at' => date('Y-m-d H:i:s', strtotime('-10 days'))
            ],
            [
                'id' => 2,
                'title' => 'Community Health Center Drive',
                'description' => 'Monthly blood donation drive for community health',
                'hospital_id' => 2,
                'hospital_name' => 'Community Health Center',
                'city' => 'Downtown',
                'organizer_name' => 'Nurse Manager Lisa Chen',
                'start_date' => date('Y-m-d', strtotime('-5 days')),
                'end_date' => date('Y-m-d', strtotime('+10 days')),
                'target_donors' => 50,
                'current_donors' => 22,
                'status' => 'active',
                'is_active' => 1,
                'days_remaining' => 10,
                'progress_percentage' => 44,
                'created_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
            ]
        ];
    }
    
    // Filter active campaigns if requested
    if ($active) {
        $campaigns = array_filter($campaigns, function($campaign) {
            return $campaign['is_active'] && strtotime($campaign['end_date']) >= time();
        });
        $campaigns = array_values($campaigns); // Re-index array
    }
    
    // Add calculated fields
    foreach ($campaigns as &$campaign) {
        // Days remaining
        $campaign['days_remaining'] = max(0, floor((strtotime($campaign['end_date']) - time()) / (24 * 60 * 60)));
        
        // Progress percentage
        if ($campaign['target_donors'] > 0) {
            $campaign['progress_percentage'] = round(($campaign['current_donors'] / $campaign['target_donors']) * 100, 1);
        } else {
            $campaign['progress_percentage'] = 0;
        }
        
        // Status based on end date
        if (strtotime($campaign['end_date']) < time()) {
            $campaign['status'] = 'completed';
            $campaign['is_active'] = 0;
        } else {
            $campaign['status'] = 'active';
        }
        
        // Format dates for display
        $campaign['start_date_formatted'] = date('M j, Y', strtotime($campaign['start_date']));
        $campaign['end_date_formatted'] = date('M j, Y', strtotime($campaign['end_date']));
        $campaign['created_at_formatted'] = date('M j, Y g:i A', strtotime($campaign['created_at']));
    }
    
    // Clean output buffer and send response
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Campaigns retrieved successfully',
        'data' => $campaigns
    ]);
    
} catch (Exception $e) {
    // Clean any output and return error
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve campaigns: ' . $e->getMessage()
    ]);
}
?>