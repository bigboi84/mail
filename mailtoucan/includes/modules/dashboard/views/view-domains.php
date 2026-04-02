<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_domains = $wpdb->prefix . 'mt_email_domains';
$domains = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table_domains WHERE brand_id = %d ORDER BY created_at DESC", $brand->id) );
?>

<header class="mb-8 flex justify-between items-end">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Sender Domains</h1>
        <p class="text-gray-500 text-sm">Authenticate your custom domain to ensure your marketing emails land in the inbox, not the spam folder.</p>
    </div>
    <button onclick="openAddDomainModal()" class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg font-bold shadow-md hover:bg-indigo-700 transition flex items-center gap-2">
        <i class="fa-solid fa-plus"></i> Add Domain
    </button>
</header>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-20">
    <table class="w-full text-left border-collapse">
        <thead class="bg-gray-50 border-b border-gray-200 text-[11px] font-bold text-gray-400 uppercase tracking-wider">
            <tr>
                <th class="p-4 pl-6">Domain Name</th>
                <th class="p-4">Status</th>
                <th class="p-4">Added On</th>
                <th class="p-4 text-right pr-6">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if(empty($domains)): ?>
                <tr><td colspan="4" class="p-12 text-center text-gray-400 italic">No domains authenticated yet. Click "Add Domain" to begin.</td></tr>
            <?php else: ?>
                <?php foreach($domains as $dom): 
                    $dkim = json_decode($dom->dkim_tokens, true) ?: [];
                ?>
                <tr class="hover:bg-gray-50 transition">
                    <td class="p-4 pl-6 font-bold text-gray-900 text-lg"><i class="fa-solid fa-globe text-gray-400 mr-2 text-sm"></i><?php echo esc_html($dom->domain_name); ?></td>
                    <td class="p-4">
                        <?php if($dom->status === 'verified'): ?>
                            <span class="bg-green-100 text-green-700 text-xs font-bold px-3 py-1 rounded-full flex items-center w-max gap-1"><i class="fa-solid fa-check-circle"></i> Verified</span>
                        <?php else: ?>
                            <span class="bg-yellow-100 text-yellow-700 text-xs font-bold px-3 py-1 rounded-full flex items-center w-max gap-1"><i class="fa-solid fa-triangle-exclamation"></i> Pending DNS</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-4 text-sm text-gray-500 font-medium"><?php echo date('M d, Y', strtotime($dom->created_at)); ?></td>
                    <td class="p-4 text-right pr-6 flex justify-end gap-3 items-center">
                        <?php 
                            // Change button text and icon based on status
                            $btn_text = $dom->status === 'verified' ? 'View DNS' : 'Setup DNS';
                            $btn_icon = $dom->status === 'verified' ? 'fa-eye' : 'fa-server';
                        ?>
                        <button onclick="openDnsModal('<?php echo esc_js($dom->domain_name); ?>', <?php echo htmlspecialchars(wp_json_encode($dkim), ENT_QUOTES, 'UTF-8'); ?>, <?php echo $dom->id; ?>)" class="text-indigo-600 font-bold text-sm hover:underline"><i class="fa-solid <?php echo $btn_icon; ?> mr-1"></i> <?php echo $btn_text; ?></button>
                        <button onclick="promptDeleteDomain(<?php echo $dom->id; ?>)" class="text-red-400 hover:text-red-600 transition ml-3" title="Remove Domain"><i class="fa-solid fa-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="add_domain_modal" class="fixed inset-0 bg-gray-900/60 z-[100] hidden flex items-center justify-center backdrop-blur-sm transition-opacity opacity-0">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform scale-95 transition-all" id="add_domain_content">
        <div class="p-6 border-b flex justify-between items-center bg-gray-50">
            <h2 class="text-lg font-bold text-gray-900">Add Sender Domain</h2>
            <button onclick="closeAddDomainModal()" class="text-gray-400 hover:text-gray-800 transition"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="p-6">
            <div id="add_domain_error" class="hidden mb-4 p-3 bg-red-50 border border-red-200 text-red-600 text-xs font-bold rounded-lg flex items-center gap-2"></div>
            
            <label class="block text-sm font-bold text-gray-700 mb-2">Domain Name</label>
            <input type="text" id="new_domain_input" class="w-full p-3 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-indigo-100 font-bold text-gray-900" placeholder="e.g., yourcompany.com">
            <p class="text-[11px] text-gray-500 mt-2">Do not include www or https://</p>
            <button id="btn_submit_domain" onclick="submitNewDomain()" class="w-full mt-6 bg-indigo-600 text-white font-bold py-3 rounded-lg shadow-md hover:bg-indigo-700 transition">Generate DNS Records</button>
        </div>
    </div>
