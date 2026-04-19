<?php
/**
 * MailToucan Autopilot Engine
 * Scans the CRM for Birthdays, Win-backs, and listens for instant WiFi connections.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MT_Workflows {

    public function init() {
        add_action( 'mt_lead_captured', array( $this, 'process_instant_triggers' ), 10, 2 );

        if ( ! wp_next_scheduled( 'mt_daily_workflow_scanner_hook' ) ) {
            $timestamp = strtotime('08:00:00');
            if ($timestamp < time()) $timestamp += 86400; 
            wp_schedule_event( $timestamp, 'daily', 'mt_daily_workflow_scanner_hook' );
        }
        add_action( 'mt_daily_workflow_scanner_hook', array( $this, 'run_daily_scanners' ) );

        // Register the 5-minute queue worker
        add_filter( 'cron_schedules', array( $this, 'add_cron_intervals' ) );
        if ( ! wp_next_scheduled( 'mt_process_email_queue_hook' ) ) {
            wp_schedule_event( time(), 'every_five_minutes', 'mt_process_email_queue_hook' );
        }
        add_action( 'mt_process_email_queue_hook', array( $this, 'process_queue' ) );
    }

    public function add_cron_intervals( $schedules ) {
        $schedules['every_five_minutes'] = array(
            'interval' => 300,
            'display'  => esc_html__( 'Every Five Minutes' ),
        );
        return $schedules;
    }

    public function process_instant_triggers( $lead_id, $brand_id ) {
        global $wpdb;
        $campaigns_table = $wpdb->prefix . 'mt_campaigns';
        $leads_table = $wpdb->prefix . 'mt_guest_leads';

        $lead = $wpdb->get_row( $wpdb->prepare("SELECT store_id, campaign_tag FROM $leads_table WHERE id = %d", $lead_id) );
        if ( ! $lead ) return;

        $workflows = $wpdb->get_results( $wpdb->prepare("SELECT id, config_json FROM $campaigns_table WHERE brand_id = %d AND campaign_type = 'workflow'", $brand_id) );

        foreach ( $workflows as $wf ) {
            $config = json_decode($wf->config_json, true) ?: [];
            $trigger = $config['trigger_type'] ?? '';
            $location_id = $config['location_id'] ?? 'all';
            
            if ( $location_id !== 'all' && intval($location_id) !== intval($lead->store_id) ) continue;

            $should_queue = false;

            if ( $trigger === 'first_visit' ) {
                $should_queue = true;
            }
            
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

    public function run_daily_scanners() {
        global $wpdb;
        $brands = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}mt_brands" );
        $campaigns_table = $wpdb->prefix . 'mt_campaigns';
        $leads_table = $wpdb->prefix . 'mt_guest_leads';

        foreach ( $brands as $brand_id ) {
            $workflows = $wpdb->get_results( $wpdb->prepare("SELECT id, config_json FROM $campaigns_table WHERE brand_id = %d AND campaign_type = 'workflow'", $brand_id) );

            foreach ( $workflows as $wf ) {
                $config = json_decode($wf->config_json, true) ?: [];
                $trigger = $config['trigger_type'] ?? '';
                $location_id = $config['location_id'] ?? 'all';

                if ( $trigger === 'birthday' ) {
                    $target_date_sql = "NOW()";
                    if ( isset($config['delay_unit']) && $config['delay_unit'] === 'days_before' ) {
                        $target_date_sql = "DATE_ADD(NOW(), INTERVAL " . intval($config['delay_val']) . " DAY)";
                    }

                    $query = "SELECT id FROM $leads_table WHERE brand_id = %d AND status = 'active' AND MONTH(birthday) = MONTH($target_date_sql) AND DAY(birthday) = DAY($target_date_sql)";
                    $params = [ $brand_id ];

                    if ( $location_id !== 'all' ) {
                        $query .= " AND store_id = %d";
                        $params[] = intval($location_id);
                    }

                    $birthday_leads = $wpdb->get_col( $wpdb->prepare($query, ...$params) );
                    foreach ( $birthday_leads as $lead_id ) {
                        if ( ! $this->has_been_queued_recently( $wf->id, $lead_id, 300 ) ) {
                            $this->add_to_queue( $brand_id, $wf->id, $lead_id, $config, true );
                        }
                    }
                }

                if ( $trigger === 'winback' ) {
                    // Win-back relies strictly on the actual last visit, ensuring accurate missing duration
                    $days_missing = 30; 
                    $query = "SELECT id FROM $leads_table WHERE brand_id = %d AND status = 'active' AND last_visit <= DATE_SUB(NOW(), INTERVAL %d DAY) AND last_visit IS NOT NULL";
                    $params = [ $brand_id, $days_missing ];

                    if ( $location_id !== 'all' ) {
                        $query .= " AND store_id = %d";
                        $params[] = intval($location_id);
                    }

                    $missing_leads = $wpdb->get_col( $wpdb->prepare($query, ...$params) );
                    foreach ( $missing_leads as $lead_id ) {
                        if ( ! $this->has_been_queued_recently( $wf->id, $lead_id, 9999 ) ) {
                            $this->add_to_queue( $brand_id, $wf->id, $lead_id, $config );
                        }
                    }
                }
            }
        }
    }

    private function add_to_queue( $brand_id, $campaign_id, $lead_id, $config, $is_birthday_scan = false ) {
        global $wpdb;
        $queue_table = $wpdb->prefix . 'mt_email_queue';
        $leads_table = $wpdb->prefix . 'mt_guest_leads';

        $lead_email = $wpdb->get_var( $wpdb->prepare("SELECT email FROM $leads_table WHERE id = %d", $lead_id) );
        if ( ! $lead_email ) return;

        $delay_val = intval($config['delay_val'] ?? 0);
        $delay_unit = $config['delay_unit'] ?? 'minutes';

        $send_after = current_time('mysql'); 

        // Since birthdays are fetched proactively by the exact calculated day by the scanner, no further offset is needed here.
        if ( $delay_val > 0 && ! $is_birthday_scan ) {
            if ( $delay_unit === 'minutes' ) {
                $send_after = date('Y-m-d H:i:s', strtotime("+$delay_val minutes", current_time('timestamp')));
            } elseif ( $delay_unit === 'hours' ) {
                $send_after = date('Y-m-d H:i:s', strtotime("+$delay_val hours", current_time('timestamp')));
            } elseif ( $delay_unit === 'days' ) {
                $send_after = date('Y-m-d H:i:s', strtotime("+$delay_val days", current_time('timestamp')));
            }
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

    public function process_queue() {
        global $wpdb;
        $queue_table     = $wpdb->prefix . 'mt_email_queue';
        $leads_table     = $wpdb->prefix . 'mt_guest_leads';
        $templates_table = $wpdb->prefix . 'mt_email_templates';
        $campaigns_table = $wpdb->prefix . 'mt_campaigns';
        
        $pending = $wpdb->get_results("SELECT * FROM $queue_table WHERE status = 'pending' AND send_after <= NOW() LIMIT 50");
        if ( empty($pending) ) return;

        $email_engine = new MT_Email();

        foreach ( $pending as $job ) {
            $campaign = $wpdb->get_row($wpdb->prepare("SELECT config_json, campaign_name FROM $campaigns_table WHERE id = %d", $job->campaign_id));
            if ( ! $campaign ) {
                $wpdb->update($queue_table, ['status' => 'failed'], ['id' => $job->id]);
                continue;
            }
            $config = json_decode($campaign->config_json, true) ?: [];
            $template_id = $config['template_id'] ?? 0;
            $subject = $config['subject'] ?? $campaign->campaign_name;

            $template = $wpdb->get_row($wpdb->prepare("SELECT email_body, email_subject FROM $templates_table WHERE id = %d", $template_id));
            if ( ! $template ) {
                $wpdb->update($queue_table, ['status' => 'failed'], ['id' => $job->id]);
                continue;
            }
            
            if( empty($subject) || $subject === $campaign->campaign_name ) $subject = $template->email_subject;

            $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $leads_table WHERE id = %d", $job->lead_id), ARRAY_A);
            if ( ! $lead || in_array($lead['status'], ['unsubscribed', 'trashed', 'deleted']) ) {
                $wpdb->update($queue_table, ['status' => 'skipped'], ['id' => $job->id]);
                continue;
            }

            $brand_name = $wpdb->get_var($wpdb->prepare("SELECT brand_name FROM {$wpdb->prefix}mt_brands WHERE id = %d", $job->brand_id));
            $store_name = $wpdb->get_var($wpdb->prepare("SELECT store_name FROM {$wpdb->prefix}mt_stores WHERE id = %d", $lead['store_id']));

            $html = $email_engine->parse_tags($template->email_body, $lead, $brand_name, $store_name ?: 'HQ');
            
            $result = $email_engine->route_email($job->to_email, $subject, $html, $job->brand_id, 'bulk');
            
            if ( $result === true ) {
                $wpdb->update($queue_table, ['status' => 'sent', 'sent_at' => current_time('mysql')], ['id' => $job->id]);
                $wpdb->insert($wpdb->prefix . 'mt_email_sends', [
                    'brand_id'    => $job->brand_id,
                    'campaign_id' => $job->campaign_id,
                    'lead_id'     => $job->lead_id,
                    'sent_at'     => current_time('mysql')
                ]);
            } else {
                $wpdb->update($queue_table, ['status' => 'failed'], ['id' => $job->id]);
            }
        }
    }
}