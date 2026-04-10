<?php
/**
 * The WiFi Module: Splash Screens, Direct Router Handshakes, and RADIUS Auth via API Bridge
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MT_Wifi_Controller {

    public function init() {
        // Listen for the Form Submission FIRST (When they click connect via direct POST)
        add_action('init', array($this, 'process_wifi_login'));
        
        // Listen for the Router Redirect (When they first join the network on older routers)
        add_action('init', array($this, 'catch_router_redirect'));

        // Listen for the modern AJAX lead capture to trigger RADIUS Auth via API Bridge
        add_action('mt_lead_captured', array($this, 'authorize_guest_mac'), 10, 2);
    }

    // ========================================================================
    // 1. LEGACY / DIRECT MIKROTIK HOTSPOT FEATURES (PRESERVED)
    // ========================================================================

    public function catch_router_redirect() {
        // Ensure we are on the /connect/ page
        $request_uri = $_SERVER['REQUEST_URI'];
        if (strpos($request_uri, '/connect/') !== false) {
            
            // Grab the Router's ID (MAC address) sent by the MikroTik
            $router_mac = isset($_GET['ap_mac']) ? sanitize_text_field($_GET['ap_mac']) : '';
            $client_mac = isset($_GET['client_mac']) ? sanitize_text_field($_GET['client_mac']) : '';

            // Look up which Store owns this Router
            global $wpdb;
            $table_stores = $wpdb->prefix . 'mt_stores';
            $store_data = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_stores WHERE router_identity = %s", 
                $router_mac
            ));

            if ($store_data) {
                // We found the store! Load their specific Splash Screen
                $this->render_splash_screen($store_data, $client_mac, $router_mac);
                exit; // Stop WordPress from loading the normal theme
            } else {
                // Router not found in database. 
                wp_die("Unregistered Access Point. Please contact MailToucan Support.");
            }
        }
    }

    public function process_wifi_login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mt_action']) && $_POST['mt_action'] === 'wifi_login') {
            
            global $wpdb;
            $email = sanitize_email($_POST['guest_email']);
            $store_id = intval($_POST['store_id']);
            $brand_id = intval($_POST['brand_id']);
            $client_mac = sanitize_text_field($_POST['client_mac']);
            $mikrotik_url = esc_url_raw($_POST['mikrotik_url']);

            if (is_email($email)) {
                // 1. Save the Subscriber to the New CRM Table (Updated from mt_roost to mt_guest_leads)
                $table_leads = $wpdb->prefix . 'mt_guest_leads';
                
                // Check if they already exist
                $lead_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_leads WHERE email = %s AND store_id = %d", $email, $store_id));
                
                if (!$lead_id) {
                    $wpdb->insert($table_leads, array(
                        'email' => $email,
                        'brand_id' => $brand_id,
                        'store_id' => $store_id,
                        'guest_mac' => $client_mac,
                        'status' => 'active', 
                        'consent_log' => 'Captured via Direct WiFi POST',
                        'unsub_token' => bin2hex(random_bytes(16))
                    ));
                    $lead_id = $wpdb->insert_id;
                } else {
                    // Update their MAC address if they are a returning guest using a new device
                    $wpdb->update($table_leads, array('guest_mac' => $client_mac), array('id' => $lead_id));
                }

                // 2. Log the Connection Session
                $table_wifi = $wpdb->prefix . 'mt_wifi_logs';
                if ($wpdb->get_var("SHOW TABLES LIKE '{$table_wifi}'") === $table_wifi) {
                    $wpdb->insert($table_wifi, array(
                        'mac_address' => $client_mac,
                        'store_id' => $store_id
                    ));
                }

                // --- NEW: TRIGGER THE RADIUS & AUTOPILOT ENGINE ---
                do_action('mt_lead_captured', $lead_id, $brand_id);

                // 3. The MikroTik Handshake (Auto-Submit Login for non-RADIUS setups)
                ?>
                <!DOCTYPE html>
                <html>
                <body style="background:#f4f4f5; display:flex; justify-content:center; align-items:center; height:100vh; font-family:sans-serif;">
                    <h2>Connecting you to the internet...</h2>
                    <form name="mikrotik_auth" action="<?php echo $mikrotik_url; ?>" method="post">
                        <input type="hidden" name="username" value="guest"> 
                        <input type="hidden" name="password" value="">
                    </form>
                    <script>
                        setTimeout(function() {
                            document.mikrotik_auth.submit();
                        }, 1000); // 1 second delay to ensure the database saved
                    </script>
                </body>
                </html>
                <?php
                exit;
            }
        }
    }

    private function render_splash_screen($store_data, $client_mac, $router_mac) {
        // We grab the MikroTik login URL (usually sent as 'link-login' in the URL by the router)
        $mikrotik_login_url = isset($_GET['link-login']) ? esc_url($_GET['link-login']) : 'http://192.168.88.1/login';
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Connect to <?php echo esc_html($store_data->store_name); ?></title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f4f4f5; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
                .splash-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; width: 90%; max-width: 400px; }
                input[type="email"] { width: 100%; padding: 12px; margin: 15px 0; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-size: 16px; }
                button { background: #E31E24; color: white; border: none; padding: 14px; width: 100%; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; }
                button:hover { background: #c8191f; }
            </style>
        </head>
        <body>
            <div class="splash-card">
                <h2>Welcome to <?php echo esc_html($store_data->store_name); ?></h2>
                <p>Enter your email to access free high-speed WiFi.</p>
                
                <form method="POST" action="">
                    <input type="hidden" name="mt_action" value="wifi_login">
                    <input type="hidden" name="store_id" value="<?php echo esc_attr($store_data->id); ?>">
                    <input type="hidden" name="brand_id" value="<?php echo esc_attr($store_data->brand_id); ?>">
                    <input type="hidden" name="client_mac" value="<?php echo esc_attr($client_mac); ?>">
                    <input type="hidden" name="mikrotik_url" value="<?php echo esc_attr($mikrotik_login_url); ?>">
                    
                    <input type="email" name="guest_email" placeholder="you@email.com" required>
                    <button type="submit">Connect to WiFi</button>
                </form>
            </div>
        </body>
        </html>
        <?php
    }

    // ========================================================================
    // 2. NEW ENTERPRISE FREERADIUS CONTROLLER (API BRIDGE)
    // ========================================================================

    public function authorize_guest_mac( $lead_id, $brand_id ) {
        global $wpdb;
        
        $lead = $wpdb->get_row( $wpdb->prepare("SELECT guest_mac, store_id FROM {$wpdb->prefix}mt_guest_leads WHERE id = %d", $lead_id) );
        
        if ( ! $lead || empty($lead->guest_mac) || $lead->guest_mac === 'UNKNOWN' ) {
            return false; 
        }

        // Format exactly to AA:BB:CC:DD:EE:FF for FreeRADIUS
        $mac = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $lead->guest_mac));
        $mac = implode(':', str_split($mac, 2));

        // Fetch Limits
        $store = $wpdb->get_row( $wpdb->prepare("SELECT local_offer_json FROM {$wpdb->prefix}mt_stores WHERE id = %d", $lead->store_id) );
        $config = $store ? (json_decode($store->local_offer_json, true) ?: []) : [];
        
        $session_time_minutes = isset($config['session_limit_min']) ? intval($config['session_limit_min']) : 120; 
        $session_time_seconds = $session_time_minutes * 60;
        
        $bandwidth_limit_mb = isset($config['bandwidth_limit_mb']) ? intval($config['bandwidth_limit_mb']) : 500; 
        $bandwidth_limit_bytes = $bandwidth_limit_mb * 1024 * 1024; 

        // --- THE FIREWALL BYPASS: Send data over HTTP instead of SQL ---
        $api_url = "http://107.173.49.14/mt-receiver.php";
        
        $payload = array(
            'api_key' => 'JLAmX7sPoWffb7N3GVcp',
            'mac'     => $mac,
            'time'    => $session_time_seconds,
            'bytes'   => $bandwidth_limit_bytes
        );

        $response = wp_remote_post( $api_url, array(
            'method'      => 'POST',
            'timeout'     => 5,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => true,
            'body'        => $payload,
            'cookies'     => array()
        ) );

        if ( is_wp_error( $response ) ) {
            error_log('MailToucan API Bridge Network Error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        
        if ( trim($body) === 'SUCCESS' ) {
            return true;
        } else {
            error_log('MailToucan API Bridge Failed: ' . $body);
            return false;
        }
    }
}

$mt_wifi_hw = new MT_Wifi_Controller();
$mt_wifi_hw->init();