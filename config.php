<?php
/**
 * PayNinja API Configuration
 * Keep this file secure - never commit to public repositories
 */

// Load environment variables if .env file exists
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    if ($env !== false) {
        foreach ($env as $key => $value) {
            $_ENV[$key] = $value;
        }
    } else {
        // Log error if .env file exists but couldn't be parsed
        error_log('Warning: Failed to parse .env file. Check for syntax errors.');
    }
}

// Ensure PHP uses Indian Standard Time by default
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/utils/time.php';

// API Configuration - Use environment variables if available, otherwise fallback to hardcoded (for development)
define('API_BASE_URL', $_ENV['API_BASE_URL'] ?? 'https://dashboard.payninja.in');
define('API_KEY', $_ENV['API_KEY'] ?? 'ucUzRVgPQdXuPwXgSnSqSCT7mhWJr0az');
define('SECRET_KEY', $_ENV['SECRET_KEY'] ?? 'VXwdYI6AsM9nvWToAulDVkkvGXxJZ2rPSrfTfb4dHhbuqungDq6lAgvbf0JKz7ol');
define('API_CODE', $_ENV['API_CODE'] ?? 101);

// Database Configuration - MySQL (XAMPP)
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'dravya');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

// CORS Configuration - Allow specific origins for security
// For development, you can use '*' but for production, specify exact origin
$allowedOrigins = [
    'http://localhost:3000',      // React default dev server
    'http://localhost:3001',
    'http://localhost:5173',      // Vite default dev server
    'http://127.0.0.1:3000',
    'http://127.0.0.1:3001',
    'http://127.0.0.1:5173',      // Vite dev server (127.0.0.1)
    // Production frontend URL(s)
    'https://dravaya-frontend.vercel.app'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowAllOrigins = isset($_ENV['ALLOW_ALL_ORIGINS']) && $_ENV['ALLOW_ALL_ORIGINS'] === 'true';

if (in_array($origin, $allowedOrigins) || $allowAllOrigins) {
    $corsOrigin = $origin ?: '*';
} else {
    // Default to first allowed origin or wildcard for development
    $corsOrigin = $allowedOrigins[0] ?? '*';
}

// CORS is handled by api/cors.php for API endpoints
// Only set CORS here if NOT in an API endpoint and not index page
$isApiEndpoint = strpos($_SERVER['PHP_SELF'] ?? '', '/api/') !== false;

// Only set CORS here for non-API endpoints (if cors.php not included)
if (!headers_sent() && (!isset($isIndexPage) || !$isIndexPage) && !$isApiEndpoint) {
    // Check if Access-Control-Allow-Origin header is already set
    if (!isset($GLOBALS['cors_headers_set'])) {
        header('Access-Control-Allow-Origin: ' . $corsOrigin, true);
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS', true);
        header('Access-Control-Allow-Headers: Content-Type, Authorization', true);
        header('Access-Control-Allow-Credentials: true', true);
        header('Content-Type: application/json', true);
    }
}

// Handle preflight requests only if not already handled by cors.php
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS' && !$isApiEndpoint) {
    http_response_code(200);
    exit();
}

/**
 * Encryption/Decryption function matching PayNinja format exactly
 * As per PayNinja documentation
 */
function encrypt_decrypt($action, $string, $key, $iv) {
    $output = false;
    $encrypt_method = "AES-256-CBC";
    
    // PayNinja documentation shows using key and iv directly
    // openssl_encrypt will automatically use first 32 bytes of key for AES-256
    // and first 16 bytes of iv for CBC mode
    
    if ($action == 'encrypt') {
        $output = openssl_encrypt($string, $encrypt_method, $key, OPENSSL_RAW_DATA, $iv);
        if ($output === false) {
            return false;
        }
        $output = base64_encode($output);
    } elseif ($action == 'decrypt') {
        $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, OPENSSL_RAW_DATA, $iv);
    }
    
    return $output;
}

/**
 * Generate random IV (16 characters)
 * According to PayNinja docs, IV should be 16 characters alphanumeric
 */
function generateIV() {
    // Generate a cryptographically secure random IV (16 bytes = 16 characters for hex)
    // But PayNinja expects alphanumeric, so we'll use the original method
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $iv = '';
    for ($i = 0; $i < 16; $i++) {
        $iv .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $iv;
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate phone number (Indian format)
 */
function validatePhoneNumber($phone) {
    // Remove any non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    // Check if it's a valid Indian mobile number (10 digits starting with 6-9)
    return preg_match('/^[6-9][0-9]{9}$/', $phone);
}

/**
 * Validate amount (must be positive number)
 */
function validateAmount($amount) {
    return is_numeric($amount) && floatval($amount) > 0;
}

/**
 * Log errors (simple file-based logging)
 */
function logError($message, $context = [], $isError = true) {
    $logFile = __DIR__ . '/logs/' . ($isError ? 'error' : 'info') . '.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    if (!empty($context)) {
        $logMessage .= " | Context: " . json_encode($context);
    }
    $logMessage .= "\n";
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Database Connection
 * Creates and returns a MySQLi connection
 */
function getDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $conn->set_charset(DB_CHARSET);

            // Ensure MySQL session uses IST for timestamp defaults
            if (!$conn->query("SET time_zone = '+05:30'")) {
                logError('Failed to set database session timezone to IST', [
                    'error' => $conn->error
                ], false);
            }
        } catch (mysqli_sql_exception $e) {
            logError('Database connection failed', ['error' => $e->getMessage()]);
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    return $conn;
}

/**
 * Test database connection
 */
function testDatabaseConnection() {
    try {
        $conn = getDBConnection();
        return ['success' => true, 'message' => 'Database connected successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Execute a prepared statement
 */
function executeQuery($query, $types = '', $params = []) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare($query);
        
        if ($stmt === false) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        // For SELECT queries, return the result set
        if (stripos($query, 'SELECT') === 0) {
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
            return $data;
        }
        
        // For INSERT/UPDATE/DELETE, return affected rows and insert ID
        $affectedRows = $stmt->affected_rows;
        $insertId = $conn->insert_id;
        $stmt->close();
        
        return [
            'affected_rows' => $affectedRows,
            'insert_id' => $insertId > 0 ? $insertId : null
        ];
        
    } catch (Exception $e) {
        logError('Database query failed', [
            'query' => substr($query, 0, 200),
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}

