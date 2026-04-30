<?php
/**
 * MailToucan Core - The Traffic Cop
 * Loads isolated modules securely.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MT_Core {
    public function __construct() {
        $this->load_dependencies();
        $this->init_modules();
    }

    private function load_dependencies() {
        // Frontend & App Modules
        require_once MT_PATH . 'includes/modules/wifi/class-mt-wifi-controller.php';
        require_once MT_PATH . 'includes/modules/auth/class-mt-auth.php';
        require_once MT_PATH . 'includes/modules/dashboard/class-mt-dashboard.php';
        
        // Automated Background Tasks (Cron) - Resolves Blocker B-1
        require_once MT_PATH . 'includes/modules/wifi/class-mt-cron.php';
        
        // AI Engine — OpenAI / Gemini / Claude
        require_once MT_PATH . 'includes/modules/ai/class-mt-ai.php';

        // Email & Automation Engines
        require_once MT_PATH . 'includes/modules/email/class-mt-email.php';
        require_once MT_PATH . 'includes/modules/email/class-mt-templates.php';
        require_once MT_PATH . 'includes/modules/email/class-mt-workflows.php';
        require_once MT_PATH . 'includes/modules/email/class-mt-unsubscribe.php'; // Unsubscribe page + CRM status update
        
        // Super Admin Backend Module
        if ( is_admin() ) {
            require_once MT_PATH . 'includes/admin/class-mt-superadmin.php';
        }
    }

    private function init_modules() {
        $wifi_engine = new MT_Wifi_Controller();
        $wifi_engine->init();

        $auth_engine = new MT_Auth();
        $auth_engine->init();

        $dashboard_engine = new MT_Dashboard();
        $dashboard_engine->init();

        // Boot the Cron Controller so 3 AM wipes and trash sweeps actually run
        $cron_engine = new MT_Cron_Controller();
        $cron_engine->init();

        $email_engine = new MT_Email();
        $email_engine->maybe_create_email_tables(); // Ensures tracking tables exist

        $templates_engine = new MT_Templates();
        $templates_engine->init();

        $workflow_engine = new MT_Workflows();
        $workflow_engine->init();

        if ( is_admin() ) {
            $super_admin = new MT_SuperAdmin();
            $super_admin->init();
        }
    }
}