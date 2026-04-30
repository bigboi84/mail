<?php
/**
 * The Auth Module v3.0
 * - Custom /login/, /signup/, /forgot-password/ routes
 * - AJAX handlers: register, login, forgot password, reset password
 * - WP Admin blocking & redirect for tenant users
 */
class MT_Auth {

    public function init() {
        // ── Post-login redirect ─────────────────────────────────────────────
        add_filter( 'login_redirect', array( $this, 'redirect_to_app' ), 10, 3 );

        // ── Block wp-admin & hide admin bar for tenants ─────────────────────
        add_action( 'admin_init',        array( $this, 'block_wp_admin' ) );
        add_action( 'after_setup_theme', array( $this, 'hide_admin_bar' ) );

        // ── Custom auth route registration ──────────────────────────────────
        add_action( 'init',              array( $this, 'add_auth_rewrite_rules' ) );
        add_filter( 'query_vars',        array( $this, 'add_auth_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'render_auth_pages' ) );

        // ── Public AJAX handlers (no login required) ─────────────────────────
        add_action( 'wp_ajax_nopriv_mt_register_tenant', array( $this, 'ajax_register_tenant' ) );
        add_action( 'wp_ajax_nopriv_mt_login_tenant',    array( $this, 'ajax_login_tenant' ) );
        add_action( 'wp_ajax_nopriv_mt_forgot_password', array( $this, 'ajax_forgot_password' ) );
        add_action( 'wp_ajax_nopriv_mt_reset_password',  array( $this, 'ajax_reset_password' ) );

        // ── Email verification hook ──────────────────────────────────────────
        add_action( 'template_redirect', array( $this, 'handle_email_verification' ) );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // REWRITE RULES
    // ═══════════════════════════════════════════════════════════════════════

    public function add_auth_rewrite_rules() {
        add_rewrite_rule( '^login/?$',            'index.php?mt_auth_page=login',  'top' );
        add_rewrite_rule( '^signup/?$',           'index.php?mt_auth_page=signup', 'top' );
        add_rewrite_rule( '^forgot-password/?$',  'index.php?mt_auth_page=forgot', 'top' );
        add_rewrite_rule( '^reset-password/?$',   'index.php?mt_auth_page=reset',  'top' );
    }

    public function add_auth_query_vars( $vars ) {
        $vars[] = 'mt_auth_page';
        return $vars;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AUTH PAGE ROUTER
    // ═══════════════════════════════════════════════════════════════════════

    public function render_auth_pages() {
        $page = get_query_var( 'mt_auth_page' );
        if ( ! $page ) return;

        // Logged-in users always go to the app
        if ( is_user_logged_in() ) {
            wp_redirect( home_url( '/app/' ) );
            exit;
        }

        switch ( $page ) {
            case 'login':  $this->render_login_page();  break;
            case 'signup': $this->render_signup_page(); break;
            case 'forgot': $this->render_forgot_page(); break;
            case 'reset':  $this->render_reset_page();  break;
        }
        exit;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // EXISTING: REDIRECT & BLOCK HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    public function redirect_to_app( $redirect_to, $request, $user ) {
        if ( isset( $user->roles ) && is_array( $user->roles ) ) {
            if ( array_intersect( $user->roles, [ 'mt_starter', 'mt_pro', 'mt_enterprise' ] ) ) {
                return home_url( '/app/' );
            }
        }
        return $redirect_to;
    }

    public function block_wp_admin() {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;
        if ( current_user_can( 'access_mt_app' ) && ! current_user_can( 'manage_options' ) ) {
            wp_redirect( home_url( '/app/' ) );
            exit;
        }
    }

    public function hide_admin_bar() {
        if ( current_user_can( 'access_mt_app' ) && ! current_user_can( 'manage_options' ) ) {
            show_admin_bar( false );
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // EMAIL VERIFICATION (from WiFi splash email links)
    // ═══════════════════════════════════════════════════════════════════════

    public function handle_email_verification() {
        if ( empty( $_GET['mt_verify'] ) ) return;
        global $wpdb;
        $token = sanitize_text_field( $_GET['mt_verify'] );
        $lead  = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, brand_id FROM {$wpdb->prefix}mt_guest_leads WHERE unsub_token = %s LIMIT 1", $token
        ) );
        if ( $lead ) {
            $wpdb->update( $wpdb->prefix . 'mt_guest_leads', [ 'status' => 'verified' ], [ 'id' => $lead->id ] );
            if ( class_exists( 'MT_Wifi_Controller' ) ) {
                $wifi = new MT_Wifi_Controller();
                $wifi->authorize_guest_mac( $lead->id, $lead->brand_id, true );
            }
        }
        wp_redirect( home_url( '/?verified=1' ) );
        exit;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HTML PAGE RENDERERS
    // ═══════════════════════════════════════════════════════════════════════

    private function auth_head( $title = 'MailToucan' ) {
        $accent   = get_option( 'mt_brand_palette', [] )['accent'] ?? '#FCC753';
        $dark     = get_option( 'mt_brand_palette', [] )['dark']   ?? '#1A232E';
        $ajax_url = admin_url( 'admin-ajax.php' );
        $nonce    = wp_create_nonce( 'mt_auth_nonce' );
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( $title ); ?> | MailToucan</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@2.1.0/dist/iconify-icon.min.js"></script>
    <style>
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Inter',sans-serif;background:#f3f4f6;min-height:100vh;display:flex;}
        /* ── Left brand panel ── */
        .auth-left{width:440px;flex-shrink:0;background:<?php echo esc_js($dark); ?>;display:flex;flex-direction:column;padding:48px 40px;position:relative;overflow:hidden;}
        .auth-left::before{content:'';position:absolute;bottom:-80px;right:-80px;width:320px;height:320px;border-radius:50%;background:<?php echo esc_js($accent); ?>;opacity:.08;}
        .auth-left::after{content:'';position:absolute;top:-60px;left:-60px;width:220px;height:220px;border-radius:50%;background:<?php echo esc_js($accent); ?>;opacity:.06;}
        .auth-brand{display:flex;align-items:center;gap:10px;margin-bottom:48px;}
        .auth-brand-icon{width:42px;height:42px;background:<?php echo esc_js($accent); ?>;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;}
        .auth-brand-name{font-size:22px;font-weight:900;color:white;letter-spacing:-.02em;}
        .auth-panel-title{font-size:32px;font-weight:900;color:white;line-height:1.2;margin-bottom:16px;letter-spacing:-.02em;}
        .auth-panel-sub{font-size:14px;color:rgba(255,255,255,.6);line-height:1.6;margin-bottom:40px;}
        .auth-feature{display:flex;align-items:flex-start;gap:12px;margin-bottom:20px;}
        .auth-feature-dot{width:32px;height:32px;border-radius:9px;background:rgba(255,255,255,.1);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:14px;color:<?php echo esc_js($accent); ?>;}
        .auth-feature-text{font-size:13px;color:rgba(255,255,255,.75);line-height:1.5;}
        .auth-feature-text strong{color:white;display:block;font-size:14px;margin-bottom:2px;}
        /* ── Right form panel ── */
        .auth-right{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 20px;overflow-y:auto;}
        .auth-form-wrap{width:100%;max-width:440px;}
        .auth-form-title{font-size:26px;font-weight:900;color:#111827;margin-bottom:6px;letter-spacing:-.02em;}
        .auth-form-sub{font-size:14px;color:#6b7280;margin-bottom:28px;}
        .auth-field{margin-bottom:16px;}
        .auth-label{display:block;font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;}
        .auth-input{width:100%;padding:12px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;font-family:inherit;outline:none;transition:border-color .15s,box-shadow .15s;background:#fff;color:#111827;}
        .auth-input:focus{border-color:<?php echo esc_js($accent); ?>;box-shadow:0 0 0 3px <?php echo esc_js($accent); ?>22;}
        .auth-input.error{border-color:#ef4444;background:#fef2f2;}
        .auth-submit{width:100%;padding:13px;background:<?php echo esc_js($dark); ?>;color:white;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:8px;transition:opacity .15s;}
        .auth-submit:hover{opacity:.9;}
        .auth-submit:disabled{opacity:.6;cursor:not-allowed;}
        .auth-google-btn{width:100%;padding:11px;background:white;color:#374151;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:8px;transition:background .15s;margin-bottom:16px;}
        .auth-google-btn:hover{background:#f9fafb;}
        .auth-divider{display:flex;align-items:center;gap:12px;margin:16px 0;}
        .auth-divider-line{flex:1;height:1px;background:#e5e7eb;}
        .auth-divider-text{font-size:12px;color:#9ca3af;font-weight:600;white-space:nowrap;}
        .auth-link{color:<?php echo esc_js($dark); ?>;font-weight:700;text-decoration:none;}
        .auth-link:hover{text-decoration:underline;}
        .auth-error-box{background:#fef2f2;border:1.5px solid #fca5a5;border-radius:10px;padding:12px 14px;font-size:13px;color:#991b1b;margin-bottom:16px;display:none;}
        .auth-success-box{background:#f0fdf4;border:1.5px solid #86efac;border-radius:10px;padding:12px 14px;font-size:13px;color:#166534;margin-bottom:16px;display:none;}
        /* Password strength */
        .pass-strength-bar{height:4px;border-radius:2px;background:#f3f4f6;overflow:hidden;margin-top:6px;}
        .pass-strength-fill{height:100%;border-radius:2px;transition:width .3s,background .3s;width:0;}
        /* Steps indicator */
        .auth-steps{display:flex;gap:8px;margin-bottom:28px;}
        .auth-step-dot{flex:1;height:4px;border-radius:2px;background:#e5e7eb;transition:background .3s;}
        .auth-step-dot.active{background:<?php echo esc_js($accent); ?>;}
        .auth-step-dot.done{background:<?php echo esc_js($dark); ?>;}
        /* Plan cards */
        .plan-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;}
        .plan-card{border:2px solid #e5e7eb;border-radius:12px;padding:16px;cursor:pointer;transition:all .15s;position:relative;}
        .plan-card:hover{border-color:#d1d5db;}
        .plan-card.selected{border-color:<?php echo esc_js($dark); ?>;background:#f8fafc;}
        .plan-card-name{font-size:13px;font-weight:800;color:#111827;margin-bottom:2px;}
        .plan-card-price{font-size:22px;font-weight:900;color:<?php echo esc_js($dark); ?>;line-height:1;}
        .plan-card-sub{font-size:11px;color:#9ca3af;margin-top:2px;}
        /* Mobile */
        @media(max-width:768px){
            .auth-left{display:none;}
            .auth-right{padding:24px 16px;}
        }
    </style>
    <script>
        const mt_ajax  = "<?php echo esc_js( $ajax_url ); ?>";
        const mt_nonce = "<?php echo esc_js( $nonce ); ?>";
    </script>
</head>
<body>
        <?php
    }

    private function auth_foot() {
        echo '</body></html>';
    }

    // ──────────────────────────────────────────────────────────────────────
    // LOGIN PAGE
    // ──────────────────────────────────────────────────────────────────────

    private function render_login_page() {
        $this->auth_head( 'Sign In' );
        $palette = get_option( 'mt_brand_palette', [] );
        $dark    = $palette['dark'] ?? '#1A232E';
        // Honour ?redirect_to= so unauthenticated users land on their intended page after login
        $redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/app/' );
        ?>
<div class="auth-left">
    <div class="auth-brand">
        <div class="auth-brand-icon"><i class="fa-solid fa-dove" style="color:<?php echo esc_attr($dark); ?>;"></i></div>
        <div class="auth-brand-name">MailToucan</div>
    </div>
    <div class="auth-panel-title">WiFi that works for your business.</div>
    <p class="auth-panel-sub">Capture guests, send campaigns, and grow your audience — all from one dashboard.</p>
    <div class="auth-feature">
        <div class="auth-feature-dot"><i class="fa-solid fa-wifi"></i></div>
        <div class="auth-feature-text"><strong>Smart Captive Portals</strong>Beautiful WiFi splash pages that capture real leads.</div>
    </div>
    <div class="auth-feature">
        <div class="auth-feature-dot"><i class="fa-solid fa-paper-plane"></i></div>
        <div class="auth-feature-text"><strong>Email Marketing</strong>Send campaigns directly to your WiFi guests.</div>
    </div>
    <div class="auth-feature">
        <div class="auth-feature-dot"><i class="fa-solid fa-chart-line"></i></div>
        <div class="auth-feature-text"><strong>Real Analytics</strong>Track connections, opens, and engagement.</div>
    </div>
</div>
<div class="auth-right">
    <div class="auth-form-wrap">
        <div class="auth-form-title">Welcome back</div>
        <p class="auth-form-sub">Sign in to your MailToucan account.</p>

        <button class="auth-google-btn" onclick="handleGoogleLogin()">
            <iconify-icon icon="flat-color-icons:google" style="font-size:20px;"></iconify-icon>
            Continue with Google
        </button>
        <div class="auth-divider"><div class="auth-divider-line"></div><div class="auth-divider-text">or sign in with email</div><div class="auth-divider-line"></div></div>

        <div id="login_error" class="auth-error-box"></div>

        <div class="auth-field">
            <label class="auth-label">Email Address</label>
            <input type="email" id="login_email" class="auth-input" placeholder="you@yourbusiness.com" onkeydown="if(event.key==='Enter')submitLogin()">
        </div>
        <div class="auth-field">
            <label class="auth-label">Password</label>
            <div style="position:relative;">
                <input type="password" id="login_pass" class="auth-input" placeholder="Your password" style="padding-right:44px;" onkeydown="if(event.key==='Enter')submitLogin()">
                <button type="button" onclick="togglePass('login_pass',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ca3af;font-size:15px;"><i class="fa-solid fa-eye"></i></button>
            </div>
        </div>
        <div style="text-align:right;margin-bottom:20px;">
            <a href="<?php echo esc_url( home_url('/forgot-password/') ); ?>" class="auth-link" style="font-size:13px;">Forgot password?</a>
        </div>
        <button class="auth-submit" id="btn_login" onclick="submitLogin()">
            <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In
        </button>
        <p style="text-align:center;margin-top:20px;font-size:13px;color:#6b7280;">
            Don't have an account? <a href="<?php echo esc_url( home_url('/signup/') ); ?>" class="auth-link">Start here</a>
        </p>
    </div>
</div>
<script>
function togglePass(id, btn) {
    const inp = document.getElementById(id);
    const showing = inp.type === 'text';
    inp.type = showing ? 'password' : 'text';
    btn.innerHTML = showing ? '<i class="fa-solid fa-eye"></i>' : '<i class="fa-solid fa-eye-slash"></i>';
}
function submitLogin() {
    const email = document.getElementById('login_email').value.trim();
    const pass  = document.getElementById('login_pass').value;
    const errBox = document.getElementById('login_error');
    const btn   = document.getElementById('btn_login');

    errBox.style.display = 'none';
    if (!email || !pass) { showErr('Please enter your email and password.'); return; }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Signing in...';

    const fd = new FormData();
    fd.append('action', 'mt_login_tenant');
    fd.append('security', mt_nonce);
    fd.append('email', email);
    fd.append('password', pass);
    fd.append('redirect_to', '<?php echo esc_js( $redirect_to ); ?>');

    fetch(mt_ajax, { method:'POST', body:fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Success!';
            window.location.href = d.data.redirect || '<?php echo esc_js( home_url('/app/') ); ?>';
        } else {
            showErr(d.data);
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In';
        }
    })
    .catch(() => {
        showErr('Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In';
    });
}
function showErr(msg) {
    const e = document.getElementById('login_error');
    e.textContent = msg;
    e.style.display = 'block';
}
function handleGoogleLogin() {
    const clientId = '<?php echo esc_js( get_option("mt_google_client_id","") ); ?>';
    if (!clientId) { showErr('Google login is not configured yet. Please use email/password.'); return; }
    const params = new URLSearchParams({
        client_id: clientId,
        redirect_uri: '<?php echo esc_js( admin_url("admin-ajax.php") ); ?>?action=mt_google_callback',
        response_type: 'code',
        scope: 'email profile',
        state: btoa(JSON.stringify({mode:'login', return:'<?php echo esc_js( home_url('/app/') ); ?>'}))
    });
    window.location.href = 'https://accounts.google.com/o/oauth2/auth?' + params.toString();
}
</script>
        <?php
        $this->auth_foot();
    }

    // ──────────────────────────────────────────────────────────────────────
    // SIGNUP PAGE (3-step)
    // ──────────────────────────────────────────────────────────────────────

    private function render_signup_page() {
        $this->auth_head( 'Create Account' );
        $palette = get_option( 'mt_brand_palette', [] );
        $dark    = $palette['dark']   ?? '#1A232E';
        $accent  = $palette['accent'] ?? '#FCC753';
        ?>
<div class="auth-left">
    <div class="auth-brand">
        <div class="auth-brand-icon"><i class="fa-solid fa-dove" style="color:<?php echo esc_attr($dark); ?>;"></i></div>
        <div class="auth-brand-name">MailToucan</div>
    </div>
    <div class="auth-panel-title">Launch your WiFi marketing in minutes.</div>
    <p class="auth-panel-sub">Join hundreds of businesses already growing with MailToucan.</p>
    <div class="auth-feature">
        <div class="auth-feature-dot" style="color:#22c55e;"><i class="fa-solid fa-circle-check"></i></div>
        <div class="auth-feature-text"><strong>Step 1 — Your Account</strong>Set up your email and create a secure password.</div>
    </div>
    <div class="auth-feature">
        <div class="auth-feature-dot"><i class="fa-solid fa-building"></i></div>
        <div class="auth-feature-text"><strong>Step 2 — Your Business</strong>Name your brand and choose your WiFi plan.</div>
    </div>
    <div class="auth-feature">
        <div class="auth-feature-dot"><i class="fa-solid fa-rocket"></i></div>
        <div class="auth-feature-text"><strong>Step 3 — Go Live</strong>Your dashboard is ready instantly. No setup calls needed.</div>
    </div>
    <div style="margin-top:auto;padding-top:32px;border-top:1px solid rgba(255,255,255,.1);font-size:12px;color:rgba(255,255,255,.4);">
        By signing up you agree to our <a href="<?php echo esc_url( home_url('/auth-terms.html') ); ?>" style="color:rgba(255,255,255,.6);" target="_blank">Terms of Service</a>. A card is required — no free accounts.
    </div>
</div>
<div class="auth-right">
    <div class="auth-form-wrap">

        <!-- Step progress dots -->
        <div class="auth-steps">
            <div class="auth-step-dot active" id="dot_1"></div>
            <div class="auth-step-dot" id="dot_2"></div>
            <div class="auth-step-dot" id="dot_3"></div>
        </div>

        <div id="signup_error"   class="auth-error-box"></div>
        <div id="signup_success" class="auth-success-box"></div>

        <!-- ── STEP 1: Email ── -->
        <div id="step_1">
            <div class="auth-form-title">Create your account</div>
            <p class="auth-form-sub">Start with Google or enter your work email.</p>

            <button class="auth-google-btn" onclick="handleGoogleSignup()">
                <iconify-icon icon="flat-color-icons:google" style="font-size:20px;"></iconify-icon>
                Sign up with Google
            </button>
            <div class="auth-divider"><div class="auth-divider-line"></div><div class="auth-divider-text">or continue with email</div><div class="auth-divider-line"></div></div>

            <div class="auth-field">
                <label class="auth-label">Work Email</label>
                <input type="email" id="su_email" class="auth-input" placeholder="you@yourbusiness.com" onkeydown="if(event.key==='Enter')goStep2()">
            </div>
            <button class="auth-submit" onclick="goStep2()">
                Continue <i class="fa-solid fa-arrow-right"></i>
            </button>
            <p style="text-align:center;margin-top:20px;font-size:13px;color:#6b7280;">
                Already have an account? <a href="<?php echo esc_url( home_url('/login/') ); ?>" class="auth-link">Sign in</a>
            </p>
        </div>

        <!-- ── STEP 2: Business + Plan ── -->
        <div id="step_2" style="display:none;">
            <div class="auth-form-title">Tell us about your business</div>
            <p class="auth-form-sub">Choose a plan and set your brand name.</p>

            <div class="auth-field">
                <label class="auth-label">Business Name</label>
                <input type="text" id="su_brand" class="auth-input" placeholder="e.g. The Coffee House" onkeydown="if(event.key==='Enter')goStep3()">
            </div>

            <div class="auth-field">
                <label class="auth-label">Choose Your Plan</label>
                <div class="plan-grid">
                    <div class="plan-card selected" id="plan_standard" onclick="selectPlan('mt_starter','plan_standard')">
                        <div class="plan-card-name">Standard</div>
                        <div class="plan-card-price">$49</div>
                        <div class="plan-card-sub">/month · 1 location</div>
                    </div>
                    <div class="plan-card" id="plan_pro" onclick="selectPlan('mt_pro','plan_pro')">
                        <div class="plan-card-name">Pro</div>
                        <div class="plan-card-price">$99</div>
                        <div class="plan-card-sub">/month · 3 locations</div>
                    </div>
                </div>
                <input type="hidden" id="su_plan" value="mt_starter">
                <p style="font-size:11px;color:#9ca3af;margin-top:4px;"><i class="fa-solid fa-lock" style="margin-right:4px;"></i>Payment collected after account setup. No surprises.</p>
            </div>

            <div style="display:flex;gap:10px;">
                <button class="auth-submit" style="background:#f3f4f6;color:#374151;flex:0 0 auto;width:auto;padding:13px 20px;" onclick="goStep(1)">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <button class="auth-submit" style="flex:1;" onclick="goStep3()">
                    Continue <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- ── STEP 3: Password + Terms ── -->
        <div id="step_3" style="display:none;">
            <div class="auth-form-title">Secure your account</div>
            <p class="auth-form-sub">Set a strong password to protect your dashboard.</p>

            <div class="auth-field">
                <label class="auth-label">Password</label>
                <div style="position:relative;">
                    <input type="password" id="su_pass" class="auth-input" placeholder="Min 8 characters" style="padding-right:44px;" oninput="checkPassStrength(this.value)">
                    <button type="button" onclick="togglePass('su_pass',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ca3af;font-size:15px;"><i class="fa-solid fa-eye"></i></button>
                </div>
                <div class="pass-strength-bar"><div class="pass-strength-fill" id="pass_fill"></div></div>
                <div style="font-size:11px;color:#9ca3af;margin-top:3px;" id="pass_hint"></div>
            </div>
            <div class="auth-field">
                <label class="auth-label">Confirm Password</label>
                <input type="password" id="su_pass2" class="auth-input" placeholder="Repeat your password">
            </div>

            <label style="display:flex;align-items:flex-start;gap:8px;margin-bottom:20px;font-size:12px;color:#6b7280;cursor:pointer;">
                <input type="checkbox" id="su_terms" style="margin-top:2px;accent-color:<?php echo esc_attr($dark); ?>;">
                I agree to MailToucan's <a href="<?php echo esc_url( home_url('/auth-terms.html') ); ?>" target="_blank" class="auth-link">Terms of Service</a> and <a href="<?php echo esc_url( home_url('/auth-terms.html') ); ?>" target="_blank" class="auth-link">Acceptable Use Policy</a>. I confirm this account will not be used for spam, adult content, gambling, or illegal activity.
            </label>

            <div style="display:flex;gap:10px;">
                <button class="auth-submit" style="background:#f3f4f6;color:#374151;flex:0 0 auto;width:auto;padding:13px 20px;" onclick="goStep(2)">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <button class="auth-submit" id="btn_register" style="flex:1;" onclick="submitRegistration()">
                    <i class="fa-solid fa-rocket"></i> Launch My Account
                </button>
            </div>
        </div>

    </div>
</div>
<script>
let currentStep = 1;
let selectedPlan = 'mt_starter';

function goStep(n) {
    document.getElementById('step_' + currentStep).style.display = 'none';
    document.getElementById('step_' + n).style.display = 'block';
    // Update dots
    [1,2,3].forEach(i => {
        const dot = document.getElementById('dot_'+i);
        dot.classList.remove('active','done');
        if (i < n) dot.classList.add('done');
        else if (i === n) dot.classList.add('active');
    });
    currentStep = n;
    document.getElementById('signup_error').style.display = 'none';
}

function goStep2() {
    const email = document.getElementById('su_email').value.trim();
    if (!email || !email.includes('@')) { showSignupErr('Please enter a valid email address.'); return; }
    goStep(2);
    setTimeout(() => document.getElementById('su_brand').focus(), 100);
}

function goStep3() {
    const brand = document.getElementById('su_brand').value.trim();
    if (!brand) { showSignupErr('Please enter your business name.'); return; }
    goStep(3);
    setTimeout(() => document.getElementById('su_pass').focus(), 100);
}

function selectPlan(slug, cardId) {
    selectedPlan = slug;
    document.getElementById('su_plan').value = slug;
    ['plan_standard','plan_pro'].forEach(id => document.getElementById(id).classList.remove('selected'));
    document.getElementById(cardId).classList.add('selected');
}

function checkPassStrength(val) {
    const fill = document.getElementById('pass_fill');
    const hint = document.getElementById('pass_hint');
    let score = 0;
    if (val.length >= 8)  score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^a-zA-Z0-9]/.test(val)) score++;
    const levels = [
        {pct:'20%', bg:'#ef4444', label:'Too short'},
        {pct:'40%', bg:'#f97316', label:'Weak'},
        {pct:'60%', bg:'#f59e0b', label:'Fair'},
        {pct:'80%', bg:'#22c55e', label:'Good'},
        {pct:'100%',bg:'#059669', label:'Strong 💪'}
    ];
    const l = levels[Math.max(0, score-1)] || levels[0];
    fill.style.width = val.length ? l.pct : '0';
    fill.style.background = l.bg;
    hint.textContent = val.length ? l.label : '';
}

function togglePass(id, btn) {
    const inp = document.getElementById(id);
    const showing = inp.type === 'text';
    inp.type = showing ? 'password' : 'text';
    btn.innerHTML = showing ? '<i class="fa-solid fa-eye"></i>' : '<i class="fa-solid fa-eye-slash"></i>';
}

function submitRegistration() {
    const email = document.getElementById('su_email').value.trim();
    const brand = document.getElementById('su_brand').value.trim();
    const plan  = document.getElementById('su_plan').value;
    const pass  = document.getElementById('su_pass').value;
    const pass2 = document.getElementById('su_pass2').value;
    const terms = document.getElementById('su_terms').checked;

    if (!terms)          { showSignupErr('You must agree to the Terms of Service to continue.'); return; }
    if (pass.length < 8) { showSignupErr('Password must be at least 8 characters.'); return; }
    if (pass !== pass2)  { showSignupErr('Passwords do not match.'); return; }

    const btn = document.getElementById('btn_register');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Creating your account...';

    const fd = new FormData();
    fd.append('action',    'mt_register_tenant');
    fd.append('security',  mt_nonce);
    fd.append('email',     email);
    fd.append('brand',     brand);
    fd.append('plan',      plan);
    fd.append('password',  pass);

    fetch(mt_ajax, { method:'POST', body:fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const s = document.getElementById('signup_success');
            s.textContent = 'Account created! Taking you to your dashboard...';
            s.style.display = 'block';
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Done!';
            setTimeout(() => { window.location.href = d.data.redirect || '<?php echo esc_js( home_url('/app/') ); ?>'; }, 1200);
        } else {
            showSignupErr(d.data);
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-rocket"></i> Launch My Account';
        }
    })
    .catch(() => {
        showSignupErr('Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-rocket"></i> Launch My Account';
    });
}

function showSignupErr(msg) {
    const e = document.getElementById('signup_error');
    e.textContent = msg;
    e.style.display = 'block';
}

function handleGoogleSignup() {
    const clientId = '<?php echo esc_js( get_option("mt_google_client_id","") ); ?>';
    if (!clientId) { showSignupErr('Google signup is not configured yet. Please use email/password.'); return; }
    const params = new URLSearchParams({
        client_id: clientId,
        redirect_uri: '<?php echo esc_js( admin_url("admin-ajax.php") ); ?>?action=mt_google_callback',
        response_type: 'code',
        scope: 'email profile',
        state: btoa(JSON.stringify({mode:'signup', return:'<?php echo esc_js( home_url('/app/') ); ?>'}))
    });
    window.location.href = 'https://accounts.google.com/o/oauth2/auth?' + params.toString();
}
</script>
        <?php
        $this->auth_foot();
    }

    // ──────────────────────────────────────────────────────────────────────
    // FORGOT PASSWORD PAGE
    // ──────────────────────────────────────────────────────────────────────

    private function render_forgot_page() {
        $this->auth_head( 'Forgot Password' );
        ?>
<div class="auth-left">
    <div class="auth-brand">
        <div class="auth-brand-icon"><i class="fa-solid fa-dove"></i></div>
        <div class="auth-brand-name">MailToucan</div>
    </div>
    <div class="auth-panel-title">Don't worry — it happens to the best of us.</div>
    <p class="auth-panel-sub">Enter your email and we'll send you a reset link. It expires in 1 hour.</p>
</div>
<div class="auth-right">
    <div class="auth-form-wrap">
        <div id="screen_request">
            <div class="auth-form-title">Reset your password</div>
            <p class="auth-form-sub">Enter your account email to receive a reset link.</p>
            <div id="forgot_error"   class="auth-error-box"></div>
            <div id="forgot_success" class="auth-success-box"></div>
            <div class="auth-field">
                <label class="auth-label">Email Address</label>
                <input type="email" id="forgot_email" class="auth-input" placeholder="you@yourbusiness.com" onkeydown="if(event.key==='Enter')submitForgot()">
            </div>
            <button class="auth-submit" id="btn_forgot" onclick="submitForgot()">
                <i class="fa-solid fa-paper-plane"></i> Send Reset Link
            </button>
            <p style="text-align:center;margin-top:20px;font-size:13px;color:#6b7280;">
                <a href="<?php echo esc_url( home_url('/login/') ); ?>" class="auth-link">← Back to Sign In</a>
            </p>
        </div>
    </div>
</div>
<script>
function submitForgot() {
    const email = document.getElementById('forgot_email').value.trim();
    const errBox = document.getElementById('forgot_error');
    const sucBox = document.getElementById('forgot_success');
    const btn    = document.getElementById('btn_forgot');
    errBox.style.display = 'none';
    sucBox.style.display = 'none';

    if (!email) { errBox.textContent = 'Please enter your email.'; errBox.style.display = 'block'; return; }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Sending...';

    const fd = new FormData();
    fd.append('action',   'mt_forgot_password');
    fd.append('security', mt_nonce);
    fd.append('email',    email);

    fetch(mt_ajax, { method:'POST', body:fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            sucBox.textContent = 'If that email is registered, a reset link is on its way. Check your inbox.';
            sucBox.style.display = 'block';
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Email Sent';
        } else {
            errBox.textContent = d.data;
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Reset Link';
        }
    })
    .catch(() => {
        errBox.textContent = 'Network error. Please try again.';
        errBox.style.display = 'block';
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Reset Link';
    });
}
</script>
        <?php
        $this->auth_foot();
    }

    // ──────────────────────────────────────────────────────────────────────
    // RESET PASSWORD PAGE
    // ──────────────────────────────────────────────────────────────────────

    private function render_reset_page() {
        $this->auth_head( 'Set New Password' );
        $key   = sanitize_text_field( $_GET['key']   ?? '' );
        $login = sanitize_text_field( $_GET['login'] ?? '' );
        ?>
<div class="auth-left">
    <div class="auth-brand">
        <div class="auth-brand-icon"><i class="fa-solid fa-dove"></i></div>
        <div class="auth-brand-name">MailToucan</div>
    </div>
    <div class="auth-panel-title">Choose a strong new password.</div>
    <p class="auth-panel-sub">Your reset link is valid for 1 hour. After setting your new password, you can sign in immediately.</p>
</div>
<div class="auth-right">
    <div class="auth-form-wrap">
        <div class="auth-form-title">Set new password</div>
        <p class="auth-form-sub">Choose a strong password for your account.</p>
        <div id="reset_error"   class="auth-error-box"></div>
        <div id="reset_success" class="auth-success-box"></div>
        <input type="hidden" id="reset_key"   value="<?php echo esc_attr($key); ?>">
        <input type="hidden" id="reset_login" value="<?php echo esc_attr($login); ?>">
        <div class="auth-field">
            <label class="auth-label">New Password</label>
            <input type="password" id="reset_pass" class="auth-input" placeholder="Min 8 characters" oninput="checkPassStrength(this.value)">
            <div class="pass-strength-bar"><div class="pass-strength-fill" id="pass_fill"></div></div>
            <div style="font-size:11px;color:#9ca3af;margin-top:3px;" id="pass_hint"></div>
        </div>
        <div class="auth-field">
            <label class="auth-label">Confirm Password</label>
            <input type="password" id="reset_pass2" class="auth-input" placeholder="Repeat your password">
        </div>
        <button class="auth-submit" id="btn_reset" onclick="submitReset()">
            <i class="fa-solid fa-lock"></i> Set New Password
        </button>
    </div>
</div>
<script>
function checkPassStrength(val) {
    const fill = document.getElementById('pass_fill');
    const hint = document.getElementById('pass_hint');
    let score = 0;
    if (val.length >= 8) score++; if (val.length >= 12) score++;
    if (/[A-Z]/.test(val)) score++; if (/[0-9]/.test(val)) score++;
    if (/[^a-zA-Z0-9]/.test(val)) score++;
    const levels = [
        {pct:'20%',bg:'#ef4444',label:'Too short'},
        {pct:'40%',bg:'#f97316',label:'Weak'},
        {pct:'60%',bg:'#f59e0b',label:'Fair'},
        {pct:'80%',bg:'#22c55e',label:'Good'},
        {pct:'100%',bg:'#059669',label:'Strong 💪'}
    ];
    const l = levels[Math.max(0,score-1)] || levels[0];
    fill.style.width = val.length ? l.pct : '0';
    fill.style.background = l.bg;
    hint.textContent = val.length ? l.label : '';
}
function submitReset() {
    const pass  = document.getElementById('reset_pass').value;
    const pass2 = document.getElementById('reset_pass2').value;
    const key   = document.getElementById('reset_key').value;
    const login = document.getElementById('reset_login').value;
    const err   = document.getElementById('reset_error');
    const suc   = document.getElementById('reset_success');
    const btn   = document.getElementById('btn_reset');
    err.style.display = 'none'; suc.style.display = 'none';

    if (pass.length < 8) { err.textContent = 'Password must be at least 8 characters.'; err.style.display='block'; return; }
    if (pass !== pass2)  { err.textContent = 'Passwords do not match.'; err.style.display='block'; return; }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Updating...';

    const fd = new FormData();
    fd.append('action','mt_reset_password'); fd.append('security',mt_nonce);
    fd.append('key',key); fd.append('login',login); fd.append('password',pass);

    fetch(mt_ajax, {method:'POST',body:fd})
    .then(r=>r.json())
    .then(d => {
        if (d.success) {
            suc.textContent = 'Password updated! Redirecting to sign in...';
            suc.style.display = 'block';
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Done!';
            setTimeout(() => window.location.href = '<?php echo esc_js( home_url('/login/') ); ?>', 2000);
        } else {
            err.textContent = d.data;
            err.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-lock"></i> Set New Password';
        }
    });
}
</script>
        <?php
        $this->auth_foot();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX HANDLERS
    // ═══════════════════════════════════════════════════════════════════════

    public function ajax_register_tenant() {
        if ( ! check_ajax_referer( 'mt_auth_nonce', 'security', false ) ) {
            wp_send_json_error( 'Security token expired. Please refresh the page.' );
        }

        $email    = sanitize_email( $_POST['email'] ?? '' );
        $brand    = sanitize_text_field( $_POST['brand'] ?? '' );
        $plan     = sanitize_text_field( $_POST['plan'] ?? 'mt_starter' );
        $password = $_POST['password'] ?? '';

        // ── Validation ──
        if ( ! is_email( $email ) )   wp_send_json_error( 'Invalid email address.' );
        if ( empty( $brand ) )         wp_send_json_error( 'Business name is required.' );
        if ( strlen($password) < 8 )  wp_send_json_error( 'Password must be at least 8 characters.' );

        // Allowed plans (no free tier)
        $allowed_plans = [ 'mt_starter', 'mt_pro', 'mt_enterprise' ];
        if ( ! in_array( $plan, $allowed_plans ) ) $plan = 'mt_starter';

        // ── Check if email is already registered ──
        if ( email_exists( $email ) ) {
            wp_send_json_error( 'An account with this email already exists. <a href="' . esc_url( home_url('/login/') ) . '">Sign in instead?</a>' );
        }

        // ── Create WordPress user ──
        $username = sanitize_user( strtolower( explode('@', $email)[0] ) . '_' . wp_rand(100, 999) );
        $user_id  = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( 'Account creation failed: ' . $user_id->get_error_message() );
        }

        // Assign the MailToucan role (remove default Subscriber role)
        $user = new WP_User( $user_id );
        $user->set_role( $plan );

        // Store display name
        wp_update_user( [ 'ID' => $user_id, 'display_name' => $brand ] );

        // ── Get plan limits from packages table ──
        global $wpdb;
        $pkg_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mt_packages WHERE package_slug = %s", $plan
        ) );
        $email_limit    = $pkg_row ? intval( $pkg_row->email_limit )    : 1000;
        $location_limit = $pkg_row ? intval( $pkg_row->location_limit ) : 1;
        $storage_mb     = $pkg_row ? intval( $pkg_row->storage_limit_mb ) : 50;

        // ── Create brand record ──
        $wpdb->insert( $wpdb->prefix . 'mt_brands', [
            'brand_name'       => $brand,
            'primary_color'    => '#FCC753',
            'package_slug'     => $plan,
            'email_limit'      => $email_limit,
            'location_limit'   => $location_limit,
            'storage_limit_mb' => $storage_mb,
        ] );
        $brand_id = $wpdb->insert_id;

        if ( ! $brand_id ) {
            // Rollback user if brand creation fails
            wp_delete_user( $user_id );
            wp_send_json_error( 'Failed to create brand environment. Please contact support.' );
        }

        // ── Link user to brand ──
        update_user_meta( $user_id, 'mt_brand_id', $brand_id );

        // ── Auto-login ──
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, true );

        // ── Fire action for welcome email / hooks ──
        do_action( 'mt_tenant_registered', $user_id, $brand_id, $plan );

        wp_send_json_success( [
            'message'  => 'Account created successfully.',
            'redirect' => home_url( '/app/' ),
        ] );
    }

    public function ajax_login_tenant() {
        if ( ! check_ajax_referer( 'mt_auth_nonce', 'security', false ) ) {
            wp_send_json_error( 'Security token expired.' );
        }

        $email    = sanitize_email( $_POST['email'] ?? '' );
        $password = $_POST['password'] ?? '';

        if ( ! $email || ! $password ) {
            wp_send_json_error( 'Please enter your email and password.' );
        }

        // Find user by email
        $user = get_user_by( 'email', $email );
        if ( ! $user ) {
            wp_send_json_error( 'No account found with that email address.' );
        }

        // Authenticate
        $result = wp_authenticate( $user->user_login, $password );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( 'Incorrect password. Please try again or reset your password.' );
        }

        // Check they have app access
        if ( ! in_array( 'mt_starter', (array)$result->roles )
          && ! in_array( 'mt_pro', (array)$result->roles )
          && ! in_array( 'mt_enterprise', (array)$result->roles )
          && ! user_can( $result, 'manage_options' ) ) {
            wp_send_json_error( 'This account does not have access to the MailToucan dashboard.' );
        }

        // Log in
        wp_set_current_user( $result->ID );
        wp_set_auth_cookie( $result->ID, true );

        // Honour redirect_to if it's a safe local URL, otherwise default to /app/
        $redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
        $safe_redirect = ( $redirect_to && wp_validate_redirect( $redirect_to, false ) )
            ? $redirect_to
            : home_url( '/app/' );

        wp_send_json_success( [
            'message'  => 'Login successful.',
            'redirect' => $safe_redirect,
        ] );
    }

    public function ajax_forgot_password() {
        if ( ! check_ajax_referer( 'mt_auth_nonce', 'security', false ) ) {
            wp_send_json_error( 'Security token expired.' );
        }

        $email = sanitize_email( $_POST['email'] ?? '' );
        if ( ! is_email( $email ) ) {
            wp_send_json_error( 'Please enter a valid email address.' );
        }

        // Always return success to prevent email enumeration
        $user = get_user_by( 'email', $email );
        if ( $user ) {
            // Generate reset key using WP's built-in system
            $reset_key = get_password_reset_key( $user );
            if ( ! is_wp_error( $reset_key ) ) {
                $reset_url = home_url( '/reset-password/?key=' . rawurlencode( $reset_key ) . '&login=' . rawurlencode( $user->user_login ) );

                $subject = 'Reset your MailToucan password';
                $message = "Hi " . $user->display_name . ",\r\n\r\n"
                    . "Someone requested a password reset for your MailToucan account.\r\n\r\n"
                    . "Click the link below to set a new password (expires in 1 hour):\r\n\r\n"
                    . $reset_url . "\r\n\r\n"
                    . "If you didn't request this, you can ignore this email.\r\n\r\n"
                    . "— The MailToucan Team";

                wp_mail( $email, $subject, $message );
            }
        }

        wp_send_json_success( 'If that email is registered, a reset link is on its way.' );
    }

    public function ajax_reset_password() {
        if ( ! check_ajax_referer( 'mt_auth_nonce', 'security', false ) ) {
            wp_send_json_error( 'Security token expired.' );
        }

        $key      = sanitize_text_field( $_POST['key']   ?? '' );
        $login    = sanitize_text_field( $_POST['login']  ?? '' );
        $password = $_POST['password'] ?? '';

        if ( ! $key || ! $login )         wp_send_json_error( 'Invalid reset link. Please request a new one.' );
        if ( strlen($password) < 8 )      wp_send_json_error( 'Password must be at least 8 characters.' );

        $user = check_password_reset_key( $key, $login );
        if ( is_wp_error( $user ) ) {
            wp_send_json_error( 'This reset link has expired or is invalid. Please request a new one.' );
        }

        reset_password( $user, $password );

        wp_send_json_success( 'Password updated successfully. You can now sign in.' );
    }
}
