<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Start output buffering to catch any stray output
ob_start();

try {
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }

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
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }

    // Get campaign ID from POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $campaignId = null;

    if ($input && isset($input['id'])) {
        $campaignId = (int)$input['id'];
    } elseif (isset($_POST['id'])) {
        $campaignId = (int)$_POST['id'];
    }

    if (!$campaignId) {
        throw new Exception('Campaign ID is required');
    }

    // First, check if the campaign exists and get its details
    $stmt = $pdo->prepare("
        SELECT id, activity_data 
        FROM hospital_activities 
        WHERE id = ? AND activity_type = 'campaign_created'
    ");
    $stmt->execute([$campaignId]);
    $campaign = $stmt->fetch();

    if (!$campaign) {
        throw new Exception('Campaign not found');
    }

    // Parse the activity data to get campaign details
    $campaignData = json_decode($campaign['activity_data'], true);
    $imagePath = null;

    if ($campaignData && isset($campaignData['image_path'])) {
        $imagePath = $campaignData['image_path'];
    }

    // Delete the campaign record
    $stmt = $pdo->prepare("
        DELETE FROM hospital_activities 
        WHERE id = ? AND activity_type = 'campaign_created'
    ");
    $result = $stmt->execute([$campaignId]);

    if (!$result || $stmt->rowCount() === 0) {
        throw new Exception('Failed to delete campaign or campaign not found');
    }

    // Delete associated image file if it exists
    if ($imagePath && file_exists($imagePath)) {
        try {
            unlink($imagePath);
        } catch (Exception $e) {
            // Log the error but don't fail the deletion
            error_log("Failed to delete campaign image: " . $e->getMessage());
        }
    }

    // Also delete any related donation records or activities if needed
    // This is optional - you might want to keep donation history for reporting
    /*
    $stmt = $pdo->prepare("
        DELETE FROM donations 
        WHERE campaign_id = ?
    ");
    $stmt->execute([$campaignId]);
    */

    // Clean output buffer and send success response
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Campaign deleted successfully',
        'data' => [
            'campaign_id' => $campaignId,
            'deleted_image' => $imagePath ? basename($imagePath) : null
        ]
    ]);

} catch (Exception $e) {
    // Clean any output and return error
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete campaign: ' . $e->getMessage()
    ]);
}
?>