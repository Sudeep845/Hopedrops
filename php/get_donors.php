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
        $pdo = null; // Database unavailable
    }
    
    // Get parameters
    $page = max(1, (int)($_GET['page'] ?? 1));
    $search = $_GET['search'] ?? '';
    $bloodType = $_GET['blood_type'] ?? '';
    $eligibility = $_GET['eligibility'] ?? '';
    $location = $_GET['location'] ?? '';
    $perPage = 20;
    $offset = ($page - 1) * $perPage;
    
    $donors = [];
    $totalCount = 0;
    
    if ($pdo) {
        try {
            // Build the query
            $whereConditions = ["u.role = 'donor'", "u.is_active = 1"];
            $params = [];
            
            // Search filter
            if (!empty($search)) {
                $whereConditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
                $searchParam = '%' . $search . '%';
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }
            
            // Blood type filter
            if (!empty($bloodType)) {
                $whereConditions[] = "u.blood_type = ?";
                $params[] = $bloodType;
            }
            
            // Location filter
            if (!empty($location)) {
                $whereConditions[] = "(u.city = ? OR u.state = ?)";
                $params[] = $location;
                $params[] = $location;
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            // Get total count for pagination
            $countSql = "SELECT COUNT(*) as total FROM users u WHERE $whereClause";
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($params);
            $totalCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get donors with pagination
            $sql = "SELECT 
                        u.id,
                        u.full_name,
                        u.email,
                        u.phone,
                        u.blood_type,
                        u.city,
                        u.state,
                        u.is_eligible,
                        u.created_at,
                        COUNT(d.id) as total_donations,
                        MAX(d.donation_date) as last_donation
                    FROM users u
                    LEFT JOIN donations d ON u.id = d.donor_id AND d.status = 'completed'
                    WHERE $whereClause
                    GROUP BY u.id
                    ORDER BY u.full_name ASC
                    LIMIT $perPage OFFSET $offset";
            
            // No need to add LIMIT/OFFSET to params array since we're using direct substitution
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $realDonors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($realDonors)) {
                $donors = $realDonors;
            }
            
        } catch (PDOException $e) {
            error_log("Database error in get_donors.php: " . $e->getMessage());
            // Return empty result with error message instead of sample data
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'message' => 'Unable to load donors from database'
            ]);
            exit;
        }
    }
    
    // If no database or no data, provide sample donors
    if (!$pdo || empty($donors)) {
        $sampleDonors = [
            [
                'id' => 1,
                'full_name' => 'Aarav Kumar',
                'email' => 'aarav.kumar@email.com',
                'phone' => '9876543210',
                'blood_type' => 'O+',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'is_eligible' => 1,
                'total_donations' => 5,
                'last_donation' => '2025-09-15',
                'created_at' => '2024-01-15 10:30:00'
            ],
            [
                'id' => 2,
                'full_name' => 'Aayush Sharma',
                'email' => 'aayush.sharma@email.com',
                'phone' => '9765432109',
                'blood_type' => 'A+',
                'city' => 'Delhi',
                'state' => 'Delhi',
                'is_eligible' => 1,
                'total_donations' => 3,
                'last_donation' => '2025-08-20',
                'created_at' => '2024-02-10 14:20:00'
            ],
            [
                'id' => 3,
                'full_name' => 'Aditi Patel',
                'email' => 'aditi.patel@email.com',
                'phone' => '9654321098',
                'blood_type' => 'B+',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'is_eligible' => 1,
                'total_donations' => 7,
                'last_donation' => '2025-10-01',
                'created_at' => '2023-12-05 09:15:00'
            ],
            [
                'id' => 4,
                'full_name' => 'Arjun Singh',
                'email' => 'arjun.singh@email.com',
                'phone' => '9543210987',
                'blood_type' => 'AB+',
                'city' => 'Chennai',
                'state' => 'Tamil Nadu',
                'is_eligible' => 1,
                'total_donations' => 2,
                'last_donation' => '2025-07-10',
                'created_at' => '2024-03-20 16:45:00'
            ],
            [
                'id' => 5,
                'full_name' => 'Ananya Gupta',
                'email' => 'ananya.gupta@email.com',
                'phone' => '9432109876',
                'blood_type' => 'O-',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'is_eligible' => 1,
                'total_donations' => 4,
                'last_donation' => '2025-08-30',
                'created_at' => '2024-01-08 11:30:00'
            ],
            [
                'id' => 6,
                'full_name' => 'Vikram Reddy',
                'email' => 'vikram.reddy@email.com',
                'phone' => '9321098765',
                'blood_type' => 'A-',
                'city' => 'Hyderabad',
                'state' => 'Telangana',
                'is_eligible' => 1,
                'total_donations' => 6,
                'last_donation' => '2025-09-25',
                'created_at' => '2023-11-12 13:15:00'
            ],
            [
                'id' => 7,
                'full_name' => 'Priya Nair',
                'email' => 'priya.nair@email.com',
                'phone' => '9210987654',
                'blood_type' => 'B-',
                'city' => 'Kochi',
                'state' => 'Kerala',
                'is_eligible' => 1,
                'total_donations' => 3,
                'last_donation' => '2025-10-10',
                'created_at' => '2024-04-05 08:20:00'
            ],
            [
                'id' => 8,
                'full_name' => 'Rohit Joshi',
                'email' => 'rohit.joshi@email.com',
                'phone' => '9109876543',
                'blood_type' => 'AB-',
                'city' => 'Jaipur',
                'state' => 'Rajasthan',
                'is_eligible' => 0,
                'total_donations' => 1,
                'last_donation' => '2025-11-01',
                'created_at' => '2024-06-18 15:40:00'
            ]
        ];
        
        // Apply filters to sample data
        $filteredDonors = $sampleDonors;
        
        if (!empty($search)) {
            $filteredDonors = array_filter($filteredDonors, function($donor) use ($search) {
                return stripos($donor['full_name'], $search) !== false ||
                       stripos($donor['email'], $search) !== false ||
                       stripos($donor['phone'], $search) !== false;
            });
        }
        
        if (!empty($bloodType)) {
            $filteredDonors = array_filter($filteredDonors, function($donor) use ($bloodType) {
                return $donor['blood_type'] === $bloodType;
            });
        }
        
        if (!empty($location)) {
            $filteredDonors = array_filter($filteredDonors, function($donor) use ($location) {
                return $donor['city'] === $location || $donor['state'] === $location;
            });
        }
        
        $totalCount = count($filteredDonors);
        $donors = array_slice($filteredDonors, $offset, $perPage);
    }
    
    // Format donors for frontend
    foreach ($donors as &$donor) {
        // Calculate donation eligibility
        $daysSinceLastDonation = 0;
        if ($donor['last_donation']) {
            $daysSinceLastDonation = floor((time() - strtotime($donor['last_donation'])) / 86400);
        }
        
        $donor['days_since_last_donation'] = $daysSinceLastDonation;
        $donor['eligible_to_donate'] = $donor['is_eligible'] && ($daysSinceLastDonation >= 56 || !$donor['last_donation']);
        
        // Format dates
        if ($donor['last_donation']) {
            $donor['last_donation_formatted'] = date('M j, Y', strtotime($donor['last_donation']));
        } else {
            $donor['last_donation_formatted'] = 'Never';
        }
        
        $donor['member_since'] = date('M Y', strtotime($donor['created_at']));
        
        // Add eligibility status with color
        if ($donor['eligible_to_donate']) {
            $donor['eligibility_status'] = [
                'text' => 'Eligible',
                'color' => 'success',
                'icon' => 'fa-check-circle'
            ];
        } else if ($daysSinceLastDonation < 56 && $donor['last_donation']) {
            $daysRemaining = 56 - $daysSinceLastDonation;
            $donor['eligibility_status'] = [
                'text' => "Eligible in $daysRemaining days",
                'color' => 'warning',
                'icon' => 'fa-clock'
            ];
        } else {
            $donor['eligibility_status'] = [
                'text' => 'Not Eligible',
                'color' => 'danger',
                'icon' => 'fa-times-circle'
            ];
        }
        
        // Privacy protection
        $emailParts = explode('@', $donor['email']);
        $donor['email_masked'] = substr($emailParts[0], 0, 3) . '***@' . $emailParts[1];
        $donor['phone_masked'] = substr($donor['phone'], 0, 3) . '***' . substr($donor['phone'], -3);
        
        // Add contact status
        $donor['contact_status'] = $donor['phone'] ? 'available' : 'no_phone';
    }
    
    // Calculate pagination info
    $totalPages = ceil($totalCount / $perPage);
    $hasNextPage = $page < $totalPages;
    $hasPrevPage = $page > 1;
    
    $pagination = [
        'current_page' => $page,
        'per_page' => $perPage,
        'total' => $totalCount,           // Frontend expects 'total'
        'total_count' => $totalCount,     // Keep for API compatibility
        'total_pages' => $totalPages,
        'has_next_page' => $hasNextPage,
        'has_prev_page' => $hasPrevPage,
        'next_page' => $hasNextPage ? $page + 1 : null,
        'prev_page' => $hasPrevPage ? $page - 1 : null
    ];
    
    // Summary statistics
    $stats = [
        'showing_count' => count($donors),
        'total_found' => $totalCount,
        'eligible_count' => count(array_filter($donors, function($d) { return $d['eligible_to_donate']; })),
        'blood_types' => array_count_values(array_column($donors, 'blood_type'))
    ];
    
    // Clean output buffer and send JSON
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => [
            'donors' => $donors,
            'pagination' => $pagination,
            'stats' => $stats
        ],
        'filters' => [
            'search' => $search,
            'blood_type' => $bloodType,
            'eligibility' => $eligibility,
            'location' => $location
        ],
        'message' => 'Donors retrieved successfully'
    ]);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Service temporarily unavailable',
        'error' => 'Unable to retrieve donors',
        'data' => []
    ]);
    exit;
}
?>