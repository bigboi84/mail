<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_campaigns = $wpdb->prefix . 'mt_campaigns';
$table_sends     = $wpdb->prefix . 'mt_email_sends';
$table_opens     = $wpdb->prefix . 'mt_email_opens';
$table_clicks    = $wpdb->prefix . 'mt_email_clicks';

// 1. Fetch Global Account Metrics
$total_sent = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_sends WHERE brand_id = %d", $brand->id)) ?: 0;
$total_opens = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_opens WHERE brand_id = %d", $brand->id)) ?: 0;
$total_clicks = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_clicks WHERE brand_id = %d", $brand->id)) ?: 0;

$open_rate = ($total_sent > 0) ? round(($total_opens / $total_sent) * 100, 1) : 0.0;
$click_rate = ($total_opens > 0) ? round(($total_clicks / $total_opens) * 100, 1) : 0.0;

// 2. Generate 7-Day Trailing Data for the Chart
$chart_labels = [];
$chart_sends = [];
$chart_opens = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $display_date = date('M d', strtotime("-$i days"));
    
    $sends_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_sends WHERE brand_id = %d AND DATE(sent_at) = %s", $brand->id, $date)) ?: 0;
    $opens_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_opens WHERE brand_id = %d AND DATE(opened_at) = %s", $brand->id, $date)) ?: 0;
    
    $chart_labels[] = $display_date;
    $chart_sends[] = $sends_count;
    $chart_opens[] = $opens_count;
}

$js_labels = wp_json_encode($chart_labels);
$js_sends  = wp_json_encode($chart_sends);
$js_opens  = wp_json_encode($chart_opens);

