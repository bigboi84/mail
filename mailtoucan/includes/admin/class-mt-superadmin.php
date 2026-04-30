<?php
/**
 * The Super Admin Module: "God Mode" for the SaaS Owner
 * Manages Tenants, Billing Cycles, Quotas, and SaaS Packages.
 */
class MT_SuperAdmin {

    public function init() {
        add_action( 'admin_menu', array( $this, 'register_super_admin_menu' ) );
        
        add_action( 'admin_post_mt_update_tenant', array( $this, 'save_tenant_limits' ) );
        add_action( 'admin_post_mt_delete_tenant', array( $this, 'delete_tenant' ) );
        add_action( 'admin_post_mt_provision_tenant', array( $this, 'process_provisioning' ) );
        add_action( 'admin_post_mt_save_package', array( $this, 'save_package' ) );
        add_action( 'admin_post_mt_save_dashboard_colors', array( $this, 'save_dashboard_colors' ) );
        add_action( 'admin_post_mt_save_ai_settings',        array( $this, 'save_ai_settings' ) );
        add_action( 'admin_post_mt_save_ai_tenant_limits',  array( $this, 'save_ai_tenant_limits' ) );
        add_action( 'admin_post_mt_save_email_settings',    array( $this, 'save_email_settings' ) );
        add_action( 'admin_post_mt_pause_tenant_email',     array( $this, 'save_tenant_email_pause' ) );
    }

    public function register_super_admin_menu() {
        add_menu_page( 'Toucan Engine', 'Toucan Engine', 'manage_options', 'mt-engine', array( $this, 'render_dashboard' ), 'dashicons-cloud', 2 );
        add_submenu_page( 'mt-engine', 'Tenants', 'Tenant Manager', 'manage_options', 'mt-tenants', array( $this, 'render_tenant_manager' ) );
        add_submenu_page( 'mt-engine', 'Packages', 'Package Manager', 'manage_options', 'mt-packages', array( $this, 'render_package_manager' ) );
        add_submenu_page( 'mt-engine', 'Dashboard Colors', 'Dashboard Colors', 'manage_options', 'mt-dashboard-colors', array( $this, 'render_dashboard_colors' ) );
        add_submenu_page( 'mt-engine', 'AI Settings', '✨ AI Settings', 'manage_options', 'mt-ai-settings', array( $this, 'render_ai_settings' ) );
        add_submenu_page( 'mt-engine', 'AI Tenant Limits', '📊 AI Tenant Limits', 'manage_options', 'mt-ai-limits', array( $this, 'render_ai_limits' ) );
        add_submenu_page( 'mt-engine', 'Email Settings', '✉️ Email Settings', 'manage_options', 'mt-email-settings', array( $this, 'render_email_settings' ) );
    }

    private function get_default_dashboard_colors() {
        return array(
            'brand_primary'      => '#FCC753',
            'sidebar_background' => '#1A232E',
            'sidebar_hover'      => '#1f2937',
            'page_background'    => '#f3f4f6',
            'card_background'    => '#ffffff',
            'text_primary'       => '#111827',
        );
    }

    private function get_dashboard_colors() {
        $defaults = $this->get_default_dashboard_colors();
        $saved = get_option( 'mt_dashboard_colors', array() );
        if ( ! is_array( $saved ) ) {
            $saved = array();
        }
        return wp_parse_args( $saved, $defaults );
    }

    private function render_admin_theme_style() {
        $colors = $this->get_dashboard_colors();
        ?>
        <style>
            .mt-admin-shell { background: <?php echo esc_html( $colors['page_background'] ); ?>; border-radius: 0.75rem; padding: 1.5rem; }
            .mt-admin-heading { color: <?php echo esc_html( $colors['text_primary'] ); ?>; }
            .mt-admin-btn-primary {
                background-color: <?php echo esc_html( $colors['brand_primary'] ); ?> !important;
                border-color: <?php echo esc_html( $colors['brand_primary'] ); ?> !important;
                color: #111827 !important;
            }
        </style>
        <?php
    }

   // --- THE ULTIMATE SELF-HEALING DATABASE PATCH ---
    private function ensure_database_columns_exist() {
        global $wpdb;

        // 1. Repair mt_brands table
        $table_brands = $wpdb->prefix . 'mt_brands';
        $brand_cols = $wpdb->get_col("DESCRIBE `$table_brands`", 0); // Get all existing columns
        
        if (!in_array('splash_config', $brand_cols)) $wpdb->query("ALTER TABLE `$table_brands` ADD `splash_config` LONGTEXT");
        if (!in_array('brand_config', $brand_cols)) $wpdb->query("ALTER TABLE `$table_brands` ADD `brand_config` LONGTEXT");
        if (!in_array('package_slug', $brand_cols)) $wpdb->query("ALTER TABLE `$table_brands` ADD `package_slug` varchar(50) DEFAULT 'mt_starter'");
        if (!in_array('location_limit', $brand_cols)) $wpdb->query("ALTER TABLE `$table_brands` ADD `location_limit` int(11) DEFAULT 1");
        if (!in_array('email_limit', $brand_cols)) $wpdb->query("ALTER TABLE `$table_brands` ADD `email_limit` int(11) DEFAULT 1000");
        if (!in_array('storage_limit_mb', $brand_cols)) $wpdb->query("ALTER TABLE `$table_brands` ADD `storage_limit_mb` int(11) DEFAULT 50");
        if (!in_array('renewal_date', $brand_cols)) $wpdb->query("ALTER TABLE `$table_brands` ADD `renewal_date` date DEFAULT NULL");
        if (!in_array('custom_mrr', $brand_cols)) $wpdb->query("ALTER TABLE `$table_brands` ADD `custom_mrr` decimal(10,2) DEFAULT NULL");
        
        // AUDIT FIX: Added API Sending permission column
        if (!in_array('api_sending_enabled', $brand_cols)) $wpdb->query("ALTER TABLE `$table_brands` ADD `api_sending_enabled` tinyint(1) DEFAULT 0");
        // Phase 2: Bulk email kill switch per-tenant
        if (!in_array('email_paused', $brand_cols)) $wpdb->query("ALTER TABLE `$table_brands` ADD `email_paused` tinyint(1) DEFAULT 0");
        // Phase 4: Onboarding tracking
        if (!in_array('onboarding_completed', $brand_cols)) $wpdb->query("ALTER TABLE `$table_brands` ADD `onboarding_completed` tinyint(1) DEFAULT 0");

        // 4. Repair mt_packages — onboarding flow type
        $table_packages = $wpdb->prefix . 'mt_packages';
        $pkg_cols2 = $wpdb->get_col("DESCRIBE `$table_packages`", 0);
        if (!in_array('onboarding_flow', $pkg_cols2)) $wpdb->query("ALTER TABLE `$table_packages` ADD `onboarding_flow` varchar(20) DEFAULT 'both'");

        // 2. Repair mt_packages table — add feature flag columns for plan gating
        $table_packages = $wpdb->prefix . 'mt_packages';
        $pkg_cols = $wpdb->get_col("DESCRIBE `$table_packages`", 0);
        if (!in_array('wifi_enabled', $pkg_cols))  $wpdb->query("ALTER TABLE `$table_packages` ADD `wifi_enabled` tinyint(1) DEFAULT 1");
        if (!in_array('email_enabled', $pkg_cols)) $wpdb->query("ALTER TABLE `$table_packages` ADD `email_enabled` tinyint(1) DEFAULT 1");

        // 3. Repair mt_stores table
        $table_stores = $wpdb->prefix . 'mt_stores';
        $store_cols = $wpdb->get_col("DESCRIBE `$table_stores`", 0);

        if (!in_array('splash_config', $store_cols)) $wpdb->query("ALTER TABLE `$table_stores` ADD `splash_config` LONGTEXT");
        if (!in_array('local_offer_json', $store_cols)) $wpdb->query("ALTER TABLE `$table_stores` ADD `local_offer_json` LONGTEXT");
        if (!in_array('router_identity', $store_cols)) $wpdb->query("ALTER TABLE `$table_stores` ADD `router_identity` varchar(100)");
    }

