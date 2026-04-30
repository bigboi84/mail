<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_leads    = $wpdb->prefix . 'mt_guest_leads';
$table_stores   = $wpdb->prefix . 'mt_stores';
$table_campaigns= $wpdb->prefix . 'mt_campaigns';
$table_domains  = $wpdb->prefix . 'mt_email_domains';
$table_responses= $wpdb->prefix . 'mt_campaign_responses';
$table_packages = $wpdb->prefix . 'mt_packages';

// ── Real account metrics ──────────────────────────────────────────────────
$total_flock     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_leads WHERE brand_id = %d AND status != 'deleted'", $brand->id ) );
$total_locations = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_stores WHERE brand_id = %d", $brand->id ) );

// ── Plan name from packages table ────────────────────────────────────────
$package = $wpdb->get_row( $wpdb->prepare(
    "SELECT package_name, email_limit FROM $table_packages WHERE package_slug = %s",
    $brand->package_slug
) );
$plan_name     = $package ? $package->package_name : ucwords( str_replace( ['mt_', '_'], ['', ' '], $brand->package_slug ?? 'Starter' ) );
$monthly_limit = intval( $brand->email_limit ) ?: ( $package ? intval( $package->email_limit ) : 1000 );

// ── Emails sent this calendar month (via campaign_responses) ─────────────
$first_of_month          = gmdate( 'Y-m-01 00:00:00' );
$emails_sent_this_month  = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(r.id)
     FROM $table_responses r
     INNER JOIN $table_campaigns c ON r.campaign_id = c.id
     WHERE c.brand_id = %d AND r.created_at >= %s",
    $brand->id, $first_of_month
) );
$quota_percentage = $monthly_limit > 0 ? min( 100, round( ( $emails_sent_this_month / $monthly_limit ) * 100, 1 ) ) : 0;

// Quota colour changes as it fills
$quota_color = '#22c55e';
if ( $quota_percentage >= 90 )      { $quota_color = '#ef4444'; }
elseif ( $quota_percentage >= 70 )  { $quota_color = '#f59e0b'; }

// ── Recent Activity (merged feed from leads, campaigns, domains) ──────────
$recent_leads = $wpdb->get_results( $wpdb->prepare(
    "SELECT gl.guest_name, gl.email, gl.created_at, s.store_name
     FROM $table_leads gl
     LEFT JOIN $table_stores s ON gl.store_id = s.id
     WHERE gl.brand_id = %d AND gl.status = 'active'
     ORDER BY gl.created_at DESC LIMIT 3",
    $brand->id
) );

$recent_campaigns = $wpdb->get_results( $wpdb->prepare(
    "SELECT campaign_name, campaign_type, created_at FROM $table_campaigns
     WHERE brand_id = %d ORDER BY created_at DESC LIMIT 2",
    $brand->id
) );

$recent_domains = $wpdb->get_results( $wpdb->prepare(
    "SELECT domain_name, status, created_at FROM $table_domains
     WHERE brand_id = %d ORDER BY created_at DESC LIMIT 2",
    $brand->id
) );

// Build unified activity array
$activity_items = [];
foreach ( $recent_leads as $lead ) {
    $name = ! empty( $lead->guest_name ) ? $lead->guest_name : $lead->email;
    $loc  = ! empty( $lead->store_name ) ? ' at ' . esc_html( $lead->store_name ) : '';
    $activity_items[] = [
        'dot'  => '#22c55e',
        'text' => 'New guest <strong>' . esc_html( $name ) . '</strong> captured' . $loc,
        'time' => $lead->created_at,
    ];
}
foreach ( $recent_campaigns as $camp ) {
    $label = $camp->campaign_type === 'sent' ? 'Campaign <strong>' . esc_html( $camp->campaign_name ) . '</strong> sent' : 'Campaign <strong>' . esc_html( $camp->campaign_name ) . '</strong> saved as draft';
    $dot   = $camp->campaign_type === 'sent' ? 'var(--mt-primary)' : '#6366f1';
    $activity_items[] = [ 'dot' => $dot, 'text' => $label, 'time' => $camp->created_at ];
}
foreach ( $recent_domains as $dom ) {
    $dot   = $dom->status === 'verified' ? '#22c55e' : '#f59e0b';
    $label = 'Domain <strong>' . esc_html( $dom->domain_name ) . '</strong> — ' . esc_html( $dom->status );
    $activity_items[] = [ 'dot' => $dot, 'text' => $label, 'time' => $dom->created_at ];
}

