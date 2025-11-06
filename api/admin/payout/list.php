<?php
/**
 * Admin - List Payout Transactions
 * Lists all payout transactions with optional filters (vendor_id, vendor_email, status)
 */

// Disable error display to prevent HTML in JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../../cors.php';
require_once '../../../config.php';

// Allow GET or POST (JSON body)
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

try {
    $conn = getDBConnection();
    
    // Query parameters (support GET query or POST JSON body)
    $payload = [];
    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $payload = $decoded;
            }
        }
    }

    $get = $_GET ?? [];
    $vendorId = $payload['vendor_id'] ?? ($get['vendor_id'] ?? null);
    $vendorEmail = $payload['vendor_email'] ?? ($get['vendor_email'] ?? null);
    $status = $payload['status'] ?? ($get['status'] ?? null);
    $page = max(1, intval($payload['page'] ?? ($get['page'] ?? 1)));
    $limit = max(1, min(100, intval($payload['limit'] ?? ($get['limit'] ?? 50))));
    $offset = ($page - 1) * $limit;
    
    // Build WHERE conditions
    $whereConditions = [];
    $params = [];
    $paramTypes = '';
    
    if (!empty($vendorId)) {
        $whereConditions[] = 't.vendor_id = ?';
        $params[] = $vendorId;
        $paramTypes .= 's';
    }
    
    if (!empty($vendorEmail)) {
        $whereConditions[] = 'LOWER(u.email) LIKE LOWER(?)';
        $params[] = '%' . $vendorEmail . '%';
        $paramTypes .= 's';
    }
    
    if (!empty($status) && in_array(strtoupper($status), ['PENDING', 'SUCCESS', 'FAILED', 'PROCESSING'])) {
        $whereConditions[] = 't.status = ?';
        $params[] = strtoupper($status);
        $paramTypes .= 's';
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countSql = "
        SELECT COUNT(*) AS total
        FROM transactions t
        LEFT JOIN users u ON u.id = t.vendor_id
        {$whereClause}
    ";
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($paramTypes, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = (int)($countResult->fetch_assoc()['total'] ?? 0);
    $countStmt->close();
    
    // Get transactions
    $dataParams = $params;
    $dataTypes = $paramTypes . 'ii';
    $dataSql = "
        SELECT 
            t.id,
            t.merchant_reference_id,
            t.payninja_transaction_id,
            t.vendor_id,
            u.email AS vendor_email,
            t.beneficiary_id,
            t.ben_name,
            t.ben_phone_number,
            t.ben_vpa_address,
            t.ben_account_number,
            t.ben_ifsc,
            t.ben_bank_name,
            t.transfer_type,
            t.amount,
            t.narration,
            t.status,
            t.api_response,
            t.api_error,
            t.created_at,
            t.updated_at
        FROM transactions t
        LEFT JOIN users u ON u.id = t.vendor_id
        {$whereClause}
        ORDER BY t.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $dataStmt = $conn->prepare($dataSql);
    $dataParams[] = $limit;
    $dataParams[] = $offset;
    $dataStmt->bind_param($dataTypes, ...$dataParams);
    $dataStmt->execute();
    $dataResult = $dataStmt->get_result();
    
    $transactions = [];
    while ($row = $dataResult->fetch_assoc()) {
        // Extract error message from api_response or api_error
        $errorMessage = null;
        if (!empty($row['api_error'])) {
            $errorMessage = $row['api_error'];
        } elseif (!empty($row['api_response'])) {
            $apiResponse = json_decode($row['api_response'], true);
            if (is_array($apiResponse) && isset($apiResponse['message'])) {
                $errorMessage = $apiResponse['message'];
            }
        }
        
        // Return only important fields
        $transactions[] = [
            'id' => (int)$row['id'],
            'merchant_reference_id' => $row['merchant_reference_id'],
            'vendor_email' => $row['vendor_email'],
            'beneficiary_name' => $row['ben_name'],
            'amount' => (float)$row['amount'],
            'status' => $row['status'],
            'narration' => $row['narration'],
            'error_message' => $errorMessage,
            'created_at' => $row['created_at']
        ];
    }
    $dataStmt->close();
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => [
            'transactions' => $transactions,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $limit > 0 ? (int)ceil($total / $limit) : 0
            ]
        ]
    ]);
    exit();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    logError('Admin payout list error', ['error' => $e->getMessage()]);
}

