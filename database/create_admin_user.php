<?php
/**
 * Create Admin User Script
 * Run this script from command line: php database/create_admin_user.php
 * Or access via browser: http://localhost/backend/database/create_admin_user.php
 */

require_once __DIR__ . '/../config.php';

// Configuration
$adminEmail = 'ramsitservices05@gmail.com';  // Admin email
$adminPassword = 'admin123';  // Admin password

try {
    $conn = getDBConnection();
    
    // Check if admin already exists
    $stmt = $conn->prepare("SELECT id, email FROM users WHERE email = ? AND role = 'admin'");
    $stmt->bind_param('s', $adminEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $existing = $result->fetch_assoc();
        echo "❌ Admin user with email '{$adminEmail}' already exists!\n";
        echo "   ID: {$existing['id']}\n";
        echo "   Email: {$existing['email']}\n\n";
        echo "If you want to update the password, use the SQL script manually.\n";
        exit(1);
    }
    $stmt->close();
    
    // Generate UUID
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
    $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
    
    // Insert admin user
    $stmt = $conn->prepare("
        INSERT INTO users (id, email, password_hash, role, status) 
        VALUES (?, ?, ?, 'admin', 'active')
    ");
    $stmt->bind_param('sss', $userId, $adminEmail, $hashedPassword);
    
    if ($stmt->execute()) {
        echo "✅ Admin user created successfully!\n\n";
        echo "User Details:\n";
        echo "  ID: {$userId}\n";
        echo "  Email: {$adminEmail}\n";
        echo "  Password: {$adminPassword}\n";
        echo "  Role: admin\n";
        echo "  Status: active\n\n";
        echo "⚠️  IMPORTANT: Please change the password after first login!\n";
        echo "⚠️  Keep this information secure!\n";
    } else {
        throw new Exception('Failed to create admin user: ' . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