    // --- VIEW: MASTER DASHBOARD ---
    public function render_dashboard() {
        $this->ensure_database_columns_exist(); // Run the patch
        
        global $wpdb;
        $total_brands = $wpdb->get_var( "SELECT COUNT(id) FROM {$wpdb->prefix}mt_brands" );
        $total_mrr = 0;
        $brands = $wpdb->get_results( "SELECT package_slug, custom_mrr FROM {$wpdb->prefix}mt_brands" );
        foreach ($brands as $b) {
            $base_price = $wpdb->get_var( $wpdb->prepare("SELECT price_mrr FROM {$wpdb->prefix}mt_packages WHERE package_slug = %s", $b->package_slug) );
            $total_mrr += floatval($base_price) + floatval($b->custom_mrr);
        }
        ?>
        <div class="wrap mt-admin-shell" style="margin-top: 20px;">
            <script src="https://cdn.tailwindcss.com"></script>
            <?php $this->render_admin_theme_style(); ?>
            <div class="bg-gray-900 rounded-xl p-8 text-white shadow-xl mb-8">
                <h1 class="text-3xl font-bold mb-2 text-white">🦜 MailToucan "God Mode"</h1>
                <p class="text-gray-400">Master SaaS Control Panel. Manage billing, clients, and quotas.</p>
            </div>
            <div class="grid grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-blue-500">
                    <h3 class="text-gray-500 font-bold text-sm uppercase">Active Tenants</h3>
                    <p class="text-4xl font-bold text-gray-900 mt-2"><?php echo esc_html($total_brands); ?></p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-green-500">
                    <h3 class="text-gray-500 font-bold text-sm uppercase">Estimated MRR</h3>
                    <p class="text-4xl font-bold text-green-600 mt-2">$<?php echo number_format((float)$total_mrr, 2); ?></p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-purple-500">
                    <h3 class="text-gray-500 font-bold text-sm uppercase">Quick Actions</h3>
                    <div class="mt-3 flex flex-col gap-2">
                        <a href="?page=mt-tenants&action=provision" class="mt-admin-btn-primary px-4 py-2 rounded text-center font-bold text-sm text-decoration-none">Provision New Account</a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_dashboard_colors() {
        $colors = $this->get_dashboard_colors();
        ?>
        <div class="wrap mt-admin-shell" style="margin-top: 20px;">
            <script src="https://cdn.tailwindcss.com"></script>
            <?php $this->render_admin_theme_style(); ?>
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Dashboard Color Controls</h1>
                <p class="text-sm text-gray-600 mt-1">These colors are applied globally to Customer, Tenant, and Super Admin dashboards.</p>
            </div>

            <?php if ( isset( $_GET['updated'] ) ) : ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                    <p class="font-bold">Dashboard colors saved.</p>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-lg shadow-sm border p-6 max-w-4xl">
                <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST">
                    <input type="hidden" name="action" value="mt_save_dashboard_colors">
                    <?php wp_nonce_field( 'mt_save_dashboard_colors', 'mt_dashboard_colors_nonce' ); ?>

                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-2 uppercase">Brand Primary</label>
                            <input type="color" name="brand_primary" value="<?php echo esc_attr( $colors['brand_primary'] ); ?>" class="w-full h-12 p-1 border rounded">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-2 uppercase">Sidebar Background</label>
                            <input type="color" name="sidebar_background" value="<?php echo esc_attr( $colors['sidebar_background'] ); ?>" class="w-full h-12 p-1 border rounded">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-2 uppercase">Sidebar Hover/Active</label>
                            <input type="color" name="sidebar_hover" value="<?php echo esc_attr( $colors['sidebar_hover'] ); ?>" class="w-full h-12 p-1 border rounded">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-2 uppercase">Page Background</label>
                            <input type="color" name="page_background" value="<?php echo esc_attr( $colors['page_background'] ); ?>" class="w-full h-12 p-1 border rounded">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-2 uppercase">Card Background</label>
                            <input type="color" name="card_background" value="<?php echo esc_attr( $colors['card_background'] ); ?>" class="w-full h-12 p-1 border rounded">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-2 uppercase">Primary Text</label>
                            <input type="color" name="text_primary" value="<?php echo esc_attr( $colors['text_primary'] ); ?>" class="w-full h-12 p-1 border rounded">
                        </div>
                    </div>

                    <div class="mt-6 border-t pt-4 text-right">
                        <button type="submit" class="mt-admin-btn-primary font-bold py-2 px-6 rounded">Save Dashboard Colors</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    // --- VIEW: PACKAGE MANAGER ---
    public function render_package_manager() {
        global $wpdb;
        $packages = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}mt_packages ORDER BY price_mrr ASC");
        $edit_id = isset($_GET['edit_pkg']) ? intval($_GET['edit_pkg']) : 0;
        $edit_pkg = $edit_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mt_packages WHERE id = %d", $edit_id)) : null;
        ?>
        <div class="wrap mt-admin-shell" style="margin-top: 20px;">
            <script src="https://cdn.tailwindcss.com"></script>
            <?php $this->render_admin_theme_style(); ?>
            <div class="mb-6"><h1 class="text-2xl font-bold">SaaS Package Manager</h1></div>
            <?php if ( isset($_GET['pkg_saved']) ) : ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6"><p class="font-bold">Package saved successfully!</p></div>
            <?php endif; ?>
            <div class="flex gap-8">
                <div class="w-1/2">
                    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <tr class="bg-gray-50 border-b"><th class="p-4 font-bold text-gray-600">Tier Name</th><th class="p-4 font-bold text-gray-600">Price/mo</th><th class="p-4 font-bold text-gray-600">Features</th><th class="p-4 font-bold text-gray-600 text-right">Actions</th></tr>
                            <?php foreach($packages as $pkg): ?>
                            <tr class="border-b hover:bg-gray-50 <?php echo ($edit_id === intval($pkg->id)) ? 'bg-blue-50' : ''; ?>">
                                <td class="p-4 font-bold text-gray-900"><?php echo esc_html($pkg->package_name); ?></td>
                                <td class="p-4 text-green-600 font-bold">$<?php echo esc_html($pkg->price_mrr); ?></td>
                                <td class="p-4">
                                    <?php if( intval($pkg->wifi_enabled ?? 1) ): ?><span class="inline-block bg-blue-100 text-blue-700 text-xs font-bold px-2 py-1 rounded mr-1">📶 WiFi</span><?php else: ?><span class="inline-block bg-gray-100 text-gray-400 text-xs font-bold px-2 py-1 rounded mr-1 line-through">WiFi</span><?php endif; ?>
                                    <?php if( intval($pkg->email_enabled ?? 1) ): ?><span class="inline-block bg-purple-100 text-purple-700 text-xs font-bold px-2 py-1 rounded">✉ Email</span><?php else: ?><span class="inline-block bg-gray-100 text-gray-400 text-xs font-bold px-2 py-1 rounded line-through">Email</span><?php endif; ?>
                                </td>
                                <td class="p-4 text-right"><a href="?page=mt-packages&edit_pkg=<?php echo $pkg->id; ?>" class="bg-gray-200 text-gray-800 px-3 py-1 rounded text-sm font-bold hover:bg-gray-300 text-decoration-none">Edit</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    <div class="mt-4"><a href="?page=mt-packages" class="text-blue-600 font-bold hover:underline">+ Create Brand New Package</a></div>
                </div>

                <div class="w-1/2">
                    <div class="bg-white rounded-lg shadow-sm border p-6 border-t-4 border-blue-500">
                        <h2 class="text-lg font-bold border-b pb-4 mb-4"><?php echo $edit_pkg ? 'Editing: ' . esc_html($edit_pkg->package_name) : 'Create New Package'; ?></h2>
                        <form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="POST">
                            <input type="hidden" name="action" value="mt_save_package">
                            <input type="hidden" name="pkg_id" value="<?php echo $edit_pkg ? esc_attr($edit_pkg->id) : '0'; ?>">
                            <?php wp_nonce_field( 'mt_package_nonce', 'mt_pkg_nonce' ); ?>
                            <div class="mb-4">
                                <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Package Name</label>
                                <input type="text" name="package_name" class="w-full p-2 border rounded font-bold" value="<?php echo $edit_pkg ? esc_attr($edit_pkg->package_name) : ''; ?>" required>
                            </div>
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div><label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Locations Limit</label><input type="number" name="location_limit" class="w-full p-2 border rounded" value="<?php echo $edit_pkg ? esc_attr($edit_pkg->location_limit) : '1'; ?>"></div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Storage Limit (MB)</label><input type="number" name="storage_limit_mb" class="w-full p-2 border rounded" value="<?php echo $edit_pkg ? esc_attr($edit_pkg->storage_limit_mb) : '50'; ?>"></div>
                            </div>
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div><label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Emails / Month</label><input type="number" name="email_limit" class="w-full p-2 border rounded" value="<?php echo $edit_pkg ? esc_attr($edit_pkg->email_limit) : '1000'; ?>"></div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Monthly Price ($)</label><input type="number" step="0.01" name="price_mrr" class="w-full p-2 border rounded text-green-600 font-bold" value="<?php echo $edit_pkg ? esc_attr($edit_pkg->price_mrr) : '0.00'; ?>"></div>
                            </div>
                            <div class="mb-4">
                                <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Onboarding Wizard Flow</label>
                                <select name="onboarding_flow" class="w-full p-2 border rounded font-bold text-sm">
                                    <option value="both"  <?php selected( $edit_pkg ? ($edit_pkg->onboarding_flow ?? 'both') : 'both', 'both' ); ?>>WiFi + Email (Both)</option>
                                    <option value="wifi"  <?php selected( $edit_pkg ? ($edit_pkg->onboarding_flow ?? 'both') : 'both', 'wifi' ); ?>>WiFi Only</option>
                                    <option value="email" <?php selected( $edit_pkg ? ($edit_pkg->onboarding_flow ?? 'both') : 'both', 'email' ); ?>>Email Only</option>
                                    <option value="custom"<?php selected( $edit_pkg ? ($edit_pkg->onboarding_flow ?? 'both') : 'both', 'custom' ); ?>>Custom / Generic</option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Determines which onboarding steps new tenants on this package see.</p>
                            </div>
                            <div class="border border-indigo-100 bg-indigo-50 rounded-lg p-4 mb-6">
                                <p class="text-xs font-bold text-indigo-700 uppercase mb-3">🔀 Plan Feature Access — Toggle what this package unlocks</p>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">WiFi Marketing</label>
                                        <select name="wifi_enabled" class="w-full p-2 border rounded font-bold text-sm">
                                            <option value="1" <?php selected( $edit_pkg ? intval($edit_pkg->wifi_enabled ?? 1) : 1, 1 ); ?>>✅ Enabled</option>
                                            <option value="0" <?php selected( $edit_pkg ? intval($edit_pkg->wifi_enabled ?? 1) : 1, 0 ); ?>>🚫 Disabled</option>
                                        </select>
                                        <p class="text-xs text-gray-500 mt-1">Locations, Splash, WiFi Insights, CRM</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Email Marketing</label>
                                        <select name="email_enabled" class="w-full p-2 border rounded font-bold text-sm">
                                            <option value="1" <?php selected( $edit_pkg ? intval($edit_pkg->email_enabled ?? 1) : 1, 1 ); ?>>✅ Enabled</option>
                                            <option value="0" <?php selected( $edit_pkg ? intval($edit_pkg->email_enabled ?? 1) : 1, 0 ); ?>>🚫 Disabled</option>
                                        </select>
                                        <p class="text-xs text-gray-500 mt-1">Campaigns, Workflows, Delivery, Domains</p>
                                    </div>
                                </div>
                            </div>
                            <?php
                            // AI Limits section
                            $pkg_ai_limits = [];
                            if ($edit_pkg && !empty($edit_pkg->ai_limits_json)) {
                                $pkg_ai_limits = json_decode($edit_pkg->ai_limits_json, true) ?: [];
                            }
                            $ai_sections = MT_AI_Engine::get_sections();
                            $ai_defaults = MT_AI_Engine::get_default_limits();
                            ?>
                            <div class="border border-purple-100 bg-purple-50 rounded-lg p-4 mb-6">
                                <p class="text-xs font-bold text-purple-700 uppercase mb-1">✨ Toucan AI — Monthly Call Limits Per Section</p>
                                <p class="text-[10px] text-purple-500 mb-3">Set -1 for unlimited. Resets automatically on the 1st of each month.</p>
                                <div class="grid grid-cols-2 gap-3">
                                    <?php foreach ($ai_sections as $key => $label):
                                        $val = $pkg_ai_limits[$key] ?? $ai_defaults[$key] ?? 5;
                                    ?>
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-600 mb-1 uppercase"><?php echo esc_html($label); ?></label>
                                        <input type="number" name="ai_limit_<?php echo esc_attr($key); ?>"
                                               min="-1" value="<?php echo esc_attr($val); ?>"
                                               class="w-full p-1.5 border rounded text-sm font-mono">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <?php if ($edit_pkg): ?><a href="?page=mt-packages" class="text-gray-500 font-bold mr-4 text-decoration-none">Cancel Edit</a><?php endif; ?>
                                <button type="submit" class="mt-admin-btn-primary font-bold py-2 px-6 rounded">Save Package</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // --- VIEW: TENANT MANAGER ROUTER ---
    public function render_tenant_manager() {
        $this->ensure_database_columns_exist(); // Run the patch
        if ( isset($_GET['edit']) ) { $this->render_tenant_editor( intval($_GET['edit']) ); return; }
        if ( isset($_GET['action']) && $_GET['action'] === 'provision' ) { $this->render_provisioning_form(); return; }
        $this->render_tenant_list();
    }

    // --- VIEW: THE PROVISIONING FORM ---
    public function render_provisioning_form() {
        global $wpdb;
        $packages = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}mt_packages ORDER BY price_mrr ASC");
        ?>
        <div class="wrap mt-admin-shell" style="margin-top: 20px;">
            <script src="https://cdn.tailwindcss.com"></script>
            <?php $this->render_admin_theme_style(); ?>
            <div class="mb-6">
                <a href="?page=mt-tenants" class="text-gray-500 hover:text-gray-900 font-bold text-decoration-none">&larr; Back to Tenants</a>
                <h1 class="text-2xl font-bold text-gray-900 mt-2">Provision New Tenant</h1>
            </div>
            <div class="bg-white rounded-lg shadow-sm border p-8 max-w-3xl border-t-4 border-green-500">
                <form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="POST">
                    <input type="hidden" name="action" value="mt_provision_tenant">
                    <?php wp_nonce_field( 'mt_tenant_provision', 'mt_provision_nonce' ); ?>
                    <div class="grid grid-cols-2 gap-6 mb-6">
                        <div><label class="block text-sm font-bold text-gray-700 mb-2">Company / Brand Name</label><input type="text" name="brand_name" class="w-full p-2 border rounded" required></div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">SaaS Plan Tier</label>
                            <select name="package_slug" class="w-full p-2 border rounded">
                                <?php foreach($packages as $pkg): ?>
                                    <option value="<?php echo esc_attr($pkg->package_slug); ?>"><?php echo esc_html($pkg->package_name); ?> (<?php echo esc_html($pkg->location_limit); ?> Locs, $<?php echo esc_html($pkg->price_mrr); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4 mt-8">Admin User Credentials</h3>
                    <div class="grid grid-cols-2 gap-6 mb-6">
                        <div><label class="block text-sm font-bold text-gray-700 mb-2">Login Email Address</label><input type="email" name="user_email" class="w-full p-2 border rounded" required></div>
                        <div><label class="block text-sm font-bold text-gray-700 mb-2">Temporary Password</label><input type="text" name="user_pass" class="w-full p-2 border rounded" value="<?php echo wp_generate_password(12, false); ?>" required></div>
                    </div>
                    <div class="mt-8 border-t pt-6 text-right">
                        <button type="submit" class="mt-admin-btn-primary font-bold py-3 px-8 rounded-lg shadow-md">Create Tenant Environment</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    // --- VIEW: TENANT LIST ---
    public function render_tenant_list() {
        global $wpdb;
        $brands = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}mt_brands ORDER BY id DESC" );
        ?>
        <div class="wrap mt-admin-shell" style="margin-top: 20px;">
            <script src="https://cdn.tailwindcss.com"></script>
            <?php $this->render_admin_theme_style(); ?>
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Tenant Manager</h1>
                <a href="?page=mt-tenants&action=provision" class="mt-admin-btn-primary px-4 py-2 rounded font-bold text-decoration-none">+ Provision New Tenant</a>
            </div>

            <?php if ( isset($_GET['provisioned']) ) : ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6"><p class="font-bold">New Tenant Successfully Provisioned!</p></div>
            <?php endif; ?>
            <?php if ( isset($_GET['deleted']) ) : ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6"><p class="font-bold">Tenant completely removed from database.</p></div>
            <?php endif; ?>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <tr class="bg-gray-50 border-b">
                        <th class="p-4 font-bold text-gray-600">ID</th><th class="p-4 font-bold text-gray-600">Brand Name</th><th class="p-4 font-bold text-gray-600">Plan</th><th class="p-4 font-bold text-gray-600">Renewal Date</th><th class="p-4 font-bold text-gray-600">Total MRR</th><th class="p-4 font-bold text-gray-600 text-right">Actions</th>
                    </tr>
                    <?php foreach ( $brands as $brand ) : 
                        $base_price = $wpdb->get_var( $wpdb->prepare("SELECT price_mrr FROM {$wpdb->prefix}mt_packages WHERE package_slug = %s", $brand->package_slug) );
                        $total_mrr = floatval($base_price) + floatval($brand->custom_mrr);
                    ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="p-4 text-gray-500">#<?php echo esc_html($brand->id); ?></td>
                            <td class="p-4 font-bold text-gray-900"><?php echo esc_html($brand->brand_name); ?></td>
                            <td class="p-4"><span class="bg-blue-100 text-blue-800 py-1 px-3 rounded-full text-xs font-bold uppercase"><?php echo esc_html(str_replace('mt_', '', $brand->package_slug)); ?></span></td>
                            <td class="p-4 text-sm text-gray-600"><?php echo $brand->renewal_date ? esc_html(date('M j, Y', strtotime($brand->renewal_date))) : 'Not Set'; ?></td>
                            <td class="p-4 text-sm font-bold text-green-600">$<?php echo number_format($total_mrr, 2); ?></td>
                            <td class="p-4 text-right"><a href="?page=mt-tenants&edit=<?php echo $brand->id; ?>" class="bg-gray-200 text-gray-800 px-3 py-1 rounded text-sm font-bold hover:bg-gray-300 text-decoration-none">Manage Tenant</a></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        <?php
    }

    // --- VIEW: TENANT EDITOR (BILLING & OVERRIDES) ---
    public function render_tenant_editor( $brand_id ) {
        global $wpdb;
        $brand = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id ) );
        if ( ! $brand ) return;

        $packages = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}mt_packages");
        $base_pkg = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$wpdb->prefix}mt_packages WHERE package_slug = %s", $brand->package_slug) );
        if (!$base_pkg) $base_pkg = (object)['package_name' => 'Custom', 'price_mrr' => 0.00];

        $total_mrr = floatval($base_pkg->price_mrr) + floatval($brand->custom_mrr);
        $linked_users = get_users(array( 'meta_key' => 'mt_brand_id', 'meta_value' => $brand_id ));
        ?>
        <div class="wrap mt-admin-shell" style="margin-top: 20px;">
            <script src="https://cdn.tailwindcss.com"></script>
            <?php $this->render_admin_theme_style(); ?>
            <div class="mb-6">
                <a href="?page=mt-tenants" class="text-gray-500 font-bold text-decoration-none">&larr; Back to Tenants</a>
                <h1 class="text-2xl font-bold mt-2">Managing: <?php echo esc_html($brand->brand_name); ?></h1>
            </div>
            <?php if ( isset($_GET['updated']) ) : ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6"><p class="font-bold">Tenant limits and billing updated successfully!</p></div>
            <?php endif; ?>
            
            <div class="flex gap-6">
                <div class="w-2/3 bg-white rounded-lg shadow-sm border p-6">
                    <form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="POST">
                        <input type="hidden" name="action" value="mt_update_tenant">
                        <input type="hidden" name="brand_id" value="<?php echo esc_attr($brand->id); ?>">
                        <?php wp_nonce_field( 'mt_tenant_update', 'mt_tenant_nonce' ); ?>
                        
                        <h2 class="text-lg font-bold border-b pb-4 mb-4">Base Package</h2>
                        <div class="mb-6">
                            <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Assigned Plan</label>
                            <select name="package_slug" class="w-full p-2 border rounded font-bold">
                                <?php foreach($packages as $pkg): ?>
                                    <option value="<?php echo esc_attr($pkg->package_slug); ?>" <?php selected($brand->package_slug, $pkg->package_slug); ?>><?php echo esc_html($pkg->package_name); ?> (Base: $<?php echo esc_html($pkg->price_mrr); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-blue-600 mt-1 font-semibold"><i class="fa-solid fa-bolt"></i> Changing this plan will automatically update the limits below to match the new package.</p>
                        </div>

                        <h2 class="text-lg font-bold border-b pb-4 mb-4">Custom Limit Overrides</h2>
                        <div class="grid grid-cols-3 gap-4 mb-4 bg-gray-50 p-4 rounded border">
                            <div><label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Locations</label><input type="number" name="location_limit" class="w-full p-2 border rounded font-bold" value="<?php echo esc_attr($brand->location_limit); ?>"></div>
                            <div><label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Storage (MB)</label><input type="number" name="storage_limit_mb" class="w-full p-2 border rounded font-bold" value="<?php echo esc_attr($brand->storage_limit_mb); ?>"></div>
                            <div><label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Emails / Mo</label><input type="number" name="email_limit" class="w-full p-2 border rounded font-bold" value="<?php echo esc_attr($brand->email_limit); ?>"></div>
                        </div>

                        <div class="mb-6 bg-purple-50 p-4 rounded border border-purple-100 flex justify-between items-center">
                            <div>
                                <h3 class="font-bold text-purple-900 text-sm"><i class="fa-solid fa-key"></i> Premium API Sending</h3>
                                <p class="text-xs text-purple-700 mt-1">Allow this tenant to use SendGrid/Postmark API keys instead of Standard SMTP.</p>
                            </div>
                            <select name="api_sending_enabled" class="p-2 border rounded font-bold outline-none text-sm w-40">
                                <option value="0" <?php selected($brand->api_sending_enabled, 0); ?>>Restricted</option>
                                <option value="1" <?php selected($brand->api_sending_enabled, 1); ?>>Enabled</option>
                            </select>
                        </div>

                        <h2 class="text-lg font-bold border-b pb-4 mb-4">Billing Tracking</h2>
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div><label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Add-On Charges ($)</label><input type="number" step="0.01" name="custom_mrr" class="w-full p-2 border rounded font-bold" value="<?php echo esc_attr($brand->custom_mrr); ?>"></div>
                            <div><label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Next Renewal Date</label><input type="date" name="renewal_date" class="w-full p-2 border rounded font-bold" value="<?php echo esc_attr($brand->renewal_date); ?>"></div>
                        </div>

                        <h2 class="text-lg font-bold border-b pb-4 mb-4 mt-8">User Linking</h2>
                        <div class="mb-6">
                            <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Link existing WP User to this Brand</label>
                            <input type="email" name="link_user_email" class="w-full p-2 border rounded" placeholder="Enter user's existing email address...">
                            <p class="text-xs text-gray-500 mt-1">If they already registered, typing their email here will grant them access to this tenant dashboard.</p>
                        </div>

                        <button type="submit" class="mt-admin-btn-primary font-bold py-2 px-6 rounded">Save Overrides</button>
                    </form>

                    <div class="mt-12 pt-6 border-t border-red-100 bg-red-50 p-4 rounded text-right">
                        <form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="POST" onsubmit="return confirm('WARNING: This will permanently delete this brand and all associated settings. Proceed?');">
                            <input type="hidden" name="action" value="mt_delete_tenant">
                            <input type="hidden" name="brand_id" value="<?php echo esc_attr($brand->id); ?>">
                            <?php wp_nonce_field( 'mt_tenant_delete', 'mt_delete_nonce' ); ?>
                            <button type="submit" class="text-red-600 font-bold hover:text-red-800 border border-red-600 px-4 py-2 rounded">Delete Tenant Permanently</button>
                        </form>
                    </div>
                </div>

                <div class="w-1/3 space-y-4">
                    <div class="bg-white rounded-lg shadow-sm border p-6">
                        <h3 class="text-sm font-bold text-gray-500 uppercase mb-3">Linked Accounts</h3>
                        <?php if (empty($linked_users)): ?>
                            <p class="text-sm text-gray-400 italic">No users linked.</p>
                        <?php else: ?>
                            <ul class="space-y-2">
                            <?php foreach ($linked_users as $lu): ?>
                                <li class="text-sm bg-gray-50 p-2 rounded border font-bold text-gray-700 flex justify-between items-center">
                                    <span><i class="fa-solid fa-user text-gray-400 mr-2"></i> <?php echo esc_html($lu->user_email); ?></span>
                                </li>
                            <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div class="bg-gray-900 rounded-lg shadow-sm border border-gray-700 p-6 text-white">
                        <h3 class="text-sm font-bold text-gray-400 uppercase border-b border-gray-700 pb-2 mb-4">Total MRR Calculator</h3>
                        <div class="flex justify-between items-center mb-2"><span class="text-sm text-gray-300">Base Plan (<?php echo esc_html($base_pkg->package_name); ?>)</span><span class="font-mono font-bold">$<?php echo esc_html($base_pkg->price_mrr); ?></span></div>
                        <div class="flex justify-between items-center mb-4"><span class="text-sm text-gray-300">Custom Add-Ons</span><span class="font-mono font-bold text-green-400">+$<?php echo esc_html($brand->custom_mrr); ?></span></div>
                        <div class="flex justify-between items-center border-t border-gray-700 pt-4 mt-2"><span class="font-bold text-white uppercase">Total Billed / Mo</span><span class="text-2xl font-bold text-green-400">$<?php echo number_format($total_mrr, 2); ?></span></div>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm border p-6">
                        <h3 class="text-sm font-bold text-gray-500 uppercase border-b pb-2 mb-4">Current Allocation</h3>
                        <ul class="space-y-3 text-sm">
                            <li class="flex justify-between items-center"><span class="text-gray-600"><i class="fa-solid fa-store mr-2 text-gray-400"></i> Locations</span><span class="font-bold text-gray-900"><?php echo esc_html($brand->location_limit); ?></span></li>
                            <li class="flex justify-between items-center"><span class="text-gray-600"><i class="fa-solid fa-database mr-2 text-gray-400"></i> Vault Storage</span><span class="font-bold text-gray-900"><?php echo esc_html($brand->storage_limit_mb); ?> MB</span></li>
                            <li class="flex justify-between items-center"><span class="text-gray-600"><i class="fa-solid fa-envelope mr-2 text-gray-400"></i> Emails / Mo</span><span class="font-bold text-gray-900"><?php echo number_format($brand->email_limit); ?></span></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // --- FORM PROCESSORS ---
    public function save_tenant_limits() {
        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['mt_tenant_nonce'], 'mt_tenant_update' ) ) wp_die('Unauthorized Request.');
        global $wpdb;
        $brand_id = intval($_POST['brand_id']);
        
        // Smart Auto-Update Logic
        $old_brand = $wpdb->get_row("SELECT package_slug FROM {$wpdb->prefix}mt_brands WHERE id = $brand_id");
        $new_package_slug = sanitize_text_field($_POST['package_slug']);
        
        $loc_limit = intval($_POST['location_limit']);
        $storage_limit = intval($_POST['storage_limit_mb']);
        $email_limit = intval($_POST['email_limit']);
        $custom_mrr = floatval($_POST['custom_mrr']);
        
        if ( $old_brand && $old_brand->package_slug !== $new_package_slug ) {
            $new_pkg = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mt_packages WHERE package_slug = %s", $new_package_slug));
            if ($new_pkg) {
                $loc_limit = $new_pkg->location_limit;
                $storage_limit = $new_pkg->storage_limit_mb;
                $email_limit = $new_pkg->email_limit;
            }
        }

        $result = $wpdb->update( $wpdb->prefix . 'mt_brands', array( 
            'package_slug' => $new_package_slug,
            'location_limit' => $loc_limit,
            'storage_limit_mb' => $storage_limit,
            'email_limit' => $email_limit,
            'custom_mrr' => $custom_mrr,
            'api_sending_enabled' => intval($_POST['api_sending_enabled']), // AUDIT FIX: Save API Preference
            'renewal_date' => sanitize_text_field($_POST['renewal_date'])
        ), array( 'id' => $brand_id ) );

        if ( $result === false ) wp_die("Database Error updating limits: " . $wpdb->last_error);

        // Manual User Linker with strict error checking
        if ( !empty($_POST['link_user_email']) ) {
            $email = sanitize_email($_POST['link_user_email']);
            $user = get_user_by('email', $email);
            if ($user) {
                update_user_meta($user->ID, 'mt_brand_id', $brand_id);
                $user->set_role( $new_package_slug );
            } else {
                wp_die("Error: Could not find a WordPress user registered with the email: " . esc_html($email) . ". Please make sure they registered on your site first.");
            }
        }
        
        wp_redirect( admin_url( 'admin.php?page=mt-tenants&edit=' . $brand_id . '&updated=true' ) );
        exit;
    }

    public function delete_tenant() {
        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['mt_delete_nonce'], 'mt_tenant_delete' ) ) wp_die();
        global $wpdb;
        $brand_id = intval($_POST['brand_id']);
        $wpdb->delete( $wpdb->prefix . 'mt_brands', array( 'id' => $brand_id ) );
        $wpdb->delete( $wpdb->prefix . 'mt_stores', array( 'brand_id' => $brand_id ) );
        wp_redirect( admin_url( 'admin.php?page=mt-tenants&deleted=true' ) );
        exit;
    }

    public function process_provisioning() {
        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['mt_provision_nonce'], 'mt_tenant_provision' ) ) wp_die();
        global $wpdb;
        $brand_name = sanitize_text_field($_POST['brand_name']);
        $user_email = sanitize_email($_POST['user_email']);
        $user_pass = $_POST['user_pass'];
        $package_slug = sanitize_text_field($_POST['package_slug']);

        if ( email_exists($user_email) ) { wp_redirect( admin_url( 'admin.php?page=mt-tenants&error=Email+already+exists' ) ); exit; }

        $pkg = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mt_packages WHERE package_slug = %s", $package_slug));
        
        $wpdb->insert( $wpdb->prefix . 'mt_brands', array( 
            'brand_name' => $brand_name, 
            'package_slug' => $package_slug,
            'location_limit' => $pkg->location_limit,
            'storage_limit_mb' => $pkg->storage_limit_mb,
            'email_limit' => $pkg->email_limit,
            'custom_mrr' => 0.00,
            'api_sending_enabled' => 0, // Default to restricted on new accounts
            'renewal_date' => date('Y-m-d', strtotime('+1 month')),
            'primary_color' => '#E31E24'
        ));
        $new_brand_id = $wpdb->insert_id;

        $user_id = wp_create_user( $user_email, $user_pass, $user_email );
        $user = new WP_User( $user_id );
        $user->set_role( $package_slug );
        update_user_meta( $user_id, 'mt_brand_id', $new_brand_id );

        wp_redirect( admin_url( 'admin.php?page=mt-tenants&provisioned=true' ) );
        exit;
    }

    public function save_package() {
        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['mt_pkg_nonce'], 'mt_package_nonce' ) ) wp_die();
        global $wpdb;
        $id = intval($_POST['pkg_id']);

        // Build AI limits JSON from posted section fields
        $ai_limits = [];
        foreach ( MT_AI_Engine::get_sections() as $key => $label ) {
            $posted = $_POST[ 'ai_limit_' . $key ] ?? null;
            if ( $posted !== null ) {
                $ai_limits[ $key ] = max( -1, intval($posted) );
            }
        }

        $data = array(
            'package_name'     => sanitize_text_field($_POST['package_name']),
            'location_limit'   => intval($_POST['location_limit']),
            'storage_limit_mb' => intval($_POST['storage_limit_mb']),
            'email_limit'      => intval($_POST['email_limit']),
            'price_mrr'        => floatval($_POST['price_mrr']),
            'wifi_enabled'     => isset($_POST['wifi_enabled'])  ? intval($_POST['wifi_enabled'])  : 1,
            'email_enabled'    => isset($_POST['email_enabled']) ? intval($_POST['email_enabled']) : 1,
            'onboarding_flow'  => in_array($_POST['onboarding_flow'] ?? 'both', ['wifi','email','both','custom']) ? $_POST['onboarding_flow'] : 'both',
            'ai_limits_json'   => ! empty($ai_limits) ? wp_json_encode($ai_limits) : null,
        );
        if ($id > 0) {
            $wpdb->update( $wpdb->prefix . 'mt_packages', $data, array( 'id' => $id ) );
        } else {
            $data['package_slug'] = 'mt_' . sanitize_title($data['package_name']);
            $wpdb->insert( $wpdb->prefix . 'mt_packages', $data );
            add_role( $data['package_slug'], 'MailToucan ' . $data['package_name'], array( 'read' => true, 'access_mt_app' => true ) );
        }
        wp_redirect( admin_url( 'admin.php?page=mt-packages&pkg_saved=true' ) );
        exit;
    }

    public function save_dashboard_colors() {
        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['mt_dashboard_colors_nonce'], 'mt_save_dashboard_colors' ) ) {
            wp_die( 'Unauthorized Request.' );
        }

        $defaults = $this->get_default_dashboard_colors();
        $payload = array();

        foreach ( $defaults as $key => $default_hex ) {
            $candidate = isset( $_POST[ $key ] ) ? sanitize_hex_color( wp_unslash( $_POST[ $key ] ) ) : '';
            $payload[ $key ] = $candidate ? $candidate : $default_hex;
        }

        update_option( 'mt_dashboard_colors', $payload );
        wp_redirect( admin_url( 'admin.php?page=mt-dashboard-colors&updated=true' ) );
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AI SETTINGS
    // ─────────────────────────────────────────────────────────────────────────

    public function render_ai_settings() {
        $openai_key    = get_option( 'mt_openai_api_key',    '' );
        $anthropic_key = get_option( 'mt_anthropic_api_key', '' );
        $gemini_key    = get_option( 'mt_gemini_api_key',    '' );
        $ai_enabled    = get_option( 'mt_ai_enabled', '1' );
        ?>
        <div class="wrap mt-admin-shell" style="margin-top: 20px;">
            <script src="https://cdn.tailwindcss.com"></script>
            <?php $this->render_admin_theme_style(); ?>

            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">✨ AI Settings</h1>
                <p class="text-sm text-gray-600 mt-1">Configure platform AI provider keys. Set per-tenant limits under <a href="?page=mt-ai-limits" class="text-blue-600 font-bold hover:underline">AI Tenant Limits</a>.</p>
            </div>

            <?php if ( isset( $_GET['updated'] ) ) : ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                    <p class="font-bold">AI Settings saved successfully.</p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'mt_save_ai_settings', 'mt_ai_settings_nonce' ); ?>
                <input type="hidden" name="action" value="mt_save_ai_settings">

                <div class="grid grid-cols-1 gap-6 max-w-2xl">

                    <!-- Master On/Off -->
                    <div class="bg-white border-2 <?php echo $ai_enabled ? 'border-green-400' : 'border-red-300'; ?> rounded-xl p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-bold text-gray-900 text-lg">Platform AI — Master Switch</h3>
                                <p class="text-sm text-gray-500 mt-1">When disabled, all AI features are hidden from every tenant dashboard. Use this if you need to pause AI billing or run maintenance.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer ml-6">
                                <input type="checkbox" name="mt_ai_enabled" value="1" <?php checked($ai_enabled, '1'); ?> class="sr-only peer">
                                <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-green-500"></div>
                            </label>
                        </div>
                    </div>

                    <!-- OpenAI -->
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-[#10a37f] rounded-lg flex items-center justify-center text-white font-black text-sm">AI</div>
                            <div>
                                <h3 class="font-bold text-gray-900">OpenAI (GPT-4o mini)</h3>
                                <p class="text-xs text-gray-500">platform.openai.com/api-keys</p>
                            </div>
                            <?php if ( ! empty( $openai_key ) ) : ?>
                                <span class="ml-auto text-[11px] font-bold text-green-600 bg-green-50 px-3 py-1 rounded-full">● Connected</span>
                            <?php else: ?>
                                <span class="ml-auto text-[11px] font-bold text-gray-400 bg-gray-100 px-3 py-1 rounded-full">Not Set</span>
                            <?php endif; ?>
                        </div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">API Key</label>
                        <input type="password" name="mt_openai_api_key" value="<?php echo esc_attr( $openai_key ); ?>"
                               placeholder="sk-proj-..." autocomplete="new-password"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-gray-500">
                    </div>

                    <!-- Anthropic -->
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-[#c57540] rounded-lg flex items-center justify-center text-white font-black text-sm">C</div>
                            <div>
                                <h3 class="font-bold text-gray-900">Anthropic (Claude Haiku)</h3>
                                <p class="text-xs text-gray-500">console.anthropic.com/api-keys</p>
                            </div>
                            <?php if ( ! empty( $anthropic_key ) ) : ?>
                                <span class="ml-auto text-[11px] font-bold text-green-600 bg-green-50 px-3 py-1 rounded-full">● Connected</span>
                            <?php else: ?>
                                <span class="ml-auto text-[11px] font-bold text-gray-400 bg-gray-100 px-3 py-1 rounded-full">Not Set</span>
                            <?php endif; ?>
                        </div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">API Key</label>
                        <input type="password" name="mt_anthropic_api_key" value="<?php echo esc_attr( $anthropic_key ); ?>"
                               placeholder="sk-ant-api03-..." autocomplete="new-password"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-gray-500">
                    </div>

                    <!-- Gemini -->
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-[#4285f4] rounded-lg flex items-center justify-center text-white font-black text-sm">G</div>
                            <div>
                                <h3 class="font-bold text-gray-900">Google Gemini 2.0 Flash</h3>
                                <p class="text-xs text-gray-500">aistudio.google.com/app/apikey</p>
                            </div>
                            <?php if ( ! empty( $gemini_key ) ) : ?>
                                <span class="ml-auto text-[11px] font-bold text-green-600 bg-green-50 px-3 py-1 rounded-full">● Connected</span>
                            <?php else: ?>
                                <span class="ml-auto text-[11px] font-bold text-gray-400 bg-gray-100 px-3 py-1 rounded-full">Not Set</span>
                            <?php endif; ?>
                        </div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">API Key</label>
                        <input type="password" name="mt_gemini_api_key" value="<?php echo esc_attr( $gemini_key ); ?>"
                               placeholder="AIza..." autocomplete="new-password"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-gray-500">
                    </div>

                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
                        <strong>Note:</strong> You only need to connect the providers you want to offer tenants. Leave unused keys blank. Each key is stored in <code>wp_options</code> and never returned to the browser after saving.
                    </div>

                    <div>
                        <button type="submit" class="mt-admin-btn-primary px-8 py-3 rounded-lg font-bold text-sm shadow-sm">Save AI Settings</button>
                    </div>

                </div>
            </form>
        </div>
        <?php
    }

    public function save_ai_settings() {
        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['mt_ai_settings_nonce'], 'mt_save_ai_settings' ) ) {
            wp_die( 'Unauthorized Request.' );
        }

        $openai_key    = sanitize_text_field( wp_unslash( $_POST['mt_openai_api_key']    ?? '' ) );
        $anthropic_key = sanitize_text_field( wp_unslash( $_POST['mt_anthropic_api_key'] ?? '' ) );
        $gemini_key    = sanitize_text_field( wp_unslash( $_POST['mt_gemini_api_key']    ?? '' ) );

        // Only overwrite if a non-empty value was submitted (prevents clearing key when left blank)
        if ( ! empty( $openai_key ) )    update_option( 'mt_openai_api_key',    $openai_key );
        if ( ! empty( $anthropic_key ) ) update_option( 'mt_anthropic_api_key', $anthropic_key );
        if ( ! empty( $gemini_key ) )    update_option( 'mt_gemini_api_key',    $gemini_key );

        // Master AI toggle — checkbox unchecked = not in POST = disabled
        update_option( 'mt_ai_enabled', isset($_POST['mt_ai_enabled']) ? '1' : '0' );

        wp_redirect( admin_url( 'admin.php?page=mt-ai-settings&updated=true' ) );
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AI TENANT LIMITS PAGE
    // ─────────────────────────────────────────────────────────────────────────

    public function render_ai_limits() {
        global $wpdb;
        $brands   = $wpdb->get_results("SELECT id, brand_name, package_slug, brand_config FROM {$wpdb->prefix}mt_brands ORDER BY brand_name ASC");
        $sections = MT_AI_Engine::get_sections();
        $defaults = MT_AI_Engine::get_default_limits();
        $period   = current_time('Y-m');
        $ai_table = $wpdb->prefix . 'mt_ai_usage';
        ?>
        <div class="wrap mt-admin-shell" style="margin-top: 20px;">
            <script src="https://cdn.tailwindcss.com"></script>
            <?php $this->render_admin_theme_style(); ?>

            <div class="mb-6 flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">📊 AI Tenant Limits</h1>
                    <p class="text-sm text-gray-600 mt-1">Override AI call limits per section for individual tenants. Blank = use package default. -1 = unlimited. Resets on the 1st of each month.</p>
                </div>
                <a href="?page=mt-ai-settings" class="text-blue-600 font-bold text-sm hover:underline mt-1">← AI Key Settings</a>
            </div>

            <?php if ( isset($_GET['saved']) ) : ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded font-bold">Limits saved.</div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                <input type="hidden" name="action" value="mt_save_ai_tenant_limits">
                <?php wp_nonce_field('mt_ai_tenant_limits_nonce', 'mt_aitl_nonce'); ?>

                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b text-[10px] uppercase text-gray-500 font-bold tracking-wider">
                                <tr>
                                    <th class="p-4 text-left">Tenant</th>
                                    <th class="p-4 text-left">Plan</th>
                                    <?php foreach($sections as $key => $label): ?>
                                        <th class="p-3 text-center min-w-[110px]"><?php echo esc_html($label); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($brands as $brand):
                                    $cfg       = json_decode($brand->brand_config ?? '{}', true) ?: [];
                                    $overrides = $cfg['ai_limits'] ?? [];
                                    // Pull current month usage for each section
                                    $usage_rows = $wpdb->get_results( $wpdb->prepare(
                                        "SELECT section, calls_used FROM $ai_table WHERE brand_id = %d AND period = %s",
                                        $brand->id, $period
                                    ), OBJECT_K );
                                ?>
                                <tr class="border-b hover:bg-gray-50 transition">
                                    <td class="p-4">
                                        <p class="font-bold text-gray-900"><?php echo esc_html($brand->brand_name); ?></p>
                                        <p class="text-[10px] text-gray-400">#<?php echo $brand->id; ?></p>
                                    </td>
                                    <td class="p-4">
                                        <span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded text-[10px] font-bold uppercase">
                                            <?php echo esc_html(str_replace('mt_', '', $brand->package_slug)); ?>
                                        </span>
                                    </td>
                                    <?php foreach ($sections as $key => $label):
                                        $override_val = $overrides[$key] ?? '';
                                        $used = isset($usage_rows[$key]) ? (int)$usage_rows[$key]->calls_used : 0;
                                        $pkg_limit = $defaults[$key]; // TODO: pull from package
                                        $display_limit = $override_val !== '' ? $override_val : $pkg_limit;
                                    ?>
                                    <td class="p-2 text-center">
                                        <input type="number" min="-1"
                                               name="ai_override[<?php echo $brand->id; ?>][<?php echo esc_attr($key); ?>]"
                                               value="<?php echo $override_val !== '' ? esc_attr($override_val) : ''; ?>"
                                               placeholder="<?php echo esc_attr($pkg_limit); ?>"
                                               class="w-full p-1.5 border rounded text-xs font-mono text-center focus:border-purple-400 outline-none">
                                        <div class="text-[9px] text-gray-400 mt-0.5"><?php echo $used; ?> / <?php echo $display_limit == -1 ? '∞' : $display_limit; ?> used</div>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-6 text-right">
                    <button type="submit" class="mt-admin-btn-primary px-8 py-3 rounded-lg font-bold shadow-sm">Save All Overrides</button>
                </div>
            </form>
        </div>
        <?php
    }

    public function save_ai_tenant_limits() {
        if ( ! current_user_can('manage_options') || ! wp_verify_nonce( $_POST['mt_aitl_nonce'], 'mt_ai_tenant_limits_nonce' ) ) wp_die();
        global $wpdb;

        $overrides = $_POST['ai_override'] ?? [];
        $sections  = array_keys( MT_AI_Engine::get_sections() );

        foreach ($overrides as $brand_id => $section_limits) {
            $brand_id = intval($brand_id);
            $brand    = $wpdb->get_row( $wpdb->prepare("SELECT brand_config FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id) );
            $cfg      = json_decode($brand->brand_config ?? '{}', true) ?: [];

            $new_limits = [];
            foreach ($sections as $key) {
                $val = $section_limits[$key] ?? '';
                if ($val !== '') {
                    $new_limits[$key] = max(-1, intval($val));
                }
            }

            if (empty($new_limits)) {
                unset($cfg['ai_limits']);
            } else {
                $cfg['ai_limits'] = $new_limits;
            }

            $wpdb->update(
                $wpdb->prefix . 'mt_brands',
                ['brand_config' => wp_json_encode($cfg)],
                ['id' => $brand_id]
            );
        }

        wp_redirect( admin_url('admin.php?page=mt-ai-limits&saved=true') );
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EMAIL SETTINGS — Global + Per-Tenant Kill Switches
    // ─────────────────────────────────────────────────────────────────────────

    public function render_email_settings() {
        $this->ensure_database_columns_exist();
        global $wpdb;
        $email_enabled = get_option( 'mt_email_enabled', '1' );
        $brands = $wpdb->get_results("SELECT id, brand_name, package_slug, email_paused FROM {$wpdb->prefix}mt_brands ORDER BY brand_name ASC");
        ?>
        <div class="wrap mt-admin-shell" style="margin-top: 20px;">
            <script src="https://cdn.tailwindcss.com"></script>
            <?php $this->render_admin_theme_style(); ?>

            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">✉️ Email Settings</h1>
                <p class="text-sm text-gray-600 mt-1">Control the global email engine and pause individual tenant email sending.</p>
            </div>

            <?php if ( isset($_GET['saved']) ) : ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded font-bold">Email settings saved.</div>
            <?php endif; ?>

            <!-- GLOBAL KILL SWITCH -->
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                <?php wp_nonce_field('mt_save_email_settings', 'mt_email_settings_nonce'); ?>
                <input type="hidden" name="action" value="mt_save_email_settings">

                <div class="bg-white border-2 <?php echo $email_enabled ? 'border-green-400' : 'border-red-400'; ?> rounded-xl p-6 shadow-sm max-w-2xl mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900 text-lg">Platform Email — Global Kill Switch</h3>
                            <p class="text-sm text-gray-500 mt-1">When OFF, <strong>no emails will be sent by any tenant</strong> — campaigns, workflows, and automations all halt. Use for maintenance, deliverability issues, or billing holds.</p>
                            <?php if ( ! $email_enabled ) : ?>
                                <div class="mt-3 bg-red-50 text-red-700 text-sm font-bold px-4 py-2 rounded-lg border border-red-200 inline-flex items-center gap-2">
                                    <i class="fa-solid fa-circle-exclamation"></i> Email is currently DISABLED — no emails are being sent.
                                </div>
                            <?php endif; ?>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer ml-6">
                            <input type="checkbox" name="mt_email_enabled" value="1" <?php checked($email_enabled, '1'); ?> class="sr-only peer">
                            <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-green-500"></div>
                        </label>
                    </div>
                    <div class="mt-5 pt-4 border-t border-gray-100 text-right">
                        <button type="submit" class="mt-admin-btn-primary px-6 py-2 rounded-lg font-bold text-sm">Save Global Setting</button>
                    </div>
                </div>
            </form>

            <!-- PER-TENANT EMAIL PAUSE -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden max-w-2xl">
                <div class="p-4 bg-gray-50 border-b border-gray-200">
                    <h2 class="font-bold text-gray-900">Per-Tenant Email Pause</h2>
                    <p class="text-xs text-gray-500 mt-1">Pause email sending for a specific tenant without affecting others.</p>
                </div>
                <table class="w-full text-sm">
                    <thead class="border-b bg-gray-50 text-[10px] uppercase text-gray-500 font-bold tracking-wider">
                        <tr>
                            <th class="p-4 text-left">Tenant</th>
                            <th class="p-4 text-left">Plan</th>
                            <th class="p-4 text-center">Email Status</th>
                            <th class="p-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($brands as $brand):
                            $paused = intval($brand->email_paused ?? 0);
                        ?>
                        <tr class="border-b hover:bg-gray-50 transition">
                            <td class="p-4">
                                <p class="font-bold text-gray-900"><?php echo esc_html($brand->brand_name); ?></p>
                                <p class="text-[10px] text-gray-400">#<?php echo $brand->id; ?></p>
                            </td>
                            <td class="p-4">
                                <span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded text-[10px] font-bold uppercase">
                                    <?php echo esc_html(str_replace('mt_', '', $brand->package_slug)); ?>
                                </span>
                            </td>
                            <td class="p-4 text-center">
                                <?php if ($paused): ?>
                                    <span class="bg-red-100 text-red-700 border border-red-200 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest">⛔ Paused</span>
                                <?php else: ?>
                                    <span class="bg-green-100 text-green-700 border border-green-200 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest">✅ Active</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-center">
                                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" class="inline">
                                    <?php wp_nonce_field('mt_pause_tenant_email_' . $brand->id, 'mt_pte_nonce'); ?>
                                    <input type="hidden" name="action"   value="mt_pause_tenant_email">
                                    <input type="hidden" name="brand_id" value="<?php echo esc_attr($brand->id); ?>">
                                    <input type="hidden" name="paused"   value="<?php echo $paused ? '0' : '1'; ?>">
                                    <button type="submit" class="<?php echo $paused ? 'bg-green-600 hover:bg-green-700' : 'bg-red-500 hover:bg-red-600'; ?> text-white text-xs font-bold px-4 py-1.5 rounded-lg transition">
                                        <?php echo $paused ? 'Resume Email' : 'Pause Email'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function save_email_settings() {
        if ( ! current_user_can('manage_options') || ! wp_verify_nonce( $_POST['mt_email_settings_nonce'], 'mt_save_email_settings' ) ) wp_die('Unauthorized.');
        update_option( 'mt_email_enabled', isset($_POST['mt_email_enabled']) ? '1' : '0' );
        wp_redirect( admin_url('admin.php?page=mt-email-settings&saved=true') );
        exit;
    }

    public function save_tenant_email_pause() {
        $brand_id = intval($_POST['brand_id'] ?? 0);
        if ( ! current_user_can('manage_options') || ! wp_verify_nonce( $_POST['mt_pte_nonce'], 'mt_pause_tenant_email_' . $brand_id ) ) wp_die('Unauthorized.');
        global $wpdb;
        $wpdb->update( $wpdb->prefix . 'mt_brands', ['email_paused' => intval($_POST['paused'])], ['id' => $brand_id] );
        wp_redirect( admin_url('admin.php?page=mt-email-settings&saved=true') );
        exit;
    }
}