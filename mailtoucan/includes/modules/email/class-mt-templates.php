<?php
/**
 * MailToucan Pre-Built Email Templates
 * ─────────────────────────────────────────────────────────────────────────────
 * Manages the library of system starter templates.
 * Templates are stored as clean HTML using brand merge tags.
 * They are injected as real mt_email_templates records per-brand on demand.
 *
 * Tags available in templates:
 *   {{brand_name}}   {{brand_color}}  {{logo_url}}   {{address}}
 *   {{first_name}}   {{unsubscribe_url}}  {{website_url}}  {{sender_name}}
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MT_Templates {

    public function init() {
        add_action( 'wp_ajax_mt_get_starter_templates',   array( $this, 'ajax_get_starter_templates' ) );
        add_action( 'wp_ajax_mt_use_starter_template',    array( $this, 'ajax_use_starter_template' ) );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TEMPLATE REGISTRY
    // ─────────────────────────────────────────────────────────────────────────

    public static function get_registry() {
        return [
            'starter'      => [ 'name' => 'Brand Starter',        'icon' => 'fa-envelope',         'color' => '#4f46e5', 'desc' => 'Clean branded template with logo, body, and footer.' ],
            'birthday'     => [ 'name' => 'Birthday Celebration',  'icon' => 'fa-cake-candles',     'color' => '#ec4899', 'desc' => 'Warm birthday email with personalised greeting and offer.' ],
            'anniversary'  => [ 'name' => 'Visit Anniversary',     'icon' => 'fa-star',             'color' => '#f59e0b', 'desc' => 'Celebrate a customer milestone with a special reward.' ],
            'winback'      => [ 'name' => "We Miss You",           'icon' => 'fa-heart',            'color' => '#ef4444', 'desc' => 'Re-engage inactive customers with a compelling offer.' ],
            'promo'        => [ 'name' => 'Promo Blast',           'icon' => 'fa-tag',              'color' => '#10b981', 'desc' => 'Bold promotional email for sales and special events.' ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HTML GENERATORS (returns HTML with merge tags still intact)
    // ─────────────────────────────────────────────────────────────────────────

    public static function get_template_html( $slug, $brand = null ) {
        $brand_name   = $brand ? esc_html( $brand->brand_name ) : '{{brand_name}}';
        $brand_color  = ( $brand && !empty($brand->primary_color) ) ? esc_attr($brand->primary_color) : '{{brand_color}}';
        $cfg          = $brand ? ( json_decode($brand->brand_config, true) ?: [] ) : [];
        $logo_url     = $cfg['logos']['main'] ?? '';
        $address      = $cfg['hq_address'] ?? '{{address}}';
        $sender_name  = $cfg['sender_name'] ?? $brand_name;
        $website_url  = $cfg['url'] ?? '#';

        // Shared header/footer HTML
        $header = self::email_header( $brand_color, $logo_url, $brand_name );
        $footer = self::email_footer( $brand_name, $address, $website_url );

        switch ( $slug ) {
            case 'birthday':
                return self::birthday_html( $header, $footer, $brand_color, $brand_name );
            case 'anniversary':
                return self::anniversary_html( $header, $footer, $brand_color, $brand_name );
            case 'winback':
                return self::winback_html( $header, $footer, $brand_color, $brand_name );
            case 'promo':
                return self::promo_html( $header, $footer, $brand_color, $brand_name );
            default:
                return self::starter_html( $header, $footer, $brand_color, $brand_name );
        }
    }

    // ── Shared Components ────────────────────────────────────────────────────

    private static function email_header( $brand_color, $logo_url, $brand_name ) {
        $logo_html = $logo_url
            ? "<img src=\"{$logo_url}\" alt=\"{$brand_name}\" style=\"max-height:60px;max-width:200px;display:block;margin:0 auto;\">"
            : "<span style=\"font-size:24px;font-weight:900;color:#ffffff;\">{$brand_name}</span>";

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>{$brand_name}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:20px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

<!-- HEADER -->
<tr>
  <td style="background-color:{$brand_color};padding:30px 40px;text-align:center;border-radius:12px 12px 0 0;">
    {$logo_html}
  </td>
</tr>
HTML;
    }

    private static function email_footer( $brand_name, $address, $website_url ) {
        return <<<HTML

<!-- FOOTER -->
<tr>
  <td style="background-color:#1f2937;padding:30px 40px;text-align:center;border-radius:0 0 12px 12px;">
    <p style="margin:0 0 8px;color:#9ca3af;font-size:13px;">&copy; {{brand_name}} — All rights reserved.</p>
    <p style="margin:0 0 8px;color:#6b7280;font-size:12px;">{{address}}</p>
    <p style="margin:0;font-size:12px;">
      <a href="{$website_url}" style="color:#6b7280;text-decoration:none;margin:0 8px;">Website</a>
      <a href="{{unsubscribe_url}}" style="color:#6b7280;text-decoration:none;margin:0 8px;">Unsubscribe</a>
    </p>
  </td>
</tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }

    // ── Individual Templates ─────────────────────────────────────────────────

    private static function starter_html( $header, $footer, $brand_color, $brand_name ) {
        return $header . <<<HTML

<!-- BODY -->
<tr>
  <td style="background:#ffffff;padding:40px;border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb;">
    <h1 style="margin:0 0 16px;font-size:26px;font-weight:900;color:#111827;line-height:1.2;">Hello {{first_name}},</h1>
    <p style="margin:0 0 20px;font-size:16px;color:#374151;line-height:1.6;">
      Thank you for being a valued guest at {$brand_name}. We appreciate your loyalty and wanted to reach out with something special just for you.
    </p>
    <p style="margin:0 0 32px;font-size:16px;color:#374151;line-height:1.6;">
      [Add your main message here — a promotion, announcement, or update for your guests.]
    </p>
    <!-- CTA Button -->
    <table cellpadding="0" cellspacing="0" style="margin:0 auto;">
      <tr>
        <td style="background-color:{$brand_color};border-radius:8px;padding:14px 32px;">
          <a href="{$brand_name}" style="color:#ffffff;font-size:16px;font-weight:700;text-decoration:none;display:block;">Visit Us Today</a>
        </td>
      </tr>
    </table>
  </td>
</tr>
HTML . $footer;
    }

    private static function birthday_html( $header, $footer, $brand_color, $brand_name ) {
        return $header . <<<HTML

<!-- BIRTHDAY BANNER -->
<tr>
  <td style="background:#fff0f6;padding:32px 40px;text-align:center;border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb;">
    <p style="margin:0 0 4px;font-size:40px;">🎂</p>
    <h1 style="margin:0 0 12px;font-size:30px;font-weight:900;color:#be185d;line-height:1.2;">Happy Birthday, {{first_name}}!</h1>
    <p style="margin:0;font-size:16px;color:#6b7280;line-height:1.5;">Wishing you a wonderful day from all of us at {$brand_name}.</p>
  </td>
</tr>

<!-- BODY -->
<tr>
  <td style="background:#ffffff;padding:40px;border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb;">
    <p style="margin:0 0 20px;font-size:16px;color:#374151;line-height:1.6;">
      Your birthday is a special occasion, and we want to celebrate it with you! As a thank-you for being part of our community, we have a little gift just for you.
    </p>
    <div style="background:#fff0f6;border:2px dashed #f9a8d4;border-radius:12px;padding:24px;text-align:center;margin:0 0 28px;">
      <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#be185d;text-transform:uppercase;letter-spacing:1px;">Your Birthday Gift</p>
      <p style="margin:0;font-size:22px;font-weight:900;color:#111827;">[Your special birthday offer here]</p>
    </div>
    <!-- CTA Button -->
    <table cellpadding="0" cellspacing="0" style="margin:0 auto;">
      <tr>
        <td style="background-color:{$brand_color};border-radius:8px;padding:14px 32px;">
          <a href="#" style="color:#ffffff;font-size:16px;font-weight:700;text-decoration:none;display:block;">Claim Your Birthday Gift</a>
        </td>
      </tr>
    </table>
    <p style="margin:24px 0 0;font-size:13px;color:#9ca3af;text-align:center;">Offer valid during your birthday month.</p>
  </td>
</tr>
HTML . $footer;
    }

    private static function anniversary_html( $header, $footer, $brand_color, $brand_name ) {
        return $header . <<<HTML

<!-- ANNIVERSARY BANNER -->
<tr>
  <td style="background:#fffbeb;padding:32px 40px;text-align:center;border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb;">
    <p style="margin:0 0 4px;font-size:40px;">⭐</p>
    <h1 style="margin:0 0 12px;font-size:28px;font-weight:900;color:#d97706;line-height:1.2;">You're a {{brand_name}} Regular, {{first_name}}!</h1>
    <p style="margin:0;font-size:16px;color:#6b7280;line-height:1.5;">We love having you as part of our community. Here's to many more visits!</p>
  </td>
</tr>

<!-- BODY -->
<tr>
  <td style="background:#ffffff;padding:40px;border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb;">
    <p style="margin:0 0 20px;font-size:16px;color:#374151;line-height:1.6;">
      We noticed you've been a loyal guest at {$brand_name} and we couldn't be more grateful. Loyal customers like you are the reason we do what we do.
    </p>
    <p style="margin:0 0 28px;font-size:16px;color:#374151;line-height:1.6;">
      As a token of our appreciation, here's something special waiting for you on your next visit:
    </p>
    <div style="background:#fffbeb;border:2px dashed #fcd34d;border-radius:12px;padding:24px;text-align:center;margin:0 0 28px;">
      <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#d97706;text-transform:uppercase;letter-spacing:1px;">Your Loyalty Reward</p>
      <p style="margin:0;font-size:22px;font-weight:900;color:#111827;">[Your loyalty reward here]</p>
    </div>
    <!-- CTA Button -->
    <table cellpadding="0" cellspacing="0" style="margin:0 auto;">
      <tr>
        <td style="background-color:{$brand_color};border-radius:8px;padding:14px 32px;">
          <a href="#" style="color:#ffffff;font-size:16px;font-weight:700;text-decoration:none;display:block;">Redeem Your Reward</a>
        </td>
      </tr>
    </table>
  </td>
</tr>
HTML . $footer;
    }

    private static function winback_html( $header, $footer, $brand_color, $brand_name ) {
        return $header . <<<HTML

<!-- WINBACK BANNER -->
<tr>
  <td style="background:#fef2f2;padding:32px 40px;text-align:center;border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb;">
    <p style="margin:0 0 4px;font-size:40px;">❤️</p>
    <h1 style="margin:0 0 12px;font-size:28px;font-weight:900;color:#dc2626;line-height:1.2;">We Miss You, {{first_name}}!</h1>
    <p style="margin:0;font-size:16px;color:#6b7280;line-height:1.5;">It's been a while since we've seen you at {$brand_name}.</p>
  </td>
</tr>

<!-- BODY -->
<tr>
  <td style="background:#ffffff;padding:40px;border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb;">
    <p style="margin:0 0 20px;font-size:16px;color:#374151;line-height:1.6;">
      We noticed you haven't been by in a while, and we genuinely miss having you around. Life gets busy — we get it. But we wanted to make it worth your while to come back.
    </p>
    <div style="background:#fef2f2;border:2px dashed #fca5a5;border-radius:12px;padding:24px;text-align:center;margin:0 0 28px;">
      <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#dc2626;text-transform:uppercase;letter-spacing:1px;">A Gift, Just For You</p>
      <p style="margin:0;font-size:22px;font-weight:900;color:#111827;">[Your win-back offer here]</p>
      <p style="margin:8px 0 0;font-size:13px;color:#6b7280;">Limited time — we'd love to see you soon.</p>
    </div>
    <!-- CTA Button -->
    <table cellpadding="0" cellspacing="0" style="margin:0 auto;">
      <tr>
        <td style="background-color:{$brand_color};border-radius:8px;padding:14px 32px;">
          <a href="#" style="color:#ffffff;font-size:16px;font-weight:700;text-decoration:none;display:block;">Come Back &amp; Claim It</a>
        </td>
      </tr>
    </table>
  </td>
</tr>
HTML . $footer;
    }

    private static function promo_html( $header, $footer, $brand_color, $brand_name ) {
        return $header . <<<HTML

<!-- PROMO BANNER -->
<tr>
  <td style="background:{$brand_color};padding:32px 40px;text-align:center;border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb;">
    <p style="margin:0 0 4px;font-size:40px;">🎉</p>
    <h1 style="margin:0 0 12px;font-size:30px;font-weight:900;color:#ffffff;line-height:1.2;">[Your Headline Here]</h1>
    <p style="margin:0;font-size:16px;color:rgba(255,255,255,0.85);line-height:1.5;">[Your sub-headline or offer summary]</p>
  </td>
</tr>

<!-- BODY -->
<tr>
  <td style="background:#ffffff;padding:40px;border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb;">
    <p style="margin:0 0 20px;font-size:16px;color:#374151;line-height:1.6;">
      Hi {{first_name}}, we have something exciting to share with you. For a limited time, {$brand_name} is offering an exclusive deal just for our loyal guests.
    </p>
    <div style="border:2px solid {$brand_color};border-radius:12px;padding:28px;text-align:center;margin:0 0 28px;">
      <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:{$brand_color};text-transform:uppercase;letter-spacing:1px;">Limited Time Offer</p>
      <p style="margin:0;font-size:28px;font-weight:900;color:#111827;">[Your promo details]</p>
      <p style="margin:8px 0 0;font-size:13px;color:#6b7280;">Expires: [Date]</p>
    </div>
    <!-- CTA Button -->
    <table cellpadding="0" cellspacing="0" style="margin:0 auto;">
      <tr>
        <td style="background-color:{$brand_color};border-radius:8px;padding:14px 32px;">
          <a href="#" style="color:#ffffff;font-size:16px;font-weight:700;text-decoration:none;display:block;">Claim This Offer</a>
        </td>
      </tr>
    </table>
  </td>
</tr>
HTML . $footer;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX: LIST STARTER TEMPLATES
    // ─────────────────────────────────────────────────────────────────────────

    public function ajax_get_starter_templates() {
        check_ajax_referer( 'mt_app_nonce', 'security' );
        $registry = self::get_registry();
        $out = [];
        foreach ( $registry as $slug => $meta ) {
            $out[] = array_merge( $meta, [ 'slug' => $slug ] );
        }
        wp_send_json_success( $out );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX: CREATE TEMPLATE FROM STARTER
    // ─────────────────────────────────────────────────────────────────────────

    public function ajax_use_starter_template() {
        check_ajax_referer( 'mt_app_nonce', 'security' );

        $slug     = sanitize_key( $_POST['slug'] ?? 'starter' );
        $user_id  = get_current_user_id();
        $brand_id = (int) get_user_meta( $user_id, 'mt_brand_id', true );

        global $wpdb;
        $brand = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mt_brands WHERE id = %d", $brand_id
        ) );
        if ( ! $brand ) wp_send_json_error( 'Brand not found.' );

        $registry = self::get_registry();
        if ( ! isset( $registry[$slug] ) ) wp_send_json_error( 'Unknown template.' );

        $html          = self::get_template_html( $slug, $brand );
        $template_name = $registry[$slug]['name'] . ' — ' . date('M j, Y');

        // Subject lines per template type
        $subjects = [
            'starter'     => 'Hello from ' . $brand->brand_name,
            'birthday'    => '🎂 Happy Birthday, {{first_name}}! A gift inside...',
            'anniversary' => '⭐ You\'re a ' . $brand->brand_name . ' regular — here\'s a reward!',
            'winback'     => '❤️ We miss you, {{first_name}} — come back for a treat',
            'promo'       => '🎉 Special offer inside — just for you',
        ];

        // Store body as base64 JSON payload (same format as BuilderJS save)
        $payload = base64_encode( wp_json_encode( [ 'html' => $html, 'json' => null, 'source' => 'starter' ] ) );

        $wpdb->insert( $wpdb->prefix . 'mt_email_templates', [
            'brand_id'      => $brand_id,
            'template_name' => $template_name,
            'email_subject' => $subjects[$slug] ?? 'Message from ' . $brand->brand_name,
            'email_body'    => $payload,
            'status'        => 'active',
            'created_at'    => current_time('mysql'),
            'updated_at'    => current_time('mysql'),
        ] );

        $template_id = $wpdb->insert_id;

        wp_send_json_success( [
            'template_id'   => $template_id,
            'template_name' => $template_name,
            'message'       => 'Template created! You can now select it in a campaign or open it in the Studio to edit.',
        ] );
    }
}
