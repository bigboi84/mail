<?php
/**
 * MailToucan Universal Dispatch Engine
 * Handles Mail.baby, API routing (SendGrid, Mailgun), Custom SMTP, and Shortcode parsing.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MT_Email {

    public function init() {
        // Intercept standard WP emails to inject Mail.baby or Custom SMTP credentials
        add_action( 'phpmailer_init', array( $this, 'configure_custom_smtp' ), 999 );
        
        // AJAX Endpoints for UI Testing
        add_action( 'wp_ajax_mt_send_test_email', array( $this, 'ajax_send_test_email' ) );
    }

    /**
     * Extracts the routing configuration for a specific brand
     */
    private function get_routing_config( $brand_id ) {
        global $wpdb;
        $brand = $wpdb->get_row( $wpdb->prepare("SELECT brand_name, brand_config FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id) );
        
        if ( ! $brand ) return false;

        $config = json_decode( $brand->brand_config, true ) ?: [];
        $delivery = isset($config['delivery']) ? $config['delivery'] : [];

        // Defaults
        return [
            'brand_name'    => $brand->brand_name,
            'from_email'    => sanitize_title($brand->brand_name) . '@mailtoucan.pro',
            'splash_method' => $delivery['splash_method'] ?? 'system',
            'bulk_method'   => $delivery['bulk_method'] ?? 'domain',
            'smtp_provider' => $delivery['smtp_provider'] ?? 'mailbaby', // Defaulting to Mail.baby
            'smtp_key'      => $delivery['smtp_key'] ?? '',
            'smtp_host'     => $delivery['smtp_host'] ?? 'relay.mail.baby',
            'smtp_port'     => $delivery['smtp_port'] ?? 587,
            'smtp_user'     => $delivery['smtp_user'] ?? '',
            'smtp_pass'     => $delivery['smtp_pass'] ?? ''
        ];
    }

    /**
     * Parses the shortcodes in the HTML body before sending
     */
    public function parse_tags( $html, $lead_data, $brand_name, $location_name = 'Our Store' ) {
        
        $first_name = 'Guest';
        $full_name = 'Guest';
        
        if ( !empty($lead_data['guest_name']) ) {
            $full_name = trim($lead_data['guest_name']);
            $parts = explode(' ', $full_name);
            $first_name = $parts[0];
        }

        $email = $lead_data['email'] ?? '';
        $phone = $lead_data['phone'] ?? '';
        $birthday = $lead_data['birthday'] ?? '';
        $unsub_token = $lead_data['unsub_token'] ?? bin2hex(random_bytes(16));
        
        // Build the safe unsubscribe link
        $unsub_link = site_url("/unsubscribe/?token=" . $unsub_token);

        $tags = [
            '[Guest_First_Name]' => $first_name,
            '[Guest_Full_Name]'  => $full_name,
            '[Guest_Email]'      => $email,
            '[Guest_Phone]'      => $phone,
            '[Guest_Birthday]'   => $birthday,
            '[Brand_Name]'       => $brand_name,
            '[Location_Name]'    => $location_name,
            '[Visit_Date]'       => date('F j, Y'),
            '[Unsubscribe_Link]' => $unsub_link
        ];

        return str_replace( array_keys($tags), array_values($tags), $html );
    }

    /**
     * CORE: The Bulk Broadcast Switchboard
     * Routes the email to the correct infrastructure
     */
    public function route_bulk_email( $to_email, $subject, $html_body, $brand_id ) {
        $route = $this->get_routing_config( $brand_id );
        if ( ! $route ) return false;

        $provider = $route['smtp_provider'];
        $api_key = $route['smtp_key'];
        $from_name = $route['brand_name'];
        $from_email = $route['from_email'];

        // --- MAIL.BABY & CUSTOM SMTP ROUTE ---
        // We rely on wp_mail(), which will be intercepted by our configure_custom_smtp() method below.
        if ( $route['bulk_method'] === 'domain' || in_array($provider, ['mailbaby', 'custom']) ) {
            return $this->send_via_wp_mail( $to_email, $subject, $html_body, $from_name, $from_email );
        }

        // --- SENDGRID API ROUTE ---
        if ( $provider === 'sendgrid' ) {
            $payload = [
                'personalizations' => [ [ 'to' => [ ['email' => $to_email] ], 'subject' => $subject ] ],
                'from' => [ 'email' => $from_email, 'name' => $from_name ],
                'content' => [ [ 'type' => 'text/html', 'value' => $html_body ] ]
            ];
            
            $response = wp_remote_post( 'https://api.sendgrid.com/v3/mail/send', [
                'headers' => [ 'Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode( $payload ),
                'timeout' => 15
            ]);
            return !is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 202;
        }

        // --- MAILGUN API ROUTE ---
        if ( $provider === 'mailgun' ) {
            $domain = substr(strrchr($from_email, "@"), 1);
            $url = "https://api.mailgun.net/v3/{$domain}/messages";
            
            $payload = [
                'from'    => "{$from_name} <{$from_email}>",
                'to'      => $to_email,
                'subject' => $subject,
                'html'    => $html_body
            ];

            $response = wp_remote_post( $url, [
                'headers' => [ 'Authorization' => 'Basic ' . base64_encode("api:{$api_key}") ],
                'body'    => $payload,
                'timeout' => 15
            ]);
            return !is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;
        }

        return false; 
    }

    /**
     * Executes the localized WordPress Mail function
     */
    private function send_via_wp_mail( $to, $subject, $html, $from_name, $from_email ) {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        ];
        return wp_mail( $to, $subject, $html, $headers );
    }

    /**
     * The SMTP Interceptor
     * Dynamically forces WordPress to use Mail.baby or Custom credentials if configured
     */
    public function configure_custom_smtp( $phpmailer ) {
        // Find who is sending the email
        $user_id = get_current_user_id();
        $brand_id = get_user_meta( $user_id, 'mt_brand_id', true );
        
        // If we are executing a background cron job, the brand_id might be set globally.
        if ( ! $brand_id && defined('MT_CURRENT_CRON_BRAND') ) {
            $brand_id = MT_CURRENT_CRON_BRAND;
        }
        
        if ( ! $brand_id ) return;

        $route = $this->get_routing_config( intval($brand_id) );
        if ( ! $route ) return;

        // If they specifically selected Mail.baby, force the Mail.baby relay host
        if ( $route['smtp_provider'] === 'mailbaby' ) {
            $phpmailer->isSMTP();
            $phpmailer->Host       = 'relay.mail.baby';
            $phpmailer->SMTPAuth   = true;
            $phpmailer->Port       = 587;
            $phpmailer->Username   = $route['smtp_user']; // The Mail.baby username (usually begins with 'mb')
            $phpmailer->Password   = $route['smtp_pass'];
            $phpmailer->SMTPSecure = 'tls';
            $phpmailer->From       = $route['from_email'];
            $phpmailer->FromName   = $route['brand_name'];
            return;
        }

        // Standard Custom SMTP
        if ( $route['smtp_provider'] === 'custom' ) {
            $phpmailer->isSMTP();
            $phpmailer->Host       = $route['smtp_host'];
            $phpmailer->SMTPAuth   = true;
            $phpmailer->Port       = $route['smtp_port'];
            $phpmailer->Username   = $route['smtp_user'];
            $phpmailer->Password   = $route['smtp_pass'];
            $phpmailer->SMTPSecure = ($route['smtp_port'] == 465) ? 'ssl' : 'tls';
            $phpmailer->From       = $route['from_email'];
            $phpmailer->FromName   = $route['brand_name'];
        }
    }

    /**
     * AJAX Handler: Test Email from the UI Dashboard
     */
    public function ajax_send_test_email() {
        if ( ! check_ajax_referer( 'mt_app_nonce', 'security', false ) ) {
            wp_send_json_error( 'Security Token Expired.' );
        }

        $user_id = get_current_user_id();
        $brand_id = get_user_meta( $user_id, 'mt_brand_id', true );
        
        $to_email = sanitize_email( $_POST['to_email'] );
        $subject  = sanitize_text_field( $_POST['subject'] );
        
        // Grab the raw JSON payload from the Studio/Campaign UI and decode it securely
        $raw_payload = isset($_POST['payload']) ? sanitize_text_field($_POST['payload']) : '';
        if ( $raw_payload && !str_starts_with($raw_payload, '{') ) {
            $raw_payload = urldecode(base64_decode($raw_payload));
        }
        
        $parsed_data = json_decode($raw_payload, true);
        $html_body = isset($parsed_data['html']) ? $parsed_data['html'] : '<p>Test Email Body</p>';

        // Setup mock lead data to verify Shortcode Parser is working
        $mock_lead = [
            'guest_name' => 'John Doe',
            'email' => $to_email,
            'phone' => '555-019-2834',
            'birthday' => '1990-10-31',
            'unsub_token' => 'test-token-123'
        ];

        // 1. Parse Tags
        $route = $this->get_routing_config( $brand_id );
        $final_html = $this->parse_tags( $html_body, $mock_lead, $route['brand_name'], 'HQ Location' );

        // 2. Dispatch via the universal router
        $sent = $this->route_bulk_email( $to_email, $subject, $final_html, $brand_id );

        if ( $sent ) {
            wp_send_json_success( 'Test email flew successfully through your selected gateway!' );
        } else {
            wp_send_json_error( 'Failed to dispatch test email. Check your Delivery Routing API keys or Mail.baby credentials.' );
        }
    }
}