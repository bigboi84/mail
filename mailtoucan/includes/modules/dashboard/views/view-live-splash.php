<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$brand_slug = sanitize_text_field(get_query_var('mt_splash_brand'));
$loc_slug = sanitize_text_field(get_query_var('mt_splash_loc'));
global $wpdb;

$config = [];
$store_id = 0;
$store_name = "";
$brand_logo = '';
$campaign_data = null;

$brand = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mt_brands WHERE REPLACE(LOWER(brand_name), ' ', '-') = %s", strtolower($brand_slug)));
if (!$brand) wp_die('Brand not found. Check the URL.');
$brand_id = $brand->id;

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
        wp_die('Location not found.');
    }
}

$is_mobile = wp_is_mobile(); 
$state = $is_mobile && isset($config['mobile']) ? $config['mobile'] : ($config['desktop'] ?? ($config['mobile'] ?? []));
if (empty($state)) wp_die('Splash screen not configured yet.');

$campaign_id = isset($state['campaign_id']) ? intval($state['campaign_id']) : 0;
if ($campaign_id > 0) {
    $camp = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mt_campaigns WHERE id = %d", $campaign_id));
    if ($camp) {
        $campaign_data = [
            'id' => $camp->id,
            'type' => $camp->campaign_type,
            'config' => json_decode($camp->config_json, true)
        ];
    }
}

// MAC PRE-PROCESSING
$raw_mac = sanitize_text_field($_GET['client_mac'] ?? $_GET['mac'] ?? $_GET['ap_mac'] ?? '');
$clean_mac = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $raw_mac));
$rad_user = 'guest';
if (strlen($clean_mac) === 12) {
    $rad_user = implode(':', str_split($clean_mac, 2)); 
}

$uamip = sanitize_text_field($_GET['uamip'] ?? '');
$uamport = sanitize_text_field($_GET['uamport'] ?? '3990');
$mt_login = sanitize_text_field($_GET['loginurl'] ?? $_GET['link-login'] ?? '');
$res_param = sanitize_text_field($_GET['res'] ?? '');

