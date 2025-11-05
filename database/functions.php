<?php
/**
 * Database Helper Functions for PayNinja Payout
 */

require_once __DIR__ . '/../config.php';

/**
 * Save transaction to database
 */
function saveTransaction($transactionData) {
    try {
        $conn = getDBConnection();
        
        // Check if transaction already exists
        $checkQuery = "SELECT id FROM transactions WHERE merchant_reference_id = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param('s', $transactionData['merchant_reference_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing transaction
            $row = $result->fetch_assoc();
            $transactionId = $row['id'];
            
            // Extract values to variables (bind_param requires references)
            $payninjaTxnId = $transactionData['payninja_transaction_id'] ?? null;
            $status = $transactionData['status'] ?? 'PENDING';
            $apiResponse = $transactionData['api_response'] ?? null;
            $apiError = $transactionData['api_error'] ?? null;
            
            $updateQuery = "UPDATE transactions SET 
                payninja_transaction_id = ?,
                status = ?,
                api_response = ?,
                api_error = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
            
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param(
                'ssssi',
                $payninjaTxnId,
                $status,
                $apiResponse,
                $apiError,
                $transactionId
            );
            $stmt->execute();
            $stmt->close();
            
            return $transactionId;
        } else {
            // Insert new transaction
            // Check if vendor_id and beneficiary_id columns exist
            $hasVendorId = isset($transactionData['vendor_id']);
            $hasBeneficiaryId = isset($transactionData['beneficiary_id']);
            
            if ($hasVendorId || $hasBeneficiaryId) {
                // Insert with vendor_id and beneficiary_id
                // Extract values to variables (bind_param requires references)
                $merchantRefId = $transactionData['merchant_reference_id'];
                $payninjaTxnId = $transactionData['payninja_transaction_id'] ?? null;
                $vendorId = $transactionData['vendor_id'] ?? null;
                $beneficiaryId = $transactionData['beneficiary_id'] ?? null;
                $benName = $transactionData['ben_name'];
                $benPhone = $transactionData['ben_phone_number'];
                $benVpa = $transactionData['ben_vpa_address'] ?? null;
                $benAccount = $transactionData['ben_account_number'] ?? null;
                $benIfsc = $transactionData['ben_ifsc'] ?? null;
                $benBank = $transactionData['ben_bank_name'] ?? null;
                $transferType = $transactionData['transfer_type'];
                $amount = $transactionData['amount'];
                $narration = $transactionData['narration'] ?? null;
                $status = $transactionData['status'] ?? 'PENDING';
                $apiResponse = $transactionData['api_response'] ?? null;
                $apiError = $transactionData['api_error'] ?? null;
                
                $insertQuery = "INSERT INTO transactions (
                    merchant_reference_id, payninja_transaction_id,
                    vendor_id, beneficiary_id,
                    ben_name, ben_phone_number, ben_vpa_address,
                    ben_account_number, ben_ifsc, ben_bank_name,
                    transfer_type, amount, narration, status,
                    api_response, api_error
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param(
                    'sssissdsssdsssss',
                    $merchantRefId,
                    $payninjaTxnId,
                    $vendorId,
                    $beneficiaryId,
                    $benName,
                    $benPhone,
                    $benVpa,
                    $benAccount,
                    $benIfsc,
                    $benBank,
                    $transferType,
                    $amount,
                    $narration,
                    $status,
                    $apiResponse,
                    $apiError
                );
            } else {
                // Original insert without vendor_id/beneficiary_id
                // Extract values to variables (bind_param requires references)
                $merchantRefId = $transactionData['merchant_reference_id'];
                $payninjaTxnId = $transactionData['payninja_transaction_id'] ?? null;
                $benName = $transactionData['ben_name'];
                $benPhone = $transactionData['ben_phone_number'];
                $benVpa = $transactionData['ben_vpa_address'] ?? null;
                $benAccount = $transactionData['ben_account_number'] ?? null;
                $benIfsc = $transactionData['ben_ifsc'] ?? null;
                $benBank = $transactionData['ben_bank_name'] ?? null;
                $transferType = $transactionData['transfer_type'];
                $amount = $transactionData['amount'];
                $narration = $transactionData['narration'] ?? null;
                $status = $transactionData['status'] ?? 'PENDING';
                $apiResponse = $transactionData['api_response'] ?? null;
                $apiError = $transactionData['api_error'] ?? null;
                
                $insertQuery = "INSERT INTO transactions (
                    merchant_reference_id, payninja_transaction_id,
                    ben_name, ben_phone_number, ben_vpa_address,
                    ben_account_number, ben_ifsc, ben_bank_name,
                    transfer_type, amount, narration, status,
                    api_response, api_error
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param(
                    'sssssssssdssss',
                    $merchantRefId,
                    $payninjaTxnId,
                    $benName,
                    $benPhone,
                    $benVpa,
                    $benAccount,
                    $benIfsc,
                    $benBank,
                    $transferType,
                    $amount,
                    $narration,
                    $status,
                    $apiResponse,
                    $apiError
                );
            }
            
            $stmt->execute();
            $transactionId = $conn->insert_id;
            $stmt->close();
            
            return $transactionId;
        }
        
    } catch (Exception $e) {
        logError('Failed to save transaction to database', [
            'error' => $e->getMessage(),
            'merchant_reference_id' => $transactionData['merchant_reference_id'] ?? 'N/A'
        ]);
        // Don't throw - allow API to continue even if DB save fails
        return false;
    }
}

