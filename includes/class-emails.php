<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Email handling for GPS Courses
 */
class Emails {

    public static function init() {
        add_action('admin_init', [__CLASS__, 'migrate_brand_once']);
        // Hook into order completion to send ticket emails
        add_action('gps_ticket_created', [__CLASS__, 'send_ticket_email'], 10, 2);

        // CE Credits awarded email
        add_action('gps_credits_awarded', [__CLASS__, 'send_credits_email'], 10, 3);

        // Fluent Forms notifications (contact form) in the GPS email frame
        add_filter('fluentform/email_header', [__CLASS__, 'fluentform_email_header'], 10, 3);
        add_filter('fluentform/email_footer', [__CLASS__, 'fluentform_email_footer'], 10, 3);
        add_filter('fluentform/email_styles', [__CLASS__, 'fluentform_email_styles'], 10, 3);

        // WooCommerce emails in the same frame
        add_filter('wc_get_template', [__CLASS__, 'woocommerce_email_template'], 20, 2);
    }

    /**
     * Fluent Forms whose notification emails use the GPS frame.
     */
    private static function fluentform_uses_gps_frame($form) {
        $form_ids = apply_filters('gps_fluentform_branded_email_forms', [3]);
        return $form && in_array((int) $form->id, array_map('intval', (array) $form_ids), true);
    }

    /**
     * Opening half of the GPS frame: navy band with the logo and heading, body cell.
     * Same Email Settings values as the ticket email.
     */
    public static function fluentform_email_header($header, $form, $notification) {
        if (!self::fluentform_uses_gps_frame($form)) {
            return $header;
        }

        $to_sender = (($notification['sendTo']['type'] ?? '') === 'field');
        $heading   = $to_sender ? __('We received your message', 'gps-courses') : __('New contact message', 'gps-courses');
        $heading   = apply_filters('gps_fluentform_email_heading', $heading, $form, $notification);

        return self::frame_open($heading);
    }

    /* ====================================================================
     * GPS email frame, shared by every email the site sends: plugin emails,
     * Fluent Forms notifications and WooCommerce's own (via its templates).
     * ==================================================================== */

    /**
     * The unified design: buttons were WordPress blue (#2271b1, the old
     * default); they become the brand blue once. Any other saved color stays.
     */
    public static function migrate_brand_once() {
        if (get_option('gps_email_brand_v2')) {
            return;
        }
        $button = strtolower(trim((string) get_option('gps_email_button_bg_color', '')));
        if ('' === $button || '#2271b1' === $button) {
            update_option('gps_email_button_bg_color', '#0B52AC');
        }
        update_option('gps_email_brand_v2', 1, false);
    }

    /**
     * Brand values for every email (Emails › Brand). Defaults are the GPS
     * brand: navy header, gold accent line, brand-blue buttons.
     */
    public static function brand($key) {
        $defaults = [
            'header_bg'    => '#0d153f',
            'header_text'  => '#ffffff',
            'accent'       => '#DDC89D',
            'button_bg'    => '#0B52AC',
            'button_text'  => '#ffffff',
            'panel_bg'     => '#f4f7fc',
            'from_name'    => 'GPS Dental Training',
            'from_email'   => 'info@gpsdentaltraining.com',
            'address'      => '6320 Sugarloaf Parkway, Duluth, GA 30097',
        ];
        $options = [
            'header_bg'   => 'gps_email_header_bg_color',
            'header_text' => 'gps_email_header_text_color',
            'accent'      => 'gps_email_accent_color',
            'button_bg'   => 'gps_email_button_bg_color',
            'button_text' => 'gps_email_button_text_color',
            'panel_bg'    => 'gps_email_panel_bg_color',
            'from_name'   => 'gps_email_from_name',
            'from_email'  => 'gps_email_from_email',
            'address'     => 'gps_email_footer_address',
        ];
        if (!isset($defaults[$key])) {
            return '';
        }
        $value = trim((string) get_option($options[$key], ''));
        if ('' === $value) {
            return $defaults[$key];
        }
        if (in_array($key, ['header_bg', 'header_text', 'accent', 'button_bg', 'button_text', 'panel_bg'], true)) {
            return sanitize_hex_color($value) ?: $defaults[$key];
        }
        if ('from_email' === $key) {
            return is_email($value) ? $value : $defaults[$key];
        }
        return $value;
    }

