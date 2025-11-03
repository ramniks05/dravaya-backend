<?php
/**
 * Check Password Hash Script
 * This script helps verify if password hashing/verification is working correctly
 */

require_once __DIR__ . '/../config.php';

// Get email and password from query string or arguments
if (php_sapi_name() === 'cli') {
    if (!isset($argv[1]) || !isset($argv[2])) {
        echo "Usage: php check_password.php <email> <password>\n";
        echo "Example: php check_password.php ramsitservices05@gmail.com admin123\n";
        exit(1);
    }
    $email = $argv[1];
    $password = $argv[2];
} else {
    $email = $_GET['email'] ?? 'ramsitservices05@gmail.com';
    $password = $_GET['password'] ?? 'admin123';
}

try {
    $conn = getDBConnection();
    
    echo "=== Password Hash Checker ===\n\n";
    echo "Checking user: {$email}\n";
    echo "Testing password: {$password}\n\n";
    
    // Get user from database
    $stmt = $conn->prepare("SELECT id, email, password_hash, role, status FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "❌ User not found in database!\n";
        echo "\nTo create this user, run:\n";
        echo "php database/create_admin_user.php\n";
        echo "Or update the email in create_admin_user.php and run it.\n";
        exit(1);
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    echo "✅ User found in database:\n";
    echo "   ID: {$user['id']}\n";
    echo "   Email: {$user['email']}\n";
    echo "   Role: {$user['role']}\n";
    echo "   Status: {$user['status']}\n";
    echo "   Password Hash: " . substr($user['password_hash'], 0, 20) . "...\n";
    echo "   Hash Length: " . strlen($user['password_hash']) . " characters\n\n";
    
    // Check hash format
    if (strpos($user['password_hash'], '$2y$') === 0) {
        echo "✅ Hash format is correct (bcrypt - starts with \$2y\$)\n\n";
    } else {
        echo "⚠️  Hash format might be incorrect (should start with \$2y\$)\n";
        echo "   Current hash starts with: " . substr($user['password_hash'], 0, 10) . "\n\n";
    }
    
    // Test password verification
    echo "Testing password verification...\n";
    $isValid = password_verify($password, $user['password_hash']);
    
    if ($isValid) {
        echo "✅ Password verification SUCCESSFUL!\n";
        echo "   The password '{$password}' matches the hash in database.\n\n";
        echo "If login still fails, check:\n";
        echo "  1. Account status is 'active' (current: {$user['status']})\n";
        echo "  2. Email matches exactly (case-sensitive)\n";
    } else {
        echo "❌ Password verification FAILED!\n";
        echo "   The password '{$password}' does NOT match the hash.\n\n";
        echo "Solutions:\n";
        echo "  1. Update the password hash in database:\n";
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        echo "     UPDATE users SET password_hash = '{$newHash}' WHERE email = '{$email}';\n\n";
        echo "  2. Or regenerate the user:\n";
        echo "     DELETE FROM users WHERE email = '{$email}';\n";
        echo "     Then run: php database/create_admin_user.php\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

