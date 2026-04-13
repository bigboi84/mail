<?php
/**
 * Plugin Name: MealCrafter Dynamic GitHub Sync
 * Description: Select any installed plugin and push its code directly to your GitHub repository.
 * Version: 2.0.0
 * Author: Sling
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MealCrafter_Dynamic_Sync {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_ajax_mc_dynamic_sync', [ $this, 'handle_sync' ] );
    }

    public function add_menu() {
        add_menu_page( 'GitHub Sync', 'GitHub Sync', 'manage_options', 'mc-dynamic-sync', [ $this, 'render_page' ], 'dashicons-cloud-upload', 30 );
    }

    public function register_settings() {
        register_setting( 'mc_gh_dynamic_settings', 'mc_gh_token' );
        register_setting( 'mc_gh_dynamic_settings', 'mc_gh_repo' );
    }

    public function render_page() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins = get_plugins();
        $token = get_option('mc_gh_token', '');
        $repo  = get_option('mc_gh_repo', 'bigboi84/onlineorder'); // Defaulting to your repo
        ?>
        <div class="wrap">
            <h1 style="margin-bottom: 20px;">Dynamic GitHub Sync</h1>
            
            <div style="display: flex; gap: 20px; align-items: flex-start;">
                <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <h3>Repository Settings</h3>
                    <form method="post" action="options.php">
                        <?php settings_fields( 'mc_gh_dynamic_settings' ); ?>
                        <p>
                            <label style="font-weight:bold;">GitHub Token:</label><br>
                            <input type="password" name="mc_gh_token" value="<?php echo esc_attr($token); ?>" style="width:100%; margin-top:5px;">
                        </p>
                        <p>
                            <label style="font-weight:bold;">Target Repository (username/repo):</label><br>
                            <input type="text" name="mc_gh_repo" value="<?php echo esc_attr($repo); ?>" style="width:100%; margin-top:5px;">
                        </p>
                        <?php submit_button('Save Settings'); ?>
                    </form>
                </div>

                <div style="flex: 2; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <h3>Select Plugins to Sync</h3>
                    <p><em>Note: Only select your custom plugins. Syncing massive plugins like WooCommerce will time out.</em></p>
                    
                    <div style="max-height: 400px; overflow-y: auto; border: 1px solid #eee; padding: 10px; margin-bottom: 15px;">
                        <table class="wp-list-table widefat striped">
                            <thead>
                                <tr>
                                    <th width="40"><input type="checkbox" id="mc-select-all"></th>
                                    <th>Plugin Name</th>
                                    <th>Folder Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $all_plugins as $plugin_file => $plugin_data ) : 
                                    $folder = dirname($plugin_file);
                                    if ($folder === '.') continue; // Skip single-file plugins outside of a folder
                                ?>
                                    <tr>
                                        <td><input type="checkbox" class="mc-plugin-cb" value="<?php echo esc_attr($folder); ?>"></td>
                                        <td><strong><?php echo esc_html($plugin_data['Name']); ?></strong></td>
                                        <td><code><?php echo esc_html($folder); ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button id="mc-start-sync" class="button button-primary button-hero">Push Selected to GitHub</button>
                    
                    <div id="sync-console" style="margin-top:20px; padding:15px; background:#1e1e1e; color:#00ff00; font-family:monospace; height:200px; overflow-y:auto; display:none;">
                        > Ready...<br>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#mc-select-all').on('change', function() {
                $('.mc-plugin-cb').prop('checked', $(this).is(':checked'));
            });

            $('#mc-start-sync').click(function(e) {
                e.preventDefault();
                let selected = [];
                $('.mc-plugin-cb:checked').each(function() { selected.push($(this).val()); });

                if (selected.length === 0) { alert('Please select at least one plugin.'); return; }
                if (!confirm('Are you sure you want to push these ' + selected.length + ' plugins to GitHub?')) return;

                const btn = $(this);
                const consoleBox = $('#sync-console');
                btn.prop('disabled', true).text('Syncing in progress...');
                consoleBox.show().append('> Starting bulk sync...<br>');

                // Process sequentially to avoid overwhelming the server
                let current = 0;
                function processNext() {
                    if (current >= selected.length) {
                        btn.prop('disabled', false).text('Push Selected to GitHub');
                        consoleBox.append('> <strong>ALL SYNCING COMPLETE.</strong><br>');
                        consoleBox.scrollTop(consoleBox[0].scrollHeight);
                        return;
                    }

                    let folder = selected[current];
                    consoleBox.append('> Processing folder: ' + folder + '...<br>');
                    consoleBox.scrollTop(consoleBox[0].scrollHeight);

                    $.post(ajaxurl, {
                        action: 'mc_dynamic_sync',
                        folder: folder,
                        _ajax_nonce: '<?php echo wp_create_nonce("mc_gh_dynamic_nonce"); ?>'
                    }, function(res) {
                        if (res.success) {
                            consoleBox.append('<span style="color:#00ff00;">> SUCCESS: ' + res.data + '</span><br>');
                        } else {
                            consoleBox.append('<span style="color:#ff5555;">> ERROR ('+folder+'): ' + res.data + '</span><br>');
                        }
                        consoleBox.scrollTop(consoleBox[0].scrollHeight);
                        current++;
                        processNext();
                    }).fail(function() {
                        consoleBox.append('<span style="color:#ff5555;">> SERVER ERROR while processing '+folder+'. Moving to next.</span><br>');
                        current++;
                        processNext();
                    });
                }
                processNext();
            });
        });
        </script>
        <?php
    }

    public function handle_sync() {
        check_ajax_referer('mc_gh_dynamic_nonce');
        $token = get_option('mc_gh_token');
        $repo  = get_option('mc_gh_repo');
        
        if (empty($token) || empty($repo)) {
            wp_send_json_error('Token or Repository is missing in settings.');
        }

        $folder = sanitize_text_field($_POST['folder']);
        // Protect against directory traversal
        if (strpos($folder, '.') !== false || strpos($folder, '/') !== false) {
            wp_send_json_error('Invalid folder name.');
        }

        $local_dir = WP_PLUGIN_DIR . '/' . $folder;
        if (!is_dir($local_dir)) wp_send_json_error('Directory does not exist locally.');

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($local_dir));
        $count = 0;
        
        foreach ($files as $file) {
            if ($file->isDir()) continue;
            
            $local_path = $file->getRealPath();
            $rel = str_replace($local_dir . '/', '', $local_path);
            
            // Skip common unnecessary files
            if (strpos($rel, '.git/') === 0 || strpos($rel, 'node_modules/') === 0 || substr($rel, -4) === '.zip') {
                continue;
            }

            // Push to github. The path on GitHub will be /folder_name/relative_path
            $github_path = $folder . '/' . $rel;
            $result = $this->push_to_gh($local_path, $github_path, $token, $repo);
            
            if ($result) $count++;
        }
        
        wp_send_json_success("Created/Updated $count files in $folder.");
    }

    private function push_to_gh($local, $gh, $token, $repo) {
        $url = "https://api.github.com/repos/{$repo}/contents/{$gh}";
        
        // Check if file exists to get the SHA
        $args = [
            'headers' => [
                'Authorization' => "token $token", 
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress-MealCrafter'
            ]
        ];
        $res = wp_remote_get($url, $args);
        $sha = json_decode(wp_remote_retrieve_body($res), true)['sha'] ?? '';

        // PUT request to create or update
        $body = [
            'message' => 'Dynamic Sync: ' . date('Y-m-d H:i:s'),
            'content' => base64_encode(file_get_contents($local)),
            'branch'  => 'main'
        ];
        if (!empty($sha)) {
            $body['sha'] = $sha;
        }

        $push = wp_remote_request($url, [
            'method'  => 'PUT',
            'headers' => [
                'Authorization' => "token $token", 
                'Content-Type' => 'application/json',
                'User-Agent' => 'WordPress-MealCrafter'
            ],
            'body'    => json_encode($body),
            'timeout' => 15
        ]);
        
        $code = wp_remote_retrieve_response_code($push);
        return ($code === 200 || $code === 201);
    }
}
new MealCrafter_Dynamic_Sync();