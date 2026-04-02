<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$config = json_decode($brand->brand_config, true) ?: [];

// Fetch URLs directly
$logo_main = isset($config['logos']['main']) ? $config['logos']['main'] : '';
$logo_footer = isset($config['logos']['footer']) ? $config['logos']['footer'] : '';
$logo_favicon = isset($config['logos']['favicon']) ? $config['logos']['favicon'] : '';

$ext_colors = isset($config['extended_colors']) && count($config['extended_colors']) === 5 ? $config['extended_colors'] : ['#ffffff', '#f3f4f6', '#d1d5db', '#9ca3af', '#4b5563'];
$socials = isset($config['socials']) ? $config['socials'] : ['fb'=>'', 'ig'=>'', 'x'=>'', 'tt'=>''];
$socials_custom = isset($config['socials_custom']) ? $config['socials_custom'] : [];
$saved_font = isset($config['font']) ? $config['font'] : 'Inter';

$vault_media = isset($config['vault']) ? $config['vault'] : [];
?>

<header class="mb-8 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Brand Identity</h1>
        <p class="text-gray-500">Global assets. These automatically populate your WiFi portals and Email templates.</p>
    </div>
    <button class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-5 py-2.5 rounded-lg font-semibold shadow-md hover:shadow-lg transition-all flex items-center gap-2">
        <i class="fa-solid fa-wand-magic-sparkles"></i> Auto-Fill via AI
    </button>
</header>

