<?php
/**
 * The Database & Role Activator
 */
class MT_Activator {

    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $tables = [
            // 1. THE NEW PACKAGE MANAGER TABLE
            "CREATE TABLE {$wpdb->prefix}mt_packages ( id bigint(20) NOT NULL AUTO_INCREMENT, package_slug varchar(50) NOT NULL, package_name varchar(100) NOT NULL, location_limit int(11) DEFAULT 1, storage_limit_mb int(11) DEFAULT 50, email_limit int(11) DEFAULT 1000, price_mrr decimal(10,2) DEFAULT 0.00, PRIMARY KEY  (id), UNIQUE KEY package_slug (package_slug) ) $charset_collate;",

            // 2. UPGRADED BRANDS TABLE (Added Billing & Limit Overrides)
            "CREATE TABLE {$wpdb->prefix}mt_brands ( id bigint(20) NOT NULL AUTO_INCREMENT, brand_name varchar(100) NOT NULL, primary_color varchar(10), logo_url text, ai_voice_profile text, splash_config LONGTEXT, brand_config LONGTEXT, package_slug varchar(50) DEFAULT 'mt_starter', location_limit int(11) DEFAULT 1, email_limit int(11) DEFAULT 1000, storage_limit_mb int(11) DEFAULT 50, storage_used_kb bigint(20) DEFAULT 0, renewal_date date DEFAULT NULL, custom_mrr decimal(10,2) DEFAULT NULL, PRIMARY KEY  (id) ) $charset_collate;",
            
            // 3. STORES, ROOST, WIFI, VAULT
            "CREATE TABLE {$wpdb->prefix}mt_stores ( id bigint(20) NOT NULL AUTO_INCREMENT, brand_id bigint(20) NOT NULL, store_name varchar(100) NOT NULL, router_identity varchar(100), local_offer_json text, splash_config LONGTEXT, PRIMARY KEY  (id), KEY brand_id (brand_id) ) $charset_collate;",
            "CREATE TABLE {$wpdb->prefix}mt_roost ( id bigint(20) NOT NULL AUTO_INCREMENT, email varchar(100) NOT NULL, brand_id bigint(20), store_id bigint(20), status varchar(20) DEFAULT 'unverified', captured_via varchar(20) DEFAULT 'wifi', created_at datetime DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY  (id), KEY email (email), KEY store_id (store_id) ) $charset_collate;",
            "CREATE TABLE {$wpdb->prefix}mt_wifi_logs ( id bigint(20) NOT NULL AUTO_INCREMENT, mac_address varchar(50), store_id bigint(20), connect_time datetime DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY  (id) ) $charset_collate;",
            "CREATE TABLE {$wpdb->prefix}mt_vault ( id bigint(20) NOT NULL AUTO_INCREMENT, brand_id bigint(20) NOT NULL, file_name varchar(255) NOT NULL, file_url text NOT NULL, asset_type varchar(20) DEFAULT 'wifi', file_size_kb int(11) DEFAULT 0, uploaded_at datetime DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY  (id), KEY brand_id (brand_id) ) $charset_collate;"
        ];

        foreach ($tables as $sql) { dbDelta($sql); }

        // --- SEED THE DEFAULT PACKAGES ---
        $packages = [
            ['slug' => 'mt_starter', 'name' => 'Starter', 'loc' => 1, 'store' => 50, 'email' => 1000, 'price' => 49.00],
            ['slug' => 'mt_pro', 'name' => 'Pro', 'loc' => 3, 'store' => 250, 'email' => 10000, 'price' => 149.00],
            ['slug' => 'mt_enterprise', 'name' => 'Enterprise', 'loc' => 15, 'store' => 1000, 'email' => 50000, 'price' => 299.00]
        ];
        foreach ($packages as $pkg) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}mt_packages WHERE package_slug = %s", $pkg['slug']));
            if (!$exists) {
                $wpdb->insert("{$wpdb->prefix}mt_packages", ['package_slug' => $pkg['slug'], 'package_name' => $pkg['name'], 'location_limit' => $pkg['loc'], 'storage_limit_mb' => $pkg['store'], 'email_limit' => $pkg['email'], 'price_mrr' => $pkg['price']]);
            }
        }

        // --- SEED GLOBAL DEFAULT TENANT ---
        $brand_exists = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}mt_brands WHERE id = 1");
        if ( ! $brand_exists ) {
            $wpdb->insert("{$wpdb->prefix}mt_brands", ['id' => 1, 'brand_name' => 'MailToucan HQ', 'primary_color' => '#E31E24', 'package_slug' => 'mt_enterprise', 'location_limit' => 999, 'email_limit' => 999999, 'storage_limit_mb' => 9999]);
            $wpdb->insert("{$wpdb->prefix}mt_stores", ['id' => 1, 'brand_id' => 1, 'store_name' => 'Global Location']);
        }

        $capabilities = array( 'read' => true, 'access_mt_app' => true );
        add_role( 'mt_starter', 'MailToucan Starter', $capabilities );
        add_role( 'mt_pro', 'MailToucan Pro', $capabilities );
        add_role( 'mt_enterprise', 'MailToucan Enterprise', $capabilities );

        add_rewrite_rule( '^app/?$', 'index.php?mt_app=1', 'top' );
        flush_rewrite_rules();
    }
}