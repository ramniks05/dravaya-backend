<?php
/**
 * Check Transaction Status API Endpoint
 * Checks latest transaction status from PayNinja and updates database
 * Updates UTR if not already set
 */

// Disable error display to prevent HTML in JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// CORS Headers - Must be first
require_once __DIR__ . '/../cors.php';

require_once '../../config.php';
require_once '../../database/functions.php';
require_once '../../database/wallet_functions.php';

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
    $previousStatus = $dbTransaction['status'] ?? null;
    $vendorId = $dbTransaction['vendor_id'] ?? null;
    $transactionAmount = isset($dbTransaction['amount']) ? floatval($dbTransaction['amount']) : 0;
    
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
    
    // Extract data from PayNinja response according to their API format
    // Response format: { "status": "success", "data": { "status": "processing", "utr": null, "mode": "NEFT", ... } }
    $payninjaStatus = $result['data']['status'] ?? 'pending';  // Fixed: use 'status' not 'transaction_status'
    $utr = $result['data']['utr'] ?? null;
    $apiResponse = json_encode($result);
    
    // Map PayNinja status to our status
    // PayNinja status values: pending, failed, processing, success, reversed (lowercase)
    $statusLower = strtolower($payninjaStatus);
    $statusMap = [
        'pending' => 'PENDING',
        'processing' => 'PROCESSING',
        'success' => 'SUCCESS',
        'failed' => 'FAILED',
        'reversed' => 'FAILED'  // Reversed transactions are marked as FAILED
    ];
    $dbStatus = $statusMap[$statusLower] ?? 'PENDING';
    
    // Always update both status and UTR (if provided) together
    // Use webhook function which handles both status and UTR updates
    // This ensures status is always updated, and UTR is updated when available
    updateTransactionStatusWithWebhook($merchantRefId, $dbStatus, $apiResponse, null, $utr);

    $walletRefundResult = null;
    if (
        $dbTransaction &&
        $previousStatus !== 'FAILED' &&
        $dbStatus === 'FAILED' &&
        !empty($vendorId) &&
        $transactionAmount > 0
    ) {
        $walletRefundResult = refundVendorWalletForFailedPayout(
            $vendorId,
            $transactionAmount,
            $merchantRefId,
            'Refund for failed payout ' . $merchantRefId
        );

        if (!($walletRefundResult['success'] ?? false) && !($walletRefundResult['already_processed'] ?? false)) {
            logError('Failed to process vendor wallet refund for failed payout', [
                'merchant_reference_id' => $merchantRefId,
                'vendor_id' => $vendorId,
                'amount' => $transactionAmount,
                'refund_result' => $walletRefundResult
            ]);
        }
    }
    
    // Log activity if transaction exists in database
    if ($dbTransaction) {
        $logPayload = $result;
        if ($walletRefundResult !== null) {
            $logPayload['wallet_refund'] = $walletRefundResult;
        }
        logTransactionActivity($dbTransaction['id'], $merchantRefId, 'STATUS_CHECK', $logPayload);
    }
    
    // Return PayNinja response as-is
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

