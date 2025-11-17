<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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

// Get statistics for audit dashboard
$stats = [];

// Total events today
$query = "SELECT COUNT(*) FROM audit_logs WHERE DATE(timestamp) = CURDATE()";
$stmt = $pdo->query($query);
$stats['totalEvents'] = (int)$stmt->fetchColumn();

// Security events (all time)
$query = "SELECT COUNT(*) FROM audit_logs WHERE category = 'security'";
$stmt = $pdo->query($query);
$stats['securityEvents'] = (int)$stmt->fetchColumn();

// Failed logins today
$query = "SELECT COUNT(*) FROM audit_logs WHERE action LIKE '%Failed Login%' AND DATE(timestamp) = CURDATE()";
$stmt = $pdo->query($query);
$stats['failedLogins'] = (int)$stmt->fetchColumn();

// Critical alerts today
$query = "SELECT COUNT(*) FROM audit_logs WHERE (status = 'error' OR JSON_EXTRACT(details, '$.urgency') = 'critical') AND DATE(timestamp) = CURDATE()";
$stmt = $pdo->query($query);
$stats['criticalAlerts'] = (int)$stmt->fetchColumn();

// Activity chart data (last 7 hours)
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $hour = date('Y-m-d H:00:00', strtotime("-$i hours"));
    $nextHour = date('Y-m-d H:00:00', strtotime("-" . ($i-1) . " hours"));
    
    $query = "SELECT COUNT(*) FROM audit_logs WHERE timestamp >= ? AND timestamp < ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$hour, $nextHour]);
    
    $chartData[] = [
        'hour' => date('H:i', strtotime($hour)),
        'total' => (int)$stmt->fetchColumn()
    ];
}

// Security events for chart
$securityChartData = [];
for ($i = 6; $i >= 0; $i--) {
    $hour = date('Y-m-d H:00:00', strtotime("-$i hours"));
    $nextHour = date('Y-m-d H:00:00', strtotime("-" . ($i-1) . " hours"));
    
    $query = "SELECT COUNT(*) FROM audit_logs WHERE category = 'security' AND timestamp >= ? AND timestamp < ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$hour, $nextHour]);
    
    $securityChartData[] = (int)$stmt->fetchColumn();
}

$response = [
    'stats' => $stats,
    'chartData' => [
        'labels' => array_column($chartData, 'hour'),
        'totalEvents' => array_column($chartData, 'total'),
        'securityEvents' => $securityChartData
    ]
];

echo json_encode($response);
?>