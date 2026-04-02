<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$stores = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$wpdb->prefix}mt_stores WHERE brand_id = %d ORDER BY id ASC", $brand->id) );
$total_stores = count($stores);
$at_limit = ($brand->location_limit !== -1 && $total_stores >= $brand->location_limit);

// The global SaaS IP for the RADIUS Server (can be dynamic later)
$saas_radius_ip = "radius.mailtoucan.pro"; 
$brand_slug = sanitize_title($brand->brand_name); // Clean brand slug for URLs
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
    .tab-btn.active { border-bottom: 2px solid #4f46e5; color: #4f46e5; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    #map { height: 300px; width: 100%; border-radius: 0.5rem; z-index: 10; }
    
    /* Setup Modal Animations */
    #setup_modal { transition: opacity 0.3s ease; }
    #setup_modal.hidden { opacity: 0; pointer-events: none; }
    #setup_modal:not(.hidden) { opacity: 1; pointer-events: auto; }
</style>

<div id="view_list">
    <header class="mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Location Manager</h1>
            <p class="text-gray-500">Manage your physical venues, routers, and localized settings.</p>
        </div>
        <?php if ($at_limit): ?>
            <button class="bg-gray-300 text-gray-500 px-5 py-2.5 rounded-lg font-bold shadow-sm cursor-not-allowed flex items-center gap-2"><i class="fa-solid fa-lock"></i> Add Location (Limit Reached)</button>
        <?php else: ?>
            <button onclick="openEditor(0)" class="bg-gray-900 text-white px-5 py-2.5 rounded-lg font-bold shadow-md hover:bg-gray-800 transition flex items-center gap-2"><i class="fa-solid fa-plus"></i> Add New Location</button>
        <?php endif; ?>
    </header>

    <div class="grid grid-cols-3 gap-6">
        <?php if (empty($stores)): ?>
            <div class="col-span-3 text-center py-12 bg-white rounded-xl border border-dashed border-gray-300">
                <i class="fa-solid fa-store text-4xl text-gray-300 mb-3"></i><h3 class="text-lg font-bold text-gray-700">No Locations Yet</h3><p class="text-sm text-gray-500 mb-4">Click "Add New Location" to get started.</p>
            </div>
        <?php else: ?>
            <?php foreach ($stores as $store): 
                $config = json_decode($store->local_offer_json, true) ?: [];
                $address = isset($config['address']) && !empty($config['address']) ? $config['address'] : 'Address Unassigned';
                $hardware = isset($config['hardware']) && is_array($config['hardware']) ? $config['hardware'] : [];
                $has_routers = !empty($hardware);
            ?>
            <div class="bg-white rounded-xl shadow-sm border <?php echo $has_routers ? 'border-gray-200' : 'border-yellow-300'; ?> overflow-hidden hover:shadow-md transition">
                <div class="h-32 bg-gray-100 relative">
                    <?php if (isset($config['image']) && !empty($config['image'])): ?>
                        <img src="<?php echo esc_url($config['image']); ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center bg-gray-200"><i class="fa-solid fa-image text-gray-400 text-3xl"></i></div>
                    <?php endif; ?>
                    
                    <?php if ($has_routers): ?>
                        <div class="absolute top-3 right-3 bg-green-500 text-white text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wide shadow-sm flex items-center gap-1"><i class="fa-solid fa-circle-check text-[8px]"></i> Active</div>
                    <?php else: ?>
                        <div class="absolute top-3 right-3 bg-yellow-500 text-white text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wide shadow-sm flex items-center gap-1"><i class="fa-solid fa-triangle-exclamation"></i> Needs Setup</div>
                    <?php endif; ?>
                </div>
                <div class="p-5">
                    <h3 class="font-bold text-lg text-gray-900 mb-1 truncate"><?php echo esc_html($store->store_name); ?></h3>
                    <p class="text-sm text-gray-500 mb-4 truncate"><i class="fa-solid fa-location-dot mr-1"></i> <?php echo esc_html($address); ?></p>
                    <div class="flex justify-between items-center text-sm border-t pt-4">
                        <span class="<?php echo $has_routers ? 'text-gray-600' : 'text-yellow-600'; ?> font-semibold"><i class="fa-solid fa-router mr-1"></i> <?php echo count($hardware); ?> Devices</span>
                        
                        <div class="flex items-center gap-3">
                            <button onclick="deleteLocationList(<?php echo $store->id; ?>)" class="text-red-400 hover:text-red-600 transition" title="Delete Location"><i class="fa-solid fa-trash"></i></button>
                            <button onclick="openEditor(<?php echo $store->id; ?>)" class="text-blue-600 font-bold hover:underline" data-config='<?php echo esc_attr(wp_json_encode($config)); ?>' data-name="<?php echo esc_attr($store->store_name); ?>">Manage &rarr;</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="view_edit" style="display: none;">
    <header class="mb-6 flex justify-between items-center">
        <div>
            <button onclick="toggleView('list')" class="text-gray-500 hover:text-gray-900 font-bold text-sm mb-2">&larr; Back to Locations</button>
            <h1 class="text-2xl font-bold text-gray-900" id="edit_title">Configure Location</h1>
        </div>
        <div class="flex gap-2">
            <button id="btn_delete_loc" class="bg-red-50 text-red-600 border border-red-200 px-4 py-2.5 rounded-lg font-bold hover:bg-red-100 transition flex items-center gap-2 hidden" onclick="deleteLocationEditor()"><i class="fa-solid fa-trash"></i> Delete</button>
            <button id="btn_save_loc" class="bg-green-600 text-white px-6 py-2.5 rounded-lg font-bold shadow-md hover:bg-green-700 transition flex items-center gap-2" onclick="saveLocation()"><i class="fa-solid fa-floppy-disk"></i> Save Location</button>
        </div>
    </header>

    <div id="status_alert" class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6 rounded-r-lg shadow-sm hidden">
        <p class="text-yellow-700 font-bold text-sm"><i class="fa-solid fa-triangle-exclamation mr-2"></i> Action Required</p>
        <ul class="list-disc ml-6 mt-1 text-xs text-yellow-600"><li>You must assign at least one Router/Hardware device to activate WiFi tracking.</li></ul>
    </div>

    <input type="hidden" id="current_store_id" value="0">

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-20">
        
        <div class="flex border-b border-gray-200 bg-gray-50 px-4 pt-2 gap-4">
            <button class="tab-btn active px-4 py-3 font-bold text-sm text-gray-500 transition-colors" onclick="switchTab('general', this)">1. Venue Details</button>
            <button class="tab-btn px-4 py-3 font-bold text-sm text-gray-500 transition-colors" onclick="switchTab('wifi', this)">2. WiFi Limits & Splash</button>
            <button class="tab-btn px-4 py-3 font-bold text-sm text-gray-500 transition-colors" onclick="switchTab('hardware', this)">3. Hardware & Integration</button>
            <button class="tab-btn px-4 py-3 font-bold text-sm text-gray-500 transition-colors" onclick="switchTab('email', this)">4. Outbound Email Setup</button>
        </div>

        <div class="p-8">
            <div id="tab_general" class="tab-content active space-y-6">
                <div class="flex gap-6">
                    <div class="flex-1 space-y-6">
                        <div class="grid grid-cols-2 gap-6">
                            <div><label class="block text-sm font-bold text-gray-700 mb-2">Location Name</label><input type="text" id="loc_name" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-100 outline-none" oninput="updatePermanentUrl()"></div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Timezone</label>
                                <select id="loc_tz" class="w-full p-2 border border-gray-300 rounded bg-white outline-none">
                                    <option value="America/Port_of_Spain">America/Port_of_Spain (AST)</option>
                                    <option value="America/New_York">America/New_York (EST)</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-6">
                            <div><label class="block text-sm font-bold text-gray-700 mb-2">Location Email</label><input type="email" id="loc_email" class="w-full p-2 border border-gray-300 rounded outline-none"></div>
                            <div><label class="block text-sm font-bold text-gray-700 mb-2">Location Phone</label><input type="text" id="loc_phone" class="w-full p-2 border border-gray-300 rounded outline-none"></div>
                        </div>
                    </div>
                    <div class="w-1/3">
                        <label class="block text-sm font-bold text-gray-700 mb-2">Storefront Image</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center relative h-32 flex flex-col items-center justify-center bg-gray-50 overflow-hidden cursor-pointer hover:bg-gray-100 transition">
                            <img id="loc_img_preview" src="" class="absolute inset-0 w-full h-full object-cover hidden">
                            <div id="loc_img_placeholder"><i class="fa-solid fa-camera text-2xl text-gray-400 mb-2"></i><p class="text-xs text-gray-500">Upload to Vault</p></div>
                            <div id="loc_img_spin" class="absolute inset-0 bg-white/80 hidden flex items-center justify-center z-20"><i class="fa-solid fa-spinner fa-spin text-2xl text-indigo-500"></i></div>
                            <input type="file" id="loc_image" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-30">
                        </div>
                    </div>
                </div>
                <div class="border-t pt-6 mt-6">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Physical Address & Geolocation</label>
                    <div class="flex gap-2 mb-4">
                        <input type="text" id="loc_address" class="flex-1 p-2 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-indigo-100 outline-none">
                        <button onclick="searchAddress(event)" class="bg-gray-900 text-white px-4 py-2 rounded text-sm font-bold hover:bg-gray-800 transition"><i class="fa-solid fa-magnifying-glass"></i> Map It</button>
                    </div>
                    <div id="map" class="mb-2 border border-gray-300 shadow-inner"></div>
                </div>
            </div>

            <div id="tab_wifi" class="tab-content space-y-6">
                
                <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-6">
                    <h3 class="font-bold text-indigo-900 mb-2"><i class="fa-solid fa-mobile-screen mr-2"></i> Splash Screen Assignment</h3>
                    <p class="text-sm text-indigo-700 mb-4">Choose which login portal customers see at this location.</p>
                    <select id="loc_splash_assignment" class="w-full p-2 border border-indigo-300 rounded font-bold text-gray-800 outline-none">
                        <option value="global">🌍 Inherit Global Brand Splash Screen</option>
                        <option value="custom">🎨 Custom Splash Screen for this Location</option>
                    </select>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-lg p-6">
                    <h3 class="font-bold text-gray-900 mb-2"><i class="fa-solid fa-link text-indigo-500 mr-2"></i> Permanent Router URL (Splash Link)</h3>
                    <p class="text-sm text-gray-600 mb-4">This is the secure, unbreakable link for this specific location. Copy and paste this directly into your MikroTik router's Splash Page settings.</p>
                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-white p-3 border border-gray-300 rounded-lg font-mono text-sm text-gray-800 shadow-inner select-all overflow-hidden whitespace-nowrap overflow-ellipsis" id="lbl_permanent_url">Generating...</div>
                        <button onclick="copyRouterUrl()" class="bg-indigo-600 text-white px-4 py-3 rounded-lg font-bold shadow-md hover:bg-indigo-700 transition" title="Copy to clipboard"><i class="fa-regular fa-copy"></i></button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-8">
                    <div>
                        <h3 class="font-bold text-gray-800 border-b pb-2 mb-4">Traffic Shaping</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Session Time Limit</label>
                                <select id="loc_session_limit" class="w-full p-2 border border-gray-300 rounded outline-none">
                                    <option value="60">60 Minutes (Cafe Standard)</option><option value="120">2 Hours</option><option value="0">Unlimited</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Bandwidth Cap (Per User)</label>
                                <select id="loc_bandwidth" class="w-full p-2 border border-gray-300 rounded outline-none">
                                    <option value="5">5 Mbps (Prevents Netflix streaming)</option><option value="10">10 Mbps</option><option value="0">Unlimited (Dangerous)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800 border-b pb-2 mb-4">Operating Hours</h3>
                        <p class="text-xs text-gray-500 mb-4">Shut off the WiFi outside of business hours to prevent parking lot loitering.</p>
                        <div class="space-y-3">
                            <label class="flex items-center gap-2"><input type="checkbox" id="loc_auto_shutoff" class="rounded text-red-600 cursor-pointer w-4 h-4"> <span class="text-sm font-bold text-gray-700">Enable Auto-Shutoff</span></label>
                            <div class="flex items-center gap-4">
                                <div><label class="text-xs font-bold text-gray-500 block mb-1">Turn ON</label><input type="time" id="loc_time_on" class="border border-gray-300 rounded p-1 outline-none text-sm" value="08:00"></div>
                                <div><label class="text-xs font-bold text-gray-500 block mb-1">Turn OFF</label><input type="time" id="loc_time_off" class="border border-gray-300 rounded p-1 outline-none text-sm" value="22:00"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="tab_hardware" class="tab-content space-y-6">
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-6">
                    <div class="mb-4"><h3 class="font-bold text-gray-900">Add Access Point / Router</h3><p class="text-sm text-gray-500">Link your physical network devices to generate configuration credentials.</p></div>
                    <div class="grid grid-cols-12 gap-3 items-end">
                        <div class="col-span-4"><label class="text-[10px] font-bold text-gray-500 uppercase block mb-1">Device Name</label><input type="text" id="hw_name" class="w-full p-2 border border-gray-300 rounded text-sm outline-none font-bold text-gray-800" placeholder="e.g., Main Bar"></div>
                        <div class="col-span-4"><label class="text-[10px] font-bold text-gray-500 uppercase block mb-1">MAC Address <span class="text-red-500">*</span></label><input type="text" id="hw_mac" class="w-full p-2 border border-gray-300 rounded text-sm font-mono outline-none" placeholder="AA:BB:CC:DD:EE:FF" maxlength="17"></div>
                        <div class="col-span-3">
                            <label class="text-[10px] font-bold text-gray-500 uppercase block mb-1">Brand / Controller</label>
                            <select id="hw_brand" class="w-full p-2 border border-gray-300 rounded text-sm outline-none bg-white font-bold">
                                <option value="mikrotik">MikroTik</option><option value="unifi">Ubiquiti UniFi</option><option value="omada">TP-Link Omada</option><option value="meraki">Cisco Meraki</option>
                            </select>
                        </div>
                        <div class="col-span-1"><button onclick="addRouter()" class="w-full bg-indigo-600 text-white p-2 rounded text-sm font-bold hover:bg-indigo-700 transition">+</button></div>
                    </div>
                </div>
                
                <div class="bg-white border rounded-lg overflow-hidden">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 border-b"><tr>
                            <th class="p-3 font-bold text-gray-600 text-xs uppercase tracking-wide">Device Name</th>
                            <th class="p-3 font-bold text-gray-600 text-xs uppercase tracking-wide">MAC Address</th>
                            <th class="p-3 font-bold text-gray-600 text-xs uppercase tracking-wide">Brand</th>
                            <th class="p-3 font-bold text-gray-600 text-xs uppercase tracking-wide text-right">Actions / Setup</th>
                        </tr></thead>
                        <tbody id="mac_list_body"></tbody>
                    </table>
                </div>
            </div>

            <div id="tab_email" class="tab-content space-y-6">
                <div class="mb-4"><h2 class="text-lg font-bold text-gray-900">Email Marketing Delivery</h2><p class="text-sm text-gray-500">How should emails from this location be routed?</p></div>
                <div class="space-y-4">
                    <label class="flex items-start gap-4 p-4 border rounded-lg cursor-pointer hover:bg-gray-50"><input type="radio" name="email_sender" value="system" class="mt-1" checked><div><p class="font-bold text-sm">System Delivery (Recommended)</p></div></label>
                    <label class="flex items-start gap-4 p-4 border rounded-lg cursor-pointer hover:bg-gray-50"><input type="radio" name="email_sender" value="custom" class="mt-1"><div class="w-full"><p class="font-bold text-sm">Custom Domain</p></div></label>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="setup_modal" class="fixed inset-0 bg-gray-900/60 z-50 hidden flex items-center justify-center backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="p-6 border-b flex justify-between items-center bg-gray-50">
            <div>
                <h2 class="text-xl font-bold text-gray-900"><i class="fa-solid fa-server text-indigo-500 mr-2"></i> Configuration Guide</h2>
                <p class="text-sm text-gray-500 mt-1" id="setup_modal_subtitle">Enter these exact credentials into your router controller.</p>
            </div>
            <button onclick="closeSetupModal()" class="text-gray-400 hover:text-red-500 transition"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        
        <div class="p-6 overflow-y-auto flex-1 space-y-6">
            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                <h3 class="font-bold text-indigo-900 text-sm mb-3">1. RADIUS Authentication Server</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-[10px] uppercase font-bold text-indigo-700 mb-1">Primary IP / Hostname</label><div class="bg-white p-2 border border-indigo-200 rounded font-mono text-sm font-bold select-all"><?php echo esc_html($saas_radius_ip); ?></div></div>
                    <div><label class="block text-[10px] uppercase font-bold text-indigo-700 mb-1">Shared Secret</label><div id="setup_secret" class="bg-white p-2 border border-indigo-200 rounded font-mono text-sm font-bold text-red-600 select-all">...</div></div>
                    <div><label class="block text-[10px] uppercase font-bold text-indigo-700 mb-1">Auth Port</label><div class="bg-white p-2 border border-indigo-200 rounded font-mono text-sm select-all">1812</div></div>
                    <div><label class="block text-[10px] uppercase font-bold text-indigo-700 mb-1">Acct Port</label><div class="bg-white p-2 border border-indigo-200 rounded font-mono text-sm select-all">1813</div></div>
                </div>
            </div>

            <div class="bg-gray-50 border rounded-lg p-4">
                <h3 class="font-bold text-gray-800 text-sm mb-3">2. Identity Details</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-[10px] uppercase font-bold text-gray-500 mb-1">NAS Identifier / Called-Station-Id</label><div id="setup_mac" class="bg-white p-2 border rounded font-mono text-sm select-all">...</div></div>
                    <div><label class="block text-[10px] uppercase font-bold text-gray-500 mb-1">Splash Page URL (External Portal)</label><div id="setup_url" class="bg-white p-2 border rounded font-mono text-[10px] overflow-hidden text-ellipsis whitespace-nowrap text-indigo-600 underline cursor-pointer" onclick="navigator.clipboard.writeText(this.innerText); alert('Copied!');">...</div></div>
                </div>
            </div>

            <div class="border rounded-lg p-4">
                <h3 class="font-bold text-gray-800 text-sm mb-3">3. Walled Garden (Allowed Domains)</h3>
                <p class="text-xs text-gray-500 mb-3">Your router must allow free access to these domains before the user logs in, otherwise the Splash Screen and Social Logins will fail to load.</p>
                <div class="bg-gray-900 p-3 rounded font-mono text-xs text-green-400 select-all leading-relaxed">
                    *.mailtoucan.pro<br>*.googleapis.com<br>*.gstatic.com<br>*.facebook.com
                </div>
            </div>
        </div>
        
        <div class="p-4 border-t bg-gray-50 text-right">
            <button onclick="closeSetupModal()" class="bg-gray-900 text-white px-6 py-2 rounded font-bold hover:bg-gray-800 transition">Done</button>
        </div>
    </div>
</div>

<script>
    let currentStoreImage = '';
    let currentRouters = [];
    const brandSlug = "<?php echo $brand_slug; ?>";
    const baseUrl = "<?php echo home_url('/splash/'); ?>";

    function toggleView(view) {
        document.getElementById('view_list').style.display = view === 'list' ? 'block' : 'none';
        document.getElementById('view_edit').style.display = view === 'edit' ? 'block' : 'none';
        if(view === 'list') window.location.reload();
    }

    function switchTab(tab, element) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active', 'border-indigo-500', 'text-indigo-600'));
        element.classList.add('active', 'border-indigo-500', 'text-indigo-600');
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.getElementById('tab_' + tab).classList.add('active');
        if(tab === 'general') setTimeout(() => { if(map) map.invalidateSize(); }, 100);
    }

    function updatePermanentUrl() {
        const name = document.getElementById('loc_name').value.trim();
        const storeId = document.getElementById('current_store_id').value;
        let locSlug = 'pending-save';
        if(name) { locSlug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)+/g, ''); }
        const fullUrl = `${baseUrl}${brandSlug}/${locSlug}`;
        document.getElementById('lbl_permanent_url').innerText = fullUrl;
        return fullUrl;
    }

    function copyRouterUrl() {
        const url = document.getElementById('lbl_permanent_url').innerText;
        navigator.clipboard.writeText(url);
        alert("Router Splash URL Copied!");
    }

    function generateRadiusSecret() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
        let secret = '';
        for (let i = 0; i < 12; i++) secret += chars.charAt(Math.floor(Math.random() * chars.length));
        return secret;
    }

    document.getElementById('hw_mac').addEventListener('input', function (e) {
        let val = e.target.value.replace(/[^A-Fa-f0-9]/g, '').toUpperCase();
        let formatted = val.match(/.{1,2}/g)?.join(':') || '';
        e.target.value = formatted.substring(0, 17);
    });

    function openEditor(storeId) {
        document.getElementById('current_store_id').value = storeId;
        currentRouters = []; 
        
        if (storeId === 0) {
            document.getElementById('edit_title').innerText = 'Create New Location';
            document.getElementById('btn_delete_loc').classList.add('hidden');
            document.getElementById('status_alert').classList.add('hidden');
            document.getElementById('loc_name').value = '';
            document.getElementById('loc_email').value = '';
            document.getElementById('loc_phone').value = '';
            document.getElementById('loc_address').value = '';
            currentStoreImage = '';
            document.getElementById('loc_img_preview').classList.add('hidden');
            document.getElementById('loc_img_placeholder').classList.remove('hidden');
            document.getElementById('loc_splash_assignment').value = 'global';
            document.getElementById('loc_session_limit').value = '60';
            document.getElementById('loc_bandwidth').value = '5';
            document.getElementById('loc_auto_shutoff').checked = false;
        } else {
            const btn = event.currentTarget;
            const name = btn.getAttribute('data-name');
            let config = {};
            try { config = JSON.parse(btn.getAttribute('data-config') || '{}'); } catch(e) {}
            
            document.getElementById('edit_title').innerText = 'Editing: ' + name;
            document.getElementById('btn_delete_loc').classList.remove('hidden'); 
            
            document.getElementById('loc_name').value = name;
            if(config.email) document.getElementById('loc_email').value = config.email;
            if(config.phone) document.getElementById('loc_phone').value = config.phone;
            if(config.address) document.getElementById('loc_address').value = config.address;
            if(config.timezone) document.getElementById('loc_tz').value = config.timezone;
            
            if(config.wifi) {
                document.getElementById('loc_splash_assignment').value = config.wifi.splash_assignment || 'global';
                document.getElementById('loc_session_limit').value = config.wifi.session_limit || '60';
                document.getElementById('loc_bandwidth').value = config.wifi.bandwidth || '5';
                document.getElementById('loc_auto_shutoff').checked = config.wifi.auto_shutoff || false;
                if(config.wifi.time_on) document.getElementById('loc_time_on').value = config.wifi.time_on;
                if(config.wifi.time_off) document.getElementById('loc_time_off').value = config.wifi.time_off;
            }
            
            if(config.image) {
                currentStoreImage = config.image;
                document.getElementById('loc_img_preview').src = config.image;
                document.getElementById('loc_img_preview').classList.remove('hidden');
                document.getElementById('loc_img_placeholder').classList.add('hidden');
            } else {
                currentStoreImage = '';
                document.getElementById('loc_img_preview').classList.add('hidden');
                document.getElementById('loc_img_placeholder').classList.remove('hidden');
            }

            if(config.hardware) {
                currentRouters = config.hardware.map(hw => {
                    if(typeof hw === 'string') { return { id: 'rt_'+Math.floor(Math.random()*10000), name: 'Imported Device', mac: hw, brand: 'mikrotik', secret: generateRadiusSecret() }; }
                    return hw;
                });
            }
            
            if(currentRouters.length === 0) document.getElementById('status_alert').classList.remove('hidden');
            else document.getElementById('status_alert').classList.add('hidden');
        }
        
        updatePermanentUrl();
        renderMacList();
        toggleView('edit');
        setTimeout(initMap, 200);
    }

    function renderMacList() {
        const tbody = document.getElementById('mac_list_body');
        tbody.innerHTML = '';
        if(currentRouters.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="p-4 text-center text-sm text-gray-400 italic">No devices linked. Add your first router above.</td></tr>';
            return;
        }
        currentRouters.forEach((router, index) => {
            const tr = document.createElement('tr');
            tr.className = "border-b hover:bg-gray-50";
            let brandName = "MikroTik";
            if(router.brand === 'unifi') brandName = "Ubiquiti UniFi";
            if(router.brand === 'omada') brandName = "TP-Link Omada";
            if(router.brand === 'meraki') brandName = "Cisco Meraki";

            tr.innerHTML = `
                <td class="p-3 font-bold text-sm text-gray-800">${router.name}</td>
                <td class="p-3 font-mono text-sm text-gray-600">${router.mac}</td>
                <td class="p-3 text-sm text-gray-500"><span class="bg-gray-200 px-2 py-1 rounded text-xs font-bold">${brandName}</span></td>
                <td class="p-3 text-right flex justify-end gap-3 items-center">
                    <button class="text-indigo-600 font-bold text-xs hover:underline" onclick="openSetupModal(${index})"><i class="fa-solid fa-gear mr-1"></i> Setup Guide</button>
                    <button class="text-red-400 hover:text-red-600 font-bold text-xs transition" onclick="removeMac(${index})"><i class="fa-solid fa-trash"></i></button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function addRouter() {
        const nameInput = document.getElementById('hw_name');
        const macInput = document.getElementById('hw_mac');
        const brandInput = document.getElementById('hw_brand');
        
        const name = nameInput.value.trim() || 'Access Point ' + (currentRouters.length + 1);
        const mac = macInput.value.trim();
        const brand = brandInput.value;

        const macRegex = /^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/;
        if(!macRegex.test(mac)) { alert("Please enter a valid MAC address."); return; }
        if(currentRouters.length >= 3) { alert("Maximum 3 devices per location allowed on your plan."); return; }
        
        currentRouters.push({ id: 'rt_' + Date.now(), name: name, mac: mac, brand: brand, secret: generateRadiusSecret() });
        nameInput.value = ''; macInput.value = '';
        renderMacList();
        document.getElementById('status_alert').classList.add('hidden');
    }

    function removeMac(index) {
        if(!confirm("Remove this device? WiFi connections through this router will immediately fail.")) return;
        currentRouters.splice(index, 1);
        renderMacList();
    }

    function openSetupModal(index) {
        const router = currentRouters[index];
        const storeId = document.getElementById('current_store_id').value;
        const tenantDomain = window.location.hostname;
        
        document.getElementById('setup_secret').innerText = router.secret;
        document.getElementById('setup_mac').innerText = router.mac;
        document.getElementById('setup_url').innerText = updatePermanentUrl();

        document.getElementById('setup_modal').classList.remove('hidden');
    }

    function closeSetupModal() { document.getElementById('setup_modal').classList.add('hidden'); }

    document.getElementById('loc_image').addEventListener('change', async function(e) {
        if(e.target.files && e.target.files[0]) {
            const spin = document.getElementById('loc_img_spin');
            spin.classList.remove('hidden');
            
            const formData = new FormData();
            formData.append('action', 'mt_upload_vault_media');
            formData.append('security', mt_nonce);
            formData.append('media_type', 'wifi'); 
            formData.append('file', e.target.files[0]);

            try {
                const res = await fetch(mt_ajax_url, { method: 'POST', body: formData });
                const data = await res.json();
                if(data.success) {
                    currentStoreImage = data.data.url;
                    document.getElementById('loc_img_preview').src = currentStoreImage;
                    document.getElementById('loc_img_preview').classList.remove('hidden');
                    document.getElementById('loc_img_placeholder').classList.add('hidden');
                } else { alert("Upload Failed: " + data.data); }
            } catch(err) { alert("Server Error during upload."); }
            
            spin.classList.add('hidden'); e.target.value = '';
        }
    });

    function saveLocation() {
        const btn = document.getElementById('btn_save_loc');
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        const config = {
            email: document.getElementById('loc_email').value,
            phone: document.getElementById('loc_phone').value,
            address: document.getElementById('loc_address').value,
            timezone: document.getElementById('loc_tz').value,
            image: currentStoreImage,
            hardware: currentRouters,
            email_setup: document.querySelector('input[name="email_sender"]:checked') ? document.querySelector('input[name="email_sender"]:checked').value : 'system',
            wifi: {
                splash_assignment: document.getElementById('loc_splash_assignment').value,
                session_limit: document.getElementById('loc_session_limit').value,
                bandwidth: document.getElementById('loc_bandwidth').value,
                auto_shutoff: document.getElementById('loc_auto_shutoff').checked,
                time_on: document.getElementById('loc_time_on').value,
                time_off: document.getElementById('loc_time_off').value
            }
        };
        const formData = new FormData();
        formData.append('action', 'mt_save_location');
        formData.append('security', mt_nonce);
        formData.append('store_id', document.getElementById('current_store_id').value);
        formData.append('store_name', document.getElementById('loc_name').value);
        formData.append('config', JSON.stringify(config));
        
        fetch(mt_ajax_url, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Saved!';
                btn.classList.add('bg-green-700');
                if (document.getElementById('current_store_id').value == 0) {
                    document.getElementById('current_store_id').value = data.data.store_id; 
                    document.getElementById('btn_delete_loc').classList.remove('hidden');
                }
                setTimeout(() => { btn.innerHTML = original; btn.classList.remove('bg-green-700'); }, 2000);
            } else { alert(data.data); btn.innerHTML = original; }
        }).catch(err => { alert("Network Error: " + err); btn.innerHTML = original; });
    }

    function deleteLocationList(storeId) {
        if(!confirm("Are you sure you want to completely delete this location?")) return;
        const formData = new FormData();
        formData.append('action', 'mt_delete_location');
        formData.append('security', mt_nonce);
        formData.append('store_id', storeId);
        fetch(mt_ajax_url, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => { if(data.success) window.location.reload(); });
    }

    function deleteLocationEditor() { deleteLocationList(document.getElementById('current_store_id').value); }

    let map, marker;
    function initMap() {
        if (map) { map.invalidateSize(); return; } 
        const defaultLat = 10.6549; 
        const defaultLng = -61.5085;
        map = L.map('map').setView([defaultLat, defaultLng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
        marker = L.marker([defaultLat, defaultLng], {draggable: true}).addTo(map);
        
        marker.on('dragend', function(e) {
            let pos = marker.getLatLng();
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${pos.lat}&lon=${pos.lng}`)
            .then(res => res.json())
            .then(data => document.getElementById('loc_address').value = data.display_name || `${pos.lat}, ${pos.lng}`);
        });
    }

    function searchAddress(e) {
        if(e) e.preventDefault();
        let query = document.getElementById('loc_address').value;
        if(!query) return;
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(data => {
            if(data && data.length > 0) {
                map.setView([data[0].lat, data[0].lon], 15);
                marker.setLatLng([data[0].lat, data[0].lon]);
                document.getElementById('loc_address').value = data[0].display_name; 
            } else { alert("Could not find address."); }
        });
    }
</script>