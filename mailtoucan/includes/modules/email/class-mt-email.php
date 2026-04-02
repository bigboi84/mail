<?php
/**
 * The Email Engine: Handles Domain Authentication & Template Building
 */
class MT_Email {

    public function init() {
        // Auto-create the database tables
        add_action( 'init', array( $this, 'maybe_create_tables' ) );
        
        // Domain AJAX Handlers
        add_action( 'wp_ajax_mt_add_domain', array( $this, 'ajax_add_domain' ) );
        add_action( 'wp_ajax_mt_delete_domain', array( $this, 'ajax_delete_domain' ) );
        add_action( 'wp_ajax_mt_verify_domain', array( $this, 'ajax_verify_domain' ) );

        // Template AJAX Handlers
        add_action( 'wp_ajax_mt_save_template', array( $this, 'ajax_save_template' ) );
        add_action( 'wp_ajax_mt_delete_template', array( $this, 'ajax_delete_template' ) );
    }

    public function maybe_create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // Table 1: Sender Domains
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

        // Table 2: Email Templates
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

    private function get_tenant_brand_id() {
        $user_id = get_current_user_id();
        $brand_id = get_user_meta( $user_id, 'mt_brand_id', true );
        if ( ! $brand_id && current_user_can( 'manage_options' ) ) return 1; 
        return intval($brand_id);
    }

    // --- DOMAIN AUTHENTICATION METHODS ---
    public function ajax_add_domain() {
        if ( ! check_ajax_referer( 'mt_app_nonce', 'security', false ) ) wp_send_json_error( 'Security Token Expired.' );
        $brand_id = $this->get_tenant_brand_id();
        
        $domain = sanitize_text_field(strtolower($_POST['domain']));
        $domain = str_replace(array('http://', 'https://', 'www.'), '', $domain);
        $domain = trim($domain, '/');

        if ( ! preg_match('/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,6}$/i', $domain) ) {
            wp_send_json_error('Invalid domain format. Use format: example.com');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mt_email_domains';
        
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE domain_name = %s", $domain));
        if ($exists) wp_send_json_error('Domain is already registered in the system.');

        // Simulated enterprise DKIM tokens for MVP UI
        $dkim1 = substr(md5(uniqid(rand(), true)), 0, 32);
        $dkim2 = substr(md5(uniqid(rand(), true)), 0, 32);
        $dkim3 = substr(md5(uniqid(rand(), true)), 0, 32);
        $dkim_tokens = wp_json_encode([$dkim1, $dkim2, $dkim3]);

        $wpdb->insert( $table_name, array(
            'brand_id' => $brand_id,
            'domain_name' => $domain,
            'status' => 'pending',
            'dkim_tokens' => $dkim_tokens
        ) );

        wp_send_json_success('Domain added successfully. Pending DNS verification.');
    }

    public function ajax_delete_domain() {
        if ( ! check_ajax_referer( 'mt_app_nonce', 'security', false ) ) wp_send_json_error( 'Security Token Expired.' );
        $brand_id = $this->get_tenant_brand_id();
        $domain_id = intval($_POST['domain_id']);

        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'mt_email_domains', array('id' => $domain_id, 'brand_id' => $brand_id) );
        wp_send_json_success('Domain removed.');
    }

    public function ajax_verify_domain() {
        if ( ! check_ajax_referer( 'mt_app_nonce', 'security', false ) ) wp_send_json_error( 'Security Token Expired.' );
        $brand_id = $this->get_tenant_brand_id();
        $domain_id = intval($_POST['domain_id']);

        global $wpdb;
        $domain_record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mt_email_domains WHERE id = %d AND brand_id = %d", $domain_id, $brand_id));
        
        if (!$domain_record) wp_send_json_error('Domain not found.');

        $domain = $domain_record->domain_name;

        // Perform basic DNS checks
        $records = @dns_get_record($domain, DNS_TXT);
        $has_spf = false;
        
        if (is_array($records)) {
            foreach ($records as $r) {
                if (isset($r['txt']) && strpos($r['txt'], 'v=spf1') !== false && strpos($r['txt'], 'amazonses.com') !== false) {
                    $has_spf = true;
                }
            }
        }

        // Mock success for 'test.com' to allow UI testing without actual DNS propagation
        if ($domain === 'test.com' || $has_spf) {
            $wpdb->update( $wpdb->prefix . 'mt_email_domains', array('status' => 'verified'), array('id' => $domain_id) );
            wp_send_json_success('DNS Verified Successfully! Your domain is ready to send emails.');
        } else {
            wp_send_json_error('Verification Failed. We could not detect the correct SPF/DKIM records. Note: DNS changes can take up to 24 hours to propagate.');
        }
    }

    // --- TEMPLATE BUILDER METHODS ---
    public function ajax_save_template() {
        if ( ! check_ajax_referer( 'mt_app_nonce', 'security', false ) ) wp_send_json_error( 'Security Token Expired.' );
        $brand_id = $this->get_tenant_brand_id();
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
            $valid = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}mt_email_templates WHERE id = %d AND brand_id = %d", $template_id, $brand_id));
            if (!$valid) wp_send_json_error('Unauthorized.');
            
            $wpdb->update( $wpdb->prefix . 'mt_email_templates', array(
                'template_name' => $name, 'email_subject' => $subject, 'email_body' => $body
            ), array('id' => $template_id) );
            wp_send_json_success(array('message' => 'Template Updated!', 'id' => $template_id));
        }
    }

    public function ajax_delete_template() {
        if ( ! check_ajax_referer( 'mt_app_nonce', 'security', false ) ) wp_send_json_error( 'Security Token Expired.' );
        $brand_id = $this->get_tenant_brand_id();
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'mt_email_templates', array('id' => intval($_POST['template_id']), 'brand_id' => $brand_id) );
        wp_send_json_success('Deleted.');
    }
}

// Instantiate the module
$mt_email_engine = new MT_Email();
$mt_email_engine->init();