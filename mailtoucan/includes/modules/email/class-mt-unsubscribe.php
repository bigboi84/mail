<?php
/**
 * MailToucan Unsubscribe System
 * Handles the public-facing unsubscribe page and CRM status updates.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MT_Unsubscribe {

    public function init() {
        // Register the custom URL route: /unsubscribe/
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        
        // Intercept the page load to show our custom UI
        add_action( 'template_redirect', array( $this, 'render_unsubscribe_page' ) );

        // AJAX Handler for processing the actual unsubscribe action
        add_action( 'wp_ajax_nopriv_mt_process_unsubscribe', array( $this, 'ajax_process_unsubscribe' ) );
        add_action( 'wp_ajax_mt_process_unsubscribe', array( $this, 'ajax_process_unsubscribe' ) );
    }

    public function add_rewrite_rules() {
        add_rewrite_rule( '^unsubscribe/?$', 'index.php?mt_unsub=1', 'top' );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'mt_unsub';
        return $vars;
    }

    /**
     * The AJAX function that actually updates the database
     */
    public function ajax_process_unsubscribe() {
        global $wpdb;
        $token = sanitize_text_field( $_POST['token'] );

        if ( empty($token) ) {
            wp_send_json_error( 'Invalid request.' );
        }

        $table_leads = $wpdb->prefix . 'mt_guest_leads';
        
        // Update the lead's status to 'unsubscribed'
        $result = $wpdb->update( 
            $table_leads, 
            array( 'status' => 'unsubscribed' ), 
            array( 'unsub_token' => $token ) 
        );

        if ( $result !== false ) {
            wp_send_json_success( 'Successfully unsubscribed.' );
        } else {
            wp_send_json_error( 'Database error. Please try again later.' );
        }
    }

    /**
     * Renders the public-facing UI when someone clicks the link in an email
     */
    public function render_unsubscribe_page() {
        if ( ! get_query_var( 'mt_unsub' ) ) {
            return; // Not the unsubscribe page, do nothing
        }

        global $wpdb;
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        
        $lead = null;
        $brand = null;
        $error = false;

        if ( empty($token) ) {
            $error = 'No token provided. Please use the exact link from your email.';
        } else {
            // Find the lead
            $lead = $wpdb->get_row( $wpdb->prepare("SELECT id, brand_id, email, status FROM {$wpdb->prefix}mt_guest_leads WHERE unsub_token = %s", $token) );
            
            if ( ! $lead ) {
                $error = 'We could not find your record. You may have already been removed.';
            } else {
                // Find the Brand to customize the UI
                $brand = $wpdb->get_row( $wpdb->prepare("SELECT brand_name, primary_color FROM {$wpdb->prefix}mt_brands WHERE id = %d", $lead->brand_id) );
            }
        }

        $brand_name = $brand ? $brand->brand_name : 'Our List';
        $brand_color = ($brand && !empty($brand->primary_color)) ? $brand->primary_color : '#0f172a';
        
        // Output the standalone HTML Page
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Unsubscribe | <?php echo esc_html($brand_name); ?></title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
            <style>
                body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
                :root { --brand-color: <?php echo esc_html($brand_color); ?>; }
            </style>
            <script>
                const ajaxUrl = "<?php echo admin_url('admin-ajax.php'); ?>";
                const unsubToken = "<?php echo esc_js($token); ?>";
            </script>
        </head>
        <body class="min-h-screen flex items-center justify-center p-4">

            <div class="max-w-md w-full bg-white rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.05)] border border-gray-100 overflow-hidden relative" id="ui_container">
                
                <div class="h-3 w-full" style="background-color: var(--brand-color);"></div>

                <div class="p-8 md:p-10 text-center" id="step_confirm">
                    <?php if ( $error ): ?>
                        <div class="w-20 h-20 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fa-solid fa-triangle-exclamation text-3xl"></i>
                        </div>
                        <h1 class="text-2xl font-black text-gray-900 mb-3">Link Expired</h1>
                        <p class="text-gray-500 mb-8"><?php echo esc_html($error); ?></p>
                    <?php elseif ( $lead->status === 'unsubscribed' ): ?>
                        <div class="w-20 h-20 bg-gray-50 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fa-solid fa-envelope-circle-check text-3xl"></i>
                        </div>
                        <h1 class="text-2xl font-black text-gray-900 mb-3">Already Unsubscribed</h1>
                        <p class="text-gray-500 mb-8">You have already been removed from the <strong><?php echo esc_html($brand_name); ?></strong> mailing list. No further action is required.</p>
                    <?php else: ?>
                        <div class="w-20 h-20 bg-gray-50 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-6 border border-gray-100">
                            <i class="fa-solid fa-envelope-open-text text-3xl"></i>
                        </div>
                        <h1 class="text-2xl font-black text-gray-900 mb-3">Unsubscribe</h1>
                        <p class="text-gray-500 mb-8 leading-relaxed">Are you sure you want to stop receiving emails and exclusive offers from <strong><?php echo esc_html($brand_name); ?></strong>?</p>
                        
                        <div class="space-y-3">
                            <button onclick="processUnsubscribe()" id="btn_unsub" class="w-full text-white font-bold py-4 rounded-xl transition shadow-md hover:opacity-90 flex items-center justify-center gap-2" style="background-color: var(--brand-color);">
                                Yes, remove my email
                            </button>
                            <p class="text-xs text-gray-400 mt-4">You are opting out for: <br><span class="font-bold text-gray-600"><?php echo esc_html($lead->email); ?></span></p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="p-8 md:p-10 text-center hidden" id="step_success">
                    <div class="w-24 h-24 bg-green-50 text-green-500 rounded-full flex items-center justify-center mx-auto mb-6 border border-green-100 transform scale-0 transition-transform duration-500" id="success_icon">
                        <i class="fa-solid fa-check text-4xl"></i>
                    </div>
                    <h1 class="text-2xl font-black text-gray-900 mb-3">You're all set.</h1>
                    <p class="text-gray-500 leading-relaxed">You have been successfully unsubscribed. You will no longer receive marketing emails from <strong><?php echo esc_html($brand_name); ?></strong>.</p>
                    <p class="text-xs text-gray-400 mt-8">You may safely close this window.</p>
                </div>

            </div>

            <div class="fixed bottom-6 left-0 right-0 text-center pointer-events-none">
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 opacity-50"><i class="fa-solid fa-dove"></i> Powered by MailToucan</p>
            </div>

            <script>
                function processUnsubscribe() {
                    const btn = document.getElementById('btn_unsub');
                    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Processing...';
                    btn.style.opacity = '0.7';
                    btn.disabled = true;

                    const fd = new FormData();
                    fd.append('action', 'mt_process_unsubscribe');
                    fd.append('token', unsubToken);

                    fetch(ajaxUrl, { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(res => {
                            if(res.success) {
                                document.getElementById('step_confirm').classList.add('hidden');
                                document.getElementById('step_success').classList.remove('hidden');
                                setTimeout(() => {
                                    document.getElementById('success_icon').classList.remove('scale-0');
                                }, 50);
                            } else {
                                alert(res.data || "An error occurred.");
                                btn.innerHTML = 'Yes, remove my email';
                                btn.style.opacity = '1';
                                btn.disabled = false;
                            }
                        }).catch(err => {
                            alert("Server connection failed.");
                            btn.innerHTML = 'Yes, remove my email';
                            btn.style.opacity = '1';
                            btn.disabled = false;
                        });
                }
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

// Instantiate and initialize the Unsubscribe Module
$mt_unsub = new MT_Unsubscribe();
$mt_unsub->init();