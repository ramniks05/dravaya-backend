<?php
/**
 * Backend API Status Page
 * This page helps verify that the backend is running correctly
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayNinja Payout API - Backend Status</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 800px;
            width: 100%;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .header h1 {
            color: #667eea;
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .status-badge {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .status-badge.error {
            background: #ef4444;
        }
        
        .info-section {
            margin: 30px 0;
        }
        
        .info-section h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.5em;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        
        .info-card h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        
        .info-card p {
            color: #666;
            word-break: break-word;
        }
        
        .endpoints {
            list-style: none;
            margin-top: 15px;
        }
        
        .endpoints li {
            background: #f8f9fa;
            padding: 12px;
            margin: 8px 0;
            border-radius: 8px;
            border-left: 3px solid #10b981;
        }
        
        .endpoints li strong {
            color: #667eea;
            display: block;
            margin-bottom: 5px;
        }
        
        .endpoints li code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.9em;
            color: #d63384;
        }
        
        .test-section {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .test-button {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            margin: 5px;
            transition: background 0.3s;
        }
        
        .test-button:hover {
            background: #5568d3;
        }
        
        .result {
            margin-top: 15px;
            padding: 15px;
            border-radius: 8px;
            display: none;
        }
        
        .result.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        
        .result.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        
        pre {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            margin-top: 10px;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 PayNinja Payout API</h1>
            <p>Backend Service Status</p>
            <div class="status-badge" id="statusBadge">✅ Running</div>
        </div>

        <div class="info-section">
            <h2>📋 System Information</h2>
            <div class="info-grid">
                <div class="info-card">
                    <h3>PHP Version</h3>
                    <p><?php echo PHP_VERSION; ?></p>
                </div>
                <div class="info-card">
                    <h3>Server Software</h3>
                    <p><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
                </div>
                <div class="info-card">
                    <h3>Server Time</h3>
                    <p><?php echo date('Y-m-d H:i:s'); ?></p>
                </div>
                <div class="info-card">
                    <h3>Document Root</h3>
                    <p><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'N/A'; ?></p>
                </div>
            </div>
        </div>

        <div class="info-section">
            <h2>🔌 API Endpoints</h2>
            <ul class="endpoints">
                <li>
                    <strong>GET /api/payout/balance.php</strong>
                    <span>Get account balance</span>
                </li>
                <li>
                    <strong>POST /api/payout/initiate.php</strong>
                    <span>Initiate fund transfer (UPI/IMPS/NEFT)</span>
                </li>
                <li>
                    <strong>POST /api/payout/status.php</strong>
                    <span>Check transaction status</span>
                </li>
                <li>
                    <strong>GET /api/admin/vendors.php</strong>
                    <span>Get all vendors (with filters)</span>
                </li>
                <li>
                    <strong>GET /api/admin/vendors/pending.php</strong>
                    <span>Get pending vendors for approval</span>
                </li>
                <li>
                    <strong>POST /api/admin/vendors/approve.php</strong>
                    <span>Approve a vendor</span>
                </li>
                <li>
                    <strong>GET /api/admin/vendors/stats.php</strong>
                    <span>Get vendor statistics</span>
                </li>
            </ul>
        </div>

        <div class="info-section">
            <h2>✅ Configuration Check</h2>
            <?php
            // Set flag to prevent CORS headers
            $isIndexPage = true;
            ?>
            <div class="info-grid">
                <div class="info-card">
                    <h3>Config File</h3>
                    <p><?php echo file_exists(__DIR__ . '/config.php') ? '✅ Found' : '❌ Missing'; ?></p>
                </div>
                <div class="info-card">
                    <h3>Environment File</h3>
                    <p><?php echo file_exists(__DIR__ . '/.env') ? '✅ Found' : '⚠️ Not found (using defaults)'; ?></p>
                </div>
                <div class="info-card">
                    <h3>Logs Directory</h3>
                    <p><?php 
                        $logDir = __DIR__ . '/logs';
                        if (is_dir($logDir) || mkdir($logDir, 0755, true)) {
                            echo '✅ Available';
                        } else {
                            echo '❌ Cannot create';
                        }
                    ?></p>
                </div>
                <div class="info-card">
                    <h3>cURL Extension</h3>
                    <p><?php echo extension_loaded('curl') ? '✅ Enabled' : '❌ Disabled'; ?></p>
                </div>
                <div class="info-card">
                    <h3>OpenSSL Extension</h3>
                    <p><?php echo extension_loaded('openssl') ? '✅ Enabled' : '❌ Disabled'; ?></p>
                </div>
                <div class="info-card">
                    <h3>JSON Extension</h3>
                    <p><?php echo extension_loaded('json') ? '✅ Enabled' : '❌ Disabled'; ?></p>
                </div>
                <div class="info-card">
                    <h3>MySQLi Extension</h3>
                    <p><?php echo extension_loaded('mysqli') ? '✅ Enabled' : '❌ Disabled'; ?></p>
                </div>
            </div>
        </div>

        <div class="info-section">
            <h2>🗄️ Database Configuration</h2>
            <div class="info-grid">
                <?php
                require_once __DIR__ . '/config.php';
                require_once __DIR__ . '/database/functions.php';
                $dbTest = testDatabaseConnection();
                ?>
                <div class="info-card">
                    <h3>Database Connection</h3>
                    <p><?php 
                        if ($dbTest['success']) {
                            echo '✅ ' . $dbTest['message'];
                        } else {
                            echo '❌ ' . $dbTest['message'];
                        }
                    ?></p>
                </div>
                <div class="info-card">
                    <h3>Database Name</h3>
                    <p><?php echo DB_NAME; ?></p>
                </div>
                <div class="info-card">
                    <h3>Database Host</h3>
                    <p><?php echo DB_HOST; ?></p>
                </div>
                <div class="info-card">
                    <h3>Database User</h3>
                    <p><?php echo DB_USER; ?></p>
                </div>
                <?php
                if ($dbTest['success']) {
                    try {
                        $conn = getDBConnection();
                        
                        // Check if tables exist
                        $tables = ['transactions', 'transaction_logs', 'balance_history', 'beneficiaries'];
                        echo '<div class="info-card"><h3>Database Tables</h3><ul style="list-style: none; padding: 0; margin: 0;">';
                        foreach ($tables as $table) {
                            $result = $conn->query("SHOW TABLES LIKE '$table'");
                            $exists = $result && $result->num_rows > 0;
                            echo '<li style="padding: 5px 0;">' . ($exists ? '✅' : '⚠️') . ' ' . $table . '</li>';
                        }
                        echo '</ul></div>';
                        
                        // Get transaction stats if transactions table exists
                        $result = $conn->query("SHOW TABLES LIKE 'transactions'");
                        if ($result && $result->num_rows > 0) {
                            $stats = getTransactionStats();
                            if ($stats) {
                                echo '<div class="info-card"><h3>Transaction Statistics</h3>';
                                echo '<p>Total: ' . ($stats['total_transactions'] ?? 0) . '<br>';
                                echo 'Success: ' . ($stats['successful'] ?? 0) . '<br>';
                                echo 'Failed: ' . ($stats['failed'] ?? 0) . '<br>';
                                echo 'Pending: ' . ($stats['pending'] ?? 0) . '</p></div>';
                            }
                        }
                    } catch (Exception $e) {
                        // Silently fail - already showed connection status
                    }
                }
                ?>
            </div>
        </div>

        <div class="test-section">
            <h2>🧪 Test API Endpoints</h2>
            
            <div style="margin-bottom: 20px;">
                <h3 style="color: #667eea; margin-bottom: 10px;">Balance API</h3>
                <p style="margin-bottom: 10px;">Click the button below to test the balance endpoint:</p>
                <button class="test-button" onclick="testBalance()">Test Balance API</button>
                <div id="testResult" class="result"></div>
            </div>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e9ecef;">
                <h3 style="color: #667eea; margin-bottom: 15px;">Fund Transfer API (Payment Initiate)</h3>
                <p style="margin-bottom: 15px;">Test fund transfer with dummy data matching PayNinja format. Select transfer type:</p>
                
                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px;">
                    <button class="test-button" onclick="testFundTransfer('UPI')" style="background: #10b981;">Test UPI Transfer</button>
                    <button class="test-button" onclick="testFundTransfer('IMPS')" style="background: #f59e0b;">Test IMPS Transfer</button>
                    <button class="test-button" onclick="testFundTransfer('NEFT')" style="background: #3b82f6;">Test NEFT Transfer</button>
                </div>

                <div id="transferResult" class="result"></div>
                
                <div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <strong style="color: #667eea;">Dummy Data Used (PayNinja Format):</strong>
                    <pre id="dummyDataDisplay" style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 8px; margin-top: 10px; overflow-x: auto; font-size: 0.85em;"></pre>
                </div>
                
                <!-- Transaction Status Check -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e9ecef;">
                    <h3 style="color: #667eea; margin-bottom: 15px;">Transaction Status Check</h3>
                    <p style="margin-bottom: 15px;">Check the status of a transaction by merchant reference ID:</p>
                    <div style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
                        <input 
                            type="text" 
                            id="statusCheckRefId" 
                            placeholder="Enter Merchant Reference ID (e.g., TEST_UPI_1234567890)"
                            style="flex: 1; min-width: 250px; padding: 10px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 0.9em;"
                        >
                        <button class="test-button" onclick="testTransactionStatus()">Check Status</button>
                    </div>
                    <div id="statusResult" class="result"></div>
                </div>
            </div>
        </div>

        <div class="test-section">
            <h2>👥 Vendor Management API Tests</h2>
            
            <div style="margin-bottom: 20px;">
                <h3 style="color: #667eea; margin-bottom: 10px;">Vendor Statistics</h3>
                <p style="margin-bottom: 10px;">Get counts of vendors by status:</p>
                <button class="test-button" onclick="testVendorStats()" style="background: #8b5cf6;">Get Vendor Stats</button>
                <div id="vendorStatsResult" class="result"></div>
            </div>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e9ecef;">
                <h3 style="color: #667eea; margin-bottom: 15px;">Pending Vendors (Approval Queue)</h3>
                <p style="margin-bottom: 15px;">Fetch all vendors waiting for approval:</p>
                <button class="test-button" onclick="testPendingVendors()" style="background: #f59e0b;">Get Pending Vendors</button>
                <div id="pendingVendorsResult" class="result"></div>
            </div>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e9ecef;">
                <h3 style="color: #667eea; margin-bottom: 15px;">All Vendors</h3>
                <p style="margin-bottom: 15px;">Get all vendors (with optional status filter):</p>
                <div style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; align-items: center;">
                    <select id="vendorStatusFilter" style="padding: 10px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 0.9em;">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>
                    <button class="test-button" onclick="testAllVendors()" style="background: #10b981;">Get Vendors</button>
                </div>
                <div id="allVendorsResult" class="result"></div>
            </div>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e9ecef;">
                <h3 style="color: #667eea; margin-bottom: 15px;">Approve Vendor</h3>
                <p style="margin-bottom: 15px;">Approve a vendor (enter vendor ID):</p>
                <div style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
                    <input 
                        type="text" 
                        id="approveVendorId" 
                        placeholder="Enter Vendor ID (UUID)"
                        style="flex: 1; min-width: 250px; padding: 10px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 0.9em;"
                    >
                    <button class="test-button" onclick="testApproveVendor()" style="background: #3b82f6;">Approve Vendor</button>
                </div>
                <div id="approveVendorResult" class="result"></div>
            </div>
        </div>

        <div class="footer">
            <p>Backend API is running successfully! 🎉</p>
            <p style="margin-top: 10px; font-size: 0.9em;">
                API Documentation: <a href="API_DOCUMENTATION.md" style="color: #667eea;">API_DOCUMENTATION.md</a>
            </p>
        </div>
    </div>

    <script>
        async function testBalance() {
            const resultDiv = document.getElementById('testResult');
            resultDiv.style.display = 'block';
            resultDiv.className = 'result';
            resultDiv.innerHTML = '<p>Testing...</p>';

            try {
                const response = await fetch('api/payout/balance.php');
                const data = await response.json();

                if (response.ok) {
                    resultDiv.className = 'result success';
                    resultDiv.innerHTML = `
                        <strong>✅ Success!</strong>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    `;
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = `
                        <strong>❌ Error:</strong>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    `;
                }
            } catch (error) {
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `
                    <strong>❌ Network Error:</strong>
                    <p>${error.message}</p>
                    <p style="margin-top: 10px; font-size: 0.9em;">
                        Make sure the API endpoint is accessible and CORS is properly configured.
                    </p>
                `;
            }
        }

        async function testFundTransfer(transferType) {
            const resultDiv = document.getElementById('transferResult');
            const dummyDataDiv = document.getElementById('dummyDataDisplay');
            resultDiv.style.display = 'block';
            resultDiv.className = 'result';
            resultDiv.innerHTML = '<p>Testing ' + transferType + ' transfer...</p>';

            // Generate unique merchant reference ID
            const merchantRefId = 'TEST_' + transferType + '_' + Date.now();

            // Dummy data matching PayNinja documentation format
            let dummyData = {
                ben_name: "Gaurav Kumar",
                ben_phone_number: "9876543210",
                amount: "100",
                merchant_reference_id: merchantRefId,
                transfer_type: transferType,
                narration: "PAYNINJA Fund Transfer"
            };

            if (transferType === 'UPI') {
                // UPI format as per PayNinja docs
                dummyData.ben_vpa_address = "gauravkumar@exampleupi";
            } else {
                // IMPS/NEFT format as per PayNinja docs
                dummyData.ben_account_number = "1121431121541121";
                dummyData.ben_ifsc = "HDFC0001234";
                dummyData.ben_bank_name = "hdfc";  // lowercase as per docs
            }

            // Display dummy data
            dummyDataDiv.textContent = JSON.stringify(dummyData, null, 2);

            try {
                const response = await fetch('api/payout/initiate.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(dummyData)
                });

                const data = await response.json();

                if (response.ok || response.status === 200) {
                    resultDiv.className = 'result success';
                    resultDiv.innerHTML = `
                        <strong>✅ Transfer Initiated Successfully!</strong>
                        <p style="margin-top: 10px;"><strong>Merchant Reference ID:</strong> ${merchantRefId}</p>
                        <pre style="margin-top: 10px;">${JSON.stringify(data, null, 2)}</pre>
                        <div style="margin-top: 15px; padding: 10px; background: #d1fae5; border-radius: 5px; font-size: 0.9em;">
                            <strong>💡 Note:</strong> This is a test transaction. Use the merchant reference ID above to check transaction status using the Status Check section below.
                        </div>
                        <div style="margin-top: 10px; padding: 10px; background: #dbeafe; border-radius: 5px; font-size: 0.9em;">
                            <strong>📋 Copy Reference ID:</strong> <code style="background: #e0e7ff; padding: 2px 6px; border-radius: 4px; cursor: pointer;" onclick="copyToClipboard('${merchantRefId}')" title="Click to copy">${merchantRefId}</code>
                        </div>
                    `;
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = `
                        <strong>❌ Error:</strong>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                        <div style="margin-top: 15px; padding: 10px; background: #fee2e2; border-radius: 5px; font-size: 0.9em;">
                            <strong>Note:</strong> This might be a PayNinja API error or validation error. Check the error message above.
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `
                    <strong>❌ Network Error:</strong>
                    <p>${error.message}</p>
                    <p style="margin-top: 10px; font-size: 0.9em;">
                        Make sure the API endpoint is accessible and CORS is properly configured.
                    </p>
                `;
            }
        }

        async function testTransactionStatus() {
            const resultDiv = document.getElementById('statusResult');
            const refIdInput = document.getElementById('statusCheckRefId');
            const merchantRefId = refIdInput.value.trim();
            
            if (!merchantRefId) {
                resultDiv.style.display = 'block';
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `
                    <strong>❌ Error:</strong>
                    <p>Please enter a merchant reference ID</p>
                `;
                return;
            }
            
            resultDiv.style.display = 'block';
            resultDiv.className = 'result';
            resultDiv.innerHTML = '<p>Checking transaction status...</p>';

            try {
                const response = await fetch('api/payout/status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        merchant_reference_id: merchantRefId
                    })
                });

                const data = await response.json();

                if (response.ok || response.status === 200) {
                    resultDiv.className = 'result success';
                    resultDiv.innerHTML = `
                        <strong>✅ Status Retrieved Successfully!</strong>
                        <pre style="margin-top: 10px;">${JSON.stringify(data, null, 2)}</pre>
                        ${data.data && data.data.status ? `
                            <div style="margin-top: 15px; padding: 10px; background: #d1fae5; border-radius: 5px; font-size: 0.9em;">
                                <strong>Status:</strong> <span style="font-weight: bold; color: #059669;">${data.data.status.toUpperCase()}</span>
                            </div>
                        ` : ''}
                    `;
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = `
                        <strong>❌ Error:</strong>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    `;
                }
            } catch (error) {
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `
                    <strong>❌ Network Error:</strong>
                    <p>${error.message}</p>
                    <p style="margin-top: 10px; font-size: 0.9em;">
                        Make sure the API endpoint is accessible and CORS is properly configured.
                    </p>
                `;
            }
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Show temporary feedback
                const notification = document.createElement('div');
                notification.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 10px 20px; border-radius: 8px; z-index: 1000; box-shadow: 0 4px 6px rgba(0,0,0,0.1);';
                notification.textContent = '✓ Copied to clipboard!';
                document.body.appendChild(notification);
                setTimeout(() => notification.remove(), 2000);
            });
        }

        async function testVendorStats() {
            const resultDiv = document.getElementById('vendorStatsResult');
            resultDiv.style.display = 'block';
            resultDiv.className = 'result';
            resultDiv.innerHTML = '<p>Fetching vendor statistics...</p>';

            try {
                const response = await fetch('api/admin/vendors/stats.php');
                const data = await response.json();

                if (response.ok) {
                    resultDiv.className = 'result success';
                    resultDiv.innerHTML = `
                        <strong>✅ Success!</strong>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                        ${data.data && data.data.statistics ? `
                            <div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                                    <div style="text-align: center; padding: 10px; background: white; border-radius: 8px;">
                                        <div style="font-size: 2em; font-weight: bold; color: #f59e0b;">${data.data.statistics.pending}</div>
                                        <div style="color: #6b7280; margin-top: 5px;">Pending</div>
                                    </div>
                                    <div style="text-align: center; padding: 10px; background: white; border-radius: 8px;">
                                        <div style="font-size: 2em; font-weight: bold; color: #10b981;">${data.data.statistics.active}</div>
                                        <div style="color: #6b7280; margin-top: 5px;">Active</div>
                                    </div>
                                    <div style="text-align: center; padding: 10px; background: white; border-radius: 8px;">
                                        <div style="font-size: 2em; font-weight: bold; color: #ef4444;">${data.data.statistics.suspended}</div>
                                        <div style="color: #6b7280; margin-top: 5px;">Suspended</div>
                                    </div>
                                    <div style="text-align: center; padding: 10px; background: white; border-radius: 8px;">
                                        <div style="font-size: 2em; font-weight: bold; color: #667eea;">${data.data.statistics.total}</div>
                                        <div style="color: #6b7280; margin-top: 5px;">Total</div>
                                    </div>
                                </div>
                            </div>
                        ` : ''}
                    `;
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = `
                        <strong>❌ Error:</strong>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    `;
                }
            } catch (error) {
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `
                    <strong>❌ Network Error:</strong>
                    <p>${error.message}</p>
                `;
            }
        }

        async function testPendingVendors() {
            const resultDiv = document.getElementById('pendingVendorsResult');
            resultDiv.style.display = 'block';
            resultDiv.className = 'result';
            resultDiv.innerHTML = '<p>Fetching pending vendors...</p>';

            try {
                const response = await fetch('api/admin/vendors/pending.php');
                const data = await response.json();

                if (response.ok) {
                    resultDiv.className = 'result success';
                    const vendors = data.data.vendors || [];
                    const vendorList = vendors.map(v => `
                        <tr>
                            <td><code style="font-size: 0.85em;">${v.id}</code></td>
                            <td>${v.email}</td>
                            <td>${new Date(v.created_at).toLocaleString()}</td>
                            <td><span style="padding: 4px 8px; background: #fef3c7; color: #92400e; border-radius: 4px; font-size: 0.85em;">${v.status}</span></td>
                            <td><button onclick="copyToClipboard('${v.id}')" style="padding: 4px 12px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85em;">Copy ID</button></td>
                        </tr>
                    `).join('');

                    resultDiv.innerHTML = `
                        <strong>✅ Success! Found ${vendors.length} pending vendor(s)</strong>
                        ${vendors.length > 0 ? `
                            <div style="margin-top: 15px; overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse; font-size: 0.9em;">
                                    <thead>
                                        <tr style="background: #f3f4f6; border-bottom: 2px solid #e5e7eb;">
                                            <th style="padding: 10px; text-align: left;">ID</th>
                                            <th style="padding: 10px; text-align: left;">Email</th>
                                            <th style="padding: 10px; text-align: left;">Created At</th>
                                            <th style="padding: 10px; text-align: left;">Status</th>
                                            <th style="padding: 10px; text-align: left;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${vendorList}
                                    </tbody>
                                </table>
                            </div>
                            <pre style="margin-top: 15px; display: none;" id="pendingVendorsRaw">${JSON.stringify(data, null, 2)}</pre>
                            <button onclick="document.getElementById('pendingVendorsRaw').style.display = document.getElementById('pendingVendorsRaw').style.display === 'none' ? 'block' : 'none';" style="margin-top: 10px; padding: 6px 12px; background: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85em;">Toggle Raw JSON</button>
                        ` : `
                            <p style="margin-top: 10px; padding: 15px; background: #f0fdf4; border-radius: 8px; color: #166534;">
                                No pending vendors found. All vendors are approved or there are no vendors yet.
                            </p>
                            <pre style="margin-top: 15px;">${JSON.stringify(data, null, 2)}</pre>
                        `}
                    `;
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = `
                        <strong>❌ Error:</strong>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    `;
                }
            } catch (error) {
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `
                    <strong>❌ Network Error:</strong>
                    <p>${error.message}</p>
                `;
            }
        }

        async function testAllVendors() {
            const resultDiv = document.getElementById('allVendorsResult');
            const statusFilter = document.getElementById('vendorStatusFilter').value;
            resultDiv.style.display = 'block';
            resultDiv.className = 'result';
            resultDiv.innerHTML = '<p>Fetching vendors...</p>';

            try {
                let url = 'api/admin/vendors.php';
                if (statusFilter) {
                    url += `?status=${statusFilter}`;
                }

                const response = await fetch(url);
                const data = await response.json();

                if (response.ok) {
                    resultDiv.className = 'result success';
                    const vendors = data.data.vendors || [];
                    const pagination = data.data.pagination || {};
                    const vendorList = vendors.map(v => `
                        <tr>
                            <td><code style="font-size: 0.85em;">${v.id}</code></td>
                            <td>${v.email}</td>
                            <td>${new Date(v.created_at).toLocaleString()}</td>
                            <td>
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 0.85em; ${
                                    v.status === 'active' ? 'background: #d1fae5; color: #065f46;' :
                                    v.status === 'pending' ? 'background: #fef3c7; color: #92400e;' :
                                    'background: #fee2e2; color: #991b1b;'
                                }">${v.status}</span>
                            </td>
                            <td><button onclick="copyToClipboard('${v.id}')" style="padding: 4px 12px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85em;">Copy ID</button></td>
                        </tr>
                    `).join('');

                    resultDiv.innerHTML = `
                        <strong>✅ Success! Found ${pagination.total} vendor(s)</strong>
                        ${pagination.total > 0 ? `
                            <div style="margin-top: 10px; padding: 10px; background: #eff6ff; border-radius: 8px; font-size: 0.9em;">
                                Showing page ${pagination.page} of ${pagination.total_pages} (${vendors.length} vendors on this page)
                            </div>
                            <div style="margin-top: 15px; overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse; font-size: 0.9em;">
                                    <thead>
                                        <tr style="background: #f3f4f6; border-bottom: 2px solid #e5e7eb;">
                                            <th style="padding: 10px; text-align: left;">ID</th>
                                            <th style="padding: 10px; text-align: left;">Email</th>
                                            <th style="padding: 10px; text-align: left;">Created At</th>
                                            <th style="padding: 10px; text-align: left;">Status</th>
                                            <th style="padding: 10px; text-align: left;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${vendorList}
                                    </tbody>
                                </table>
                            </div>
                            <pre style="margin-top: 15px; display: none;" id="allVendorsRaw">${JSON.stringify(data, null, 2)}</pre>
                            <button onclick="document.getElementById('allVendorsRaw').style.display = document.getElementById('allVendorsRaw').style.display === 'none' ? 'block' : 'none';" style="margin-top: 10px; padding: 6px 12px; background: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85em;">Toggle Raw JSON</button>
                        ` : `
                            <p style="margin-top: 10px; padding: 15px; background: #f0fdf4; border-radius: 8px; color: #166534;">
                                No vendors found${statusFilter ? ` with status: ${statusFilter}` : ''}.
                            </p>
                            <pre style="margin-top: 15px;">${JSON.stringify(data, null, 2)}</pre>
                        `}
                    `;
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = `
                        <strong>❌ Error:</strong>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    `;
                }
            } catch (error) {
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `
                    <strong>❌ Network Error:</strong>
                    <p>${error.message}</p>
                `;
            }
        }

        async function testApproveVendor() {
            const resultDiv = document.getElementById('approveVendorResult');
            const vendorIdInput = document.getElementById('approveVendorId');
            const vendorId = vendorIdInput.value.trim();
            
            if (!vendorId) {
                resultDiv.style.display = 'block';
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `
                    <strong>❌ Error:</strong>
                    <p>Please enter a vendor ID</p>
                    <p style="margin-top: 10px; font-size: 0.9em; color: #6b7280;">
                        Tip: Use "Get Pending Vendors" above to get vendor IDs, then click "Copy ID" to copy the UUID.
                    </p>
                `;
                return;
            }
            
            resultDiv.style.display = 'block';
            resultDiv.className = 'result';
            resultDiv.innerHTML = '<p>Approving vendor...</p>';

            try {
                const response = await fetch('api/admin/vendors/approve.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        vendor_id: vendorId
                    })
                });

                const data = await response.json();

                if (response.ok) {
                    resultDiv.className = 'result success';
                    resultDiv.innerHTML = `
                        <strong>✅ Vendor Approved Successfully!</strong>
                        <pre style="margin-top: 10px;">${JSON.stringify(data, null, 2)}</pre>
                        ${data.data && data.data.vendor ? `
                            <div style="margin-top: 15px; padding: 15px; background: #d1fae5; border-radius: 8px;">
                                <strong>Vendor Details:</strong>
                                <ul style="margin-top: 10px; padding-left: 20px;">
                                    <li><strong>Email:</strong> ${data.data.vendor.email}</li>
                                    <li><strong>Status:</strong> <span style="padding: 4px 8px; background: #10b981; color: white; border-radius: 4px; font-size: 0.85em;">${data.data.vendor.status}</span></li>
                                    <li><strong>Previous Status:</strong> ${data.data.vendor.previous_status}</li>
                                </ul>
                            </div>
                        ` : ''}
                        <div style="margin-top: 15px; padding: 10px; background: #dbeafe; border-radius: 8px; font-size: 0.9em;">
                            <strong>💡 Next Steps:</strong>
                            <ul style="margin-top: 5px; padding-left: 20px;">
                                <li>Click "Get Pending Vendors" again to see updated list</li>
                                <li>The vendor can now log in to their account</li>
                            </ul>
                        </div>
                    `;
                    // Clear the input field
                    vendorIdInput.value = '';
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = `
                        <strong>❌ Error:</strong>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    `;
                }
            } catch (error) {
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `
                    <strong>❌ Network Error:</strong>
                    <p>${error.message}</p>
                `;
            }
        }

        // Check if page loaded successfully
        window.addEventListener('load', function() {
            console.log('Backend API Status Page loaded successfully');
        });
    </script>
</body>
</html>

