<?php
/**
 * Get Topup Requests List API
 * Admin endpoint to list all topup requests with filters
 */

// Disable error display to prevent HTML in JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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
    $conn = getDBConnection();
    
    // Get query parameters
    $status = $_GET['status'] ?? null; // 'pending', 'approved', 'rejected', or null for all
    $vendorId = $_GET['vendor_id'] ?? null;
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(100, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    
    // Build query
    $whereConditions = [];
    $params = [];
    $paramTypes = '';
    
    if ($status && in_array($status, ['pending', 'approved', 'rejected'])) {
        $whereConditions[] = "tr.status = ?";
        $params[] = $status;
        $paramTypes .= 's';
    }
    
    if ($vendorId) {
        $whereConditions[] = "tr.vendor_id = ?";
        $params[] = $vendorId;
        $paramTypes .= 's';
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM topup_requests tr {$whereClause}";
    $countStmt = $conn->prepare($countQuery);
    if (!empty($params)) {
        $countStmt->bind_param($paramTypes, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Get requests with vendor info
    $params[] = $limit;
    $params[] = $offset;
    $paramTypes .= 'ii';
    
    $query = "
        SELECT tr.*, u.email as vendor_email, u.status as vendor_status,
               a.email as admin_email
        FROM topup_requests tr
        LEFT JOIN users u ON tr.vendor_id = u.id
        LEFT JOIN users a ON tr.admin_id = a.id
        {$whereClause}
        ORDER BY tr.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = [
            'id' => $row['id'],
            'request_id' => $row['request_id'],
            'vendor_id' => $row['vendor_id'],
            'vendor_email' => $row['vendor_email'],
            'vendor_status' => $row['vendor_status'],
            'amount' => floatval($row['amount']),
            'currency' => $row['currency'],
            'status' => $row['status'],
            'admin_id' => $row['admin_id'],
            'admin_email' => $row['admin_email'],
            'admin_notes' => $row['admin_notes'],
            'rejection_reason' => $row['rejection_reason'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'processed_at' => $row['processed_at']
        ];
    }
    $stmt->close();
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => [
            'requests' => $requests,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => intval($total),
                'total_pages' => ceil($total / $limit)
            ],
            'filters' => [
                'status' => $status,
                'vendor_id' => $vendorId
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    logError('List topup requests error', ['error' => $e->getMessage()]);
}

