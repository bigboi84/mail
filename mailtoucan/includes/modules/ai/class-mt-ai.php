<?php
/**
 * Toucan AI Engine
 * Centralized Brain for AI Generation, Credit Tracking, and API Routing.
 */
class MT_AI_Engine {

    public function init() {
        // We will add the AJAX listeners here for when the UI requests AI content
        add_action( 'wp_ajax_mt_generate_ai_copy', array( $this, 'ajax_generate_copy' ) );
    }

    /**
     * The Master AI Generation Function (Placeholder for Gemini/Claude API)
     */
    public function generate_text( $prompt, $brand_id, $context = 'email_copy' ) {
        // 1. Verify Credit Limit
        if ( ! $this->has_credits( $brand_id ) ) {
            return new WP_Error( 'no_credits', 'AI Credit limit reached. Please upgrade your tier.' );
        }

        // 2. Route to AI Provider (Gemini / Claude)
        // [API LOGIC GOES HERE IN PHASE 4]
        $simulated_response = "Here is some magical AI-generated text for: " . sanitize_text_field($prompt);

        // 3. Deduct Credit
        $this->deduct_credit( $brand_id );

        return $simulated_response;
    }

    private function has_credits( $brand_id ) {
        // Future Logic: Check the mt_subscriptions table for their tier limit
        return true; 
    }

    private function deduct_credit( $brand_id ) {
        // Future Logic: Log the usage and deduct 1 credit from their monthly pool
    }

    public function ajax_generate_copy() {
        if ( ! check_ajax_referer( 'mt_app_nonce', 'security', false ) ) wp_send_json_error( 'Security Token Expired.' );
        
        $prompt = sanitize_text_field($_POST['prompt']);
        $user_id = get_current_user_id();
        $brand_id = get_user_meta( $user_id, 'mt_brand_id', true ) ?: 1;

        $response = $this->generate_text( $prompt, $brand_id );
        
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }
        wp_send_json_success( $response );
    }
}

// Instantiate the Brain
$mt_ai = new MT_AI_Engine();
$mt_ai->init();