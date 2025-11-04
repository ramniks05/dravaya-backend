<?php
/**
 * Vendor Payout Initiate API
 * Vendor can make payments using saved beneficiaries or manual entry
 * Automatically deducts amount from vendor wallet
 */

require_once __DIR__ . '/../../cors.php';
require_once '../../../config.php';
require_once '../../../database/functions.php';
require_once '../../../database/wallet_functions.php';

// Set error handler to catch all errors and return JSON
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $message,
        'file' => basename($file),
        'line' => $line
    ]);
    exit();
});

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

try {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    if (!$data || !is_array($data)) {
        throw new Exception('Invalid JSON request data');
    }
    
    // Validate required fields
    if (!isset($data['vendor_id']) || empty($data['vendor_id'])) {
        throw new Exception('vendor_id is required');
    }
    
    if (!isset($data['amount'])) {
        throw new Exception('amount is required');
    }
    
    $vendorId = trim($data['vendor_id']);
    $amount = floatval($data['amount']);
    $beneficiaryId = isset($data['beneficiary_id']) ? intval($data['beneficiary_id']) : null;
    $transferType = $data['transfer_type'] ?? null;
    $merchantRefId = $data['merchant_reference_id'] ?? null;
    $narration = $data['narration'] ?? 'PAYNINJA Fund Transfer';
    
    // Generate merchant reference ID if not provided
    if (!$merchantRefId) {
        $merchantRefId = 'PAYOUT_' . time() . '_' . substr(md5($vendorId . time()), 0, 8);
    }
    
    // Validate amount
    if ($amount <= 0) {
        throw new Exception('Amount must be greater than 0');
    }
    
    // Verify vendor exists and is active
    $conn = getDBConnection();
    $vendorStmt = $conn->prepare("SELECT id, email, status FROM users WHERE id = ? AND role = 'vendor' LIMIT 1");
    $vendorStmt->bind_param('s', $vendorId);
    $vendorStmt->execute();
    $vendorResult = $vendorStmt->get_result();
    
    if ($vendorResult->num_rows === 0) {
        $vendorStmt->close();
        throw new Exception('Vendor not found');
    }
    
    $vendor = $vendorResult->fetch_assoc();
    $vendorStmt->close();
    
    if ($vendor['status'] !== 'active') {
        throw new Exception('Vendor account is not active');
    }
    
    // Check wallet balance
    $walletBalance = getVendorBalance($vendorId);
    
    if ($walletBalance < $amount) {
        throw new Exception('Insufficient wallet balance. Available: ' . number_format($walletBalance, 2));
    }
    
    // Get beneficiary details (if beneficiary_id is provided)
    $beneficiary = null;
    if ($beneficiaryId) {
        $benStmt = $conn->prepare("
            SELECT * FROM beneficiaries 
            WHERE id = ? AND vendor_id = ? AND is_active = 1
            LIMIT 1
        ");
        $benStmt->bind_param('is', $beneficiaryId, $vendorId);
        $benStmt->execute();
        $benResult = $benStmt->get_result();
        
        if ($benResult->num_rows === 0) {
            $benStmt->close();
            throw new Exception('Beneficiary not found or inactive. Please check beneficiary ID: ' . $beneficiaryId);
        }
        
        $beneficiary = $benResult->fetch_assoc();
        $benStmt->close();
        
        // Use beneficiary's transfer type if not specified
        if (!$transferType) {
            $transferType = $beneficiary['transfer_type'];
        }
        
        // Validate transfer type matches
        if ($transferType !== $beneficiary['transfer_type']) {
            throw new Exception('Transfer type does not match beneficiary transfer type');
        }
    } else {
        // Manual entry - validate all required fields
        $requiredFields = ['ben_name', 'ben_phone_number', 'transfer_type'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        $transferType = $data['transfer_type'];
        
        // Validate transfer type specific fields
        if ($transferType === 'UPI') {
            if (!isset($data['ben_vpa_address']) || empty($data['ben_vpa_address'])) {
                throw new Exception('ben_vpa_address is required for UPI transfers');
            }
        } else {
            if (!isset($data['ben_account_number']) || empty($data['ben_account_number']) ||
                !isset($data['ben_ifsc']) || empty($data['ben_ifsc']) ||
                !isset($data['ben_bank_name']) || empty($data['ben_bank_name'])) {
                throw new Exception('Account details (account_number, ifsc, bank_name) are required for IMPS/NEFT transfers');
            }
        }
    }
    
    // Build payload for PayNinja API
    if ($beneficiary) {
        // Use beneficiary details
        $payload = [
            'ben_name' => trim($beneficiary['name']),
            'ben_phone_number' => (string)$beneficiary['phone_number'],
            'amount' => (string)$amount,
            'merchant_reference_id' => $merchantRefId,
            'transfer_type' => $beneficiary['transfer_type'],
            'apicode' => API_CODE,
            'narration' => $narration
        ];
        
        if ($beneficiary['transfer_type'] === 'UPI') {
            $payload['ben_vpa_address'] = trim($beneficiary['vpa_address']);
        } else {
            $payload['ben_account_number'] = (string)$beneficiary['account_number'];
            $payload['ben_ifsc'] = strtoupper(trim($beneficiary['ifsc']));
            $payload['ben_bank_name'] = strtolower(trim($beneficiary['bank_name']));
        }
    } 
    
    // Validate amount
    if (!validateAmount($amount)) {
        throw new Exception('Invalid amount');
    }
    
    // Generate signature according to PayNinja documentation
    // UPI format: {ben_name}-{ben_phone_number}-{ben_vpa_address}-{amount}-{merchant_reference_id}-{transfer_type}-{apicode}-{narration}{secret_key}
    // IMPS/NEFT format: {ben_name}-{ben_phone_number}-{ben_account_number}{ben_ifsc}-{ben_bank_name}-{amount}-{merchant_reference_id}-{transfer_type}-{apicode}-{narration}{secret_key}
    $narration = $payload['narration'];
    $apicodeStr = (string)$payload['apicode'];
    
   /* if ($payload['transfer_type'] === 'UPI') {
        // UPI signature format
        $signatureString = "{$payload['ben_name']}-{$payload['ben_phone_number']}-{$payload['ben_vpa_address']}-{$payload['amount']}-{$payload['merchant_reference_id']}-{$payload['transfer_type']}-{$apicodeStr}-{$narration}" . SECRET_KEY;
    } else {
        // IMPS/NEFT signature format
        $signatureString = "{$payload['ben_name']}-{$payload['ben_phone_number']}-{$payload['ben_account_number']}{$payload['ben_ifsc']}-{$payload['ben_bank_name']}-{$payload['amount']}-{$payload['merchant_reference_id']}-{$payload['transfer_type']}-{$apicodeStr}-{$narration}" . SECRET_KEY;
    }*/
    $signatureString = implode("-", $payload) . SECRET_KEY;
    $payload['signature'] = hash('sha256', $signatureString);
    
    // Log signature for debugging
    $payloadJson = json_encode($payload);

    logError('Signature generated', [
        'payload' =>  $payloadJson = json_encode($payload),
        'signature' => $payload['signature']
    ], false);

    // Generate IV - PayNinja expects 16-character alphanumeric IV
    $iv = generateIV();
    
    // Encrypt using AES-256-CBC (openssl_encrypt uses first 32 bytes of key for AES-256)
    $encdata = encrypt_decrypt('encrypt', $payloadJson, SECRET_KEY, $iv);
    
    if ($encdata === false || empty($encdata)) {
        throw new Exception('Failed to encrypt payment data');
    }
    
    // Test decryption to verify
    $decrypted = encrypt_decrypt('decrypt', $encdata, SECRET_KEY, $iv);
    logError('Decryption Test', [
        'iv' =>$iv,
        'encdata' => $encdata,
        'original' => $payloadJson,
        'decrypted' => $decrypted,
        'match' => $payloadJson === $decrypted
    ], false);
    
    // Prepare request to PayNinja
    // PayNinja API expects lowercase 'iv' (not 'Iv')
    
    $requestBody = [
        'encdata' => $encdata,
        'iv' => $iv  // PayNinja API expects lowercase 'iv'
    ];
    
    // Log request for debugging
    logError('PayNinja API Request', [
        'endpoint' => '/api/v1/payout/fundTransfer',
        'merchant_reference_id' => $payload['merchant_reference_id']
    ], false);
    
    // Call PayNinja API
    $ch = curl_init(API_BASE_URL . '/api/v1/payout/fundTransfer');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
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
        logError('CURL Error in vendor payout', ['error' => $curlError, 'vendor_id' => $vendorId]);
        throw new Exception('Network error: ' . $curlError);
    }
    
    $result = json_decode($response, true);
    
    // Log PayNinja response for debugging
    logError('PayNinja API Response', [
        'http_code' => $httpCode,
        'response' => $result,
        'merchant_ref_id' => $merchantRefId
    ], false);
    
    // Prepare transaction data
    $transactionData = [
        'merchant_reference_id' => $merchantRefId,
        'ben_name' => $payload['ben_name'],
        'ben_phone_number' => $payload['ben_phone_number'],
        'transfer_type' => $payload['transfer_type'],
        'amount' => $amount,
        'narration' => $narration,
        'vendor_id' => $vendorId,
        'beneficiary_id' => $beneficiaryId
    ];
    
    if ($payload['transfer_type'] === 'UPI') {
        $transactionData['ben_vpa_address'] = $payload['ben_vpa_address'];
    } else {
        $transactionData['ben_account_number'] = $payload['ben_account_number'];
        $transactionData['ben_ifsc'] = $payload['ben_ifsc'];
        $transactionData['ben_bank_name'] = $payload['ben_bank_name'];
    }
    
    $walletDeducted = false;
    
    if ($httpCode !== 200) {
        // API call failed - don't deduct wallet
        $errorMsg = $result['message'] ?? 'Fund transfer request failed';
        
        // Include detailed errors from PayNinja if available
        if (isset($result['errors'])) {
            if (is_array($result['errors'])) {
                $errorDetails = is_array($result['errors'][0]) 
                    ? ($result['errors'][0]['description'] ?? implode(', ', $result['errors']))
                    : implode(', ', $result['errors']);
                $errorMsg .= ' - ' . $errorDetails;
            } else {
                $errorMsg .= ' - ' . (is_string($result['errors']) ? $result['errors'] : json_encode($result['errors']));
            }
        }
        
        $transactionData['status'] = 'FAILED';
        $transactionData['api_response'] = json_encode($result);
        $transactionData['api_error'] = $errorMsg;
        
        // Save failed transaction
        $transactionId = saveTransaction($transactionData);
        if ($transactionId) {
            logTransactionActivity($transactionId, $merchantRefId, 'ERROR', $result);
        }
        
        // Log detailed error
        logError('PayNinja API validation error', [
            'http_code' => $httpCode,
            'error_message' => $errorMsg,
            'errors' => $result['errors'] ?? null,
            'full_response' => $result,
            'merchant_ref_id' => $merchantRefId
        ]);
        
        throw new Exception($errorMsg);
    }
    
    // API call successful - deduct from wallet
    $deductResult = deductFromWallet(
        $vendorId,
        $amount,
        $merchantRefId,
        "Payout to {$payload['ben_name']} - {$payload['transfer_type']} - Ref: {$merchantRefId}"
    );
    
    if (!$deductResult['success']) {
        // Wallet deduction failed - log error but transaction was initiated
        logError('Wallet deduction failed after successful payout', [
            'vendor_id' => $vendorId,
            'amount' => $amount,
            'error' => $deductResult['error'] ?? 'Unknown error'
        ]);
        // Continue - transaction was successful with PayNinja
    } else {
        $walletDeducted = true;
    }
    
    // Map PayNinja status to our status
    // PayNinja status values: pending, failed, processing, success, reversed (lowercase in response)
    $payninjaStatus = strtolower($result['data']['status'] ?? 'pending');
    $statusMap = [
        'pending' => 'PENDING',
        'processing' => 'PROCESSING',
        'success' => 'SUCCESS',
        'failed' => 'FAILED',
        'reversed' => 'FAILED'  // Reversed transactions are marked as FAILED in our system
    ];
    $mappedStatus = $statusMap[$payninjaStatus] ?? 'PENDING';
    
    // Save successful transaction
    $transactionData['status'] = $mappedStatus;
    $transactionData['api_response'] = json_encode($result);
    $transactionData['payninja_transaction_id'] = $result['data']['transaction_id'] ?? $result['transaction_id'] ?? null;
    $transactionId = saveTransaction($transactionData);
    
    // Log activity
    if ($transactionId) {
        logTransactionActivity($transactionId, $merchantRefId, 'REQUEST', $transactionData);
        logTransactionActivity($transactionId, $merchantRefId, 'RESPONSE', $result);
    }
    
    logError('Vendor payout initiated', [
        'vendor_id' => $vendorId,
        'merchant_ref_id' => $merchantRefId,
        'amount' => $amount,
        'wallet_deducted' => $walletDeducted,
        'balance_after' => $deductResult['balance_after'] ?? null,
        'payninja_status' => $payninjaStatus,
        'mapped_status' => $mappedStatus
    ], false);
    
    // Return response
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Payout initiated successfully',
        'data' => [
            'transaction' => [
                'merchant_reference_id' => $merchantRefId,
                'payninja_transaction_id' => $transactionData['payninja_transaction_id'],
                'amount' => $amount,
                'transfer_type' => $payload['transfer_type'],
                'status' => $mappedStatus,
                'payninja_status' => $payninjaStatus  // Original PayNinja status
            ],
            'wallet' => [
                'balance_before' => $deductResult['balance_before'] ?? $walletBalance,
                'balance_after' => $deductResult['balance_after'] ?? $walletBalance,
                'deducted' => $walletDeducted
            ],
            'beneficiary_used' => $beneficiaryId ? true : false,
            'payninja_response' => $result
        ]
    ]);
    
} catch (Exception $e) {
    $errorCode = http_response_code();
    if ($errorCode === 200 || !$errorCode) {
        http_response_code(400);
    }
    
    $errorMessage = $e->getMessage();
    if (empty($errorMessage)) {
        $errorMessage = 'Unknown error occurred';
    }
    
    $errorResponse = [
        'status' => 'error',
        'message' => $errorMessage,
        'error_type' => get_class($e)
    ];
    
    // Always include debug info for vendor payout errors to help diagnose issues
    $errorResponse['debug'] = [
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ];
    
    // Log full error details
    logError('Vendor payout error', [
        'error' => $errorMessage,
        'error_type' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'raw_input' => $rawInput ?? 'not set',
        'vendor_id' => $vendorId ?? 'not set',
        'amount' => $amount ?? 'not set'
    ]);
    
    echo json_encode($errorResponse, JSON_PRETTY_PRINT);
}

