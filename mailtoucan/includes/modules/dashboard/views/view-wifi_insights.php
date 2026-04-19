<?php
/**
 * MailToucan Enterprise - WiFi & CRM Insights v2.2
 * Features: Network Analytics, Deep-Dive Campaign Modal, Dynamic Filtering, Filtered CSV Export, JS Syntax Fix
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_leads = $wpdb->prefix . 'mt_guest_leads';
$table_stores = $wpdb->prefix . 'mt_stores';
$table_campaigns = $wpdb->prefix . 'mt_campaigns';
$table_responses = $wpdb->prefix . 'mt_campaign_responses';

// AUDIT FIX: Process Date Filter Range
$range = isset($_GET['range']) ? intval($_GET['range']) : 30;
$date_cond = $range > 0 ? "AND created_at >= NOW() - INTERVAL " . intval($range) . " DAY" : "";

// AUDIT FIX: Queries now respect 'deleted_at' and the active date filter
$total_guests = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_leads WHERE brand_id = %d AND deleted_at IS NULL AND status NOT IN ('trashed','deleted') $date_cond", $brand->id));
$active_guests = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_leads WHERE brand_id = %d AND deleted_at IS NULL AND status IN ('active','verified') $date_cond", $brand->id));
$total_campaign_engagements = $wpdb->get_var($wpdb->prepare("SELECT COUNT(r.id) FROM $table_responses r INNER JOIN $table_campaigns c ON r.campaign_id = c.id WHERE c.brand_id = %d $date_cond", $brand->id));

// AUDIT FIX: Compute real Avg Session time using last_visit proxy
$avg_session = $wpdb->get_var($wpdb->prepare("
    SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, IFNULL(last_visit, created_at))))
    FROM $table_leads
    WHERE brand_id = %d AND last_visit IS NOT NULL AND last_visit != created_at
      AND deleted_at IS NULL AND status != 'trashed' $date_cond
", $brand->id));
$avg_session = $avg_session ?: 0;

// Location Breakdown with Date Filter
$location_stats = $wpdb->get_results($wpdb->prepare("
    SELECT s.store_name, COUNT(l.id) as guest_count 
    FROM $table_stores s 
    LEFT JOIN $table_leads l ON s.id = l.store_id AND l.deleted_at IS NULL AND l.status != 'trashed' $date_cond
    WHERE s.brand_id = %d 
    GROUP BY s.id 
    ORDER BY guest_count DESC
", $brand->id));

// AUDIT FIX: Gather Daily Connections for Graph
$daily_connections = $wpdb->get_results($wpdb->prepare("
    SELECT DATE(created_at) as day, COUNT(id) as count
    FROM $table_leads
    WHERE brand_id = %d AND deleted_at IS NULL AND status != 'trashed' $date_cond
    GROUP BY DATE(created_at)
    ORDER BY day ASC
", $brand->id));

// Campaign Performance History
$campaign_stats = $wpdb->get_results($wpdb->prepare("
    SELECT c.id, c.campaign_name, c.campaign_type, c.config_json, c.created_at, COUNT(r.id) as total_responses
    FROM $table_campaigns c
    LEFT JOIN $table_responses r ON c.id = r.campaign_id
    WHERE c.brand_id = %d
    GROUP BY c.id
    ORDER BY c.created_at DESC
", $brand->id));

// Pre-fetch all responses
$responses_raw = $wpdb->get_results($wpdb->prepare("
    SELECT r.id, r.campaign_id, r.response_data, r.created_at, l.email, l.guest_name, s.store_name 
    FROM $table_responses r
    LEFT JOIN $table_leads l ON r.lead_id = l.id
    LEFT JOIN $table_stores s ON l.store_id = s.id
    INNER JOIN $table_campaigns c ON r.campaign_id = c.id
    WHERE c.brand_id = %d
    ORDER BY r.created_at DESC
", $brand->id));

$js_campaigns = [];
foreach($campaign_stats as $c) {
    $c->config = json_decode($c->config_json, true);
    $c->responses = [];
    $js_campaigns[$c->id] = $c;
}
foreach($responses_raw as $r) {
    if(isset($js_campaigns[$r->campaign_id])) {
        $r->parsed_data = json_decode($r->response_data, true);
        $js_campaigns[$r->campaign_id]->responses[] = $r;
    }
}
?>

<div id="mt_toast" class="fixed bottom-6 right-6 bg-gray-900 text-white px-5 py-3 rounded-lg shadow-2xl transform translate-y-20 opacity-0 transition-all duration-300 z-[300] font-bold text-sm flex items-center gap-3">
    <i id="mt_toast_icon" class="fa-solid fa-circle-info"></i>
    <span id="mt_toast_msg">Message</span>
</div>

<header class="mb-8 flex justify-between items-end">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">WiFi & CRM Insights</h1>
        <p class="text-gray-500 text-sm">Analyze network traffic, location performance, and campaign engagement.</p>
    </div>
    <div class="flex gap-3">
        <select onchange="window.location.search = '?view=wifi_insights&range=' + this.value" class="bg-white border border-gray-300 px-4 py-2 rounded-lg font-bold text-sm text-gray-600 outline-none shadow-sm cursor-pointer">
            <option value="30" <?php echo ($range===30)?'selected':'';?>>Last 30 Days</option>
            <option value="7"  <?php echo ($range===7) ?'selected':'';?>>Last 7 Days</option>
            <option value="0"  <?php echo ($range===0) ?'selected':'';?>>All Time</option>
        </select>
        <button onclick="exportGlobalReport()" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg font-bold text-sm shadow-sm hover:bg-gray-50 transition flex items-center gap-2">
            <i class="fa-solid fa-download text-indigo-500"></i> Global Report
        </button>
    </div>
</header>

<div class="grid grid-cols-4 gap-6 mb-8">
    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm relative overflow-hidden">
        <div class="absolute -right-4 -top-4 text-gray-100 text-7xl"><i class="fa-solid fa-users"></i></div>
        <p class="text-xs font-bold text-gray-400 uppercase mb-1 relative z-10">Total Unique Guests</p>
        <p class="text-3xl font-bold text-gray-900 relative z-10"><?php echo number_format($total_guests); ?></p>
        <p class="text-[10px] text-green-500 font-bold mt-2 relative z-10"><i class="fa-solid fa-arrow-trend-up"></i> Network Growing</p>
    </div>
    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm relative overflow-hidden">
        <div class="absolute -right-4 -top-4 text-gray-100 text-7xl"><i class="fa-solid fa-chart-pie"></i></div>
        <p class="text-xs font-bold text-gray-400 uppercase mb-1 relative z-10">Active Retention</p>
        <p class="text-3xl font-bold text-indigo-600 relative z-10"><?php echo $total_guests > 0 ? round(($active_guests/$total_guests)*100) : 0; ?>%</p>
        <p class="text-[10px] text-gray-400 font-bold mt-2 relative z-10">Guests opted into marketing</p>
    </div>
    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm relative overflow-hidden">
        <div class="absolute -right-4 -top-4 text-gray-100 text-7xl"><i class="fa-solid fa-bullseye"></i></div>
        <p class="text-xs font-bold text-gray-400 uppercase mb-1 relative z-10">Campaign Engagements</p>
        <p class="text-3xl font-bold text-purple-600 relative z-10"><?php echo number_format($total_campaign_engagements); ?></p>
        <p class="text-[10px] text-gray-400 font-bold mt-2 relative z-10">Surveys, Promos & Games</p>
    </div>
    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm relative overflow-hidden">
        <div class="absolute -right-4 -top-4 text-gray-100 text-7xl"><i class="fa-solid fa-clock"></i></div>
        <p class="text-xs font-bold text-gray-400 uppercase mb-1 relative z-10">Avg Session Time</p>
        <p class="text-3xl font-bold text-gray-900 relative z-10"><?php echo $avg_session; ?> <span class="text-sm">mins</span></p>
        <p class="text-[10px] text-gray-400 font-bold mt-2 relative z-10">Estimated network dwell time</p>
    </div>
</div>

<div class="grid grid-cols-3 gap-8 mb-8">
    <div class="col-span-2 bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
        <h3 class="font-bold text-gray-900 mb-6">Network Connections <?php echo $range > 0 ? "($range Days)" : "(All Time)"; ?></h3>
        
        <canvas id="connections_chart" height="90"></canvas>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
        <script>
        const dailyData = <?php echo wp_json_encode($daily_connections); ?>;
        new Chart(document.getElementById('connections_chart'), {
            type: 'bar',
            data: { 
                labels: dailyData.map(d => d.day), 
                datasets: [{ label: 'New Connections', data: dailyData.map(d => d.count), backgroundColor: '#6366F1', borderRadius: 4 }] 
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
        </script>
    </div>

    <div class="col-span-1 bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex flex-col">
        <h3 class="font-bold text-gray-900 mb-6">Traffic by Location</h3>
        <div class="flex-1 space-y-4 overflow-y-auto pr-2" style="max-height: 250px;">
            <?php if(empty($location_stats)): ?>
                <p class="text-xs text-gray-400 italic text-center mt-10">No location data captured yet.</p>
            <?php else: ?>
                <?php foreach($location_stats as $loc): 
                    $pct = $total_guests > 0 ? round(($loc->guest_count / $total_guests) * 100) : 0;
                ?>
                <div>
                    <div class="flex justify-between text-xs font-bold mb-1">
                        <span class="text-gray-700"><?php echo esc_html($loc->store_name ?: 'Global Web Portal'); ?></span>
                        <span class="text-indigo-600"><?php echo $pct; ?>%</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                        <div class="bg-indigo-500 h-2 rounded-full" style="width: <?php echo $pct; ?>%"></div>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1 text-right"><?php echo number_format($loc->guest_count); ?> Guests</p>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-12">
    <div class="p-6 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
        <div>
            <h3 class="font-bold text-gray-900">Campaign Performance History</h3>
            <p class="text-xs text-gray-500 mt-1">Review engagement rates and collected data across all your active and past modules.</p>
        </div>
    </div>
    
    <div class="divide-y divide-gray-100">
        <?php if(empty($campaign_stats)): ?>
            <div class="p-12 text-center">
                <i class="fa-solid fa-folder-open text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-500 font-bold">No campaigns have been deployed yet. Create one in the CRM to see data here!</p>
            </div>
        <?php else: ?>
            <?php foreach($campaign_stats as $camp): 
                $icon = 'fa-tag text-gray-400';
                if($camp->campaign_type == 'survey') $icon = 'fa-clipboard-list text-blue-500';
                if($camp->campaign_type == 'versus') $icon = 'fa-code-compare text-orange-500';
                if($camp->campaign_type == 'wheel') $icon = 'fa-compact-disc text-yellow-500';
                if($camp->campaign_type == 'box') $icon = 'fa-box-open text-green-500';
            ?>
            <div class="p-6 hover:bg-indigo-50/50 transition flex items-center justify-between group">
                <div class="flex items-center gap-4 w-1/3">
                    <div class="w-10 h-10 rounded bg-white border shadow-sm flex items-center justify-center text-lg"><i class="fa-solid <?php echo $icon; ?>"></i></div>
                    <div>
                        <h4 class="font-bold text-gray-900 leading-tight group-hover:text-indigo-600 transition"><?php echo esc_html($camp->campaign_name); ?></h4>
                        <p class="text-[10px] uppercase font-bold text-gray-400 mt-0.5"><?php echo esc_html($camp->campaign_type); ?> • Created <?php echo date('M d, Y', strtotime($camp->created_at)); ?></p>
                    </div>
                </div>
                
                <div class="w-1/3 flex justify-center">
                    <div class="text-center">
                        <p class="text-2xl font-black text-gray-800 leading-none"><?php echo number_format($camp->total_responses); ?></p>
                        <p class="text-[10px] font-bold text-gray-400 uppercase mt-1">Total Engagements</p>
                    </div>
                </div>

                <div class="w-1/3 flex justify-end">
                    <button onclick="openCampaignDeepDive(<?php echo $camp->id; ?>)" class="bg-white hover:bg-indigo-600 border border-gray-200 text-indigo-600 hover:text-white text-xs font-bold py-2 px-4 rounded-lg shadow-sm transition">
                        View Deep Dive Data &rarr;
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="camp_data_modal" class="fixed inset-0 bg-gray-900/60 z-[200] hidden opacity-0 transition-opacity duration-300 backdrop-blur-sm flex items-center justify-center p-6">
    <div id="camp_data_content" class="bg-white w-full max-w-6xl h-[85vh] rounded-2xl shadow-2xl flex flex-col transform scale-95 transition-transform duration-300 overflow-hidden">
        
        <div class="p-6 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
            <div>
                <p class="text-xs font-bold text-indigo-500 uppercase tracking-wider mb-1" id="modal_camp_type">Campaign Type</p>
                <h2 class="text-2xl font-black text-gray-900 leading-none" id="modal_camp_name">Campaign Name</h2>
            </div>
            <div class="flex gap-3">
                <button onclick="exportCampaignCSV()" class="bg-green-600 hover:bg-green-700 text-white font-bold text-sm py-2 px-4 rounded-lg shadow transition flex items-center gap-2">
                    <i class="fa-solid fa-file-csv"></i> Export Filtered View
                </button>
                <button onclick="closeCampaignDeepDive()" class="text-gray-400 hover:text-gray-800 bg-white border border-gray-300 rounded-lg p-2 shadow-sm transition"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>

        <div class="bg-white p-4 border-b border-gray-200 flex gap-4 items-center flex-wrap shadow-sm z-10 relative">
            <div class="flex items-center gap-2 bg-gray-50 border rounded-lg px-3 py-1.5">
                <i class="fa-regular fa-calendar text-gray-400 text-xs"></i>
                <input type="date" id="flt_date_start" class="bg-transparent text-xs font-bold text-gray-700 outline-none" onchange="applyDeepFilters()">
                <span class="text-gray-400 text-xs font-bold">to</span>
                <input type="date" id="flt_date_end" class="bg-transparent text-xs font-bold text-gray-700 outline-none" onchange="applyDeepFilters()">
            </div>

            <div class="relative">
                <select id="flt_location" class="appearance-none bg-gray-50 border rounded-lg pl-8 pr-8 py-2 text-xs font-bold text-gray-700 outline-none focus:ring-2 focus:ring-indigo-100" onchange="applyDeepFilters()">
                    <option value="">All Locations</option>
                </select>
                <i class="fa-solid fa-location-dot absolute left-3 top-2.5 text-gray-400 text-xs pointer-events-none"></i>
            </div>

            <div class="relative hidden" id="flt_answer_wrapper">
                <select id="flt_answer" class="appearance-none bg-indigo-50 border border-indigo-200 rounded-lg pl-8 pr-8 py-2 text-xs font-bold text-indigo-700 outline-none focus:ring-2 focus:ring-indigo-200" onchange="applyDeepFilters()">
                    <option value="">All Answers</option>
                </select>
                <i class="fa-solid fa-filter absolute left-3 top-2.5 text-indigo-400 text-xs pointer-events-none"></i>
            </div>

            <div class="relative ml-auto">
                <input type="text" id="flt_search" placeholder="Search Guest Name or Email..." class="bg-gray-50 border rounded-lg pl-8 pr-3 py-2 text-xs outline-none w-64 focus:ring-2 focus:ring-indigo-100" onkeyup="applyDeepFilters()">
                <i class="fa-solid fa-search absolute left-3 top-2.5 text-gray-400 text-xs pointer-events-none"></i>
            </div>
            
            <button onclick="resetDeepFilters()" class="text-xs font-bold text-gray-400 hover:text-red-500 transition"><i class="fa-solid fa-rotate-right"></i> Reset</button>
        </div>

        <div class="flex-1 overflow-y-auto bg-gray-50 relative">
            <table class="w-full text-left border-collapse">
                <thead class="bg-white border-b border-gray-200 text-[10px] font-bold text-gray-400 uppercase tracking-wider sticky top-0 shadow-sm z-20">
                    <tr>
                        <th class="p-4 pl-6 w-1/4">Date & Time</th>
                        <th class="p-4 w-1/4">Guest Identity</th>
                        <th class="p-4 w-1/4">Location / Origin</th>
                        <th class="p-4 w-1/4">Campaign Result / Answer</th>
                    </tr>
                </thead>
                <tbody id="camp_data_tbody" class="divide-y divide-gray-100 bg-white">
                </tbody>
            </table>
            
            <div id="camp_data_empty" class="hidden p-16 text-center">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fa-solid fa-filter-circle-xmark text-2xl text-gray-400"></i></div>
                <h3 class="text-lg font-bold text-gray-700">No matching records found.</h3>
                <p class="text-sm text-gray-500 mt-1">Try adjusting your date range or filters.</p>
            </div>
        </div>
        
        <div class="p-4 border-t border-gray-200 bg-white text-xs font-bold text-gray-500 flex justify-between">
            <span id="txt_showing_count">Showing 0 records</span>
            <span class="text-indigo-500"><i class="fa-solid fa-circle-check"></i> Live Sync Active</span>
        </div>

    </div>
</div>

<script>
    function showToast(msg, type = 'info') {
        const toast = document.getElementById('mt_toast');
        document.getElementById('mt_toast_msg').innerText = msg;
        const icon = document.getElementById('mt_toast_icon');
        
        if(type === 'error') { icon.className = 'fa-solid fa-circle-exclamation text-red-400'; }
        else if(type === 'success') { icon.className = 'fa-solid fa-circle-check text-green-400'; }
        else { icon.className = 'fa-solid fa-circle-info text-blue-400'; }

        toast.classList.remove('translate-y-20', 'opacity-0');
        setTimeout(() => { toast.classList.add('translate-y-20', 'opacity-0'); }, 3000);
    }

    function exportGlobalReport() {
        showToast("Generating Global Location Report...", "info");
        
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "Metric,Value\n";
        csvContent += `Total Unique Guests,<?php echo intval($total_guests); ?>\n`;
        csvContent += `Active Subscribers,<?php echo intval($active_guests); ?>\n`;
        csvContent += `Campaign Engagements,<?php echo intval($total_campaign_engagements); ?>\n\n`;
        
        csvContent += "Location,Total Guests\n";
        <?php foreach($location_stats as $loc): ?>
            csvContent += `"${escapeCsv('<?php echo esc_js($loc->store_name ?: 'Global Web Portal'); ?>')}",<?php echo intval($loc->guest_count); ?>\n`;
        <?php endforeach; ?>
        
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `Global_Network_Report_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        setTimeout(() => showToast("Export Successful!", "success"), 500);
    }

    function escapeCsv(str) { return str.replace(/"/g, '""'); }

    const fullCampData = <?php echo wp_json_encode($js_campaigns); ?>;
    let activeCampId = null;
    let currentFilteredRows = [];

    function openCampaignDeepDive(id) {
        activeCampId = id; const camp = fullCampData[id]; if(!camp) return;

        document.getElementById('modal_camp_name').innerText = camp.campaign_name;
        document.getElementById('modal_camp_type').innerText = camp.campaign_type;

        const locSelect = document.getElementById('flt_location');
        locSelect.innerHTML = '<option value="">All Locations</option>';
        let uniqueLocs = [...new Set(camp.responses.map(r => r.store_name || 'Global Web Portal'))];
        uniqueLocs.forEach(loc => { locSelect.innerHTML += `<option value="${loc}">${loc}</option>`; });

        const ansWrapper = document.getElementById('flt_answer_wrapper');
        const ansSelect = document.getElementById('flt_answer');
        ansSelect.innerHTML = '<option value="">All Answers</option>';
        
        if (camp.campaign_type === 'versus') {
            ansWrapper.classList.remove('hidden');
            if(camp.config.a) ansSelect.innerHTML += `<option value="${camp.config.a}">Chose: ${camp.config.a}</option>`;
            if(camp.config.b) ansSelect.innerHTML += `<option value="${camp.config.b}">Chose: ${camp.config.b}</option>`;
        } 
        else if (camp.campaign_type === 'wheel' || camp.campaign_type === 'box') {
            ansWrapper.classList.remove('hidden');
            if(camp.config.prizes) { camp.config.prizes.forEach(p => { ansSelect.innerHTML += `<option value="${p.name}">Won: ${p.name}</option>`; }); }
        }
        else if (camp.campaign_type === 'survey' && camp.config.stars) {
            ansWrapper.classList.remove('hidden');
            ansSelect.innerHTML += `<option value="5">5 Stars</option><option value="4">4 Stars</option><option value="3">3 Stars</option><option value="2">2 Stars</option><option value="1">1 Star</option>`;
        }
        else { ansWrapper.classList.add('hidden'); }

        resetDeepFilters(false); applyDeepFilters();

        const modal = document.getElementById('camp_data_modal');
        const content = document.getElementById('camp_data_content');
        modal.classList.remove('hidden');
        setTimeout(() => { modal.classList.remove('opacity-0'); content.classList.remove('scale-95'); }, 10);
    }

    function closeCampaignDeepDive() {
        const modal = document.getElementById('camp_data_modal');
        const content = document.getElementById('camp_data_content');
        content.classList.add('scale-95'); modal.classList.add('opacity-0');
        setTimeout(() => { modal.classList.add('hidden'); activeCampId = null; }, 300);
    }

    function resetDeepFilters(reRender = true) {
        document.getElementById('flt_date_start').value = ''; document.getElementById('flt_date_end').value = ''; document.getElementById('flt_location').value = ''; document.getElementById('flt_answer').value = ''; document.getElementById('flt_search').value = '';
        if(reRender) applyDeepFilters();
    }

    function applyDeepFilters() {
        if(!activeCampId || !fullCampData[activeCampId]) return;
        
        const camp = fullCampData[activeCampId];
        const dateStart = document.getElementById('flt_date_start').value; const dateEnd = document.getElementById('flt_date_end').value; const loc = document.getElementById('flt_location').value; const ans = document.getElementById('flt_answer').value; const search = document.getElementById('flt_search').value.toLowerCase();

        currentFilteredRows = camp.responses.filter(r => {
            if (dateStart) { const rowDate = new Date(r.created_at.split(' ')[0]); if(rowDate < new Date(dateStart)) return false; }
            if (dateEnd) { const rowDate = new Date(r.created_at.split(' ')[0]); if(rowDate > new Date(dateEnd)) return false; }
            const rowLoc = r.store_name || 'Global Web Portal';
            if (loc && rowLoc !== loc) return false;
            if (search && !r.email.toLowerCase().includes(search) && !(r.guest_name && r.guest_name.toLowerCase().includes(search))) return false;

            if (ans && r.parsed_data) {
                if (camp.campaign_type === 'versus' && r.parsed_data.versus_choice !== ans) return false;
                if ((camp.campaign_type === 'wheel' || camp.campaign_type === 'box') && r.parsed_data.prize !== ans) return false;
                if (camp.campaign_type === 'survey' && String(r.parsed_data.rating) !== ans) return false;
            }
            return true;
        });

        renderTableRows();
    }

    function extractDisplayAnswer(parsedData, type) {
        if(!parsedData) return '<span class="text-gray-300 italic">No specific data</span>';
        
        if (type === 'versus') return `<span class="bg-orange-100 text-orange-700 px-2 py-1 rounded font-bold text-[10px] uppercase">Chose: ${parsedData.versus_choice || 'Unknown'}</span>`;
        if (type === 'birthday') return `<span class="bg-pink-100 text-pink-700 px-2 py-1 rounded font-bold text-[10px] uppercase">🎂 ${parsedData.birthday || 'Unknown'}</span>`;
        if (type === 'wheel' || type === 'box') return `<span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded font-bold text-[10px] uppercase">🎁 Won: ${parsedData.prize || 'Unknown'}</span>`;
        if (type === 'survey') {
            let html = '';
            if(parsedData.rating) html += `<span class="text-yellow-500 font-bold text-xs mr-2"><i class="fa-solid fa-star"></i> ${parsedData.rating}</span>`;
            let ansCount = Object.keys(parsedData).length - (parsedData.rating ? 1 : 0);
            if(ansCount > 0) html += `<span class="text-xs text-gray-500 underline decoration-dotted decoration-gray-300 cursor-help" title="Export CSV to view full written answers.">Answered ${ansCount} Questions</span>`;
            return html || '<span class="text-gray-300 italic">Form submitted</span>';
        }
        return '<span class="text-gray-500 text-xs"><i class="fa-solid fa-eye text-indigo-400"></i> Impression Tracked</span>'; 
    }

    function renderTableRows() {
        const tbody = document.getElementById('camp_data_tbody'); const emptyState = document.getElementById('camp_data_empty'); const countTxt = document.getElementById('txt_showing_count'); const campType = fullCampData[activeCampId].campaign_type;

        tbody.innerHTML = '';
        if (currentFilteredRows.length === 0) { emptyState.classList.remove('hidden'); countTxt.innerText = "Showing 0 records"; return; }
        emptyState.classList.add('hidden'); countTxt.innerText = `Showing ${currentFilteredRows.length} records`;

        currentFilteredRows.forEach(r => {
            const displayDate = new Date(r.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
            const resultHtml = extractDisplayAnswer(r.parsed_data, campType);
            
            tbody.innerHTML += `
                <tr class="hover:bg-indigo-50/30 transition">
                    <td class="p-4 pl-6"><div class="font-bold text-gray-800 text-sm">${displayDate.split(', ')[0]}</div><div class="text-[10px] text-gray-400 font-bold">${displayDate.split(', ')[1] || ''}</div></td>
                    <td class="p-4"><div class="font-bold text-gray-900 text-sm">${r.email}</div><div class="text-[10px] text-gray-500 uppercase font-bold">${r.guest_name || 'Unknown'}</div></td>
                    <td class="p-4"><span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-[10px] font-bold uppercase border border-gray-200">${r.store_name || 'Global Web'}</span></td>
                    <td class="p-4">${resultHtml}</td>
                </tr>
            `;
        });
    }

    function exportCampaignCSV() {
        if(currentFilteredRows.length === 0) { showToast("No records match your filters to export.", "error"); return; }
        showToast("Generating filtered report...", "info");

        const camp = fullCampData[activeCampId]; let csvContent = "data:text/csv;charset=utf-8,";
        let headers = ["Date", "Email", "Name", "Location"];
        if (camp.campaign_type === 'versus') headers.push("A/B Choice");
        else if (camp.campaign_type === 'birthday') headers.push("Birthday");
        else if (camp.campaign_type === 'wheel' || camp.campaign_type === 'box') headers.push("Prize Won");
        else if (camp.campaign_type === 'survey') { headers.push("Star Rating"); headers.push("Full Form Data (JSON)"); }
        else headers.push("Engagement Type");

        csvContent += headers.join(",") + "\n";
        currentFilteredRows.forEach(r => {
            let row = [ r.created_at, r.email, `"${escapeCsv(r.guest_name || '')}"`, `"${escapeCsv(r.store_name || 'Global Web')}"` ];
            if (camp.campaign_type === 'versus') row.push(`"${escapeCsv(r.parsed_data?.versus_choice || '')}"`);
            else if (camp.campaign_type === 'birthday') row.push(`"${escapeCsv(r.parsed_data?.birthday || '')}"`);
            else if (camp.campaign_type === 'wheel' || camp.campaign_type === 'box') row.push(`"${escapeCsv(r.parsed_data?.prize || '')}"`);
            else if (camp.campaign_type === 'survey') { row.push(r.parsed_data?.rating || ''); let rawJson = r.response_data ? r.response_data.replace(/"/g, '""') : '{}'; row.push(`"${rawJson}"`); }
            else row.push("Impression Tracked");
            csvContent += row.join(",") + "\n";
        });

        const encodedUri = encodeURI(csvContent); const link = document.createElement("a"); const cleanName = camp.campaign_name.replace(/[^a-z0-9]/gi, '_').toLowerCase();
        link.setAttribute("href", encodedUri); link.setAttribute("download", `report_${cleanName}_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link); link.click(); document.body.removeChild(link);
    }
</script>