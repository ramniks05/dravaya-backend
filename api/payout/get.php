<?php
/**
 * Get Transaction Details API Endpoint
 * Returns transaction details from database by merchant_reference_id
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

// Allow both GET and POST requests
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
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
    // Get merchant_reference_id from request
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        
        if (!$data || !is_array($data)) {
            throw new Exception('Invalid JSON request data');
        }
        
        $merchantRefId = isset($data['merchant_reference_id']) ? trim($data['merchant_reference_id']) : null;
    } else {
        // GET request
        $merchantRefId = isset($_GET['merchant_reference_id']) ? trim($_GET['merchant_reference_id']) : null;
    }
    
    if (empty($merchantRefId)) {
        throw new Exception('merchant_reference_id is required');
    }
    
    // Get transaction from database
    $transaction = getTransactionByReferenceId($merchantRefId);
    
    if (!$transaction) {
        // Clean any output buffer
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        // Ensure proper headers
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8', true);
        }
        
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Transaction not found',
            'merchant_reference_id' => $merchantRefId
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        exit();
    }
    
    // Get transaction logs if available
    $conn = getDBConnection();
    $logsStmt = $conn->prepare("
        SELECT log_type, log_data, created_at 
        FROM transaction_logs 
        WHERE merchant_reference_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $logsStmt->bind_param('s', $merchantRefId);
    $logsStmt->execute();
    $logsResult = $logsStmt->get_result();
    
    $logs = [];
    while ($logRow = $logsResult->fetch_assoc()) {
        $logData = json_decode($logRow['log_data'], true);
        if ($logData === null) {
            $logData = $logRow['log_data'];
        }
        $logs[] = [
            'log_type' => $logRow['log_type'],
            'log_data' => $logData,
            'created_at' => $logRow['created_at']
        ];
    }
    $logsStmt->close();
    
    // Parse API response if available
    $apiResponseData = null;
    if (!empty($transaction['api_response'])) {
        $apiResponseData = json_decode($transaction['api_response'], true);
        if ($apiResponseData === null) {
            $apiResponseData = $transaction['api_response'];
        }
    }
    
    // Prepare response data
    $responseData = [
        'id' => intval($transaction['id']),
        'merchant_reference_id' => $transaction['merchant_reference_id'],
        'payninja_transaction_id' => $transaction['payninja_transaction_id'] ?? null,
        'utr' => $transaction['utr'] ?? null,
        'beneficiary' => [
            'name' => $transaction['ben_name'],
            'phone_number' => $transaction['ben_phone_number'],
            'vpa_address' => $transaction['ben_vpa_address'] ?? null,
            'account_number' => $transaction['ben_account_number'] ?? null,
            'ifsc' => $transaction['ben_ifsc'] ?? null,
            'bank_name' => $transaction['ben_bank_name'] ?? null
        ],
        'transaction' => [
            'transfer_type' => $transaction['transfer_type'],
            'amount' => floatval($transaction['amount']),
            'narration' => $transaction['narration'] ?? null,
            'status' => $transaction['status'],
            'payment_mode' => $transaction['payment_mode'] ?? null
        ],
        'vendor' => [
            'vendor_id' => $transaction['vendor_id'] ?? null,
            'beneficiary_id' => $transaction['beneficiary_id'] ?? null
        ],
        'api_response' => $apiResponseData,
        'api_error' => $transaction['api_error'] ?? null,
        'timestamps' => [
            'created_at' => $transaction['created_at'],
            'updated_at' => $transaction['updated_at']
        ],
        'logs' => $logs
    ];
    
    // Clean any output buffer
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    // Ensure proper headers
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8', true);
    }
    
    http_response_code(200);
    $response = [
        'status' => 'success',
        'message' => 'Transaction retrieved successfully',
        'data' => $responseData
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    exit();
    
} catch (Exception $e) {
    // Clean any output buffer
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    // Ensure proper headers
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8', true);
    }
    
    http_response_code(400);
    $errorResponse = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    
    echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    exit();
}

