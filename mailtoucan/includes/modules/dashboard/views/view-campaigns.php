<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_templates = $wpdb->prefix . 'mt_email_templates';

// Fetch the user's saved templates to display in Step 3
$active_templates = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table_templates WHERE brand_id = %d AND status = 'active' ORDER BY created_at DESC", $brand->id) );

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
        <button onclick="startWizard()" class="bg-gray-900 text-white px-6 py-3 rounded-xl font-bold shadow-lg hover:bg-black transition flex items-center gap-2">
            <i class="fa-solid fa-paper-plane"></i> Create Campaign
        </button>
    </header>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden text-center py-24">
        <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-gray-100">
            <i class="fa-solid fa-satellite-dish text-3xl text-gray-300"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-700">No campaigns yet</h3>
        <p class="text-sm text-gray-500 mb-6">Create your first email blast to engage your audience.</p>
        <button onclick="startWizard()" class="text-indigo-600 font-bold hover:text-indigo-800 transition">Get Started &rarr;</button>
    </div>
</div>

<div id="view_campaign_wizard" class="fixed inset-0 bg-[#f8fafc] z-[100] hidden flex-col font-sans overflow-hidden">
    
    <div class="h-16 bg-white border-b border-gray-200 px-6 flex justify-between items-center shrink-0 z-30 shadow-sm">
        <div class="flex items-center gap-4">
            <button onclick="exitWizard()" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-500 transition" title="Exit">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div class="h-6 w-px bg-gray-200 mx-2"></div>
            <h2 class="text-lg font-black text-gray-900">New Campaign</h2>
        </div>
        
        <div class="flex items-center gap-8">
            <div class="step-indicator active flex items-center gap-2" id="nav-step-1">
                <div class="step-icon w-6 h-6 rounded-full border-2 border-gray-300 text-[10px] flex items-center justify-center text-gray-400 font-bold transition-colors">1</div>
                <span class="text-xs font-bold text-gray-600 uppercase tracking-wide">Setup</span>
            </div>
            <div class="w-8 h-px bg-gray-200"></div>
            <div class="step-indicator flex items-center gap-2" id="nav-step-2">
                <div class="step-icon w-6 h-6 rounded-full border-2 border-gray-300 text-[10px] flex items-center justify-center text-gray-400 font-bold transition-colors">2</div>
                <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Audience</span>
            </div>
            <div class="w-8 h-px bg-gray-200"></div>
            <div class="step-indicator flex items-center gap-2" id="nav-step-3">
                <div class="step-icon w-6 h-6 rounded-full border-2 border-gray-300 text-[10px] flex items-center justify-center text-gray-400 font-bold transition-colors">3</div>
                <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Design</span>
            </div>
            <div class="w-8 h-px bg-gray-200"></div>
            <div class="step-indicator flex items-center gap-2" id="nav-step-4">
                <div class="step-icon w-6 h-6 rounded-full border-2 border-gray-300 text-[10px] flex items-center justify-center text-gray-400 font-bold transition-colors">4</div>
                <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Review</span>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button onclick="saveDraftAndExit()" class="text-gray-500 hover:text-gray-900 text-sm font-bold transition mr-4">Save & Exit</button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-10 relative">
        <div class="max-w-4xl mx-auto pb-24">

            <div id="step-1" class="wizard-step active">
                <h2 class="text-3xl font-black text-gray-900 mb-2">Campaign Setup</h2>
                <p class="text-gray-500 mb-8">Define the core details for this email blast.</p>
                
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 space-y-6">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Internal Campaign Name</label>
                        <input type="text" id="camp_name" class="w-full p-4 bg-gray-50 border border-gray-200 rounded-xl text-sm font-bold focus:border-indigo-500 outline-none transition" placeholder="e.g. October 2026 Promo (Not seen by customers)">
                    </div>
                    <div class="border-t border-gray-100 pt-6">
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Email Subject Line</label>
                        <input type="text" id="camp_subject" class="w-full p-4 bg-white border border-gray-300 rounded-xl text-lg font-black focus:border-indigo-500 outline-none transition shadow-sm" placeholder="What will make them click?">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Preview Text (Snippet)</label>
                        <input type="text" id="camp_preview" class="w-full p-4 bg-white border border-gray-300 rounded-xl text-sm font-medium focus:border-indigo-500 outline-none transition shadow-sm" placeholder="A brief summary that appears next to the subject line...">
                    </div>
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-200 flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-user-tie"></i></div>
                        <div>
                            <p class="text-[10px] uppercase font-bold text-gray-400 tracking-widest">Sending As</p>
                            <p class="text-sm font-bold text-gray-900"><?php echo esc_html($brand->brand_name); ?> &lt;<?php echo esc_html($sender_email); ?>&gt;</p>
                        </div>
                    </div>
                </div>
            </div>

            <div id="step-2" class="wizard-step">
                <h2 class="text-3xl font-black text-gray-900 mb-2">Select Audience</h2>
                <p class="text-gray-500 mb-8">Who are we sending this campaign to?</p>
                
                <div class="space-y-4">
                    <label class="block relative">
                        <input type="radio" name="audience" value="all" class="audience-radio" checked>
                        <div class="audience-card bg-white rounded-2xl p-6 flex items-center gap-6">
                            <div class="radio-circle w-5 h-5 rounded-full border-2 border-gray-300 shrink-0"></div>
                            <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-users"></i></div>
                            <div class="flex-1">
                                <h3 class="text-lg font-black text-gray-900">All Captured Guests</h3>
                                <p class="text-sm text-gray-500">Send to every contact in your Toucan CRM.</p>
                            </div>
                            <div class="text-right">
                                <span class="block text-2xl font-black text-gray-900">2,451</span>
                                <span class="text-[10px] uppercase tracking-widest font-bold text-gray-400">Recipients</span>
                            </div>
                        </div>
                    </label>

                    <label class="block relative">
                        <input type="radio" name="audience" value="recent" class="audience-radio">
                        <div class="audience-card bg-white rounded-2xl p-6 flex items-center gap-6">
                            <div class="radio-circle w-5 h-5 rounded-full border-2 border-gray-300 shrink-0"></div>
                            <div class="w-12 h-12 rounded-full bg-green-50 text-green-500 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-wifi"></i></div>
                            <div class="flex-1">
                                <h3 class="text-lg font-black text-gray-900">Recent WiFi Logins</h3>
                                <p class="text-sm text-gray-500">Guests who visited your location in the last 30 days.</p>
                            </div>
                            <div class="text-right">
                                <span class="block text-2xl font-black text-gray-900">342</span>
                                <span class="text-[10px] uppercase tracking-widest font-bold text-gray-400">Recipients</span>
                            </div>
                        </div>
                    </label>

                    <label class="block relative">
                        <input type="radio" name="audience" value="birthday" class="audience-radio">
                        <div class="audience-card bg-white rounded-2xl p-6 flex items-center gap-6">
                            <div class="radio-circle w-5 h-5 rounded-full border-2 border-gray-300 shrink-0"></div>
                            <div class="w-12 h-12 rounded-full bg-purple-50 text-purple-500 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-cake-candles"></i></div>
                            <div class="flex-1">
                                <h3 class="text-lg font-black text-gray-900">Birthday Month</h3>
                                <p class="text-sm text-gray-500">Guests whose birthday is in the current month.</p>
                            </div>
                            <div class="text-right">
                                <span class="block text-2xl font-black text-gray-900">87</span>
                                <span class="text-[10px] uppercase tracking-widest font-bold text-gray-400">Recipients</span>
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            <div id="step-3" class="wizard-step">
                <h2 class="text-3xl font-black text-gray-900 mb-2">Choose Design</h2>
                <p class="text-gray-500 mb-8">Select a template you built in the Toucan Studio.</p>
                
                <input type="hidden" id="selected_template_id" value="">

                <?php if(empty($active_templates)): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 text-center py-16">
                        <i class="fa-solid fa-palette text-4xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-bold text-gray-700">No Saved Templates</h3>
                        <p class="text-sm text-gray-500 mb-4">You need to build a design in the Studio first.</p>
                        <button onclick="exitWizard()" class="text-indigo-600 font-bold hover:underline">Go to Studio</button>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-3 gap-6">
                        <?php foreach($active_templates as $tpl): ?>
                        <div class="template-card bg-white rounded-2xl overflow-hidden shadow-sm relative group" onclick="selectTemplate(this, <?php echo $tpl->id; ?>)">
                            <div class="select-check absolute top-3 right-3 w-6 h-6 bg-[color:var(--mt-brand)] text-white rounded-full flex items-center justify-center opacity-0 scale-50 transition-all shadow-md z-10">
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
                <h2 class="text-3xl font-black text-gray-900 mb-2">Review & Send</h2>
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
                        <button onclick="alert('Sending test email...')" class="bg-white border border-gray-300 text-gray-700 px-6 py-3 rounded-xl font-bold shadow-sm hover:bg-gray-50 transition flex items-center gap-2"><i class="fa-regular fa-paper-plane"></i> Send Test</button>
                        <div class="flex gap-3">
                            <button onclick="alert('Scheduling coming soon!')" class="bg-indigo-50 text-indigo-700 border border-indigo-200 px-6 py-3 rounded-xl font-bold shadow-sm hover:bg-indigo-100 transition"><i class="fa-regular fa-clock mr-2"></i>Schedule Later</button>
                            <button onclick="launchCampaign()" id="btn_blast" class="bg-gray-900 text-white px-8 py-3 rounded-xl font-black shadow-lg hover:bg-black transition flex items-center gap-2"><i class="fa-solid fa-rocket"></i> Send Now</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="h-20 bg-white border-t border-gray-200 px-10 flex justify-between items-center shrink-0 z-30 shadow-[0_-4px_15px_rgba(0,0,0,0.02)] relative">
        <button id="btn-prev" onclick="prevStep()" class="text-gray-500 font-bold px-4 py-2 rounded-lg hover:bg-gray-100 transition opacity-0 pointer-events-none flex items-center gap-2"><i class="fa-solid fa-arrow-left text-xs"></i> Back</button>
        <button id="btn-next" onclick="nextStep()" class="bg-[color:var(--mt-brand)] text-white px-8 py-3 rounded-xl font-black shadow-md hover:opacity-90 transition flex items-center gap-2 absolute right-10">Continue <i class="fa-solid fa-arrow-right text-xs"></i></button>
    </div>

