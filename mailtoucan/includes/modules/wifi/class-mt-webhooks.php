<?php
/**
 * MailToucan API & Webhooks Engine
 * Handles lightweight inbound server-to-server traffic (RADIUS Heartbeats)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class MT_Webhooks {

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_api_routes' ) );
    }

    public function register_api_routes() {
        register_rest_route( 'mt/v1', '/heartbeat', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'process_router_heartbeat' ),
            'permission_callback' => '__return_true', 
        ) );
    }

    public function process_router_heartbeat( WP_REST_Request $request ) {
        $mac = sanitize_text_field( $request->get_param( 'mac' ) );

        if ( empty( $mac ) ) {
            return new WP_Error( 'missing_mac', 'MAC Address Required', array( 'status' => 400 ) );
        }

        $clean_mac = strtoupper( preg_replace( '/[^a-fA-F0-9]/', '', $mac ) );
        if ( strlen( $clean_mac ) === 12 ) {
            $clean_mac = implode( ':', str_split( $clean_mac, 2 ) );
        }

        // Fast RAM Cache
        $transient_key = 'mt_ping_' . md5( $clean_mac );
        set_transient( $transient_key, time(), 24 * HOUR_IN_SECONDS );

        // Auto-Recovery
        $alert_key = 'mt_offline_alert_' . md5( $clean_mac );
        if ( get_transient( $alert_key ) ) {
            delete_transient( $alert_key );
            do_action('mt_router_recovered', $clean_mac);
        }

        return rest_ensure_response( array(
            'success'   => true,
            'message'   => 'Heartbeat successfully logged for ' . $clean_mac,
            'timestamp' => time()
        ) );
    }
}