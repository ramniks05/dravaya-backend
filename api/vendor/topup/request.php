<?php
/**
 * Submit Topup Request API
 * Vendor submits a request to add funds to their wallet
 */

require_once __DIR__ . '/../../cors.php';
require_once '../../../config.php';
require_once '../../../database/wallet_functions.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

try {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON request data');
    }
    
    // Validate required fields
    if (!isset($data['vendor_id']) || empty($data['vendor_id'])) {
        throw new Exception('vendor_id is required');
    }
    
    if (!isset($data['amount']) || empty($data['amount'])) {
        throw new Exception('amount is required');
    }
    
    $vendorId = trim($data['vendor_id']);
    $amount = floatval($data['amount']);
    
    // Validate amount
    if ($amount <= 0) {
        throw new Exception('Amount must be greater than 0');
    }
    
    if ($amount > 1000000) {
        throw new Exception('Amount cannot exceed 10,00,000');
    }
    
    // Verify vendor exists and is active
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, email, role, status FROM users WHERE id = ? AND role = 'vendor' LIMIT 1");
    $stmt->bind_param('s', $vendorId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('Vendor not found');
    }
    
    $vendor = $result->fetch_assoc();
    $stmt->close();
    
    if ($vendor['status'] !== 'active') {
        throw new Exception('Vendor account is not active. Cannot submit topup request.');
    }
    
    // Create topup request
    $requestId = $data['request_id'] ?? null;
    $result = createTopupRequest($vendorId, $amount, $requestId);
    
    if (!$result['success']) {
        throw new Exception($result['error'] ?? 'Failed to create topup request');
    }
    
    // Get the created request
    $topupRequest = getTopupRequest($result['request_id']);
    
    logError('Topup request created', [
        'vendor_id' => $vendorId,
        'request_id' => $result['request_id'],
        'amount' => $amount
    ], false);
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Topup request submitted successfully. Waiting for admin approval.',
        'data' => [
            'request' => [
                'id' => $topupRequest['id'],
                'request_id' => $topupRequest['request_id'],
                'vendor_id' => $vendorId,
                'vendor_email' => $vendor['email'],
                'amount' => floatval($topupRequest['amount']),
                'currency' => $topupRequest['currency'],
                'status' => $topupRequest['status'],
                'created_at' => $topupRequest['created_at']
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    logError('Create topup request error', ['error' => $e->getMessage()]);
}

