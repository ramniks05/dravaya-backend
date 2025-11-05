<?php
/**
 * PayNinja Fund Transfer Webhook Endpoint
 * Receives encrypted transaction status updates from PayNinja
 * 
 * Webhook URL: http://your-domain.com/backend/api/payout/webhook.php
 * Method: POST
 * 
 * PayNinja sends encrypted data in format:
 * {
 *   "data": "encrypted_base64_string",
 *   "iv": "16_character_iv"
 * }
 */

// Turn off error display to prevent HTML output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Start output buffering to catch any accidental output
if (ob_get_level() == 0) {
    ob_start();
}

require_once __DIR__ . '/../cors.php';
require_once '../../config.php';
require_once '../../database/functions.php';

// Re-start output buffering if cors.php ended it
if (ob_get_level() == 0) {
    ob_start();
}

// Set error handler to catch all errors and return JSON
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    // Clean any output buffer
    if (ob_get_level() > 0) {
        ob_clean();
    }
    // Ensure JSON content type
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8', true);
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $message,
        'file' => basename($file),
        'line' => $line
    ]);
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    exit();
});

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Clean any output buffer
    if (ob_get_level() > 0) {
        ob_clean();
    }
    // Ensure proper headers
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8', true);
    }
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    exit();
}

try {
    // Get raw input
    $rawInput = file_get_contents('php://input');
    
    // Log incoming webhook (for debugging)
    logError('Webhook Received', [
        'raw_input' => $rawInput,
        'headers' => getallheaders(),
        'method' => $_SERVER['REQUEST_METHOD']
    ], false);
    
    // Decode JSON input
    $webhookData = json_decode($rawInput, true);
    
    if (!$webhookData || !is_array($webhookData)) {
        throw new Exception('Invalid JSON request data');
    }
    
    // Validate required fields
    if (!isset($webhookData['data']) || empty($webhookData['data'])) {
        throw new Exception('Missing required field: data');
    }
    
    if (!isset($webhookData['iv']) || empty($webhookData['iv'])) {
        throw new Exception('Missing required field: iv');
    }
    
    $encryptedData = $webhookData['data'];
    $iv = $webhookData['iv'];
    
    // Decrypt the data
    $decryptedData = encrypt_decrypt('decrypt', $encryptedData, SECRET_KEY, $iv);
    
    if ($decryptedData === false || empty($decryptedData)) {
        logError('Webhook Decryption Failed', [
            'encrypted_data_length' => strlen($encryptedData),
            'iv' => $iv,
            'iv_length' => strlen($iv)
        ]);
        throw new Exception('Failed to decrypt webhook data');
    }
    
    // Parse decrypted JSON
    $transactionData = json_decode($decryptedData, true);
    
    if (!$transactionData || !is_array($transactionData)) {
        logError('Webhook Invalid Decrypted Data', [
            'decrypted_data' => $decryptedData
        ]);
        throw new Exception('Invalid decrypted data format');
    }
    
    // Log decrypted data (for debugging)
    logError('Webhook Decrypted', [
        'decrypted_data' => $transactionData
    ], false);
    
    // Validate required fields in decrypted data
    if (!isset($transactionData['merchant_reference_id']) || empty($transactionData['merchant_reference_id'])) {
        throw new Exception('Missing merchant_reference_id in webhook data');
    }
    
    if (!isset($transactionData['status'])) {
        throw new Exception('Missing status in webhook data');
    }
    
    $merchantRefId = $transactionData['merchant_reference_id'];
    $status = strtolower($transactionData['status']); // PayNinja sends lowercase
    $amount = isset($transactionData['amount']) ? floatval($transactionData['amount']) : null;
    $utr = isset($transactionData['utr']) ? $transactionData['utr'] : null;
    
    // Map PayNinja status to our database status
    // PayNinja status values: pending, failed, processing, success, reversed
    $statusMap = [
        'pending' => 'PENDING',
        'processing' => 'PROCESSING',
        'success' => 'SUCCESS',
        'failed' => 'FAILED',
        'reversed' => 'FAILED'  // Reversed transactions are marked as FAILED
    ];
    
    $dbStatus = $statusMap[$status] ?? 'PENDING';
    
    // Get existing transaction
    $transaction = getTransactionByReferenceId($merchantRefId);
    
    if (!$transaction) {
        logError('Webhook Transaction Not Found', [
            'merchant_reference_id' => $merchantRefId,
            'webhook_data' => $transactionData
        ]);
        // Still return success to PayNinja to prevent retries
        // But log the issue for investigation
    } else {
        // Update transaction status
        $apiResponse = json_encode($transactionData);
        $apiError = ($dbStatus === 'FAILED') ? 'Transaction failed as per webhook' : null;
        
        // Update transaction with new status and UTR if available
        $updateSuccess = updateTransactionStatusWithWebhook(
            $merchantRefId,
            $dbStatus,
            $apiResponse,
            $apiError,
            $utr
        );
        
        if ($updateSuccess) {
            // Log webhook activity
            logTransactionActivity(
                $transaction['id'],
                $merchantRefId,
                'WEBHOOK',
                $transactionData
            );
            
            logError('Webhook Processed Successfully', [
                'merchant_reference_id' => $merchantRefId,
                'old_status' => $transaction['status'],
                'new_status' => $dbStatus,
                'payninja_status' => $status,
                'utr' => $utr,
                'amount' => $amount
            ], false);
        } else {
            logError('Webhook Update Failed', [
                'merchant_reference_id' => $merchantRefId,
                'status' => $dbStatus
            ]);
        }
    }
    
    // Return success response to PayNinja
    // PayNinja expects a 200 response to acknowledge receipt
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8', true);
    }
    
    http_response_code(200);
    $response = [
        'status' => 'success',
        'message' => 'Webhook received and processed'
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    exit();
    
} catch (Exception $e) {
    // Log error but still return success to PayNinja to prevent retries
    // We'll investigate the error from logs
    logError('Webhook Error', [
        'error' => $e->getMessage(),
        'error_type' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'raw_input' => $rawInput ?? 'not set'
    ]);
    
    // Clean any output buffer
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    // Ensure proper headers
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8', true);
    }
    
    // Return 200 to PayNinja to prevent retries
    // Log the error for investigation
    http_response_code(200);
    $errorResponse = [
        'status' => 'error',
        'message' => 'Webhook received but processing failed',
        'error' => $e->getMessage()
    ];
    
    echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    exit();
}

