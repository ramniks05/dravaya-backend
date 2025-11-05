<?php
/**
 * Get Vendor Payout Transactions API
 * Returns all payout transactions for a logged-in vendor
 */

// Turn off error display to prevent HTML output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Start output buffering to catch any accidental output
if (ob_get_level() == 0) {
    ob_start();
}

require_once __DIR__ . '/../../cors.php';
require_once '../../../config.php';
require_once '../../../database/functions.php';

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
    // Get vendor_id from request
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        
        if (!$data || !is_array($data)) {
            throw new Exception('Invalid JSON request data');
        }
        
        $vendorId = isset($data['vendor_id']) ? trim($data['vendor_id']) : null;
    } else {
        // GET request
        $vendorId = isset($_GET['vendor_id']) ? trim($_GET['vendor_id']) : null;
    }
    
    if (empty($vendorId)) {
        throw new Exception('vendor_id is required');
    }
    
    // Pagination parameters
    $page = max(1, intval($_GET['page'] ?? $data['page'] ?? 1));
    $limit = max(1, min(100, intval($_GET['limit'] ?? $data['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    
    // Filter parameters
    $status = isset($_GET['status']) ? trim($_GET['status']) : (isset($data['status']) ? trim($data['status']) : null);
    $transferType = isset($_GET['transfer_type']) ? trim($_GET['transfer_type']) : (isset($data['transfer_type']) ? trim($data['transfer_type']) : null);
    $startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : (isset($data['start_date']) ? trim($data['start_date']) : null);
    $endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : (isset($data['end_date']) ? trim($data['end_date']) : null);
    
    // Verify vendor exists
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
    
    // Build query to get transactions
    $query = "SELECT * FROM transactions WHERE vendor_id = ?";
    $countParams = [$vendorId];
    $countTypes = 's';
    
    // Add status filter
    if ($status && in_array(strtoupper($status), ['PENDING', 'SUCCESS', 'FAILED', 'PROCESSING'])) {
        $query .= " AND status = ?";
        $countParams[] = strtoupper($status);
        $countTypes .= 's';
    }
    
    // Add transfer type filter
    if ($transferType && in_array(strtoupper($transferType), ['UPI', 'IMPS', 'NEFT'])) {
        $query .= " AND transfer_type = ?";
        $countParams[] = strtoupper($transferType);
        $countTypes .= 's';
    }
    
    // Add date range filter
    if ($startDate) {
        $query .= " AND created_at >= ?";
        $countParams[] = $startDate;
        $countTypes .= 's';
    }
    
    if ($endDate) {
        $query .= " AND created_at <= ?";
        $countParams[] = $endDate;
        $countTypes .= 's';
    }
    
    // Get total count (use same params without limit/offset)
    $countQuery = str_replace('SELECT *', 'SELECT COUNT(*) as total', $query);
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param($countTypes, ...$countParams);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Add ordering and pagination for main query
    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params = array_merge($countParams, [$limit, $offset]);
    $types = $countTypes . 'ii';
    
    // Get transactions
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        // Parse API response if available
        $apiResponseData = null;
        if (!empty($row['api_response'])) {
            $apiResponseData = json_decode($row['api_response'], true);
            if ($apiResponseData === null) {
                $apiResponseData = $row['api_response'];
            }
        }
        
        $transactions[] = [
            'id' => intval($row['id']),
            'merchant_reference_id' => $row['merchant_reference_id'],
            'payninja_transaction_id' => $row['payninja_transaction_id'] ?? null,
            'utr' => $row['utr'] ?? null,
            'beneficiary' => [
                'name' => $row['ben_name'],
                'phone_number' => $row['ben_phone_number'],
                'vpa_address' => $row['ben_vpa_address'] ?? null,
                'account_number' => $row['ben_account_number'] ?? null,
                'ifsc' => $row['ben_ifsc'] ?? null,
                'bank_name' => $row['ben_bank_name'] ?? null
            ],
            'transaction' => [
                'transfer_type' => $row['transfer_type'],
                'amount' => floatval($row['amount']),
                'narration' => $row['narration'] ?? null,
                'status' => $row['status'],
                'payment_mode' => $row['payment_mode'] ?? null
            ],
            'beneficiary_id' => $row['beneficiary_id'] ?? null,
            'api_error' => $row['api_error'] ?? null,
            'timestamps' => [
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ]
        ];
    }
    $stmt->close();
    
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
        'message' => 'Transactions retrieved successfully',
        'data' => [
            'vendor_id' => $vendorId,
            'vendor_email' => $vendor['email'],
            'transactions' => $transactions,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => intval($total),
                'total_pages' => ceil($total / $limit)
            ],
            'filters' => [
                'status' => $status,
                'transfer_type' => $transferType,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]
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

