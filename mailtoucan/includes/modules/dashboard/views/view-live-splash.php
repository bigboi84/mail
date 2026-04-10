<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Get the slugs from the URL (e.g., /splash/hakka-express/global)
$brand_slug = sanitize_text_field(get_query_var('mt_splash_brand'));
$loc_slug = sanitize_text_field(get_query_var('mt_splash_loc'));
global $wpdb;

$config = [];
$store_id = 0;
$store_name = "";
$brand_logo = '';
$campaign_data = null;

// 2. Dynamic Slug Lookup (Find the brand by replacing spaces with hyphens)
$brand = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mt_brands WHERE REPLACE(LOWER(brand_name), ' ', '-') = %s", strtolower($brand_slug)));

if (!$brand) {
    wp_die('Brand not found. Check the URL.');
}

$brand_id = $brand->id;

// 3. Find the specific location
if ($loc_slug === 'global' || empty($loc_slug)) {
    $config = json_decode($brand->splash_config, true) ?: [];
    $store_name = $brand->brand_name . " (Global)";
    $brand_settings = json_decode($brand->brand_config, true) ?: [];
    $brand_logo = $brand_settings['logos']['main'] ?? '';
} else {
    $store = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mt_stores WHERE brand_id = %d AND REPLACE(LOWER(store_name), ' ', '-') = %s", $brand_id, strtolower($loc_slug)));
    if ($store) {
        $store_name = $store->store_name;
        $store_id = $store->id;
        $config = json_decode($store->splash_config, true) ?: [];
        $brand_settings = json_decode($brand->brand_config, true) ?: [];
        $brand_logo = $brand_settings['logos']['main'] ?? '';
    } else {
        wp_die('Location not found. Check the URL.');
    }
}

// 4. Extract the active device state
$is_mobile = wp_is_mobile(); 
$state = $is_mobile && isset($config['mobile']) ? $config['mobile'] : ($config['desktop'] ?? ($config['mobile'] ?? []));

if (empty($state)) wp_die('Splash screen not configured yet. Please save the design in The Roost.');

// 5. Fetch the attached Campaign (if any)
$campaign_id = isset($state['campaign_id']) ? intval($state['campaign_id']) : 0;
if ($campaign_id > 0) {
    $camp = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mt_campaigns WHERE id = %d", $campaign_id));
    if ($camp) {
        $campaign_data = [
            'id' => $camp->id,
            'tag' => $camp->campaign_name,
            'type' => $camp->campaign_type,
            'config' => json_decode($camp->config_json, true)
        ];
    }
}

// Ensure defaults for rendering
$flow_type = isset($config['flow_type']) ? intval($config['flow_type']) : 1;
// --- BUG FIX: Extract redirect URL properly from config or state ---
$redirect_url = !empty($config['redirect_url']) ? $config['redirect_url'] : (!empty($state['redirect_url']) ? $state['redirect_url'] : '');

$s1 = $state['step1'] ?? [];
$s2 = $state['step2'] ?? [];
$s3 = $state['step3'] ?? [];

