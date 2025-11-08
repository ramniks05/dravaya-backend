<?php
/**
 * User Login API Endpoint
 * Authenticates user and returns user data with role and status
 */

// CORS Headers - Must be first
require_once __DIR__ . '/../cors.php';

require_once '../../config.php';
require_once '../../database/functions.php';
require_once '../../database/session_functions.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get request data
    $rawInput = file_get_contents('php://input');
    
    // Log raw input for debugging (remove in production)
    logError('Login request received', ['raw_input' => substr($rawInput, 0, 200)], false);
    
    $data = json_decode($rawInput, true);
    
    // Check for JSON parsing errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        $jsonError = json_last_error_msg();
        logError('JSON decode error', ['error' => $jsonError, 'raw_input' => substr($rawInput, 0, 200)]);
        throw new Exception('Invalid JSON format: ' . $jsonError);
    }
    
    if (!$data || !is_array($data)) {
        throw new Exception('Invalid request data. Expected JSON object.');
    }
    
    // Validate input - check both isset and empty
    if (empty($data['email']) || empty($data['password'])) {
        $missing = [];
        if (empty($data['email'])) $missing[] = 'email';
        if (empty($data['password'])) $missing[] = 'password';
        throw new Exception('Missing required fields: ' . implode(', ', $missing));
    }
    
    $email = filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL);
    $password = $data['password'];  // Don't trim password - may contain intentional spaces
    
    if (!$email) {
        throw new Exception('Invalid email format: ' . $data['email']);
    }
    
    if (strlen($password) < 1) {
        throw new Exception('Password cannot be empty');
    }
    
    // Get user from database
    try {
        $conn = getDBConnection();
        
        // First check if user exists (case-insensitive for email)
        $stmt = $conn->prepare("
            SELECT id, email, password_hash, role, status, created_at 
            FROM users 
            WHERE LOWER(email) = LOWER(?) 
            LIMIT 1
        ");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            logError('Login failed - user not found', [
                'email' => $email,
                'email_lowercase' => strtolower($email)
            ], false);
            throw new Exception('Invalid email or password');
        }
        
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // Log user found for debugging
        logError('User found for login', [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'status' => $user['status'],
            'hash_length' => strlen($user['password_hash']),
            'hash_preview' => substr($user['password_hash'], 0, 20)
        ], false);
        
    } catch (mysqli_sql_exception $e) {
        logError('Database error during login', ['error' => $e->getMessage(), 'email' => $email]);
        throw new Exception('Login failed. Please try again later.');
    }
    
    // Verify password
    logError('Password verification attempt', [
        'email' => $email,
        'hash_length' => strlen($user['password_hash']),
        'hash_starts_with' => substr($user['password_hash'], 0, 10),
        'password_length' => strlen($password)
    ], false);
    
    $passwordValid = password_verify($password, $user['password_hash']);
    
    if (!$passwordValid) {
        logError('Failed login attempt - password mismatch', [
            'email' => $email,
            'hash_format_valid' => strpos($user['password_hash'], '$2y$') === 0
        ], false);
        throw new Exception('Invalid email or password');
    }
    
    // Check account status
    if ($user['status'] === 'pending') {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Your account is pending approval. Please wait for an admin to activate your account.'
        ]);
        exit();
    }
    
    if ($user['status'] === 'suspended') {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Your account has been suspended. Please contact support.'
        ]);
        exit();
    }
    
    // Only allow login if status is 'active'
    if ($user['status'] !== 'active') {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Your account is not active. Please contact support.'
        ]);
        exit();
    }
    
    // Generate session token (simple token for now, can be upgraded to JWT)
    $token = bin2hex(random_bytes(32));
    
    // Replace any existing active session (enforce single session per user)
    $existingSession = getActiveUserSession($user['id']);
    if ($existingSession) {
        logError('Existing session replaced during login', [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'previous_session_id' => $existingSession['id'] ?? null
        ], false);
    }
    
    $sessionInfo = createUserSession($user['id'], $token);
    
    // Log successful login
    logError('User logged in successfully', [
        'user_id' => $user['id'],
        'email' => $email,
        'role' => $user['role']
    ], false);
    
    // Return success response with user data
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Login successful',
        'data' => [
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role'],      // REQUIRED: 'admin' or 'vendor'
                'status' => $user['status']   // REQUIRED: 'active', 'pending', 'suspended'
            ],
            'token' => $token,  // Session token
            'session_expires_at' => $sessionInfo['expires_at'] ?? null
        ]
    ]);
    
} catch (Exception $e) {
    // Log the error for debugging
    logError('Login error', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug' => [
            'raw_input_length' => isset($rawInput) ? strlen($rawInput) : 0,
            'json_error' => json_last_error_msg() ?? null
        ]
    ]);
}

