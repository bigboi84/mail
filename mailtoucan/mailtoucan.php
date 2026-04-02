<?php
/**
 * Plugin Name: MailToucan Pro
 * Plugin URI: https://mailtoucan.pro
 * Description: AI-Powered Email Marketing & Social WiFi SaaS.
 * Version: 1.0.0
 * Author: Sling
 * Text Domain: mailtoucan
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Define Constants for easy migration to RackNerd later
define( 'MT_VERSION', '1.0.0' );
define( 'MT_PATH', plugin_dir_path( __FILE__ ) );
define( 'MT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Activation Logic: Creates the High-Performance "Roost" Tables
 */
function activate_mailtoucan() {
    require_once MT_PATH . 'includes/class-mt-activator.php';
    MT_Activator::activate();
}
register_activation_hook( __FILE__, 'activate_mailtoucan' );

/**
 * Boot up the MailToucan Core Engine
 */
require_once MT_PATH . 'includes/class-mt-core.php';
function run_mailtoucan() {
    $plugin = new MT_Core();
}
run_mailtoucan();