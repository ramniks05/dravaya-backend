<?php
/**
 * Admin - List Wallet Transactions
 * Lists all wallet transactions with optional filters (vendor_id, vendor_email)
 */

// Disable error display to prevent HTML in JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../../cors.php';
require_once '../../../config.php';

	// Allow GET or POST (JSON body)
	$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
	if ($method !== 'GET' && $method !== 'POST') {
	http_response_code(405);
	echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
	exit();
}

try {
	$conn = getDBConnection();

    // Query params (support GET query or POST JSON body)
	$payload = [];
	if ($method === 'POST') {
		$raw = file_get_contents('php://input');
		if ($raw) {
			$decoded = json_decode($raw, true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
				$payload = $decoded;
			}
		}
	}

	$get = $_GET ?? [];
	$vendorId = $payload['vendor_id'] ?? ($get['vendor_id'] ?? null);
	$vendorEmail = $payload['vendor_email'] ?? ($get['vendor_email'] ?? null); // supports partial match
	$page = max(1, intval($payload['page'] ?? ($get['page'] ?? 1)));
	$limit = max(1, min(100, intval($payload['limit'] ?? ($get['limit'] ?? 50))));
	$offset = ($page - 1) * $limit;

	$whereConditions = [];
	$params = [];
	$paramTypes = '';

	if (!empty($vendorId)) {
		$whereConditions[] = 'wt.vendor_id = ?';
		$params[] = $vendorId;
		$paramTypes .= 's';
	}

	if (!empty($vendorEmail)) {
		$whereConditions[] = 'LOWER(u.email) LIKE LOWER(?)';
		$params[] = '%' . $vendorEmail . '%';
		$paramTypes .= 's';
	}

	$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

	// Total count
	$countSql = "
		SELECT COUNT(*) AS total
		FROM wallet_transactions wt
		JOIN users u ON u.id = wt.vendor_id
		{$whereClause}
	";
	$countStmt = $conn->prepare($countSql);
	if (!empty($params)) {
		$countStmt->bind_param($paramTypes, ...$params);
	}
	$countStmt->execute();
	$countResult = $countStmt->get_result();
	$total = (int)($countResult->fetch_assoc()['total'] ?? 0);
	$countStmt->close();

	// Data query
	$dataParams = $params;
	$dataTypes = $paramTypes . 'ii';
	$dataSql = "
		SELECT 
			wt.id,
			wt.vendor_id,
			u.email AS vendor_email,
			wt.transaction_type,
			wt.amount,
			wt.currency,
			wt.balance_before,
			wt.balance_after,
			wt.reference_id,
			wt.topup_request_id,
			wt.description,
			wt.created_at
		FROM wallet_transactions wt
		JOIN users u ON u.id = wt.vendor_id
		{$whereClause}
		ORDER BY wt.created_at DESC
		LIMIT ? OFFSET ?
	";
	$dataStmt = $conn->prepare($dataSql);
	$dataParams[] = $limit;
	$dataParams[] = $offset;
	$dataStmt->bind_param($dataTypes, ...$dataParams);
	$dataStmt->execute();
	$dataResult = $dataStmt->get_result();

	$rows = [];
	while ($row = $dataResult->fetch_assoc()) {
		$row['amount'] = (float)$row['amount'];
		$row['balance_before'] = (float)$row['balance_before'];
		$row['balance_after'] = (float)$row['balance_after'];
		$rows[] = $row;
	}
	$dataStmt->close();

	http_response_code(200);
	echo json_encode([
		'status' => 'success',
		'data' => [
			'transactions' => $rows,
			'pagination' => [
				'page' => $page,
				'limit' => $limit,
				'total' => $total,
				'total_pages' => $limit > 0 ? (int)ceil($total / $limit) : 0
			]
		]
	]);
	exit();

} catch (Exception $e) {
	http_response_code(500);
	echo json_encode([
		'status' => 'error',
		'message' => $e->getMessage()
	]);
}


