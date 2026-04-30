<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_templates = $wpdb->prefix . 'mt_email_templates';
$active_templates  = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table_templates WHERE brand_id = %d AND status = 'active'  ORDER BY created_at DESC", $brand->id) );
$trashed_templates = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table_templates WHERE brand_id = %d AND status = 'trashed' ORDER BY deleted_at DESC", $brand->id) );

$brand_config   = json_decode($brand->brand_config, true) ?: [];
$brand_logo     = !empty($brand_config['logos']['main']) ? $brand_config['logos']['main'] : 'https://placehold.co/400x150/e2e8f0/0f172a?text=Your+Logo';
$brand_color    = !empty($brand->primary_color) ? $brand->primary_color : '#0f172a';
$brand_name     = esc_html($brand->brand_name);
$mt_palette     = get_option('mt_brand_palette', ['accent' => '#FCC753', 'dark' => '#1A232E']);

$raw_slug       = sanitize_title($brand->brand_name);
$clean_slug     = str_replace('-', '', $raw_slug) ?: 'hello';
$system_email   = $clean_slug . '@fly.mailtoucan.com';
$sender_email   = !empty($brand_config['delivery']['from_email']) ? $brand_config['delivery']['from_email'] : $system_email;

// BuilderJS asset URLs
$bjs_css = MT_URL . 'assets/builderjs/dist/builder.css';
$bjs_js  = MT_URL . 'assets/builderjs/dist/builder.js';
$bjs_tmc = MT_URL . 'assets/builderjs/tinymce/tinymce.min.js';
$bjs_theme_url = MT_URL . 'assets/builderjs/themes/default';

// Vault images for the image panel
$vault_assets = [];
if (!empty($brand_config['logos']['main']))   $vault_assets[] = $brand_config['logos']['main'];
if (!empty($brand_config['logos']['footer'])) $vault_assets[] = $brand_config['logos']['footer'];
if (!empty($brand_config['vault']) && is_array($brand_config['vault'])) {
    foreach ($brand_config['vault'] as $media) {
        if ($media['type'] === 'image') $vault_assets[] = $media['url'];
    }
}

// Default blank BuilderJS template JSON — clean empty canvas
$default_bjs_json = json_encode([
    "theme"      => "default",
    "name"       => "PageElement",
    "template"   => "Page",
    "page_title" => null,
    "blocks"     => []
]);

?>

<!-- ═══════════════════════════════════════════════════════════════
     BUILDERJS REQUIRED DEPENDENCIES
════════════════════════════════════════════════════════════════ -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<script src="<?php echo esc_url($bjs_tmc); ?>" referrerpolicy="origin"></script>
<link href="<?php echo esc_url($bjs_css); ?>" rel="stylesheet">
<script src="<?php echo esc_url($bjs_js); ?>"></script>

