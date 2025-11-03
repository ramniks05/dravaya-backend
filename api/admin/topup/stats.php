<?php
/**
 * Get Topup Request Statistics API
 * Returns counts and statistics of topup requests
 */

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
    
    // Get counts by status
    $query = "
        SELECT 
            status,
            COUNT(*) as count,
            SUM(amount) as total_amount
        FROM topup_requests
        GROUP BY status
    ";
    
    $stmt = $conn->query($query);
    $result = $stmt->get_result();
    
    $stats = [
        'pending' => ['count' => 0, 'total_amount' => 0],
        'approved' => ['count' => 0, 'total_amount' => 0],
        'rejected' => ['count' => 0, 'total_amount' => 0],
        'total' => ['count' => 0, 'total_amount' => 0]
    ];
    
    while ($row = $result->fetch_assoc()) {
        $status = $row['status'];
        if (isset($stats[$status])) {
            $stats[$status]['count'] = intval($row['count']);
            $stats[$status]['total_amount'] = floatval($row['total_amount']);
        }
        $stats['total']['count'] += intval($row['count']);
        $stats['total']['total_amount'] += floatval($row['total_amount']);
    }
    $stmt->close();
    
    // Get pending amount (for approval queue)
    $pendingStmt = $conn->query("SELECT SUM(amount) as pending_amount FROM topup_requests WHERE status = 'pending'");
    $pendingResult = $pendingStmt->fetch_assoc();
    $pendingAmount = floatval($pendingResult['pending_amount'] ?? 0);
    $pendingStmt->close();
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => [
            'statistics' => [
                'pending' => [
                    'count' => $stats['pending']['count'],
                    'total_amount' => $stats['pending']['total_amount']
                ],
                'approved' => [
                    'count' => $stats['approved']['count'],
                    'total_amount' => $stats['approved']['total_amount']
                ],
                'rejected' => [
                    'count' => $stats['rejected']['count'],
                    'total_amount' => $stats['rejected']['total_amount']
                ],
                'total' => [
                    'count' => $stats['total']['count'],
                    'total_amount' => $stats['total']['total_amount']
                ]
            ],
            'summary' => [
                'pending_requests' => $stats['pending']['count'],
                'pending_amount' => $pendingAmount,
                'approved_requests' => $stats['approved']['count'],
                'approved_amount' => $stats['approved']['total_amount'],
                'rejected_requests' => $stats['rejected']['count']
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    logError('Topup stats error', ['error' => $e->getMessage()]);
}

