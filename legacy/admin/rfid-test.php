<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFID Scanner Test Page</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <style>
        .test-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
        }
        .test-section {
            margin: 2rem 0;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .test-results {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-success { background: #28a745; }
        .status-error { background: #dc3545; }
        .status-warning { background: #ffc107; }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>🧪 RFID Scanner Test & Debug Page</h1>
        <p>This page helps diagnose RFID scanning issues in the registration system.</p>
        
        <!-- Test Form -->
        <div class="test-section">
            <h3>📝 RFID Input Test</h3>
            <div class="form-group">
                <label for="rfid_tag">RFID Tag</label>
                <div class="rfid-input-group">
                    <input type="text" id="rfid_tag" name="rfid_tag" 
                           placeholder="Enter RFID tag or scan below"
                           autocomplete="new-password"
                           autocorrect="off"
                           autocapitalize="off"
                           spellcheck="false">
                    <button type="button" class="btn btn-primary btn-scan-rfid" 
                            data-rfid-scan data-rfid-target="rfid_tag"
                            title="Scan RFID tag using connected reader">
                        📡 Scan RFID
                    </button>
                </div>
            </div>
        </div>
        
        <!-- System Status -->
        <div class="test-section">
            <h3>🔍 System Status</h3>
            <div id="system-status">
                <div id="scanner-status">
                    <span class="status-indicator status-warning"></span>
                    Checking RFID Scanner...
                </div>
                <div id="endpoint-status">
                    <span class="status-indicator status-warning"></span>
                    Checking API Endpoints...
                </div>
                <div id="database-status">
                    <span class="status-indicator status-warning"></span>
                    Checking Database...
                </div>
            </div>
        </div>
        
        <!-- Manual Tests -->
        <div class="test-section">
            <h3>🔧 Manual Tests</h3>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <button onclick="testSimulateRFID()" class="btn btn-secondary">
                    🎯 Simulate RFID Scan
                </button>
                <button onclick="testEndpoints()" class="btn btn-secondary">
                    🌐 Test API Endpoints
                </button>
                <button onclick="testDatabase()" class="btn btn-secondary">
                    🗄️ Test Database
                </button>
                <button onclick="addTestRFID()" class="btn btn-secondary">
                    ➕ Add Test RFID to Queue
                </button>
                <button onclick="checkQueue()" class="btn btn-secondary">
                    📋 Check RFID Queue
                </button>
            </div>
        </div>
        
        <!-- Test Results -->
        <div class="test-section">
            <h3>📊 Test Results</h3>
            <div id="test-results" class="test-results">
                Click a test button to see results...
            </div>
        </div>
    </div>

    <script src="../assets/js/rfid-scanner.js"></script>
    <script>
        let testResults = '';
        
        function log(message) {
            const timestamp = new Date().toLocaleTimeString();
            testResults += `[${timestamp}] ${message}\n`;
            document.getElementById('test-results').textContent = testResults;
            console.log(message);
        }
        
        function updateStatus(elementId, status, message) {
            const element = document.getElementById(elementId);
            const indicator = element.querySelector('.status-indicator');
            indicator.className = `status-indicator status-${status}`;
            element.innerHTML = `<span class="status-indicator status-${status}"></span>${message}`;
        }
        
        // Initialize system checks
        window.addEventListener('load', function() {
            log('🚀 RFID Test Page Loaded');
            
            setTimeout(() => {
                checkSystemStatus();
            }, 1000);
        });
        
        async function checkSystemStatus() {
            log('🔍 Checking system status...');
            
            // Check RFID Scanner
            if (window.rfidScanner) {
                updateStatus('scanner-status', 'success', 'RFID Scanner initialized');
                log('✅ RFID Scanner object found');
            } else {
                updateStatus('scanner-status', 'error', 'RFID Scanner not found');
                log('❌ RFID Scanner object not found');
            }
            
            // Check target input
            const targetInput = document.getElementById('rfid_tag');
            if (targetInput) {
                log('✅ RFID input field found');
            } else {
                log('❌ RFID input field not found');
            }
            
            // Test endpoints
            await testEndpoints(false);
        }
        
        function testSimulateRFID() {
            log('🎯 Testing RFID simulation...');
            
            const testRFID = 'SIM' + Math.random().toString(36).substr(2, 8).toUpperCase();
            const targetInput = document.getElementById('rfid_tag');
            
            if (!targetInput) {
                log('❌ Target input not found');
                return;
            }
            
            if (!window.rfidScanner) {
                log('❌ RFID Scanner not initialized');
                return;
            }
            
            try {
                // Simulate the scanning process
                window.rfidScanner.targetInput = targetInput;
                window.rfidScanner.isScanning = true;
                window.rfidScanner.onRFIDScanned(testRFID);
                
                log(`✅ Simulated RFID scan: ${testRFID}`);
                log(`📝 Input field value: "${targetInput.value}"`);
                
                if (targetInput.value === testRFID) {
                    log('✅ RFID successfully populated in field');
                } else {
                    log('❌ RFID not populated correctly');
                }
                
            } catch (error) {
                log(`❌ Simulation failed: ${error.message}`);
            }
        }
        
        async function testEndpoints(showLogs = true) {
            if (showLogs) log('🌐 Testing API endpoints...');
            
            const endpoints = [
                '../api/rfid-poll-noauth.php',
                '../api/rfid-poll.php',
                '../api/rfid-test.php'
            ];
            
            let workingEndpoint = null;
            
            for (const endpoint of endpoints) {
                try {
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ last_tag: '', timeout: 100 })
                    });
                    
                    if (response.ok) {
                        const data = await response.json();
                        if (showLogs) log(`✅ ${endpoint}: Working (${response.status})`);
                        workingEndpoint = endpoint;
                    } else {
                        if (showLogs) log(`⚠️ ${endpoint}: HTTP ${response.status}`);
                    }
                } catch (error) {
                    if (showLogs) log(`❌ ${endpoint}: ${error.message}`);
                }
            }
            
            if (workingEndpoint) {
                updateStatus('endpoint-status', 'success', 'API Endpoints working');
            } else {
                updateStatus('endpoint-status', 'error', 'No working API endpoints');
            }
        }
        
        async function testDatabase() {
            log('🗄️ Testing database connectivity...');
            
            try {
                const response = await fetch('../api/rfid-test.php');
                const data = await response.json();
                
                if (data.error) {
                    log(`❌ Database error: ${data.error}`);
                    updateStatus('database-status', 'error', 'Database connection failed');
                } else {
                    log(`✅ Database working: ${data.queue_items} items in queue`);
                    updateStatus('database-status', 'success', 'Database connected');
                }
            } catch (error) {
                log(`❌ Database test failed: ${error.message}`);
                updateStatus('database-status', 'error', 'Database test failed');
            }
        }
        
        async function addTestRFID() {
            log('➕ Adding test RFID to queue...');
            
            try {
                const response = await fetch('../api/rfid-test.php', { method: 'POST' });
                const data = await response.json();
                
                if (data.success) {
                    log(`✅ Test RFID added: ${data.rfid}`);
                } else {
                    log(`❌ Failed to add test RFID: ${data.error}`);
                }
            } catch (error) {
                log(`❌ Add test RFID failed: ${error.message}`);
            }
        }
        
        async function checkQueue() {
            log('📋 Checking RFID queue...');
            
            try {
                const response = await fetch('../api/rfid-test.php');
                const data = await response.json();
                
                if (data.error) {
                    log(`❌ Queue check failed: ${data.error}`);
                } else {
                    log(`📊 Queue has ${data.queue_items} items:`);
                    data.items.forEach((item, index) => {
                        log(`   ${index + 1}. ${item.tag_value} (${item.source}) - ${item.created_at}`);
                    });
                }
            } catch (error) {
                log(`❌ Queue check failed: ${error.message}`);
            }
        }
        
        // Monitor input changes
        document.getElementById('rfid_tag').addEventListener('input', function(e) {
            log(`📝 Input changed: "${e.target.value}"`);
        });
    </script>
</body>
</html>