</div>

<div id="dns_modal" class="fixed inset-0 bg-gray-900/60 z-[100] hidden flex items-center justify-center backdrop-blur-sm transition-opacity opacity-0">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl overflow-hidden transform scale-95 transition-all flex flex-col max-h-[90vh]" id="dns_modal_content">
        <div class="p-6 border-b flex justify-between items-center bg-gray-50">
            <div>
                <h2 class="text-xl font-bold text-gray-900">DNS Setup Guide</h2>
                <p class="text-sm text-gray-500">Add these records to your domain's DNS manager (GoDaddy, Cloudflare, etc.)</p>
            </div>
            <button onclick="closeDnsModal()" class="text-gray-400 hover:text-gray-800 transition text-xl"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <div class="p-6 overflow-y-auto flex-1 space-y-6 bg-gray-50/50">
            
            <div id="verify_dns_msg" class="hidden w-full text-center p-3 rounded-lg text-sm font-bold mb-4"></div>

            <div class="bg-white border rounded-lg overflow-hidden shadow-sm">
                <div class="bg-gray-100 p-3 border-b font-bold text-sm text-gray-800 flex items-center gap-2"><div class="w-6 h-6 bg-indigo-100 text-indigo-600 rounded flex items-center justify-center text-xs">1</div> Authenticate Senders (SPF)</div>
                <div class="p-4 overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead><tr class="text-[10px] uppercase text-gray-500"><th class="pb-2 w-1/6">Type</th><th class="pb-2 w-1/4">Name / Host</th><th class="pb-2">Value / Target</th></tr></thead>
                        <tbody>
                            <tr>
                                <td class="font-bold text-gray-700 py-2">TXT</td>
                                <td class="py-2">
                                    <div class="flex items-center gap-2">
                                        <div class="font-mono text-gray-600">@ <em>(or blank)</em></div>
                                        <button onclick="copyToClipboard(this, '@')" class="text-gray-400 hover:text-indigo-600 transition ml-1" title="Copy"><i class="fa-regular fa-copy"></i></button>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <div class="flex items-center gap-2">
                                        <div class="font-mono text-indigo-600 bg-indigo-50 p-2 rounded text-xs select-all flex-1">v=spf1 include:spf.mailtoucan.pro ~all</div>
                                        <button onclick="copyToClipboard(this, 'v=spf1 include:spf.mailtoucan.pro ~all')" class="text-gray-400 hover:text-indigo-600 transition p-2" title="Copy"><i class="fa-regular fa-copy"></i></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white border rounded-lg overflow-hidden shadow-sm">
                <div class="bg-gray-100 p-3 border-b font-bold text-sm text-gray-800 flex items-center gap-2"><div class="w-6 h-6 bg-indigo-100 text-indigo-600 rounded flex items-center justify-center text-xs">2</div> Encrypt Emails (DKIM)</div>
                <div class="p-4 overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead><tr class="text-[10px] uppercase text-gray-500"><th class="pb-2 w-1/6">Type</th><th class="pb-2 w-1/3">Name / Host</th><th class="pb-2">Value / Target</th></tr></thead>
                        <tbody id="dkim_table_body">
                            </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white border rounded-lg overflow-hidden shadow-sm">
                <div class="bg-gray-100 p-3 border-b font-bold text-sm text-gray-800 flex items-center gap-2"><div class="w-6 h-6 bg-indigo-100 text-indigo-600 rounded flex items-center justify-center text-xs">3</div> Protect Domain (DMARC)</div>
                <div class="p-4 overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead><tr class="text-[10px] uppercase text-gray-500"><th class="pb-2 w-1/6">Type</th><th class="pb-2 w-1/4">Name / Host</th><th class="pb-2">Value / Target</th></tr></thead>
                        <tbody>
                            <tr>
                                <td class="font-bold text-gray-700 py-2">TXT</td>
                                <td class="py-2">
                                    <div class="flex items-center gap-2">
                                        <div class="font-mono text-gray-600">_dmarc</div>
                                        <button onclick="copyToClipboard(this, '_dmarc')" class="text-gray-400 hover:text-indigo-600 transition ml-1" title="Copy"><i class="fa-regular fa-copy"></i></button>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <div class="flex items-center gap-2">
                                        <div class="font-mono text-indigo-600 bg-indigo-50 p-2 rounded text-xs select-all flex-1">v=DMARC1; p=quarantine; adkim=r; aspf=r;</div>
                                        <button onclick="copyToClipboard(this, 'v=DMARC1; p=quarantine; adkim=r; aspf=r;')" class="text-gray-400 hover:text-indigo-600 transition p-2" title="Copy"><i class="fa-regular fa-copy"></i></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="p-4 border-t bg-white flex justify-between items-center">
            <p class="text-xs text-gray-500 max-w-sm"><i class="fa-solid fa-circle-info text-indigo-400 mr-1"></i> DNS changes can take up to 24 hours to fully propagate globally.</p>
            <input type="hidden" id="verify_domain_id">
            <button id="btn_verify" onclick="triggerVerify()" class="bg-green-600 text-white px-6 py-2.5 rounded-lg font-bold shadow-md hover:bg-green-700 transition flex items-center gap-2">
                <i class="fa-solid fa-satellite-dish"></i> Verify / Refresh DNS
            </button>
        </div>
    </div>
