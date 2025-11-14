<?php
// Complete error suppression and JSON-only output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('html_errors', 0);
error_reporting(0);

// Start output buffering immediately  
ob_start();

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

try {
    // Only allow GET method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
        exit;
    }

    // Database connection
    $host = 'localhost';
    $dbname = 'bloodbank_db';
    $username = 'root';
    $password = '';
    
    $pdo = null;
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
        ]);
        exit;
    }
    
    // Get user ID parameter
    $userId = (int)($_GET['user_id'] ?? 0);
    
    if ($userId <= 0) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Valid user ID is required'
        ]);
        exit;
    }
    
    // Get donor details
    $sql = "SELECT 
                u.id,
                u.username,
                u.full_name,
                u.email,
                u.phone,
                u.date_of_birth,
                u.gender,
                u.blood_type,
                u.address,
                u.city,
                u.state,
                u.pincode as postal_code,
                u.emergency_contact,
                u.medical_conditions,
                u.is_eligible,
                u.is_active,
                u.created_at,
                u.updated_at,
                COUNT(d.id) as total_donations,
                MAX(d.donation_date) as last_donation_date,
                MIN(d.donation_date) as first_donation_date
            FROM users u
            LEFT JOIN donations d ON u.id = d.donor_id AND d.status = 'completed'
            WHERE u.id = ? AND u.role = 'donor'
            GROUP BY u.id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $donor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$donor) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Donor not found'
        ]);
        exit;
    }
    
    // Calculate additional data
    $today = new DateTime();
    $birthDate = new DateTime($donor['date_of_birth']);
    $age = $today->diff($birthDate)->y;
    
    // Determine eligibility status
    $eligibilityStatus = 'eligible';
    $eligibilityColor = 'success';
    $eligibilityIcon = 'fa-check-circle';
    
    if (!$donor['is_eligible']) {
        $eligibilityStatus = 'not-eligible';
        $eligibilityColor = 'danger';
        $eligibilityIcon = 'fa-times-circle';
    } elseif (!empty($donor['last_donation_date'])) {
        $lastDonation = new DateTime($donor['last_donation_date']);
        $daysSinceLastDonation = $today->diff($lastDonation)->days;
        
        if ($daysSinceLastDonation < 56) {
            $daysUntilEligible = 56 - $daysSinceLastDonation;
            $eligibilityStatus = "eligible-in-{$daysUntilEligible}-days";
            $eligibilityColor = 'warning';
            $eligibilityIcon = 'fa-clock';
        }
    }
    
    // Get recent donation history
    $donationHistorySql = "SELECT 
                                donation_date,
                                blood_type,
                                units_donated,
                                status,
                                h.hospital_name,
                                notes
                            FROM donations d
                            LEFT JOIN hospitals h ON d.hospital_id = h.id
                            WHERE d.donor_id = ?
                            ORDER BY d.donation_date DESC
                            LIMIT 5";
    
    $historyStmt = $pdo->prepare($donationHistorySql);
    $historyStmt->execute([$userId]);
    $donationHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse medical conditions
    $medicalConditions = [];
    if (!empty($donor['medical_conditions'])) {
        $conditions = json_decode($donor['medical_conditions'], true);
        if (is_array($conditions)) {
            $medicalConditions = $conditions;
        }
    }
    
    // Prepare response
    $donorDetails = [
        'id' => (int)$donor['id'],
        'username' => $donor['username'],
        'full_name' => $donor['full_name'],
        'email' => $donor['email'],
        'phone' => $donor['phone'],
        'date_of_birth' => $donor['date_of_birth'],
        'age' => $age,
        'gender' => ucfirst($donor['gender']),
        'blood_type' => $donor['blood_type'],
        'address' => $donor['address'],
        'city' => $donor['city'],
        'state' => $donor['state'],
        'postal_code' => $donor['postal_code'],
        'emergency_contact' => $donor['emergency_contact'],
        'medical_conditions' => $medicalConditions,
        'is_eligible' => (bool)$donor['is_eligible'],
        'is_active' => (bool)$donor['is_active'],
        'eligibility_status' => $eligibilityStatus,
        'eligibility_color' => $eligibilityColor,
        'eligibility_icon' => $eligibilityIcon,
        'total_donations' => (int)$donor['total_donations'],
        'last_donation_date' => $donor['last_donation_date'],
        'first_donation_date' => $donor['first_donation_date'],
        'donation_history' => $donationHistory,
        'member_since' => $donor['created_at'],
        'created_at' => $donor['created_at'],
        'updated_at' => $donor['updated_at'],
        'statistics' => [
            'total_donations' => (int)$donor['total_donations'],
            'total_units_donated' => (int)$donor['total_donations'], // Assuming 1 unit per donation
            'average_donations_per_year' => 0,
            'days_since_registration' => $today->diff(new DateTime($donor['created_at']))->days,
            'days_since_last_donation' => !empty($donor['last_donation_date']) 
                ? $today->diff(new DateTime($donor['last_donation_date']))->days 
                : null
        ]
    ];
    
    // Calculate average donations per year
    $membershipDays = $donorDetails['statistics']['days_since_registration'];
    if ($membershipDays > 0) {
        $membershipYears = $membershipDays / 365.25;
        $donorDetails['statistics']['average_donations_per_year'] = 
            $membershipYears > 0 ? round($donorDetails['statistics']['total_donations'] / $membershipYears, 1) : 0;
    }
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Donor details retrieved successfully',
        'data' => $donorDetails
    ]);
    
} catch (Exception $e) {
    // Clean any output and return error
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve donor details: ' . $e->getMessage()
    ]);
}
?>