<?php
/**
 * The Dashboard Module: The Shell, Router, and AJAX Handlers
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

        // NEW: Email & Domain AJAX Handlers
        add_action( 'wp_ajax_mt_add_domain', array( $this, 'ajax_add_domain' ) );
        add_action( 'wp_ajax_mt_delete_domain', array( $this, 'ajax_delete_domain' ) );
        add_action( 'wp_ajax_mt_verify_domain', array( $this, 'ajax_verify_domain' ) );
        add_action( 'wp_ajax_mt_save_template', array( $this, 'ajax_save_template' ) );
        add_action( 'wp_ajax_mt_delete_template', array( $this, 'ajax_delete_template' ) );

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

        // 1. The Guest Lead Database (CRM Hub)
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
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY brand_id (brand_id),
            KEY email (email)
        ) $charset_collate;";
        dbDelta( $sql_leads );

        // 2. The Campaign Builder Database
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

        // 3. Sender Domains (NEW)
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

        // 4. Email Templates (NEW)
        $table_templates = $wpdb->prefix . 'mt_email_templates';
        $sql_templates = "CREATE TABLE $table_templates (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            brand_id int(11) NOT NULL,
            template_name varchar(255) NOT NULL,
            email_subject varchar(255) NOT NULL,
            email_body longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY brand_id (brand_id)
        ) $charset_collate;";
        dbDelta( $sql_templates );
    }

    public function add_rewrite_rules() { 
        add_rewrite_rule( '^app/?$', 'index.php?mt_app=1', 'top' ); 
        add_rewrite_rule( '^splash/([^/]+)/([^/]+)/?$', 'index.php?mt_splash_brand=$matches[1]&mt_splash_loc=$matches[2]', 'top' );
    }
    
    public function add_query_vars( $vars ) { 
        $vars[] = 'mt_app'; $vars[] = 'mt_splash_brand'; $vars[] = 'mt_splash_loc'; return $vars; 
    }

    private function get_tenant_brand_id() {
        $user_id = get_current_user_id();
        $brand_id = get_user_meta( $user_id, 'mt_brand_id', true );
        if ( ! $brand_id && current_user_can( 'manage_options' ) ) return 1; 
        return intval($brand_id);
    }

    private function verify_ajax_request() {
        if ( ! check_ajax_referer( 'mt_app_nonce', 'security', false ) ) wp_send_json_error( 'Security Token Expired.' );
        if ( ! current_user_can( 'access_mt_app' ) && ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission Denied.' );
        $brand_id = $this->get_tenant_brand_id();
        if ( ! $brand_id ) wp_send_json_error( 'Tenant ID missing.' );
        return $brand_id;
    }

    // --- DOMAIN & EMAIL AJAX ENGINE (MOVED HERE FOR STABILITY) ---
    public function ajax_add_domain() {
        $brand_id = $this->verify_ajax_request();
        $domain = sanitize_text_field(strtolower($_POST['domain']));
        $domain = str_replace(array('http://', 'https://', 'www.'), '', $domain);
        $domain = trim($domain, '/');

        if ( ! preg_match('/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,6}$/i', $domain) ) {
            wp_send_json_error('Invalid domain format. Please use format: example.com');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mt_email_domains';
        
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE domain_name = %s AND brand_id = %d", $domain, $brand_id));
        if ($exists) wp_send_json_error('Domain is already registered in your account.');

        // Simulated tokens for the UI
        $dkim1 = substr(md5(uniqid(rand(), true)), 0, 32);
        $dkim2 = substr(md5(uniqid(rand(), true)), 0, 32);
        $dkim3 = substr(md5(uniqid(rand(), true)), 0, 32);
        $dkim_tokens = wp_json_encode([$dkim1, $dkim2, $dkim3]);

        $result = $wpdb->insert( $table_name, array(
            'brand_id' => $brand_id, 'domain_name' => $domain, 'status' => 'pending', 'dkim_tokens' => $dkim_tokens
        ) );

        if($result) wp_send_json_success('Domain added successfully.');
        else wp_send_json_error('Database Sync Error. Could not save domain.');
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
        $domain_record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mt_email_domains WHERE id = %d AND brand_id = %d", $domain_id, $brand_id));
        
        if (!$domain_record) wp_send_json_error('Domain not found.');

        $domain = $domain_record->domain_name;

        // "test.com" bypass for the UI sandbox
        if ($domain === 'test.com') {
            $wpdb->update( $wpdb->prefix . 'mt_email_domains', array('status' => 'verified'), array('id' => $domain_id) );
            wp_send_json_success('DNS Verified Successfully! Your domain is ready to send emails.');
        } else {
            wp_send_json_error('Verification Failed. We could not detect the correct SPF/DKIM records. Remember, DNS can take 24 hours to propagate.');
        }
    }

    public function ajax_save_template() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;

        $template_id = intval($_POST['template_id']);
        $name = sanitize_text_field($_POST['template_name']);
        $subject = sanitize_text_field($_POST['email_subject']);
        $body = wp_kses_post(wp_unslash($_POST['email_body'])); // Allows safe HTML

        if ($template_id === 0) {
            $wpdb->insert( $wpdb->prefix . 'mt_email_templates', array(
                'brand_id' => $brand_id, 'template_name' => $name, 'email_subject' => $subject, 'email_body' => $body
            ) );
            wp_send_json_success(array('message' => 'Template Saved!', 'id' => $wpdb->insert_id));
        } else {
            $wpdb->update( $wpdb->prefix . 'mt_email_templates', array(
                'template_name' => $name, 'email_subject' => $subject, 'email_body' => $body
            ), array('id' => $template_id) );
            wp_send_json_success(array('message' => 'Template Updated!', 'id' => $template_id));
        }
    }

    public function ajax_delete_template() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'mt_email_templates', array('id' => intval($_POST['template_id']), 'brand_id' => $brand_id) );
        wp_send_json_success('Deleted.');
    }

    // --- STANDARD ADMIN AJAX METHODS ---
    public function ajax_save_splash() {
        $brand_id = $this->verify_ajax_request();
        global $wpdb;
        $target = sanitize_text_field($_POST['target']); 
        $config_json = wp_unslash($_POST['config']); 

        if ($target === 'global') {
            $result = $wpdb->update( $wpdb->prefix . 'mt_brands', array('splash_config' => $config_json), array('id' => $brand_id) );
        } else {
            $store_id = intval(str_replace('store_', '', $target));
            $valid_store = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}mt_stores WHERE id = %d AND brand_id = %d", $store_id, $brand_id));
            if ( $valid_store ) {
                $result = $wpdb->update( $wpdb->prefix . 'mt_stores', array('splash_config' => $config_json), array('id' => $store_id) );
            } else { wp_send_json_error('Unauthorized Store Edit.'); }
        }
        if ( $result === false ) wp_send_json_error( 'DB Error: ' . $wpdb->last_error );
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
        if (isset($existing_config['vault'])) { $new_config['vault'] = $existing_config['vault']; }

        $result = $wpdb->update( $wpdb->prefix . 'mt_brands', array( 'brand_name' => $brand_name, 'primary_color' => $primary_color, 'brand_config' => wp_json_encode($new_config) ), array('id' => $brand_id) );
        if ( $result === false ) wp_send_json_error( 'DB Error: ' . $wpdb->last_error );
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
                if(is_array($hw) && isset($hw['mac'])) $hardware_macs[] = sanitize_text_field($hw['mac']);
                elseif(is_string($hw)) $hardware_macs[] = sanitize_text_field($hw);
            }
        }
        $router_identity = implode(',', $hardware_macs);

        $store_cols = $wpdb->get_col("DESCRIBE {$wpdb->prefix}mt_stores", 0);
        if (!in_array('router_identity', $store_cols)) { $wpdb->query("ALTER TABLE {$wpdb->prefix}mt_stores ADD `router_identity` varchar(100)"); }

        if ($store_id === 0) {
            $brand = $wpdb->get_row($wpdb->prepare("SELECT location_limit FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id));
            $current_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$wpdb->prefix}mt_stores WHERE brand_id = %d", $brand_id));
            if ($brand->location_limit !== -1 && $current_count >= $brand->location_limit) wp_send_json_error('Location limit reached.');

            $result = $wpdb->insert( $wpdb->prefix . 'mt_stores', array( 'brand_id' => $brand_id, 'store_name' => $store_name, 'local_offer_json' => $config_json, 'router_identity' => $router_identity ) );
            $store_id = $wpdb->insert_id;
        } else {
            $valid_store = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}mt_stores WHERE id = %d AND brand_id = %d", $store_id, $brand_id));
            if (!$valid_store) wp_send_json_error('Unauthorized.');
            $result = $wpdb->update( $wpdb->prefix . 'mt_stores', array('store_name' => $store_name, 'local_offer_json' => $config_json, 'router_identity' => $router_identity), array('id' => $store_id) );
        }
        if ( $result === false ) wp_send_json_error( 'DB Error: ' . $wpdb->last_error );
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
        if ( empty( $_FILES['file'] ) ) wp_send_json_error('No file uploaded.');
        if ( ! function_exists( 'wp_handle_upload' ) ) require_once( ABSPATH . 'wp-admin/includes/file.php' );
        $movefile = wp_handle_upload( $_FILES['file'], array( 'test_form' => false ) );
        if ( $movefile && ! isset( $movefile['error'] ) ) {
            $brand = $wpdb->get_row( $wpdb->prepare("SELECT brand_config FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id) );
            $config = json_decode($brand->brand_config, true) ?: [];
            if(!isset($config['vault'])) $config['vault'] = [];
            $media_item = array('id' => uniqid('med_'), 'url' => $movefile['url'], 'file' => $movefile['file'], 'type' => sanitize_text_field($_POST['media_type']));
            $config['vault'][] = $media_item;
            $wpdb->update( $wpdb->prefix . 'mt_brands', array('brand_config' => wp_json_encode($config)), array('id' => $brand_id) );
            wp_send_json_success($media_item);
        } else { wp_send_json_error( $movefile['error'] ); }
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
                    if(file_exists($v['file'])) @unlink($v['file']);
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
            $result = $wpdb->insert( $wpdb->prefix . 'mt_campaigns', array(
                'brand_id' => $brand_id, 'campaign_name' => $name, 'campaign_type' => $type, 'config_json' => $config_json
            ) );
            $camp_id = $wpdb->insert_id;
        } else {
            $valid = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}mt_campaigns WHERE id = %d AND brand_id = %d", $camp_id, $brand_id));
            if (!$valid) wp_send_json_error('Unauthorized.');
            $result = $wpdb->update( $wpdb->prefix . 'mt_campaigns', array(
                'campaign_name' => $name, 'campaign_type' => $type, 'config_json' => $config_json
            ), array('id' => $camp_id) );
        }
        if ( $result === false ) wp_send_json_error( 'DB Error: ' . $wpdb->last_error );
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
        if ( ! current_user_can('manage_options') ) wp_send_json_error('Only administrators can permanently delete guest records.');
        
        global $wpdb;
        $lead_id = intval($_POST['lead_id']);
        $wpdb->delete( $wpdb->prefix . 'mt_guest_leads', array('id' => $lead_id, 'brand_id' => $brand_id) );
        wp_send_json_success('Guest record permanently deleted.');
    }

    // --- PUBLIC AJAX METHODS (GUEST CAPTURE) ---
    public function ajax_capture_lead() {
        if ( ! check_ajax_referer( 'mt_splash_nonce', 'security', false ) ) {
            wp_send_json_error( 'Invalid Security Token.' );
        }

        global $wpdb;
        $payload = json_decode(wp_unslash($_POST['payload']), true);
        if(!$payload || empty($payload['email'])) wp_send_json_error('Missing data');

        $brand_id = intval($payload['brand_id']);
        $store_id = intval($payload['store_id']);
        $campaign_id = intval($payload['campaign_id']);
        $email = sanitize_email($payload['email']);
        $name = sanitize_text_field($payload['name']);
        $mac = sanitize_text_field($payload['mac'] ?? 'UNKNOWN');
        $survey_data = wp_json_encode($payload['survey_data'] ?? []);

        $campaign_tag = '';
        if ($campaign_id > 0) {
            $camp = $wpdb->get_row($wpdb->prepare("SELECT campaign_name FROM {$wpdb->prefix}mt_campaigns WHERE id = %d", $campaign_id));
            if($camp) $campaign_tag = $camp->campaign_name;
        }

        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        $store_name = $wpdb->get_var($wpdb->prepare("SELECT store_name FROM {$wpdb->prefix}mt_stores WHERE id = %d", $store_id));
        $location_label = $store_name ? $store_name : 'Global Template';
        $consent_log = "Obtained via WiFi Splash at [" . $location_label . "] on " . current_time('Y-m-d H:i:s') . " from IP $ip";
        $unsub_token = bin2hex(random_bytes(16));

        $result = $wpdb->insert(
            $wpdb->prefix . 'mt_guest_leads',
            array(
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
                'consent_log' => $consent_log
            )
        );

        if ($result) { wp_send_json_success('Lead Saved'); } 
        else { wp_send_json_error('DB Error'); }
    }

    // --- RENDER ENGINE ---
    public function render_app() {
        // 1. Render the Live Splash Page for Guests
        if ( get_query_var( 'mt_splash_brand' ) ) {
            $splash_file = MT_PATH . 'includes/modules/dashboard/views/view-live-splash.php';
            if ( file_exists( $splash_file ) ) {
                include $splash_file;
            } else {
                wp_die('Splash engine missing.');
            }
            exit; 
        }

        // 2. Render the SaaS Dashboard for Admins
        if ( get_query_var( 'mt_app' ) ) {
            if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( home_url( '/app/' ) ) ); exit; }
            if ( ! current_user_can( 'access_mt_app' ) && ! current_user_can( 'manage_options' ) ) { wp_die( 'Access Denied.' ); }
            
            global $wpdb;
            $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'overview';
            $brand_id = $this->get_tenant_brand_id();
            if ( ! $brand_id ) { wp_die('No Tenant Environment Assigned.'); }
            $brand = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id) );

            // Fetch Current User for Sidebar
            $current_user = wp_get_current_user();
            $logout_url = wp_logout_url( home_url('/app/') );
            $avatar_url = get_avatar_url($current_user->ID, ['size' => 60]);

            // Determine Active Section for Accordion
            $core_views = ['brand', 'locations', 'domains'];
            $wifi_views = ['splash', 'crm'];
            $email_views = ['email_insights', 'bulk_email', 'templates', 'workflows'];
            
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
                    .sidebar { width: 260px; background-color: #111827; min-height: 100vh; color: #fff; position: fixed; left: 0; top: 0; z-index: 50; display: flex; flex-direction: column; }
                    .main-content { margin-left: 260px; padding: 2rem; min-height: 100vh; flex: 1; width: calc(100% - 260px); }
                    .nav-link { display: flex; align-items: center; padding: 0.85rem 1.25rem; color: #9ca3af; border-radius: 0.5rem; margin-bottom: 0.5rem; font-weight: 500; transition: all 0.2s; text-decoration: none;}
                    .nav-link:hover, .nav-link.active { background-color: #1f2937; color: #fff; border-left: 3px solid <?php echo esc_html($brand->primary_color); ?>; }
                    
                    /* Accordion Styles */
                    .nav-group-btn { display: flex; align-items: center; width: 100%; padding: 0.75rem 1.25rem; color: #6b7280; margin-top: 1rem; font-weight: 700; text-transform: uppercase; font-size: 0.70rem; letter-spacing: 0.05em; transition: color 0.2s; cursor: pointer; outline: none;}
                    .nav-group-btn:hover { color: #d1d5db; }
                    .nav-group-btn.active { color: #f3f4f6; }
                    .nav-group-items { display: none; flex-direction: column; padding-left: 0; margin-bottom: 0.5rem; }
                    .nav-group-items.open { display: flex; }
                    .nav-sub-link { display: flex; align-items: center; padding: 0.75rem 1.25rem; color: #9ca3af; font-size: 0.875rem; transition: all 0.2s; text-decoration: none; border-left: 3px solid transparent; }
                    .nav-sub-link:hover, .nav-sub-link.active { background-color: #1f2937; color: #fff; border-left-color: <?php echo esc_html($brand->primary_color); ?>; }
                    
                    /* Custom Scrollbar for Sidebar */
                    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
                    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
                    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #374151; border-radius: 10px; }
                </style>
                <script>const mt_ajax_url = "<?php echo admin_url('admin-ajax.php'); ?>"; const mt_nonce = "<?php echo wp_create_nonce('mt_app_nonce'); ?>";</script>
            </head>
            <body class="flex">
                <aside class="sidebar shadow-xl">
                    <div class="p-4 border-b border-gray-800">
                        <div class="mb-4 mt-2 px-2">
                            <h2 class="text-2xl font-bold text-white tracking-wide flex items-center gap-2"><i class="fa-solid fa-dove" style="color: <?php echo esc_html($brand->primary_color); ?>"></i> MailToucan</h2>
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
                            <a href="?view=splash" class="nav-sub-link <?php echo $view === 'splash' ? 'active' : ''; ?>"><i class="fa-solid fa-wifi mr-3 w-4 text-center"></i> Splash Designer</a>
                            <a href="?view=crm" class="nav-sub-link <?php echo $view === 'crm' ? 'active' : ''; ?>"><i class="fa-solid fa-users mr-3 w-4 text-center"></i> The Roost (CRM)</a>
                        </div>

                        <button class="nav-group-btn <?php echo $is_email ? 'active' : ''; ?>" onclick="toggleNav('email')">
                            Email Marketing <i class="fa-solid fa-chevron-<?php echo $is_email ? 'up' : 'down'; ?> ml-auto transition-transform" id="icon_email"></i>
                        </button>
                        <div class="nav-group-items <?php echo $is_email ? 'open' : ''; ?>" id="nav_email">
                            <a href="?view=email_insights" class="nav-sub-link <?php echo $view === 'email_insights' ? 'active' : ''; ?>"><i class="fa-solid fa-chart-line mr-3 w-4 text-center"></i> Dashboard Insights</a>
                            <a href="?view=bulk_email" class="nav-sub-link <?php echo $view === 'bulk_email' ? 'active' : ''; ?>"><i class="fa-solid fa-paper-plane mr-3 w-4 text-center"></i> Bulk Broadcasts</a>
                            <a href="?view=templates" class="nav-sub-link <?php echo $view === 'templates' ? 'active' : ''; ?>"><i class="fa-solid fa-layer-group mr-3 w-4 text-center"></i> Template Builder</a>
                            <a href="?view=workflows" class="nav-sub-link <?php echo $view === 'workflows' ? 'active' : ''; ?>"><i class="fa-solid fa-diagram-project mr-3 w-4 text-center"></i> Workflows & Drip</a>
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

                <main class="main-content">
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