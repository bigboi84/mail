<?php
/**
 * MailToucan Onboarding Wizard
 * Auto-fires on first login. Adapts to package flow: wifi / email / both / custom.
 * $ob_flow and $brand are available from the dashboard shell.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$ob_brand_name   = esc_js($brand->brand_name ?? '');
$ob_brand_color  = esc_js($brand->primary_color ?? '#0f172a');
$ob_brand_config = json_decode($brand->brand_config ?? '{}', true) ?: [];
$ob_website      = esc_js($ob_brand_config['url'] ?? '');
$ob_address      = esc_js($ob_brand_config['hq_address'] ?? '');
$ob_from_email   = esc_js($ob_brand_config['delivery']['from_email'] ?? '');
$ob_from_name    = esc_js($ob_brand_config['delivery']['from_name'] ?? $brand->brand_name ?? '');

// Build step list per flow
$wifi_steps  = ['welcome','brand','location','splash_intro','done'];
$email_steps = ['welcome','brand','sender','dns','done'];
$both_steps  = ['welcome','brand','location','splash_intro','sender','dns','done'];
$custom_steps= ['welcome','brand','tour','done'];
$steps       = match($ob_flow) {
    'wifi'  => $wifi_steps,
    'email' => $email_steps,
    'both'  => $both_steps,
    default => $custom_steps,
};
$steps_json = wp_json_encode($steps);

$step_labels = [
    'welcome'      => 'Welcome',
    'brand'        => 'Brand Setup',
    'location'     => 'Your Location',
    'splash_intro' => 'Splash Page',
    'sender'       => 'Sender Identity',
    'dns'          => 'Domain & DNS',
    'tour'         => 'Platform Tour',
    'done'         => 'All Set!',
];
?>

<!-- ═══════════════════════════════════════════════════════════════
     ONBOARDING WIZARD OVERLAY — auto-mounts, full-screen modal
════════════════════════════════════════════════════════════════ -->
<div id="mt_onboarding_overlay"
     class="fixed inset-0 z-[500] flex items-center justify-center"
     style="background:rgba(15,23,42,0.85);backdrop-filter:blur(4px);">

<div id="mt_onboarding_box"
     class="relative bg-white rounded-3xl shadow-2xl w-full overflow-hidden flex flex-col"
     style="max-width:680px;max-height:90vh;">

    <!-- Progress bar -->
    <div class="h-1.5 bg-gray-100 shrink-0">
        <div id="ob_progress_bar" class="h-full rounded-full transition-all duration-500"
             style="width:0%;background:<?php echo esc_attr($brand->primary_color ?? '#4f46e5'); ?>;"></div>
    </div>

    <!-- Header -->
    <div class="flex items-center justify-between px-8 pt-6 pb-4 border-b border-gray-100 shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-sm font-black"
                 style="background:<?php echo esc_attr($brand->primary_color ?? '#4f46e5'); ?>">
                <i class="fa-solid fa-dove"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">MailToucan Setup</p>
                <p id="ob_step_label" class="text-sm font-black text-gray-900">Welcome</p>
            </div>
        </div>
        <button onclick="obSkip()" class="text-xs font-bold text-gray-400 hover:text-gray-700 transition px-3 py-1.5 rounded-lg hover:bg-gray-100">
            Skip Setup →
        </button>
    </div>

    <!-- Step breadcrumbs -->
    <div class="flex items-center gap-1.5 px-8 py-3 overflow-x-auto shrink-0 border-b border-gray-50">
        <?php foreach ($steps as $i => $s): ?>
        <div class="ob-breadcrumb flex items-center gap-1.5 shrink-0" data-step="<?php echo $i; ?>">
            <div class="ob-dot w-2 h-2 rounded-full bg-gray-200 transition-all"></div>
            <?php if ($i < count($steps) - 1): ?>
                <div class="w-4 h-px bg-gray-200 shrink-0"></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Scrollable step content area -->
    <div class="flex-1 overflow-y-auto">
        <div id="ob_steps_wrapper" class="min-h-full">

            <!-- STEP: welcome -->
            <div class="ob-step px-8 py-10" data-step-id="welcome">
                <div class="text-center mb-8">
                    <div class="w-20 h-20 rounded-2xl flex items-center justify-center mx-auto mb-5 text-white text-4xl"
                         style="background:<?php echo esc_attr($brand->primary_color ?? '#4f46e5'); ?>">
                        <i class="fa-solid fa-dove"></i>
                    </div>
                    <h2 class="text-3xl font-black text-gray-900 mb-2">Welcome to MailToucan!</h2>
                    <p class="text-gray-500 text-base max-w-md mx-auto leading-relaxed">
                        Let's get your account set up in a few quick steps. This only takes a few minutes and you can always come back to it.
                    </p>
                </div>
                <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100 max-w-md mx-auto">
                    <p class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-4">Your setup includes:</p>
                    <div class="space-y-3 text-sm font-semibold text-gray-700">
                        <?php if (in_array($ob_flow, ['wifi','both','custom'])): ?>
                        <div class="flex items-center gap-3"><div class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-wifi text-xs"></i></div> WiFi Marketing Setup</div>
                        <?php endif; ?>
                        <?php if (in_array($ob_flow, ['email','both'])): ?>
                        <div class="flex items-center gap-3"><div class="w-6 h-6 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-envelope text-xs"></i></div> Email Sending Identity</div>
                        <div class="flex items-center gap-3"><div class="w-6 h-6 rounded-full bg-green-100 text-green-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-shield-halved text-xs"></i></div> Domain DNS (AI-Assisted)</div>
                        <?php endif; ?>
                        <div class="flex items-center gap-3"><div class="w-6 h-6 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-palette text-xs"></i></div> Brand Identity</div>
                    </div>
                </div>
            </div>

            <!-- STEP: brand -->
            <div class="ob-step px-8 py-8 hidden" data-step-id="brand">
                <h2 class="text-2xl font-black text-gray-900 mb-1">Brand Identity</h2>
                <p class="text-gray-500 text-sm mb-7">Tell us about your business. This information is used across your campaigns and splash pages.</p>
                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Business Name</label>
                        <input type="text" id="ob_brand_name" class="ob-input" value="<?php echo esc_attr($brand->brand_name ?? ''); ?>" placeholder="e.g. The Corner Café">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Brand Color</label>
                        <div class="flex items-center gap-3">
                            <input type="color" id="ob_brand_color" value="<?php echo esc_attr($brand->primary_color ?? '#4f46e5'); ?>" class="w-12 h-12 p-1 border border-gray-200 rounded-lg cursor-pointer">
                            <input type="text" id="ob_brand_color_text" value="<?php echo esc_attr($brand->primary_color ?? '#4f46e5'); ?>" class="ob-input flex-1 font-mono" placeholder="#4f46e5">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Website URL <span class="text-gray-300 font-normal normal-case">(optional)</span></label>
                        <input type="url" id="ob_website" class="ob-input" value="<?php echo esc_attr($ob_brand_config['url'] ?? ''); ?>" placeholder="https://yourwebsite.com">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Business Address <span class="text-gray-300 font-normal normal-case">(for email footer)</span></label>
                        <input type="text" id="ob_address" class="ob-input" value="<?php echo esc_attr($ob_brand_config['hq_address'] ?? ''); ?>" placeholder="123 Main St, City, State 00000">
                    </div>
                </div>
            </div>

            <!-- STEP: location (WiFi / Both flows) -->
            <div class="ob-step px-8 py-8 hidden" data-step-id="location">
                <h2 class="text-2xl font-black text-gray-900 mb-1">Your First Location</h2>
                <p class="text-gray-500 text-sm mb-7">Add your first venue. You can add more locations later from the Locations section.</p>
                <div class="bg-blue-50 border border-blue-200 rounded-2xl p-6 text-sm text-blue-800 mb-6 flex items-start gap-3">
                    <i class="fa-solid fa-circle-info mt-0.5 shrink-0"></i>
                    <div>
                        <p class="font-bold">After setup, go to <strong>WiFi Marketing → Locations</strong> to add your router SSID and configure the captive portal.</p>
                        <p class="mt-1 text-blue-600">Each location gets its own splash page and WiFi session settings.</p>
                    </div>
                </div>
                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Location / Venue Name</label>
                        <input type="text" id="ob_location_name" class="ob-input" placeholder="e.g. Downtown Branch, HQ, Main Store">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Location Address</label>
                        <input type="text" id="ob_location_address" class="ob-input" placeholder="123 Main St, City">
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-4">You can skip this and add your location later — it won't block the rest of setup.</p>
            </div>

            <!-- STEP: splash_intro (WiFi / Both flows) -->
            <div class="ob-step px-8 py-8 hidden" data-step-id="splash_intro">
                <h2 class="text-2xl font-black text-gray-900 mb-1">Splash Page Designer</h2>
                <p class="text-gray-500 text-sm mb-7">Your splash page is the branded WiFi login screen guests see when they connect.</p>
                <div class="grid grid-cols-2 gap-5 mb-6">
                    <div class="bg-white border-2 border-gray-100 rounded-2xl p-5">
                        <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center mb-3"><i class="fa-solid fa-wifi"></i></div>
                        <h3 class="font-bold text-gray-900 text-sm mb-1">Captive Portal</h3>
                        <p class="text-xs text-gray-500 leading-relaxed">Guests connect to your WiFi and are shown your branded page before getting internet access.</p>
                    </div>
                    <div class="bg-white border-2 border-gray-100 rounded-2xl p-5">
                        <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center mb-3"><i class="fa-solid fa-user-plus"></i></div>
                        <h3 class="font-bold text-gray-900 text-sm mb-1">Lead Capture</h3>
                        <p class="text-xs text-gray-500 leading-relaxed">Guests enter their name, email, and birthday. Each submission adds them to your CRM automatically.</p>
                    </div>
                    <div class="bg-white border-2 border-gray-100 rounded-2xl p-5">
                        <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center mb-3"><i class="fa-solid fa-palette"></i></div>
                        <h3 class="font-bold text-gray-900 text-sm mb-1">Fully Branded</h3>
                        <p class="text-xs text-gray-500 leading-relaxed">Your logo, brand colors, and custom offer message. Use the AI copy tool to generate headlines instantly.</p>
                    </div>
                    <div class="bg-white border-2 border-gray-100 rounded-2xl p-5">
                        <div class="w-10 h-10 rounded-xl bg-green-100 text-green-600 flex items-center justify-center mb-3"><i class="fa-solid fa-arrow-pointer"></i></div>
                        <h3 class="font-bold text-gray-900 text-sm mb-1">Where to Find It</h3>
                        <p class="text-xs text-gray-500 leading-relaxed">Go to <strong>WiFi Marketing → Splash Designer</strong> in the sidebar to customise your page.</p>
                    </div>
                </div>
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800 flex items-center gap-3">
                    <i class="fa-solid fa-lightbulb shrink-0"></i>
                    <span>Tip: Use the <strong>AI Copy button</strong> on the splash designer to auto-write your headline and CTA in seconds.</span>
                </div>
            </div>

            <!-- STEP: sender (Email / Both flows) -->
            <div class="ob-step px-8 py-8 hidden" data-step-id="sender">
                <h2 class="text-2xl font-black text-gray-900 mb-1">Sender Identity</h2>
                <p class="text-gray-500 text-sm mb-7">This is the "From" name and address that appears in your guests' inboxes. Use your business email for best deliverability.</p>
                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">From Name</label>
                        <input type="text" id="ob_from_name" class="ob-input" value="<?php echo esc_attr($ob_brand_config['delivery']['from_name'] ?? $brand->brand_name ?? ''); ?>" placeholder="e.g. The Corner Café">
                        <p class="text-[11px] text-gray-400 mt-1">This is the name guests see before opening your email.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">From Email Address</label>
                        <input type="email" id="ob_from_email" class="ob-input" value="<?php echo esc_attr($ob_brand_config['delivery']['from_email'] ?? ''); ?>" placeholder="hello@yourdomain.com">
                        <p class="text-[11px] text-gray-400 mt-1">Must be on a domain you control. You'll verify it in the next step.</p>
                    </div>
                </div>
                <div class="mt-6 bg-gray-50 rounded-xl p-4 border border-gray-100 text-sm text-gray-600">
                    <p class="font-bold text-gray-700 mb-1"><i class="fa-solid fa-circle-info text-blue-400 mr-1"></i> Why does this matter?</p>
                    <p class="text-xs leading-relaxed">Sending from a verified domain dramatically improves inbox delivery rates and prevents your emails from landing in spam. We'll walk you through adding the DNS records in the next step.</p>
                </div>
            </div>

            <!-- STEP: dns (Email / Both flows — AI Chat) -->
            <div class="ob-step px-8 py-8 hidden" data-step-id="dns">
                <h2 class="text-2xl font-black text-gray-900 mb-1">Domain Verification</h2>
                <p class="text-gray-500 text-sm mb-5">Our AI assistant will guide you through adding the DNS records needed to authenticate your sender domain. Just tell it your domain name and follow the steps.</p>

                <!-- DNS Chat Interface -->
                <div class="border border-gray-200 rounded-2xl overflow-hidden" style="height:340px;display:flex;flex-direction:column;">
                    <div class="bg-gray-50 px-4 py-2.5 border-b border-gray-100 flex items-center gap-2 shrink-0">
                        <div class="w-2 h-2 rounded-full bg-green-400"></div>
                        <span class="text-xs font-bold text-gray-600">Toucan AI — DNS Guide</span>
                        <span class="ml-auto text-[10px] text-gray-400">Messages stay private and are never stored</span>
                    </div>
                    <div id="ob_dns_chat" class="flex-1 overflow-y-auto p-4 space-y-3">
                        <!-- Initial AI message -->
                        <div class="flex items-start gap-2.5">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs shrink-0 mt-0.5"
                                 style="background:<?php echo esc_attr($brand->primary_color ?? '#4f46e5'); ?>">
                                <i class="fa-solid fa-dove"></i>
                            </div>
                            <div class="bg-gray-100 rounded-2xl rounded-tl-sm px-4 py-3 max-w-sm">
                                <p class="text-sm text-gray-800 leading-relaxed">Hi! I'll guide you through verifying your sender domain. To get started, tell me: <strong>what is the domain you want to send email from?</strong> (e.g. "mycafe.com")</p>
                            </div>
                        </div>
                    </div>
                    <div class="border-t border-gray-100 p-3 shrink-0">
                        <div class="flex items-center gap-2">
                            <input type="text" id="ob_dns_input"
                                   class="flex-1 bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-gray-400 transition"
                                   placeholder="Type here... (e.g. mycafe.com)"
                                   onkeydown="if(event.key==='Enter')obDnsSend()">
                            <button onclick="obDnsSend()" id="ob_dns_send_btn"
                                    class="w-9 h-9 rounded-xl flex items-center justify-center text-white shrink-0 transition hover:opacity-90"
                                    style="background:<?php echo esc_attr($brand->primary_color ?? '#4f46e5'); ?>">
                                <i class="fa-solid fa-paper-plane text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-4 bg-blue-50 border border-blue-100 rounded-xl p-3 text-xs text-blue-700 flex items-start gap-2">
                    <i class="fa-solid fa-circle-info shrink-0 mt-0.5"></i>
                    <span>DNS changes can take up to 48 hours to propagate. You can complete setup now and verify later from <strong>Email Marketing → Sender Domains</strong>.</span>
                </div>
            </div>

            <!-- STEP: tour (Generic / Custom flow) -->
            <div class="ob-step px-8 py-8 hidden" data-step-id="tour">
                <h2 class="text-2xl font-black text-gray-900 mb-1">Your Platform Tour</h2>
                <p class="text-gray-500 text-sm mb-7">Here's a quick overview of what's available in your account.</p>
                <div class="space-y-3">
                    <div class="flex items-start gap-4 p-4 bg-white border border-gray-100 rounded-2xl hover:shadow-sm transition">
                        <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-chart-pie"></i></div>
                        <div><h3 class="font-bold text-gray-900 text-sm">Account Status</h3><p class="text-xs text-gray-500 mt-0.5">Monitor your plan usage, email quota, and key metrics at a glance.</p></div>
                    </div>
                    <div class="flex items-start gap-4 p-4 bg-white border border-gray-100 rounded-2xl hover:shadow-sm transition">
                        <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-palette"></i></div>
                        <div><h3 class="font-bold text-gray-900 text-sm">Brand Identity</h3><p class="text-xs text-gray-500 mt-0.5">Upload your logo, set your brand colors, and configure your business details.</p></div>
                    </div>
                    <div class="flex items-start gap-4 p-4 bg-white border border-gray-100 rounded-2xl hover:shadow-sm transition">
                        <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                        <div><h3 class="font-bold text-gray-900 text-sm">Toucan Studio</h3><p class="text-xs text-gray-500 mt-0.5">Design beautiful email templates with our drag-and-drop builder or AI template generator.</p></div>
                    </div>
                    <div class="flex items-start gap-4 p-4 bg-white border border-gray-100 rounded-2xl hover:shadow-sm transition">
                        <div class="w-10 h-10 rounded-xl bg-green-100 text-green-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-key"></i></div>
                        <div><h3 class="font-bold text-gray-900 text-sm">API & Credits</h3><p class="text-xs text-gray-500 mt-0.5">View your AI credit usage or connect your own API keys to remove limits.</p></div>
                    </div>
                </div>
            </div>

            <!-- STEP: done -->
            <div class="ob-step px-8 py-10 hidden" data-step-id="done">
                <div class="text-center">
                    <div class="w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-5xl"
                         style="background:<?php echo esc_attr($brand->primary_color ?? '#4f46e5'); ?>">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <h2 class="text-3xl font-black text-gray-900 mb-3">You're All Set!</h2>
                    <p class="text-gray-500 text-base max-w-sm mx-auto leading-relaxed mb-8">
                        Your account is configured and ready to go. Here's what you can do next:
                    </p>
                    <div class="text-left space-y-3 max-w-sm mx-auto mb-8">
                        <?php if (in_array($ob_flow, ['wifi','both','custom'])): ?>
                        <a href="?view=splash" class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl border border-gray-100 hover:border-gray-300 transition text-sm font-semibold text-gray-700">
                            <i class="fa-solid fa-wifi text-blue-500 w-5 text-center"></i> Customise your Splash Page
                        </a>
                        <?php endif; ?>
                        <?php if (in_array($ob_flow, ['email','both'])): ?>
                        <a href="?view=studio" class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl border border-gray-100 hover:border-gray-300 transition text-sm font-semibold text-gray-700">
                            <i class="fa-solid fa-wand-magic-sparkles text-purple-500 w-5 text-center"></i> Design your first email template
                        </a>
                        <a href="?view=domains" class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl border border-gray-100 hover:border-gray-300 transition text-sm font-semibold text-gray-700">
                            <i class="fa-solid fa-globe text-green-500 w-5 text-center"></i> Verify your sender domain
                        </a>
                        <?php endif; ?>
                        <a href="?view=brand" class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl border border-gray-100 hover:border-gray-300 transition text-sm font-semibold text-gray-700">
                            <i class="fa-solid fa-palette text-amber-500 w-5 text-center"></i> Upload your brand logo
                        </a>
                    </div>
                </div>
            </div>

        </div><!-- /ob_steps_wrapper -->
    </div><!-- /scrollable area -->

    <!-- Footer nav -->
    <div class="px-8 py-5 border-t border-gray-100 flex justify-between items-center shrink-0 bg-white">
        <button id="ob_btn_back" onclick="obBack()"
                class="text-gray-500 font-bold px-4 py-2.5 rounded-xl hover:bg-gray-100 transition opacity-0 pointer-events-none flex items-center gap-2">
            <i class="fa-solid fa-arrow-left text-xs"></i> Back
        </button>
        <button id="ob_btn_next" onclick="obNext()"
                class="text-white px-8 py-3 rounded-xl font-black shadow-lg hover:opacity-90 transition flex items-center gap-2"
                style="background:<?php echo esc_attr($brand->primary_color ?? '#4f46e5'); ?>">
            Continue <i class="fa-solid fa-arrow-right text-xs"></i>
        </button>
    </div>

</div><!-- /box -->
</div><!-- /overlay -->

<style>
    .ob-input {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        font-size: 14px;
        outline: none;
        transition: border-color 0.2s;
        background: #f9fafb;
    }
    .ob-input:focus { border-color: <?php echo esc_js($brand->primary_color ?? '#4f46e5'); ?>; background: #fff; }
    .ob-dns-bubble-ai { background: #f3f4f6; border-radius: 16px 16px 16px 4px; padding: 10px 16px; max-width: 80%; font-size: 13px; line-height: 1.5; color: #1f2937; }
    .ob-dns-bubble-user { background: <?php echo esc_js($brand->primary_color ?? '#4f46e5'); ?>; border-radius: 16px 16px 4px 16px; padding: 10px 16px; max-width: 80%; font-size: 13px; color: #fff; margin-left: auto; }
</style>

<script>
(function() {
    const STEPS       = <?php echo $steps_json; ?>;
    const STEP_LABELS = <?php echo wp_json_encode($step_labels); ?>;
    const BRAND_NAME  = '<?php echo esc_js($brand->brand_name ?? ''); ?>';
    const BRAND_COLOR = '<?php echo esc_js($brand->primary_color ?? '#4f46e5'); ?>';
    let   currentIdx  = 0;
    let   dnsDomain   = '';
    let   dnsChatHistory = [];

    function obRender(idx) {
        // Hide all steps
        document.querySelectorAll('.ob-step').forEach(s => s.classList.add('hidden'));
        // Show current
        const stepId = STEPS[idx];
        const stepEl = document.querySelector(`.ob-step[data-step-id="${stepId}"]`);
        if (stepEl) stepEl.classList.remove('hidden');

        // Label
        document.getElementById('ob_step_label').textContent = STEP_LABELS[stepId] || stepId;

        // Progress bar
        const pct = ((idx) / (STEPS.length - 1)) * 100;
        document.getElementById('ob_progress_bar').style.width = pct + '%';

        // Breadcrumb dots
        document.querySelectorAll('.ob-breadcrumb').forEach((dot, i) => {
            const inner = dot.querySelector('.ob-dot');
            if (i < idx)       { inner.style.background = BRAND_COLOR; inner.style.transform = 'scale(1)'; }
            else if (i === idx) { inner.style.background = BRAND_COLOR; inner.style.transform = 'scale(1.5)'; }
            else               { inner.style.background = '#e5e7eb'; inner.style.transform = 'scale(1)'; }
        });

        // Back btn
        const backBtn = document.getElementById('ob_btn_back');
        if (idx === 0) { backBtn.classList.add('opacity-0','pointer-events-none'); }
        else           { backBtn.classList.remove('opacity-0','pointer-events-none'); }

        // Next btn label
        const nextBtn = document.getElementById('ob_btn_next');
        if (stepId === 'done') {
            nextBtn.innerHTML = '<i class="fa-solid fa-dove mr-2"></i> Open My Dashboard';
        } else if (stepId === 'dns') {
            nextBtn.innerHTML = 'Save & Continue <i class="fa-solid fa-arrow-right text-xs ml-1"></i>';
        } else {
            nextBtn.innerHTML = 'Continue <i class="fa-solid fa-arrow-right text-xs ml-1"></i>';
        }
    }

    async function obSaveStep(stepId) {
        const fd = new FormData();
        fd.append('action',   'mt_save_onboarding_step');
        fd.append('security', mt_nonce);
        fd.append('step',     stepId);

        if (stepId === 'brand') {
            fd.append('brand_name',    document.getElementById('ob_brand_name')?.value || '');
            fd.append('primary_color', document.getElementById('ob_brand_color')?.value || '');
            fd.append('website_url',   document.getElementById('ob_website')?.value || '');
            fd.append('hq_address',    document.getElementById('ob_address')?.value || '');
        } else if (stepId === 'sender') {
            fd.append('from_name',  document.getElementById('ob_from_name')?.value || '');
            fd.append('from_email', document.getElementById('ob_from_email')?.value || '');
        } else if (stepId === 'dns') {
            fd.append('domain', dnsDomain);
        }

        try { await fetch(mt_ajax_url, { method:'POST', body:fd }); } catch(e) {}
    }

    window.obNext = async function() {
        const stepId  = STEPS[currentIdx];
        const nextBtn = document.getElementById('ob_btn_next');

        // Done step → close
        if (stepId === 'done') { obComplete(); return; }

        // Validate brand step
        if (stepId === 'brand') {
            const name = document.getElementById('ob_brand_name')?.value.trim();
            if (!name) { obFlash('Please enter your business name.'); return; }
        }

        // Validate sender step
        if (stepId === 'sender') {
            const email = document.getElementById('ob_from_email')?.value.trim();
            if (!email || !email.includes('@')) { obFlash('Please enter a valid from email address.'); return; }
        }

        nextBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Saving...';
        nextBtn.disabled  = true;
        await obSaveStep(stepId);
        nextBtn.disabled = false;

        currentIdx++;
        obRender(currentIdx);
    };

    window.obBack = function() {
        if (currentIdx > 0) { currentIdx--; obRender(currentIdx); }
    };

    window.obSkip = async function() {
        if (!confirm('Skip the setup wizard? You can re-launch it anytime from your profile menu.')) return;
        await obMarkComplete();
        document.getElementById('mt_onboarding_overlay').remove();
    };

    async function obComplete() {
        await obSaveStep(STEPS[currentIdx]);
        await obMarkComplete();
        document.getElementById('mt_onboarding_overlay').remove();
    }

    async function obMarkComplete() {
        const fd = new FormData();
        fd.append('action',   'mt_complete_onboarding');
        fd.append('security', mt_nonce);
        try { await fetch(mt_ajax_url, { method:'POST', body:fd }); } catch(e) {}
    }

    function obFlash(msg) {
        let toast = document.createElement('div');
        toast.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 bg-red-600 text-white text-sm font-bold px-5 py-3 rounded-xl shadow-xl z-[600] transition-all';
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    // ── DNS AI Chat ───────────────────────────────────────────────────────────
    window.obDnsSend = async function() {
        const input   = document.getElementById('ob_dns_input');
        const sendBtn = document.getElementById('ob_dns_send_btn');
        const chat    = document.getElementById('ob_dns_chat');
        const msg     = input.value.trim();
        if (!msg) return;

        input.value     = '';
        sendBtn.disabled = true;

        // User bubble
        const userBubble = document.createElement('div');
        userBubble.className = 'flex justify-end';
        userBubble.innerHTML = `<div class="ob-dns-bubble-user">${escHtml(msg)}</div>`;
        chat.appendChild(userBubble);

        // Thinking bubble
        const thinkBubble = document.createElement('div');
        thinkBubble.className = 'flex items-start gap-2.5';
        thinkBubble.innerHTML = `
            <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs shrink-0" style="background:${BRAND_COLOR}">
                <i class="fa-solid fa-dove"></i>
            </div>
            <div class="ob-dns-bubble-ai">
                <i class="fa-solid fa-circle-notch fa-spin text-gray-400"></i>
            </div>`;
        chat.appendChild(thinkBubble);
        chat.scrollTop = chat.scrollHeight;

        // Detect if user mentioned a domain in first messages
        const domainMatch = msg.match(/([a-z0-9\-]+\.[a-z]{2,})/i);
        if (domainMatch && !dnsDomain) dnsDomain = domainMatch[1];

        // Build context for AI
        dnsChatHistory.push({ role: 'user', content: msg });
        const systemCtx = `You are a friendly DNS setup assistant for MailToucan email platform.
Help the user add SPF, DKIM, and DMARC DNS records for their sender domain to improve email deliverability.
Brand context: Business name is "${BRAND_NAME}".
For each record, give the exact Type, Host/Name, and Value to enter in their DNS panel.
SPF record: TXT @ "v=spf1 include:spf.mailtoucan.com ~all"
DKIM: They should go to Email Marketing → Sender Domains → Add Domain to generate their unique DKIM key.
DMARC: TXT _dmarc "v=DMARC1; p=none; rua=mailto:dmarc@mailtoucan.com"
Be concise, friendly, and step-by-step. Ask one question at a time.
If they've added the records, tell them to verify in Sender Domains → the domain's Verify button.
Current detected domain: ${dnsDomain || '(not yet provided)'}.`;

        const historyStr = dnsChatHistory.map(h => `${h.role === 'user' ? 'User' : 'Assistant'}: ${h.content}`).join('\n');
        const prompt     = systemCtx + '\n\nConversation so far:\n' + historyStr;

        // AJAX call to platform help_chat AI
        const fd = new FormData();
        fd.append('action',          'mt_ai_help_chat');
        fd.append('security',        mt_nonce);
        fd.append('message',         prompt);
        fd.append('current_section', 'dns_onboarding');

        try {
            const res  = await fetch(mt_ajax_url, { method:'POST', body:fd });
            const data = await res.json();
            const text = data.success ? (data.data?.text || data.data || 'Something went wrong.') : (data.data?.message || 'Error. Please try again.');
            dnsChatHistory.push({ role: 'assistant', content: text });

            thinkBubble.querySelector('.ob-dns-bubble-ai').innerHTML = formatDnsResponse(text);
        } catch(e) {
            thinkBubble.querySelector('.ob-dns-bubble-ai').textContent = 'Network error. Please check your connection.';
        }

        sendBtn.disabled = false;
        chat.scrollTop   = chat.scrollHeight;
    };

    function formatDnsResponse(text) {
        // Light markdown: bold **text**, code `text`, newlines → <br>
        return text
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/`([^`]+)`/g, '<code style="background:#e5e7eb;padding:1px 5px;border-radius:4px;font-size:12px;font-family:monospace;">$1</code>')
            .replace(/\n/g, '<br>');
    }

    function escHtml(t) {
        return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // Color sync
    const colorPicker = document.getElementById('ob_brand_color');
    const colorText   = document.getElementById('ob_brand_color_text');
    if (colorPicker && colorText) {
        colorPicker.addEventListener('input', () => { colorText.value = colorPicker.value; });
        colorText.addEventListener('input', () => {
            if (/^#[0-9a-f]{6}$/i.test(colorText.value)) colorPicker.value = colorText.value;
        });
    }

    // Boot
    obRender(0);
})();
</script>
