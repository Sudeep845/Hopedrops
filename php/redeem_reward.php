<?php
/**
 * HopeDrops Blood Bank Management System
 * Redeem Reward API
 * 
 * Allows users to redeem rewards using their points
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
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $itemId = intval($input['item_id'] ?? 0);
    $userId = $_SESSION['user_id'];
    
    if (!$itemId) {
        sendJsonResponse(false, 'Invalid item ID');
        exit;
    }
    
    $db = getDBConnection();
    $db->beginTransaction();
    
    // Get reward item details
    $itemQuery = "
        SELECT id, name, description, points_cost, stock_quantity, is_active
        FROM reward_items 
        WHERE id = ? AND is_active = 1
    ";
    
    $stmt = $db->prepare($itemQuery);
    $stmt->execute([$itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        $db->rollback();
        sendJsonResponse(false, 'Reward item not found or inactive');
        exit;
    }
    
    if ($item['stock_quantity'] <= 0) {
        $db->rollback();
        sendJsonResponse(false, 'This reward is out of stock');
        exit;
    }
    
    // Get user's current points
    $userQuery = "
        SELECT current_points 
        FROM user_rewards 
        WHERE user_id = ?
    ";
    
    $stmt = $db->prepare($userQuery);
    $stmt->execute([$userId]);
    $userRewards = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userRewards) {
        $db->rollback();
        sendJsonResponse(false, 'User rewards not found');
        exit;
    }
    
    $currentPoints = intval($userRewards['current_points']);
    $requiredPoints = intval($item['points_cost']);
    
    if ($currentPoints < $requiredPoints) {
        $db->rollback();
        sendJsonResponse(false, "Insufficient points. You have {$currentPoints} points but need {$requiredPoints} points.");
        exit;
    }
    
    // Deduct points from user
    $newPoints = $currentPoints - $requiredPoints;
    $updatePointsQuery = "
        UPDATE user_rewards 
        SET current_points = ?, last_updated = NOW()
        WHERE user_id = ?
    ";
    
    $stmt = $db->prepare($updatePointsQuery);
    $stmt->execute([$newPoints, $userId]);
    
    // Update item stock
    $updateStockQuery = "
        UPDATE reward_items 
        SET stock_quantity = stock_quantity - 1 
        WHERE id = ?
    ";
    
    $stmt = $db->prepare($updateStockQuery);
    $stmt->execute([$itemId]);
    
    // Record the redemption
    $redeemQuery = "
        INSERT INTO reward_redemptions (user_id, item_id, points_used, redemption_date, status)
        VALUES (?, ?, ?, NOW(), 'completed')
    ";
    
    $stmt = $db->prepare($redeemQuery);
    $stmt->execute([$userId, $itemId, $requiredPoints]);
    
    $redemptionId = $db->lastInsertId();
    
    $db->commit();
    
    sendJsonResponse(true, 'Reward redeemed successfully!', [
        'redemption_id' => $redemptionId,
        'item_name' => $item['name'],
        'points_used' => $requiredPoints,
        'points_remaining' => $newPoints,
        'message' => "Congratulations! You have successfully redeemed '{$item['name']}' for {$requiredPoints} points."
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollback();
    }
    error_log("Redeem reward error: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to redeem reward: ' . $e->getMessage());
}
?>