// Sort by most recent and cap at 5
usort( $activity_items, fn( $a, $b ) => strcmp( $b['time'], $a['time'] ) );
$activity_items = array_slice( $activity_items, 0, 5 );

// Human-readable time helper
if ( ! function_exists( 'mt_human_time' ) ) :
function mt_human_time( $datetime ) {
    $diff = time() - strtotime( $datetime );
    if ( $diff < 60 )           return 'Just now';
    if ( $diff < 3600 )         return floor( $diff / 60 ) . ' minutes ago';
    if ( $diff < 86400 )        return floor( $diff / 3600 ) . ' hours ago';
    if ( $diff < 172800 )       return 'Yesterday';
    if ( $diff < 604800 )       return floor( $diff / 86400 ) . ' days ago';
    return gmdate( 'M j, Y', strtotime( $datetime ) );
}
endif;

// ── Extra hub data ────────────────────────────────────────────────────────
// New guests captured today
$today_start = gmdate( 'Y-m-d 00:00:00' );
$guests_today = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(id) FROM $table_leads WHERE brand_id = %d AND status = 'active' AND created_at >= %s",
    $brand->id, $today_start
) );

// Active (non-trashed) guest count
$active_guests = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(id) FROM $table_leads WHERE brand_id = %d AND status = 'active'",
    $brand->id
) );

// Trashed count
$trashed_guests = $total_flock - $active_guests;

// Last campaign
$last_campaign = $wpdb->get_row( $wpdb->prepare(
    "SELECT campaign_name, campaign_type, created_at FROM $table_campaigns WHERE brand_id = %d ORDER BY created_at DESC LIMIT 1",
    $brand->id
) );

// Total campaigns sent (type='sent')
$campaigns_sent_total = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(id) FROM $table_campaigns WHERE brand_id = %d AND campaign_type = 'sent'",
    $brand->id
) );

// Verified domains
$verified_domains = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(id) FROM $table_domains WHERE brand_id = %d AND status = 'verified'",
    $brand->id
) );

// WiFi connections this month
$wifi_this_month = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(id) FROM $table_leads WHERE brand_id = %d AND created_at >= %s",
    $brand->id, $first_of_month
) );

$first_name = ! empty( $current_user->user_firstname ) ? $current_user->user_firstname : $current_user->display_name;
?>

