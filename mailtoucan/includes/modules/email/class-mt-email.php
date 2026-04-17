<?php
/**
 * MailToucan Universal Dispatch Engine (Centralized SaaS Architecture)
 * Acts as the master gateway for CRM, WiFi, and Bulk Broadcasts.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MT_Email {

    public function __construct() {
        add_action( 'phpmailer_init', array( $this, 'configure_custom_smtp' ), 999 );
        
        // Diagnostic Hooks
        add_action( 'wp_ajax_mt_run_master_diagnostic', array( $this, 'ajax_run_master_diagnostic' ) );
        add_action( 'wp_ajax_mt_fire_diagnostic_test', array( $this, 'ajax_send_diagnostic_email' ) );
        
        // SMTP Tester Hooks
        add_action( 'wp_ajax_mt_test_smtp_connection', array( $this, 'ajax_tenant_test_smtp_connection' ) );
        add_action( 'wp_ajax_mt_admin_test_smtp_connection', array( $this, 'ajax_admin_test_smtp_connection' ) );
    }

    /**
     * Retrieves routing config and builds the SaaS System Email dynamically
     */
    private function get_routing_config( $brand_id ) {
        global $wpdb;
        $brand = $wpdb->get_row( $wpdb->prepare("SELECT brand_name, brand_config FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id) );
        
        if ( ! $brand ) return false;

        $config = json_decode( $brand->brand_config, true ) ?: [];
        $delivery = isset($config['delivery']) ? $config['delivery'] : [];

        // HARDCODED MASTER SAAS DOMAIN
        $master_saas_domain = 'fly.mailtoucan.com';

        $raw_slug = sanitize_title($brand->brand_name);
        $clean_slug = str_replace('-', '', $raw_slug); 
        if (empty($clean_slug)) $clean_slug = 'hello';
        
        // Dynamically builds: brand@fly.mailtoucan.com
        $system_email = $clean_slug . '@' . $master_saas_domain;

        $configured_domain_email = !empty($delivery['from_email']) ? $delivery['from_email'] : $system_email;

        return [
            'brand_name'    => $brand->brand_name,
            'system_email'  => $system_email,
            'from_email'    => $configured_domain_email,
            'splash_method' => $delivery['splash_method'] ?? 'system',
            'bulk_method'   => $delivery['bulk_method'] ?? 'domain',
            'smtp_provider' => $delivery['smtp_provider'] ?? 'custom',
            'smtp_key'      => $delivery['smtp_key'] ?? '',
            'smtp_host'     => $delivery['smtp_host'] ?? '',
            'smtp_port'     => $delivery['smtp_port'] ?? 587,
            'smtp_user'     => $delivery['smtp_user'] ?? '',
            'smtp_pass'     => $delivery['smtp_pass'] ?? ''
        ];
    }

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

    public function route_email( $to_email, $subject, $html_body, $brand_id, $engine = 'bulk' ) {
        $route = $this->get_routing_config( $brand_id );
        if ( ! $route ) return 'Failed to load brand configuration.';

        $method = ($engine === 'splash') ? $route['splash_method'] : $route['bulk_method'];
        $provider = $route['smtp_provider'];
        $api_key = $route['smtp_key'];
        $from_name = $route['brand_name'];
        
        $from_email = ($method === 'system') ? $route['system_email'] : $route['from_email'];

        if ( $method === 'google' ) return 'Google Workspace routing not yet configured.';

        if ( $method === 'api' && $provider === 'custom' && !empty($route['smtp_host']) ) {
            return $this->execute_isolated_phpmailer(
                $route['smtp_host'], $route['smtp_port'], $route['smtp_user'], $route['smtp_pass'], 
                $to_email, $subject, $html_body, $from_name, $from_email
            );
        }

        if ( $method === 'system' || $method === 'domain' || $provider === 'mailbaby' ) {
            return $this->send_via_global_pool( $to_email, $subject, $html_body, $from_name, $from_email );
        }

        if ( $method === 'api' && $provider === 'sendgrid' ) {
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
            return (!is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 202) ? true : 'SendGrid API Error: ' . wp_remote_retrieve_body($response);
        }

        if ( $method === 'api' && $provider === 'mailgun' ) {
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
            return (!is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200) ? true : 'Mailgun API Error: ' . wp_remote_retrieve_body($response);
        }
        
        return 'Invalid routing setup. Please configure a valid API or Domain route.'; 
    }

    private function send_via_global_pool( $to, $subject, $html, $from_name, $from_email ) {
        $saved_smtps = get_option('mt_marketing_smtps', '[]');
        $smtp_pool = json_decode($saved_smtps, true) ?: [];
        $active_pool = array_filter($smtp_pool, function($smtp) {
            return !empty($smtp['active']) && intval($smtp['weight']) > 0;
        });

        if (empty($active_pool)) {
            $sent = $this->send_via_wp_mail( $to, $subject, $html, $from_name, $from_email );
            return $sent ? true : 'Fallback wp_mail() failed. No global SMTP servers are active.';
        }

        $rand = mt_rand(1, 100);
        $cumulative_weight = 0;
        $selected_smtp = null;
        foreach ($active_pool as $smtp) {
            $cumulative_weight += intval($smtp['weight']);
            if ($rand <= $cumulative_weight) { $selected_smtp = $smtp; break; }
        }
        if (!$selected_smtp) $selected_smtp = reset($active_pool);
        
        $final_from_email = !empty($from_email) ? $from_email : $selected_smtp['from_email'];
        $final_from_name = !empty($from_name) ? $from_name : $selected_smtp['from_name'];

        return $this->execute_isolated_phpmailer(
            $selected_smtp['host'], $selected_smtp['port'], $selected_smtp['user'], $selected_smtp['pass'], 
            $to, $subject, $html, $final_from_name, $final_from_email
        );
    }

    private function execute_isolated_phpmailer( $host, $port, $user, $pass, $to, $subject, $html, $from_name, $from_email ) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $user;
            $mail->Password   = $pass;
            $mail->SMTPSecure = ($port == 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = intval($port);
            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($to);
            $mail->addCustomHeader('X-MailToucan-Campaign', 'True');
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = wp_strip_all_tags($html);
            
            $mail->send();
            return true;
        } catch (Exception $e) { 
            return 'SMTP Engine Error: ' . $mail->ErrorInfo; 
        }
    }

    private function send_via_wp_mail( $to, $subject, $html, $from_name, $from_email ) {
        $headers = [ 'Content-Type: text/html; charset=UTF-8', 'From: ' . $from_name . ' <' . $from_email . '>' ];
        return wp_mail( $to, $subject, $html, $headers );
    }

    public function configure_custom_smtp( $phpmailer ) {
        $user_id = get_current_user_id();
        $brand_id = get_user_meta( $user_id, 'mt_brand_id', true );
        if ( ! $brand_id && defined('MT_CURRENT_CRON_BRAND') ) $brand_id = MT_CURRENT_CRON_BRAND;
        if ( ! $brand_id ) return;

        $route = $this->get_routing_config( intval($brand_id) );
        if ( ! $route ) return;

        if ( $route['smtp_provider'] === 'custom' && !empty($route['smtp_host']) ) {
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
     * INDIVIDUAL DIAGNOSTIC ENGINE (Fires from the two separate buttons)
     */
    public function ajax_send_diagnostic_email() {
        if (ob_get_length()) ob_clean();
        if ( ! check_ajax_referer( 'mt_app_nonce', 'security', false ) ) wp_send_json_error( 'Security Token Expired.' );

        $brand_id = isset($_POST['brand_id']) ? intval($_POST['brand_id']) : get_user_meta( get_current_user_id(), 'mt_brand_id', true );
        if ( ! $brand_id ) wp_send_json_error( 'System Error: Brand ID missing. Could not identify tenant.' );
        
        $to_email = sanitize_email( $_POST['to_email'] );
        $engine   = isset($_POST['engine']) ? sanitize_text_field($_POST['engine']) : 'bulk';
        $subject  = sanitize_text_field( $_POST['subject'] );
        
        $raw_payload = isset($_POST['payload']) ? wp_unslash($_POST['payload']) : '';
        if ( $raw_payload && !str_starts_with($raw_payload, '{') ) $raw_payload = urldecode(base64_decode($raw_payload));
        
        $parsed_data = json_decode($raw_payload, true);
        $html_body = isset($parsed_data['html']) ? $parsed_data['html'] : '<p>Test Email Body</p>';

        global $wpdb;
        $brand_name = $wpdb->get_var($wpdb->prepare("SELECT brand_name FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id));
        
        $mock_lead = [ 'guest_name' => 'Diagnostic Test', 'email' => $to_email, 'phone' => '555-000-0000', 'birthday' => '1990-01-01' ];
        $route = $this->get_routing_config( $brand_id );
        $final_html = $this->parse_tags( $html_body, $mock_lead, $brand_name, 'HQ' );

        try {
            $result = $this->route_email( $to_email, $subject, $final_html, $brand_id, $engine );
            
            if ( $result === true ) {
                $used_email = ($engine === 'splash' && $route['splash_method'] === 'system') ? $route['system_email'] : $route['from_email'];
                wp_send_json_success( 'Fired successfully from: ' . $used_email );
            } else {
                wp_send_json_error( is_string($result) ? $result : 'Unknown failure during dispatch.' );
            }
        } catch (Exception $e) {
            wp_send_json_error('Fatal Error: ' . $e->getMessage());
        }
    }

    /**
     * UNIFIED MASTER DIAGNOSTIC ENGINE (Fires from the main button)
     */
    public function ajax_run_master_diagnostic() {
        if (ob_get_length()) ob_clean();
        if ( ! check_ajax_referer( 'mt_app_nonce', 'security', false ) ) wp_send_json_error( 'Security Token Expired.' );

        $brand_id = isset($_POST['brand_id']) ? intval($_POST['brand_id']) : get_user_meta( get_current_user_id(), 'mt_brand_id', true );
        if ( ! $brand_id ) wp_send_json_error( 'System Error: Brand ID missing. Could not identify tenant.' );
        
        $to_email = sanitize_email( $_POST['to_email'] );
        
        global $wpdb;
        $brand_name = $wpdb->get_var($wpdb->prepare("SELECT brand_name FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id));
        $mock_lead = ['guest_name' => 'Diagnostic Test', 'email' => $to_email, 'phone' => '555-000-0000', 'birthday' => '1990-01-01'];
        
        $results = [
            'splash' => ['success' => false, 'msg' => ''],
            'bulk'   => ['success' => false, 'msg' => '']
        ];

        // 1. Test WiFi Engine
        try {
            $html_splash = $this->parse_tags( "<div style='padding:20px; font-family:sans-serif;'><h2>WiFi Transactional Test</h2><p>This email successfully fired through your WiFi Engine settings.</p></div>", $mock_lead, $brand_name, 'HQ' );
            $res_splash = $this->route_email( $to_email, 'MailToucan WiFi Route Test 🚀', $html_splash, $brand_id, 'splash' );
            
            if ($res_splash === true) {
                $route = $this->get_routing_config( $brand_id );
                $used = ($route['splash_method'] === 'system') ? $route['system_email'] : $route['from_email'];
                $results['splash'] = ['success' => true, 'msg' => "Passed! Sent from: " . $used];
            } else {
                $results['splash'] = ['success' => false, 'msg' => is_string($res_splash) ? $res_splash : "WiFi Engine Failed."];
            }
        } catch (Exception $e) {
            $results['splash'] = ['success' => false, 'msg' => "Crash: " . $e->getMessage()];
        }

        // 2. Test Bulk Engine
        try {
            $html_bulk = $this->parse_tags( "<div style='padding:20px; font-family:sans-serif;'><h2>Bulk Broadcast Test</h2><p>This email successfully fired through your Bulk Broadcast settings.</p></div>", $mock_lead, $brand_name, 'HQ' );
            $res_bulk = $this->route_email( $to_email, 'MailToucan Bulk Route Test 🚀', $html_bulk, $brand_id, 'bulk' );
            
            if ($res_bulk === true) {
                $route = $this->get_routing_config( $brand_id );
                $used = ($route['bulk_method'] === 'system') ? $route['system_email'] : $route['from_email'];
                $results['bulk'] = ['success' => true, 'msg' => "Passed! Sent from: " . $used];
            } else {
                $results['bulk'] = ['success' => false, 'msg' => is_string($res_bulk) ? $res_bulk : "Bulk Engine Failed."];
            }
        } catch (Exception $e) {
            $results['bulk'] = ['success' => false, 'msg' => "Crash: " . $e->getMessage()];
        }

        wp_send_json_success( $results );
    }

    public function ajax_tenant_test_smtp_connection() {
        if (ob_get_length()) ob_clean();
        if ( ! check_ajax_referer( 'mt_app_nonce', 'security', false ) ) wp_send_json_error( 'Tenant Security Token Expired.' );
        $this->process_smtp_connection_test();
    }

    public function ajax_admin_test_smtp_connection() {
        if (ob_get_length()) ob_clean();
        if ( ! check_ajax_referer( 'mt_admin_smtp_test', 'security', false ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Admin Permission Denied.' );
        }
        $this->process_smtp_connection_test();
    }

    private function process_smtp_connection_test() {
        $host = sanitize_text_field($_POST['host']);
        $port = intval($_POST['port']);
        $user = sanitize_text_field($_POST['user']);
        $pass = isset($_POST['pass']) ? wp_unslash($_POST['pass']) : '';

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $user;
            $mail->Password   = $pass;
            $mail->SMTPSecure = ($port == 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $port;
            $mail->Timeout    = 10;

            if ( $mail->smtpConnect() ) {
                $mail->smtpClose();
                wp_send_json_success('Connection Verified Successfully.');
            } else {
                wp_send_json_error('Authentication failed. Check your API Host and Port.');
            }
        } catch (Exception $e) {
            wp_send_json_error('Connection Error: ' . $mail->ErrorInfo);
        }
    }
}

// Guarantee the class is instantiated globally so the AJAX hooks always fire
new MT_Email();