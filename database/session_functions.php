<?php

require_once __DIR__ . '/../config.php';

if (!defined('USER_SESSION_TTL_MINUTES')) {
    define('USER_SESSION_TTL_MINUTES', 720); // 12 hours
}

function ensureUserSessionsTable(mysqli $conn): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $createTableSql = "
        CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            token VARCHAR(128) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            INDEX idx_user (user_id),
            UNIQUE KEY uniq_token (token),
            CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $conn->query($createTableSql);
    $initialized = true;
}

function cleanupExpiredSessions(): void
{
    $conn = getDBConnection();
    ensureUserSessionsTable($conn);

    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("UPDATE user_sessions SET is_active = 0 WHERE is_active = 1 AND expires_at <= ?");
    $stmt->bind_param('s', $now);
    $stmt->execute();
    $stmt->close();
}

function createUserSession(string $userId, string $token, ?int $ttlMinutes = null): array
{
    $conn = getDBConnection();
    ensureUserSessionsTable($conn);
    cleanupExpiredSessions();

    $ttlMinutes = $ttlMinutes ?? USER_SESSION_TTL_MINUTES;
    $createdAt = date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$ttlMinutes} minutes"));

    $deactivateStmt = $conn->prepare("UPDATE user_sessions SET is_active = 0 WHERE user_id = ?");
    $deactivateStmt->bind_param('s', $userId);
    $deactivateStmt->execute();
    $deactivateStmt->close();

    $insertStmt = $conn->prepare("
        INSERT INTO user_sessions (user_id, token, created_at, last_active, expires_at, is_active)
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    $insertStmt->bind_param('sssss', $userId, $token, $createdAt, $createdAt, $expiresAt);
    $insertStmt->execute();
    $insertStmt->close();

    return [
        'token' => $token,
        'user_id' => $userId,
        'created_at' => $createdAt,
        'expires_at' => $expiresAt
    ];
}

function getActiveUserSession(string $userId): ?array
{
    $conn = getDBConnection();
    ensureUserSessionsTable($conn);
    cleanupExpiredSessions();

    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("
        SELECT *
        FROM user_sessions
        WHERE user_id = ? AND is_active = 1 AND expires_at > ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->bind_param('ss', $userId, $now);
    $stmt->execute();
    $result = $stmt->get_result();
    $session = $result->fetch_assoc();
    $stmt->close();

    return $session ?: null;
}

function invalidateSessionByToken(string $token): bool
{
    $conn = getDBConnection();
    ensureUserSessionsTable($conn);

    $stmt = $conn->prepare("UPDATE user_sessions SET is_active = 0 WHERE token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $affected = $stmt->affected_rows > 0;
    $stmt->close();

    return $affected;
}

function invalidateSessionsByUser(string $userId): void
{
    $conn = getDBConnection();
    ensureUserSessionsTable($conn);

    $stmt = $conn->prepare("UPDATE user_sessions SET is_active = 0 WHERE user_id = ?");
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $stmt->close();
}

function validateSessionToken(string $token): ?array
{
    if (!$token) {
        return null;
    }

    $conn = getDBConnection();
    ensureUserSessionsTable($conn);
    cleanupExpiredSessions();

    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("
        SELECT us.*, u.email, u.role, u.status
        FROM user_sessions us
        INNER JOIN users u ON u.id = us.user_id
        WHERE us.token = ? AND us.is_active = 1 AND us.expires_at > ?
        LIMIT 1
    ");
    $stmt->bind_param('ss', $token, $now);
    $stmt->execute();
    $result = $stmt->get_result();
    $session = $result->fetch_assoc();
    $stmt->close();

    if (!$session) {
        return null;
    }

    $lastActive = date('Y-m-d H:i:s');
    $updateStmt = $conn->prepare("UPDATE user_sessions SET last_active = ? WHERE id = ?");
    $updateStmt->bind_param('si', $lastActive, $session['id']);
    $updateStmt->execute();
    $updateStmt->close();

    $session['last_active'] = $lastActive;

    return $session;
}


