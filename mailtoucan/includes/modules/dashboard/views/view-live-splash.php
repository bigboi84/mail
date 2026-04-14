<?php
/**
 * MailToucan Enterprise Splash Engine v8.6
 * Features: True Time Remaining, Router Loop Fix, CoovaChilli Fix, Syntax Patched
 */

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

$raw_mac = sanitize_text_field($_GET['clientmac'] ?? $_GET['client_mac'] ?? $_GET['mac'] ?? $_GET['ap_mac'] ?? '');
$clean_mac = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $raw_mac));
$mac_colon_format = !empty($clean_mac) ? implode(':', str_split($clean_mac, 2)) : ''; 

$uamip = sanitize_text_field($_GET['uamip'] ?? '');
$uamport = sanitize_text_field($_GET['uamport'] ?? '3990');
$mt_login = sanitize_text_field($_GET['loginurl'] ?? $_GET['link-login'] ?? '');
$res_param = sanitize_text_field($_GET['res'] ?? '');
$challenge = sanitize_text_field($_GET['challenge'] ?? '');

$rad_user = 'guest';
if (strlen($clean_mac) === 12) {
    if (!empty($uamip)) {
        $rad_user = implode('-', str_split($clean_mac, 2)); 
    } else {
        $rad_user = implode(':', str_split($clean_mac, 2)); 
    }
}

// IDENTITY & TIME TRACKING RESOLUTION
$welcome_back = false;
$known_email = '';
$known_name = '';
$time_remaining_html = '';

if (!empty($raw_mac)) {
    $existing_lead = $wpdb->get_row($wpdb->prepare(
        "SELECT guest_name, email FROM {$wpdb->prefix}mt_guest_leads WHERE guest_mac = %s ORDER BY id DESC LIMIT 1", 
        $raw_mac
    ));

    if ($existing_lead && is_email($existing_lead->email) && strpos($existing_lead->email, '@local.wifi') === false) {
        $welcome_back = true;
        $known_email = $existing_lead->email;
        $known_name = $existing_lead->guest_name;

        // Fetch remaining time for UI
        $active_session_end = get_transient('mt_wifi_session_' . md5($mac_colon_format));
        if ($active_session_end && $active_session_end > time()) {
            $mins = ceil(($active_session_end - time()) / 60);
            $time_remaining_html = "<div style='margin-top: 8px; font-size: 0.85rem; color: #059669; font-weight: bold; background: #d1fae5; padding: 4px 12px; border-radius: 20px; display: inline-block;'><i class='fa-solid fa-clock'></i> You have {$mins} minutes remaining</div>";
        }
    }
}

$flow_type = isset($config['flow_type']) ? intval($config['flow_type']) : 1;
$require_verification = isset($config['verify_email']) ? (bool)$config['verify_email'] : true;
$redirect_url = !empty($config['redirect_url']) ? $config['redirect_url'] : (!empty($state['redirect_url']) ? $state['redirect_url'] : 'http://captive.apple.com/hotspot-detect.html');

$s1 = $state['step1'] ?? [];
$s2 = $state['step2'] ?? [];
$s3 = $state['step3'] ?? [];

$logo_img = !empty($state['logo']['image']) ? $state['logo']['image'] : $brand_logo;
$logo_width = isset($state['logo']['width']) ? intval($state['logo']['width']) : ($is_mobile ? 80 : 40);
$logo_mb = isset($state['logo']['margin_bottom']) ? intval($state['logo']['margin_bottom']) : 24;

$show_name = isset($s1['fields']['show_name']) ? (bool)$s1['fields']['show_name'] : true;
$req_name = isset($s1['fields']['req_name']) ? (bool)$s1['fields']['req_name'] : false;
$show_email = isset($s1['fields']['show_email']) ? (bool)$s1['fields']['show_email'] : true;
$has_fields = ($show_name || $show_email);
$has_socials = (!empty($s1['google_auth']) || !empty($s1['fb_auth']) || !empty($s1['apple_auth']));
$tos_url = !empty($config['tos_url']) ? esc_url($config['tos_url']) : '#'; 

