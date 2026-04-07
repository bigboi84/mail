<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_templates = $wpdb->prefix . 'mt_email_templates';
// Fetch Active and Trashed
$active_templates = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table_templates WHERE brand_id = %d AND status = 'active' ORDER BY created_at DESC", $brand->id) );
$trashed_templates = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table_templates WHERE brand_id = %d AND status = 'trashed' ORDER BY deleted_at DESC", $brand->id) );

// FETCH DYNAMIC BRANDING
$brand_config = json_decode($brand->brand_config, true) ?: [];
$brand_logo = isset($brand_config['logos']['main']) && !empty($brand_config['logos']['main']) ? $brand_config['logos']['main'] : 'https://placehold.co/400x150/ffffff/0f172a?text=Your+Logo';
$brand_color = !empty($brand->primary_color) ? $brand->primary_color : '#0f172a';
$brand_name = esc_html($brand->brand_name);
$sender_email = sanitize_title($brand->brand_name) . '@mailtoucan.pro';

// PRE-LOAD VAULT IMAGES
$vault_assets = [];
if(isset($brand_config['logos']['main']) && !empty($brand_config['logos']['main'])) { $vault_assets[] = $brand_config['logos']['main']; }
if(isset($brand_config['logos']['footer']) && !empty($brand_config['logos']['footer'])) { $vault_assets[] = $brand_config['logos']['footer']; }
if(isset($brand_config['vault']) && is_array($brand_config['vault'])) {
    foreach($brand_config['vault'] as $media) {
        if($media['type'] === 'image') $vault_assets[] = $media['url'];
    }
}
$mt_palette = get_option( 'mt_brand_palette', ['accent' => '#FCC753', 'dark' => '#1A232E'] );

// STRICT 3-TIER DEFAULT MJML TEMPLATE
$default_mjml = "
<mjml>
  <mj-head>
    <mj-attributes>
      <mj-all font-family=\"Arial, sans-serif\"></mj-all>
    </mj-attributes>
  </mj-head>
  <mj-body background-color=\"#f1f5f9\">
    <mj-section padding=\"20px 0\">
      <mj-column>
        <mj-image src=\"{$brand_logo}\" alt=\"{$brand_name}\" width=\"160px\"></mj-image>
      </mj-column>
    </mj-section>
    <mj-section background-color=\"#ffffff\" border-radius=\"8px\" padding=\"40px 20px\" box-shadow=\"0 4px 6px rgba(0,0,0,0.05)\">
      <mj-column>
        <mj-text font-size=\"24px\" color=\"#0f172a\" font-weight=\"900\" align=\"center\">Welcome to {$brand_name}</mj-text>
        <mj-text font-size=\"15px\" color=\"#475569\" line-height=\"1.6\" align=\"center\" padding-top=\"10px\">Drag and drop blocks from the left panel to build your email.</mj-text>
      </mj-column>
    </mj-section>
    <mj-section padding=\"30px 0\">
      <mj-column>
        <mj-text font-size=\"12px\" color=\"#94a3b8\" align=\"center\" line-height=\"1.5\">
          <strong>{$brand_name}</strong><br>
          You are receiving this because you visited our location.
        </mj-text>
        <mj-text font-size=\"12px\" align=\"center\">
          <a href=\"[Unsubscribe_Link]\" style=\"color:{$brand_color}; text-decoration:underline;\">Unsubscribe safely here.</a>
        </mj-text>
      </mj-column>
    </mj-section>
  </mj-body>
</mjml>
";
?>

<link rel="stylesheet" href="https://unpkg.com/grapesjs/dist/css/grapes.min.css">
<script src="https://unpkg.com/grapesjs"></script>
<script src="https://unpkg.com/grapesjs-mjml@1.0.5/dist/grapesjs-mjml.min.js"></script>

