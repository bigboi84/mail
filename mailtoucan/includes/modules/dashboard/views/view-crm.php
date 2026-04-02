<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_leads = $wpdb->prefix . 'mt_guest_leads';
$table_stores = $wpdb->prefix . 'mt_stores';
$table_campaigns = $wpdb->prefix . 'mt_campaigns';

$leads = $wpdb->get_results( $wpdb->prepare("
    SELECT l.*, s.store_name 
    FROM $table_leads l
    LEFT JOIN $table_stores s ON l.store_id = s.id
    WHERE l.brand_id = %d 
    ORDER BY l.created_at DESC
", $brand->id) );
$campaigns = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table_campaigns WHERE brand_id = %d ORDER BY created_at DESC", $brand->id) );

$total_leads = count($leads);
$unsub_count = 0;

$js_leads = [];
foreach($leads as $l) { 
    if($l->status === 'unsubscribed') $unsub_count++; 
    $js_leads[$l->id] = $l;
}

$is_admin = current_user_can('manage_options') ? true : false;
?>

<style>
    .crm-tab.active { border-bottom: 2px solid #4f46e5; color: #4f46e5; }
    .crm-view { display: none; }
    .crm-view.active { display: block; }
    .img-upload-zone { position: relative; overflow: hidden; border: 1px solid #d1d5db; border-radius: 0.375rem; background: #f9fafb; text-align: center; cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100px; }
    .img-upload-zone img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: 10; }
    .img-upload-zone .rem-btn { position: absolute; top: 4px; right: 4px; background: rgba(239,68,68,0.9); color: white; border-radius: 50%; width: 24px; height: 24px; z-index: 40; display: flex; align-items: center; justify-content: center; font-size: 10px; cursor: pointer; }
    .img-upload-zone .rem-btn:hover { background: rgba(220,38,38,1); }
    .camp-type-view { display: none; }
    .camp-type-view.active { display: block; }
    
    .phone-mockup { border: 12px solid #1f2937; border-radius: 36px; height: 600px; width: 320px; background: #fff; position: relative; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); margin: 0 auto; }
    .phone-notch { position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 100px; height: 25px; background: #1f2937; border-bottom-left-radius: 16px; border-bottom-right-radius: 16px; z-index: 20;}
    .input-error { border-color: #ef4444 !important; background-color: #fef2f2 !important; }

    .drawer-scroll::-webkit-scrollbar { width: 4px; }
    .drawer-scroll::-webkit-scrollbar-track { background: transparent; }
    .drawer-scroll::-webkit-scrollbar-thumb { background-color: #e5e7eb; border-radius: 20px; }

    /* Gamification Specific UI */
    .wheel-preview { width: 200px; height: 200px; border-radius: 50%; border: 8px solid #f3f4f6; background: conic-gradient(#4f46e5 0 25%, #e0e7ff 25% 50%, #4f46e5 50% 75%, #e0e7ff 75% 100%); margin: 0 auto; position: relative; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
    .wheel-pointer { position: absolute; top: -15px; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 15px solid transparent; border-right: 15px solid transparent; border-top: 25px solid #ef4444; z-index: 10; }
</style>

<header class="mb-8 flex justify-between items-end">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">The Roost</h1>
        <p class="text-gray-500 text-sm">Gathering place for your captured leads and data campaigns.</p>
    </div>
    <div class="flex gap-4">
        <button class="bg-white border border-gray-300 px-4 py-2 rounded-lg font-bold text-sm text-gray-600 hover:bg-gray-50 transition flex items-center gap-2">
            <i class="fa-solid fa-download"></i> Export Leads
        </button>
        <button onclick="alert('Bulk Email Engine will be activated in Phase 3. Get your leads first!')" class="bg-indigo-600 text-white px-4 py-2 rounded-lg font-bold text-sm shadow-md hover:bg-indigo-700 transition flex items-center gap-2">
            <i class="fa-solid fa-bullhorn"></i> Start Bulk Email
        </button>
    </div>
</header>

<div class="grid grid-cols-4 gap-6 mb-6">
    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
        <p class="text-xs font-bold text-gray-400 uppercase mb-1">Total Guests</p>
        <p class="text-3xl font-bold text-gray-900"><?php echo number_format($total_leads); ?></p>
    </div>
    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
        <p class="text-xs font-bold text-gray-400 uppercase mb-1">Active Subscribers</p>
        <p class="text-3xl font-bold text-green-600"><?php echo number_format($total_leads - $unsub_count); ?></p>
    </div>
    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
        <p class="text-xs font-bold text-gray-400 uppercase mb-1">Unsubscribed</p>
        <p class="text-3xl font-bold text-red-500"><?php echo number_format($unsub_count); ?></p>
    </div>
    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
        <p class="text-xs font-bold text-gray-400 uppercase mb-1">Opt-out Rate</p>
        <p class="text-3xl font-bold text-gray-900"><?php echo $total_leads > 0 ? round(($unsub_count/$total_leads)*100, 1) : 0; ?>%</p>
    </div>
</div>

<div class="flex border-b border-gray-200 mb-6 gap-6">
    <button class="crm-tab active pb-3 font-bold text-sm text-gray-500 transition-colors" onclick="switchCrmTab('directory', this)"><i class="fa-solid fa-address-book mr-2"></i> Guest Directory</button>
    <button class="crm-tab pb-3 font-bold text-sm text-gray-500 transition-colors" onclick="switchCrmTab('campaigns', this)"><i class="fa-solid fa-tags mr-2"></i> Campaign Manager</button>
</div>

<div id="view_directory" class="crm-view active">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-20">
        <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
            <h3 class="font-bold text-gray-800">Master Guest List</h3>
            <div class="flex gap-2">
                <input type="text" placeholder="Search email or tag..." class="px-3 py-1.5 border rounded-lg text-sm outline-none w-64 focus:ring-2 focus:ring-indigo-100">
            </div>
        </div>
        
        <table class="w-full text-left border-collapse">
            <thead class="bg-white border-b text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                <tr>
                    <th class="p-4 pl-6">Guest Identity</th>
                    <th class="p-4">Origin / Store</th>
                    <th class="p-4">Campaign Tag</th>
                    <th class="p-4">Proof of Consent</th>
                    <th class="p-4">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if(empty($leads)): ?>
                    <tr><td colspan="5" class="p-12 text-center text-gray-400 italic">No guests captured yet. Connect a router and test your Splash Screen to see data here.</td></tr>
                <?php else: ?>
                    <?php foreach($leads as $lead): ?>
                    <tr class="hover:bg-indigo-50 transition group cursor-pointer" onclick="openGuestDrawer(<?php echo $lead->id; ?>)">
                        <td class="p-4 pl-6">
                            <div class="font-bold text-gray-900 group-hover:text-indigo-600 transition-colors"><?php echo esc_html($lead->email); ?></div>
                            <div class="text-[10px] text-gray-500 uppercase font-bold"><?php echo esc_html($lead->guest_name ?: 'Unknown Guest'); ?></div>
                        </td>
                        <td class="p-4">
                            <div class="text-sm font-semibold text-gray-700"><?php echo esc_html($lead->store_name ?: 'Global'); ?></div>
                            <div class="text-[10px] text-gray-400"><?php echo date('M d, Y @ H:i', strtotime($lead->created_at)); ?></div>
                        </td>
                        <td class="p-4">
                            <?php if($lead->campaign_tag): ?>
                                <span class="bg-indigo-50 text-indigo-700 text-[10px] font-bold px-2 py-1 rounded-full border border-indigo-100 uppercase tracking-tight"><?php echo esc_html($lead->campaign_tag); ?></span>
                            <?php else: ?>
                                <span class="text-gray-300 italic text-xs">Organic Connect</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4">
                            <div class="max-w-[200px] truncate text-[10px] text-gray-500 bg-gray-100 p-2 rounded border border-gray-200">
                                <i class="fa-solid fa-shield-check text-green-500 mr-1"></i> Validated
                            </div>
                        </td>
                        <td class="p-4">
                            <?php if($lead->status === 'active'): ?>
                                <span class="text-green-500 text-xs font-bold flex items-center gap-1"><i class="fa-solid fa-circle text-[8px]"></i> Active</span>
                            <?php else: ?>
                                <span class="text-red-500 text-xs font-bold flex items-center gap-1"><i class="fa-solid fa-circle text-[8px]"></i> Unsubscribed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="guest_drawer_overlay" class="fixed inset-0 bg-gray-900/40 z-[100] hidden opacity-0 transition-opacity duration-300 backdrop-blur-sm" onclick="closeGuestDrawer()"></div>
<div id="guest_drawer" class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-[101] transform translate-x-full transition-transform duration-300 flex flex-col border-l border-gray-200">
    <div class="p-6 border-b border-gray-100 bg-gray-50 flex justify-between items-start">
        <div class="flex gap-4 items-center">
            <div class="w-12 h-12 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xl font-bold shadow-sm border border-indigo-200">
                <i class="fa-solid fa-user"></i>
            </div>
            <div>
                <h2 id="dr_name" class="text-xl font-bold text-gray-900 leading-tight">Guest Name</h2>
                <p id="dr_email" class="text-sm text-gray-500 font-medium">email@example.com</p>
            </div>
        </div>
        <button onclick="closeGuestDrawer()" class="text-gray-400 hover:text-gray-800 bg-white border rounded-lg p-2 shadow-sm transition"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="flex-1 overflow-y-auto drawer-scroll p-6 bg-white space-y-6">
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-gray-50 border p-3 rounded-lg">
                <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Status</p><div id="dr_status"></div>
            </div>
            <div class="bg-gray-50 border p-3 rounded-lg">
                <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">First Seen</p><div id="dr_date" class="text-sm font-bold text-gray-800"></div>
            </div>
            <div class="bg-gray-50 border p-3 rounded-lg">
                <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Device MAC</p><div id="dr_mac" class="text-sm font-mono font-bold text-gray-700"></div>
            </div>
            <div class="bg-gray-50 border p-3 rounded-lg">
                <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Origin Location</p><div id="dr_location" class="text-sm font-bold text-gray-800"></div>
            </div>
        </div>
        <div id="dr_data_section" class="hidden">
            <h3 class="font-bold text-gray-900 border-b pb-2 mb-4 flex justify-between items-center">
                <span><i class="fa-solid fa-database text-indigo-500 mr-2"></i> Captured Data</span>
                <span id="dr_camp_tag" class="text-[10px] bg-indigo-100 text-indigo-700 px-2 py-1 rounded font-bold uppercase"></span>
            </h3>
            <div id="dr_data_cards" class="space-y-3"></div>
        </div>
        <div>
            <h3 class="font-bold text-gray-900 border-b pb-2 mb-4"><i class="fa-solid fa-shield-check text-green-500 mr-2"></i> Proof of Consent</h3>
            <div class="bg-gray-900 rounded-lg p-4 font-mono text-[10px] text-green-400 leading-relaxed shadow-inner"><span id="dr_consent"></span></div>
        </div>
    </div>
    <div class="p-6 border-t bg-gray-50 flex justify-between items-center">
        <input type="hidden" id="dr_lead_id">
        <?php if($is_admin): ?>
            <button onclick="triggerDeleteLead()" class="text-red-500 hover:text-red-700 font-bold text-xs flex items-center gap-1 transition"><i class="fa-solid fa-trash"></i> Delete Guest</button>
        <?php else: ?>
            <span class="text-[10px] text-gray-400 italic">Delete restricted to Admin.</span>
        <?php endif; ?>
        <a href="#" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-md hover:bg-indigo-700 transition">View Full History</a>
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
                    <i class="fa-solid fa-tags text-4xl text-gray-300 mb-3"></i><h3 class="text-lg font-bold text-gray-700">No Campaigns Created</h3><p class="text-sm text-gray-500">Create a Survey or Gamification module to capture data.</p>
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
                ?>
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition">
                    <div class="flex justify-between items-start mb-4">
                        <div class="bg-gray-50 p-2 rounded-lg border"><i class="fa-solid <?php echo $icon; ?> text-xl"></i></div>
                        <span class="text-[10px] uppercase font-bold text-gray-400"><?php echo date('M d, Y', strtotime($camp->created_at)); ?></span>
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
                    
                    <div class="mb-8">
                        <label class="block text-sm font-bold text-gray-700 mb-2">Campaign Name <span class="text-red-500">*</span></label>
                        <input type="text" id="camp_name" class="w-full p-3 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-indigo-100 font-bold text-gray-900 text-lg required-field" placeholder="e.g., Summer Promo">
                        <p class="text-[11px] text-gray-500 mt-2 bg-indigo-50 border border-indigo-100 p-2 rounded"><i class="fa-solid fa-circle-info text-indigo-500 mr-1"></i> <strong>Important:</strong> This name becomes the exact tag assigned to the guest. You will use this tag to search for leads and send targeted bulk emails later.</p>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-bold text-gray-700 mb-2">Campaign Type</label>
                        <select id="camp_type" class="w-full p-2.5 border border-gray-300 rounded-lg outline-none bg-white font-bold text-indigo-700" onchange="switchCampTypeView()">
                            <optgroup label="Data Collection">
                                <option value="survey">📝 Advanced Survey / Form</option>
                                <option value="versus">⚔️ Pick A Side (Versus)</option>
                                <option value="birthday">🎂 Birthday Collector</option>
                            </optgroup>
                            <optgroup label="Gamification & Offers (High Conversion)">
                                <option value="promo">🖼️ Image Promo / Coupon</option>
                                <option value="wheel">🎡 Spin the Wheel (Probability Engine)</option>
                                <option value="box">🎁 Mystery Box</option>
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
                                    <select id="camp_q<?php echo $i; ?>_type" class="w-1/3 p-2 border border-gray-300 rounded text-sm outline-none bg-white" onchange="toggleQOpts(<?php echo $i; ?>)"><option value="text">Text Input</option><option value="radio">Pick One (Radio)</option><option value="checkbox">Select Multiple</option></select>
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
                            <div class="img-upload-zone h-40 max-w-sm" id="zone_promo">
                                <span class="text-xs text-gray-500 font-bold" id="lbl_promo"><i class="fa-solid fa-cloud-arrow-up mr-1"></i> Upload Image</span>
                                <img id="img_promo" src="" class="hidden">
                                <button class="rem-btn hidden" id="rem_promo" onclick="removeCampImage(event, 'promo')"><i class="fa-solid fa-xmark"></i></button>
                                <input type="file" id="input_promo_img" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-30">
                            </div>
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
                            <p class="text-xs text-gray-500 mb-4">Set the prizes and probability limits. If a limit is reached, the system will mathematically force the wheel to land on an unlimited item.</p>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Wheel Title <span class="text-red-500">*</span></label>
                            <input type="text" id="camp_wheel_title" class="w-full p-2.5 border border-gray-300 rounded-lg text-sm outline-none required-field mb-4" placeholder="e.g., Spin to Win a Prize!" oninput="updateLivePreview()">
                            
                            <div class="space-y-3 bg-gray-50 p-4 border rounded-lg">
                                <div class="grid grid-cols-12 gap-3 text-[10px] font-bold text-gray-500 uppercase px-1">
                                    <div class="col-span-8">Prize Name</div><div class="col-span-4">Monthly Limit (0=Unlimited)</div>
                                </div>
                                <?php for($i=1; $i<=6; $i++): $req = ($i<=2) ? 'required-field' : ''; ?>
                                <div class="grid grid-cols-12 gap-3 items-center">
                                    <div class="col-span-8"><input type="text" id="wheel_p<?php echo $i; ?>_name" class="w-full p-2 border border-gray-300 rounded text-sm outline-none <?php echo $req; ?>" placeholder="<?php echo $i<=2 ? 'Required Prize' : 'Optional Prize'; ?>" oninput="updateLivePreview()"></div>
                                    <div class="col-span-4"><input type="number" id="wheel_p<?php echo $i; ?>_limit" class="w-full p-2 border border-gray-300 rounded text-sm outline-none" min="0" value="0"></div>
                                </div>
                                <?php endfor; ?>
                            </div>
                            <p class="text-[10px] text-blue-600 bg-blue-50 p-2 rounded border border-blue-200 mt-2"><i class="fa-solid fa-circle-info"></i> Guests will be prompted to "Forward to Email" to claim their prize, ensuring accurate data capture.</p>
                        </div>

                        <div id="ctype_box" class="camp-type-view space-y-4">
                            <h3 class="font-bold text-gray-800 mb-2"><i class="fa-solid fa-box-open text-green-500 mr-2"></i> Mystery Box Inventory</h3>
                            <p class="text-xs text-gray-500 mb-4">Set up to 4 potential prizes. The system selects one based on inventory availability when they click the box.</p>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Box Title <span class="text-red-500">*</span></label>
                            <input type="text" id="camp_box_title" class="w-full p-2.5 border border-gray-300 rounded-lg text-sm outline-none required-field mb-4" placeholder="e.g., Tap the box to reveal your mystery gift!" oninput="updateLivePreview()">
                            
                            <div class="space-y-3 bg-gray-50 p-4 border rounded-lg">
                                <div class="grid grid-cols-12 gap-3 text-[10px] font-bold text-gray-500 uppercase px-1">
                                    <div class="col-span-8">Prize Name</div><div class="col-span-4">Monthly Limit (0=Unlimited)</div>
                                </div>
                                <?php for($i=1; $i<=4; $i++): $req = ($i<=2) ? 'required-field' : ''; ?>
                                <div class="grid grid-cols-12 gap-3 items-center">
                                    <div class="col-span-8"><input type="text" id="box_p<?php echo $i; ?>_name" class="w-full p-2 border border-gray-300 rounded text-sm outline-none <?php echo $req; ?>" placeholder="<?php echo $i<=2 ? 'Required Prize' : 'Optional Prize'; ?>" oninput="updateLivePreview()"></div>
                                    <div class="col-span-4"><input type="number" id="box_p<?php echo $i; ?>_limit" class="w-full p-2 border border-gray-300 rounded text-sm outline-none" min="0" value="0"></div>
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
                                <div class="wheel-preview flex items-center justify-center">
                                    <div class="wheel-pointer"></div>
                                    <div class="w-10 h-10 bg-white rounded-full z-20 shadow-md"></div>
                                </div>
                                <button class="mt-6 bg-indigo-600 text-white font-bold py-3 px-8 rounded-full shadow-lg">SPIN NOW</button>
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

<div id="delete_modal" class="fixed inset-0 bg-gray-900/60 z-[150] hidden flex items-center justify-center backdrop-blur-sm transition-opacity opacity-0">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform scale-95 transition-all" id="delete_modal_content">
        <div class="p-6 text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-triangle-exclamation text-3xl text-red-600"></i>
            </div>
            <h2 class="text-xl font-bold text-gray-900 mb-2">Delete Campaign?</h2>
            <p class="text-sm text-gray-500 mb-6">Are you sure you want to permanently delete this campaign? This action cannot be undone.</p>
            <div class="flex gap-3 justify-center">
                <button onclick="closeDeleteModal()" class="px-5 py-2.5 bg-gray-100 text-gray-700 font-bold rounded-lg hover:bg-gray-200 transition">Cancel</button>
                <button id="btn_confirm_delete" class="px-5 py-2.5 bg-red-600 text-white font-bold rounded-lg shadow-md hover:bg-red-700 transition flex items-center gap-2">Yes, Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
    const mt_leads_data = <?php echo wp_json_encode($js_leads); ?>;

    // --- CRM TAB SWITCHER ---
    function switchCrmTab(tab, el) {
        document.querySelectorAll('.crm-tab').forEach(b => b.classList.remove('active', 'border-indigo-600', 'text-indigo-600'));
        el.classList.add('active', 'border-indigo-600', 'text-indigo-600');
        document.querySelectorAll('.crm-view').forEach(v => v.classList.remove('active'));
        document.getElementById('view_' + tab).classList.add('active');
    }

    // --- GUEST PROFILE DRAWER LOGIC ---
    function openGuestDrawer(id) {
        const lead = mt_leads_data[id];
        if(!lead) return;

        document.getElementById('dr_lead_id').value = lead.id;
        document.getElementById('dr_name').innerText = lead.guest_name || 'Unknown Guest';
        document.getElementById('dr_email').innerText = lead.email;
        
        let statusHtml = lead.status === 'active' ? '<span class="text-green-500 text-sm font-bold flex items-center gap-1"><i class="fa-solid fa-circle text-[10px]"></i> Active</span>' : '<span class="text-red-500 text-sm font-bold flex items-center gap-1"><i class="fa-solid fa-circle text-[10px]"></i> Unsubscribed</span>';
        document.getElementById('dr_status').innerHTML = statusHtml;
        
        document.getElementById('dr_date').innerText = new Date(lead.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        document.getElementById('dr_mac').innerText = lead.guest_mac || 'N/A';
        document.getElementById('dr_location').innerText = lead.store_name || 'Global Portal';
        
        document.getElementById('dr_consent').innerText = lead.consent_log || 'System Override';

        const dataSection = document.getElementById('dr_data_section');
        const cardsCont = document.getElementById('dr_data_cards');
        cardsCont.innerHTML = ''; 

        let surveyData = {};
        try { surveyData = JSON.parse(lead.survey_data); } catch(e) {}
        
        if((surveyData && Object.keys(surveyData).length > 0) || lead.birthday) {
            dataSection.classList.remove('hidden');
            document.getElementById('dr_camp_tag').innerText = lead.campaign_tag || 'Standard Capture';
            
            if(lead.birthday) { cardsCont.innerHTML += `<div class="bg-gray-50 border p-3 rounded-lg flex items-center gap-3"><div class="text-pink-500 text-xl"><i class="fa-solid fa-cake-candles"></i></div><div><p class="text-[10px] uppercase font-bold text-gray-400">Birthday Captured</p><p class="text-sm font-bold text-gray-800">${lead.birthday}</p></div></div>`; }
            if(surveyData.birthday) { cardsCont.innerHTML += `<div class="bg-gray-50 border p-3 rounded-lg flex items-center gap-3"><div class="text-pink-500 text-xl"><i class="fa-solid fa-cake-candles"></i></div><div><p class="text-[10px] uppercase font-bold text-gray-400">Birthday</p><p class="text-sm font-bold text-gray-800">${surveyData.birthday}</p></div></div>`; }
            if(surveyData.rating) {
                let stars = '';
                for(let i=0; i<5; i++) { stars += i < surveyData.rating ? '<i class="fa-solid fa-star text-yellow-400"></i> ' : '<i class="fa-solid fa-star text-gray-300"></i> '; }
                cardsCont.innerHTML += `<div class="bg-gray-50 border p-3 rounded-lg"><p class="text-[10px] uppercase font-bold text-gray-400 mb-1">Experience Rating</p><div class="text-lg">${stars}</div></div>`;
            }
            if(surveyData.versus_choice) { cardsCont.innerHTML += `<div class="bg-indigo-50 border border-indigo-100 p-3 rounded-lg"><p class="text-[10px] uppercase font-bold text-indigo-400 mb-1">A/B Choice Selected</p><p class="text-sm font-bold text-indigo-900">${surveyData.versus_choice}</p></div>`; }

            for (const [key, value] of Object.entries(surveyData)) {
                if(key !== 'rating' && key !== 'versus_choice' && key !== 'birthday') {
                    let valDisplay = Array.isArray(value) ? value.join(', ') : value;
                    cardsCont.innerHTML += `<div class="bg-white border p-3 rounded-lg shadow-sm"><p class="text-[10px] uppercase font-bold text-gray-400 mb-1">${key}</p><p class="text-sm font-bold text-gray-800">${valDisplay}</p></div>`;
                }
            }
        } else {
            dataSection.classList.add('hidden');
        }

        const overlay = document.getElementById('guest_drawer_overlay');
        const drawer = document.getElementById('guest_drawer');
        overlay.classList.remove('hidden');
        setTimeout(() => { overlay.classList.remove('opacity-0'); drawer.classList.remove('translate-x-full'); }, 10);
    }

    function closeGuestDrawer() {
        const overlay = document.getElementById('guest_drawer_overlay');
        const drawer = document.getElementById('guest_drawer');
        drawer.classList.add('translate-x-full');
        overlay.classList.add('opacity-0');
        setTimeout(() => { overlay.classList.add('hidden'); }, 300);
    }

    function triggerDeleteLead() {
        const id = document.getElementById('dr_lead_id').value;
        if(!confirm("Are you sure you want to permanently delete this guest record? This cannot be undone.")) return;
        
        const formData = new FormData();
        formData.append('action', 'mt_delete_guest_lead');
        formData.append('security', mt_nonce);
        formData.append('lead_id', id);

        fetch(mt_ajax_url, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => { if(data.success) { window.location.reload(); } else { alert("Error deleting record."); } });
    }

    // --- CAMPAIGN EDITOR LOGIC ---
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
        if(type === 'radio' || type === 'checkbox') { optInput.classList.remove('hidden'); } 
        else { optInput.classList.add('hidden'); }
        updateLivePreview();
    }

    let campImages = { promo: '', vsa: '', vsb: '' };

    async function uploadToVault(file) {
        const formData = new FormData();
        formData.append('action', 'mt_upload_vault_media');
        formData.append('security', mt_nonce);
        formData.append('media_type', 'wifi');
        formData.append('file', file);
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
                        campImages[key] = url;
                        document.getElementById(`img_${key}`).src = url;
                        document.getElementById(`img_${key}`).classList.remove('hidden');
                        document.getElementById(`rem_${key}`).classList.remove('hidden');
                        updateLivePreview(); 
                    } catch(err) { alert("Upload Failed"); }
                    document.getElementById(`zone_${key}`).style.opacity = '1';
                    e.target.value = '';
                }
            });
        }
    });

    function removeCampImage(event, key) {
        event.preventDefault(); event.stopPropagation();
        campImages[key] = '';
        document.getElementById(`img_${key}`).src = '';
        document.getElementById(`img_${key}`).classList.add('hidden');
        document.getElementById(`rem_${key}`).classList.add('hidden');
        updateLivePreview();
    }

    // --- DYNAMIC LIVE PREVIEW ENGINE ---
    function updateLivePreview() {
        const type = document.getElementById('camp_type').value;
        
        ['survey', 'promo', 'versus', 'birthday', 'wheel', 'box'].forEach(t => document.getElementById('prev_type_' + t).classList.add('hidden'));
        document.getElementById('prev_type_' + type).classList.remove('hidden');

        if(type === 'survey') {
            document.getElementById('prev_stars').classList.toggle('hidden', !document.getElementById('camp_stars').checked);
            const container = document.getElementById('prev_survey_container');
            container.innerHTML = ''; 
            for(let i=1; i<=4; i++) {
                let text = document.getElementById(`camp_q${i}_text`).value;
                let qType = document.getElementById(`camp_q${i}_type`).value;
                let opts = document.getElementById(`camp_q${i}_opts`).value.split(',').map(s=>s.trim()).filter(s=>s);
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
            const wList = document.getElementById('prev_wheel_list');
            wList.innerHTML = '';
            for(let i=1; i<=6; i++) {
                let p = document.getElementById(`wheel_p${i}_name`).value;
                if(p) wList.innerHTML += `<div>🎁 ${p}</div>`;
            }
            if(!wList.innerHTML) wList.innerHTML = '<div class="italic text-gray-400">Add prizes on the left</div>';
        } else if(type === 'box') {
            document.getElementById('prev_box_title').innerText = document.getElementById('camp_box_title').value || 'Open the Mystery Box!';
        }
    }

    function openCampaignEditor(id) {
        document.getElementById('camp_list_state').classList.add('hidden');
        document.getElementById('camp_edit_state').classList.remove('hidden');
        document.getElementById('camp_error_banner').classList.add('hidden');
        document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
        document.getElementById('current_camp_id').value = id;

        if (id === 0) {
            document.getElementById('camp_editor_title').innerText = 'Create New Campaign';
            document.getElementById('camp_name').value = '';
            document.getElementById('camp_type').value = 'survey';
            campImages = { promo: '', vsa: '', vsb: '' };
            document.querySelectorAll('input[type="text"]').forEach(i => i.value = '');
            document.querySelectorAll('input[type="number"]').forEach(i => i.value = 0);
            document.getElementById('camp_promo_text').value = '';
            for(let i=1; i<=4; i++) { document.getElementById(`camp_q${i}_type`).value = 'text'; toggleQOpts(i); }
            ['promo','vsa','vsb'].forEach(k => { document.getElementById(`img_${k}`).classList.add('hidden'); document.getElementById(`rem_${k}`).classList.add('hidden'); });
            switchCampTypeView();
        } else {
            const btn = event.currentTarget;
            document.getElementById('camp_editor_title').innerText = 'Editing Campaign';
            document.getElementById('camp_name').value = btn.getAttribute('data-name');
            
            const type = btn.getAttribute('data-type');
            document.getElementById('camp_type').value = type;

            let config = {};
            try { config = JSON.parse(btn.getAttribute('data-config')); } catch(e) {}

            if(type === 'survey') {
                document.getElementById('camp_stars').checked = config.stars ?? true;
                if(config.questions && Array.isArray(config.questions)) {
                    for(let i=0; i<4; i++) {
                        let qNum = i+1;
                        if(config.questions[i]) {
                            document.getElementById(`camp_q${qNum}_text`).value = config.questions[i].text || '';
                            document.getElementById(`camp_q${qNum}_type`).value = config.questions[i].type || 'text';
                            document.getElementById(`camp_q${qNum}_opts`).value = config.questions[i].options || '';
                        } else {
                            document.getElementById(`camp_q${qNum}_text`).value = '';
                            document.getElementById(`camp_q${qNum}_type`).value = 'text';
                            document.getElementById(`camp_q${qNum}_opts`).value = '';
                        }
                        toggleQOpts(qNum);
                    }
                }
            }
            else if(type === 'promo') {
                document.getElementById('camp_promo_text').value = config.text || '';
                if(config.img) { campImages.promo = config.img; document.getElementById('img_promo').src = config.img; document.getElementById('img_promo').classList.remove('hidden'); document.getElementById('rem_promo').classList.remove('hidden'); }
            }
            else if(type === 'versus') {
                document.getElementById('camp_vs_title').value = config.title || '';
                document.getElementById('camp_vs_mid').value = config.mid || 'VS';
                document.getElementById('camp_vs_a').value = config.a || '';
                document.getElementById('camp_vs_b').value = config.b || '';
                if(config.img_a) { campImages.vsa = config.img_a; document.getElementById('img_vsa').src = config.img_a; document.getElementById('img_vsa').classList.remove('hidden'); document.getElementById('rem_vsa').classList.remove('hidden'); }
                if(config.img_b) { campImages.vsb = config.img_b; document.getElementById('img_vsb').src = config.img_b; document.getElementById('img_vsb').classList.remove('hidden'); document.getElementById('rem_vsb').classList.remove('hidden'); }
            }
            else if(type === 'birthday') {
                document.getElementById('camp_bday_text').value = config.text || '';
            }
            else if(type === 'wheel') {
                document.getElementById('camp_wheel_title').value = config.title || '';
                for(let i=1; i<=6; i++) {
                    if(config.prizes && config.prizes[i-1]) {
                        document.getElementById(`wheel_p${i}_name`).value = config.prizes[i-1].name || '';
                        document.getElementById(`wheel_p${i}_limit`).value = config.prizes[i-1].limit || 0;
                    }
                }
            }
            else if(type === 'box') {
                document.getElementById('camp_box_title').value = config.title || '';
                for(let i=1; i<=4; i++) {
                    if(config.prizes && config.prizes[i-1]) {
                        document.getElementById(`box_p${i}_name`).value = config.prizes[i-1].name || '';
                        document.getElementById(`box_p${i}_limit`).value = config.prizes[i-1].limit || 0;
                    }
                }
            }
            switchCampTypeView();
        }
    }

    function closeCampaignEditor() {
        document.getElementById('camp_edit_state').classList.add('hidden');
        document.getElementById('camp_list_state').classList.remove('hidden');
    }

    function saveCampaign() {
        document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
        document.getElementById('camp_error_banner').classList.add('hidden');
        
        let hasError = false;
        const name = document.getElementById('camp_name');
        if(!name.value.trim()) { name.classList.add('input-error'); hasError = true; }

        const type = document.getElementById('camp_type').value;
        let config = {};

        if(type === 'survey') {
            const q1 = document.getElementById('camp_q1_text');
            if(!q1.value.trim()) { q1.classList.add('input-error'); hasError = true; }
            let questions = [];
            for(let i=1; i<=4; i++) {
                questions.push({ text: document.getElementById(`camp_q${i}_text`).value, type: document.getElementById(`camp_q${i}_type`).value, options: document.getElementById(`camp_q${i}_opts`).value });
            }
            config = { stars: document.getElementById('camp_stars').checked, questions: questions };
        }
        else if(type === 'promo') {
            const txt = document.getElementById('camp_promo_text');
            if(!txt.value.trim()) { txt.classList.add('input-error'); hasError = true; }
            config = { text: txt.value, img: campImages.promo };
        }
        else if(type === 'versus') {
            const title = document.getElementById('camp_vs_title');
            const vsa = document.getElementById('camp_vs_a');
            const vsb = document.getElementById('camp_vs_b');
            if(!title.value.trim()) { title.classList.add('input-error'); hasError = true; }
            if(!vsa.value.trim()) { vsa.classList.add('input-error'); hasError = true; }
            if(!vsb.value.trim()) { vsb.classList.add('input-error'); hasError = true; }
            config = { title: title.value, mid: document.getElementById('camp_vs_mid').value, a: vsa.value, b: vsb.value, img_a: campImages.vsa, img_b: campImages.vsb };
        }
        else if(type === 'birthday') {
            const txt = document.getElementById('camp_bday_text');
            if(!txt.value.trim()) { txt.classList.add('input-error'); hasError = true; }
            config = { text: txt.value };
        }
        else if(type === 'wheel') {
            const title = document.getElementById('camp_wheel_title');
            const p1 = document.getElementById('wheel_p1_name');
            const p2 = document.getElementById('wheel_p2_name');
            if(!title.value.trim()) { title.classList.add('input-error'); hasError = true; }
            if(!p1.value.trim()) { p1.classList.add('input-error'); hasError = true; }
            if(!p2.value.trim()) { p2.classList.add('input-error'); hasError = true; }
            
            let prizes = [];
            for(let i=1; i<=6; i++) {
                let pName = document.getElementById(`wheel_p${i}_name`).value.trim();
                let pLim = document.getElementById(`wheel_p${i}_limit`).value;
                if(pName) prizes.push({ name: pName, limit: parseInt(pLim) || 0 });
            }
            config = { title: title.value, prizes: prizes };
        }
        else if(type === 'box') {
            const title = document.getElementById('camp_box_title');
            const p1 = document.getElementById('box_p1_name');
            const p2 = document.getElementById('box_p2_name');
            if(!title.value.trim()) { title.classList.add('input-error'); hasError = true; }
            if(!p1.value.trim()) { p1.classList.add('input-error'); hasError = true; }
            if(!p2.value.trim()) { p2.classList.add('input-error'); hasError = true; }
            
            let prizes = [];
            for(let i=1; i<=4; i++) {
                let pName = document.getElementById(`box_p${i}_name`).value.trim();
                let pLim = document.getElementById(`box_p${i}_limit`).value;
                if(pName) prizes.push({ name: pName, limit: parseInt(pLim) || 0 });
            }
            config = { title: title.value, prizes: prizes };
        }

        if(hasError) {
            document.getElementById('camp_error_banner').classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        const btn = document.getElementById('btn_save_camp');
        const ogText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

        const formData = new FormData();
        formData.append('action', 'mt_save_campaign');
        formData.append('security', mt_nonce);
        formData.append('campaign_id', document.getElementById('current_camp_id').value);
        formData.append('campaign_name', name.value.trim());
        formData.append('campaign_type', type);
        formData.append('config', JSON.stringify(config));

        fetch(mt_ajax_url, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Saved!';
                btn.classList.add('bg-green-600');
                setTimeout(() => window.location.reload(), 1000);
            } else { 
                document.getElementById('camp_error_text').innerText = "Server Error: " + data.data;
                document.getElementById('camp_error_banner').classList.remove('hidden');
                btn.innerHTML = ogText; 
            }
        });
    }

    let itemToDelete = null;

    function promptDeleteCampaign(id) {
        itemToDelete = id;
        const modal = document.getElementById('delete_modal');
        const content = document.getElementById('delete_modal_content');
        modal.classList.remove('hidden');
        setTimeout(() => { modal.classList.remove('opacity-0'); content.classList.remove('scale-95'); }, 10);
    }

    function closeDeleteModal() {
        const modal = document.getElementById('delete_modal');
        const content = document.getElementById('delete_modal_content');
        content.classList.add('scale-95');
        modal.classList.add('opacity-0');
        setTimeout(() => { modal.classList.add('hidden'); itemToDelete = null; }, 300);
    }

    document.getElementById('btn_confirm_delete').addEventListener('click', function() {
        if(!itemToDelete) return;
        const btn = this;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
        
        const formData = new FormData();
        formData.append('action', 'mt_delete_campaign');
        formData.append('security', mt_nonce);
        formData.append('campaign_id', itemToDelete);
        
        fetch(mt_ajax_url, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => { 
            if(data.success) { window.location.reload(); } 
            else { alert("Error deleting campaign."); closeDeleteModal(); }
        });
    });
</script>