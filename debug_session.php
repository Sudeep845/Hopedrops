<?php
/**
 * Debug Session Information
 */
require_once 'php/db_connect.php';

header('Content-Type: application/json');

// Debug session information
$debug_info = [
    'session_status' => session_status(),
    'session_id' => session_id(),
    'session_data' => $_SESSION ?? [],
    'cookies' => $_COOKIE ?? [],
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
];

echo json_encode([
    'success' => true,
    'message' => 'Session debug information',
    'debug' => $debug_info
], JSON_PRETTY_PRINT);
?>