<style>
    :root {
        --mt-brand: <?php echo esc_html($brand_color); ?>;
        --mt-accent: <?php echo esc_html($mt_palette['accent']); ?>;
        --gjs-main-color: #e2e8f0;           
        --gjs-primary-color: var(--mt-brand);     
        --gjs-secondary-color: #ffffff;   
        --gjs-tertiary-color: #ffffff;    
        --gjs-quaternary-color: var(--mt-brand);     
        --gjs-font-color: #0f172a;           
    }

    .gjs-one-bg { background-color: #ffffff !important; }
    .gjs-two-color { color: #334155 !important; }
    .gjs-three-bg { background-color: #ffffff !important; }
    .gjs-four-color, .gjs-four-color-h:hover { color: var(--mt-brand) !important; }
    
    .gjs-editor-cont { font-family: 'Inter', sans-serif !important; background: transparent !important; }
    .gjs-pn-panels { display: none !important; } 
    .gjs-cv-canvas { top: 0 !important; width: 100% !important; height: 100% !important; background: transparent !important;}

    /* LEFT PANEL TABS */
    .pill-tab { transition: all 0.2s; color: #64748b; font-weight: 800; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; padding: 15px 0; border-bottom: 2px solid transparent;}
    .pill-tab:hover { color: #0f172a; }
    .pill-tab.active { color: var(--mt-brand); border-bottom-color: var(--mt-brand); }
    .panel-tab-content { display: none; background: #ffffff; }
    .panel-tab-content.active { display: block; }

    /* THE BLOCKS GRID */
    .gjs-block-category { border: none !important; }
    .gjs-block-category .gjs-title { display: none !important; } 
    .gjs-blocks-c { display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 10px !important; padding: 20px !important; background: #ffffff !important; }
    
    .gjs-block { 
        display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important;
        border: 1px solid #e2e8f0 !important; border-radius: 8px !important; padding: 15px 5px !important; 
        background: #ffffff !important; width: 100% !important; margin: 0 !important;
        transition: all 0.2s ease !important; min-height: 85px !important; cursor: grab !important; box-shadow: none !important;
    }
    .gjs-block:hover { border-color: var(--mt-brand) !important; box-shadow: 0 4px 10px rgba(0,0,0,0.04) !important; transform: translateY(-2px); }
    .gjs-block svg { width: 22px !important; height: 22px !important; fill: #475569 !important; transition: fill 0.2s; }
    .gjs-block-label { font-family: 'Inter', sans-serif !important; font-weight: 700 !important; margin: 8px 0 0 0 !important; font-size: 11px !important; color: #334155 !important; text-align: center; }
    .gjs-block:hover svg { fill: var(--mt-brand) !important; }
    .gjs-block:hover .gjs-block-label { color: var(--mt-brand) !important; }
    
    /* ----------------------------------------------------------------- */
    /* THE EDIT STATE OVERHAUL (Traits, Styles, Color Pickers)           */
    /* ----------------------------------------------------------------- */
    #mt-left-panel * { box-sizing: border-box; }
    
    /* SECTION TITLES (Typography, Padding, etc) - FORCE WHITE & DARK TEXT */
    .gjs-sm-sector, .gjs-trt-traits, .gjs-sm-properties { background: #ffffff !important; border-bottom: none !important; }
    
    .gjs-sm-title, .gjs-trt-header,
    .gjs-sm-title-c, .gjs-sm-sector-title,
    .gjs-sm-title *, .gjs-trt-header * { 
        background-color: #ffffff !important; 
        color: #0f172a !important; 
        font-weight: 800 !important; 
        font-size: 11px !important; 
        letter-spacing: 0.5px !important; 
        text-transform: uppercase !important; 
        border: none !important; 
    }
    .gjs-sm-title, .gjs-trt-header {
        border-top: 1px solid #e2e8f0 !important; 
        border-bottom: 1px solid #f1f5f9 !important; 
        padding: 15px 20px !important;
        cursor: pointer !important;
    }
    .gjs-sm-sector.gjs-open { border-bottom: 1px solid #f1f5f9 !important; }

    /* INDIVIDUAL PROPERTIES */
    .gjs-sm-property, .gjs-trt-trait { 
        padding: 12px 20px !important; 
        border-bottom: 1px solid #f8fafc !important; 
        display: flex; 
        flex-direction: column; 
        background: #ffffff !important; 
        width: 100% !important;
    }
    
    .gjs-sm-label, .gjs-trt-label,
    .gjs-sm-label *, .gjs-trt-label * { 
        color: #475569 !important; 
        font-size: 10px !important; 
        font-weight: 800 !important; 
        text-transform: uppercase; 
        margin-bottom: 6px !important; 
        display: block; 
        letter-spacing: 0.5px !important;
        white-space: normal !important; 
        word-break: break-word !important; 
    }
    
    .gjs-field, .gjs-field input, .gjs-field select, .gjs-field textarea { 
        background-color: #ffffff !important; 
        border: 1px solid #cbd5e1 !important; 
        color: #0f172a !important; 
        border-radius: 6px !important; 
        font-size: 12px !important; 
        font-family: 'Inter', sans-serif !important; 
        width: 100% !important; 
        font-weight: 600 !important; 
        box-shadow: none !important;
        min-height: 36px !important;
    }
    .gjs-field { padding: 0 !important; display: flex !important; align-items: center !important; overflow: hidden !important;}
    .gjs-field input, .gjs-field select, .gjs-field textarea { border: none !important; padding: 8px 12px !important; height: 100% !important; flex: 1 !important;}
    
    .gjs-field input:focus, .gjs-field textarea:focus, .gjs-field select:focus { 
        outline: none !important; 
        box-shadow: inset 0 0 0 2px rgba(15,23,42,0.1) !important;
    }

    .gjs-field-arrows { display: none !important; }

    /* COLOR PICKER */
    .gjs-field-color { flex-direction: row !important; padding: 4px !important; align-items: center !important;}
    .gjs-field-color-picker {
        border: 1px solid #cbd5e1 !important;
        border-radius: 4px !important;
        width: 26px !important;
        height: 26px !important;
        cursor: pointer !important;
        margin-right: 6px !important;
        flex-shrink: 0 !important;
    }
    
    /* PADDING & MARGIN (Zero Border Grid) */
    .gjs-sm-composite .gjs-sm-properties { 
        display: grid !important; 
        grid-template-columns: 1fr 1fr !important; 
        gap: 10px !important; 
        padding: 5px 0 0 0 !important; 
        background: #ffffff !important; 
        border: none !important; /* REMOVE SECTION BORDER */
    }
    .gjs-sm-composite .gjs-sm-property { 
        padding: 0 !important; 
        border: none !important; /* REMOVE INNER BORDER */
        background: transparent !important;
        width: 100% !important;
    }
    .gjs-sm-property__padding-indicator, .gjs-sm-property__margin-indicator, .gjs-sm-composite-ch {
        display: none !important;
    }
    .gjs-sm-composite .gjs-sm-label {
        font-size: 9px !important;
        color: #94a3b8 !important;
        margin-bottom: 4px !important;
        display: block !important;
        text-align: left !important;
    }
    
    .gjs-clm-tags, .gjs-sm-property[data-property="flex-direction"], .gjs-sm-property[data-property="position"] { display: none !important; } 
    .gjs-sm-clear { display: none !important; } 

    /* STUDIO GALLERY TABS */
    .studio-tab-btn { transition: all 0.2s; cursor: pointer; border-bottom: 2px solid transparent; }
    .studio-tab-btn.active { border-bottom-color: var(--mt-accent); color: #0f172a; font-weight: 800; }
    .studio-tab-content { display: none; }
    .studio-tab-content.active { display: block; }
</style>

<div id="view_list">
    <header class="mb-6 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-black text-gray-900 flex items-center gap-3">Toucan Studio</h1>
            <p class="text-gray-500 text-sm mt-1">Design and manage beautiful email templates.</p>
        </div>
        <button onclick="openBuilder(0)" class="bg-gray-900 text-white px-6 py-3 rounded-xl font-bold shadow-lg hover:bg-black transition flex items-center gap-2">
            <i class="fa-solid fa-plus"></i> New Design
        </button>
    </header>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-20">
        <div class="flex border-b border-gray-100 bg-gray-50/50 px-6 pt-3 gap-8 items-center">
            <button class="studio-tab-btn active px-2 py-4 text-xs font-bold uppercase tracking-widest text-gray-400" onclick="switchStudioTab('saved', this)">Saved Designs</button>
            <button class="studio-tab-btn px-2 py-4 text-xs font-bold uppercase tracking-widest text-gray-400" onclick="switchStudioTab('gallery', this)">The Gallery</button>
            <button class="studio-tab-btn px-2 py-4 text-xs font-bold uppercase tracking-widest text-gray-400" onclick="switchStudioTab('trash', this)">Trash</button>
        </div>

        <div class="p-8 min-h-[500px]">
            <div id="tab_saved" class="studio-tab-content active">
                <?php if(empty($active_templates)): ?>
                    <div class="text-center py-20">
                        <i class="fa-regular fa-folder-open text-5xl text-gray-200 mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-700">Your studio is empty</h3>
                        <p class="text-sm text-gray-500 mb-4">Start with a blank canvas or choose a pre-built design.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-3 gap-8">
                        <?php foreach($active_templates as $tpl): ?>
                        <div class="group relative bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-xl transition-all p-2 flex flex-col">
                            <div class="aspect-[4/3] bg-gray-50 rounded-xl mb-4 flex items-center justify-center border border-gray-100 overflow-hidden relative">
                                <i class="fa-regular fa-envelope text-4xl text-gray-200"></i>
                            </div>
                            <div class="px-3 pb-3 flex flex-col flex-1">
                                <h3 class="font-bold text-gray-900 truncate"><?php echo esc_html($tpl->template_name); ?></h3>
                                <div class="flex justify-between items-center mt-4 border-t border-gray-100 pt-4">
                                    <button onclick="trashTemplate(<?php echo $tpl->id; ?>)" class="text-gray-300 hover:text-red-500 transition"><i class="fa-solid fa-trash-can"></i></button>
                                    <button onclick="openBuilder(<?php echo $tpl->id; ?>, '<?php echo esc_js($tpl->template_name); ?>')" data-body="<?php echo esc_attr($tpl->email_body); ?>" id="raw_body_<?php echo $tpl->id; ?>" class="bg-gray-900 text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-black transition">Open Designer</button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div id="tab_gallery" class="studio-tab-content">
                <div class="grid grid-cols-4 gap-6">
                    <?php 
                    $prebuilts = [['name' => 'The Instant Reward', 'desc' => 'Perfect for Free WiFi gifts.', 'icon' => 'fa-gift'], ['name' => 'The Review Requester', 'desc' => 'Drive 5-star Google Reviews.', 'icon' => 'fa-star']];
                    foreach($prebuilts as $pb):
                    ?>
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm hover:shadow-md hover:border-gray-900 transition cursor-pointer flex flex-col" onclick="openBuilder(0, '<?php echo $pb['name']; ?> Copy')">
                        <div class="h-32 bg-gray-50 flex items-center justify-center shrink-0 border-b border-gray-100">
                            <i class="fa-solid <?php echo $pb['icon']; ?> text-4xl text-gray-300 mb-2"></i>
                        </div>
                        <div class="p-5 text-center flex-1 flex flex-col justify-center">
                            <h3 class="font-bold text-gray-900 text-sm mb-1"><?php echo $pb['name']; ?></h3>
                            <p class="text-xs text-gray-500 leading-tight"><?php echo $pb['desc']; ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div id="tab_trash" class="studio-tab-content">
                <div class="text-center py-16"><p class="text-gray-500">Trash is empty.</p></div>
            </div>
        </div>
    </div>
</div>

<div id="view_builder" class="fixed inset-0 bg-[#f4f4f5] z-[100] hidden flex-col font-sans">
    
    <div class="h-16 bg-white border-b border-gray-200 px-6 flex justify-between items-center shrink-0 z-30 shadow-sm">
        <div class="flex items-center gap-4">
            <button onclick="closeBuilder()" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-500 transition" title="Exit Builder"><i class="fa-solid fa-arrow-left text-sm"></i></button>
            <div class="h-6 w-px bg-gray-200 mx-2"></div>
            <input type="text" id="builder_tpl_name" class="border-none bg-transparent outline-none text-lg font-black text-gray-900 placeholder-gray-300 w-64" placeholder="Design Name...">
        </div>
        
        <div class="flex items-center gap-2 bg-gray-50 border border-gray-200 p-1 rounded-lg">
            <button onclick="setDevice('desktop')" id="device-desktop" class="device-btn active w-8 h-8 rounded bg-white shadow-sm flex items-center justify-center text-gray-800 transition"><i class="fa-solid fa-desktop text-xs"></i></button>
            <button onclick="setDevice('mobile')" id="device-mobile" class="device-btn w-8 h-8 rounded hover:bg-gray-200 flex items-center justify-center text-gray-500 transition"><i class="fa-solid fa-mobile-screen text-xs"></i></button>
        </div>

        <div class="flex items-center gap-3">
            <button onclick="openTestModal()" class="bg-white border border-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-bold hover:bg-gray-50 transition shadow-sm"><i class="fa-regular fa-paper-plane mr-2"></i>Send Test</button>
            <button onclick="saveBuilder()" id="btn_save_builder" class="bg-gray-900 text-white px-8 py-2.5 rounded-lg text-sm font-bold hover:bg-black transition shadow-lg flex items-center gap-2">Save Design</button>
        </div>
    </div>

    <input type="hidden" id="builder_tpl_id" value="0">

    <div class="flex flex-1 overflow-hidden relative">
        <div id="builder_loader" class="absolute inset-0 bg-[#f8fafc] z-50 flex flex-col items-center justify-center">
            <i class="fa-solid fa-circle-notch fa-spin text-5xl text-indigo-500 mb-4"></i>
            <h2 class="text-gray-900 font-black tracking-widest uppercase text-sm">Loading Studio...</h2>
        </div>
        
        <div id="mt-left-panel" class="w-[320px] bg-[#ffffff] border-r border-gray-200 flex flex-col shrink-0 relative shadow-[4px_0_15px_rgba(0,0,0,0.03)] z-20">
            <div class="px-6 flex gap-6 bg-white border-b border-gray-100 shrink-0">
                <button onclick="switchLeftTab('content')" id="tab-btn-content" class="pill-tab active">Blocks</button>
                <button onclick="switchLeftTab('sections')" id="tab-btn-sections" class="pill-tab">Sections</button>
                <button onclick="switchLeftTab('body')" id="tab-btn-body" class="pill-tab">Body</button>
                <button onclick="switchLeftTab('images')" id="tab-btn-images" class="pill-tab">Images</button>
            </div>
            
            <div id="tab-content" class="flex-1 overflow-y-auto custom-scrollbar panel-tab-content active p-0">
                <div id="mt-blocks-container"></div>
            </div>
            <div id="tab-sections" class="flex-1 overflow-y-auto custom-scrollbar panel-tab-content p-6">
                <div id="mt-layers-container"></div>
            </div>
            <div id="tab-body" class="flex-1 overflow-y-auto custom-scrollbar panel-tab-content p-6">
                <div class="space-y-5">
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">Canvas Background</label>
                        <input type="color" value="#f1f5f9" onchange="updateGlobalBg(this.value)" class="w-full h-10 rounded cursor-pointer border border-gray-200 p-1 bg-white">
                    </div>
                </div>
            </div>
            <div id="tab-images" class="flex-1 overflow-y-auto custom-scrollbar panel-tab-content p-6">
                <div class="grid grid-cols-2 gap-3">
                    <?php foreach($vault_assets as $img): ?>
                        <div onclick="insertImageToCanvas('<?php echo esc_js($img); ?>')" class="bg-white border border-gray-200 rounded-lg p-2 h-24 flex items-center justify-center cursor-pointer hover:border-indigo-500 transition">
                            <img src="<?php echo esc_url($img); ?>" class="max-h-full max-w-full object-contain">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="panel-edit-menu" class="hidden flex-col h-full bg-[#ffffff] absolute inset-0 z-30">
                <div class="h-14 border-b border-gray-100 flex items-center px-4 bg-gray-50 shrink-0">
                    <button onclick="closeEditPanel()" class="flex items-center gap-2 text-[10px] font-bold text-gray-500 hover:text-gray-900 uppercase tracking-widest transition"><i class="fa-solid fa-arrow-left"></i> Back to Elements</button>
                </div>
                <div class="flex-1 overflow-y-auto custom-scrollbar flex flex-col">
                    <div id="mt-traits-container"></div>
                    <div id="mt-styles-container" class="pb-10"></div>
                </div>
            </div>
        </div>

        <div id="canvas-wrapper" class="flex-1 relative overflow-y-auto flex justify-center pt-10 pb-24 bg-[#e2e8f0]">
            <div id="gjs-container" class="w-full max-w-[650px] shadow-[0_20px_50px_rgba(0,0,0,0.1)] relative transition-all duration-300 mx-auto">
                <div id="gjs" class="absolute inset-0 bg-transparent"></div>
            </div>
            <div id="shortcode_tab" onclick="toggleShortcodes()" class="fixed bottom-0 right-1/2 translate-x-[150px] bg-white border border-gray-200 border-b-0 rounded-t-xl px-6 py-2 shadow-lg cursor-pointer z-40 flex items-center gap-2 hover:bg-gray-50 transition">
                <span class="text-[10px] font-black uppercase tracking-tighter text-gray-500">Shortcodes</span>
                <i class="fa-solid fa-chevron-up text-[10px] text-gray-400"></i>
            </div>
        </div>
    </div>

    <div id="test_email_modal" class="fixed inset-0 bg-gray-900/60 z-[200] hidden items-center justify-center backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <h2 class="text-lg font-black text-gray-900">Send Test Email</h2>
                <button onclick="closeTestModal()" class="text-gray-400 hover:text-gray-900 transition"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div class="p-6 space-y-5 bg-white">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">From Sender</label>
                    <input type="text" value="<?php echo esc_attr($sender_email); ?>" class="w-full p-3 bg-gray-100 border border-gray-200 rounded-lg text-sm font-bold text-gray-500 cursor-not-allowed" disabled>
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">Test Subject Line</label>
                    <input type="text" id="test_subject" class="w-full p-3 border border-gray-300 rounded-lg text-sm font-bold focus:border-indigo-500 outline-none transition" placeholder="e.g. Test Offer">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">Send To</label>
                    <input type="email" id="test_recipient" class="w-full p-3 border border-gray-300 rounded-lg text-sm font-bold focus:border-indigo-500 outline-none transition" placeholder="you@domain.com">
                </div>
                <button onclick="sendTestEmail()" class="w-full bg-gray-900 text-white font-bold py-3.5 rounded-xl hover:bg-black transition mt-4 shadow-lg">Send Now</button>
            </div>
        </div>
    </div>
</div>

<script>
    let editor = null;
    const defaultMjml = `<?php echo addslashes(str_replace(["\r", "\n"], '', $default_mjml)); ?>`;
    const brandPrimaryColor = '<?php echo esc_js($brand_color); ?>';

    function switchStudioTab(tab, el) {
        document.querySelectorAll('.studio-tab-btn').forEach(b => b.classList.remove('active'));
        el.classList.add('active');
        document.querySelectorAll('.studio-tab-content').forEach(c => c.classList.remove('active'));
        document.getElementById('tab_' + tab).classList.add('active');
    }

    function switchLeftTab(tab) {
        document.querySelectorAll('.pill-tab').forEach(b => { b.classList.remove('active'); });
        document.getElementById('tab-btn-' + tab).classList.add('active');
        document.querySelectorAll('.panel-tab-content').forEach(c => { c.classList.remove('active'); });
        document.getElementById('tab-' + tab).classList.add('active');
    }

    function closeEditPanel() { if(editor) editor.select(null); document.getElementById('panel-edit-menu').classList.add('hidden'); }
    function openTestModal() { document.getElementById('test_email_modal').classList.remove('hidden'); document.getElementById('test_email_modal').classList.add('flex'); }
    function closeTestModal() { document.getElementById('test_email_modal').classList.add('hidden'); }
    function sendTestEmail() { alert("Test Email queued!"); closeTestModal(); }
    function toggleShortcodes() { document.getElementById('shortcode_drawer').classList.toggle('open'); }
    function copyTag(tag) { navigator.clipboard.writeText(tag); alert('Copied: ' + tag); }
    function setDevice(device) {
        document.querySelectorAll('.device-btn').forEach(b => { b.classList.remove('active', 'bg-white', 'shadow-sm'); });
        document.getElementById('device-' + device).classList.add('active', 'bg-white', 'shadow-sm');
        if(device === 'mobile') editor.setDevice('Mobile portrait'); else editor.setDevice('Desktop');
    }
    function updateGlobalBg(color) { document.getElementById('canvas-wrapper').style.backgroundColor = color; }
    function insertImageToCanvas(url) {
        const selected = editor.getSelected();
        if(selected && selected.is('mj-image')) selected.addAttributes({'src': url});
        else alert("Select an Image block first.");
    }

    function openBuilder(id, name = '') {
        document.getElementById('builder_tpl_id').value = id;
        document.getElementById('builder_tpl_name').value = name;
        document.getElementById('view_list').style.display = 'none';
        document.querySelector('.sidebar').style.display = 'none';
        document.getElementById('view_builder').classList.remove('hidden');
        document.getElementById('view_builder').classList.add('flex');
        document.getElementById('builder_loader').classList.remove('hidden');

        let startingData = '';
        if(id !== 0) { 
            const rawBody = document.getElementById('raw_body_' + id).getAttribute('data-body'); 
            try { let parsed = JSON.parse(rawBody); startingData = parsed.mjml || defaultMjml; } catch(e) { startingData = rawBody || defaultMjml; }
        } else { startingData = defaultMjml; }

        if (!editor) { initGrapesJS(startingData); } 
        else { editor.setComponents(startingData); setTimeout(() => { document.getElementById('builder_loader').classList.add('hidden'); }, 500); }
        closeEditPanel(); switchLeftTab('content'); 
    }

    function initGrapesJS(startingData) {
        editor = grapesjs.init({
            container: '#gjs',
            fromElement: false,
            height: '100%',
            width: '100%',
            storageManager: false,
            plugins: ['grapesjs-mjml'],
            pluginsOpts: { 'grapesjs-mjml': {} },
            blockManager: { appendTo: '#mt-blocks-container' },
            layerManager: { appendTo: '#mt-layers-container' },
            traitManager: { appendTo: '#mt-traits-container' },
            styleManager: { 
                appendTo: '#mt-styles-container',
                sectors: [
                    { name: 'Typography', open: true, buildProps: ['font-family', 'font-size', 'font-weight', 'color', 'text-align', 'line-height'] },
                    { name: 'Padding & Margin', open: false, buildProps: ['padding', 'margin'] },
                    { name: 'Decorations', open: false, buildProps: ['background-color', 'border-radius', 'border'] }
                ]
            }
        });

        editor.on('load', () => {
            const bm = editor.BlockManager;
            bm.getAll().reset(); 
            bm.add('mt-1-col', { category: 'Structure', label: `<i class="fa-regular fa-square"></i><div class="gjs-block-label">1 Column</div>`, content: `<mj-section padding="20px" background-color="#ffffff"><mj-column><mj-text>Content</mj-text></mj-column></mj-section>` });
            bm.add('mt-2-col', { category: 'Structure', label: `<i class="fa-solid fa-table-columns"></i><div class="gjs-block-label">2 Columns</div>`, content: `<mj-section padding="20px" background-color="#ffffff"><mj-column><mj-text>Left</mj-text></mj-column><mj-column><mj-text>Right</mj-text></mj-column></mj-section>` });
            bm.add('mt-text', { category: 'Content', label: `<i class="fa-solid fa-font"></i><div class="gjs-block-label">Text</div>`, content: '<mj-text font-size="15px" color="#334155" line-height="1.6">Type your paragraph here.</mj-text>' });
            bm.add('mt-image', { category: 'Content', label: `<i class="fa-regular fa-image"></i><div class="gjs-block-label">Image</div>`, content: '<mj-image src="https://placehold.co/600x300?text=Image" border-radius="6px"></mj-image>' });
            bm.add('mt-button', { category: 'Content', label: `<i class="fa-solid fa-hand-pointer"></i><div class="gjs-block-label">Button</div>`, content: `<mj-button background-color="${brandPrimaryColor}" color="#ffffff" font-weight="bold" border-radius="6px" href="#">Click Here</mj-button>` });
            bm.add('mt-divider', { category: 'Content', label: `<i class="fa-solid fa-minus"></i><div class="gjs-block-label">Divider</div>`, content: `<mj-divider border-width="1px" border-color="#e2e8f0"></mj-divider>` });
            setTimeout(() => { document.getElementById('builder_loader').classList.add('hidden'); }, 800);
        });

        editor.on('component:selected', component => { 
            document.getElementById('panel-edit-menu').classList.remove('hidden');
            document.getElementById('panel-edit-menu').classList.add('flex');
            const traits = component.get('traits');
            if(traits) component.set('traits', traits.filter(t => t.get('name') !== 'title' && t.get('name') !== 'id'));
        });
    }

    function trashTemplate(id) { if(confirm("Trash it?")) { const fd = new FormData(); fd.append('action','mt_trash_template'); fd.append('security',mt_nonce); fd.append('template_id',id); fetch(mt_ajax_url,{method:'POST',body:fd}).then(()=>window.location.reload()); } }
    
    function saveBuilder() {
        const btn = document.getElementById('btn_save_builder');
        const id = document.getElementById('builder_tpl_id').value;
        const name = document.getElementById('builder_tpl_name').value.trim();
        if(!name) { alert('Name needed!'); return; }
        btn.innerHTML = 'Saving...'; btn.disabled = true;
        const mjmlData = editor.runCommand('mjml-get-code');
        const fd = new FormData();
        fd.append('action', 'mt_save_template'); fd.append('security', mt_nonce); fd.append('template_id', id); fd.append('template_name', name);
        fd.append('email_body', JSON.stringify({ html: mjmlData.html, mjml: mjmlData.mjml }));
        fetch(mt_ajax_url, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            btn.disabled = false; btn.innerHTML = 'Save Design';
            if(res.success && id == 0) document.getElementById('builder_tpl_id').value = res.data.id;
        });
    }
</script>