<div class="max-w-5xl space-y-6 pb-20">
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <h2 class="text-lg font-bold text-gray-800 border-b pb-4 mb-6"><i class="fa-solid fa-building text-gray-400 mr-2"></i> Core Details</h2>
        <div class="grid grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Company Name <span class="text-red-500">*</span></label>
                <input type="text" id="brand_name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 outline-none font-bold text-gray-900" value="<?php echo esc_attr($brand->brand_name); ?>">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Website URL</label>
                <input type="url" id="brand_url" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 outline-none" value="<?php echo esc_attr($config['url'] ?? ''); ?>">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Brand Slogan / Tagline</label>
                <input type="text" id="brand_slogan" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 outline-none" value="<?php echo esc_attr($config['slogan'] ?? ''); ?>">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Company HQ Email</label>
                <input type="email" id="brand_email" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 outline-none" value="<?php echo esc_attr($config['email'] ?? ''); ?>">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <h2 class="text-lg font-bold text-gray-800 border-b pb-4 mb-6"><i class="fa-solid fa-image text-gray-400 mr-2"></i> Visuals & Typography</h2>
        
        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Global Font Family</label>
            <select id="brand_font" class="w-full px-4 py-2 border border-gray-300 rounded-lg font-bold focus:ring-2 focus:ring-blue-100 outline-none bg-white">
                <option value="Inter" <?php selected($saved_font, 'Inter'); ?>>Inter</option>
                <option value="Roboto" <?php selected($saved_font, 'Roboto'); ?>>Roboto</option>
                <option value="Open Sans" <?php selected($saved_font, 'Open Sans'); ?>>Open Sans</option>
                <option value="Montserrat" <?php selected($saved_font, 'Montserrat'); ?>>Montserrat</option>
                <option value="Lato" <?php selected($saved_font, 'Lato'); ?>>Lato</option>
                <option value="Poppins" <?php selected($saved_font, 'Poppins'); ?>>Poppins</option>
                <option value="Oswald" <?php selected($saved_font, 'Oswald'); ?>>Oswald</option>
                <option value="Raleway" <?php selected($saved_font, 'Raleway'); ?>>Raleway</option>
                <option value="Playfair Display" <?php selected($saved_font, 'Playfair Display'); ?>>Playfair Display</option>
            </select>
        </div>

        <div class="grid grid-cols-3 gap-6">
            <div>
                <div class="flex justify-between items-end mb-2">
                    <label class="block text-sm font-semibold text-gray-700">Main Logo</label>
                    <button type="button" id="btn_rem_main" onclick="removeLogo('main')" class="text-xs font-bold text-red-500 hover:text-red-700 <?php echo empty($logo_main) ? 'hidden' : ''; ?>"><i class="fa-solid fa-trash"></i> Remove</button>
                </div>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:bg-gray-50 transition cursor-pointer relative group h-32 flex flex-col items-center justify-center bg-gray-50 overflow-hidden">
                    <img id="preview_logo_main" src="<?php echo esc_url($logo_main); ?>" class="max-h-20 w-auto object-contain z-10 <?php echo empty($logo_main) ? 'hidden' : ''; ?>">
                    <div id="icon_logo_main" class="absolute z-0 flex flex-col items-center <?php echo !empty($logo_main) ? 'hidden' : ''; ?>">
                        <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-400 mb-1 group-hover:text-blue-500 transition"></i>
                        <p class="text-[10px] font-bold text-gray-500 uppercase">Upload Main</p>
                    </div>
                    <div id="spin_main" class="absolute z-30 hidden bg-white/80 inset-0 flex items-center justify-center"><i class="fa-solid fa-spinner fa-spin text-blue-500 text-2xl"></i></div>
                    <input type="file" id="logo_main" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20">
                </div>
            </div>
            
            <div>
                <div class="flex justify-between items-end mb-2">
                    <label class="block text-sm font-semibold text-gray-700">Footer / Dark Logo</label>
                    <button type="button" id="btn_rem_footer" onclick="removeLogo('footer')" class="text-xs font-bold text-red-500 hover:text-red-700 <?php echo empty($logo_footer) ? 'hidden' : ''; ?>"><i class="fa-solid fa-trash"></i> Remove</button>
                </div>
                <div class="border-2 border-dashed border-gray-600 rounded-lg p-6 text-center hover:bg-gray-800 transition cursor-pointer relative bg-gray-900 group h-32 flex flex-col items-center justify-center overflow-hidden">
                    <img id="preview_logo_footer" src="<?php echo esc_url($logo_footer); ?>" class="max-h-20 w-auto object-contain z-10 <?php echo empty($logo_footer) ? 'hidden' : ''; ?>">
                    <div id="icon_logo_footer" class="absolute z-0 flex flex-col items-center <?php echo !empty($logo_footer) ? 'hidden' : ''; ?>">
                        <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-500 mb-1 group-hover:text-gray-300 transition"></i>
                        <p class="text-[10px] font-bold text-gray-400 uppercase">Upload Footer</p>
                    </div>
                    <div id="spin_footer" class="absolute z-30 hidden bg-gray-900/80 inset-0 flex items-center justify-center"><i class="fa-solid fa-spinner fa-spin text-blue-500 text-2xl"></i></div>
                    <input type="file" id="logo_footer" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20">
                </div>
            </div>
            
            <div>
                <div class="flex justify-between items-end mb-2">
                    <label class="block text-sm font-semibold text-gray-700">Favicon</label>
                    <button type="button" id="btn_rem_favicon" onclick="removeLogo('favicon')" class="text-xs font-bold text-red-500 hover:text-red-700 <?php echo empty($logo_favicon) ? 'hidden' : ''; ?>"><i class="fa-solid fa-trash"></i> Remove</button>
                </div>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:bg-gray-50 transition cursor-pointer relative group h-32 flex flex-col items-center justify-center bg-gray-50 overflow-hidden">
                    <img id="preview_favicon" src="<?php echo esc_url($logo_favicon); ?>" class="max-h-12 w-12 object-contain z-10 <?php echo empty($logo_favicon) ? 'hidden' : ''; ?>">
                    <div id="icon_favicon" class="absolute z-0 flex flex-col items-center <?php echo !empty($logo_favicon) ? 'hidden' : ''; ?>">
                        <div class="w-10 h-10 bg-gray-200 rounded mx-auto mb-1 flex items-center justify-center text-gray-400 group-hover:bg-gray-300 transition group-hover:text-blue-500"><i class="fa-solid fa-cube"></i></div>
                        <p class="text-[10px] font-bold text-gray-500 uppercase">Upload Icon</p>
                    </div>
                    <div id="spin_favicon" class="absolute z-30 hidden bg-white/80 inset-0 flex items-center justify-center"><i class="fa-solid fa-spinner fa-spin text-blue-500 text-2xl"></i></div>
                    <input type="file" id="logo_favicon" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20">
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <h2 class="text-lg font-bold text-gray-800 border-b pb-4 mb-6"><i class="fa-solid fa-palette text-gray-400 mr-2"></i> Brand Colors</h2>
        <div class="grid grid-cols-2 gap-8 mb-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Primary Color (Buttons)</label>
                <div class="flex gap-3">
                    <input type="color" id="color_primary" class="h-10 w-12 rounded cursor-pointer border-gray-200" value="<?php echo esc_attr($brand->primary_color); ?>">
                    <input type="text" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg font-mono text-sm font-bold uppercase" value="<?php echo esc_attr($brand->primary_color); ?>">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Secondary Color</label>
                <div class="flex gap-3">
                    <input type="color" id="color_secondary" class="h-10 w-12 rounded cursor-pointer border-gray-200" value="<?php echo esc_attr($config['secondary_color'] ?? '#111827'); ?>">
                    <input type="text" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg font-mono text-sm font-bold uppercase" value="<?php echo esc_attr($config['secondary_color'] ?? '#111827'); ?>">
                </div>
            </div>
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Extended Palette (For Builders)</label>
            <div class="flex gap-4">
                <input type="color" id="color_ext_1" class="h-10 w-10 rounded-full cursor-pointer border-0 shadow-sm" value="<?php echo esc_attr($ext_colors[0]); ?>">
                <input type="color" id="color_ext_2" class="h-10 w-10 rounded-full cursor-pointer border-0 shadow-sm" value="<?php echo esc_attr($ext_colors[1]); ?>">
                <input type="color" id="color_ext_3" class="h-10 w-10 rounded-full cursor-pointer border-0 shadow-sm" value="<?php echo esc_attr($ext_colors[2]); ?>">
                <input type="color" id="color_ext_4" class="h-10 w-10 rounded-full cursor-pointer border-0 shadow-sm" value="<?php echo esc_attr($ext_colors[3]); ?>">
                <input type="color" id="color_ext_5" class="h-10 w-10 rounded-full cursor-pointer border-0 shadow-sm" value="<?php echo esc_attr($ext_colors[4]); ?>">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <h2 class="text-lg font-bold text-gray-800 border-b pb-4 mb-6"><i class="fa-solid fa-envelope-open-text text-gray-400 mr-2"></i> Support & Compliance</h2>
        <div class="grid grid-cols-2 gap-6 mb-6">
            <div><label class="block text-sm font-semibold text-gray-700 mb-2">Help/Support Email</label><input type="email" id="support_email" class="w-full px-4 py-2 border border-gray-300 rounded-lg" value="<?php echo esc_attr($config['support_email'] ?? ''); ?>"></div>
            <div><label class="block text-sm font-semibold text-gray-700 mb-2">Help Portal URL (Optional)</label><input type="url" id="support_url" class="w-full px-4 py-2 border border-gray-300 rounded-lg" value="<?php echo esc_attr($config['support_url'] ?? ''); ?>"></div>
        </div>
        <div class="grid grid-cols-2 gap-6 mb-6">
            <div><label class="block text-sm font-semibold text-gray-700 mb-2">Default Sender Name</label><input type="text" id="sender_name" class="w-full px-4 py-2 border border-gray-300 rounded-lg" value="<?php echo esc_attr($config['sender_name'] ?? ''); ?>"></div>
            <div><label class="block text-sm font-semibold text-gray-700 mb-2">HQ Phone Number</label><input type="text" id="hq_phone" class="w-full px-4 py-2 border border-gray-300 rounded-lg" value="<?php echo esc_attr($config['hq_phone'] ?? ''); ?>"></div>
        </div>
        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">HQ Physical Address</label>
            <textarea id="hq_address" class="w-full px-4 py-2 border border-gray-300 rounded-lg" rows="2"><?php echo esc_textarea($config['hq_address'] ?? ''); ?></textarea>
        </div>
        <div class="grid grid-cols-2 gap-6 bg-gray-50 p-4 rounded-lg border border-gray-200">
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Privacy Policy URL</label><input type="url" id="url_privacy" class="w-full px-3 py-2 border border-gray-300 rounded text-sm" value="<?php echo esc_attr($config['url_privacy'] ?? ''); ?>"></div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Terms of Service URL</label><input type="url" id="url_tos" class="w-full px-3 py-2 border border-gray-300 rounded text-sm" value="<?php echo esc_attr($config['url_tos'] ?? ''); ?>"></div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <h2 class="text-lg font-bold text-gray-800 border-b pb-4 mb-6"><i class="fa-solid fa-share-nodes text-gray-400 mr-2"></i> Social Profiles</h2>
        <div class="grid grid-cols-2 gap-4">
            <div class="flex items-center"><span class="bg-gray-100 border border-gray-300 border-r-0 px-4 py-2 rounded-l-lg text-gray-500 w-12 text-center"><i class="fa-brands fa-facebook-f"></i></span><input type="url" id="social_fb" class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg" placeholder="Facebook URL" value="<?php echo esc_attr($socials['fb']); ?>"></div>
            <div class="flex items-center"><span class="bg-gray-100 border border-gray-300 border-r-0 px-4 py-2 rounded-l-lg text-gray-500 w-12 text-center"><i class="fa-brands fa-instagram"></i></span><input type="url" id="social_ig" class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg" placeholder="Instagram URL" value="<?php echo esc_attr($socials['ig']); ?>"></div>
            <div class="flex items-center"><span class="bg-gray-100 border border-gray-300 border-r-0 px-4 py-2 rounded-l-lg text-gray-500 w-12 text-center"><i class="fa-brands fa-twitter"></i></span><input type="url" id="social_x" class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg" placeholder="Twitter / X URL" value="<?php echo esc_attr($socials['x']); ?>"></div>
            <div class="flex items-center"><span class="bg-gray-100 border border-gray-300 border-r-0 px-4 py-2 rounded-l-lg text-gray-500 w-12 text-center"><i class="fa-brands fa-tiktok"></i></span><input type="url" id="social_tt" class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg" placeholder="TikTok URL" value="<?php echo esc_attr($socials['tt']); ?>"></div>
        </div>

        <div class="mt-6 border-t pt-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Additional Links</label>
            <div id="dynamic_socials">
                <?php foreach($socials_custom as $custom): ?>
                <div class="flex items-center mb-3 gap-0 custom-social-row">
                    <select class="bg-gray-100 border border-gray-300 border-r-0 px-3 py-2 rounded-l-lg text-gray-600 custom-social-icon font-bold outline-none">
                        <option value="fa-link" <?php selected($custom['icon'], 'fa-link'); ?>>Link</option>
                        <option value="fa-youtube" <?php selected($custom['icon'], 'fa-youtube'); ?>>YouTube</option>
                        <option value="fa-linkedin" <?php selected($custom['icon'], 'fa-linkedin'); ?>>LinkedIn</option>
                        <option value="fa-pinterest" <?php selected($custom['icon'], 'fa-pinterest'); ?>>Pinterest</option>
                        <option value="fa-snapchat" <?php selected($custom['icon'], 'fa-snapchat'); ?>>Snapchat</option>
                    </select>
                    <input type="url" class="flex-1 px-4 py-2 border border-gray-300 custom-social-url outline-none" value="<?php echo esc_attr($custom['url']); ?>" placeholder="https://...">
                    <button type="button" onclick="this.parentElement.remove()" class="bg-red-50 text-red-500 hover:text-white hover:bg-red-500 px-4 py-2 border border-l-0 border-red-200 rounded-r-lg transition"><i class="fa-solid fa-trash"></i></button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" onclick="addSocialRow()" class="mt-2 text-sm font-bold text-blue-600 hover:text-blue-800"><i class="fa-solid fa-plus mr-1"></i> Add Custom Link</button>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-0 overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50">
            <h2 class="text-lg font-bold text-gray-800"><i class="fa-solid fa-folder-open text-gray-400 mr-2"></i> The Media Vault</h2>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-1 flex gap-1">
                <input type="hidden" id="current_vault_filter" value="wifi">
                <button id="btn_filter_wifi" class="px-4 py-1.5 bg-gray-900 text-white rounded font-bold text-sm transition" onclick="filterVault('wifi')">WiFi Assets</button>
                <button id="btn_filter_email" class="px-4 py-1.5 text-gray-500 hover:bg-gray-100 rounded font-bold text-sm transition" onclick="filterVault('email')">Email Assets</button>
            </div>
        </div>
        <div class="p-6">
            <div id="vault_dropzone" class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center bg-gray-50 mb-6 cursor-pointer hover:bg-gray-100 hover:border-blue-400 transition">
                <div id="vault_upload_status">
                    <i class="fa-solid fa-cloud-arrow-up text-4xl text-gray-400 mb-3 pointer-events-none"></i>
                    <p class="font-bold text-gray-700 pointer-events-none">Click or Drag & Drop new media here</p>
                    <p class="text-xs text-gray-500 mt-1 pointer-events-none">Uploads sync directly to your secure SaaS storage.</p>
                </div>
                <input type="file" id="vault_upload_input" accept="image/*" class="hidden">
            </div>
            
            <div class="grid grid-cols-4 gap-4" id="vault_grid">
                <?php if(empty($vault_media)): ?>
                    <p id="vault_empty_msg" class="col-span-4 text-center text-sm text-gray-400 italic py-4">Your vault is currently empty.</p>
                <?php else: ?>
                    <?php foreach($vault_media as $media): ?>
                    <div id="<?php echo esc_attr($media['id']); ?>" class="vault-item <?php echo esc_attr($media['type']); ?> relative group rounded-lg overflow-hidden border border-gray-200 shadow-sm <?php echo $media['type'] !== 'wifi' ? 'hidden' : ''; ?>">
                        <img src="<?php echo esc_url($media['url']); ?>" class="w-full h-32 object-cover">
                        <div class="absolute inset-0 bg-gray-900/70 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 backdrop-blur-sm">
                            <button onclick="navigator.clipboard.writeText('<?php echo esc_url($media['url']); ?>'); alert('URL Copied!')" class="bg-white text-gray-900 w-8 h-8 rounded-full hover:bg-blue-500 hover:text-white transition" title="Copy URL"><i class="fa-solid fa-link"></i></button>
                            <button onclick="deleteVaultMedia('<?php echo esc_attr($media['id']); ?>', this)" class="bg-white text-red-600 w-8 h-8 rounded-full hover:bg-red-600 hover:text-white transition" title="Permanently Delete"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<div class="fixed bottom-0 left-[260px] right-0 bg-white border-t border-gray-200 p-4 px-8 flex justify-between items-center z-40 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
    <button id="btn_save_brand" class="bg-gray-900 text-white px-8 py-2.5 rounded-lg font-bold shadow-md hover:bg-gray-800 transition flex items-center gap-2 ml-auto">
        <i class="fa-solid fa-floppy-disk"></i> Save Brand Identity
    </button>
