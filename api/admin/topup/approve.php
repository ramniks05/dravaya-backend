<?php
/**
 * Approve/Reject Topup Request API
 * Admin endpoint to approve or reject a topup request
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
    if (!isset($data['request_id']) || empty($data['request_id'])) {
        throw new Exception('request_id is required');
    }
    
    if (!isset($data['action']) || !in_array($data['action'], ['approve', 'reject'])) {
        throw new Exception('action must be either "approve" or "reject"');
    }
    
    // TODO: Get admin_id from authentication token/session
    $adminId = $data['admin_id'] ?? null;
    
    if (!$adminId) {
        throw new Exception('admin_id is required');
    }
    
    $requestId = trim($data['request_id']);
    $action = $data['action'];
    $adminNotes = $data['admin_notes'] ?? null;
    $rejectionReason = $data['rejection_reason'] ?? null;
    
    // Verify admin exists
    $conn = getDBConnection();
    $adminStmt = $conn->prepare("SELECT id, role FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
    $adminStmt->bind_param('s', $adminId);
    $adminStmt->execute();
    $adminResult = $adminStmt->get_result();
    
    if ($adminResult->num_rows === 0) {
        $adminStmt->close();
        throw new Exception('Admin not found');
    }
    $adminStmt->close();
    
    // Get topup request
    $topupRequest = getTopupRequest($requestId);
    
    if (!$topupRequest) {
        throw new Exception('Topup request not found');
    }
    
    // Check if already processed
    if ($topupRequest['status'] !== 'pending') {
        throw new Exception("Topup request is already {$topupRequest['status']}");
    }
    
    // Verify vendor is still active
    $vendorStmt = $conn->prepare("SELECT id, status FROM users WHERE id = ? AND role = 'vendor' LIMIT 1");
    $vendorStmt->bind_param('s', $topupRequest['vendor_id']);
    $vendorStmt->execute();
    $vendorResult = $vendorStmt->get_result();
    
    if ($vendorResult->num_rows === 0) {
        $vendorStmt->close();
        throw new Exception('Vendor not found');
    }
    
    $vendor = $vendorResult->fetch_assoc();
    $vendorStmt->close();
    
    if ($vendor['status'] !== 'active') {
        throw new Exception('Cannot process topup request: Vendor account is not active');
    }
    
    $status = ($action === 'approve') ? 'approved' : 'rejected';
    
    // Update request status
    $updateResult = updateTopupRequestStatus(
        $requestId, 
        $status, 
        $adminId, 
        $adminNotes, 
        $rejectionReason
    );
    
    if (!$updateResult['success']) {
        throw new Exception($updateResult['error'] ?? 'Failed to update topup request');
    }
    
    // If approved, add amount to vendor wallet
    if ($action === 'approve') {
        $addResult = addToWallet(
            $topupRequest['vendor_id'],
            $topupRequest['amount'],
            $topupRequest['request_id'],
            $topupRequest['id'],
            "Topup request approved - Request ID: {$topupRequest['request_id']}"
        );
        
        if (!$addResult['success']) {
            // Rollback request status
            updateTopupRequestStatus($requestId, 'pending', null, null, null);
            throw new Exception('Failed to add amount to wallet: ' . ($addResult['error'] ?? 'Unknown error'));
        }
        
        logError('Topup approved and wallet credited', [
            'request_id' => $requestId,
            'vendor_id' => $topupRequest['vendor_id'],
            'amount' => $topupRequest['amount'],
            'admin_id' => $adminId,
            'balance_after' => $addResult['balance_after']
        ], false);
    } else {
        logError('Topup rejected', [
            'request_id' => $requestId,
            'vendor_id' => $topupRequest['vendor_id'],
            'amount' => $topupRequest['amount'],
            'admin_id' => $adminId,
            'rejection_reason' => $rejectionReason
        ], false);
    }
    
    // Get updated request
    $updatedRequest = getTopupRequest($requestId);
    
    // Get current wallet balance if approved
    $walletBalance = null;
    if ($action === 'approve') {
        $wallet = getVendorWallet($topupRequest['vendor_id']);
        $walletBalance = floatval($wallet['balance']);
    }
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => "Topup request {$action}d successfully",
        'data' => [
            'request' => [
                'id' => $updatedRequest['id'],
                'request_id' => $updatedRequest['request_id'],
                'vendor_id' => $updatedRequest['vendor_id'],
                'vendor_email' => $updatedRequest['vendor_email'],
                'amount' => floatval($updatedRequest['amount']),
                'status' => $updatedRequest['status'],
                'admin_id' => $updatedRequest['admin_id'],
                'admin_notes' => $updatedRequest['admin_notes'],
                'rejection_reason' => $updatedRequest['rejection_reason'],
                'processed_at' => $updatedRequest['processed_at']
            ],
            'wallet_balance' => $walletBalance
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    logError('Approve/reject topup error', ['error' => $e->getMessage()]);
}

