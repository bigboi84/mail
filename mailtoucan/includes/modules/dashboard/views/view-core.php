<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

// Brand config
$brand_config  = json_decode($brand->brand_config, true) ?: [];
$brand_color   = !empty($brand->primary_color) ? $brand->primary_color : '#0f172a';
$mt_palette    = get_option('mt_brand_palette', ['accent' => '#FCC753', 'dark' => '#1A232E']);

// Own API keys (stored per-brand in brand_config.api_keys)
$own_keys      = $brand_config['api_keys'] ?? [];
$own_openai    = !empty($own_keys['own_openai_key'])    ? $own_keys['own_openai_key']    : '';
$own_gemini    = !empty($own_keys['own_gemini_key'])    ? $own_keys['own_gemini_key']    : '';
$own_anthropic = !empty($own_keys['own_anthropic_key']) ? $own_keys['own_anthropic_key'] : '';

// Platform AI usage for this period
$period       = current_time('Y-m');
$ai_table     = $wpdb->prefix . 'mt_ai_usage';
$ai_sections  = class_exists('MT_AI_Engine') ? MT_AI_Engine::get_sections() : [];
$ai_defaults  = class_exists('MT_AI_Engine') ? MT_AI_Engine::get_default_limits() : [];
$usage_rows   = $wpdb->get_results( $wpdb->prepare(
    "SELECT section, calls_used FROM $ai_table WHERE brand_id = %d AND period = %s",
    $brand->id, $period
), OBJECT_K );

// Platform global AI on/off
$platform_ai_enabled = get_option('mt_ai_enabled', '1') === '1';
?>

