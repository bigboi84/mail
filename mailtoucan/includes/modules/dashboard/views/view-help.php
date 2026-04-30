<?php
/**
 * MailToucan — Floating Help Chatbot + Per-Section FAQ
 * Included at the bottom of every dashboard page from class-mt-dashboard.php
 * Requires: $view (string), $brand (object), wp_nonce 'mt_app_nonce'
 */
if ( ! defined('ABSPATH') ) exit;

$help_nonce = wp_create_nonce('mt_app_nonce');

// ── Per-section FAQ data ──────────────────────────────────────────────────────
$faq_data = [
    'overview' => [
        ['q' => 'What does the Account Status page show?',    'a' => 'It shows a snapshot of your account health — active locations, recent campaign sends, lead growth trends, and your current AI credit usage for the month.'],
        ['q' => 'How do I upgrade my plan?',                  'a' => 'Contact your account manager or reach out to support. Plan upgrades unlock additional locations, higher email send limits, and more AI credits.'],
        ['q' => 'Why is my lead count not updating?',         'a' => 'Lead counts refresh every few minutes. If it\'s been a while, try refreshing the page. Make sure your WiFi captive portal is active and capturing leads correctly.'],
    ],
    'brand' => [
        ['q' => 'How do I change my brand colours?',          'a' => 'Go to Brand Settings and update your primary colour. This colour is used across your splash pages, email templates, and the dashboard accent.'],
        ['q' => 'Can I upload multiple logos?',               'a' => 'Yes — you can upload a main logo, a dark-mode variant, and a square icon. Use the Media Vault to manage all your brand assets.'],
        ['q' => 'What is the HQ address used for?',           'a' => 'Your HQ address is inserted into the footer of outbound marketing emails as required by CAN-SPAM and GDPR regulations.'],
    ],
    'crm' => [
        ['q' => 'What is the difference between Active and Trashed leads?', 'a' => 'Active leads receive marketing emails and are counted in your audience. Trashed leads are soft-deleted and hidden from campaigns — you can restore them at any time.'],
        ['q' => 'How do I export my leads?',                  'a' => 'Use the Export button in the CRM toolbar to download a CSV of all active leads for your brand.'],
        ['q' => 'Can I segment leads by location?',           'a' => 'Yes — use the Location filter in the CRM header to view leads captured at a specific venue. Campaign targeting also supports location-based filtering.'],
        ['q' => 'What does the CRM Advisor AI do?',           'a' => 'The CRM Advisor analyses your lead data and suggests growth strategies, re-engagement ideas, and audience insights. Click the ✨ AI Advisor button in the CRM toolbar.'],
    ],
    'campaigns' => [
        ['q' => 'What types of campaign can I create?',       'a' => 'You can create one-time broadcast emails (to your full list or a segment), birthday reward campaigns, and win-back campaigns for lapsed guests.'],
        ['q' => 'What are merge tags?',                       'a' => 'Merge tags like {{first_name}} or {{brand_name}} are automatically replaced with real data when the email is sent. Always preview before sending.'],
        ['q' => 'How do I pick a template for my campaign?',  'a' => 'In Step 3 of the campaign builder, choose from Starter Templates (pre-built designs) or your own Studio templates. You can also build a new one on-the-fly via AI.'],
        ['q' => 'Can I schedule a campaign?',                 'a' => 'Campaigns are currently queued for immediate dispatch when you click Send. Scheduled sending (pick a date/time) is coming in a future update.'],
    ],
    'studio' => [
        ['q' => 'What is the Email Studio?',                  'a' => 'The Email Studio is a drag-and-drop HTML email builder. You can create templates from scratch, start from a starter design, or let the AI build one for you based on a description.'],
        ['q' => 'How do I use the AI template builder?',      'a' => 'In the Studio, set the AI context to "✨ Build Full Template", choose a base style, describe what you want, and click Generate. The AI will produce a complete branded template.'],
        ['q' => 'What are merge tags and do I need to keep them?', 'a' => 'Yes — merge tags like {{first_name}} and {{unsubscribe_url}} are required. The unsubscribe link is mandatory for compliance. Keep them as-is in your templates.'],
        ['q' => 'How do I trash or delete a template?',       'a' => 'Hover a template card and click the trash icon to move it to the Trash. From the Trash view you can restore it or permanently delete it.'],
    ],
    'workflows' => [
        ['q' => 'What is a workflow?',                        'a' => 'A workflow is an automated email trigger — for example, sending a birthday reward 7 days before a guest\'s birthday, or a win-back email after 90 days of inactivity.'],
        ['q' => 'How often does the workflow engine run?',    'a' => 'Workflows are processed hourly via WP-Cron. If your server has issues with WP-Cron, you can set up a real system cron to hit the WordPress cron endpoint.'],
        ['q' => 'Can I set a custom delay on a workflow?',    'a' => 'Yes — each workflow has a delay setting (in days). For example, a birthday workflow can send 7 days before or on the day itself.'],
        ['q' => 'How do I stop a workflow from sending?',     'a' => 'Toggle the workflow status to Inactive from the Workflows list. The engine will skip inactive workflows during processing.'],
    ],
    'email_insights' => [
        ['q' => 'What metrics are tracked?',                  'a' => 'MailToucan tracks email opens (via a tracking pixel), clicks on links, bounces, and unsubscribes. All stats are shown per campaign in the Insights dashboard.'],
        ['q' => 'Why is my open rate low?',                   'a' => 'Open rates depend on your subject line, send time, and list quality. Try A/B testing subject lines and sending at different times. Also check that your sender domain is verified.'],
        ['q' => 'What counts as a bounce?',                   'a' => 'A hard bounce means the email address is invalid or unreachable permanently. Soft bounces are temporary failures. MailToucan auto-suppresses hard bounces to protect your sender reputation.'],
    ],
    'delivery' => [
        ['q' => 'Do I need to set up SMTP?',                  'a' => 'For reliable deliverability, yes. Go to Delivery Settings → SMTP and enter your provider credentials (SendGrid, Mailgun, etc.). Without SMTP, emails send via the default WordPress mail function which is often blocked.'],
        ['q' => 'What is a "from" domain and why does it matter?', 'a' => 'Using your own domain (e.g. hello@yourbusiness.com) as the sender looks more professional and builds trust. You\'ll need to verify the domain by adding DNS records.'],
        ['q' => 'My test email landed in spam — what do I do?', 'a' => 'Check that your domain is verified with correct SPF, DKIM, and DMARC records. Use the DNS AI guide in the Domains section for step-by-step instructions. Also avoid spam trigger words in subject lines.'],
    ],
    'domains' => [
        ['q' => 'How do I verify my sender domain?',          'a' => 'Add your domain, then copy the SPF and DKIM records shown and add them to your DNS provider (Cloudflare, GoDaddy, etc.). Click Verify after adding them — propagation can take up to 48 hours.'],
        ['q' => 'What DNS records do I need?',                'a' => 'You need: (1) an SPF TXT record, (2) two DKIM CNAME records, and (3) a DMARC TXT record. The DNS AI Guide in the Domains section walks you through each one.'],
        ['q' => 'DNS verification keeps failing — help!',     'a' => 'Use the AI DNS Guide to diagnose the issue. Common causes: records added to the wrong subdomain, TTL not yet propagated, or a typo in the record value. DNS changes can take up to 48 hours.'],
    ],
    'locations' => [
        ['q' => 'How many locations can I add?',              'a' => 'The number of locations is determined by your package. Check the Account Status page or your package details to see your location limit.'],
        ['q' => 'What is a RADIUS server?',                   'a' => 'RADIUS is the authentication server that controls WiFi access on your router. MailToucan connects to RADIUS to approve or block guest WiFi sessions after they submit the splash page form.'],
        ['q' => 'How do I link a location to a splash page?', 'a' => 'When editing a location, select the Splash Page you want guests to see at that venue. Each location can have its own splash configuration.'],
    ],
    'splash' => [
        ['q' => 'What is a splash page?',                     'a' => 'A splash page (also called a captive portal) is the page guests see before they can access your WiFi. It captures their email, name, and optionally birthday — growing your CRM automatically.'],
        ['q' => 'Can I customise the look of my splash page?', 'a' => 'Yes — go to Splash Settings to change the background, logo, welcome text, form fields, and button colour. Changes go live immediately.'],
        ['q' => 'What is the "survey" field on the splash page?', 'a' => 'You can add a custom survey question (e.g. "How did you hear about us?") to the splash form. Responses are stored in the lead record for your reference.'],
    ],
    'wifi_insights' => [
        ['q' => 'What does the WiFi Insights page show?',     'a' => 'It shows daily connection counts, new vs returning guests, peak hours, and device type breakdown — all based on your WiFi logs.'],
        ['q' => 'Why are there no WiFi stats showing?',       'a' => 'WiFi stats only appear once guests start connecting through your captive portal. Make sure your router is pointing authentication to the MailToucan RADIUS server and the splash URL is correctly configured.'],
    ],
    'core' => [
        ['q' => 'What are own API keys for?',                 'a' => 'If you have your own OpenAI, Gemini, or Anthropic API key, you can enter it here. MailToucan will use your key for all AI features — bypassing platform credit limits entirely.'],
        ['q' => 'What happens when I run out of AI credits?', 'a' => 'AI features will show a "Credit limit reached" message until the next month\'s credits reset. You can add your own API key to bypass this, or ask your admin about upgrading your plan.'],
        ['q' => 'Which AI provider does MailToucan use?',     'a' => 'MailToucan tries Gemini first, then OpenAI, then Claude — based on what the admin has enabled. If you have your own key for a provider, that takes priority.'],
    ],
];