/**
 * Update transaction status
 */
function updateTransactionStatus($merchantReferenceId, $status, $apiResponse = null, $apiError = null) {
    try {
        $conn = getDBConnection();
        
        $query = "UPDATE transactions SET 
            status = ?,
            api_response = ?,
            api_error = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE merchant_reference_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssss', $status, $apiResponse, $apiError, $merchantReferenceId);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
        
    } catch (Exception $e) {
        logError('Failed to update transaction status', [
            'error' => $e->getMessage(),
            'merchant_reference_id' => $merchantReferenceId
        ]);
        return false;
    }
}

/**
 * Get transaction by merchant reference ID
 */
function getTransactionByReferenceId($merchantReferenceId) {
    try {
        $conn = getDBConnection();
        
        $query = "SELECT * FROM transactions WHERE merchant_reference_id = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $merchantReferenceId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $transaction = $result->fetch_assoc();
            $stmt->close();
            return $transaction;
        }
        
        $stmt->close();
        return null;
        
    } catch (Exception $e) {
        logError('Failed to get transaction', [
            'error' => $e->getMessage(),
            'merchant_reference_id' => $merchantReferenceId
        ]);
        return null;
    }
}

/**
 * Log transaction activity
 */
function logTransactionActivity($transactionId, $merchantReferenceId, $logType, $logData) {
    try {
        $conn = getDBConnection();
        
        $query = "INSERT INTO transaction_logs (transaction_id, merchant_reference_id, log_type, log_data) 
                  VALUES (?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $logDataJson = is_array($logData) ? json_encode($logData) : $logData;
        $stmt->bind_param('isss', $transactionId, $merchantReferenceId, $logType, $logDataJson);
        $stmt->execute();
        $stmt->close();
        
        return true;
        
    } catch (Exception $e) {
        logError('Failed to log transaction activity', [
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Save balance check to history
 */
function saveBalanceHistory($balance, $currency, $apiResponse = null) {
    try {
        $conn = getDBConnection();
        
        $query = "INSERT INTO balance_history (balance, currency, api_response) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $apiResponseJson = is_array($apiResponse) ? json_encode($apiResponse) : $apiResponse;
        $stmt->bind_param('dss', $balance, $currency, $apiResponseJson);
        $stmt->execute();
        $stmt->close();
        
        return true;
        
    } catch (Exception $e) {
        logError('Failed to save balance history', [
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Get transaction statistics
 */
function getTransactionStats($startDate = null, $endDate = null) {
    try {
        $conn = getDBConnection();
        
        $query = "SELECT 
            COUNT(*) as total_transactions,
            SUM(CASE WHEN status = 'SUCCESS' THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'SUCCESS' THEN amount ELSE 0 END) as total_amount,
            SUM(amount) as total_transaction_amount
            FROM transactions";
        
        $params = [];
        $types = '';
        
        if ($startDate) {
            $query .= " WHERE created_at >= ?";
            $params[] = $startDate;
            $types .= 's';
        }
        
        if ($endDate) {
            $query .= $startDate ? " AND created_at <= ?" : " WHERE created_at <= ?";
            $params[] = $endDate;
            $types .= 's';
        }
        
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        $stmt->close();
        
        return $stats;
        
    } catch (Exception $e) {
        logError('Failed to get transaction stats', [
            'error' => $e->getMessage()
        ]);
        return null;
    }
}

/**
 * Update transaction status with webhook data (includes UTR)
 */
function updateTransactionStatusWithWebhook($merchantReferenceId, $status, $apiResponse = null, $apiError = null, $utr = null) {
    try {
        $conn = getDBConnection();
        
        // Check if UTR column exists
        $checkColumn = $conn->query("SHOW COLUMNS FROM transactions LIKE 'utr'");
        $hasUtrColumn = $checkColumn && $checkColumn->num_rows > 0;
        
        if ($hasUtrColumn && $utr !== null) {
            // Update with UTR
            $query = "UPDATE transactions SET 
                status = ?,
                api_response = ?,
                api_error = ?,
                utr = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE merchant_reference_id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('sssss', $status, $apiResponse, $apiError, $utr, $merchantReferenceId);
        } else {
            // Update without UTR
            $query = "UPDATE transactions SET 
                status = ?,
                api_response = ?,
                api_error = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE merchant_reference_id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssss', $status, $apiResponse, $apiError, $merchantReferenceId);
        }
        
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
        
    } catch (Exception $e) {
        logError('Failed to update transaction status from webhook', [
            'error' => $e->getMessage(),
            'merchant_reference_id' => $merchantReferenceId
        ]);
        return false;
    }
}

