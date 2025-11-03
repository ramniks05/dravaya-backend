<?php
/**
 * Get Vendor Wallet Balance API
 * Returns the current wallet balance for the logged-in vendor
 */

require_once __DIR__ . '/../../cors.php';
require_once '../../../config.php';
require_once '../../../database/wallet_functions.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

try {
    // TODO: Get vendor_id from authentication token/session
    // For now, we'll accept it as a query parameter (should be removed after implementing auth)
    $vendorId = $_GET['vendor_id'] ?? null;
    
    if (!$vendorId) {
        throw new Exception('vendor_id is required');
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
        throw new Exception('Vendor account is not active');
    }
    
    // Get wallet balance
    $wallet = getVendorWallet($vendorId);
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => [
            'vendor_id' => $vendorId,
            'vendor_email' => $vendor['email'],
            'balance' => floatval($wallet['balance']),
            'currency' => $wallet['currency'],
            'updated_at' => $wallet['updated_at']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    logError('Get wallet balance error', ['error' => $e->getMessage()]);
}

