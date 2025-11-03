<?php
/**
 * Get Wallet Transaction History API
 * Returns transaction history for vendor wallet
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
    $vendorId = $_GET['vendor_id'] ?? null;
    
    if (!$vendorId) {
        throw new Exception('vendor_id is required');
    }
    
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(100, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    
    // Verify vendor exists
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, email FROM users WHERE id = ? AND role = 'vendor' LIMIT 1");
    $stmt->bind_param('s', $vendorId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('Vendor not found');
    }
    
    $vendor = $result->fetch_assoc();
    $stmt->close();
    
    // Get total count
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM wallet_transactions WHERE vendor_id = ?");
    $countStmt->bind_param('s', $vendorId);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Get transactions
    $transactions = getWalletTransactions($vendorId, $limit, $offset);
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => [
            'vendor_id' => $vendorId,
            'transactions' => array_map(function($t) {
                return [
                    'id' => $t['id'],
                    'transaction_type' => $t['transaction_type'],
                    'amount' => floatval($t['amount']),
                    'currency' => $t['currency'],
                    'balance_before' => floatval($t['balance_before']),
                    'balance_after' => floatval($t['balance_after']),
                    'reference_id' => $t['reference_id'],
                    'description' => $t['description'],
                    'created_at' => $t['created_at']
                ];
            }, $transactions),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => intval($total),
                'total_pages' => ceil($total / $limit)
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    logError('Get wallet transactions error', ['error' => $e->getMessage()]);
}

