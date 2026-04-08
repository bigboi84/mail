<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_campaigns = $wpdb->prefix . 'mt_campaigns';

// Fetch Email specific data
$recent_campaigns = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_campaigns WHERE brand_id = %d ORDER BY created_at DESC LIMIT 5", $brand->id));

// FETCH DYNAMIC BRANDING
$brand_color = !empty($brand->primary_color) ? $brand->primary_color : '#0f172a';
$mt_palette = get_option( 'mt_brand_palette', ['accent' => '#FCC753', 'dark' => '#1A232E'] );
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
            <p class="text-sm font-bold uppercase tracking-widest text-gray-400 mb-1">Email Marketing Engine</p>
            <h1 class="text-3xl font-black text-gray-900">Dashboard Insights</h1>
            <p class="text-gray-500 mt-2 font-medium">Track your engagement, recent flight logs, and automation health.</p>
        </div>
        <div class="flex gap-3 relative z-10">
            <a href="?view=studio" class="bg-gray-50 border border-gray-200 text-gray-700 px-6 py-3 rounded-xl font-bold shadow-sm hover:bg-white transition flex items-center gap-2">
                <i class="fa-solid fa-palette"></i> Open Studio
            </a>
            <a href="?view=campaigns" class="text-white px-6 py-3 rounded-xl font-black shadow-lg hover:opacity-90 transition flex items-center gap-2" style="background-color: var(--mt-brand);">
                <i class="fa-solid fa-rocket"></i> Launch Campaign
            </a>
        </div>
        <div class="absolute right-0 top-0 bottom-0 w-64 opacity-5 pointer-events-none flex items-center justify-center transform translate-x-10">
            <i class="fa-solid fa-envelope-open-text text-[150px]" style="color: var(--mt-brand);"></i>
        </div>
    </header>

    <div class="grid grid-cols-4 gap-6 mb-10">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 relative overflow-hidden group">
            <div class="absolute top-4 right-4 w-10 h-10 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center text-lg"><i class="fa-solid fa-paper-plane"></i></div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Total Delivered</p>
            <h3 class="text-3xl font-black text-gray-900 mb-1">0</h3>
            <p class="text-xs text-gray-400 font-medium">All Time</p>
        </div>
        
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 relative overflow-hidden group">
            <div class="absolute top-4 right-4 w-10 h-10 bg-green-50 text-green-500 rounded-full flex items-center justify-center text-lg"><i class="fa-solid fa-envelope-open"></i></div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Avg Open Rate</p>
            <h3 class="text-3xl font-black text-gray-900 mb-1">0.0%</h3>
            <p class="text-xs text-gray-400 font-medium">Industry Avg: 21%</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 relative overflow-hidden group">
            <div class="absolute top-4 right-4 w-10 h-10 bg-purple-50 text-purple-500 rounded-full flex items-center justify-center text-lg"><i class="fa-solid fa-hand-pointer"></i></div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Avg Click Rate</p>
            <h3 class="text-3xl font-black text-gray-900 mb-1">0.0%</h3>
            <p class="text-xs text-gray-400 font-medium">Industry Avg: 2.5%</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 relative overflow-hidden group">
            <div class="absolute top-4 right-4 w-10 h-10 bg-orange-50 text-orange-500 rounded-full flex items-center justify-center text-lg"><i class="fa-solid fa-robot"></i></div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Autopilot Drips</p>
            <h3 class="text-3xl font-black text-gray-900 mb-1">0</h3>
            <p class="text-xs text-gray-400 font-medium">Active Background Rules</p>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden mb-20">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <h2 class="text-lg font-black text-gray-900">Recent Flight Logs</h2>
            <a href="?view=campaigns" class="text-xs font-bold text-indigo-600 hover:underline">View All Campaigns</a>
        </div>
        
        <?php if(empty($recent_campaigns)): ?>
            <div class="text-center py-16">
                <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3 border border-gray-100">
                    <i class="fa-solid fa-wind text-2xl text-gray-300"></i>
                </div>
                <h3 class="text-sm font-bold text-gray-700">No flights recently</h3>
                <p class="text-xs text-gray-500 mt-1">Your campaign history and open rates will appear here.</p>
            </div>
        <?php else: ?>
            <table class="w-full text-left text-sm">
                <thead class="bg-white border-b text-[10px] uppercase text-gray-400 font-bold tracking-wider">
                    <tr>
                        <th class="p-4 pl-6">Campaign Name</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Open Rate</th>
                        <th class="p-4 text-right pr-6">Sent Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach($recent_campaigns as $camp): 
                        $is_draft = ($camp->campaign_type === 'draft');
                    ?>
                    <tr class="hover:bg-gray-50/50 transition">
                        <td class="p-4 pl-6 font-bold text-gray-900"><?php echo esc_html($camp->campaign_name); ?></td>
                        <td class="p-4">
                            <?php if($is_draft): ?>
                                <span class="px-2 py-1 bg-yellow-50 text-yellow-700 border border-yellow-200 text-[9px] font-black uppercase tracking-widest rounded">Draft</span>
                            <?php else: ?>
                                <span class="px-2 py-1 bg-green-50 text-green-700 border border-green-200 text-[9px] font-black uppercase tracking-widest rounded">Sent</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-gray-500 font-medium">
                            <?php echo $is_draft ? '-' : '0.0%'; ?>
                        </td>
                        <td class="p-4 pr-6 text-right text-gray-500 text-xs font-medium">
                            <?php echo date('M d, Y', strtotime($camp->created_at)); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>