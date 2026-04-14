<?php
/**
 * MailToucan Enterprise Cron Engine
 * Handles Nightly RADIUS purges to force the "Welcome Back" CRM flow.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MT_Cron_Controller {

    public function init() {
        // 1. Register the automated nightly hook
        add_action('mt_nightly_radius_purge', array($this, 'execute_radius_purge'));

        // 2. Schedule the event if it isn't already scheduled
        if (!wp_next_scheduled('mt_nightly_radius_purge')) {
            // Calculate the next 3:00 AM
            $timestamp = strtotime('03:00:00');
            if ($timestamp < time()) {
                $timestamp += DAY_IN_SECONDS; // Move to tomorrow if 3 AM already passed today
            }
            wp_schedule_event($timestamp, 'daily', 'mt_nightly_radius_purge');
        }

        // 3. Manual trigger for your Dashboard Testing
        add_action('wp_ajax_mt_test_radius_purge', array($this, 'manual_purge_test'));
    }

    public function execute_radius_purge() {
        try {
            // Connect to your RackNerd FreeRADIUS Database
            $pdo = new PDO(
                'mysql:host=107.173.49.14;dbname=radius;port=3306;charset=utf8mb4',
                'mt_radius',
                'JLAmX7sPoWffb7N3GVcp',
                [
                    PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT    => 10,
                ]
            );

            // Wipe the active session tables to force the Splash Screen tomorrow
            $pdo->exec('TRUNCATE TABLE radcheck');
            $pdo->exec('TRUNCATE TABLE radreply');

            // Log success silently in the background
            error_log('MailToucan: Nightly RADIUS Purge executed successfully at ' . current_time('mysql'));
            return true;

        } catch (PDOException $e) {
            error_log('MailToucan RADIUS Purge Failed: ' . $e->getMessage());
            return false;
        }
    }

    public function manual_purge_test() {
        // Security check: Only admins can trigger this manually
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $result = $this->execute_radius_purge();
        
        if ($result) {
            wp_send_json_success('RADIUS memory wiped successfully! The splash screen will now pop up for all users.');
        } else {
            wp_send_json_error('Failed to connect to RADIUS. Check your server firewall or error logs.');
        }
    }
}

$mt_cron = new MT_Cron_Controller();
$mt_cron->init();