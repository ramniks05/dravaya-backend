<?php
/**
 * Test PayNinja Signature Generation
 * This script helps verify the exact signature format PayNinja expects
 */

require_once 'config.php';

// Test data matching your latest request
$testData = [
    'ben_name' => 'Ramesh kumar',
    'ben_phone_number' => '7903152429',
    'amount' => '100',
    'merchant_reference_id' => 'PAYOUT_1762108744_2065dcec',
    'transfer_type' => 'NEFT',
    'apicode' => '810',
    'narration' => 'Pay',
    'ben_account_number' => '33618111989',
    'ben_ifsc' => 'SBIN0008435',
    'ben_bank_name' => 'state bank of india'
];

$apicodeStr = (string)$testData['apicode'];
$narration = $testData['narration'];

// Build signature string exactly as per PayNinja docs
$signatureString = "{$testData['ben_name']}-{$testData['ben_phone_number']}-{$testData['ben_account_number']}{$testData['ben_ifsc']}-{$testData['ben_bank_name']}-{$testData['amount']}-{$testData['merchant_reference_id']}-{$testData['transfer_type']}-{$apicodeStr}-{$narration}" . SECRET_KEY;

$signatureHash = hash('sha256', $signatureString);

echo "=== PayNinja Signature Test ===\n\n";
echo "API_KEY: " . API_KEY . "\n";
echo "SECRET_KEY: " . substr(SECRET_KEY, 0, 10) . "..." . substr(SECRET_KEY, -10) . " (length: " . strlen(SECRET_KEY) . ")\n";
echo "API_CODE: " . API_CODE . " (type: " . gettype(API_CODE) . ")\n\n";

echo "Signature String (Full):\n";
echo $signatureString . "\n\n";
echo "Signature String Length: " . strlen($signatureString) . "\n\n";

echo "Signature Hash (SHA256):\n";
echo $signatureHash . "\n\n";

echo "=== Verification ===\n";
echo "This signature should match what PayNinja calculates when they:\n";
echo "1. Receive your request with API_KEY: " . API_KEY . "\n";
echo "2. Look up the SECRET_KEY linked to that API_KEY in their database\n";
echo "3. Decrypt your payload using the 'key' and 'iv' from the request body\n";
echo "4. Extract fields from decrypted payload\n";
echo "5. Rebuild signature string in the same format\n";
echo "6. Compare the hash\n\n";

echo "=== Possible Issues ===\n";
echo "If PayNinja rejects the signature, it could mean:\n";
echo "1. The SECRET_KEY in your config doesn't match what's in PayNinja's dashboard for this API_KEY\n";
echo "2. PayNinja uses the SECRET_KEY from their database (not the one in request body) for validation\n";
echo "3. There's a subtle difference in how PayNinja extracts/reconstructs the signature string\n";
echo "4. The API_KEY/SECRET_KEY combination isn't properly configured in PayNinja dashboard\n\n";

echo "=== Next Steps ===\n";
echo "1. Verify in PayNinja dashboard that API_KEY and SECRET_KEY match exactly\n";
echo "2. Check if PayNinja has different credentials for different endpoints\n";
echo "3. Contact PayNinja support with:\n";
echo "   - API_KEY: " . API_KEY . "\n";
echo "   - Request timestamp\n";
echo "   - merchant_reference_id: PAYOUT_1762108744_2065dcec\n";
echo "   - Signature hash: " . $signatureHash . "\n";