</div>

<div id="delete_domain_modal" class="fixed inset-0 bg-gray-900/60 z-[150] hidden flex items-center justify-center backdrop-blur-sm transition-opacity opacity-0">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform scale-95 transition-all" id="delete_domain_content">
        <div class="p-6 text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-triangle-exclamation text-3xl text-red-600"></i>
            </div>
            <h2 class="text-xl font-bold text-gray-900 mb-2">Remove Domain?</h2>
            <p class="text-sm text-gray-500 mb-6">Are you sure you want to delete this domain? You will no longer be able to send marketing emails from this address.</p>
            <input type="hidden" id="domain_to_delete">
            <div class="flex gap-3 justify-center">
                <button onclick="closeDeleteModal()" class="px-5 py-2.5 bg-gray-100 text-gray-700 font-bold rounded-lg hover:bg-gray-200 transition">Cancel</button>
                <button id="btn_confirm_delete" onclick="executeDomainDelete()" class="px-5 py-2.5 bg-red-600 text-white font-bold rounded-lg shadow-md hover:bg-red-700 transition flex items-center gap-2">Yes, Remove</button>
            </div>
        </div>
    </div>
</div>

<script>
    // --- COPY TO CLIPBOARD HELPER ---
    function copyToClipboard(btn, text) {
        navigator.clipboard.writeText(text).then(() => {
            const icon = btn.querySelector('i');
            icon.classList.remove('fa-copy', 'fa-regular');
            icon.classList.add('fa-check', 'fa-solid', 'text-green-500');
            setTimeout(() => {
                icon.classList.remove('fa-check', 'fa-solid', 'text-green-500');
                icon.classList.add('fa-copy', 'fa-regular');
            }, 2000);
        }).catch(err => {
            console.error('Failed to copy: ', err);
        });
    }

    // --- MODAL CONTROLS ---
    function openAddDomainModal() {
        document.getElementById('new_domain_input').value = '';
        document.getElementById('add_domain_error').classList.add('hidden');
        const m = document.getElementById('add_domain_modal');
        const c = document.getElementById('add_domain_content');
        m.classList.remove('hidden');
        setTimeout(() => { m.classList.remove('opacity-0'); c.classList.remove('scale-95'); }, 10);
    }
    function closeAddDomainModal() {
        const m = document.getElementById('add_domain_modal');
        const c = document.getElementById('add_domain_content');
        m.classList.add('opacity-0'); c.classList.add('scale-95');
        setTimeout(() => { m.classList.add('hidden'); }, 300);
    }

    function openDnsModal(domain, dkimTokens, id) {
        document.getElementById('verify_domain_id').value = id;
        document.getElementById('verify_dns_msg').classList.add('hidden');
        
        const tbody = document.getElementById('dkim_table_body');
        tbody.innerHTML = '';
        dkimTokens.forEach(token => {
            tbody.innerHTML += `
            <tr class="border-b border-gray-50 last:border-0">
                <td class="font-bold text-gray-700 py-3">CNAME</td>
                <td class="py-3">
                    <div class="flex items-center gap-2">
                        <div class="font-mono text-gray-600 text-xs">${token}._domainkey</div>
                        <button onclick="copyToClipboard(this, '${token}._domainkey')" class="text-gray-400 hover:text-indigo-600 transition ml-1" title="Copy"><i class="fa-regular fa-copy"></i></button>
                    </div>
                </td>
                <td class="py-3">
                    <div class="flex items-center gap-2">
                        <div class="font-mono text-indigo-600 bg-indigo-50 p-2 rounded text-xs select-all flex-1">${token}.dkim.mailtoucan.pro</div>
                        <button onclick="copyToClipboard(this, '${token}.dkim.mailtoucan.pro')" class="text-gray-400 hover:text-indigo-600 transition p-2" title="Copy"><i class="fa-regular fa-copy"></i></button>
                    </div>
                </td>
            </tr>`;
        });

        const m = document.getElementById('dns_modal');
        const c = document.getElementById('dns_modal_content');
        m.classList.remove('hidden');
        setTimeout(() => { m.classList.remove('opacity-0'); c.classList.remove('scale-95'); }, 10);
    }
    function closeDnsModal() {
        const m = document.getElementById('dns_modal');
        const c = document.getElementById('dns_modal_content');
        m.classList.add('opacity-0'); c.classList.add('scale-95');
        setTimeout(() => { m.classList.add('hidden'); }, 300);
    }

    function promptDeleteDomain(id) {
        document.getElementById('domain_to_delete').value = id;
        const m = document.getElementById('delete_domain_modal');
        const c = document.getElementById('delete_domain_content');
        m.classList.remove('hidden');
        setTimeout(() => { m.classList.remove('opacity-0'); c.classList.remove('scale-95'); }, 10);
    }
    function closeDeleteModal() {
        const m = document.getElementById('delete_domain_modal');
        const c = document.getElementById('delete_domain_content');
        m.classList.add('opacity-0'); c.classList.add('scale-95');
        setTimeout(() => { m.classList.add('hidden'); }, 300);
    }

    // --- AJAX ACTIONS ---
    function submitNewDomain() {
        const domain = document.getElementById('new_domain_input').value.trim();
        const errBox = document.getElementById('add_domain_error');
        errBox.classList.add('hidden');

        if(!domain) {
            errBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Please enter a domain name.';
            errBox.classList.remove('hidden');
            return;
        }
        
        const btn = document.getElementById('btn_submit_domain');
        const ogText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'mt_add_domain');
        formData.append('security', mt_nonce);
        formData.append('domain', domain);

        fetch(mt_ajax_url, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.success) { 
                window.location.reload(); 
            } else { 
                errBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + (data.data || 'Failed to add domain.');
                errBox.classList.remove('hidden');
                btn.innerHTML = ogText; 
                btn.disabled = false; 
            }
        })
        .catch(err => {
            errBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Server error. Could not connect.';
            errBox.classList.remove('hidden');
            btn.innerHTML = ogText; 
            btn.disabled = false;
        });
    }

    function executeDomainDelete() {
        const id = document.getElementById('domain_to_delete').value;
        const btn = document.getElementById('btn_confirm_delete');
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Removing...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'mt_delete_domain');
        formData.append('security', mt_nonce);
        formData.append('domain_id', id);

        fetch(mt_ajax_url, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => { 
            if(data.success) { window.location.reload(); }
            else { alert("Error removing domain"); btn.innerHTML = 'Yes, Remove'; btn.disabled = false; }
        });
    }

    function triggerVerify() {
        const id = document.getElementById('verify_domain_id').value;
        const btn = document.getElementById('btn_verify');
        const msgBox = document.getElementById('verify_dns_msg');
        const ogText = btn.innerHTML;

        msgBox.classList.add('hidden');
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Querying DNS Servers...';
        btn.disabled = true;
        
        const formData = new FormData();
        formData.append('action', 'mt_verify_domain');
        formData.append('security', mt_nonce);
        formData.append('domain_id', id);

        fetch(mt_ajax_url, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            msgBox.classList.remove('hidden', 'bg-red-50', 'text-red-600', 'bg-green-50', 'text-green-600', 'border', 'border-red-200', 'border-green-200');
            
            if(data.success) { 
                msgBox.classList.add('bg-green-50', 'text-green-600', 'border', 'border-green-200');
                msgBox.innerHTML = '<i class="fa-solid fa-check-circle mr-2"></i>' + (data.data || "Verified!");
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Verified';
                setTimeout(() => window.location.reload(), 1500);
            } else { 
                msgBox.classList.add('bg-red-50', 'text-red-600', 'border', 'border-red-200');
                msgBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation mr-2"></i>' + (data.data || "Verification failed.");
                btn.innerHTML = ogText;
                btn.disabled = false;
            }
        })
        .catch(err => {
            msgBox.classList.remove('hidden');
            msgBox.classList.add('bg-red-50', 'text-red-600', 'border', 'border-red-200');
            msgBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation mr-2"></i> Server Error: Could not connect.';
            btn.innerHTML = ogText;
            btn.disabled = false;
        });
    }
</script>