$current_faq = $faq_data[ $view ] ?? $faq_data['overview'];
$faq_json    = wp_json_encode($current_faq);
$view_label  = ucwords(str_replace(['_'], [' '], $view));
?>

<!-- ═══════════════════════════════════════════════════════════════════
     FLOATING HELP BUTTON
═══════════════════════════════════════════════════════════════════ -->
<button id="mt_help_btn" onclick="mtHelpToggle()" title="Help & Support" aria-label="Open help chat" style="
    position: fixed;
    bottom: 28px;
    right: 28px;
    z-index: 400;
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: var(--mt-primary, #FCC753);
    color: #fff;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 18px rgba(0,0,0,0.22);
    transition: transform 0.2s, box-shadow 0.2s;
    font-size: 20px;
" onmouseover="this.style.transform='scale(1.08)';this.style.boxShadow='0 6px 24px rgba(0,0,0,0.3)';"
   onmouseout="this.style.transform='scale(1)';this.style.boxShadow='0 4px 18px rgba(0,0,0,0.22)';">
    <i class="fa-solid fa-circle-question" id="mt_help_btn_icon"></i>
</button>

<!-- Unread badge (shown if AI reply arrives while panel is closed) -->
<span id="mt_help_badge" style="
    display:none;
    position:fixed;
    bottom:68px;
    right:24px;
    z-index:401;
    background:#ef4444;
    color:#fff;
    font-size:10px;
    font-weight:700;
    width:18px;
    height:18px;
    border-radius:50%;
    align-items:center;
    justify-content:center;
    border:2px solid #fff;
    pointer-events:none;
">1</span>

<!-- ═══════════════════════════════════════════════════════════════════
     HELP PANEL (slide-in drawer from bottom-right)
═══════════════════════════════════════════════════════════════════ -->
<div id="mt_help_panel" style="
    position: fixed;
    bottom: 92px;
    right: 28px;
    z-index: 399;
    width: 380px;
    max-width: calc(100vw - 40px);
    height: 560px;
    max-height: calc(100vh - 120px);
    background: var(--mt-card-bg, #fff);
    border-radius: 16px;
    box-shadow: 0 8px 40px rgba(0,0,0,0.18);
    display: none;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid var(--mt-border, #e5e7eb);
    transform: translateY(16px);
    opacity: 0;
    transition: transform 0.22s cubic-bezier(.4,0,.2,1), opacity 0.22s;
">

    <!-- Panel header -->
    <div style="background: var(--mt-primary, #FCC753); padding: 14px 16px; display:flex; align-items:center; gap:10px; flex-shrink:0;">
        <div style="width:34px;height:34px;background:rgba(255,255,255,0.25);border-radius:50%;display:flex;align-items:center;justify-content:center;">
            <i class="fa-solid fa-dove" style="color:#fff; font-size:16px;"></i>
        </div>
        <div style="flex:1;">
            <div style="color:#fff;font-weight:700;font-size:14px;line-height:1;">Toucan Help</div>
            <div style="color:rgba(255,255,255,0.8);font-size:11px;">Ask anything about MailToucan</div>
        </div>
        <button onclick="mtHelpToggle()" style="background:rgba(255,255,255,0.2);border:none;border-radius:6px;padding:4px 8px;color:#fff;cursor:pointer;font-size:12px;" title="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Tab bar -->
    <div style="display:flex;border-bottom:1px solid var(--mt-border,#e5e7eb);flex-shrink:0;">
        <button class="mt-help-tab active" data-tab="chat" onclick="mtHelpSwitchTab('chat',this)" style="flex:1;padding:10px 8px;border:none;background:none;cursor:pointer;font-size:12px;font-weight:600;color:var(--mt-primary,#FCC753);border-bottom:2px solid var(--mt-primary,#FCC753);">
            <i class="fa-solid fa-comments mr-1"></i> Chat
        </button>
        <button class="mt-help-tab" data-tab="faq" onclick="mtHelpSwitchTab('faq',this)" style="flex:1;padding:10px 8px;border:none;background:none;cursor:pointer;font-size:12px;font-weight:600;color:var(--mt-text-secondary,#6b7280);border-bottom:2px solid transparent;">
            <i class="fa-solid fa-circle-info mr-1"></i> <?php echo esc_html($view_label); ?> FAQ
        </button>
    </div>

    <!-- Chat tab -->
    <div id="mt_help_tab_chat" style="flex:1;display:flex;flex-direction:column;overflow:hidden;">
        <!-- Messages -->
        <div id="mt_help_messages" style="flex:1;overflow-y:auto;padding:14px 14px 8px;display:flex;flex-direction:column;gap:10px;">
            <!-- Welcome bubble -->
            <div class="mt-help-bubble-ai">
                <div style="width:26px;height:26px;background:var(--mt-primary,#FCC753);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa-solid fa-dove" style="color:#fff;font-size:11px;"></i>
                </div>
                <div class="mt-help-bubble-text">
                    👋 Hi! I'm your MailToucan assistant. Ask me anything about the platform, or check the <strong>FAQ tab</strong> for quick answers about <em><?php echo esc_html($view_label); ?></em>.
                </div>
            </div>
        </div>
        <!-- Suggested questions -->
        <div id="mt_help_suggestions" style="padding:0 14px 10px; display:flex; flex-wrap:wrap; gap:6px;">
            <?php
            $suggestions = array_slice($current_faq, 0, 2);
            foreach ($suggestions as $s) {
                $q = esc_attr($s['q']);
                $ql = esc_html(strlen($s['q']) > 50 ? substr($s['q'],0,48).'…' : $s['q']);
                echo "<button onclick=\"mtHelpAsk(" . json_encode($s['q']) . ")\" class=\"mt-help-suggest-btn\">{$ql}</button>";
            }
            ?>
        </div>
        <!-- Input bar -->
        <div style="padding:10px 14px 14px;border-top:1px solid var(--mt-border,#e5e7eb);display:flex;gap:8px;flex-shrink:0;">
            <textarea id="mt_help_input"
                placeholder="Ask a question…"
                rows="2"
                onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();mtHelpSend();}"
                style="flex:1;resize:none;border:1px solid var(--mt-border,#e5e7eb);border-radius:8px;padding:8px 10px;font-size:13px;font-family:inherit;background:var(--mt-page-bg,#f3f4f6);color:var(--mt-text-primary,#111827);outline:none;"></textarea>
            <button onclick="mtHelpSend()" id="mt_help_send_btn" style="width:38px;height:38px;background:var(--mt-primary,#FCC753);border:none;border-radius:8px;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;align-self:flex-end;" title="Send">
                <i class="fa-solid fa-paper-plane" style="font-size:14px;"></i>
            </button>
        </div>
    </div>

    <!-- FAQ tab -->
    <div id="mt_help_tab_faq" style="flex:1;overflow-y:auto;padding:14px;display:none;flex-direction:column;gap:10px;">
        <p style="font-size:12px;color:var(--mt-text-secondary,#6b7280);margin:0 0 6px;">Quick answers for the <strong><?php echo esc_html($view_label); ?></strong> section:</p>
        <?php foreach ($current_faq as $item) : ?>
        <div class="mt-faq-item" style="border:1px solid var(--mt-border,#e5e7eb);border-radius:8px;overflow:hidden;">
            <button class="mt-faq-q" onclick="this.parentElement.classList.toggle('open')" style="width:100%;text-align:left;background:var(--mt-page-bg,#f9fafb);border:none;padding:10px 12px;font-size:13px;font-weight:600;cursor:pointer;color:var(--mt-text-primary,#111827);display:flex;justify-content:space-between;align-items:center;gap:8px;">
                <span><?php echo esc_html($item['q']); ?></span>
                <i class="fa-solid fa-chevron-down" style="font-size:10px;flex-shrink:0;transition:transform 0.2s;"></i>
            </button>
            <div class="mt-faq-a" style="display:none;padding:10px 12px;font-size:13px;color:var(--mt-text-secondary,#374151);line-height:1.6;border-top:1px solid var(--mt-border,#e5e7eb);">
                <?php echo esc_html($item['a']); ?>
            </div>
        </div>
        <?php endforeach; ?>
        <div style="margin-top:8px;padding:10px;background:rgba(var(--mt-primary-rgb,252,199,83),0.1);border-radius:8px;font-size:12px;color:var(--mt-text-secondary,#6b7280);">
            💬 Still stuck? Switch to the <strong>Chat</strong> tab and ask the AI directly.
        </div>
    </div>

</div><!-- /#mt_help_panel -->

<!-- ═══════════════════════════════════════════════════════════════════
     HELP CHATBOT STYLES + JS
═══════════════════════════════════════════════════════════════════ -->
<style>
.mt-help-bubble-ai  { display:flex; align-items:flex-start; gap:8px; }
.mt-help-bubble-user { display:flex; justify-content:flex-end; }
.mt-help-bubble-text {
    background: var(--mt-page-bg, #f3f4f6);
    color: var(--mt-text-primary, #111827);
    border-radius: 0 12px 12px 12px;
    padding: 9px 12px;
    font-size: 13px;
    line-height: 1.55;
    max-width: 86%;
}
.mt-help-bubble-user .mt-help-bubble-text {
    background: var(--mt-primary, #FCC753);
    color: #fff;
    border-radius: 12px 0 12px 12px;
}
.mt-help-suggest-btn {
    background: var(--mt-page-bg, #f3f4f6);
    border: 1px solid var(--mt-border, #e5e7eb);
    border-radius: 20px;
    padding: 5px 10px;
    font-size: 11px;
    cursor: pointer;
    color: var(--mt-text-primary, #111827);
    transition: background 0.15s;
}
.mt-help-suggest-btn:hover { background: var(--mt-border, #e5e7eb); }
.mt-help-tab { transition: color 0.15s, border-color 0.15s; }
.mt-faq-item.open .mt-faq-a { display: block !important; }
.mt-faq-item.open .mt-faq-q i { transform: rotate(180deg); }
.mt-help-typing {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 8px 12px;
    background: var(--mt-page-bg, #f3f4f6);
    border-radius: 0 12px 12px 12px;
}
.mt-help-typing span {
    width: 6px; height: 6px;
    background: var(--mt-text-secondary, #9ca3af);
    border-radius: 50%;
    display: inline-block;
    animation: mtHelpBounce 1.2s infinite;
}
.mt-help-typing span:nth-child(2) { animation-delay: 0.2s; }
.mt-help-typing span:nth-child(3) { animation-delay: 0.4s; }
@keyframes mtHelpBounce {
    0%, 60%, 100% { transform: translateY(0); }
    30%            { transform: translateY(-5px); }
}
</style>

<script>
(function() {
    var mtHelpOpen     = false;
    var mtHelpHistory  = [];
    var mtHelpSection  = '<?php echo esc_js($view); ?>';
    var mtHelpNonce    = '<?php echo esc_js($help_nonce); ?>';
    var mtHelpAjaxUrl  = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

    // ── Toggle open/close ────────────────────────────────────────────
    window.mtHelpToggle = function() {
        var panel  = document.getElementById('mt_help_panel');
        var badge  = document.getElementById('mt_help_badge');
        var btnI   = document.getElementById('mt_help_btn_icon');
        if (!mtHelpOpen) {
            panel.style.display = 'flex';
            requestAnimationFrame(function() {
                panel.style.opacity  = '1';
                panel.style.transform = 'translateY(0)';
            });
            mtHelpOpen = true;
            btnI.className = 'fa-solid fa-xmark';
            badge.style.display = 'none';
            // Focus input
            setTimeout(function() {
                var inp = document.getElementById('mt_help_input');
                if (inp) inp.focus();
            }, 240);
        } else {
            panel.style.opacity   = '0';
            panel.style.transform = 'translateY(16px)';
            setTimeout(function() { panel.style.display = 'none'; }, 230);
            mtHelpOpen = false;
            btnI.className = 'fa-solid fa-circle-question';
        }
    };

    // ── Switch tabs ──────────────────────────────────────────────────
    window.mtHelpSwitchTab = function(tab, btn) {
        document.getElementById('mt_help_tab_chat').style.display = (tab === 'chat') ? 'flex' : 'none';
        document.getElementById('mt_help_tab_faq').style.display  = (tab === 'faq')  ? 'flex' : 'none';
        document.querySelectorAll('.mt-help-tab').forEach(function(b) {
            b.style.color        = 'var(--mt-text-secondary,#6b7280)';
            b.style.borderBottom = '2px solid transparent';
        });
        btn.style.color        = 'var(--mt-primary,#FCC753)';
        btn.style.borderBottom = '2px solid var(--mt-primary,#FCC753)';
    };

    // ── Pre-fill and send a suggested question ───────────────────────
    window.mtHelpAsk = function(question) {
        document.getElementById('mt_help_input').value = question;
        mtHelpSend();
    };

    // ── Send message ─────────────────────────────────────────────────
    window.mtHelpSend = function() {
        var inp = document.getElementById('mt_help_input');
        var msg = inp.value.trim();
        if (!msg) return;
        inp.value = '';

        // Hide suggestions after first message
        var sugg = document.getElementById('mt_help_suggestions');
        if (sugg) sugg.style.display = 'none';

        // Show user bubble
        mtHelpAddBubble('user', msg);
        mtHelpHistory.push({role:'user', content:msg});

        // Show typing indicator
        var typingId = 'mt_typing_' + Date.now();
        var typingEl = document.createElement('div');
        typingEl.id = typingId;
        typingEl.className = 'mt-help-bubble-ai';
        typingEl.innerHTML = '<div style="width:26px;height:26px;background:var(--mt-primary,#FCC753);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fa-solid fa-dove" style="color:#fff;font-size:11px;"></i></div><div class="mt-help-typing"><span></span><span></span><span></span></div>';
        document.getElementById('mt_help_messages').appendChild(typingEl);
        mtHelpScrollBottom();

        // Disable send button
        var sendBtn = document.getElementById('mt_help_send_btn');
        sendBtn.disabled = true;
        sendBtn.style.opacity = '0.5';

        // AJAX call
        var body = new URLSearchParams({
            action:          'mt_ai_help_chat',
            security:        mtHelpNonce,
            message:         msg,
            current_section: mtHelpSection
        });

        fetch(mtHelpAjaxUrl, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString()})
        .then(function(r) { return r.json(); })
        .then(function(d) {
            var el = document.getElementById(typingId);
            if (el) el.remove();

            sendBtn.disabled = false;
            sendBtn.style.opacity = '1';

            if (d.success && d.data && (d.data.text || d.data.reply)) {
                var reply = d.data.text || d.data.reply;
                mtHelpHistory.push({role:'assistant', content:reply});
                mtHelpAddBubble('ai', reply);
                // Badge if panel is closed
                if (!mtHelpOpen) {
                    var badge = document.getElementById('mt_help_badge');
                    badge.style.display = 'flex';
                }
            } else {
                var errMsg = (d.data && d.data.message) ? d.data.message : 'Something went wrong. Please try again.';
                mtHelpAddBubble('ai', '⚠️ ' + errMsg);
            }
        })
        .catch(function() {
            var el = document.getElementById(typingId);
            if (el) el.remove();
            sendBtn.disabled = false;
            sendBtn.style.opacity = '1';
            mtHelpAddBubble('ai', '⚠️ Could not reach the server. Please check your connection and try again.');
        });
    };

    // ── Add a chat bubble ────────────────────────────────────────────
    function mtHelpAddBubble(role, text) {
        var container = document.getElementById('mt_help_messages');
        var wrap = document.createElement('div');
        wrap.className = (role === 'ai') ? 'mt-help-bubble-ai' : 'mt-help-bubble-user';

        var bubble = document.createElement('div');
        bubble.className = 'mt-help-bubble-text';
        bubble.innerHTML = mtHelpFormat(text);

        if (role === 'ai') {
            var avatar = document.createElement('div');
            avatar.style.cssText = 'width:26px;height:26px;background:var(--mt-primary,#FCC753);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;';
            avatar.innerHTML = '<i class="fa-solid fa-dove" style="color:#fff;font-size:11px;"></i>';
            wrap.appendChild(avatar);
        }
        wrap.appendChild(bubble);
        container.appendChild(wrap);
        mtHelpScrollBottom();
    }

    // ── Scroll messages to bottom ────────────────────────────────────
    function mtHelpScrollBottom() {
        var m = document.getElementById('mt_help_messages');
        if (m) setTimeout(function() { m.scrollTop = m.scrollHeight; }, 50);
    }

    // ── Simple markdown-lite formatter ──────────────────────────────
    function mtHelpFormat(text) {
        // Escape HTML first
        text = text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        // Bold **text**
        text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        // Inline code `code`
        text = text.replace(/`([^`]+)`/g, '<code style="background:rgba(0,0,0,0.08);padding:1px 4px;border-radius:3px;font-size:12px;">$1</code>');
        // Line breaks
        text = text.replace(/\n/g, '<br>');
        return text;
    }

})();
</script>
