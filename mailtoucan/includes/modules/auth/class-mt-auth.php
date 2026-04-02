<?php
/**
 * The Auth Module: Login Redirection & WP-Admin Blocking
 */
class MT_Auth {

    public function init() {
        // Redirect upon login
        add_filter( 'login_redirect', array( $this, 'redirect_to_app' ), 10, 3 );
        
        // Block wp-admin access
        add_action( 'admin_init', array( $this, 'block_wp_admin' ) );
        
        // Hide the admin bar on the frontend for clients
        add_action( 'after_setup_theme', array( $this, 'hide_admin_bar' ) );
    }

    public function redirect_to_app( $redirect_to, $request, $user ) {
        // Is there a user to check?
        if ( isset( $user->roles ) && is_array( $user->roles ) ) {
            // Check if they hold any of our SaaS roles
            if ( in_array( 'mt_starter', $user->roles ) || in_array( 'mt_pro', $user->roles ) || in_array( 'mt_enterprise', $user->roles ) ) {
                return home_url( '/app/' ); // Send them to the clean UI
            }
        }
        return $redirect_to; // Let the Super Admin go to wp-admin
    }

    public function block_wp_admin() {
        // Allow AJAX calls to pass through
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }

        // If the user does not have Super Admin 'manage_options', kick them to the app
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
}