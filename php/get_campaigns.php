<?php
/**
 * HopeDrops Blood Bank Management System
 * Campaigns Data Provider
 * 
 * Returns active donation campaigns
 * Created: November 11, 2025
 */

// Use comprehensive API helper to prevent HTML output
require_once 'api_helper.php';
initializeAPI();

require_once 'db_connect.php';

try {
    $db = getDBConnection();
    
    // Get query parameters
    $active = isset($_GET['active']) ? (bool)$_GET['active'] : false;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    // Build query based on parameters
    $whereClause = '';
    $params = [];
    
    if ($active) {
        $whereClause = 'WHERE c.is_active = 1 AND c.end_date >= CURDATE()';
    }
    
    $stmt = $db->prepare("
        SELECT 
            c.*,
            h.hospital_name,
            h.city,
            u.full_name as organizer_name,
            DATEDIFF(c.end_date, CURDATE()) as days_remaining
        FROM campaigns c
        LEFT JOIN hospitals h ON c.hospital_id = h.id
        LEFT JOIN users u ON c.organizer_id = u.id
        {$whereClause}
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt->execute($params);
    $campaigns = $stmt->fetchAll();
    
    // Format dates and add additional info
    foreach ($campaigns as &$campaign) {
        $campaign['start_date'] = formatDate($campaign['start_date'], 'M d, Y');
        $campaign['end_date'] = formatDate($campaign['end_date'], 'M d, Y');
        $campaign['created_at'] = formatDateTime($campaign['created_at'], 'M d, Y g:i A');
        
        // Calculate progress percentage
        if ($campaign['target_units'] > 0) {
            $campaign['progress_percentage'] = min(100, ($campaign['collected_units'] / $campaign['target_units']) * 100);
        } else {
            $campaign['progress_percentage'] = 0;
        }
        
        // Add status based on dates
        $today = new DateTime();
        $startDate = new DateTime($campaign['start_date']);
        $endDate = new DateTime($campaign['end_date']);
        
        if ($today < $startDate) {
            $campaign['status'] = 'upcoming';
        } elseif ($today > $endDate) {
            $campaign['status'] = 'completed';
        } else {
            $campaign['status'] = 'active';
        }
    }
    
    outputJSON([
        'success' => true,
        'message' => 'Campaigns retrieved successfully',
        'data' => $campaigns
    ]);
    
} catch (PDOException $e) {
    error_log("Campaigns database error: " . $e->getMessage());
    handleAPIError('Database error occurred', $e->getMessage());
} catch (Exception $e) {
    error_log("Campaigns error: " . $e->getMessage());
    handleAPIError('An error occurred while retrieving campaigns', $e->getMessage());
}
?>