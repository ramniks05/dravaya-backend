<?php
/**
 * Get Account Balance API Endpoint
 */

// CORS Headers - Must be first
require_once __DIR__ . '/../cors.php';

require_once '../../config.php';
require_once '../../database/functions.php';

// Allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

try {
    // Call PayNinja API
    $ch = curl_init(API_BASE_URL . '/api/v1/account/balance');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
        logError('CURL Error in balance check', ['error' => $curlError]);
        throw new Exception('Network error: ' . $curlError);
    }
    
    $result = json_decode($response, true);
    
    if ($httpCode !== 200) {
        $errorMsg = $result['message'] ?? 'Failed to get account balance';
        logError('PayNinja API error in balance check', [
            'http_code' => $httpCode,
            'response' => $result
        ]);
        throw new Exception($errorMsg);
    }
    
    // Save balance to history if successful
    if (isset($result['data']['balance']) || isset($result['balance'])) {
        $balance = floatval($result['data']['balance'] ?? $result['balance'] ?? 0);
        $currency = $result['data']['currency'] ?? $result['currency'] ?? 'INR';
        saveBalanceHistory($balance, $currency, $result);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