<style>
    :root {
        --mt-brand: <?php echo esc_html($brand_color); ?>;
        --mt-accent: <?php echo esc_html($mt_palette['accent']); ?>;
    }

    /* ── Template list page ── */
    .studio-tab-btn { transition: all 0.2s; cursor: pointer; border-bottom: 2px solid transparent; }
    .studio-tab-btn.active { border-bottom-color: var(--mt-accent); color: #0f172a; font-weight: 800; }
    .studio-tab-content { display: none; }
    .studio-tab-content.active { display: block; }

    /* ── Full-screen builder overlay ── */
    #view_builder { font-family: 'Inter', sans-serif; }
    #bjs-widgets-panel { width: 300px; background: #ffffff; border-right: 1px solid #e2e8f0; overflow-y: auto; flex-shrink: 0; display: flex; flex-direction: column; }
    #bjs-settings-panel { width: 300px; background: #ffffff; border-left: 1px solid #e2e8f0; overflow-y: auto; flex-shrink: 0; display: flex; flex-direction: column; }
    #bjs-canvas-wrapper { flex: 1; background: #e9ecef; overflow: auto; display: flex; justify-content: center; padding: 24px; }
    #bjsBuilder { width: 100%; max-width: 680px; }

    /* Panel section headers */
    .bjs-panel-header { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; background: #fafafa; }

    /* Images panel */
    #bjs-images-container .img-thumb { width: 100%; aspect-ratio: 4/3; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; cursor: pointer; transition: all 0.2s; }
    #bjs-images-container .img-thumb:hover { border-color: var(--mt-brand); transform: scale(1.02); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }

    /* Device toggle */
    .device-btn { transition: all 0.2s; }
    .device-btn.active { background: #ffffff !important; box-shadow: 0 1px 3px rgba(0,0,0,0.1); color: #111827 !important; }

    /* Shortcode drawer */
    #shortcode_drawer { transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); transform: translateY(100%); }
    #shortcode_drawer.open { transform: translateY(0); }
    .sc-chip { cursor: pointer; transition: all 0.2s; border: 1px solid #e2e8f0; background: #fff; }
    .sc-chip:hover { border-color: var(--mt-brand); background: #f8fafc; transform: translateY(-1px); }

    /* ── AI Panel ── */
    #mt_ai_panel { position: absolute; top: 0; right: 0; bottom: 0; width: 300px; background: #ffffff; border-left: 1px solid #e2e8f0; z-index: 50; display: flex; flex-direction: column; transform: translateX(100%); transition: transform 0.3s cubic-bezier(0.4,0,0.2,1); box-shadow: -10px 0 40px rgba(0,0,0,0.06); }
    #mt_ai_panel.open { transform: translateX(0); }
    .ai-provider-btn { flex: 1; padding: 8px 4px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; background: #f8fafc; cursor: pointer; transition: all 0.2s; text-align: center; }
    .ai-provider-btn:hover { border-color: #94a3b8; color: #0f172a; }
    .ai-provider-btn.active.openai  { background: #10a37f; color: #fff; border-color: #10a37f; }
    .ai-provider-btn.active.claude  { background: #c57540; color: #fff; border-color: #c57540; }
    .ai-provider-btn.active.gemini  { background: #4285f4; color: #fff; border-color: #4285f4; }
    .ai-output-area { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; min-height: 120px; font-size: 13px; color: #334155; line-height: 1.6; white-space: pre-wrap; word-break: break-word; }

    /* Toast */
    #mt_toast_container { position: fixed; bottom: 2rem; right: 2rem; z-index: 9999; display: flex; flex-direction: column; align-items: flex-end; pointer-events: none; }
</style>

<!-- ═══════════════════════════════════════════════════════════════
     TEMPLATE LIST VIEW
════════════════════════════════════════════════════════════════ -->
<div id="view_list">
    <div class="flex justify-between items-end mb-5 flex-wrap gap-3">
        <div>
            <div class="text-2xl font-black text-gray-900 flex items-center gap-2"><i class="fa-solid fa-pen-nib" style="color:var(--mt-brand);"></i> Toucan Studio</div>
            <div class="text-sm text-gray-500 mt-1">Design and manage beautiful email templates.</div>
        </div>
        <button onclick="openBuilder(0)" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white flex items-center gap-2 transition hover:brightness-110" style="background:var(--mt-brand);">
            <i class="fa-solid fa-plus"></i> New Design
        </button>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-20">
        <div class="flex border-b border-gray-100 bg-gray-50/50 px-6 pt-3 gap-8 items-center">
            <button class="studio-tab-btn active px-2 py-4 text-xs font-bold uppercase tracking-widest text-gray-400" onclick="switchStudioTab('saved', this)">Saved Designs</button>
            <button class="studio-tab-btn px-2 py-4 text-xs font-bold uppercase tracking-widest text-gray-400" onclick="switchStudioTab('gallery', this)">The Gallery</button>
            <button class="studio-tab-btn px-2 py-4 text-xs font-bold uppercase tracking-widest text-gray-400" onclick="switchStudioTab('trash', this)">Trash</button>
        </div>

        <div class="p-8 min-h-[500px]">
            <!-- Saved Designs -->
            <div id="tab_saved" class="studio-tab-content active">
                <?php if (empty($active_templates)): ?>
                    <div class="text-center py-20">
                        <i class="fa-regular fa-folder-open text-5xl text-gray-200 mb-4 block"></i>
                        <h3 class="text-xl font-bold text-gray-700">Your studio is empty</h3>
                        <p class="text-sm text-gray-500 mb-4">Click "New Design" to get started.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-3 gap-8">
                        <?php foreach ($active_templates as $tpl): ?>
                        <div class="group relative bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-xl transition-all p-2 flex flex-col">
                            <div class="aspect-[4/3] bg-gray-50 rounded-xl mb-4 flex items-center justify-center border border-gray-100 overflow-hidden">
                                <i class="fa-regular fa-envelope text-4xl text-gray-200"></i>
                            </div>
                            <div class="px-3 pb-3 flex flex-col flex-1">
                                <h3 class="font-bold text-gray-900 truncate">
                                    <?php echo esc_html($tpl->template_name); ?>
                                    <?php if (strpos($tpl->template_name, 'Draft') !== false) echo '<span class="ml-2 px-2 py-0.5 bg-yellow-100 text-yellow-700 text-[10px] rounded font-bold">DRAFT</span>'; ?>
                                </h3>
                                <div class="flex justify-between items-center mt-4 border-t border-gray-100 pt-4">
                                    <button onclick="trashTemplate(<?php echo $tpl->id; ?>)" class="text-gray-300 hover:text-red-500 transition"><i class="fa-solid fa-trash-can"></i></button>
                                    <button onclick="openBuilder(<?php echo $tpl->id; ?>, '<?php echo esc_js($tpl->template_name); ?>')" class="bg-gray-900 text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-black transition">Open Designer</button>
                                </div>
                                <textarea id="raw_body_<?php echo $tpl->id; ?>" style="display:none;"><?php echo esc_textarea($tpl->email_body); ?></textarea>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Gallery -->
            <div id="tab_gallery" class="studio-tab-content">
                <div class="grid grid-cols-4 gap-6">
                    <?php
                    $prebuilts = [
                        ['name' => 'Welcome Email',       'icon' => 'fa-hand-wave'],
                        ['name' => 'Special Offer',       'icon' => 'fa-tag'],
                        ['name' => 'Review Request',      'icon' => 'fa-star'],
                        ['name' => 'Loyalty Reward',      'icon' => 'fa-gift'],
                    ];
                    foreach ($prebuilts as $pb): ?>
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm hover:shadow-md hover:border-gray-900 transition cursor-pointer flex flex-col" onclick="openBuilder(0, '<?php echo esc_js($pb['name']); ?>')">
                        <div class="h-32 bg-gray-50 flex items-center justify-center shrink-0 border-b border-gray-100">
                            <i class="fa-solid <?php echo $pb['icon']; ?> text-4xl text-gray-300"></i>
                        </div>
                        <div class="p-4 text-center flex-1 flex flex-col justify-center">
                            <h3 class="font-bold text-gray-900 text-sm"><?php echo $pb['name']; ?></h3>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Trash -->
            <div id="tab_trash" class="studio-tab-content">
                <?php if (empty($trashed_templates)): ?>
                    <div class="text-center py-16"><p class="text-gray-500">Trash is empty.</p></div>
                <?php else: ?>
                    <button onclick="emptyTrash()" class="mb-4 text-xs font-bold text-red-600 bg-red-50 px-4 py-2 rounded-lg hover:bg-red-100 transition">Empty Trash Now</button>
                    <div class="bg-white border rounded-xl overflow-hidden">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 border-b text-[10px] uppercase text-gray-500 font-bold">
                                <tr><th class="p-4 pl-6">Template Name</th><th class="p-4">Deleted On</th><th class="p-4 text-right pr-6">Action</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trashed_templates as $tpl): ?>
                                <tr class="border-b hover:bg-gray-50 transition">
                                    <td class="p-4 pl-6 font-bold text-gray-700 line-through"><?php echo esc_html($tpl->template_name); ?></td>
                                    <td class="p-4 text-gray-500"><?php echo esc_html(date('M d, Y', strtotime($tpl->deleted_at))); ?></td>
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

<!-- ═══════════════════════════════════════════════════════════════
     FULL-SCREEN BUILDER OVERLAY
════════════════════════════════════════════════════════════════ -->
<div id="view_builder" class="fixed inset-0 z-[100] hidden flex-col bg-gray-100">

    <!-- Top toolbar -->
    <div class="h-14 bg-white border-b border-gray-200 px-4 flex justify-between items-center shrink-0 z-30 shadow-sm">
        <div class="flex items-center gap-3">
            <button onclick="closeBuilder()" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-500 transition" title="Exit Builder"><i class="fa-solid fa-arrow-left text-sm"></i></button>
            <div class="h-5 w-px bg-gray-200"></div>
            <input type="text" id="builder_tpl_name" class="border-none bg-transparent outline-none text-base font-black text-gray-900 placeholder-gray-400 w-56" placeholder="Design Name...">
        </div>

        <div class="flex items-center gap-2 bg-gray-50 border border-gray-200 p-1 rounded-lg">
            <button onclick="setDevice('desktop')" id="device-desktop" class="device-btn active w-8 h-8 rounded flex items-center justify-center text-gray-500 transition text-xs"><i class="fa-solid fa-desktop"></i></button>
            <button onclick="setDevice('mobile')"  id="device-mobile"  class="device-btn w-8 h-8 rounded flex items-center justify-center text-gray-500 transition text-xs hover:bg-gray-200"><i class="fa-solid fa-mobile-alt"></i></button>
        </div>

        <div class="flex items-center gap-2">
            <button onclick="toggleAiPanel()" id="mt_ai_btn_toggle" class="bg-white border border-gray-200 text-gray-700 px-3 py-2 rounded-lg text-sm font-bold hover:bg-gray-50 transition flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-wand-magic-sparkles" style="color:#c57540;"></i> Toucan AI
            </button>
            <button onclick="openTestModal()" class="bg-white border border-gray-200 text-gray-700 px-3 py-2 rounded-lg text-sm font-bold hover:bg-gray-50 transition shadow-sm"><i class="fa-regular fa-paper-plane mr-1"></i> Test</button>
            <button onclick="saveAsTemplate()" class="bg-indigo-50 border border-indigo-200 text-indigo-700 px-3 py-2 rounded-lg text-sm font-bold hover:bg-indigo-100 transition shadow-sm"><i class="fa-regular fa-copy mr-1"></i> Duplicate</button>
            <button onclick="saveBuilder()" id="btn_save_builder" class="bg-gray-900 text-white px-5 py-2 rounded-lg text-sm font-bold hover:bg-black transition shadow-lg flex items-center gap-2"><i class="fa-solid fa-floppy-disk"></i> Save</button>
        </div>
    </div>

    <input type="hidden" id="builder_tpl_id" value="0">

    <!-- Three-column layout -->
    <div class="flex flex-1 overflow-hidden relative">

        <!-- Loading overlay -->
        <div id="builder_loader" class="absolute inset-0 bg-white z-50 flex flex-col items-center justify-center">
            <i class="fa-solid fa-circle-notch fa-spin text-4xl text-indigo-500 mb-3"></i>
            <p class="text-gray-500 font-bold text-sm uppercase tracking-widest">Loading Studio...</p>
        </div>

        <!-- LEFT: Widget panel (BuilderJS injects here) -->
        <div id="bjs-widgets-panel">
            <div class="bjs-panel-header">Blocks</div>
            <div id="bjsWidgets" class="flex-1 overflow-y-auto"></div>

            <!-- Brand Images section -->
            <?php if (!empty($vault_assets)): ?>
            <div class="border-t border-gray-100">
                <div class="bjs-panel-header">Brand Images — Click to Copy URL</div>
                <div id="bjs-images-container" class="grid grid-cols-2 gap-2 p-3">
                    <?php foreach ($vault_assets as $img): ?>
                    <img src="<?php echo esc_url($img); ?>" class="img-thumb" onclick="copyImageUrl('<?php echo esc_js($img); ?>')" title="Click to copy URL">
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Shortcodes -->
            <div class="border-t border-gray-100">
                <div class="bjs-panel-header">Personalization Tags — Click to Copy</div>
                <div class="p-3 flex flex-wrap gap-1.5">
                    <?php
                    $tags = ['[Guest_First_Name]','[Guest_Full_Name]','[Guest_Email]','[Guest_Phone]','[Guest_Birthday]','[Brand_Name]','[Location_Name]','[Visit_Date]','[Unsubscribe_Link]'];
                    foreach ($tags as $tag):
                        $color = $tag === '[Unsubscribe_Link]' ? 'text-red-600 border-red-100 bg-red-50' : (in_array($tag, ['[Brand_Name]','[Location_Name]','[Visit_Date]']) ? 'text-indigo-600 border-indigo-100 bg-indigo-50' : 'text-gray-700 border-gray-200 bg-white');
                    ?>
                    <div onclick="copyTag('<?php echo esc_js($tag); ?>')" class="sc-chip px-2 py-1 rounded-lg font-bold text-[10px] <?php echo $color; ?>"><?php echo esc_html($tag); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- CENTER: BuilderJS canvas -->
        <div id="bjs-canvas-wrapper">
            <div id="bjsBuilder"></div>
        </div>

        <!-- RIGHT: Settings panel (BuilderJS injects here + AI panel overlays) -->
        <div id="bjs-settings-panel" style="position:relative;">
            <div class="bjs-panel-header">Element Settings</div>
            <div id="bjsSettings" class="flex-1 overflow-y-auto p-2"></div>

            <!-- ── TOUCAN AI PANEL (slides over the settings panel) ── -->
            <div id="mt_ai_panel">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-wand-magic-sparkles" style="color:#c57540;font-size:14px;"></i>
                        <span class="text-sm font-black text-gray-900">Toucan AI</span>
                    </div>
                    <button onclick="toggleAiPanel()" class="w-6 h-6 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 transition"><i class="fa-solid fa-xmark text-xs"></i></button>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    <!-- Provider -->
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">Provider</label>
                        <div class="flex gap-1.5">
                            <button onclick="setAiProvider('openai', this)" class="ai-provider-btn openai active" id="ai_btn_openai">OpenAI</button>
                            <button onclick="setAiProvider('claude', this)" class="ai-provider-btn claude"        id="ai_btn_claude">Claude</button>
                            <button onclick="setAiProvider('gemini', this)" class="ai-provider-btn gemini"        id="ai_btn_gemini">Gemini</button>
                        </div>
                    </div>
                    <!-- Type -->
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">Generate</label>
                        <select id="ai_context" onchange="toggleTemplateBuilder(this.value)" class="w-full border border-gray-200 rounded-lg text-sm font-bold p-2 outline-none bg-white text-gray-700">
                            <option value="email_copy">Email Body Copy</option>
                            <option value="subject_line">Subject Line</option>
                            <option value="preheader">Preheader Text</option>
                            <option value="build_template">✨ Build Full Template</option>
                        </select>
                    </div>

                    <!-- Template Builder Options (shown only for build_template) -->
                    <div id="ai_template_builder_opts" class="hidden">
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">Base Template Style</label>
                        <select id="ai_base_template" class="w-full border border-gray-200 rounded-lg text-sm font-bold p-2 outline-none bg-white text-gray-700 mb-3">
                            <option value="starter">Brand Starter (General)</option>
                            <option value="birthday">Birthday Celebration</option>
                            <option value="anniversary">Visit Anniversary</option>
                            <option value="winback">Win-Back Email</option>
                            <option value="promo">Promo Blast</option>
                        </select>
                        <p class="text-[10px] text-amber-600 bg-amber-50 rounded-lg p-2 border border-amber-100 mb-1">
                            <i class="fa-solid fa-wand-magic-sparkles mr-1"></i>
                            AI will generate a full branded HTML email. The result will be saved as a new template you can open in the builder.
                        </p>
                    </div>
                    <!-- Tone -->
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">Tone</label>
                        <div class="grid grid-cols-3 gap-1" id="ai_tone_group">
                            <button onclick="setAiTone(this)" data-tone="Professional" class="ai-tone-btn text-[10px] font-bold py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:border-gray-400 transition active-tone">Professional</button>
                            <button onclick="setAiTone(this)" data-tone="Friendly"     class="ai-tone-btn text-[10px] font-bold py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:border-gray-400 transition">Friendly</button>
                            <button onclick="setAiTone(this)" data-tone="Promotional"  class="ai-tone-btn text-[10px] font-bold py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:border-gray-400 transition">Promo</button>
                            <button onclick="setAiTone(this)" data-tone="Witty"        class="ai-tone-btn text-[10px] font-bold py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:border-gray-400 transition">Witty</button>
                            <button onclick="setAiTone(this)" data-tone="Urgent"       class="ai-tone-btn text-[10px] font-bold py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:border-gray-400 transition">Urgent</button>
                            <button onclick="setAiTone(this)" data-tone="Luxury"       class="ai-tone-btn text-[10px] font-bold py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:border-gray-400 transition">Luxury</button>
                        </div>
                    </div>
                    <!-- Prompt -->
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">Your Prompt</label>
                        <textarea id="ai_prompt" rows="4" placeholder="e.g. Write a welcome email for a coffee shop offering a free drink on first visit." class="w-full border border-gray-200 rounded-lg text-sm p-2.5 outline-none focus:border-gray-400 resize-none bg-white text-gray-700 leading-relaxed"></textarea>
                        <p class="text-[10px] text-gray-400 mt-1">Brand "<?php echo esc_html($brand_name); ?>" & tone sent automatically.</p>
                    </div>
                    <!-- Generate -->
                    <button onclick="runAiGenerate()" id="ai_generate_btn" class="w-full py-2.5 rounded-xl font-bold text-sm text-white flex items-center justify-center gap-2 transition" style="background:#c57540;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Generate
                    </button>
                    <!-- Output -->
                    <div id="ai_output_wrap" class="hidden">
                        <div class="flex justify-between items-center mb-2">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Result</label>
                            <button id="ai_copy_btn" onclick="copyAiResult()" class="text-[10px] font-bold text-gray-500 hover:text-gray-900 flex items-center gap-1"><i class="fa-regular fa-copy"></i> Copy & Paste into Builder</button>
                        </div>
                        <div id="ai_output" class="ai-output-area"></div>
                        <!-- Full template action: open in builder -->
                        <button id="ai_load_template_btn" onclick="loadAiTemplate()" class="hidden mt-2 w-full py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition flex items-center justify-center gap-2">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Open This Template in Builder
                        </button>
                        <button onclick="runAiGenerate()" class="mt-2 w-full py-1.5 border border-gray-200 rounded-lg text-[11px] font-bold text-gray-600 hover:bg-gray-50 transition">↺ Regenerate</button>
                    </div>
                    <div id="ai_error_wrap" class="hidden">
                        <div class="bg-red-50 border border-red-200 rounded-xl p-3 text-sm text-red-700 font-medium" id="ai_error_msg"></div>
                    </div>
                </div>

                <div class="px-4 py-3 border-t border-gray-100 shrink-0">
                    <p id="ai_studio_credits" class="text-[10px] text-gray-400 text-center leading-relaxed mb-1"></p>
                    <p class="text-[10px] text-gray-400 text-center leading-relaxed">Keys set in <strong>Super Admin → AI Settings</strong></p>
                </div>
            </div>
            <!-- /AI Panel -->
        </div>
        <!-- /Settings panel -->

    </div>
    <!-- /Three-column layout -->

</div>
<!-- /Builder overlay -->

<!-- ═══════════════════════════════════════════════════════════════
     MODALS
════════════════════════════════════════════════════════════════ -->
<div id="test_email_modal" class="fixed inset-0 bg-gray-900/60 z-[200] hidden items-center justify-center backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex justify-between items-center">
            <h2 class="text-lg font-black text-gray-900">Send Test Email</h2>
            <button onclick="closeTestModal()" class="text-gray-400 hover:text-gray-900"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <div class="p-6 space-y-5 bg-gray-50">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">From Sender</label>
                <input type="text" value="<?php echo esc_attr($sender_email); ?>" class="w-full p-3 bg-gray-100 border border-gray-200 rounded-lg text-sm font-bold text-gray-500 cursor-not-allowed" disabled>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Test Subject</label>
                <input type="text" id="test_subject" class="w-full p-3 bg-white border border-gray-300 rounded-lg text-sm font-bold focus:border-indigo-500 outline-none" placeholder="e.g. Test: Welcome Email">
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Send To</label>
                <input type="email" id="test_recipient" class="w-full p-3 bg-white border border-gray-300 rounded-lg text-sm font-bold focus:border-indigo-500 outline-none" placeholder="you@domain.com">
            </div>
            <button onclick="sendTestEmail()" class="w-full bg-gray-900 text-white font-bold py-3.5 rounded-xl hover:bg-black transition shadow-lg">Send Now</button>
        </div>
    </div>
</div>

<div id="mt_confirm_modal" class="fixed inset-0 bg-gray-900/60 z-[300] hidden items-center justify-center backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden transform scale-95 transition-transform duration-200" id="mt_confirm_box">
        <div class="p-6 space-y-4 text-center mt-2">
            <div class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto"><i class="fa-solid fa-triangle-exclamation text-3xl"></i></div>
            <h2 id="confirm_title" class="text-xl font-black text-gray-900">Confirm</h2>
            <p id="confirm_msg" class="text-sm text-gray-500 font-medium"></p>
        </div>
        <div class="p-4 bg-gray-50 flex gap-3 border-t border-gray-100">
            <button onclick="closeConfirmModal()" class="flex-1 bg-white border border-gray-200 text-gray-700 py-2.5 rounded-xl font-bold hover:bg-gray-50 transition">Cancel</button>
            <button onclick="executeConfirm()"    class="flex-1 bg-red-600 text-white py-2.5 rounded-xl font-bold hover:bg-red-700 transition shadow">Confirm</button>
        </div>
    </div>
</div>

<div id="mt_prompt_modal" class="fixed inset-0 bg-gray-900/60 z-[300] hidden items-center justify-center backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform scale-95 transition-transform duration-200" id="mt_prompt_box">
        <div class="p-5 border-b border-gray-100 flex justify-between items-center">
            <h2 id="prompt_title" class="text-lg font-black text-gray-900">Input Needed</h2>
            <button onclick="closePromptModal()" class="text-gray-400 hover:text-gray-900"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <div class="p-6 space-y-4 bg-gray-50">
            <p id="prompt_msg" class="text-[11px] uppercase tracking-widest font-bold text-gray-500"></p>
            <input type="text" id="prompt_input" class="w-full p-3 bg-white border border-gray-300 rounded-lg text-sm font-bold focus:border-indigo-500 outline-none shadow-sm">
            <button onclick="executePrompt()" class="w-full bg-indigo-600 text-white font-bold py-3.5 rounded-xl hover:bg-indigo-700 transition shadow-lg">Confirm</button>
        </div>
    </div>
</div>

<div id="mt_toast_container"></div>

<!-- ═══════════════════════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════════════════════════ -->
<script>
const mt_ajax_url_studio = "<?php echo admin_url('admin-ajax.php'); ?>";
const mt_nonce_studio    = "<?php echo wp_create_nonce('mt_app_nonce'); ?>";
const bjsThemeUrl        = "<?php echo esc_js($bjs_theme_url); ?>";
const defaultBjsJson     = <?php echo $default_bjs_json; ?>;
const brandPrimaryColor  = '<?php echo esc_js($brand_color); ?>';
const brandName          = '<?php echo esc_js($brand_name); ?>';

let bjsBuilder          = null;
let hasUnsavedChanges   = false;

// ── Utilities ──────────────────────────────────────────────────
function showToast(message, type = 'success') {
    const container = document.getElementById('mt_toast_container');
    const toast     = document.createElement('div');
    const bgColor   = type === 'error' ? 'bg-red-600' : 'bg-gray-900';
    const icon      = type === 'error' ? 'fa-triangle-exclamation' : 'fa-check-circle';
    toast.className = `flex items-center gap-3 px-5 py-3.5 rounded-xl shadow-xl text-white text-sm font-bold transform transition-all duration-300 translate-y-10 opacity-0 ${bgColor} mb-3 pointer-events-auto`;
    toast.innerHTML = `<i class="fa-solid ${icon} text-lg"></i> ${message}`;
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.remove('translate-y-10', 'opacity-0'));
    setTimeout(() => { toast.classList.add('translate-y-10', 'opacity-0'); setTimeout(() => toast.remove(), 300); }, 3200);
}

let confirmCallback = null;
function mtConfirm(title, msg, callback) {
    document.getElementById('confirm_title').innerText = title;
    document.getElementById('confirm_msg').innerText   = msg;
    confirmCallback = callback;
    document.getElementById('mt_confirm_modal').classList.remove('hidden');
    document.getElementById('mt_confirm_modal').classList.add('flex');
    setTimeout(() => document.getElementById('mt_confirm_box').classList.remove('scale-95'), 10);
}
function closeConfirmModal() {
    document.getElementById('mt_confirm_box').classList.add('scale-95');
    setTimeout(() => { document.getElementById('mt_confirm_modal').classList.add('hidden'); document.getElementById('mt_confirm_modal').classList.remove('flex'); }, 150);
}
function executeConfirm() { if (confirmCallback) confirmCallback(); closeConfirmModal(); }

let promptCallback = null;
function mtPrompt(title, msg, defValue, callback) {
    document.getElementById('prompt_title').innerText = title;
    document.getElementById('prompt_msg').innerText   = msg;
    document.getElementById('prompt_input').value     = defValue || '';
    promptCallback = callback;
    document.getElementById('mt_prompt_modal').classList.remove('hidden');
    document.getElementById('mt_prompt_modal').classList.add('flex');
    setTimeout(() => { document.getElementById('mt_prompt_box').classList.remove('scale-95'); document.getElementById('prompt_input').focus(); }, 10);
}
function closePromptModal() {
    document.getElementById('mt_prompt_box').classList.add('scale-95');
    setTimeout(() => { document.getElementById('mt_prompt_modal').classList.add('hidden'); document.getElementById('mt_prompt_modal').classList.remove('flex'); }, 150);
}
function executePrompt() { if (promptCallback) promptCallback(document.getElementById('prompt_input').value); closePromptModal(); }

function switchStudioTab(tab, el) {
    document.querySelectorAll('.studio-tab-btn').forEach(b => b.classList.remove('active')); el.classList.add('active');
    document.querySelectorAll('.studio-tab-content').forEach(c => c.classList.remove('active')); document.getElementById('tab_' + tab).classList.add('active');
}

function copyTag(tag) { navigator.clipboard.writeText(tag).then(() => showToast('Copied ' + tag)); }
function copyImageUrl(url) { navigator.clipboard.writeText(url).then(() => showToast('Image URL copied — paste it into an Image block.')); }

// ── Builder open/close ─────────────────────────────────────────
function openBuilder(id, name = '') {
    document.getElementById('builder_tpl_id').value    = id;
    document.getElementById('builder_tpl_name').value  = name;
    document.getElementById('view_list').style.display = 'none';
    document.querySelector('.sidebar').style.display   = 'none';
    document.getElementById('view_builder').classList.remove('hidden');
    document.getElementById('view_builder').classList.add('flex');
    document.getElementById('builder_loader').classList.remove('hidden');

    // Parse saved JSON or use blank default
    let startingJson = defaultBjsJson;
    if (id !== 0) {
        const rawBody = document.getElementById('raw_body_' + id)?.value.trim() || '';
        if (rawBody) {
            try {
                let decoded = rawBody;
                // Try base64-encoded outer wrapper
                if (!rawBody.startsWith('{') && !rawBody.startsWith('[')) {
                    decoded = decodeURIComponent(atob(rawBody).split('').map(c => '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2)).join(''));
                }
                const parsed = JSON.parse(decoded);
                // Support both old { html, mjml } format and new { html, json } format
                startingJson = parsed.json || parsed.mjml ? JSON.parse(parsed.json || defaultBjsJson) : parsed;
            } catch(e) {
                console.warn('Could not parse template data, using default.', e);
                startingJson = defaultBjsJson;
            }
        }
    }

    initBuilderJS(id, startingJson);
}

function closeBuilder() {
    if (hasUnsavedChanges) {
        mtConfirm("Discard Changes?", "You have unsaved changes. Leave anyway?", () => window.location.reload());
    } else {
        window.location.reload();
    }
}

// ── BuilderJS init ─────────────────────────────────────────────
function initBuilderJS(templateId, jsonData) {
    if (!bjsBuilder) {
        // First time — create the Builder instance
        bjsBuilder = new Builder({
            mainContainer:     '#bjsBuilder',
            settingsContainer: '#bjsSettings',
            widgetsContainer:  '#bjsWidgets',
            saveUrl:           mt_ajax_url_studio, // Not used directly
            themeUrl:          bjsThemeUrl,
        });

        // BuilderJS calls sidebarTabManager.openTab() internally when elements
        // are selected. Since our layout shows widgets + settings simultaneously
        // (not in tabs), we use a no-op stub to prevent JS errors.
        window.sidebarTabManager = {
            addTab:  function() {},
            openTab: function() {}
        };
    }

    bjsBuilder.init(jsonData, function () {
        document.getElementById('builder_loader').classList.add('hidden');
        hasUnsavedChanges = false;
        // Auto-save blank designs as a draft
        if (templateId === 0) setTimeout(silentDraftSave, 2000);
        // Track changes
        if (typeof bjsBuilder.onChange === 'function') {
            bjsBuilder.onChange(() => { hasUnsavedChanges = true; });
        }
    });
}

// ── Device toggle (visual only — BuilderJS handles canvas width) ──
function setDevice(device) {
    document.querySelectorAll('.device-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('device-' + device).classList.add('active');
    const canvas = document.getElementById('bjsBuilder');
    if (canvas) {
        canvas.style.maxWidth  = device === 'mobile' ? '375px' : '680px';
        canvas.style.margin    = 'auto';
    }
}

// ── Save / auto-save ───────────────────────────────────────────
function getBuilderData() {
    if (!bjsBuilder) throw new Error('Builder not initialized.');
    return {
        html: bjsBuilder.getHtml(),
        json: JSON.stringify(bjsBuilder.getJson()),
    };
}

function encodePayload(obj) {
    try {
        return btoa(unescape(encodeURIComponent(JSON.stringify(obj))));
    } catch(e) { return btoa(JSON.stringify(obj)); }
}

function silentDraftSave() {
    if (!bjsBuilder) return;
    const now      = new Date();
    const draftName = 'Draft - ' + now.toLocaleDateString() + ' ' + now.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    let data;
    try { data = getBuilderData(); } catch(e) { return; }

    const fd = new FormData();
    fd.append('action',        'mt_save_template');
    fd.append('security',      mt_nonce_studio);
    fd.append('template_id',   0);
    fd.append('template_name', draftName);
    fd.append('email_body',    encodePayload({ html: data.html, json: data.json }));

    fetch(mt_ajax_url_studio, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                document.getElementById('builder_tpl_id').value    = res.data.id;
                document.getElementById('builder_tpl_name').value  = draftName;
            }
        }).catch(err => console.error('Silent draft save failed:', err));
}

function saveBuilder(opts = {}) {
    const btn  = document.getElementById('btn_save_builder');
    const id   = document.getElementById('builder_tpl_id').value;
    const name = document.getElementById('builder_tpl_name').value.trim();

    if (!name) { showToast('Enter a design name in the toolbar before saving.', 'error'); return; }
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Saving...';
    btn.disabled  = true;

    let data;
    try { data = getBuilderData(); } catch(e) {
        showToast('Builder not ready. Try again.', 'error');
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save';
        btn.disabled  = false;
        if (opts.onError) opts.onError();
        return;
    }

    const fd = new FormData();
    fd.append('action',        'mt_save_template');
    fd.append('security',      mt_nonce_studio);
    fd.append('template_id',   id);
    fd.append('template_name', name);
    fd.append('email_body',    encodePayload({ html: data.html, json: data.json }));

    fetch(mt_ajax_url_studio, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save';
            if (res.success) {
                hasUnsavedChanges = false;
                showToast('Design saved!');
                if (id == 0 && res.data && res.data.id) document.getElementById('builder_tpl_id').value = res.data.id;
            } else {
                showToast(res.data || 'Save failed.', 'error');
                if (opts.onError) opts.onError();
            }
        }).catch(err => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save';
            showToast('Network error.', 'error');
            if (opts.onError) opts.onError();
        });
}

function saveAsTemplate() {
    const originalId   = document.getElementById('builder_tpl_id').value;
    const originalName = document.getElementById('builder_tpl_name').value;
    mtPrompt('Duplicate Design', 'Enter a name for the copy:', (originalName || 'Untitled') + ' (Copy)', function(newName) {
        if (!newName) return;
        document.getElementById('builder_tpl_id').value    = 0;
        document.getElementById('builder_tpl_name').value  = newName;
        saveBuilder({
            onError: function() {
                document.getElementById('builder_tpl_id').value    = originalId;
                document.getElementById('builder_tpl_name').value  = originalName;
            }
        });
    });
}

// ── Test email ─────────────────────────────────────────────────
function openTestModal()  { document.getElementById('test_email_modal').classList.remove('hidden'); document.getElementById('test_email_modal').classList.add('flex'); }
function closeTestModal() { document.getElementById('test_email_modal').classList.add('hidden'); document.getElementById('test_email_modal').classList.remove('flex'); }

function sendTestEmail() {
    const email = document.getElementById('test_recipient').value;
    if (!email || !email.includes('@')) { showToast('Enter a valid email address.', 'error'); return; }
    const subject = document.getElementById('test_subject').value.trim() || document.getElementById('builder_tpl_name').value.trim() || 'Studio Test';
    showToast('Sending test email...');

    let data;
    try { data = getBuilderData(); } catch(e) { showToast('Builder not ready.', 'error'); return; }

    const fd = new FormData();
    fd.append('action',   'mt_fire_diagnostic_test');
    fd.append('security', mt_nonce_studio);
    fd.append('brand_id', '<?php echo intval($brand->id); ?>');
    fd.append('to_email', email);
    fd.append('subject',  '[TEST] ' + subject);
    fd.append('payload',  JSON.stringify({ html: data.html }));

    fetch(mt_ajax_url_studio, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => { closeTestModal(); showToast(res.success ? res.data : (res.data || 'Send failed.'), res.success ? 'success' : 'error'); })
        .catch(() => showToast('Network error.', 'error'));
}

// ── Template management ────────────────────────────────────────
function trashTemplate(id) {
    mtConfirm('Move to Trash', 'Move this design to the trash?', () => {
        const fd = new FormData(); fd.append('action', 'mt_trash_template'); fd.append('template_id', id);
        fetch(mt_ajax_url_studio, { method: 'POST', body: fd }).then(() => window.location.reload());
    });
}
function restoreTemplate(id) {
    const fd = new FormData(); fd.append('action', 'mt_restore_template'); fd.append('template_id', id);
    fetch(mt_ajax_url_studio, { method: 'POST', body: fd }).then(() => window.location.reload());
}
function emptyTrash() {
    mtConfirm('Empty Trash', 'Permanently delete all trashed items?', () => {
        const fd = new FormData(); fd.append('action', 'mt_empty_trash');
        fetch(mt_ajax_url_studio, { method: 'POST', body: fd }).then(() => window.location.reload());
    });
}
function deletePermanent(id) {
    mtConfirm('Delete Forever', 'This cannot be undone.', () => {
        const fd = new FormData(); fd.append('action', 'mt_delete_template_permanent'); fd.append('template_id', id);
        fetch(mt_ajax_url_studio, { method: 'POST', body: fd }).then(() => window.location.reload());
    });
}

// ── AI Panel ──────────────────────────────────────────────────
let aiProvider   = 'openai';
let aiTone       = 'Professional';
let aiPanelOpen  = false;

function toggleAiPanel() {
    aiPanelOpen = !aiPanelOpen;
    document.getElementById('mt_ai_panel').classList.toggle('open', aiPanelOpen);
    document.getElementById('mt_ai_btn_toggle').style.background   = aiPanelOpen ? '#fff7ed' : '';
    document.getElementById('mt_ai_btn_toggle').style.borderColor  = aiPanelOpen ? '#c57540' : '';
}

function setAiProvider(p, el) {
    aiProvider = p;
    document.querySelectorAll('.ai-provider-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
}

function setAiTone(el) {
    aiTone = el.dataset.tone;
    document.querySelectorAll('.ai-tone-btn').forEach(b => {
        b.style.background = ''; b.style.borderColor = ''; b.style.color = '';
    });
    el.style.background = '#c57540'; el.style.borderColor = '#c57540'; el.style.color = '#ffffff';
}
// Apply initial tone
document.querySelector('.ai-tone-btn.active-tone').style.background  = '#c57540';
document.querySelector('.ai-tone-btn.active-tone').style.borderColor = '#c57540';
document.querySelector('.ai-tone-btn.active-tone').style.color       = '#ffffff';

let aiBuiltTemplateId = 0; // set when AI builds a full template

function toggleTemplateBuilder(val) {
    const opts = document.getElementById('ai_template_builder_opts');
    const copyBtn = document.getElementById('ai_copy_btn');
    if (val === 'build_template') {
        opts.classList.remove('hidden');
        if (copyBtn) copyBtn.classList.add('hidden');
    } else {
        opts.classList.add('hidden');
        if (copyBtn) copyBtn.classList.remove('hidden');
    }
}

function runAiGenerate() {
    const rawPrompt = document.getElementById('ai_prompt').value.trim();
    const context   = document.getElementById('ai_context').value;
    if (!rawPrompt) { showToast('Enter a prompt first.', 'error'); return; }

    const btn = document.getElementById('ai_generate_btn');
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Generating...';
    btn.disabled  = true;
    document.getElementById('ai_output_wrap').classList.add('hidden');
    document.getElementById('ai_error_wrap').classList.add('hidden');
    document.getElementById('ai_load_template_btn')?.classList.add('hidden');
    aiBuiltTemplateId = 0;

    // ── "Build Full Template" mode: different AJAX action ──────────────────
    if (context === 'build_template') {
        const baseSlug = document.getElementById('ai_base_template')?.value || 'starter';
        const fd = new FormData();
        fd.append('action',        'mt_ai_build_template');
        fd.append('security',      mt_nonce_studio);
        fd.append('base_slug',     baseSlug);
        fd.append('description',   rawPrompt);
        fd.append('tone',          aiTone);
        fd.append('provider',      aiProvider);

        fetch(mt_ajax_url_studio, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                btn.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> Generate';
                btn.disabled  = false;
                if (res.success) {
                    aiBuiltTemplateId = res.data.template_id || 0;
                    document.getElementById('ai_output').textContent = res.data.preview_text || 'Template built successfully!';
                    document.getElementById('ai_output_wrap').classList.remove('hidden');
                    if (aiBuiltTemplateId) {
                        document.getElementById('ai_load_template_btn')?.classList.remove('hidden');
                    }
                    const creditEl = document.getElementById('ai_studio_credits');
                    if (creditEl && res.data?.remaining !== undefined) {
                        creditEl.textContent = res.data.remaining + ' AI generates remaining this month';
                    }
                } else {
                    const errMsg = res.data?.message || res.data || 'Something went wrong.';
                    document.getElementById('ai_error_msg').textContent = errMsg;
                    document.getElementById('ai_error_wrap').classList.remove('hidden');
                    if (res.data?.out_of_credits) { btn.disabled = true; btn.innerHTML = 'Limit Reached'; }
                }
            }).catch(() => {
                btn.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> Generate';
                btn.disabled  = false;
                showToast('Network error.', 'error');
            });
        return;
    }
    // ───────────────────────────────────────────────────────────────────────

    const fullPrompt = 'Brand: ' + brandName + '. Tone: ' + aiTone + '.\n\n' + rawPrompt;

    const fd = new FormData();
    fd.append('action',   'mt_generate_ai_copy');
    fd.append('security', mt_nonce_studio);
    fd.append('prompt',   fullPrompt);
    fd.append('provider', aiProvider);
    fd.append('context',  context);

    fetch(mt_ajax_url_studio, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            btn.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> Generate';
            btn.disabled  = false;
            if (res.success) {
                const text = (typeof res.data === 'object') ? (res.data.text || '') : (res.data || '');
                document.getElementById('ai_output').textContent = text;
                document.getElementById('ai_output_wrap').classList.remove('hidden');
                const creditEl = document.getElementById('ai_studio_credits');
                if (creditEl && res.data?.remaining !== undefined) {
                    creditEl.textContent = res.data.remaining + ' AI generates remaining this month';
                }
            } else {
                const errMsg = res.data?.message || res.data || 'Something went wrong.';
                const outOfCredits = res.data?.out_of_credits;
                document.getElementById('ai_error_msg').textContent = errMsg;
                document.getElementById('ai_error_wrap').classList.remove('hidden');
                if (outOfCredits) { btn.disabled = true; btn.innerHTML = 'Limit Reached'; }
            }
        }).catch(() => {
            btn.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> Generate';
            btn.disabled  = false;
            showToast('Network error.', 'error');
        });
}

function loadAiTemplate() {
    if (!aiBuiltTemplateId) return;
    openBuilder(aiBuiltTemplateId, 'AI Generated Template');
    toggleAiPanel();
}

function copyAiResult() {
    const text = document.getElementById('ai_output').textContent;
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => showToast('Copied! Click a text block in the builder and paste.'));
}
</script>
