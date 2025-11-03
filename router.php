<?php
/**
 * Simple PHP Router for Built-in Server
 * This allows PHP built-in server to route requests properly
 * 
 * Usage: php -S localhost:8080 -t . backend/router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// If the file exists and is a PHP file, serve it
if ($uri !== '/' && file_exists(__DIR__ . $uri) && pathinfo($uri, PATHINFO_EXTENSION) === 'php') {
    return false; // Serve the file as-is
}

// Route API requests
if (strpos($uri, '/backend/api/') === 0) {
    require __DIR__ . $uri;
    return true;
}

// For any other requests, return 404
http_response_code(404);
echo json_encode(['error' => 'Not found']);
return true;

