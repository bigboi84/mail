<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// We will store these settings in the brand_config JSON
$brand_config = json_decode($brand->brand_config, true) ?: [];
$delivery = isset($brand_config['delivery']) ? $brand_config['delivery'] : [
    'splash_method' => 'system',
    'bulk_method' => 'domain',
    'smtp_provider' => 'sendgrid',
    'smtp_key' => '',
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_user' => '',
    'smtp_pass' => ''
];

// Dynamically generate their System Email
$brand_slug = sanitize_title($brand->brand_name);
if (empty($brand_slug)) $brand_slug = 'rewards';
$system_email = $brand_slug . '@mailtoucan.pro';
?>

<header class="mb-8 flex justify-between items-end">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Delivery Routing</h1>
        <p class="text-gray-500 text-sm">Configure how your Transactional and Bulk marketing emails are sent.</p>
    </div>
    <button onclick="saveDeliverySettings()" id="btn_save_delivery" class="bg-indigo-600 text-white px-6 py-2.5 rounded-lg font-bold shadow-md hover:bg-indigo-700 transition flex items-center gap-2">
        <i class="fa-solid fa-floppy-disk"></i> Save Settings
    </button>
</header>

<div class="grid grid-cols-2 gap-8 mb-12">
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-5 border-b bg-gray-50 flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center text-lg"><i class="fa-solid fa-bolt"></i></div>
            <div>
                <h2 class="font-bold text-gray-900">Transactional Engine</h2>
                <p class="text-xs text-gray-500">For WiFi Splash Pages & Autoresponders</p>
            </div>
        </div>
        <div class="p-6 space-y-4">
            <label class="flex items-start gap-4 p-4 border rounded-lg cursor-pointer hover:bg-gray-50 <?php echo $delivery['splash_method'] == 'system' ? 'border-blue-500 bg-blue-50' : ''; ?>">
                <input type="radio" name="splash_method" value="system" class="mt-1" <?php echo $delivery['splash_method'] == 'system' ? 'checked' : ''; ?>>
                <div>
                    <p class="font-bold text-sm text-gray-900">System Branded (Free)</p>
                    <p class="text-xs text-gray-500">Sent from <span class="font-mono text-indigo-600 bg-indigo-50 px-1 rounded"><?php echo esc_html($system_email); ?></span></p>
                </div>
            </label>
            <label class="flex items-start gap-4 p-4 border rounded-lg cursor-pointer hover:bg-gray-50 <?php echo $delivery['splash_method'] == 'google' ? 'border-blue-500 bg-blue-50' : ''; ?>">
                <input type="radio" name="splash_method" value="google" class="mt-1" <?php echo $delivery['splash_method'] == 'google' ? 'checked' : ''; ?>>
                <div class="w-full">
                    <div class="flex justify-between">
                        <p class="font-bold text-sm text-gray-900">Google Workspace / Gmail</p>
                        <span class="text-[10px] bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded font-bold">Low Volume</span>
                    </div>
                    <p class="text-xs text-gray-500 mb-2">Send directly from your connected Gmail account.</p>
                    <button class="text-xs bg-gray-900 text-white px-3 py-1.5 rounded font-bold hover:bg-gray-800 transition"><i class="fa-brands fa-google mr-1"></i> Connect Account</button>
                </div>
            </label>
            <label class="flex items-start gap-4 p-4 border rounded-lg cursor-pointer hover:bg-gray-50 <?php echo $delivery['splash_method'] == 'domain' ? 'border-blue-500 bg-blue-50' : ''; ?>">
                <input type="radio" name="splash_method" value="domain" class="mt-1" <?php echo $delivery['splash_method'] == 'domain' ? 'checked' : ''; ?>>
                <div>
                    <p class="font-bold text-sm text-gray-900">Authenticated Domain</p>
                    <p class="text-xs text-gray-500">Send using the domains you verified in Core Setup.</p>
                </div>
            </label>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-5 border-b bg-gray-50 flex items-center gap-3">
            <div class="w-10 h-10 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center text-lg"><i class="fa-solid fa-users"></i></div>
            <div>
                <h2 class="font-bold text-gray-900">Bulk Broadcast Engine</h2>
                <p class="text-xs text-gray-500">For Newsletters & Mass Campaigns</p>
            </div>
        </div>
        <div class="p-6 space-y-4">
            
            <div class="bg-red-50 p-3 rounded border border-red-100 flex gap-2 mb-2">
                <i class="fa-solid fa-shield-halved text-red-500 mt-0.5"></i>
                <p class="text-xs text-red-700 font-medium">To protect sender reputation, <strong>System</strong> and <strong>Gmail</strong> routing are disabled for bulk sending.</p>
            </div>

            <label class="flex items-start gap-4 p-4 border rounded-lg cursor-pointer hover:bg-gray-50 <?php echo $delivery['bulk_method'] == 'domain' ? 'border-purple-500 bg-purple-50' : ''; ?>">
                <input type="radio" name="bulk_method" value="domain" class="mt-1" <?php echo $delivery['bulk_method'] == 'domain' ? 'checked' : ''; ?>>
                <div>
                    <p class="font-bold text-sm text-gray-900">Authenticated Domain (Native)</p>
                    <p class="text-xs text-gray-500">Use our high-deliverability internal network.</p>
                </div>
            </label>
            
            <label class="flex items-start gap-4 p-4 border rounded-lg cursor-pointer hover:bg-gray-50 <?php echo $delivery['bulk_method'] == 'api' ? 'border-purple-500 bg-purple-50' : ''; ?>">
                <input type="radio" name="bulk_method" value="api" class="mt-1" onchange="document.getElementById('external_api_box').classList.remove('hidden')" <?php echo $delivery['bulk_method'] == 'api' ? 'checked' : ''; ?>>
                <div class="w-full">
                    <p class="font-bold text-sm text-gray-900">External API / Custom SMTP</p>
                    <p class="text-xs text-gray-500">Bring your own server (SendGrid, Mailgun, etc.)</p>
                </div>
            </label>

            <div id="external_api_box" class="bg-gray-50 p-4 border rounded-lg mt-2 transition-all <?php echo $delivery['bulk_method'] == 'api' ? '' : 'hidden'; ?>">
                <div class="flex justify-between items-end mb-3">
                    <label class="block text-[10px] uppercase font-bold text-gray-500">Select Provider</label>
                    <button type="button" onclick="openSmtpGuide()" class="text-xs font-bold text-indigo-600 hover:underline"><i class="fa-solid fa-circle-info mr-1"></i>Setup Guide</button>
                </div>
                
                <select id="smtp_provider" onchange="toggleSmtpFields()" class="w-full p-2 border border-gray-300 rounded text-sm mb-4 outline-none font-bold text-gray-700 bg-white shadow-sm">
                    <option value="sendgrid" <?php echo $delivery['smtp_provider'] == 'sendgrid' ? 'selected' : ''; ?>>SendGrid API</option>
                    <option value="mailgun" <?php echo $delivery['smtp_provider'] == 'mailgun' ? 'selected' : ''; ?>>Mailgun API</option>
                    <option value="postmark" <?php echo $delivery['smtp_provider'] == 'postmark' ? 'selected' : ''; ?>>Postmark API</option>
                    <option value="brevo" <?php echo $delivery['smtp_provider'] == 'brevo' ? 'selected' : ''; ?>>Brevo (Sendinblue) API</option>
                    <option value="ses" <?php echo $delivery['smtp_provider'] == 'ses' ? 'selected' : ''; ?>>Amazon SES API</option>
                    <option value="custom" <?php echo $delivery['smtp_provider'] == 'custom' ? 'selected' : ''; ?>>Other / Standard SMTP</option>
                </select>

                <div id="api_key_box" class="<?php echo $delivery['smtp_provider'] !== 'custom' ? '' : 'hidden'; ?>">
                    <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1" id="lbl_api_key">Provider API Key</label>
                    <input type="password" id="smtp_key" value="<?php echo esc_attr($delivery['smtp_key']); ?>" class="w-full p-2 border border-gray-300 rounded text-sm outline-none focus:ring-2 focus:ring-purple-100 shadow-inner" placeholder="Enter your secret API key">
                </div>

                <div id="full_smtp_box" class="space-y-3 <?php echo $delivery['smtp_provider'] === 'custom' ? '' : 'hidden'; ?>">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2">
                            <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1">SMTP Host</label>
                            <input type="text" id="smtp_host" value="<?php echo esc_attr($delivery['smtp_host']); ?>" class="w-full p-2 border border-gray-300 rounded text-sm outline-none shadow-inner" placeholder="e.g. smtp.mail.com">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1">Port</label>
                            <input type="text" id="smtp_port" value="<?php echo esc_attr($delivery['smtp_port']); ?>" class="w-full p-2 border border-gray-300 rounded text-sm outline-none shadow-inner" placeholder="587">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1">SMTP Username</label>
                        <input type="text" id="smtp_user" value="<?php echo esc_attr($delivery['smtp_user']); ?>" class="w-full p-2 border border-gray-300 rounded text-sm outline-none shadow-inner" placeholder="Username or Email">
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1">SMTP Password</label>
                        <input type="password" id="smtp_pass" value="<?php echo esc_attr($delivery['smtp_pass']); ?>" class="w-full p-2 border border-gray-300 rounded text-sm outline-none shadow-inner" placeholder="Password">
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-gray-200">
                    <button type="button" id="btn_test_conn" onclick="testConnection()" class="w-full bg-gray-800 text-white py-2 rounded text-sm font-bold shadow-sm hover:bg-gray-700 transition flex items-center justify-center gap-2">
                        <i class="fa-solid fa-plug"></i> Test Connection
                    </button>
                    
                    <div id="test_console" class="hidden mt-3 bg-gray-900 rounded p-3 text-xs font-mono text-gray-300 shadow-inner h-32 overflow-y-auto">
                        </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div id="smtp_guide_modal" class="fixed inset-0 bg-gray-900/60 z-[100] hidden flex items-center justify-center backdrop-blur-sm transition-opacity opacity-0">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden transform scale-95 transition-all flex flex-col max-h-[90vh]" id="smtp_guide_content">
        <div class="p-6 border-b flex justify-between items-center bg-gray-50">
            <div>
                <h2 class="text-xl font-bold text-gray-900"><i class="fa-solid fa-book text-indigo-500 mr-2"></i> External API Setup Guide</h2>
            </div>
            <button onclick="closeSmtpGuide()" class="text-gray-400 hover:text-gray-800 transition text-xl"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <div class="p-6 overflow-y-auto flex-1 space-y-6">
            <p class="text-sm text-gray-600 mb-4">Connecting a third-party server allows you to use your existing email infrastructure while still using our visual drag-and-drop builder to design your campaigns.</p>
            
            <div class="space-y-4">
                <div class="border rounded-lg p-4">
                    <h3 class="font-bold text-gray-900 flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-blue-500"></span> SendGrid</h3>
                    <ol class="list-decimal ml-6 mt-2 text-sm text-gray-600 space-y-1">
                        <li>Log into your SendGrid dashboard.</li>
                        <li>Navigate to <strong>Settings > API Keys</strong>.</li>
                        <li>Click <strong>Create API Key</strong>, give it Full Access, and copy the key.</li>
                        <li>Paste it into the API Key field in our dashboard.</li>
                    </ol>
                </div>

                <div class="border rounded-lg p-4">
                    <h3 class="font-bold text-gray-900 flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-red-500"></span> Mailgun</h3>
                    <ol class="list-decimal ml-6 mt-2 text-sm text-gray-600 space-y-1">
                        <li>Log into Mailgun and select your domain.</li>
                        <li>Go to <strong>Settings > API Keys</strong>.</li>
                        <li>Copy your <strong>Private API Key</strong> (it usually starts with `key-`).</li>
                        <li>Paste it into our dashboard.</li>
                    </ol>
                </div>

                <div class="border rounded-lg p-4 bg-gray-50">
                    <h3 class="font-bold text-gray-900 flex items-center gap-2"><i class="fa-solid fa-server text-gray-500 text-xs"></i> Custom SMTP (cPanel, Outlook, etc.)</h3>
                    <p class="text-sm text-gray-600 mt-2 mb-2">If your provider isn't listed, select <strong>Other / Standard SMTP</strong> from the dropdown.</p>
                    <ul class="list-disc ml-6 text-sm text-gray-600 space-y-1">
                        <li><strong>Host:</strong> Usually looks like `smtp.yourdomain.com` or `mail.yourdomain.com`.</li>
                        <li><strong>Port:</strong> Use <strong>587</strong> for TLS (Recommended) or <strong>465</strong> for SSL.</li>
                        <li><strong>Username/Password:</strong> The credentials you use to log into that email account.</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="p-4 border-t bg-white text-right">
            <button onclick="closeSmtpGuide()" class="bg-gray-900 text-white px-6 py-2 rounded-lg font-bold hover:bg-gray-800 transition">Got it</button>
        </div>
    </div>
