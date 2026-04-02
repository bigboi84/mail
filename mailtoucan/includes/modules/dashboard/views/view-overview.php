<?php
// Secure the view
if ( ! defined( 'ABSPATH' ) ) exit;

// We have access to $brand and $wpdb from the router
$total_stores = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(id) FROM {$wpdb->prefix}mt_stores WHERE brand_id = %d", $brand->id) );
$total_roost = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(id) FROM {$wpdb->prefix}mt_roost WHERE brand_id = %d", $brand->id) );

// For UI demonstration, mock emails sent until we build the Mail.baby engine
$emails_sent = 0; 
$storage_used_mb = round($brand->storage_used_kb / 1024, 2);

// Calculate Percentages for Progress Bars
$loc_percent = ($brand->location_limit > 0) ? ($total_stores / $brand->location_limit) * 100 : 0;
$store_percent = ($brand->storage_limit_mb > 0) ? ($storage_used_mb / $brand->storage_limit_mb) * 100 : 0;
$email_percent = ($brand->email_limit > 0) ? ($emails_sent / $brand->email_limit) * 100 : 0;

$plan_name = str_replace('mt_', '', $brand->package_slug);
?>

<header class="mb-8 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Welcome back, <?php echo esc_html($brand->brand_name); ?>!</h1>
        <p class="text-gray-500">Here is your current account status and system limits.</p>
    </div>
    
    <div class="bg-white px-5 py-3 rounded-xl shadow-sm border border-gray-200 flex items-center gap-4 text-right">
        <div>
            <p class="text-[10px] uppercase tracking-widest font-bold text-gray-400 mb-1">Current Plan</p>
            <p class="text-sm font-bold text-gray-900 uppercase"><?php echo esc_html($plan_name); ?></p>
        </div>
        <div class="h-8 w-px bg-gray-200"></div>
        <div>
            <p class="text-[10px] uppercase tracking-widest font-bold text-gray-400 mb-1">Renewal Date</p>
            <p class="text-sm font-bold text-blue-600">
                <?php echo $brand->renewal_date ? esc_html(date('M j, Y', strtotime($brand->renewal_date))) : 'N/A'; ?>
            </p>
        </div>
    </div>
</header>

<div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <h2 class="text-lg font-bold text-gray-800 border-b pb-4 mb-6"><i class="fa-solid fa-server text-gray-400 mr-2"></i> Allocation Limits</h2>
        
        <div class="mb-6">
            <div class="flex justify-between items-end mb-2">
                <span class="text-sm font-bold text-gray-700">Active Locations</span>
                <span class="text-xs font-bold text-gray-500"><?php echo esc_html($total_stores); ?> / <?php echo esc_html($brand->location_limit); ?></span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-2.5">
                <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-500" style="width: <?php echo min(100, $loc_percent); ?>%"></div>
            </div>
            <?php if ($total_stores >= $brand->location_limit) : ?>
                <p class="text-xs text-red-500 mt-2 font-semibold"><i class="fa-solid fa-triangle-exclamation"></i> Location limit reached. Contact support to upgrade.</p>
            <?php endif; ?>
        </div>

        <div class="mb-6">
            <div class="flex justify-between items-end mb-2">
                <span class="text-sm font-bold text-gray-700">Media Vault Storage</span>
                <span class="text-xs font-bold text-gray-500"><?php echo $storage_used_mb; ?> MB / <?php echo esc_html($brand->storage_limit_mb); ?> MB</span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-2.5">
                <div class="bg-indigo-500 h-2.5 rounded-full transition-all duration-500" style="width: <?php echo min(100, $store_percent); ?>%"></div>
            </div>
        </div>

        <div>
            <div class="flex justify-between items-end mb-2">
                <span class="text-sm font-bold text-gray-700">Monthly Emails Sent</span>
                <span class="text-xs font-bold text-gray-500"><?php echo number_format($emails_sent); ?> / <?php echo number_format($brand->email_limit); ?></span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-2.5">
                <div class="bg-green-500 h-2.5 rounded-full transition-all duration-500" style="width: <?php echo min(100, $email_percent); ?>%"></div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Limits reset on your billing renewal date.</p>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="text-gray-500 text-sm font-bold uppercase tracking-wider mb-1">Total CRM Subscribers</h3>
                <p class="text-4xl font-bold text-gray-900"><?php echo number_format($total_roost); ?></p>
            </div>
            <div class="bg-orange-50 text-orange-500 p-4 rounded-xl"><i class="fa-solid fa-users text-3xl"></i></div>
        </div>
        
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between opacity-75">
            <div>
                <h3 class="text-gray-500 text-sm font-bold uppercase tracking-wider mb-1">Emails Opened (30 Days)</h3>
                <p class="text-4xl font-bold text-gray-900">0</p>
            </div>
            <div class="bg-blue-50 text-blue-500 p-4 rounded-xl"><i class="fa-solid fa-envelope-open-text text-3xl"></i></div>
        </div>
    </div>

</div>

<div class="bg-gradient-to-r from-gray-900 to-gray-800 rounded-xl shadow-lg border border-gray-700 p-8 text-white flex items-center justify-between">
    <div>
        <h2 class="text-xl font-bold mb-2">Ready to scale up?</h2>
        <p class="text-gray-400 text-sm max-w-lg">If you are hitting your storage or location limits, you can easily upgrade your plan to unlock more bandwidth and premium Enterprise features.</p>
    </div>
    <button class="bg-white text-gray-900 px-6 py-3 rounded-lg font-bold shadow-md hover:bg-gray-100 transition">
        Upgrade Plan
    </button>
</div>