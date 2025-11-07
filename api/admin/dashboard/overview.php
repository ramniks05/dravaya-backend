<?php
/**
 * Admin Dashboard Overview API
 * Provides consolidated metrics for admin dashboard widgets
 */

require_once __DIR__ . '/../../cors.php';
require_once '../../../config.php';
require_once '../../../database/functions.php';
require_once '../../../database/wallet_functions.php';

header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

try {
    $conn = getDBConnection();

    // 1. PayNinja balance
    $payninjaBalance = null;
    $payninjaBalanceError = null;

    try {
        $ch = curl_init(API_BASE_URL . '/api/v1/account/balance');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'api-Key: ' . API_KEY
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception('Network error: ' . $curlError);
        }

        $balanceResult = json_decode($response, true);
        if ($httpCode !== 200) {
            $errorMsg = $balanceResult['message'] ?? 'Failed to fetch PayNinja balance';
            throw new Exception($errorMsg);
        }

        $payninjaBalance = [
            'balance' => floatval($balanceResult['data']['balance'] ?? $balanceResult['balance'] ?? 0),
            'currency' => $balanceResult['data']['currency'] ?? $balanceResult['currency'] ?? 'INR',
            'raw' => $balanceResult
        ];

    } catch (Exception $balanceException) {
        $payninjaBalanceError = $balanceException->getMessage();
        logError('Dashboard PayNinja balance fetch failed', [
            'error' => $payninjaBalanceError
        ]);
    }

    // 2. Vendor wallet balances
    $vendorWallets = [];
    $vendorWalletsQuery = "
        SELECT 
            vw.vendor_id,
            vw.balance,
            vw.currency,
            vw.updated_at,
            u.email,
            u.status
        FROM vendor_wallets vw
        LEFT JOIN users u ON u.id = vw.vendor_id
        ORDER BY vw.balance DESC
    ";

    if ($walletResult = $conn->query($vendorWalletsQuery)) {
        while ($row = $walletResult->fetch_assoc()) {
            $vendorWallets[] = [
                'vendor_id' => $row['vendor_id'],
                'email' => $row['email'],
                'status' => $row['status'],
                'balance' => floatval($row['balance']),
                'currency' => $row['currency'],
                'updated_at' => $row['updated_at']
            ];
        }
        $walletResult->free();
    }

    // 3. Vendor status counts
    $vendorStatusCounts = [
        'pending' => 0,
        'active' => 0,
        'suspended' => 0,
        'total' => 0
    ];

    $vendorStatusQuery = "
        SELECT status, COUNT(*) AS count
        FROM users
        WHERE role = 'vendor'
        GROUP BY status
    ";

    if ($statusResult = $conn->query($vendorStatusQuery)) {
        while ($row = $statusResult->fetch_assoc()) {
            $status = $row['status'];
            $count = intval($row['count']);
            $vendorStatusCounts[$status] = $count;
            $vendorStatusCounts['total'] += $count;
        }
        $statusResult->free();
    }

    // 4. Recent transactions (last 20)
    $recentTransactions = [];
    $recentTransactionsQuery = "
        SELECT 
            t.merchant_reference_id,
            t.payninja_transaction_id,
            t.vendor_id,
            u.email AS vendor_email,
            t.amount,
            t.status,
            t.transfer_type,
            t.created_at,
            t.updated_at
        FROM transactions t
        LEFT JOIN users u ON u.id = t.vendor_id
        ORDER BY t.created_at DESC
        LIMIT 20
    ";

    if ($recentResult = $conn->query($recentTransactionsQuery)) {
        while ($row = $recentResult->fetch_assoc()) {
            $recentTransactions[] = [
                'merchant_reference_id' => $row['merchant_reference_id'],
                'payninja_transaction_id' => $row['payninja_transaction_id'],
                'vendor_id' => $row['vendor_id'],
                'vendor_email' => $row['vendor_email'],
                'amount' => floatval($row['amount']),
                'status' => $row['status'],
                'transfer_type' => $row['transfer_type'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
        $recentResult->free();
    }

    // 5. Top 10 vendors by total successful payout amount
    $topVendors = [];
    $topVendorsQuery = "
        SELECT 
            t.vendor_id,
            u.email,
            COUNT(*) AS transaction_count,
            SUM(t.amount) AS total_amount
        FROM transactions t
        INNER JOIN users u ON u.id = t.vendor_id
        WHERE t.status = 'SUCCESS'
        GROUP BY t.vendor_id, u.email
        ORDER BY total_amount DESC
        LIMIT 10
    ";

    if ($topVendorsResult = $conn->query($topVendorsQuery)) {
        while ($row = $topVendorsResult->fetch_assoc()) {
            $topVendors[] = [
                'vendor_id' => $row['vendor_id'],
                'email' => $row['email'],
                'transaction_count' => intval($row['transaction_count']),
                'total_amount' => floatval($row['total_amount'])
            ];
        }
        $topVendorsResult->free();
    }

    // 6. Transaction statistics (overall and today)
    $overallStats = [
        'SUCCESS' => ['count' => 0, 'amount' => 0],
        'PENDING' => ['count' => 0, 'amount' => 0],
        'FAILED' => ['count' => 0, 'amount' => 0],
        'PROCESSING' => ['count' => 0, 'amount' => 0]
    ];

    $overallStatsQuery = "
        SELECT status, COUNT(*) AS count, SUM(amount) AS total_amount
        FROM transactions
        GROUP BY status
    ";

    if ($overallResult = $conn->query($overallStatsQuery)) {
        while ($row = $overallResult->fetch_assoc()) {
            $status = strtoupper($row['status']);
            if (isset($overallStats[$status])) {
                $overallStats[$status]['count'] = intval($row['count']);
                $overallStats[$status]['amount'] = floatval($row['total_amount']);
            }
        }
        $overallResult->free();
    }

    $todayStats = [
        'SUCCESS' => ['count' => 0, 'amount' => 0],
        'PENDING' => ['count' => 0, 'amount' => 0],
        'FAILED' => ['count' => 0, 'amount' => 0],
        'PROCESSING' => ['count' => 0, 'amount' => 0]
    ];

    $todayStatsQuery = "
        SELECT status, COUNT(*) AS count, SUM(amount) AS total_amount
        FROM transactions
        WHERE DATE(created_at) = CURDATE()
        GROUP BY status
    ";

    if ($todayResult = $conn->query($todayStatsQuery)) {
        while ($row = $todayResult->fetch_assoc()) {
            $status = strtoupper($row['status']);
            if (isset($todayStats[$status])) {
                $todayStats[$status]['count'] = intval($row['count']);
                $todayStats[$status]['amount'] = floatval($row['total_amount']);
            }
        }
        $todayResult->free();
    }

    // Derived totals for success
    $totalSuccessAmount = $overallStats['SUCCESS']['amount'];
    $totalSuccessCount = $overallStats['SUCCESS']['count'];

    $responseData = [
        'payninja_balance' => [
            'data' => $payninjaBalance,
            'error' => $payninjaBalanceError
        ],
        'vendor_wallets' => $vendorWallets,
        'vendor_status_counts' => $vendorStatusCounts,
        'recent_transactions' => $recentTransactions,
        'top_vendors' => $topVendors,
        'transaction_stats' => [
            'overall' => $overallStats,
            'today' => $todayStats,
            'totals' => [
                'success_amount' => $totalSuccessAmount,
                'success_count' => $totalSuccessCount
            ]
        ]
    ];

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => $responseData
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    logError('Dashboard overview API error', ['error' => $e->getMessage()]);
}