</div>

<script>
    // --- UI Interactions ---
    document.querySelectorAll('input[name="splash_method"]').forEach(radio => {
        radio.addEventListener('change', (e) => {
            document.querySelectorAll('input[name="splash_method"]').forEach(r => r.closest('label').classList.remove('border-blue-500', 'bg-blue-50'));
            e.target.closest('label').classList.add('border-blue-500', 'bg-blue-50');
        });
    });

    document.querySelectorAll('input[name="bulk_method"]').forEach(radio => {
        radio.addEventListener('change', (e) => {
            document.querySelectorAll('input[name="bulk_method"]').forEach(r => r.closest('label').classList.remove('border-purple-500', 'bg-purple-50'));
            e.target.closest('label').classList.add('border-purple-500', 'bg-purple-50');
            if(e.target.value !== 'api') document.getElementById('external_api_box').classList.add('hidden');
        });
    });

    function toggleSmtpFields() {
        const val = document.getElementById('smtp_provider').value;
        const apiKeyBox = document.getElementById('api_key_box');
        const fullSmtpBox = document.getElementById('full_smtp_box');
        document.getElementById('test_console').classList.add('hidden'); // hide console on change
        
        if (val === 'custom') {
            apiKeyBox.classList.add('hidden');
            fullSmtpBox.classList.remove('hidden');
        } else {
            apiKeyBox.classList.remove('hidden');
            fullSmtpBox.classList.add('hidden');
            
            // Adjust label based on provider
            const lbl = document.getElementById('lbl_api_key');
            if (val === 'ses') lbl.innerText = "AWS Access Key ID | Secret Access Key (Comma Separated)";
            else lbl.innerText = val.charAt(0).toUpperCase() + val.slice(1) + " API Key";
        }
    }

    // --- Modal Logic ---
    function openSmtpGuide() {
        const m = document.getElementById('smtp_guide_modal');
        const c = document.getElementById('smtp_guide_content');
        m.classList.remove('hidden');
        setTimeout(() => { m.classList.remove('opacity-0'); c.classList.remove('scale-95'); }, 10);
    }
    
    function closeSmtpGuide() {
        const m = document.getElementById('smtp_guide_modal');
        const c = document.getElementById('smtp_guide_content');
        m.classList.add('opacity-0'); c.classList.add('scale-95');
        setTimeout(() => { m.classList.add('hidden'); }, 300);
    }

    // --- Testing Logic (The Terminal) ---
    function testConnection() {
        const btn = document.getElementById('btn_test_conn');
        const consoleBox = document.getElementById('test_console');
        const ogText = btn.innerHTML;
        
        // Show console and clear it
        consoleBox.classList.remove('hidden');
        consoleBox.innerHTML = '<i>Connecting to backend...</i><br>';
        
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Testing...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'mt_test_smtp_connection');
        formData.append('security', mt_nonce);
        formData.append('provider', document.getElementById('smtp_provider').value);
        formData.append('key', document.getElementById('smtp_key').value);
        formData.append('host', document.getElementById('smtp_host').value);
        formData.append('user', document.getElementById('smtp_user').value);
        formData.append('pass', document.getElementById('smtp_pass').value);
        formData.append('port', document.getElementById('smtp_port').value);

        fetch(mt_ajax_url, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            consoleBox.innerHTML = '';
            const logs = data.data.logs || [];
            
            // Simulate a typing effect for the logs for that pro feel
            let delay = 0;
            logs.forEach((logLine, index) => {
                setTimeout(() => {
                    let formattedLine = logLine;
                    if(logLine.includes('[ERROR]')) formattedLine = `<span class="text-red-400">${logLine}</span>`;
                    if(logLine.includes('[SUCCESS]')) formattedLine = `<span class="text-green-400 font-bold">${logLine}</span>`;
                    if(logLine.includes('[SYSTEM]')) formattedLine = `<span class="text-blue-300">${logLine}</span>`;
                    
                    consoleBox.innerHTML += `> ${formattedLine}<br>`;
                    consoleBox.scrollTop = consoleBox.scrollHeight;
                    
                    if (index === logs.length - 1) {
                        btn.innerHTML = data.success ? '<i class="fa-solid fa-check text-green-400"></i> Test Passed' : '<i class="fa-solid fa-xmark text-red-400"></i> Test Failed';
                        setTimeout(() => { btn.innerHTML = ogText; btn.disabled = false; }, 3000);
                    }
                }, delay);
                delay += 300; // 300ms delay between log lines
            });
        })
        .catch(err => {
            consoleBox.innerHTML = `<span class="text-red-400">> [FATAL ERROR] Could not reach MailToucan servers.</span>`;
            btn.innerHTML = ogText;
            btn.disabled = false;
        });
    }

    // --- Save Logic ---
    function saveDeliverySettings() {
        alert("Delivery UI is mapped! Next phase will wire these fields to the API Backend.");
    }
</script>