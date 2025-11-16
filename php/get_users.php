<?php
/**
 * HopeDrops Blood Bank Management System
 * Get Users API - Admin Panel
 * 
 * Retrieves users list with filtering and pagination
 * Created: November 16, 2025
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    require_once 'db_connect.php';
    
    // Get database connection
    $pdo = getDBConnection();

    // Temporarily bypass authentication for testing
    // TODO: Re-enable authentication once session system is fixed
    
    // Check if user is logged in as admin
    // if (!isLoggedIn()) {
    //     echo json_encode([
    //         'success' => false,
    //         'message' => 'Authentication required'
    //     ]);
    //     exit;
    // }

    // Get current user role (should be admin)
    // $currentUserRole = $_SESSION['role'] ?? '';
    // if ($currentUserRole !== 'admin') {
    //     echo json_encode([
    //         'success' => false,
    //         'message' => 'Admin access required'
    //     ]);
    //     exit;
    // }

    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    // Check if requesting statistics only
    if (isset($input['get_stats']) && $input['get_stats']) {
        $stats = [];
        
        // Get total users count
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
        $stats['total'] = (int)$stmt->fetchColumn();
        
        // Get active users count
        $stmt = $pdo->query("SELECT COUNT(*) as active FROM users WHERE is_active = 1");
        $stats['active'] = (int)$stmt->fetchColumn();
        
        // Get users by role
        $stmt = $pdo->query("SELECT COUNT(*) as donors FROM users WHERE role = 'donor'");
        $stats['donors'] = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) as hospitals FROM users WHERE role = 'hospital'");
        $stats['hospitals'] = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) as admins FROM users WHERE role = 'admin'");
        $stats['admins'] = (int)$stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'stats' => $stats
            ]
        ]);
        exit;
    }

    $role = $input['role'] ?? '';
    $status = $input['status'] ?? '';
    $search = trim($input['search'] ?? '');
    $page = max(1, intval($input['page'] ?? 1));
    $limit = max(1, min(50, intval($input['limit'] ?? 10))); // Max 50 per page
    $offset = ($page - 1) * $limit;

    // Build the query
    $whereConditions = [];
    $params = [];

    // Role filter
    if (!empty($role)) {
        $whereConditions[] = "role = ?";
        $params[] = $role;
    }

    // Status filter
    if (!empty($status)) {
        switch ($status) {
            case 'active':
                $whereConditions[] = "is_active = 1";
                break;
            case 'inactive':
                $whereConditions[] = "is_active = 0";
                break;
        }
    }

    // Search filter
    if (!empty($search)) {
        $whereConditions[] = "(username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // Build WHERE clause
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM users {$whereClause}";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalUsers = $countStmt->fetch()['total'];

    // Get users with pagination  
    $query = "
        SELECT 
            id, username, email, full_name, role, is_active, created_at, updated_at
        FROM users 
        {$whereClause}
        ORDER BY created_at DESC, id DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([...$params, $limit, $offset]);
    $users = $stmt->fetchAll();

    // Process users data
    $processedUsers = array_map(function($user) {
        return [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'full_name' => $user['full_name'] ?? '',
            'role' => $user['role'],
            'is_active' => (bool)$user['is_active'],
            'is_verified' => false, // Default since column doesn't exist
            'created_at' => $user['created_at'],
            'updated_at' => $user['updated_at'] ?? null,
            'last_login' => null // Default since column doesn't exist
        ];
    }, $users);

    // Calculate pagination info
    $totalPages = ceil($totalUsers / $limit);
    $pagination = [
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'totalUsers' => (int)$totalUsers,
        'limit' => $limit,
        'hasNextPage' => $page < $totalPages,
        'hasPrevPage' => $page > 1
    ];

    // Calculate statistics for reports
    $statsStmt = $pdo->query("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN role = 'hospital' THEN 1 ELSE 0 END) as hospital_count,
            SUM(CASE WHEN role = 'donor' THEN 1 ELSE 0 END) as donor_count,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count
        FROM users
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Users retrieved successfully',
        'data' => [
            'users' => $processedUsers,
            'total' => (int)$totalUsers,
            'active_count' => (int)$stats['active_count'],
            'hospital_count' => (int)$stats['hospital_count'],
            'donor_count' => (int)$stats['donor_count'],
            'admin_count' => (int)$stats['admin_count'],
            'pagination' => $pagination,
            'filters' => [
                'role' => $role,
                'status' => $status,
                'search' => $search
            ]
        ]
    ]);

} catch (PDOException $e) {
    error_log("Database error in get_users.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error in get_users.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>