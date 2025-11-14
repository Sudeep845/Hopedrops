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

    // Validate required fields
    $requiredFields = ['title', 'description', 'start_date', 'end_date', 'target_donors'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }

    // Get form data
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $startTime = $_POST['start_time'] ?? '09:00';
    $endTime = $_POST['end_time'] ?? '17:00';
    $targetDonors = (int)$_POST['target_donors'];
    $maxCapacity = isset($_POST['max_capacity']) ? (int)$_POST['max_capacity'] : $targetDonors;
    $organizer = $_POST['organizer'] ?? 'Admin';

    // Validate dates
    $startDateTime = new DateTime($startDate . ' ' . $startTime);
    $endDateTime = new DateTime($endDate . ' ' . $endTime);
    $now = new DateTime();

    if ($startDateTime < $now) {
        throw new Exception('Start date cannot be in the past');
    }

    if ($endDateTime <= $startDateTime) {
        throw new Exception('End date must be after start date');
    }

    if ($targetDonors < 1) {
        throw new Exception('Target donors must be at least 1');
    }

    // Handle image upload (optional)
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/campaigns/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $fileType = $_FILES['image']['type'];

        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception('Invalid image type. Only JPG, PNG, and GIF are allowed.');
        }

        if ($_FILES['image']['size'] > 5 * 1024 * 1024) { // 5MB limit
            throw new Exception('Image file is too large. Maximum size is 5MB.');
        }

        $fileName = uniqid('campaign_') . '.' . pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $imagePath = $uploadDir . $fileName;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $imagePath)) {
            throw new Exception('Failed to upload image');
        }

        // Store relative path for database
        $imagePath = 'uploads/campaigns/' . $fileName;
    }

    // Get admin hospital ID (for demo purposes, we'll use the first approved hospital)
    $stmt = $pdo->query("SELECT id FROM hospitals WHERE is_approved = 1 ORDER BY id LIMIT 1");
    $hospital = $stmt->fetch();
    
    if (!$hospital) {
        throw new Exception('No approved hospital found. Cannot create campaign.');
    }

    $hospitalId = $hospital['id'];

    // Insert campaign (we'll simulate this by creating a hospital activity)
    $stmt = $pdo->prepare("
        INSERT INTO hospital_activities 
        (hospital_id, user_id, activity_type, activity_data, description, created_at) 
        VALUES (?, NULL, ?, ?, ?, NOW())
    ");

    $activityData = json_encode([
        'title' => $title,
        'description' => $description,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'target_donors' => $targetDonors,
        'max_capacity' => $maxCapacity,
        'organizer' => $organizer,
        'image_path' => $imagePath,
        'status' => 'active',
        'current_donors' => 0,
        'campaign_type' => 'blood_drive'
    ]);

    $activityDescription = "Campaign: $title - $description (Target: $targetDonors donors)";

    $stmt->execute([
        $hospitalId,
        'campaign_created',
        $activityData,
        $activityDescription
    ]);

    $campaignId = $pdo->lastInsertId();

    // Clean output buffer and send success response
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Campaign created successfully',
        'data' => [
            'campaign_id' => $campaignId,
            'title' => $title,
            'status' => 'active',
            'hospital_id' => $hospitalId,
            'target_donors' => $targetDonors,
            'image_path' => $imagePath
        ]
    ]);

} catch (Exception $e) {
    // Clean any output and return error
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create campaign: ' . $e->getMessage()
    ]);
}
?>