<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_templates = $wpdb->prefix . 'mt_email_templates';
// Fetch Active and Trashed
$active_templates = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table_templates WHERE brand_id = %d AND status = 'active' ORDER BY created_at DESC", $brand->id) );
$trashed_templates = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table_templates WHERE brand_id = %d AND status = 'trashed' ORDER BY deleted_at DESC", $brand->id) );

// FETCH DYNAMIC BRANDING
$brand_config = json_decode($brand->brand_config, true) ?: [];
$brand_logo = isset($brand_config['logos']['main']) && !empty($brand_config['logos']['main']) ? $brand_config['logos']['main'] : 'https://placehold.co/400x150/e2e8f0/0f172a?text=Your+Logo';
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

// THE DEFAULT TEMPLATE
$default_mjml = "
<mjml>
  <mj-head>
    <mj-attributes>
      <mj-all font-family=\"Arial, Helvetica, sans-serif\"></mj-all>
      <mj-text font-size=\"15px\" color=\"#334155\" line-height=\"1.6\"></mj-text>
      <mj-section padding=\"0\"></mj-section>
    </mj-attributes>
    <mj-style>
      .email-card { box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; }
    </mj-style>
  </mj-head>
  <mj-body background-color=\"#f1f5f9\">
    <mj-section padding=\"30px 0 10px 0\" background-color=\"transparent\">
      <mj-column>
        <mj-image src=\"{$brand_logo}\" alt=\"{$brand_name}\" width=\"160px\" align=\"center\"></mj-image>
      </mj-column>
    </mj-section>
    <mj-wrapper background-color=\"#ffffff\" border-radius=\"12px\" padding=\"20px\" css-class=\"email-card\">
      <mj-section padding=\"20px 0\">
        <mj-column>
          <mj-text font-size=\"24px\" color=\"#0f172a\" font-weight=\"900\" align=\"center\">Welcome to {$brand_name}</mj-text>
          <mj-text align=\"center\" padding-top=\"10px\" padding-bottom=\"20px\">Drag and drop beautiful, pre-designed sections from the left panel to build your email.</mj-text>
          <mj-button background-color=\"{$brand_color}\" color=\"#ffffff\" font-weight=\"bold\" border-radius=\"6px\" inner-padding=\"14px 28px\" href=\"#\">Get Started</mj-button>
        </mj-column>
      </mj-section>
    </mj-wrapper>
    <mj-section padding=\"30px 0\" background-color=\"transparent\">
      <mj-column>
        <mj-text font-size=\"12px\" color=\"#94a3b8\" align=\"center\" line-height=\"1.5\">
          <strong>{$brand_name}</strong><br>
          123 Brand Street, City, State 12345<br>
          You are receiving this email because you opted in.
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
<script src="https://unpkg.com/grapesjs-mjml"></script>

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
    .gjs-cv-canvas { top: 0 !important; width: 100% !important; height: 100% !important; background: #f1f5f9 !important;}

    /* TABS */
    .pill-tab { transition: all 0.2s; color: #64748b; font-weight: 800; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; padding: 15px 0; border-bottom: 2px solid transparent;}
    .pill-tab:hover { color: #0f172a; }
    .pill-tab.active { color: var(--mt-brand); border-bottom-color: var(--mt-brand); }
    .panel-tab-content { display: none; background: #ffffff; }
    .panel-tab-content.active { display: block; }
    #tab-sections.active { display: flex !important; flex-direction: column; }

    .sub-pill-tab { transition: all 0.2s; color: #64748b; font-weight: 800; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; padding: 12px 0; background: #f8fafc; border: none; border-bottom: 2px solid transparent; cursor: pointer; outline: none; border-right: 1px solid #f1f5f9; }
    .sub-pill-tab:hover { color: #0f172a; background: #f1f5f9; }
    .sub-pill-tab.active { color: var(--mt-brand); border-bottom-color: var(--mt-brand); background: #ffffff; }
    .sub-panel { display: none; }
    .sub-panel.active { display: block !important; }
    .sub-panel.hidden { display: none !important; }

    /* BLOCKS */
    .gjs-block-category { border: none !important; width: 100% !important; background: #ffffff !important;}
    .gjs-block-category .gjs-title { 
        display: block !important; font-weight: 800 !important; color: #94a3b8 !important; font-size: 10px !important; 
        text-transform: uppercase; letter-spacing: 1px; padding: 15px 20px !important; background: #ffffff !important; 
        border: none !important; border-bottom: 1px solid #f1f5f9 !important; cursor: pointer !important; transition: color 0.2s;
    }
    .gjs-block-category .gjs-title:hover { color: #0f172a !important; }
    .gjs-block-category .gjs-blocks-c { display: none !important; }
    .gjs-block-category.gjs-open .gjs-blocks-c { display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 10px !important; padding: 15px 20px 20px 20px !important; background: #ffffff !important; }
    
    .gjs-block { 
        display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important;
        border: 1px solid #e2e8f0 !important; border-radius: 8px !important; padding: 15px 5px !important; background: #ffffff !important; width: 100% !important; margin: 0 !important;
        transition: all 0.2s ease !important; min-height: 85px !important; cursor: grab !important; box-shadow: none !important;
    }
    .gjs-block:hover { border-color: var(--mt-brand) !important; box-shadow: 0 4px 10px rgba(0,0,0,0.04) !important; transform: translateY(-2px); }
    .gjs-block svg { width: 22px !important; height: 22px !important; fill: #475569 !important; transition: fill 0.2s; }
    .gjs-block-label { font-family: 'Inter', sans-serif !important; font-weight: 700 !important; margin: 8px 0 0 0 !important; font-size: 10px !important; color: #334155 !important; text-align: center; }
    .gjs-block:hover svg { fill: var(--mt-brand) !important; }
    .gjs-block:hover .gjs-block-label { color: var(--mt-brand) !important; }
    
    /* EDIT STATE */
    #mt-left-panel * { box-sizing: border-box; }
    .gjs-sm-sector, .gjs-trt-traits, .gjs-sm-properties { background: #ffffff !important; border-bottom: none !important; }
    
    .gjs-sm-title, .gjs-trt-header, .gjs-sm-title-c, .gjs-sm-sector-title, .gjs-sm-title *, .gjs-trt-header * { 
        background-color: #ffffff !important; color: #0f172a !important; font-weight: 800 !important; font-size: 11px !important; letter-spacing: 0.5px !important; text-transform: uppercase !important; border: none !important; 
    }
    .gjs-sm-title, .gjs-trt-header { border-top: 1px solid #e2e8f0 !important; border-bottom: 1px solid #f1f5f9 !important; padding: 15px 20px !important; cursor: pointer !important; }
    .gjs-sm-sector.gjs-open { border-bottom: 1px solid #f1f5f9 !important; }

    .gjs-sm-property, .gjs-trt-trait { overflow: visible !important; padding: 12px 20px !important; border-bottom: 1px solid #f8fafc !important; display: flex; flex-direction: column; background: #ffffff !important; width: 100% !important;}
    .gjs-sm-label, .gjs-trt-label, .gjs-sm-label *, .gjs-trt-label * { color: #475569 !important; font-size: 10px !important; font-weight: 800 !important; text-transform: uppercase; margin-bottom: 6px !important; display: block; letter-spacing: 0.5px !important; white-space: normal !important; word-break: break-word !important; }
    
    .gjs-field, .gjs-field input, .gjs-field select, .gjs-field textarea { background-color: #ffffff !important; border: 1px solid #cbd5e1 !important; color: #0f172a !important; border-radius: 6px !important; font-size: 12px !important; font-family: 'Inter', sans-serif !important; width: 100% !important; font-weight: 600 !important; box-shadow: none !important; min-height: 36px !important;}
    .gjs-field { padding: 0 !important; display: flex !important; align-items: center !important; overflow: hidden !important;}
    .gjs-field input, .gjs-field select, .gjs-field textarea { border: none !important; padding: 8px 12px !important; height: 100% !important; flex: 1 !important;}
    .gjs-field input:focus, .gjs-field textarea:focus, .gjs-field select:focus { outline: none !important; box-shadow: inset 0 0 0 2px rgba(15,23,42,0.1) !important;}
    .gjs-field-arrows { display: none !important; }

    /* SEGMENTED CONTROLS FIX */
    .gjs-field-radio {
        display: flex !important; flex-direction: row !important; background-color: #f8fafc !important; 
        border-radius: 8px !important; padding: 4px !important; border: 1px solid #e2e8f0 !important;
        width: 100% !important; gap: 4px !important; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02) !important;
    }
    .gjs-radio-item {
        flex: 1 !important; background: transparent !important; border: none !important; box-shadow: none !important;
        border-radius: 6px !important; padding: 6px 0 !important; display: flex !important; align-items: center !important;
        justify-content: center !important; cursor: pointer !important; transition: all 0.2s ease !important;
    }
    .gjs-radio-item input { display: none !important; }
    .gjs-radio-item-label { color: #64748b !important; cursor: pointer !important; display: flex !important; align-items: center !important; justify-content: center !important; width: 100% !important;}
    .gjs-radio-item-label svg { fill: #64748b !important; width: 14px !important; height: 14px !important; transition: fill 0.2s; }
    .gjs-radio-item.gjs-active { background-color: #ffffff !important; box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important; }
    .gjs-radio-item.gjs-active .gjs-radio-item-label { color: var(--mt-brand) !important; font-weight: 800 !important; }
    .gjs-radio-item.gjs-active .gjs-radio-item-label svg { fill: var(--mt-brand) !important; }

    /* PERFECT COLOR CIRCLE FIX */
    .gjs-field-color { flex-direction: row !important; padding: 4px 8px !important; align-items: center !important;}
    .gjs-field-color-picker, .gjs-field-colorp { 
        border: 1px solid #cbd5e1 !important; border-radius: 50% !important; width: 24px !important; height: 24px !important; 
        cursor: pointer !important; margin-right: 8px !important; flex-shrink: 0 !important; position: relative !important;
        overflow: hidden !important; background: transparent !important; display: flex !important; align-items: center !important;
        justify-content: center !important; padding: 0 !important;
    }
    .gjs-checker-bg { display: none !important; background-image: none !important;}
    .gjs-field-color-picker-color, .gjs-field-colorp-color, .gjs-field-colorp-c { 
        width: 100% !important; height: 100% !important; border-radius: 50% !important; position: absolute !important;
        top: 0 !important; left: 0 !important; border: none !important; margin: 0 !important; padding: 0 !important;
        transform: scale(1.15) !important; 
    }

    /* CUSTOM IN-PLATFORM COLOR DRAWER UI */
    #mt_color_drawer {
        position: absolute; bottom: 0; left: 0; right: 0; background: #ffffff; border-top: 1px solid #e2e8f0;
        padding: 20px; transform: translateY(100%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 500; box-shadow: 0 -10px 40px rgba(0,0,0,0.08);
    }
    #mt_color_drawer.open { transform: translateY(0); }
    .color-swatch-btn { width: 100%; aspect-ratio: 1; border-radius: 50%; border: 1px solid #cbd5e1; cursor: pointer; transition: transform 0.2s; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05); }
    .color-swatch-btn:hover { transform: scale(1.1); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    
    input[type="color"] { -webkit-appearance: none; border: none; width: 100%; height: 40px; border-radius: 6px; padding: 0; cursor: pointer; background: transparent; }
    input[type="color"]::-webkit-color-swatch-wrapper { padding: 0; }
    input[type="color"]::-webkit-color-swatch { border: 1px solid #cbd5e1; border-radius: 6px; }

    /* PADDING & MARGIN FIX */
    .gjs-sm-composite .gjs-sm-properties { display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 10px !important; padding: 5px 0 0 0 !important; background: #ffffff !important; border: none !important; }
    .gjs-sm-composite .gjs-sm-property { padding: 0 !important; border: none !important; background: transparent !important; width: 100% !important;}
    .gjs-sm-property__padding-indicator, .gjs-sm-property__margin-indicator, .gjs-sm-composite-ch { display: none !important; }
    .gjs-sm-composite .gjs-sm-label { font-size: 9px !important; color: #94a3b8 !important; margin-bottom: 4px !important; display: block !important; text-align: left !important; }
    
    .gjs-clm-tags, .gjs-sm-property[data-property="flex-direction"], .gjs-sm-property[data-property="position"] { display: none !important; } 
    .gjs-sm-clear { display: none !important; } 
    .gjs-layer-manager { padding: 10px 0; background: #ffffff; }
    .gjs-layer { padding: 12px 15px; border: 1px solid #e2e8f0; margin-bottom: 8px; border-radius: 6px; background: #ffffff; font-size: 12px; font-weight: bold; color: #334155; cursor: pointer; transition: all 0.2s; }
    .gjs-layer:hover { border-color: var(--mt-brand); }
    .gjs-layer.gjs-layer-active { border-color: var(--mt-brand); color: var(--mt-brand); background: #f8fafc;}
    .gjs-layer-name { margin-left: 5px; }

    #builder_loader { transition: opacity 0.3s; }
    #shortcode_drawer { transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); transform: translateY(100%); }
    #shortcode_drawer.open { transform: translateY(0); }
    .sc-chip { cursor: pointer; transition: all 0.2s; border: 1px solid #e2e8f0; background: #fff; }
    .sc-chip:hover { border-color: var(--mt-brand); background: #f8fafc; transform: translateY(-1px); }

    .studio-tab-btn { transition: all 0.2s; cursor: pointer; border-bottom: 2px solid transparent; }
    .studio-tab-btn.active { border-bottom-color: var(--mt-accent); color: #0f172a; font-weight: 800; }
    .studio-tab-content { display: none; }
    .studio-tab-content.active { display: block; }

    /* ================================================================== */
    /* STUDIO DARK MODE OVERRIDES                                         */
    /* ================================================================== */
    .studio-dark { background-color: #0f172a !important; }
    .studio-dark .bg-white { background-color: #1e293b !important; border-color: #334155 !important; color: #f8fafc !important;}
    .studio-dark .bg-gray-50 { background-color: #0f172a !important; border-color: #334155 !important; }
    .studio-dark .text-gray-900, .studio-dark .text-gray-800 { color: #f8fafc !important; }
    .studio-dark .text-gray-700, .studio-dark .text-gray-500 { color: #cbd5e1 !important; }
    .studio-dark .border-gray-200, .studio-dark .border-gray-100 { border-color: #334155 !important; }
    .studio-dark input { background-color: #0f172a !important; color: #f8fafc !important; border-color: #334155 !important; }
    .studio-dark input::placeholder { color: #64748b !important; }
    
    .studio-dark #mt-left-panel, .studio-dark .panel-tab-content { background-color: #1e293b !important; border-color: #334155 !important;}
    .studio-dark .gjs-block { background-color: #0f172a !important; border-color: #334155 !important; }
    .studio-dark .gjs-block-label { color: #cbd5e1 !important; }
    .studio-dark .gjs-block svg { fill: #cbd5e1 !important; }
    .studio-dark .gjs-block-category .gjs-title { background-color: #1e293b !important; border-color: #334155 !important; color: #cbd5e1 !important;}
    .studio-dark .gjs-block-category.gjs-open .gjs-blocks-c { background-color: #1e293b !important; }
    
    .studio-dark .gjs-sm-title, .studio-dark .gjs-sm-sector, .studio-dark .gjs-sm-properties, .studio-dark .gjs-sm-property, .studio-dark .gjs-trt-trait { background-color: #1e293b !important; color: #f8fafc !important; border-color: #334155 !important; }
    .studio-dark .gjs-sm-title *, .studio-dark .gjs-trt-header * { background-color: transparent !important; color: #f8fafc !important; }
    .studio-dark .gjs-sm-label, .studio-dark .gjs-sm-label * { color: #94a3b8 !important; }
    .studio-dark .gjs-field, .studio-dark .gjs-field input, .studio-dark .gjs-field select { background-color: #0f172a !important; color: #f8fafc !important; border-color: #334155 !important; }
    .studio-dark .gjs-field-color-picker, .studio-dark .gjs-field-colorp { border-color: #475569 !important; background: transparent !important;}
    
    .studio-dark .gjs-field-radio { background-color: #0f172a !important; border-color: #334155 !important;}
    .studio-dark .gjs-radio-item.gjs-active { background-color: #334155 !important; box-shadow: none !important;}
    .studio-dark .gjs-radio-item.gjs-active .gjs-radio-item-label svg { fill: #ffffff !important; }
    
    .studio-dark #mt_color_drawer { background-color: #1e293b !important; border-top-color: #334155 !important; }
    .studio-dark .color-swatch-btn { border-color: #475569 !important; }

    .studio-dark .pill-tab:hover { color: #f8fafc; }
    .studio-dark .sub-pill-tab { background: #0f172a !important; border-color: #334155 !important; }
    .studio-dark .sub-pill-tab.active { background: #1e293b !important; color: var(--mt-brand) !important; }
    .studio-dark .gjs-layer { background: #0f172a !important; border-color: #334155 !important; color: #cbd5e1 !important; }
    .studio-dark .gjs-layer.gjs-layer-active { border-color: var(--mt-brand) !important; background: #1e293b !important; color:#ffffff !important;}
    
    .studio-dark #shortcode_drawer { background-color: #0f172a !important; border-top-color: #334155 !important; }
    .studio-dark #shortcode_drawer h3 { color: #f8fafc !important; }
    .studio-dark #shortcode_drawer p.italic { color: #94a3b8 !important; }
    .studio-dark .sc-chip { background-color: #1e293b !important; color: #f8fafc !important; border-color: #334155 !important; }
    .studio-dark .sc-chip:hover { background-color: #334155 !important; }
    .studio-dark #shortcode_tab { background-color: #1e293b !important; border-color: #334155 !important; }
    .studio-dark #shortcode_tab span, .studio-dark #shortcode_tab i { color: #cbd5e1 !important; }
    
    .studio-dark .device-btn { background: transparent !important; color: #64748b !important; }
    .studio-dark .device-btn.active { background: #334155 !important; color: #ffffff !important; }
    .studio-dark #btn-dark-mode:hover { background-color: #334155 !important; color: #ffffff !important;}
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
                                <h3 class="font-bold text-gray-900 truncate">
                                    <?php echo esc_html($tpl->template_name); ?>
                                    <?php if(strpos($tpl->template_name, 'Draft') !== false) echo '<span class="ml-2 px-2 py-0.5 bg-yellow-100 text-yellow-700 text-[10px] rounded font-bold">DRAFT</span>'; ?>
                                </h3>
                                <div class="flex justify-between items-center mt-4 border-t border-gray-100 pt-4">
                                    <button onclick="trashTemplate(<?php echo $tpl->id; ?>)" class="text-gray-300 hover:text-red-500 transition"><i class="fa-solid fa-trash-can"></i></button>
                                    <button onclick="openBuilder(<?php echo $tpl->id; ?>, '<?php echo esc_js($tpl->template_name); ?>')" class="bg-gray-900 text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-black transition">Open Designer</button>
                                    <textarea id="raw_body_<?php echo $tpl->id; ?>" style="display:none;"><?php echo esc_textarea($tpl->email_body); ?></textarea>
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
                    $prebuilts = [
                        ['name' => 'The Instant Reward', 'desc' => 'Perfect for Free WiFi gifts.', 'icon' => 'fa-gift'],
                        ['name' => 'The Review Requester', 'desc' => 'Drive 5-star Google Reviews.', 'icon' => 'fa-star'],
                    ];
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
                <?php if(empty($trashed_templates)): ?>
                    <div class="text-center py-16"><p class="text-gray-500">Trash is empty.</p></div>
                <?php else: ?>
                    <button onclick="emptyTrash()" class="mb-4 text-xs font-bold text-red-600 bg-red-50 px-4 py-2 rounded-lg transition hover:bg-red-100">Empty Trash Now</button>
                    <div class="bg-white border rounded-xl overflow-hidden">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 border-b text-[10px] uppercase text-gray-500 font-bold">
                                <tr><th class="p-4 pl-6">Template Name</th><th class="p-4">Deleted On</th><th class="p-4 text-right pr-6">Action</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($trashed_templates as $tpl): ?>
                                <tr class="border-b hover:bg-gray-50 transition">
                                    <td class="p-4 pl-6 font-bold text-gray-700 line-through"><?php echo esc_html($tpl->template_name); ?></td>
                                    <td class="p-4 text-gray-500"><?php echo date('M d, Y', strtotime($tpl->deleted_at)); ?></td>
                                    <td class="p-4 pr-6 text-right flex justify-end gap-4">
                                        <button onclick="restoreTemplate(<?php echo $tpl->id; ?>)" class="text-green-600 font-bold hover:underline">Restore</button>
                                        <button onclick="deletePermanent(<?php echo $tpl->id; ?>)" class="text-red-500 font-bold hover:underline">Delete Forever</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="view_builder" class="fixed inset-0 z-[100] hidden flex-col font-sans transition-colors duration-300 bg-[#f4f4f5]">
    
    <div class="h-16 bg-white border-b border-gray-200 px-6 flex justify-between items-center shrink-0 z-30 shadow-sm transition-colors duration-300">
        <div class="flex items-center gap-4">
            <button onclick="closeBuilder()" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-500 transition" title="Exit Builder"><i class="fa-solid fa-arrow-left text-sm"></i></button>
            <div class="h-6 w-px bg-gray-200 mx-2"></div>
            <input type="text" id="builder_tpl_name" class="border-none bg-transparent outline-none text-lg font-black text-gray-900 placeholder-gray-400 w-64 transition-colors" placeholder="Design Name...">
        </div>
        
        <div class="flex items-center gap-2 bg-gray-50 border border-gray-200 p-1 rounded-lg transition-colors">
            <button onclick="setDevice('desktop')" id="device-desktop" class="device-btn active w-8 h-8 rounded bg-white shadow-sm flex items-center justify-center text-gray-800 transition"><i class="fa-solid fa-desktop text-xs"></i></button>
            <button onclick="setDevice('mobile')" id="device-mobile" class="device-btn w-8 h-8 rounded hover:bg-gray-200 flex items-center justify-center text-gray-500 transition"><i class="fa-solid fa-mobile-alt text-xs"></i></button>
            <div class="h-4 w-px bg-gray-300 mx-1 transition-colors"></div>
            <button onclick="toggleDarkMode()" id="btn-dark-mode" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200 text-gray-500 transition" title="Toggle Dark Mode"><i class="fa-solid fa-moon text-sm"></i></button>
        </div>

        <div class="flex items-center gap-3">
            <button onclick="openTestModal()" class="bg-white border border-gray-200 text-gray-700 px-4 py-2.5 rounded-lg text-sm font-bold hover:bg-gray-50 transition shadow-sm"><i class="fa-regular fa-paper-plane mr-2"></i>Test</button>
            <button onclick="saveAsTemplate()" class="bg-indigo-50 border border-indigo-200 text-indigo-700 px-4 py-2.5 rounded-lg text-sm font-bold hover:bg-indigo-100 transition shadow-sm flex items-center gap-2"><i class="fa-regular fa-copy"></i> Save as Template</button>
            <button onclick="saveBuilder()" id="btn_save_builder" class="bg-gray-900 text-white px-6 py-2.5 rounded-lg text-sm font-bold hover:bg-black transition shadow-lg flex items-center gap-2"><i class="fa-solid fa-floppy-disk"></i> Save</button>
        </div>
    </div>

    <input type="hidden" id="builder_tpl_id" value="0">

    <div class="flex flex-1 overflow-hidden relative">
        
        <div id="builder_loader" class="absolute inset-0 bg-[#f8fafc] z-50 flex flex-col items-center justify-center transition-colors">
            <i class="fa-solid fa-circle-notch fa-spin text-5xl text-indigo-500 mb-4"></i>
            <h2 class="text-gray-900 font-black tracking-widest uppercase text-sm">Loading Studio...</h2>
        </div>
        
        <div id="mt-left-panel" class="w-[320px] bg-[#ffffff] border-r border-gray-200 flex flex-col shrink-0 relative shadow-[4px_0_15px_rgba(0,0,0,0.03)] z-20 transition-colors">
            
            <div class="px-6 flex gap-6 bg-white border-b border-gray-100 shrink-0 transition-colors">
                <button onclick="switchLeftTab('content')" id="tab-btn-content" class="pill-tab active">Elements</button>
                <button onclick="switchLeftTab('sections')" id="tab-btn-sections" class="pill-tab">Sections</button>
                <button onclick="switchLeftTab('body')" id="tab-btn-body" class="pill-tab">Body</button>
                <button onclick="switchLeftTab('images')" id="tab-btn-images" class="pill-tab">Images</button>
            </div>
            
            <div id="tab-content" class="flex-1 overflow-y-auto custom-scrollbar panel-tab-content active p-0">
                <div id="mt-blocks-container"></div>
            </div>

            <div id="tab-sections" class="flex-1 flex-col overflow-hidden panel-tab-content">
                <div class="flex border-b border-gray-100 bg-gray-50 shrink-0 transition-colors">
                    <button onclick="switchSubTab('prebuilt')" id="sub-btn-prebuilt" class="sub-pill-tab w-1/2 active text-center border-r border-gray-100">Pre-Built</button>
                    <button onclick="switchSubTab('layers')" id="sub-btn-layers" class="sub-pill-tab w-1/2 text-center">Layers</button>
                </div>
                <div id="sub-content-prebuilt" class="flex-1 overflow-y-auto custom-scrollbar p-0 sub-panel active block">
                    <div id="mt-prebuilt-container"></div>
                </div>
                <div id="sub-content-layers" class="flex-1 overflow-y-auto custom-scrollbar p-6 sub-panel hidden">
                    <div id="mt-layers-container"></div>
                </div>
            </div>
            
            <div id="tab-body" class="flex-1 overflow-y-auto custom-scrollbar panel-tab-content p-6">
                <div class="space-y-5">
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">Outer Background Color</label>
                        <input type="color" value="#f1f5f9" onchange="updateOuterBg(this.value)" class="w-full h-10 rounded cursor-pointer border border-gray-200 p-1 bg-white transition-colors">
                        <p class="text-[10px] text-gray-400 mt-1">The area behind your email.</p>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">Inner Body Color</label>
                        <input type="color" value="#ffffff" onchange="updateInnerBg(this.value)" class="w-full h-10 rounded cursor-pointer border border-gray-200 p-1 bg-white transition-colors">
                        <p class="text-[10px] text-gray-400 mt-1">The canvas of the email card itself.</p>
                    </div>
                </div>
            </div>

            <div id="tab-images" class="flex-1 overflow-y-auto custom-scrollbar panel-tab-content p-6">
                <p class="text-[10px] text-gray-500 mb-4 font-bold uppercase tracking-widest">Click to Insert</p>
                <div class="grid grid-cols-2 gap-3">
                    <?php foreach($vault_assets as $img): ?>
                        <div onclick="insertImageToCanvas('<?php echo esc_js($img); ?>')" class="bg-white border border-gray-200 rounded-lg p-2 h-24 flex items-center justify-center cursor-pointer hover:border-indigo-500 transition-colors">
                            <img src="<?php echo esc_url($img); ?>" class="max-h-full max-w-full object-contain">
                        </div>
                    <?php endforeach; ?>
                    <?php if(empty($vault_assets)): ?>
                        <p class="text-xs text-gray-400 col-span-2 text-center py-4">No images found. Check Brand Settings.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div id="panel-edit-menu" class="hidden flex-col h-full bg-[#ffffff] absolute inset-0 z-30 border-l border-gray-100 transition-colors">
                <div class="h-14 border-b border-gray-100 flex items-center px-4 bg-white shrink-0 transition-colors">
                    <button onclick="closeEditPanel()" class="flex items-center gap-2 text-[10px] font-bold text-gray-500 hover:text-gray-900 uppercase tracking-widest transition"><i class="fa-solid fa-arrow-left"></i> Back</button>
                </div>
                <div id="style-scroll-container" class="flex-1 overflow-y-auto custom-scrollbar flex flex-col">
                    <div id="mt-traits-container"></div>
                    <div id="mt-styles-container" class="pb-10"></div>
                </div>
            </div>

            <div id="mt_color_drawer">
                <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3">
                    <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-500">Pick a Color</h3>
                    <button onclick="closeColorDrawer()" class="text-gray-400 hover:text-gray-900"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <p class="text-[10px] font-bold uppercase text-gray-400 mb-2">Brand Palette</p>
                <div class="grid grid-cols-6 gap-2 mb-5">
                    <button class="color-swatch-btn" style="background-color: <?php echo esc_attr($brand_color); ?>;" onclick="applyCustomColor('<?php echo esc_js($brand_color); ?>')" title="Primary"></button>
                    <button class="color-swatch-btn" style="background-color: <?php echo esc_attr($mt_palette['accent']); ?>;" onclick="applyCustomColor('<?php echo esc_js($mt_palette['accent']); ?>')" title="Accent"></button>
                    <button class="color-swatch-btn" style="background-color: #0f172a;" onclick="applyCustomColor('#0f172a')" title="Dark Slate"></button>
                    <button class="color-swatch-btn" style="background-color: #475569;" onclick="applyCustomColor('#475569')" title="Slate"></button>
                    <button class="color-swatch-btn" style="background-color: #ffffff;" onclick="applyCustomColor('#ffffff')" title="White"></button>
                    <button class="color-swatch-btn flex items-center justify-center text-red-500 bg-gray-50" onclick="applyCustomColor('transparent')" title="Transparent"><i class="fa-solid fa-ban text-xs"></i></button>
                </div>
                <p class="text-[10px] font-bold uppercase text-gray-400 mb-2">Custom Color</p>
                <div class="flex items-center gap-3">
                    <input type="color" id="mt_custom_color_picker" class="w-10 h-10 rounded cursor-pointer border border-gray-200 p-0 bg-transparent" onchange="applyCustomColor(this.value)">
                    <input type="text" id="mt_custom_color_hex" class="flex-1 border border-gray-200 rounded-lg text-sm p-2.5 font-bold text-gray-700 outline-none focus:border-indigo-500 shadow-sm" placeholder="#HEXCODE" onchange="applyCustomColor(this.value)">
                </div>
            </div>

        </div>

        <div id="canvas-wrapper" class="flex-1 relative overflow-y-auto flex justify-center pt-10 pb-24 bg-[#f1f5f9] transition-colors custom-scrollbar">
            <div id="gjs-container" class="w-full max-w-[650px] relative transition-all duration-300 mx-auto">
                <div id="gjs" class="absolute inset-0 bg-transparent"></div>
            </div>
            
            <div id="shortcode_tab" onclick="toggleShortcodes()" class="fixed bottom-0 right-1/2 translate-x-[150px] bg-white border border-gray-200 border-b-0 rounded-t-xl px-6 py-2 shadow-lg cursor-pointer z-40 flex items-center gap-2 hover:bg-gray-50 transition-colors">
                <span class="text-[10px] font-black uppercase tracking-tighter text-gray-500">Shortcodes</span>
                <i class="fa-solid fa-chevron-up text-[10px] text-gray-400"></i>
            </div>
        </div>

    </div>
    
    <div id="shortcode_drawer" class="fixed bottom-0 left-[320px] right-0 h-auto bg-white border-t border-gray-200 z-50 shadow-[0_-10px_40px_rgba(0,0,0,0.1)] p-6 transition-colors">
        <div class="max-w-4xl mx-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xs font-black uppercase tracking-widest text-gray-400">Personalization Tags</h3>
                <button onclick="toggleShortcodes()" class="text-gray-400 hover:text-gray-900 text-lg"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="flex flex-wrap gap-2">
                <div onclick="copyTag('[Guest_First_Name]')" class="sc-chip px-3 py-1.5 rounded-lg font-bold text-[11px] text-gray-800">[Guest_First_Name]</div>
                <div onclick="copyTag('[Guest_Full_Name]')" class="sc-chip px-3 py-1.5 rounded-lg font-bold text-[11px] text-gray-800">[Guest_Full_Name]</div>
                <div onclick="copyTag('[Guest_Email]')" class="sc-chip px-3 py-1.5 rounded-lg font-bold text-[11px] text-gray-800">[Guest_Email]</div>
                <div onclick="copyTag('[Guest_Phone]')" class="sc-chip px-3 py-1.5 rounded-lg font-bold text-[11px] text-gray-800">[Guest_Phone]</div>
                <div onclick="copyTag('[Guest_Birthday]')" class="sc-chip px-3 py-1.5 rounded-lg font-bold text-[11px] text-gray-800">[Guest_Birthday]</div>
                <div onclick="copyTag('[Brand_Name]')" class="sc-chip px-3 py-1.5 rounded-lg font-bold text-[11px] text-indigo-600 border-indigo-100 bg-indigo-50">[Brand_Name]</div>
                <div onclick="copyTag('[Location_Name]')" class="sc-chip px-3 py-1.5 rounded-lg font-bold text-[11px] text-indigo-600 border-indigo-100 bg-indigo-50">[Location_Name]</div>
                <div onclick="copyTag('[Visit_Date]')" class="sc-chip px-3 py-1.5 rounded-lg font-bold text-[11px] text-indigo-600 border-indigo-100 bg-indigo-50">[Visit_Date]</div>
                <div onclick="copyTag('[Unsubscribe_Link]')" class="sc-chip px-3 py-1.5 rounded-lg font-bold text-[11px] text-red-600 border-red-100 bg-red-50">[Unsubscribe_Link]</div>
            </div>
            <p class="text-[10px] text-gray-400 mt-4 italic">Click any tag to copy it, then paste it directly into your text blocks.</p>
        </div>
    </div>
</div>

<div id="test_email_modal" class="fixed inset-0 bg-gray-900/60 z-[200] hidden items-center justify-center backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-white">
            <h2 class="text-lg font-black text-gray-900">Send Test Email</h2>
            <button onclick="closeTestModal()" class="text-gray-400 hover:text-gray-900"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <div class="p-6 space-y-5 bg-gray-50">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">From Sender</label>
                <input type="text" value="<?php echo esc_attr($sender_email); ?>" class="w-full p-3 bg-gray-100 border border-gray-200 rounded-lg text-sm font-bold text-gray-500 cursor-not-allowed" disabled>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">Test Subject Line</label>
                <input type="text" id="test_subject" class="w-full p-3 bg-white border border-gray-300 rounded-lg text-sm font-bold focus:border-indigo-500 outline-none transition" placeholder="e.g. Test: Welcome Offer">
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">Send To</label>
                <input type="email" id="test_recipient" class="w-full p-3 bg-white border border-gray-300 rounded-lg text-sm font-bold focus:border-indigo-500 outline-none transition" placeholder="you@domain.com">
            </div>
            <button onclick="sendTestEmail()" class="w-full bg-gray-900 text-white font-bold py-3.5 rounded-xl hover:bg-black transition mt-4 shadow-lg">Send Now</button>
        </div>
    </div>
</div>

<div id="mt_confirm_modal" class="fixed inset-0 bg-gray-900/60 z-[300] hidden items-center justify-center backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden transform scale-95 transition-transform duration-200" id="mt_confirm_box">
        <div class="p-6 space-y-4 text-center mt-2">
            <div class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-2"><i class="fa-solid fa-triangle-exclamation text-3xl"></i></div>
            <h2 id="confirm_title" class="text-xl font-black text-gray-900">Confirm</h2>
            <p id="confirm_msg" class="text-sm text-gray-500 font-medium"></p>
        </div>
        <div class="p-4 bg-gray-50 flex gap-3 border-t border-gray-100">
            <button onclick="closeConfirmModal()" class="flex-1 bg-white border border-gray-200 text-gray-700 py-2.5 rounded-xl font-bold hover:bg-gray-50 transition">Cancel</button>
            <button onclick="executeConfirm()" class="flex-1 bg-red-600 text-white py-2.5 rounded-xl font-bold hover:bg-red-700 transition shadow">Confirm</button>
        </div>
    </div>
</div>

<div id="mt_prompt_modal" class="fixed inset-0 bg-gray-900/60 z-[300] hidden items-center justify-center backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform scale-95 transition-transform duration-200" id="mt_prompt_box">
        <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-white">
            <h2 id="prompt_title" class="text-lg font-black text-gray-900">Input Needed</h2>
            <button onclick="closePromptModal()" class="text-gray-400 hover:text-gray-900"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <div class="p-6 space-y-4 bg-gray-50">
            <p id="prompt_msg" class="text-[11px] uppercase tracking-widest font-bold text-gray-500"></p>
            <input type="text" id="prompt_input" class="w-full p-3 bg-white border border-gray-300 rounded-lg text-sm font-bold focus:border-indigo-500 outline-none transition shadow-sm">
            <button onclick="executePrompt()" class="w-full bg-indigo-600 text-white font-bold py-3.5 rounded-xl hover:bg-indigo-700 transition mt-2 shadow-lg">Save Configuration</button>
        </div>
    </div>
</div>

<div id="mt_toast_container" class="fixed bottom-8 right-8 z-[400] flex flex-col items-end pointer-events-none"></div>

<script>
    let editor = null;
    const defaultMjml = `<?php echo addslashes(str_replace(["\r", "\n"], '', $default_mjml)); ?>`;
    const brandPrimaryColor = '<?php echo esc_js($brand_color); ?>';
    const brandAccentColor = '<?php echo esc_js($mt_palette["accent"]); ?>';
    const brandLogoUrl = '<?php echo esc_js($brand_logo); ?>';
    const brandName = '<?php echo esc_js($brand_name); ?>';

    // --- UI/UX: CUSTOM POPUPS & TOASTS ---
    function showToast(message, type = 'success') {
        const container = document.getElementById('mt_toast_container');
        const toast = document.createElement('div');
        const bgColor = type === 'error' ? 'bg-red-600' : 'bg-gray-900';
        const icon = type === 'error' ? 'fa-triangle-exclamation' : 'fa-check-circle';
        
        toast.className = `flex items-center gap-3 px-5 py-3.5 rounded-xl shadow-[0_10px_40px_rgba(0,0,0,0.2)] text-white text-sm font-bold transform transition-all duration-300 translate-y-10 opacity-0 ${bgColor} mb-3 pointer-events-auto`;
        toast.innerHTML = `<i class="fa-solid ${icon} text-lg"></i> ${message}`;
        container.appendChild(toast);
        
        requestAnimationFrame(() => { toast.classList.remove('translate-y-10', 'opacity-0'); });
        setTimeout(() => {
            toast.classList.add('translate-y-10', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    let confirmCallback = null;
    function mtConfirm(title, msg, callback) {
        document.getElementById('confirm_title').innerText = title;
        document.getElementById('confirm_msg').innerText = msg;
        confirmCallback = callback;
        document.getElementById('mt_confirm_modal').classList.remove('hidden');
        document.getElementById('mt_confirm_modal').classList.add('flex');
        setTimeout(() => document.getElementById('mt_confirm_box').classList.remove('scale-95'), 10);
    }
    function closeConfirmModal() { 
        document.getElementById('mt_confirm_box').classList.add('scale-95');
        setTimeout(() => { document.getElementById('mt_confirm_modal').classList.add('hidden'); document.getElementById('mt_confirm_modal').classList.remove('flex'); }, 150);
    }
    function executeConfirm() { if(confirmCallback) confirmCallback(); closeConfirmModal(); }

    let promptCallback = null;
    function mtPrompt(title, msg, defValue, callback) {
        document.getElementById('prompt_title').innerText = title;
        document.getElementById('prompt_msg').innerText = msg;
        document.getElementById('prompt_input').value = defValue || '';
        promptCallback = callback;
        document.getElementById('mt_prompt_modal').classList.remove('hidden');
        document.getElementById('mt_prompt_modal').classList.add('flex');
        setTimeout(() => { document.getElementById('mt_prompt_box').classList.remove('scale-95'); document.getElementById('prompt_input').focus(); }, 10);
    }
    function closePromptModal() { 
        document.getElementById('mt_prompt_box').classList.add('scale-95');
        setTimeout(() => { document.getElementById('mt_prompt_modal').classList.add('hidden'); document.getElementById('mt_prompt_modal').classList.remove('flex'); }, 150);
    }
    function executePrompt() { 
        const val = document.getElementById('prompt_input').value;
        if(promptCallback) promptCallback(val); 
        closePromptModal(); 
    }

    // --- DARK MODE LOGIC ---
    function toggleDarkMode() {
        const builder = document.getElementById('view_builder');
        const icon = document.querySelector('#btn-dark-mode i');
        builder.classList.toggle('studio-dark');
        
        if (builder.classList.contains('studio-dark')) {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
            showToast('Studio Dark Mode Activated');
        } else {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        }
    }

    // --- CUSTOM COLOR DRAWER LOGIC WITH AUTO-CLOSE ---
    let activeColorInput = null;

    document.getElementById('mt-left-panel').addEventListener('click', function(e) {
        const pickerBtn = e.target.closest('.gjs-field-color-picker') || e.target.closest('.gjs-field-colorp');
        const insideDrawer = e.target.closest('#mt_color_drawer');
        
        if (pickerBtn) {
            e.preventDefault();
            e.stopPropagation();
            const fieldWrapper = pickerBtn.closest('.gjs-field-color, .gjs-field');
            if(fieldWrapper) {
                activeColorInput = fieldWrapper.querySelector('input[type="text"]');
                if(activeColorInput) {
                    document.getElementById('mt_color_drawer').classList.add('open');
                    let currentVal = activeColorInput.value;
                    document.getElementById('mt_custom_color_hex').value = currentVal;
                    if(currentVal && currentVal !== 'transparent' && currentVal !== 'none') {
                        try { document.getElementById('mt_custom_color_picker').value = currentVal.slice(0,7); } catch(err){}
                    }
                }
            }
        } else if (!insideDrawer) {
            closeColorDrawer();
        }
    }, true);

    function closeColorDrawer() {
        document.getElementById('mt_color_drawer').classList.remove('open');
        activeColorInput = null;
    }

    function applyCustomColor(hex) {
        if(!activeColorInput) return;
        document.getElementById('mt_custom_color_hex').value = hex;
        if(hex && hex !== 'transparent' && hex !== 'none') {
            try { document.getElementById('mt_custom_color_picker').value = hex.slice(0,7); } catch(err){}
        }
        activeColorInput.value = hex;
        activeColorInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // --- TAB LOGIC ---
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
        closeColorDrawer();
    }
    function switchSubTab(tab) {
        document.querySelectorAll('.sub-pill-tab').forEach(b => b.classList.remove('active'));
        document.getElementById('sub-btn-' + tab).classList.add('active');
        document.querySelectorAll('.sub-panel').forEach(c => { c.classList.remove('active'); c.classList.add('hidden'); });
        document.getElementById('sub-content-' + tab).classList.remove('hidden');
        document.getElementById('sub-content-' + tab).classList.add('active');
    }
    function closeEditPanel() { 
        if(editor) editor.select(null); 
        document.getElementById('panel-edit-menu').classList.add('hidden'); 
        closeColorDrawer();
    }

    // --- ACTIONS & MODALS ---
    function openTestModal() { document.getElementById('test_email_modal').classList.remove('hidden'); document.getElementById('test_email_modal').classList.add('flex'); }
    function closeTestModal() { document.getElementById('test_email_modal').classList.add('hidden'); }
    function sendTestEmail() { 
        const to = document.getElementById('test_recipient').value;
        if(!to) { showToast("Please enter a recipient email.", "error"); return; }
        showToast("Test Email queued successfully!"); closeTestModal(); 
    }
    
    function toggleShortcodes() { document.getElementById('shortcode_drawer').classList.toggle('open'); }
    function copyTag(tag) { navigator.clipboard.writeText(tag); showToast(`Copied ${tag} to clipboard!`); }
    
    function closeBuilder() { 
        mtConfirm("Exit Studio", "Are you sure you want to exit? Any unsaved changes will be lost.", function() {
            window.location.reload(); 
        });
    }

    function setDevice(device) {
        document.querySelectorAll('.device-btn').forEach(b => { b.classList.remove('active', 'bg-white', 'shadow-sm'); });
        document.getElementById('device-' + device).classList.add('active', 'bg-white', 'shadow-sm');
        if(editor) {
            if(device === 'mobile') editor.setDevice('Mobile portrait'); else editor.setDevice('Desktop');
        }
    }
    function updateOuterBg(color) { 
        document.getElementById('canvas-wrapper').style.backgroundColor = color; 
        if(editor) { const body = editor.getWrapper().find('mj-body')[0]; if(body) body.addAttributes({'background-color': color}); }
    }
    function updateInnerBg(color) {
        if(editor) { const innerWrapper = editor.getWrapper().find('mj-wrapper')[0]; if(innerWrapper) innerWrapper.addAttributes({'background-color': color}); }
    }
    function insertImageToCanvas(url) {
        const selected = editor.getSelected();
        if(selected && selected.is('mj-image')) { selected.addAttributes({'src': url}); showToast("Image updated successfully!"); }
        else { showToast("Please select an Image block on the canvas first.", "error"); }
    }

    // --- SILENT AUTO-SAVE DRAFT LOGIC ---
    function silentDraftSave() {
        const now = new Date();
        const timeString = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        const dateString = now.toLocaleDateString();
        const draftName = 'Draft - ' + dateString + ' ' + timeString;
        
        const fd = new FormData();
        fd.append('action', 'mt_save_template'); 
        fd.append('security', typeof mt_nonce !== 'undefined' ? mt_nonce : ''); 
        fd.append('template_id', 0); 
        fd.append('template_name', draftName);
        
        const payload = JSON.stringify({ html: '', mjml: defaultMjml });
        const safePayload = btoa(unescape(encodeURIComponent(payload))); 
        fd.append('email_body', safePayload);
        
        const ajaxUrl = typeof mt_ajax_url !== 'undefined' ? mt_ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
        fetch(ajaxUrl, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            if(res.success) {
                document.getElementById('builder_tpl_id').value = res.data.id;
                document.getElementById('builder_tpl_name').value = draftName;
            }
        }).catch(err => console.error("Silent Draft Save Failed", err));
    }

    // --- TRASH RESTORE / EMPTY / PERMANENT LOGIC ---
    function trashTemplate(id) { 
        mtConfirm("Trash Template", "Are you sure you want to move this design to the trash?", function() {
            const fd = new FormData(); 
            fd.append('action','mt_trash_template'); 
            fd.append('security', typeof mt_nonce !== 'undefined' ? mt_nonce : ''); 
            fd.append('template_id',id); 
            
            const ajaxUrl = typeof mt_ajax_url !== 'undefined' ? mt_ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
            fetch(ajaxUrl,{method:'POST',body:fd}).then(()=>window.location.reload());
        });
    }
    
    function restoreTemplate(id) {
        const fd = new FormData(); 
        fd.append('action','mt_restore_template'); 
        fd.append('security', typeof mt_nonce !== 'undefined' ? mt_nonce : ''); 
        fd.append('template_id',id); 
        const ajaxUrl = typeof mt_ajax_url !== 'undefined' ? mt_ajax_url : '/wp-admin/admin-ajax.php';
        fetch(ajaxUrl,{method:'POST',body:fd}).then(()=>window.location.reload());
    }

    function emptyTrash() {
        mtConfirm("Empty Trash", "Are you sure you want to permanently delete all items in the trash? This cannot be undone.", function() {
            const fd = new FormData(); 
            fd.append('action','mt_empty_trash'); 
            fd.append('security', typeof mt_nonce !== 'undefined' ? mt_nonce : ''); 
            const ajaxUrl = typeof mt_ajax_url !== 'undefined' ? mt_ajax_url : '/wp-admin/admin-ajax.php';
            fetch(ajaxUrl,{method:'POST',body:fd}).then(()=>window.location.reload());
        });
    }

    function deletePermanent(id) {
        mtConfirm("Delete Forever", "Are you sure you want to permanently delete this template? This cannot be undone.", function() {
            const fd = new FormData(); 
            fd.append('action','mt_delete_template_permanent'); 
            fd.append('security', typeof mt_nonce !== 'undefined' ? mt_nonce : ''); 
            fd.append('template_id',id); 
            const ajaxUrl = typeof mt_ajax_url !== 'undefined' ? mt_ajax_url : '/wp-admin/admin-ajax.php';
            fetch(ajaxUrl,{method:'POST',body:fd}).then(()=>window.location.reload());
        });
    }

    // --- BUILDER CORE ---
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
            let rawBody = document.getElementById('raw_body_' + id).value.trim(); 
            if (rawBody && !rawBody.startsWith('{') && !rawBody.startsWith('<')) {
                try { rawBody = decodeURIComponent(escape(atob(rawBody))); } catch(e) { console.error("Base64 decode failed"); }
            }
            try { 
                let parsed = JSON.parse(rawBody); 
                startingData = parsed.mjml || defaultMjml; 
            } catch(e) { startingData = rawBody || defaultMjml; }
        } else { 
            startingData = defaultMjml; 
            silentDraftSave();
        }

        if (!editor) { initGrapesJS(startingData); } 
        else { 
            editor.setComponents(startingData); 
            setTimeout(() => { document.getElementById('builder_loader').classList.add('hidden'); }, 500); 
        }
        closeEditPanel(); switchLeftTab('content'); 
    }

    function initGrapesJS(startingData) {
        editor = grapesjs.init({
            container: '#gjs',
            fromElement: false,
            components: startingData, 
            height: '100%',
            width: '100%',
            storageManager: false,
            plugins: ['grapesjs-mjml'],
            pluginsOpts: { 'grapesjs-mjml': {} },
            colorPicker: false, 
            blockManager: { appendTo: '#mt-blocks-container' },
            layerManager: { appendTo: '#mt-layers-container' },
            traitManager: { appendTo: '#mt-traits-container' },
            styleManager: { 
                appendTo: '#mt-styles-container',
                sectors: [
                    { name: 'Typography', open: true, buildProps: ['font-family', 'font-size', 'font-weight', 'color', 'text-align', 'line-height'] },
                    { name: 'Padding & Margin', open: true, buildProps: ['padding', 'margin'] },
                    { name: 'Decorations', open: false, buildProps: ['background-color', 'border-radius', 'border'] }
                ]
            }
        });

        editor.on('load', () => {
            const bm = editor.BlockManager;
            bm.getAll().reset(); 
            
            /* 1. THE ELEMENTS TAB (Structure) */
            bm.add('mt-1-col', { category: 'Structure', label: `<i class="fa-regular fa-square"></i><div class="gjs-block-label">1 Column</div>`, content: `<mj-section padding="10px 0"><mj-column><mj-text>Content</mj-text></mj-column></mj-section>` });
            bm.add('mt-2-col', { category: 'Structure', label: `<i class="fa-solid fa-table-columns"></i><div class="gjs-block-label">2 Columns</div>`, content: `<mj-section padding="10px 0"><mj-column padding="0 10px"><mj-text>Left</mj-text></mj-column><mj-column padding="0 10px"><mj-text>Right</mj-text></mj-column></mj-section>` });
            bm.add('mt-3-col', { category: 'Structure', label: `<i class="fa-solid fa-bars-staggered"></i><div class="gjs-block-label">3 Columns</div>`, content: `<mj-section padding="10px 0"><mj-column padding="0 5px"><mj-text align="center">1</mj-text></mj-column><mj-column padding="0 5px"><mj-text align="center">2</mj-text></mj-column><mj-column padding="0 5px"><mj-text align="center">3</mj-text></mj-column></mj-section>` });
            bm.add('mt-4-col', { category: 'Structure', label: `<i class="fa-solid fa-grip"></i><div class="gjs-block-label">4 Columns</div>`, content: `<mj-section padding="10px 0"><mj-column padding="0 5px"><mj-text align="center">1</mj-text></mj-column><mj-column padding="0 5px"><mj-text align="center">2</mj-text></mj-column><mj-column padding="0 5px"><mj-text align="center">3</mj-text></mj-column><mj-column padding="0 5px"><mj-text align="center">4</mj-text></mj-column></mj-section>` });

            /* 2. THE ELEMENTS TAB (Basic Elements) */
            bm.add('mt-logo', { category: 'Basic', label: `<i class="fa-solid fa-leaf"></i><div class="gjs-block-label">Logo</div>`, content: `<mj-image src="${brandLogoUrl}" alt="Logo" width="120px" align="center" padding="10px 0"></mj-image>` });
            bm.add('mt-spacer', { category: 'Basic', label: `<i class="fa-solid fa-arrows-up-down"></i><div class="gjs-block-label">Padding</div>`, content: `<mj-spacer height="20px"></mj-spacer>` });
            bm.add('mt-social', { category: 'Basic', label: `<i class="fa-solid fa-hashtag"></i><div class="gjs-block-label">Social</div>`, content: `<mj-social font-size="15px" icon-size="30px" mode="horizontal" padding="10px"><mj-social-element name="facebook" href="#"></mj-social-element><mj-social-element name="instagram" href="#"></mj-social-element><mj-social-element name="twitter" href="#"></mj-social-element></mj-social>` });
            bm.add('mt-title', { category: 'Basic', label: `<i class="fa-solid fa-heading"></i><div class="gjs-block-label">Title</div>`, content: `<mj-text font-size="24px" color="#0f172a" font-weight="900" line-height="1.4" padding="10px 0">Add your heading</mj-text>` });
            bm.add('mt-text', { category: 'Basic', label: `<i class="fa-solid fa-font"></i><div class="gjs-block-label">Paragraph</div>`, content: `<mj-text font-size="15px" color="#334155" line-height="1.6" padding="10px 0">Type your paragraph here. You can add more text to fill this area.</mj-text>` });
            bm.add('mt-boxed-text', { category: 'Basic', label: `<i class="fa-regular fa-square-plus"></i><div class="gjs-block-label">Boxed Text</div>`, content: `<mj-text container-background-color="#f8fafc" padding="20px" font-size="15px" color="#334155" line-height="1.6" border-radius="6px">This text is highlighted inside a shaded box.</mj-text>` });
            bm.add('mt-code', { category: 'Basic', label: `<i class="fa-solid fa-code"></i><div class="gjs-block-label">HTML</div>`, content: `<mj-raw><div style="text-align:center; padding:10px; color:#94a3b8; font-size:12px; border:1px dashed #cbd5e1;">Custom HTML / Code Block</div></mj-raw>` });
            bm.add('mt-image', { category: 'Basic', label: `<i class="fa-regular fa-image"></i><div class="gjs-block-label">Image</div>`, content: `<mj-image src="https://placehold.co/600x300/e2e8f0/94a3b8?text=Image" border-radius="6px" padding="10px 0"></mj-image>` });
            bm.add('mt-image-group', { category: 'Basic', label: `<i class="fa-regular fa-images"></i><div class="gjs-block-label">Image Group</div>`, content: `<mj-section padding="10px 0"><mj-group><mj-column padding="0 5px"><mj-image src="https://placehold.co/300x300/e2e8f0/94a3b8?text=Img+1" border-radius="6px" padding="0"></mj-image></mj-column><mj-column padding="0 5px"><mj-image src="https://placehold.co/300x300/e2e8f0/94a3b8?text=Img+2" border-radius="6px" padding="0"></mj-image></mj-column></mj-group></mj-section>` });
            bm.add('mt-divider', { category: 'Basic', label: `<i class="fa-solid fa-minus"></i><div class="gjs-block-label">Divider</div>`, content: `<mj-divider border-width="2px" border-color="#f1f5f9" padding="20px 0"></mj-divider>` });
            bm.add('mt-button', { category: 'Basic', label: `<i class="fa-solid fa-hand-pointer"></i><div class="gjs-block-label">Button</div>`, content: `<mj-button background-color="${brandPrimaryColor}" color="#ffffff" font-weight="bold" border-radius="6px" inner-padding="14px 28px" padding="15px 0" href="#">Click Here</mj-button>` });

            /* 3. THE ELEMENTS TAB (Advanced Elements) */
            bm.add('adv-dual-hdr', { category: 'Advanced', label: `<i class="fa-solid fa-text-width"></i><div class="gjs-block-label">Dual Header</div>`, content: `<mj-text font-size="28px" font-weight="900" align="center" padding="10px 0"><span style="color:#0f172a;">MAIN</span> <span style="color:${brandPrimaryColor};">HIGHLIGHT</span></mj-text>` });
            bm.add('adv-img-card', { category: 'Advanced', label: `<i class="fa-regular fa-id-card"></i><div class="gjs-block-label">Image Card</div>`, content: `<mj-section background-color="#ffffff" border-radius="8px" border="1px solid #e2e8f0" padding="0"><mj-column><mj-image src="https://placehold.co/600x300/f8fafc/94a3b8?text=Card+Image" padding="0" border-radius="8px 8px 0 0"></mj-image><mj-text font-size="20px" font-weight="800" color="#0f172a" padding="20px 20px 5px 20px">Card Title</mj-text><mj-text font-size="15px" color="#475569" line-height="1.6" padding="0 20px 20px 20px">This is a beautiful image card. Add your descriptive paragraph here to engage your audience.</mj-text><mj-button background-color="${brandPrimaryColor}" color="#ffffff" font-weight="bold" border-radius="6px" inner-padding="12px 25px" align="left" padding="0 20px 20px 20px" href="#">Action</mj-button></mj-column></mj-section>` });
            bm.add('adv-img-cap', { category: 'Advanced', label: `<i class="fa-solid fa-photo-film"></i><div class="gjs-block-label">Img + Caption</div>`, content: `<mj-section padding="15px 0"><mj-column width="50%" padding="0 10px 0 0"><mj-image src="https://placehold.co/400x400/e2e8f0/94a3b8?text=Image" border-radius="6px" padding="0"></mj-image></mj-column><mj-column width="50%" padding="0 0 0 10px" vertical-align="middle"><mj-text font-size="18px" font-weight="800" color="#0f172a" padding="0 0 10px 0">Feature Title</mj-text><mj-text font-size="14px" color="#475569" line-height="1.6" padding="0 0 15px 0">Describe your product or feature here. Keep it concise.</mj-text><mj-button background-color="${brandPrimaryColor}" color="#ffffff" font-weight="bold" border-radius="6px" inner-padding="10px 20px" align="left" padding="0" href="#">Learn More</mj-button></mj-column></mj-section>` });
            bm.add('adv-video', { category: 'Advanced', label: `<i class="fa-solid fa-circle-play"></i><div class="gjs-block-label">Video</div>`, content: `<mj-image src="https://placehold.co/600x337/1e293b/ffffff?text=▶+PLAY+VIDEO" href="#" border-radius="8px" padding="10px 0"></mj-image>` });
            bm.add('adv-product', { category: 'Advanced', label: `<i class="fa-solid fa-store"></i><div class="gjs-block-label">Product Sync</div>`, content: `<mj-section background-color="#f8fafc" border-radius="8px" border="1px dashed #cbd5e1" padding="20px"><mj-column><mj-text align="center" font-size="24px" color="#94a3b8"><i class="fa-solid fa-shop"></i></mj-text><mj-text align="center" font-weight="bold" color="#0f172a" padding-top="10px">Dynamic Product Sync</mj-text><mj-text align="center" font-size="12px" color="#64748b" padding-top="5px">Select a WooCommerce/Shopify product from the right panel to populate this card.</mj-text></mj-column></mj-section>` });
            bm.add('adv-crm', { category: 'Advanced', label: `<i class="fa-solid fa-chart-pie"></i><div class="gjs-block-label">CRM Survey</div>`, content: `<mj-section background-color="#f8fafc" border-radius="8px" border="1px dashed #cbd5e1" padding="20px"><mj-column><mj-text align="center" font-size="24px" color="#94a3b8"><i class="fa-solid fa-chart-pie"></i></mj-text><mj-text align="center" font-weight="bold" color="#0f172a" padding-top="10px">CRM Survey Block</mj-text><mj-text align="center" font-size="12px" color="#64748b" padding-top="5px">Displays NPS / Feedback survey linked to CRM.</mj-text></mj-column></mj-section>` });

            /* 4. THE PRE-BUILT SECTIONS TAB (Templates) */
            bm.add('hdr-center', { category: 'Headers', label: `<i class="fa-solid fa-arrows-to-dot"></i><div class="gjs-block-label">Logo Center</div>`, content: `<mj-section background-color="transparent" padding="20px 0"><mj-column><mj-image src="${brandLogoUrl}" alt="Logo" width="150px" align="center"></mj-image></mj-column></mj-section>` });
            bm.add('hdr-left', { category: 'Headers', label: `<i class="fa-solid fa-arrow-left"></i><div class="gjs-block-label">Logo Left</div>`, content: `<mj-section background-color="transparent" padding="20px 0"><mj-column width="50%"><mj-image src="${brandLogoUrl}" alt="Logo" width="150px" align="left"></mj-image></mj-column><mj-column width="50%"></mj-column></mj-section>` });
            bm.add('hdr-right', { category: 'Headers', label: `<i class="fa-solid fa-arrow-right"></i><div class="gjs-block-label">Logo Right</div>`, content: `<mj-section background-color="transparent" padding="20px 0"><mj-column width="50%"></mj-column><mj-column width="50%"><mj-image src="${brandLogoUrl}" alt="Logo" width="150px" align="right"></mj-image></mj-column></mj-section>` });

            bm.add('hero-center', { category: 'Hero Banners', label: `<i class="fa-regular fa-image"></i><div class="gjs-block-label">Hero Center</div>`, content: `<mj-section background-url="https://placehold.co/600x400/1e293b/ffffff?text=Hero+Banner" background-size="cover" background-repeat="no-repeat" padding="80px 20px" border-radius="8px"><mj-column><mj-text align="center" color="#ffffff" font-size="32px" font-weight="900" padding-bottom="10px">Big Headline</mj-text><mj-text align="center" color="#f8fafc" font-size="16px" line-height="1.5" padding-bottom="20px">Catchy subheadline to drive clicks.</mj-text><mj-button align="center" background-color="${brandPrimaryColor}" color="#ffffff" border-radius="6px" inner-padding="15px 30px" font-weight="bold" href="#">Shop Now</mj-button></mj-column></mj-section>` });
            bm.add('hero-left', { category: 'Hero Banners', label: `<i class="fa-solid fa-image"></i><div class="gjs-block-label">Hero Left</div>`, content: `<mj-section background-url="https://placehold.co/600x400/1e293b/ffffff?text=Hero+Banner" background-size="cover" background-repeat="no-repeat" padding="80px 20px" border-radius="8px"><mj-column><mj-text align="left" color="#ffffff" font-size="32px" font-weight="900" padding-bottom="10px">Big Headline</mj-text><mj-text align="left" color="#f8fafc" font-size="16px" line-height="1.5" padding-bottom="20px">Catchy subheadline to drive clicks.</mj-text><mj-button align="left" background-color="${brandPrimaryColor}" color="#ffffff" border-radius="6px" inner-padding="15px 30px" font-weight="bold" href="#">Shop Now</mj-button></mj-column></mj-section>` });
            bm.add('hero-right', { category: 'Hero Banners', label: `<i class="fa-regular fa-images"></i><div class="gjs-block-label">Hero Right</div>`, content: `<mj-section background-url="https://placehold.co/600x400/1e293b/ffffff?text=Hero+Banner" background-size="cover" background-repeat="no-repeat" padding="80px 20px" border-radius="8px"><mj-column><mj-text align="right" color="#ffffff" font-size="32px" font-weight="900" padding-bottom="10px">Big Headline</mj-text><mj-text align="right" color="#f8fafc" font-size="16px" line-height="1.5" padding-bottom="20px">Catchy subheadline to drive clicks.</mj-text><mj-button align="right" background-color="${brandPrimaryColor}" color="#ffffff" border-radius="6px" inner-padding="15px 30px" font-weight="bold" href="#">Shop Now</mj-button></mj-column></mj-section>` });

            bm.add('body-promo', { category: 'Body Layouts', label: `<i class="fa-solid fa-newspaper"></i><div class="gjs-block-label">Promo Post</div>`, content: `<mj-section background-color="#ffffff" padding="0" border-radius="8px"><mj-column width="100%"><mj-image src="https://placehold.co/600x300/e2e8f0/94a3b8?text=Promo+Banner" padding="0" border-radius="8px 8px 0 0"></mj-image></mj-column><mj-column width="100%" padding="30px 20px"><mj-text font-size="24px" font-weight="800" color="#0f172a" padding-bottom="10px">Exciting Updates</mj-text><mj-text font-size="15px" color="#475569" line-height="1.6" padding-bottom="20px">Keep your audience engaged with this clean layout.</mj-text><mj-button align="left" background-color="${brandPrimaryColor}" color="#ffffff" border-radius="6px" inner-padding="12px 25px" font-weight="bold" href="#">Learn More</mj-button></mj-column></mj-section>` });
            bm.add('body-prod1', { category: 'Body Layouts', label: `<i class="fa-solid fa-box"></i><div class="gjs-block-label">1 Product</div>`, content: `<mj-section background-color="#f8fafc" padding="30px 20px" border-radius="8px"><mj-column width="100%"><mj-image src="https://placehold.co/400x300/e2e8f0/94a3b8?text=Product+Image" border-radius="6px" padding="0 0 20px 0"></mj-image><mj-text font-size="22px" font-weight="800" color="#0f172a" align="center">Premium Product</mj-text><mj-text font-size="20px" font-weight="900" color="${brandPrimaryColor}" align="center" padding="10px 0">$49.99</mj-text><mj-text font-size="14px" color="#475569" line-height="1.5" align="center" padding-bottom="20px">A brief description of this product.</mj-text><mj-button background-color="${brandPrimaryColor}" color="#ffffff" border-radius="6px" inner-padding="14px 30px" font-weight="bold" width="100%">Add to Cart</mj-button></mj-column></mj-section>` });
            bm.add('body-prod2', { category: 'Body Layouts', label: `<i class="fa-solid fa-boxes-stacked"></i><div class="gjs-block-label">2 Products</div>`, content: `<mj-section background-color="#ffffff" padding="10px 0"><mj-column width="48%" background-color="#f8fafc" padding="20px 10px" border-radius="8px"><mj-image src="https://placehold.co/300x300/e2e8f0/94a3b8?text=Item+1" border-radius="6px" padding="0 0 15px 0"></mj-image><mj-text font-size="16px" font-weight="800" color="#0f172a" align="center" padding="0">Product A</mj-text><mj-text font-size="16px" font-weight="900" color="${brandPrimaryColor}" align="center" padding="5px 0 15px 0">$29.99</mj-text><mj-button background-color="${brandPrimaryColor}" color="#ffffff" border-radius="6px" inner-padding="10px" font-weight="bold" width="100%">Buy</mj-button></mj-column><mj-column width="4%"></mj-column><mj-column width="48%" background-color="#f8fafc" padding="20px 10px" border-radius="8px"><mj-image src="https://placehold.co/300x300/e2e8f0/94a3b8?text=Item+2" border-radius="6px" padding="0 0 15px 0"></mj-image><mj-text font-size="16px" font-weight="800" color="#0f172a" align="center" padding="0">Product B</mj-text><mj-text font-size="16px" font-weight="900" color="${brandPrimaryColor}" align="center" padding="5px 0 15px 0">$34.99</mj-text><mj-button background-color="${brandPrimaryColor}" color="#ffffff" border-radius="6px" inner-padding="10px" font-weight="bold" width="100%">Buy</mj-button></mj-column></mj-section>` });

            bm.add('ftr-standard', { category: 'Footers', label: `<i class="fa-solid fa-shoe-prints"></i><div class="gjs-block-label">Full Footer</div>`, content: `<mj-section padding="30px 20px" background-color="transparent"><mj-column><mj-image src="${brandLogoUrl}" width="120px" align="center" padding-bottom="15px"></mj-image><mj-social font-size="15px" icon-size="30px" mode="horizontal" padding-bottom="20px"><mj-social-element name="facebook" href="#"></mj-social-element><mj-social-element name="instagram" href="#"></mj-social-element></mj-social><mj-text font-size="12px" color="#94a3b8" align="center" line-height="1.5"><strong>${brandName}</strong><br>123 Brand Street, City, State 12345<br>contact@domain.com | (555) 123-4567<br><br>You received this because you opted in.</mj-text><mj-text font-size="12px" align="center" padding-top="5px"><a href="[Unsubscribe_Link]" style="color:#64748b; text-decoration:underline;">Unsubscribe Safely</a></mj-text></mj-column></mj-section>` });

            setTimeout(() => { 
                const cats = document.querySelectorAll('.gjs-block-category');
                const prebuiltContainer = document.getElementById('mt-prebuilt-container');
                cats.forEach(cat => {
                    const titleEl = cat.querySelector('.gjs-title');
                    if(titleEl) {
                        const title = titleEl.innerText.trim().toUpperCase();
                        if(['HEADERS', 'HERO BANNERS', 'BODY LAYOUTS', 'FOOTERS'].includes(title)) {
                            prebuiltContainer.appendChild(cat);
                        }
                    }
                });
                document.getElementById('builder_loader').classList.add('hidden'); 
            }, 800);
            
            const editorBody = document.querySelector('.gjs-editor');
            if(editorBody) editorBody.style.backgroundColor = 'transparent';
        });

        editor.on('component:selected', component => { 
            document.getElementById('panel-edit-menu').classList.remove('hidden');
            document.getElementById('panel-edit-menu').classList.add('flex');
            closeColorDrawer();
            const traits = component.get('traits');
            if(traits) component.set('traits', traits.filter(t => t.get('name') !== 'title' && t.get('name') !== 'id'));
        });
    }

    function saveBuilder() {
        const btn = document.getElementById('btn_save_builder');
        const id = document.getElementById('builder_tpl_id').value;
        const name = document.getElementById('builder_tpl_name').value.trim();
        
        if(!name) { showToast('Please enter a Design Name in the top bar before saving.', 'error'); return; }
        
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Saving...'; 
        btn.disabled = true;
        
        let htmlData = '';
        let mjmlData = '';
        
        try {
            const compiled = editor.runCommand('mjml-get-code');
            if (compiled && compiled.html) {
                htmlData = compiled.html;
                mjmlData = compiled.mjml || '';
            } else {
                throw new Error("MJML command returned empty data");
            }
        } catch (error) {
            console.warn("MJML Export Warning. Falling back to standard HTML render:", error);
            htmlData = editor.getHtml(); 
            mjmlData = editor.getHtml();
        }

        const fd = new FormData();
        fd.append('action', 'mt_save_template'); 
        fd.append('security', typeof mt_nonce !== 'undefined' ? mt_nonce : ''); 
        fd.append('template_id', id); 
        fd.append('template_name', name);
        
        const payload = JSON.stringify({ html: htmlData, mjml: mjmlData });
        const safePayload = btoa(unescape(encodeURIComponent(payload))); 
        fd.append('email_body', safePayload);
        
        const ajaxUrl = typeof mt_ajax_url !== 'undefined' ? mt_ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
        
        fetch(ajaxUrl, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            btn.disabled = false; 
            showToast("Design saved successfully!");
            if(res.success && id == 0) {
                document.getElementById('builder_tpl_id').value = res.data.id;
            }
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save';
        }).catch(err => {
            console.error("AJAX Error:", err);
            btn.disabled = false; 
            showToast("Error communicating with server. Check console.", "error");
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save';
        });
    }
</script>