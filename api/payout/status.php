<?php
/**
 * Check Transaction Status API Endpoint
 */

// CORS Headers - Must be first
require_once __DIR__ . '/../cors.php';

require_once '../../config.php';
require_once '../../database/functions.php';

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
        logError('Invalid JSON input in status check', ['raw_input' => substr($rawInput, 0, 500)]);
        throw new Exception('Invalid JSON request data');
    }
    
    if (!isset($data['merchant_reference_id']) || empty($data['merchant_reference_id'])) {
        throw new Exception('Missing merchant_reference_id');
    }
    
    $merchantRefId = sanitizeInput($data['merchant_reference_id']);
    
    // First check if we have the transaction in database
    $dbTransaction = getTransactionByReferenceId($merchantRefId);
    
    // Call PayNinja API - According to docs, this should be POST with merchant_reference_id in body
    $requestBody = json_encode([
        'merchant_reference_id' => $merchantRefId
    ]);
    
    $ch = curl_init(API_BASE_URL . '/api/v1/payout/transactionStatus');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'api-Key: ' . API_KEY
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        logError('CURL Error in status check', ['error' => $curlError, 'reference_id' => $merchantRefId]);
        throw new Exception('Network error: ' . $curlError);
    }
    
    $result = json_decode($response, true);
    
    if ($httpCode !== 200) {
        $errorMsg = $result['message'] ?? 'Failed to get transaction status';
        logError('PayNinja API error in status check', [
            'http_code' => $httpCode,
            'response' => $result,
            'reference_id' => $merchantRefId
        ]);
        throw new Exception($errorMsg);
    }
    
    // Update transaction status in database
    $status = $result['data']['transaction_status'] ?? $result['status'] ?? 'PENDING';
    $apiResponse = json_encode($result);
    
    // Map PayNinja status to our status
    // PayNinja status values: pending, failed, processing, success, reversed (lowercase)
    $statusLower = strtolower($status);
    $statusMap = [
        'pending' => 'PENDING',
        'processing' => 'PROCESSING',
        'success' => 'SUCCESS',
        'failed' => 'FAILED',
        'reversed' => 'FAILED'  // Reversed transactions are marked as FAILED
    ];
    $dbStatus = $statusMap[$statusLower] ?? 'PENDING';
    
    updateTransactionStatus($merchantRefId, $dbStatus, $apiResponse);
    
    // Log activity if transaction exists in database
    if ($dbTransaction) {
        logTransactionActivity($dbTransaction['id'], $merchantRefId, 'STATUS_CHECK', $result);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

