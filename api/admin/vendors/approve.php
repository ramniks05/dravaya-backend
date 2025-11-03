<?php
/**
 * Approve Vendor API
 * Changes vendor status from 'pending' to 'active'
 * POST: { "vendor_id": "uuid-here" }
 */

require_once __DIR__ . '/../../cors.php';
require_once '../../../config.php';
require_once '../../../database/functions.php';

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
    
    $vendorId = trim($data['vendor_id']);
    $conn = getDBConnection();
    
    // Check if vendor exists and get current status
    $checkStmt = $conn->prepare("
        SELECT id, email, role, status 
        FROM users 
        WHERE id = ? AND role = 'vendor'
        LIMIT 1
    ");
    $checkStmt->bind_param('s', $vendorId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $checkStmt->close();
        throw new Exception('Vendor not found');
    }
    
    $vendor = $result->fetch_assoc();
    $checkStmt->close();
    
    // Check if already active
    if ($vendor['status'] === 'active') {
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Vendor is already active',
            'data' => [
                'vendor' => [
                    'id' => $vendor['id'],
                    'email' => $vendor['email'],
                    'status' => $vendor['status']
                ]
            ]
        ]);
        exit();
    }
    
    // Update status to active
    $updateStmt = $conn->prepare("
        UPDATE users 
        SET status = 'active', updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $updateStmt->bind_param('s', $vendorId);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to approve vendor: ' . $updateStmt->error);
    }
    
    $updateStmt->close();
    
    // Log the action
    logError('Vendor approved', [
        'vendor_id' => $vendorId,
        'vendor_email' => $vendor['email'],
        'previous_status' => $vendor['status']
    ], false);
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Vendor approved successfully',
        'data' => [
            'vendor' => [
                'id' => $vendor['id'],
                'email' => $vendor['email'],
                'role' => $vendor['role'],
                'status' => 'active',
                'previous_status' => $vendor['status']
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    logError('Approve vendor API error', ['error' => $e->getMessage()]);
}

