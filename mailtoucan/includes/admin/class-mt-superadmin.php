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
    }

    public function register_super_admin_menu() {
        add_menu_page( 'Toucan Engine', 'Toucan Engine', 'manage_options', 'mt-engine', array( $this, 'render_dashboard' ), 'dashicons-cloud', 2 );
        add_submenu_page( 'mt-engine', 'Tenants', 'Tenant Manager', 'manage_options', 'mt-tenants', array( $this, 'render_tenant_manager' ) );
        add_submenu_page( 'mt-engine', 'Packages', 'Package Manager', 'manage_options', 'mt-packages', array( $this, 'render_package_manager' ) );
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

        // 2. Repair mt_stores table
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
        <div class="wrap" style="margin-top: 20px;">
            <script src="https://cdn.tailwindcss.com"></script>
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
                        <a href="?page=mt-tenants&action=provision" class="bg-purple-600 text-white px-4 py-2 rounded text-center font-bold text-sm hover:bg-purple-700 text-decoration-none">Provision New Account</a>
                    </div>
                </div>
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
        <div class="wrap" style="margin-top: 20px;">
            <script src="https://cdn.tailwindcss.com"></script>
            <div class="mb-6"><h1 class="text-2xl font-bold">SaaS Package Manager</h1></div>
            <?php if ( isset($_GET['pkg_saved']) ) : ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6"><p class="font-bold">Package saved successfully!</p></div>
            <?php endif; ?>
            <div class="flex gap-8">
                <div class="w-1/2">
                    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <tr class="bg-gray-50 border-b"><th class="p-4 font-bold text-gray-600">Tier Name</th><th class="p-4 font-bold text-gray-600">Price/mo</th><th class="p-4 font-bold text-gray-600 text-right">Actions</th></tr>
                            <?php foreach($packages as $pkg): ?>
                            <tr class="border-b hover:bg-gray-50 <?php echo ($edit_id === intval($pkg->id)) ? 'bg-blue-50' : ''; ?>">
                                <td class="p-4 font-bold text-gray-900"><?php echo esc_html($pkg->package_name); ?></td>
                                <td class="p-4 text-green-600 font-bold">$<?php echo esc_html($pkg->price_mrr); ?></td>
                                <td class="p-4 text-right"><a href="?page=mt-packages&edit_pkg=<?php echo $pkg->id; ?>" class="bg-gray-200 text-gray-800 px-3 py-1 rounded text-sm font-bold hover:bg-gray-300 text-decoration-none">Edit Limits</a></td>
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
                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div><label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Emails / Month</label><input type="number" name="email_limit" class="w-full p-2 border rounded" value="<?php echo $edit_pkg ? esc_attr($edit_pkg->email_limit) : '1000'; ?>"></div>
                                <div><label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Monthly Price ($)</label><input type="number" step="0.01" name="price_mrr" class="w-full p-2 border rounded text-green-600 font-bold" value="<?php echo $edit_pkg ? esc_attr($edit_pkg->price_mrr) : '0.00'; ?>"></div>
                            </div>
                            <div class="text-right">
                                <?php if ($edit_pkg): ?><a href="?page=mt-packages" class="text-gray-500 font-bold mr-4 text-decoration-none">Cancel Edit</a><?php endif; ?>
                                <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-6 rounded hover:bg-blue-700">Save Package</button>
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
        <div class="wrap" style="margin-top: 20px;">
            <script src="https://cdn.tailwindcss.com"></script>
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
                        <button type="submit" class="bg-green-600 text-white font-bold py-3 px-8 rounded-lg hover:bg-green-700 shadow-md">Create Tenant Environment</button>
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
        <div class="wrap" style="margin-top: 20px;">
            <script src="https://cdn.tailwindcss.com"></script>
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Tenant Manager</h1>
                <a href="?page=mt-tenants&action=provision" class="bg-green-600 text-white px-4 py-2 rounded font-bold hover:bg-green-700 text-decoration-none">+ Provision New Tenant</a>
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
        <div class="wrap" style="margin-top: 20px;">
            <script src="https://cdn.tailwindcss.com"></script>
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
                        <div class="grid grid-cols-3 gap-4 mb-6 bg-gray-50 p-4 rounded border">
                            <div><label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Locations</label><input type="number" name="location_limit" class="w-full p-2 border rounded font-bold" value="<?php echo esc_attr($brand->location_limit); ?>"></div>
                            <div><label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Storage (MB)</label><input type="number" name="storage_limit_mb" class="w-full p-2 border rounded font-bold" value="<?php echo esc_attr($brand->storage_limit_mb); ?>"></div>
                            <div><label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Emails / Mo</label><input type="number" name="email_limit" class="w-full p-2 border rounded font-bold" value="<?php echo esc_attr($brand->email_limit); ?>"></div>
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

                        <button type="submit" class="bg-gray-900 text-white font-bold py-2 px-6 rounded hover:bg-gray-800">Save Overrides</button>
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
        $data = array(
            'package_name' => sanitize_text_field($_POST['package_name']),
            'location_limit' => intval($_POST['location_limit']),
            'storage_limit_mb' => intval($_POST['storage_limit_mb']),
            'email_limit' => intval($_POST['email_limit']),
            'price_mrr' => floatval($_POST['price_mrr'])
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
}