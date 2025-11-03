<?php
/**
 * Test Login Endpoint - For debugging
 * This helps test login without database connection
 */

// CORS Headers
require_once __DIR__ . '/../cors.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$response = [
    'status' => 'info',
    'message' => 'Login test endpoint',
    'received_data' => [
        'raw_input' => $rawInput,
        'parsed_data' => $data,
        'json_error' => json_last_error() !== JSON_ERROR_NONE ? json_last_error_msg() : null,
        'method' => $_SERVER['REQUEST_METHOD'],
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
        'email_received' => $data['email'] ?? 'not set',
        'password_received' => isset($data['password']) ? '***' : 'not set',
        'email_valid' => isset($data['email']) ? filter_var($data['email'], FILTER_VALIDATE_EMAIL) !== false : false
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);

