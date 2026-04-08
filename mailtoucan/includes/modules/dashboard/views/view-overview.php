<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_leads = $wpdb->prefix . 'mt_guest_leads';
$table_stores = $wpdb->prefix . 'mt_stores';

// Fetch global account metrics
$total_flock = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_leads WHERE brand_id = %d", $brand->id));
$total_locations = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_stores WHERE brand_id = %d", $brand->id));

// Mock Quota Data (In Phase 3, this will pull from a billing table)
$monthly_limit = 50000;
$emails_sent_this_month = 12450;
$quota_percentage = ($emails_sent_this_month / $monthly_limit) * 100;

// FETCH DYNAMIC BRANDING
$brand_color = !empty($brand->primary_color) ? $brand->primary_color : '#0f172a';
$mt_palette = get_option( 'mt_brand_palette', ['accent' => '#FCC753', 'dark' => '#1A232E'] );
$current_user = wp_get_current_user();
$first_name = !empty($current_user->user_firstname) ? $current_user->user_firstname : $current_user->display_name;
?>

<style>
    :root {
        --mt-brand: <?php echo esc_html($brand_color); ?>;
        --mt-accent: <?php echo esc_html($mt_palette['accent']); ?>;
    }
</style>

<div class="max-w-7xl mx-auto">
    <header class="mb-10 flex justify-between items-end bg-white p-8 rounded-3xl shadow-sm border border-gray-200 relative overflow-hidden">
        <div class="relative z-10">
            <p class="text-sm font-bold uppercase tracking-widest text-gray-400 mb-1">Global Account Status</p>
            <h1 class="text-3xl font-black text-gray-900">Hello, <?php echo esc_html($first_name); ?> 👋</h1>
            <p class="text-gray-500 mt-2 font-medium">Here is the top-level overview of your MailToucan account.</p>
        </div>
        <div class="flex gap-3 relative z-10">
            <button onclick="alert('Billing portal coming soon!')" class="bg-gray-50 border border-gray-200 text-gray-700 px-6 py-3 rounded-xl font-bold shadow-sm hover:bg-white transition flex items-center gap-2">
                <i class="fa-solid fa-credit-card"></i> Manage Plan
            </button>
        </div>
        <div class="absolute right-0 top-0 bottom-0 w-64 opacity-5 pointer-events-none flex items-center justify-center transform translate-x-10">
            <i class="fa-solid fa-chart-pie text-[150px]" style="color: var(--mt-brand);"></i>
        </div>
    </header>

    <div class="grid grid-cols-4 gap-6 mb-10">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 relative overflow-hidden group">
            <div class="absolute top-4 right-4 w-10 h-10 bg-indigo-50 text-indigo-500 rounded-full flex items-center justify-center text-lg"><i class="fa-solid fa-crown"></i></div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Current Plan</p>
            <h3 class="text-3xl font-black text-gray-900 mb-1">Pro Tier</h3>
            <p class="text-xs text-green-500 font-bold flex items-center gap-1"><i class="fa-solid fa-check-circle"></i> Active & Healthy</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 relative overflow-hidden group">
            <div class="absolute top-4 right-4 w-10 h-10 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center text-lg"><i class="fa-solid fa-store"></i></div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Connected Locations</p>
            <h3 class="text-3xl font-black text-gray-900 mb-1"><?php echo number_format($total_locations); ?></h3>
            <p class="text-xs text-gray-400 font-medium">Active WiFi Zones</p>
        </div>
        
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 relative overflow-hidden group">
            <div class="absolute top-4 right-4 w-10 h-10 bg-purple-50 text-purple-500 rounded-full flex items-center justify-center text-lg"><i class="fa-solid fa-users"></i></div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Global Flock Size</p>
            <h3 class="text-3xl font-black text-gray-900 mb-1"><?php echo number_format($total_flock); ?></h3>
            <p class="text-xs text-gray-400 font-medium">Total Captured Guests</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 relative overflow-hidden group">
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Monthly Email Quota</p>
            <h3 class="text-xl font-black text-gray-900 mb-3"><?php echo number_format($emails_sent_this_month); ?> <span class="text-sm font-medium text-gray-400">/ <?php echo number_format($monthly_limit); ?></span></h3>
            
            <div class="w-full bg-gray-100 rounded-full h-2 mb-2">
                <div class="bg-indigo-500 h-2 rounded-full" style="width: <?php echo esc_attr($quota_percentage); ?>%"></div>
            </div>
            <p class="text-[10px] font-bold text-gray-500 text-right"><?php echo round($quota_percentage, 1); ?>% Used</p>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-8">
        <h2 class="text-lg font-black text-gray-900 mb-6">Quick Links</h2>
        <div class="grid grid-cols-3 gap-6">
            <a href="?view=brand" class="flex items-start gap-4 p-4 rounded-xl border border-gray-100 hover:border-indigo-200 hover:bg-indigo-50 transition group">
                <div class="w-10 h-10 rounded-full bg-gray-100 group-hover:bg-indigo-100 text-gray-500 group-hover:text-indigo-600 flex items-center justify-center shrink-0 transition"><i class="fa-solid fa-palette"></i></div>
                <div>
                    <h4 class="font-bold text-gray-900 text-sm">Brand Identity</h4>
                    <p class="text-xs text-gray-500 mt-1">Update your logos and core colors.</p>
                </div>
            </a>
            <a href="?view=locations" class="flex items-start gap-4 p-4 rounded-xl border border-gray-100 hover:border-blue-200 hover:bg-blue-50 transition group">
                <div class="w-10 h-10 rounded-full bg-gray-100 group-hover:bg-blue-100 text-gray-500 group-hover:text-blue-600 flex items-center justify-center shrink-0 transition"><i class="fa-solid fa-network-wired"></i></div>
                <div>
                    <h4 class="font-bold text-gray-900 text-sm">Manage Routers</h4>
                    <p class="text-xs text-gray-500 mt-1">Add new venues to your network.</p>
                </div>
            </a>
            <a href="?view=crm" class="flex items-start gap-4 p-4 rounded-xl border border-gray-100 hover:border-purple-200 hover:bg-purple-50 transition group">
                <div class="w-10 h-10 rounded-full bg-gray-100 group-hover:bg-purple-100 text-gray-500 group-hover:text-purple-600 flex items-center justify-center shrink-0 transition"><i class="fa-solid fa-users"></i></div>
                <div>
                    <h4 class="font-bold text-gray-900 text-sm">View CRM (The Roost)</h4>
                    <p class="text-xs text-gray-500 mt-1">Export or manage your guest data.</p>
                </div>
            </a>
        </div>
    </div>
</div>