// 3. Fetch Recent Campaigns
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
    .vei-page-header{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:24px;flex-wrap:wrap;gap:12px;}
    .vei-page-title{font-size:22px;font-weight:900;color:#111827;display:flex;align-items:center;gap:8px;}
    .vei-page-sub{font-size:13px;color:#6b7280;margin-top:3px;}
    .vei-new-btn{background:var(--mt-brand);color:white;border:none;border-radius:10px;padding:10px 20px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:8px;text-decoration:none;transition:filter .15s;}
    .vei-new-btn:hover{filter:brightness(1.1);}

    /* Mobile */
    @media(max-width:768px){
        .vei-page-header{flex-direction:column;align-items:flex-start;}
        .vei-page-title{font-size:18px;}
        .vei-new-btn{width:100%;justify-content:center;}
        .grid.grid-cols-3{grid-template-columns:1fr!important;}
        .grid.grid-cols-4{grid-template-columns:1fr 1fr!important;}
        .grid.grid-cols-2{grid-template-columns:1fr!important;}
        .max-w-7xl{max-width:100%;}
        canvas{max-width:100%;}
        .overflow-x-auto{overflow-x:auto;-webkit-overflow-scrolling:touch;}
        table{min-width:500px;}
    }
</style>

<div class="max-w-7xl mx-auto">
    <div class="vei-page-header">
        <div>
            <div class="vei-page-title"><i class="fa-solid fa-chart-line" style="color:var(--mt-brand);"></i> Email Insights</div>
            <div class="vei-page-sub">Track delivery rates, open rates, and campaign performance.</div>
        </div>
        <a href="?view=campaigns" class="vei-new-btn">
            <i class="fa-solid fa-paper-plane"></i> New Broadcast
        </a>
    </div>

    <div class="grid grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm relative overflow-hidden group hover:border-gray-300 transition">
            <div class="flex justify-between items-start mb-4">
                <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-paper-plane"></i></div>
                <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">All Time</span>
            </div>
            <h3 class="text-gray-500 text-sm font-bold mb-1">Total Emails Sent</h3>
            <p class="text-3xl font-black text-gray-900"><?php echo number_format($total_sent); ?></p>
            <div class="absolute bottom-0 left-0 w-full h-1 bg-blue-500 transform scale-x-0 group-hover:scale-x-100 transition-transform origin-left duration-300"></div>
        </div>

        <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm relative overflow-hidden group hover:border-gray-300 transition">
            <div class="flex justify-between items-start mb-4">
                <div class="w-12 h-12 rounded-full bg-green-50 text-green-500 flex items-center justify-center text-xl shrink-0"><i class="fa-regular fa-envelope-open"></i></div>
                <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Avg</span>
            </div>
            <h3 class="text-gray-500 text-sm font-bold mb-1">Overall Open Rate</h3>
            <p class="text-3xl font-black text-gray-900"><?php echo number_format($open_rate, 1); ?>%</p>
            <div class="absolute bottom-0 left-0 w-full h-1 bg-green-500 transform scale-x-0 group-hover:scale-x-100 transition-transform origin-left duration-300"></div>
        </div>

        <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm relative overflow-hidden group hover:border-gray-300 transition">
            <div class="flex justify-between items-start mb-4">
                <div class="w-12 h-12 rounded-full bg-purple-50 text-purple-500 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-link"></i></div>
                <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Avg</span>
            </div>
            <h3 class="text-gray-500 text-sm font-bold mb-1">Overall Click Rate</h3>
            <p class="text-3xl font-black text-gray-900"><?php echo number_format($click_rate, 1); ?>%</p>
            <div class="absolute bottom-0 left-0 w-full h-1 bg-purple-500 transform scale-x-0 group-hover:scale-x-100 transition-transform origin-left duration-300"></div>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-8">
        <div class="col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-lg font-black text-gray-900">7-Day Performance</h2>
                <div class="flex gap-4 text-sm font-bold">
                    <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full" style="background-color: var(--mt-brand);"></div><span>Sent</span></div>
                    <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full bg-green-400"></div><span>Opened</span></div>
                </div>
            </div>
            <div class="w-full h-72">
                <canvas id="emailPerformanceChart"></canvas>
            </div>
        </div>

        <div class="col-span-1 bg-white rounded-2xl border border-gray-200 shadow-sm flex flex-col">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                <h2 class="text-lg font-black text-gray-900">Recent Blasts</h2>
                <a href="?view=campaigns" class="text-xs font-bold text-indigo-600 hover:underline">View All</a>
            </div>
            
            <?php if(empty($recent_campaigns)): ?>
                <div class="flex-1 flex flex-col items-center justify-center p-8 text-center">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-3">
                        <i class="fa-solid fa-inbox text-2xl text-gray-300"></i>
                    </div>
                    <p class="text-sm font-bold text-gray-600">No campaigns yet</p>
                    <p class="text-xs text-gray-400 mt-1">Your recent broadcasts will appear here.</p>
                </div>
            <?php else: ?>
                <div class="flex-1 overflow-y-auto p-2">
                    <table class="w-full text-left text-sm">
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($recent_campaigns as $camp): 
                                $is_draft = ($camp->campaign_type === 'draft' || $camp->campaign_type === 'workflow_draft');
                                
                                // Calculate specific stats for this campaign
                                $camp_sent = 0; $camp_opens = 0; $camp_open_rate = 0.0;
                                if (!$is_draft) {
                                    $camp_sent = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_sends WHERE campaign_id = %d", $camp->id)) ?: 0;
                                    $camp_opens = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_opens WHERE campaign_id = %d", $camp->id)) ?: 0;
                                    if ($camp_sent > 0) $camp_open_rate = round(($camp_opens / $camp_sent) * 100, 1);
                                }
                            ?>
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="p-4 pl-4">
                                    <p class="font-bold text-gray-900 truncate max-w-[150px]"><?php echo esc_html($camp->campaign_name); ?></p>
                                    <p class="text-[10px] text-gray-400 mt-1"><?php echo date('M d, Y', strtotime($camp->created_at)); ?></p>
                                </td>
                                <td class="p-4 text-right pr-4">
                                    <?php if($is_draft): ?>
                                        <span class="px-2 py-1 bg-yellow-50 text-yellow-700 border border-yellow-200 text-[9px] font-black uppercase tracking-widest rounded">Draft</span>
                                    <?php else: ?>
                                        <div class="flex flex-col items-end">
                                            <span class="text-xs font-black text-gray-900"><?php echo number_format($camp_open_rate, 1); ?>% Open</span>
                                            <span class="text-[10px] text-gray-400 font-medium"><?php echo number_format($camp_sent); ?> Sent</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById('emailPerformanceChart');
        if (!ctx) return;

        // Dynamic data from PHP
        const labels = <?php echo $js_labels; ?>;
        const sendsData = <?php echo $js_sends; ?>;
        const opensData = <?php echo $js_opens; ?>;
        
        // Brand color hex from CSS variable
        const brandColor = getComputedStyle(document.documentElement).getPropertyValue('--mt-brand').trim() || '#0f172a';

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Emails Sent',
                        data: sendsData,
                        borderColor: brandColor,
                        backgroundColor: brandColor + '15', // Adding 15 hex for low opacity
                        borderWidth: 3,
                        pointBackgroundColor: brandColor,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Emails Opened',
                        data: opensData,
                        borderColor: '#4ade80', // Tailwind green-400
                        backgroundColor: 'transparent',
                        borderWidth: 3,
                        borderDash: [5, 5],
                        pointBackgroundColor: '#4ade80',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: false,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1f2937',
                        padding: 12,
                        titleFont: { size: 13, family: "'Inter', sans-serif" },
                        bodyFont: { size: 13, family: "'Inter', sans-serif", weight: 'bold' },
                        cornerRadius: 8,
                        displayColors: true,
                        boxPadding: 4
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9', drawBorder: false },
                        border: { display: false },
                        ticks: {
                            font: { family: "'Inter', sans-serif", size: 11 },
                            color: '#94a3b8',
                            stepSize: 1, // Forces whole numbers on the Y axis
                            callback: function(value) { if (value % 1 === 0) { return value; } }
                        }
                    },
                    x: {
                        grid: { display: false, drawBorder: false },
                        border: { display: false },
                        ticks: {
                            font: { family: "'Inter', sans-serif", size: 11 },
                            color: '#94a3b8'
                        }
                    }
                }
            }
        });
    });
</script>