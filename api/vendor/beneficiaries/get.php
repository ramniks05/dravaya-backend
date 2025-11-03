<?php
/**
 * Get Beneficiary by ID API
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
    $beneficiaryId = $_GET['id'] ?? null;
    $vendorId = $_GET['vendor_id'] ?? null;
    
    if (!$beneficiaryId) {
        throw new Exception('id is required');
    }
    
    if (!$vendorId) {
        throw new Exception('vendor_id is required');
    }
    
    $conn = getDBConnection();
    
    // Get beneficiary and verify it belongs to vendor
    $stmt = $conn->prepare("
        SELECT * FROM beneficiaries 
        WHERE id = ? AND vendor_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('is', $beneficiaryId, $vendorId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('Beneficiary not found');
    }
    
    $beneficiary = $result->fetch_assoc();
    $stmt->close();
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => [
            'beneficiary' => [
                'id' => $beneficiary['id'],
                'vendor_id' => $beneficiary['vendor_id'],
                'name' => $beneficiary['name'],
                'phone_number' => $beneficiary['phone_number'],
                'vpa_address' => $beneficiary['vpa_address'],
                'account_number' => $beneficiary['account_number'],
                'ifsc' => $beneficiary['ifsc'],
                'bank_name' => $beneficiary['bank_name'],
                'transfer_type' => $beneficiary['transfer_type'],
                'is_active' => (bool)$beneficiary['is_active'],
                'created_at' => $beneficiary['created_at'],
                'updated_at' => $beneficiary['updated_at']
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    logError('Get beneficiary error', ['error' => $e->getMessage()]);
}

