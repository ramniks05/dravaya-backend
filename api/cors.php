<?php
/**
 * CORS Headers Helper
 * Include this file at the TOP of all your API endpoints (before any other code)
 * This MUST be the first thing executed in your PHP files
 */

// Prevent any output before headers
if (ob_get_level()) {
    ob_clean();
} else {
    ob_start();
}

// Get the origin from the request
$origin = $_SERVER['HTTP_ORIGIN'] ?? null;

// List of allowed origins
$allowedOrigins = [
    'http://localhost:5173',
    'http://localhost:3000',
    'http://localhost:3001',
    'http://localhost:5174',
    'http://127.0.0.1:5173',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:3001',
];

// Determine allowed origin - use * for development
$allowedOrigin = '*';
if ($origin && in_array($origin, $allowedOrigins)) {
    $allowedOrigin = $origin;
}

// Set CORS headers immediately - MUST be first
// Mark that CORS headers are set to prevent duplicates
$GLOBALS['cors_headers_set'] = true;

// Use true parameter to replace any existing headers (prevents duplicates)
if (!headers_sent()) {
    header("Access-Control-Allow-Origin: {$allowedOrigin}", true);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH', true);
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, api-Key, Accept, Origin', true);
    header('Access-Control-Allow-Credentials: true', true);
    header('Access-Control-Max-Age: 86400', true);
}

// Handle preflight OPTIONS request - MUST exit here
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    header('Content-Type: application/json', true);
    if (ob_get_level()) {
        ob_end_clean();
    }
    exit();
}

// Set content type for actual requests
header('Content-Type: application/json; charset=utf-8', true);

// End output buffering
if (ob_get_level()) {
    ob_end_flush();
}

