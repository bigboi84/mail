<?php
/**
 * MailToucan Enterprise CRM v11.0
 * Features: Pagination, Bulk Actions, Soft-Delete Trash Bin, Restored Campaign Builder, API Toggle
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

if (isset($_POST['mt_api_toggle_action']) && isset($_POST['brand_id'])) {
    update_option('mt_api_sync_' . intval($_POST['brand_id']), sanitize_text_field($_POST['mt_api_toggle_action']));
    wp_send_json_success();
    exit;
}

$table_leads = $wpdb->prefix . 'mt_guest_leads';
$table_stores = $wpdb->prefix . 'mt_stores';
$table_campaigns = $wpdb->prefix . 'mt_campaigns';

// AUDIT FIX: Removed the slow database DELETE query that ran on every page load. It is now handled by class-mt-cron.php.

$leads = $wpdb->get_results( $wpdb->prepare("
    SELECT l.*, s.store_name 
    FROM $table_leads l
    LEFT JOIN $table_stores s ON l.store_id = s.id
    WHERE l.brand_id = %d 
    ORDER BY IFNULL(l.last_visit, l.created_at) DESC
", $brand->id) );

$campaigns = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table_campaigns WHERE brand_id = %d ORDER BY created_at DESC", $brand->id) );

$js_leads = [];
$active_count = 0;
$trashed_count = 0;
$unsub_count = 0; // AUDIT FIX: Declared missing variable

foreach($leads as $l) { 
    // AUDIT FIX: Calculate Live Network Status using the Transient
    $mac_clean = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $l->guest_mac ?? ''));
    $mac_colon = strlen($mac_clean) === 12 ? implode(':', str_split($mac_clean, 2)) : '';
    $session_end = $mac_colon ? get_transient('mt_wifi_session_' . md5($mac_colon)) : false;
    $l->online_until = ($session_end && $session_end > time()) ? $session_end : 0;

    $js_leads[$l->id] = $l;
    
    if($l->status === 'trashed') {
        $trashed_count++;
    } else {
        $active_count++;
        // AUDIT FIX: Calculate exact unsubs to make the UI stat correct
        if ($l->status === 'unsubscribed') {
            $unsub_count++;
        }
    }
}

$is_admin = current_user_can('manage_options') ? true : false;
$api_synced = get_option('mt_api_sync_' . $brand->id, 'no') === 'yes';
?>

<style>
    .crm-tab.active { border-bottom: 2px solid #4f46e5; color: #4f46e5; } .crm-view { display: none; } .crm-view.active { display: block; }
    .dr-tab.active { border-bottom: 2px solid #4f46e5; color: #4f46e5; } .dr-view { display: none; } .dr-view.active { display: block; }
    .drawer-scroll::-webkit-scrollbar { width: 4px; } .drawer-scroll::-webkit-scrollbar-track { background: transparent; } .drawer-scroll::-webkit-scrollbar-thumb { background-color: #e5e7eb; border-radius: 20px; }
    .timeline-container { position: relative; padding-left: 1.5rem; } .timeline-container::before { content: ''; position: absolute; left: 0.45rem; top: 0; bottom: 0; width: 2px; background: #e5e7eb; }
    .timeline-item { position: relative; margin-bottom: 1.5rem; } .timeline-icon { position: absolute; left: -1.5rem; top: 0; width: 1.5rem; height: 1.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; border: 2px solid white; z-index: 10; }
    .checkbox-custom { width: 1.1rem; height: 1.1rem; accent-color: #4f46e5; cursor: pointer; }
    .camp-type-view { display: none; } .camp-type-view.active { display: block; }
    .phone-mockup { border: 12px solid #1f2937; border-radius: 36px; height: 600px; width: 320px; background: #fff; position: relative; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); margin: 0 auto; }
    .phone-notch { position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 100px; height: 25px; background: #1f2937; border-bottom-left-radius: 16px; border-bottom-right-radius: 16px; z-index: 20;}
    .input-error { border-color: #ef4444 !important; background-color: #fef2f2 !important; }
    .img-upload-zone { position: relative; overflow: hidden; border: 1px solid #d1d5db; border-radius: 0.375rem; background: #f9fafb; text-align: center; cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100px; }
    .img-upload-zone img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: 10; }
    .img-upload-zone .rem-btn { position: absolute; top: 4px; right: 4px; background: rgba(239,68,68,0.9); color: white; border-radius: 50%; width: 24px; height: 24px; z-index: 40; display: flex; align-items: center; justify-content: center; font-size: 10px; cursor: pointer; }
    #confirm_modal { z-index: 9999 !important; }
</style>

<div id="mt_toast" class="fixed bottom-6 right-6 bg-gray-900 text-white px-5 py-3 rounded-lg shadow-2xl transform translate-y-20 opacity-0 transition-all duration-300 z-[9999] font-bold text-sm flex items-center gap-3">
    <i id="mt_toast_icon" class="fa-solid fa-circle-info"></i>
    <span id="mt_toast_msg">Message</span>
</div>

<div id="confirm_modal" class="fixed inset-0 bg-gray-900/60 hidden flex items-center justify-center backdrop-blur-sm transition-opacity opacity-0">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform scale-95 transition-all" id="confirm_modal_content">
        <div class="p-6 text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fa-solid fa-triangle-exclamation text-3xl text-red-600" id="confirm_icon"></i></div>
            <h2 class="text-xl font-bold text-gray-900 mb-2" id="confirm_title">Are you sure?</h2>
            <p class="text-sm text-gray-500 mb-6" id="confirm_desc">This action cannot be undone.</p>
            <div class="flex gap-3 justify-center">
                <button onclick="closeConfirmModal()" class="px-5 py-2.5 bg-gray-100 text-gray-700 font-bold rounded-lg hover:bg-gray-200 transition">Cancel</button>
                <button id="btn_confirm_action" class="px-5 py-2.5 bg-red-600 text-white font-bold rounded-lg shadow-md hover:bg-red-700 transition flex items-center gap-2">Confirm</button>
            </div>
        </div>
    </div>
</div>

<header class="mb-8 flex justify-between items-end">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">The Roost (CRM)</h1>
        <p class="text-gray-500 text-sm">Manage Guest Identities, Network Access, and Campaigns.</p>
    </div>
    <div class="flex gap-3">
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-1 flex items-center shadow-sm mr-4">
            <span class="text-[10px] font-bold text-gray-400 uppercase px-2">MealCrafter API:</span>
            <?php if($api_synced): ?>
                <button id="btn_api_toggle" onclick="toggleExternalAPI()" class="bg-green-50 border border-green-300 px-3 py-1 rounded text-xs font-bold text-green-700 hover:bg-red-50 hover:text-red-600 hover:border-red-300 transition">
                    <i class="fa-solid fa-check-circle text-green-500 mr-1" id="icon_api_toggle"></i> <span id="txt_api_toggle">Connected & Synced</span>
                </button>
            <?php else: ?>
                <button id="btn_api_toggle" onclick="toggleExternalAPI()" class="bg-white border border-gray-300 px-3 py-1 rounded text-xs font-bold text-gray-600 hover:bg-indigo-50 hover:text-indigo-600 transition">
                    <i class="fa-solid fa-plug text-gray-400 mr-1" id="icon_api_toggle"></i> <span id="txt_api_toggle">Connect to Sync</span>
                </button>
            <?php endif; ?>
        </div>
        <button onclick="exportLeadsCSV()" class="bg-white border border-gray-300 px-4 py-2 rounded-lg font-bold text-sm text-gray-600 hover:bg-gray-50 transition shadow-sm"><i class="fa-solid fa-file-csv text-green-600 mr-2"></i> Export CSV</button>
    </div>
</header>

<div class="grid grid-cols-4 gap-6 mb-6">
    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
        <p class="text-xs font-bold text-gray-400 uppercase mb-1">Total Network Guests</p>
        <p class="text-3xl font-bold text-gray-900"><?php echo number_format($active_count); ?></p>
    </div>
    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
        <p class="text-xs font-bold text-gray-400 uppercase mb-1">Active Subscribers</p>
        <p class="text-3xl font-bold text-green-600"><?php echo number_format($active_count - $unsub_count); ?></p>
    </div>
    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
        <p class="text-xs font-bold text-gray-400 uppercase mb-1">Unsubscribed</p>
        <p class="text-3xl font-bold text-red-500"><?php echo number_format($unsub_count); ?></p>
    </div>
    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
        <p class="text-xs font-bold text-gray-400 uppercase mb-1">Database Health</p>
        <p class="text-3xl font-bold text-indigo-600">Optimal</p>
    </div>
</div>

<div class="flex border-b border-gray-200 mb-6 gap-6">
    <button class="crm-tab active pb-3 font-bold text-sm text-gray-500 transition-colors" onclick="switchCrmTab('directory', this)"><i class="fa-solid fa-id-card-clip mr-2"></i> Guest Directory</button>
    <button class="crm-tab pb-3 font-bold text-sm text-gray-500 transition-colors" onclick="switchCrmTab('trash', this)"><i class="fa-solid fa-trash-can mr-2"></i> Trash Bin (<?php echo $trashed_count; ?>)</button>
    <button class="crm-tab pb-3 font-bold text-sm text-gray-500 transition-colors" onclick="switchCrmTab('campaigns', this)"><i class="fa-solid fa-tags mr-2"></i> Campaigns</button>
</div>

<div id="view_directory" class="crm-view active">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-20">
        
        <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
            <div class="flex gap-3 items-center">
                <select id="bulk_action_select" class="px-3 py-1.5 border rounded-lg text-sm outline-none bg-white font-bold text-gray-600">
                    <option value="">Bulk Actions</option>
                    <option value="trash">Move to Trash</option>
                </select>
                <button onclick="executeBulkAction()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1.5 rounded-lg text-sm font-bold transition">Apply</button>
            </div>
            <div class="flex gap-2 relative">
                <i class="fa-solid fa-search absolute left-3 top-2.5 text-gray-400"></i>
                <input type="text" id="crm_search" onkeyup="resetPaginationAndRender()" placeholder="Search email, name, or MAC..." class="pl-9 pr-3 py-2 border rounded-lg text-sm outline-none w-72 focus:ring-2 focus:ring-indigo-100 shadow-inner">
            </div>
        </div>
        
        <table class="w-full text-left border-collapse">
            <thead class="bg-white border-b text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                <tr>
                    <th class="p-4 w-12 text-center"><input type="checkbox" id="master_checkbox" class="checkbox-custom" onclick="toggleAllCheckboxes(this)"></th>
                    <th class="p-4">Guest Identity</th>
                    <th class="p-4">Last Known Location</th>
                    <th class="p-4">Network Status</th>
                    <th class="p-4">Profile Data</th>
                </tr>
            </thead>
            <tbody id="directory_tbody" class="divide-y divide-gray-100"></tbody>
        </table>

        <div class="p-4 border-t border-gray-200 bg-gray-50 flex justify-between items-center text-sm">
            <div class="text-gray-500 font-bold" id="dir_showing_text">Showing 0 records</div>
            <div class="flex items-center gap-4">
                <select id="dir_per_page" onchange="resetPaginationAndRender()" class="border rounded px-2 py-1 bg-white outline-none">
                    <option value="30">30 per page</option><option value="50">50 per page</option><option value="100">100 per page</option>
                </select>
                <div class="flex gap-1">
                    <button onclick="changePage(-1)" class="px-3 py-1 border bg-white rounded hover:bg-gray-100 font-bold text-gray-600">&larr; Prev</button>
                    <span class="px-3 py-1 font-bold text-gray-700" id="dir_page_text">Page 1 of 1</span>
                    <button onclick="changePage(1)" class="px-3 py-1 border bg-white rounded hover:bg-gray-100 font-bold text-gray-600">Next &rarr;</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="view_trash" class="crm-view">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-20">
        <div class="p-4 border-b border-gray-200 bg-red-50 flex justify-between items-center">
            <div>
                <h3 class="font-bold text-red-800"><i class="fa-solid fa-trash-can mr-2"></i> Trash Bin</h3>
                <p class="text-xs text-red-600 mt-1">Items here are permanently deleted automatically after 30 days.</p>
            </div>
            <button onclick="promptEmptyTrash()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-bold shadow transition"><i class="fa-solid fa-fire mr-2"></i> Empty Trash Now</button>
        </div>
        <table class="w-full text-left border-collapse">
            <thead class="bg-white border-b text-[11px] font-bold text-gray-400 uppercase tracking-wider"><tr><th class="p-4 pl-6">Guest Identity</th><th class="p-4">Date Deleted</th><th class="p-4">Actions</th></tr></thead>
            <tbody id="trash_tbody" class="divide-y divide-gray-100"></tbody>
        </table>
    </div>
</div>

<div id="guest_drawer_overlay" class="fixed inset-0 bg-gray-900/40 z-[100] hidden opacity-0 transition-opacity duration-300 backdrop-blur-sm" onclick="closeGuestDrawer()"></div>
<div id="guest_drawer" class="fixed top-0 right-0 h-full w-full max-w-lg bg-white shadow-2xl z-[101] transform translate-x-full transition-transform duration-300 flex flex-col border-l border-gray-200">
    <div class="p-6 border-b border-gray-200 bg-white flex justify-between items-start relative overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-50 rounded-bl-full -z-10"></div>
        <div class="flex gap-4 items-center z-10">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center text-2xl font-bold shadow-lg"><i class="fa-solid fa-user-astronaut"></i></div>
            <div>
                <h2 id="dr_name" class="text-2xl font-black text-gray-900 leading-tight">Guest Name</h2>
                <p id="dr_email" class="text-sm text-gray-500 font-medium flex items-center gap-1"><i class="fa-solid fa-envelope"></i> <span>email@example.com</span></p>
                <div id="dr_status_badge" class="mt-2 inline-block px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider bg-green-100 text-green-700">Subscribed</div>
            </div>
        </div>
        <button onclick="closeGuestDrawer()" class="text-gray-400 hover:text-gray-800 bg-white border rounded-lg p-2 shadow-sm transition z-10"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <div class="bg-gray-900 text-white p-4 flex justify-between items-center shadow-inner">
        <div>
            <p class="text-[10px] text-gray-400 font-bold uppercase mb-0.5"><i class="fa-solid fa-wifi text-green-400"></i> Live Network Status</p>
            <p id="dr_network_status" class="text-sm font-bold">Currently Offline</p>
        </div>
        <button id="btn_radius_extend" onclick="triggerRadiusExtend()" class="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold py-1.5 px-3 rounded shadow transition">
            + Add 1 Hour
        </button>
    </div>

    <div class="flex border-b border-gray-200 bg-gray-50 px-6 pt-2">
        <button class="dr-tab active px-4 py-2 text-sm font-bold text-gray-500 hover:text-gray-700 transition" onclick="switchDrawerTab('overview', this)"><i class="fa-solid fa-chart-pie mr-1"></i> Overview</button>
        <button class="dr-tab px-4 py-2 text-sm font-bold text-gray-500 hover:text-gray-700 transition" onclick="switchDrawerTab('timeline', this)"><i class="fa-solid fa-clock-rotate-left mr-1"></i> Timeline</button>
        <button id="dr_tab_rewards" class="dr-tab px-4 py-2 text-sm font-bold text-purple-600 hover:text-purple-800 transition <?php echo $api_synced ? '' : 'hidden'; ?>" onclick="switchDrawerTab('rewards', this)"><i class="fa-solid fa-crown mr-1"></i> Rewards</button>
    </div>

    <div class="flex-1 overflow-y-auto drawer-scroll p-6 bg-white">
        <div id="dr_view_overview" class="dr-view active space-y-6">
            <div class="bg-gray-50 border p-4 rounded-xl border-dashed">
                <h3 class="text-xs font-bold text-gray-500 uppercase mb-3"><i class="fa-solid fa-link"></i> Linked Identities</h3>
                <div class="flex justify-between items-center bg-white p-2 rounded border shadow-sm mb-2">
                    <span class="text-xs font-mono text-gray-600 font-bold" id="dr_mac">AA:BB:CC:DD:EE:FF</span>
                    <span class="text-[10px] bg-blue-100 text-blue-700 px-2 py-0.5 rounded font-bold uppercase">Primary Device</span>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-gray-50 border p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-bold uppercase mb-1">First Seen</p><div id="dr_first_date" class="text-sm font-bold text-gray-800"></div></div>
                <div class="bg-gray-50 border p-3 rounded-lg"><p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Last Location</p><div id="dr_location" class="text-sm font-bold text-gray-800"></div></div>
            </div>
            <div id="dr_data_section">
                <h3 class="font-bold text-gray-900 border-b pb-2 mb-4 flex justify-between items-center"><span><i class="fa-solid fa-database text-indigo-500 mr-2"></i> Marketing Data</span></h3>
                <div id="dr_data_cards" class="space-y-3"></div>
                <div id="dr_data_empty" class="hidden text-center py-6 bg-gray-50 rounded-lg border border-dashed border-gray-200"><p class="text-xs text-gray-400 font-bold italic">No marketing data captured for this guest yet.</p></div>
            </div>
        </div>

        <div id="dr_view_timeline" class="dr-view">
            <h3 class="font-bold text-gray-900 mb-6 flex justify-between items-center"><span>Guest History</span><span class="text-xs font-normal text-gray-500 bg-gray-100 px-2 py-1 rounded">Chronological</span></h3>
            <div id="dr_timeline_container" class="timeline-container"></div>
        </div>

        <div id="dr_view_rewards" class="dr-view space-y-6 hidden">
            <div class="bg-gradient-to-br from-purple-600 to-indigo-700 rounded-xl p-6 text-white shadow-lg relative overflow-hidden">
                <i class="fa-solid fa-store absolute -right-4 -bottom-4 text-white opacity-10 text-8xl"></i>
                <h3 class="font-bold text-lg mb-1 relative z-10"><i class="fa-solid fa-crown text-yellow-300 mr-2"></i> External API Rewards</h3>
                <p class="text-xs text-purple-200 mb-4 relative z-10">Synced via email address</p>
                <div class="bg-black/20 rounded-lg p-4 inline-block relative z-10 backdrop-blur-sm border border-white/10">
                    <p class="text-[10px] uppercase font-bold text-purple-200 tracking-wider mb-1">Current Balance</p>
                    <p class="text-4xl font-black" id="woo_pts_balance">--</p>
                </div>
                <div class="mt-6 flex justify-between items-end relative z-10">
                    <p class="text-[10px] text-purple-200 font-bold">Last Sync: <span id="woo_last_sync">Never</span></p>
                    <button id="btn_woo_sync" onclick="triggerWooSync()" class="bg-white text-purple-700 px-4 py-2 rounded-lg font-bold text-xs shadow hover:bg-gray-50 transition flex items-center gap-2"><i class="fa-solid fa-rotate"></i> Force Sync</button>
                </div>
            </div>
            <div>
                <h3 class="font-bold text-gray-900 border-b pb-2 mb-4 text-sm">Reward Adjustments</h3>
                <div class="flex gap-2">
                    <input type="number" id="woo_pts_input" placeholder="Points" class="w-1/3 px-3 py-2 border rounded-lg text-sm outline-none focus:ring-2 focus:ring-purple-200" disabled>
                    <select class="w-1/3 px-3 py-2 border rounded-lg text-sm outline-none bg-gray-50" disabled><option>Add</option><option>Deduct</option></select>
                    <button class="w-1/3 bg-gray-800 text-gray-500 font-bold text-sm rounded-lg cursor-not-allowed">Phase 3</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="p-4 border-t bg-gray-50 flex justify-between items-center">
        <input type="hidden" id="dr_lead_id">
        <?php if($is_admin): ?>
            <button onclick="promptTrashGuest()" class="text-red-500 hover:text-red-700 font-bold text-xs flex items-center gap-1 transition"><i class="fa-solid fa-trash"></i> Move to Trash</button>
        <?php else: ?>
            <span class="text-[10px] text-gray-400 italic">Delete restricted.</span>
        <?php endif; ?>
    </div>
</div>

<div id="view_campaigns" class="crm-view">
    <div id="camp_list_state">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-lg font-bold text-gray-900">Active Campaigns</h2>
            <button onclick="openCampaignEditor(0)" class="bg-indigo-600 text-white px-4 py-2 rounded-lg font-bold text-sm shadow-md hover:bg-indigo-700 transition flex items-center gap-2">
                <i class="fa-solid fa-plus"></i> Create New Campaign
            </button>
        </div>

        <div class="grid grid-cols-3 gap-6">
            <?php if(empty($campaigns)): ?>
                <div class="col-span-3 text-center py-12 bg-white rounded-xl border border-dashed border-gray-300">
                    <i class="fa-solid fa-tags text-4xl text-gray-300 mb-3"></i>
                    <h3 class="text-lg font-bold text-gray-700">No Campaigns Created</h3>
                    <p class="text-sm text-gray-500">Create a Survey or Gamification module to capture data.</p>
                </div>
            <?php else: ?>
                <?php foreach($campaigns as $camp): 
                    $type_label = ''; $icon = '';
                    if($camp->campaign_type == 'survey') { $type_label = 'Advanced Survey'; $icon = 'fa-clipboard-list text-blue-500'; }
                    if($camp->campaign_type == 'promo') { $type_label = 'Image Promo'; $icon = 'fa-image text-purple-500'; }
                    if($camp->campaign_type == 'versus') { $type_label = 'A/B Versus'; $icon = 'fa-code-compare text-orange-500'; }
                    if($camp->campaign_type == 'birthday') { $type_label = 'Birthday Collector'; $icon = 'fa-cake-candles text-pink-500'; }
                    if($camp->campaign_type == 'wheel') { $type_label = 'Spin the Wheel'; $icon = 'fa-compact-disc text-yellow-500'; }
                    if($camp->campaign_type == 'box') { $type_label = 'Mystery Box'; $icon = 'fa-box-open text-green-500'; }

                    // AUDIT FIX: Process campaign status directly in the card loop
                    $now = current_time('timestamp');
                    $camp_status = 'live';
                    $c_conf = json_decode($camp->config_json, true) ?: [];
                    if (!empty($c_conf['schedule']['start']) && strtotime($c_conf['schedule']['start']) > $now) $camp_status = 'scheduled';
                    if (!empty($c_conf['schedule']['end'])   && strtotime($c_conf['schedule']['end'])   < $now) $camp_status = 'ended';
                    $status_badge = [
                        'live'      => ['bg' => 'bg-green-100',  'text' => 'text-green-700',  'label' => 'Live'],
                        'scheduled' => ['bg' => 'bg-blue-100',   'text' => 'text-blue-700',   'label' => 'Scheduled'],
                        'ended'     => ['bg' => 'bg-gray-100',   'text' => 'text-gray-500',   'label' => 'Ended'],
                    ][$camp_status];
                ?>
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition">
                    <div class="flex justify-between items-start mb-4">
                        <div class="bg-gray-50 p-2 rounded-lg border"><i class="fa-solid <?php echo $icon; ?> text-xl"></i></div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded <?php echo $status_badge['bg']; ?> <?php echo $status_badge['text']; ?>"><?php echo $status_badge['label']; ?></span>
                    </div>
                    <h3 class="font-bold text-lg text-gray-900 truncate"><?php echo esc_html($camp->campaign_name); ?></h3>
                    <p class="text-xs font-bold text-gray-500 uppercase mb-4"><?php echo $type_label; ?></p>
                    <div class="border-t pt-4 flex justify-between">
                        <button onclick="promptDeleteCampaign(<?php echo $camp->id; ?>)" class="text-red-400 hover:text-red-600 text-sm font-bold"><i class="fa-solid fa-trash"></i></button>
                        <button onclick="openCampaignEditor(<?php echo $camp->id; ?>)" data-config='<?php echo esc_attr($camp->config_json); ?>' data-name="<?php echo esc_attr($camp->campaign_name); ?>" data-type="<?php echo esc_attr($camp->campaign_type); ?>" class="text-indigo-600 font-bold text-sm hover:underline">Edit Content &rarr;</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="camp_edit_state" class="hidden">
        <div id="camp_error_banner" class="hidden mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded shadow-sm flex justify-between items-center transition-all">
            <span class="text-red-700 text-sm font-bold"><i class="fa-solid fa-triangle-exclamation mr-2"></i> <span id="camp_error_text">Please fill out all required fields marked with an asterisk (*).</span></span>
            <button onclick="document.getElementById('camp_error_banner').classList.add('hidden')" class="text-red-400 hover:text-red-600"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-20">
            <div class="p-6 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                <div>
                    <button onclick="closeCampaignEditor()" class="text-gray-500 hover:text-gray-900 font-bold text-sm mb-1">&larr; Back</button>
                    <h2 class="text-xl font-bold text-gray-900" id="camp_editor_title">Create Campaign</h2>
                </div>
                <button id="btn_save_camp" onclick="saveCampaign()" class="bg-indigo-600 text-white px-6 py-2.5 rounded-lg font-bold shadow-md hover:bg-indigo-700 transition flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i> Save Campaign
                </button>
            </div>
            
            <div class="flex gap-8 p-8">
                <div class="w-1/2">
                    <input type="hidden" id="current_camp_id" value="0">
                    
                    <div class="mb-6">
                        <label class="block text-sm font-bold text-gray-700 mb-2">Campaign Name <span class="text-red-500">*</span></label>
                        <input type="text" id="camp_name" class="w-full p-3 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-indigo-100 font-bold text-gray-900 text-lg required-field" placeholder="e.g., Summer Promo">
                    </div>

                    <div class="mb-6 bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <h3 class="text-sm font-bold text-gray-800 mb-3"><i class="fa-regular fa-calendar-clock text-indigo-500 mr-2"></i> Campaign Schedule</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Start Date & Time</label><input type="datetime-local" id="camp_start_date" class="w-full p-2 border border-gray-300 rounded text-sm outline-none bg-white"></div>
                            <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">End Date & Time</label><input type="datetime-local" id="camp_end_date" class="w-full p-2 border border-gray-300 rounded text-sm outline-none bg-white"></div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-bold text-gray-700 mb-2">Campaign Type</label>
                        <select id="camp_type" class="w-full p-2.5 border border-gray-300 rounded-lg outline-none bg-white font-bold text-indigo-700" onchange="switchCampTypeView()">
                            <optgroup label="Data Collection">
                                <option value="survey">📝 Advanced Survey / Form</option><option value="versus">⚔️ Pick A Side (Versus)</option><option value="birthday">🎂 Birthday Collector</option>
                            </optgroup>
                            <optgroup label="Gamification & Offers">
                                <option value="promo">🖼️ Image Promo / Coupon</option><option value="wheel">🎡 Spin the Wheel</option><option value="box">🎁 Mystery Box</option>
                            </optgroup>
                        </select>
                    </div>

                    <div class="border-t border-gray-200 pt-6">
                        <div id="ctype_survey" class="camp-type-view active space-y-4">
                            <h3 class="font-bold text-gray-800 mb-4"><i class="fa-solid fa-clipboard-list text-blue-500 mr-2"></i> Survey Questions</h3>
                            <label class="flex items-center gap-2 text-sm font-bold text-gray-700 bg-gray-50 p-3 rounded-lg border mb-4 cursor-pointer"><input type="checkbox" id="camp_stars" class="w-4 h-4 text-indigo-600 rounded" checked onchange="updateLivePreview()"> Ask for 5-Star Rating first</label>
                            <?php for($i=1; $i<=4; $i++): $req = ($i===1) ? '<span class="text-red-500">*</span>' : ''; ?>
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-3">
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Question <?php echo $i; ?> <?php echo $req; ?></label>
                                <input type="text" id="camp_q<?php echo $i; ?>_text" class="w-full p-2 border border-gray-300 rounded outline-none text-sm mb-2 <?php echo ($i===1)?'required-field':'';?>" placeholder="Enter your question here..." oninput="updateLivePreview()">
                                <div class="flex gap-3">
                                    <select id="camp_q<?php echo $i; ?>_type" class="w-1/3 p-2 border border-gray-300 rounded text-sm outline-none bg-white" onchange="toggleQOpts(<?php echo $i; ?>)"><option value="text">Text Input</option><option value="radio">Pick One</option><option value="checkbox">Select Multiple</option></select>
                                    <input type="text" id="camp_q<?php echo $i; ?>_opts" class="w-2/3 p-2 border border-gray-300 rounded text-sm outline-none hidden" placeholder="Options (comma separated)" oninput="updateLivePreview()">
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>

                        <div id="ctype_promo" class="camp-type-view space-y-4">
                            <h3 class="font-bold text-gray-800 mb-4"><i class="fa-solid fa-image text-purple-500 mr-2"></i> Promotional Content</h3>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Headline <span class="text-red-500">*</span></label>
                            <textarea id="camp_promo_text" rows="2" class="w-full p-2.5 border border-gray-300 rounded-lg text-sm outline-none resize-none required-field" placeholder="Show this screen for 10% off!" oninput="updateLivePreview()"></textarea>
                            <label class="block text-sm font-bold text-gray-700 mt-4 mb-2">Upload Promo Image</label>
                            <div class="img-upload-zone h-40 max-w-sm" id="zone_promo"><span class="text-xs text-gray-500 font-bold" id="lbl_promo"><i class="fa-solid fa-cloud-arrow-up mr-1"></i> Upload Image</span><img id="img_promo" src="" class="hidden"><button class="rem-btn hidden" id="rem_promo" onclick="removeCampImage(event, 'promo')"><i class="fa-solid fa-xmark"></i></button><input type="file" id="input_promo_img" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-30"></div>
                        </div>

                        <div id="ctype_versus" class="camp-type-view space-y-4">
                            <h3 class="font-bold text-gray-800 mb-4"><i class="fa-solid fa-code-compare text-orange-500 mr-2"></i> A/B Options</h3>
                            <div class="flex gap-4 items-center mb-6">
                                <div class="flex-1"><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Main Title <span class="text-red-500">*</span></label><input type="text" id="camp_vs_title" class="w-full p-2.5 border border-gray-300 rounded-lg text-sm font-bold outline-none required-field" placeholder="Pick a side:" oninput="updateLivePreview()"></div>
                                <div class="w-24"><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Divider</label><input type="text" id="camp_vs_mid" class="w-full p-2.5 text-center border border-gray-300 rounded-lg font-bold text-sm outline-none bg-gray-50" value="VS" oninput="updateLivePreview()"></div>
                            </div>
                            <div class="grid grid-cols-2 gap-6">
                                <div class="bg-gray-50 p-4 border rounded-lg">
                                    <label class="text-[10px] font-bold text-gray-500 uppercase block mb-2">Option A Label <span class="text-red-500">*</span></label><input type="text" id="camp_vs_a" class="w-full p-2 border border-gray-300 rounded mb-3 text-sm outline-none required-field" placeholder="e.g., I Like Meat" oninput="updateLivePreview()">
                                    <div class="img-upload-zone h-32" id="zone_vsa"><span class="text-[10px] text-gray-500 font-bold"><i class="fa-solid fa-image block text-lg mb-1"></i> Image A</span><img id="img_vsa" src="" class="hidden"><button class="rem-btn hidden" id="rem_vsa" onclick="removeCampImage(event, 'vsa')"><i class="fa-solid fa-xmark"></i></button><input type="file" id="input_vsa_img" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-30"></div>
                                </div>
                                <div class="bg-gray-50 p-4 border rounded-lg">
                                    <label class="text-[10px] font-bold text-gray-500 uppercase block mb-2">Option B Label <span class="text-red-500">*</span></label><input type="text" id="camp_vs_b" class="w-full p-2 border border-gray-300 rounded mb-3 text-sm outline-none required-field" placeholder="e.g., I am Vegan" oninput="updateLivePreview()">
                                    <div class="img-upload-zone h-32" id="zone_vsb"><span class="text-[10px] text-gray-500 font-bold"><i class="fa-solid fa-image block text-lg mb-1"></i> Image B</span><img id="img_vsb" src="" class="hidden"><button class="rem-btn hidden" id="rem_vsb" onclick="removeCampImage(event, 'vsb')"><i class="fa-solid fa-xmark"></i></button><input type="file" id="input_vsb_img" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-30"></div>
                                </div>
                            </div>
                        </div>

                        <div id="ctype_birthday" class="camp-type-view space-y-4">
                            <h3 class="font-bold text-gray-800 mb-4"><i class="fa-solid fa-cake-candles text-pink-500 mr-2"></i> Birthday Text</h3>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Prompt Text <span class="text-red-500">*</span></label><input type="text" id="camp_bday_text" class="w-full p-2.5 border border-gray-300 rounded-lg text-sm outline-none required-field" placeholder="e.g., When is your birthday?" oninput="updateLivePreview()">
                        </div>

                        <div id="ctype_wheel" class="camp-type-view space-y-4">
                            <h3 class="font-bold text-gray-800 mb-2"><i class="fa-solid fa-compact-disc text-yellow-500 mr-2"></i> Spin the Wheel Inventory</h3>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Wheel Title <span class="text-red-500">*</span></label>
                            <input type="text" id="camp_wheel_title" class="w-full p-2.5 border border-gray-300 rounded-lg text-sm outline-none required-field mb-4" placeholder="e.g., Spin to Win a Prize!" oninput="updateLivePreview()">
                            <div class="space-y-3 bg-gray-50 p-4 border rounded-lg">
                                <div class="grid grid-cols-12 gap-2 text-[10px] font-bold text-gray-500 uppercase px-1">
                                    <div class="col-span-5">Prize Name</div>
                                    <div class="col-span-3">Limit (0=Unl)</div>
                                    <div class="col-span-4">Unlock Date</div>
                                </div>
                                <?php for($i=1; $i<=6; $i++): $req = ($i<=2) ? 'required-field' : ''; ?>
                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <div class="col-span-5"><input type="text" id="wheel_p<?php echo $i; ?>_name" class="w-full p-2 border border-gray-300 rounded text-sm outline-none <?php echo $req; ?>" placeholder="<?php echo $i<=2 ? 'Required' : 'Optional'; ?>" oninput="updateLivePreview()"></div>
                                    <div class="col-span-3"><input type="number" id="wheel_p<?php echo $i; ?>_limit" class="w-full p-2 border border-gray-300 rounded text-sm outline-none" min="0" value="0"></div>
                                    <div class="col-span-4"><input type="date" id="wheel_p<?php echo $i; ?>_unlock" class="w-full p-2 border border-gray-300 rounded text-[10px] text-gray-500 outline-none" title="Leave blank to unlock immediately"></div>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div id="ctype_box" class="camp-type-view space-y-4">
                            <h3 class="font-bold text-gray-800 mb-2"><i class="fa-solid fa-box-open text-green-500 mr-2"></i> Mystery Box Inventory</h3>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Box Title <span class="text-red-500">*</span></label>
                            <input type="text" id="camp_box_title" class="w-full p-2.5 border border-gray-300 rounded-lg text-sm outline-none required-field mb-4" placeholder="e.g., Tap the box to reveal your mystery gift!" oninput="updateLivePreview()">
                            <div class="space-y-3 bg-gray-50 p-4 border rounded-lg">
                                <div class="grid grid-cols-12 gap-2 text-[10px] font-bold text-gray-500 uppercase px-1">
                                    <div class="col-span-5">Prize Name</div>
                                    <div class="col-span-3">Limit (0=Unl)</div>
                                    <div class="col-span-4">Unlock Date</div>
                                </div>
                                <?php for($i=1; $i<=4; $i++): $req = ($i<=2) ? 'required-field' : ''; ?>
                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <div class="col-span-5"><input type="text" id="box_p<?php echo $i; ?>_name" class="w-full p-2 border border-gray-300 rounded text-sm outline-none <?php echo $req; ?>" placeholder="<?php echo $i<=2 ? 'Required' : 'Optional'; ?>" oninput="updateLivePreview()"></div>
                                    <div class="col-span-3"><input type="number" id="box_p<?php echo $i; ?>_limit" class="w-full p-2 border border-gray-300 rounded text-sm outline-none" min="0" value="0"></div>
                                    <div class="col-span-4"><input type="date" id="box_p<?php echo $i; ?>_unlock" class="w-full p-2 border border-gray-300 rounded text-[10px] text-gray-500 outline-none" title="Leave blank to unlock immediately"></div>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="w-1/2 flex justify-center bg-gray-50 rounded-xl p-8 border border-gray-200">
                    <div class="phone-mockup flex flex-col items-center justify-center p-6 text-center">
                        <div class="phone-notch"></div>
                        <div class="w-full bg-white p-6 rounded-xl shadow-lg border border-gray-100 z-10">
                            <div id="prev_type_survey" class="w-full text-left hidden">
                                <div id="prev_stars" class="text-center mb-4"><h2 class="text-sm font-bold text-gray-900 mb-2">Rate your experience:</h2><div class="flex gap-2 justify-center text-gray-300"><i class="fa-solid fa-star text-2xl"></i><i class="fa-solid fa-star text-2xl"></i><i class="fa-solid fa-star text-2xl"></i><i class="fa-solid fa-star text-2xl"></i><i class="fa-solid fa-star text-2xl"></i></div></div>
                                <div id="prev_survey_container" class="space-y-3"></div>
                            </div>
                            <div id="prev_type_promo" class="w-full hidden">
                                <div class="bg-gray-100 w-full rounded-lg overflow-hidden flex items-center justify-center text-gray-300 font-bold min-h-[120px] mb-4"><img id="prev_img_promo" src="" class="w-full h-full object-cover hidden"><i class="fa-solid fa-image text-3xl" id="prev_icon_promo"></i></div>
                                <h2 id="prev_promo_title" class="text-lg font-bold text-gray-900 whitespace-pre-wrap leading-tight">Promo Headline</h2>
                            </div>
                            <div id="prev_type_versus" class="w-full hidden">
                                <h2 id="prev_vs_title" class="text-lg font-bold text-gray-900 whitespace-pre-wrap leading-tight mb-4">Pick a side:</h2>
                                <div class="flex items-center justify-center gap-2">
                                    <button class="flex-1 flex flex-col items-center border-2 border-gray-200 bg-gray-50 rounded-xl overflow-hidden shadow-sm"><img id="prev_img_vsa" src="" class="w-full h-20 object-cover hidden"><span id="prev_vs_a" class="font-bold py-2 text-[10px] text-gray-700 leading-tight">Option A</span></button>
                                    <span id="prev_vs_mid" class="text-xs font-bold text-gray-400">VS</span>
                                    <button class="flex-1 flex flex-col items-center border-2 border-gray-200 bg-gray-50 rounded-xl overflow-hidden shadow-sm"><img id="prev_img_vsb" src="" class="w-full h-20 object-cover hidden"><span id="prev_vs_b" class="font-bold py-2 text-[10px] text-gray-700 leading-tight">Option B</span></button>
                                </div>
                            </div>
                            <div id="prev_type_birthday" class="w-full hidden">
                                <h2 id="prev_bday_text" class="text-lg font-bold text-gray-900 whitespace-pre-wrap leading-tight mb-4">When is your birthday?</h2>
                                <input type="date" class="w-full px-4 py-3 border rounded-lg text-center text-gray-600 font-bold" disabled>
                            </div>
                            <div id="prev_type_wheel" class="w-full hidden text-center">
                                <h2 id="prev_wheel_title" class="text-lg font-bold text-gray-900 whitespace-pre-wrap leading-tight mb-6">Spin to Win!</h2>
                                <div class="text-6xl text-indigo-500 mb-6"><i class="fa-solid fa-compact-disc"></i></div>
                                <button class="mb-4 bg-indigo-600 text-white font-bold py-3 px-8 rounded-full shadow-lg">SPIN NOW</button>
                                <div id="prev_wheel_list" class="mt-4 text-[10px] text-gray-500 font-bold uppercase leading-tight space-y-1"></div>
                            </div>
                            <div id="prev_type_box" class="w-full hidden text-center">
                                <h2 id="prev_box_title" class="text-lg font-bold text-gray-900 whitespace-pre-wrap leading-tight mb-6">Open the Mystery Box!</h2>
                                <div class="text-7xl text-yellow-500 hover:scale-110 transition-transform cursor-pointer drop-shadow-xl mb-6"><i class="fa-solid fa-box"></i></div>
                                <button class="mt-2 bg-indigo-600 text-white font-bold py-3 px-8 rounded-full shadow-lg">TAP TO REVEAL</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const mt_leads_data = <?php echo wp_json_encode($js_leads); ?>;
    let externalApiActive = <?php echo $api_synced ? 'true' : 'false'; ?>;
    
    let currentPage = 1;
    let filteredActiveRows = [];
    let filteredTrashRows = [];

    document.addEventListener("DOMContentLoaded", () => {
        resetPaginationAndRender();
    });

    function showToast(msg, type = 'info') {
        const toast = document.getElementById('mt_toast');
        document.getElementById('mt_toast_msg').innerText = msg;
        const icon = document.getElementById('mt_toast_icon');
        
        if(type === 'error') { icon.className = 'fa-solid fa-circle-exclamation text-red-400'; }
        else if(type === 'success') { icon.className = 'fa-solid fa-circle-check text-green-400'; }
        else { icon.className = 'fa-solid fa-circle-info text-blue-400'; }

        toast.classList.remove('translate-y-20', 'opacity-0');
        setTimeout(() => { toast.classList.add('translate-y-20', 'opacity-0'); }, 3000);
    }

    function openConfirmModal() {
        const modal = document.getElementById('confirm_modal');
        const content = document.getElementById('confirm_modal_content');
        modal.classList.remove('hidden');
        setTimeout(() => { modal.classList.remove('opacity-0'); content.classList.remove('scale-95'); }, 10);
    }

    function closeConfirmModal() {
        const modal = document.getElementById('confirm_modal');
        const content = document.getElementById('confirm_modal_content');
        content.classList.add('scale-95');
        modal.classList.add('opacity-0');
        setTimeout(() => { modal.classList.add('hidden'); }, 300);
    }

    function switchCrmTab(tab, el) {
        document.querySelectorAll('.crm-tab').forEach(b => b.classList.remove('active', 'border-indigo-600', 'text-indigo-600'));
        el.classList.add('active', 'border-indigo-600', 'text-indigo-600');
        document.querySelectorAll('.crm-view').forEach(v => v.classList.remove('active'));
        document.getElementById('view_' + tab).classList.add('active');
        if(tab === 'directory' || tab === 'trash') resetPaginationAndRender();
    }

    // AUDIT FIX: Improved search to include MAC addresses
    function resetPaginationAndRender() {
        currentPage = 1;
        const mc = document.getElementById('master_checkbox');
        if(mc) mc.checked = false;
        
        const searchInput = document.getElementById('crm_search');
        const searchStr = searchInput ? searchInput.value.toLowerCase() : '';
        const rawSearch = searchStr.replace(/[^a-f0-9]/gi,'');
        
        filteredActiveRows = [];
        filteredTrashRows = [];

        Object.values(mt_leads_data).forEach(lead => {
            const macSearch = (lead.guest_mac || '').toLowerCase().replace(/[^a-f0-9]/gi,'');
            const match = lead.email.toLowerCase().includes(searchStr) 
                       || (lead.guest_name && lead.guest_name.toLowerCase().includes(searchStr))
                       || (rawSearch.length >= 4 && macSearch.includes(rawSearch));
            if(match) {
                if(lead.status === 'trashed') filteredTrashRows.push(lead);
                else filteredActiveRows.push(lead);
            }
        });

        filteredActiveRows.sort((a,b) => new Date(b.last_visit || b.created_at) - new Date(a.last_visit || a.created_at));
        filteredTrashRows.sort((a,b) => new Date(b.deleted_at) - new Date(a.deleted_at));

        renderActiveTable();
        renderTrashTable();
    }

    function changePage(direction) {
        const rowsPerPage = parseInt(document.getElementById('dir_per_page').value);
        const maxPage = Math.ceil(filteredActiveRows.length / rowsPerPage) || 1;
        currentPage += direction;
        if(currentPage < 1) currentPage = 1;
        if(currentPage > maxPage) currentPage = maxPage;
        document.getElementById('master_checkbox').checked = false;
        renderActiveTable();
    }

    function renderActiveTable() {
        const tbody = document.getElementById('directory_tbody');
        if(!tbody) return;
        const rowsPerPage = parseInt(document.getElementById('dir_per_page').value);
        const maxPage = Math.ceil(filteredActiveRows.length / rowsPerPage) || 1;
        
        document.getElementById('dir_page_text').innerText = `Page ${currentPage} of ${maxPage}`;
        document.getElementById('dir_showing_text').innerText = `Showing ${filteredActiveRows.length} records`;
        
        tbody.innerHTML = '';

        if(filteredActiveRows.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="p-12 text-center text-gray-400 italic">No active guests found.</td></tr>`;
            return;
        }

        const startIdx = (currentPage - 1) * rowsPerPage;
        const endIdx = startIdx + rowsPerPage;
        const pageData = filteredActiveRows.slice(startIdx, endIdx);

        pageData.forEach(lead => {
            const displayDate = new Date(lead.last_visit || lead.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
            
            // AUDIT FIX: Generate real Network Status HTML using transient data
            const isOnline = lead.online_until && lead.online_until > Math.floor(Date.now()/1000);
            const minsLeft = isOnline ? Math.ceil((lead.online_until - Date.now()/1000) / 60) : 0;
            const statusHtml = isOnline
                ? `<span class="text-[10px] font-bold text-green-600 bg-green-100 px-2 py-1 rounded">ONLINE ~${minsLeft}m</span>`
                : `<span class="text-[10px] font-bold text-gray-400 uppercase">Offline</span>`;

            tbody.innerHTML += `
                <tr class="hover:bg-indigo-50 transition group">
                    <td class="p-4 text-center"><input type="checkbox" class="checkbox-custom row-checkbox" value="${lead.id}"></td>
                    <td class="p-4 cursor-pointer" onclick="openGuestDrawer(${lead.id})">
                        <div class="font-bold text-gray-900 group-hover:text-indigo-600 transition">${lead.email}</div>
                        <div class="text-[10px] text-gray-500 uppercase font-bold">${lead.guest_name || 'Unknown Guest'}</div>
                    </td>
                    <td class="p-4 cursor-pointer" onclick="openGuestDrawer(${lead.id})">
                        <div class="text-sm font-semibold text-gray-700">${lead.store_name || 'Global Portal'}</div>
                        <div class="text-[10px] text-indigo-500 font-bold">Last Seen: ${displayDate}</div>
                    </td>
                    <td class="p-4 cursor-pointer" onclick="openGuestDrawer(${lead.id})">${statusHtml}</td>
                    <td class="p-4"><button onclick="openGuestDrawer(${lead.id})" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold py-1.5 px-3 rounded border">View Card &rarr;</button></td>
                </tr>
            `;
        });
    }

    function renderTrashTable() {
        const tbody = document.getElementById('trash_tbody');
        if(!tbody) return;
        tbody.innerHTML = '';
        if(filteredTrashRows.length === 0) {
            tbody.innerHTML = `<tr><td colspan="3" class="p-12 text-center text-gray-400 italic">Trash bin is empty.</td></tr>`;
            return;
        }
        filteredTrashRows.forEach(lead => {
            const delDate = new Date(lead.deleted_at).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
            tbody.innerHTML += `
                <tr class="hover:bg-red-50 transition">
                    <td class="p-4 pl-6">
                        <div class="font-bold text-gray-900">${lead.email}</div>
                        <div class="text-[10px] text-gray-500 uppercase font-bold">${lead.guest_name || 'Unknown'}</div>
                    </td>
                    <td class="p-4 text-sm font-bold text-gray-600">${delDate}</td>
                    <td class="p-4 flex gap-2">
                        <button onclick="restoreGuest(${lead.id})" class="bg-green-100 hover:bg-green-200 text-green-700 px-3 py-1.5 rounded text-xs font-bold transition">Restore</button>
                        <button onclick="permanentDeleteGuest(${lead.id})" class="bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1.5 rounded text-xs font-bold transition">Delete Forever</button>
                    </td>
                </tr>
            `;
        });
    }

    function toggleAllCheckboxes(masterCheckbox) {
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = masterCheckbox.checked);
    }

    function executeBulkAction() {
        const action = document.getElementById('bulk_action_select').value;
        if(action !== 'trash') return;

        const checked = document.querySelectorAll('.row-checkbox:checked');
        if(checked.length === 0) { showToast("No rows selected.", "error"); return; }

        let ids = [];
        checked.forEach(cb => ids.push(cb.value));

        document.getElementById('confirm_title').innerText = `Trash ${ids.length} Guests?`;
        document.getElementById('confirm_desc').innerText = "They will be moved to the Trash Bin and deleted after 30 days.";
        document.getElementById('confirm_icon').className = "fa-solid fa-trash text-3xl text-red-600";
        document.getElementById('btn_confirm_action').innerHTML = "Move to Trash";
        
        document.getElementById('btn_confirm_action').onclick = function() {
            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Trashing...';
            const fd = new FormData();
            fd.append('action', 'mt_bulk_trash_leads');
            fd.append('security', mt_nonce);
            fd.append('lead_ids', JSON.stringify(ids));
            fetch(mt_ajax_url, { method: 'POST', body: fd }).then(res=>res.json()).then(data => {
                showToast("Guests moved to trash.", "success");
                setTimeout(()=>window.location.reload(), 1000);
            });
        };
        openConfirmModal();
    }

    function promptTrashGuest() {
        const id = document.getElementById('dr_lead_id').value;
        closeGuestDrawer();
        
        document.getElementById('confirm_title').innerText = "Move to Trash?";
        document.getElementById('confirm_desc').innerText = "This profile will be disabled and deleted in 30 days.";
        document.getElementById('confirm_icon').className = "fa-solid fa-trash text-3xl text-red-600";
        document.getElementById('btn_confirm_action').innerHTML = "Trash Guest";
        
        document.getElementById('btn_confirm_action').onclick = function() {
            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Moving...';
            const fd = new FormData();
            fd.append('action', 'mt_trash_guest_lead');
            fd.append('security', mt_nonce);
            fd.append('lead_id', id);
            fetch(mt_ajax_url, { method: 'POST', body: fd }).then(res=>res.json()).then(() => {
                showToast("Guest moved to trash.", "success");
                setTimeout(()=>window.location.reload(), 1000);
            });
        };
        openConfirmModal();
    }

    function restoreGuest(id) {
        const fd = new FormData();
        fd.append('action', 'mt_restore_guest_lead');
        fd.append('security', mt_nonce);
        fd.append('lead_id', id);
        fetch(mt_ajax_url, { method: 'POST', body: fd }).then(res=>res.json()).then(() => {
            showToast("Guest successfully restored.", "success");
            setTimeout(()=>window.location.reload(), 1000);
        });
    }

    function permanentDeleteGuest(id) {
        document.getElementById('confirm_title').innerText = "Delete Permanently?";
        document.getElementById('confirm_desc').innerText = "This cannot be undone. All data will be wiped immediately.";
        document.getElementById('confirm_icon').className = "fa-solid fa-fire text-3xl text-red-600";
        document.getElementById('btn_confirm_action').innerHTML = "Delete Forever";
        
        document.getElementById('btn_confirm_action').onclick = function() {
            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
            const fd = new FormData();
            fd.append('action', 'mt_delete_guest_lead_permanent');
            fd.append('security', mt_nonce);
            fd.append('lead_id', id);
            fetch(mt_ajax_url, { method: 'POST', body: fd }).then(res=>res.json()).then(() => {
                showToast("Guest permanently deleted.", "success");
                setTimeout(()=>window.location.reload(), 1000);
            });
        };
        openConfirmModal();
    }

    function promptEmptyTrash() {
        if(filteredTrashRows.length === 0) { showToast("Trash is already empty.", "error"); return; }
        
        document.getElementById('confirm_title').innerText = "Empty Entire Trash?";
        document.getElementById('confirm_desc').innerText = "All items in the trash bin will be permanently erased.";
        document.getElementById('confirm_icon').className = "fa-solid fa-fire text-3xl text-red-600";
        document.getElementById('btn_confirm_action').innerHTML = "Empty Trash";
        
        document.getElementById('btn_confirm_action').onclick = function() {
            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Emptying...';
            const fd = new FormData();
            fd.append('action', 'mt_empty_guest_trash');
            fd.append('security', mt_nonce);
            fetch(mt_ajax_url, { method: 'POST', body: fd }).then(res=>res.json()).then(() => {
                showToast("Trash emptied.", "success");
                setTimeout(()=>window.location.reload(), 1000);
            });
        };
        openConfirmModal();
    }

    function exportLeadsCSV() {
        if(filteredActiveRows.length === 0) { showToast("No active leads available to export.", "error"); return; }
        let csvContent = "data:text/csv;charset=utf-8,ID,Email,Name,MAC Address,Location,Campaign Tag,Status,First Seen,Last Seen\n";
        filteredActiveRows.forEach(function(lead) {
            let row = [ lead.id, lead.email, `"${lead.guest_name || ''}"`, lead.guest_mac, `"${lead.store_name || 'Global'}"`, lead.campaign_tag || '', lead.status, lead.created_at, lead.last_visit || lead.created_at ];
            csvContent += row.join(",") + "\n";
        });
        var encodedUri = encodeURI(csvContent);
        var link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "roost_active_guests_" + new Date().toISOString().slice(0,10) + ".csv");
        document.body.appendChild(link); link.click(); document.body.removeChild(link);
        showToast("Guest list exported successfully!", "success");
    }

    function switchDrawerTab(tab, el) {
        document.querySelectorAll('.dr-tab').forEach(b => {
            b.classList.remove('active', 'border-indigo-600', 'text-indigo-600', 'border-purple-600', 'text-purple-600');
            if(b.innerText.includes('Rewards')) b.classList.add('text-purple-600'); else b.classList.add('text-gray-500');
        });
        el.classList.remove('text-gray-500', 'text-purple-600');
        if(tab === 'rewards') el.classList.add('active', 'border-purple-600', 'text-purple-600');
        else el.classList.add('active', 'border-indigo-600', 'text-indigo-600');
        document.querySelectorAll('.dr-view').forEach(v => v.classList.remove('active'));
        const targetView = document.getElementById('dr_view_' + tab);
        if(targetView) { targetView.classList.add('active'); }
    }

    function openGuestDrawer(id) {
        const lead = mt_leads_data[id];
        if(!lead) return;

        document.querySelector('.dr-tab').click();

        document.getElementById('dr_lead_id').value = lead.id;
        document.getElementById('dr_name').innerText = lead.guest_name || 'Unknown Guest';
        document.getElementById('dr_email').innerHTML = `<i class="fa-solid fa-envelope"></i> <span>${lead.email}</span>`;
        
        const badge = document.getElementById('dr_status_badge');
        if(lead.status === 'active') { badge.className = "mt-2 inline-block px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider bg-green-100 text-green-700"; badge.innerText = "Subscribed"; } 
        else { badge.className = "mt-2 inline-block px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider bg-red-100 text-red-700"; badge.innerText = "Unsubscribed"; }
        
        // AUDIT FIX: Update drawer network status using Live Transient calculation
        const isOnline = lead.online_until && lead.online_until > Math.floor(Date.now()/1000);
        const minsLeft = isOnline ? Math.ceil((lead.online_until - Date.now()/1000) / 60) : 0;
        const statusEl = document.getElementById('dr_network_status');
        if(isOnline) {
            statusEl.innerHTML = `<span class="text-green-400">Online (~${minsLeft} mins left)</span>`;
        } else {
            statusEl.innerHTML = `<span class="text-gray-400">Currently Offline</span>`;
        }

        document.getElementById('dr_first_date').innerText = new Date(lead.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        document.getElementById('dr_mac').innerText = lead.guest_mac || 'N/A';
        document.getElementById('dr_location').innerText = lead.store_name || 'Global Portal';

        const dataCards = document.getElementById('dr_data_cards');
        const emptyState = document.getElementById('dr_data_empty');
        dataCards.innerHTML = ''; 
        let surveyData = {};
        try { surveyData = JSON.parse(lead.survey_data); } catch(e) {}
        
        if((surveyData && Object.keys(surveyData).length > 0) || lead.birthday) {
            emptyState.classList.add('hidden'); 
            if(lead.birthday || surveyData.birthday) { dataCards.innerHTML += `<div class="bg-gray-50 border p-3 rounded-lg flex items-center gap-3"><div class="text-pink-500 text-xl"><i class="fa-solid fa-cake-candles"></i></div><div><p class="text-[10px] uppercase font-bold text-gray-400">Birthday</p><p class="text-sm font-bold text-gray-800">${lead.birthday || surveyData.birthday}</p></div></div>`; }
            if(surveyData.rating) {
                let stars = ''; for(let i=0; i<5; i++) { stars += i < surveyData.rating ? '<i class="fa-solid fa-star text-yellow-400"></i> ' : '<i class="fa-solid fa-star text-gray-300"></i> '; }
                dataCards.innerHTML += `<div class="bg-gray-50 border p-3 rounded-lg"><p class="text-[10px] uppercase font-bold text-gray-400 mb-1">Experience Rating</p><div class="text-lg">${stars}</div></div>`;
            }
            if(surveyData.versus_choice) { dataCards.innerHTML += `<div class="bg-indigo-50 border border-indigo-100 p-3 rounded-lg"><p class="text-[10px] uppercase font-bold text-indigo-400 mb-1">A/B Choice</p><p class="text-sm font-bold text-indigo-900">${surveyData.versus_choice}</p></div>`; }
        } else { 
            emptyState.classList.remove('hidden'); 
        }

        const timeline = document.getElementById('dr_timeline_container');
        timeline.innerHTML = '';
        
        timeline.innerHTML += `
            <div class="timeline-item">
                <div class="timeline-icon bg-blue-100 text-blue-500"><i class="fa-solid fa-wifi"></i></div>
                <div>
                    <p class="text-sm font-bold text-gray-900 leading-none">First Network Connection</p>
                    <p class="text-[10px] text-gray-400 font-bold uppercase mt-1 mb-1">${new Date(lead.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                    <p class="text-xs text-gray-600 bg-gray-50 border border-gray-100 p-2 rounded inline-block mt-1">Originated at ${lead.store_name || 'Global Portal'}</p>
                </div>
            </div>`;

        if((surveyData && Object.keys(surveyData).length > 0) || lead.birthday) {
            timeline.innerHTML += `
            <div class="timeline-item">
                <div class="timeline-icon bg-purple-100 text-purple-500"><i class="fa-solid fa-bullseye"></i></div>
                <div>
                    <p class="text-sm font-bold text-gray-900 leading-none">Campaign Engagement</p>
                    <p class="text-[10px] text-gray-400 font-bold uppercase mt-1 mb-1">${new Date(lead.last_visit || lead.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                    <p class="text-xs text-purple-700 bg-purple-50 border border-purple-100 p-2 rounded inline-block mt-1">Provided marketing profile data.</p>
                </div>
            </div>`;
        }

        if(lead.last_visit && lead.last_visit !== lead.created_at) {
            timeline.innerHTML += `
            <div class="timeline-item">
                <div class="timeline-icon bg-green-100 text-green-500"><i class="fa-solid fa-arrow-right-to-bracket"></i></div>
                <div>
                    <p class="text-sm font-bold text-gray-900 leading-none">Returning Visit</p>
                    <p class="text-[10px] text-gray-400 font-bold uppercase mt-1 mb-1">${new Date(lead.last_visit).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                    <p class="text-xs text-gray-600 bg-gray-50 border border-gray-100 p-2 rounded inline-block mt-1">Reconnected to network automatically.</p>
                </div>
            </div>`;
        }

        document.getElementById('woo_pts_balance').innerText = '--';
        document.getElementById('woo_last_sync').innerText = 'Never';

        document.getElementById('guest_drawer_overlay').classList.remove('hidden');
        setTimeout(() => { document.getElementById('guest_drawer_overlay').classList.remove('opacity-0'); document.getElementById('guest_drawer').classList.remove('translate-x-full'); }, 10);
    }

    function closeGuestDrawer() {
        document.getElementById('guest_drawer').classList.add('translate-x-full');
        document.getElementById('guest_drawer_overlay').classList.add('opacity-0');
        setTimeout(() => { document.getElementById('guest_drawer_overlay').classList.add('hidden'); }, 300);
    }

    function toggleExternalAPI() {
        const btn = document.getElementById('btn_api_toggle');
        const icon = document.getElementById('icon_api_toggle');
        const txt = document.getElementById('txt_api_toggle');
        const rewardsTab = document.getElementById('dr_tab_rewards');
        const rewardsView = document.getElementById('dr_view_rewards');

        if (!externalApiActive) {
            txt.innerText = "Syncing...";
            icon.className = "fa-solid fa-circle-notch fa-spin text-indigo-500 mr-1";
            
            const fd = new FormData(); fd.append('mt_api_toggle_action', 'yes'); fd.append('brand_id', <?php echo $brand->id; ?>);
            fetch(window.location.href, { method: 'POST', body: fd }).then(() => {
                externalApiActive = true; txt.innerText = "Connected & Synced"; icon.className = "fa-solid fa-check-circle text-green-500 mr-1";
                btn.classList.replace('text-gray-600', 'text-green-700'); btn.classList.replace('border-gray-300', 'border-green-300'); btn.classList.add('bg-green-50');
                rewardsTab.classList.remove('hidden'); rewardsView.classList.remove('hidden'); showToast("External API successfully connected and saved.", "success");
            });
        } else {
            if(confirm("Are you sure you want to remove this API connection? Rewards data will be hidden.")) {
                const fd = new FormData(); fd.append('mt_api_toggle_action', 'no'); fd.append('brand_id', <?php echo $brand->id; ?>);
                fetch(window.location.href, { method: 'POST', body: fd }).then(() => {
                    externalApiActive = false; txt.innerText = "Connect to Sync"; icon.className = "fa-solid fa-plug text-gray-400 mr-1";
                    btn.classList.replace('text-green-700', 'text-gray-600'); btn.classList.replace('border-green-300', 'border-gray-300'); btn.classList.remove('bg-green-50');
                    rewardsTab.classList.add('hidden'); if(rewardsTab.classList.contains('active')) document.querySelector('.dr-tab').click(); 
                    rewardsView.classList.add('hidden'); showToast("API data connection removed and saved.", "info");
                });
            }
        }
    }

    // AUDIT FIX: Made the RADIUS 'Add 1 Hour' button execute real server logic 
    function triggerRadiusExtend() {
        const id = document.getElementById('dr_lead_id').value;
        const btn = document.getElementById('btn_radius_extend');
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Extending...';
        btn.classList.add('opacity-80', 'cursor-not-allowed');

        const fd = new FormData();
        fd.append('action', 'mt_extend_radius_session');
        fd.append('security', mt_nonce);
        fd.append('lead_id', id);

        fetch(mt_ajax_url, { method: 'POST', body: fd })
          .then(r => r.json())
          .then(d => {
              if (d.success) { 
                  btn.innerHTML = '<i class="fa-solid fa-check"></i> Added 1 Hour';
                  btn.classList.replace('bg-indigo-600', 'bg-green-600');
                  btn.classList.remove('hover:bg-indigo-500');
                  showToast('Session extended by +60 minutes in RADIUS.', 'success');
              } else { 
                  showToast('Failed to extend session.', 'error'); 
                  btn.innerHTML = '+ Add 1 Hour'; 
              }
              setTimeout(() => {
                  btn.innerHTML = '+ Add 1 Hour';
                  btn.classList.replace('bg-green-600', 'bg-indigo-600');
                  btn.classList.add('hover:bg-indigo-500');
                  btn.classList.remove('opacity-80', 'cursor-not-allowed');
              }, 3000);
          });
    }

    // AUDIT FIX: Adjusted the Force Sync API button to not display fake data
    function triggerWooSync() {
        document.getElementById('woo_pts_balance').innerText = 'API Not Configured';
        document.getElementById('woo_last_sync').innerText = 'Phase 3 Feature';
        showToast('Rewards API integration launches in Phase 3.', 'info');
    }

    function switchCampTypeView() {
        let type = document.getElementById('camp_type').value;
        document.querySelectorAll('.camp-type-view').forEach(v => v.classList.remove('active'));
        document.getElementById('ctype_' + type).classList.add('active');
        document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
        document.getElementById('camp_error_banner').classList.add('hidden');
        updateLivePreview();
    }

    function toggleQOpts(qNum) {
        let type = document.getElementById(`camp_q${qNum}_type`).value;
        let optInput = document.getElementById(`camp_q${qNum}_opts`);
        if(type === 'radio' || type === 'checkbox') { optInput.classList.remove('hidden'); } else { optInput.classList.add('hidden'); }
        updateLivePreview();
    }

    let campImages = { promo: '', vsa: '', vsb: '' };

    async function uploadToVault(file) {
        const formData = new FormData();
        formData.append('action', 'mt_upload_vault_media'); formData.append('security', mt_nonce); formData.append('media_type', 'wifi'); formData.append('file', file);
        const res = await fetch(mt_ajax_url, { method: 'POST', body: formData });
        const data = await res.json();
        if(data.success) return data.data.url;
        throw new Error(data.data);
    }

    ['promo', 'vsa', 'vsb'].forEach(key => {
        const input = document.getElementById(`input_${key}_img`);
        if(input) {
            input.addEventListener('change', async e => {
                if(e.target.files && e.target.files[0]) {
                    document.getElementById(`zone_${key}`).style.opacity = '0.5';
                    try {
                        const url = await uploadToVault(e.target.files[0]);
                        campImages[key] = url; document.getElementById(`img_${key}`).src = url; document.getElementById(`img_${key}`).classList.remove('hidden'); document.getElementById(`rem_${key}`).classList.remove('hidden'); updateLivePreview(); 
                    } catch(err) { showToast("Image Upload Failed", "error"); }
                    document.getElementById(`zone_${key}`).style.opacity = '1'; e.target.value = '';
                }
            });
        }
    });

    function removeCampImage(event, key) {
        event.preventDefault(); event.stopPropagation();
        campImages[key] = ''; document.getElementById(`img_${key}`).src = ''; document.getElementById(`img_${key}`).classList.add('hidden'); document.getElementById(`rem_${key}`).classList.add('hidden'); updateLivePreview();
    }

    function updateLivePreview() {
        const type = document.getElementById('camp_type').value;
        ['survey', 'promo', 'versus', 'birthday', 'wheel', 'box'].forEach(t => document.getElementById('prev_type_' + t).classList.add('hidden'));
        document.getElementById('prev_type_' + type).classList.remove('hidden');

        if(type === 'survey') {
            document.getElementById('prev_stars').classList.toggle('hidden', !document.getElementById('camp_stars').checked);
            const container = document.getElementById('prev_survey_container'); container.innerHTML = ''; 
            for(let i=1; i<=4; i++) {
                let text = document.getElementById(`camp_q${i}_text`).value; let qType = document.getElementById(`camp_q${i}_type`).value; let opts = document.getElementById(`camp_q${i}_opts`).value.split(',').map(s=>s.trim()).filter(s=>s);
                if(text) {
                    let html = `<label class="block text-[10px] font-bold text-gray-500 mb-1">${text}</label>`;
                    if(qType === 'text') { html += `<input type="text" class="w-full px-3 py-1.5 border border-gray-200 rounded-lg text-sm bg-gray-50" disabled>`; } 
                    else if (qType === 'radio' || qType === 'checkbox') {
                        if(opts.length === 0) html += `<div class="text-xs text-gray-400 italic">No options entered...</div>`;
                        opts.forEach(opt => { html += `<div class="flex items-center gap-2 mb-1"><input type="${qType}" disabled class="text-indigo-600"> <span class="text-xs text-gray-700">${opt}</span></div>`; });
                    }
                    container.innerHTML += `<div class="mb-3">${html}</div>`;
                }
            }
        } else if(type === 'promo') {
            document.getElementById('prev_promo_title').innerText = document.getElementById('camp_promo_text').value || 'Promo Headline';
            if(campImages.promo) { document.getElementById('prev_img_promo').src = campImages.promo; document.getElementById('prev_img_promo').classList.remove('hidden'); document.getElementById('prev_icon_promo').classList.add('hidden'); }
            else { document.getElementById('prev_img_promo').classList.add('hidden'); document.getElementById('prev_icon_promo').classList.remove('hidden'); }
        } else if(type === 'versus') {
            document.getElementById('prev_vs_title').innerText = document.getElementById('camp_vs_title').value || 'Pick a side:';
            document.getElementById('prev_vs_mid').innerText = document.getElementById('camp_vs_mid').value || 'VS';
            document.getElementById('prev_vs_a').innerText = document.getElementById('camp_vs_a').value || 'Option A';
            document.getElementById('prev_vs_b').innerText = document.getElementById('camp_vs_b').value || 'Option B';
            if(campImages.vsa) { document.getElementById('prev_img_vsa').src = campImages.vsa; document.getElementById('prev_img_vsa').classList.remove('hidden'); } else document.getElementById('prev_img_vsa').classList.add('hidden');
            if(campImages.vsb) { document.getElementById('prev_img_vsb').src = campImages.vsb; document.getElementById('prev_img_vsb').classList.remove('hidden'); } else document.getElementById('prev_img_vsb').classList.add('hidden');
        } else if(type === 'birthday') {
            document.getElementById('prev_bday_text').innerText = document.getElementById('camp_bday_text').value || 'When is your birthday?';
        } else if(type === 'wheel') {
            document.getElementById('prev_wheel_title').innerText = document.getElementById('camp_wheel_title').value || 'Spin to Win!';
            const wList = document.getElementById('prev_wheel_list'); wList.innerHTML = '';
            for(let i=1; i<=6; i++) { let p = document.getElementById(`wheel_p${i}_name`).value; if(p) wList.innerHTML += `<div>🎁 ${p}</div>`; }
            if(!wList.innerHTML) wList.innerHTML = '<div class="italic text-gray-400">Add prizes on the left</div>';
        } else if(type === 'box') {
            document.getElementById('prev_box_title').innerText = document.getElementById('camp_box_title').value || 'Open the Mystery Box!';
        }
    }

    function openCampaignEditor(id) {
        document.getElementById('camp_list_state').classList.add('hidden'); document.getElementById('camp_edit_state').classList.remove('hidden'); document.getElementById('camp_error_banner').classList.add('hidden'); document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error')); document.getElementById('current_camp_id').value = id;

        if (id === 0) {
            document.getElementById('camp_editor_title').innerText = 'Create New Campaign'; document.getElementById('camp_name').value = ''; document.getElementById('camp_type').value = 'survey'; campImages = { promo: '', vsa: '', vsb: '' };
            document.getElementById('camp_start_date').value = ''; document.getElementById('camp_end_date').value = '';
            document.querySelectorAll('#camp_edit_state input[type="text"]').forEach(i => i.value = ''); document.querySelectorAll('#camp_edit_state input[type="number"]').forEach(i => i.value = 0); document.querySelectorAll('#camp_edit_state input[type="date"]').forEach(i => i.value = '');
            document.getElementById('camp_promo_text').value = '';
            for(let i=1; i<=4; i++) { document.getElementById(`camp_q${i}_type`).value = 'text'; toggleQOpts(i); }
            ['promo','vsa','vsb'].forEach(k => { document.getElementById(`img_${k}`).classList.add('hidden'); document.getElementById(`rem_${k}`).classList.add('hidden'); });
            switchCampTypeView();
        } else {
            const btn = event.currentTarget; document.getElementById('camp_editor_title').innerText = 'Editing Campaign'; document.getElementById('camp_name').value = btn.getAttribute('data-name'); const type = btn.getAttribute('data-type'); document.getElementById('camp_type').value = type;
            let config = {}; try { config = JSON.parse(btn.getAttribute('data-config')); } catch(e) {}
            document.getElementById('camp_start_date').value = config.schedule?.start || ''; document.getElementById('camp_end_date').value = config.schedule?.end || '';

            if(type === 'survey') {
                document.getElementById('camp_stars').checked = config.stars ?? true;
                if(config.questions && Array.isArray(config.questions)) { for(let i=0; i<4; i++) { let qNum = i+1; if(config.questions[i]) { document.getElementById(`camp_q${qNum}_text`).value = config.questions[i].text || ''; document.getElementById(`camp_q${qNum}_type`).value = config.questions[i].type || 'text'; document.getElementById(`camp_q${qNum}_opts`).value = config.questions[i].options || ''; } toggleQOpts(qNum); } }
            } else if(type === 'promo') {
                document.getElementById('camp_promo_text').value = config.text || ''; if(config.img) { campImages.promo = config.img; document.getElementById('img_promo').src = config.img; document.getElementById('img_promo').classList.remove('hidden'); document.getElementById('rem_promo').classList.remove('hidden'); }
            } else if(type === 'versus') {
                document.getElementById('camp_vs_title').value = config.title || ''; document.getElementById('camp_vs_mid').value = config.mid || 'VS'; document.getElementById('camp_vs_a').value = config.a || ''; document.getElementById('camp_vs_b').value = config.b || '';
                if(config.img_a) { campImages.vsa = config.img_a; document.getElementById('img_vsa').src = config.img_a; document.getElementById('img_vsa').classList.remove('hidden'); document.getElementById('rem_vsa').classList.remove('hidden'); }
                if(config.img_b) { campImages.vsb = config.img_b; document.getElementById('img_vsb').src = config.img_b; document.getElementById('img_vsb').classList.remove('hidden'); document.getElementById('rem_vsb').classList.remove('hidden'); }
            } else if(type === 'birthday') { document.getElementById('camp_bday_text').value = config.text || '';
            } else if(type === 'wheel') {
                document.getElementById('camp_wheel_title').value = config.title || '';
                for(let i=1; i<=6; i++) { if(config.prizes && config.prizes[i-1]) { document.getElementById(`wheel_p${i}_name`).value = config.prizes[i-1].name || ''; document.getElementById(`wheel_p${i}_limit`).value = config.prizes[i-1].limit || 0; document.getElementById(`wheel_p${i}_unlock`).value = config.prizes[i-1].unlock || ''; } }
            } else if(type === 'box') {
                document.getElementById('camp_box_title').value = config.title || '';
                for(let i=1; i<=4; i++) { if(config.prizes && config.prizes[i-1]) { document.getElementById(`box_p${i}_name`).value = config.prizes[i-1].name || ''; document.getElementById(`box_p${i}_limit`).value = config.prizes[i-1].limit || 0; document.getElementById(`box_p${i}_unlock`).value = config.prizes[i-1].unlock || ''; } }
            }
            switchCampTypeView();
        }
    }

    function closeCampaignEditor() { document.getElementById('camp_edit_state').classList.add('hidden'); document.getElementById('camp_list_state').classList.remove('hidden'); }

    function saveCampaign() {
        document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error')); document.getElementById('camp_error_banner').classList.add('hidden');
        let hasError = false; const name = document.getElementById('camp_name'); if(!name.value.trim()) { name.classList.add('input-error'); hasError = true; }
        const type = document.getElementById('camp_type').value; let config = {};
        config.schedule = { start: document.getElementById('camp_start_date').value, end: document.getElementById('camp_end_date').value };

        if(type === 'survey') {
            const q1 = document.getElementById('camp_q1_text'); if(!q1.value.trim()) { q1.classList.add('input-error'); hasError = true; }
            let questions = []; for(let i=1; i<=4; i++) { questions.push({ text: document.getElementById(`camp_q${i}_text`).value, type: document.getElementById(`camp_q${i}_type`).value, options: document.getElementById(`camp_q${i}_opts`).value }); }
            config.stars = document.getElementById('camp_stars').checked; config.questions = questions;
        } else if(type === 'promo') {
            const txt = document.getElementById('camp_promo_text'); if(!txt.value.trim()) { txt.classList.add('input-error'); hasError = true; }
            config.text = txt.value; config.img = campImages.promo;
        } else if(type === 'versus') {
            const title = document.getElementById('camp_vs_title'); const vsa = document.getElementById('camp_vs_a'); const vsb = document.getElementById('camp_vs_b');
            if(!title.value.trim()) { title.classList.add('input-error'); hasError = true; } if(!vsa.value.trim()) { vsa.classList.add('input-error'); hasError = true; } if(!vsb.value.trim()) { vsb.classList.add('input-error'); hasError = true; }
            config.title = title.value; config.mid = document.getElementById('camp_vs_mid').value; config.a = vsa.value; config.b = vsb.value; config.img_a = campImages.vsa; config.img_b = campImages.vsb;
        } else if(type === 'birthday') {
            const txt = document.getElementById('camp_bday_text'); if(!txt.value.trim()) { txt.classList.add('input-error'); hasError = true; } config.text = txt.value;
        } else if(type === 'wheel') {
            const title = document.getElementById('camp_wheel_title'); const p1 = document.getElementById('wheel_p1_name'); const p2 = document.getElementById('wheel_p2_name');
            if(!title.value.trim()) { title.classList.add('input-error'); hasError = true; } if(!p1.value.trim()) { p1.classList.add('input-error'); hasError = true; } if(!p2.value.trim()) { p2.classList.add('input-error'); hasError = true; }
            let prizes = []; for(let i=1; i<=6; i++) { let pName = document.getElementById(`wheel_p${i}_name`).value.trim(); let pLim = document.getElementById(`wheel_p${i}_limit`).value; let pUnlock = document.getElementById(`wheel_p${i}_unlock`).value; if(pName) prizes.push({ name: pName, limit: parseInt(pLim) || 0, unlock: pUnlock }); }
            config.title = title.value; config.prizes = prizes;
        } else if(type === 'box') {
            const title = document.getElementById('camp_box_title'); const p1 = document.getElementById('box_p1_name'); const p2 = document.getElementById('box_p2_name');
            if(!title.value.trim()) { title.classList.add('input-error'); hasError = true; } if(!p1.value.trim()) { p1.classList.add('input-error'); hasError = true; } if(!p2.value.trim()) { p2.classList.add('input-error'); hasError = true; }
            let prizes = []; for(let i=1; i<=4; i++) { let pName = document.getElementById(`box_p${i}_name`).value.trim(); let pLim = document.getElementById(`box_p${i}_limit`).value; let pUnlock = document.getElementById(`box_p${i}_unlock`).value; if(pName) prizes.push({ name: pName, limit: parseInt(pLim) || 0, unlock: pUnlock }); }
            config.title = title.value; config.prizes = prizes;
        }

        if(hasError) { document.getElementById('camp_error_banner').classList.remove('hidden'); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }

        const btn = document.getElementById('btn_save_camp'); const ogText = btn.innerHTML; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        const formData = new FormData(); formData.append('action', 'mt_save_campaign'); formData.append('security', mt_nonce); formData.append('campaign_id', document.getElementById('current_camp_id').value); formData.append('campaign_name', name.value.trim()); formData.append('campaign_type', type); formData.append('config', JSON.stringify(config));

        fetch(mt_ajax_url, { method: 'POST', body: formData }).then(res => res.json()).then(data => {
            if(data.success) { btn.innerHTML = '<i class="fa-solid fa-check"></i> Saved!'; btn.classList.add('bg-green-600'); setTimeout(() => window.location.reload(), 1000); } 
            else { document.getElementById('camp_error_text').innerText = "Server Error: " + data.data; document.getElementById('camp_error_banner').classList.remove('hidden'); btn.innerHTML = ogText; }
        });
    }

    let itemToDelete = null;
    function promptDeleteCampaign(id) {
        itemToDelete = id; document.getElementById('confirm_title').innerText = "Delete Campaign?"; document.getElementById('confirm_desc').innerText = "This will permanently remove the campaign and all its captured analytics."; document.getElementById('confirm_icon').className = "fa-solid fa-triangle-exclamation text-3xl text-red-600"; document.getElementById('btn_confirm_action').innerHTML = "Yes, Delete"; document.getElementById('btn_confirm_action').onclick = executeDeleteCampaign; openConfirmModal();
    }
    function executeDeleteCampaign() {
        if(!itemToDelete) return; const btn = document.getElementById('btn_confirm_action'); btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
        const formData = new FormData(); formData.append('action', 'mt_delete_campaign'); formData.append('security', mt_nonce); formData.append('campaign_id', itemToDelete);
        fetch(mt_ajax_url, { method: 'POST', body: formData }).then(res => res.json()).then(data => { 
            if(data.success) { showToast("Campaign deleted.", "success"); setTimeout(() => window.location.reload(), 1000); } else { showToast("Error deleting campaign.", "error"); closeConfirmModal(); btn.innerHTML = 'Yes, Delete'; }
        });
    }
</script>