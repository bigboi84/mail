<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_campaigns = $wpdb->prefix . 'mt_campaigns';
$table_stores = $wpdb->prefix . 'mt_stores';

$campaigns = $wpdb->get_results( $wpdb->prepare("SELECT id, campaign_name, campaign_type, config_json FROM $table_campaigns WHERE brand_id = %d ORDER BY created_at DESC", $brand->id) );
$stores = $wpdb->get_results( $wpdb->prepare("SELECT id, store_name, splash_config FROM $table_stores WHERE brand_id = %d ORDER BY store_name ASC", $brand->id) );

$brand_config = json_decode($brand->brand_config, true) ?: [];
$brand_logo = isset($brand_config['logos']['main']) && !empty($brand_config['logos']['main']) ? $brand_config['logos']['main'] : '';
$ext_colors = isset($brand_config['extended_colors']) && count($brand_config['extended_colors']) === 5 ? $brand_config['extended_colors'] : ['#ffffff', '#f3f4f6', '#d1d5db', '#9ca3af', '#4b5563'];
$sec_color = isset($brand_config['secondary_color']) ? $brand_config['secondary_color'] : '#111827';

$global_config = json_decode($brand->splash_config, true) ?: [];
$is_pro_tier = true;

function render_swatches($target_id, $primary, $sec, $ext) {
    $html = '<div class="flex gap-1 mt-1.5">';
    $html .= '<div class="w-4 h-4 rounded cursor-pointer border border-gray-300 shadow-sm hover:scale-110 transition-transform" style="background: '.$primary.';" onclick="setColor(this, \''.$target_id.'\')" title="Primary"></div>';
    $html .= '<div class="w-4 h-4 rounded cursor-pointer border border-gray-300 shadow-sm hover:scale-110 transition-transform" style="background: '.$sec.';" onclick="setColor(this, \''.$target_id.'\')" title="Secondary"></div>';
    foreach($ext as $c) {
        $html .= '<div class="w-4 h-4 rounded cursor-pointer border border-gray-300 shadow-sm hover:scale-110 transition-transform" style="background: '.$c.';" onclick="setColor(this, \''.$target_id.'\')"></div>';
    }
    $html .= '</div>';
    return $html;
}
?>

