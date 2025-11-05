<?php
/**
 * Wallet and Topup Request Management Functions
 */

require_once __DIR__ . '/../config.php';

/**
 * Get or create vendor wallet
 */
function getVendorWallet($vendorId) {
    $conn = getDBConnection();
    
    // Check if wallet exists
    $stmt = $conn->prepare("SELECT * FROM vendor_wallets WHERE vendor_id = ? LIMIT 1");
    $stmt->bind_param('s', $vendorId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $wallet = $result->fetch_assoc();
        $stmt->close();
        return $wallet;
    }
    
    // Create wallet if doesn't exist
    $stmt->close();
    $balance = 0.00;
    $currency = 'INR';
    
    $insertStmt = $conn->prepare("
        INSERT INTO vendor_wallets (vendor_id, balance, currency) 
        VALUES (?, ?, ?)
    ");
    $insertStmt->bind_param('sds', $vendorId, $balance, $currency);
    $insertStmt->execute();
    $insertStmt->close();
    
    // Return newly created wallet
    $stmt = $conn->prepare("SELECT * FROM vendor_wallets WHERE vendor_id = ? LIMIT 1");
    $stmt->bind_param('s', $vendorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $wallet = $result->fetch_assoc();
    $stmt->close();
    
    return $wallet;
}

/**
 * Get vendor wallet balance
 */
function getVendorBalance($vendorId) {
    $wallet = getVendorWallet($vendorId);
    return floatval($wallet['balance']);
}

/**
 * Add amount to vendor wallet (topup)
 */
function addToWallet($vendorId, $amount, $referenceId = null, $topupRequestId = null, $description = null) {
    $conn = getDBConnection();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get current wallet
        $wallet = getVendorWallet($vendorId);
        $balanceBefore = floatval($wallet['balance']);
        $balanceAfter = $balanceBefore + floatval($amount);
        
        // Update wallet balance
        $updateStmt = $conn->prepare("
            UPDATE vendor_wallets 
            SET balance = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE vendor_id = ?
        ");
        $updateStmt->bind_param('ds', $balanceAfter, $vendorId);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Record transaction
        $transStmt = $conn->prepare("
            INSERT INTO wallet_transactions 
            (vendor_id, transaction_type, amount, balance_before, balance_after, reference_id, topup_request_id, description)
            VALUES (?, 'topup', ?, ?, ?, ?, ?, ?)
        ");
        $transStmt->bind_param('sdddsis', 
            $vendorId, 
            $amount, 
            $balanceBefore, 
            $balanceAfter, 
            $referenceId,
            $topupRequestId,
            $description
        );
        $transStmt->execute();
        $transactionId = $conn->insert_id;
        $transStmt->close();
        
        $conn->commit();
        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter
        ];
    } catch (Exception $e) {
        $conn->rollback();
        logError('Error adding to wallet', ['error' => $e->getMessage(), 'vendor_id' => $vendorId]);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Deduct amount from vendor wallet
 */
function deductFromWallet($vendorId, $amount, $referenceId = null, $description = null) {
    $conn = getDBConnection();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get current wallet
        $wallet = getVendorWallet($vendorId);
        $balanceBefore = floatval($wallet['balance']);
        
        if ($balanceBefore < floatval($amount)) {
            throw new Exception('Insufficient balance');
        }
        
        $balanceAfter = $balanceBefore - floatval($amount);
        
        // Update wallet balance
        $updateStmt = $conn->prepare("
            UPDATE vendor_wallets 
            SET balance = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE vendor_id = ?
        ");
        $updateStmt->bind_param('ds', $balanceAfter, $vendorId);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Record transaction
        $transStmt = $conn->prepare("
            INSERT INTO wallet_transactions 
            (vendor_id, transaction_type, amount, balance_before, balance_after, reference_id, description)
            VALUES (?, 'deduction', ?, ?, ?, ?, ?)
        ");
        $transStmt->bind_param('sdddss', 
            $vendorId, 
            $amount, 
            $balanceBefore, 
            $balanceAfter, 
            $referenceId,
            $description
        );
        $transStmt->execute();
        $transactionId = $conn->insert_id;
        $transStmt->close();
        
        $conn->commit();
        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter
        ];
    } catch (Exception $e) {
        $conn->rollback();
        logError('Error deducting from wallet', ['error' => $e->getMessage(), 'vendor_id' => $vendorId]);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Create topup request
 */
function createTopupRequest($vendorId, $amount, $requestId = null) {
    $conn = getDBConnection();
    
    if (!$requestId) {
        $requestId = 'TOPUP_' . time() . '_' . substr(md5($vendorId . time()), 0, 8);
    }
    
    $currency = 'INR';
    $status = 'pending';
    
    $stmt = $conn->prepare("
        INSERT INTO topup_requests (vendor_id, request_id, amount, currency, status)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('ssdss', $vendorId, $requestId, $amount, $currency, $status);
    
    if ($stmt->execute()) {
        $requestIdInserted = $conn->insert_id;
        $stmt->close();
        return [
            'success' => true,
            'id' => $requestIdInserted,
            'request_id' => $requestId
        ];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'error' => $error];
    }
}

/**
 * Get topup request by ID
 */
function getTopupRequest($requestId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT tr.*, u.email as vendor_email, u.status as vendor_status
        FROM topup_requests tr
        LEFT JOIN users u ON tr.vendor_id = u.id
        WHERE tr.request_id = ? OR tr.id = ?
        LIMIT 1
    ");
    $stmt->bind_param('si', $requestId, $requestId);
    $stmt->execute();
    $result = $stmt->get_result();
    $request = $result->fetch_assoc();
    $stmt->close();
    return $request;
}

/**
 * Update topup request status
 */
function updateTopupRequestStatus($requestId, $status, $adminId = null, $adminNotes = null, $rejectionReason = null) {
    $conn = getDBConnection();
    
    $processedAt = ($status === 'approved' || $status === 'rejected') ? date('Y-m-d H:i:s') : null;
    
    $stmt = $conn->prepare("
        UPDATE topup_requests 
        SET status = ?, admin_id = ?, admin_notes = ?, rejection_reason = ?, 
            processed_at = ?, updated_at = CURRENT_TIMESTAMP
        WHERE request_id = ? OR id = ?
    ");
    $stmt->bind_param('ssssssi', $status, $adminId, $adminNotes, $rejectionReason, $processedAt, $requestId, $requestId);
    
    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'error' => $error];
    }
}

/**
 * Get wallet transactions for vendor
 */
function getWalletTransactions($vendorId, $limit = 50, $offset = 0) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT * FROM wallet_transactions 
        WHERE vendor_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('sii', $vendorId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    $stmt->close();
    return $transactions;
}

