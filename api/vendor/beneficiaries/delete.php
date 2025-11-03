<?php
/**
 * Delete Beneficiary API
 */

require_once __DIR__ . '/../../cors.php';
require_once '../../../config.php';

// Only allow POST or DELETE requests
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'])) {
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
    
    if (!isset($data['id']) || empty($data['id'])) {
        throw new Exception('id is required');
    }
    
    if (!isset($data['vendor_id']) || empty($data['vendor_id'])) {
        throw new Exception('vendor_id is required');
    }
    
    $beneficiaryId = intval($data['id']);
    $vendorId = trim($data['vendor_id']);
    
    $conn = getDBConnection();
    
    // Verify beneficiary belongs to vendor
    $checkStmt = $conn->prepare("SELECT id FROM beneficiaries WHERE id = ? AND vendor_id = ? LIMIT 1");
    $checkStmt->bind_param('is', $beneficiaryId, $vendorId);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows === 0) {
        $checkStmt->close();
        throw new Exception('Beneficiary not found');
    }
    $checkStmt->close();
    
    // Delete beneficiary
    $deleteStmt = $conn->prepare("DELETE FROM beneficiaries WHERE id = ? AND vendor_id = ?");
    $deleteStmt->bind_param('is', $beneficiaryId, $vendorId);
    
    if (!$deleteStmt->execute()) {
        throw new Exception('Failed to delete beneficiary: ' . $deleteStmt->error);
    }
    $deleteStmt->close();
    
    logError('Beneficiary deleted', [
        'beneficiary_id' => $beneficiaryId,
        'vendor_id' => $vendorId
    ], false);
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Beneficiary deleted successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    logError('Delete beneficiary error', ['error' => $e->getMessage()]);
}

