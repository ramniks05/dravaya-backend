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
    'http://dravya.hrntechsolutions.com',
    'https://dravya.hrntechsolutions.com',
];

// Determine allowed origin
// Note: Cannot use '*' when Access-Control-Allow-Credentials is true
$allowedOrigin = null;

// Check if origin is in allowed list
if ($origin && in_array($origin, $allowedOrigins)) {
    $allowedOrigin = $origin;
} else {
    // For development, allow any origin if not in production
    $isProduction = strpos($_SERVER['HTTP_HOST'] ?? '', 'dravya.hrntechsolutions.com') !== false;
    
    if (!$isProduction) {
        // Development: Allow any origin but don't set credentials
        $allowedOrigin = $origin ?: '*';
    } else {
        // Production: Check if origin matches production domain pattern
        if ($origin && (
            strpos($origin, 'http://dravya.hrntechsolutions.com') === 0 ||
            strpos($origin, 'https://dravya.hrntechsolutions.com') === 0
        )) {
            $allowedOrigin = $origin;
        } else {
            // Production fallback: use first matching production origin
            $allowedOrigin = 'https://dravya.hrntechsolutions.com';
        }
    }
}

// Set CORS headers immediately - MUST be first
// Mark that CORS headers are set to prevent duplicates
$GLOBALS['cors_headers_set'] = true;

// Use true parameter to replace any existing headers (prevents duplicates)
if (!headers_sent()) {
    if ($allowedOrigin) {
        header("Access-Control-Allow-Origin: {$allowedOrigin}", true);
    }
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH', true);
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, api-Key, Accept, Origin', true);
    
    // Only set credentials if origin is not wildcard
    if ($allowedOrigin !== '*') {
        header('Access-Control-Allow-Credentials: true', true);
    }
    
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

