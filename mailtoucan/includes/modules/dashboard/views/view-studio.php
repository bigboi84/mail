<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_templates = $wpdb->prefix . 'mt_email_templates';
// Fetch Active and Trashed
$active_templates = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table_templates WHERE brand_id = %d AND status = 'active' ORDER BY created_at DESC", $brand->id) );
$trashed_templates = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table_templates WHERE brand_id = %d AND status = 'trashed' ORDER BY deleted_at DESC", $brand->id) );
?>

<link rel="stylesheet" href="https://unpkg.com/grapesjs/dist/css/grapes.min.css">
<script src="https://unpkg.com/grapesjs"></script>
<script src="https://unpkg.com/grapesjs-preset-newsletter"></script>

<style>
    /* MAIN DASHBOARD TABS */
    .studio-tab-btn { transition: all 0.2s; cursor: pointer; }
    .studio-tab-btn.active { border-bottom: 2px solid #4f46e5; color: #4f46e5; }
    .studio-tab-content { display: none; }
    .studio-tab-content.active { display: block; }
    
    /* TOUCAN STUDIO: PREMIUM GRAPESJS OVERRIDES */
    .gjs-one-bg { background-color: #ffffff !important; }
    .gjs-two-color { color: #475569 !important; }
    .gjs-three-bg { background-color: #f8fafc !important; color: #0f172a !important; }
    .gjs-four-color, .gjs-four-color-h:hover { color: #4f46e5 !important; }
    
    .gjs-pn-devices-c, .gjs-pn-options { display: none !important; }
    .gjs-pn-panel { border-bottom: 1px solid #e2e8f0 !important; box-shadow: none !important; }
    
    .gjs-cv-canvas { background-color: #f1f5f9 !important; top: 0 !important; height: 100% !important;}
    
    .gjs-blocks-c { padding: 1rem !important; }
    .gjs-block { 
        border: 1px solid #e2e8f0 !important; 
        border-radius: 0.5rem !important; 
        padding: 1.25rem 1rem !important; 
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important; 
        transition: all 0.2s ease-in-out !important; 
        background: #fff !important;
        margin-bottom: 10px !important;
        width: calc(50% - 10px) !important;
    }
    .gjs-block:hover { 
        border-color: #4f46e5 !important; 
        color: #4f46e5 !important; 
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important; 
        transform: translateY(-2px);
    }
    .gjs-block-label { font-family: 'Inter', sans-serif !important; font-weight: 700 !important; margin-top: 0.75rem !important; font-size: 0.75rem !important; }
    .gjs-block svg { width: 28px !important; height: 28px !important; }
</style>

<div id="view_list">
    <header class="mb-6 flex justify-between items-end">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2"><i class="fa-solid fa-wand-magic-sparkles text-indigo-500"></i> Toucan Studio</h1>
            <p class="text-gray-500 text-sm mt-1">Design, manage, and assign beautiful email templates for your workflows.</p>
        </div>
        <button onclick="openBuilder(0)" class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg font-bold shadow-md hover:bg-indigo-700 transition flex items-center gap-2">
            <i class="fa-solid fa-plus"></i> Blank Canvas
        </button>
    </header>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-20">
        
        <div class="flex border-b border-gray-200 bg-gray-50 px-4 pt-2 gap-6 justify-between items-center">
            <div class="flex gap-6">
                <button class="studio-tab-btn active px-4 py-3 font-bold text-sm text-gray-500 hover:text-gray-900" onclick="switchStudioTab('saved', this)">My Templates (<?php echo count($active_templates); ?>)</button>
                <button class="studio-tab-btn px-4 py-3 font-bold text-sm text-gray-500 hover:text-gray-900" onclick="switchStudioTab('gallery', this)">Template Gallery</button>
                <button class="studio-tab-btn px-4 py-3 font-bold text-sm text-gray-500 hover:text-red-600" onclick="switchStudioTab('trash', this)">Trash Bin (<?php echo count($trashed_templates); ?>)</button>
            </div>
            
            <div class="flex items-center gap-4 pr-4">
                <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Status Legend:</span>
                <div class="flex items-center gap-1 text-[10px] font-bold text-green-700 bg-green-50 px-2 py-1 rounded"><span class="w-2 h-2 rounded-full bg-green-500"></span> Splash / Trigger</div>
                <div class="flex items-center gap-1 text-[10px] font-bold text-purple-700 bg-purple-50 px-2 py-1 rounded"><span class="w-2 h-2 rounded-full bg-purple-500"></span> Bulk Broadcast</div>
                <div class="flex items-center gap-1 text-[10px] font-bold text-yellow-700 bg-yellow-50 px-2 py-1 rounded"><span class="w-2 h-2 rounded-full bg-yellow-500"></span> Draft</div>
            </div>
        </div>

        <div class="p-6 bg-gray-50/50 min-h-[500px]">
            
            <div id="tab_saved" class="studio-tab-content active">
                <?php if(empty($active_templates)): ?>
                    <div class="text-center py-16">
                        <i class="fa-regular fa-folder-open text-4xl text-gray-300 mb-3"></i>
                        <h3 class="text-lg font-bold text-gray-700">No Custom Templates</h3>
                        <p class="text-sm text-gray-500 mb-4">You haven't built any templates yet. Start with a blank canvas or choose from the gallery.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-4 gap-6">
                        <?php foreach($active_templates as $tpl): 
                            $badge_color = 'bg-yellow-100 text-yellow-700'; $dot_color = 'bg-yellow-500'; $badge_text = 'Draft';
                            if ($tpl->assigned_to === 'splash') { $badge_color = 'bg-green-100 text-green-700'; $dot_color = 'bg-green-500'; $badge_text = 'Splash Autoresponder'; }
                            if ($tpl->assigned_to === 'bulk') { $badge_color = 'bg-purple-100 text-purple-700'; $dot_color = 'bg-purple-500'; $badge_text = 'Bulk Campaign'; }
                        ?>
                        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm hover:shadow-md transition group flex flex-col">
                            <div class="h-32 bg-gray-100 border-b border-gray-100 relative flex items-center justify-center shrink-0">
                                <i class="fa-regular fa-envelope text-3xl text-gray-300"></i>
                                <div class="absolute top-2 right-2 <?php echo $badge_color; ?> text-[9px] font-bold px-2 py-1 rounded shadow-sm flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full <?php echo $dot_color; ?>"></span> <?php echo $badge_text; ?></div>
                            </div>
                            <div class="p-4 flex-1 flex flex-col">
                                <h3 class="font-bold text-gray-900 text-sm truncate mb-1" title="<?php echo esc_attr($tpl->template_name); ?>"><?php echo esc_html($tpl->template_name); ?></h3>
                                <p class="text-xs text-gray-500 truncate mb-4 flex-1">Subj: <?php echo esc_html($tpl->email_subject); ?></p>
                                <div class="flex justify-between items-center border-t pt-3 mt-auto">
                                    <span class="text-[10px] text-gray-400 font-medium">Updated <?php echo date('M d', strtotime($tpl->created_at)); ?></span>
                                    <div class="flex gap-3">
                                        <button onclick="trashTemplate(<?php echo $tpl->id; ?>)" class="text-gray-400 hover:text-red-500 transition" title="Move to Trash"><i class="fa-solid fa-trash"></i></button>
                                        <button onclick="openBuilder(<?php echo $tpl->id; ?>, '<?php echo esc_attr($tpl->template_name); ?>', '<?php echo esc_attr($tpl->email_subject); ?>')" data-body="<?php echo esc_attr($tpl->email_body); ?>" id="raw_body_<?php echo $tpl->id; ?>" class="text-indigo-600 font-bold text-sm hover:underline">Edit <i class="fa-solid fa-arrow-right text-[10px] ml-1"></i></button>
                                    </div>
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
                        ['name' => 'The Instant Reward', 'desc' => 'Perfect for Free Spring Roll WiFi gifts.', 'icon' => 'fa-gift'],
                        ['name' => 'The Review Requester', 'desc' => 'Drive 5-star Google Reviews.', 'icon' => 'fa-star'],
                        ['name' => 'The "Miss You" Win-Back', 'desc' => 'Bring back customers gone 30+ days.', 'icon' => 'fa-heart-crack'],
                        ['name' => 'The Birthday Treat', 'desc' => 'Automated birthday gift delivery.', 'icon' => 'fa-cake-candles'],
                        ['name' => 'The Monthly Newsletter', 'desc' => 'Standard 2-column menu update.', 'icon' => 'fa-newspaper'],
                        ['name' => 'The Flash Sale', 'desc' => 'High urgency. Big buttons.', 'icon' => 'fa-bolt'],
                        ['name' => 'The Event RSVP', 'desc' => 'Live music or ticketed dinners.', 'icon' => 'fa-ticket'],
                        ['name' => 'The VIP Exclusive', 'desc' => 'Black/Gold theme for big spenders.', 'icon' => 'fa-crown']
                    ];
                    foreach($prebuilts as $pb):
                    ?>
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm hover:shadow-md hover:border-indigo-300 transition cursor-pointer flex flex-col" onclick="openBuilder(0, '<?php echo $pb['name']; ?> Copy')">
                        <div class="h-28 bg-gradient-to-br from-indigo-50 to-blue-50 border-b border-gray-100 flex flex-col items-center justify-center shrink-0">
                            <i class="fa-solid <?php echo $pb['icon']; ?> text-3xl text-indigo-300 mb-2"></i>
                        </div>
                        <div class="p-4 text-center flex-1 flex flex-col justify-center">
                            <h3 class="font-bold text-gray-900 text-sm mb-1"><?php echo $pb['name']; ?></h3>
                            <p class="text-xs text-gray-500 leading-tight"><?php echo $pb['desc']; ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="tab_trash" class="studio-tab-content">
                <?php if(empty($trashed_templates)): ?>
                    <div class="text-center py-16">
                        <i class="fa-solid fa-trash-can-arrow-up text-4xl text-gray-300 mb-3"></i>
                        <h3 class="text-lg font-bold text-gray-700">Trash is Empty</h3>
                        <p class="text-sm text-gray-500">Deleted templates stay here for 30 days before being permanently removed.</p>
                    </div>
                <?php else: ?>
                    <div class="flex justify-between items-end mb-4">
                        <p class="text-xs text-gray-500"><i class="fa-solid fa-circle-info mr-1"></i> Items in trash are automatically deleted after 30 days.</p>
                        <button onclick="emptyTrash()" class="text-xs font-bold text-red-600 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded transition">Empty Trash Now</button>
                    </div>
                    <div class="bg-white border rounded-lg overflow-hidden">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 border-b text-[10px] uppercase text-gray-500 font-bold">
                                <tr><th class="p-3 pl-4">Template Name</th><th class="p-3">Deleted On</th><th class="p-3 text-right pr-4">Action</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($trashed_templates as $tpl): ?>
                                <tr class="border-b last:border-0 hover:bg-gray-50">
                                    <td class="p-3 pl-4 font-bold text-gray-700 line-through"><?php echo esc_html($tpl->template_name); ?></td>
                                    <td class="p-3 text-gray-500"><?php echo date('M d, Y', strtotime($tpl->deleted_at)); ?></td>
                                    <td class="p-3 pr-4 text-right flex justify-end gap-3">
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

<div id="view_builder" class="fixed inset-0 bg-[#f8fafc] z-[100] hidden flex-col font-sans">
    
    <div class="h-16 bg-white border-b border-gray-200 px-6 flex justify-between items-center shrink-0 shadow-sm z-20">
        <div class="flex items-center gap-4">
            <button onclick="closeBuilder()" class="text-gray-500 hover:text-gray-900 transition flex items-center gap-2 font-bold text-sm">
                <i class="fa-solid fa-arrow-left"></i> Exit Studio
            </button>
            <div class="h-6 w-px bg-gray-200 mx-2"></div>
            <span class="text-sm font-bold text-gray-900 bg-gray-100 px-3 py-1 rounded flex items-center gap-2"><i class="fa-solid fa-layer-group text-indigo-500"></i> Design Mode</span>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="openAiModal('body')" class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-indigo-100 text-indigo-700 text-sm font-bold px-4 py-2 rounded-lg flex items-center gap-2 hover:shadow-md transition">
                <i class="fa-solid fa-feather-pointed text-indigo-500"></i> Ask Tou-can
            </button>
            <button onclick="saveBuilder()" id="btn_save_builder" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold px-6 py-2 rounded-lg transition shadow-md flex items-center gap-2">
                <i class="fa-solid fa-check"></i> Save & Finish
            </button>
        </div>
    </div>

    <input type="hidden" id="builder_tpl_id" value="0">

    <div class="flex flex-1 overflow-hidden">
        
        <div class="w-80 bg-white border-r border-gray-200 shadow-[4px_0_24px_rgba(0,0,0,0.02)] z-10 flex flex-col shrink-0">
            <div class="p-6 border-b border-gray-100 bg-gray-50/50">
                <div class="w-8 h-8 bg-indigo-100 text-indigo-600 rounded flex items-center justify-center font-bold text-sm mb-3">1</div>
                <h2 class="text-lg font-bold text-gray-900">Campaign Details</h2>
                <p class="text-xs text-gray-500 mt-1">Configure what your guests will see in their inbox before they open the email.</p>
            </div>
            
            <div class="p-6 space-y-6 overflow-y-auto">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-gray-500 mb-2">Internal Name</label>
                    <input type="text" id="builder_tpl_name" class="w-full p-3 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-indigo-100 text-sm font-medium text-gray-900 transition-shadow" placeholder="e.g. June Welcome Email">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-gray-500 mb-2">Subject Line</label>
                    <div class="relative group">
                        <input type="text" id="builder_tpl_subject" class="w-full p-3 pr-12 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-indigo-100 text-sm font-medium text-gray-900 transition-shadow" placeholder="e.g. Open for a free gift! 🎁">
                        <button onclick="openAiModal('subject')" class="absolute right-2 top-2 w-8 h-8 flex items-center justify-center bg-indigo-50 text-indigo-600 rounded hover:bg-indigo-600 hover:text-white transition-colors" title="Ask Tou-can for a Subject Line">
                            <i class="fa-solid fa-feather-pointed text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="p-6 border-t border-gray-100 bg-gray-50/50 mt-auto">
                <div class="w-8 h-8 bg-indigo-100 text-indigo-600 rounded flex items-center justify-center font-bold text-sm mb-3">2</div>
                <h2 class="text-lg font-bold text-gray-900">Build Your Design</h2>
                <p class="text-xs text-gray-500 mt-1">Drag and drop elements from the right panel onto the center canvas to build your email.</p>
            </div>
        </div>

        <div class="flex-1 relative bg-[#f1f5f9]">
            <div id="gjs" class="absolute inset-0">
                <div class="absolute inset-0 flex items-center justify-center text-gray-400 font-bold flex-col gap-2">
                    <i class="fa-solid fa-spinner fa-spin text-3xl"></i>
                    Initializing Studio...
                </div>
            </div>
        </div>
    </div>
</div>

<div id="ai_modal" class="fixed inset-0 bg-gray-900/80 z-[200] hidden flex items-center justify-center backdrop-blur-sm transition-opacity opacity-0">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden transform scale-95 transition-all" id="ai_modal_content">
        
        <div class="p-6 border-b flex justify-between items-center bg-gradient-to-r from-indigo-900 to-purple-900 text-white relative overflow-hidden">
            <i class="fa-solid fa-leaf absolute -right-4 -bottom-4 text-white/10 text-6xl"></i>
            
            <div class="flex items-center gap-4 z-10">
                <div class="w-14 h-14 rounded-full bg-indigo-800/50 border-2 border-indigo-400/50 flex items-center justify-center overflow-hidden shrink-0 shadow-inner">
                    <i class="fa-solid fa-crow text-indigo-200 text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold flex items-center gap-2">Tou-can AI</h2>
                    <p class="text-xs text-indigo-200 mt-1" id="ai_modal_subtitle">I'm Tou-can! The AI that *can* write your copy.</p>
                </div>
            </div>
            <button onclick="closeAiModal()" class="text-indigo-200 hover:text-white transition text-xl z-10"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <div class="p-6 space-y-4">
            <input type="hidden" id="ai_target_mode" value="body">
            
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">What should I squawk about?</label>
                <textarea id="ai_prompt" rows="3" class="w-full p-4 border border-gray-300 rounded-xl outline-none focus:ring-2 focus:ring-indigo-100 text-sm font-medium resize-none shadow-sm" placeholder="e.g., Squawk about a fun, urgent promo for 2-for-1 Margaritas this Friday night..."></textarea>
            </div>
            
            <button id="btn_generate_ai" onclick="generateAiCopy()" class="w-full bg-indigo-600 text-white font-bold py-3.5 rounded-xl shadow-md hover:shadow-lg hover:bg-indigo-700 transition flex items-center justify-center gap-2">
                <i class="fa-solid fa-feather-pointed"></i> Let's Fly!
            </button>

            <div id="ai_error_box" class="hidden mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm font-bold text-red-600 flex items-center gap-2"></div>

            <div id="ai_result_box" class="hidden mt-4">
                <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1">Tou-can Generated This:</label>
                <div id="ai_result_text" class="bg-gray-50 border border-gray-200 rounded-xl p-5 text-sm text-gray-800 whitespace-pre-wrap max-h-48 overflow-y-auto leading-relaxed"></div>
                
                <button onclick="insertAiCopy()" class="w-full mt-4 bg-gray-900 text-white font-bold py-3 rounded-xl hover:bg-gray-800 transition flex items-center justify-center gap-2 shadow-md">
                    <i class="fa-solid fa-arrow-turn-down"></i> <span id="ai_insert_btn_text">Drop into Canvas</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    let editor = null;

    function switchStudioTab(tab, element) {
        document.querySelectorAll('.studio-tab-btn').forEach(b => b.classList.remove('active', 'border-indigo-500', 'text-gray-900'));
        element.classList.add('active', 'border-indigo-500', 'text-gray-900');
        document.querySelectorAll('.studio-tab-content').forEach(c => c.classList.remove('active'));
        document.getElementById('tab_' + tab).classList.add('active');
    }

    function openBuilder(id, name = '', subject = '') {
        document.getElementById('builder_tpl_id').value = id;
        document.getElementById('builder_tpl_name').value = name;
        document.getElementById('builder_tpl_subject').value = subject;
        
        document.getElementById('view_list').style.display = 'none';
        document.querySelector('.sidebar').style.display = 'none';
        document.getElementById('main_content_area').classList.add('studio-active');
        document.getElementById('view_builder').classList.remove('hidden');
        document.getElementById('view_builder').classList.add('flex');

        let startingHtml = '';
        if(id !== 0) { startingHtml = document.getElementById('raw_body_' + id).getAttribute('data-body') || ''; }

        if (!editor) {
            setTimeout(() => {
                editor = grapesjs.init({
                    container: '#gjs',
                    fromElement: false,
                    height: '100%',
                    width: 'auto',
                    storageManager: false,
                    plugins: ['gjs-preset-newsletter'],
                    pluginsOpts: { 'gjs-preset-newsletter': { modalTitleImport: 'Import HTML' } },
                    components: startingHtml
                });
            }, 100);
        } else {
            editor.setComponents(startingHtml);
        }
    }

    function closeBuilder() {
        if(!confirm("Are you sure you want to close? Unsaved changes will be lost.")) return;
        document.getElementById('view_builder').classList.add('hidden');
        document.getElementById('view_builder').classList.remove('flex');
        document.getElementById('view_list').style.display = 'block';
        document.querySelector('.sidebar').style.display = 'flex';
        document.getElementById('main_content_area').classList.remove('studio-active');
    }

    function openAiModal(targetMode) {
        document.getElementById('ai_target_mode').value = targetMode; 
        document.getElementById('ai_prompt').value = '';
        document.getElementById('ai_result_box').classList.add('hidden');
        document.getElementById('ai_error_box').classList.add('hidden');
        
        if(targetMode === 'subject') {
            document.getElementById('ai_modal_subtitle').innerText = "I'll write a catchy, high-open-rate subject line.";
            document.getElementById('ai_prompt').placeholder = "e.g., Squawk about a Friday flash sale...";
            document.getElementById('ai_insert_btn_text').innerText = "Set as Subject Line";
        } else {
            document.getElementById('ai_modal_subtitle').innerText = "I'll write high-converting copy for your email body.";
            document.getElementById('ai_prompt').placeholder = "e.g., Squawk about a fun promo for 2-for-1 Margaritas...";
            document.getElementById('ai_insert_btn_text').innerText = "Drop into Canvas";
        }

        const m = document.getElementById('ai_modal');
        const c = document.getElementById('ai_modal_content');
        m.classList.remove('hidden');
        setTimeout(() => { m.classList.remove('opacity-0'); c.classList.remove('scale-95'); }, 10);
    }

    function closeAiModal() {
        const m = document.getElementById('ai_modal');
        const c = document.getElementById('ai_modal_content');
        m.classList.add('opacity-0'); c.classList.add('scale-95');
        setTimeout(() => { m.classList.add('hidden'); }, 300);
    }

    function generateAiCopy() {
        const prompt = document.getElementById('ai_prompt').value.trim();
        const errBox = document.getElementById('ai_error_box');
        errBox.classList.add('hidden');

        if(!prompt) return;
        
        const btn = document.getElementById('btn_generate_ai');
        const ogText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-feather-pointed fa-bounce"></i> Flapping wings...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'mt_generate_ai_copy');
        formData.append('security', mt_nonce);
        formData.append('prompt', prompt);

        fetch(mt_ajax_url, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            btn.innerHTML = ogText; btn.disabled = false;
            if(data.success) {
                document.getElementById('ai_result_text').innerText = data.data;
                document.getElementById('ai_result_box').classList.remove('hidden');
            } else {
                errBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Tou-can hit a branch: ' + data.data;
                errBox.classList.remove('hidden');
            }
        }).catch(err => {
            btn.innerHTML = ogText; btn.disabled = false;
            errBox.innerHTML = '<i class="fa-solid fa-wifi"></i> Tou-can lost connection to the canopy. Try again.';
            errBox.classList.remove('hidden');
        });
    }

    function insertAiCopy() {
        const text = document.getElementById('ai_result_text').innerText;
        const targetMode = document.getElementById('ai_target_mode').value;

        if (targetMode === 'subject') {
            document.getElementById('builder_tpl_subject').value = text;
        } else {
            if(!editor) return;
            editor.addComponents({
                type: 'text',
                content: text.replace(/\n/g, '<br>'),
                style: { padding: '15px', 'font-family': 'Inter, sans-serif', 'font-size': '16px', 'line-height': '1.6', 'color': '#334155' }
            });
        }
        closeAiModal();
    }

    function trashTemplate(id) {
        if(!confirm("Move this template to trash? It will be paused if assigned to a workflow.")) return;
        const formData = new FormData(); formData.append('action', 'mt_trash_template'); formData.append('security', mt_nonce); formData.append('template_id', id);
        fetch(mt_ajax_url, { method: 'POST', body: formData }).then(res => res.json()).then(data => { if(data.success) window.location.reload(); });
    }

    function restoreTemplate(id) {
        const formData = new FormData(); formData.append('action', 'mt_restore_template'); formData.append('security', mt_nonce); formData.append('template_id', id);
        fetch(mt_ajax_url, { method: 'POST', body: formData }).then(res => res.json()).then(data => { if(data.success) window.location.reload(); });
    }

    function emptyTrash() {
        if(!confirm("Permanently delete ALL items in the trash? This cannot be undone.")) return;
        const formData = new FormData(); formData.append('action', 'mt_empty_trash'); formData.append('security', mt_nonce);
        fetch(mt_ajax_url, { method: 'POST', body: formData }).then(res => res.json()).then(data => { if(data.success) window.location.reload(); });
    }

    function deletePermanent(id) {
        if(!confirm("Permanently delete this template?")) return;
        const formData = new FormData(); formData.append('action', 'mt_delete_template_permanent'); formData.append('security', mt_nonce); formData.append('template_id', id);
        fetch(mt_ajax_url, { method: 'POST', body: formData }).then(res => res.json()).then(data => { if(data.success) window.location.reload(); });
    }

    function saveBuilder() {
        const btn = document.getElementById('btn_save_builder');
        const ogText = btn.innerHTML;
        
        const id = document.getElementById('builder_tpl_id').value;
        const name = document.getElementById('builder_tpl_name').value.trim();
        const subject = document.getElementById('builder_tpl_subject').value.trim();
        
        if(!name || !subject) { alert("Please provide a Campaign Name and Subject Line in the left panel."); return; }
        
        const html = editor.getHtml();
        const css = editor.getCss();
        const fullBody = `<style>${css}</style>${html}`;

        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...'; btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'mt_save_template');
        formData.append('security', mt_nonce);
        formData.append('template_id', id);
        formData.append('template_name', name);
        formData.append('email_subject', subject);
        formData.append('email_body', fullBody);

        fetch(mt_ajax_url, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Saved!';
                if (id == 0) document.getElementById('builder_tpl_id').value = data.data.id;
                setTimeout(() => { btn.innerHTML = ogText; btn.disabled = false; }, 2000);
            } else { alert("Failed to save."); btn.innerHTML = ogText; btn.disabled = false; }
        });
    }
</script>