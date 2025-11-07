<?php
/**
 * Test .env file loading
 * This file helps debug .env configuration issues
 * DELETE THIS FILE after fixing the issue!
 */

header('Content-Type: text/html; charset=utf-8');
echo "<h2>.env File Debug Test</h2>";
echo "<pre>";

// Check if .env file exists
$envPath = __DIR__ . '/.env';
echo "1. Checking .env file location:\n";
echo "   Path: " . $envPath . "\n";
echo "   Exists: " . (file_exists($envPath) ? "YES ✓" : "NO ✗") . "\n\n";

if (file_exists($envPath)) {
    echo "2. .env file contents (first 500 chars):\n";
    echo "   " . substr(file_get_contents($envPath), 0, 500) . "\n\n";
    
    echo "3. Parsing .env file:\n";
    $env = parse_ini_file($envPath);
    
    if ($env === false) {
        echo "   ERROR: Failed to parse .env file!\n";
        echo "   Check for syntax errors (no spaces around =, no quotes needed)\n";
    } else {
        echo "   Parsed successfully! Found " . count($env) . " keys:\n";
        foreach ($env as $key => $value) {
            // Don't show full passwords, just first 3 chars
            if (strpos(strtolower($key), 'pass') !== false || strpos(strtolower($key), 'secret') !== false || strpos(strtolower($key), 'key') !== false) {
                $displayValue = substr($value, 0, 3) . str_repeat('*', max(0, strlen($value) - 3));
            } else {
                $displayValue = $value;
            }
            echo "   - $key = $displayValue\n";
        }
    }
} else {
    echo "2. .env file NOT FOUND!\n";
    echo "   Please create it at: $envPath\n";
}

echo "\n4. Current configuration values:\n";
echo "   DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED') . "\n";
echo "   DB_USER: " . (defined('DB_USER') ? DB_USER : 'NOT DEFINED') . "\n";
echo "   DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED') . "\n";

echo "\n5. Testing database connection:\n";
try {
    require_once __DIR__ . '/config.php';
    $conn = getDBConnection();
    echo "   Connection: SUCCESS ✓\n";
    echo "   Host: " . DB_HOST . "\n";
    echo "   User: " . DB_USER . "\n";
    echo "   Database: " . DB_NAME . "\n";
} catch (Exception $e) {
    echo "   Connection: FAILED ✗\n";
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n</pre>";
echo "<p><strong>⚠️ IMPORTANT: Delete this file (test_env.php) after fixing the issue!</strong></p>";



