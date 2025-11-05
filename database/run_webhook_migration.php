<?php
// Run the add_webhook_support.sql migration against current DB
require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain');

try {
    $conn = getDBConnection();
    $sqlFile = __DIR__ . '/add_webhook_support.sql';
    if (!file_exists($sqlFile)) {
        echo "Migration file not found: add_webhook_support.sql\n";
        exit(1);
    }
    $sql = file_get_contents($sqlFile);
    // Split by semicolon keeping things simple; file is small and straightforward
    $statements = array_filter(array_map('trim', explode(";\n", $sql)));
    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        $conn->query($stmt);
    }
    echo "Webhook migration executed successfully.\n";
} catch (Throwable $e) {
    logError('Webhook migration failed', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}