</div>

<script>
    // --- GLOBAL AJAX UPLOADER ---
    async function uploadAndGetUrl(file, type) {
        const formData = new FormData();
        formData.append('action', 'mt_upload_vault_media');
        formData.append('security', mt_nonce);
        formData.append('media_type', type);
        formData.append('file', file);
        const res = await fetch(mt_ajax_url, { method: 'POST', body: formData });
        const data = await res.json();
        if(data.success) return data.data;
        throw new Error(data.data);
    }

    function addMediaToVaultGrid(mediaData) {
        const emptyMsg = document.getElementById('vault_empty_msg');
        if(emptyMsg) emptyMsg.remove();
        
        const grid = document.getElementById('vault_grid');
        const newItem = document.createElement('div');
        newItem.id = mediaData.id;
        newItem.className = `vault-item ${mediaData.type} relative group rounded-lg overflow-hidden border border-gray-200 shadow-sm`;
        
        // Hide it initially if it doesn't match the current filter
        if(document.getElementById('current_vault_filter').value !== mediaData.type) {
            newItem.classList.add('hidden');
        }

        newItem.innerHTML = `
            <img src="${mediaData.url}" class="w-full h-32 object-cover">
            <div class="absolute inset-0 bg-gray-900/70 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 backdrop-blur-sm">
                <button onclick="navigator.clipboard.writeText('${mediaData.url}'); alert('URL Copied!')" class="bg-white text-gray-900 w-8 h-8 rounded-full hover:bg-blue-500 hover:text-white transition"><i class="fa-solid fa-link"></i></button>
                <button onclick="deleteVaultMedia('${mediaData.id}', this)" class="bg-white text-red-600 w-8 h-8 rounded-full hover:bg-red-600 hover:text-white transition"><i class="fa-solid fa-trash"></i></button>
            </div>`;
        grid.appendChild(newItem);
    }

    // --- LOGO UPLOADERS (SYNCED TO VAULT) ---
    let currentMainLogo = '<?php echo esc_js($logo_main); ?>';
    let currentFooterLogo = '<?php echo esc_js($logo_footer); ?>';
    let currentFavicon = '<?php echo esc_js($logo_favicon); ?>';

    function setupVaultUploader(inputId, previewId, iconId, spinId, btnRemId, varName) {
        document.getElementById(inputId).addEventListener('change', async function(e) {
            if(e.target.files && e.target.files[0]) {
                document.getElementById(spinId).classList.remove('hidden');
                try {
                    const mediaData = await uploadAndGetUrl(e.target.files[0], 'wifi'); // Logos go to wifi vault
                    
                    if(varName === 'main') currentMainLogo = mediaData.url;
                    if(varName === 'footer') currentFooterLogo = mediaData.url;
                    if(varName === 'favicon') currentFavicon = mediaData.url;

                    const img = document.getElementById(previewId);
                    img.src = mediaData.url;
                    img.classList.remove('hidden');
                    document.getElementById(iconId).classList.add('hidden');
                    document.getElementById(btnRemId).classList.remove('hidden');
                    
                    addMediaToVaultGrid(mediaData); // Add to vault visually
                } catch(err) {
                    alert("Upload Failed: " + err);
                }
                document.getElementById(spinId).classList.add('hidden');
            }
        });
    }

    setupVaultUploader('logo_main', 'preview_logo_main', 'icon_logo_main', 'spin_main', 'btn_rem_main', 'main');
    setupVaultUploader('logo_footer', 'preview_logo_footer', 'icon_logo_footer', 'spin_footer', 'btn_rem_footer', 'footer');
    setupVaultUploader('logo_favicon', 'preview_favicon', 'icon_favicon', 'spin_favicon', 'btn_rem_favicon', 'favicon');

    function removeLogo(type) {
        if(type === 'main') {
            currentMainLogo = '';
            document.getElementById('preview_logo_main').src = '';
            document.getElementById('preview_logo_main').classList.add('hidden');
            document.getElementById('icon_logo_main').classList.remove('hidden');
            document.getElementById('btn_rem_main').classList.add('hidden');
            document.getElementById('logo_main').value = '';
        } else if(type === 'footer') {
            currentFooterLogo = '';
            document.getElementById('preview_logo_footer').src = '';
            document.getElementById('preview_logo_footer').classList.add('hidden');
            document.getElementById('icon_logo_footer').classList.remove('hidden');
            document.getElementById('btn_rem_footer').classList.add('hidden');
            document.getElementById('logo_footer').value = '';
        } else if(type === 'favicon') {
            currentFavicon = '';
            document.getElementById('preview_favicon').src = '';
            document.getElementById('preview_favicon').classList.add('hidden');
            document.getElementById('icon_favicon').classList.remove('hidden');
            document.getElementById('btn_rem_favicon').classList.add('hidden');
            document.getElementById('logo_favicon').value = '';
        }
    }

    document.querySelectorAll('input[type="color"]').forEach(picker => {
        picker.addEventListener('input', function(e) {
            let nextText = e.target.nextElementSibling;
            if (nextText && nextText.tagName === 'INPUT') nextText.value = e.target.value.toUpperCase();
        });
    });

    // --- VAULT MANAGER ---
    function filterVault(type) {
        document.getElementById('current_vault_filter').value = type;
        const btnW = document.getElementById('btn_filter_wifi');
        const btnE = document.getElementById('btn_filter_email');
        if (type === 'wifi') {
            btnW.className = 'px-4 py-1.5 bg-gray-900 text-white rounded font-bold text-sm transition';
            btnE.className = 'px-4 py-1.5 text-gray-500 hover:bg-gray-100 rounded font-bold text-sm transition';
        } else {
            btnE.className = 'px-4 py-1.5 bg-gray-900 text-white rounded font-bold text-sm transition';
            btnW.className = 'px-4 py-1.5 text-gray-500 hover:bg-gray-100 rounded font-bold text-sm transition';
        }
        document.querySelectorAll('.vault-item').forEach(item => {
            if (item.classList.contains(type)) item.classList.remove('hidden');
            else item.classList.add('hidden');
        });
    }

    const dropzone = document.getElementById('vault_dropzone');
    const fileInput = document.getElementById('vault_upload_input');
    
    dropzone.addEventListener('click', () => fileInput.click());
    dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('border-blue-500', 'bg-blue-50'); });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('border-blue-500', 'bg-blue-50'));
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('border-blue-500', 'bg-blue-50');
        if(e.dataTransfer.files.length) uploadToVault(e.dataTransfer.files[0]);
    });
    fileInput.addEventListener('change', (e) => { if(e.target.files.length) uploadToVault(e.target.files[0]); });

    async function uploadToVault(file) {
        const statusBox = document.getElementById('vault_upload_status');
        const originalStatus = statusBox.innerHTML;
        statusBox.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-4xl text-blue-500 mb-3"></i><p class="font-bold text-blue-600">Uploading securely to server...</p>';

        try {
            const mediaData = await uploadAndGetUrl(file, document.getElementById('current_vault_filter').value);
            addMediaToVaultGrid(mediaData);
            statusBox.innerHTML = originalStatus;
            fileInput.value = ''; // Reset
        } catch(err) {
            alert("Upload Failed: " + err);
            statusBox.innerHTML = originalStatus;
        }
    }

    async function deleteVaultMedia(mediaId, btnEl) {
        if(!confirm("Permanently delete this file from your server?")) return;
        
        btnEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        
        const formData = new FormData();
        formData.append('action', 'mt_delete_vault_media');
        formData.append('security', mt_nonce);
        formData.append('media_id', mediaId);

        const res = await fetch(mt_ajax_url, { method: 'POST', body: formData });
        const data = await res.json();
        
        if(data.success) {
            document.getElementById(mediaId).remove();
        } else {
            alert("Delete Failed: " + data.data);
            btnEl.innerHTML = '<i class="fa-solid fa-trash"></i>';
        }
    }

    function addSocialRow() {
        const container = document.getElementById('dynamic_socials');
        const div = document.createElement('div');
        div.className = "flex items-center mb-3 gap-0 custom-social-row";
        div.innerHTML = `
            <select class="bg-gray-100 border border-gray-300 border-r-0 px-3 py-2 rounded-l-lg text-gray-600 custom-social-icon font-bold outline-none">
                <option value="fa-link">Link</option>
                <option value="fa-youtube">YouTube</option>
                <option value="fa-linkedin">LinkedIn</option>
                <option value="fa-pinterest">Pinterest</option>
                <option value="fa-snapchat">Snapchat</option>
            </select>
            <input type="url" class="flex-1 px-4 py-2 border border-gray-300 custom-social-url outline-none" placeholder="https://...">
            <button type="button" onclick="this.parentElement.remove()" class="bg-red-50 text-red-500 hover:text-white hover:bg-red-500 px-4 py-2 border border-l-0 border-red-200 rounded-r-lg transition"><i class="fa-solid fa-trash"></i></button>
        `;
        container.appendChild(div);
    }

    document.getElementById('btn_save_brand').addEventListener('click', async function() {
        const btn = this;
        const originalText = btn.innerHTML;
        
        try {
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Saving...';

            let customSocialsArray = [];
            document.querySelectorAll('.custom-social-row').forEach(row => {
                customSocialsArray.push({
                    icon: row.querySelector('.custom-social-icon').value,
                    url: row.querySelector('.custom-social-url').value
                });
            });

            const brandData = {
                url: document.getElementById('brand_url').value,
                slogan: document.getElementById('brand_slogan').value,
                email: document.getElementById('brand_email').value,
                font: document.getElementById('brand_font').value,
                secondary_color: document.getElementById('color_secondary').value,
                logos: {
                    main: currentMainLogo,
                    footer: currentFooterLogo,
                    favicon: currentFavicon
                },
                extended_colors: [
                    document.getElementById('color_ext_1').value, document.getElementById('color_ext_2').value,
                    document.getElementById('color_ext_3').value, document.getElementById('color_ext_4').value,
                    document.getElementById('color_ext_5').value
                ],
                support_email: document.getElementById('support_email').value,
                support_url: document.getElementById('support_url').value,
                sender_name: document.getElementById('sender_name').value,
                hq_phone: document.getElementById('hq_phone').value,
                hq_address: document.getElementById('hq_address').value,
                url_privacy: document.getElementById('url_privacy').value,
                url_tos: document.getElementById('url_tos').value,
                socials: {
                    fb: document.getElementById('social_fb').value, ig: document.getElementById('social_ig').value,
                    x: document.getElementById('social_x').value, tt: document.getElementById('social_tt').value
                },
                socials_custom: customSocialsArray
            };

            const formData = new FormData();
            formData.append('action', 'mt_save_brand_config');
            formData.append('security', mt_nonce);
            formData.append('brand_name', document.getElementById('brand_name').value);
            formData.append('primary_color', document.getElementById('color_primary').value);
            formData.append('config', JSON.stringify(brandData));

            const response = await fetch(mt_ajax_url, { method: 'POST', body: formData });
            const text = await response.text();
            
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    btn.innerHTML = '<i class="fa-solid fa-check mr-2"></i> Saved Perfectly!';
                    btn.classList.add('bg-green-600');
                    document.querySelector('.sidebar h2 + p').innerText = document.getElementById('brand_name').value;
                    setTimeout(() => { btn.innerHTML = originalText; btn.classList.remove('bg-green-600'); }, 2500);
                } else {
                    alert("SERVER REJECTED SAVE: " + JSON.stringify(data.data));
                    btn.innerHTML = originalText;
                }
            } catch(e) {
                alert("FATAL SERVER CRASH. Check Console (F12).");
                btn.innerHTML = originalText;
            }
        } catch (err) {
            alert("NETWORK ERROR: " + err.message);
            btn.innerHTML = originalText;
        }
    });
</script>