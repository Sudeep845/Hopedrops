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
        throw new Exception('Database connection failed');
    }
    
    // Get campaign ID
    $campaignId = $_GET['id'] ?? null;
    
    if (!$campaignId) {
        throw new Exception('Campaign ID is required');
    }
    
    // Get campaign details from hospital_activities table
    $sql = "SELECT 
                ha.id,
                ha.hospital_id,
                ha.activity_data,
                ha.description,
                ha.created_at,
                h.hospital_name,
                h.city,
                h.contact_person,
                h.contact_phone,
                h.contact_email,
                h.address as hospital_address
            FROM hospital_activities ha
            JOIN hospitals h ON ha.hospital_id = h.id
            WHERE ha.id = ? AND ha.activity_type = 'campaign_created'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$campaignId]);
    $campaignActivity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$campaignActivity) {
        throw new Exception('Campaign not found');
    }
    
    // Parse campaign data from JSON
    $campaignData = json_decode($campaignActivity['activity_data'], true);
    if (!$campaignData) {
        throw new Exception('Invalid campaign data');
    }
    
    // Calculate campaign metrics
    $startDate = new DateTime($campaignData['start_date'] ?? date('Y-m-d'));
    $endDate = new DateTime($campaignData['end_date'] ?? date('Y-m-d', strtotime('+30 days')));
    $today = new DateTime();
    
    $daysActive = max(1, $today->diff($startDate)->days);
    $daysRemaining = max(0, $today->diff($endDate)->days);
    if ($endDate < $today) $daysRemaining = 0;
    
    $currentDonors = $campaignData['current_donors'] ?? 0;
    $targetDonors = $campaignData['target_donors'] ?? 100;
    $progressPercentage = $targetDonors > 0 ? min(100, ($currentDonors / $targetDonors) * 100) : 0;
    
    // Get related donations for this campaign (if any)
    $donationsSql = "SELECT 
                        d.id,
                        d.donor_id,
                        d.blood_type,
                        d.units_donated as quantity,
                        d.status,
                        d.donation_date,
                        d.created_at,
                        u.full_name as donor_name,
                        u.phone as donor_phone,
                        u.blood_type as donor_blood_type
                    FROM donations d
                    LEFT JOIN users u ON d.donor_id = u.id
                    WHERE d.hospital_id = ?
                    ORDER BY d.created_at DESC
                    LIMIT 20";
    
    $stmt = $pdo->prepare($donationsSql);
    $stmt->execute([$campaignActivity['hospital_id']]);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format donation data
    foreach ($donations as &$donation) {
        $donation['created_formatted'] = date('M j, Y g:i A', strtotime($donation['created_at']));
        $donation['donation_date_formatted'] = $donation['donation_date'] 
            ? date('M j, Y', strtotime($donation['donation_date'])) 
            : null;
    }
    
    // Build campaign timeline
    $timeline = [];
    
    // Add campaign creation to timeline
    $timeline[] = [
        'activity_type' => 'campaign_created',
        'activity_description' => 'Campaign created: ' . ($campaignData['title'] ?? 'Blood Drive Campaign'),
        'activity_date' => $campaignActivity['created_at'],
        'activity_formatted' => date('M j, Y g:i A', strtotime($campaignActivity['created_at']))
    ];
    
    // Add donations to timeline
    foreach (array_slice($donations, 0, 10) as $donation) {
        $timeline[] = [
            'activity_type' => 'donation',
            'activity_description' => "Donation of {$donation['quantity']} units by {$donation['donor_name']}",
            'activity_date' => $donation['created_at'],
            'activity_formatted' => $donation['created_formatted'],
            'activity_status' => $donation['status']
        ];
    }
    
    // Sort timeline by date (newest first)
    usort($timeline, function($a, $b) {
        return strtotime($b['activity_date']) - strtotime($a['activity_date']);
    });
    
    // Build complete campaign details
    $campaignDetails = [
        'id' => $campaignActivity['id'],
        'title' => $campaignData['title'] ?? 'Blood Drive Campaign',
        'description' => $campaignData['description'] ?? $campaignActivity['description'],
        'status' => $daysRemaining > 0 ? 'active' : 'completed',
        'location' => $campaignData['location'] ?? $campaignActivity['city'],
        'organizer' => $campaignData['organizer'] ?? $campaignActivity['contact_person'],
        'start_date' => $campaignData['start_date'] ?? date('Y-m-d'),
        'end_date' => $campaignData['end_date'] ?? date('Y-m-d', strtotime('+30 days')),
        'start_time' => $campaignData['start_time'] ?? '09:00',
        'end_time' => $campaignData['end_time'] ?? '17:00',
        'target_donors' => $targetDonors,
        'current_donors' => $currentDonors,
        'max_capacity' => $campaignData['max_capacity'] ?? $targetDonors,
        'image_path' => $campaignData['image_path'] ?? null,
        'hospital_id' => $campaignActivity['hospital_id'],
        'hospital_name' => $campaignActivity['hospital_name'],
        'hospital_address' => $campaignActivity['hospital_address'],
        'contact_person' => $campaignActivity['contact_person'],
        'contact_phone' => $campaignActivity['contact_phone'],
        'contact_email' => $campaignActivity['contact_email'],
        'created_at' => $campaignActivity['created_at'],
        'created_formatted' => date('M j, Y g:i A', strtotime($campaignActivity['created_at'])),
        'start_date_formatted' => date('M j, Y', strtotime($campaignData['start_date'] ?? date('Y-m-d'))),
        'end_date_formatted' => date('M j, Y', strtotime($campaignData['end_date'] ?? date('Y-m-d', strtotime('+30 days')))),
        'progress_percentage' => $progressPercentage,
        'days_active' => $daysActive,
        'days_remaining' => $daysRemaining,
        'total_donations' => count($donations),
        'completed_donations' => count(array_filter($donations, function($d) { return $d['status'] === 'completed'; }))
    ];
    
    $metrics = [
        'progress_percentage' => $progressPercentage,
        'completion_rate' => count($donations) > 0 
            ? round(($campaignDetails['completed_donations'] / count($donations)) * 100, 1)
            : 0,
        'days_active' => $daysActive,
        'days_remaining' => $daysRemaining,
        'avg_daily_donations' => $daysActive > 0 ? round(count($donations) / $daysActive, 1) : 0
    ];
    
    $result = [
        'campaign' => $campaignDetails,
        'donations' => $donations,
        'timeline' => $timeline,
        'metrics' => $metrics
    ];
    
    // Clean output buffer and send response
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => $result,
        'message' => 'Campaign details retrieved successfully'
    ]);
    
} catch (Exception $e) {
    // Clean any output and return error
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve campaign details: ' . $e->getMessage()
    ]);
}
?>