</div>

<div id="modal_success" class="fixed inset-0 bg-gray-900/80 z-[200] hidden items-center justify-center backdrop-blur-sm">
    <div class="bg-white rounded-3xl p-10 text-center max-w-sm transform scale-95 transition-transform" id="success_box">
        <div class="w-24 h-24 bg-green-100 text-green-500 rounded-full flex items-center justify-center mx-auto mb-6"><i class="fa-solid fa-check text-5xl"></i></div>
        <h2 class="text-3xl font-black text-gray-900 mb-2">Campaign Sent!</h2>
        <p class="text-gray-500 mb-8">Your email is blasting off to the servers right now.</p>
        <button onclick="window.location.reload()" class="w-full bg-gray-900 text-white py-4 rounded-xl font-black hover:bg-black transition">Back to Dashboard</button>
    </div>
</div>

<script>
    let currentStep = 1;
    const totalSteps = 4;

    function startWizard() {
        document.getElementById('view_campaign_list').style.display = 'none';
        document.getElementById('view_campaign_wizard').classList.remove('hidden');
        document.getElementById('view_campaign_wizard').classList.add('flex');
    }

    function exitWizard() {
        if(confirm("Are you sure? Your progress will be lost.")) {
            window.location.reload();
        }
    }

    function saveDraftAndExit() {
        alert("Draft saved! (Functionality coming soon)");
        window.location.reload();
    }

    function selectTemplate(element, id) {
        document.querySelectorAll('.template-card').forEach(c => c.classList.remove('selected'));
        element.classList.add('selected');
        document.getElementById('selected_template_id').value = id;
    }

    function goToStep(step) {
        // Validation before advancing
        if (step > currentStep) {
            if (currentStep === 1) {
                if(!document.getElementById('camp_name').value || !document.getElementById('camp_subject').value) {
                    alert("Please fill out the Name and Subject Line first.");
                    return;
                }
            }
            if (currentStep === 3) {
                if(!document.getElementById('selected_template_id').value) {
                    alert("Please select a design template to continue.");
                    return;
                }
            }
        }

        // Hide all steps
        document.querySelectorAll('.wizard-step').forEach(el => {
            el.classList.remove('active');
        });
        
        // Show target step
        currentStep = step;
        document.getElementById('step-' + currentStep).classList.add('active');

        // Update Top Navigation UI
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

        // Update Bottom Buttons
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
            btnNext.style.display = 'none'; // Hide "Continue" on final step
            populateReviewScreen();
        } else {
            btnNext.style.display = 'flex';
        }
    }

    function nextStep() {
        if(currentStep < totalSteps) goToStep(currentStep + 1);
    }

    function prevStep() {
        if(currentStep > 1) goToStep(currentStep - 1);
    }

    function populateReviewScreen() {
        // Grab data from previous steps
        const subject = document.getElementById('camp_subject').value;
        const preview = document.getElementById('camp_preview').value;
        
        let audienceText = "All Captured Guests";
        const selectedRadio = document.querySelector('.audience-radio:checked');
        if(selectedRadio && selectedRadio.nextElementSibling) {
            audienceText = selectedRadio.nextElementSibling.querySelector('h3').innerText;
        }

        let templateText = "Selected Template ID: " + document.getElementById('selected_template_id').value;
        const selectedTplCard = document.querySelector('.template-card.selected h3');
        if(selectedTplCard) templateText = selectedTplCard.innerText;

        // Inject into Review Step
        document.getElementById('review_subject').innerText = subject;
        document.getElementById('review_preview').innerText = preview || 'No preview text provided.';
        document.getElementById('review_audience').innerText = audienceText;
        document.getElementById('review_template').innerText = templateText;
    }

    function launchCampaign() {
        const btn = document.getElementById('btn_blast');
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Preparing...';
        btn.disabled = true;

        // Simulate an API call delay for dramatic effect
        setTimeout(() => {
            document.getElementById('modal_success').classList.remove('hidden');
            document.getElementById('modal_success').classList.add('flex');
            setTimeout(() => document.getElementById('success_box').classList.remove('scale-95'), 50);
        }, 1500);
    }
</script>