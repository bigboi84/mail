<?php
/**
 * The WiFi Module: Splash Screens, Direct Router Handshakes, and RADIUS Auth
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MT_Wifi_Controller {

    public function init() {
        // Listen for the Form Submission FIRST (When they click connect via direct POST)
        add_action('init', array($this, 'process_wifi_login'));
        
        // Listen for the Router Redirect (When they first join the network on older routers)
        add_action('init', array($this, 'catch_router_redirect'));

        // Listen for the modern AJAX lead capture to trigger RADIUS Auth via Direct DB
        add_action('mt_lead_captured', array($this, 'authorize_guest_mac'), 10, 2);
    }

    // ========================================================================
    // 1. LEGACY / DIRECT MIKROTIK HOTSPOT FEATURES
    // ========================================================================

    public function catch_router_redirect() {
        $request_uri = $_SERVER['REQUEST_URI'];
        if (strpos($request_uri, '/connect/') !== false) {
            
            $router_mac = isset($_GET['ap_mac']) ? sanitize_text_field($_GET['ap_mac']) : '';
            $client_mac = isset($_GET['client_mac']) ? sanitize_text_field($_GET['client_mac']) : '';

            global $wpdb;
            $table_stores = $wpdb->prefix . 'mt_stores';
            $store_data = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_stores WHERE router_identity = %s", 
                $router_mac
            ));

            if ($store_data) {
                $this->render_splash_screen($store_data, $client_mac, $router_mac);
                exit; 
            } else {
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
                $table_leads = $wpdb->prefix . 'mt_guest_leads';
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
                    $wpdb->update($table_leads, array('guest_mac' => $client_mac), array('id' => $lead_id));
                }

                $table_wifi = $wpdb->prefix . 'mt_wifi_logs';
                if ($wpdb->get_var("SHOW TABLES LIKE '{$table_wifi}'") === $table_wifi) {
                    $wpdb->insert($table_wifi, array(
                        'mac_address' => $client_mac,
                        'store_id' => $store_id
                    ));
                }

                // TRIGGER THE RADIUS & AUTOPILOT ENGINE
                do_action('mt_lead_captured', $lead_id, $brand_id);

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
                        }, 1000); 
                    </script>
                </body>
                </html>
                <?php
                exit;
            }
        }
    }

    private function render_splash_screen($store_data, $client_mac, $router_mac) {
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
    // 2. ENTERPRISE FREERADIUS CONTROLLER (DIRECT PDO CONNECTION)
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

        // Fetch Limits from Store Config
        $store = $wpdb->get_row( $wpdb->prepare("SELECT local_offer_json FROM {$wpdb->prefix}mt_stores WHERE id = %d", $lead->store_id) );
        $config = $store ? (json_decode($store->local_offer_json, true) ?: []) : [];
        
        $session_time_minutes = isset($config['session_limit_min']) ? intval($config['session_limit_min']) : 120; 
        $session_time_seconds = $session_time_minutes * 60;
        
        $bandwidth_limit_mb = isset($config['bandwidth_limit_mb']) ? intval($config['bandwidth_limit_mb']) : 500; 

        // Direct connection to the 107.173.49.14 RADIUS Database
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
            error_log('MailToucan RADIUS DB Error: ' . $e->getMessage());
            return false;
        }

        try {
            $pdo->beginTransaction();

            // Clear any previous session for this MAC to prevent conflicts
            $pdo->prepare('DELETE FROM radcheck WHERE username = ?')->execute([$mac]);
            $pdo->prepare('DELETE FROM radreply WHERE username = ?')->execute([$mac]);

            // Authorize this MAC address
            $pdo->prepare(
                "INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Auth-Type', ':=', 'Accept')"
            )->execute([$mac]);

            // Set session time limit (seconds)
            $pdo->prepare(
                "INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Session-Timeout', '=', ?)"
            )->execute([$mac, $session_time_seconds]);

            // Set data cap for MikroTik and CoovaChilli/A62 attributes
            if ($bandwidth_limit_mb > 0) {
                $bytes = $bandwidth_limit_mb * 1024 * 1024;
                
                $pdo->prepare(
                    "INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Mikrotik-Total-Limit', '=', ?)"
                )->execute([$mac, $bytes]);

                $pdo->prepare(
                    "INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'ChilliSpot-Max-Total-Octets', '=', ?)"
                )->execute([$mac, $bytes]);
            }

            $pdo->commit();
            return true;

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('MailToucan RADIUS Injection Failed: ' . $e->getMessage());
            return false;
        }
    }
}

$mt_wifi_hw = new MT_Wifi_Controller();
$mt_wifi_hw->init();