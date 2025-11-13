<?php
/**
 * Comprehensive API Error Handler
 * This file contains functions to ensure ONLY JSON is output from APIs
 */

/**
 * Initialize API environment to prevent ANY HTML output
 */
function initializeAPI() {
    // Suppress ALL error display
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('html_errors', 0);
    error_reporting(0);
    
    // Set proper JSON headers
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    // Start output buffering to catch any unwanted output
    if (ob_get_level() == 0) {
        ob_start();
    }
    
    // Handle OPTIONS preflight
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }
}

/**
 * Output clean JSON response
 */
function outputJSON($data) {
    // Clean ALL output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Start fresh buffer for JSON only
    ob_start();
    echo json_encode($data);
    ob_end_flush();
    exit;
}

/**
 * Handle API errors and output JSON error response
 */
function handleAPIError($message, $error = null) {
    // Clean ALL output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Log the error
    if ($error) {
        error_log("API Error: " . $error);
    }
    
    // Output clean error JSON
    ob_start();
    echo json_encode([
        'success' => false,
        'message' => $message,
        'data' => [],
        'error' => 'Service temporarily unavailable'
    ]);
    ob_end_flush();
    exit;
}
?>