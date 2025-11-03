<?php
/**
 * Admin Vendor Management API
 * GET: List all vendors (with optional status filter)
 * POST: Update vendor status (approve/suspend/activate)
 */

require_once __DIR__ . '/../cors.php';
require_once '../../config.php';
require_once '../../database/functions.php';

// Only allow GET and POST requests
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

try {
    $conn = getDBConnection();
    
    // GET: Fetch vendors
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get query parameters
        $status = $_GET['status'] ?? null; // 'pending', 'active', 'suspended', or null for all
        $role = $_GET['role'] ?? 'vendor'; // Filter by role (default: vendor)
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = max(1, min(100, intval($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;
        
        // Build query
        $whereConditions = ["role = ?"];
        $params = [$role];
        $paramTypes = 's';
        
        if ($status && in_array($status, ['pending', 'active', 'suspended'])) {
            $whereConditions[] = "status = ?";
            $params[] = $status;
            $paramTypes .= 's';
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM users WHERE {$whereClause}";
        $countStmt = $conn->prepare($countQuery);
        if (count($params) > 0) {
            $countStmt->bind_param($paramTypes, ...$params);
        }
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $total = $countResult->fetch_assoc()['total'];
        $countStmt->close();
        
        // Get vendors with pagination
        $query = "
            SELECT id, email, role, status, created_at, updated_at 
            FROM users 
            WHERE {$whereClause}
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        $paramTypes .= 'ii';
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $vendors = [];
        while ($row = $result->fetch_assoc()) {
            $vendors[] = [
                'id' => $row['id'],
                'email' => $row['email'],
                'role' => $row['role'],
                'status' => $row['status'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
        $stmt->close();
        
        // Return response
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'data' => [
                'vendors' => $vendors,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => intval($total),
                    'total_pages' => ceil($total / $limit)
                ],
                'filters' => [
                    'status' => $status,
                    'role' => $role
                ]
            ]
        ]);
        
    // POST: Update vendor status
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        
        if (!$data) {
            throw new Exception('Invalid JSON request data');
        }
        
        // Validate required fields
        if (!isset($data['vendor_id']) || empty($data['vendor_id'])) {
            throw new Exception('vendor_id is required');
        }
        
        if (!isset($data['action']) || !in_array($data['action'], ['approve', 'suspend', 'activate'])) {
            throw new Exception('action must be one of: approve, suspend, activate');
        }
        
        $vendorId = trim($data['vendor_id']);
        $action = $data['action'];
        
        // Map actions to status
        $statusMap = [
            'approve' => 'active',
            'activate' => 'active',
            'suspend' => 'suspended'
        ];
        
        $newStatus = $statusMap[$action];
        
        // Check if vendor exists and is a vendor (not admin)
        $checkStmt = $conn->prepare("SELECT id, email, role, status FROM users WHERE id = ? LIMIT 1");
        $checkStmt->bind_param('s', $vendorId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            $checkStmt->close();
            throw new Exception('Vendor not found');
        }
        
        $vendor = $checkResult->fetch_assoc();
        $checkStmt->close();
        
        // Prevent modifying admin accounts
        if ($vendor['role'] === 'admin') {
            throw new Exception('Cannot modify admin accounts');
        }
        
        // Update status
        $updateStmt = $conn->prepare("UPDATE users SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $updateStmt->bind_param('ss', $newStatus, $vendorId);
        
        if (!$updateStmt->execute()) {
            throw new Exception('Failed to update vendor status: ' . $updateStmt->error);
        }
        
        $updateStmt->close();
        
        // Log the action
        logError('Vendor status updated', [
            'vendor_id' => $vendorId,
            'vendor_email' => $vendor['email'],
            'old_status' => $vendor['status'],
            'new_status' => $newStatus,
            'action' => $action
        ], false);
        
        // Return success response
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => "Vendor {$action}d successfully",
            'data' => [
                'vendor' => [
                    'id' => $vendor['id'],
                    'email' => $vendor['email'],
                    'role' => $vendor['role'],
                    'status' => $newStatus,
                    'previous_status' => $vendor['status']
                ]
            ]
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    logError('Vendor management API error', ['error' => $e->getMessage()]);
}