    /**
     * Logo for the navy band. Gmail and Outlook don't show SVG, so an SVG
     * setting falls back to the white PNG logo.
     */
    public static function frame_logo() {
        $logo = (string) get_option('gps_email_logo', '');
        if ($logo === '' || preg_match('/\.svg(\?|$)/i', $logo)) {
            $id   = (int) apply_filters('gps_email_png_logo_id', 7626);
            $png  = $id ? wp_get_attachment_image_url($id, 'full') : '';
            $logo = $png ?: '';
        }
        return (string) apply_filters('gps_email_frame_logo', $logo);
    }

    /**
     * Everything up to and including the opening body cell.
     *
     * @param string $heading         Plain text heading for the navy band.
     * @param string $body_cell_attrs Extra attributes for the body <td> (an id, for example). Printed as is: internal literals only.
     * @param string $body_align      left|center
     */
    public static function frame_open($heading, $body_cell_attrs = '', $body_align = 'left') {
        $logo   = self::frame_logo();
        $navy   = self::brand('header_bg');
        $white  = self::brand('header_text');
        $accent = self::brand('accent');
        $align  = $body_align === 'center' ? 'center' : 'left';
        $name   = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

        $html  = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />'
               . '<meta name="viewport" content="width=device-width, initial-scale=1.0" /><title>' . esc_html($name) . '</title>'
               // Phones: less side padding (clients without media query support keep 40px)
               . '<style>@media only screen and (max-width:480px){.gps-mail-outer{padding:20px 8px !important}.gps-mail-body{padding:24px 18px !important}}</style></head>'
               . '<body style="margin:0;padding:0;background-color:#f2f3f5;font-family:Arial,Helvetica,sans-serif;">'
               . '<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="background-color:#f2f3f5;"><tr><td class="gps-mail-outer" align="center" style="padding:40px 16px;">'
               . '<table width="600" cellpadding="0" cellspacing="0" border="0" role="presentation" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(13,21,63,0.10);">';

        if ($logo) {
            $html .= '<tr><td align="center" style="padding:32px 30px 0;background-color:' . esc_attr($navy) . ';">'
                   . '<img src="' . esc_url($logo) . '" alt="' . esc_attr($name) . '" width="200" style="display:inline-block;width:200px;max-width:200px;height:auto;border:0;margin:0;"></td></tr>';
        }

        $html .= '<tr><td align="center" style="background-color:' . esc_attr($navy) . ';padding:' . ($logo ? '22px 30px 30px' : '34px 30px') . ';">'
               . '<h1 style="margin:0;text-align:center;font-family:Arial,Helvetica,sans-serif;font-size:26px;font-weight:bold;line-height:1.3;letter-spacing:normal;color:' . esc_attr($white) . ';">' . esc_html($heading) . '</h1></td></tr>'
               . '<tr><td style="height:4px;line-height:4px;font-size:0;background-color:' . esc_attr($accent) . ';">&nbsp;</td></tr>'
               . '<tr><td class="gps-mail-body" ' . $body_cell_attrs . ' style="padding:32px 40px;text-align:' . $align . ';font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#2b2f36;background-color:#ffffff;">';

        return $html;
    }

    /**
     * Closes the body cell and adds the footer: address, links, copyright and
     * the sending address.
     */
    public static function frame_close() {
        $name    = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $address = self::brand('address');
        $link    = 'color:' . esc_attr(self::brand('button_bg')) . ';text-decoration:none;';
        $links   = [];
        if (function_exists('wc_get_page_permalink')) {
            $links[] = '<a href="' . esc_url(wc_get_page_permalink('myaccount')) . '" style="' . $link . '">' . esc_html__('My account', 'gps-courses') . '</a>';
        }
        $links[] = '<a href="' . esc_url(home_url('/courses/')) . '" style="' . $link . '">' . esc_html__('Courses', 'gps-courses') . '</a>';
        $links[] = '<a href="' . esc_url(home_url('/contact/')) . '" style="' . $link . '">' . esc_html__('Contact', 'gps-courses') . '</a>';

        return '</td></tr>'
             . '<tr><td align="center" style="background-color:#f6f7f9;padding:24px 30px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.6;color:#5b6472;">'
             . '<p style="margin:0;"><strong style="color:#2b2f36;">' . esc_html($name) . '</strong><br>' . nl2br(esc_html($address)) . '</p>'
             . '<p style="margin:8px 0 0;">' . implode(' &middot; ', $links) . '</p>'
             . '<p style="margin:8px 0 0;font-size:12px;color:#737b88;">&copy; ' . esc_html(date_i18n('Y')) . ' ' . esc_html($name) . '. '
             /* translators: %s: sending email address */
             . esc_html(sprintf(__('Sent from %s', 'gps-courses'), self::brand('from_email'))) . '</p>'
             . '</td></tr></table></td></tr></table></body></html>';
    }

