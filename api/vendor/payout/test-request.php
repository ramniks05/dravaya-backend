<?php
/**
 * Test endpoint to debug vendor payout request format
 * This helps verify the payload is being received correctly
 */

require_once __DIR__ . '/../../cors.php';
require_once '../../../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
$postData = $_POST;

echo json_encode([
    'status' => 'info',
    'message' => 'Request received - debugging info',
    'debug' => [
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
        'raw_input' => $rawInput,
        'raw_input_length' => strlen($rawInput),
        'json_decoded' => $data,
        'json_error' => json_last_error() !== JSON_ERROR_NONE ? json_last_error_msg() : 'No error',
        'post_data' => $postData,
        'received_fields' => $data ? array_keys($data) : [],
        'expected_fields' => ['vendor_id', 'amount', 'beneficiary_id', 'narration'],
        'field_validation' => [
            'vendor_id' => isset($data['vendor_id']) ? 'present' : 'missing',
            'amount' => isset($data['amount']) ? 'present: ' . $data['amount'] : 'missing',
            'beneficiary_id' => isset($data['beneficiary_id']) ? 'present: ' . $data['beneficiary_id'] : 'missing',
            'narration' => isset($data['narration']) ? 'present: ' . $data['narration'] : 'missing'
        ]
    ]
], JSON_PRETTY_PRINT);

