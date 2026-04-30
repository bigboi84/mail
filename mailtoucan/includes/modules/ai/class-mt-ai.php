<?php
/**
 * Toucan AI Engine v2.0
 * ─────────────────────────────────────────────────────────────────────────────
 * Routes AI calls through OpenAI, Gemini, or Claude.
 * Enforces per-section monthly credit limits configurable per package.
 * Supports tenant own-key mode (bypass platform credits).
 *
 * Sections:
 *   email_studio      — Email body copy in Studio
 *   campaign_subject  — Subject line / preview text in Campaigns
 *   crm_advisor       — CRM segment analysis & recommendations
 *   automation        — Workflow / automation email body generation
 *   brand_autofill    — Brand page Auto-Fill from website URL
 *   splash_copy       — Splash page headline + CTA copy
 *   help_chat         — In-dashboard help chatbot
 *   onboarding        — Onboarding wizard (always platform, not tenant-gated)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MT_AI_Engine {

    // -------------------------------------------------------------------------
    // SECTION DEFINITIONS  (used for limit labels in super admin)
    // -------------------------------------------------------------------------
    public static function get_sections() {
        return [
            'email_studio'     => 'Email Studio Copy',
            'campaign_subject' => 'Campaign Subject & Preview',
            'crm_advisor'      => 'CRM Segment Advisor',
            'automation'       => 'Automation Email Body',
            'brand_autofill'   => 'Brand Auto-Fill',
            'splash_copy'      => 'Splash Page Copy',
            'help_chat'        => 'Help Chatbot',
        ];
    }

    // Default limits (used when package has no override)
    public static function get_default_limits() {
        return [
            'email_studio'     => 5,
            'campaign_subject' => 5,
            'crm_advisor'      => 5,
            'automation'       => 5,
            'brand_autofill'   => 3,
            'splash_copy'      => 3,
            'help_chat'        => 10,
        ];
    }

    // -------------------------------------------------------------------------
    // INIT
    // -------------------------------------------------------------------------
    public function init() {
        $this->maybe_create_ai_table();
        $this->maybe_add_ai_limits_column();

        // Studio / general copy
        add_action( 'wp_ajax_mt_generate_ai_copy',    array( $this, 'ajax_studio_copy' ) );

        // Campaign subject + preview
        add_action( 'wp_ajax_mt_ai_campaign_assist',  array( $this, 'ajax_campaign_assist' ) );

        // Brand auto-fill
        add_action( 'wp_ajax_mt_ai_brand_autofill',   array( $this, 'ajax_brand_autofill' ) );

        // CRM Advisor
        add_action( 'wp_ajax_mt_ai_crm_advisor',      array( $this, 'ajax_crm_advisor' ) );

        // Splash copy
        add_action( 'wp_ajax_mt_ai_splash_copy',      array( $this, 'ajax_splash_copy' ) );

        // Workflow/automation email body
        add_action( 'wp_ajax_mt_ai_workflow_body',    array( $this, 'ajax_workflow_body' ) );

        // Help chatbot
        add_action( 'wp_ajax_mt_ai_help_chat',        array( $this, 'ajax_help_chat' ) );

        // Credits status (for frontend usage meters)
        add_action( 'wp_ajax_mt_ai_get_credits',      array( $this, 'ajax_get_credits' ) );

        // AI Template Builder (Studio)
        add_action( 'wp_ajax_mt_ai_build_template',   array( $this, 'ajax_build_template' ) );
    }

    // -------------------------------------------------------------------------
    // DATABASE SETUP
    // -------------------------------------------------------------------------
    private function maybe_create_ai_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'mt_ai_usage';
        if ( $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table ) return;

        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            brand_id INT NOT NULL,
            section VARCHAR(50) NOT NULL,
            period VARCHAR(7) NOT NULL,
            calls_used INT UNSIGNED DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY brand_section_period (brand_id, section, period)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    private function maybe_add_ai_limits_column() {
        global $wpdb;
        $table = $wpdb->prefix . 'mt_packages';
        $cols  = $wpdb->get_col("DESCRIBE $table", 0);
        if ( ! in_array( 'ai_limits_json', $cols ) ) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN ai_limits_json LONGTEXT NULL DEFAULT NULL");
        }
        // Per-brand AI override stored in mt_brand_config — no column needed
    }

    // -------------------------------------------------------------------------
    // CREDIT SYSTEM
    // -------------------------------------------------------------------------

    /**
     * Get current-month usage for a brand+section.
     */
    public function get_section_usage( $brand_id, $section ) {
        global $wpdb;
        $period = current_time('Y-m');
        $table  = $wpdb->prefix . 'mt_ai_usage';
        $used   = $wpdb->get_var( $wpdb->prepare(
            "SELECT calls_used FROM $table WHERE brand_id = %d AND section = %s AND period = %s",
            $brand_id, $section, $period
        ) );
        return (int) $used;
    }

    /**
     * Get the monthly limit for a brand+section.
     * Checks: brand-level override → package limit → default.
     */
    public function get_section_limit( $brand_id, $section ) {
        global $wpdb;

        // 1. Brand-level override stored in brand config
        $brand       = $wpdb->get_row( $wpdb->prepare( "SELECT brand_config, package_id FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id ) );
        $brand_cfg   = json_decode( $brand->brand_config ?? '{}', true ) ?: [];
        $overrides   = $brand_cfg['ai_limits'] ?? [];
        if ( isset( $overrides[ $section ] ) ) {
            return (int) $overrides[ $section ];
        }

        // 2. Package-level limit
        if ( ! empty( $brand->package_id ) ) {
            $pkg_limits_json = $wpdb->get_var( $wpdb->prepare(
                "SELECT ai_limits_json FROM {$wpdb->prefix}mt_packages WHERE id = %d",
                $brand->package_id
            ) );
            $pkg_limits = json_decode( $pkg_limits_json ?? '{}', true ) ?: [];
            if ( isset( $pkg_limits[ $section ] ) ) {
                return (int) $pkg_limits[ $section ];
            }
        }

        // 3. Hardcoded default
        $defaults = self::get_default_limits();
        return $defaults[ $section ] ?? 5;
    }

    /**
     * Check if brand has remaining credits for a section.
     * Returns array: ['allowed' => bool, 'used' => int, 'limit' => int]
     * Super admins always pass through.
     */
    public function check_section_credits( $brand_id, $section ) {
        if ( current_user_can( 'manage_options' ) ) {
            return [ 'allowed' => true, 'used' => 0, 'limit' => 999 ];
        }

        // Check if platform AI is globally disabled
        if ( ! get_option( 'mt_ai_enabled', '1' ) ) {
            return [ 'allowed' => false, 'used' => 0, 'limit' => 0 ];
        }

        $used  = $this->get_section_usage( $brand_id, $section );
        $limit = $this->get_section_limit( $brand_id, $section );

        // -1 = unlimited
        if ( $limit === -1 ) return [ 'allowed' => true, 'used' => $used, 'limit' => -1 ];

        return [
            'allowed'   => $used < $limit,
            'used'      => $used,
            'limit'     => $limit,
            'remaining' => max( 0, $limit - $used ),
        ];
    }

    /**
     * Deduct one credit from brand+section for current month.
     */
    private function deduct_section_credit( $brand_id, $section ) {
        if ( current_user_can( 'manage_options' ) ) return;
        global $wpdb;
        $period = current_time('Y-m');
        $table  = $wpdb->prefix . 'mt_ai_usage';
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO $table (brand_id, section, period, calls_used)
             VALUES (%d, %s, %s, 1)
             ON DUPLICATE KEY UPDATE calls_used = calls_used + 1",
            $brand_id, $section, $period
        ) );
    }

    // -------------------------------------------------------------------------
    // SYSTEM PROMPTS
    // -------------------------------------------------------------------------
    private function get_system_prompt( $context ) {
        $prompts = [
            'email_copy'   => 'You are an expert email copywriter for small local businesses. Write engaging, conversion-focused email body content in HTML paragraph format. Be concise, warm, and action-oriented. Return only the copy — no explanation, no markdown fences.',
            'subject_line' => 'You are an email subject line specialist. Write a compelling subject line that maximizes open rates. Under 60 characters. Return ONLY the subject line — nothing else, no quotes.',
            'preheader'    => 'You are an email preview text expert. Write a short curiosity-driven preview sentence, 50–90 characters. Return ONLY the preheader text.',
            'crm_advisor'  => 'You are a marketing strategist for local businesses. Analyze the provided customer data and give clear, specific, actionable recommendations. Be direct and practical. Use plain text with short paragraphs.',
            'brand_autofill' => 'You are a brand data extraction expert. Extract structured brand information from the provided website content. Return ONLY valid JSON matching the exact schema provided.',
            'splash_copy'  => 'You are a conversion copywriter specializing in WiFi splash pages and lead capture. Write punchy, welcoming copy that converts visitors. Return ONLY valid JSON with the keys provided.',
            'workflow_body' => 'You are an email automation specialist for local hospitality businesses. Write warm, personalized automated email content. Return ONLY valid HTML body content, no full document.',
            'help_chat'    => 'You are the Toucan AI assistant, the friendly help guide for the MailToucan platform — a WiFi marketing and email automation tool for local businesses. Answer questions about the platform clearly and helpfully. If you don\'t know something specific, say so and suggest they contact support. Keep answers concise and practical.',
        ];
        return $prompts[ $context ] ?? $prompts['email_copy'];
    }

    // -------------------------------------------------------------------------
    // PUBLIC GENERATION ENTRY POINT
    // -------------------------------------------------------------------------
    public function generate_text( $prompt, $brand_id, $provider = null, $context = 'email_copy' ) {
        // Auto-select: own keys preferred, then cheapest platform key
        if ( ! $provider ) {
            $provider = $this->get_active_provider( $brand_id );
        }

        $system = $this->get_system_prompt( $context );
        $full   = $system . "\n\n" . $prompt;

        // Pass brand_id so provider functions can choose own key vs platform key
        switch ( $provider ) {
            case 'claude':  return $this->call_claude( $full, $brand_id );
            case 'gemini':  return $this->call_gemini( $full, $brand_id );
            default:        return $this->call_openai( $full, $brand_id );
        }
    }

    /**
     * Whether this brand is using their own API key for a given provider.
     * Used to skip credit deduction when own key is active.
     */
    public function is_using_own_key( $brand_id, $provider ) {
        $own = $this->get_own_keys( $brand_id );
        return match($provider) {
            'openai' => ! empty( $own['own_openai_key'] ),
            'gemini' => ! empty( $own['own_gemini_key'] ),
            'claude' => ! empty( $own['own_anthropic_key'] ),
            default  => false,
        };
    }

    /**
     * Returns which provider to use — own keys take priority (no credits consumed),
     * then platform keys cheapest-first (Gemini → OpenAI → Claude).
     */
    private function get_active_provider( $brand_id = 0 ) {
        // Check brand's own API keys first
        if ( $brand_id ) {
            $own = $this->get_own_keys( $brand_id );
            if ( ! empty( $own['own_gemini_key'] ) )    return 'gemini';
            if ( ! empty( $own['own_openai_key'] ) )    return 'openai';
            if ( ! empty( $own['own_anthropic_key'] ) ) return 'claude';
        }
        // Fall back to platform keys
        if ( get_option('mt_gemini_api_key') )    return 'gemini';
        if ( get_option('mt_openai_api_key') )    return 'openai';
        if ( get_option('mt_anthropic_api_key') ) return 'claude';
        return 'openai'; // will fail gracefully with no-key error
    }

    /**
     * Returns brand's own API keys array, or empty array.
     */
    private function get_own_keys( $brand_id ) {
        global $wpdb;
        $brand = $wpdb->get_row( $wpdb->prepare( "SELECT brand_config FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id ) );
        $cfg   = json_decode( $brand->brand_config ?? '{}', true ) ?: [];
        return $cfg['api_keys'] ?? [];
    }

    // -------------------------------------------------------------------------
    // PROVIDER CALLS — own key takes precedence over platform key
    // -------------------------------------------------------------------------
    private function call_openai( $prompt, $brand_id = 0 ) {
        $own     = $brand_id ? $this->get_own_keys($brand_id) : [];
        $api_key = ! empty($own['own_openai_key']) ? $own['own_openai_key'] : get_option( 'mt_openai_api_key', '' );
        if ( empty( $api_key ) ) return new WP_Error( 'no_key', 'OpenAI API key not set. Go to Super Admin → AI Settings.' );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [ 'Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [
                'model'       => 'gpt-4o-mini',
                'messages'    => [ [ 'role' => 'user', 'content' => $prompt ] ],
                'max_tokens'  => 800,
                'temperature' => 0.72,
            ] ),
        ] );

        if ( is_wp_error($response) ) return $response;
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode( wp_remote_retrieve_body($response), true );
        if ( $code !== 200 || isset($body['error']) ) {
            return new WP_Error( 'api_error', $body['error']['message'] ?? 'OpenAI returned an error.' );
        }
        return trim( $body['choices'][0]['message']['content'] ?? '' ) ?: new WP_Error('empty','OpenAI returned an empty response.');
    }

    private function call_claude( $prompt, $brand_id = 0 ) {
        $own     = $brand_id ? $this->get_own_keys($brand_id) : [];
        $api_key = ! empty($own['own_anthropic_key']) ? $own['own_anthropic_key'] : get_option( 'mt_anthropic_api_key', '' );
        if ( empty( $api_key ) ) return new WP_Error( 'no_key', 'Anthropic API key not set. Go to Super Admin → AI Settings.' );

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => 30,
            'headers' => [
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 800,
                'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
            ] ),
        ] );

        if ( is_wp_error($response) ) return $response;
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode( wp_remote_retrieve_body($response), true );
        if ( $code !== 200 || isset($body['error']) ) {
            return new WP_Error( 'api_error', $body['error']['message'] ?? 'Claude returned an error.' );
        }
        return trim( $body['content'][0]['text'] ?? '' ) ?: new WP_Error('empty','Claude returned an empty response.');
    }

    private function call_gemini( $prompt, $brand_id = 0 ) {
        $own     = $brand_id ? $this->get_own_keys($brand_id) : [];
        $api_key = ! empty($own['own_gemini_key']) ? $own['own_gemini_key'] : get_option( 'mt_gemini_api_key', '' );
        if ( empty( $api_key ) ) return new WP_Error( 'no_key', 'Gemini API key not set. Go to Super Admin → AI Settings.' );

        $url      = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $api_key;
        $response = wp_remote_post( $url, [
            'timeout' => 30,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [
                'contents'         => [ [ 'parts' => [ [ 'text' => $prompt ] ] ] ],
                'generationConfig' => [ 'maxOutputTokens' => 800, 'temperature' => 0.72 ],
            ] ),
        ] );

        if ( is_wp_error($response) ) return $response;
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode( wp_remote_retrieve_body($response), true );
        if ( $code !== 200 || isset($body['error']) ) {
            return new WP_Error( 'api_error', $body['error']['message'] ?? 'Gemini returned an error.' );
        }
        return trim( $body['candidates'][0]['content']['parts'][0]['text'] ?? '' ) ?: new WP_Error('empty','Gemini returned an empty response.');
    }

    // -------------------------------------------------------------------------
    // SHARED AJAX HELPERS
    // -------------------------------------------------------------------------
    private function verify_request() {
        if ( ! check_ajax_referer( 'mt_app_nonce', 'security', false ) ) {
            wp_send_json_error( 'Security token expired. Refresh and try again.' );
        }
        $user_id  = get_current_user_id();
        $brand_id = (int) ( get_user_meta( $user_id, 'mt_brand_id', true ) ?: 0 );
        return [ 'user_id' => $user_id, 'brand_id' => $brand_id ];
    }

    private function check_and_gate( $brand_id, $section ) {
        // If brand has their own API key set, bypass credit checks entirely
        $provider = $this->get_active_provider( $brand_id );
        if ( $this->is_using_own_key( $brand_id, $provider ) ) {
            return [ 'allowed' => true, 'used' => 0, 'limit' => -1, 'remaining' => 999, 'own_key' => true ];
        }

        $credits = $this->check_section_credits( $brand_id, $section );
        if ( ! $credits['allowed'] ) {
            $msg = isset($credits['limit']) && $credits['limit'] === 0
                ? 'Platform AI is currently disabled.'
                : 'Monthly AI limit reached for ' . ( self::get_sections()[$section] ?? $section ) . '. Add your own API key under Core → API & Credits to continue.';
            wp_send_json_error( [ 'message' => $msg, 'out_of_credits' => true ] );
        }
        return $credits;
    }

    private function send_ai_response( $result, $brand_id, $section, $extra = [] ) {
        if ( is_wp_error($result) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        // Only deduct platform credits when not using own API key
        $provider = $this->get_active_provider( $brand_id );
        $using_own = $this->is_using_own_key( $brand_id, $provider );
        if ( ! $using_own ) {
            $this->deduct_section_credit( $brand_id, $section );
        }
        $credits_after = $using_own
            ? [ 'remaining' => 999 ]
            : $this->check_section_credits( $brand_id, $section );
        wp_send_json_success( array_merge( [
            'text'      => $result,
            'remaining' => $using_own ? '∞ (own key)' : ( $credits_after['remaining'] ?? 999 ),
        ], $extra ) );
    }

    // -------------------------------------------------------------------------
    // AJAX: CREDITS STATUS (frontend meter)
    // -------------------------------------------------------------------------
    public function ajax_get_credits() {
        $ctx      = $this->verify_request();
        $brand_id = $ctx['brand_id'];
        $sections = self::get_sections();
        $out      = [];
        foreach ( $sections as $key => $label ) {
            $c = $this->check_section_credits( $brand_id, $key );
            $out[$key] = [
                'label'     => $label,
                'used'      => $c['used'],
                'limit'     => $c['limit'],
                'remaining' => $c['remaining'] ?? 999,
                'allowed'   => $c['allowed'],
            ];
        }
        wp_send_json_success( $out );
    }

    // -------------------------------------------------------------------------
    // AJAX: EMAIL STUDIO COPY
    // -------------------------------------------------------------------------
    public function ajax_studio_copy() {
        $ctx      = $this->verify_request();
        $brand_id = $ctx['brand_id'];
        $this->check_and_gate( $brand_id, 'email_studio' );

        $prompt  = sanitize_textarea_field( $_POST['prompt']  ?? '' );
        $context = sanitize_text_field( $_POST['context']     ?? 'email_copy' );
        if ( empty($prompt) ) wp_send_json_error('Prompt cannot be empty.');

        $result = $this->generate_text( $prompt, $brand_id, null, $context );
        $this->send_ai_response( $result, $brand_id, 'email_studio' );
    }

    // -------------------------------------------------------------------------
    // AJAX: CAMPAIGN SUBJECT + PREVIEW ASSIST
    // -------------------------------------------------------------------------
    public function ajax_campaign_assist() {
        $ctx      = $this->verify_request();
        $brand_id = $ctx['brand_id'];
        $this->check_and_gate( $brand_id, 'campaign_subject' );

        $campaign_name = sanitize_text_field( $_POST['campaign_name'] ?? '' );
        $goal          = sanitize_textarea_field( $_POST['goal']       ?? '' );
        $brand_name    = sanitize_text_field( $_POST['brand_name']     ?? '' );

        $prompt = "Brand: {$brand_name}\nCampaign goal: {$goal}\nCampaign name: {$campaign_name}\n\n";
        $prompt .= "Generate TWO subject lines and ONE preview text. Return ONLY valid JSON:\n{\"subjects\":[\"...\",\"...\"],\"preview\":\"...\"}";

        $raw = $this->generate_text( $prompt, $brand_id, null, 'subject_line' );
        if ( is_wp_error($raw) ) wp_send_json_error( $raw->get_error_message() );

        // Strip markdown fences if present
        $clean = trim( preg_replace('/^```(?:json)?\s*/i', '', preg_replace('/\s*```$/i', '', trim($raw))) );
        $data  = json_decode( $clean, true );
        if ( ! $data ) wp_send_json_error('AI returned unexpected format. Try again.');

        $this->deduct_section_credit( $brand_id, 'campaign_subject' );
        $credits_after = $this->check_section_credits( $brand_id, 'campaign_subject' );
        wp_send_json_success( [
            'subjects'  => $data['subjects'] ?? [],
            'preview'   => $data['preview']  ?? '',
            'remaining' => $credits_after['remaining'] ?? 999,
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX: BRAND AUTO-FILL
    // -------------------------------------------------------------------------
    public function ajax_brand_autofill() {
        $ctx      = $this->verify_request();
        $brand_id = $ctx['brand_id'];
        $this->check_and_gate( $brand_id, 'brand_autofill' );

        $website_url  = esc_url_raw( $_POST['website_url']  ?? '' );
        $brand_name   = sanitize_text_field( $_POST['brand_name'] ?? '' );

        if ( empty($website_url) && empty($brand_name) ) {
            wp_send_json_error('Please save your Website URL or Company Name first so Toucan AI has something to work with.');
        }

        $prompt = "Business name: \"{$brand_name}\"\nWebsite: {$website_url}\n\n";
        $prompt .= "Based on this business, generate professional brand details. Return ONLY valid JSON:\n";
        $prompt .= '{"slogan":"","industry":"","description":"","suggested_primary_color":"#hexcode","suggested_secondary_color":"#hexcode","suggested_font":"Inter|Roboto|Poppins|Montserrat|Open Sans","support_email_suggestion":"support@domain.com"}';
        $prompt .= "\n\nFor colors, choose ones that feel right for this type of business. For font pick one from the list.";

        $raw = $this->generate_text( $prompt, $brand_id, null, 'brand_autofill' );
        if ( is_wp_error($raw) ) wp_send_json_error( $raw->get_error_message() );

        $clean = trim( preg_replace('/^```(?:json)?\s*/i', '', preg_replace('/\s*```$/i', '', trim($raw))) );
        $data  = json_decode( $clean, true );
        if ( ! $data ) wp_send_json_error('AI could not generate brand data. Try again.');

        $this->deduct_section_credit( $brand_id, 'brand_autofill' );
        $credits_after = $this->check_section_credits( $brand_id, 'brand_autofill' );
        wp_send_json_success( array_merge( $data, [ 'remaining' => $credits_after['remaining'] ?? 999 ] ) );
    }

    // -------------------------------------------------------------------------
    // AJAX: CRM SEGMENT ADVISOR
    // -------------------------------------------------------------------------
    public function ajax_crm_advisor() {
        $ctx      = $this->verify_request();
        $brand_id = $ctx['brand_id'];
        $this->check_and_gate( $brand_id, 'crm_advisor' );

        global $wpdb;
        $leads_table = $wpdb->prefix . 'mt_guest_leads';
        $camps_table = $wpdb->prefix . 'mt_campaigns';

        // Build a data summary for the AI (no PII — aggregates only)
        $total        = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $leads_table WHERE brand_id = %d AND status = 'active'", $brand_id) );
        $recent_30    = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $leads_table WHERE brand_id = %d AND status = 'active' AND last_visit >= DATE_SUB(NOW(), INTERVAL 30 DAY)", $brand_id) );
        $inactive_60  = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $leads_table WHERE brand_id = %d AND status = 'active' AND last_visit <= DATE_SUB(NOW(), INTERVAL 60 DAY)", $brand_id) );
        $birthday_mo  = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $leads_table WHERE brand_id = %d AND status = 'active' AND MONTH(birthday) = MONTH(NOW())", $brand_id) );
        $campaigns_sent = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $camps_table WHERE brand_id = %d AND campaign_type = 'sent'", $brand_id) );
        $last_campaign  = $wpdb->get_var( $wpdb->prepare("SELECT created_at FROM $camps_table WHERE brand_id = %d AND campaign_type = 'sent' ORDER BY created_at DESC LIMIT 1", $brand_id) );
        $top_tags       = $wpdb->get_results( $wpdb->prepare("SELECT campaign_tag, COUNT(*) as cnt FROM $leads_table WHERE brand_id = %d AND status = 'active' AND campaign_tag != '' GROUP BY campaign_tag ORDER BY cnt DESC LIMIT 5", $brand_id) );

        $tag_summary = '';
        foreach ( $top_tags as $t ) $tag_summary .= "\n  - {$t->campaign_tag}: {$t->cnt} contacts";

        $question = sanitize_textarea_field( $_POST['question'] ?? 'Give me your best recommendations for who to target and what to send next.' );

        $prompt  = "LOCAL BUSINESS CRM DATA SUMMARY:\n";
        $prompt .= "- Total active contacts: {$total}\n";
        $prompt .= "- Visited in last 30 days: {$recent_30}\n";
        $prompt .= "- Inactive 60+ days (win-back candidates): {$inactive_60}\n";
        $prompt .= "- Birthdays this month: {$birthday_mo}\n";
        $prompt .= "- Total campaigns sent: {$campaigns_sent}\n";
        $prompt .= $last_campaign ? "- Last campaign sent: " . human_time_diff(strtotime($last_campaign)) . " ago\n" : "- No campaigns sent yet\n";
        $prompt .= "- Top audience segments by WiFi capture:{$tag_summary}\n\n";
        $prompt .= "QUESTION: {$question}\n\n";
        $prompt .= "Give 3 specific, actionable recommendations. For each: who to target, what to send, and why. Be concise.";

        $result = $this->generate_text( $prompt, $brand_id, null, 'crm_advisor' );
        $this->send_ai_response( $result, $brand_id, 'crm_advisor' );
    }

    // -------------------------------------------------------------------------
    // AJAX: SPLASH PAGE COPY
    // -------------------------------------------------------------------------
    public function ajax_splash_copy() {
        $ctx      = $this->verify_request();
        $brand_id = $ctx['brand_id'];
        $this->check_and_gate( $brand_id, 'splash_copy' );

        global $wpdb;
        $brand = $wpdb->get_row( $wpdb->prepare("SELECT brand_name, brand_config FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id) );
        $cfg   = json_decode( $brand->brand_config ?? '{}', true ) ?: [];

        $tone        = sanitize_text_field( $_POST['tone']        ?? 'friendly' );
        $offer       = sanitize_text_field( $_POST['offer']       ?? '' );
        $location    = sanitize_text_field( $_POST['location']    ?? '' );

        $prompt  = "Business: {$brand->brand_name}\n";
        $prompt .= "Type of place: " . ( $cfg['industry'] ?? 'local business' ) . "\n";
        if ($location)  $prompt .= "Location name: {$location}\n";
        if ($offer)     $prompt .= "Special offer/incentive: {$offer}\n";
        $prompt .= "Tone: {$tone}\n\n";
        $prompt .= "Write splash page WiFi login copy. Return ONLY valid JSON:\n";
        $prompt .= '{"headline":"","subheadline":"","cta_button":"","terms_snippet":"By connecting you agree to our terms."}';

        $raw = $this->generate_text( $prompt, $brand_id, null, 'splash_copy' );
        if ( is_wp_error($raw) ) wp_send_json_error( $raw->get_error_message() );

        $clean = trim( preg_replace('/^```(?:json)?\s*/i', '', preg_replace('/\s*```$/i', '', trim($raw))) );
        $data  = json_decode( $clean, true );
        if ( ! $data ) wp_send_json_error('AI returned unexpected format. Try again.');

        $this->deduct_section_credit( $brand_id, 'splash_copy' );
        $credits_after = $this->check_section_credits( $brand_id, 'splash_copy' );
        wp_send_json_success( array_merge( $data, [ 'remaining' => $credits_after['remaining'] ?? 999 ] ) );
    }

    // -------------------------------------------------------------------------
    // AJAX: WORKFLOW EMAIL BODY
    // -------------------------------------------------------------------------
    public function ajax_workflow_body() {
        $ctx      = $this->verify_request();
        $brand_id = $ctx['brand_id'];
        $this->check_and_gate( $brand_id, 'automation' );

        global $wpdb;
        $brand = $wpdb->get_row( $wpdb->prepare("SELECT brand_name, primary_color, brand_config FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id) );

        $trigger  = sanitize_text_field( $_POST['trigger_type'] ?? 'birthday' );
        $tone     = sanitize_text_field( $_POST['tone']         ?? 'warm' );
        $offer    = sanitize_text_field( $_POST['offer']        ?? '' );

        $trigger_labels = [
            'birthday'    => 'customer birthday celebration',
            'winback'     => 'win-back / "we miss you"',
            'first_visit' => 'welcome after first WiFi visit',
            'anniversary' => 'visit anniversary',
        ];
        $trigger_label = $trigger_labels[$trigger] ?? $trigger;

        $prompt  = "Business: {$brand->brand_name}\n";
        $prompt .= "Email type: {$trigger_label}\n";
        $prompt .= "Tone: {$tone}\n";
        if ($offer) $prompt .= "Special offer to include: {$offer}\n";
        $prompt .= "Personalization tags available: {{first_name}}, {{store_name}}\n\n";
        $prompt .= "Write the complete email body as HTML paragraphs. Include a clear call-to-action. ";
        $prompt .= "Use the personalization tags naturally. Do not include a full HTML document — just the body content paragraphs and a CTA button styled inline.";

        $result = $this->generate_text( $prompt, $brand_id, null, 'workflow_body' );
        $this->send_ai_response( $result, $brand_id, 'automation' );
    }

    // -------------------------------------------------------------------------
    // AJAX: HELP CHATBOT
    // -------------------------------------------------------------------------
    public function ajax_help_chat() {
        $ctx      = $this->verify_request();
        $brand_id = $ctx['brand_id'];
        $this->check_and_gate( $brand_id, 'help_chat' );

        $message = sanitize_textarea_field( $_POST['message'] ?? '' );
        $section = sanitize_text_field( $_POST['current_section'] ?? '' );
        if ( empty($message) ) wp_send_json_error('Message cannot be empty.');

        $context_hint = $section ? "The user is currently viewing the {$section} section of the dashboard." : '';

        $prompt  = "PLATFORM CONTEXT: MailToucan is a SaaS WiFi marketing and email automation platform for local businesses. ";
        $prompt .= "Features include: WiFi captive portal/splash pages, CRM (guest leads), email campaigns, automated workflows (birthday, win-back, first-visit), email studio with template builder, sender domain management, brand settings, and analytics.\n\n";
        if ($context_hint) $prompt .= "{$context_hint}\n\n";
        $prompt .= "USER QUESTION: {$message}";

        $result = $this->generate_text( $prompt, $brand_id, null, 'help_chat' );
        $this->send_ai_response( $result, $brand_id, 'help_chat' );
    }

    // -------------------------------------------------------------------------
    // AJAX: AI TEMPLATE BUILDER (Studio — Build Full Template mode)
    // -------------------------------------------------------------------------
    public function ajax_build_template() {
        $ctx      = $this->verify_request();
        $brand_id = $ctx['brand_id'];
        $this->check_and_gate( $brand_id, 'email_studio' );

        $base_slug   = sanitize_key( $_POST['base_slug']   ?? 'starter' );
        $description = sanitize_textarea_field( $_POST['description'] ?? '' );
        $tone        = sanitize_text_field( $_POST['tone'] ?? 'Professional' );
        if ( empty($description) ) wp_send_json_error('Please describe what you want in your template.');

        // Get brand data for context
        global $wpdb;
        $brand = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id ) );
        $cfg   = json_decode( $brand->brand_config ?? '{}', true ) ?: [];

        $brand_name  = $brand->brand_name ?? 'Our Business';
        $brand_color = $brand->primary_color ?? '#0f172a';
        $address     = $cfg['hq_address'] ?? '';
        $website     = $cfg['url'] ?? '#';
        $logo_url    = $cfg['logos']['main'] ?? '';

        // Get the base template HTML (merge tags intact)
        $base_html = class_exists('MT_Templates')
            ? MT_Templates::get_template_html( $base_slug, $brand )
            : '';

        // Build prompt asking AI to customise the template
        $prompt = "You are an expert email marketer building a branded HTML email for a business called \"{$brand_name}\".\n\n";
        $prompt .= "Brand color: {$brand_color}. Website: {$website}. Tone: {$tone}.\n\n";
        $prompt .= "TASK: Customise the following HTML email template based on this description:\n\"{$description}\"\n\n";
        $prompt .= "RULES:\n";
        $prompt .= "- Keep the existing HTML structure and table layout intact — do NOT rebuild from scratch.\n";
        $prompt .= "- Replace placeholder text ([Add your main message here], [Your promo details], etc.) with compelling copy matching the description and tone.\n";
        $prompt .= "- Replace generic CTA button text with a strong, relevant call to action.\n";
        $prompt .= "- Keep all merge tags exactly as-is: {{first_name}}, {{brand_name}}, {{unsubscribe_url}}, {{address}}, etc.\n";
        $prompt .= "- Output ONLY the complete HTML email — no explanation, no markdown fences.\n\n";
        $prompt .= "BASE TEMPLATE HTML:\n" . $base_html;

        $result = $this->generate_text( $prompt, $brand_id, $_POST['provider'] ?? null, 'email_copy' );

        if ( is_wp_error($result) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        // Strip any accidental markdown fences
        $html = preg_replace('/^```html?\s*/i', '', $result);
        $html = preg_replace('/\s*```$/', '', $html);
        $html = trim($html);

        // Save as a real template record
        $registry      = class_exists('MT_Templates') ? MT_Templates::get_registry() : [];
        $base_label    = $registry[$base_slug]['name'] ?? 'Template';
        $template_name = 'AI: ' . $base_label . ' — ' . date('M j, Y g:ia');
        $payload       = base64_encode( wp_json_encode( ['html' => $html, 'json' => null, 'source' => 'ai_built'] ) );

        $wpdb->insert( $wpdb->prefix . 'mt_email_templates', [
            'brand_id'      => $brand_id,
            'template_name' => $template_name,
            'email_subject' => 'Message from ' . $brand_name,
            'email_body'    => $payload,
            'status'        => 'active',
            'created_at'    => current_time('mysql'),
            'updated_at'    => current_time('mysql'),
        ] );
        $template_id = $wpdb->insert_id;

        // Deduct credit
        $provider  = $this->get_active_provider( $brand_id );
        $using_own = $this->is_using_own_key( $brand_id, $provider );
        if ( ! $using_own ) $this->deduct_section_credit( $brand_id, 'email_studio' );
        $credits_after = $using_own ? ['remaining' => 999] : $this->check_section_credits( $brand_id, 'email_studio' );

        wp_send_json_success([
            'template_id'  => $template_id,
            'template_name'=> $template_name,
            'preview_text' => "Template \"{$template_name}\" built and saved. Click \"Open in Builder\" to review and edit it.",
            'remaining'    => $using_own ? '∞ (own key)' : ( $credits_after['remaining'] ?? 999 ),
        ]);
    }
}

// Boot
$mt_ai = new MT_AI_Engine();
$mt_ai->init();