    /* ====================================================================
     * Body components. Table-based with inline styles: email clients
     * (Outlook above all) ignore flex, grid and <style> blocks.
     * Every argument documented as "html" must already be escaped.
     * ==================================================================== */

    /** A paragraph. $html must be escaped. */
    public static function paragraph($html, $muted = false) {
        return '<p style="margin:0 0 18px;' . ($muted ? 'font-size:14px;color:#5b6472;' : '') . '">' . $html . '</p>';
    }

    /**
     * Light panel with a bold title and label / value rows.
     *
     * @param string $title  Plain text (escaped here).
     * @param array  $rows   [label => value]; values are html (escape them).
     * @param string $badge  Plain text pill above the title (optional).
     */
    public static function panel($title, array $rows = [], $badge = '') {
        $html = '<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="margin:0 0 18px;background-color:' . esc_attr(self::brand('panel_bg')) . ';border-radius:8px;"><tr><td style="padding:20px 22px;">';
        if ('' !== $badge) {
            $html .= '<span style="display:inline-block;margin:0 0 10px;padding:3px 10px;border-radius:999px;background-color:#f3efe4;color:#6f5822;font-size:12px;font-weight:bold;">' . esc_html($badge) . '</span><br>';
        }
        if ('' !== $title) {
            $html .= '<div style="margin:0 0 10px;font-size:18px;font-weight:bold;line-height:1.35;color:#0d153f;">' . esc_html($title) . '</div>';
        }
        if ($rows) {
            $html .= '<table cellpadding="0" cellspacing="0" border="0" role="presentation" style="font-size:14px;line-height:1.5;">';
            foreach ($rows as $label => $value) {
                $html .= '<tr><td valign="top" style="padding:3px 14px 3px 0;color:#5b6472;white-space:nowrap;">' . esc_html($label) . '</td><td valign="top" style="padding:3px 0;color:#2b2f36;">' . $value . '</td></tr>';
            }
            $html .= '</table>';
        }
        return $html . '</td></tr></table>';
    }

    /** Dashed box with an optional QR image (above, so it fits a phone) and a code. */
    public static function code_box($label, $code, $note = '', $qr_url = '') {
        $html = '<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="margin:0 0 18px;border:1px dashed #c8cfdb;border-radius:8px;">';
        if ($qr_url) {
            $html .= '<tr><td align="center" style="padding:22px 22px 0;"><img src="' . esc_url($qr_url) . '" alt="' . esc_attr__('QR code', 'gps-courses') . '" width="160" height="160" style="display:block;width:160px;height:160px;border:1px solid #e3e7ee;border-radius:6px;"></td></tr>';
        }
        $html .= '<tr><td align="center" style="padding:16px 22px 20px;text-align:center;">'
               . '<div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#5b6472;font-weight:bold;">' . esc_html($label) . '</div>'
               . '<div style="margin:4px 0;font-family:\'Courier New\',monospace;font-size:17px;font-weight:bold;color:#0d153f;word-break:break-all;">' . esc_html($code) . '</div>';
        if ('' !== $note) {
            $html .= '<div style="font-size:13px;color:#5b6472;">' . esc_html($note) . '</div>';
        }
        return $html . '</td></tr></table>';
    }

    /** Big centered code (sign-up code). */
    public static function big_code($code) {
        return '<table cellpadding="0" cellspacing="0" border="0" role="presentation" align="center" style="margin:0 auto 18px;"><tr><td style="padding:18px 34px;background-color:' . esc_attr(self::brand('panel_bg')) . ';border-radius:8px;font-family:\'Courier New\',monospace;font-size:34px;font-weight:bold;letter-spacing:6px;color:#0d153f;text-align:center;">' . esc_html($code) . '</td></tr></table>';
    }

    /** Round gold badge with a number (credits) above a title and lines (centered: fits a phone). Lines are html. */
    public static function credits_badge($number, $unit, $title, array $lines = []) {
        $html = '<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="margin:0 0 18px;background-color:' . esc_attr(self::brand('panel_bg')) . ';border-radius:8px;"><tr>'
              . '<td align="center" style="padding:22px 22px 0;">'
              . '<table cellpadding="0" cellspacing="0" border="0" role="presentation" align="center"><tr><td align="center" valign="middle" width="98" height="98" style="width:98px;height:98px;border-radius:50%;background-color:#ffffff;border:3px solid ' . esc_attr(self::brand('accent')) . ';text-align:center;">'
              . '<div style="font-size:32px;font-weight:bold;line-height:1;color:#0d153f;">' . esc_html($number) . '</div>'
              . '<div style="font-size:12px;font-weight:bold;letter-spacing:.08em;color:#6f5822;">' . esc_html($unit) . '</div>'
              . '</td></tr></table></td></tr>'
              . '<tr><td align="center" style="padding:14px 22px 22px;text-align:center;">'
              . '<div style="font-size:17px;font-weight:bold;line-height:1.35;color:#0d153f;">' . esc_html($title) . '</div>';
        foreach ($lines as $line) {
            $html .= '<div style="margin-top:4px;font-size:14px;color:#5b6472;">' . $line . '</div>';
        }
        return $html . '</td></tr></table>';
    }

