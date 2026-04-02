<?php
/**
 * The WiFi Module: Splash Screens & Router Handshakes
 */

class MT_Wifi_Controller {

    public function init() {
        // Listen for the Form Submission FIRST (When they click connect)
        add_action('init', array($this, 'process_wifi_login'));
        
        // Listen for the Router Redirect (When they first join the network)
        add_action('init', array($this, 'catch_router_redirect'));
    }

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
                // 1. Save the Subscriber to the CRM
                $table_roost = $wpdb->prefix . 'mt_roost';
                
                // Check if they already exist
                $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_roost WHERE email = %s AND store_id = %d", $email, $store_id));
                
                if (!$exists) {
                    $wpdb->insert($table_roost, array(
                        'email' => $email,
                        'brand_id' => $brand_id,
                        'store_id' => $store_id,
                        'status' => 'verified', // Captured via WiFi, so it's a real person
                        'captured_via' => 'wifi'
                    ));
                }

                // 2. Log the Connection Session
                $table_wifi = $wpdb->prefix . 'mt_wifi_logs';
                $wpdb->insert($table_wifi, array(
                    'mac_address' => $client_mac,
                    'store_id' => $store_id
                ));

                // 3. The MikroTik Handshake (Auto-Submit Login)
                // We generate a hidden form that auto-submits to the MikroTik router to grant access.
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
}