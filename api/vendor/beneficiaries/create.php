<?php
/**
 * Create Beneficiary API
 * Vendor creates a new beneficiary for faster transfers
 */

require_once __DIR__ . '/../../cors.php';
require_once '../../../config.php';

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
    if (!isset($data['vendor_id']) || empty($data['vendor_id'])) {
        throw new Exception('vendor_id is required');
    }
    
    if (!isset($data['name']) || empty(trim($data['name']))) {
        throw new Exception('name is required');
    }
    
    if (!isset($data['phone_number']) || empty(trim($data['phone_number']))) {
        throw new Exception('phone_number is required');
    }
    
    if (!isset($data['transfer_type']) || !in_array($data['transfer_type'], ['UPI', 'IMPS', 'NEFT'])) {
        throw new Exception('transfer_type must be UPI, IMPS, or NEFT');
    }
    
    $vendorId = trim($data['vendor_id']);
    $name = sanitizeInput(trim($data['name']));
    $phoneNumber = sanitizeInput(trim($data['phone_number']));
    $transferType = $data['transfer_type'];
    
    // Validate phone number
    if (!validatePhoneNumber($phoneNumber)) {
        throw new Exception('Invalid phone number format');
    }
    
    // Validate transfer type specific fields
    if ($transferType === 'UPI') {
        if (!isset($data['vpa_address']) || empty(trim($data['vpa_address']))) {
            throw new Exception('vpa_address is required for UPI transfers');
        }
        $vpaAddress = sanitizeInput(trim($data['vpa_address']));
        $accountNumber = null;
        $ifsc = null;
        $bankName = null;
    } else {
        // IMPS/NEFT requires account details
        if (!isset($data['account_number']) || empty(trim($data['account_number']))) {
            throw new Exception('account_number is required for IMPS/NEFT transfers');
        }
        if (!isset($data['ifsc']) || empty(trim($data['ifsc']))) {
            throw new Exception('ifsc is required for IMPS/NEFT transfers');
        }
        if (!isset($data['bank_name']) || empty(trim($data['bank_name']))) {
            throw new Exception('bank_name is required for IMPS/NEFT transfers');
        }
        $vpaAddress = null;
        $accountNumber = sanitizeInput(trim($data['account_number']));
        $ifsc = strtoupper(sanitizeInput(trim($data['ifsc'])));
        $bankName = sanitizeInput(trim($data['bank_name']));
    }
    
    $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : true;
    
    // Verify vendor exists and is active
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, email, status FROM users WHERE id = ? AND role = 'vendor' LIMIT 1");
    $stmt->bind_param('s', $vendorId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('Vendor not found');
    }
    
    $vendor = $result->fetch_assoc();
    $stmt->close();
    
    if ($vendor['status'] !== 'active') {
        throw new Exception('Vendor account is not active');
    }
    
    // Check if beneficiary already exists (same phone + transfer type + vendor)
    $checkStmt = $conn->prepare("
        SELECT id FROM beneficiaries 
        WHERE vendor_id = ? AND phone_number = ? AND transfer_type = ?
        LIMIT 1
    ");
    $checkStmt->bind_param('sss', $vendorId, $phoneNumber, $transferType);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $checkStmt->close();
        throw new Exception('Beneficiary with this phone number and transfer type already exists');
    }
    $checkStmt->close();
    
    // Insert beneficiary
    $insertStmt = $conn->prepare("
        INSERT INTO beneficiaries 
        (vendor_id, name, phone_number, vpa_address, account_number, ifsc, bank_name, transfer_type, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insertStmt->bind_param('ssssssssi', 
        $vendorId, 
        $name, 
        $phoneNumber, 
        $vpaAddress, 
        $accountNumber, 
        $ifsc, 
        $bankName, 
        $transferType, 
        $isActive
    );
    
    if (!$insertStmt->execute()) {
        throw new Exception('Failed to create beneficiary: ' . $insertStmt->error);
    }
    
    $beneficiaryId = $conn->insert_id;
    $insertStmt->close();
    
    // Get created beneficiary
    $getStmt = $conn->prepare("SELECT * FROM beneficiaries WHERE id = ? LIMIT 1");
    $getStmt->bind_param('i', $beneficiaryId);
    $getStmt->execute();
    $beneficiary = $getStmt->get_result()->fetch_assoc();
    $getStmt->close();
    
    logError('Beneficiary created', [
        'beneficiary_id' => $beneficiaryId,
        'vendor_id' => $vendorId,
        'transfer_type' => $transferType
    ], false);
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Beneficiary created successfully',
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
    logError('Create beneficiary error', ['error' => $e->getMessage()]);
}

