<?php
/**
 * MailToucan Autopilot Engine
 * Scans the CRM for Birthdays, Win-backs, and listens for instant WiFi connections.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MT_Workflows {

    public function init() {
        // 1. Listen for Instant Triggers (WiFi Connects & vCard Scans)
        add_action( 'mt_lead_captured', array( $this, 'process_instant_triggers' ), 10, 2 );

        // 2. Register the Daily Morning Scanner (runs once a day)
        if ( ! wp_next_scheduled( 'mt_daily_workflow_scanner_hook' ) ) {
            // Schedule it to run daily at 8:00 AM local time
            $timestamp = strtotime('08:00:00');
            if ($timestamp < time()) $timestamp += 86400; // If 8am passed, set for tomorrow
            wp_schedule_event( $timestamp, 'daily', 'mt_daily_workflow_scanner_hook' );
        }
        add_action( 'mt_daily_workflow_scanner_hook', array( $this, 'run_daily_scanners' ) );
    }

    /**
     * Executes the moment a user logs into the WiFi or scans a vCard.
     * @param int $lead_id The ID of the newly captured guest
     * @param int $brand_id The Brand ID
     */
    public function process_instant_triggers( $lead_id, $brand_id ) {
        global $wpdb;
        $campaigns_table = $wpdb->prefix . 'mt_campaigns';
        $leads_table = $wpdb->prefix . 'mt_guest_leads';

        // Get the lead's details (Location and Tag)
        $lead = $wpdb->get_row( $wpdb->prepare("SELECT store_id, campaign_tag FROM $leads_table WHERE id = %d", $lead_id) );
        if ( ! $lead ) return;

        // Fetch all ACTIVE workflows for this brand
        $workflows = $wpdb->get_results( $wpdb->prepare("SELECT id, config_json FROM $campaigns_table WHERE brand_id = %d AND campaign_type = 'workflow'", $brand_id) );

        foreach ( $workflows as $wf ) {
            $config = json_decode($wf->config_json, true) ?: [];
            $trigger = $config['trigger_type'] ?? '';
            $location_id = $config['location_id'] ?? 'all';
            
            // Location Filter Check: Skip if this workflow is for a different store
            if ( $location_id !== 'all' && intval($location_id) !== intval($lead->store_id) ) continue;

            $should_queue = false;

            // Scenario 1: First Time Connect
            if ( $trigger === 'first_visit' ) {
                $should_queue = true;
            }
            
            // Scenario 2: Specific Campaign Tag (vCard)
            if ( $trigger === 'tag' && !empty($config['audience_tag']) ) {
                if ( strtolower($config['audience_tag']) === strtolower($lead->campaign_tag) ) {
                    $should_queue = true;
                }
            }

            if ( $should_queue ) {
                $this->add_to_queue( $brand_id, $wf->id, $lead_id, $config );
            }
        }
    }

    /**
     * The Morning Robot: Runs at 8:00 AM every day to sweep the CRM.
     */
    public function run_daily_scanners() {
        global $wpdb;
        $brands = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}mt_brands" );
        $campaigns_table = $wpdb->prefix . 'mt_campaigns';
        $leads_table = $wpdb->prefix . 'mt_guest_leads';

        foreach ( $brands as $brand_id ) {
            // Fetch active daily workflows for this brand (Birthdays & Winbacks)
            $workflows = $wpdb->get_results( $wpdb->prepare("SELECT id, config_json FROM $campaigns_table WHERE brand_id = %d AND campaign_type = 'workflow'", $brand_id) );

            foreach ( $workflows as $wf ) {
                $config = json_decode($wf->config_json, true) ?: [];
                $trigger = $config['trigger_type'] ?? '';
                $location_id = $config['location_id'] ?? 'all';

                if ( $trigger === 'birthday' ) {
                    // Find guests whose birthday is today (Month and Day match)
                    $query = "SELECT id FROM $leads_table WHERE brand_id = %d AND status = 'active' AND MONTH(birthday) = MONTH(NOW()) AND DAY(birthday) = DAY(NOW())";
                    $params = [ $brand_id ];

                    if ( $location_id !== 'all' ) {
                        $query .= " AND store_id = %d";
                        $params[] = intval($location_id);
                    }

                    $birthday_leads = $wpdb->get_col( $wpdb->prepare($query, ...$params) );
                    foreach ( $birthday_leads as $lead_id ) {
                        // Ensure we haven't already sent them a birthday email this year
                        if ( ! $this->has_been_queued_recently( $wf->id, $lead_id, 300 ) ) {
                            $this->add_to_queue( $brand_id, $wf->id, $lead_id, $config );
                        }
                    }
                }

                if ( $trigger === 'winback' ) {
                    // Win-back: e.g., haven't been seen in 30 days
                    // In Phase 3, this will check 'last_login', but for now we use 'created_at' as a baseline
                    $days_missing = 30; // Default threshold
                    $query = "SELECT id FROM $leads_table WHERE brand_id = %d AND status = 'active' AND created_at <= DATE_SUB(NOW(), INTERVAL %d DAY)";
                    $params = [ $brand_id, $days_missing ];

                    if ( $location_id !== 'all' ) {
                        $query .= " AND store_id = %d";
                        $params[] = intval($location_id);
                    }

                    $missing_leads = $wpdb->get_col( $wpdb->prepare($query, ...$params) );
                    foreach ( $missing_leads as $lead_id ) {
                        // Ensure we only send the win-back email ONCE per workflow
                        if ( ! $this->has_been_queued_recently( $wf->id, $lead_id, 9999 ) ) {
                            $this->add_to_queue( $brand_id, $wf->id, $lead_id, $config );
                        }
                    }
                }
            }
        }
    }

    /**
     * Calculates the delay and drops the payload into the Queue table.
     */
    private function add_to_queue( $brand_id, $campaign_id, $lead_id, $config ) {
        global $wpdb;
        $queue_table = $wpdb->prefix . 'mt_email_queue';
        $leads_table = $wpdb->prefix . 'mt_guest_leads';

        $lead_email = $wpdb->get_var( $wpdb->prepare("SELECT email FROM $leads_table WHERE id = %d", $lead_id) );
        if ( ! $lead_email ) return;

        // Calculate Delay
        $delay_val = intval($config['delay_val'] ?? 0);
        $delay_unit = $config['delay_unit'] ?? 'minutes';

        $send_after = current_time('mysql'); // Default: Send immediately

        if ( $delay_val > 0 ) {
            if ( $delay_unit === 'minutes' ) {
                $send_after = date('Y-m-d H:i:s', strtotime("+$delay_val minutes", current_time('timestamp')));
            } elseif ( $delay_unit === 'hours' ) {
                $send_after = date('Y-m-d H:i:s', strtotime("+$delay_val hours", current_time('timestamp')));
            } elseif ( $delay_unit === 'days' ) {
                $send_after = date('Y-m-d H:i:s', strtotime("+$delay_val days", current_time('timestamp')));
            }
            // Note: 'days_before' logic for birthdays requires advanced parsing, skipping for MVP
        }

        $wpdb->insert( $queue_table, array(
            'brand_id'    => $brand_id,
            'campaign_id' => $campaign_id,
            'lead_id'     => $lead_id,
            'to_email'    => $lead_email,
            'status'      => 'pending',
            'send_after'  => $send_after
        ) );
    }

    /**
     * Prevents spamming. Checks if a lead was already added to the queue for a specific workflow within X days.
     */
    private function has_been_queued_recently( $campaign_id, $lead_id, $days = 300 ) {
        global $wpdb;
        $queue_table = $wpdb->prefix . 'mt_email_queue';
        
        $recent = $wpdb->get_var( $wpdb->prepare("
            SELECT id FROM $queue_table 
            WHERE campaign_id = %d AND lead_id = %d 
            AND send_after >= DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $campaign_id, $lead_id, $days) );

        return $recent ? true : false;
    }
}