$final_action_url = $redirect_url;
if (!empty($uamip)) {
    $final_action_url = "http://" . esc_attr($uamip . ':' . $uamport) . "/logon?username=" . urlencode($rad_user) . "&password=" . urlencode($rad_user);
    if (!empty($challenge)) $final_action_url .= "&challenge=" . urlencode($challenge);
    if (!empty($redirect_url)) $final_action_url .= "&userurl=" . urlencode($redirect_url);
}
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
        #splash-scroll-area { position: relative; z-index: 10; height: 100%; width: 100%; overflow-y: auto; padding-bottom: 3rem; display: flex; flex-direction: column; align-items: center; padding-top: 3rem; }
        .portal-card-wrapper { width: 100%; max-width: 440px; padding: 0 1.5rem; margin: auto 0; }
        .brand-logo { display: block; margin: 0 auto; object-fit: contain; position: relative; z-index: 10; transition: opacity 0.3s ease; }
        .step-container { display: none; opacity: 0; transition: opacity 0.4s ease-in-out; width: 100%; }
        .step-container.active { display: block; opacity: 1; }
        .card { background: rgba(255, 255, 255, 0.96); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border-radius: 1.25rem; padding: 2.25rem 1.75rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); text-align: center; border: 1px solid rgba(255,255,255,0.4); }
        .card-title { font-size: 1.75rem; font-weight: 800; margin-bottom: 0.5rem; margin-top: 0; line-height: 1.2; letter-spacing: -0.025em; white-space: pre-wrap; }
        .card-desc { font-size: 0.95rem; color: #4b5563; margin-bottom: 1.5rem; margin-top: 0; line-height: 1.4; white-space: pre-wrap; }
        .custom-input { width: 100%; padding: 0.875rem 1.25rem; border: 1px solid #e5e7eb; border-radius: 0.75rem; background: #f9fafb; font-size: 0.95rem; outline: none; margin-bottom: 0.875rem; transition: all 0.2s; text-align: center; font-weight: bold; color: #111827; }
        .custom-input::placeholder { color: #9ca3af; font-weight: normal; }
        .custom-input:focus { border-color: #4f46e5; background: #fff; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        .custom-input.error { border-color: #ef4444; background: #fef2f2; }
        .main-btn { width: 100%; color: #fff; font-weight: 700; padding: 1.125rem; border-radius: 0.75rem; border: none; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 0.5rem; font-size: 1.05rem; transition: transform 0.1s, opacity 0.2s; text-decoration: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); }
        .main-btn:active { transform: scale(0.98); }
        .main-btn:disabled { opacity: 0.7; cursor: not-allowed; }
        .social-btn { width: 100%; font-weight: 700; padding: 0.875rem; border-radius: 0.75rem; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 0.5rem; font-size: 0.95rem; transition: transform 0.1s; text-decoration: none; margin-bottom: 0.75rem; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
        .btn-google { background-color: #fff; border: 1px solid #d1d5db; color: #374151; }
        .btn-fb { background-color: #1877f2; border: none; color: #fff; }
        .btn-apple { background-color: #000; border: none; color: #fff; }
        .terms-block { text-align: left; margin-top: 0.5rem; margin-bottom: 1.5rem; display: flex; align-items: flex-start; gap: 0.5rem; background: #f9fafb; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #f3f4f6; }
        .terms-checkbox { margin-top: 0.15rem; width: 1.25rem; height: 1.25rem; cursor: pointer; accent-color: #4f46e5; }
        .terms-text { font-size: 0.75rem; color: #6b7280; line-height: 1.4; margin: 0; cursor: pointer; }
        .terms-link { color: #3b82f6; text-decoration: none; font-weight: 700; }
        .vs-container { display: flex; gap: 0.75rem; justify-content: center; }
        .vs-btn { flex: 1; display: flex; flex-direction: column; align-items: center; border: 2px solid #e5e7eb; background: #f9fafb; border-radius: 0.75rem; overflow: hidden; cursor: pointer; transition: all 0.2s; }
        .vs-btn span { padding: 0.75rem 0; font-weight: bold; font-size: 0.875rem; color: #374151; pointer-events: none; }
        .vs-btn img { width: 100%; height: 80px; object-fit: cover; pointer-events: none; }
        .vs-btn.selected { border-color: #4f46e5; background: #e0e7ff; box-shadow: 0 0 0 2px rgba(79,70,229,0.2); }
        .camp-label { display: block; text-align: left; font-size: 0.875rem; font-weight: bold; color: #374151; margin-bottom: 0.5rem; margin-top: 1rem;}
        .radio-wrap { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem; border: 1px solid #e5e7eb; border-radius: 0.5rem; background: #f9fafb; cursor: pointer; margin-bottom: 0.5rem;}
        .radio-wrap span { font-size: 0.875rem; font-weight: 600; color: #374151; }
        .apple-note { font-size: 0.8rem; color: #6b7280; margin-top: 1.5rem; line-height: 1.5; background: #f9fafb; padding: 1rem; border-radius: 0.5rem; border: 1px solid #f3f4f6; text-align: left;}
    </style>
</head>
<body>

    <div id="splash-bg"></div>
    <div id="splash-overlay"></div>

    <div id="splash-scroll-area">
        <div class="portal-card-wrapper">
            
            <img id="brand-logo" src="<?php echo esc_attr($logo_img); ?>" alt="Logo" class="brand-logo" style="width: <?php echo esc_attr($logo_width); ?>%; margin-bottom: <?php echo esc_attr($logo_mb); ?>px;">

            <div id="step_1" class="step-container active card">
                
                <?php if(empty($raw_mac)): ?>
                    <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; font-size: 11px; margin-bottom: 15px; text-align: left; word-break: break-all;">
                        <strong>🚨 MAC Address Missing</strong><br>
                        The router did not attach the MAC address to this URL. If you are on a real WiFi connection, WordPress likely redirected the page and stripped the router's data.<br><br>
                        <strong>URL your phone sees:</strong><br>
                        <script>document.write(window.location.href);</script>
                    </div>
                <?php endif; ?>

                <?php if($welcome_back): ?>
                    <div style="background-color: #e0e7ff; color: #4f46e5; width: 5rem; height: 5rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center; font-size: 2.25rem; margin: 0 auto 1rem auto;">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <h2 class="card-title" style="margin-bottom: 0;">Welcome Back!</h2>
                    <?php echo $time_remaining_html; ?>
                    <p class="card-desc" style="margin-top: 1rem;">Good to see you again, <?php echo esc_html(!empty($known_name) ? $known_name : $known_email); ?>.</p>
                    
                    <form id="form_login" onsubmit="handleLogin(event)">
                        <input type="hidden" id="guest_name" value="<?php echo esc_attr($known_name); ?>">
                        <input type="hidden" id="guest_email" value="<?php echo esc_attr($known_email); ?>">
                        <button type="submit" id="btn_1" class="main-btn" style="background-color: <?php echo esc_attr($s1['btn_color'] ?? '#4f46e5'); ?>;">
                            <span>Continue to WiFi</span> <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </form>

                <?php else: ?>
                    <h2 class="card-title" style="color: <?php echo esc_attr($s1['title']['color'] ?? '#111827'); ?>; margin-bottom: <?php echo esc_attr($s1['spacing']['title_mb'] ?? 8); ?>px; <?php echo ($s1['title']['bold'] ?? true) ? 'font-weight:800;' : 'font-weight:normal;'; ?>"><?php echo esc_html($s1['title']['text'] ?? 'Welcome to WiFi'); ?></h2>
                    <p class="card-desc" style="color: <?php echo esc_attr($s1['desc']['color'] ?? '#4b5563'); ?>; margin-bottom: <?php echo esc_attr($s1['spacing']['desc_mb'] ?? 32); ?>px; <?php echo ($s1['desc']['bold'] ?? false) ? 'font-weight:bold;' : 'font-weight:normal;'; ?>"><?php echo esc_html($s1['desc']['text'] ?? 'Enter your email to connect.'); ?></p>
                    
                    <form id="form_login" onsubmit="handleLogin(event)">
                        <?php if($show_name): ?>
                            <input type="text" id="guest_name" class="custom-input" placeholder="<?php echo $req_name ? 'Your Name *' : 'Your Name (Optional)'; ?>" style="font-weight: normal;" <?php echo $req_name ? 'required' : ''; ?>>
                        <?php else: ?><input type="hidden" id="guest_name" value=""><?php endif; ?>

                        <?php if($show_email): ?>
                            <input type="email" id="guest_email" class="custom-input" placeholder="Email Address *" required>
                        <?php else: ?><input type="hidden" id="guest_email" value=""><?php endif; ?>
                        
                        <?php if($has_fields): ?>
                            <div class="terms-block">
                                <input type="checkbox" id="tos_agree" class="terms-checkbox" required checked>
                                <label for="tos_agree" class="terms-text">I agree to the <a href="<?php echo esc_attr($tos_url); ?>" class="terms-link" target="_blank">Terms & Conditions</a> and Privacy Policy.</label>
                            </div>
                            <button type="submit" id="btn_1" class="main-btn" style="background-color: <?php echo esc_attr($s1['btn_color'] ?? '#4f46e5'); ?>; margin-top: <?php echo esc_attr($s1['spacing']['btn_mt'] ?? 16); ?>px;">
                                <span><?php echo esc_html($s1['btn_text'] ?? 'Connect to WiFi'); ?></span>
                            </button>
                        <?php endif; ?>
                        
                        <?php if($has_socials): ?>
                            <div style="<?php echo $has_fields ? 'margin-top: 1.5rem; border-top: 1px solid #e5e7eb; padding-top: 1.5rem;' : ''; ?>">
                                <?php if(!$has_fields): ?><p style="font-size: 0.75rem; color: #6b7280; margin-bottom: 1rem;">By connecting, you agree to our <a href="<?php echo esc_attr($tos_url); ?>" class="terms-link" target="_blank">Terms & Conditions</a>.</p><?php endif; ?>
                                <?php if(!empty($s1['google_auth'])): ?><button type="button" class="social-btn btn-google" onclick="triggerSocialAuth('google')"><img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" width="18" alt="Google"> Continue with Google</button><?php endif; ?>
                                <?php if(!empty($s1['fb_auth'])): ?><button type="button" class="social-btn btn-fb" onclick="triggerSocialAuth('facebook')"><i class="fa-brands fa-facebook-f"></i> Continue with Facebook</button><?php endif; ?>
                                <?php if(!empty($s1['apple_auth'])): ?><button type="button" class="social-btn btn-apple" onclick="triggerSocialAuth('apple')"><i class="fa-brands fa-apple" style="font-size: 1.1rem;"></i> Continue with Apple</button><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
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
                                <h2 style="font-size: 1rem; font-weight: bold; margin-bottom: 0.75rem;">Rate your recent experience:</h2>
                                <div style="display: flex; justify-content: center; gap: 0.75rem; color: #d1d5db;" id="star_rating_block">
                                    <i class="fa-solid fa-star fa-2x cursor-pointer" style="cursor:pointer; transition:color 0.2s;" data-val="1"></i>
                                    <i class="fa-solid fa-star fa-2x cursor-pointer" style="cursor:pointer; transition:color 0.2s;" data-val="2"></i>
                                    <i class="fa-solid fa-star fa-2x cursor-pointer" style="cursor:pointer; transition:color 0.2s;" data-val="3"></i>
                                    <i class="fa-solid fa-star fa-2x cursor-pointer" style="cursor:pointer; transition:color 0.2s;" data-val="4"></i>
                                    <i class="fa-solid fa-star fa-2x cursor-pointer" style="cursor:pointer; transition:color 0.2s;" data-val="5"></i>
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
                                            echo '<label class="radio-wrap"><input type="'.esc_attr($q['type']).'" name="q_'.$i.'" value="'.esc_attr($o).'" class="camp-answer-multi" style="accent-color:#4f46e5;" data-q="'.esc_attr($q['text']).'"> <span>'.esc_html($o).'</span></label>';
                                        }
                                    }
                                    echo '</div>';
                                }
                            }
                            ?>
                        </div>
                    <?php elseif($c_type === 'promo'): ?>
                        <div style="text-align: center;">
                            <?php if(!empty($c_conf['img'])): ?><img src="<?php echo esc_attr($c_conf['img']); ?>" style="width: 100%; border-radius: 0.75rem; margin-bottom: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);"><?php endif; ?>
                            <h2 style="font-size: 1.5rem; font-weight: bold; line-height: 1.3;"><?php echo esc_html($c_conf['text']); ?></h2>
                        </div>
                    <?php elseif($c_type === 'versus'): ?>
                        <div style="text-align: center;">
                            <h2 style="font-size: 1.5rem; font-weight: bold; margin-bottom: 1.5rem; line-height: 1.3;"><?php echo esc_html($c_conf['title']); ?></h2>
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
                            <h2 style="font-size: 1.5rem; font-weight: bold; margin-bottom: 1.5rem; line-height: 1.3;"><?php echo esc_html($c_conf['text']); ?></h2>
                            <input type="date" id="camp_data_bday" class="custom-input" style="font-size: 1.25rem;">
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" id="btn_2" onclick="submitCampaign()" class="main-btn" style="background-color: <?php echo esc_attr($s2['btn_color'] ?? '#4f46e5'); ?>; margin-top: <?php echo esc_attr($s2['spacing']['btn_mt'] ?? 16); ?>px;">
                    <span><?php echo esc_html($s2['btn_text'] ?? 'Next'); ?></span>
                </button>
            </div>
            <?php endif; ?>

            <div id="step_3" class="step-container card">
                <div style="background-color: #d1fae5; color: #059669; width: 5rem; height: 5rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center; font-size: 2.25rem; margin: 0 auto 1.5rem auto;">
                    <i class="fa-solid fa-wifi"></i>
                </div>
                <h2 class="card-title" style="color: <?php echo esc_attr($s3['title_color'] ?? '#111827'); ?>; margin-bottom: <?php echo esc_attr($s3['spacing']['title_mb'] ?? 8); ?>px;"><?php echo esc_html($s3['title'] ?? "You're connected!"); ?></h2>
                
                <?php if($require_verification && !$welcome_back): ?>
                    <p class="card-desc" style="color: #059669; font-weight: bold;">You have temporary access. Check your email to verify your connection and keep browsing.</p>
                <?php else: ?>
                    <p class="card-desc">Your connection is ready. Tap the button below to sync with the network.</p>
                <?php endif; ?>
                
                <?php if(!empty($mt_login)): ?>
                    <form method="POST" action="<?php echo esc_attr($mt_login); ?>">
                        <input type="hidden" name="username" value="<?php echo esc_attr($rad_user); ?>">
                        <input type="hidden" name="password" value="<?php echo esc_attr($rad_user); ?>">
                        <input type="hidden" name="dst" value="<?php echo esc_attr($redirect_url); ?>">
                        <button type="submit" onclick="this.innerHTML='<i class=\'fa-solid fa-circle-notch fa-spin\'></i> Connecting...'" class="main-btn" style="background-color: <?php echo esc_attr($s3['btn_color'] ?? '#111827'); ?>; margin-top: <?php echo esc_attr($s3['spacing']['btn_mt'] ?? 32); ?>px;">
                            <span><?php echo esc_html($s3['btn_text'] ?? 'Browse the Internet'); ?></span>
                        </button>
                    </form>
                <?php else: ?>
                    <a href="<?php echo $final_action_url; ?>" onclick="this.innerHTML='<i class=\'fa-solid fa-circle-notch fa-spin\'></i> Connecting...'" class="main-btn" style="background-color: <?php echo esc_attr($s3['btn_color'] ?? '#111827'); ?>; margin-top: <?php echo esc_attr($s3['spacing']['btn_mt'] ?? 32); ?>px;">
                        <span><?php echo esc_html($s3['btn_text'] ?? 'Browse the Internet'); ?></span>
                    </a>
                <?php endif; ?>
                
                <div class="apple-note">
                    <i class="fa-brands fa-apple" style="color: #000; font-size: 1.1rem; margin-right: 0.25rem;"></i> <b>iPhone & iPad Users:</b><br>After clicking connect, wait for the page to load, then tap <b>"Done"</b> in the top right corner of your screen to exit.
                </div>
            </div>

        </div> 
    </div>

    <script>
        const mt_ajax_url = "<?php echo admin_url('admin-ajax.php'); ?>";
        const mt_splash_nonce = "<?php echo wp_create_nonce('mt_splash_nonce'); ?>";
        const flowType = <?php echo $flow_type; ?>;
        const hasCampaign = <?php echo $campaign_data ? 'true' : 'false'; ?>;
        const resParam = "<?php echo esc_js($res_param); ?>";
        const welcomeBack = <?php echo $welcome_back ? 'true' : 'false'; ?>;
        const campType = "<?php echo isset($c_type) ? esc_js($c_type) : ''; ?>";
        
        const showEmail = <?php echo $show_email ? 'true' : 'false'; ?>;
        
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

        const rawMac = "<?php echo esc_js($raw_mac); ?>";
        const redirectUrl = "<?php echo esc_js($redirect_url); ?>";

        if (resParam === 'success') {
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
                document.getElementById('splash-bg').style.backgroundSize = bg.size || 'cover';
                document.getElementById('splash-bg').style.backgroundPosition = bg.position || 'center';
                document.getElementById('splash-overlay').style.backgroundColor = hexToRgba(bg.overlay_c || '#000000', bg.overlay_o || 0);
            }
            if(hideLogos[step]) {
                document.getElementById('brand-logo').style.display = 'none';
            } else {
                document.getElementById('brand-logo').style.display = 'block';
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

        function isValidEmail(email) {
            const regex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            return regex.test(String(email).toLowerCase());
        }

        function handleLogin(e) {
            e.preventDefault();
            
            if (!welcomeBack) {
                const nameInput = document.getElementById('guest_name');
                const emailInput = document.getElementById('guest_email');
                
                if (showEmail) {
                    if(!emailInput.value || !isValidEmail(emailInput.value)) {
                        emailInput.classList.add('error'); 
                        alert('Please enter a valid email address.');
                        return;
                    }
                    emailInput.classList.remove('error');
                    leadData.email = emailInput.value;
                } else {
                    leadData.email = `guest_${rawMac}@local.wifi`; 
                }

                const tosCheckbox = document.getElementById('tos_agree');
                if (tosCheckbox && !tosCheckbox.checked) {
                    alert('You must agree to the Terms & Conditions.');
                    return;
                }
            } else {
                leadData.email = document.getElementById('guest_email').value;
            }

            const btn = document.getElementById('btn_1');
            if(btn) {
                btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Loading...';
                btn.disabled = true;
            }

            leadData.name = document.getElementById('guest_name').value;

            if (flowType === 3 && hasCampaign) {
                transitionToStep(2);
            } else {
                saveLeadToServerAndFinish();
            }
        }

        function triggerSocialAuth(provider) {
            alert("Social Login Engine: Routing to " + provider + " OAuth...");
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

            const fallbackTimeout = setTimeout(() => { transitionToStep(3); }, 3000);

            fetch(mt_ajax_url, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                clearTimeout(fallbackTimeout);
                if(data.success) {
                    transitionToStep(3);
                } else {
                    alert("Backend Error: " + data.data);
                    transitionToStep(3);
                }
            })
            .catch((e) => {
                console.error(e);
                clearTimeout(fallbackTimeout);
                transitionToStep(3);
            });
        }
    </script>
</body>
</html>