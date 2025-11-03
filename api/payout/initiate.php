<?php
/**
 * Initiate Fund Transfer API Endpoint
 * Handles fund transfer requests to PayNinja
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
    // Get request data
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    if (!$data) {
        logError('Invalid JSON input', ['raw_input' => substr($rawInput, 0, 500)]);
        throw new Exception('Invalid JSON request data');
    }
    
    // Validate required fields
    $required = ['ben_name', 'ben_phone_number', 'amount', 'merchant_reference_id', 'transfer_type'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Validate transfer type
    $validTransferTypes = ['UPI', 'IMPS', 'NEFT'];
    if (!in_array($data['transfer_type'], $validTransferTypes)) {
        throw new Exception("Invalid transfer_type. Must be one of: " . implode(', ', $validTransferTypes));
    }
    
    // Validate phone number
    if (!validatePhoneNumber($data['ben_phone_number'])) {
        throw new Exception("Invalid phone number format. Must be a valid 10-digit Indian mobile number.");
    }
    
    // Validate amount
    if (!validateAmount($data['amount'])) {
        throw new Exception("Invalid amount. Must be a positive number.");
    }
    
    // Sanitize input data
    $data = sanitizeInput($data);
    
    // Build payload according to PayNinja documentation
    // Note: signature field should be null initially, we'll add it after generation
    $payload = [
        'ben_name' => $data['ben_name'],
        'ben_phone_number' => (string)$data['ben_phone_number'],
        'amount' => (string)$data['amount'],
        'merchant_reference_id' => $data['merchant_reference_id'],
        'transfer_type' => $data['transfer_type'],
        'apicode' => API_CODE,
        'narration' => $data['narration'] ?? 'PAYNINJA Fund Transfer',
        'signature' => null  // Will be set after signature generation
    ];
    
    // Add mode-specific fields
    if ($data['transfer_type'] === 'UPI') {
        if (!isset($data['ben_vpa_address']) || empty($data['ben_vpa_address'])) {
            throw new Exception('Missing required field: ben_vpa_address');
        }
        // Validate UPI format (basic validation)
        if (!preg_match('/^[\w\.-]+@[\w]+$/', $data['ben_vpa_address'])) {
            throw new Exception('Invalid UPI address format');
        }
        $payload['ben_vpa_address'] = $data['ben_vpa_address'];
    } else {
        if (!isset($data['ben_account_number']) || empty($data['ben_account_number']) || 
            !isset($data['ben_ifsc']) || empty($data['ben_ifsc']) || 
            !isset($data['ben_bank_name']) || empty($data['ben_bank_name'])) {
            throw new Exception('Missing required fields for IMPS/NEFT: ben_account_number, ben_ifsc, ben_bank_name');
        }
        // Validate IFSC format (11 characters, alphanumeric)
        if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', strtoupper($data['ben_ifsc']))) {
            throw new Exception('Invalid IFSC code format');
        }
        $payload['ben_account_number'] = (string)$data['ben_account_number'];
        $payload['ben_ifsc'] = strtoupper($data['ben_ifsc']);
        $payload['ben_bank_name'] = strtolower($data['ben_bank_name']);
    }
    
    // Generate signature according to PayNinja documentation
    // UPI format: {ben_name}-{ben_phone_number}-{ben_vpa_address}-{amount}-{merchant_reference_id}-{transfer_type}-{apicode}-{narration}{secret_key}
    // IMPS/NEFT format: {ben_name}-{ben_phone_number}-{ben_account_number}{ben_ifsc}-{ben_bank_name}-{amount}-{merchant_reference_id}-{transfer_type}-{apicode}-{narration}{secret_key}
    // IMPORTANT: NO dash between ben_account_number and ben_ifsc for IMPS/NEFT
    $narration = $payload['narration'];
    $apicodeStr = (string)$payload['apicode'];
    
    if ($data['transfer_type'] === 'UPI') {
        // UPI signature format
        $signatureString = "{$payload['ben_name']}-{$payload['ben_phone_number']}-{$payload['ben_vpa_address']}-{$payload['amount']}-{$payload['merchant_reference_id']}-{$payload['transfer_type']}-{$apicodeStr}-{$narration}" . SECRET_KEY;
    } else {
        // IMPS/NEFT signature format
        // NOTE: NO dash between ben_account_number and ben_ifsc (they are concatenated directly)
        // Example: Gaurav Kumar-9876543210-1121431121541121HDFC0001234-hdfc-100-test123-IMPS-810-PAYNINJA Fund Transfer{secret}
        $signatureString = "{$payload['ben_name']}-{$payload['ben_phone_number']}-{$payload['ben_account_number']}{$payload['ben_ifsc']}-{$payload['ben_bank_name']}-{$payload['amount']}-{$payload['merchant_reference_id']}-{$payload['transfer_type']}-{$apicodeStr}-{$narration}" . SECRET_KEY;
    }
    
    $payload['signature'] = hash('sha256', $signatureString);
    
    // Log signature for debugging (remove in production)
    logError('Signature generated', [
        'signature_string_length' => strlen($signatureString),
        'signature_hash' => $payload['signature'],
        'transfer_type' => $data['transfer_type']
    ], false);
    
    // Generate IV and encrypt
    // According to PayNinja docs: IV should be 16 characters (alphanumeric)
    $iv = generateIV();
    $payloadJson = json_encode($payload);
    
    // Encrypt using PayNinja's method
    // openssl_encrypt will automatically use first 32 bytes of SECRET_KEY for AES-256
    // and first 16 bytes (or characters) of IV for CBC mode
    $encdata = encrypt_decrypt('encrypt', $payloadJson, SECRET_KEY, $iv);
    
    // Verify encryption worked
    if ($encdata === false || empty($encdata)) {
        logError('Encryption failed', [
            'payload_length' => strlen($payloadJson),
            'key_length' => strlen($keyToUse),
            'iv_length' => strlen($iv)
        ]);
        throw new Exception('Failed to encrypt payment data');
    }
    
    // Prepare request to PayNinja
    // According to PayNinja documentation, the field is "iv" (lowercase)
    // Request body: { "encdata": "...", "key": "...", "iv": "..." }
    $requestBody = [
        'encdata' => $encdata,
        'key' => SECRET_KEY,
        'iv' => $iv  // PayNinja API expects lowercase 'iv' as per documentation
    ];
    
    // Log request details for debugging (remove sensitive data in production)
    logError('PayNinja API Request', [
        'endpoint' => '/api/v1/payout/fundTransfer',
        'merchant_reference_id' => $payload['merchant_reference_id'],
        'transfer_type' => $payload['transfer_type'],
        'iv_length' => strlen($iv),
        'encdata_length' => strlen($encdata)
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
        logError('CURL Error in initiate payout', ['error' => $curlError, 'reference_id' => $data['merchant_reference_id'] ?? 'N/A']);
        throw new Exception('Network error: ' . $curlError);
    }
    
    $result = json_decode($response, true);
    
    // Prepare transaction data for database
    $transactionData = [
        'merchant_reference_id' => $data['merchant_reference_id'],
        'ben_name' => $data['ben_name'],
        'ben_phone_number' => $data['ben_phone_number'],
        'transfer_type' => $data['transfer_type'],
        'amount' => floatval($data['amount']),
        'narration' => $data['narration'] ?? 'PAYNINJA Fund Transfer'
    ];
    
    // Add transfer type specific fields
    if ($data['transfer_type'] === 'UPI') {
        $transactionData['ben_vpa_address'] = $data['ben_vpa_address'];
    } else {
        $transactionData['ben_account_number'] = $data['ben_account_number'];
        $transactionData['ben_ifsc'] = strtoupper($data['ben_ifsc']);
        $transactionData['ben_bank_name'] = strtolower($data['ben_bank_name']);
    }
    
    if ($httpCode !== 200) {
        $errorMsg = $result['message'] ?? 'Fund transfer request failed';
        
        // Save failed transaction to database
        $transactionData['status'] = 'FAILED';
        $transactionData['api_response'] = json_encode($result);
        $transactionData['api_error'] = $errorMsg;
        $transactionId = saveTransaction($transactionData);
        
        // Log activity
        if ($transactionId) {
            logTransactionActivity($transactionId, $data['merchant_reference_id'], 'ERROR', $result);
        }
        
        logError('PayNinja API error in initiate payout', [
            'http_code' => $httpCode,
            'response' => $result,
            'reference_id' => $data['merchant_reference_id'] ?? 'N/A'
        ]);
        throw new Exception($errorMsg);
    }
    
    // Save successful transaction to database
    $transactionData['status'] = 'PENDING'; // Initial status
    $transactionData['api_response'] = json_encode($result);
    $transactionData['payninja_transaction_id'] = $result['data']['transaction_id'] ?? $result['transaction_id'] ?? null;
    $transactionId = saveTransaction($transactionData);
    
    // Log activity
    if ($transactionId) {
        logTransactionActivity($transactionId, $data['merchant_reference_id'], 'REQUEST', $transactionData);
        logTransactionActivity($transactionId, $data['merchant_reference_id'], 'RESPONSE', $result);
    }
    
    // Log successful initiation
    logError('Payout initiated successfully', ['reference_id' => $data['merchant_reference_id'] ?? 'N/A'], false);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

