<?php
/**
 * Get Pending Vendors API
 * Returns all vendors with 'pending' status
 * This is a convenience endpoint for the admin approval page
 */

require_once __DIR__ . '/../../cors.php';
require_once '../../../config.php';
require_once '../../../database/functions.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

try {
    $conn = getDBConnection();
    
    // Get pending vendors only
    $query = "
        SELECT id, email, role, status, created_at, updated_at 
        FROM users 
        WHERE role = 'vendor' AND status = 'pending'
        ORDER BY created_at ASC
    ";
    
    $stmt = $conn->query($query);
    $result = $stmt->get_result();
    
    $vendors = [];
    while ($row = $result->fetch_assoc()) {
        $vendors[] = [
            'id' => $row['id'],
            'email' => $row['email'],
            'role' => $row['role'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'pending_since' => $row['created_at'] // Time since signup
        ];
    }
    $stmt->close();
    
    // Return response
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => [
            'vendors' => $vendors,
            'count' => count($vendors)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    logError('Pending vendors API error', ['error' => $e->getMessage()]);
}

