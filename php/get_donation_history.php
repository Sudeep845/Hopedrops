<?php
/**
 * HopeDrops Blood Bank Management System
 * Get Donation History API
 * 
 * Retrieves donation history for the current user
 * Created: November 12, 2025
 */

require_once 'db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    sendJsonResponse(false, 'Authentication required');
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    $db = getDBConnection();
    
    // Get donation history
    $query = "
        SELECT 
            d.id,
            d.donation_date,
            d.donation_time,
            d.blood_type,
            d.units_donated,
            d.notes,
            d.status,
            d.hemoglobin_level,
            d.weight,
            d.blood_pressure,
            d.health_status,
            d.certificate_generated,
            h.hospital_name,
            h.address as hospital_address,
            h.city as hospital_city,
            h.contact_phone as hospital_phone
        FROM donations d
        LEFT JOIN hospitals h ON d.hospital_id = h.id
        WHERE d.donor_id = ?
        ORDER BY d.donation_date DESC, d.donation_time DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$userId]);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary statistics
    $summaryQuery = "
        SELECT 
            COUNT(*) as total_donations,
            SUM(units_donated) as total_units,
            MIN(donation_date) as first_donation,
            MAX(donation_date) as last_donation,
            AVG(units_donated) as avg_units_per_donation
        FROM donations 
        WHERE donor_id = ? AND status = 'completed'
    ";
    
    $stmt = $db->prepare($summaryQuery);
    $stmt->execute([$userId]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get monthly donation counts for chart
    $monthlyQuery = "
        SELECT 
            DATE_FORMAT(donation_date, '%Y-%m') as month,
            COUNT(*) as donation_count,
            SUM(units_donated) as units_donated
        FROM donations 
        WHERE donor_id = ? AND status = 'completed'
        AND donation_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(donation_date, '%Y-%m')
        ORDER BY month DESC
    ";
    
    $stmt = $db->prepare($monthlyQuery);
    $stmt->execute([$userId]);
    $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format donations for display
    foreach ($donations as &$donation) {
        $donation['donation_date_formatted'] = date('M j, Y', strtotime($donation['donation_date']));
        $donation['donation_datetime_formatted'] = date('M j, Y g:i A', strtotime($donation['donation_date'] . ' ' . $donation['donation_time']));
        $donation['units_donated'] = intval($donation['units_donated']);
        $donation['hemoglobin_level'] = $donation['hemoglobin_level'] ? floatval($donation['hemoglobin_level']) : null;
        $donation['weight'] = $donation['weight'] ? floatval($donation['weight']) : null;
        $donation['certificate_generated'] = (bool)$donation['certificate_generated'];
        
        // Create donation location from hospital info
        $donation['donation_location'] = $donation['hospital_name'];
        if ($donation['hospital_address']) {
            $donation['donation_location'] .= ' - ' . $donation['hospital_address'];
        }
    }
    
    // Calculate next eligible donation date
    $nextEligibleDate = null;
    if (!empty($donations)) {
        $lastDonation = $donations[0]['donation_date'];
        $nextEligibleDate = date('Y-m-d', strtotime($lastDonation . ' +56 days'));
    }
    
    $responseData = [
        'donations' => $donations,
        'summary' => [
            'total_donations' => intval($summary['total_donations'] ?? 0),
            'total_units' => intval($summary['total_units'] ?? 0),
            'first_donation' => $summary['first_donation'],
            'last_donation' => $summary['last_donation'],
            'avg_units_per_donation' => round(floatval($summary['avg_units_per_donation'] ?? 0), 1),
            'next_eligible_date' => $nextEligibleDate
        ],
        'monthly_data' => $monthlyData
    ];
    
    sendJsonResponse(true, 'Donation history retrieved successfully', $responseData);
    
} catch (Exception $e) {
    error_log("Get donation history error: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to retrieve donation history: ' . $e->getMessage());
}
?>