<style>
    .ov-hero{background:linear-gradient(135deg,var(--mt-sidebar-bg) 0%,var(--mt-primary) 100%);border-radius:16px;padding:28px 32px;margin-bottom:24px;color:white;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;}
    .ov-hero-title{font-size:28px;font-weight:900;margin:6px 0 4px;letter-spacing:-0.02em;}
    .ov-hero-sub{font-size:13px;opacity:.8;margin:0;}
    .ov-hero-badge{font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:rgba(255,255,255,0.18);border-radius:99px;padding:3px 12px;display:inline-block;margin-bottom:6px;}
    .ov-btn{background:white;color:var(--mt-sidebar-bg);border:none;border-radius:10px;padding:10px 20px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:8px;transition:all .15s;white-space:nowrap;}
    .ov-btn:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,0,0,.2);}
    .ov-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;}
    .ov-stat{background:white;border-radius:14px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,.06);}
    .ov-stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:14px;}
    .ov-stat-label{font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;}
    .ov-stat-val{font-size:26px;font-weight:900;color:#111827;line-height:1;}
    .ov-stat-sub{font-size:11px;color:#6b7280;margin-top:4px;}
    .ov-quota-track{height:8px;background:#f3f4f6;border-radius:99px;overflow:hidden;margin:10px 0 4px;}
    .ov-quota-fill{height:100%;border-radius:99px;background:var(--mt-primary);transition:width .6s ease;}
    .ov-bottom{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
    .ov-card{background:white;border-radius:14px;padding:22px 24px;box-shadow:0 1px 4px rgba(0,0,0,.06);}
    .ov-card-title{font-size:15px;font-weight:800;color:#111827;margin-bottom:16px;display:flex;align-items:center;gap:8px;}
    .ov-link{display:flex;align-items:center;gap:14px;padding:12px 14px;border-radius:10px;text-decoration:none;transition:background .15s;border:1.5px solid #f3f4f6;margin-bottom:8px;}
    .ov-link:hover{background:#f9fafb;border-color:#e5e7eb;}
    .ov-link-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
    .ov-link-name{font-size:13px;font-weight:700;color:#111827;}
    .ov-link-desc{font-size:11px;color:#9ca3af;margin-top:1px;}
    .ov-link-arrow{margin-left:auto;color:#d1d5db;font-size:14px;}
    .ov-activity-item{display:flex;align-items:flex-start;gap:12px;padding:10px 0;border-bottom:1px solid #f3f4f6;}
    .ov-activity-item:last-child{border-bottom:none;}
    .ov-activity-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:5px;}
    .ov-activity-text{font-size:12px;color:#374151;}
    .ov-activity-time{font-size:11px;color:#9ca3af;margin-top:2px;}

    /* ── Hub module cards ── */
    .ov-hub{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px;}
    .ov-hub-card{background:white;border-radius:14px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,.06);display:flex;flex-direction:column;}
    .ov-hub-header{display:flex;align-items:center;gap:10px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid #f3f4f6;}
    .ov-hub-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
    .ov-hub-title{font-size:13px;font-weight:800;color:#111827;}
    .ov-hub-sub{font-size:11px;color:#9ca3af;}
    .ov-hub-metric{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid #f9fafb;}
    .ov-hub-metric:last-of-type{border-bottom:none;}
    .ov-hub-metric-label{font-size:12px;color:#6b7280;}
    .ov-hub-metric-val{font-size:13px;font-weight:800;color:#111827;}
    .ov-hub-footer{margin-top:auto;padding-top:14px;}
    .ov-hub-btn{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:9px;border-radius:8px;font-size:12px;font-weight:700;border:1.5px solid var(--mt-primary);color:var(--mt-primary);text-decoration:none;transition:all .15s;background:transparent;}
    .ov-hub-btn:hover{background:var(--mt-primary);color:white;}

    /* ── Mobile ── */
    @media(max-width:768px){
        .ov-hero{padding:20px;flex-direction:column;align-items:flex-start;gap:14px;}
        .ov-hero-title{font-size:22px;}
        .ov-stats{grid-template-columns:repeat(2,1fr);gap:10px;}
        .ov-hub{grid-template-columns:1fr;gap:12px;}
        .ov-bottom{grid-template-columns:1fr;gap:14px;}
        .ov-stat-val{font-size:22px;}
    }
    @media(max-width:420px){
        .ov-stats{grid-template-columns:1fr;}
    }
</style>

<!-- Hero Banner -->
<div class="ov-hero">
    <div>
        <div class="ov-hero-badge">Global Account Status</div>
        <div class="ov-hero-title">Hello, <?php echo esc_html($first_name); ?> 👋</div>
        <p class="ov-hero-sub">Here's the top-level overview of your MailToucan account.</p>
    </div>
    <button onclick="alert('Billing portal coming soon!')" class="ov-btn">
        <i class="fa-solid fa-credit-card"></i> Manage Plan
    </button>
</div>

<!-- KPI Stats -->
<div class="ov-stats">
    <div class="ov-stat">
        <div class="ov-stat-icon" style="background:rgba(99,102,241,.12);color:#6366f1;"><i class="fa-solid fa-crown"></i></div>
        <div class="ov-stat-label">Current Plan</div>
        <div class="ov-stat-val"><?php echo esc_html( $plan_name ); ?></div>
        <div class="ov-stat-sub" style="color:#22c55e;font-weight:700;"><i class="fa-solid fa-check-circle"></i> Active &amp; Healthy</div>
    </div>
    <div class="ov-stat">
        <div class="ov-stat-icon" style="background:rgba(59,130,246,.12);color:#3b82f6;"><i class="fa-solid fa-store"></i></div>
        <div class="ov-stat-label">Connected Locations</div>
        <div class="ov-stat-val"><?php echo number_format($total_locations); ?></div>
        <div class="ov-stat-sub">Active WiFi Zones</div>
    </div>
    <div class="ov-stat">
        <div class="ov-stat-icon" style="background:rgba(168,85,247,.12);color:#a855f7;"><i class="fa-solid fa-users"></i></div>
        <div class="ov-stat-label">Global Flock Size</div>
        <div class="ov-stat-val"><?php echo number_format($total_flock); ?></div>
        <div class="ov-stat-sub">Total Captured Guests</div>
    </div>
    <div class="ov-stat">
        <div class="ov-stat-icon" style="background:rgba(var(--mt-primary-rgb,8,145,178),.12);color:var(--mt-primary);"><i class="fa-solid fa-paper-plane"></i></div>
        <div class="ov-stat-label">Monthly Email Quota</div>
        <div style="font-size:20px;font-weight:900;color:#111827;line-height:1;"><?php echo number_format( $emails_sent_this_month ); ?> <span style="font-size:12px;font-weight:500;color:#9ca3af;">/ <?php echo number_format( $monthly_limit ); ?></span></div>
        <div class="ov-quota-track"><div class="ov-quota-fill" style="width:<?php echo esc_attr( $quota_percentage ); ?>%;background:<?php echo esc_attr( $quota_color ); ?>;"></div></div>
        <div style="font-size:10px;color:<?php echo esc_attr( $quota_color ); ?>;text-align:right;font-weight:600;"><?php echo $quota_percentage; ?>% Used</div>
    </div>
</div>

<!-- Module Hub Cards -->
<div class="ov-hub">

    <!-- 📶 Network & WiFi -->
    <div class="ov-hub-card">
        <div class="ov-hub-header">
            <div class="ov-hub-icon" style="background:rgba(59,130,246,.12);color:#3b82f6;"><i class="fa-solid fa-wifi"></i></div>
            <div>
                <div class="ov-hub-title">Network & WiFi</div>
                <div class="ov-hub-sub">Locations · Splash · Insights</div>
            </div>
        </div>
        <div class="ov-hub-metric">
            <span class="ov-hub-metric-label"><i class="fa-solid fa-store" style="margin-right:5px;color:#3b82f6;"></i>Active Locations</span>
            <span class="ov-hub-metric-val"><?php echo number_format($total_locations); ?></span>
        </div>
        <div class="ov-hub-metric">
            <span class="ov-hub-metric-label"><i class="fa-solid fa-signal" style="margin-right:5px;color:#3b82f6;"></i>Connections This Month</span>
            <span class="ov-hub-metric-val"><?php echo number_format($wifi_this_month); ?></span>
        </div>
        <div class="ov-hub-metric">
            <span class="ov-hub-metric-label"><i class="fa-solid fa-users" style="margin-right:5px;color:#3b82f6;"></i>New Guests Today</span>
            <span class="ov-hub-metric-val"><?php echo number_format($guests_today); ?></span>
        </div>
        <div class="ov-hub-footer">
            <a href="?view=wifi_insights" class="ov-hub-btn"><i class="fa-solid fa-chart-area"></i> View WiFi Details</a>
        </div>
    </div>

    <!-- 📧 Email Marketing -->
    <div class="ov-hub-card">
        <div class="ov-hub-header">
            <div class="ov-hub-icon" style="background:rgba(16,185,129,.12);color:#10b981;"><i class="fa-solid fa-paper-plane"></i></div>
            <div>
                <div class="ov-hub-title">Email Marketing</div>
                <div class="ov-hub-sub">Campaigns · Domains · Delivery</div>
            </div>
        </div>
        <div class="ov-hub-metric">
            <span class="ov-hub-metric-label"><i class="fa-solid fa-paper-plane" style="margin-right:5px;color:#10b981;"></i>Sent This Month</span>
            <span class="ov-hub-metric-val"><?php echo number_format($emails_sent_this_month); ?> <span style="font-size:11px;font-weight:500;color:#9ca3af;">/ <?php echo number_format($monthly_limit); ?></span></span>
        </div>
        <div style="padding:4px 0 8px;">
            <div class="ov-quota-track" style="margin:0;"><div class="ov-quota-fill" style="width:<?php echo esc_attr($quota_percentage); ?>%;background:<?php echo esc_attr($quota_color); ?>;"></div></div>
            <div style="font-size:10px;color:<?php echo esc_attr($quota_color); ?>;font-weight:600;text-align:right;margin-top:3px;"><?php echo $quota_percentage; ?>% of quota</div>
        </div>
        <div class="ov-hub-metric">
            <span class="ov-hub-metric-label"><i class="fa-solid fa-bullhorn" style="margin-right:5px;color:#10b981;"></i>Total Campaigns Sent</span>
            <span class="ov-hub-metric-val"><?php echo number_format($campaigns_sent_total); ?></span>
        </div>
        <div class="ov-hub-metric">
            <span class="ov-hub-metric-label"><i class="fa-solid fa-globe" style="margin-right:5px;color:#10b981;"></i>Verified Domains</span>
            <span class="ov-hub-metric-val"><?php echo number_format($verified_domains); ?></span>
        </div>
        <?php if($last_campaign): ?>
        <div style="margin:8px 0;padding:8px 10px;background:#f0fdf4;border-radius:8px;font-size:11px;color:#15803d;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <i class="fa-solid fa-circle-check" style="margin-right:5px;"></i>
            Last: <?php echo esc_html($last_campaign->campaign_name); ?>
        </div>
        <?php endif; ?>
        <div class="ov-hub-footer">
            <a href="?view=campaigns" class="ov-hub-btn"><i class="fa-solid fa-paper-plane"></i> View Campaigns</a>
        </div>
    </div>

    <!-- 👥 Guest Management -->
    <div class="ov-hub-card">
        <div class="ov-hub-header">
            <div class="ov-hub-icon" style="background:rgba(168,85,247,.12);color:#a855f7;"><i class="fa-solid fa-users"></i></div>
            <div>
                <div class="ov-hub-title">Guest Management</div>
                <div class="ov-hub-sub">The Roost · CRM · Segments</div>
            </div>
        </div>
        <div class="ov-hub-metric">
            <span class="ov-hub-metric-label"><i class="fa-solid fa-users" style="margin-right:5px;color:#a855f7;"></i>Total Flock Size</span>
            <span class="ov-hub-metric-val"><?php echo number_format($total_flock); ?></span>
        </div>
        <div class="ov-hub-metric">
            <span class="ov-hub-metric-label"><i class="fa-solid fa-user-check" style="margin-right:5px;color:#22c55e;"></i>Active Guests</span>
            <span class="ov-hub-metric-val"><?php echo number_format($active_guests); ?></span>
        </div>
        <div class="ov-hub-metric">
            <span class="ov-hub-metric-label"><i class="fa-solid fa-user-plus" style="margin-right:5px;color:#a855f7;"></i>New Today</span>
            <span class="ov-hub-metric-val"><?php echo number_format($guests_today); ?></span>
        </div>
        <?php if($trashed_guests > 0): ?>
        <div class="ov-hub-metric">
            <span class="ov-hub-metric-label"><i class="fa-solid fa-trash" style="margin-right:5px;color:#9ca3af;"></i>In Trash</span>
            <span class="ov-hub-metric-val" style="color:#9ca3af;"><?php echo number_format($trashed_guests); ?></span>
        </div>
        <?php endif; ?>
        <div class="ov-hub-footer">
            <a href="?view=crm" class="ov-hub-btn"><i class="fa-solid fa-table-list"></i> Open The Roost</a>
        </div>
    </div>
</div>

<!-- Recent Activity (full width) -->
<div class="ov-card" style="margin-bottom:20px;">
    <div class="ov-card-title"><i class="fa-solid fa-clock-rotate-left" style="color:var(--mt-primary);"></i> Recent Activity</div>
    <?php if ( empty( $activity_items ) ) : ?>
        <div style="text-align:center;padding:28px 0;color:#9ca3af;">
            <i class="fa-solid fa-dove" style="font-size:28px;opacity:.3;margin-bottom:8px;display:block;"></i>
            <p style="font-size:13px;">No activity yet — add a location or capture your first guest to get started.</p>
        </div>
    <?php else : ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:0 24px;">
            <?php foreach ( $activity_items as $item ) : ?>
                <div class="ov-activity-item">
                    <div class="ov-activity-dot" style="background:<?php echo esc_attr( $item['dot'] ); ?>;"></div>
                    <div>
                        <div class="ov-activity-text"><?php echo wp_kses( $item['text'], [ 'strong' => [] ] ); ?></div>
                        <div class="ov-activity-time"><?php echo esc_html( mt_human_time( $item['time'] ) ); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div style="margin-top:12px;padding-top:12px;border-top:1px solid #f3f4f6;display:flex;gap:16px;justify-content:flex-end;">
        <a href="?view=crm" style="font-size:12px;font-weight:700;color:var(--mt-primary);text-decoration:none;">View All Guests →</a>
        <a href="?view=campaigns" style="font-size:12px;font-weight:700;color:var(--mt-primary);text-decoration:none;">View Campaigns →</a>
    </div>
</div>
