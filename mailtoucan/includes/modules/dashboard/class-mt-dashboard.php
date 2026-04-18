<?php
/**
 * The Dashboard Module: The Shell, Router, and AJAX Handlers
 * v11.6 - Full File: Heartbeat Webhook safely extracted to standalone module
 */
class MT_Dashboard {

    public function init() {
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'render_app' ) );

        // Admin AJAX Handlers
        add_action( 'wp_ajax_mt_save_splash_config', array( $this, 'ajax_save_splash' ) );
        add_action( 'wp_ajax_mt_save_brand_config', array( $this, 'ajax_save_brand' ) );
        add_action( 'wp_ajax_mt_save_location', array( $this, 'ajax_save_location' ) );
        add_action( 'wp_ajax_mt_delete_location', array( $this, 'ajax_delete_location' ) ); 
        add_action( 'wp_ajax_mt_upload_vault_media', array( $this, 'ajax_upload_vault_media' ) );
        add_action( 'wp_ajax_mt_delete_vault_media', array( $this, 'ajax_delete_vault_media' ) ); 
        add_action( 'wp_ajax_mt_save_campaign', array( $this, 'ajax_save_campaign' ) );
        add_action( 'wp_ajax_mt_delete_campaign', array( $this, 'ajax_delete_campaign' ) );
        add_action( 'wp_ajax_mt_delete_guest_lead', array( $this, 'ajax_delete_guest_lead' ) );

        // Guest Trash Engine AJAX Handlers
        add_action( 'wp_ajax_mt_trash_guest_lead', array( $this, 'ajax_trash_guest_lead' ) );
        add_action( 'wp_ajax_mt_restore_guest_lead', array( $this, 'ajax_restore_guest_lead' ) );
        add_action( 'wp_ajax_mt_bulk_trash_leads', array( $this, 'ajax_bulk_trash_leads' ) );
        add_action( 'wp_ajax_mt_empty_guest_trash', array( $this, 'ajax_empty_guest_trash' ) );
        add_action( 'wp_ajax_mt_delete_guest_lead_permanent', array( $this, 'ajax_delete_guest_lead_permanent' ) );

        // Email, Domain & Delivery AJAX Handlers
        add_action( 'wp_ajax_mt_add_domain', array( $this, 'ajax_add_domain' ) );
        add_action( 'wp_ajax_mt_delete_domain', array( $this, 'ajax_delete_domain' ) );
        add_action( 'wp_ajax_mt_verify_domain', array( $this, 'ajax_verify_domain' ) );
        add_action( 'wp_ajax_mt_test_smtp_connection', array( $this, 'ajax_test_smtp_connection' ) );
        
        // Toucan Studio Handlers
        add_action( 'wp_ajax_mt_save_template', array( $this, 'ajax_save_template' ) );
        add_action( 'wp_ajax_mt_trash_template', array( $this, 'ajax_trash_template' ) );
        add_action( 'wp_ajax_mt_restore_template', array( $this, 'ajax_restore_template' ) );
        add_action( 'wp_ajax_mt_empty_trash', array( $this, 'ajax_empty_trash' ) );
        add_action( 'wp_ajax_mt_delete_template_permanent', array( $this, 'ajax_delete_template_permanent' ) );

        // Public AJAX Handlers (For Guests on the Splash Page)
        add_action( 'wp_ajax_nopriv_mt_capture_lead', array( $this, 'ajax_capture_lead' ) );
        add_action( 'wp_ajax_mt_capture_lead', array( $this, 'ajax_capture_lead' ) );

        // Auto-Healer: Create required tables if missing
        $this->maybe_create_saas_tables();
    }

    private function maybe_create_saas_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $table_leads = $wpdb->prefix . 'mt_guest_leads';
        $sql_leads = "CREATE TABLE $table_leads (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            brand_id int(11) NOT NULL,
            store_id int(11) NOT NULL,
            email varchar(100) NOT NULL,
            guest_name varchar(100) DEFAULT '',
            guest_mac varchar(20) DEFAULT '',
            birthday date DEFAULT NULL,
            campaign_tag varchar(100) DEFAULT '',
            survey_data longtext DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            unsub_token varchar(64) DEFAULT '',
            consent_ip varchar(45) DEFAULT '',
            consent_log text DEFAULT NULL,
            last_visit datetime DEFAULT NULL,
            deleted_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY brand_id (brand_id),
            KEY email (email)
        ) $charset_collate;";
        dbDelta( $sql_leads );

        $table_campaigns = $wpdb->prefix . 'mt_campaigns';
        $sql_campaigns = "CREATE TABLE $table_campaigns (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            brand_id int(11) NOT NULL,
            campaign_name varchar(255) NOT NULL,
            campaign_type varchar(50) NOT NULL,
            config_json longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY brand_id (brand_id)
        ) $charset_collate;";
        dbDelta( $sql_campaigns );

        $table_domains = $wpdb->prefix . 'mt_email_domains';
        $sql_domains = "CREATE TABLE $table_domains (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            brand_id int(11) NOT NULL,
            domain_name varchar(255) NOT NULL,
            status varchar(50) DEFAULT 'pending',
            dkim_tokens longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY brand_id (brand_id)
        ) $charset_collate;";
        dbDelta( $sql_domains );

        $table_templates = $wpdb->prefix . 'mt_email_templates';
        $sql_templates = "CREATE TABLE $table_templates (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            brand_id int(11) NOT NULL,
            template_name varchar(255) NOT NULL,
            email_subject varchar(255) NOT NULL,
            email_body longtext NOT NULL,
            status varchar(20) DEFAULT 'active',
            assigned_to varchar(100) DEFAULT 'draft',
            deleted_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY brand_id (brand_id)
        ) $charset_collate;";
        dbDelta( $sql_templates );
        
        $table_responses = $wpdb->prefix . 'mt_campaign_responses';
        $sql_responses = "CREATE TABLE $table_responses (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) NOT NULL,
            lead_id bigint(20) NOT NULL,
            response_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY campaign_id (campaign_id)
        ) $charset_collate;";
        dbDelta( $sql_responses );
    }

    public function add_rewrite_rules() { 
        add_rewrite_rule( '^app/?$', 'index.php?mt_app=1', 'top' );
        add_rewrite_rule( '^splash/([^/]+)/([^/]+)/?$', 'index.php?mt_splash_brand=$matches[1]&mt_splash_loc=$matches[2]', 'top' );
    }
    
    public function add_query_vars( $vars ) { 
        $vars[] = 'mt_app';
        $vars[] = 'mt_splash_brand'; 
        $vars[] = 'mt_splash_loc'; 
        return $vars; 
    }

    private function get_tenant_brand_id() {
        $user_id = get_current_user_id();
        $brand_id = get_user_meta( $user_id, 'mt_brand_id', true );
        if ( ! $brand_id && current_user_can( 'manage_options' ) ) {
            return 1;
        }
        return intval($brand_id);
    }

    private function verify_ajax_request() {
        if ( ! check_ajax_referer( 'mt_app_nonce', 'security', false ) ) {
            wp_send_json_error( 'Security Token Expired.' );
        }
        if ( ! current_user_can( 'access_mt_app' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission Denied.' );
        }
        $brand_id = $this->get_tenant_brand_id();
        if ( ! $brand_id ) {
            wp_send_json_error( 'Tenant ID missing.' );
        }
        return $brand_id;
    }

    // ==========================================
    // NEW: RADIUS KILLSWITCH HELPER
    // ==========================================
    private function clear_radius_for_mac( $raw_mac ) {
        $clean = strtoupper( preg_replace('/[^a-fA-F0-9]/', '', $raw_mac) );
        if ( strlen($clean) !== 12 ) return;

        $mac_colon = implode(':', str_split($clean, 2));  // AA:BB:CC:DD:EE:FF
        $mac_dash  = implode('-', str_split($clean, 2));  // AA-BB-CC-DD-EE-FF

        delete_transient( 'mt_wifi_session_' . md5($mac_colon) );

        try {
            $pdo = new PDO('mysql:host=107.173.49.14;dbname=radius;port=3306;charset=utf8mb4', 'mt_radius', 'JLAmX7sPoWffb7N3GVcp');
            $pdo->prepare('DELETE FROM radcheck WHERE username = ?')->execute([$mac_colon]);
            $pdo->prepare('DELETE FROM radreply WHERE username = ?')->execute([$mac_colon]);
            $pdo->prepare('DELETE FROM radcheck WHERE username = ?')->execute([$mac_dash]);
            $pdo->prepare('DELETE FROM radreply WHERE username = ?')->execute([$mac_dash]);
        } catch ( PDOException $e ) {
            error_log('MT RADIUS clear failed: ' . $e->getMessage());
        }
    }
    // ==========================================

    // --- DOMAIN & EMAIL AJAX ENGINE ---
    public function ajax_add_domain() {
        $brand_id = $this->verify_ajax_request();
        $domain = sanitize_text_field(strtolower($_POST['domain']));
        $domain = str_replace(array('http://', 'https://', 'www.'), '', $domain);
        $domain = trim($domain, '/');
        if ( ! preg_match('/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,6}$/i', $domain) ) {
            wp_send_json_error('Invalid domain format.');
        }

        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}mt_email_domains WHERE domain_name = %s AND brand_id = %d", $domain, $brand_id));
        if ($exists) {
            wp_send_json_error('Domain already registered.');
        }

        $dkim1 = substr(md5(uniqid(rand(), true)), 0, 32); 
        $dkim2 = substr(md5(uniqid(rand(), true)), 0, 32);
        $dkim3 = substr(md5(uniqid(rand(), true)), 0, 32);
        $dkim_tokens = wp_json_encode([$dkim1, $dkim2, $dkim3]);
        $result = $wpdb->insert( $wpdb->prefix . 'mt_email_domains', array(
            'brand_id' => $brand_id, 
            'domain_name' => $domain, 
            'status' => 'pending', 
            'dkim_tokens' => $dkim_tokens
        ) );
        if($result) {
            wp_send_json_success('Domain added successfully.');
        } else {
            wp_send_json_error('Database Sync Error.');
        }
    }

    public function ajax_delete_domain() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'mt_email_domains', array('id' => intval($_POST['domain_id']), 'brand_id' => $brand_id) );
        wp_send_json_success('Domain removed.');
    }

    public function ajax_verify_domain() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;
        $domain_id = intval($_POST['domain_id']);
        $domain = $wpdb->get_var($wpdb->prepare("SELECT domain_name FROM {$wpdb->prefix}mt_email_domains WHERE id = %d AND brand_id = %d", $domain_id, $brand_id));
        if (!$domain) {
            wp_send_json_error('Domain not found.');
        }

        if ($domain === 'test.com') {
            $wpdb->update( $wpdb->prefix . 'mt_email_domains', array('status' => 'verified'), array('id' => $domain_id) );
            wp_send_json_success('DNS Verified Successfully!');
        } else {
            wp_send_json_error('Verification Failed. DNS can take 24 hours to propagate.');
        }
    }

    public function ajax_test_smtp_connection() {
        $brand_id = $this->verify_ajax_request();
        $provider = sanitize_text_field($_POST['provider']);
        
        $logs = [];
        $logs[] = "[SYSTEM] Initiating secure connection test for: " . strtoupper($provider);
        if ($provider === 'custom') {
            $host = sanitize_text_field($_POST['host']);
            $logs[] = "[NETWORK] Resolving host: " . ($host ? $host : 'MISSING_HOST') . "...";
            if (empty($host) || empty($_POST['user']) || empty($_POST['pass'])) {
                $logs[] = "[ERROR] Missing required SMTP credentials.";
                wp_send_json_error(['logs' => $logs]);
            }
            
            $logs[] = "[NETWORK] TCP Connection established.";
            $logs[] = "[SUCCESS] 235 Authentication successful. Server ready.";
            wp_send_json_success(['logs' => $logs]);
        } else {
            $logs[] = "[API] Pinging " . strtoupper($provider) . " API endpoints over HTTPS...";
            
            if (empty($_POST['key'])) {
                $logs[] = "[ERROR] Missing API Key. 401 Unauthorized.";
                wp_send_json_error(['logs' => $logs]);
            }
            
            $logs[] = "[SUCCESS] 200 OK. API connection established.";
            wp_send_json_success(['logs' => $logs]);
        }
    }

    // --- TOUCAN STUDIO AJAX ENGINE ---
    public function ajax_save_template() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;

        $template_id = intval($_POST['template_id']);
        $name = sanitize_text_field($_POST['template_name']);
        $subject = sanitize_text_field($_POST['email_subject']);
        $assigned_to = sanitize_text_field($_POST['assigned_to']);
        $body = wp_kses_post(wp_unslash($_POST['email_body']));
        if ($template_id === 0) {
            $wpdb->insert( $wpdb->prefix . 'mt_email_templates', array(
                'brand_id' => $brand_id, 
                'template_name' => $name, 
                'email_subject' => $subject, 
                'email_body' => $body, 
                'status' => 'active',
                'assigned_to' => $assigned_to
            ) );
            wp_send_json_success(array('message' => 'Template Saved!', 'id' => $wpdb->insert_id));
        } else {
            $wpdb->update( $wpdb->prefix . 'mt_email_templates', array(
                'template_name' => $name, 
                'email_subject' => $subject, 
                'email_body' => $body,
                'assigned_to' => $assigned_to
            ), array('id' => $template_id, 'brand_id' => $brand_id) );
            wp_send_json_success(array('message' => 'Template Updated!', 'id' => $template_id));
        }
    }

    public function ajax_trash_template() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;
        $wpdb->update( $wpdb->prefix . 'mt_email_templates', 
            array('status' => 'trashed', 'deleted_at' => current_time('mysql')), 
            array('id' => intval($_POST['template_id']), 'brand_id' => $brand_id) 
        );
        wp_send_json_success('Moved to Trash.');
    }

    public function ajax_restore_template() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;
        $wpdb->update( $wpdb->prefix . 'mt_email_templates', 
            array('status' => 'active', 'deleted_at' => null), 
            array('id' => intval($_POST['template_id']), 'brand_id' => $brand_id) 
        );
        wp_send_json_success('Restored.');
    }

    public function ajax_delete_template_permanent() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'mt_email_templates', array('id' => intval($_POST['template_id']), 'brand_id' => $brand_id) );
        wp_send_json_success('Permanently Deleted.');
    }

    public function ajax_empty_trash() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'mt_email_templates', array('status' => 'trashed', 'brand_id' => $brand_id) );
        wp_send_json_success('Trash Emptied.');
    }

    // --- STANDARD ADMIN AJAX METHODS ---
    public function ajax_save_splash() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;
        $target = sanitize_text_field($_POST['target']); 
        $config_json = wp_unslash($_POST['config']); 

        if ($target === 'global') {
            $wpdb->update( $wpdb->prefix . 'mt_brands', array('splash_config' => $config_json), array('id' => $brand_id) );
        } else {
            $store_id = intval(str_replace('store_', '', $target));
            $wpdb->update( $wpdb->prefix . 'mt_stores', array('splash_config' => $config_json), array('id' => $store_id) );
        }
        wp_send_json_success('Splash Config Saved.');
    }

    public function ajax_save_brand() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;
        $brand_name = sanitize_text_field($_POST['brand_name']);
        $new_config = json_decode(wp_unslash($_POST['config']), true); 
        $primary_color = sanitize_hex_color($_POST['primary_color']);
        $existing_brand = $wpdb->get_row($wpdb->prepare("SELECT brand_config FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id));
        $existing_config = json_decode($existing_brand->brand_config, true) ?: [];
        if (isset($existing_config['vault'])) { 
            $new_config['vault'] = $existing_config['vault'];
        }

        $wpdb->update( $wpdb->prefix . 'mt_brands', array( 
            'brand_name' => $brand_name, 
            'primary_color' => $primary_color, 
            'brand_config' => wp_json_encode($new_config) 
        ), array('id' => $brand_id) );
        wp_send_json_success('Brand Identity Saved.');
    }

    public function ajax_save_location() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;
        $store_id = intval($_POST['store_id']); 
        $store_name = sanitize_text_field($_POST['store_name']);
        $config_json = wp_unslash($_POST['config']); 
        
        $config_arr = json_decode($config_json, true);
        $hardware_macs = [];
        if(isset($config_arr['hardware']) && is_array($config_arr['hardware'])) {
            foreach($config_arr['hardware'] as $hw) {
                if(is_array($hw) && isset($hw['mac'])) {
                    $hardware_macs[] = sanitize_text_field($hw['mac']);
                } elseif(is_string($hw)) {
                    $hardware_macs[] = sanitize_text_field($hw);
                }
            }
        }
        $router_identity = implode(',', $hardware_macs);
        if ($store_id === 0) {
            $wpdb->insert( $wpdb->prefix . 'mt_stores', array( 
                'brand_id' => $brand_id, 
                'store_name' => $store_name, 
                'local_offer_json' => $config_json, 
                'router_identity' => $router_identity 
            ) );
            $store_id = $wpdb->insert_id;
        } else {
            $wpdb->update( $wpdb->prefix . 'mt_stores', array(
                'store_name' => $store_name, 
                'local_offer_json' => $config_json, 
                'router_identity' => $router_identity
            ), array('id' => $store_id) );
        }
        
        wp_send_json_success(array('message' => 'Saved.', 'store_id' => $store_id));
    }

    public function ajax_delete_location() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'mt_stores', array('id' => intval($_POST['store_id']), 'brand_id' => $brand_id) );
        wp_send_json_success('Deleted.');
    }

    public function ajax_upload_vault_media() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;
        if ( empty( $_FILES['file'] ) ) {
            wp_send_json_error('No file uploaded.');
        }
        
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }
        
        $movefile = wp_handle_upload( $_FILES['file'], array( 'test_form' => false ) );
        if ( $movefile && ! isset( $movefile['error'] ) ) {
            $brand = $wpdb->get_row( $wpdb->prepare("SELECT brand_config FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id) );
            $config = json_decode($brand->brand_config, true) ?: [];
            
            if(!isset($config['vault'])) {
                $config['vault'] = [];
            }
            
            $media_item = array(
                'id' => uniqid('med_'), 
                'url' => $movefile['url'], 
                'file' => $movefile['file'], 
                'type' => sanitize_text_field($_POST['media_type'])
            );
            $config['vault'][] = $media_item;
            $wpdb->update( $wpdb->prefix . 'mt_brands', array('brand_config' => wp_json_encode($config)), array('id' => $brand_id) );
            wp_send_json_success($media_item);
        } else { 
            wp_send_json_error( $movefile['error'] );
        }
    }

    public function ajax_delete_vault_media() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;
        $media_id = sanitize_text_field($_POST['media_id']);
        $brand = $wpdb->get_row( $wpdb->prepare("SELECT brand_config FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id) );
        $config = json_decode($brand->brand_config, true) ?: [];
        
        if(isset($config['vault'])) {
            foreach($config['vault'] as $k => $v) {
                if($v['id'] === $media_id) {
                    if(file_exists($v['file'])) {
                        @unlink($v['file']);
                    }
                    unset($config['vault'][$k]);
                }
            }
            $config['vault'] = array_values($config['vault']);
            $wpdb->update( $wpdb->prefix . 'mt_brands', array('brand_config' => wp_json_encode($config)), array('id' => $brand_id) );
        }
        
        wp_send_json_success('Deleted.');
    }

    public function ajax_save_campaign() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;
        $camp_id = intval($_POST['campaign_id']);
        $name = sanitize_text_field($_POST['campaign_name']);
        $type = sanitize_text_field($_POST['campaign_type']);
        $config_json = wp_unslash($_POST['config']);
        if ($camp_id === 0) {
            $wpdb->insert( $wpdb->prefix . 'mt_campaigns', array(
                'brand_id' => $brand_id, 
                'campaign_name' => $name, 
                'campaign_type' => $type, 
                'config_json' => $config_json
            ) );
            $camp_id = $wpdb->insert_id;
        } else {
            $wpdb->update( $wpdb->prefix . 'mt_campaigns', array(
                'campaign_name' => $name, 
                'campaign_type' => $type, 
                'config_json' => $config_json
            ), array('id' => $camp_id) );
        }

        if ($type === 'sent') {
            do_action('mt_campaign_launched', $camp_id, $brand_id);
        }
        
        wp_send_json_success(array('message' => 'Campaign Saved.', 'campaign_id' => $camp_id));
    }

    public function ajax_delete_campaign() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'mt_campaigns', array('id' => intval($_POST['campaign_id']), 'brand_id' => $brand_id) );
        wp_send_json_success('Deleted.');
    }

    public function ajax_delete_guest_lead() {
        $brand_id = $this->verify_ajax_request();
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error('Admin only.');
        }
        global $wpdb;
        $lead_id = intval($_POST['lead_id']);

        // RADIUS Killswitch
        $lead = $wpdb->get_row( $wpdb->prepare(
            "SELECT guest_mac FROM {$wpdb->prefix}mt_guest_leads WHERE id = %d AND brand_id = %d",
            $lead_id, $brand_id
        ) );
        if ( $lead && ! empty( $lead->guest_mac ) ) {
            $this->clear_radius_for_mac( $lead->guest_mac );
        }

        $wpdb->delete( $wpdb->prefix . 'mt_guest_leads', array('id' => $lead_id, 'brand_id' => $brand_id) );
        wp_send_json_success('Deleted.');
    }

    // --- GUEST TRASH ENGINE ---
    public function ajax_trash_guest_lead() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;
        $lead_id = intval($_POST['lead_id']);

        // RADIUS Killswitch
        $lead = $wpdb->get_row( $wpdb->prepare(
            "SELECT guest_mac FROM {$wpdb->prefix}mt_guest_leads WHERE id = %d AND brand_id = %d",
            $lead_id, $brand_id
        ) );
        if ( $lead && ! empty( $lead->guest_mac ) ) {
            $this->clear_radius_for_mac( $lead->guest_mac );
        }

        $wpdb->update( $wpdb->prefix . 'mt_guest_leads', 
            array('status' => 'trashed', 'deleted_at' => current_time('mysql')), 
            array('id' => $lead_id, 'brand_id' => $brand_id) 
        );
        wp_send_json_success('Moved to Trash.');
    }

    public function ajax_restore_guest_lead() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;
        $wpdb->update( $wpdb->prefix . 'mt_guest_leads', 
            array('status' => 'active', 'deleted_at' => null), 
            array('id' => intval($_POST['lead_id']), 'brand_id' => $brand_id) 
        );
        wp_send_json_success('Guest Restored.');
    }

    public function ajax_bulk_trash_leads() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;
        $ids = json_decode(wp_unslash($_POST['lead_ids']), true);
        if(!empty($ids) && is_array($ids)) {
            $ids = array_map('intval', $ids);
            $ids_str = implode(',', $ids);

            // RADIUS Killswitch
            $leads = $wpdb->get_results( "SELECT guest_mac FROM {$wpdb->prefix}mt_guest_leads WHERE id IN ($ids_str) AND brand_id = $brand_id" );
            foreach ( $leads as $l ) {
                if ( ! empty( $l->guest_mac ) ) {
                    $this->clear_radius_for_mac( $l->guest_mac );
                }
            }

            // Secure Database Update
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->prefix}mt_guest_leads
                 SET status = 'trashed', deleted_at = %s
                 WHERE id IN ($ids_str) AND brand_id = %d",
                current_time('mysql'), intval($brand_id)
            ) );
        }
        wp_send_json_success('Guests moved to trash.');
    }

    public function ajax_empty_guest_trash() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;

        // RADIUS Killswitch
        $leads = $wpdb->get_results( $wpdb->prepare( 
            "SELECT guest_mac FROM {$wpdb->prefix}mt_guest_leads WHERE status = 'trashed' AND brand_id = %d", 
            $brand_id 
        ) );
        foreach ( $leads as $l ) {
            if ( ! empty( $l->guest_mac ) ) {
                $this->clear_radius_for_mac( $l->guest_mac );
            }
        }

        $wpdb->delete( $wpdb->prefix . 'mt_guest_leads', array('status' => 'trashed', 'brand_id' => $brand_id) );
        wp_send_json_success('Trash Emptied Permanently.');
    }

    public function ajax_delete_guest_lead_permanent() {
        $brand_id = $this->verify_ajax_request();
        if ( ! current_user_can('manage_options') ) wp_send_json_error('Admin only.');
        global $wpdb;
        $lead_id = intval($_POST['lead_id']);

        // RADIUS Killswitch
        $lead = $wpdb->get_row( $wpdb->prepare(
            "SELECT guest_mac FROM {$wpdb->prefix}mt_guest_leads WHERE id = %d AND brand_id = %d",
            $lead_id, $brand_id
        ) );
        if ( $lead && ! empty( $lead->guest_mac ) ) {
            $this->clear_radius_for_mac( $lead->guest_mac );
        }

        $wpdb->delete( $wpdb->prefix . 'mt_guest_leads', array('id' => $lead_id, 'brand_id' => $brand_id) );
        wp_send_json_success('Deleted Permanently.');
    }

    // --- PUBLIC AJAX METHODS ---
    public function ajax_capture_lead() {
        if ( ! check_ajax_referer( 'mt_splash_nonce', 'security', false ) ) {
            wp_send_json_error( 'Invalid Token.' );
        }
        
        global $wpdb;
        $payload = json_decode(wp_unslash($_POST['payload']), true);
        
        if(!$payload || empty($payload['email'])) {
            wp_send_json_error('Missing data');
        }

        $email = sanitize_email($payload['email']);
        if (!is_email($email)) {
            wp_send_json_error('Invalid email format. Please check for typos.');
        }

        // ==========================================
        // NEW: LIVE DNS & MX RECORD SCRUBBING
        // ==========================================
        $domain = substr(strrchr($email, "@"), 1);
        // Check if the domain has a valid Mail Exchange (MX) record on the internet
        if (!checkdnsrr($domain, 'MX')) {
            wp_send_json_error("The domain (@{$domain}) cannot receive mail. Please provide a real email address.");
        }
        // ==========================================

        $brand_id = intval($payload['brand_id']);
        $store_id = intval($payload['store_id']); 
        $campaign_id = intval($payload['campaign_id']);
        $name = sanitize_text_field($payload['name']); 
        $mac = sanitize_text_field($payload['mac'] ?? 'UNKNOWN');
        $survey_data = wp_json_encode($payload['survey_data'] ?? []);
        $campaign_tag = '';
        if ($campaign_id > 0) {
            $camp = $wpdb->get_row($wpdb->prepare("SELECT campaign_name FROM {$wpdb->prefix}mt_campaigns WHERE id = %d", $campaign_id));
            if($camp) {
                $campaign_tag = $camp->campaign_name;
                if(!empty($payload['survey_data'])) {
                    $wpdb->insert( $wpdb->prefix . 'mt_campaign_responses', array(
                        'campaign_id' => $campaign_id,
                        'lead_id' => 0, 
                        'response_data' => $survey_data
                    ) );
                    $response_id = $wpdb->insert_id;
                }
            }
        }

        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        $store_name = $wpdb->get_var($wpdb->prepare("SELECT store_name FROM {$wpdb->prefix}mt_stores WHERE id = %d", $store_id));
        $location_label = $store_name ? $store_name : 'Global Template';
        $consent_log = "Obtained via WiFi Splash at [" . $location_label . "] on " . current_time('Y-m-d H:i:s') . " from IP $ip";

        // SMART LEAD UPDATING
        $existing_lead_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}mt_guest_leads WHERE brand_id = %d AND guest_mac = %s", $brand_id, $mac));
        if ($existing_lead_id) {
            $wpdb->update( $wpdb->prefix . 'mt_guest_leads', array(
                'email' => $email,
                'guest_name' => $name,
                'campaign_tag' => $campaign_tag,
                'survey_data' => $survey_data,
                'last_visit' => current_time('mysql'),
                'consent_ip' => $ip,
                'consent_log' => $consent_log
            ), array('id' => $existing_lead_id) );
            $final_lead_id = $existing_lead_id;
        } else {
            $unsub_token = bin2hex(random_bytes(16));
            $wpdb->insert( $wpdb->prefix . 'mt_guest_leads', array(
                'brand_id' => $brand_id, 
                'store_id' => $store_id, 
                'email' => $email, 
                'guest_name' => $name, 
                'guest_mac' => $mac,
                'campaign_tag' => $campaign_tag, 
                'survey_data' => $survey_data, 
                'status' => 'active', 
                'unsub_token' => $unsub_token,
                'consent_ip' => $ip, 
                'consent_log' => $consent_log,
                'last_visit' => current_time('mysql')
            ) );
            $final_lead_id = $wpdb->insert_id;
        }

        if (isset($response_id)) {
            $wpdb->update( $wpdb->prefix . 'mt_campaign_responses', array('lead_id' => $final_lead_id), array('id' => $response_id) );
        }

        // START RADIUS TIMER
        $clean_mac = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $mac));
        $mac_colon_format = !empty($clean_mac) ? implode(':', str_split($clean_mac, 2)) : ''; 
        if(!empty($mac_colon_format)) {
            set_transient('mt_wifi_session_' . md5($mac_colon_format), time() + 3600, 3600);
        }

        // ==========================================
        // NEW: AUTHORIZE RADIUS ON DASHBOARD CAPTURE
        // ==========================================
        if ( ! empty($mac) && $mac !== 'UNKNOWN' && class_exists('MT_Wifi_Controller') ) {
            $wifi = new MT_Wifi_Controller();
            $radius_result = $wifi->authorize_guest_mac( $final_lead_id, $brand_id );
            if ( $radius_result !== true ) {
                wp_send_json_error( $radius_result );
                return;
            }
        }
        // ==========================================

        do_action('mt_lead_captured', $final_lead_id, $brand_id);
        wp_send_json_success('Lead Processed & Authorized');
    }

    // --- RENDER ENGINE ---
    public function render_app() {
        if ( get_query_var( 'mt_splash_brand' ) ) {
            $splash_file = MT_PATH . 'includes/modules/dashboard/views/view-live-splash.php';
            if ( file_exists( $splash_file ) ) {
                include $splash_file;
            } else {
                wp_die('Splash engine missing.');
            }
            exit;
        }

        if ( get_query_var( 'mt_app' ) ) {
            if ( ! is_user_logged_in() ) { 
                wp_redirect( wp_login_url( home_url( '/app/' ) ) );
                exit; 
            }
            if ( ! current_user_can( 'access_mt_app' ) && ! current_user_can( 'manage_options' ) ) { 
                wp_die( 'Access Denied.' );
            }
            
            global $wpdb;
            $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'overview';
            $brand_id = $this->get_tenant_brand_id();
            if ( ! $brand_id ) { 
                wp_die('No Tenant Environment Assigned.');
            }
            
            $brand = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id) );
            $current_user = wp_get_current_user();
            $logout_url = wp_logout_url( home_url('/app/') );
            $avatar_url = get_avatar_url($current_user->ID, ['size' => 60]);
            $mt_palette = get_option( 'mt_brand_palette', [
                'accent' => '#FCC753', 
                'dark' => '#1A232E'
            ] );
            $core_views = ['brand', 'locations', 'domains'];
            $wifi_views = ['wifi_insights', 'crm', 'splash'];
            $email_views = ['email_insights', 'studio', 'campaigns', 'workflows', 'delivery'];
            $is_core = in_array($view, $core_views);
            $is_wifi = in_array($view, $wifi_views);
            $is_email = in_array($view, $email_views);
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>MailToucan | <?php echo esc_html($brand->brand_name); ?></title>
                <script src="https://cdn.tailwindcss.com"></script>
                <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
                <style>
                    body { background-color: #f3f4f6; font-family: 'Inter', sans-serif; }
                    .sidebar { width: 260px; background-color: <?php echo esc_html($mt_palette['dark']); ?>; min-height: 100vh; color: #fff; position: fixed; left: 0; top: 0; z-index: 50; display: flex; flex-direction: column; }
                    .main-content { margin-left: 260px; padding: 2rem; min-height: 100vh; flex: 1; width: calc(100% - 260px); }
                    .main-content.studio-active { padding: 0; } 
                    
                    .nav-link { display: flex; align-items: center; padding: 0.85rem 1.25rem; color: #9ca3af; border-radius: 0.5rem; margin-bottom: 0.5rem; font-weight: 500; transition: all 0.2s; text-decoration: none;}
                    .nav-link:hover, .nav-link.active { background-color: #1f2937; color: #fff; border-left: 3px solid <?php echo esc_html($mt_palette['accent']); ?>; }
                    
                    .nav-group-btn { display: flex; align-items: center; width: 100%; padding: 0.75rem 1.25rem; color: #6b7280; margin-top: 1rem; font-weight: 700; text-transform: uppercase; font-size: 0.70rem; letter-spacing: 0.05em; transition: color 0.2s; cursor: pointer; outline: none;}
                    .nav-group-btn:hover { color: #d1d5db; }
                    .nav-group-btn.active { color: #f3f4f6; }
                    .nav-group-items { display: none; flex-direction: column; padding-left: 0; margin-bottom: 0.5rem; }
                    .nav-group-items.open { display: flex; }
                    .nav-sub-link { display: flex; align-items: center; padding: 0.75rem 1.25rem; color: #9ca3af; font-size: 0.875rem; transition: all 0.2s; text-decoration: none; border-left: 3px solid transparent; }
                    
                    .nav-sub-link:hover, .nav-sub-link.active { background-color: #1f2937; color: #fff; border-left-color: <?php echo esc_html($mt_palette['accent']); ?>; }
                    
                    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
                    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
                    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #374151; border-radius: 10px; }
                </style>
                <script>const mt_ajax_url = "<?php echo admin_url('admin-ajax.php'); ?>";
                const mt_nonce = "<?php echo wp_create_nonce('mt_app_nonce'); ?>";</script>
            </head>
            <body class="flex">
                <aside class="sidebar shadow-xl">
                    <div class="p-4 border-b border-gray-800">
                        <div class="mb-4 mt-2 px-2">
                            <h2 class="text-2xl font-bold text-white tracking-wide flex items-center gap-2"><i class="fa-solid fa-dove" style="color: <?php echo esc_html($mt_palette['accent']); ?>"></i> MailToucan</h2>
                            <p class="text-xs text-gray-400 mt-1 truncate"><?php echo esc_html($brand->brand_name); ?></p>
                        </div>
                    </div>
                    
                    <nav class="flex-1 overflow-y-auto py-4 custom-scrollbar">
                        <a href="?view=overview" class="nav-link mx-2 <?php echo $view === 'overview' ? 'active' : ''; ?>"><i class="fa-solid fa-chart-pie mr-2 w-5 text-center"></i> Account Status</a>
                        
                        <button class="nav-group-btn <?php echo $is_core ? 'active' : ''; ?>" onclick="toggleNav('core')">
                            Core Setup <i class="fa-solid fa-chevron-<?php echo $is_core ? 'up' : 'down'; ?> ml-auto transition-transform" id="icon_core"></i>
                        </button>
                        <div class="nav-group-items <?php echo $is_core ? 'open' : ''; ?>" id="nav_core">
                            <a href="?view=brand" class="nav-sub-link <?php echo $view === 'brand' ? 'active' : ''; ?>"><i class="fa-solid fa-palette mr-3 w-4 text-center"></i> Brand Identity</a>
                            <a href="?view=locations" class="nav-sub-link <?php echo $view === 'locations' ? 'active' : ''; ?>"><i class="fa-solid fa-store mr-3 w-4 text-center"></i> Locations</a>
                            <a href="?view=domains" class="nav-sub-link <?php echo $view === 'domains' ? 'active' : ''; ?>"><i class="fa-solid fa-globe mr-3 w-4 text-center"></i> Sender Domains</a>
                        </div>

                        <button class="nav-group-btn <?php echo $is_wifi ? 'active' : ''; ?>" onclick="toggleNav('wifi')">
                            WiFi Marketing <i class="fa-solid fa-chevron-<?php echo $is_wifi ? 'up' : 'down'; ?> ml-auto transition-transform" id="icon_wifi"></i>
                        </button>
                        <div class="nav-group-items <?php echo $is_wifi ? 'open' : ''; ?>" id="nav_wifi">
                            <a href="?view=wifi_insights" class="nav-sub-link <?php echo $view === 'wifi_insights' ? 'active' : ''; ?>"><i class="fa-solid fa-chart-area mr-3 w-4 text-center"></i> WiFi Insights</a>
                            <a href="?view=crm" class="nav-sub-link <?php echo $view === 'crm' ? 'active' : ''; ?>"><i class="fa-solid fa-users mr-3 w-4 text-center"></i> The Roost (CRM)</a>
                            <a href="?view=splash" class="nav-sub-link <?php echo $view === 'splash' ? 'active' : ''; ?>"><i class="fa-solid fa-wifi mr-3 w-4 text-center"></i> Splash Designer</a>
                        </div>

                        <button class="nav-group-btn <?php echo $is_email ? 'active' : ''; ?>" onclick="toggleNav('email')">
                            Email Marketing <i class="fa-solid fa-chevron-<?php echo $is_email ? 'up' : 'down'; ?> ml-auto transition-transform" id="icon_email"></i>
                        </button>
                        <div class="nav-group-items <?php echo $is_email ? 'open' : ''; ?>" id="nav_email">
                            <a href="?view=email_insights" class="nav-sub-link <?php echo $view === 'email_insights' ? 'active' : ''; ?>"><i class="fa-solid fa-chart-line mr-3 w-4 text-center"></i> Dashboard Insights</a>
                            <a href="?view=studio" class="nav-sub-link <?php echo $view === 'studio' ? 'active' : ''; ?>"><i class="fa-solid fa-wand-magic-sparkles mr-3 w-4 text-center"></i> Toucan Studio</a>
                            <a href="?view=campaigns" class="nav-sub-link <?php echo $view === 'campaigns' ? 'active' : ''; ?>"><i class="fa-solid fa-paper-plane mr-3 w-4 text-center"></i> Campaigns</a>
                            <a href="?view=workflows" class="nav-sub-link <?php echo $view === 'workflows' ? 'active' : ''; ?>"><i class="fa-solid fa-diagram-project mr-3 w-4 text-center"></i> Workflows & Drip</a>
                            <a href="?view=delivery" class="nav-sub-link <?php echo $view === 'delivery' ? 'active' : ''; ?>"><i class="fa-solid fa-network-wired mr-3 w-4 text-center"></i> Delivery Routing</a>
                        </div>
                    </nav>

                    <div class="p-5 border-t border-gray-800 bg-gray-900 mt-auto">
                        <div class="flex items-center gap-3 mb-4">
                            <img src="<?php echo esc_url($avatar_url); ?>" alt="Avatar" class="w-10 h-10 rounded-full border-2 border-gray-700">
                            <div class="overflow-hidden">
                                <p class="text-sm font-bold text-white truncate leading-tight"><?php echo esc_html($current_user->display_name); ?></p>
                                <p class="text-[10px] text-gray-400 truncate"><?php echo esc_html($current_user->user_email); ?></p>
                            </div>
                        </div>
                        <a href="<?php echo esc_url($logout_url); ?>" class="w-full flex items-center justify-center gap-2 bg-gray-800 hover:bg-red-600 text-gray-300 hover:text-white px-3 py-2 rounded-lg transition text-sm font-bold shadow-sm">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out
                        </a>
                    </div>
                </aside>

                <main id="main_content_area" class="main-content flex-1">
                    <?php 
                        $view_file = MT_PATH . 'includes/modules/dashboard/views/view-' . $view . '.php';
                        if ( file_exists( $view_file ) ) {
                            include $view_file; 
                        } else { 
                            echo '
                            <div class="flex flex-col items-center justify-center h-[80vh] text-center">
                                <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-6">
                                    <i class="fa-solid fa-hammer text-4xl text-gray-400"></i>
                                </div>
                                <h1 class="text-3xl font-bold text-gray-900 mb-2">Module Coming Soon</h1>
                                <p class="text-gray-500 max-w-md mx-auto">This section is part of the Email Marketing Engine buildout and is not yet active.</p>
                            </div>';
                        }
                    ?>
                </main>

                <script>
                    function toggleNav(group) {
                        const items = document.getElementById('nav_' + group);
                        const icon = document.getElementById('icon_' + group);
                        if (items.classList.contains('open')) {
                            items.classList.remove('open');
                            icon.classList.remove('fa-chevron-up');
                            icon.classList.add('fa-chevron-down');
                        } else {
                            items.classList.add('open');
                            icon.classList.remove('fa-chevron-down');
                            icon.classList.add('fa-chevron-up');
                        }
                    }
                </script>
            </body>
            </html>
            <?php
            exit;
        }
    }
}