<style>
    :root {
        --mt-brand: <?php echo esc_html($brand_color); ?>;
        --mt-accent: <?php echo esc_html($mt_palette['accent']); ?>;
    }
    .core-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 28px 32px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); margin-bottom: 24px; }
    .core-section-title { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #9ca3af; margin-bottom: 16px; }
    .credit-bar { height: 8px; border-radius: 99px; background: #f1f5f9; overflow: hidden; }
    .credit-bar-fill { height: 100%; border-radius: 99px; background: var(--mt-brand); transition: width 0.5s; }
    .key-field { width: 100%; padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 10px; font-family: monospace; font-size: 13px; outline: none; background: #f8fafc; transition: border-color 0.2s; }
    .key-field:focus { border-color: var(--mt-brand); background: #fff; }
    .key-status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; }
    .vw-primary-btn { background: var(--mt-brand); color: #fff; border: none; border-radius: 10px; padding: 10px 20px; font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 8px; transition: filter .15s; }
    .vw-primary-btn:hover { filter: brightness(1.1); }
</style>

<div class="p-8 max-w-3xl mx-auto">

    <div class="flex items-end justify-between mb-8">
        <div>
            <div class="text-2xl font-black text-gray-900 flex items-center gap-2">
                <i class="fa-solid fa-key" style="color:var(--mt-brand);"></i> API & Credits
            </div>
            <div class="text-sm text-gray-500 mt-1">Platform AI credits and optional custom API keys.</div>
        </div>
    </div>

    <!-- PLATFORM AI CREDITS -->
    <div class="core-card">
        <div class="core-section-title">✨ Platform AI Credits — <?php echo esc_html(date('F Y')); ?></div>

        <?php if (!$platform_ai_enabled): ?>
            <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 mb-6 text-sm font-semibold flex items-start gap-3">
                <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                <span>Platform AI is currently disabled by your account administrator. AI features are unavailable until it is re-enabled.</span>
            </div>
        <?php endif; ?>

        <?php if (empty($ai_sections)): ?>
            <p class="text-sm text-gray-500 italic">AI engine not loaded.</p>
        <?php else: ?>
            <div class="space-y-5">
                <?php foreach ($ai_sections as $key => $label):
                    $limit = class_exists('MT_AI_Engine') ? MT_AI_Engine::get_section_limit($brand->id, $key) : ($ai_defaults[$key] ?? 5);
                    $used  = isset($usage_rows[$key]) ? (int)$usage_rows[$key]->calls_used : 0;
                    $pct   = ($limit > 0) ? min(100, round(($used / $limit) * 100)) : 0;
                    $remaining = ($limit === -1) ? '∞' : max(0, $limit - $used);
                    $bar_color = $pct >= 90 ? '#ef4444' : ($pct >= 60 ? '#f59e0b' : 'var(--mt-brand)');
                ?>
                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <span class="text-sm font-bold text-gray-800"><?php echo esc_html($label); ?></span>
                        <span class="text-xs font-bold <?php echo ($limit !== -1 && $used >= $limit) ? 'text-red-600' : 'text-gray-500'; ?>">
                            <?php echo $used; ?> / <?php echo $limit === -1 ? '∞' : $limit; ?> used
                            <?php if ($limit !== -1): ?> · <span class="text-gray-700"><?php echo $remaining; ?> left</span><?php endif; ?>
                        </span>
                    </div>
                    <div class="credit-bar">
                        <div class="credit-bar-fill" style="width:<?php echo $limit === -1 ? 5 : $pct; ?>%; background:<?php echo $bar_color; ?>;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-gray-400 mt-5 pt-4 border-t border-gray-100">
                <i class="fa-solid fa-rotate mr-1"></i> Limits reset on the 1st of each month. Contact your account admin to adjust limits.
            </p>
        <?php endif; ?>
    </div>

    <!-- OWN API KEYS -->
    <div class="core-card">
        <div class="core-section-title">🔑 Your Own API Keys (Optional)</div>
        <p class="text-sm text-gray-600 mb-6 leading-relaxed">
            Optionally connect your own AI provider keys. When set, your calls will use your keys instead of the platform's, and <strong>will not consume platform credits</strong>. Leave blank to continue using platform credits.
        </p>

        <div id="own_keys_msg" class="hidden mb-4 p-3 rounded-xl text-sm font-semibold"></div>

        <div class="space-y-5">

            <!-- OpenAI -->
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">
                    <span class="key-status-dot" style="background:<?php echo $own_openai ? '#10b981' : '#d1d5db'; ?>"></span>
                    OpenAI (GPT-4o mini)
                    <?php if ($own_openai): ?><span class="text-green-600 font-bold ml-1">● Connected</span><?php endif; ?>
                </label>
                <input type="password" id="own_openai_key" class="key-field"
                       placeholder="sk-proj-..."
                       value="<?php echo esc_attr($own_openai ? str_repeat('•', 20) . substr($own_openai, -4) : ''); ?>"
                       autocomplete="new-password">
                <p class="text-[11px] text-gray-400 mt-1">Get your key at <strong>platform.openai.com/api-keys</strong></p>
            </div>

            <!-- Gemini -->
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">
                    <span class="key-status-dot" style="background:<?php echo $own_gemini ? '#10b981' : '#d1d5db'; ?>"></span>
                    Google Gemini 2.0 Flash
                    <?php if ($own_gemini): ?><span class="text-green-600 font-bold ml-1">● Connected</span><?php endif; ?>
                </label>
                <input type="password" id="own_gemini_key" class="key-field"
                       placeholder="AIza..."
                       value="<?php echo esc_attr($own_gemini ? str_repeat('•', 20) . substr($own_gemini, -4) : ''); ?>"
                       autocomplete="new-password">
                <p class="text-[11px] text-gray-400 mt-1">Get your key at <strong>aistudio.google.com/app/apikey</strong></p>
            </div>

            <!-- Anthropic -->
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">
                    <span class="key-status-dot" style="background:<?php echo $own_anthropic ? '#10b981' : '#d1d5db'; ?>"></span>
                    Anthropic (Claude Haiku)
                    <?php if ($own_anthropic): ?><span class="text-green-600 font-bold ml-1">● Connected</span><?php endif; ?>
                </label>
                <input type="password" id="own_anthropic_key" class="key-field"
                       placeholder="sk-ant-api03-..."
                       value="<?php echo esc_attr($own_anthropic ? str_repeat('•', 20) . substr($own_anthropic, -4) : ''); ?>"
                       autocomplete="new-password">
                <p class="text-[11px] text-gray-400 mt-1">Get your key at <strong>console.anthropic.com/api-keys</strong></p>
            </div>
        </div>

        <div class="flex items-center gap-4 mt-8 pt-4 border-t border-gray-100">
            <button onclick="saveOwnKeys()" id="btn_save_keys" class="vw-primary-btn">
                <i class="fa-solid fa-floppy-disk"></i> Save API Keys
            </button>
            <p class="text-xs text-gray-400">Keys are encrypted and stored securely in your brand configuration.</p>
        </div>
    </div>

    <!-- HOW IT WORKS -->
    <div class="bg-blue-50 border border-blue-100 rounded-xl p-5 text-sm text-blue-800">
        <p class="font-bold text-blue-900 mb-2"><i class="fa-solid fa-circle-info mr-2"></i>How AI Credit Priority Works</p>
        <ol class="list-decimal list-inside space-y-1 text-blue-700">
            <li>If you have your own API key set for a provider — that key is used, <strong>no credits consumed</strong>.</li>
            <li>If no own key is set — the platform key is used and <strong>1 credit is deducted</strong> from your monthly allowance.</li>
            <li>When your platform credits run out — AI features show a "Limit Reached" message until the 1st of next month.</li>
            <li>Platform credits are reset automatically on the 1st of every month.</li>
        </ol>
    </div>

</div>

<script>
    function saveOwnKeys() {
        const btn = document.getElementById('btn_save_keys');
        const msg = document.getElementById('own_keys_msg');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Saving...';

        // Only send actual typed values (not the masked placeholder dots)
        const openai    = document.getElementById('own_openai_key').value;
        const gemini    = document.getElementById('own_gemini_key').value;
        const anthropic = document.getElementById('own_anthropic_key').value;

        const fd = new FormData();
        fd.append('action',          'mt_save_own_api_keys');
        fd.append('security',        mt_nonce);
        fd.append('own_openai_key',    openai.includes('•') ? '' : openai);
        fd.append('own_gemini_key',    gemini.includes('•') ? '' : gemini);
        fd.append('own_anthropic_key', anthropic.includes('•') ? '' : anthropic);

        fetch(mt_ajax_url, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save API Keys';
                msg.classList.remove('hidden');
                if (res.success) {
                    msg.className = 'mb-4 p-3 rounded-xl text-sm font-semibold bg-green-50 text-green-700 border border-green-200';
                    msg.innerHTML = '<i class="fa-solid fa-check-circle mr-2"></i> Keys saved successfully! Your own keys will be used for AI calls.';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    msg.className = 'mb-4 p-3 rounded-xl text-sm font-semibold bg-red-50 text-red-700 border border-red-200';
                    msg.innerHTML = '<i class="fa-solid fa-triangle-exclamation mr-2"></i> ' + (res.data || 'Save failed.');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save API Keys';
                msg.classList.remove('hidden');
                msg.className = 'mb-4 p-3 rounded-xl text-sm font-semibold bg-red-50 text-red-700 border border-red-200';
                msg.innerHTML = 'Network error. Please try again.';
            });
    }
</script>
