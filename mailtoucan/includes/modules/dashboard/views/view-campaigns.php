<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_templates = $wpdb->prefix . 'mt_email_templates';
$table_campaigns = $wpdb->prefix . 'mt_campaigns';
$table_leads     = $wpdb->prefix . 'mt_guest_leads';

// Fetch user's saved templates for Step 3
$active_templates = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table_templates WHERE brand_id = %d AND status = 'active' ORDER BY created_at DESC", $brand->id) );

// Fetch existing campaigns for the Dashboard List
$campaigns = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table_campaigns WHERE brand_id = %d ORDER BY created_at DESC", $brand->id) );

// Fetch distinct Campaign Tags from the CRM for the "Custom" Audience dropdown
$roost_tags = $wpdb->get_col( $wpdb->prepare("SELECT DISTINCT campaign_tag FROM $table_leads WHERE brand_id = %d AND campaign_tag != ''", $brand->id) );

// FETCH DYNAMIC BRANDING
$brand_color = !empty($brand->primary_color) ? $brand->primary_color : '#0f172a';
$mt_palette = get_option( 'mt_brand_palette', ['accent' => '#FCC753', 'dark' => '#1A232E'] );
$sender_email = sanitize_title($brand->brand_name) . '@mailtoucan.pro';
?>

<style>
    :root {
        --mt-brand: <?php echo esc_html($brand_color); ?>;
        --mt-accent: <?php echo esc_html($mt_palette['accent']); ?>;
    }
    
    /* Wizard Step Transitions */
    .wizard-step { display: none; opacity: 0; transition: opacity 0.3s ease-in-out; }
    .wizard-step.active { display: block; opacity: 1; animation: fadeIn 0.4s ease forwards; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    /* Step Indicators */
    .step-indicator { transition: all 0.3s ease; }
    .step-indicator.completed .step-icon { background-color: var(--mt-brand); color: white; border-color: var(--mt-brand); }
    .step-indicator.active .step-icon { border-color: var(--mt-brand); color: var(--mt-brand); font-weight: 900; }
    
    /* Template Selection Cards */
    .template-card { transition: all 0.2s ease; cursor: pointer; border: 2px solid transparent; }
    .template-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
    .template-card.selected { border-color: var(--mt-brand); background-color: #f8fafc; }
    .template-card.selected .select-check { opacity: 1; scale: 1; }

    /* Audience Radio Cards */
    .audience-radio { display: none; }
    .audience-card { transition: all 0.2s ease; border: 2px solid #e2e8f0; cursor: pointer; }
    .audience-radio:checked + .audience-card { border-color: var(--mt-brand); background-color: #f8fafc; }
    .audience-radio:checked + .audience-card .radio-circle { border-color: var(--mt-brand); }
    .audience-radio:checked + .audience-card .radio-circle::after { content: ''; display: block; width: 10px; height: 10px; background: var(--mt-brand); border-radius: 50%; margin: 3px auto; }
</style>

<div id="view_campaign_list">
    <header class="mb-8 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-black text-gray-900 flex items-center gap-3">Campaigns</h1>
            <p class="text-gray-500 text-sm mt-1">Manage, schedule, and track your email blasts.</p>
        </div>
        <button onclick="startWizard(0)" class="text-white px-6 py-3 rounded-xl font-bold shadow-lg transition flex items-center gap-2 hover:opacity-90" style="background-color: var(--mt-brand);">
            <i class="fa-solid fa-paper-plane"></i> Create Campaign
        </button>
    </header>

    <?php if(empty($campaigns)): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden text-center py-24">
            <div class="w-20 h-20 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-blue-100">
                <i class="fa-solid fa-cloud-sun text-3xl text-blue-400"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-700">Clear skies ahead!</h3>
            <p class="text-sm text-gray-500 mb-6">No messages waiting on your perch. Create your first email blast.</p>
            <button onclick="startWizard(0)" class="text-indigo-600 font-bold hover:text-indigo-800 transition">Get Started &rarr;</button>
        </div>
    <?php else: ?>
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 border-b text-[10px] uppercase text-gray-500 font-bold tracking-wider">
                    <tr>
                        <th class="p-5 pl-6">Campaign Name</th>
                        <th class="p-5">Status</th>
                        <th class="p-5">Created On</th>
                        <th class="p-5 text-right pr-6">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($campaigns as $camp): 
                        $is_draft = ($camp->campaign_type === 'draft');
                        $config = json_decode($camp->config_json, true) ?: [];
                    ?>
                    <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition">
                        <td class="p-5 pl-6">
                            <p class="font-bold text-gray-900"><?php echo esc_html($camp->campaign_name); ?></p>
                            <?php if(!empty($config['subject'])): ?>
                                <p class="text-xs text-gray-400 truncate mt-1">Subj: <?php echo esc_html($config['subject']); ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="p-5">
                            <?php if($is_draft): ?>
                                <span class="px-3 py-1 bg-yellow-50 text-yellow-700 border border-yellow-200 text-[10px] font-black uppercase tracking-widest rounded-md">Draft</span>
                            <?php else: ?>
                                <span class="px-3 py-1 bg-green-50 text-green-700 border border-green-200 text-[10px] font-black uppercase tracking-widest rounded-md">Sent</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-5 text-gray-500 text-xs font-medium">
                            <?php echo date('M d, Y - h:i A', strtotime($camp->created_at)); ?>
                        </td>
                        <td class="p-5 pr-6 text-right flex justify-end gap-3">
                            <?php if($is_draft): ?>
                                <button onclick="showToast('Resume Draft logic coming in Phase 3!', 'success')" class="w-8 h-8 rounded-lg bg-gray-50 text-gray-500 hover:bg-indigo-50 hover:text-indigo-600 transition flex items-center justify-center border border-gray-200"><i class="fa-solid fa-pen text-xs"></i></button>
                            <?php else: ?>
                                <button onclick="showToast('Insights coming soon!', 'success')" class="w-8 h-8 rounded-lg bg-gray-50 text-gray-500 hover:bg-green-50 hover:text-green-600 transition flex items-center justify-center border border-gray-200"><i class="fa-solid fa-chart-simple text-xs"></i></button>
                            <?php endif; ?>
                            <button onclick="trashCampaign(<?php echo $camp->id; ?>)" class="w-8 h-8 rounded-lg bg-gray-50 text-gray-400 hover:bg-red-50 hover:text-red-500 hover:border-red-200 transition flex items-center justify-center border border-gray-200"><i class="fa-solid fa-trash-can text-xs"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div id="view_campaign_wizard" class="fixed inset-0 bg-[#f8fafc] z-[100] hidden flex-col font-sans overflow-hidden">
    
    <input type="hidden" id="campaign_id" value="0">

    <div class="h-16 bg-white border-b border-gray-200 px-6 flex justify-between items-center shrink-0 z-30 shadow-sm">
        <div class="flex items-center gap-4">
            <button onclick="exitWizard()" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-500 transition" title="Exit">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div class="h-6 w-px bg-gray-200 mx-2"></div>
            <h2 class="text-lg font-black text-gray-900">Flight Path</h2>
        </div>
        
        <div class="flex items-center gap-8">
            <div class="step-indicator active flex items-center gap-2" id="nav-step-1">
                <div class="step-icon w-6 h-6 rounded-full border-2 border-gray-300 text-[10px] flex items-center justify-center text-gray-400 font-bold transition-colors">1</div>
                <span class="text-xs font-bold text-gray-600 uppercase tracking-wide">Setup</span>
            </div>
            <div class="w-8 h-px bg-gray-200"></div>
            <div class="step-indicator flex items-center gap-2" id="nav-step-2">
                <div class="step-icon w-6 h-6 rounded-full border-2 border-gray-300 text-[10px] flex items-center justify-center text-gray-400 font-bold transition-colors">2</div>
                <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Choose Your Flock</span>
            </div>
            <div class="w-8 h-px bg-gray-200"></div>
            <div class="step-indicator flex items-center gap-2" id="nav-step-3">
                <div class="step-icon w-6 h-6 rounded-full border-2 border-gray-300 text-[10px] flex items-center justify-center text-gray-400 font-bold transition-colors">3</div>
                <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Toucan Styled</span>
            </div>
            <div class="w-8 h-px bg-gray-200"></div>
            <div class="step-indicator flex items-center gap-2" id="nav-step-4">
                <div class="step-icon w-6 h-6 rounded-full border-2 border-gray-300 text-[10px] flex items-center justify-center text-gray-400 font-bold transition-colors">4</div>
                <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Pre-Flight Check</span>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button onclick="saveDraftAndExit()" id="btn_save_draft" class="text-gray-500 hover:text-gray-900 text-sm font-bold transition mr-4">Save & Exit</button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-10 relative">
        <div class="max-w-4xl mx-auto pb-24">

            <div id="step-1" class="wizard-step active">
                <h2 class="text-3xl font-black text-gray-900 mb-2">Setup</h2>
                <p class="text-gray-500 mb-8">Let's get this bird off the ground. Define your core details.</p>
                
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 space-y-6">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Internal Campaign Name</label>
                        <input type="text" id="camp_name" class="w-full p-4 bg-gray-50 border border-gray-200 rounded-xl text-sm font-normal text-gray-800 placeholder-gray-400 focus:border-indigo-500 outline-none transition" placeholder="e.g. Black Friday 2026 - VIP Early Access (Internal use only)">
                    </div>
                    <div class="border-t border-gray-100 pt-6">
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Email Subject Line</label>
                        <div class="relative">
                            <input type="text" id="camp_subject" class="w-full p-4 pr-32 bg-white border border-gray-300 rounded-xl text-lg font-normal text-gray-800 placeholder-gray-400 focus:border-indigo-500 outline-none transition shadow-sm" placeholder="e.g. 🎁 Open to reveal your exclusive weekend gift!">
                            <button onclick="triggerAI()" class="absolute right-3 top-1/2 -translate-y-1/2 bg-indigo-50 text-indigo-600 border border-indigo-100 px-3 py-2 rounded-lg text-xs font-bold hover:bg-indigo-100 transition shadow-sm flex items-center">
                                <i class="fa-solid fa-wand-magic-sparkles mr-1.5"></i> AI Assist
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Preview Text (Snippet)</label>
                        <input type="text" id="camp_preview" class="w-full p-4 bg-white border border-gray-300 rounded-xl text-sm font-normal text-gray-800 placeholder-gray-400 focus:border-indigo-500 outline-none transition shadow-sm" placeholder="e.g. You don't want to miss this limited-time offer inside...">
                    </div>
                    
                    <div class="bg-gray-50 rounded-xl p-5 border border-gray-200 flex items-start gap-4 mt-8">
                        <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center shrink-0 mt-1"><i class="fa-solid fa-user-tie"></i></div>
                        <div>
                            <p class="text-[10px] uppercase font-bold text-gray-400 tracking-widest">Sending As</p>
                            <p class="text-base font-bold text-gray-900"><?php echo esc_html($brand->brand_name); ?> &lt;<?php echo esc_html($sender_email); ?>&gt;</p>
                            <p class="text-[11px] text-gray-500 mt-2 leading-relaxed"><i class="fa-solid fa-circle-info text-blue-400 mr-1"></i> Need to change this? You can update your authenticated Sender Identity in the <strong>Sender Domains</strong> tab.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div id="step-2" class="wizard-step">
                <h2 class="text-3xl font-black text-gray-900 mb-2">Choose Your Flock</h2>
                <p class="text-gray-500 mb-8">Who are we sending this campaign to?</p>
                
                <div class="space-y-4">
                    <label class="block relative">
                        <input type="radio" name="audience" value="all" class="audience-radio" checked onchange="toggleCustomAudience()">
                        <div class="audience-card bg-white rounded-2xl p-6 flex items-center gap-6">
                            <div class="radio-circle w-5 h-5 rounded-full border-2 border-gray-300 shrink-0"></div>
                            <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-users"></i></div>
                            <div class="flex-1">
                                <h3 class="text-lg font-black text-gray-900">All Captured Guests</h3>
                                <p class="text-sm text-gray-500">Send to every contact in your Toucan CRM.</p>
                            </div>
                        </div>
                    </label>

                    <label class="block relative">
                        <input type="radio" name="audience" value="recent" class="audience-radio" onchange="toggleCustomAudience()">
                        <div class="audience-card bg-white rounded-2xl p-6 flex items-center gap-6">
                            <div class="radio-circle w-5 h-5 rounded-full border-2 border-gray-300 shrink-0"></div>
                            <div class="w-12 h-12 rounded-full bg-green-50 text-green-500 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-wifi"></i></div>
                            <div class="flex-1">
                                <h3 class="text-lg font-black text-gray-900">Recent WiFi Logins</h3>
                                <p class="text-sm text-gray-500">Guests who visited your location in the last 30 days.</p>
                            </div>
                        </div>
                    </label>

                    <label class="block relative">
                        <input type="radio" name="audience" value="birthday" class="audience-radio" onchange="toggleCustomAudience()">
                        <div class="audience-card bg-white rounded-2xl p-6 flex items-center gap-6">
                            <div class="radio-circle w-5 h-5 rounded-full border-2 border-gray-300 shrink-0"></div>
                            <div class="w-12 h-12 rounded-full bg-purple-50 text-purple-500 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-cake-candles"></i></div>
                            <div class="flex-1">
                                <h3 class="text-lg font-black text-gray-900">Birthday Month</h3>
                                <p class="text-sm text-gray-500">Guests whose birthday is in the current month.</p>
                            </div>
                        </div>
                    </label>

                    <label class="block relative">
                        <input type="radio" name="audience" value="custom" class="audience-radio" onchange="toggleCustomAudience()">
                        <div class="audience-card bg-white rounded-2xl p-6 flex items-center gap-6">
                            <div class="radio-circle w-5 h-5 rounded-full border-2 border-gray-300 shrink-0"></div>
                            <div class="w-12 h-12 rounded-full bg-orange-50 text-orange-500 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-filter"></i></div>
                            <div class="flex-1">
                                <h3 class="text-lg font-black text-gray-900">Custom Segment (The Roost)</h3>
                                <p class="text-sm text-gray-500">Filter guests by the specific campaign where their data was captured.</p>
                            </div>
                        </div>
                    </label>
                    
                    <div id="custom_audience_filters" class="hidden pl-16 pt-2 pb-4">
                        <div class="bg-gray-50 p-5 rounded-xl border border-gray-200">
                            <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">Filter by Capture Source</label>
                            <select id="camp_segment_tag" class="w-full p-3 bg-white border border-gray-300 rounded-lg text-sm font-normal text-gray-800 focus:border-indigo-500 outline-none">
                                <option value="">-- Select a Campaign Tag --</option>
                                <?php if(!empty($roost_tags)): foreach($roost_tags as $tag): ?>
                                    <option value="<?php echo esc_attr($tag); ?>"><?php echo esc_html($tag); ?></option>
                                <?php endforeach; else: ?>
                                    <option value="" disabled>No campaign tags found in CRM yet.</option>
                                <?php endif; ?>
                            </select>
                            <p class="text-xs text-gray-400 mt-2 italic">Only guests who entered through this specific vCard/Splash campaign will receive this email.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div id="step-3" class="wizard-step">
                <h2 class="text-3xl font-black text-gray-900 mb-2">Toucan Styled</h2>
                <p class="text-gray-500 mb-8">Select a beautiful template you built in the Toucan Studio.</p>
                
                <input type="hidden" id="selected_template_id" value="">

                <?php if(empty($active_templates)): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 text-center py-16">
                        <i class="fa-solid fa-palette text-4xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-bold text-gray-700">No Saved Templates</h3>
                        <p class="text-sm text-gray-500 mb-4">You need to build a design in the Studio first.</p>
                        <button onclick="exitWizard()" class="text-indigo-600 font-bold hover:underline">Go back to Dashboard</button>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-3 gap-6">
                        <?php foreach($active_templates as $tpl): ?>
                        <div class="template-card bg-white rounded-2xl overflow-hidden shadow-sm relative group" onclick="selectTemplate(this, <?php echo $tpl->id; ?>)">
                            <div class="select-check absolute top-3 right-3 w-6 h-6 text-white rounded-full flex items-center justify-center opacity-0 scale-50 transition-all shadow-md z-10" style="background-color: var(--mt-brand);">
                                <i class="fa-solid fa-check text-xs"></i>
                            </div>
                            <div class="aspect-[4/3] bg-gray-100 flex items-center justify-center border-b border-gray-100 relative overflow-hidden">
                                <i class="fa-regular fa-envelope text-4xl text-gray-300 transition-transform group-hover:scale-110"></i>
                            </div>
                            <div class="p-4">
                                <h3 class="font-bold text-gray-900 truncate text-sm"><?php echo esc_html($tpl->template_name); ?></h3>
                                <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-widest font-bold">Last Edited: <?php echo date('M d', strtotime($tpl->updated_at)); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div id="step-4" class="wizard-step">
                <h2 class="text-3xl font-black text-gray-900 mb-2">Pre-Flight Check</h2>
                <p class="text-gray-500 mb-8">Double check everything before liftoff.</p>
                
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-8 space-y-6">
                        <div class="flex items-start gap-4 pb-6 border-b border-gray-100">
                            <div class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 shrink-0"><i class="fa-solid fa-heading"></i></div>
                            <div class="flex-1">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Subject</p>
                                <p id="review_subject" class="text-lg font-black text-gray-900">...</p>
                                <p id="review_preview" class="text-sm text-gray-500 mt-1">...</p>
                            </div>
                            <button onclick="goToStep(1)" class="text-xs font-bold text-indigo-600 hover:underline">Edit</button>
                        </div>

                        <div class="flex items-start gap-4 pb-6 border-b border-gray-100">
                            <div class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 shrink-0"><i class="fa-solid fa-users"></i></div>
                            <div class="flex-1">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Audience</p>
                                <p id="review_audience" class="text-lg font-black text-gray-900">...</p>
                                <p id="review_audience_sub" class="text-sm text-gray-500 mt-1 hidden"></p>
                            </div>
                            <button onclick="goToStep(2)" class="text-xs font-bold text-indigo-600 hover:underline">Edit</button>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 shrink-0"><i class="fa-solid fa-palette"></i></div>
                            <div class="flex-1">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Design</p>
                                <p id="review_template" class="text-lg font-black text-gray-900">...</p>
                            </div>
                            <button onclick="goToStep(3)" class="text-xs font-bold text-indigo-600 hover:underline">Edit</button>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-8 border-t border-gray-200 flex items-center justify-between">
                        <button onclick="showToast('Sending test email preview...', 'success')" class="bg-white border border-gray-300 text-gray-700 px-6 py-3 rounded-xl font-bold shadow-sm hover:bg-gray-50 transition flex items-center gap-2"><i class="fa-regular fa-paper-plane"></i> Send Test</button>
                        <div class="flex gap-3">
                            <button onclick="showToast('Scheduling engine coming in Phase 3!', 'success')" class="bg-indigo-50 text-indigo-700 border border-indigo-200 px-6 py-3 rounded-xl font-bold shadow-sm hover:bg-indigo-100 transition"><i class="fa-regular fa-clock mr-2"></i>Schedule Later</button>
                            <button onclick="launchCampaign()" id="btn_blast" class="text-white px-8 py-3 rounded-xl font-black shadow-lg transition flex items-center gap-2 hover:opacity-90" style="background-color: var(--mt-brand);"><i class="fa-solid fa-rocket"></i> Take Flight</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="h-20 bg-white border-t border-gray-200 px-10 flex justify-between items-center shrink-0 z-30 shadow-[0_-4px_15px_rgba(0,0,0,0.02)] relative">
        <button id="btn-prev" onclick="prevStep()" class="text-gray-500 font-bold px-4 py-2 rounded-lg hover:bg-gray-100 transition opacity-0 pointer-events-none flex items-center gap-2"><i class="fa-solid fa-arrow-left text-xs"></i> Back</button>
        <button id="btn-next" onclick="nextStep()" class="text-white px-8 py-3 rounded-xl font-black shadow-md hover:opacity-90 transition flex items-center gap-2 absolute right-10" style="background-color: var(--mt-brand);">Continue <i class="fa-solid fa-arrow-right text-xs"></i></button>
    </div>

</div>

<div id="modal_success" class="fixed inset-0 bg-gray-900/80 z-[200] hidden items-center justify-center backdrop-blur-sm">
    <div class="bg-white rounded-3xl p-10 text-center max-w-sm transform scale-95 transition-transform" id="success_box">
        <div class="w-24 h-24 bg-green-100 text-green-500 rounded-full flex items-center justify-center mx-auto mb-6"><i class="fa-solid fa-dove text-5xl"></i></div>
        <h2 class="text-3xl font-black text-gray-900 mb-2">Message Soaring!</h2>
        <p class="text-gray-500 mb-8">Your message is soaring above the canopy! Check back later to see the flock's response.</p>
        <button onclick="window.location.reload()" class="w-full bg-gray-900 text-white py-4 rounded-xl font-black hover:bg-black transition">Back to Dashboard</button>
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

<div id="mt_toast_container" class="fixed bottom-8 right-8 z-[400] flex flex-col items-end pointer-events-none"></div>

<script>
    // --- UI/UX: CUSTOM POPUPS & TOASTS ---
    function showToast(message, type = 'success') {
        const container = document.getElementById('mt_toast_container');
        const toast = document.createElement('div');
        const bgColor = type === 'error' ? 'bg-red-600' : 'bg-gray-900';
        const icon = type === 'error' ? 'fa-triangle-exclamation' : 'fa-check-circle';
        
        let displayMessage = message;
        // Toucan slang for errors!
        if (type === 'error' && !message.includes('Jungle Tangle')) {
            displayMessage = "Looks like a bit of a Jungle Tangle. - " + message;
        }

        toast.className = `flex items-center gap-3 px-5 py-3.5 rounded-xl shadow-[0_10px_40px_rgba(0,0,0,0.2)] text-white text-sm font-bold transform transition-all duration-300 translate-y-10 opacity-0 ${bgColor} mb-3 pointer-events-auto`;
        toast.innerHTML = `<i class="fa-solid ${icon} text-lg"></i> ${displayMessage}`;
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

    // --- WIZARD LOGIC ---
    let currentStep = 1;
    const totalSteps = 4;

    function triggerAI() {
        showToast('Connecting to Toucan AI Engine...', 'success');
        // AI logic will go here in Phase 3
    }

    function toggleCustomAudience() {
        const customRadio = document.querySelector('input[value="custom"]');
        const customFilters = document.getElementById('custom_audience_filters');
        if (customRadio && customRadio.checked) {
            customFilters.classList.remove('hidden');
        } else {
            customFilters.classList.add('hidden');
        }
    }

    function startWizard(id) {
        document.getElementById('view_campaign_list').style.display = 'none';
        document.getElementById('view_campaign_wizard').classList.remove('hidden');
        document.getElementById('view_campaign_wizard').classList.add('flex');
        
        if (id === 0) {
            silentDraftSave();
        } else {
            // Load existing draft logic
            document.getElementById('campaign_id').value = id;
        }
    }

    function exitWizard() {
        mtConfirm("Abort Takeoff?", "Are you sure you want to exit? Your progress may be lost.", function() {
            window.location.reload();
        });
    }

    // Connects to mt_save_campaign
    function silentDraftSave() {
        const now = new Date();
        const timeString = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        const dateString = now.toLocaleDateString();
        const draftName = 'Draft - ' + dateString + ' ' + timeString;
        
        const fd = new FormData();
        fd.append('action', 'mt_save_campaign'); 
        fd.append('security', typeof mt_nonce !== 'undefined' ? mt_nonce : ''); 
        fd.append('campaign_id', 0); 
        fd.append('campaign_name', draftName);
        fd.append('campaign_type', 'draft');
        fd.append('config', JSON.stringify({})); // Empty config for now
        
        const ajaxUrl = typeof mt_ajax_url !== 'undefined' ? mt_ajax_url : '/wp-admin/admin-ajax.php';
        fetch(ajaxUrl, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            if(res.success) {
                document.getElementById('campaign_id').value = res.data.campaign_id;
            }
        }).catch(err => console.error("Silent Draft Save Failed", err));
    }

    // Connects to mt_delete_campaign
    function trashCampaign(id) {
        mtConfirm("Delete Campaign", "Are you sure you want to permanently delete this campaign?", function() {
            const fd = new FormData(); 
            fd.append('action','mt_delete_campaign'); 
            fd.append('security', typeof mt_nonce !== 'undefined' ? mt_nonce : ''); 
            fd.append('campaign_id',id); 
            
            const ajaxUrl = typeof mt_ajax_url !== 'undefined' ? mt_ajax_url : '/wp-admin/admin-ajax.php';
            fetch(ajaxUrl,{method:'POST',body:fd}).then(()=>window.location.reload());
        });
    }

    function saveDraftAndExit() {
        const btn = document.getElementById('btn_save_draft');
        btn.innerHTML = "Saving...";
        
        const id = document.getElementById('campaign_id').value;
        const name = document.getElementById('camp_name').value || "Untitled Draft";
        const subject = document.getElementById('camp_subject').value;
        
        const config = {
            subject: subject,
            preview: document.getElementById('camp_preview').value,
            audience: document.querySelector('.audience-radio:checked')?.value || 'all',
            template_id: document.getElementById('selected_template_id').value
        };

        const fd = new FormData();
        fd.append('action', 'mt_save_campaign'); 
        fd.append('security', typeof mt_nonce !== 'undefined' ? mt_nonce : ''); 
        fd.append('campaign_id', id); 
        fd.append('campaign_name', name);
        fd.append('campaign_type', 'draft');
        fd.append('config', JSON.stringify(config));
        
        const ajaxUrl = typeof mt_ajax_url !== 'undefined' ? mt_ajax_url : '/wp-admin/admin-ajax.php';
        fetch(ajaxUrl, { method: 'POST', body: fd }).then(() => {
            showToast("Progress tucked safely into The Nest.", "success");
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        });
    }

    function selectTemplate(element, id) {
        document.querySelectorAll('.template-card').forEach(c => c.classList.remove('selected'));
        element.classList.add('selected');
        document.getElementById('selected_template_id').value = id;
    }

    function goToStep(step) {
        // Validation
        if (step > currentStep) {
            if (currentStep === 1) {
                if(!document.getElementById('camp_name').value || !document.getElementById('camp_subject').value) {
                    showToast("Please give your flock a Name and Subject Line first.", "error");
                    return;
                }
            }
            if (currentStep === 2) {
                const audience = document.querySelector('.audience-radio:checked').value;
                if(audience === 'custom' && !document.getElementById('camp_segment_tag').value) {
                    showToast("Please select a valid Roost Campaign tag to filter by.", "error");
                    return;
                }
            }
            if (currentStep === 3) {
                if(!document.getElementById('selected_template_id').value) {
                    showToast("Please pick your plumage (select a design) to continue.", "error");
                    return;
                }
            }
        }

        // Hide all steps
        document.querySelectorAll('.wizard-step').forEach(el => el.classList.remove('active'));
        
        // Show target step
        currentStep = step;
        document.getElementById('step-' + currentStep).classList.add('active');

        // Update Nav
        for(let i=1; i<=totalSteps; i++) {
            let indicator = document.getElementById('nav-step-' + i);
            if(i < currentStep) {
                indicator.className = 'step-indicator completed flex items-center gap-2';
                indicator.querySelector('span').className = 'text-xs font-bold text-gray-900 uppercase tracking-wide';
                indicator.querySelector('.step-icon').innerHTML = '<i class="fa-solid fa-check text-[8px]"></i>';
            } else if (i === currentStep) {
                indicator.className = 'step-indicator active flex items-center gap-2';
                indicator.querySelector('span').className = 'text-xs font-bold text-gray-900 uppercase tracking-wide';
                indicator.querySelector('.step-icon').innerHTML = i;
            } else {
                indicator.className = 'step-indicator flex items-center gap-2';
                indicator.querySelector('span').className = 'text-xs font-bold text-gray-400 uppercase tracking-wide';
                indicator.querySelector('.step-icon').innerHTML = i;
            }
        }

        const btnPrev = document.getElementById('btn-prev');
        const btnNext = document.getElementById('btn-next');

        if(currentStep === 1) {
            btnPrev.classList.remove('opacity-100', 'pointer-events-auto');
            btnPrev.classList.add('opacity-0', 'pointer-events-none');
        } else {
            btnPrev.classList.add('opacity-100', 'pointer-events-auto');
            btnPrev.classList.remove('opacity-0', 'pointer-events-none');
        }

        if(currentStep === totalSteps) {
            btnNext.style.display = 'none';
            populateReviewScreen();
        } else {
            btnNext.style.display = 'flex';
        }
    }

    function nextStep() { if(currentStep < totalSteps) goToStep(currentStep + 1); }
    function prevStep() { if(currentStep > 1) goToStep(currentStep - 1); }

    function populateReviewScreen() {
        const subject = document.getElementById('camp_subject').value;
        const preview = document.getElementById('camp_preview').value;
        
        let audienceText = "All Captured Guests";
        let audienceSub = "";
        const selectedRadio = document.querySelector('.audience-radio:checked');
        
        if(selectedRadio) {
            if(selectedRadio.value === 'custom') {
                audienceText = "Custom Segment";
                audienceSub = "Filtered by Tag: " + document.getElementById('camp_segment_tag').value;
                document.getElementById('review_audience_sub').innerText = audienceSub;
                document.getElementById('review_audience_sub').classList.remove('hidden');
            } else {
                audienceText = selectedRadio.nextElementSibling.querySelector('h3').innerText;
                document.getElementById('review_audience_sub').classList.add('hidden');
            }
        }

        let templateText = "Selected Template ID: " + document.getElementById('selected_template_id').value;
        const selectedTplCard = document.querySelector('.template-card.selected h3');
        if(selectedTplCard) templateText = selectedTplCard.innerText;

        document.getElementById('review_subject').innerText = subject;
        document.getElementById('review_preview').innerText = preview || 'No preview text provided.';
        document.getElementById('review_audience').innerText = audienceText;
        document.getElementById('review_template').innerText = templateText;
    }

    function launchCampaign() {
        mtConfirm("Ready for Takeoff?", "This will deploy your email blast to the selected audience immediately. Proceed?", function() {
            const btn = document.getElementById('btn_blast');
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Launching...';
            btn.disabled = true;

            // Final Save as 'sent'
            const id = document.getElementById('campaign_id').value;
            const config = {
                subject: document.getElementById('camp_subject').value,
                preview: document.getElementById('camp_preview').value,
                audience: document.querySelector('.audience-radio:checked')?.value || 'all',
                audience_tag: document.getElementById('camp_segment_tag').value,
                template_id: document.getElementById('selected_template_id').value
            };

            const fd = new FormData();
            fd.append('action', 'mt_save_campaign'); 
            fd.append('security', typeof mt_nonce !== 'undefined' ? mt_nonce : ''); 
            fd.append('campaign_id', id); 
            fd.append('campaign_name', document.getElementById('camp_name').value);
            fd.append('campaign_type', 'sent'); // Flags it as dispatched
            fd.append('config', JSON.stringify(config));
            
            const ajaxUrl = typeof mt_ajax_url !== 'undefined' ? mt_ajax_url : '/wp-admin/admin-ajax.php';
            fetch(ajaxUrl, { method: 'POST', body: fd }).then(() => {
                document.getElementById('modal_success').classList.remove('hidden');
                document.getElementById('modal_success').classList.add('flex');
                setTimeout(() => document.getElementById('success_box').classList.remove('scale-95'), 50);
            });
        });
    }
</script>