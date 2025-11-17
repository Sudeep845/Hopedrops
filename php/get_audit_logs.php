<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'hopedrops';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Get request parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
$category = isset($_GET['category']) ? $_GET['category'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$dateRange = isset($_GET['date_range']) ? $_GET['date_range'] : '';
$fromDate = isset($_GET['fromDate']) ? $_GET['fromDate'] : '';
$toDate = isset($_GET['toDate']) ? $_GET['toDate'] : '';
$export = isset($_GET['export']) ? $_GET['export'] === 'true' : false;

$offset = ($page - 1) * $limit;

// Build the query
$whereConditions = [];
$params = [];

if ($category) {
    $whereConditions[] = "category = :category";
    $params['category'] = $category;
}

if ($status) {
    $whereConditions[] = "status = :status";
    $params['status'] = $status;
}

if ($search) {
    $whereConditions[] = "(user_name LIKE :search OR action LIKE :search OR resource LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

// Handle date filtering
if ($dateRange && $dateRange !== 'custom') {
    switch($dateRange) {
        case 'today':
            $whereConditions[] = "DATE(timestamp) = CURDATE()";
            break;
        case 'yesterday':
            $whereConditions[] = "DATE(timestamp) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'week':
            $whereConditions[] = "timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $whereConditions[] = "timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
} elseif ($fromDate && $toDate) {
    $whereConditions[] = "DATE(timestamp) BETWEEN :fromDate AND :toDate";
    $params['fromDate'] = $fromDate;
    $params['toDate'] = $toDate;
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = ' WHERE ' . implode(' AND ', $whereConditions);
}

// Get total count
$countQuery = "SELECT COUNT(*) FROM audit_logs" . $whereClause;
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalCount = $countStmt->fetchColumn();

// Build main query - for export, don't apply pagination
if ($export) {
    $query = "SELECT * FROM audit_logs" . $whereClause . " ORDER BY timestamp DESC";
    $stmt = $pdo->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
} else {
    $query = "SELECT * FROM audit_logs" . $whereClause . " ORDER BY timestamp DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($query);

    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
}

$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format the response
$response = [
    'logs' => $logs,
    'totalCount' => (int)$totalCount,
    'currentPage' => $page,
    'totalPages' => ceil($totalCount / $limit),
    'limit' => $limit
];

echo json_encode($response);
?>