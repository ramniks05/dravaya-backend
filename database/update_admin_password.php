<?php
/**
 * Update Admin Password Script
 * Use this to update/regenerate password hash for existing admin user
 */

require_once __DIR__ . '/../config.php';

$email = 'ramsitservices05@gmail.com';
$newPassword = 'admin123';  // Change this to your desired password

try {
    $conn = getDBConnection();
    
    echo "=== Update Admin Password ===\n\n";
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, email, role FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "❌ User not found. Creating new admin user...\n\n";
        
        // Create new user
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
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("
            INSERT INTO users (id, email, password_hash, role, status) 
            VALUES (?, ?, ?, 'admin', 'active')
        ");
        $stmt->bind_param('sss', $userId, $email, $hashedPassword);
        
        if ($stmt->execute()) {
            echo "✅ Admin user created successfully!\n";
            echo "   Email: {$email}\n";
            echo "   Password: {$newPassword}\n";
            echo "   Role: admin\n";
            echo "   Status: active\n";
        } else {
            throw new Exception('Failed to create user: ' . $stmt->error);
        }
        $stmt->close();
    } else {
        // Update existing user
        $user = $result->fetch_assoc();
        $stmt->close();
        
        echo "✅ User found: {$user['email']} (Role: {$user['role']})\n";
        echo "Updating password hash...\n\n";
        
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password_hash = ?, status = 'active' WHERE email = ?");
        $stmt->bind_param('ss', $hashedPassword, $email);
        
        if ($stmt->execute()) {
            echo "✅ Password updated successfully!\n";
            echo "   Email: {$email}\n";
            echo "   New Password: {$newPassword}\n";
            echo "   Hash: " . substr($hashedPassword, 0, 30) . "...\n\n";
            
            // Verify the password works
            if (password_verify($newPassword, $hashedPassword)) {
                echo "✅ Password verification test: SUCCESS\n";
                echo "   You can now login with email: {$email}\n";
                echo "   Password: {$newPassword}\n";
            } else {
                echo "❌ Password verification test: FAILED\n";
            }
        } else {
            throw new Exception('Failed to update password: ' . $stmt->error);
        }
        $stmt->close();
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

