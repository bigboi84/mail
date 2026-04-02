<?php
/**
 * MailToucan Core - The Traffic Cop
 * Loads isolated modules securely.
 */

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

        if ( is_admin() ) {
            $super_admin = new MT_SuperAdmin();
            $super_admin->init();
        }
    }
}