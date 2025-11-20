<?php
/**
 * HopeDrops Blood Bank Management System
 * Donor Dashboard Dynamic Data API
 * 
 * Fetches comprehensive data from hospitals, admin, and emergency requests
 * for the donor dashboard
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';

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
    $db = getDBConnection();

    // Get user's blood type and location
    $stmt = $db->prepare("SELECT blood_type, city, state FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit();
    }

    $userBloodType = $user['blood_type'];
    $userCity = $user['city'];

    // Initialize response data
    $dashboardData = [
        'success' => true,
        'emergency_requests' => [],
        'nearby_hospitals' => [],
        'blood_availability' => [],
        'active_campaigns' => [],
        'system_alerts' => []
    ];

    // 1. Get Emergency Blood Requests (Critical and High Priority)
    try {
        $emergencyStmt = $db->prepare("
            SELECT 
                r.id,
                r.blood_type,
                r.units_needed,
                r.urgency_level,
                r.status,
                r.description as notes,
                r.location,
                r.contact_person,
                r.phone,
                r.created_at,
                h.hospital_name,
                h.city,
                h.contact_phone as hospital_phone
            FROM requests r
            LEFT JOIN hospitals h ON r.hospital_id = h.id
            WHERE r.urgency_level IN ('critical', 'emergency', 'high')
            AND r.status = 'pending'
            AND r.blood_type = ?
            ORDER BY 
                CASE r.urgency_level 
                    WHEN 'critical' THEN 1
                    WHEN 'emergency' THEN 2
                    WHEN 'high' THEN 3
                    ELSE 4
                END,
                r.created_at DESC
            LIMIT 5
        ");
        $emergencyStmt->execute([$userBloodType]);
        $dashboardData['emergency_requests'] = $emergencyStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Emergency requests error: " . $e->getMessage());
    }

    // 2. Get Nearby Hospitals with Blood Inventory
    try {
        $hospitalStmt = $db->prepare("
            SELECT 
                h.id,
                h.hospital_name,
                h.address,
                h.city,
                h.state,
                h.contact_phone,
                h.email,
                h.latitude,
                h.longitude,
                (
                    SELECT GROUP_CONCAT(
                        CONCAT(bi.blood_type, ':', bi.units_available) 
                        SEPARATOR '|'
                    )
                    FROM blood_inventory bi 
                    WHERE bi.hospital_id = h.id 
                    AND bi.units_available > 0
                ) as available_blood_types,
                (
                    SELECT bi.units_available
                    FROM blood_inventory bi
                    WHERE bi.hospital_id = h.id
                    AND bi.blood_type = ?
                ) as user_blood_available
            FROM hospitals h
            WHERE h.is_approved = 1
            AND h.is_active = 1
            AND (h.city = ? OR h.state = ?)
            ORDER BY h.city = ? DESC, h.hospital_name
            LIMIT 10
        ");
        $hospitalStmt->execute([$userBloodType, $userCity, $user['state'], $userCity]);
        $hospitals = $hospitalStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format blood inventory data
        foreach ($hospitals as &$hospital) {
            $inventory = [];
            if ($hospital['available_blood_types']) {
                $bloodTypes = explode('|', $hospital['available_blood_types']);
                foreach ($bloodTypes as $bt) {
                    list($type, $units) = explode(':', $bt);
                    $inventory[$type] = (int)$units;
                }
            }
            $hospital['blood_inventory'] = $inventory;
            unset($hospital['available_blood_types']);
        }
        
        $dashboardData['nearby_hospitals'] = $hospitals;
    } catch (Exception $e) {
        error_log("Nearby hospitals error: " . $e->getMessage());
    }

    // 3. Get Blood Availability Statistics
    try {
        $availabilityStmt = $db->prepare("
            SELECT 
                bi.blood_type,
                SUM(bi.units_available) as total_units,
                COUNT(DISTINCT h.id) as hospital_count,
                AVG(bi.units_available) as avg_units_per_hospital
            FROM blood_inventory bi
            JOIN hospitals h ON bi.hospital_id = h.id
            WHERE h.is_approved = 1
            AND h.is_active = 1
            GROUP BY bi.blood_type
            ORDER BY bi.blood_type
        ");
        $availabilityStmt->execute();
        $dashboardData['blood_availability'] = $availabilityStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Blood availability error: " . $e->getMessage());
    }

    // 4. Get Active Blood Donation Campaigns
    try {
        $campaignsStmt = $db->prepare("
            SELECT 
                ha.id,
                ha.hospital_id,
                ha.activity_data,
                ha.description,
                ha.created_at,
                h.hospital_name,
                h.city,
                h.contact_person,
                h.contact_phone
            FROM hospital_activities ha
            JOIN hospitals h ON ha.hospital_id = h.id
            WHERE ha.activity_type = 'campaign_created'
            AND h.is_approved = 1
            AND h.is_active = 1
            ORDER BY ha.created_at DESC
            LIMIT 5
        ");
        $campaignsStmt->execute();
        $campaigns = $campaignsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format campaign data
        foreach ($campaigns as &$campaign) {
            $campaignData = json_decode($campaign['activity_data'], true);
            if ($campaignData) {
                $campaign['title'] = $campaignData['title'] ?? 'Blood Donation Campaign';
                $campaign['start_date'] = $campaignData['start_date'] ?? date('Y-m-d');
                $campaign['end_date'] = $campaignData['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
                $campaign['target_donors'] = $campaignData['target_donors'] ?? 100;
                $campaign['current_donors'] = $campaignData['current_donors'] ?? 0;
                $campaign['status'] = $campaignData['status'] ?? 'active';
                
                // Calculate days remaining
                $endDate = new DateTime($campaign['end_date']);
                $today = new DateTime();
                $campaign['days_remaining'] = max(0, $today->diff($endDate)->days);
                if ($endDate < $today) $campaign['days_remaining'] = 0;
            } else {
                $campaign['title'] = 'Blood Donation Drive';
                $campaign['start_date'] = date('Y-m-d');
                $campaign['end_date'] = date('Y-m-d', strtotime('+30 days'));
                $campaign['days_remaining'] = 30;
            }
        }
        
        $dashboardData['active_campaigns'] = $campaigns;
    } catch (Exception $e) {
        error_log("Campaigns error: " . $e->getMessage());
    }

    // 5. Get System Alerts and Notifications
    try {
        // Check for critical blood shortages
        $shortageStmt = $db->prepare("
            SELECT 
                bi.blood_type,
                bi.units_available,
                bi.units_required,
                h.hospital_name,
                h.city
            FROM blood_inventory bi
            JOIN hospitals h ON bi.hospital_id = h.id
            WHERE h.is_approved = 1
            AND bi.units_available < bi.units_required
            AND bi.blood_type = ?
            ORDER BY (bi.units_required - bi.units_available) DESC
            LIMIT 3
        ");
        $shortageStmt->execute([$userBloodType]);
        $shortages = $shortageStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($shortages as $shortage) {
            $deficit = $shortage['units_required'] - $shortage['units_available'];
            $dashboardData['system_alerts'][] = [
                'type' => 'critical',
                'title' => 'Blood Shortage Alert',
                'message' => "{$shortage['hospital_name']} in {$shortage['city']} needs {$deficit} units of {$shortage['blood_type']}",
                'blood_type' => $shortage['blood_type'],
                'severity' => 'high'
            ];
        }
        
        // Check for upcoming campaigns in user's city
        foreach ($dashboardData['active_campaigns'] as $campaign) {
            if ($campaign['city'] == $userCity) {
                $dashboardData['system_alerts'][] = [
                    'type' => 'info',
                    'title' => 'Nearby Campaign',
                    'message' => "{$campaign['title']} at {$campaign['hospital_name']} - {$campaign['days_remaining']} days left",
                    'severity' => 'info'
                ];
            }
        }
    } catch (Exception $e) {
        error_log("System alerts error: " . $e->getMessage());
    }

    // Add metadata
    $dashboardData['metadata'] = [
        'fetched_at' => date('Y-m-d H:i:s'),
        'user_blood_type' => $userBloodType,
        'user_location' => $userCity
    ];

    echo json_encode($dashboardData);

} catch (Exception $e) {
    error_log("Donor dashboard data error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch dashboard data',
        'error' => $e->getMessage()
    ]);
}
?>
