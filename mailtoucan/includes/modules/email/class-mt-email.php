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

    private $google_client_id;
    private $google_client_secret;
    private $google_redirect_uri;

    public function __construct() {
        // Securely pull Google API credentials from the database instead of hardcoding
        $this->google_client_id = get_option('mt_google_client_id', '');
        $this->google_client_secret = get_option('mt_google_client_secret', '');
        $this->google_redirect_uri = admin_url('admin-ajax.php?action=mt_google_oauth_callback');

        add_action( 'phpmailer_init', array( $this, 'configure_custom_smtp' ), 999 );
        
        // Broadcast Event Listener
        add_action( 'mt_campaign_launched', array( $this, 'queue_broadcast_campaign' ), 10, 2 );

        // Diagnostic Hooks
        add_action( 'wp_ajax_mt_run_master_diagnostic', array( $this, 'ajax_run_master_diagnostic' ) );
        add_action( 'wp_ajax_mt_fire_diagnostic_test', array( $this, 'ajax_send_diagnostic_email' ) );
        
        // SMTP Tester Hooks
        add_action( 'wp_ajax_mt_test_smtp_connection', array( $this, 'ajax_tenant_test_smtp_connection' ) );
        add_action( 'wp_ajax_mt_admin_test_smtp_connection', array( $this, 'ajax_admin_test_smtp_connection' ) );

        // Google OAuth Handlers
        add_action( 'wp_ajax_mt_get_google_auth_url', array( $this, 'ajax_get_google_auth_url' ) );
        add_action( 'wp_ajax_mt_google_oauth_callback', array( $this, 'google_oauth_callback' ) );
        add_action( 'wp_ajax_nopriv_mt_google_oauth_callback', array( $this, 'google_oauth_callback' ) );
        
        // Note: Tracking Pixel hooks removed. Now handled cleanly by Dashboard Rewrite Rules (Item 7).
    }

    public function maybe_create_email_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // CHANGES GUIDE ITEM 10: Added `retries` column
        $table_queue = $wpdb->prefix . 'mt_email_queue';
        $sql_queue = "CREATE TABLE $table_queue (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            brand_id int(11) NOT NULL,
            campaign_id bigint(20) NOT NULL,
            lead_id bigint(20) NOT NULL,
            to_email varchar(100) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            retries int(11) DEFAULT 0,
            send_after datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY brand_id (brand_id),
            KEY status_send_after (status, send_after)
        ) $charset_collate;";
        dbDelta( $sql_queue );

        // CHANGES GUIDE ITEM 9: Added `provider` column
        $table_sends = $wpdb->prefix . 'mt_email_sends';
        $sql_sends = "CREATE TABLE $table_sends (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            brand_id int(11) NOT NULL,
            campaign_id bigint(20) NOT NULL,
            lead_id bigint(20) NOT NULL,
            provider varchar(50) DEFAULT 'system',
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql_sends );
        
        $table_opens = $wpdb->prefix . 'mt_email_opens';
        $sql_opens = "CREATE TABLE $table_opens (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            brand_id int(11) NOT NULL,
            campaign_id bigint(20) NOT NULL,
            lead_id bigint(20) NOT NULL,
            opened_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql_opens );

        $table_clicks = $wpdb->prefix . 'mt_email_clicks';
        $sql_clicks = "CREATE TABLE $table_clicks (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            brand_id int(11) NOT NULL,
            campaign_id bigint(20) NOT NULL,
            lead_id bigint(20) NOT NULL,
            clicked_url varchar(255) NOT NULL,
            clicked_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql_clicks );
    }

    public function queue_broadcast_campaign( $campaign_id, $brand_id ) {
        global $wpdb;
        $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mt_campaigns WHERE id = %d", $campaign_id));
        if ( ! $campaign ) return;
        
        $config = json_decode($campaign->config_json, true) ?: [];
        $audience = $config['audience'] ?? 'all';
        $location_id = $config['location_id'] ?? 'all';
        $tag = $config['audience_tag'] ?? '';

        $leads_table = $wpdb->prefix . 'mt_guest_leads';
        $queue_table = $wpdb->prefix . 'mt_email_queue';

        $query = "SELECT id, email FROM $leads_table WHERE brand_id = %d AND status NOT IN ('unsubscribed', 'trashed', 'deleted')";
        $params = [$brand_id];

        if ( $location_id !== 'all' ) {
            $query .= " AND store_id = %d";
            $params[] = intval($location_id);
        }

        if ( $audience === 'recent' ) {
            $query .= " AND last_visit >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        } elseif ( $audience === 'birthday' ) {
            $query .= " AND MONTH(birthday) = MONTH(NOW())";
        } elseif ( $audience === 'custom' && !empty($tag) ) {
            $query .= " AND campaign_tag = %s";
            $params[] = $tag;
        }

        $leads = $wpdb->get_results($wpdb->prepare($query, ...$params));
        
        foreach ( $leads as $lead ) {
            $wpdb->insert($queue_table, [
                'brand_id'    => $brand_id,
                'campaign_id' => $campaign_id,
                'lead_id'     => $lead->id,
                'to_email'    => $lead->email,
                'status'      => 'pending',
                'send_after'  => current_time('mysql')
            ]);
        }
    }

    private function get_routing_config( $brand_id ) {
        global $wpdb;
        $brand = $wpdb->get_row( $wpdb->prepare("SELECT brand_name, brand_config FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id) );
        
        if ( ! $brand ) return false;

        $config = json_decode( $brand->brand_config, true ) ?: [];
        $delivery = isset($config['delivery']) ? $config['delivery'] : [];

        $master_saas_domain = 'fly.mailtoucan.com';
        $raw_slug = sanitize_title($brand->brand_name);
        $clean_slug = str_replace('-', '', $raw_slug); 
        if (empty($clean_slug)) $clean_slug = 'hello';
        
        $system_email = $clean_slug . '@' . $master_saas_domain;
        $configured_domain_email = !empty($delivery['from_email']) ? $delivery['from_email'] : $system_email;

        // Note: Strict verification is now enforced natively via get_brand_sender().
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
            'smtp_pass'     => $delivery['smtp_pass'] ?? '',
            'google_refresh_token' => $delivery['google_refresh_token'] ?? '',
            'google_email'  => $delivery['google_email'] ?? ''
        ];
    }

    // CHANGES GUIDE ITEM 3: Strict Domain Verification check
    public function get_brand_sender( $brand_id, $requested_email, $brand_name ) {
        global $wpdb;
        $master_domain = 'fly.mailtoucan.com';
        $fallback = sanitize_title($brand_name) . '@' . $master_domain;
        
        if ( empty($requested_email) ) return $fallback;

        $domain = substr(strrchr($requested_email, "@"), 1);
        if ( $domain === $master_domain ) return $requested_email;

        $is_verified = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}mt_email_domains WHERE brand_id = %d AND domain_name = %s AND status = 'verified'",
            $brand_id, $domain
        ));

        return $is_verified ? $requested_email : $fallback;
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

    // CHANGES GUIDE ITEM 7: Inject Vanity Tracking URL
    public function inject_tracking_pixel( $html, $campaign_id, $lead_id ) {
        $track_url = site_url('/mt-track/open/' . $campaign_id . '/' . $lead_id);
        $pixel = '<img src="' . esc_url($track_url) . '" width="1" height="1" alt="" style="display:none; visibility:hidden; mso-hide:all;" />';
        
        if (stripos($html, '</body>') !== false) {
            return str_ireplace('</body>', $pixel . '</body>', $html);
        }
        return $html . $pixel;
    }

    // CHANGES GUIDE ITEM 7: Process Vanity Endpoint directly from router
    public function process_tracking_pixel( $campaign_id, $lead_id ) {
        if ($campaign_id > 0 && $lead_id > 0) {
            global $wpdb;
            $table_opens = $wpdb->prefix . 'mt_email_opens';
            $brand_id = $wpdb->get_var($wpdb->prepare("SELECT brand_id FROM {$wpdb->prefix}mt_campaigns WHERE id = %d", $campaign_id));
            
            if ($brand_id) {
                $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_opens WHERE campaign_id = %d AND lead_id = %d", $campaign_id, $lead_id));
                if (!$exists) {
                    $wpdb->insert($table_opens, [
                        'brand_id'    => $brand_id,
                        'campaign_id' => $campaign_id,
                        'lead_id'     => $lead_id,
                        'opened_at'   => current_time('mysql')
                    ]);
                }
            }
        }

        header('Content-Type: image/gif');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
        exit;
    }

    // CHANGES GUIDE ITEM 9 & 10: Array return structure with error reporting
    public function route_email( $to_email, $subject, $html_body, $brand_id, $engine = 'bulk' ) {
        $route = $this->get_routing_config( $brand_id );
        if ( ! $route ) return ['success' => false, 'error' => 'Failed to load brand configuration.'];

        $method = ($engine === 'splash') ? $route['splash_method'] : $route['bulk_method'];
        $provider = $route['smtp_provider'];
        $api_key = $route['smtp_key'];
        $from_name = $route['brand_name'];
        
        // Enforce strict domain ownership (Guide Item 3)
        $from_email = $this->get_brand_sender($brand_id, $route['from_email'], $from_name);

        if ( $method === 'google' ) {
            $res = $this->send_via_google_api( $to_email, $subject, $html_body, $from_name, $brand_id );
            return ($res === true) ? ['success' => true, 'provider' => 'google'] : ['success' => false, 'error' => $res];
        }

        if ( $method === 'api' && $provider === 'custom' && !empty($route['smtp_host']) ) {
            $res = $this->execute_isolated_phpmailer(
                $route['smtp_host'], $route['smtp_port'], $route['smtp_user'], $route['smtp_pass'], 
                $to_email, $subject, $html_body, $from_name, $from_email
            );
            return ($res === true) ? ['success' => true, 'provider' => 'custom_smtp'] : ['success' => false, 'error' => $res];
        }

        if ( $method === 'system' || $method === 'domain' || $provider === 'mailbaby' ) {
            $res = $this->send_via_global_pool( $to_email, $subject, $html_body, $from_name, $from_email );
            return ($res === true) ? ['success' => true, 'provider' => 'system_pool'] : ['success' => false, 'error' => $res];
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
            if (!is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 202) {
                return ['success' => true, 'provider' => 'sendgrid'];
            }
            return ['success' => false, 'error' => 'SendGrid API Error: ' . wp_remote_retrieve_body($response)];
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
            if (!is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200) {
                return ['success' => true, 'provider' => 'mailgun'];
            }
            return ['success' => false, 'error' => 'Mailgun API Error: ' . wp_remote_retrieve_body($response)];
        }

        if ( $method === 'api' && $provider === 'postmark' ) {
            $payload = [
                'From' => "{$from_name} <{$from_email}>",
                'To' => $to_email,
                'Subject' => $subject,
                'HtmlBody' => $html_body,
                'MessageStream' => 'outbound'
            ];
            $response = wp_remote_post( 'https://api.postmarkapp.com/email', [
                'headers' => [ 'X-Postmark-Server-Token' => $api_key, 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
                'body'    => wp_json_encode( $payload ),
                'timeout' => 15
            ]);
            if (!is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200) {
                return ['success' => true, 'provider' => 'postmark'];
            }
            return ['success' => false, 'error' => 'Postmark API Error: ' . wp_remote_retrieve_body($response)];
        }

        if ( $method === 'api' && $provider === 'brevo' ) {
            $payload = [
                'sender' => ['name' => $from_name, 'email' => $from_email],
                'to' => [['email' => $to_email]],
                'subject' => $subject,
                'htmlContent' => $html_body
            ];
            $response = wp_remote_post( 'https://api.brevo.com/v3/smtp/email', [
                'headers' => [ 'api-key' => $api_key, 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
                'body'    => wp_json_encode( $payload ),
                'timeout' => 15
            ]);
            if (!is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 201) {
                return ['success' => true, 'provider' => 'brevo'];
            }
            return ['success' => false, 'error' => 'Brevo API Error: ' . wp_remote_retrieve_body($response)];
        }
        
        return ['success' => false, 'error' => 'Invalid routing setup. Please configure a valid API or Domain route.']; 
    }

    private function send_via_google_api($to, $subject, $html, $from_name, $brand_id) {
        global $wpdb;
        $brand = $wpdb->get_row($wpdb->prepare("SELECT brand_config FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id));
        $config = json_decode($brand->brand_config, true) ?: [];
        $refresh_token = $config['delivery']['google_refresh_token'] ?? '';
        $google_email = $config['delivery']['google_email'] ?? '';
        
        if (empty($refresh_token)) return 'Google Workspace is selected but no account is connected. Please authenticate in the dashboard.';

        $res = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $this->google_client_id,
                'client_secret' => $this->google_client_secret,
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token'
            ]
        ]);
        
        if (is_wp_error($res)) return 'Failed to refresh Google Token.';
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (empty($body['access_token'])) return 'Invalid Google Token response. Account may be disconnected.';
        $access_token = $body['access_token'];

        $boundary = uniqid('np');
        $raw_msg = "To: {$to}\r\n";
        $raw_msg .= "From: =?utf-8?B?".base64_encode($from_name)."?= <{$google_email}>\r\n";
        $raw_msg .= "Subject: =?utf-8?B?".base64_encode($subject)."?=\r\n";
        $raw_msg .= "MIME-Version: 1.0\r\n";
        $raw_msg .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";
        $raw_msg .= "--{$boundary}\r\n";
        $raw_msg .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $raw_msg .= wp_strip_all_tags($html) . "\r\n\r\n";
        $raw_msg .= "--{$boundary}\r\n";
        $raw_msg .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $raw_msg .= $html . "\r\n\r\n";
        $raw_msg .= "--{$boundary}--";

        $base64url = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($raw_msg));

        $send_res = wp_remote_post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode(['raw' => $base64url])
        ]);

        if (is_wp_error($send_res)) return 'Google API Network Error: ' . $send_res->get_error_message();
        
        $code = wp_remote_retrieve_response_code($send_res);
        if ($code === 200) return true;
        
        return 'Google API Error: ' . wp_remote_retrieve_body($send_res);
    }

    public function ajax_get_google_auth_url() {
        if (ob_get_length()) ob_clean();
        $brand_id = isset($_POST['brand_id']) ? intval($_POST['brand_id']) : 0;
        if(!$brand_id) wp_send_json_error('No brand ID');
        
        $scope = urlencode('https://www.googleapis.com/auth/gmail.send');
        $state = base64_encode(json_encode(['brand_id' => $brand_id, 'redirect' => $_SERVER['HTTP_REFERER']]));
        
        $url = "https://accounts.google.com/o/oauth2/v2/auth?client_id={$this->google_client_id}&redirect_uri={$this->google_redirect_uri}&response_type=code&scope={$scope}&access_type=offline&prompt=consent&state={$state}";
        wp_send_json_success(['url' => $url]);
    }

    public function google_oauth_callback() {
        if (empty($_GET['code'])) wp_die('No code returned from Google.');
        
        $code = sanitize_text_field($_GET['code']);
        $state_raw = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';
        $state = json_decode(base64_decode($state_raw), true);
        $brand_id = isset($state['brand_id']) ? intval($state['brand_id']) : 0;
        
        if (!$brand_id) wp_die('Invalid Security State. Brand ID missing.');

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'code' => $code,
                'client_id' => $this->google_client_id,
                'client_secret' => $this->google_client_secret,
                'redirect_uri' => $this->google_redirect_uri,
                'grant_type' => 'authorization_code'
            ]
        ]);

        if (is_wp_error($response)) wp_die('Google Token Error: ' . $response->get_error_message());
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['refresh_token'])) wp_die('Failed to get Refresh Token. Please disconnect MailToucan from your Google Security settings and try again.');

        $refresh_token = $body['refresh_token'];
        $access_token = $body['access_token'];

        $profile_res = wp_remote_get('https://gmail.googleapis.com/gmail/v1/users/me/profile', [
            'headers' => ['Authorization' => 'Bearer ' . $access_token]
        ]);
        $profile = json_decode(wp_remote_retrieve_body($profile_res), true);
        $email = isset($profile['emailAddress']) ? $profile['emailAddress'] : 'Connected Gmail';

        global $wpdb;
        $brand = $wpdb->get_row($wpdb->prepare("SELECT brand_config FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id));
        $config = json_decode($brand->brand_config, true) ?: [];
        if (!isset($config['delivery'])) $config['delivery'] = [];
        $config['delivery']['google_refresh_token'] = $refresh_token;
        $config['delivery']['google_email'] = $email;
        
        $wpdb->update($wpdb->prefix . 'mt_brands', ['brand_config' => wp_json_encode($config)], ['id' => $brand_id]);

        $back_url = $state['redirect'] ?? site_url();
        echo "<!DOCTYPE html><html lang='en'><head><meta name='viewport' content='width=device-width, initial-scale=1'><title>Google Connected</title><style>body{font-family:-apple-system,BlinkMacSystemFont,sans-serif;background:#f3f4f6;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center;}.card{background:#fff;padding:40px;border-radius:16px;box-shadow:0 10px 25px rgba(0,0,0,0.1);max-width:400px;width:90%;}.icon{background:#d1fae5;color:#059669;width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:40px;margin:0 auto 20px auto;}h2{color:#111827;margin-top:0;}p{color:#4b5563;font-size:15px;}</style></head><body><div class='card'><div class='icon'>✓</div><h2>Successfully Connected!</h2><p>Your Gmail (<b>{$email}</b>) is now linked to MailToucan.</p><p style='font-size: 13px; color: #9ca3af; margin-top: 20px;'><i class='fa-solid fa-spinner fa-spin'></i> Redirecting back to dashboard...</p></div><script>setTimeout(()=>window.location.href='{$back_url}', 2500);</script></body></html>";
        exit;
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
            
            // Adjusted response handler to catch the new array structure securely
            if ( is_array($result) && isset($result['success']) && $result['success'] === true ) {
                $used_email = ($engine === 'splash' && $route['splash_method'] === 'system') ? $route['system_email'] : $route['from_email'];
                if ($engine === 'splash' && $route['splash_method'] === 'google') $used_email = $route['google_email'] ?? 'Google API';
                wp_send_json_success( 'Fired successfully from: ' . $used_email );
            } else {
                wp_send_json_error( isset($result['error']) ? $result['error'] : 'Unknown failure during dispatch.' );
            }
        } catch (Exception $e) {
            wp_send_json_error('Fatal Error: ' . $e->getMessage());
        }
    }

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

        try {
            $html_splash = $this->parse_tags( "<div style='padding:20px; font-family:sans-serif;'><h2>WiFi Transactional Test</h2><p>This email successfully fired through your WiFi Engine settings.</p></div>", $mock_lead, $brand_name, 'HQ' );
            $res_splash = $this->route_email( $to_email, 'MailToucan WiFi Route Test 🚀', $html_splash, $brand_id, 'splash' );
            
            if ( is_array($res_splash) && isset($res_splash['success']) && $res_splash['success'] === true ) {
                $route = $this->get_routing_config( $brand_id );
                $used = ($route['splash_method'] === 'system') ? $route['system_email'] : $route['from_email'];
                if ($route['splash_method'] === 'google') $used = "Google API Connected";
                $results['splash'] = ['success' => true, 'msg' => "Passed! Sent from: " . $used];
            } else {
                $results['splash'] = ['success' => false, 'msg' => isset($res_splash['error']) ? $res_splash['error'] : "WiFi Engine Failed."];
            }
        } catch (Exception $e) {
            $results['splash'] = ['success' => false, 'msg' => "Crash: " . $e->getMessage()];
        }

        try {
            $html_bulk = $this->parse_tags( "<div style='padding:20px; font-family:sans-serif;'><h2>Bulk Broadcast Test</h2><p>This email successfully fired through your Bulk Broadcast settings.</p></div>", $mock_lead, $brand_name, 'HQ' );
            $res_bulk = $this->route_email( $to_email, 'MailToucan Bulk Route Test 🚀', $html_bulk, $brand_id, 'bulk' );
            
            if ( is_array($res_bulk) && isset($res_bulk['success']) && $res_bulk['success'] === true ) {
                $route = $this->get_routing_config( $brand_id );
                $used = ($route['bulk_method'] === 'system') ? $route['system_email'] : $route['from_email'];
                $results['bulk'] = ['success' => true, 'msg' => "Passed! Sent from: " . $used];
            } else {
                $results['bulk'] = ['success' => false, 'msg' => isset($res_bulk['error']) ? $res_bulk['error'] : "Bulk Engine Failed."];
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