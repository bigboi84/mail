<?php
/**
 * Toucan Pro Backend Admin Settings Page
 */
class MT_Admin_Settings {

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        // Enqueue media uploader and color picker scripts
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }

    public function add_admin_menu() {
        add_menu_page(
            'MailToucan Settings',
            'Toucan Pro',
            'manage_options',
            'mailtoucan-settings',
            array( $this, 'render_settings_page' ),
            'dashicons-email-alt2',
            100
        );
    }

    public function enqueue_admin_scripts( $hook_suffix ) {
        if ( 'toplevel_page_mailtoucan-settings' !== $hook_suffix ) {
            return;
        }
        // WordPress built-in color picker and media library uploader
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_media();
        
        // Custom script to initialize pickers and media library
        wp_add_inline_script( 'wp-color-picker', '
            jQuery(document).ready(function($){
                $(".mt-color-picker").wpColorPicker();
                
                $(".mt-upload-button").click(function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var id = button.attr("id").replace("_button", "");
                    var custom_uploader = wp.media({
                        title: "Choose Toucan Mascot",
                        button: { text: "Use this image" },
                        multiple: false
                    }).on("select", function() {
                        var attachment = custom_uploader.state().get("selection").first().toJSON();
                        $("#" + id).val(attachment.url);
                        $("#" + id + "_preview").attr("src", attachment.url).show();
                    }).open();
                });
            });
        ' );
    }

    public function register_settings() {
        register_setting( 'mailtoucan_settings_group', 'mt_ai_mascot_url' );
        register_setting( 'mailtoucan_settings_group', 'mt_openai_key' );
        register_setting( 'mailtoucan_settings_group', 'mt_toucan_api_key' );
        register_setting( 'mailtoucan_settings_group', 'mt_brand_palette' );
    }

    public function render_settings_page() {
        $palette = get_option( 'mt_brand_palette', [
            'dark'   => '#1A232E', // Default from image_0.png Charcoal
            'blue'   => '#283F8F', // Blue
            'cream'  => '#FCFAF2', // Cream
            'accent' => '#FCC753', // Mustard / Primary Accent
            'orange' => '#E67A05', // Orange
            'red'    => '#AE2E00'  // Red-Orange
        ] );
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-email-alt2"></span> MailToucan Pro Global Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'mailtoucan_settings_group' ); ?>
                <?php do_settings_sections( 'mailtoucan_settings_group' ); ?>

                <table class="form-table mt-admin-table">
                    
                    <tr valign="top">
                        <th scope="row">Tou-can AI Mascot (GIF or Lottie JSON URL)</th>
                        <td>
                            <input type="text" name="mt_ai_mascot_url" id="mt_ai_mascot_url" value="<?php echo esc_attr( get_option( 'mt_ai_mascot_url' ) ); ?>" class="regular-text" />
                            <input type="button" class="button mt-upload-button" id="mt_ai_mascot_url_button" value="Upload Mascot" /><br />
                            <img id="mt_ai_mascot_url_preview" src="<?php echo esc_url(get_option('mt_ai_mascot_url')); ?>" style="max-width:100px; max-height:100px; margin-top:10px; <?php echo get_option('mt_ai_mascot_url') ? '' : 'display:none;'; ?>" />
                            <p class="description">Upload your animated GIF or paste the URL of your Lottie JSON file. Standard GIFs will have poor transparency on dark backgrounds; Lottie JSON is highly recommended.</p>
                        </td>
                    </table>

                    <h2><i class="dashicons dashicons-key"></i> API Integrations</h2>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">OpenAI Key (for Tou-can Magic Assist)</th>
                            <td><input type="password" name="mt_openai_key" value="<?php echo esc_attr( get_option( 'mt_openai_key' ) ); ?>" class="regular-text" placeholder="sk-..." /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Toucan Core API Key</th>
                            <td><input type="password" name="mt_toucan_api_key" value="<?php echo esc_attr( get_option( 'mt_toucan_api_key' ) ); ?>" class="regular-text" /></td>
                        </tr>
                    </table>

                    <h2><i class="dashicons dashicons-color-picker"></i> Brand Identity & Colors (Toco Toucan Palette)</h2>
                    <table class="form-table mt-color-table">
                        <tr valign="top">
                            <th scope="row">Primary Accent Color (Mustard)</th>
                            <td><input type="text" name="mt_brand_palette[accent]" value="<?php echo esc_attr( $palette['accent'] ); ?>" class="mt-color-picker" /> <p class="description">Used globally for buttons, active links, and highlights.</p></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Dark Charcoal (Sidebar Background)</th>
                            <td><input type="text" name="mt_brand_palette[dark]" value="<?php echo esc_attr( $palette['dark'] ); ?>" class="mt-color-picker" /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Branding Blue</th>
                            <td><input type="text" name="mt_brand_palette[blue]" value="<?php echo esc_attr( $palette['blue'] ); ?>" class="mt-color-picker" /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Cream (Light Backgrounds)</th>
                            <td><input type="text" name="mt_brand_palette[cream]" value="<?php echo esc_attr( $palette['cream'] ); ?>" class="mt-color-picker" /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Orange</th>
                            <td><input type="text" name="mt_brand_palette[orange]" value="<?php echo esc_attr( $palette['orange'] ); ?>" class="mt-color-picker" /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Red-Orange</th>
                            <td><input type="text" name="mt_brand_palette[red]" value="<?php echo esc_attr( $palette['red'] ); ?>" class="mt-color-picker" /></td>
                        </tr>
                    </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <style>
            .mt-admin-table th { width: 300px; }
            .mt-color-table input { width: 100px; }
            .mt-color-table .description { display: block; margin-top: 5px; font-style: italic; }
        </style>
        <?php
    }
}