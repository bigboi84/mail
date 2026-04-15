<?php
/**
 * The WiFi Module: Splash Screens, Lead Capture, Verification, and Time Tracking
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MT_Wifi_Controller {

    public function init() {
        add_action('wp_ajax_mt_capture_lead', array($this, 'capture_lead_ajax'));
        add_action('wp_ajax_nopriv_mt_capture_lead', array($this, 'capture_lead_ajax'));
        add_action('init', array($this, 'catch_email_verification'));
        add_action('init', array($this, 'process_wifi_login'));
        add_action('init', array($this, 'catch_router_redirect'));
    }

    public function capture_lead_ajax() {
        check_ajax_referer('mt_splash_nonce', 'security');

        if (empty($_POST['payload'])) {
            wp_send_json_error('No payload received.');
        }

        $data = json_decode(stripslashes($_POST['payload']), true);
        if (!$data) wp_send_json_error('Invalid JSON payload.');

        global $wpdb;
        $table_leads = $wpdb->prefix . 'mt_guest_leads';

        $email = sanitize_email($data['email'] ?? '');
        $name = sanitize_text_field($data['name'] ?? '');
        $mac = sanitize_text_field($data['mac'] ?? '');
        $store_id = intval($data['store_id'] ?? 0);
        $brand_id = intval($data['brand_id'] ?? 0);
        $campaign_id = intval($data['campaign_id'] ?? 0);
        $survey_data = isset($data['survey_data']) ? json_encode($data['survey_data']) : '';

        $existing_lead = null;
        if (!empty($mac) && $mac !== 'UNKNOWN') {
            $existing_lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_leads WHERE guest_mac = %s AND store_id = %d ORDER BY id DESC LIMIT 1", $mac, $store_id));
        }
        
        if (!$existing_lead && !empty($email)) {
            $existing_lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_leads WHERE email = %s AND store_id = %d ORDER BY id DESC LIMIT 1", $email, $store_id));
        }

        if ($existing_lead) {
            $wpdb->update($table_leads, [
                'guest_name' => $name ?: $existing_lead->guest_name,
                'guest_mac' => !empty($mac) ? $mac : $existing_lead->guest_mac, 
                'last_visit' => current_time('mysql')
            ], ['id' => $existing_lead->id]);
            $lead_id = $existing_lead->id;
        } else {
            $wpdb->insert($table_leads, [
                'email' => $email,
                'guest_name' => $name,
                'brand_id' => $brand_id,
                'store_id' => $store_id,
                'guest_mac' => $mac,
                'status' => 'active', 
                'consent_log' => 'Captured via AJAX Splash',
                'unsub_token' => bin2hex(random_bytes(16))
            ]);
            $lead_id = $wpdb->insert_id;
        }

        if ($campaign_id > 0 && !empty($survey_data) && $survey_data !== '{}') {
            $table_responses = $wpdb->prefix . 'mt_campaign_responses';
            $wpdb->insert($table_responses, [
                'campaign_id' => $campaign_id,
                'lead_id' => $lead_id,
                'response_data' => $survey_data
            ]);
        }

        $table_wifi = $wpdb->prefix . 'mt_wifi_logs';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_wifi}'") === $table_wifi) {
            $wpdb->insert($table_wifi, ['mac_address' => $mac, 'store_id' => $store_id]);
        }

        $radius_result = $this->authorize_guest_mac($lead_id, $brand_id);

        if ($radius_result !== true) {
            wp_send_json_error($radius_result);
            return;
        }

        wp_send_json_success('Lead captured and authorized.');
    }


    public function catch_email_verification() {
        if (isset($_GET['mt_verify'])) {
            $token = sanitize_text_field($_GET['mt_verify']);
            global $wpdb;
            $table_leads = $wpdb->prefix . 'mt_guest_leads';
            
            $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_leads WHERE unsub_token = %s ORDER BY id DESC LIMIT 1", $token));
            
            if ($lead) {
                $wpdb->update($table_leads, ['status' => 'verified'], ['email' => $lead->email]);
                $this->authorize_guest_mac($lead->id, $lead->brand_id, true);
                
                $html = '<!DOCTYPE html><html lang="en"><head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Email Verified</title><style>body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f3f4f6; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; } .card { background: white; padding: 40px 20px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 90%; } .icon { background: #d1fae5; color: #059669; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; margin: 0 auto 20px auto; } h2 { color: #111827; margin-top: 0; font-weight: 800; font-size: 24px; } p { color: #4b5563; line-height: 1.5; font-size: 15px; margin-bottom: 20px; }</style></head><body><div class="card"><div class="icon">✓</div><h2>Email Verified!</h2><p>Thank you for verifying your email address. Your device has been upgraded to full internet access.</p><p style="font-size: 13px; color: #9ca3af;">You may now close this window and continue browsing.</p></div></body></html>';
                wp_die($html, 'Verified', ['response' => 200]);
            } else {
                wp_die('Invalid or expired verification link. Please connect to the WiFi network again.', 'Verification Failed', ['response' => 400]);
            }
        }
    }

    public function catch_router_redirect() {
        $request_uri = $_SERVER['REQUEST_URI'];
        if (strpos($request_uri, '/connect/') !== false) {
            $router_mac = isset($_GET['ap_mac']) ? sanitize_text_field($_GET['ap_mac']) : '';
            $client_mac = isset($_GET['client_mac']) ? sanitize_text_field($_GET['client_mac']) : '';

            global $wpdb;
            $table_stores = $wpdb->prefix . 'mt_stores';
            $store_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_stores WHERE router_identity = %s", $router_mac));

            if ($store_data) {
                $this->render_splash_screen($store_data, $client_mac, $router_mac);
                exit; 
            }
        }
    }

    public function process_wifi_login() {
        // Legacy block maintained
    }

    private function render_splash_screen($store_data, $client_mac, $router_mac) {
        // Legacy block maintained
    }

    public function authorize_guest_mac( $lead_id, $brand_id, $force_full_auth = false ) {
        global $wpdb;
        
        $lead = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$wpdb->prefix}mt_guest_leads WHERE id = %d", $lead_id) );
        
        if ( ! $lead ) {
            return 'Diagnostic: Lead ID ' . $lead_id . ' not found in DB.'; 
        }

        if ( empty($lead->guest_mac) || $lead->guest_mac === 'UNKNOWN' ) {
            return 'Diagnostic: MAC Address is missing. The router did not send a MAC address to the splash page. Make sure you are connecting through the real WiFi network and not just previewing the link in your browser.'; 
        }

        $raw_mac = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $lead->guest_mac));
        $mac_colon = implode(':', str_split($raw_mac, 2)); 
        $mac_dash = implode('-', str_split($raw_mac, 2));  

        $store = $wpdb->get_row( $wpdb->prepare("SELECT local_offer_json, splash_config FROM {$wpdb->prefix}mt_stores WHERE id = %d", $lead->store_id) );
        
        $splash_config = [];
        if ($store && !empty($store->splash_config)) {
            $splash_config = json_decode($store->splash_config, true) ?: [];
        }

        $require_verification = isset($splash_config['verify_email']) ? (bool)$splash_config['verify_email'] : true;
        $previously_verified = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}mt_guest_leads WHERE email = %s AND status = 'verified' LIMIT 1", $lead->email));

        $config = $store ? (json_decode($store->local_offer_json, true) ?: []) : [];
        $base_session_minutes = isset($config['session_limit_min']) ? intval($config['session_limit_min']) : 120; 
        $bandwidth_limit_mb = isset($config['bandwidth_limit_mb']) ? intval($config['bandwidth_limit_mb']) : 500;

        $session_time_seconds = 0;
        $idle_timeout_seconds = 0; // New Idle Timeout Variable
        $transient_key = 'mt_wifi_session_' . md5($mac_colon);

        if ($require_verification && !$previously_verified && !$force_full_auth && strpos($lead->email, '@local.wifi') === false) {
            
            // GRACE PERIOD MODE
            $session_time_seconds = 10 * 60; 
            $idle_timeout_seconds = 3 * 60; // 3 minute idle disconnect during grace period
            $bandwidth_limit_mb = 50; 
            
            $wpdb->update($wpdb->prefix . 'mt_guest_leads', ['status' => 'pending'], ['id' => $lead_id]);

            $verify_link = site_url('?mt_verify=' . $lead->unsub_token);
            $subject = "Verify your WiFi Connection";
            $message = "Hello " . ($lead->guest_name ?: 'Guest') . ",\n\nPlease click the secure link below to verify your email address and unlock your full WiFi session:\n\n" . $verify_link;
            wp_mail($lead->email, $subject, $message, array('Content-Type: text/plain; charset=UTF-8'));
            
            set_transient($transient_key, time() + $session_time_seconds, $session_time_seconds);
            
        } else {
            // FULL ACCESS MODE
            if (!$previously_verified && strpos($lead->email, '@local.wifi') === false) {
                $wpdb->update($wpdb->prefix . 'mt_guest_leads', ['status' => 'verified'], ['id' => $lead_id]);
            }

            // MIDDLE GROUND: 15 Minute Idle Disconnect to force the Splash Screen / Ad impression
            $idle_timeout_seconds = 15 * 60; 

            $active_session_end = get_transient($transient_key);
            $current_time = time();

            if ($active_session_end && $active_session_end > $current_time) {
                $session_time_seconds = $active_session_end - $current_time;
            } else {
                $session_time_seconds = $base_session_minutes * 60;
                $new_end_time = $current_time + $session_time_seconds;
                set_transient($transient_key, $new_end_time, $session_time_seconds);
            }
        }

        try {
            $pdo = new PDO(
                'mysql:host=107.173.49.14;dbname=radius;port=3306;charset=utf8mb4',
                'mt_radius',
                'JLAmX7sPoWffb7N3GVcp',
                [
                    PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT    => 5,
                ]
            );
        } catch (PDOException $e) {
            return 'DATABASE CONNECTION FAILED: ' . $e->getMessage();
        }

        try {
            $pdo->beginTransaction();

            // 1. MIKROTIK INJECTION (Colons format)
            $pdo->prepare('DELETE FROM radcheck WHERE username = ?')->execute([$mac_colon]);
            $pdo->prepare('DELETE FROM radreply WHERE username = ?')->execute([$mac_colon]);
            
            $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Auth-Type', ':=', 'Accept')")->execute([$mac_colon]);
            $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)")->execute([$mac_colon, $mac_colon]);
            $pdo->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Session-Timeout', '=', ?)")->execute([$mac_colon, $session_time_seconds]);
            $pdo->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Idle-Timeout', '=', ?)")->execute([$mac_colon, $idle_timeout_seconds]);

            // 2. DATTO/OPENMESH INJECTION (Dashes format)
            $pdo->prepare('DELETE FROM radcheck WHERE username = ?')->execute([$mac_dash]);
            $pdo->prepare('DELETE FROM radreply WHERE username = ?')->execute([$mac_dash]);
            
            $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Auth-Type', ':=', 'Accept')")->execute([$mac_dash]);
            $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)")->execute([$mac_dash, $mac_dash]);
            $pdo->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Session-Timeout', '=', ?)")->execute([$mac_dash, $session_time_seconds]);
            $pdo->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Idle-Timeout', '=', ?)")->execute([$mac_dash, $idle_timeout_seconds]);

            if ($bandwidth_limit_mb > 0) {
                $bytes = $bandwidth_limit_mb * 1024 * 1024;
                $pdo->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Mikrotik-Total-Limit', '=', ?)")->execute([$mac_colon, $bytes]);
                $pdo->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'ChilliSpot-Max-Total-Octets', '=', ?)")->execute([$mac_dash, $bytes]);
            }

            $pdo->commit();
            return true;

        } catch (PDOException $e) {
            $pdo->rollBack();
            return 'SQL QUERY FAILED: ' . $e->getMessage();
        }
    }
}

$mt_wifi_hw = new MT_Wifi_Controller();
$mt_wifi_hw->init();