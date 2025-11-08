<?php
/**
 * Logout From All Devices API Endpoint
 * Invalidates every active session for the authenticated user
 */

require_once __DIR__ . '/../cors.php';
require_once '../../config.php';
require_once '../../database/functions.php';
require_once '../../database/session_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

try {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = null;

    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = trim($matches[1]);
    }

    if (!$token) {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        if (isset($data['token']) && !empty($data['token'])) {
            $token = $data['token'];
        }
    }

    if (!$token) {
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'All sessions have been terminated'
        ]);
        return;
    }

    $session = validateSessionToken($token);

    if ($session) {
        invalidateSessionsByUser($session['user_id']);

        logError('User logged out from all devices', [
            'user_id' => $session['user_id'],
            'email' => $session['email'] ?? null
        ], false);
    } else {
        // If token invalid, ensure it is removed
        invalidateSessionByToken($token);
    }

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'All sessions have been terminated'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

