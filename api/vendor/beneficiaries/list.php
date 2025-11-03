<?php
/**
 * List Beneficiaries API
 * Get list of all beneficiaries for a vendor
 */

require_once __DIR__ . '/../../cors.php';
require_once '../../../config.php';

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
    
    $transferType = $_GET['transfer_type'] ?? null; // Filter by transfer type
    $isActive = $_GET['is_active'] ?? null; // Filter by active status
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(100, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    
    // Verify vendor exists
    $conn = getDBConnection();
    $vendorStmt = $conn->prepare("SELECT id, email FROM users WHERE id = ? AND role = 'vendor' LIMIT 1");
    $vendorStmt->bind_param('s', $vendorId);
    $vendorStmt->execute();
    $vendorResult = $vendorStmt->get_result();
    
    if ($vendorResult->num_rows === 0) {
        $vendorStmt->close();
        throw new Exception('Vendor not found');
    }
    $vendorStmt->close();
    
    // Build query
    $whereConditions = ["vendor_id = ?"];
    $params = [$vendorId];
    $paramTypes = 's';
    
    if ($transferType && in_array($transferType, ['UPI', 'IMPS', 'NEFT'])) {
        $whereConditions[] = "transfer_type = ?";
        $params[] = $transferType;
        $paramTypes .= 's';
    }
    
    if ($isActive !== null) {
        $whereConditions[] = "is_active = ?";
        $params[] = $isActive ? 1 : 0;
        $paramTypes .= 'i';
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM beneficiaries WHERE {$whereClause}";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param($paramTypes, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Get beneficiaries
    $params[] = $limit;
    $params[] = $offset;
    $paramTypes .= 'ii';
    
    $query = "
        SELECT * FROM beneficiaries 
        WHERE {$whereClause}
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $beneficiaries = [];
    while ($row = $result->fetch_assoc()) {
        $beneficiaries[] = [
            'id' => $row['id'],
            'vendor_id' => $row['vendor_id'],
            'name' => $row['name'],
            'phone_number' => $row['phone_number'],
            'vpa_address' => $row['vpa_address'],
            'account_number' => $row['account_number'],
            'ifsc' => $row['ifsc'],
            'bank_name' => $row['bank_name'],
            'transfer_type' => $row['transfer_type'],
            'is_active' => (bool)$row['is_active'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    $stmt->close();
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => [
            'vendor_id' => $vendorId,
            'beneficiaries' => $beneficiaries,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => intval($total),
                'total_pages' => ceil($total / $limit)
            ],
            'filters' => [
                'transfer_type' => $transferType,
                'is_active' => $isActive
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    logError('List beneficiaries error', ['error' => $e->getMessage()]);
}

