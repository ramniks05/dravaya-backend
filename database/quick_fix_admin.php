<?php
/**
 * Quick Fix Admin User - One-click solution
 * Open this in browser: http://localhost/backend/database/quick_fix_admin.php
 */

// CORS headers for browser access
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config.php';

$email = 'ramsitservices05@gmail.com';
$password = 'admin123';

echo "<!DOCTYPE html><html><head><title>Quick Fix Admin</title>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;background:#f5f5f5;} .success{color:green;font-weight:bold;} .error{color:red;font-weight:bold;} .info{background:white;padding:20px;margin:15px 0;border-radius:8px;border-left:4px solid #667eea;} pre{background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:5px;overflow-x:auto;}</style></head><body>";
echo "<h1>🔧 Quick Fix Admin User</h1>";

try {
    $conn = getDBConnection();
    echo "<div class='info'><strong>✅ Database Connected</strong></div>";
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, email, password_hash, role, status FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    if ($result->num_rows === 0) {
        // Create user
        echo "<div class='info'>";
        echo "<strong>User not found. Creating new admin user...</strong><br><br>";
        
        function generateUUID() {
            return sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        }
        
        $userId = generateUUID();
        $stmt = $conn->prepare("INSERT INTO users (id, email, password_hash, role, status) VALUES (?, ?, ?, 'admin', 'active')");
        $stmt->bind_param('sss', $userId, $email, $hashedPassword);
        
        if ($stmt->execute()) {
            echo "<span class='success'>✅ Admin user created successfully!</span><br>";
        } else {
            throw new Exception('Failed to create user: ' . $stmt->error);
        }
        $stmt->close();
        echo "</div>";
    } else {
        // Update user
        $user = $result->fetch_assoc();
        $stmt->close();
        
        echo "<div class='info'>";
        echo "<strong>User found. Updating password hash...</strong><br><br>";
        echo "Current Status: {$user['status']}<br>";
        echo "Current Role: {$user['role']}<br><br>";
        
        $updateStmt = $conn->prepare("UPDATE users SET password_hash = ?, status = 'active' WHERE email = ?");
        $updateStmt->bind_param('ss', $hashedPassword, $email);
        
        if ($updateStmt->execute()) {
            echo "<span class='success'>✅ Password hash updated successfully!</span><br>";
        } else {
            throw new Exception('Failed to update: ' . $updateStmt->error);
        }
        $updateStmt->close();
        echo "</div>";
    }
    
    // Verify password works
    echo "<div class='info'>";
    echo "<h2>🔐 Password Verification Test</h2>";
    
    // Get updated user
    $verifyStmt = $conn->prepare("SELECT password_hash, status FROM users WHERE email = ? LIMIT 1");
    $verifyStmt->bind_param('s', $email);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();
    $verifyUser = $verifyResult->fetch_assoc();
    $verifyStmt->close();
    
    $passwordValid = password_verify($password, $verifyUser['password_hash']);
    
    if ($passwordValid) {
        echo "<span class='success'>✅ Password verification: SUCCESS</span><br><br>";
        echo "<strong>You can now login with:</strong><br>";
        echo "Email: <code>{$email}</code><br>";
        echo "Password: <code>{$password}</code><br><br>";
        echo "Account Status: <code>{$verifyUser['status']}</code><br>";
        
        if ($verifyUser['status'] !== 'active') {
            echo "<br><span class='error'>⚠️ WARNING: Status is not 'active'. Updating now...</span><br>";
            $statusStmt = $conn->prepare("UPDATE users SET status = 'active' WHERE email = ?");
            $statusStmt->bind_param('s', $email);
            $statusStmt->execute();
            $statusStmt->close();
            echo "<span class='success'>✅ Status updated to 'active'</span>";
        }
    } else {
        echo "<span class='error'>❌ Password verification: FAILED</span><br>";
        echo "This should not happen. Please contact support.";
    }
    echo "</div>";
    
    echo "<div class='info' style='background:#d1fae5;'>";
    echo "<h2>✅ Fix Complete!</h2>";
    echo "Try logging in now with:<br>";
    echo "<strong>Email:</strong> {$email}<br>";
    echo "<strong>Password:</strong> {$password}<br>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='info' style='background:#fee2e2;'>";
    echo "<h2 class='error'>❌ Error</h2>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</body></html>";

