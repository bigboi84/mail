<?php
/**
 * Toucan Pro Backend Admin Settings Page
 */
class MT_Admin_Settings {

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        
        // SMTP AJAX Handlers
        add_action( 'wp_ajax_mt_admin_test_smtp_connection', array( $this, 'ajax_admin_test_smtp_connection' ) );
        add_action( 'wp_ajax_mt_save_global_smtps', array( $this, 'ajax_save_global_smtps' ) ); 
    }

    public function add_admin_menu() {
        add_menu_page(
            'MailToucan Settings',
            'Toucan Pro',
            'manage_options',
            'mailtoucan-settings',
            array( $this, 'render_settings_page' ),
            'dashicons-email-alt2',
            100
        );
    }

    public function enqueue_admin_scripts( $hook_suffix ) {
        if ( 'toplevel_page_mailtoucan-settings' !== $hook_suffix ) {
            return;
        }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_media();
        
        wp_add_inline_script( 'wp-color-picker', '
            jQuery(document).ready(function($){
                $(".mt-color-picker").wpColorPicker();
                
                $(".mt-upload-button").click(function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var id = button.attr("id").replace("_button", "");
                    var custom_uploader = wp.media({
                        title: "Choose Toucan Mascot",
                        button: { text: "Use this image" },
                        multiple: false
                    }).on("select", function() {
                        var attachment = custom_uploader.state().get("selection").first().toJSON();
                        $("#" + id).val(attachment.url);
                        $("#" + id + "_preview").attr("src", attachment.url).show();
                    }).open();
                });
            });
        ' );
    }

    public function register_settings() {
        register_setting( 'mailtoucan_settings_group', 'mt_ai_mascot_url' );
        register_setting( 'mailtoucan_settings_group', 'mt_openai_key' );
        register_setting( 'mailtoucan_settings_group', 'mt_toucan_api_key' );
        register_setting( 'mailtoucan_settings_group', 'mt_brand_palette' );
    }

    /**
     * Dedicated AJAX Save Handler for SMTPs
     */
    public function ajax_save_global_smtps() {
        if ( ! check_ajax_referer( 'mt_admin_smtp_test', 'security', false ) ) {
            wp_send_json_error( 'Security Token Expired.' );
        }
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission Denied.' );

        $smtps_json = isset($_POST['smtps']) ? wp_unslash($_POST['smtps']) : '[]';

        if (json_decode($smtps_json) === null) {
            wp_send_json_error( 'Invalid data format.' );
        }

        update_option('mt_marketing_smtps', $smtps_json);
        wp_send_json_success('SMTP Relays saved successfully.');
    }

    /**
     * AJAX Handler: Test SMTP Connection from Admin Panel
     */
    public function ajax_admin_test_smtp_connection() {
        if ( ! check_ajax_referer( 'mt_admin_smtp_test', 'security', false ) ) {
            wp_send_json_error( 'Admin Security Token Expired. Please refresh the page.' );
        }
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission Denied.' );

        $host = sanitize_text_field($_POST['host']);
        $port = intval($_POST['port']);
        $user = sanitize_text_field($_POST['user']);
        $pass = isset($_POST['pass']) ? wp_unslash($_POST['pass']) : '';

        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $user;
            $mail->Password   = $pass;
            $mail->SMTPSecure = ($port == 465) ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $port;
            $mail->Timeout    = 10;

            if ( $mail->smtpConnect() ) {
                $mail->smtpClose();
                wp_send_json_success('Connection successful');
            } else {
                wp_send_json_error('Authentication failed. Check your credentials.');
            }
        } catch (\Exception $e) {
            wp_send_json_error('Connection Error: ' . $e->getMessage());
        }
    }

    public function render_settings_page() {
        global $wpdb;

        $palette = get_option( 'mt_brand_palette', [
            'dark'   => '#1A232E',
            'blue'   => '#283F8F',
            'cream'  => '#FCFAF2',
            'accent' => '#FCC753',
            'orange' => '#E67A05',
            'red'    => '#AE2E00'
        ] );

        $saved_smtps = get_option('mt_marketing_smtps', '[]');
        if (empty($saved_smtps)) $saved_smtps = '[]';
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-email-alt2"></span> MailToucan Pro Global Settings</h1>
            
            <form method="post" action="options.php" id="mt_admin_settings_form">
                <?php settings_fields( 'mailtoucan_settings_group' ); ?>
                <?php do_settings_sections( 'mailtoucan_settings_group' ); ?>

                <table class="form-table mt-admin-table">
                    <tr valign="top">
                        <th scope="row">Tou-can AI Mascot (GIF or Lottie JSON)</th>
                        <td>
                            <input type="text" name="mt_ai_mascot_url" id="mt_ai_mascot_url" value="<?php echo esc_attr( get_option( 'mt_ai_mascot_url' ) ); ?>" class="regular-text" />
                            <input type="button" class="button mt-upload-button" id="mt_ai_mascot_url_button" value="Upload Mascot" /><br />
                            <img id="mt_ai_mascot_url_preview" src="<?php echo esc_url(get_option('mt_ai_mascot_url')); ?>" style="max-width:100px; max-height:100px; margin-top:10px; <?php echo get_option('mt_ai_mascot_url') ? '' : 'display:none;'; ?>" />
                        </td>
                    </tr>
                </table>

                <hr style="margin-top: 30px;">

                <h2><i class="dashicons dashicons-key"></i> API Integrations</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">OpenAI Key (for Tou-can Magic Assist)</th>
                        <td><input type="password" name="mt_openai_key" value="<?php echo esc_attr( get_option( 'mt_openai_key' ) ); ?>" class="regular-text" placeholder="sk-..." /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Toucan Core API Key</th>
                        <td><input type="password" name="mt_toucan_api_key" value="<?php echo esc_attr( get_option( 'mt_toucan_api_key' ) ); ?>" class="regular-text" /></td>
                    </tr>
                </table>

                <hr>

                <h2><i class="dashicons dashicons-color-picker"></i> Brand Identity & Colors</h2>
                <table class="form-table mt-color-table">
                    <tr valign="top">
                        <th scope="row">Primary Accent Color (Mustard)</th>
                        <td><input type="text" name="mt_brand_palette[accent]" value="<?php echo esc_attr( $palette['accent'] ); ?>" class="mt-color-picker" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Dark Charcoal (Sidebar Background)</th>
                        <td><input type="text" name="mt_brand_palette[dark]" value="<?php echo esc_attr( $palette['dark'] ); ?>" class="mt-color-picker" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Branding Blue</th>
                        <td><input type="text" name="mt_brand_palette[blue]" value="<?php echo esc_attr( $palette['blue'] ); ?>" class="mt-color-picker" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Cream (Light Backgrounds)</th>
                        <td><input type="text" name="mt_brand_palette[cream]" value="<?php echo esc_attr( $palette['cream'] ); ?>" class="mt-color-picker" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Orange</th>
                        <td><input type="text" name="mt_brand_palette[orange]" value="<?php echo esc_attr( $palette['orange'] ); ?>" class="mt-color-picker" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Red-Orange</th>
                        <td><input type="text" name="mt_brand_palette[red]" value="<?php echo esc_attr( $palette['red'] ); ?>" class="mt-color-picker" /></td>
                    </tr>
                </table>

                <?php submit_button( 'Save App Settings', 'primary', 'submit', true ); ?>
            </form>

            <hr style="margin: 40px 0;">

            <div style="background: #f8f9fa; border: 1px solid #ccd0d4; padding: 20px; border-radius: 8px;">
                <h2><i class="dashicons dashicons-networking"></i> Weighted SMTP Load Balancer (Global)</h2>
                <p class="description mb-4">Allocate traffic percentages to your relay providers. Click 'Test' to verify, then you <b>MUST</b> click 'Save SMTP Configuration' below to save changes.</p>
                
                <div id="smtp_pool_container"></div>
                
                <div style="margin-top: 15px; display: flex; gap: 15px; align-items: center; justify-content: space-between;">
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <button type="button" class="button button-secondary" onclick="addSmtpRow()">+ Add SMTP Relay</button>
                        <span id="weight_warning" style="color: #d63638; font-weight: bold; display: none;">Warning: Active weights do not equal 100%</span>
                    </div>
                    <button type="button" id="btn_save_smtps" class="button button-primary button-large" onclick="saveSmtpPoolViaAjax()">Save SMTP Configuration</button>
                </div>
            </div>

            <hr style="margin: 40px 0;">

            <h2><i class="dashicons dashicons-admin-site-alt3"></i> Customer Authenticated Domains</h2>
            <p class="description">Track all custom domains your clients have added to their MailToucan accounts for white-label sending.</p>
            <table class="wp-list-table widefat fixed striped" style="max-width: 1200px; margin-top: 15px; margin-bottom: 40px;">
                <thead>
                    <tr>
                        <th style="width: 25%;">Brand / Tenant</th>
                        <th style="width: 35%;">Domain Name</th>
                        <th style="width: 20%;">Added On</th>
                        <th style="width: 20%;">Verification Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Join domains table with brands table to get the brand name
                    $tenant_domains = $wpdb->get_results("
                        SELECT d.domain_name, d.status, d.created_at, b.brand_name 
                        FROM {$wpdb->prefix}mt_email_domains d 
                        LEFT JOIN {$wpdb->prefix}mt_brands b ON d.brand_id = b.id 
                        ORDER BY d.created_at DESC
                    ");

                    if(empty($tenant_domains)) {
                        echo '<tr><td colspan="4" style="padding: 15px; color: #6b7280; font-style: italic;">No custom domains have been added by customers yet.</td></tr>';
                    } else {
                        foreach($tenant_domains as $td) {
                            $status_badge = $td->status === 'verified' ? '<span style="color:#059669;font-weight:bold;"><i class="dashicons dashicons-yes-alt"></i> Verified</span>' : '<span style="color:#d97706;font-weight:bold;"><i class="dashicons dashicons-clock"></i> Pending DNS</span>';
                            echo '<tr>';
                            echo '<td><strong>' . esc_html($td->brand_name ?? 'Unknown Brand') . '</strong></td>';
                            echo '<td>' . esc_html($td->domain_name) . '</td>';
                            echo '<td>' . esc_html(date('M d, Y', strtotime($td->created_at))) . '</td>';
                            echo '<td>' . $status_badge . '</td>';
                            echo '</tr>';
                        }
                    }
                    ?>
                </tbody>
            </table>

            <h2><i class="dashicons dashicons-groups"></i> Customer (Tenant) Custom SMTP Relays</h2>
            <p class="description">View the custom delivery relays configured by your clients (if they choose to use their own SendGrid/Mailgun accounts instead of your global network).</p>
            <table class="wp-list-table widefat fixed striped" style="max-width: 1200px; margin-top: 15px;">
                <thead>
                    <tr>
                        <th style="width: 25%;">Brand / Tenant</th>
                        <th style="width: 25%;">Relay Host</th>
                        <th style="width: 20%;">Username</th>
                        <th style="width: 20%;">Sender Email</th>
                        <th style="width: 10%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $brands = $wpdb->get_results("SELECT brand_name, brand_config FROM {$wpdb->prefix}mt_brands");
                    $has_tenant_smtps = false;

                    foreach($brands as $b) {
                        $c = json_decode($b->brand_config, true);
                        if(!empty($c['delivery_pool']) && is_array($c['delivery_pool'])) {
                            foreach($c['delivery_pool'] as $t_smtp) {
                                $has_tenant_smtps = true;
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($b->brand_name); ?></strong></td>
                                    <td><?php echo esc_html($t_smtp['host'] . ':' . $t_smtp['port']); ?></td>
                                    <td><?php echo esc_html($t_smtp['user']); ?></td>
                                    <td><?php echo esc_html($t_smtp['from_email']); ?></td>
                                    <td><?php echo !empty($t_smtp['active']) ? '<span style="color:#059669;font-weight:bold;">Active</span>' : '<span style="color:#6b7280;font-weight:bold;">Paused</span>'; ?></td>
                                </tr>
                                <?php
                            }
                        }
                    }

                    if(!$has_tenant_smtps) {
                        echo '<tr><td colspan="5" style="padding: 15px; color: #6b7280; font-style: italic;">No customers have set up custom SMTPs yet. They are currently using your global load balancer.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div id="mt-toast" class="mt-toast"></div>

        <style>
            .mt-admin-table th { width: 300px; }
            .mt-color-table input { width: 100px; }
            .mt-color-table .description { display: block; margin-top: 5px; font-style: italic; }
            .smtp-row { background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin-bottom: 10px; border-left: 4px solid #2271b1; box-shadow: 0 1px 1px rgba(0,0,0,.04); display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;}
            .smtp-col { display: flex; flex-direction: column; }
            .smtp-col label { font-size: 11px; font-weight: bold; text-transform: uppercase; color: #646970; margin-bottom: 5px; }
            .smtp-col input { padding: 3px 8px; font-size: 13px; }
            .test-btn { background: #f0f6fc; color: #2271b1; border: 1px solid #2271b1; border-radius: 3px; cursor: pointer; padding: 4px 10px; font-weight: bold; transition: all 0.2s; }
            .test-btn:hover { background: #2271b1; color: #fff; }
            
            .mt-toast { visibility: hidden; min-width: 250px; background-color: #333; color: #fff; text-align: center; border-radius: 8px; padding: 16px; position: fixed; z-index: 99999; right: 20px; bottom: 20px; font-weight: bold; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); opacity: 0; transition: opacity 0.3s, transform 0.3s; transform: translateY(20px); }
            .mt-toast.show { visibility: visible; opacity: 1; transform: translateY(0); }
            .mt-toast.success { background-color: #059669; border-left: 6px solid #047857; }
            .mt-toast.error { background-color: #dc2626; border-left: 6px solid #b91c1c; }
        </style>

        <script>
            let smtpPool = <?php echo $saved_smtps; ?>;
            if(typeof smtpPool === 'string') { try { smtpPool = JSON.parse(smtpPool); } catch(e) { smtpPool = []; } }

            function showToast(message, type = 'error') {
                const toast = document.getElementById('mt-toast');
                toast.innerText = message;
                toast.className = 'mt-toast show ' + type;
                setTimeout(() => { toast.className = toast.className.replace('show', ''); }, 4000);
            }

            function renderSmtpPool() {
                const container = document.getElementById('smtp_pool_container');
                container.innerHTML = '';
                let totalWeight = 0;

                smtpPool.forEach((smtp, index) => {
                    if (smtp.active) totalWeight += parseInt(smtp.weight || 0);

                    const row = document.createElement('div');
                    row.className = 'smtp-row';
                    
                    row.innerHTML = `
                        <div class="smtp-col">
                            <label>Host</label>
                            <input type="text" id="host_${index}" value="${smtp.host || ''}" oninput="updateSmtp(${index}, 'host', this.value)" placeholder="smtp.mailbaby.net">
                        </div>
                        <div class="smtp-col" style="width: 70px;">
                            <label>Port</label>
                            <input type="number" id="port_${index}" value="${smtp.port || 587}" oninput="updateSmtp(${index}, 'port', this.value)">
                        </div>
                        <div class="smtp-col">
                            <label>Username</label>
                            <input type="text" id="user_${index}" value="${smtp.user || ''}" oninput="updateSmtp(${index}, 'user', this.value)">
                        </div>
                        <div class="smtp-col">
                            <label>Password</label>
                            <input type="password" id="pass_${index}" value="${smtp.pass || ''}" oninput="updateSmtp(${index}, 'pass', this.value)">
                        </div>
                        <div class="smtp-col" style="width: 80px;">
                            <label>Weight (%)</label>
                            <input type="number" min="0" max="100" value="${smtp.weight || 0}" oninput="updateSmtp(${index}, 'weight', this.value)">
                        </div>
                        <div class="smtp-col">
                            <label>Sender Email (Fallback)</label>
                            <input type="email" value="${smtp.from_email || ''}" oninput="updateSmtp(${index}, 'from_email', this.value)" placeholder="hello@fly.mailtoucan.com">
                        </div>
                        <div class="smtp-col">
                            <label>Sender Name (Fallback)</label>
                            <input type="text" value="${smtp.from_name || ''}" oninput="updateSmtp(${index}, 'from_name', this.value)" placeholder="MailToucan">
                        </div>
                        <div class="smtp-col" style="flex-direction: row; align-items: center; gap: 5px; margin-bottom: 5px;">
                            <input type="checkbox" ${smtp.active ? 'checked' : ''} onchange="updateSmtp(${index}, 'active', this.checked)">
                            <span style="font-size: 13px; font-weight: bold;">Active</span>
                        </div>
                        <div class="smtp-col" style="margin-left: auto; gap: 8px; flex-direction: row;">
                            <button type="button" class="test-btn" id="btn_test_${index}" onclick="testSmtpConnection(${index})">Test</button>
                            <button type="button" class="button" style="color: #d63638; border-color: #d63638;" onclick="removeSmtp(${index})">Remove</button>
                        </div>
                    `;
                    container.appendChild(row);
                });

                document.getElementById('weight_warning').style.display = (totalWeight !== 100 && smtpPool.length > 0) ? 'block' : 'none';
            }

            function addSmtpRow() {
                smtpPool.push({ host: '', port: 587, user: '', pass: '', from_email: 'hello@fly.mailtoucan.com', from_name: 'MailToucan', weight: 0, active: true });
                renderSmtpPool();
            }

            function removeSmtp(index) {
                if(confirm('Remove this relay server?')) { smtpPool.splice(index, 1); renderSmtpPool(); }
            }

            function updateSmtp(index, key, value) {
                smtpPool[index][key] = value;
                if(key === 'weight' || key === 'active') {
                    let tw = 0;
                    smtpPool.forEach(s => { if(s.active) tw += parseInt(s.weight || 0); });
                    document.getElementById('weight_warning').style.display = (tw !== 100 && smtpPool.length > 0) ? 'block' : 'none';
                }
            }

            function saveSmtpPoolViaAjax() {
                const btn = document.getElementById('btn_save_smtps');
                btn.innerText = 'Saving...';
                btn.disabled = true;

                const fd = new FormData();
                fd.append('action', 'mt_save_global_smtps');
                fd.append('security', '<?php echo wp_create_nonce("mt_admin_smtp_test"); ?>');
                fd.append('smtps', JSON.stringify(smtpPool));

                fetch(ajaxurl, { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        showToast(data.data, 'success');
                    } else {
                        showToast("Save Failed: " + data.data, 'error');
                    }
                    setTimeout(() => { btn.innerText = 'Save SMTP Configuration'; btn.disabled = false; }, 1000);
                });
            }

            function testSmtpConnection(index) {
                const btn = document.getElementById('btn_test_' + index);
                const ogText = btn.innerText;
                btn.innerText = 'Testing...';
                btn.disabled = true;

                const fd = new FormData();
                fd.append('action', 'mt_admin_test_smtp_connection');
                fd.append('security', '<?php echo wp_create_nonce("mt_admin_smtp_test"); ?>');
                fd.append('host', document.getElementById('host_' + index).value);
                fd.append('port', document.getElementById('port_' + index).value);
                fd.append('user', document.getElementById('user_' + index).value);
                fd.append('pass', document.getElementById('pass_' + index).value);

                fetch(ajaxurl, { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        btn.innerText = 'Success!';
                        btn.style.backgroundColor = '#d1fae5';
                        btn.style.color = '#059669';
                        btn.style.borderColor = '#059669';
                        showToast('Connection Validated!', 'success');
                    } else {
                        btn.innerText = 'Failed';
                        btn.style.backgroundColor = '#fee2e2';
                        btn.style.color = '#dc2626';
                        btn.style.borderColor = '#dc2626';
                        showToast('Connection Failed. Check credentials.', 'error');
                    }
                    setTimeout(() => {
                        btn.innerText = ogText;
                        btn.disabled = false;
                        btn.style = '';
                    }, 3000);
                });
            }

            // Init
            renderSmtpPool();
        </script>
        <?php
    }
}