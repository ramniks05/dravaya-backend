<?php
/**
 * Password Hash Generator
 * Run this to generate a password hash for the SQL script
 * Usage: php database/get_password_hash.php
 * Or via browser with ?password=yourpassword
 */

// Get password from command line argument or query parameter
if (php_sapi_name() === 'cli') {
    if (isset($argv[1])) {
        $password = $argv[1];
    } else {
        echo "Usage: php get_password_hash.php <password>\n";
        echo "Example: php get_password_hash.php admin123\n";
        exit(1);
    }
} else {
    $password = $_GET['password'] ?? null;
    if (!$password) {
        echo "Usage: ?password=yourpassword\n";
        echo "Example: ?password=admin123\n";
        exit(1);
    }
}

// Generate hash
$hash = password_hash($password, PASSWORD_DEFAULT);

// Output format based on how script is called
if (php_sapi_name() === 'cli') {
    echo "\n";
    echo "=== Password Hash Generator ===\n\n";
    echo "Password: {$password}\n";
    echo "Hash: {$hash}\n\n";
    echo "SQL Statement to Update:\n";
    echo "UPDATE users SET password_hash = '{$hash}', status = 'active' WHERE email = 'ramsitservices05@gmail.com';\n\n";
    echo "Or INSERT if user doesn't exist:\n";
    echo "INSERT INTO users (id, email, password_hash, role, status) \n";
    echo "VALUES (UUID(), 'ramsitservices05@gmail.com', '{$hash}', 'admin', 'active');\n\n";
} else {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Password Hash Generator</title>";
    echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;} pre{background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:5px;}</style></head><body>";
    echo "<h1>Password Hash Generator</h1>";
    echo "<div style='background:white;padding:20px;border-radius:8px;margin:20px 0;'>";
    echo "<h2>Password: <code>{$password}</code></h2>";
    echo "<h3>Generated Hash:</h3>";
    echo "<pre style='word-break:break-all;'>{$hash}</pre>";
    echo "<h3>SQL UPDATE Statement:</h3>";
    echo "<pre>UPDATE users \nSET password_hash = '{$hash}', status = 'active' \nWHERE email = 'ramsitservices05@gmail.com';</pre>";
    echo "<h3>SQL INSERT Statement (if user doesn't exist):</h3>";
    echo "<pre>INSERT INTO users (id, email, password_hash, role, status) \nVALUES (UUID(), 'ramsitservices05@gmail.com', '{$hash}', 'admin', 'active');</pre>";
    echo "</div>";
    echo "</body></html>";
}