$flow_type = isset($config['flow_type']) ? intval($config['flow_type']) : 1;
$redirect_url = !empty($config['redirect_url']) ? $config['redirect_url'] : (!empty($state['redirect_url']) ? $state['redirect_url'] : 'http://captive.apple.com/hotspot-detect.html');

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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body, html { margin: 0; padding: 0; height: 100%; width: 100%; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #000; overflow: hidden; }
        * { box-sizing: border-box; }
        
        #splash-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; transition: all 0.5s ease-in-out; background-position: center; background-repeat: no-repeat; background-size: cover; }
        #splash-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 2; transition: background-color 0.5s ease; }
        
        #splash-scroll-area { position: relative; z-index: 10; height: 100%; width: 100%; overflow-y: auto; padding-bottom: 2rem; display: flex; flex-direction: column; align-items: center; padding-top: 2rem; }
        .portal-card-wrapper { width: 100%; max-width: 420px; padding: 0 1.5rem; margin: auto 0; }

        .step-container { display: none; opacity: 0; transition: opacity 0.4s ease-in-out; width: 100%; }
        .step-container.active { display: block; opacity: 1; }
        
        .card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-radius: 1rem; padding: 1.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); text-align: center; }
        .card-title { font-size: 1.5rem; font-weight: bold; margin-bottom: 0.5rem; line-height: 1.2; }
        .card-desc { font-size: 0.875rem; color: #4b5563; margin-bottom: 1.5rem; line-height: 1.4; }
        
        .custom-input { width: 100%; padding: 0.8rem 1rem; border: 1px solid #e5e7eb; border-radius: 0.5rem; background: #f9fafb; font-size: 0.875rem; outline: none; margin-bottom: 0.75rem; transition: border-color 0.2s; text-align: center; font-weight: bold; }
        .custom-input:focus { border-color: #4f46e5; background: #fff; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        .custom-input.error { border-color: #ef4444; background: #fef2f2; }
        
        .main-btn { width: 100%; color: #fff; font-weight: bold; padding: 0.875rem; border-radius: 0.75rem; border: none; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 0.5rem; font-size: 1rem; transition: transform 0.1s; text-decoration: none; }
        .main-btn:active { transform: scale(0.98); }
        .main-btn:disabled { opacity: 0.7; cursor: not-allowed; }
        
        .terms-text { font-size: 10px; color: #9ca3af; margin-top: 1.5rem; line-height: 1.4; }
        .terms-link { color: #3b82f6; text-decoration: underline; font-weight: bold; }
        
        .vs-container { display: flex; gap: 0.5rem; justify-content: center; }
        .vs-btn { flex: 1; display: flex; flex-direction: column; align-items: center; border: 2px solid #e5e7eb; background: #f9fafb; border-radius: 0.75rem; overflow: hidden; cursor: pointer; }
        .vs-btn span { padding: 0.75rem 0; font-weight: bold; font-size: 0.75rem; color: #374151; pointer-events: none; }
        .vs-btn img { width: 100%; height: 60px; object-fit: cover; pointer-events: none; }
        .vs-btn.selected { border-color: #4f46e5; background: #e0e7ff; box-shadow: 0 0 0 2px rgba(79,70,229,0.2); }
        
        .camp-label { display: block; text-align: left; font-size: 0.75rem; font-weight: bold; color: #374151; margin-bottom: 0.25rem; margin-top: 1rem;}
        .radio-wrap { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 0.5rem; background: #f9fafb; cursor: pointer; margin-bottom: 0.5rem;}
        .radio-wrap span { font-size: 0.875rem; font-weight: 600; color: #374151; }
    </style>
</head>
<body>

    <div id="splash-bg"></div>
    <div id="splash-overlay"></div>

    <div id="splash-scroll-area">
        <div class="portal-card-wrapper">
            
            <img id="brand-logo" src="<?php echo esc_attr($logo_img); ?>" alt="Logo" class="mx-auto z-10 relative object-contain" style="width: <?php echo esc_attr($logo_width); ?>%; margin-bottom: <?php echo esc_attr($logo_mb); ?>px; display: block;">

            <div id="step_1" class="step-container active card">
                <h2 class="card-title" style="color: <?php echo esc_attr($s1['title']['color'] ?? '#111827'); ?>;"><?php echo esc_html($s1['title']['text'] ?? 'Welcome to WiFi'); ?></h2>
                <p class="card-desc" style="color: <?php echo esc_attr($s1['desc']['color'] ?? '#4b5563'); ?>;"><?php echo esc_html($s1['desc']['text'] ?? 'Enter your email to connect.'); ?></p>
                
                <form id="form_login" onsubmit="handleLogin(event)">
                    <input type="text" id="guest_name" class="custom-input" placeholder="Your Name (Optional)" style="font-weight: normal;">
                    <input type="email" id="guest_email" class="custom-input" placeholder="Email Address *" required>
                    <button type="submit" id="btn_1" class="main-btn" style="background-color: <?php echo esc_attr($s1['btn_color'] ?? '#4f46e5'); ?>; margin-top: 1rem;">
                        <span><?php echo esc_html($s1['btn_text'] ?? 'Connect to WiFi'); ?></span>
                    </button>
                </form>

                <p class="terms-text">By connecting, you agree to our <br><a href="<?php echo esc_attr($tos_url); ?>" class="terms-link" target="_blank">Terms & Conditions</a> & Privacy Policy.</p>
            </div>

            <?php if($flow_type === 3 && $campaign_data): 
                $c_type = $campaign_data['type'];
                $c_conf = $campaign_data['config'];
            ?>
            <div id="step_2" class="step-container card">
                <div id="camp_render_area" style="width: 100%; text-align: left; margin-bottom: 1.5rem;">
                    <?php if($c_type === 'survey'): ?>
                        <?php if(!empty($c_conf['stars'])): ?>
                            <div style="text-align: center; margin-bottom: 1.5rem;">
                                <h2 style="font-size: 0.875rem; font-weight: bold; margin-bottom: 0.75rem;">Rate your recent experience:</h2>
                                <div style="display: flex; justify-content: center; gap: 0.75rem; color: #d1d5db;" id="star_rating_block">
                                    <i class="fa-solid fa-star fa-2x cursor-pointer" style="cursor:pointer;" data-val="1"></i>
                                    <i class="fa-solid fa-star fa-2x cursor-pointer" style="cursor:pointer;" data-val="2"></i>
                                    <i class="fa-solid fa-star fa-2x cursor-pointer" style="cursor:pointer;" data-val="3"></i>
                                    <i class="fa-solid fa-star fa-2x cursor-pointer" style="cursor:pointer;" data-val="4"></i>
                                    <i class="fa-solid fa-star fa-2x cursor-pointer" style="cursor:pointer;" data-val="5"></i>
                                </div>
                                <input type="hidden" id="camp_data_stars" value="0">
                            </div>
                        <?php endif; ?>
                        <div>
                            <?php 
                            if(!empty($c_conf['questions'])) {
                                foreach($c_conf['questions'] as $i => $q) {
                                    if(empty($q['text'])) continue;
                                    $req = ($i===0) ? 'required' : '';
                                    echo '<div><label class="camp-label">'.esc_html($q['text']).'</label>';
                                    if($q['type'] === 'text') {
                                        echo '<input type="text" class="custom-input camp-answer" style="text-align:left; font-weight:normal;" data-q="'.esc_attr($q['text']).'" '.$req.'>';
                                    } else if ($q['type'] === 'radio' || $q['type'] === 'checkbox') {
                                        $opts = !empty($q['options']) ? array_map('trim', explode(',', $q['options'])) : [];
                                        foreach($opts as $o) {
                                            if(empty($o)) continue;
                                            echo '<label class="radio-wrap"><input type="'.esc_attr($q['type']).'" name="q_'.$i.'" value="'.esc_attr($o).'" class="camp-answer-multi" data-q="'.esc_attr($q['text']).'"> <span>'.esc_html($o).'</span></label>';
                                        }
                                    }
                                    echo '</div>';
                                }
                            }
                            ?>
                        </div>
                    <?php elseif($c_type === 'promo'): ?>
                        <div style="text-align: center;">
                            <?php if(!empty($c_conf['img'])): ?><img src="<?php echo esc_attr($c_conf['img']); ?>" style="width: 100%; border-radius: 0.75rem; margin-bottom: 1rem;"><?php endif; ?>
                            <h2 style="font-size: 1.25rem; font-weight: bold;"><?php echo esc_html($c_conf['text']); ?></h2>
                        </div>
                    <?php elseif($c_type === 'versus'): ?>
                        <div style="text-align: center;">
                            <h2 style="font-size: 1.25rem; font-weight: bold; margin-bottom: 1.25rem;"><?php echo esc_html($c_conf['title']); ?></h2>
                            <div class="vs-container">
                                <button type="button" class="vs-btn" data-val="<?php echo esc_attr($c_conf['a']); ?>">
                                    <?php if(!empty($c_conf['img_a'])): ?><img src="<?php echo esc_attr($c_conf['img_a']); ?>"><?php endif; ?>
                                    <span><?php echo esc_html($c_conf['a']); ?></span>
                                </button>
                                <button type="button" class="vs-btn" data-val="<?php echo esc_attr($c_conf['b']); ?>">
                                    <?php if(!empty($c_conf['img_b'])): ?><img src="<?php echo esc_attr($c_conf['img_b']); ?>"><?php endif; ?>
                                    <span><?php echo esc_html($c_conf['b']); ?></span>
                                </button>
                            </div>
                            <input type="hidden" id="camp_data_versus" value="">
                        </div>
                    <?php elseif($c_type === 'birthday'): ?>
                        <div style="text-align: center; padding: 1rem 0;">
                            <h2 style="font-size: 1.25rem; font-weight: bold; margin-bottom: 1.25rem;"><?php echo esc_html($c_conf['text']); ?></h2>
                            <input type="date" id="camp_data_bday" class="custom-input text-center text-lg font-bold">
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" id="btn_2" onclick="submitCampaign()" class="main-btn" style="background-color: <?php echo esc_attr($s2['btn_color'] ?? '#4f46e5'); ?>;">
                    <span><?php echo esc_html($s2['btn_text'] ?? 'Next'); ?></span>
                </button>
            </div>
            <?php endif; ?>

            <div id="step_3" class="step-container card">
                <div style="background-color: #d1fae5; color: #059669; width: 4.5rem; height: 4.5rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 1.25rem auto;">
                    <i class="fa-solid fa-wifi"></i>
                </div>
                <h2 class="card-title">Device Authorized!</h2>
                <p class="card-desc">Your connection is ready. Tap the button below to sync with the network.</p>
                
                <a href="#" id="final_connect_btn" class="main-btn" style="background-color: #111827;">
                    <span>Browse the Internet</span>
                </a>
            </div>

        </div> 
    </div>

    <script>
        const mt_ajax_url = "<?php echo admin_url('admin-ajax.php'); ?>";
        const mt_splash_nonce = "<?php echo wp_create_nonce('mt_splash_nonce'); ?>";
        const flowType = <?php echo $flow_type; ?>;
        const hasCampaign = <?php echo $campaign_data ? 'true' : 'false'; ?>;
        const resParam = "<?php echo esc_js($res_param); ?>";
        
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

        const uamip = "<?php echo esc_js($uamip); ?>";
        const uamport = "<?php echo esc_js($uamport); ?>";
        const mikrotikLoginUrl = "<?php echo esc_js($mt_login); ?>";
        const rawMac = "<?php echo esc_js($raw_mac); ?>";
        const radUser = "<?php echo esc_js($rad_user); ?>";
        const redirectUrl = "<?php echo esc_js($redirect_url); ?>";
        const isA62Router = (resParam !== '' || uamip !== '');

        // LOOP BREAKER
        if (resParam === 'success' || resParam === 'already' || resParam === 'logon') {
            window.location.replace(redirectUrl); 
        } else {
            applyBackground(1);
        }

        const leadData = {
            store_id: <?php echo $store_id; ?>, brand_id: <?php echo $brand_id; ?>, campaign_id: <?php echo $campaign_id; ?>,
            email: '', name: '', mac: rawMac, survey_data: {}
        };

        function hexToRgba(hex, opacity) {
            if(!hex) return 'transparent';
            let r = parseInt(hex.slice(1, 3), 16), g = parseInt(hex.slice(3, 5), 16), b = parseInt(hex.slice(5, 7), 16);
            return `rgba(${r}, ${g}, ${b}, ${opacity / 100})`;
        }

        function applyBackground(step) {
            const bg = backgrounds[step];
            if(bg) {
                document.getElementById('splash-bg').style.backgroundImage = bg.image ? `url(${bg.image})` : 'none';
                document.getElementById('splash-bg').style.backgroundColor = bg.color || '#ffffff';
                document.getElementById('splash-overlay').style.backgroundColor = hexToRgba(bg.overlay_c || '#000000', bg.overlay_o || 0);
            }
            if(hideLogos[step]) {
                document.getElementById('brand-logo').style.display = 'none';
            }
        }

        function transitionToStep(stepNum) {
            document.querySelectorAll('.step-container').forEach(el => {
                el.style.opacity = '0';
                setTimeout(() => el.classList.remove('active'), 300);
            });
            setTimeout(() => {
                applyBackground(stepNum);
                const nextEl = document.getElementById(`step_${stepNum}`);
                if(nextEl) {
                    nextEl.classList.add('active');
                    setTimeout(() => nextEl.style.opacity = '1', 50);
                }
            }, 300);
        }

        function handleLogin(e) {
            e.preventDefault();
            const emailInput = document.getElementById('guest_email');
            if(!emailInput.value || !emailInput.value.includes('@')) {
                emailInput.classList.add('error'); return;
            }
            emailInput.classList.remove('error');

            const btn = document.getElementById('btn_1');
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Saving...';
            btn.disabled = true;

            leadData.email = emailInput.value;
            leadData.name = document.getElementById('guest_name').value;

            if (flowType === 3 && hasCampaign) {
                transitionToStep(2);
            } else {
                saveLeadToServerAndFinish();
            }
        }

        document.querySelectorAll('#star_rating_block i').forEach(s => {
            s.addEventListener('click', function() {
                const val = this.getAttribute('data-val');
                document.getElementById('camp_data_stars').value = val;
                document.querySelectorAll('#star_rating_block i').forEach(st => {
                    st.style.color = (st.getAttribute('data-val') <= val) ? '#fbbf24' : '#d1d5db';
                });
            });
        });

        document.querySelectorAll('.vs-btn').forEach(b => {
            b.addEventListener('click', function() {
                document.querySelectorAll('.vs-btn').forEach(btn => btn.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('camp_data_versus').value = this.getAttribute('data-val');
            });
        });

        function submitCampaign() {
            const btn = document.getElementById('btn_2');
            if(campType === 'survey') {
                const sv = document.getElementById('camp_data_stars');
                if(sv && sv.value !== "0") leadData.survey_data.rating = sv.value;
                let err = false;
                document.querySelectorAll('.camp-answer').forEach(el => {
                    if(el.required && !el.value) { el.classList.add('error'); err = true; }
                    else { el.classList.remove('error'); leadData.survey_data[el.dataset.q] = el.value; }
                });
                if(err) return;
                document.querySelectorAll('.camp-answer-multi:checked').forEach(el => {
                    if(!leadData.survey_data[el.dataset.q]) leadData.survey_data[el.dataset.q] = [];
                    leadData.survey_data[el.dataset.q].push(el.value);
                });
            } else if (campType === 'versus') {
                const c = document.getElementById('camp_data_versus').value;
                if(!c) { alert("Please make a selection."); return; }
                leadData.survey_data.versus_choice = c;
            } else if (campType === 'birthday') {
                const b = document.getElementById('camp_data_bday').value;
                if(!b) { alert("Please enter your birthday."); return; }
                leadData.survey_data.birthday = b;
            }
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Saving...';
            btn.disabled = true;
            saveLeadToServerAndFinish();
        }

        function saveLeadToServerAndFinish() {
            const fd = new FormData();
            fd.append('action', 'mt_capture_lead');
            fd.append('security', mt_splash_nonce);
            fd.append('payload', JSON.stringify(leadData));

            // Fire to CRM silently
            fetch(mt_ajax_url, { method: 'POST', body: fd }).catch(e => console.error(e));

            // Setup the final native buttons based on router type
            const finalBtn = document.getElementById('final_connect_btn');
            
            if (isA62Router && uamip) {
                // A62 requires a clean GET link
                finalBtn.href = `http://${uamip}:${uamport}/logon?username=${encodeURIComponent(radUser)}&password=${encodeURIComponent(radUser)}`;
            } else if (mikrotikLoginUrl) {
                // MikroTik requires a form POST triggered on click
                finalBtn.onclick = function(e) {
                    e.preventDefault();
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = mikrotikLoginUrl;
                    const u = document.createElement('input'); u.type = 'hidden'; u.name = 'username'; u.value = radUser;
                    const p = document.createElement('input'); p.type = 'hidden'; p.name = 'password'; p.value = radUser;
                    form.appendChild(u); form.appendChild(p);
                    document.body.appendChild(form);
                    form.submit();
                };
            } else {
                finalBtn.href = redirectUrl;
            }

            transitionToStep(3);
        }
    </script>
</body>
</html>