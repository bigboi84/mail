<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_templates = $wpdb->prefix . 'mt_email_templates';
$table_campaigns = $wpdb->prefix . 'mt_campaigns'; // We store workflows here too, using campaign_type = 'workflow'
$table_stores    = $wpdb->prefix . 'mt_stores';

// Fetch user's saved templates for the payload step
$active_templates = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table_templates WHERE brand_id = %d AND status = 'active' ORDER BY created_at DESC", $brand->id) );

// Fetch active and draft workflows
$workflows = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table_campaigns WHERE brand_id = %d AND campaign_type IN ('workflow', 'workflow_draft') ORDER BY created_at DESC", $brand->id) );

// Fetch Locations for the Location Filter
$locations = $wpdb->get_results( $wpdb->prepare("SELECT id, store_name FROM $table_stores WHERE brand_id = %d", $brand->id) );

// FETCH DYNAMIC BRANDING
$brand_color = !empty($brand->primary_color) ? $brand->primary_color : '#0f172a';
$mt_palette = get_option( 'mt_brand_palette', ['accent' => '#FCC753', 'dark' => '#1A232E'] );
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
    
    /* Custom Radio Cards */
    .custom-radio-input { display: none; }
    .custom-card { transition: all 0.2s ease; border: 2px solid #e2e8f0; cursor: pointer; }
    .custom-radio-input:checked + .custom-card { border-color: var(--mt-brand); background-color: #f8fafc; }
    .custom-radio-input:checked + .custom-card .radio-circle { border-color: var(--mt-brand); }
    .custom-radio-input:checked + .custom-card .radio-circle::after { content: ''; display: block; width: 10px; height: 10px; background: var(--mt-brand); border-radius: 50%; margin: 3px auto; }
    .custom-card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(0,0,0,0.05); }

    /* Template Cards */
    .template-card { transition: all 0.2s ease; cursor: pointer; border: 2px solid transparent; }
    .template-card.selected { border-color: var(--mt-brand); background-color: #f8fafc; }
    .template-card.selected .select-check { opacity: 1; scale: 1; }
    .template-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
</style>

<div id="view_workflow_list">
    <header class="mb-8 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-black text-gray-900 flex items-center gap-3">Autopilot</h1>
            <p class="text-gray-500 text-sm mt-1">Set up automated background rules that trigger while you sleep.</p>
        </div>
        <button onclick="startWizard(0)" class="text-white px-6 py-3 rounded-xl font-bold shadow-lg transition flex items-center gap-2 hover:opacity-90" style="background-color: var(--mt-brand);">
            <i class="fa-solid fa-robot"></i> New Automation
        </button>
    </header>

    <?php if(empty($workflows)): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden text-center py-24">
            <div class="w-20 h-20 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-indigo-100">
                <i class="fa-solid fa-gears text-3xl text-indigo-400"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-700">Autopilot is disengaged</h3>
            <p class="text-sm text-gray-500 mb-6">Build workflows to automatically welcome guests or celebrate birthdays.</p>
            <button onclick="startWizard(0)" class="text-indigo-600 font-bold hover:text-indigo-800 transition">Create your first rule &rarr;</button>
        </div>
    <?php else: ?>
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 border-b text-[10px] uppercase text-gray-500 font-bold tracking-wider">
                    <tr>
                        <th class="p-5 pl-6">Automation Rule</th>
                        <th class="p-5">Trigger</th>
                        <th class="p-5">Status</th>
                        <th class="p-5 text-right pr-6">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($workflows as $wf): 
                        $is_draft = ($wf->campaign_type === 'workflow_draft');
                        $config = json_decode($wf->config_json, true) ?: [];
                        
                        // Map Trigger to friendly name
                        $trigger_labels = [
                            'first_visit' => 'First Time Connect',
                            'birthday' => 'Guest Birthday',
                            'winback' => 'Missing Guest',
                            'tag' => 'Specific Campaign Tag'
                        ];
                        $trigger_type = $config['trigger_type'] ?? 'first_visit';
                        $trigger_name = $trigger_labels[$trigger_type] ?? 'Unknown Trigger';
                    ?>
                    <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition">
                        <td class="p-5 pl-6">
                            <p class="font-bold text-gray-900"><?php echo esc_html($wf->campaign_name); ?></p>
                            <?php if(!empty($config['location_id']) && $config['location_id'] !== 'all'): 
                                // Lookup Location name
                                $loc_name = 'Specific Location';
                                foreach($locations as $l) { if($l->id == $config['location_id']) $loc_name = $l->store_name; }
                            ?>
                                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-1"><i class="fa-solid fa-location-dot mr-1"></i> <?php echo esc_html($loc_name); ?></p>
                            <?php else: ?>
                                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-1"><i class="fa-solid fa-globe mr-1"></i> Global Reach</p>
                            <?php endif; ?>
                        </td>
                        <td class="p-5 font-medium text-gray-600">
                            <?php echo esc_html($trigger_name); ?>
                        </td>
                        <td class="p-5">
                            <?php if($is_draft): ?>
                                <span class="px-3 py-1 bg-gray-100 text-gray-600 border border-gray-200 text-[10px] font-black uppercase tracking-widest rounded-md">Draft / Paused</span>
                            <?php else: ?>
                                <span class="px-3 py-1 bg-green-50 text-green-700 border border-green-200 text-[10px] font-black uppercase tracking-widest rounded-md"><i class="fa-solid fa-bolt text-green-500 mr-1"></i> Active</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-5 pr-6 text-right flex justify-end gap-3">
                            <button onclick="editWorkflow(<?php echo $wf->id; ?>, this)" 
                                    data-name="<?php echo esc_attr($wf->campaign_name); ?>" 
                                    data-config="<?php echo esc_attr($wf->config_json); ?>" 
                                    class="w-8 h-8 rounded-lg bg-gray-50 text-gray-500 hover:bg-indigo-50 hover:text-indigo-600 transition flex items-center justify-center border border-gray-200" title="Edit Automation">
                                <i class="fa-solid fa-pen text-xs"></i>
                            </button>
                            <button onclick="trashWorkflow(<?php echo $wf->id; ?>)" class="w-8 h-8 rounded-lg bg-gray-50 text-gray-400 hover:bg-red-50 hover:text-red-500 hover:border-red-200 transition flex items-center justify-center border border-gray-200"><i class="fa-solid fa-trash-can text-xs"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div id="view_workflow_wizard" class="fixed inset-0 bg-[#f8fafc] z-[100] hidden flex-col font-sans overflow-hidden">
    
    <input type="hidden" id="wf_id" value="0">

    <div class="h-16 bg-white border-b border-gray-200 px-6 flex justify-between items-center shrink-0 z-30 shadow-sm">
        <div class="flex items-center gap-4">
            <button onclick="exitWizard()" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-500 transition" title="Exit">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div class="h-6 w-px bg-gray-200 mx-2"></div>
            <h2 class="text-lg font-black text-gray-900">Autopilot Setup</h2>
        </div>
        
        <div class="flex items-center gap-8">
            <div class="step-indicator active flex items-center gap-2" id="nav-step-1">
                <div class="step-icon w-6 h-6 rounded-full border-2 border-gray-300 text-[10px] flex items-center justify-center text-gray-400 font-bold transition-colors">1</div>
                <span class="text-xs font-bold text-gray-600 uppercase tracking-wide">The Trigger</span>
            </div>
            <div class="w-8 h-px bg-gray-200"></div>
            <div class="step-indicator flex items-center gap-2" id="nav-step-2">
                <div class="step-icon w-6 h-6 rounded-full border-2 border-gray-300 text-[10px] flex items-center justify-center text-gray-400 font-bold transition-colors">2</div>
                <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Flight Rules</span>
            </div>
            <div class="w-8 h-px bg-gray-200"></div>
            <div class="step-indicator flex items-center gap-2" id="nav-step-3">
                <div class="step-icon w-6 h-6 rounded-full border-2 border-gray-300 text-[10px] flex items-center justify-center text-gray-400 font-bold transition-colors">3</div>
                <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Plumage</span>
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
                <h2 class="text-3xl font-black text-gray-900 mb-2">The Trigger</h2>
                <p class="text-gray-500 mb-8">Name your rule and select the event that awakens this automation.</p>
                
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-8">
                    <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Automation Rule Name</label>
                    <input type="text" id="wf_name" class="w-full p-4 bg-gray-50 border border-gray-200 rounded-xl text-sm font-normal text-gray-800 placeholder-gray-400 focus:border-indigo-500 outline-none transition" placeholder="e.g. South Store Welcome Sequence">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <label class="block relative">
                        <input type="radio" name="wf_trigger" value="first_visit" class="custom-radio-input" checked>
                        <div class="custom-card bg-white rounded-2xl p-6 flex flex-col h-full">
                            <div class="flex justify-between items-start mb-4">
                                <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-wifi"></i></div>
                                <div class="radio-circle w-5 h-5 rounded-full border-2 border-gray-300 shrink-0"></div>
                            </div>
                            <h3 class="text-lg font-black text-gray-900 mb-1">First Time Connect</h3>
                            <p class="text-sm text-gray-500">Triggers the moment a brand new guest logs into your Captive Portal.</p>
                        </div>
                    </label>

                    <label class="block relative">
                        <input type="radio" name="wf_trigger" value="birthday" class="custom-radio-input">
                        <div class="custom-card bg-white rounded-2xl p-6 flex flex-col h-full">
                            <div class="flex justify-between items-start mb-4">
                                <div class="w-12 h-12 rounded-full bg-purple-50 text-purple-500 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-cake-candles"></i></div>
                                <div class="radio-circle w-5 h-5 rounded-full border-2 border-gray-300 shrink-0"></div>
                            </div>
                            <h3 class="text-lg font-black text-gray-900 mb-1">Guest Birthday</h3>
                            <p class="text-sm text-gray-500">Triggers annually based on the birthdate collected in The Roost.</p>
                        </div>
                    </label>

                    <label class="block relative">
                        <input type="radio" name="wf_trigger" value="winback" class="custom-radio-input">
                        <div class="custom-card bg-white rounded-2xl p-6 flex flex-col h-full">
                            <div class="flex justify-between items-start mb-4">
                                <div class="w-12 h-12 rounded-full bg-red-50 text-red-500 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-heart-crack"></i></div>
                                <div class="radio-circle w-5 h-5 rounded-full border-2 border-gray-300 shrink-0"></div>
                            </div>
                            <h3 class="text-lg font-black text-gray-900 mb-1">Missing Guest (Win-back)</h3>
                            <p class="text-sm text-gray-500">Triggers if a guest hasn't logged into your location in a specified amount of time.</p>
                        </div>
                    </label>

                    <label class="block relative">
                        <input type="radio" name="wf_trigger" value="tag" class="custom-radio-input">
                        <div class="custom-card bg-white rounded-2xl p-6 flex flex-col h-full">
                            <div class="flex justify-between items-start mb-4">
                                <div class="w-12 h-12 rounded-full bg-orange-50 text-orange-500 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-tag"></i></div>
                                <div class="radio-circle w-5 h-5 rounded-full border-2 border-gray-300 shrink-0"></div>
                            </div>
                            <h3 class="text-lg font-black text-gray-900 mb-1">Specific Campaign Tag</h3>
                            <p class="text-sm text-gray-500">Triggers when a guest signs up through a specific external vCard or promo link.</p>
                        </div>
                    </label>
                </div>
            </div>

            <div id="step-2" class="wizard-step">
                <h2 class="text-3xl font-black text-gray-900 mb-2">Flight Rules</h2>
                <p class="text-gray-500 mb-8">When should we drop this payload, and to which locations?</p>
                
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                    <div class="p-8">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center text-gray-400 text-xl shrink-0"><i class="fa-solid fa-stopwatch"></i></div>
                            <div class="flex-1">
                                <h3 class="font-bold text-gray-900">Timeline Delay</h3>
                                <p class="text-sm text-gray-500">How long to wait after the trigger fires before sending the email.</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <input type="number" id="wf_delay_val" value="0" class="w-24 p-3 border border-gray-300 rounded-lg text-lg font-normal text-gray-800 text-center outline-none focus:border-indigo-500">
                            <select id="wf_delay_unit" class="flex-1 p-3 border border-gray-300 rounded-lg text-sm font-bold text-gray-700 outline-none focus:border-indigo-500 bg-white cursor-pointer">
                                <option value="minutes">Minutes (0 = Instantly)</option>
                                <option value="hours">Hours</option>
                                <option value="days">Days</option>
                                <option value="days_before">Days Before (For Birthdays)</option>
                            </select>
                        </div>
                        
                        <div class="mt-6 p-4 bg-blue-50 rounded-xl border border-blue-100">
                            <p class="text-xs text-blue-700 font-medium leading-relaxed"><i class="fa-solid fa-circle-info mr-1"></i> <strong>Pro Tip:</strong> For "First Time Connect", setting this to 0 minutes will fire an instant Welcome Email while they are still in your venue looking at their phone.</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-8">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center text-gray-400 text-xl shrink-0"><i class="fa-solid fa-store"></i></div>
                            <div class="flex-1">
                                <h3 class="font-bold text-gray-900">Location Filter</h3>
                                <p class="text-sm text-gray-500">Only trigger this rule for guests who visited a specific venue.</p>
                            </div>
                        </div>
                        <select id="wf_location_filter" class="w-full p-4 bg-gray-50 border border-gray-200 rounded-xl text-sm font-bold text-gray-700 outline-none focus:border-indigo-500 shadow-sm cursor-pointer">
                            <option value="all">All Locations (Global Reach)</option>
                            <?php if(!empty($locations)): foreach($locations as $loc): ?>
                                <option value="<?php echo esc_attr($loc->id); ?>"><?php echo esc_html($loc->store_name); ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                </div>

            </div>

            <div id="step-3" class="wizard-step">
                <h2 class="text-3xl font-black text-gray-900 mb-2">Pick Your Plumage</h2>
                <p class="text-gray-500 mb-8">Select the design from Toucan Studio that this automation will deliver.</p>
                
                <input type="hidden" id="selected_template_id" value="">

                <?php if(empty($active_templates)): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 text-center py-16">
                        <i class="fa-solid fa-palette text-4xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-bold text-gray-700">No Saved Templates</h3>
                        <p class="text-sm text-gray-500 mb-4">You need to build a design in the Studio first.</p>
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
                                <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-widest font-bold">Subject: <?php echo esc_html($tpl->email_subject ?: 'No Subject'); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div id="step-4" class="wizard-step">
                <h2 class="text-3xl font-black text-gray-900 mb-2">Pre-Flight Check</h2>
                <p class="text-gray-500 mb-8">Review your automation rules before engaging Autopilot.</p>
                
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-8 space-y-6">
                        
                        <div class="flex items-start gap-4 pb-6 border-b border-gray-100">
                            <div class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 shrink-0"><i class="fa-solid fa-bolt"></i></div>
                            <div class="flex-1">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Trigger Event</p>
                                <p id="review_trigger" class="text-lg font-black text-gray-900">...</p>
                            </div>
                            <button onclick="goToStep(1)" class="text-xs font-bold text-indigo-600 hover:underline">Edit</button>
                        </div>

                        <div class="flex items-start gap-4 pb-6 border-b border-gray-100">
                            <div class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 shrink-0"><i class="fa-solid fa-stopwatch"></i></div>
                            <div class="flex-1">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Flight Rules</p>
                                <p id="review_delay" class="text-lg font-black text-gray-900">...</p>
                                <p id="review_location" class="text-sm text-gray-500 mt-1">...</p>
                            </div>
                            <button onclick="goToStep(2)" class="text-xs font-bold text-indigo-600 hover:underline">Edit</button>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 shrink-0"><i class="fa-solid fa-palette"></i></div>
                            <div class="flex-1">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Payload Design</p>
                                <p id="review_template" class="text-lg font-black text-gray-900">...</p>
                            </div>
                            <button onclick="goToStep(3)" class="text-xs font-bold text-indigo-600 hover:underline">Edit</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="h-20 bg-white border-t border-gray-200 px-10 flex justify-between items-center shrink-0 z-30 shadow-[0_-4px_15px_rgba(0,0,0,0.02)] relative">
        <button id="btn-prev" onclick="prevStep()" class="text-gray-500 font-bold px-4 py-2 rounded-lg hover:bg-gray-100 transition opacity-0 pointer-events-none flex items-center gap-2"><i class="fa-solid fa-arrow-left text-xs"></i> Back</button>
        
        <button id="btn-next" onclick="nextStep()" class="text-white px-8 py-3 rounded-xl font-black shadow-md hover:opacity-90 transition flex items-center gap-2 absolute right-10" style="background-color: var(--mt-brand);">Continue <i class="fa-solid fa-arrow-right text-xs"></i></button>
        
        <button id="btn-finish" onclick="activateAutopilot()" class="hidden text-white px-8 py-3 rounded-xl font-black shadow-lg transition flex items-center gap-2 absolute right-10 hover:opacity-90" style="background-color: var(--mt-brand);"><i class="fa-solid fa-power-off"></i> Engage Autopilot</button>
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
    // --- UI/UX TOASTS ---
    function showToast(message, type = 'success') {
        const container = document.getElementById('mt_toast_container');
        const toast = document.createElement('div');
        const bgColor = type === 'error' ? 'bg-red-600' : 'bg-gray-900';
        const icon = type === 'error' ? 'fa-triangle-exclamation' : 'fa-check-circle';
        
        let displayMessage = type === 'error' ? "Jungle Tangle: " + message : message;

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

    function editWorkflow(id, btnElement) {
        const name = btnElement.getAttribute('data-name');
        let config = {};
        try { config = JSON.parse(btnElement.getAttribute('data-config') || '{}'); } catch(e) {}

        // Repopulate Step 1
        document.getElementById('wf_id').value = id;
        document.getElementById('wf_name').value = name;
        
        const trigger = config.trigger_type || 'first_visit';
        const radio = document.querySelector(`input[name="wf_trigger"][value="${trigger}"]`);
        if(radio) radio.checked = true;

        // Repopulate Step 2
        document.getElementById('wf_delay_val').value = config.delay_val || 0;
        document.getElementById('wf_delay_unit').value = config.delay_unit || 'minutes';
        
        if(config.location_id) {
            document.getElementById('wf_location_filter').value = config.location_id;
        }

        // Repopulate Step 3
        document.querySelectorAll('.template-card').forEach(c => c.classList.remove('selected'));
        document.getElementById('selected_template_id').value = '';
        if(config.template_id) {
            const tplCard = document.querySelector(`.template-card[onclick*="selectTemplate(this, ${config.template_id})"]`);
            if(tplCard) {
                selectTemplate(tplCard, config.template_id);
            } else {
                document.getElementById('selected_template_id').value = config.template_id;
            }
        }

        // Open Wizard
        document.getElementById('view_workflow_list').style.display = 'none';
        document.getElementById('view_workflow_wizard').classList.remove('hidden');
        document.getElementById('view_workflow_wizard').classList.add('flex');
        
        goToStep(1);
    }

    function startWizard(id) {
        if(id === 0) {
            document.getElementById('wf_id').value = 0;
            document.getElementById('wf_name').value = '';
            document.querySelector('input[name="wf_trigger"][value="first_visit"]').checked = true;
            document.getElementById('wf_delay_val').value = 0;
            document.getElementById('wf_delay_unit').value = 'minutes';
            document.getElementById('wf_location_filter').value = 'all';
            document.querySelectorAll('.template-card').forEach(c => c.classList.remove('selected'));
            document.getElementById('selected_template_id').value = '';

            document.getElementById('view_workflow_list').style.display = 'none';
            document.getElementById('view_workflow_wizard').classList.remove('hidden');
            document.getElementById('view_workflow_wizard').classList.add('flex');
            
            goToStep(1);
            silentDraftSave(); // Auto save new rule
        }
    }

    function exitWizard() {
        mtConfirm("Abort Setup?", "Are you sure you want to exit? Your unsaved automation rules will be lost.", function() {
            window.location.reload();
        });
    }

    function silentDraftSave() {
        const now = new Date();
        const draftName = 'Automation Rule - ' + now.toLocaleDateString() + ' ' + now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        
        const fd = new FormData();
        fd.append('action', 'mt_save_campaign'); 
        fd.append('security', typeof mt_nonce !== 'undefined' ? mt_nonce : ''); 
        fd.append('campaign_id', 0); 
        fd.append('campaign_name', draftName);
        fd.append('campaign_type', 'workflow_draft');
        fd.append('config', JSON.stringify({})); 
        
        const ajaxUrl = typeof mt_ajax_url !== 'undefined' ? mt_ajax_url : '/wp-admin/admin-ajax.php';
        fetch(ajaxUrl, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            if(res.success) { document.getElementById('wf_id').value = res.data.campaign_id; }
        }).catch(err => console.error("Silent Draft Save Failed", err));
    }

    function saveDraftAndExit() {
        const btn = document.getElementById('btn_save_draft');
        btn.innerHTML = "Saving...";
        
        const id = document.getElementById('wf_id').value;
        const name = document.getElementById('wf_name').value || "Untitled Rule";
        
        const config = {
            trigger_type: document.querySelector('input[name="wf_trigger"]:checked')?.value || 'first_visit',
            delay_val: document.getElementById('wf_delay_val').value,
            delay_unit: document.getElementById('wf_delay_unit').value,
            location_id: document.getElementById('wf_location_filter').value,
            template_id: document.getElementById('selected_template_id').value
        };

        const fd = new FormData();
        fd.append('action', 'mt_save_campaign'); 
        fd.append('security', typeof mt_nonce !== 'undefined' ? mt_nonce : ''); 
        fd.append('campaign_id', id); 
        fd.append('campaign_name', name);
        fd.append('campaign_type', 'workflow_draft');
        fd.append('config', JSON.stringify(config));
        
        const ajaxUrl = typeof mt_ajax_url !== 'undefined' ? mt_ajax_url : '/wp-admin/admin-ajax.php';
        fetch(ajaxUrl, { method: 'POST', body: fd }).then(() => {
            showToast("Progress tucked safely into The Nest.", "success");
            setTimeout(() => { window.location.reload(); }, 1000);
        });
    }

    function trashWorkflow(id) {
        mtConfirm("Delete Automation", "Are you sure you want to permanently delete this background rule?", function() {
            const fd = new FormData(); 
            fd.append('action','mt_delete_campaign'); 
            fd.append('security', typeof mt_nonce !== 'undefined' ? mt_nonce : ''); 
            fd.append('campaign_id',id); 
            
            const ajaxUrl = typeof mt_ajax_url !== 'undefined' ? mt_ajax_url : '/wp-admin/admin-ajax.php';
            fetch(ajaxUrl,{method:'POST',body:fd}).then(()=>window.location.reload());
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
            if (currentStep === 1 && !document.getElementById('wf_name').value) {
                showToast("Please give this automation a name.", "error");
                return;
            }
            if (currentStep === 3 && !document.getElementById('selected_template_id').value) {
                showToast("Please pick a Payload design to deliver.", "error");
                return;
            }
        }

        // Hide all
        document.querySelectorAll('.wizard-step').forEach(el => el.classList.remove('active'));
        
        // Show target
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

        // Buttons
        const btnPrev = document.getElementById('btn-prev');
        const btnNext = document.getElementById('btn-next');
        const btnFinish = document.getElementById('btn-finish');

        if(currentStep === 1) {
            btnPrev.classList.remove('opacity-100', 'pointer-events-auto');
            btnPrev.classList.add('opacity-0', 'pointer-events-none');
        } else {
            btnPrev.classList.add('opacity-100', 'pointer-events-auto');
            btnPrev.classList.remove('opacity-0', 'pointer-events-none');
        }

        if(currentStep === totalSteps) {
            btnNext.style.display = 'none';
            btnFinish.style.display = 'flex';
            populateReviewScreen();
        } else {
            btnNext.style.display = 'flex';
            btnFinish.style.display = 'none';
        }
    }

    function nextStep() { if(currentStep < totalSteps) goToStep(currentStep + 1); }
    function prevStep() { if(currentStep > 1) goToStep(currentStep - 1); }

    function populateReviewScreen() {
        // Trigger
        const triggerLabels = {
            'first_visit': 'First Time Connect',
            'birthday': 'Guest Birthday',
            'winback': 'Missing Guest',
            'tag': 'Specific Campaign Tag'
        };
        const trigger = document.querySelector('input[name="wf_trigger"]:checked')?.value || 'first_visit';
        document.getElementById('review_trigger').innerText = triggerLabels[trigger];

        // Delay & Location
        const delayVal = document.getElementById('wf_delay_val').value;
        const delayUnit = document.getElementById('wf_delay_unit').options[document.getElementById('wf_delay_unit').selectedIndex].text;
        document.getElementById('review_delay').innerText = `Wait ${delayVal} ${delayUnit}`;

        let locFilterText = "All Locations (Global Reach)";
        const locSelect = document.getElementById('wf_location_filter');
        if (locSelect.value !== 'all') locFilterText = locSelect.options[locSelect.selectedIndex].text;
        document.getElementById('review_location').innerText = "Filtered to: " + locFilterText;

        // Template
        let templateText = "Selected Template ID: " + document.getElementById('selected_template_id').value;
        const selectedTplCard = document.querySelector('.template-card.selected h3');
        if(selectedTplCard) templateText = selectedTplCard.innerText;
        document.getElementById('review_template').innerText = templateText;
    }

    function activateAutopilot() {
        mtConfirm("Engage Autopilot?", "This automation will run silently in the background and deliver payloads based on these rules. Proceed?", function() {
            const btn = document.getElementById('btn-finish');
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Initializing...';
            btn.disabled = true;

            const id = document.getElementById('wf_id').value;
            const config = {
                trigger_type: document.querySelector('input[name="wf_trigger"]:checked')?.value || 'first_visit',
                delay_val: document.getElementById('wf_delay_val').value,
                delay_unit: document.getElementById('wf_delay_unit').value,
                location_id: document.getElementById('wf_location_filter').value,
                template_id: document.getElementById('selected_template_id').value
            };

            const fd = new FormData();
            fd.append('action', 'mt_save_campaign'); 
            fd.append('security', typeof mt_nonce !== 'undefined' ? mt_nonce : ''); 
            fd.append('campaign_id', id); 
            fd.append('campaign_name', document.getElementById('wf_name').value);
            fd.append('campaign_type', 'workflow'); // Flags it as ACTIVE
            fd.append('config', JSON.stringify(config));
            
            const ajaxUrl = typeof mt_ajax_url !== 'undefined' ? mt_ajax_url : '/wp-admin/admin-ajax.php';
            fetch(ajaxUrl, { method: 'POST', body: fd }).then(() => {
                showToast("Autopilot Rules Generated! The engine is humming.", "success");
                setTimeout(() => { window.location.reload(); }, 1500);
            });
        });
    }
</script>