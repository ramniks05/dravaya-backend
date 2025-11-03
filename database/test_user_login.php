<?php
/**
 * Test User Login - Direct Password Verification
 * This script tests if the password hash works correctly
 */

require_once __DIR__ . '/../config.php';

$email = 'ramsitservices05@gmail.com';
$password = 'admin123';

echo "<!DOCTYPE html><html><head><title>User Login Test</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;} .success{color:green;} .error{color:red;} .info{background:white;padding:15px;margin:10px 0;border-radius:5px;}</style></head><body>";
echo "<h1>User Login Test</h1>";

try {
    $conn = getDBConnection();
    
    echo "<div class='info'>";
    echo "<h2>1. Checking Database Connection</h2>";
    echo "✅ Database connected successfully<br>";
    echo "Database: " . DB_NAME . "<br>";
    echo "</div>";
    
    // Get user from database
    echo "<div class='info'>";
    echo "<h2>2. Fetching User from Database</h2>";
    $stmt = $conn->prepare("SELECT id, email, password_hash, role, status, LENGTH(password_hash) as hash_len FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "<span class='error'>❌ User NOT found in database!</span><br><br>";
        echo "<strong>Solution:</strong> Run <code>database/update_admin_password.php</code> to create the user.<br>";
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    echo "<span class='success'>✅ User found!</span><br>";
    echo "ID: {$user['id']}<br>";
    echo "Email: {$user['email']}<br>";
    echo "Role: {$user['role']}<br>";
    echo "Status: {$user['status']}<br>";
    echo "Hash Length: {$user['hash_len']} characters<br>";
    echo "Hash Preview: " . htmlspecialchars(substr($user['password_hash'], 0, 30)) . "...<br>";
    echo "</div>";
    
    // Check hash format
    echo "<div class='info'>";
    echo "<h2>3. Checking Hash Format</h2>";
    if (strpos($user['password_hash'], '$2y$') === 0) {
        echo "<span class='success'>✅ Hash format is correct (bcrypt - starts with \$2y\$)</span><br>";
    } else {
        echo "<span class='error'>❌ Hash format might be incorrect!</span><br>";
        echo "Hash starts with: " . htmlspecialchars(substr($user['password_hash'], 0, 10)) . "<br>";
    }
    echo "</div>";
    
    // Test password verification
    echo "<div class='info'>";
    echo "<h2>4. Testing Password Verification</h2>";
    echo "Testing password: <strong>{$password}</strong><br>";
    echo "Email: <strong>{$email}</strong><br><br>";
    
    // Test 1: Direct password_verify
    $test1 = password_verify($password, $user['password_hash']);
    echo "Test 1 - password_verify(): " . ($test1 ? "<span class='success'>✅ SUCCESS</span>" : "<span class='error'>❌ FAILED</span>") . "<br>";
    
    // Test 2: Trim password
    $test2 = password_verify(trim($password), trim($user['password_hash']));
    echo "Test 2 - password_verify(trim): " . ($test2 ? "<span class='success'>✅ SUCCESS</span>" : "<span class='error'>❌ FAILED</span>") . "<br>";
    
    // Test 3: Generate new hash and compare
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $test3 = password_verify($password, $newHash);
    echo "Test 3 - New hash verification: " . ($test3 ? "<span class='success'>✅ SUCCESS</span>" : "<span class='error'>❌ FAILED</span>") . "<br>";
    echo "</div>";
    
    if ($test1 || $test2) {
        echo "<div class='info' style='background:#d1fae5;'>";
        echo "<h2 class='success'>✅ Password Verification Works!</h2>";
        if ($user['status'] !== 'active') {
            echo "<span class='error'>⚠️ BUT: Account status is '{$user['status']}' - must be 'active' to login!</span><br>";
            echo "<strong>Fix:</strong> UPDATE users SET status = 'active' WHERE email = '{$email}';<br>";
        } else {
            echo "Account status is 'active' ✅<br>";
            echo "You should be able to login!<br>";
        }
        echo "</div>";
    } else {
        echo "<div class='info' style='background:#fee2e2;'>";
        echo "<h2 class='error'>❌ Password Verification Failed!</h2>";
        echo "<strong>The password hash in database doesn't match the password.</strong><br><br>";
        echo "<strong>Solution:</strong> Update the password hash:<br>";
        echo "<code>UPDATE users SET password_hash = '{$newHash}' WHERE email = '{$email}';</code><br><br>";
        echo "Or run: <code>database/update_admin_password.php</code>";
        echo "</div>";
    }
    
    // Show SQL to fix
    echo "<div class='info'>";
    echo "<h2>5. SQL Commands to Fix</h2>";
    echo "<strong>If password verification failed, run this SQL:</strong><br>";
    echo "<pre style='background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:5px;overflow-x:auto;'>";
    echo "UPDATE users SET \n";
    echo "    password_hash = '{$newHash}',\n";
    echo "    status = 'active'\n";
    echo "WHERE email = '{$email}';";
    echo "</pre>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='info' style='background:#fee2e2;'>";
    echo "<h2 class='error'>❌ Error</h2>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</body></html>";

