<?php
/**
 * HopeDrops Blood Bank Management System
 * User Registration Handler
 * 
 * Handles user registration for different roles (donor, hospital)
 * Created: November 11, 2025
 */

require_once 'db_connect.php';

header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Invalid request method');
}

// Check rate limiting
if (!checkRateLimit('register', 3, 300)) {
    sendJsonResponse(false, 'Too many registration attempts. Please try again later.');
}

try {
    $db = getDBConnection();
    $db->beginTransaction();
    
    // Get and sanitize input
    $role = sanitizeInput($_POST['role'] ?? 'donor');
    $fullName = sanitizeInput($_POST['fullName'] ?? '');
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    
    // Validation array to collect errors
    $errors = [];
    
    // Basic validation
    if (empty($fullName)) {
        $errors['fullName'] = 'Full name is required';
    }
    
    if (empty($username)) {
        $errors['username'] = 'Username is required';
    } elseif (strlen($username) < 3) {
        $errors['username'] = 'Username must be at least 3 characters';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors['username'] = 'Username can only contain letters, numbers, and underscores';
    }
    
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!validateEmail($email)) {
        $errors['email'] = 'Please enter a valid email address';
    }
    
    if (empty($phone)) {
        $errors['phone'] = 'Phone number is required';
    } elseif (!validatePhone($phone)) {
        $errors['phone'] = 'Please enter a valid phone number';
    }
    
    if (empty($address)) {
        $errors['address'] = 'Address is required';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9])/', $password)) {
        $errors['password'] = 'Password must contain uppercase, lowercase, number, and special character';
    }
    
    if ($password !== $confirmPassword) {
        $errors['confirmPassword'] = 'Passwords do not match';
    }
    
    if (!in_array($role, ['donor', 'hospital'])) {
        $errors['role'] = 'Invalid user role';
    }
    
    // Role-specific validation
    if ($role === 'donor') {
        $dateOfBirth = sanitizeInput($_POST['dateOfBirth'] ?? '');
        $gender = sanitizeInput($_POST['gender'] ?? '');
        $bloodType = sanitizeInput($_POST['bloodType'] ?? '');
        
        if (empty($dateOfBirth)) {
            $errors['dateOfBirth'] = 'Date of birth is required';
        } else {
            // Check age (must be 18-65)
            $birthDate = new DateTime($dateOfBirth);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
            
            if ($age < 18) {
                $errors['dateOfBirth'] = 'You must be at least 18 years old to donate blood';
            } elseif ($age > 65) {
                $errors['dateOfBirth'] = 'Maximum age for blood donation is 65 years';
            }
        }
        
        if (empty($gender)) {
            $errors['gender'] = 'Gender is required';
        }
        
        if (empty($bloodType)) {
            $errors['bloodType'] = 'Blood type is required';
        }
        
        // Check eligibility checkboxes
        $eligibility = $_POST['eligibility'] ?? [];
        if (count($eligibility) < 5) {
            $errors['eligibility'] = 'All eligibility criteria must be confirmed';
        }
        
    } elseif ($role === 'hospital') {
        // Basic hospital information (Step 1)
        $hospitalName = sanitizeInput($_POST['hospitalName'] ?? '');
        $licenseNumber = sanitizeInput($_POST['licenseNumber'] ?? '');
        $contactPerson = sanitizeInput($_POST['contactPerson'] ?? '');
        $city = sanitizeInput($_POST['city'] ?? '');
        
        // Hospital verification information (Step 2)
        $registrationNumber = sanitizeInput($_POST['registrationNumber'] ?? '');
        $establishedDate = sanitizeInput($_POST['establishedDate'] ?? '');
        $bedCapacity = (int)($_POST['bedCapacity'] ?? 0);
        $hospitalType = sanitizeInput($_POST['hospitalType'] ?? '');
        $services = sanitizeInput($_POST['services'] ?? '');
        
        // Validate basic information
        if (empty($hospitalName)) {
            $errors['hospitalName'] = 'Hospital name is required';
        }
        
        if (empty($licenseNumber)) {
            $errors['licenseNumber'] = 'License number is required';
        }
        
        if (empty($contactPerson)) {
            $errors['contactPerson'] = 'Contact person is required';
        }
        
        if (empty($city)) {
            $errors['city'] = 'City is required';
        }
        
        // Validate verification information
        if (empty($registrationNumber)) {
            $errors['registrationNumber'] = 'Medical registration number is required';
        }
        
        if (empty($establishedDate)) {
            $errors['establishedDate'] = 'Established date is required';
        } else {
            // Check if date is not in the future
            $established = new DateTime($establishedDate);
            $today = new DateTime();
            
            if ($established > $today) {
                $errors['establishedDate'] = 'Established date cannot be in the future';
            }
        }
        
        if ($bedCapacity <= 0) {
            $errors['bedCapacity'] = 'Bed capacity must be a positive number';
        } elseif ($bedCapacity > 10000) {
            $errors['bedCapacity'] = 'Bed capacity seems unrealistic (max 10,000)';
        }
        
        if (empty($hospitalType)) {
            $errors['hospitalType'] = 'Hospital type is required';
        }
    }
    
    // Check if username already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $errors['username'] = 'Username is already taken';
    }
    
    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($user = $stmt->fetch()) {
        $errors['email'] = 'Email is already registered';
    }
    
    // If hospital, check if license number already exists
    if ($role === 'hospital' && !empty($licenseNumber)) {
        $stmt = $db->prepare("SELECT id FROM hospitals WHERE license_number = ?");
        $stmt->execute([$licenseNumber]);
        if ($stmt->fetch()) {
            $errors['licenseNumber'] = 'License number is already registered';
        }
    }
    
    // Return validation errors if any
    if (!empty($errors)) {
        sendJsonResponse(false, 'Please correct the errors below', null, null, $errors);
    }
    
    // Hash password
    $hashedPassword = hashPassword($password);
    
    // Insert user record
    $stmt = $db->prepare("
        INSERT INTO users (username, password, role, full_name, email, phone, address, date_of_birth, gender, blood_type, is_active) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    
    $stmt->execute([
        $username,
        $hashedPassword,
        $role,
        $fullName,
        $email,
        $phone,
        $address,
        $role === 'donor' ? $dateOfBirth : null,
        $role === 'donor' ? $gender : null,
        $role === 'donor' ? $bloodType : null
    ]);
    
    $userId = $db->lastInsertId();
    
    // Role-specific additional records
    if ($role === 'hospital') {
        // Insert hospital record
        $stmt = $db->prepare("
            INSERT INTO hospitals (
                user_id, hospital_name, license_number, address, city, state, 
                pincode, contact_person, contact_phone, contact_email, 
                hospital_type, is_approved
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ");
        
        // Extract state and pincode from address (simplified)
        $addressParts = explode(',', $address);
        $state = isset($addressParts[1]) ? trim($addressParts[1]) : '';
        $pincode = '';
        
        // Try to extract pincode from address
        if (preg_match('/\b\d{6}\b/', $address, $matches)) {
            $pincode = $matches[0];
        }
        
        $stmt->execute([
            $userId,
            $hospitalName,
            $licenseNumber,
            $address,
            $city,
            $state ?: 'Not specified',
            $pincode ?: '000000',
            $contactPerson,
            $phone,
            $email,
            $hospitalType ?: 'General'
            // Note: is_approved is hardcoded as 0 in the SQL, so no parameter needed
        ]);
        
        $hospitalId = $db->lastInsertId();
        
        // Initialize blood inventory for hospital
        $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        $inventoryStmt = $db->prepare("
            INSERT INTO blood_inventory (hospital_id, blood_type, units_available, units_required) 
            VALUES (?, ?, 0, 0)
        ");
        
        foreach ($bloodTypes as $bloodType) {
            $inventoryStmt->execute([$hospitalId, $bloodType]);
        }
        
        // Send notification to admin about new hospital registration
        $adminQuery = $db->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $adminQuery->execute();
        if ($admin = $adminQuery->fetch()) {
            createNotification(
                $admin['id'],
                'New Hospital Registration',
                "New hospital '{$hospitalName}' has registered and is pending approval.",
                'info'
            );
        }
        
    } elseif ($role === 'donor') {
        // Initialize donor rewards
        $stmt = $db->prepare("
            INSERT INTO user_rewards (user_id, total_points, current_points, level, donations_count) 
            VALUES (?, 50, 50, 1, 0)
        ");
        $stmt->execute([$userId]);
        
        // Welcome bonus notification
        createNotification(
            $userId,
            'Welcome to HopeDrops!',
            'Thank you for joining our life-saving community! You have earned 50 welcome points.',
            'success'
        );
    }
    
    // Log the registration
    logActivity($userId, 'registration', "New {$role} account created");
    
    // Send welcome notification
    $welcomeMessage = $role === 'hospital' 
        ? 'Your hospital account has been created and is pending approval. You will be notified once approved.'
        : 'Welcome to HopeDrops! Your account has been created successfully.';
    
    createNotification(
        $userId,
        'Welcome to HopeDrops',
        $welcomeMessage,
        'success'
    );
    
    $db->commit();
    
    // Success response
    $message = $role === 'hospital' 
        ? 'Hospital account created successfully! Your account is pending approval and you will be notified once approved.'
        : 'Account created successfully! You can now login and start saving lives.';
    
    sendJsonResponse(true, $message, [
        'user_id' => $userId,
        'username' => $username,
        'role' => $role,
        'requires_approval' => $role === 'hospital'
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Registration database error: " . $e->getMessage());
    // Temporary: Show actual error for debugging
    sendJsonResponse(false, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    error_log("Registration error: " . $e->getMessage());
    // Temporary: Show actual error for debugging
    sendJsonResponse(false, 'Registration error: ' . $e->getMessage());
}

// sendJsonResponse function is now defined in db_connect.php
?>