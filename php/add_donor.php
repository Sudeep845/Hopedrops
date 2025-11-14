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
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

try {
    // Only allow POST method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    
    // Get form data
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // Generate email if not provided (since email is NOT NULL in database)
    if (empty($email)) {
        $emailBase = strtolower(preg_replace('/[^a-z0-9]/', '', str_replace(' ', '', $fullName)));
        $email = $emailBase . substr($phone, -4) . '@hopedrops.local';
        
        // Ensure email starts with a letter
        if (!preg_match('/^[a-z]/', $email)) {
            $email = 'donor' . $email;
        }
    }
    $dateOfBirth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $bloodType = $_POST['blood_type'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $emergencyContact = trim($_POST['emergency_contact'] ?? '');
    $weight = floatval($_POST['weight'] ?? 0);
    $lastDonation = $_POST['last_donation'] ?? null;
    $additionalNotes = trim($_POST['additional_notes'] ?? '');
    $medicalConditions = $_POST['medical_conditions'] ?? '[]';
    
    // Validate required fields
    $errors = [];
    
    if (empty($fullName)) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($phone)) {
        $errors[] = 'Phone number is required';
    }
    
    if (empty($dateOfBirth)) {
        $errors[] = 'Date of birth is required';
    }
    
    if (empty($gender)) {
        $errors[] = 'Gender is required';
    }
    
    if (empty($bloodType)) {
        $errors[] = 'Blood type is required';
    }
    
    if (empty($address)) {
        $errors[] = 'Address is required';
    }
    
    if (empty($city)) {
        $errors[] = 'City is required';
    }
    
    if (empty($state)) {
        $errors[] = 'State is required';
    }
    
    if ($weight < 45) {
        $errors[] = 'Weight must be at least 45 kg for blood donation eligibility';
    }
    
    // Validate blood type
    $validBloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    if (!in_array($bloodType, $validBloodTypes)) {
        $errors[] = 'Invalid blood type';
    }
    
    // Validate gender
    $validGenders = ['male', 'female', 'other'];
    if (!in_array($gender, $validGenders)) {
        $errors[] = 'Invalid gender';
    }
    
    // Validate age (must be at least 18)
    if (!empty($dateOfBirth)) {
        $dob = new DateTime($dateOfBirth);
        $today = new DateTime();
        $age = $today->diff($dob)->y;
        
        if ($age < 18) {
            $errors[] = 'Donor must be at least 18 years old';
        }
        if ($age > 65) {
            $errors[] = 'Donor must be under 65 years old for new registrations';
        }
    }
    
    // Validate email format if provided
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    // Check for duplicate phone number
    if (!empty($phone)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND role = 'donor'");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $errors[] = 'Phone number already registered';
        }
    }
    
    // Check for duplicate email if provided
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'donor'");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email address already registered';
        }
    }
    
    if (!empty($errors)) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors
        ]);
        exit;
    }
    
    // Determine eligibility based on age, weight, and last donation
    $isEligible = true;
    $eligibilityReasons = [];
    
    // Age check
    if (isset($age)) {
        if ($age < 18 || $age > 65) {
            $isEligible = false;
            $eligibilityReasons[] = 'Age not within eligible range (18-65)';
        }
    }
    
    // Weight check
    if ($weight < 50) {
        $isEligible = false;
        $eligibilityReasons[] = 'Weight below minimum requirement (50kg)';
    }
    
    // Last donation check (56 days minimum gap)
    if (!empty($lastDonation)) {
        $lastDonationDate = new DateTime($lastDonation);
        $daysSinceLastDonation = $today->diff($lastDonationDate)->days;
        
        if ($daysSinceLastDonation < 56) {
            $isEligible = false;
            $eligibilityReasons[] = 'Must wait at least 56 days since last donation';
        }
    }
    
    // Medical conditions check
    $conditionsArray = json_decode($medicalConditions, true) ?: [];
    $disqualifyingConditions = ['heart_disease', 'diabetes', 'hepatitis', 'hiv'];
    
    foreach ($conditionsArray as $condition) {
        if (in_array($condition, $disqualifyingConditions)) {
            $isEligible = false;
            $eligibilityReasons[] = 'Medical condition prevents donation';
            break;
        }
    }
    
    // Generate username from name and phone
    $baseUsername = strtolower(str_replace(' ', '', $fullName));
    $username = $baseUsername . substr($phone, -4);
    
    // Ensure username is unique
    $counter = 1;
    $originalUsername = $username;
    while (true) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if (!$stmt->fetch()) {
            break;
        }
        $username = $originalUsername . $counter;
        $counter++;
    }
    
    // Generate a temporary password (donor should change it)
    $tempPassword = 'donor' . rand(1000, 9999);
    $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
    
    $pdo->beginTransaction();
    
    try {
        // Insert into users table
        $stmt = $pdo->prepare("
            INSERT INTO users (
                username, email, password, role, full_name, phone, 
                date_of_birth, gender, blood_type, address, city, state, 
                pincode, emergency_contact, medical_conditions, is_eligible, 
                is_active, created_at
            ) VALUES (?, ?, ?, 'donor', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        
        $stmt->execute([
            $username,
            $email,
            $hashedPassword,
            $fullName,
            $phone,
            $dateOfBirth,
            $gender,
            $bloodType,
            $address,
            $city,
            $state,
            $postalCode ?: null,
            $emergencyContact ?: null,
            $medicalConditions,
            $isEligible ? 1 : 0
        ]);
        
        $donorId = $pdo->lastInsertId();
        
        // If last donation date is provided, insert donation record
        if (!empty($lastDonation)) {
            $stmt = $pdo->prepare("
                INSERT INTO donations (
                    donor_id, hospital_id, blood_type, donation_date, 
                    units_donated, status, created_at
                ) VALUES (?, 1, ?, ?, 1, 'completed', NOW())
            ");
            $stmt->execute([$donorId, $bloodType, $lastDonation]);
        }
        
        $pdo->commit();
        
        // Prepare response
        $response = [
            'success' => true,
            'message' => 'Donor registered successfully',
            'data' => [
                'donor_id' => $donorId,
                'username' => $username,
                'temp_password' => $tempPassword,
                'is_eligible' => $isEligible,
                'eligibility_notes' => $eligibilityReasons
            ]
        ];
        
        if (!$isEligible) {
            $response['message'] .= ' (Note: Currently not eligible for donation)';
        }
        
        ob_end_clean();
        echo json_encode($response);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    // Clean any output and return error
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Failed to add donor: ' . $e->getMessage()
    ]);
}
?>