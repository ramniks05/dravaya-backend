<?php
/**
 * Update Beneficiary API
 */

require_once __DIR__ . '/../../cors.php';
require_once '../../../config.php';

// Only allow POST or PUT requests
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'])) {
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
    
    // Verify beneficiary belongs to vendor
    $conn = getDBConnection();
    $checkStmt = $conn->prepare("SELECT * FROM beneficiaries WHERE id = ? AND vendor_id = ? LIMIT 1");
    $checkStmt->bind_param('is', $beneficiaryId, $vendorId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $checkStmt->close();
        throw new Exception('Beneficiary not found');
    }
    
    $existing = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    // Build update fields
    $updateFields = [];
    $params = [];
    $paramTypes = '';
    
    if (isset($data['name']) && !empty(trim($data['name']))) {
        $updateFields[] = "name = ?";
        $params[] = sanitizeInput(trim($data['name']));
        $paramTypes .= 's';
    }
    
    if (isset($data['phone_number']) && !empty(trim($data['phone_number']))) {
        $phoneNumber = sanitizeInput(trim($data['phone_number']));
        if (!validatePhoneNumber($phoneNumber)) {
            throw new Exception('Invalid phone number format');
        }
        
        // Check if phone number already exists for another beneficiary
        $phoneCheckStmt = $conn->prepare("
            SELECT id FROM beneficiaries 
            WHERE vendor_id = ? AND phone_number = ? AND id != ?
            LIMIT 1
        ");
        $phoneCheckStmt->bind_param('ssi', $vendorId, $phoneNumber, $beneficiaryId);
        $phoneCheckStmt->execute();
        if ($phoneCheckStmt->get_result()->num_rows > 0) {
            $phoneCheckStmt->close();
            throw new Exception('Phone number already exists for another beneficiary');
        }
        $phoneCheckStmt->close();
        
        $updateFields[] = "phone_number = ?";
        $params[] = $phoneNumber;
        $paramTypes .= 's';
    }
    
    // Update transfer type specific fields
    $transferType = $data['transfer_type'] ?? $existing['transfer_type'];
    
    if (isset($data['transfer_type']) && in_array($data['transfer_type'], ['UPI', 'IMPS', 'NEFT'])) {
        $transferType = $data['transfer_type'];
        $updateFields[] = "transfer_type = ?";
        $params[] = $transferType;
        $paramTypes .= 's';
        
        // Clear opposite transfer type fields
        if ($transferType === 'UPI') {
            $updateFields[] = "vpa_address = ?";
            $updateFields[] = "account_number = NULL";
            $updateFields[] = "ifsc = NULL";
            $updateFields[] = "bank_name = NULL";
            $params[] = isset($data['vpa_address']) ? sanitizeInput(trim($data['vpa_address'])) : null;
            $paramTypes .= 's';
        } else {
            $updateFields[] = "vpa_address = NULL";
            if (isset($data['account_number'])) {
                $updateFields[] = "account_number = ?";
                $params[] = sanitizeInput(trim($data['account_number']));
                $paramTypes .= 's';
            }
            if (isset($data['ifsc'])) {
                $updateFields[] = "ifsc = ?";
                $params[] = strtoupper(sanitizeInput(trim($data['ifsc'])));
                $paramTypes .= 's';
            }
            if (isset($data['bank_name'])) {
                $updateFields[] = "bank_name = ?";
                $params[] = sanitizeInput(trim($data['bank_name']));
                $paramTypes .= 's';
            }
        }
    } else {
        // Update fields for existing transfer type
        if ($transferType === 'UPI' && isset($data['vpa_address'])) {
            $updateFields[] = "vpa_address = ?";
            $params[] = sanitizeInput(trim($data['vpa_address']));
            $paramTypes .= 's';
        } else if (in_array($transferType, ['IMPS', 'NEFT'])) {
            if (isset($data['account_number'])) {
                $updateFields[] = "account_number = ?";
                $params[] = sanitizeInput(trim($data['account_number']));
                $paramTypes .= 's';
            }
            if (isset($data['ifsc'])) {
                $updateFields[] = "ifsc = ?";
                $params[] = strtoupper(sanitizeInput(trim($data['ifsc'])));
                $paramTypes .= 's';
            }
            if (isset($data['bank_name'])) {
                $updateFields[] = "bank_name = ?";
                $params[] = sanitizeInput(trim($data['bank_name']));
                $paramTypes .= 's';
            }
        }
    }
    
    if (isset($data['is_active'])) {
        $updateFields[] = "is_active = ?";
        $params[] = $data['is_active'] ? 1 : 0;
        $paramTypes .= 'i';
    }
    
    if (empty($updateFields)) {
        throw new Exception('No fields to update');
    }
    
    $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
    $params[] = $beneficiaryId;
    $params[] = $vendorId;
    $paramTypes .= 'is';
    
    $query = "UPDATE beneficiaries SET " . implode(', ', $updateFields) . " WHERE id = ? AND vendor_id = ?";
    $updateStmt = $conn->prepare($query);
    $updateStmt->bind_param($paramTypes, ...$params);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update beneficiary: ' . $updateStmt->error);
    }
    $updateStmt->close();
    
    // Get updated beneficiary
    $getStmt = $conn->prepare("SELECT * FROM beneficiaries WHERE id = ? AND vendor_id = ? LIMIT 1");
    $getStmt->bind_param('is', $beneficiaryId, $vendorId);
    $getStmt->execute();
    $beneficiary = $getStmt->get_result()->fetch_assoc();
    $getStmt->close();
    
    logError('Beneficiary updated', [
        'beneficiary_id' => $beneficiaryId,
        'vendor_id' => $vendorId
    ], false);
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Beneficiary updated successfully',
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
    logError('Update beneficiary error', ['error' => $e->getMessage()]);
}