<style>
    .step-btn.active { border-color: #4f46e5; background-color: #eef2ff; color: #4f46e5; }
    .preview-pane { display: none; }
    .preview-pane.active { display: flex; }
    .img-upload-zone { position: relative; overflow: hidden; border: 1px solid #d1d5db; border-radius: 0.5rem; background: #f9fafb; text-align: center; cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100px; transition: all 0.2s; }
    .img-upload-zone:hover { border-color: #4f46e5; background: #eef2ff; }
    .img-upload-zone img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: 10; background: #fff;}
    .img-upload-zone .rem-btn { position: absolute; top: 6px; right: 6px; background: rgba(239,68,68,0.9); color: white; border-radius: 50%; width: 26px; height: 26px; z-index: 40; display: flex; align-items: center; justify-content: center; font-size: 12px; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
    .img-upload-zone .rem-btn:hover { background: rgba(220,38,38,1); }
    
    .phone-mockup { border: 14px solid #1f2937; border-radius: 40px; height: 667px; width: 315px; background: #fff; position: relative; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); margin: 0 auto; transition: all 0.3s ease; }
    .desktop-mockup { border: 2px solid #d1d5db; border-radius: 12px; height: 600px; width: 100%; max-width: 800px; background: #fff; position: relative; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); margin: 0 auto; transition: all 0.3s ease; }
    .phone-notch { position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 140px; height: 28px; background: #1f2937; border-bottom-left-radius: 18px; border-bottom-right-radius: 18px; z-index: 20;}
    .desktop-browser-bar { display: none; width: 100%; height: 32px; background: #f3f4f6; border-bottom: 1px solid #e5e7eb; align-items: center; padding: 0 12px; gap: 6px; z-index: 20; position: relative; }
    .desktop-mockup .desktop-browser-bar { display: flex; }
    
    .input-error { border-color: #ef4444 !important; background-color: #fef2f2 !important; }
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }

    /* Premium Ad & Gamification Effects */
    .ad-float { animation: floatAd 4s ease-in-out infinite; }
    @keyframes floatAd { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
    .ad-glow { box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 0 15px rgba(79, 70, 229, 0.3); border: 2px solid rgba(79, 70, 229, 0.15); }

    /* Mobile */
    @media(max-width:768px){
        .grid.grid-cols-2{grid-template-columns:1fr!important;}
        .grid.grid-cols-3{grid-template-columns:1fr!important;}
        .phone-mockup{width:260px;height:540px;border-width:10px;border-radius:30px;}
        .desktop-mockup{height:320px;}
        .flex.gap-6{flex-direction:column;gap:12px;}
    }
</style>

<div id="save_modal_overlay" class="fixed inset-0 bg-gray-900/80 z-[100] hidden flex items-center justify-center backdrop-blur-sm transition-opacity duration-300 opacity-0">
    <div class="bg-white rounded-2xl p-8 flex flex-col items-center justify-center w-72 shadow-2xl transform scale-95 transition-all duration-300" id="save_modal_box">
        <div id="save_spinner" class="text-indigo-600 text-5xl mb-5"><i class="fa-solid fa-circle-notch fa-spin"></i></div>
        <div id="save_success_icon" class="text-green-500 text-6xl mb-5 hidden"><i class="fa-solid fa-circle-check"></i></div>
        <h3 id="save_modal_title" class="text-xl font-bold text-gray-900">Syncing...</h3>
        <p id="save_modal_desc" class="text-xs text-gray-500 mt-2 text-center">Publishing your design to the WiFi routers.</p>
        
        <div id="save_modal_actions" class="mt-6 w-full hidden">
            <button onclick="closeSaveModal()" class="w-full bg-gray-100 text-gray-800 font-bold py-2 rounded-lg hover:bg-gray-200 transition">Close</button>
        </div>
    </div>
</div>

<div id="global_warning_modal" class="fixed inset-0 bg-gray-900/60 z-[100] hidden flex items-center justify-center backdrop-blur-sm transition-opacity duration-300 opacity-0">
    <div class="bg-white rounded-2xl p-8 flex flex-col items-center justify-center w-full max-w-md shadow-2xl transform scale-95 transition-all duration-300" id="global_warning_box">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
            <i class="fa-solid fa-triangle-exclamation text-3xl text-red-600"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Overwrite Global Template?</h3>
        <p class="text-sm text-gray-500 text-center mb-6">You are editing the Global Brand Template. Saving this will instantly overwrite the Splash Screens for <strong>ALL locations</strong> that currently rely on the Global default.</p>
        <div class="flex gap-3 w-full">
            <button onclick="closeGlobalWarning()" class="flex-1 bg-gray-100 text-gray-700 font-bold py-2.5 rounded-lg hover:bg-gray-200 transition">Cancel</button>
            <button onclick="confirmGlobalSave()" class="flex-1 bg-red-600 text-white font-bold py-2.5 rounded-lg shadow-md hover:bg-red-700 transition">Yes, Overwrite All</button>
        </div>
    </div>
</div>

<style>
    .vspl-page-header{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:20px;flex-wrap:wrap;gap:12px;}
    .vspl-page-title{font-size:22px;font-weight:900;color:#111827;display:flex;align-items:center;gap:8px;}
    .vspl-page-sub{font-size:13px;color:#6b7280;margin-top:3px;}
</style>

<div class="vspl-page-header">
    <div>
        <div class="vspl-page-title"><i class="fa-solid fa-mobile-screen-button" style="color:var(--mt-primary);"></i> Splash Designer</div>
        <div class="vspl-page-sub">Design the exact login portal your guests will see when they connect to the WiFi.</div>
    </div>
    <button onclick="openSplashAI()" class="flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg font-bold text-sm hover:bg-indigo-700 transition shadow-sm">
        <i class="fa-solid fa-wand-magic-sparkles"></i> Write with AI
    </button>
</div>

<!-- Splash AI Copy Modal -->
<div id="splash_ai_modal" class="fixed inset-0 bg-gray-900/60 z-[500] hidden items-center justify-center backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center"><i class="fa-solid fa-wand-magic-sparkles text-indigo-600"></i></div>
            <div>
                <h3 class="text-lg font-black text-gray-900">AI Splash Copy</h3>
                <p class="text-xs text-gray-500">Let Toucan AI write your headline, subheadline, and CTA button.</p>
            </div>
        </div>
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1">Tone</label>
                <select id="splash_ai_tone" class="w-full p-2.5 border border-gray-300 rounded-lg text-sm font-semibold outline-none focus:border-indigo-400">
                    <option value="friendly">Friendly &amp; Welcoming</option>
                    <option value="exciting">Exciting &amp; Energetic</option>
                    <option value="professional">Professional</option>
                    <option value="casual">Casual &amp; Fun</option>
                    <option value="luxurious">Upscale &amp; Luxurious</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1">Special Offer (optional)</label>
                <input type="text" id="splash_ai_offer" class="w-full p-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-indigo-400" placeholder="e.g. Free dessert on your next visit">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1">Location Name (optional)</label>
                <input type="text" id="splash_ai_location" class="w-full p-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-indigo-400" placeholder="e.g. Downtown Branch">
            </div>
        </div>
        <div id="splash_ai_preview" class="hidden mt-5 p-4 bg-indigo-50 border border-indigo-200 rounded-xl space-y-2 text-sm"></div>
        <div id="splash_ai_error" class="hidden mt-3 text-sm text-red-600 font-medium"></div>
        <div class="flex gap-3 mt-6">
            <button onclick="closeSplashAI()" class="flex-1 bg-gray-100 text-gray-700 py-2.5 rounded-xl font-bold hover:bg-gray-200 transition">Cancel</button>
            <button id="splash_ai_btn" onclick="runSplashAI()" class="flex-1 bg-indigo-600 text-white py-2.5 rounded-xl font-bold hover:bg-indigo-700 transition flex items-center justify-center gap-2">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Generate
            </button>
        </div>
        <div id="splash_ai_credit_info" class="text-[10px] text-gray-400 text-center mt-3"></div>
    </div>
</div>

<div id="splash_error_banner" class="hidden mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded shadow-sm flex justify-between items-center transition-all">
    <span class="text-red-700 text-sm font-bold"><i class="fa-solid fa-triangle-exclamation mr-2"></i> <span id="splash_error_text">Please fix the highlighted fields.</span></span>
    <button onclick="document.getElementById('splash_error_banner').classList.add('hidden')" class="text-red-400 hover:text-red-600"><i class="fa-solid fa-xmark"></i></button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-20">
    
    <div class="p-6 border-b border-gray-100 bg-gray-50 flex justify-between items-center z-50 relative rounded-t-xl">
        <div class="flex items-center gap-4">
            <label class="text-sm font-bold text-gray-700 uppercase tracking-wide">Editing Target:</label>
            <select id="target_location" class="p-2 border border-indigo-300 bg-indigo-50 text-indigo-700 font-bold rounded-lg outline-none cursor-pointer" onchange="loadTargetConfig()">
                <option value="global" data-slug="global">🌍 Global Brand Template</option>
                <?php foreach($stores as $s): 
                    $loc_slug = strtolower(str_replace(' ', '-', $s->store_name));
                ?>
                    <option value="store_<?php echo $s->id; ?>" data-slug="<?php echo esc_attr($loc_slug); ?>">📍 Location: <?php echo esc_html($s->store_name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-center gap-3">
            <a id="btn_live_preview" href="#" target="_blank" class="bg-white border border-gray-300 text-gray-700 px-4 py-2.5 rounded-lg font-bold shadow-sm hover:bg-gray-50 transition flex items-center gap-2 text-sm">
                <i class="fa-solid fa-arrow-up-right-from-square text-indigo-500"></i> View Live Portal
            </a>
            <button onclick="triggerSave()" class="bg-indigo-600 text-white px-8 py-2.5 rounded-lg font-bold shadow-md hover:bg-indigo-700 transition flex items-center gap-2">
                <i class="fa-solid fa-floppy-disk"></i> Publish to WiFi
            </button>
        </div>
    </div>
    
    <div class="flex flex-col lg:flex-row gap-8 p-8 relative">
        
        <div class="w-full lg:w-5/12 space-y-6 pb-12">
            
            <div class="bg-indigo-900 text-white p-3 rounded-lg text-sm font-bold flex justify-between items-center shadow-md">
                <span><i class="fa-solid fa-sliders mr-2"></i> Editing Settings For: <span id="lbl_editing_device" class="text-yellow-300 uppercase tracking-wider">MOBILE</span></span>
            </div>

            <div class="p-4 bg-gray-50 border rounded-xl flex flex-col gap-4">
                <div class="flex items-center justify-between bg-white p-3 rounded-lg border shadow-sm">
                    <div>
                        <p class="font-bold text-gray-800 text-sm">Require Email Verification</p>
                        <p class="text-xs text-gray-500">Sends a link they must click to keep browsing.</p>
                    </div>
                    <input type="checkbox" id="check_verify_email" class="w-5 h-5 text-indigo-600 rounded cursor-pointer" onchange="updateLivePreview()">
                </div>

                <div class="flex gap-2 relative">
                    <button id="btn_flow_1" class="step-btn flex-1 py-2 border-2 border-gray-200 rounded-lg font-bold text-gray-500 transition text-sm" onclick="toggleFlow(1)">1-Step (Login Only)</button>
                    
                    <button id="btn_flow_3" class="step-btn flex-1 py-2 border-2 border-gray-200 rounded-lg font-bold text-gray-500 transition text-sm relative <?php echo !$is_pro_tier ? 'opacity-60 cursor-not-allowed bg-gray-50' : ''; ?>" onclick="<?php echo $is_pro_tier ? 'toggleFlow(3)' : 'triggerProUpgradeModal()'; ?>">
                        3-Step (Data Capture)
                        <?php if(!$is_pro_tier): ?><span class="absolute -top-3 -right-2 bg-yellow-400 text-yellow-900 text-[9px] font-bold px-2 py-0.5 rounded-full shadow-sm border border-yellow-500"><i class="fa-solid fa-lock"></i> PRO</span><?php endif; ?>
                    </button>
                </div>
            </div>

            <div class="p-4 rounded-xl bg-gray-50 border border-gray-200">
                <label class="text-sm font-bold text-gray-800 border-b pb-2 mb-3 flex justify-between items-center">
                    Portal Logo <span class="text-[10px] bg-gray-200 text-gray-600 px-2 py-1 rounded uppercase dev-indicator">Mobile</span>
                </label>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="text-[10px] font-bold text-gray-500 uppercase">Logo Size (%)</label>
                        <input type="range" id="logo_size" min="10" max="100" class="w-full mt-1">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-gray-500 uppercase">Bottom Spacing</label>
                        <input type="range" id="logo_margin" min="-100" max="150" class="w-full mt-1">
                    </div>
                </div>
                <div class="img-upload-zone" id="zone_logo">
                    <span class="text-xs text-gray-400 font-bold"><i class="fa-solid fa-crown block text-2xl mb-1"></i> Custom Location Logo</span>
                    <img id="img_logo" src="" class="hidden">
                    <button class="rem-btn hidden" id="rem_logo" onclick="removeImage(event, 'logo')"><i class="fa-solid fa-xmark"></i></button>
                    <input type="file" id="input_logo_img" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-30">
                </div>
            </div>

            <div id="step_1_container" class="cursor-pointer border-2 p-4 rounded-xl border-blue-400 shadow-sm transition-colors" onclick="switchPreview(1)">
                <div class="flex items-center gap-2 mb-3 justify-between">
                    <div class="flex items-center gap-2">
                        <span class="bg-gray-900 text-white w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold">1</span>
                        <h4 class="font-bold text-gray-800">Login Gate (Visuals)</h4>
                    </div>
                    <span class="text-[10px] bg-gray-200 text-gray-600 px-2 py-1 rounded uppercase font-bold dev-indicator">Mobile</span>
                </div>
                
                <div class="space-y-4">
                    <div class="bg-white p-3 border rounded-lg">
                        <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1">Title Text</label>
                        <textarea id="input_title_1" rows="2" class="w-full px-2 py-2 border rounded-md text-sm mb-3 outline-none font-bold resize-none bg-gray-50"></textarea>
                        <div class="flex items-center gap-3 mb-3">
                            <div>
                                <input type="color" id="input_title_color_1" class="h-8 w-12 p-1 border rounded cursor-pointer block">
                                <?php echo render_swatches('input_title_color_1', $brand->primary_color, $sec_color, $ext_colors); ?>
                            </div>
                            <select id="input_title_size_1" class="flex-1 text-xs border rounded p-2 outline-none h-10">
                                <option value="text-lg">Large</option><option value="text-2xl">Extra Large</option><option value="text-3xl">Huge</option><option value="text-4xl">Massive</option>
                            </select>
                            <button id="btn_title_bold_1" class="w-10 h-10 border rounded text-sm text-gray-700 font-bold" onclick="toggleBold('prev_title_1', this)">B</button>
                        </div>
                        <div class="border-t pt-2 mt-2">
                            <div class="flex justify-between text-[10px] text-gray-400 font-bold uppercase mb-1"><span>Bottom Spacing</span></div>
                            <input type="range" id="input_title_mb_1" min="0" max="100" class="w-full">
                        </div>
                    </div>

                    <div class="bg-white p-3 border rounded-lg">
                        <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1">Description Text</label>
                        <textarea id="input_desc_1" rows="2" class="w-full px-2 py-2 border rounded-md text-sm mb-3 outline-none resize-none bg-gray-50"></textarea>
                        <div class="flex items-center gap-3 mb-3">
                            <div>
                                <input type="color" id="input_desc_color_1" class="h-8 w-12 p-1 border rounded cursor-pointer block">
                                <?php echo render_swatches('input_desc_color_1', $brand->primary_color, $sec_color, $ext_colors); ?>
                            </div>
                            <select id="input_desc_size_1" class="flex-1 text-xs border rounded p-2 outline-none h-10">
                                <option value="text-xs">Small</option><option value="text-sm">Normal</option><option value="text-base">Large</option><option value="text-lg">Extra Large</option>
                            </select>
                            <button id="btn_desc_bold_1" class="w-10 h-10 border rounded text-sm text-gray-700 font-bold" onclick="toggleBold('prev_desc_1', this)">B</button>
                        </div>
                        <div class="border-t pt-2 mt-2">
                            <div class="flex justify-between text-[10px] text-gray-400 font-bold uppercase mb-1"><span>Bottom Spacing</span></div>
                            <input type="range" id="input_desc_mb_1" min="0" max="100" class="w-full">
                        </div>
                    </div>

                    <div class="bg-white p-3 border rounded-lg">
                        <div class="grid grid-cols-2 gap-4 mb-3">
                            <div><label class="text-[10px] font-bold text-gray-400 uppercase block mb-1">Button Text</label><input type="text" id="input_btn_text_1" class="w-full px-2 py-1.5 border rounded text-sm outline-none"></div>
                            <div>
                                <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1">Button Color</label>
                                <input type="color" id="input_btn_color_1" class="w-full h-8 p-1 border rounded cursor-pointer block">
                                <?php echo render_swatches('input_btn_color_1', $brand->primary_color, $sec_color, $ext_colors); ?>
                            </div>
                        </div>
                        <div class="border-t pt-2 mt-2">
                            <div class="flex justify-between text-[10px] text-gray-400 font-bold uppercase mb-1"><span>Top Spacing</span></div>
                            <input type="range" id="input_btn_mt_1" min="0" max="200" class="w-full">
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-3 rounded border mt-3">
                        <label class="text-xs font-bold text-gray-500 border-b pb-1 mb-2 block">Form Fields</label>
                        <div class="flex flex-wrap gap-4 text-sm text-gray-600 mb-4">
                            <label class="flex items-center gap-1"><input type="checkbox" id="check_show_name" onchange="updateLivePreview()" checked> Show Name</label>
                            <label class="flex items-center gap-1"><input type="checkbox" id="check_req_name" onchange="updateLivePreview()"> Require Name</label>
                            <label class="flex items-center gap-1"><input type="checkbox" id="check_show_email" onchange="updateLivePreview()" checked> Show Email</label>
                        </div>
                        <label class="text-xs font-bold text-gray-500 border-b pb-1 mb-2 block">Social Logins</label>
                        <div class="flex flex-wrap gap-4 text-sm text-gray-600">
                            <label class="flex items-center gap-1"><input type="checkbox" id="check_google" onchange="updateLivePreview()"> Google</label>
                            <label class="flex items-center gap-1"><input type="checkbox" id="check_fb" onchange="updateLivePreview()"> Facebook</label>
                            <label class="flex items-center gap-1"><input type="checkbox" id="check_apple" onchange="updateLivePreview()"> Apple</label>
                        </div>
                    </div>

                    <div class="bg-white p-3 border rounded-lg">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Terms & Conditions URL <span class="text-red-500">*</span></label>
                        <p class="text-[9px] text-gray-500 mb-2">Required for compliance. This links at the bottom of the login form.</p>
                        <input type="url" id="input_tos_url" class="w-full px-2 py-1.5 border rounded text-sm outline-none font-bold text-blue-600" placeholder="https://yourdomain.com/terms">
                    </div>

                    <div class="bg-gray-50 p-3 rounded border">
                        <label class="text-xs font-bold text-gray-500 border-b pb-1 mb-2 block">Step 1 Background</label>
                        
                        <select id="input_bg_type_1" class="w-full p-2 border border-gray-300 rounded mb-3 text-sm outline-none bg-white font-bold" onchange="toggleBgMode(1)">
                            <option value="color">Solid Color Background</option>
                            <option value="image">Image Background</option>
                        </select>

                        <div id="bg_color_wrap_1" class="flex items-center gap-3">
                            <input type="color" id="input_bg_color_1" class="h-8 w-10 p-1 border rounded cursor-pointer block">
                            <?php echo render_swatches('input_bg_color_1', $brand->primary_color, $sec_color, $ext_colors); ?>
                        </div>

                        <div id="bg_image_wrap_1" class="hidden space-y-3">
                            <div class="img-upload-zone h-16" id="zone_bg_1">
                                <span class="text-[10px] text-gray-500 font-bold" id="lbl_bg_1">Upload Background</span>
                                <img id="img_bg_1" src="" class="hidden">
                                <button class="rem-btn hidden" id="rem_bg_1" onclick="removeImage(event, '1')"><i class="fa-solid fa-xmark"></i></button>
                                <input type="file" id="input_bg_image_1" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-30">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <select id="input_bg_size_1" class="border rounded text-xs outline-none w-full p-2 bg-white">
                                    <option value="cover">Cover</option><option value="contain">Contain</option>
                                </select>
                                <select id="input_bg_pos_1" class="border rounded text-xs outline-none w-full p-2 bg-white">
                                    <option value="center">Center</option><option value="top">Top</option><option value="bottom">Bottom</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-4 pt-2 border-t items-start">
                                <div>
                                    <label class="text-[10px] uppercase font-bold text-gray-400 block mb-1">Overlay Color</label>
                                    <input type="color" id="input_overlay_color_1" class="w-full h-6 p-0 border rounded cursor-pointer block">
                                    <?php echo render_swatches('input_overlay_color_1', $brand->primary_color, $sec_color, $ext_colors); ?>
                                </div>
                                <div>
                                    <div class="flex justify-between items-center"><label class="text-[10px] uppercase font-bold text-gray-400">Opacity (%)</label><span id="val_overlay_1" class="text-[10px] font-bold text-gray-600">0</span></div>
                                    <input type="range" id="input_overlay_opacity_1" min="0" max="100" class="w-full mt-1" oninput="document.getElementById('val_overlay_1').innerText = this.value; updateLivePreview();">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="step_2_container" class="cursor-pointer border p-4 rounded-xl hover:border-indigo-400 transition-colors" onclick="switchPreview(2)">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="bg-indigo-600 text-white w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold">2</span>
                        <h4 class="font-bold text-indigo-700">Lead Capture Campaign</h4>
                    </div>
                    <span class="text-[10px] bg-gray-200 text-gray-600 px-2 py-1 rounded uppercase font-bold dev-indicator">Mobile</span>
                </div>
                
                <div class="mb-4 bg-indigo-50 border border-indigo-100 p-4 rounded-lg">
                    <h3 class="font-bold text-indigo-900 mb-2"><i class="fa-solid fa-link mr-2"></i> Active Campaign</h3>
                    <select id="splash_campaign" class="w-full p-2.5 border border-indigo-200 rounded outline-none bg-white font-bold text-gray-900 shadow-sm" onchange="updateLivePreview()">
                        <option value="">-- No Campaign (Skip Step 2) --</option>
                        <?php foreach($campaigns as $camp): ?>
                            <option value="<?php echo $camp->id; ?>" data-type="<?php echo esc_attr($camp->campaign_type); ?>" data-config='<?php echo esc_attr($camp->config_json); ?>'><?php echo esc_html($camp->campaign_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="space-y-3 p-3 bg-gray-50 rounded-lg border">
                    <div class="bg-white p-3 border rounded-lg">
                        <div class="grid grid-cols-2 gap-4 mb-3">
                            <div><label class="text-[10px] font-bold text-gray-400 uppercase block mb-1">Button Text</label><input type="text" id="input_btn_text_2" class="w-full px-2 py-1.5 border rounded text-sm outline-none"></div>
                            <div>
                                <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1">Button Color</label>
                                <input type="color" id="input_btn_color_2" class="w-full h-8 p-1 border rounded cursor-pointer block">
                                <?php echo render_swatches('input_btn_color_2', $brand->primary_color, $sec_color, $ext_colors); ?>
                            </div>
                        </div>
                        <div class="border-t pt-2 mt-2">
                            <div class="flex justify-between text-[10px] text-gray-400 font-bold uppercase mb-1"><span>Top Spacing</span></div>
                            <input type="range" id="input_btn_mt_2" min="0" max="200" class="w-full">
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-3 rounded border">
                        <label class="text-xs font-bold text-gray-500 border-b pb-1 mb-2 block">Step 2 Background</label>
                        
                        <select id="input_bg_type_2" class="w-full p-2 border border-gray-300 rounded mb-3 text-sm outline-none bg-white font-bold" onchange="toggleBgMode(2)">
                            <option value="color">Solid Color Background</option>
                            <option value="image">Image Background</option>
                        </select>

                        <div id="bg_color_wrap_2" class="flex items-center gap-3">
                            <input type="color" id="input_bg_color_2" class="h-8 w-10 p-1 border rounded cursor-pointer block">
                            <?php echo render_swatches('input_bg_color_2', $brand->primary_color, $sec_color, $ext_colors); ?>
                        </div>

                        <div id="bg_image_wrap_2" class="hidden space-y-3">
                            <div class="img-upload-zone h-16" id="zone_bg_2">
                                <span class="text-[10px] text-gray-500 font-bold" id="lbl_bg_2">Upload Background</span>
                                <img id="img_bg_2" src="" class="hidden">
                                <button class="rem-btn hidden" id="rem_bg_2" onclick="removeImage(event, '2')"><i class="fa-solid fa-xmark"></i></button>
                                <input type="file" id="input_bg_image_2" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-30">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <select id="input_bg_size_2" class="border rounded text-xs outline-none w-full p-2 bg-white">
                                    <option value="cover">Cover</option><option value="contain">Contain</option>
                                </select>
                                <select id="input_bg_pos_2" class="border rounded text-xs outline-none w-full p-2 bg-white">
                                    <option value="center">Center</option><option value="top">Top</option><option value="bottom">Bottom</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-4 pt-2 border-t items-start">
                                <div>
                                    <label class="text-[10px] uppercase font-bold text-gray-400 block mb-1">Overlay Color</label>
                                    <input type="color" id="input_overlay_color_2" class="w-full h-6 p-0 border rounded cursor-pointer block">
                                    <?php echo render_swatches('input_overlay_color_2', $brand->primary_color, $sec_color, $ext_colors); ?>
                                </div>
                                <div>
                                    <div class="flex justify-between items-center"><label class="text-[10px] uppercase font-bold text-gray-400">Opacity (%)</label><span id="val_overlay_2" class="text-[10px] font-bold text-gray-600">0</span></div>
                                    <input type="range" id="input_overlay_opacity_2" min="0" max="100" class="w-full mt-1" oninput="document.getElementById('val_overlay_2').innerText = this.value; updateLivePreview();">
                                </div>
                            </div>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-[10px] font-bold text-gray-500 uppercase mt-4 border-t pt-3 cursor-pointer hover:text-gray-800">
                        <input type="checkbox" id="check_hide_logo_2" onchange="updateLivePreview()" class="rounded w-4 h-4 text-indigo-600"> Hide Brand Logo on this Step
                    </label>
                </div>
            </div>

            <div id="step_3_container" class="cursor-pointer border p-4 rounded-xl hover:border-green-400 transition-colors" onclick="switchPreview(3)">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="bg-green-500 text-white w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold">3</span>
                        <h4 class="font-bold text-green-700">Success Gate</h4>
                    </div>
                    <span class="text-[10px] bg-gray-200 text-gray-600 px-2 py-1 rounded uppercase font-bold dev-indicator">Mobile</span>
                </div>
                
                <div class="space-y-3 mb-4 p-3 bg-gray-50 rounded-lg border">
                    <div class="bg-white p-3 border rounded-lg">
                        <div class="grid grid-cols-2 gap-4 mb-3">
                            <div><label class="text-[10px] font-bold text-gray-400 uppercase block mb-1">Button Text</label><input type="text" id="input_btn_text_3" class="w-full px-2 py-1.5 border rounded text-sm outline-none"></div>
                            <div>
                                <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1">Button Color</label>
                                <input type="color" id="input_btn_color_3" class="w-full h-8 p-1 border rounded cursor-pointer block">
                                <?php echo render_swatches('input_btn_color_3', $brand->primary_color, $sec_color, $ext_colors); ?>
                            </div>
                        </div>
                        <div class="border-t pt-2 mt-2">
                            <div class="flex justify-between text-[10px] text-gray-400 font-bold uppercase mb-1"><span>Top Spacing</span></div>
                            <input type="range" id="input_btn_mt_3" min="0" max="200" class="w-full">
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-3 rounded border">
                        <label class="text-xs font-bold text-gray-500 border-b pb-1 mb-2 block">Step 3 Background</label>
                        
                        <select id="input_bg_type_3" class="w-full p-2 border border-gray-300 rounded mb-3 text-sm outline-none bg-white font-bold" onchange="toggleBgMode(3)">
                            <option value="color">Solid Color Background</option>
                            <option value="image">Image Background</option>
                        </select>

                        <div id="bg_color_wrap_3" class="flex items-center gap-3">
                            <input type="color" id="input_bg_color_3" class="h-8 w-10 p-1 border rounded cursor-pointer block">
                            <?php echo render_swatches('input_bg_color_3', $brand->primary_color, $sec_color, $ext_colors); ?>
                        </div>

                        <div id="bg_image_wrap_3" class="hidden space-y-3">
                            <div class="img-upload-zone h-16" id="zone_bg_3">
                                <span class="text-[10px] text-gray-500 font-bold" id="lbl_bg_3">Upload Background</span>
                                <img id="img_bg_3" src="" class="hidden">
                                <button class="rem-btn hidden" id="rem_bg_3" onclick="removeImage(event, '3')"><i class="fa-solid fa-xmark"></i></button>
                                <input type="file" id="input_bg_image_3" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-30">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <select id="input_bg_size_3" class="border rounded text-xs outline-none w-full p-2 bg-white">
                                    <option value="cover">Cover</option><option value="contain">Contain</option>
                                </select>
                                <select id="input_bg_pos_3" class="border rounded text-xs outline-none w-full p-2 bg-white">
                                    <option value="center">Center</option><option value="top">Top</option><option value="bottom">Bottom</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-4 pt-2 border-t items-start">
                                <div>
                                    <label class="text-[10px] uppercase font-bold text-gray-400 block mb-1">Overlay Color</label>
                                    <input type="color" id="input_overlay_color_3" class="w-full h-6 p-0 border rounded cursor-pointer block">
                                    <?php echo render_swatches('input_overlay_color_3', $brand->primary_color, $sec_color, $ext_colors); ?>
                                </div>
                                <div>
                                    <div class="flex justify-between items-center"><label class="text-[10px] uppercase font-bold text-gray-400">Opacity (%)</label><span id="val_overlay_3" class="text-[10px] font-bold text-gray-600">0</span></div>
                                    <input type="range" id="input_overlay_opacity_3" min="0" max="100" class="w-full mt-1" oninput="document.getElementById('val_overlay_3').innerText = this.value; updateLivePreview();">
                                </div>
                            </div>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-[10px] font-bold text-gray-500 uppercase mt-4 border-t pt-3 cursor-pointer hover:text-gray-800">
                        <input type="checkbox" id="check_hide_logo_3" onchange="updateLivePreview()" class="rounded w-4 h-4 text-green-600"> Hide Brand Logo on this Step
                    </label>
                </div>

                <div class="space-y-2 bg-white border p-3 rounded-lg">
                    <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1">Success Headline</label>
                    <textarea id="input_success_title" rows="2" class="w-full px-2 py-2 border rounded-md text-sm outline-none resize-none font-bold text-gray-900 bg-gray-50 mb-3"></textarea>
                    
                    <div class="flex items-center gap-3">
                        <div>
                            <input type="color" id="input_success_color" class="h-8 w-12 p-1 border rounded cursor-pointer block">
                            <?php echo render_swatches('input_success_color', $brand->primary_color, $sec_color, $ext_colors); ?>
                        </div>
                        <div class="flex-1 ml-4 border-l pl-4">
                            <div class="flex justify-between text-[10px] text-gray-400 font-bold uppercase mb-1"><span>Bottom Spacing</span></div>
                            <input type="range" id="input_title_mb_3" min="0" max="100" class="w-full mt-1">
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <label class="text-xs font-bold text-blue-900 mb-1 block"><i class="fa-solid fa-diamond-turn-right"></i> Post-Login Redirect</label>
                    <p class="text-[10px] text-blue-700 mb-2">When they click "Browse the Internet", send them to a specific URL (like your Website).</p>
                    <input type="url" id="input_redirect_url" class="w-full px-3 py-2 border border-blue-300 rounded text-sm outline-none bg-white" placeholder="https://yourdomain.com">
                </div>
            </div>
            
        </div>

        <div class="w-full lg:w-7/12 relative">
            <div class="sticky top-8 flex flex-col items-center pt-0 max-h-[calc(100vh-4rem)] overflow-y-auto custom-scrollbar pb-10">
                
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-1 mb-4 flex gap-1 z-50">
                    <button id="btn_mobile" class="px-4 py-2 bg-gray-100 text-gray-800 rounded-md font-bold text-sm transition" onclick="switchDeviceMode('mobile')"><i class="fa-solid fa-mobile-screen mr-1"></i> Mobile</button>
                    <button id="btn_desktop" class="px-4 py-2 text-gray-500 hover:bg-gray-50 rounded-md font-bold text-sm transition" onclick="switchDeviceMode('desktop')"><i class="fa-solid fa-desktop mr-1"></i> Desktop</button>
                    <button id="btn_sync_device" class="px-4 py-2 ml-4 text-blue-600 hover:bg-blue-50 rounded-md font-bold text-xs transition border border-blue-200" onclick="syncDeviceToOther()" title="Copy current design to other device"><i class="fa-solid fa-copy mr-1"></i> Copy to Desktop</button>
                </div>

                <div id="device_frame" class="phone-mockup flex flex-col text-center shadow-2xl relative transition-all duration-300 bg-center bg-no-repeat">
                    
                    <div class="desktop-browser-bar">
                        <div class="w-3 h-3 rounded-full bg-red-400"></div>
                        <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                        <div class="w-3 h-3 rounded-full bg-green-400"></div>
                    </div>

                    <div id="bg_overlay" class="absolute inset-0 z-0 transition-colors"></div>
                    <div id="notch" class="phone-notch"></div>
                    
                    <div id="preview_content_wrapper" class="w-full max-w-sm mx-auto flex flex-col h-full relative z-10 p-6 overflow-y-auto custom-scrollbar">
                        
                        <img id="preview_logo" src="" class="z-10 relative object-contain transition-opacity" style="margin: 0 auto; display: block;" alt="Brand Logo">
                        
                        <div id="preview_1" class="preview-pane active flex-col items-center justify-center flex-1 w-full z-10 relative mt-6 bg-white/95 backdrop-blur-md rounded-2xl p-6 shadow-2xl text-center border border-gray-100">
                            <h2 id="prev_title_1" class="transition-all whitespace-pre-wrap leading-tight font-extrabold tracking-tight"></h2>
                            <p id="prev_desc_1" class="transition-all whitespace-pre-wrap leading-tight text-gray-600 mb-6"></p>
                            
                            <div class="w-full">
                                <input type="text" id="prev_input_name" placeholder="Your Name" class="w-full px-4 py-3 border border-gray-200 rounded-lg bg-gray-50 text-sm outline-none mb-3 text-center font-normal" disabled>
                                <input type="email" id="prev_input_email" placeholder="Email Address *" class="w-full px-4 py-3 border border-gray-200 rounded-lg bg-gray-50 text-sm outline-none mb-3 text-center font-bold" disabled>
                                
                                <div id="prev_tos_block" class="text-left mt-2 mb-4 flex items-start gap-2">
                                    <input type="checkbox" checked disabled class="mt-1">
                                    <p class="text-[10px] text-gray-500 leading-tight">I agree to the <a href="#" id="prev_tos_link" class="text-blue-500 font-bold" target="_blank">Terms & Conditions</a> and Privacy Policy.</p>
                                </div>

                                <button id="prev_btn_1" class="w-full text-white font-bold py-3.5 rounded-xl shadow-md transition-colors flex justify-center items-center gap-2"></button>
                                
                                <div id="social_btns" class="mt-4 flex flex-col gap-2 border-t border-gray-200 pt-4">
                                    <button id="btn_google" class="w-full bg-white border border-gray-300 text-gray-700 font-bold py-2.5 rounded-lg text-sm flex items-center justify-center gap-2 shadow-sm"><img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" width="16"> Continue with Google</button>
                                    <button id="btn_fb" class="w-full bg-blue-600 text-white font-bold py-2.5 rounded-lg text-sm flex items-center justify-center gap-2 shadow-sm"><i class="fa-brands fa-facebook-f text-white"></i> Continue with Facebook</button>
                                    <button id="btn_apple" class="w-full bg-black text-white font-bold py-2.5 rounded-lg text-sm flex items-center justify-center gap-2 shadow-sm"><i class="fa-brands fa-apple text-white text-lg"></i> Continue with Apple</button>
                                </div>
                            </div>
                        </div>
                        
                        <div id="preview_2" class="preview-pane flex-col items-center justify-center flex-1 w-full z-10 relative mt-6 bg-white/95 backdrop-blur-md rounded-2xl p-6 shadow-2xl text-center border border-gray-100">
                            
                            <div id="prev_camp_empty" class="text-xs text-gray-400 italic py-4">
                                Select a campaign from the left to preview it here.
                            </div>

                            <div id="prev_camp_survey" class="w-full text-left hidden">
                                <div id="prev_camp_stars" class="text-center mb-4">
                                    <h2 class="text-sm font-bold text-gray-900 mb-2">Rate your experience:</h2>
                                    <div class="flex gap-2 justify-center text-gray-300"><i class="fa-solid fa-star text-2xl"></i><i class="fa-solid fa-star text-2xl"></i><i class="fa-solid fa-star text-2xl"></i><i class="fa-solid fa-star text-2xl"></i><i class="fa-solid fa-star text-2xl"></i></div>
                                </div>
                                <div id="prev_camp_q_container" class="space-y-3"></div>
                            </div>

                            <div id="prev_camp_promo" class="w-full hidden">
                                <div class="bg-gray-100 w-full rounded-xl overflow-hidden flex items-center justify-center text-gray-300 font-bold min-h-[120px] mb-4 ad-float ad-glow">
                                    <img id="prev_camp_img_promo" src="" class="w-full h-full object-cover hidden">
                                </div>
                                <h2 id="prev_camp_promo_title" class="text-lg font-bold text-gray-900 whitespace-pre-wrap leading-tight mb-4"></h2>
                            </div>

                            <div id="prev_camp_versus" class="w-full hidden">
                                <h2 id="prev_camp_vs_title" class="text-lg font-bold text-gray-900 whitespace-pre-wrap leading-tight mb-4"></h2>
                                <div class="flex items-center justify-center gap-2 mb-4">
                                    <button class="flex-1 flex flex-col items-center border-2 border-gray-200 bg-gray-50 rounded-xl overflow-hidden shadow-sm">
                                        <img id="prev_camp_img_vsa" src="" class="w-full h-20 object-cover hidden">
                                        <span id="prev_camp_vs_a" class="font-bold py-2 text-[10px] text-gray-700 leading-tight"></span>
                                    </button>
                                    <span id="prev_camp_vs_mid" class="text-xs font-bold text-gray-400"></span>
                                    <button class="flex-1 flex flex-col items-center border-2 border-gray-200 bg-gray-50 rounded-xl overflow-hidden shadow-sm">
                                        <img id="prev_camp_img_vsb" src="" class="w-full h-20 object-cover hidden">
                                        <span id="prev_camp_vs_b" class="font-bold py-2 text-[10px] text-gray-700 leading-tight"></span>
                                    </button>
                                </div>
                            </div>

                            <div id="prev_camp_birthday" class="w-full hidden mb-4">
                                <h2 id="prev_camp_bday_text" class="text-lg font-bold text-gray-900 whitespace-pre-wrap leading-tight mb-4"></h2>
                                <input type="date" class="w-full px-4 py-3 border rounded-lg text-center text-gray-600 font-bold" disabled>
                            </div>

                            <div id="prev_camp_mystery_box" class="w-full hidden mb-4">
                                <h2 id="prev_camp_mb_title" class="text-lg font-bold text-gray-900 whitespace-pre-wrap leading-tight mb-4"></h2>
                                <div class="relative w-32 h-32 mx-auto flex items-center justify-center ad-float">
                                    <img src="https://cdn-icons-png.flaticon.com/512/5138/5138131.png" class="w-full h-full drop-shadow-xl">
                                </div>
                            </div>

                            <button id="prev_btn_2" class="w-full text-white font-bold py-3.5 rounded-xl shadow-md hidden transition-colors"></button>
                        </div>

                        <div id="preview_3" class="preview-pane flex-col items-center justify-center flex-1 w-full z-10 relative mt-6 bg-white/95 backdrop-blur-md rounded-2xl p-6 shadow-2xl text-center border border-gray-100">
                            <div class="bg-green-100 text-green-600 w-16 h-16 rounded-full flex items-center justify-center text-2xl mb-4 mx-auto"><i class="fa-solid fa-wifi"></i></div>
                            <h2 id="prev_success_title" class="text-2xl font-extrabold whitespace-pre-wrap leading-tight mb-2"></h2>
                            <p id="prev_success_desc" class="text-sm text-gray-600 mb-6">Your connection is ready. Tap the button below to sync with the network.</p>
                            <button id="prev_btn_3" class="w-full text-white font-bold py-3.5 rounded-xl shadow-md flex justify-center items-center gap-2"></button>
                            <p class="text-[10px] text-gray-500 mt-6 leading-tight text-left bg-gray-50 p-3 rounded-lg border border-gray-100">
                                <i class="fa-brands fa-apple mr-1"></i> <b>iPhone/iPad Users:</b><br>After clicking connect, tap <b>"Done"</b> in the top right corner of your screen to close this window.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // --- STATE MANAGEMENT ---
    const brandLogo = "<?php echo esc_js($brand_logo); ?>";
    
    const globalConfig = <?php echo wp_json_encode($global_config); ?> || {};
    const storeConfigs = {};
    <?php foreach($stores as $s): ?>
        storeConfigs['store_<?php echo $s->id; ?>'] = <?php echo wp_json_encode(json_decode($s->splash_config, true) ?: []); ?>;
    <?php endforeach; ?>

    let activeDevice = 'mobile'; 
    let currentConfig = { mobile: {}, desktop: {} }; 
    let currentBg = { 1: {}, 2: {}, 3: {} };

    function hexToRgba(hex, opacity) {
        if(!hex) return 'transparent';
        let r = parseInt(hex.slice(1, 3), 16), g = parseInt(hex.slice(3, 5), 16), b = parseInt(hex.slice(5, 7), 16);
        if(isNaN(r) || isNaN(g) || isNaN(b)) return 'transparent';
        return `rgba(${r}, ${g}, ${b}, ${opacity / 100})`;
    }

    // --- DATA MIGRATION & LOADING ---
    function loadTargetConfig() {
        const target = document.getElementById('target_location').value;
        let dbConfig = target === 'global' ? globalConfig : (storeConfigs[target] || {});

        if (!dbConfig.mobile) {
            let legacyData = JSON.parse(JSON.stringify(dbConfig));
            if(Object.keys(legacyData).length === 0) legacyData = { step1:{}, step2:{}, step3:{logo:{}} };

            dbConfig = {
                verify_email: legacyData.verify_email ?? true,
                flow_type: legacyData.flow_type || 1,
                campaign_id: legacyData.step2?.campaign_id || '',
                redirect_url: legacyData.step3?.redirect_url || '',
                tos_url: legacyData.tos_url || '',
                mobile: JSON.parse(JSON.stringify(legacyData)),
                desktop: JSON.parse(JSON.stringify(legacyData))
            };
            if(!dbConfig.desktop.logo) dbConfig.desktop.logo = {};
            dbConfig.desktop.logo.width = 40; 
        }

        currentConfig = dbConfig;

        document.getElementById('check_verify_email').checked = currentConfig.verify_email ?? true;
        document.getElementById('splash_campaign').value = currentConfig.campaign_id || '';
        document.getElementById('input_redirect_url').value = currentConfig.redirect_url || '';
        document.getElementById('input_tos_url').value = currentConfig.tos_url || '';

        toggleFlow(currentConfig.flow_type || 1);

        const brandSlug = "<?php echo strtolower(str_replace(' ', '-', $brand->brand_name)); ?>";
        const locSelect = document.getElementById('target_location');
        const locSlug = locSelect.options[locSelect.selectedIndex].getAttribute('data-slug');
        const baseUrl = "<?php echo home_url('/splash/'); ?>";
        document.getElementById('btn_live_preview').href = `${baseUrl}${brandSlug}/${locSlug}`;

        applyStateToUI();
    }

    function applyStateToUI() {
        let state = currentConfig[activeDevice] || {};
        
        const s1 = state.step1 || { title:{}, desc:{}, bg:{}, spacing:{}, fields:{} };
        const s2 = state.step2 || { bg:{}, spacing:{} };
        const s3 = state.step3 || { bg:{}, spacing:{} };

        document.getElementById('logo_size').value = state.logo?.width || (activeDevice==='desktop'?40:80);
        document.getElementById('logo_margin').value = state.logo?.margin_bottom || 24;
        let logoImg = state.logo?.image || '';

        if(logoImg) {
            document.getElementById('img_logo').src = logoImg;
            document.getElementById('img_logo').classList.remove('hidden');
            document.getElementById('rem_logo').classList.remove('hidden');
        } else {
            document.getElementById('img_logo').src = '';
            document.getElementById('img_logo').classList.add('hidden');
            document.getElementById('rem_logo').classList.add('hidden');
        }

        document.getElementById('check_hide_logo_2').checked = s2.hide_logo || false;
        document.getElementById('check_hide_logo_3').checked = s3.hide_logo || false;

        document.getElementById('input_title_1').value = s1.title?.text || 'Welcome to WiFi';
        document.getElementById('input_title_color_1').value = s1.title?.color || '#111827';
        document.getElementById('input_title_size_1').value = s1.title?.size || 'text-2xl';
        if(s1.title?.bold) document.getElementById('btn_title_bold_1').classList.add('bg-gray-300'); else document.getElementById('btn_title_bold_1').classList.remove('bg-gray-300');
        document.getElementById('input_title_mb_1').value = s1.spacing?.title_mb || 8;

        document.getElementById('input_desc_1').value = s1.desc?.text || 'Enter your email to access free WiFi.';
        document.getElementById('input_desc_color_1').value = s1.desc?.color || '#4b5563';
        document.getElementById('input_desc_size_1').value = s1.desc?.size || 'text-sm';
        if(s1.desc?.bold) document.getElementById('btn_desc_bold_1').classList.add('bg-gray-300'); else document.getElementById('btn_desc_bold_1').classList.remove('bg-gray-300');
        document.getElementById('input_desc_mb_1').value = s1.spacing?.desc_mb || 32;

        document.getElementById('input_btn_text_1').value = s1.btn_text || 'Connect to WiFi';
        document.getElementById('input_btn_color_1').value = s1.btn_color || '#4f46e5';
        document.getElementById('input_btn_mt_1').value = s1.spacing?.btn_mt || 32;
        
        // Field toggles
        document.getElementById('check_show_name').checked = s1.fields?.show_name ?? true;
        document.getElementById('check_req_name').checked = s1.fields?.req_name ?? false;
        document.getElementById('check_show_email').checked = s1.fields?.show_email ?? true;

        document.getElementById('check_google').checked = s1.google_auth ?? false;
        document.getElementById('check_fb').checked = s1.fb_auth ?? false;
        document.getElementById('check_apple').checked = s1.apple_auth ?? false;

        document.getElementById('input_btn_text_2').value = s2.btn_text || 'Next Step';
        document.getElementById('input_btn_color_2').value = s2.btn_color || '#4f46e5';
        document.getElementById('input_btn_mt_2').value = s2.spacing?.btn_mt || 16;

        document.getElementById('input_success_title').value = s3.title || "You're connected!";
        document.getElementById('input_success_color').value = s3.title_color || '#111827';
        document.getElementById('input_title_mb_3').value = s3.spacing?.title_mb || 8;
        document.getElementById('input_btn_text_3').value = s3.btn_text || 'Browse the Internet';
        document.getElementById('input_btn_color_3').value = s3.btn_color || '#111827';
        document.getElementById('input_btn_mt_3').value = s3.spacing?.btn_mt || 32;

        [1, 2, 3].forEach(step => {
            let bgObj = (step===1)?s1.bg : ((step===2)?s2.bg : s3.bg);
            if(!bgObj) bgObj = {};
            
            currentBg[step] = {
                type: bgObj.type || (bgObj.image ? 'image' : 'color'),
                color: bgObj.color || '#ffffff',
                image: bgObj.image || '',
                size: bgObj.size || 'cover',
                position: bgObj.position || 'center',
                overlay_c: bgObj.overlay_c || '#000000',
                overlay_o: bgObj.overlay_o || 0
            };
            
            document.getElementById(`input_bg_type_${step}`).value = currentBg[step].type;
            document.getElementById(`input_bg_color_${step}`).value = currentBg[step].color;
            document.getElementById(`input_bg_size_${step}`).value = currentBg[step].size;
            document.getElementById(`input_bg_pos_${step}`).value = currentBg[step].position;
            document.getElementById(`input_overlay_color_${step}`).value = currentBg[step].overlay_c;
            document.getElementById(`input_overlay_opacity_${step}`).value = currentBg[step].overlay_o;
            document.getElementById(`val_overlay_${step}`).innerText = currentBg[step].overlay_o || 0;

            if(currentBg[step].image) {
                let cleanUrl = currentBg[step].image.replace(/^url\(['"]?/, '').replace(/['"]?\)$/, '');
                document.getElementById(`img_bg_${step}`).src = cleanUrl;
                document.getElementById(`img_bg_${step}`).classList.remove('hidden');
                document.getElementById(`rem_bg_${step}`).classList.remove('hidden');
            } else {
                document.getElementById(`img_bg_${step}`).src = '';
                document.getElementById(`img_bg_${step}`).classList.add('hidden');
                document.getElementById(`rem_bg_${step}`).classList.add('hidden');
            }
            
            toggleBgMode(step);
        });

        document.querySelectorAll('input[type="color"]').forEach(el => el.dispatchEvent(new Event('input')));
        updateLivePreview();
    }

    function applyUIToState() {
        [1, 2, 3].forEach(step => {
            if (document.getElementById(`input_bg_type_${step}`)) {
                currentBg[step].type = document.getElementById(`input_bg_type_${step}`).value;
                currentBg[step].color = document.getElementById(`input_bg_color_${step}`).value;
                currentBg[step].size = document.getElementById(`input_bg_size_${step}`).value;
                currentBg[step].position = document.getElementById(`input_bg_pos_${step}`).value;
                currentBg[step].overlay_c = document.getElementById(`input_overlay_color_${step}`).value;
                currentBg[step].overlay_o = document.getElementById(`input_overlay_opacity_${step}`).value;
            }
        });

        const state = {
            logo: { 
                image: document.getElementById('img_logo').src.includes('via.placeholder') ? '' : document.getElementById('img_logo').src, 
                width: document.getElementById('logo_size').value, 
                margin_bottom: document.getElementById('logo_margin').value 
            },
            step1: {
                title: { text: document.getElementById('input_title_1').value, color: document.getElementById('input_title_color_1').value, size: document.getElementById('input_title_size_1').value, bold: document.getElementById('btn_title_bold_1').classList.contains('bg-gray-300') },
                desc: { text: document.getElementById('input_desc_1').value, color: document.getElementById('input_desc_color_1').value, size: document.getElementById('input_desc_size_1').value, bold: document.getElementById('btn_desc_bold_1').classList.contains('bg-gray-300') },
                btn_text: document.getElementById('input_btn_text_1').value,
                btn_color: document.getElementById('input_btn_color_1').value,
                bg: currentBg[1],
                spacing: { title_mb: document.getElementById('input_title_mb_1').value, desc_mb: document.getElementById('input_desc_mb_1').value, btn_mt: document.getElementById('input_btn_mt_1').value },
                fields: {
                    show_name: document.getElementById('check_show_name').checked,
                    req_name: document.getElementById('check_req_name').checked,
                    show_email: document.getElementById('check_show_email').checked
                },
                google_auth: document.getElementById('check_google').checked,
                fb_auth: document.getElementById('check_fb').checked,
                apple_auth: document.getElementById('check_apple').checked
            },
            step2: {
                hide_logo: document.getElementById('check_hide_logo_2').checked,
                btn_text: document.getElementById('input_btn_text_2').value,
                btn_color: document.getElementById('input_btn_color_2').value,
                bg: currentBg[2],
                spacing: { btn_mt: document.getElementById('input_btn_mt_2').value }
            },
            step3: {
                hide_logo: document.getElementById('check_hide_logo_3').checked,
                title: document.getElementById('input_success_title').value,
                title_color: document.getElementById('input_success_color').value,
                btn_text: document.getElementById('input_btn_text_3').value,
                btn_color: document.getElementById('input_btn_color_3').value,
                bg: currentBg[3],
                spacing: { title_mb: document.getElementById('input_title_mb_3').value, btn_mt: document.getElementById('input_btn_mt_3').value }
            }
        };

        ['logo', 'step1', 'step2', 'step3'].forEach(k => {
            if(k==='logo' && state.logo.image === window.location.href) state.logo.image = '';
            if(k!=='logo' && state[k].bg.image === `url(${window.location.href})`) state[k].bg.image = '';
        });

        currentConfig[activeDevice] = state;
    }

    function toggleBgMode(step) {
        const type = document.getElementById(`input_bg_type_${step}`).value;
        currentBg[step].type = type;
        if(type === 'image') {
            document.getElementById(`bg_color_wrap_${step}`).classList.add('hidden');
            document.getElementById(`bg_image_wrap_${step}`).classList.remove('hidden');
        } else {
            document.getElementById(`bg_color_wrap_${step}`).classList.remove('hidden');
            document.getElementById(`bg_image_wrap_${step}`).classList.add('hidden');
        }
        applyBackgrounds(step);
    }

    function switchDeviceMode(device) {
        if(activeDevice === device) return;
        applyUIToState(); 
        
        activeDevice = device;
        
        document.getElementById('btn_mobile').className = `px-4 py-2 rounded-md font-bold text-sm transition ${device==='mobile' ? 'bg-gray-100 text-gray-800' : 'text-gray-500 hover:bg-gray-50'}`;
        document.getElementById('btn_desktop').className = `px-4 py-2 rounded-md font-bold text-sm transition ${device==='desktop' ? 'bg-gray-100 text-gray-800' : 'text-gray-500 hover:bg-gray-50'}`;
        document.getElementById('lbl_editing_device').innerText = device;
        document.querySelectorAll('.dev-indicator').forEach(el => el.innerText = device);

        const btnSync = document.getElementById('btn_sync_device');
        if(device === 'mobile') {
            btnSync.innerHTML = '<i class="fa-solid fa-copy mr-1"></i> Copy to Desktop';
            btnSync.title = "Copy Mobile design to Desktop";
        } else {
            btnSync.innerHTML = '<i class="fa-solid fa-copy mr-1"></i> Copy to Mobile';
            btnSync.title = "Copy Desktop design to Mobile";
        }

        const frame = document.getElementById('device_frame');
        const notch = document.getElementById('notch');
        const contentWrap = document.getElementById('preview_content_wrapper');
        
        if(device === 'desktop') {
            frame.className = 'desktop-mockup flex flex-col text-center shadow-2xl relative transition-all duration-300 bg-center bg-no-repeat';
            notch.style.display = 'none';
            contentWrap.className = 'w-full max-w-lg mx-auto flex flex-col h-full relative z-10 p-6 overflow-y-auto custom-scrollbar';
        } else {
            frame.className = 'phone-mockup flex flex-col text-center shadow-2xl relative transition-all duration-300 bg-center bg-no-repeat';
            notch.style.display = 'block';
            contentWrap.className = 'w-full max-w-sm mx-auto flex flex-col h-full relative z-10 p-6 overflow-y-auto custom-scrollbar';
        }

        applyStateToUI();
        const activeStep = document.querySelector('.preview-pane.active')?.id.split('_')[1] || 1;
        applyBackgrounds(activeStep);
    }

    function syncDeviceToOther() {
        applyUIToState();
        if(activeDevice === 'mobile') {
            if(confirm("Overwrite Desktop design with current Mobile design?")) {
                currentConfig.desktop = JSON.parse(JSON.stringify(currentConfig.mobile));
                alert("Design copied to Desktop.");
            }
        } else {
            if(confirm("Overwrite Mobile design with current Desktop design?")) {
                currentConfig.mobile = JSON.parse(JSON.stringify(currentConfig.desktop));
                alert("Design copied to Mobile.");
            }
        }
    }

    function triggerProUpgradeModal() {
        alert("Campaign Limit Reached or Feature Locked. Upgrade to the Pro Plan to unlock Data Capture Steps.");
    }

    function toggleFlow(type) {
        document.querySelectorAll('.step-btn').forEach(b => b.classList.remove('active', 'text-red-600', 'bg-red-50', 'border-red-500'));
        document.getElementById('btn_flow_'+type).classList.add('active', 'text-red-600', 'bg-red-50', 'border-red-500');
        if(type === 1) {
            document.getElementById('step_2_container').classList.add('hidden');
            document.getElementById('step_3_container').classList.add('hidden');
            switchPreview(1);
        } else {
            document.getElementById('step_2_container').classList.remove('hidden');
            document.getElementById('step_3_container').classList.remove('hidden');
        }
    }

    function switchPreview(step) {
        document.querySelectorAll('.preview-pane').forEach(p => p.classList.remove('active'));
        document.getElementById('preview_' + step).classList.add('active');
        
        applyBackgrounds(step);
        
        document.getElementById('step_1_container').classList.remove('border-blue-400');
        document.getElementById('step_2_container').classList.remove('border-indigo-400');
        document.getElementById('step_3_container').classList.remove('border-green-400');
        
        if(step === 1) document.getElementById('step_1_container').classList.add('border-blue-400');
        if(step === 2) document.getElementById('step_2_container').classList.add('border-indigo-400');
        if(step === 3) document.getElementById('step_3_container').classList.add('border-green-400');

        updateLivePreview();
    }

    function toggleBold(targetId, btn) { 
        document.getElementById(targetId).classList.toggle('font-bold'); 
        btn.classList.toggle('bg-gray-300'); 
        updateLivePreview();
    }

    function setColor(el, targetId) {
        let color = el.style.backgroundColor;
        let hex;
        if(color.startsWith('rgb')) {
            let rgb = color.match(/\d+/g);
            hex = '#' + rgb.map(x => parseInt(x).toString(16).padStart(2, '0')).join('');
        } else { hex = color; }
        let input = document.getElementById(targetId);
        input.value = hex;
        input.dispatchEvent(new Event('input'));
    }

    function applyBackgrounds(step) {
        const frame = document.getElementById('device_frame');
        const overlay = document.getElementById('bg_overlay');
        
        if (currentBg[step].type === 'color') {
            frame.style.backgroundColor = document.getElementById(`input_bg_color_${step}`).value;
            frame.style.backgroundImage = 'none';
            overlay.style.backgroundColor = 'transparent';
        } else {
            frame.style.backgroundColor = document.getElementById(`input_bg_color_${step}`).value;
            const imgUrl = document.getElementById(`img_bg_${step}`).src;
            if(imgUrl && !imgUrl.includes(window.location.href)) { frame.style.backgroundImage = `url(${imgUrl})`; } 
            else { frame.style.backgroundImage = 'none'; }
            
            frame.style.backgroundSize = document.getElementById(`input_bg_size_${step}`).value;
            frame.style.backgroundPosition = document.getElementById(`input_bg_pos_${step}`).value;
            overlay.style.backgroundColor = hexToRgba(document.getElementById(`input_overlay_color_${step}`).value, document.getElementById(`input_overlay_opacity_${step}`).value);
        }
    }

    function updateLivePreview() {
        const activeStep = document.querySelector('.preview-pane.active')?.id.split('_')[1] || 1;
        
        let hideLogo = false;
        if(activeStep == 2 && document.getElementById('check_hide_logo_2').checked) hideLogo = true;
        if(activeStep == 3 && document.getElementById('check_hide_logo_3').checked) hideLogo = true;

        const customLogo = document.getElementById('img_logo').src;
        const logoEl = document.getElementById('preview_logo');
        
        if(hideLogo || (!customLogo.includes('http') && !brandLogo)) {
            logoEl.classList.add('opacity-0', 'h-0', 'mb-0', 'mt-0', 'hidden');
        } else {
            logoEl.classList.remove('opacity-0', 'h-0', 'mb-0', 'mt-0', 'hidden');
            logoEl.src = (customLogo && !customLogo.includes(window.location.href)) ? customLogo : brandLogo;
            logoEl.style.width = document.getElementById('logo_size').value + '%';
            logoEl.style.marginBottom = document.getElementById('logo_margin').value + 'px';
        }

        // Step 1 UI Updates
        const tosUrl = document.getElementById('input_tos_url').value;
        document.getElementById('prev_tos_link').href = tosUrl ? tosUrl : '#';

        document.getElementById('prev_title_1').innerText = document.getElementById('input_title_1').value;
        document.getElementById('prev_title_1').style.color = document.getElementById('input_title_color_1').value;
        document.getElementById('prev_title_1').style.marginBottom = document.getElementById('input_title_mb_1').value + 'px';
        const t1El = document.getElementById('prev_title_1');
        t1El.className = `${document.getElementById('btn_title_bold_1').classList.contains('bg-gray-300') ? 'font-extrabold' : 'font-bold'} transition-all whitespace-pre-wrap leading-tight tracking-tight ${document.getElementById('input_title_size_1').value}`;

        document.getElementById('prev_desc_1').innerText = document.getElementById('input_desc_1').value;
        document.getElementById('prev_desc_1').style.color = document.getElementById('input_desc_color_1').value;
        document.getElementById('prev_desc_1').style.marginBottom = document.getElementById('input_desc_mb_1').value + 'px';
        const d1El = document.getElementById('prev_desc_1');
        d1El.className = `${document.getElementById('btn_desc_bold_1').classList.contains('bg-gray-300') ? 'font-bold' : ''} transition-all whitespace-pre-wrap leading-tight text-gray-600 mb-6 ${document.getElementById('input_desc_size_1').value}`;

        document.getElementById('prev_btn_1').innerText = document.getElementById('input_btn_text_1').value;
        document.getElementById('prev_btn_1').style.backgroundColor = document.getElementById('input_btn_color_1').value;
        document.getElementById('prev_btn_1').style.marginTop = document.getElementById('input_btn_mt_1').value + 'px';
        
        // Field Visibility
        const showName = document.getElementById('check_show_name').checked;
        const reqName = document.getElementById('check_req_name').checked;
        const showEmail = document.getElementById('check_show_email').checked;

        const nameInput = document.getElementById('prev_input_name');
        nameInput.classList.toggle('hidden', !showName);
        nameInput.placeholder = reqName ? "Your Name *" : "Your Name (Optional)";
        
        document.getElementById('prev_input_email').classList.toggle('hidden', !showEmail);

        // Hide Main Connect Button if BOTH fields are hidden (Pure Social login)
        if(!showName && !showEmail) {
            document.getElementById('prev_btn_1').classList.add('hidden');
            document.getElementById('prev_tos_block').classList.add('hidden');
        } else {
            document.getElementById('prev_btn_1').classList.remove('hidden');
            document.getElementById('prev_tos_block').classList.remove('hidden');
        }

        const showG = document.getElementById('check_google').checked;
        const showF = document.getElementById('check_fb').checked;
        const showA = document.getElementById('check_apple').checked;

        document.getElementById('btn_google').classList.toggle('hidden', !showG);
        document.getElementById('btn_fb').classList.toggle('hidden', !showF);
        document.getElementById('btn_apple').classList.toggle('hidden', !showA);

        const socialWrap = document.getElementById('social_btns');
        if(!showG && !showF && !showA) {
            socialWrap.classList.add('hidden');
        } else {
            socialWrap.classList.remove('hidden');
            if(!showName && !showEmail) {
                socialWrap.classList.remove('border-t', 'pt-4');
            } else {
                socialWrap.classList.add('border-t', 'pt-4');
            }
        }

        // Step 2 & 3 Updates (Standard)
        const campSelect = document.getElementById('splash_campaign');
        const emptyState = document.getElementById('prev_camp_empty');
        const btn2 = document.getElementById('prev_btn_2');
        
        btn2.innerText = document.getElementById('input_btn_text_2').value;
        btn2.style.backgroundColor = document.getElementById('input_btn_color_2').value;
        btn2.style.marginTop = document.getElementById('input_btn_mt_2').value + 'px';
        ['survey', 'promo', 'versus', 'birthday', 'mystery_box'].forEach(t => document.getElementById('prev_camp_'+t).classList.add('hidden'));

        if (campSelect.value) {
            emptyState.classList.add('hidden');
            btn2.classList.remove('hidden');
            const opt = campSelect.options[campSelect.selectedIndex];
            const type = opt.getAttribute('data-type');
            let campConfig = {};
            try { campConfig = JSON.parse(opt.getAttribute('data-config')); } catch(e){}

            document.getElementById('prev_camp_'+type).classList.remove('hidden');
            if(type === 'survey') {
                document.getElementById('prev_camp_stars').classList.toggle('hidden', !campConfig.stars);
                const qCont = document.getElementById('prev_camp_q_container');
                qCont.innerHTML = '';
                if(campConfig.questions) {
                    campConfig.questions.forEach(q => {
                        if(q.text) {
                            let html = `<label class="block text-[10px] font-bold text-gray-500 mb-1">${q.text}</label>`;
                            if(q.type === 'text') {
                                html += `<input type="text" class="w-full px-3 py-1.5 border border-gray-200 rounded-lg text-sm bg-gray-50" disabled>`;
                            } else if (q.type === 'radio' || q.type === 'checkbox') {
                                let opts = q.options ? q.options.split(',').map(s=>s.trim()).filter(s=>s) : [];
                                opts.forEach(o => { html += `<div class="flex items-center gap-2 mb-1"><input type="${q.type}" disabled class="text-indigo-600"> <span class="text-xs text-gray-700">${o}</span></div>`; });
                            }
                            qCont.innerHTML += `<div class="mb-3">${html}</div>`;
                        }
                    });
                }
            } else if (type === 'promo') {
                document.getElementById('prev_camp_promo_title').innerText = campConfig.text || '';
                if(campConfig.img) { document.getElementById('prev_camp_img_promo').src = campConfig.img; document.getElementById('prev_camp_img_promo').classList.remove('hidden'); } else document.getElementById('prev_camp_img_promo').classList.add('hidden');
            } else if (type === 'versus') {
                document.getElementById('prev_camp_vs_title').innerText = campConfig.title || '';
                document.getElementById('prev_camp_vs_mid').innerText = campConfig.mid || 'VS';
                document.getElementById('prev_camp_vs_a').innerText = campConfig.a || '';
                document.getElementById('prev_camp_vs_b').innerText = campConfig.b || '';
                if(campConfig.img_a) { document.getElementById('prev_camp_img_vsa').src = campConfig.img_a; document.getElementById('prev_camp_img_vsa').classList.remove('hidden'); } else document.getElementById('prev_camp_img_vsa').classList.add('hidden');
                if(campConfig.img_b) { document.getElementById('prev_camp_img_vsb').src = campConfig.img_b; document.getElementById('prev_camp_img_vsb').classList.remove('hidden'); } else document.getElementById('prev_camp_img_vsb').classList.add('hidden');
            } else if (type === 'birthday') {
                document.getElementById('prev_camp_bday_text').innerText = campConfig.text || '';
            } else if (type === 'mystery_box') {
                document.getElementById('prev_camp_mb_title').innerText = campConfig.text || 'Tap to unlock your prize!';
            }
        } else {
            emptyState.classList.remove('hidden');
            btn2.classList.add('hidden');
        }

        // Step 3 Verification Check
        document.getElementById('prev_success_title').innerText = document.getElementById('input_success_title').value;
        document.getElementById('prev_success_title').style.color = document.getElementById('input_success_color').value;
        document.getElementById('prev_success_title').style.marginBottom = document.getElementById('input_title_mb_3').value + 'px';
        document.getElementById('prev_btn_3').innerText = document.getElementById('input_btn_text_3').value;
        document.getElementById('prev_btn_3').style.backgroundColor = document.getElementById('input_btn_color_3').value;
        document.getElementById('prev_btn_3').style.marginTop = document.getElementById('input_btn_mt_3').value + 'px';
        
        if (document.getElementById('check_verify_email').checked) {
            document.getElementById('prev_success_desc').innerText = "You have temporary access. Check your email to verify your connection and keep browsing.";
        } else {
            document.getElementById('prev_success_desc').innerText = "Your connection is ready. Tap the button below to sync with the network.";
        }

        if(activeStep) applyBackgrounds(activeStep);
    }

    ['input_title_1', 'input_desc_1', 'input_btn_text_1', 'input_btn_text_2', 'input_success_title', 'input_btn_text_3', 'logo_size', 'logo_margin', 'input_title_mb_1', 'input_desc_mb_1', 'input_btn_mt_1', 'input_btn_mt_2', 'input_title_mb_3', 'input_btn_mt_3', 'input_title_color_1', 'input_desc_color_1', 'input_btn_color_1', 'input_btn_color_2', 'input_success_color', 'input_btn_color_3', 'input_title_size_1', 'input_desc_size_1', 'input_tos_url'].forEach(id => {
        document.getElementById(id).addEventListener('input', updateLivePreview);
        document.getElementById(id).addEventListener('change', updateLivePreview);
    });

    [1, 2, 3].forEach(step => {
        document.getElementById(`input_bg_color_${step}`).addEventListener('input', e => { applyBackgrounds(step); });
        document.getElementById(`input_bg_size_${step}`).addEventListener('change', e => { applyBackgrounds(step); });
        document.getElementById(`input_bg_pos_${step}`).addEventListener('change', e => { applyBackgrounds(step); });
        document.getElementById(`input_overlay_color_${step}`).addEventListener('input', e => { applyBackgrounds(step); });
        document.getElementById(`input_overlay_opacity_${step}`).addEventListener('input', e => { document.getElementById(`val_overlay_${step}`).innerText = e.target.value; applyBackgrounds(step); });
    });

    async function uploadAndGetUrl(file, type) {
        const formData = new FormData();
        formData.append('action', 'mt_upload_vault_media');
        formData.append('security', mt_nonce);
        formData.append('media_type', type);
        formData.append('file', file);
        const res = await fetch(mt_ajax_url, { method: 'POST', body: formData });
        const data = await res.json();
        if(data.success) return data.data.url;
        throw new Error(data.data);
    }

    [1, 2, 3].forEach(step => {
        document.getElementById(`input_bg_image_${step}`).addEventListener('change', async e => {
            if(e.target.files && e.target.files[0]) {
                const zone = document.getElementById(`zone_bg_${step}`);
                zone.style.opacity = '0.5';
                try {
                    const url = await uploadAndGetUrl(e.target.files[0], 'wifi');
                    document.getElementById(`img_bg_${step}`).src = url;
                    document.getElementById(`img_bg_${step}`).classList.remove('hidden');
                    document.getElementById(`rem_bg_${step}`).classList.remove('hidden');
                    currentBg[step].image = `url(${url})`;
                    updateLivePreview();
                } catch(err) { alert("Upload Error: " + err); }
                zone.style.opacity = '1';
                e.target.value = '';
            }
        });
    });

    document.getElementById(`input_logo_img`).addEventListener('change', async e => {
        if(e.target.files && e.target.files[0]) {
            const zone = document.getElementById(`zone_logo`);
            zone.style.opacity = '0.5';
            try {
                const url = await uploadAndGetUrl(e.target.files[0], 'logo');
                document.getElementById(`img_logo`).src = url;
                document.getElementById(`img_logo`).classList.remove('hidden');
                document.getElementById(`rem_logo`).classList.remove('hidden');
                updateLivePreview();
            } catch(err) { alert("Upload Error: " + err); }
            zone.style.opacity = '1';
            e.target.value = '';
        }
    });

    function removeImage(event, type) {
        event.preventDefault();
        event.stopPropagation();
        if(type === 'logo') {
            document.getElementById(`img_logo`).src = '';
            document.getElementById(`img_logo`).classList.add('hidden');
            document.getElementById(`rem_logo`).classList.add('hidden');
        } else {
            document.getElementById(`img_bg_${type}`).src = '';
            document.getElementById(`img_bg_${type}`).classList.add('hidden');
            document.getElementById(`rem_bg_${type}`).classList.add('hidden');
            currentBg[type].image = '';
        }
        updateLivePreview();
    }

    function closeSaveModal() {
        const modal = document.getElementById('save_modal_overlay');
        const box = document.getElementById('save_modal_box');
        box.classList.remove('scale-100');
        box.classList.add('scale-95');
        modal.classList.remove('opacity-100');
        modal.classList.add('opacity-0');
        setTimeout(() => { modal.classList.add('hidden'); }, 300);
    }

    function openGlobalWarning() {
        const modal = document.getElementById('global_warning_modal');
        const box = document.getElementById('global_warning_box');
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.classList.add('opacity-100');
            box.classList.remove('scale-95');
            box.classList.add('scale-100');
        }, 10);
    }

    function closeGlobalWarning() {
        const modal = document.getElementById('global_warning_modal');
        const box = document.getElementById('global_warning_box');
        box.classList.remove('scale-100');
        box.classList.add('scale-95');
        modal.classList.remove('opacity-100');
        modal.classList.add('opacity-0');
        setTimeout(() => { modal.classList.add('hidden'); }, 300);
    }

    function confirmGlobalSave() {
        closeGlobalWarning();
        executeSave('global');
    }

    function triggerSave() {
        document.getElementById('splash_error_banner').classList.add('hidden');
        
        let hasError = false;
        
        // Only require button text if either name or email is showing
        if(document.getElementById('check_show_name').checked || document.getElementById('check_show_email').checked) {
            const btnText1 = document.getElementById('input_btn_text_1');
            if(!btnText1.value.trim()) { btnText1.classList.add('input-error'); hasError = true; } 
            else { btnText1.classList.remove('input-error'); }
        }

        const tosUrl = document.getElementById('input_tos_url');
        if(!tosUrl.value.trim()) { tosUrl.classList.add('input-error'); hasError = true; } 
        else { tosUrl.classList.remove('input-error'); }

        if(hasError) {
            document.getElementById('splash_error_banner').classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        const target = document.getElementById('target_location').value;
        if(target === 'global') {
            openGlobalWarning();
            return;
        }

        executeSave(target);
    }

    async function executeSave(target) {
        const modal = document.getElementById('save_modal_overlay');
        const box = document.getElementById('save_modal_box');
        
        document.getElementById('save_spinner').classList.remove('hidden');
        document.getElementById('save_success_icon').classList.add('hidden');
        document.getElementById('save_modal_title').innerText = 'Syncing...';
        document.getElementById('save_modal_desc').innerText = 'Publishing your design to the WiFi routers.';
        document.getElementById('save_modal_actions').classList.add('hidden');
        document.getElementById('save_modal_title').classList.remove('text-red-600');

        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.classList.add('opacity-100');
            box.classList.remove('scale-95');
            box.classList.add('scale-100');
        }, 10);

        try {
            applyUIToState();
            const masterConfig = {
                verify_email: document.getElementById('check_verify_email').checked,
                flow_type: document.querySelector('.step-btn.active') ? (document.querySelector('.step-btn.active').innerText.includes('1-Step') ? 1 : 3) : 1,
                campaign_id: document.getElementById('splash_campaign').value,
                redirect_url: document.getElementById('input_redirect_url').value,
                tos_url: document.getElementById('input_tos_url').value.trim(),
                mobile: currentConfig.mobile,
                desktop: currentConfig.desktop
            };

            if(target === 'global') { Object.assign(globalConfig, masterConfig); } 
            else { storeConfigs[target] = masterConfig; }

            const formData = new FormData();
            formData.append('action', 'mt_save_splash_config');
            formData.append('security', mt_nonce); 
            formData.append('target', target); 
            formData.append('config', JSON.stringify(masterConfig));
            
            const response = await fetch(mt_ajax_url, { method: 'POST', body: formData });
            const text = await response.text();
            
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    document.getElementById('save_spinner').classList.add('hidden');
                    document.getElementById('save_success_icon').classList.remove('hidden');
                    document.getElementById('save_modal_title').innerText = 'Synced Successfully!';
                    document.getElementById('save_modal_desc').innerText = 'Your routers are now using the updated design.';
                    setTimeout(closeSaveModal, 2000);
                } else { throw new Error(data.data); }
            } catch (jsonError) { throw new Error("Server Error: " + text); }

        } catch (jsError) { 
            document.getElementById('save_spinner').classList.add('hidden');
            document.getElementById('save_modal_title').innerText = 'Sync Failed';
            document.getElementById('save_modal_title').classList.add('text-red-600');
            document.getElementById('save_modal_desc').innerText = jsError.message;
            document.getElementById('save_modal_actions').classList.remove('hidden');
        }
    }

    window.addEventListener('DOMContentLoaded', () => { loadTargetConfig(); });

    // ── SPLASH AI COPY ────────────────────────────────────────────────────────
    function openSplashAI() {
        const modal = document.getElementById('splash_ai_modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.getElementById('splash_ai_preview').classList.add('hidden');
        document.getElementById('splash_ai_error').classList.add('hidden');
    }
    function closeSplashAI() {
        const modal = document.getElementById('splash_ai_modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    function runSplashAI() {
        const btn  = document.getElementById('splash_ai_btn');
        const errBox = document.getElementById('splash_ai_error');
        errBox.classList.add('hidden');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Writing...';

        const fd = new FormData();
        fd.append('action',   'mt_ai_splash_copy');
        fd.append('security', mt_nonce);
        fd.append('tone',     document.getElementById('splash_ai_tone').value);
        fd.append('offer',    document.getElementById('splash_ai_offer').value);
        fd.append('location', document.getElementById('splash_ai_location').value);

        fetch(mt_ajax_url, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> Regenerate';
                if (!res.success) {
                    errBox.textContent = res.data?.message || res.data || 'AI error. Please try again.';
                    errBox.classList.remove('hidden');
                    return;
                }
                const d = res.data;
                const preview = document.getElementById('splash_ai_preview');
                preview.innerHTML = `
                    <p class="text-[10px] font-bold uppercase tracking-widest text-indigo-500 mb-2">Preview — click Apply to use</p>
                    <div class="font-black text-gray-900 text-base">${d.headline || ''}</div>
                    <div class="text-gray-600">${d.subheadline || ''}</div>
                    <div class="inline-block bg-indigo-600 text-white px-4 py-1.5 rounded-lg text-sm font-bold mt-1">${d.cta_button || 'Connect Now'}</div>
                    <button onclick="applySplashAICopy(${JSON.stringify(d).replace(/"/g,'&quot;')})" class="block w-full mt-3 bg-green-500 text-white py-2 rounded-xl font-bold hover:bg-green-600 transition text-sm">Apply to Splash Page</button>`;
                preview.classList.remove('hidden');
                if (res.data.remaining !== undefined) {
                    document.getElementById('splash_ai_credit_info').textContent = res.data.remaining + ' AI writes remaining this month';
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> Retry';
                errBox.textContent = 'Network error. Please try again.';
                errBox.classList.remove('hidden');
            });
    }
    function applySplashAICopy(d) {
        // Try to find the active campaign's headline / CTA fields and populate them
        const headlineInput = document.getElementById('field_headline') || document.querySelector('input[data-field="headline"]') || document.querySelector('[id*="headline"]');
        const ctaInput      = document.getElementById('field_cta_text') || document.querySelector('input[data-field="cta_text"]') || document.querySelector('[id*="cta"]');
        const subInput      = document.getElementById('field_subheadline') || document.querySelector('input[data-field="subheadline"]') || document.querySelector('[id*="sub"]');
        if (headlineInput) headlineInput.value = d.headline || '';
        if (subInput)      subInput.value      = d.subheadline || '';
        if (ctaInput)      ctaInput.value      = d.cta_button || '';
        // Trigger any preview refresh that might be listening
        ['field_headline','field_cta_text','field_subheadline'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.dispatchEvent(new Event('input', { bubbles: true }));
        });
        closeSplashAI();
        if (typeof showToast === 'function') showToast('AI copy applied! Review the preview and save.', 'success');
    }
</script>