    /** Amber (or green / red) notice. $html must be escaped. */
    public static function notice($html, $tone = 'warn') {
        $tones = [
            'warn' => ['#fdf6e8', '#f0d9ae', '#6b3a00'],
            'ok'   => ['#e7f4ed', '#bfe0cc', '#145c3a'],
            'bad'  => ['#fbecea', '#f0c4be', '#9c2a23'],
        ];
        $t = isset($tones[$tone]) ? $tones[$tone] : $tones['warn'];
        return '<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="margin:0 0 18px;background-color:' . $t[0] . ';border:1px solid ' . $t[1] . ';border-radius:8px;"><tr><td style="padding:14px 18px;font-size:14px;line-height:1.5;color:' . $t[2] . ';">' . $html . '</td></tr></table>';
    }

    /** Progress bar with a caption. */
    public static function progress($percent, $caption) {
        $percent = max(0, min(100, (int) $percent));
        return '<div style="margin:0 0 18px;">'
             . '<div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#5b6472;font-weight:bold;margin-bottom:6px;">' . esc_html__('Your progress', 'gps-courses') . '</div>'
             . '<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation"><tr>'
             . ($percent > 0 ? '<td width="' . $percent . '%" style="height:8px;line-height:8px;font-size:0;background-color:' . esc_attr(self::brand('button_bg')) . ';border-radius:4px 0 0 4px;">&nbsp;</td>' : '')
             . ($percent < 100 ? '<td style="height:8px;line-height:8px;font-size:0;background-color:#e8ecf2;border-radius:0 4px 4px 0;">&nbsp;</td>' : '')
             . '</tr></table>'
             . '<div style="margin-top:6px;font-size:14px;color:#5b6472;">' . esc_html($caption) . '</div></div>';
    }

    /** One attached-file row (certificate, receipt). */
    public static function file_row($filename, $meta = '') {
        return '<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="margin:0 0 18px;border:1px solid #e3e7ee;border-radius:8px;"><tr>'
             . '<td width="62" valign="middle" style="padding:16px 0 16px 18px;"><div style="width:44px;height:54px;line-height:54px;border-radius:4px;background-color:#fbecea;color:#9c2a23;font-size:12px;font-weight:bold;text-align:center;">PDF</div></td>'
             . '<td valign="middle" style="padding:16px 18px;"><div style="font-weight:bold;color:#0d153f;">' . esc_html($filename) . '</div>'
             . ('' !== $meta ? '<div style="font-size:13px;color:#5b6472;">' . esc_html($meta) . '</div>' : '')
             . '</td></tr></table>';
    }

    /** Table of rows: [[cells...], ...]; first row is the header. Cells are html. */
    public static function table(array $rows, array $align = []) {
        $html = '<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="margin:0 0 18px;border:1px solid #e3e7ee;border-radius:8px;font-size:14px;">';
        foreach (array_values($rows) as $i => $cells) {
            $html .= '<tr>';
            foreach (array_values($cells) as $c => $cell) {
                $a = isset($align[$c]) ? $align[$c] : 'left';
                $html .= 0 === $i
                    ? '<td align="' . $a . '" style="padding:10px;' . ('right' === $a ? 'white-space:nowrap;' : '') . 'background-color:#f6f7f9;font-size:11px;font-weight:bold;letter-spacing:.04em;text-transform:uppercase;color:#5b6472;">' . $cell . '</td>'
                    // Amounts / numbers (right-aligned) never wrap: "$2,500.00" stays on one line
                    : '<td align="' . $a . '" style="padding:10px;border-top:1px solid #eef0f4;' . ('right' === $a ? 'white-space:nowrap;' : 'overflow-wrap:break-word;word-break:normal;') . '">' . $cell . '</td>';
            }
            $html .= '</tr>';
        }
        return $html . '</table>';
    }

