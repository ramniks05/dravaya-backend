<?php
/**
 * Vendor Statistics API
 * Returns counts of vendors by status
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
    
    // Get counts by status
    $query = "
        SELECT 
            status,
            COUNT(*) as count
        FROM users
        WHERE role = 'vendor'
        GROUP BY status
    ";
    
    $stmt = $conn->query($query);
    $result = $stmt->get_result();
    
    $stats = [
        'pending' => 0,
        'active' => 0,
        'suspended' => 0,
        'total' => 0
    ];
    
    while ($row = $result->fetch_assoc()) {
        $status = $row['status'];
        $count = intval($row['count']);
        $stats[$status] = $count;
        $stats['total'] += $count;
    }
    $stmt->close();
    
    // Return response
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => [
            'statistics' => $stats,
            'summary' => [
                'pending_approvals' => $stats['pending'],
                'active_vendors' => $stats['active'],
                'suspended_vendors' => $stats['suspended']
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    logError('Vendor stats API error', ['error' => $e->getMessage()]);
}

