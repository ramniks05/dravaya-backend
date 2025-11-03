<?php
/**
 * User Signup API Endpoint
 * Creates a new vendor account (pending approval)
 */

// CORS Headers - Must be first
require_once __DIR__ . '/../cors.php';

require_once '../../config.php';
require_once '../../database/functions.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get request data
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON request data');
    }
    
    // Validate input
    if (!isset($data['email']) || !isset($data['password'])) {
        throw new Exception('Email and password are required');
    }
    
    $email = filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL);
    $password = $data['password'];
    
    if (!$email) {
        throw new Exception('Invalid email format');
    }
    
    if (strlen($password) < 6) {
        throw new Exception('Password must be at least 6 characters');
    }
    
    // Check if email already exists
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception('Email already registered. Please use a different email or try logging in.');
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        logError('Database error checking email', ['error' => $e->getMessage(), 'email' => $email]);
        throw new Exception('Registration failed. Please try again later.');
    }
    
    // Generate UUID for user ID
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
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $role = 'vendor'; // All signups are vendors by default
    $status = 'pending'; // New accounts require admin approval
    
    // Insert user into database
    try {
        $stmt = $conn->prepare("
            INSERT INTO users (id, email, password_hash, role, status) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('sssss', $userId, $email, $hashedPassword, $role, $status);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create account. Please try again.');
        }
        $stmt->close();
        
        // Log successful signup
        logError('User signed up successfully', [
            'user_id' => $userId,
            'email' => $email,
            'role' => $role
        ], false);
        
        // Return success response
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Account created successfully! Your account is pending approval. Please wait for an admin to activate your account.',
            'data' => [
                'user' => [
                    'id' => $userId,
                    'email' => $email,
                    'role' => $role,
                    'status' => $status,
                    'created_at' => date('c')
                ]
            ]
        ]);
        
    } catch (mysqli_sql_exception $e) {
        logError('Database error creating user', ['error' => $e->getMessage(), 'email' => $email]);
        throw new Exception('Registration failed. Please try again later.');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

