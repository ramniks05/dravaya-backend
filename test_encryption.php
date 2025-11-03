<?php
/**
 * Test Encryption Script
 * Run this to verify encryption is working correctly
 */

require_once 'config.php';

// Test payload
$testPayload = [
    'ben_name' => 'Test User',
    'ben_phone_number' => '9876543210',
    'ben_vpa_address' => 'test@upi',
    'amount' => '100',
    'merchant_reference_id' => 'TEST123',
    'transfer_type' => 'UPI',
    'apicode' => API_CODE,
    'narration' => 'PAYNINJA Fund Transfer',
    'signature' => 'test_signature'
];

echo "=== PayNinja Encryption Test ===\n\n";

// Step 1: Create JSON
$payloadJson = json_encode($testPayload);
echo "1. Payload JSON:\n";
echo $payloadJson . "\n\n";

// Step 2: Generate IV
$iv = generateIV();
echo "2. IV (16 characters):\n";
echo $iv . " (length: " . strlen($iv) . ")\n\n";

// Step 3: Prepare key (32 bytes)
$keyLength = strlen(SECRET_KEY);
if ($keyLength > 32) {
    $keyToUse = substr(SECRET_KEY, 0, 32);
} elseif ($keyLength < 32) {
    $keyToUse = str_pad(SECRET_KEY, 32, "\0");
} else {
    $keyToUse = SECRET_KEY;
}
echo "3. Encryption Key:\n";
echo "   Original length: " . $keyLength . "\n";
echo "   Using length: " . strlen($keyToUse) . "\n\n";

// Step 4: Encrypt
$encdata = encrypt_decrypt('encrypt', $payloadJson, $keyToUse, $iv);
echo "4. Encrypted Data (Base64):\n";
echo substr($encdata, 0, 100) . "...\n";
echo "   Full length: " . strlen($encdata) . "\n\n";

// Step 5: Verify decryption works
$decrypted = encrypt_decrypt('decrypt', $encdata, $keyToUse, $iv);
echo "5. Decryption Test:\n";
if ($decrypted === $payloadJson) {
    echo "   ✅ Decryption successful! Data matches.\n";
    echo "   Decrypted: " . $decrypted . "\n\n";
} else {
    echo "   ❌ Decryption failed! Data does not match.\n";
    echo "   Original: " . $payloadJson . "\n";
    echo "   Decrypted: " . $decrypted . "\n\n";
}

// Step 6: Request body structure
$requestBody = [
    'encdata' => $encdata,
    'key' => SECRET_KEY,
    'Iv' => $iv
];

echo "6. Request Body Structure:\n";
echo json_encode([
    'encdata' => substr($encdata, 0, 50) . '... (length: ' . strlen($encdata) . ')',
    'key' => substr(SECRET_KEY, 0, 10) . '... (length: ' . strlen(SECRET_KEY) . ')',
    'Iv' => $iv
], JSON_PRETTY_PRINT) . "\n\n";

echo "=== Test Complete ===\n";
echo "\n✅ If decryption works, encryption is correct!\n";
echo "⚠️  Make sure the data is NOT plain text in the request body.\n";
echo "    Request body should only contain 'encdata', 'key', and 'Iv'.\n";

