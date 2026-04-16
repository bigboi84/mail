<?php
if ( ! defined( 'ABSPATH' ) ) exit;

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

// SAAS LOGIC: Dynamically generate their System Email
$raw_slug = sanitize_title($brand->brand_name);
$clean_slug = str_replace('-', '', $raw_slug);
if (empty($clean_slug)) $clean_slug = 'hello';
$system_email = $clean_slug . '@fly.mailtoucan.com';

$brand_color = !empty($brand->primary_color) ? $brand->primary_color : '#0f172a';
$mt_palette = get_option( 'mt_brand_palette', ['accent' => '#FCC753', 'dark' => '#1A232E'] );
?>

<style>
    :root { --mt-brand: <?php echo esc_html($brand_color); ?>; --mt-accent: <?php echo esc_html($mt_palette['accent']); ?>; }
    .routing-radio { display: none; }
    .routing-card { transition: all 0.2s ease; border: 2px solid #e2e8f0; cursor: pointer; }
    .routing-radio:checked + .routing-card { border-color: var(--mt-brand); background-color: #f8fafc; }
    .routing-radio:checked + .routing-card .radio-circle { border-color: var(--mt-brand); }
    .routing-radio:checked + .routing-card .radio-circle::after { content: ''; display: block; width: 10px; height: 10px; background: var(--mt-brand); border-radius: 50%; margin: 3px auto; }
    .routing-card:hover { border-color: #cbd5e1; }
    .routing-radio:checked + .routing-card:hover { border-color: var(--mt-brand); }
</style>

<header class="mb-8 flex justify-between items-end">
    <div>
        <h1 class="text-3xl font-black text-gray-900 flex items-center gap-3">Flight Routing</h1>
        <p class="text-gray-500 text-sm mt-1">Configure how your Transactional and Bulk marketing emails take flight.</p>
    </div>
    <button onclick="saveDeliverySettings()" id="btn_save_delivery" class="text-white px-8 py-3 rounded-xl font-black shadow-lg hover:opacity-90 transition flex items-center gap-2" style="background-color: var(--mt-brand);">
        <i class="fa-solid fa-floppy-disk"></i> Save Routes
    </button>
</header>

<div class="grid grid-cols-2 gap-8 mb-12">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden flex flex-col">
        <div class="p-6 border-b border-gray-100 bg-gray-50/50 flex items-center gap-4 shrink-0">
            <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center text-xl shadow-sm border border-blue-100"><i class="fa-solid fa-bolt"></i></div>
            <div>
                <h2 class="text-lg font-black text-gray-900">Transactional Engine</h2>
                <p class="text-xs text-gray-500 font-medium">For WiFi Splash Pages & Autoresponders</p>
            </div>
        </div>
        <div class="p-8 space-y-4 flex-1 bg-white">
            <label class="block relative">
                <input type="radio" name="splash_method" value="system" class="routing-radio" <?php echo $delivery['splash_method'] == 'system' ? 'checked' : ''; ?>>
                <div class="routing-card bg-white rounded-xl p-5 flex items-start gap-4">
                    <div class="radio-circle w-5 h-5 rounded-full border-2 border-gray-300 shrink-0 mt-0.5"></div>
                    <div>
                        <p class="font-bold text-sm text-gray-900 mb-1">System Branded (Free)</p>
                        <p class="text-xs text-gray-500 leading-relaxed">Sent reliably from our shared network via <br><span class="font-mono text-indigo-600 bg-indigo-50 border border-indigo-100 px-1.5 py-0.5 rounded mt-1 inline-block"><?php echo esc_html($system_email); ?></span></p>
                    </div>
                </div>
            </label>
            <label class="block relative">
                <input type="radio" name="splash_method" value="google" class="routing-radio" <?php echo $delivery['splash_method'] == 'google' ? 'checked' : ''; ?>>
                <div class="routing-card bg-white rounded-xl p-5 flex items-start gap-4">
                    <div class="radio-circle w-5 h-5 rounded-full border-2 border-gray-300 shrink-0 mt-0.5"></div>
                    <div class="w-full">
                        <div class="flex justify-between items-center mb-1">
                            <p class="font-bold text-sm text-gray-900">Google Workspace / Gmail</p>
                            <span class="text-[9px] bg-yellow-100 text-yellow-700 px-2 py-1 rounded border border-yellow-200 font-black uppercase tracking-widest">Low Volume</span>
                        </div>
                        <p class="text-xs text-gray-500 mb-3">Send directly from your connected Gmail account.</p>
                        <button class="text-xs bg-gray-900 text-white px-4 py-2 rounded-lg font-bold hover:bg-black transition shadow-sm"><i class="fa-brands fa-google mr-1.5"></i> Connect Account</button>
                    </div>
                </div>
            </label>
            <label class="block relative">
                <input type="radio" name="splash_method" value="domain" class="routing-radio" <?php echo $delivery['splash_method'] == 'domain' ? 'checked' : ''; ?>>
                <div class="routing-card bg-white rounded-xl p-5 flex items-start gap-4">
                    <div class="radio-circle w-5 h-5 rounded-full border-2 border-gray-300 shrink-0 mt-0.5"></div>
                    <div>
                        <p class="font-bold text-sm text-gray-900 mb-1">Authenticated Domain</p>
                        <p class="text-xs text-gray-500">Send using the domains you verified in Core Setup.</p>
                    </div>
                </div>
            </label>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden flex flex-col">
        <div class="p-6 border-b border-gray-100 bg-gray-50/50 flex items-center gap-4 shrink-0">
            <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-full flex items-center justify-center text-xl shadow-sm border border-purple-100"><i class="fa-solid fa-users"></i></div>
            <div>
                <h2 class="text-lg font-black text-gray-900">Bulk Broadcast Engine</h2>
                <p class="text-xs text-gray-500 font-medium">For Newsletters & Mass Campaigns</p>
            </div>
        </div>
        <div class="p-8 space-y-4 flex-1 bg-white">
            <div class="bg-red-50 p-4 rounded-xl border border-red-100 flex gap-3 mb-2">
                <i class="fa-solid fa-shield-halved text-red-500 mt-0.5"></i>
                <p class="text-[11px] text-red-700 font-medium leading-relaxed">To protect global sender reputation, <strong>System</strong> and <strong>Gmail</strong> routing are strictly disabled for massive flock deployments.</p>
            </div>
            <label class="block relative">
                <input type="radio" name="bulk_method" value="domain" class="routing-radio" onchange="document.getElementById('external_api_box').classList.add('hidden')" <?php echo $delivery['bulk_method'] == 'domain' ? 'checked' : ''; ?>>
                <div class="routing-card bg-white rounded-xl p-5 flex items-start gap-4">
                    <div class="radio-circle w-5 h-5 rounded-full border-2 border-gray-300 shrink-0 mt-0.5"></div>
                    <div>
                        <p class="font-bold text-sm text-gray-900 mb-1">Authenticated Domain (Native)</p>
                        <p class="text-xs text-gray-500">Use our high-deliverability internal Toucan network.</p>
                    </div>
                </div>
            </label>
            <label class="block relative">
                <input type="radio" name="bulk_method" value="api" class="routing-radio" onchange="document.getElementById('external_api_box').classList.remove('hidden')" <?php echo $delivery['bulk_method'] == 'api' ? 'checked' : ''; ?>>
                <div class="routing-card bg-white rounded-xl p-5 flex items-start gap-4">
                    <div class="radio-circle w-5 h-5 rounded-full border-2 border-gray-300 shrink-0 mt-0.5"></div>
                    <div class="w-full">
                        <p class="font-bold text-sm text-gray-900 mb-1">External API / Custom SMTP</p>
                        <p class="text-xs text-gray-500">Bring your own server (SendGrid, Mailgun, etc.)</p>
                    </div>
                </div>
            </label>

            <div id="external_api_box" class="bg-gray-50 p-6 border border-gray-200 rounded-xl mt-4 transition-all <?php echo $delivery['bulk_method'] == 'api' ? '' : 'hidden'; ?>">
                <div class="flex justify-between items-end mb-3">
                    <label class="block text-[10px] uppercase font-bold tracking-widest text-gray-500">Select Provider</label>
                    <button type="button" onclick="openSmtpGuide()" class="text-[11px] font-black text-indigo-600 hover:text-indigo-800 transition"><i class="fa-solid fa-circle-info mr-1"></i>Setup Guide</button>
                </div>
                
                <select id="smtp_provider" onchange="toggleSmtpFields()" class="w-full p-3 border border-gray-300 rounded-lg text-sm mb-5 outline-none font-bold text-gray-700 bg-white focus:border-indigo-500 transition shadow-sm">
                    <option value="sendgrid" <?php echo $delivery['smtp_provider'] == 'sendgrid' ? 'selected' : ''; ?>>SendGrid API</option>
                    <option value="mailgun" <?php echo $delivery['smtp_provider'] == 'mailgun' ? 'selected' : ''; ?>>Mailgun API</option>
                    <option value="postmark" <?php echo $delivery['smtp_provider'] == 'postmark' ? 'selected' : ''; ?>>Postmark API</option>
                    <option value="brevo" <?php echo $delivery['smtp_provider'] == 'brevo' ? 'selected' : ''; ?>>Brevo (Sendinblue) API</option>
                    <option value="ses" <?php echo $delivery['smtp_provider'] == 'ses' ? 'selected' : ''; ?>>Amazon SES API</option>
                    <option value="custom" <?php echo $delivery['smtp_provider'] == 'custom' ? 'selected' : ''; ?>>Other / Standard SMTP</option>
                </select>

                <div id="api_key_box" class="<?php echo $delivery['smtp_provider'] !== 'custom' ? '' : 'hidden'; ?>">
                    <label class="block text-[10px] uppercase font-bold tracking-widest text-gray-500 mb-2" id="lbl_api_key">Provider API Key</label>
                    <input type="password" id="smtp_key" value="<?php echo esc_attr($delivery['smtp_key']); ?>" class="w-full p-3 border border-gray-300 rounded-lg text-sm font-medium text-gray-800 placeholder-gray-400 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition shadow-sm" placeholder="Enter your secret API key">
                </div>

                <div id="full_smtp_box" class="space-y-4 <?php echo $delivery['smtp_provider'] === 'custom' ? '' : 'hidden'; ?>">
                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <label class="block text-[10px] uppercase font-bold tracking-widest text-gray-500 mb-2">SMTP Host</label>
                            <input type="text" id="smtp_host" value="<?php echo esc_attr($delivery['smtp_host']); ?>" class="w-full p-3 border border-gray-300 rounded-lg text-sm font-medium text-gray-800 placeholder-gray-400 outline-none focus:border-indigo-500 transition shadow-sm" placeholder="e.g. smtp.mail.com">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase font-bold tracking-widest text-gray-500 mb-2">Port</label>
                            <input type="text" id="smtp_port" value="<?php echo esc_attr($delivery['smtp_port']); ?>" class="w-full p-3 border border-gray-300 rounded-lg text-sm font-medium text-gray-800 placeholder-gray-400 outline-none focus:border-indigo-500 transition shadow-sm" placeholder="587">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase font-bold tracking-widest text-gray-500 mb-2">SMTP Username</label>
                        <input type="text" id="smtp_user" value="<?php echo esc_attr($delivery['smtp_user']); ?>" class="w-full p-3 border border-gray-300 rounded-lg text-sm font-medium text-gray-800 placeholder-gray-400 outline-none focus:border-indigo-500 transition shadow-sm" placeholder="Username or Email">
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase font-bold tracking-widest text-gray-500 mb-2">SMTP Password</label>
                        <input type="password" id="smtp_pass" value="<?php echo esc_attr($delivery['smtp_pass']); ?>" class="w-full p-3 border border-gray-300 rounded-lg text-sm font-medium text-gray-800 placeholder-gray-400 outline-none focus:border-indigo-500 transition shadow-sm" placeholder="Password">
                    </div>
                </div>

                <div class="mt-6 pt-5 border-t border-gray-200">
                    <button type="button" id="btn_test_conn" onclick="testConnection()" class="w-full bg-gray-900 text-white py-3 rounded-xl text-sm font-black tracking-wide shadow-md hover:bg-black transition flex items-center justify-center gap-2">
                        <i class="fa-solid fa-plug"></i> Test Connection
                    </button>
                    <div id="test_console" class="hidden mt-4 bg-[#0f172a] rounded-xl p-4 text-[11px] font-mono text-green-400 shadow-inner overflow-y-auto border border-gray-800"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="bg-white border border-gray-200 rounded-xl p-8 mt-8 shadow-sm max-w-4xl mx-auto">
    <h3 class="font-bold text-gray-900 mb-2 text-xl"><i class="fa-solid fa-satellite-dish text-indigo-500 mr-2"></i> Master Infrastructure Diagnostic</h3>
    <p class="text-sm text-gray-500 mb-6">Run a full system check on both your WiFi and Bulk routing engines simultaneously.</p>
    
    <div class="flex gap-4 items-end mb-6">
        <div class="flex-1">
            <label class="block text-[10px] uppercase font-bold tracking-widest text-gray-500 mb-2">Target Inbox</label>
            <input type="email" id="test_email_address" placeholder="your.personal@gmail.com" class="w-full p-3 border border-gray-300 rounded-lg outline-none focus:border-indigo-500 font-bold text-gray-800">
        </div>
        <button onclick="runMasterDiagnostic()" id="btn_master_diag" class="bg-indigo-600 text-white px-8 py-3 rounded-lg font-bold shadow hover:bg-indigo-700 transition flex items-center justify-center gap-2 h-[46px]">
            <i class="fa-solid fa-radar"></i> RUN DIAGNOSTIC
        </button>
    </div>

    <div id="diagnostic_readout" class="hidden border border-gray-200 rounded-xl overflow-hidden shadow-inner">
        <div class="p-4 border-b border-gray-200 bg-gray-50 flex items-start gap-3">
            <div id="icon_res_splash" class="mt-0.5"></div>
            <div class="flex-1">
                <h4 class="font-bold text-gray-900 text-sm">WiFi Transactional Engine</h4>
                <p id="text_res_splash" class="text-xs font-mono mt-1"></p>
            </div>
        </div>
        <div class="p-4 bg-gray-50 flex items-start gap-3">
            <div id="icon_res_bulk" class="mt-0.5"></div>
            <div class="flex-1">
                <h4 class="font-bold text-gray-900 text-sm">Bulk Broadcast Engine</h4>
                <p id="text_res_bulk" class="text-xs font-mono mt-1"></p>
            </div>
        </div>
    </div>
    
    <div id="crash_console" class="hidden mt-4 bg-gray-900 rounded-xl p-4 text-[11px] font-mono text-red-400 overflow-x-auto shadow-inner border border-gray-700"></div>
</div>

<div id="smtp_guide_modal" class="fixed inset-0 bg-gray-900/60 z-[300] hidden flex items-center justify-center backdrop-blur-sm transition-opacity opacity-0">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden transform scale-95 transition-all flex flex-col max-h-[90vh]" id="smtp_guide_content">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <div><h2 class="text-xl font-black text-gray-900"><i class="fa-solid fa-book text-indigo-500 mr-2"></i> External API Setup Guide</h2></div>
            <button onclick="closeSmtpGuide()" class="w-8 h-8 flex items-center justify-center rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-800 transition"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="p-6 overflow-y-auto flex-1 space-y-6 bg-white">
            <p class="text-sm text-gray-600 font-medium">Connecting a third-party server allows you to use your existing email infrastructure while still using our visual drag-and-drop builder to design your campaigns.</p>
            <div class="space-y-4">
                <div class="border border-gray-200 rounded-xl p-5 shadow-sm">
                    <h3 class="font-bold text-gray-900 flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> SendGrid</h3>
                    <ol class="list-decimal ml-6 mt-3 text-sm text-gray-600 font-medium space-y-1.5"><li>Log into your SendGrid dashboard.</li><li>Navigate to <strong>Settings > API Keys</strong>.</li><li>Click <strong>Create API Key</strong>, give it Full Access, and copy the key.</li><li>Paste it into the API Key field in our dashboard.</li></ol>
                </div>
            </div>
        </div>
        <div class="p-5 border-t border-gray-100 bg-gray-50 text-right">
            <button onclick="closeSmtpGuide()" class="bg-gray-900 text-white px-8 py-3 rounded-xl font-bold shadow-md hover:bg-black transition">Got it</button>
        </div>
    </div>
</div>

<div id="mt_toast_container" class="fixed bottom-8 right-8 z-[400] flex flex-col items-end pointer-events-none"></div>

<script>
    let brandConfig = <?php echo wp_json_encode($brand_config); ?>;

    function showToast(message, type = 'success') {
        const container = document.getElementById('mt_toast_container');
        const toast = document.createElement('div');
        const bgColor = type === 'error' ? 'bg-red-600' : 'bg-gray-900';
        const icon = type === 'error' ? 'fa-triangle-exclamation' : 'fa-check-circle';
        
        let displayMessage = message || "Unknown Error Occurred";
        toast.className = `flex items-center gap-3 px-5 py-3.5 rounded-xl shadow-[0_10px_40px_rgba(0,0,0,0.2)] text-white text-sm font-bold transform transition-all duration-300 translate-y-10 opacity-0 ${bgColor} mb-3 pointer-events-auto`;
        toast.innerHTML = `<i class="fa-solid ${icon} text-lg"></i> ${displayMessage}`;
        container.appendChild(toast);
        
        requestAnimationFrame(() => { toast.classList.remove('translate-y-10', 'opacity-0'); });
        setTimeout(() => {
            toast.classList.add('translate-y-10', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    function toggleSmtpFields() {
        const val = document.getElementById('smtp_provider').value;
        const apiKeyBox = document.getElementById('api_key_box');
        const fullSmtpBox = document.getElementById('full_smtp_box');
        document.getElementById('test_console').classList.add('hidden');
        
        if (val === 'custom') {
            apiKeyBox.classList.add('hidden'); fullSmtpBox.classList.remove('hidden');
        } else {
            apiKeyBox.classList.remove('hidden'); fullSmtpBox.classList.add('hidden');
            const lbl = document.getElementById('lbl_api_key');
            if (val === 'ses') lbl.innerText = "AWS Access Key ID | Secret Access Key (Comma Separated)";
            else lbl.innerText = val.charAt(0).toUpperCase() + val.slice(1) + " API Key";
        }
    }

    function openSmtpGuide() {
        const m = document.getElementById('smtp_guide_modal');
        const c = document.getElementById('smtp_guide_content');
        m.classList.remove('hidden'); setTimeout(() => { m.classList.remove('opacity-0'); c.classList.remove('scale-95'); }, 10);
    }
    
    function closeSmtpGuide() {
        const m = document.getElementById('smtp_guide_modal');
        const c = document.getElementById('smtp_guide_content');
        m.classList.add('opacity-0'); c.classList.add('scale-95'); setTimeout(() => { m.classList.add('hidden'); }, 300);
    }

    function saveDeliverySettings() {
        const btn = document.getElementById('btn_save_delivery');
        const ogText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Saving...';
        btn.disabled = true;

        brandConfig.delivery = {
            splash_method: document.querySelector('input[name="splash_method"]:checked').value,
            bulk_method: document.querySelector('input[name="bulk_method"]:checked').value,
            smtp_provider: document.getElementById('smtp_provider').value,
            smtp_key: document.getElementById('smtp_key').value,
            smtp_host: document.getElementById('smtp_host').value,
            smtp_port: document.getElementById('smtp_port').value,
            smtp_user: document.getElementById('smtp_user').value,
            smtp_pass: document.getElementById('smtp_pass').value
        };

        const fd = new FormData();
        fd.append('action', 'mt_save_brand_config');
        fd.append('security', mt_nonce);
        fd.append('brand_name', "<?php echo esc_js($brand->brand_name); ?>");
        fd.append('primary_color', "<?php echo esc_js($brand->primary_color); ?>");
        fd.append('config', JSON.stringify(brandConfig));

        fetch(mt_ajax_url, { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if(data.success) showToast("Flight Routes updated successfully in The Nest!", "success");
            else showToast(data.data || "Failed to save. Please try again.", "error");
            btn.innerHTML = ogText; btn.disabled = false;
        })
        .catch(err => {
            showToast("System Error: Could not connect to database.", "error");
            btn.innerHTML = ogText; btn.disabled = false;
        });
    }

    // --- MASTER DIAGNOSTIC TRIGGER ---
    function runMasterDiagnostic() {
        const email = document.getElementById('test_email_address').value.trim();
        if(!email) return showToast("Please enter a target email address.", "error");
        
        const btn = document.getElementById('btn_master_diag');
        const readout = document.getElementById('diagnostic_readout');
        const crashBox = document.getElementById('crash_console');
        const ogText = btn.innerHTML;
        
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Scanning Routes...';
        btn.disabled = true;
        
        readout.classList.add('hidden');
        crashBox.classList.add('hidden');
        document.getElementById('icon_res_splash').innerHTML = '<i class="fa-solid fa-circle-notch fa-spin text-gray-400"></i>';
        document.getElementById('text_res_splash').innerHTML = 'Testing...';
        document.getElementById('text_res_splash').className = 'text-xs font-mono mt-1 text-gray-500';
        document.getElementById('icon_res_bulk').innerHTML = '<i class="fa-solid fa-circle-notch fa-spin text-gray-400"></i>';
        document.getElementById('text_res_bulk').innerHTML = 'Testing...';
        document.getElementById('text_res_bulk').className = 'text-xs font-mono mt-1 text-gray-500';
        readout.classList.remove('hidden');

        const fd = new FormData();
        fd.append('action', 'mt_run_master_diagnostic');
        fd.append('security', mt_nonce);
        fd.append('to_email', email);

        fetch(mt_ajax_url, { method: 'POST', body: fd })
        .then(async res => {
            const rawText = await res.text(); 
            try {
                const data = JSON.parse(rawText);
                if(data.success && data.data) {
                    if(data.data.splash.success) {
                        document.getElementById('icon_res_splash').innerHTML = '<i class="fa-solid fa-circle-check text-green-500 text-lg"></i>';
                        document.getElementById('text_res_splash').innerHTML = data.data.splash.msg;
                        document.getElementById('text_res_splash').className = 'text-xs font-mono mt-1 text-green-600 font-bold';
                    } else {
                        document.getElementById('icon_res_splash').innerHTML = '<i class="fa-solid fa-triangle-exclamation text-red-500 text-lg"></i>';
                        document.getElementById('text_res_splash').innerHTML = data.data.splash.msg;
                        document.getElementById('text_res_splash').className = 'text-xs font-mono mt-1 text-red-600 font-bold break-words';
                    }
                    if(data.data.bulk.success) {
                        document.getElementById('icon_res_bulk').innerHTML = '<i class="fa-solid fa-circle-check text-green-500 text-lg"></i>';
                        document.getElementById('text_res_bulk').innerHTML = data.data.bulk.msg;
                        document.getElementById('text_res_bulk').className = 'text-xs font-mono mt-1 text-green-600 font-bold';
                    } else {
                        document.getElementById('icon_res_bulk').innerHTML = '<i class="fa-solid fa-triangle-exclamation text-red-500 text-lg"></i>';
                        document.getElementById('text_res_bulk').innerHTML = data.data.bulk.msg;
                        document.getElementById('text_res_bulk').className = 'text-xs font-mono mt-1 text-red-600 font-bold break-words';
                    }
                } else {
                    throw new Error(data.data || "Unknown Error");
                }
            } catch(e) {
                readout.classList.add('hidden');
                crashBox.classList.remove('hidden');
                if (rawText.trim() === '0' || rawText.trim() === '') {
                    crashBox.innerHTML = `[CRITICAL FAULT] Server returned empty response. The wp_ajax_mt_run_master_diagnostic hook is missing.`;
                } else {
                    crashBox.innerHTML = `[PHP CRASH LOG]<br><br>${rawText}`;
                }
            }
            btn.innerHTML = ogText; 
            btn.disabled = false;
        })
        .catch(err => {
            readout.classList.add('hidden');
            crashBox.classList.remove('hidden');
            crashBox.innerHTML = '[NETWORK ERROR] Failed to connect to the server.';
            btn.innerHTML = ogText; 
            btn.disabled = false;
        });
    }

    function testConnection() {
        const btn = document.getElementById('btn_test_conn');
        const consoleBox = document.getElementById('test_console');
        const ogText = btn.innerHTML;
        
        consoleBox.classList.remove('hidden');
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Testing...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'mt_test_smtp_connection');
        formData.append('security', typeof mt_nonce !== 'undefined' ? mt_nonce : '');
        formData.append('host', document.getElementById('smtp_host').value);
        formData.append('user', document.getElementById('smtp_user').value);
        formData.append('pass', document.getElementById('smtp_pass').value);
        formData.append('port', document.getElementById('smtp_port').value);

        fetch(mt_ajax_url, { method: 'POST', body: formData })
        .then(async res => {
            const rawText = await res.text();
            try {
                const data = JSON.parse(rawText);
                const msg = data.data || (data.success ? "Verified." : "Failed.");
                if(data.success) {
                    consoleBox.innerHTML = `> <span class="text-green-400 font-bold">[SUCCESS] ${msg}</span>`;
                    btn.innerHTML = '<i class="fa-solid fa-check text-green-400"></i> Verified';
                } else {
                    consoleBox.innerHTML = `> <span class="text-red-400 font-bold">[ERROR] ${msg}</span>`;
                    btn.innerHTML = '<i class="fa-solid fa-xmark text-red-400"></i> Failed';
                }
            } catch(e) {
                consoleBox.innerHTML = `> <span class="text-red-400">[FATAL ERROR] Server returned unreadable data.</span><br><span class="text-gray-500">${rawText.substring(0, 100)}...</span>`;
                btn.innerHTML = '<i class="fa-solid fa-xmark text-red-400"></i> Crash';
            }
            setTimeout(() => { btn.innerHTML = ogText; btn.disabled = false; }, 3000);
        })
        .catch(err => {
            consoleBox.innerHTML = `> <span class="text-red-400">[NETWORK ERROR] Could not reach server.</span>`;
            btn.innerHTML = ogText; btn.disabled = false;
        });
    }
</script>