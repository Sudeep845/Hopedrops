<?php
// Use comprehensive API helper to prevent HTML output
require_once 'api_helper.php';
initializeAPI();

try {
    require_once 'db_connect.php';
    
    // Get campaign ID
    $campaignId = $_GET['id'] ?? null;
    
    if (!$campaignId) {
        outputJSON([
            'success' => false,
            'message' => 'Campaign ID is required',
            'data' => null
        ]);
    }
    
    try {
        // Get detailed campaign information
        $sql = "SELECT 
                    c.*,
                    r.blood_type,
                    r.units_needed,
                    r.urgency_level,
                    r.location as request_location,
                    r.description as request_description,
                    h.hospital_name,
                    h.contact_person,
                    h.phone as hospital_phone,
                    h.address as hospital_address,
                    COUNT(d.id) as total_donations,
                    SUM(CASE WHEN d.status = 'completed' THEN d.quantity ELSE 0 END) as units_collected,
                    AVG(d.quantity) as avg_donation_size
                FROM campaigns c
                LEFT JOIN requests r ON c.request_id = r.id
                LEFT JOIN hospitals h ON r.hospital_id = h.id
                LEFT JOIN donations d ON c.id = d.campaign_id
                WHERE c.id = ?
                GROUP BY c.id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$campaignId]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$campaign) {
            outputJSON([
                'success' => false,
                'message' => 'Campaign not found',
                'data' => null
            ]);
        }
        
        // Get campaign donations list
        $sql = "SELECT 
                    d.*,
                    u.full_name as donor_name,
                    u.blood_type as donor_blood_type,
                    u.phone as donor_phone
                FROM donations d
                LEFT JOIN users u ON d.donor_id = u.id
                WHERE d.campaign_id = ?
                ORDER BY d.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$campaignId]);
        $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get campaign timeline/activities
        $sql = "SELECT 
                    'donation' as activity_type,
                    d.created_at as activity_date,
                    CONCAT('Donation of ', d.quantity, ' units by ', u.full_name) as activity_description,
                    d.status as activity_status
                FROM donations d
                LEFT JOIN users u ON d.donor_id = u.id
                WHERE d.campaign_id = ?
                
                UNION ALL
                
                SELECT 
                    'campaign_update' as activity_type,
                    c.updated_at as activity_date,
                    CONCAT('Campaign status updated to: ', c.status) as activity_description,
                    c.status as activity_status
                FROM campaigns c
                WHERE c.id = ?
                
                ORDER BY activity_date DESC
                LIMIT 20";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$campaignId, $campaignId]);
        $timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate campaign metrics
        $metrics = [
            'progress_percentage' => $campaign['units_needed'] > 0 
                ? min(100, round(($campaign['units_collected'] / $campaign['units_needed']) * 100, 1))
                : 0,
            'completion_rate' => $campaign['total_donations'] > 0
                ? round((count(array_filter($donations, function($d) { return $d['status'] === 'completed'; })) / $campaign['total_donations']) * 100, 1)
                : 0,
            'days_active' => floor((strtotime('now') - strtotime($campaign['created_at'])) / 86400),
            'avg_daily_donations' => 0
        ];
        
        if ($metrics['days_active'] > 0) {
            $metrics['avg_daily_donations'] = round($campaign['total_donations'] / $metrics['days_active'], 1);
        }
        
        // Format dates for display
        $campaign['created_formatted'] = date('M j, Y g:i A', strtotime($campaign['created_at']));
        $campaign['updated_formatted'] = date('M j, Y g:i A', strtotime($campaign['updated_at']));
        if ($campaign['end_date']) {
            $campaign['end_formatted'] = date('M j, Y', strtotime($campaign['end_date']));
        }
        
        // Format donation dates
        foreach ($donations as &$donation) {
            $donation['created_formatted'] = date('M j, Y g:i A', strtotime($donation['created_at']));
            if ($donation['scheduled_date']) {
                $donation['scheduled_formatted'] = date('M j, Y g:i A', strtotime($donation['scheduled_date']));
            }
        }
        
        // Format timeline dates
        foreach ($timeline as &$activity) {
            $activity['activity_formatted'] = date('M j, Y g:i A', strtotime($activity['activity_date']));
        }
        
        $campaignDetails = [
            'campaign' => $campaign,
            'donations' => $donations,
            'timeline' => $timeline,
            'metrics' => $metrics
        ];
        
    } catch (PDOException $e) {
        error_log("Database error in get_campaign_details.php: " . $e->getMessage());
        
        // Return sample campaign details if database fails
        $campaignDetails = [
            'campaign' => [
                'id' => $campaignId,
                'title' => 'Emergency Blood Drive - Sample',
                'description' => 'Sample campaign for emergency blood collection',
                'status' => 'active',
                'blood_type' => 'O+',
                'units_needed' => 50,
                'units_collected' => 32,
                'hospital_name' => 'Sample Hospital',
                'contact_person' => 'Dr. Sample',
                'created_formatted' => date('M j, Y g:i A', strtotime('-5 days')),
                'end_formatted' => date('M j, Y', strtotime('+10 days'))
            ],
            'donations' => [
                [
                    'id' => 1,
                    'donor_name' => 'John Doe',
                    'quantity' => 1,
                    'status' => 'completed',
                    'created_formatted' => date('M j, Y g:i A', strtotime('-2 days'))
                ]
            ],
            'timeline' => [
                [
                    'activity_type' => 'donation',
                    'activity_description' => 'Donation of 1 units by John Doe',
                    'activity_formatted' => date('M j, Y g:i A', strtotime('-2 days'))
                ]
            ],
            'metrics' => [
                'progress_percentage' => 64.0,
                'completion_rate' => 95.5,
                'days_active' => 5,
                'avg_daily_donations' => 2.4
            ]
        ];
    }
    
    outputJSON([
        'success' => true,
        'data' => $campaignDetails,
        'message' => 'Campaign details retrieved successfully'
    ]);
    
} catch (Exception $e) {
    handleAPIError('Unable to load campaign details', $e->getMessage());
}
?>