$logo_img = !empty($state['logo']['image']) ? $state['logo']['image'] : $brand_logo;
$logo_width = $state['logo']['width'] ?? 80;
$logo_mb = $state['logo']['margin_bottom'] ?? 24;
$tos_url = $config['tos_url'] ?? '#';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Connect to WiFi - <?php echo esc_html($store_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body, html { margin: 0; padding: 0; height: 100%; width: 100%; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #000; overflow: hidden; }
        
        #splash-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; transition: all 0.5s ease-in-out; background-position: center; background-repeat: no-repeat; background-size: cover; }
        #splash-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 2; transition: background-color 0.5s ease; }
        
        /* The main scrollable area */
        #splash-scroll-area { position: relative; z-index: 10; height: 100%; width: 100%; overflow-y: auto; padding-bottom: 2rem; }
        
        /* THE DESKTOP FIX: The central content card */
        .portal-card-wrapper { width: 100%; max-width: 420px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; min-height: 100%; padding: 2rem 1.5rem; }

        .step-container { display: none; opacity: 0; transition: opacity 0.4s ease-in-out; width: 100%; margin-top: auto; }
        .step-container.active { display: flex; flex-direction: column; opacity: 1; }
        
        .custom-input { width: 100%; padding: 0.8rem 1rem; border: 1px solid #e5e7eb; border-radius: 0.5rem; background: #f9fafb; font-size: 0.875rem; outline: none; margin-bottom: 0.75rem; transition: border-color 0.2s; }
        .custom-input:focus { border-color: #4f46e5; background: #fff; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        .custom-input.error { border-color: #ef4444; background: #fef2f2; }
    </style>
</head>
<body>

    <div id="splash-bg"></div>
    <div id="splash-overlay"></div>

    <div id="splash-scroll-area">
        <div class="portal-card-wrapper">
            
            <img id="brand-logo" src="<?php echo esc_attr($logo_img); ?>" alt="Logo" class="mx-auto z-10 relative object-contain transition-opacity duration-300" style="width: <?php echo esc_attr($logo_width); ?>%; margin-bottom: <?php echo esc_attr($logo_mb); ?>px;">

            <div id="step_1" class="step-container active bg-white/95 backdrop-blur-md p-6 rounded-2xl shadow-2xl text-center relative overflow-hidden">
                <h2 class="whitespace-pre-wrap leading-tight <?php echo esc_attr($s1['title']['size'] ?? 'text-2xl'); ?> <?php echo !empty($s1['title']['bold']) ? 'font-bold' : ''; ?>" style="color: <?php echo esc_attr($s1['title']['color'] ?? '#111827'); ?>; margin-bottom: <?php echo esc_attr($s1['spacing']['title_mb'] ?? 8); ?>px;"><?php echo esc_html($s1['title']['text'] ?? 'Welcome to WiFi'); ?></h2>
                
                <p class="whitespace-pre-wrap leading-tight <?php echo esc_attr($s1['desc']['size'] ?? 'text-sm'); ?> <?php echo !empty($s1['desc']['bold']) ? 'font-bold' : ''; ?>" style="color: <?php echo esc_attr($s1['desc']['color'] ?? '#4b5563'); ?>; margin-bottom: <?php echo esc_attr($s1['spacing']['desc_mb'] ?? 32); ?>px;"><?php echo esc_html($s1['desc']['text'] ?? ''); ?></p>
                
                <form id="form_login" onsubmit="handleLogin(event)">
                    <input type="text" id="guest_name" class="custom-input" placeholder="Your Name (Optional)">
                    <input type="email" id="guest_email" class="custom-input font-bold" placeholder="Email Address *" required>
                    
                    <button type="submit" id="btn_1" class="w-full text-white font-bold py-3.5 rounded-xl shadow-md transition-transform active:scale-95 flex justify-center items-center gap-2" style="background-color: <?php echo esc_attr($s1['btn_color'] ?? '#4f46e5'); ?>; margin-top: <?php echo esc_attr($s1['spacing']['btn_mt'] ?? 16); ?>px;">
                        <span><?php echo esc_html($s1['btn_text'] ?? 'Connect to WiFi'); ?></span>
                    </button>
                </form>

                <?php if(!empty($s1['google_auth']) || !empty($s1['fb_auth'])): ?>
                    <div class="mt-5 space-y-2">
                        <?php if(!empty($s1['google_auth'])): ?><button type="button" class="w-full bg-white border border-gray-300 text-gray-700 font-bold py-2.5 rounded-xl text-sm flex items-center justify-center gap-2 shadow-sm"><img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" width="18"> Continue with Google</button><?php endif; ?>
                        <?php if(!empty($s1['fb_auth'])): ?><button type="button" class="w-full bg-[#1877F2] text-white font-bold py-2.5 rounded-xl text-sm flex items-center justify-center gap-2 shadow-sm"><i class="fa-brands fa-facebook-f text-lg"></i> Continue with Facebook</button><?php endif; ?>
                    </div>
                <?php endif; ?>

                <p class="text-[10px] text-center text-gray-400 mt-6 leading-tight">By connecting, you agree to our <br><a href="<?php echo esc_attr($tos_url); ?>" class="underline text-blue-500 font-bold" target="_blank">Terms & Conditions</a> & Privacy Policy.</p>
            </div>

            <?php if($flow_type === 3 && $campaign_data): 
                $c_type = $campaign_data['type'];
                $c_conf = $campaign_data['config'];
            ?>
            <div id="step_2" class="step-container bg-white/95 backdrop-blur-md p-6 rounded-2xl shadow-2xl text-center relative overflow-hidden">
                
                <div id="camp_render_area" class="w-full text-left">
                    <?php if($c_type === 'survey'): ?>
                        <?php if(!empty($c_conf['stars'])): ?>
                            <div class="text-center mb-6">
                                <h2 class="text-sm font-bold text-gray-900 mb-3">Rate your recent experience:</h2>
                                <div class="flex gap-3 justify-center text-gray-300" id="star_rating_block">
                                    <i class="fa-solid fa-star text-3xl cursor-pointer hover:text-yellow-400 transition-colors" data-val="1"></i>
                                    <i class="fa-solid fa-star text-3xl cursor-pointer hover:text-yellow-400 transition-colors" data-val="2"></i>
                                    <i class="fa-solid fa-star text-3xl cursor-pointer hover:text-yellow-400 transition-colors" data-val="3"></i>
                                    <i class="fa-solid fa-star text-3xl cursor-pointer hover:text-yellow-400 transition-colors" data-val="4"></i>
                                    <i class="fa-solid fa-star text-3xl cursor-pointer hover:text-yellow-400 transition-colors" data-val="5"></i>
                                </div>
                                <input type="hidden" id="camp_data_stars" value="0">
                            </div>
                        <?php endif; ?>
                        <div class="space-y-4">
                            <?php 
                            if(!empty($c_conf['questions'])) {
                                foreach($c_conf['questions'] as $i => $q) {
                                    if(empty($q['text'])) continue;
                                    $req = ($i===0) ? 'required' : '';
                                    echo '<div class="camp-q-block">';
                                    echo '<label class="block text-xs font-bold text-gray-700 mb-1">'.esc_html($q['text']).($i===0?' <span class="text-red-500">*</span>':'').'</label>';
                                    if($q['type'] === 'text') {
                                        echo '<input type="text" class="custom-input camp-answer" data-q="'.esc_attr($q['text']).'" '.$req.'>';
                                    } else if ($q['type'] === 'radio' || $q['type'] === 'checkbox') {
                                        $opts = !empty($q['options']) ? array_map('trim', explode(',', $q['options'])) : [];
                                        echo '<div class="space-y-2 mt-2">';
                                        foreach($opts as $o) {
                                            if(empty($o)) continue;
                                            echo '<label class="flex items-center gap-2 p-2 border border-gray-200 rounded-lg bg-gray-50 cursor-pointer">';
                                            echo '<input type="'.esc_attr($q['type']).'" name="q_'.$i.'" value="'.esc_attr($o).'" class="camp-answer-multi text-indigo-600" data-q="'.esc_attr($q['text']).'"> <span class="text-sm font-semibold text-gray-700">'.esc_html($o).'</span>';
                                            echo '</label>';
                                        }
                                        echo '</div>';
                                    }
                                    echo '</div>';
                                }
                            }
                            ?>
                        </div>
                    <?php elseif($c_type === 'promo'): ?>
                        <div class="text-center">
                            <?php if(!empty($c_conf['img'])): ?>
                                <img src="<?php echo esc_attr($c_conf['img']); ?>" class="w-full h-auto rounded-xl mb-4 shadow-sm">
                            <?php endif; ?>
                            <h2 class="text-xl font-bold text-gray-900 whitespace-pre-wrap leading-tight"><?php echo esc_html($c_conf['text']); ?></h2>
                        </div>
                    <?php elseif($c_type === 'versus'): ?>
                        <div class="text-center">
                            <h2 class="text-xl font-bold text-gray-900 whitespace-pre-wrap leading-tight mb-5"><?php echo esc_html($c_conf['title']); ?></h2>
                            <div class="flex gap-3">
                                <button type="button" class="vs-btn flex-1 flex flex-col items-center border-2 border-gray-200 bg-gray-50 rounded-xl overflow-hidden shadow-sm active:scale-95 transition-transform" data-val="<?php echo esc_attr($c_conf['a']); ?>">
                                    <?php if(!empty($c_conf['img_a'])): ?><img src="<?php echo esc_attr($c_conf['img_a']); ?>" class="w-full h-24 object-cover pointer-events-none"><?php endif; ?>
                                    <span class="font-bold py-3 text-xs text-gray-700 pointer-events-none"><?php echo esc_html($c_conf['a']); ?></span>
                                </button>
                                <button type="button" class="vs-btn flex-1 flex flex-col items-center border-2 border-gray-200 bg-gray-50 rounded-xl overflow-hidden shadow-sm active:scale-95 transition-transform" data-val="<?php echo esc_attr($c_conf['b']); ?>">
                                    <?php if(!empty($c_conf['img_b'])): ?><img src="<?php echo esc_attr($c_conf['img_b']); ?>" class="w-full h-24 object-cover pointer-events-none"><?php endif; ?>
                                    <span class="font-bold py-3 text-xs text-gray-700 pointer-events-none"><?php echo esc_html($c_conf['b']); ?></span>
                                </button>
                            </div>
                            <input type="hidden" id="camp_data_versus" value="">
                        </div>
                    <?php elseif($c_type === 'birthday'): ?>
                        <div class="text-center py-4">
                            <h2 class="text-xl font-bold text-gray-900 whitespace-pre-wrap leading-tight mb-5"><?php echo esc_html($c_conf['text']); ?></h2>
                            <input type="date" id="camp_data_bday" class="w-full p-4 border-2 border-gray-200 rounded-xl text-center text-lg font-bold text-gray-700 outline-none focus:border-indigo-500">
                        </div>
                    <?php endif; ?>
                </div>

                <button type="button" id="btn_2" onclick="submitCampaign()" class="w-full text-white font-bold py-3.5 rounded-xl shadow-md transition-transform active:scale-95 flex justify-center items-center gap-2" style="background-color: <?php echo esc_attr($s2['btn_color'] ?? '#4f46e5'); ?>; margin-top: <?php echo esc_attr($s2['spacing']['btn_mt'] ?? 16); ?>px;">
                    <span><?php echo esc_html($s2['btn_text'] ?? 'Submit'); ?></span>
                </button>
            </div>
            <?php endif; ?>

            <div id="step_3" class="step-container bg-white/95 backdrop-blur-md p-8 rounded-2xl shadow-2xl text-center relative overflow-hidden">
                <div class="bg-green-100 text-green-600 w-20 h-20 rounded-full flex items-center justify-center text-3xl mb-5 mx-auto shadow-inner"><i class="fa-solid fa-check"></i></div>
                
                <h2 class="text-3xl font-bold whitespace-pre-wrap leading-tight" style="color: <?php echo esc_attr($s3['title_color'] ?? '#111827'); ?>; margin-bottom: <?php echo esc_attr($s3['spacing']['title_mb'] ?? 8); ?>px;"><?php echo esc_html($s3['title'] ?? 'You\'re connected!'); ?></h2>
                
                <button type="button" onclick="finalizeConnection()" class="w-full text-white font-bold py-4 rounded-xl shadow-lg transition-transform active:scale-95 text-lg" style="background-color: <?php echo esc_attr($s3['btn_color'] ?? '#111827'); ?>; margin-top: <?php echo esc_attr($s3['spacing']['btn_mt'] ?? 32); ?>px;">
                    <?php echo esc_html($s3['btn_text'] ?? 'Browse the Internet'); ?>
                </button>
            </div>

        </div> 
    </div>

    <script>
        // 1. Core System Routing
        const mt_ajax_url = "<?php echo admin_url('admin-ajax.php'); ?>";
        const mt_splash_nonce = "<?php echo wp_create_nonce('mt_splash_nonce'); ?>";
        
        // 2. Data Payloads from PHP
        const flowType = <?php echo $flow_type; ?>;
        // --- BUG FIX: Properly passed redirectUrl from PHP ---
        const redirectUrl = "<?php echo esc_js($redirect_url); ?>";
        const hasCampaign = <?php echo $campaign_data ? 'true' : 'false'; ?>;
        const campType = "<?php echo $campaign_data ? esc_js($campaign_data['type']) : ''; ?>";
        
        // Background State Map
        const backgrounds = {
            1: <?php echo wp_json_encode($s1['bg'] ?? []); ?>,
            2: <?php echo wp_json_encode($s2['bg'] ?? []); ?>,
            3: <?php echo wp_json_encode($s3['bg'] ?? []); ?>
        };

        const hideLogos = {
            1: false,
            2: <?php echo !empty($s2['hide_logo']) ? 'true' : 'false'; ?>,
            3: <?php echo !empty($s3['hide_logo']) ? 'true' : 'false'; ?>
        };

        // --- BUG FIX: Extract the Router's URL params ---
        const urlParams = new URLSearchParams(window.location.search);
        let clientMac = urlParams.get('client_mac') || urlParams.get('mac') || urlParams.get('ap_mac') || '';
        let loginUrl = urlParams.get('loginurl') || urlParams.get('link-login') || '';

        // Lead Data Payload to send to Server
        const leadData = {
            store_id: <?php echo $store_id; ?>,
            brand_id: <?php echo $brand_id; ?>,
            campaign_id: <?php echo $campaign_id; ?>,
            email: '',
            name: '',
            mac: clientMac, // --- BUG FIX: Push the MAC into the payload ---
            survey_data: {}
        };

        // Initialize First View
        applyBackground(1);

        // --- CORE FUNCTIONS ---
        function hexToRgba(hex, opacity) {
            if(!hex) return 'transparent';
            let r = parseInt(hex.slice(1, 3), 16), g = parseInt(hex.slice(3, 5), 16), b = parseInt(hex.slice(5, 7), 16);
            if(isNaN(r)) return 'transparent';
            return `rgba(${r}, ${g}, ${b}, ${opacity / 100})`;
        }

        function applyBackground(step) {
            const bg = backgrounds[step];
            const bgEl = document.getElementById('splash-bg');
            const overlayEl = document.getElementById('splash-overlay');
            const logoEl = document.getElementById('brand-logo');

            if(bg) {
                if(bg.type === 'color' || !bg.image) {
                    bgEl.style.backgroundColor = bg.color || '#ffffff';
                    bgEl.style.backgroundImage = 'none';
                    overlayEl.style.backgroundColor = 'transparent';
                } else {
                    bgEl.style.backgroundColor = bg.color || '#ffffff';
                    bgEl.style.backgroundImage = bg.image.includes('url') ? bg.image : `url(${bg.image})`;
                    bgEl.style.backgroundSize = bg.size || 'cover';
                    bgEl.style.backgroundPosition = bg.position || 'center';
                    overlayEl.style.backgroundColor = hexToRgba(bg.overlay_c || '#000000', bg.overlay_o || 0);
                }
            }

            if(hideLogos[step]) {
                logoEl.style.opacity = '0';
                setTimeout(() => { if(hideLogos[step]) logoEl.style.display = 'none'; }, 300);
            } else {
                logoEl.style.display = 'block';
                setTimeout(() => { logoEl.style.opacity = '1'; }, 10);
            }
        }

        function transitionToStep(stepNum) {
            document.querySelectorAll('.step-container').forEach(el => {
                el.style.opacity = '0';
                setTimeout(() => { el.classList.remove('active'); }, 300);
            });

            setTimeout(() => {
                applyBackground(stepNum);
                const nextEl = document.getElementById(`step_${stepNum}`);
                if(nextEl) {
                    nextEl.classList.add('active');
                    setTimeout(() => { nextEl.style.opacity = '1'; }, 50);
                }
            }, 300);
        }

        // --- STEP 1: LOGIN ---
        function handleLogin(e) {
            e.preventDefault();
            const emailInput = document.getElementById('guest_email');
            const nameInput = document.getElementById('guest_name');
            
            if(!emailInput.value || !emailInput.value.includes('@')) {
                emailInput.classList.add('error');
                return;
            }
            emailInput.classList.remove('error');

            const btn = document.getElementById('btn_1');
            const ogHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
            btn.disabled = true;

            // Save basic lead info
            leadData.email = emailInput.value;
            leadData.name = nameInput.value;

            setTimeout(() => { 
                if(flowType === 3 && hasCampaign) {
                    transitionToStep(2);
                } else {
                    saveLeadToServerAndFinish(); 
                }
            }, 500);
        }

        // --- STEP 2: CAMPAIGN LOGIC ---
        // Star Rating Binding
        const stars = document.querySelectorAll('#star_rating_block i');
        stars.forEach(s => {
            s.addEventListener('click', function() {
                const val = this.getAttribute('data-val');
                document.getElementById('camp_data_stars').value = val;
                stars.forEach(st => {
                    if(st.getAttribute('data-val') <= val) { st.classList.remove('text-gray-300'); st.classList.add('text-yellow-400'); }
                    else { st.classList.add('text-gray-300'); st.classList.remove('text-yellow-400'); }
                });
            });
        });

        // Versus Binding
        const vsBtns = document.querySelectorAll('.vs-btn');
        vsBtns.forEach(b => {
            b.addEventListener('click', function() {
                vsBtns.forEach(btn => btn.classList.remove('border-indigo-500', 'bg-indigo-50', 'ring-2', 'ring-indigo-200'));
                this.classList.add('border-indigo-500', 'bg-indigo-50', 'ring-2', 'ring-indigo-200');
                document.getElementById('camp_data_versus').value = this.getAttribute('data-val');
            });
        });

        function submitCampaign() {
            const btn = document.getElementById('btn_2');
            
            if(campType === 'survey') {
                const starVal = document.getElementById('camp_data_stars') ? document.getElementById('camp_data_stars').value : null;
                if(starVal) leadData.survey_data.rating = starVal;
                
                let hasError = false;
                document.querySelectorAll('.camp-answer').forEach(el => {
                    if(el.required && !el.value) { el.classList.add('error'); hasError = true; }
                    else { el.classList.remove('error'); leadData.survey_data[el.getAttribute('data-q')] = el.value; }
                });
                if(hasError) return;
                
                document.querySelectorAll('.camp-answer-multi:checked').forEach(el => {
                    const q = el.getAttribute('data-q');
                    if(!leadData.survey_data[q]) leadData.survey_data[q] = [];
                    leadData.survey_data[q].push(el.value);
                });
            } else if (campType === 'versus') {
                const choice = document.getElementById('camp_data_versus').value;
                if(!choice) { alert("Please make a selection."); return; }
                leadData.survey_data.versus_choice = choice;
            } else if (campType === 'birthday') {
                const bday = document.getElementById('camp_data_bday').value;
                if(!bday) { alert("Please enter your birthday."); return; }
                leadData.survey_data.birthday = bday;
            }

            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
            saveLeadToServerAndFinish();
        }

        // --- STEP 3: FINALIZE ---
        async function saveLeadToServerAndFinish() {
            // Prepare the payload for WordPress
            const formData = new FormData();
            formData.append('action', 'mt_capture_lead');
            formData.append('security', mt_splash_nonce);
            formData.append('payload', JSON.stringify(leadData));

            try {
                // Fire the data silently to the CRM
                await fetch(mt_ajax_url, { method: 'POST', body: formData });
                console.log("Lead successfully routed to The Roost CRM.");
            } catch (error) {
                console.error("CRM Sync Error", error);
            }

            // Regardless of network speed, push the guest to the Success Gate instantly
            transitionToStep(3);
        }

        function finalizeConnection() {
            const btn = document.getElementById('step_3').querySelector('button');
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Authorizing...';
            
            // --- BUG FIX: Delayed redirect to allow Server to ping RackNerd ---
            setTimeout(() => {
                btn.innerHTML = 'Connected!';
                btn.classList.add('bg-green-500');
                
                setTimeout(() => {
                    if(redirectUrl && redirectUrl !== '#') {
                        let finalUrl = redirectUrl.includes('http') ? redirectUrl : 'https://' + redirectUrl;
                        window.location.href = finalUrl;
                    } else if(loginUrl) {
                        window.location.href = loginUrl;
                    } else {
                        window.location.href = "https://www.google.com";
                    }
                }, 1000);
            }, 1500);
        }
    </script>
</body>
</html>