    /** Button(s), centered. $secondary: [label, url] for an outlined second button. */
    public static function button($label, $url, $secondary = null) {
        $primary = '<a href="' . esc_url($url) . '" style="display:inline-block;margin:4px;background-color:' . esc_attr(self::brand('button_bg')) . ';color:' . esc_attr(self::brand('button_text')) . ';text-decoration:none;font-weight:bold;font-size:15px;padding:13px 26px;border-radius:6px;">' . esc_html($label) . '</a>';
        $second  = '';
        if (is_array($secondary) && !empty($secondary[0]) && !empty($secondary[1])) {
            $second = '<a href="' . esc_url($secondary[1]) . '" style="display:inline-block;margin:4px;background-color:#ffffff;color:' . esc_attr(self::brand('button_bg')) . ';text-decoration:none;font-weight:bold;font-size:15px;padding:12px 24px;border-radius:6px;border:1px solid ' . esc_attr(self::brand('button_bg')) . ';">' . esc_html($secondary[0]) . '</a>';
        }
        return '<p style="margin:2px 0 16px;text-align:center;">' . $primary . $second . '</p>';
    }

    /* ====================================================================
     * One way to build and send every email
     * ==================================================================== */

    /** "{course}" → value, for subject / heading / intro / button texts. */
    protected static function fill($text, array $vars) {
        return preg_replace_callback('/\{([a-z0-9_]+)\}/', function ($m) use ($vars) {
            return array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0];
        }, (string) $text);
    }

    /**
     * Subject and HTML of an email from its catalog texts and its blocks.
     *
     * @param string $key    Email_Catalog key.
     * @param array  $vars   Plain-text values for {placeholders} (first_name adds "Hi …,").
     * @param string $blocks Body html between the intro and the button (escaped).
     * @param array  $opts   button_url, secondary [label, url], after (html after the
     *                       button), heading / subject (override), align (center).
     * @return array{subject:string, html:string}
     */
    public static function render($key, array $vars, $blocks = '', array $opts = []) {
        $texts = Email_Catalog::texts($key);
        $vars  = array_map(function ($v) {
            return html_entity_decode(wp_strip_all_tags((string) $v), ENT_QUOTES, 'UTF-8');
        }, $vars);

        $subject = isset($opts['subject']) ? (string) $opts['subject'] : self::fill($texts['subject'], $vars);
        $heading = isset($opts['heading']) ? (string) $opts['heading'] : self::fill($texts['heading'], $vars);
        $intro   = self::fill($texts['intro'], $vars);
        $button  = self::fill($texts['button'], $vars);

        $body = '';
        if (!empty($vars['first_name']) && empty($opts['no_greeting'])) {
            /* translators: %s: first name */
            $body .= self::paragraph(esc_html(sprintf(__('Hi %s,', 'gps-courses'), $vars['first_name'])));
        }
        if ('' !== trim($intro)) {
            foreach (preg_split("/\n\s*\n/", trim($intro)) as $para) {
                $body .= self::paragraph(nl2br(esc_html($para)));
            }
        }
        $body .= (string) $blocks;
        if ('' !== $button && !empty($opts['button_url'])) {
            $body .= self::button($button, $opts['button_url'], isset($opts['secondary']) ? $opts['secondary'] : null);
        }
        if (!empty($opts['after'])) {
            $body .= $opts['after'];
        }

        $html = self::frame_open($heading, '', !empty($opts['align']) ? $opts['align'] : 'left') . $body . self::frame_close();
        return ['subject' => wp_specialchars_decode($subject, ENT_QUOTES), 'html' => $html];
    }

    /** "From" for every email: Emails › Brand. */
    public static function from_header() {
        $name = str_replace(['"', "\r", "\n"], '', self::brand('from_name'));
        return 'From: ' . $name . ' <' . self::brand('from_email') . '>';
    }

    /**
     * Render and send. Returns false (and sends nothing) when the email is
     * switched off in Emails › All emails, unless $opts['force'] (tests).
     *
     * $opts adds to render(): attachments (paths), reply_to, headers (extra).
     */
    public static function send($key, $to, array $vars, $blocks = '', array $opts = []) {
        if (empty($opts['force']) && !Email_Catalog::enabled($key)) {
            return false;
        }
        if (empty($to)) {
            return false;
        }
        $mail    = self::render($key, $vars, $blocks, $opts);
        $headers = array_merge(
            ['Content-Type: text/html; charset=UTF-8', self::from_header()],
            !empty($opts['reply_to']) ? ['Reply-To: ' . $opts['reply_to']] : [],
            isset($opts['headers']) ? (array) $opts['headers'] : []
        );
        $sent = wp_mail($to, $mail['subject'], $mail['html'], $headers, isset($opts['attachments']) ? (array) $opts['attachments'] : []);
        if ($sent && empty($opts['test'])) {
            Email_Catalog::mark_sent($key);
        }
        if (!$sent) {
            error_log('GPS Courses: email "' . $key . '" to ' . (is_array($to) ? implode(',', $to) : $to) . ' failed.');
        }
        return $sent;
    }

    /**
     * Send the parts a catalog "parts" builder returned:
     * ['to', 'vars', 'blocks', 'opts'].
     */
    public static function send_parts($key, $parts) {
        if (!is_array($parts) || empty($parts['to'])) {
            return false;
        }
        return self::send($key, $parts['to'], isset($parts['vars']) ? (array) $parts['vars'] : [], isset($parts['blocks']) ? (string) $parts['blocks'] : '', isset($parts['opts']) ? (array) $parts['opts'] : []);
    }

    /** First name for "Hi …," from a WP user, a full name, or an email. */
    public static function first_name($user_or_name, $fallback = '') {
        if ($user_or_name instanceof \WP_User) {
            $first = trim((string) $user_or_name->first_name);
            $first = $first ?: trim((string) strtok((string) $user_or_name->display_name, ' '));
            return ('' !== $first && false === strpos($first, '@')) ? self::person_name($first) : $fallback;
        }
        $name = trim((string) $user_or_name);
        if ('' === $name || false !== strpos($name, '@')) {
            return $fallback;
        }
        return self::person_name((string) strtok($name, ' '));
    }

    /**
     * {all_data} prints a fixed 600px table; keep it inside the GPS card.
     */
    public static function fluentform_email_styles($css, $form, $notification) {
        if (!self::fluentform_uses_gps_frame($form)) {
            return $css;
        }
        return $css . ' table.ff_all_data { width: 100%; }';
    }

    /**
     * Closing half of the GPS frame for Fluent Forms.
     */
    public static function fluentform_email_footer($footer, $form, $notification) {
        if (!self::fluentform_uses_gps_frame($form)) {
            return $footer;
        }
        return self::frame_close();
    }

    /**
     * WooCommerce emails (order, account, password reset) use the GPS frame:
     * our email-header.php / email-footer.php replace WooCommerce's.
     */
    public static function woocommerce_email_template($template, $template_name) {
        if (!in_array($template_name, ['emails/email-header.php', 'emails/email-footer.php'], true)) {
            return $template;
        }
        if (!apply_filters('gps_woocommerce_emails_use_gps_frame', true)) {
            return $template;
        }
        $ours = GPSC_PATH . 'templates/' . $template_name;
        return file_exists($ours) ? $ours : $template;
    }

    /* ====================================================================
     * Course facts shared by the course emails (ticket, credits,
     * certificate, waitlist). Plain text; escape when printing.
     * ==================================================================== */

    /** Title or meta as plain text (no tags, entities decoded). */
    public static function plain($text) {
        return trim(html_entity_decode(wp_strip_all_tags((string) $text), ENT_QUOTES, 'UTF-8'));
    }

    /** "Nov 13–14, 2026", "Nov 30 – Dec 1, 2026", "Nov 13, 2026"; '' without a start. */
    public static function date_range_label($start, $end = '') {
        $start = trim((string) $start);
        if ('' === $start) {
            return '';
        }
        $s = strtotime($start);
        if (!$s) {
            return '';
        }
        $e = trim((string) $end) !== '' ? strtotime($end) : $s;
        if (!$e || $e <= $s) {
            return date_i18n('M j, Y', $s);
        }
        if (date('Y-m', $s) === date('Y-m', $e)) {
            return date_i18n('M j', $s) . '–' . date_i18n('j, Y', $e);
        }
        if (date('Y', $s) === date('Y', $e)) {
            return date_i18n('M j', $s) . ' – ' . date_i18n('M j, Y', $e);
        }
        return date_i18n('M j, Y', $s) . ' – ' . date_i18n('M j, Y', $e);
    }

    /** "maria lopez" → "Maria Lopez"; names typed with capitals stay as typed. */
    public static function person_name($name) {
        $name = trim((string) $name);
        return ($name !== '' && $name === strtolower($name)) ? ucwords($name, " \t-") : $name;
    }

    /** "8:30 AM – 5:30 PM" from "08:30" / "17:30" (or "8:30 AM"); '' without a start. */
    public static function time_range_label($start, $end = '') {
        $label = function ($time) {
            $time = trim((string) $time);
            if ('' === $time || !preg_match('/^\d{1,2}:\d{2}/', $time)) {
                return '';
            }
            $ts = strtotime('2000-01-01 ' . $time);
            return $ts ? date('g:i A', $ts) : '';
        };
        $s = $label($start);
        if ('' === $s) {
            return '';
        }
        $e = $label($end);
        return ('' !== $e && $e !== $s) ? $s . ' – ' . $e : $s;
    }

    /** "16 CE credits", "1 CE credit"; '' for none. */
    public static function credits_label($credits) {
        $credits = (float) $credits;
        if ($credits <= 0) {
            return '';
        }
        /* translators: %s: number of CE credits */
        return sprintf(1.0 === $credits ? __('%s CE credit', 'gps-courses') : __('%s CE credits', 'gps-courses'), Credits::format($credits));
    }

    /**
     * What the course emails show about a course.
     *
     * @return array{title:string,url:string,dates:string,time:string,location:string,city:string,credits:float}
     */
    public static function course_facts($event_id) {
        $event_id = (int) $event_id;
        $meta     = function ($key) use ($event_id) {
            return trim((string) get_post_meta($event_id, $key, true));
        };
        $venue = $meta('_gps_venue') ?: $meta('_gps_location');
        $city  = trim($meta('_gps_city') . ('' !== $meta('_gps_state') ? ', ' . $meta('_gps_state') : ''), ', ');
        $where = array_filter([self::plain($venue), $city], 'strlen');
        // "Duluth, GA" inside the venue name already: don't repeat it
        if (count($where) === 2 && '' !== $city && false !== stripos($where[0], $city)) {
            $where = [$where[0]];
        }
        return [
            'title'    => self::plain(get_the_title($event_id)),
            'url'      => (string) get_permalink($event_id),
            'dates'    => self::date_range_label($meta('_gps_start_date'), $meta('_gps_end_date')),
            'time'     => self::time_range_label($meta('_gps_start_time'), $meta('_gps_end_time')),
            'location' => implode(' · ', $where),
            'city'     => $city,
            'credits'  => (float) get_post_meta($event_id, '_gps_ce_credits', true),
        ];
    }

    /** My Account endpoint URL (gps-tickets, gps-ce-credits, …). */
    public static function account_url($endpoint) {
        if (function_exists('wc_get_account_endpoint_url')) {
            return (string) wc_get_account_endpoint_url($endpoint);
        }
        return home_url('/my-account/' . $endpoint . '/');
    }

    /* ====================================================================
     * Ticket confirmation (catalog key "ticket")
     * ==================================================================== */

    /**
     * Ticket email for a course ticket: to the effective attendee (the
     * designated attendee, else the buyer / guest on the ticket).
     *
     * @param int|object $ticket_id gps_tickets id or row.
     * @param int        $order_id  Order (defaults to the ticket's).
     * @return array|null ['to','vars','blocks','opts']
     */
    public static function ticket_parts($ticket_id, $order_id = 0) {
        global $wpdb;
        $ticket = is_object($ticket_id) ? $ticket_id : $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_tickets WHERE id = %d",
            (int) $ticket_id
        ));
        if (!$ticket || !get_post((int) $ticket->event_id)) {
            return null;
        }

        // Copy without the id: building an email never writes to the ticket
        $copy = clone $ticket;
        unset($copy->id);
        $effective = Tickets_Admin::get_effective_attendee($copy);

        $to    = '';
        $first = '';
        $user  = (int) $effective->user_id > 0 ? get_userdata((int) $effective->user_id) : false;
        if ($user) {
            $to    = $effective->email ?: $user->user_email;
            $first = $effective->name ? self::first_name(self::person_name($effective->name)) : self::first_name($user);
        } else {
            $to    = (string) $effective->email;
            $first = self::first_name(self::person_name($effective->name));
        }
        if ('' === trim((string) $to)) {
            return null;
        }

        $f    = self::course_facts((int) $ticket->event_id);
        $type = get_post((int) $ticket->ticket_type_id);

        $rows = [];
        if ('' !== $f['dates']) {
            $rows[__('Dates', 'gps-courses')] = esc_html($f['dates']);
        }
        if ('' !== $f['time']) {
            $rows[__('Time', 'gps-courses')] = esc_html($f['time']);
        }
        if ('' !== $f['location']) {
            $rows[__('Location', 'gps-courses')] = esc_html($f['location']);
        }
        if ($type) {
            $rows[__('Ticket type', 'gps-courses')] = esc_html(self::plain($type->post_title));
        }
        if ($f['credits'] > 0) {
            $rows[__('CE credits', 'gps-courses')] = esc_html(self::credits_label($f['credits']));
        }
        $order_id = (int) $order_id ?: (int) $ticket->order_id;
        $order    = $order_id && function_exists('wc_get_order') ? wc_get_order($order_id) : null;
        if ($order) {
            $rows[__('Order', 'gps-courses')] = esc_html('#' . $order->get_order_number());
        }

        $qr = !empty($ticket->qr_code_path) ? QRCodeGenerator::get_qr_code_url($ticket->qr_code_path) : '';

        $blocks = self::panel($f['title'], $rows)
                . self::code_box(__('Your ticket code', 'gps-courses'), (string) $ticket->ticket_code, __('Show this code at the front desk to check in.', 'gps-courses'), $qr);

        return [
            'to'     => $to,
            'vars'   => [
                'first_name' => $first,
                'course'     => $f['title'],
                'dates'      => $f['dates'],
                'ce_credits' => $f['credits'] > 0 ? Credits::format($f['credits']) : '',
            ],
            'blocks' => $blocks,
            'opts'   => ['button_url' => self::account_url('gps-tickets')],
        ];
    }

    /** Preview / test: the latest valid course ticket. Read-only. */
    public static function ticket_sample() {
        global $wpdb;
        $ticket = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}gps_tickets WHERE status = 'valid' ORDER BY id DESC LIMIT 1");
        return $ticket ? self::ticket_parts($ticket, (int) $ticket->order_id) : null;
    }

    /**
     * Send ticket confirmation email with QR code (hooked to gps_ticket_created;
     * also called by the Tickets admin "resend").
     */
    public static function send_ticket_email($ticket_id, $order_id) {
        $parts = self::ticket_parts((int) $ticket_id, (int) $order_id);
        if (!$parts) {
            error_log('GPS Courses: ticket email not sent for ticket #' . (int) $ticket_id . ' (ticket, course or attendee email missing).');
            return false;
        }
        if (!function_exists('wc_get_order') || !wc_get_order((int) $order_id)) {
            error_log('GPS Courses: ticket email not sent for ticket #' . (int) $ticket_id . ': order #' . (int) $order_id . ' not found.');
            return false;
        }

        $sent = self::send_parts('ticket', $parts);
        error_log('GPS Courses: ticket email for ticket #' . (int) $ticket_id . ($sent ? ' sent to ' : ' FAILED to ') . $parts['to']);
        return $sent;
    }

    /* ====================================================================
     * CE credits awarded (catalog key "credits")
     * ==================================================================== */

    /**
     * @return array|null ['to','vars','blocks','opts']
     */
    public static function credits_parts($user_id, $event_id, $credits) {
        $user = get_userdata((int) $user_id);
        if (!$user || !get_post((int) $event_id)) {
            return null;
        }
        $f     = self::course_facts((int) $event_id);
        $total = Credits::user_total((int) $user_id);

        $lines = [];
        $where = implode(' · ', array_filter([$f['dates'], $f['city']], 'strlen'));
        if ('' !== $where) {
            $lines[] = esc_html($where);
        }
        /* translators: %s: total CE credits */
        $lines[] = esc_html(sprintf(__('Your total: %s CE credits', 'gps-courses'), Credits::format($total)));

        $blocks = self::credits_badge(Credits::format($credits), 'CE', $f['title'], $lines)
                . self::paragraph(esc_html__('Your certificate of completion is attached to a separate email and is always available in your account.', 'gps-courses'));

        return [
            'to'     => $user->user_email,
            'vars'   => [
                'first_name'    => self::first_name($user),
                'course'        => $f['title'],
                'credits'       => Credits::format($credits),
                'total_credits' => Credits::format($total),
            ],
            'blocks' => $blocks,
            'opts'   => ['button_url' => self::account_url('gps-ce-credits')],
        ];
    }

    /** Preview / test: the latest course credits award. Read-only. */
    public static function credits_sample() {
        global $wpdb;
        $row = $wpdb->get_row(
            "SELECT l.user_id, l.event_id, l.credits FROM {$wpdb->prefix}gps_ce_ledger l
             INNER JOIN {$wpdb->posts} p ON p.ID = l.event_id AND p.post_type = 'gps_event'
             WHERE l.credits > 0 ORDER BY l.id DESC LIMIT 1"
        );
        return $row ? self::credits_parts((int) $row->user_id, (int) $row->event_id, (float) $row->credits) : null;
    }

    /**
     * Send CE credits awarded email (hooked to gps_credits_awarded).
     */
    public static function send_credits_email($user_id, $event_id, $credits) {
        return self::send_parts('credits', self::credits_parts($user_id, $event_id, $credits));
    }
}
