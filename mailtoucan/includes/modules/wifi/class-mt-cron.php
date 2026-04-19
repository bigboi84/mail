<?php
/**
 * MailToucan Enterprise Cron Engine
 * Handles Nightly RADIUS purges and CRM Trash Sweeping securely.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MT_Cron_Controller {

    public function init() {
        add_action('mt_nightly_radius_purge', array($this, 'execute_radius_purge'));
        add_action('mt_nightly_radius_purge', array($this, 'clean_old_trash'));

        if (!wp_next_scheduled('mt_nightly_radius_purge')) {
            $timestamp = strtotime('03:00:00');
            if ($timestamp < time()) {
                $timestamp += DAY_IN_SECONDS; 
            }
            wp_schedule_event($timestamp, 'daily', 'mt_nightly_radius_purge');
        }

        add_action('wp_ajax_mt_test_radius_purge', array($this, 'manual_purge_test'));
    }

    public function execute_radius_purge() {
        global $wpdb;
        $db_host = get_option('mt_radius_host', '107.173.49.14');
        $db_user = get_option('mt_radius_user', 'mt_radius');
        $db_pass = get_option('mt_radius_pass', '');

        if (empty($db_pass)) {
            error_log('MailToucan RADIUS Purge Skipped: No password configured in settings.');
            return false;
        }

        try {
            $pdo = new PDO("mysql:host={$db_host};dbname=radius;port=3306;charset=utf8mb4", $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 10,
            ]);

            // AUDIT FIX: Only wipe RADIUS entries for guests whose last connection was over 24 hours ago
            $table_leads = $wpdb->prefix . 'mt_guest_leads';
            $expired_leads = $wpdb->get_results("SELECT guest_mac FROM $table_leads WHERE last_visit < NOW() - INTERVAL 1 DAY");

            if ($expired_leads) {
                $stmt_chk = $pdo->prepare("DELETE FROM radcheck WHERE username = ?");
                $stmt_rep = $pdo->prepare("DELETE FROM radreply WHERE username = ?");

                foreach ($expired_leads as $l) {
                    if (empty($l->guest_mac) || $l->guest_mac === 'UNKNOWN') continue;
                    
                    $mac_clean = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $l->guest_mac));
                    if (strlen($mac_clean) !== 12) continue;

                    $mac_colon = implode(':', str_split($mac_clean, 2));
                    $mac_dash = implode('-', str_split($mac_clean, 2));

                    $stmt_chk->execute([$mac_colon]); 
                    $stmt_rep->execute([$mac_colon]);
                    $stmt_chk->execute([$mac_dash]); 
                    $stmt_rep->execute([$mac_dash]);
                }
            }

            error_log('MailToucan: Safe Nightly RADIUS Purge executed successfully.');
            return true;

        } catch (PDOException $e) {
            error_log('MailToucan RADIUS Purge Failed: ' . $e->getMessage());
            return false;
        }
    }

    // AUDIT FIX: Moved the heavy CRM Trash Sweeper to run securely on the nightly cron
    public function clean_old_trash() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}mt_guest_leads WHERE status = 'trashed' AND deleted_at < NOW() - INTERVAL 30 DAY");
    }

    public function manual_purge_test() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $result = $this->execute_radius_purge();
        
        if ($result) {
            wp_send_json_success('Expired RADIUS sessions wiped successfully! Long sessions were preserved.');
        } else {
            wp_send_json_error('Failed to connect to RADIUS. Check your API settings.');
        }
    }
}
// AUDIT FIX: Removed double instantiation at the bottom of the file