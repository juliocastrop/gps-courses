<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * GPS Courses › Emails: one hub for every email the site sends.
 *
 *   All emails  (gps-emails)          list, on/off, preview, "Send test to me";
 *                                    ?email=<key> opens one email: texts + live preview
 *   Brand       (gps-email-brand)     sender, logo, colors, address (every email)
 *   Schedule    (gps-email-schedule)  automatic seminar emails and upcoming sends
 *
 * The old screens (Email Branding, Email Templates, Seminar Notifications)
 * redirect here. Previews are built by the same code that sends each email
 * (Email_Catalog "sample" → the email's parts builder → Emails::render).
 */
class Email_Hub {

    const CAP          = 'manage_options';
    const OPT_OFFSETS  = 'gps_seminar_reminder_offsets';
    const OFFSETS      = [14, 7, 1];

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu'], 30);
        add_action('admin_init', [__CLASS__, 'redirect_old_screens'], 1);
        add_action('admin_init', [__CLASS__, 'register_brand_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);

        add_action('admin_post_gps_email_toggle', [__CLASS__, 'handle_toggle']);
        add_action('admin_post_gps_email_test', [__CLASS__, 'handle_test']);
        add_action('admin_post_gps_email_texts', [__CLASS__, 'handle_texts']);
        add_action('admin_post_gps_email_schedule', [__CLASS__, 'handle_schedule']);
        add_action('wp_ajax_gps_email_preview', [__CLASS__, 'ajax_preview']);
    }

    public static function menu() {
        add_submenu_page('gps-dashboard', __('All emails', 'gps-courses'), __('All emails', 'gps-courses'), self::CAP, 'gps-emails', [__CLASS__, 'render_all']);
        add_submenu_page('gps-dashboard', __('Email brand', 'gps-courses'), __('Email brand', 'gps-courses'), self::CAP, 'gps-email-brand', [__CLASS__, 'render_brand']);
        add_submenu_page('gps-dashboard', __('Email schedule', 'gps-courses'), __('Email schedule', 'gps-courses'), self::CAP, 'gps-email-schedule', [__CLASS__, 'render_schedule']);
    }

    /** Bookmarks and old links keep working. */
    public static function redirect_old_screens() {
        if (!isset($_GET['page']) || !current_user_can(self::CAP)) {
            return;
        }
        $map = [
            'gps-email-settings'        => 'gps-email-brand',
            'gps-email-templates'       => 'gps-emails',
            'gps-seminar-notifications' => 'gps-email-schedule',
        ];
        $page = sanitize_key(wp_unslash($_GET['page']));
        if (isset($map[$page])) {
            wp_safe_redirect(admin_url('admin.php?page=' . $map[$page]));
            exit;
        }
    }

    public static function assets($hook) {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (!in_array($page, ['gps-emails', 'gps-email-brand', 'gps-email-schedule'], true)) {
            return;
        }
        $css = GPSC_PATH . 'assets/css/admin-email-hub.css';
        $js  = GPSC_PATH . 'assets/js/admin-email-hub.js';
        wp_enqueue_style('gps-email-hub', GPSC_URL . 'assets/css/admin-email-hub.css', [], file_exists($css) ? (string) filemtime($css) : GPSC_VERSION);
        if ('gps-email-brand' === $page) {
            wp_enqueue_media();
            wp_enqueue_style('wp-color-picker');
        }
        wp_enqueue_script('gps-email-hub', GPSC_URL . 'assets/js/admin-email-hub.js', 'gps-email-brand' === $page ? ['jquery', 'wp-color-picker'] : ['jquery'], file_exists($js) ? (string) filemtime($js) : GPSC_VERSION, true);
        wp_localize_script('gps-email-hub', 'gpsEmailHub', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gps_email_preview'),
            'i18n'  => [
                'chooseLogo' => __('Choose the email logo', 'gps-courses'),
                'useLogo'    => __('Use this logo', 'gps-courses'),
                'svgWarn'    => __('SVG logos do not show in Gmail or Outlook. The white PNG logo is used instead.', 'gps-courses'),
            ],
        ]);
    }

    /* ============================================================
     * Shared bits
     * ============================================================ */

    protected static function check() {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('You do not have permission to do this.', 'gps-courses'));
        }
    }

    protected static function url($args = []) {
        return add_query_arg($args, admin_url('admin.php?page=gps-emails'));
    }

    protected static function badge($label, $tone) {
        return '<span class="gps-badge gps-badge--' . esc_attr($tone) . '">' . esc_html($label) . '</span>';
    }

    /** Notice after a redirect (?gps_msg=…). */
    protected static function notice() {
        if (empty($_GET['gps_msg'])) {
            return;
        }
        $msg = sanitize_key(wp_unslash($_GET['gps_msg']));
        $to  = isset($_GET['to']) ? sanitize_email(wp_unslash($_GET['to'])) : '';
        $map = [
            'test_sent'   => ['success', sprintf(/* translators: %s: email address */ __('Test sent to %s. Check your inbox (and spam folder).', 'gps-courses'), $to)],
            'test_failed' => ['error', __('The test could not be sent. Check FluentSMTP › Email Logs for the reason.', 'gps-courses')],
            'no_sample'   => ['warning', __('There is no real record to build this email from yet, so no test was sent.', 'gps-courses')],
            'saved'       => ['success', __('Texts saved.', 'gps-courses')],
            'reset'       => ['success', __('Default texts restored.', 'gps-courses')],
            'on'          => ['success', __('Email switched on.', 'gps-courses')],
            'off'         => ['success', __('Email switched off. It will not be sent until you switch it on again.', 'gps-courses')],
            'schedule'    => ['success', __('Schedule saved.', 'gps-courses')],
        ];
        if (isset($map[$msg])) {
            echo '<div class="notice notice-' . esc_attr($map[$msg][0]) . ' is-dismissible"><p>' . esc_html($map[$msg][1]) . '</p></div>';
        }
    }

    /**
     * The real email for a preview: sample parts with optional text overrides.
     * Returns ['subject', 'html', 'to'] or null.
     */
    protected static function preview($key, $texts = null) {
        $parts = Email_Catalog::sample_parts($key);
        if (!$parts) {
            return null;
        }
        $filter = null;
        if (null !== $texts) {
            // Unsaved edits: render with the typed texts
            $filter = function ($value) use ($key, $texts) {
                $value = is_array($value) ? $value : [];
                $value[$key] = $texts;
                return $value;
            };
            add_filter('option_' . Email_Catalog::OPT_TEXTS, $filter);
            add_filter('default_option_' . Email_Catalog::OPT_TEXTS, $filter);
        }
        $mail = Emails::render($key, (array) $parts['vars'], (string) $parts['blocks'], (array) $parts['opts']);
        if ($filter) {
            remove_filter('option_' . Email_Catalog::OPT_TEXTS, $filter);
            remove_filter('default_option_' . Email_Catalog::OPT_TEXTS, $filter);
        }
        return $mail + ['to' => implode(', ', (array) $parts['to'])];
    }

    /* ============================================================
     * All emails
     * ============================================================ */

    public static function render_all() {
        self::check();
        if (!empty($_GET['email'])) {
            self::render_detail(sanitize_key(wp_unslash($_GET['email'])));
            return;
        }
        $all   = Email_Catalog::all();
        $group = isset($_GET['group']) ? sanitize_key(wp_unslash($_GET['group'])) : '';
        $counts = array_fill_keys(Email_Catalog::GROUPS, 0);
        $off = 0;
        foreach ($all as $key => $def) {
            $counts[$def['group']]++;
            if (!Email_Catalog::enabled($key)) {
                $off++;
            }
        }
        ?>
        <div class="wrap gps-email-hub">
            <h1 class="wp-heading-inline"><?php esc_html_e('All emails', 'gps-courses'); ?></h1>
            <p class="gps-email-hub__lead">
                <?php
                printf(
                    /* translators: 1: sender name, 2: sender email */
                    esc_html__('Every email the site sends, in the GPS design. Sent from %1$s <%2$s>.', 'gps-courses'),
                    esc_html(Emails::brand('from_name')),
                    esc_html(Emails::brand('from_email'))
                );
                ?>
            </p>
            <hr class="wp-header-end">
            <?php self::notice(); ?>
            <ul class="subsubsub">
                <li><a href="<?php echo esc_url(self::url()); ?>"<?php echo '' === $group ? ' class="current" aria-current="page"' : ''; ?>><?php esc_html_e('All', 'gps-courses'); ?> <span class="count">(<?php echo (int) count($all); ?>)</span></a></li>
                <?php foreach (Email_Catalog::GROUPS as $g) : ?>
                    <li>| <a href="<?php echo esc_url(self::url(['group' => $g])); ?>"<?php echo $g === $group ? ' class="current" aria-current="page"' : ''; ?>><?php echo esc_html(Email_Catalog::group_label($g)); ?> <span class="count">(<?php echo (int) $counts[$g]; ?>)</span></a></li>
                <?php endforeach; ?>
                <?php if ($off) : ?>
                    <li>| <a href="<?php echo esc_url(self::url(['group' => 'off'])); ?>"<?php echo 'off' === $group ? ' class="current" aria-current="page"' : ''; ?>><?php esc_html_e('Off', 'gps-courses'); ?> <span class="count">(<?php echo (int) $off; ?>)</span></a></li>
                <?php endif; ?>
            </ul>
            <div class="gps-email-table-wrap">
            <table class="widefat striped gps-email-table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Email', 'gps-courses'); ?></th>
                        <th scope="col"><?php esc_html_e('Sent when', 'gps-courses'); ?></th>
                        <th scope="col"><?php esc_html_e('To', 'gps-courses'); ?></th>
                        <th scope="col"><?php esc_html_e('Status', 'gps-courses'); ?></th>
                        <th scope="col"><?php esc_html_e('Last sent', 'gps-courses'); ?></th>
                        <th scope="col"><span class="screen-reader-text"><?php esc_html_e('Actions', 'gps-courses'); ?></span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($all as $key => $def) :
                    $on = Email_Catalog::enabled($key);
                    if ('' !== $group && ('off' === $group ? $on : $def['group'] !== $group)) {
                        continue;
                    }
                    $last = Email_Catalog::last_sent($key);
                    ?>
                    <tr>
                        <td class="gps-email-table__name">
                            <?php if ($def['editable']) : ?>
                                <a href="<?php echo esc_url(self::url(['email' => $key])); ?>"><strong><?php echo esc_html($def['name']); ?></strong></a>
                            <?php else : ?>
                                <strong><?php echo esc_html($def['name']); ?></strong>
                            <?php endif; ?>
                            <span class="gps-sub"><?php echo esc_html(Email_Catalog::group_label($def['group'])); ?></span>
                        </td>
                        <td><?php echo esc_html($def['when']); ?></td>
                        <td><?php echo esc_html($def['to']); ?></td>
                        <td>
                            <?php
                            if (!$def['editable'] && $def['manage_url']) {
                                echo self::badge(__('Managed elsewhere', 'gps-courses'), 'info');
                            } elseif (!$def['can_disable']) {
                                echo self::badge(__('Always on', 'gps-courses'), 'ok');
                            } else {
                                echo $on ? self::badge(__('On', 'gps-courses'), 'ok') : self::badge(__('Off', 'gps-courses'), 'neutral');
                            }
                            ?>
                        </td>
                        <td><?php echo $last ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last))) : '<span class="gps-muted">' . esc_html__('Not since this screen exists', 'gps-courses') . '</span>'; ?></td>
                        <td class="gps-email-table__actions">
                            <?php if ($def['editable']) : ?>
                                <a class="button" href="<?php echo esc_url(self::url(['email' => $key])); ?>"><?php esc_html_e('Preview & edit', 'gps-courses'); ?></a>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <?php wp_nonce_field('gps_email_test_' . $key); ?>
                                    <input type="hidden" name="action" value="gps_email_test">
                                    <input type="hidden" name="email" value="<?php echo esc_attr($key); ?>">
                                    <button type="submit" class="button-link"><?php esc_html_e('Send test to me', 'gps-courses'); ?></button>
                                </form>
                                <?php if ($def['can_disable']) : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <?php wp_nonce_field('gps_email_toggle_' . $key); ?>
                                        <input type="hidden" name="action" value="gps_email_toggle">
                                        <input type="hidden" name="email" value="<?php echo esc_attr($key); ?>">
                                        <input type="hidden" name="on" value="<?php echo $on ? '0' : '1'; ?>">
                                        <button type="submit" class="button-link<?php echo $on ? ' gps-danger-link' : ''; ?>"><?php echo $on ? esc_html__('Switch off', 'gps-courses') : esc_html__('Switch on', 'gps-courses'); ?></button>
                                    </form>
                                <?php endif; ?>
                            <?php elseif ($def['manage_url']) : ?>
                                <a class="button" href="<?php echo esc_url(admin_url($def['manage_url'])); ?>"><?php esc_html_e('Open', 'gps-courses'); ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php
    }

    /* ============================================================
     * One email: texts + live preview
     * ============================================================ */

    protected static function render_detail($key) {
        $def = Email_Catalog::get($key);
        if (!$def || !$def['editable']) {
            echo '<div class="wrap"><h1>' . esc_html__('Email not found', 'gps-courses') . '</h1><p><a href="' . esc_url(self::url()) . '">' . esc_html__('Back to all emails', 'gps-courses') . '</a></p></div>';
            return;
        }
        $texts   = Email_Catalog::texts($key);
        $on      = Email_Catalog::enabled($key);
        $preview = self::preview($key);
        $user    = wp_get_current_user();
        ?>
        <div class="wrap gps-email-hub">
            <p class="gps-email-back"><a href="<?php echo esc_url(self::url()); ?>">&larr; <?php esc_html_e('All emails', 'gps-courses'); ?></a></p>
            <h1 class="wp-heading-inline"><?php echo esc_html($def['name']); ?></h1>
            <?php
            if (!$def['can_disable']) {
                echo self::badge(__('Always on', 'gps-courses'), 'ok');
            } else {
                echo $on ? self::badge(__('On', 'gps-courses'), 'ok') : self::badge(__('Off', 'gps-courses'), 'neutral');
            }
            ?>
            <p class="gps-email-hub__lead">
                <?php
                printf(
                    /* translators: 1: when, 2: recipient */
                    esc_html__('Sent when: %1$s. To: %2$s.', 'gps-courses'),
                    esc_html($def['when']),
                    esc_html($def['to'])
                );
                ?>
            </p>
            <hr class="wp-header-end">
            <?php self::notice(); ?>
            <div class="gps-email-detail">
                <form class="gps-email-detail__texts gps-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-email="<?php echo esc_attr($key); ?>">
                    <h2><?php esc_html_e('Texts', 'gps-courses'); ?></h2>
                    <?php wp_nonce_field('gps_email_texts_' . $key); ?>
                    <input type="hidden" name="action" value="gps_email_texts">
                    <input type="hidden" name="email" value="<?php echo esc_attr($key); ?>">
                    <?php
                    $fields = [
                        'subject' => __('Subject', 'gps-courses'),
                        'heading' => __('Heading', 'gps-courses'),
                        'intro'   => __('Intro', 'gps-courses'),
                        'button'  => __('Button label', 'gps-courses'),
                    ];
                    foreach ($fields as $field => $label) :
                        if ('button' === $field && '' === $def['texts']['button']) {
                            continue; // this email has no button
                        }
                        $id = 'gps-email-' . $field;
                        ?>
                        <p class="gps-field">
                            <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
                            <?php if ('intro' === $field) : ?>
                                <textarea id="<?php echo esc_attr($id); ?>" name="texts[intro]" rows="5" class="large-text gps-email-input"><?php echo esc_textarea($texts['intro']); ?></textarea>
                                <span class="description"><?php esc_html_e('Leave a blank line to start a new paragraph.', 'gps-courses'); ?></span>
                            <?php else : ?>
                                <input id="<?php echo esc_attr($id); ?>" type="text" name="texts[<?php echo esc_attr($field); ?>]" value="<?php echo esc_attr($texts[$field]); ?>" class="large-text gps-email-input">
                            <?php endif; ?>
                        </p>
                    <?php endforeach; ?>
                    <?php if ($def['placeholders']) : ?>
                        <div class="gps-field">
                            <span class="gps-field__label" id="gps-email-insert-label"><?php esc_html_e('Insert in the last field you edited', 'gps-courses'); ?></span>
                            <div class="gps-chips" role="group" aria-labelledby="gps-email-insert-label">
                                <?php foreach ($def['placeholders'] as $ph) : ?>
                                    <button type="button" class="gps-chip" data-insert="<?php echo esc_attr('{' . $ph . '}'); ?>"><?php echo esc_html('{' . $ph . '}'); ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <p class="description"><?php esc_html_e('Course details, codes and dates are filled in automatically. Logo, colors and footer come from Brand.', 'gps-courses'); ?></p>
                    <div class="gps-email-detail__buttons">
                        <button type="submit" name="do" value="save" class="button button-primary"><?php esc_html_e('Save texts', 'gps-courses'); ?></button>
                        <button type="submit" name="do" value="reset" class="button-link gps-muted-link"><?php esc_html_e('Restore default texts', 'gps-courses'); ?></button>
                    </div>
                </form>
                <section class="gps-email-detail__preview gps-card" aria-label="<?php esc_attr_e('Preview', 'gps-courses'); ?>">
                    <div class="gps-email-preview__bar">
                        <div class="gps-email-preview__meta">
                            <?php if ($preview) : ?>
                                <span><?php esc_html_e('Subject:', 'gps-courses'); ?> <strong class="gps-email-preview__subject"><?php echo esc_html($preview['subject']); ?></strong></span>
                                <span class="gps-muted"><?php printf(esc_html__('From: %s', 'gps-courses'), esc_html(Emails::brand('from_name') . ' <' . Emails::brand('from_email') . '>')); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="gps-segmented" role="group" aria-label="<?php esc_attr_e('Preview width', 'gps-courses'); ?>">
                            <button type="button" class="is-on" data-width="700" aria-pressed="true"><?php esc_html_e('Desktop', 'gps-courses'); ?></button>
                            <button type="button" data-width="390" aria-pressed="false"><?php esc_html_e('Mobile', 'gps-courses'); ?></button>
                        </div>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('gps_email_test_' . $key); ?>
                            <input type="hidden" name="action" value="gps_email_test">
                            <input type="hidden" name="email" value="<?php echo esc_attr($key); ?>">
                            <input type="hidden" name="back" value="detail">
                            <button type="submit" class="button"><?php printf(esc_html__('Send test to %s', 'gps-courses'), esc_html($user->user_email)); ?></button>
                        </form>
                    </div>
                    <?php if ($preview) : ?>
                        <p class="gps-email-preview__note gps-muted"><?php esc_html_e('Built from the latest real record, with the texts as typed (save to keep them).', 'gps-courses'); ?></p>
                        <div class="gps-email-preview__stage"><iframe class="gps-email-preview__frame" title="<?php esc_attr_e('Email preview', 'gps-courses'); ?>" sandbox="" srcdoc="<?php echo esc_attr($preview['html']); ?>"></iframe></div>
                    <?php else : ?>
                        <div class="gps-email-preview__empty">
                            <p><strong><?php esc_html_e('No preview yet', 'gps-courses'); ?></strong></p>
                            <p><?php esc_html_e('This email is built from a real record (an order, a registration, a session…). There is none yet, so it cannot be previewed or tested.', 'gps-courses'); ?></p>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
        <?php
    }

    /** Live preview while typing texts, or while editing Brand. */
    public static function ajax_preview() {
        check_ajax_referer('gps_email_preview', 'nonce');
        if (!current_user_can(self::CAP)) {
            wp_send_json_error([], 403);
        }
        $key = isset($_POST['email']) ? sanitize_key(wp_unslash($_POST['email'])) : '';
        $def = Email_Catalog::get($key);
        if (!$def) {
            wp_send_json_error(['message' => __('Unknown email.', 'gps-courses')]);
        }

        $texts = null;
        if (isset($_POST['texts']) && is_array($_POST['texts'])) {
            $texts = [];
            foreach (['subject', 'heading', 'intro', 'button'] as $f) {
                if (isset($_POST['texts'][$f])) {
                    $raw = wp_unslash($_POST['texts'][$f]);
                    $texts[$f] = 'intro' === $f ? sanitize_textarea_field($raw) : sanitize_text_field($raw);
                }
            }
        }

        // Unsaved Brand values: override the options for this render only
        $brand_filters = [];
        if (isset($_POST['brand']) && is_array($_POST['brand'])) {
            foreach (self::brand_fields() as $option => $field) {
                if (!isset($_POST['brand'][$option])) {
                    continue;
                }
                $value = call_user_func($field['sanitize'], wp_unslash($_POST['brand'][$option]));
                $cb = function () use ($value) {
                    return $value;
                };
                add_filter('pre_option_' . $option, $cb);
                $brand_filters[$option] = $cb;
            }
        }

        $mail = self::preview($key, $texts);

        foreach ($brand_filters as $option => $cb) {
            remove_filter('pre_option_' . $option, $cb);
        }
        if (!$mail) {
            wp_send_json_error(['message' => __('No real record to preview yet.', 'gps-courses')]);
        }
        wp_send_json_success(['subject' => $mail['subject'], 'html' => $mail['html']]);
    }

    public static function handle_texts() {
        self::check();
        $key = isset($_POST['email']) ? sanitize_key(wp_unslash($_POST['email'])) : '';
        check_admin_referer('gps_email_texts_' . $key);
        $msg = 'saved';
        if (isset($_POST['do']) && 'reset' === $_POST['do']) {
            Email_Catalog::reset_texts($key);
            $msg = 'reset';
        } elseif (!Email_Catalog::save_texts($key, isset($_POST['texts']) && is_array($_POST['texts']) ? wp_unslash($_POST['texts']) : [])) {
            wp_safe_redirect(self::url());
            exit;
        }
        wp_safe_redirect(self::url(['email' => $key, 'gps_msg' => $msg]));
        exit;
    }

    public static function handle_toggle() {
        self::check();
        $key = isset($_POST['email']) ? sanitize_key(wp_unslash($_POST['email'])) : '';
        check_admin_referer('gps_email_toggle_' . $key);
        $on = !empty($_POST['on']);
        if (!Email_Catalog::set_enabled($key, $on)) {
            wp_safe_redirect(self::url());
            exit;
        }
        // Switching reminders on with no day ticked in Schedule: tick all three again
        if ($on && 'seminar_reminder' === $key && !self::reminder_offsets()) {
            update_option(self::OPT_OFFSETS, self::OFFSETS, false);
        }
        wp_safe_redirect(self::url(['gps_msg' => $on ? 'on' : 'off']));
        exit;
    }

    /** "Send test to me": the real email for the latest record, to the admin. */
    public static function handle_test() {
        self::check();
        $key = isset($_POST['email']) ? sanitize_key(wp_unslash($_POST['email'])) : '';
        check_admin_referer('gps_email_test_' . $key);
        $back  = isset($_POST['back']) && 'detail' === $_POST['back'] ? ['email' => $key] : [];
        $parts = Email_Catalog::sample_parts($key);
        if (!$parts) {
            wp_safe_redirect(self::url($back + ['gps_msg' => 'no_sample']));
            exit;
        }
        $me   = wp_get_current_user()->user_email;
        $opts = (array) $parts['opts'];
        $opts['force'] = true;
        $opts['test']  = true;
        unset($opts['reply_to']);
        $opts['headers'] = []; // never Bcc / Cc the real recipients of the sample
        $mail = Emails::render($key, (array) $parts['vars'], (string) $parts['blocks'], $opts);
        $opts['subject'] = '[TEST] ' . $mail['subject'];
        $ok = Emails::send($key, $me, (array) $parts['vars'], (string) $parts['blocks'], $opts);
        wp_safe_redirect(self::url($back + ['gps_msg' => $ok ? 'test_sent' : 'test_failed', 'to' => $me]));
        exit;
    }

    /* ============================================================
     * Brand
     * ============================================================ */

    /** option => [label, type, sanitize, help] */
    protected static function brand_fields() {
        $hex = function ($v) {
            return sanitize_hex_color(trim((string) $v)) ?: '';
        };
        return [
            'gps_email_from_name'         => ['label' => __('From name', 'gps-courses'), 'type' => 'text', 'sanitize' => 'sanitize_text_field', 'section' => 'sender'],
            'gps_email_from_email'        => ['label' => __('From email', 'gps-courses'), 'type' => 'email', 'sanitize' => 'sanitize_email', 'section' => 'sender'],
            'gps_email_logo'              => ['label' => __('Logo', 'gps-courses'), 'type' => 'logo', 'sanitize' => 'esc_url_raw', 'section' => 'logo'],
            'gps_email_header_bg_color'   => ['label' => __('Header', 'gps-courses'), 'help' => __('Band behind the logo and title', 'gps-courses'), 'type' => 'color', 'sanitize' => $hex, 'section' => 'colors', 'default' => '#0d153f'],
            'gps_email_accent_color'      => ['label' => __('Accent line', 'gps-courses'), 'help' => __('Thin line under the header, credit badges', 'gps-courses'), 'type' => 'color', 'sanitize' => $hex, 'section' => 'colors', 'default' => '#DDC89D'],
            'gps_email_button_bg_color'   => ['label' => __('Buttons and links', 'gps-courses'), 'help' => __('Main button, links, progress bars', 'gps-courses'), 'type' => 'color', 'sanitize' => $hex, 'section' => 'colors', 'default' => '#0B52AC'],
            'gps_email_panel_bg_color'    => ['label' => __('Info panels', 'gps-courses'), 'help' => __('Course, ticket and session details', 'gps-courses'), 'type' => 'color', 'sanitize' => $hex, 'section' => 'colors', 'default' => '#f4f7fc'],
            'gps_email_footer_address'    => ['label' => __('Address', 'gps-courses'), 'type' => 'text', 'sanitize' => 'sanitize_text_field', 'section' => 'footer'],
        ];
    }

    public static function register_brand_settings() {
        foreach (self::brand_fields() as $option => $field) {
            register_setting('gps_email_brand', $option, ['sanitize_callback' => $field['sanitize']]);
        }
    }

    public static function render_brand() {
        self::check();
        $fields   = self::brand_fields();
        $sections = [
            'sender' => [__('Sender', 'gps-courses'), __('Every email is sent from this name and address, and replies go to it.', 'gps-courses')],
            'logo'   => [__('Logo', 'gps-courses'), ''],
            'colors' => [__('Colors', 'gps-courses'), ''],
            'footer' => [__('Footer', 'gps-courses'), ''],
        ];
        $samples = [];
        foreach (Email_Catalog::all() as $key => $def) {
            if ($def['editable'] && $def['sample']) {
                $samples[$key] = $def['name'];
            }
        }
        $first   = isset($samples['credits']) ? 'credits' : key($samples);
        $preview = $first ? self::preview($first) : null;
        $logo    = (string) get_option('gps_email_logo', '');
        ?>
        <div class="wrap gps-email-hub">
            <form method="post" action="options.php" class="gps-email-brand" id="gps-email-brand">
                <div class="gps-email-brand__head">
                    <h1 class="wp-heading-inline"><?php esc_html_e('Brand', 'gps-courses'); ?></h1>
                    <span class="gps-email-hub__lead"><?php esc_html_e('Applies to every email the site sends.', 'gps-courses'); ?></span>
                    <?php submit_button(__('Save changes', 'gps-courses'), 'primary', 'submit', false); ?>
                </div>
                <hr class="wp-header-end">
                <?php settings_errors(); ?>
                <?php settings_fields('gps_email_brand'); ?>
                <div class="gps-email-detail">
                    <div class="gps-email-brand__fields">
                        <?php foreach ($sections as $section => $info) : ?>
                            <div class="gps-card">
                                <h2><?php echo esc_html($info[0]); ?></h2>
                                <?php if ($info[1]) : ?><p class="description"><?php echo esc_html($info[1]); ?></p><?php endif; ?>
                                <?php foreach ($fields as $option => $field) :
                                    if ($field['section'] !== $section) {
                                        continue;
                                    }
                                    $value = (string) get_option($option, '');
                                    $id    = 'gps-brand-' . $option;
                                    if ('logo' === $field['type']) : ?>
                                        <div class="gps-logo-field">
                                            <div class="gps-logo-field__swatch" style="background-color: <?php echo esc_attr(Emails::brand('header_bg')); ?>"><img src="<?php echo esc_url(Emails::frame_logo()); ?>" alt="<?php esc_attr_e('Logo as emails show it', 'gps-courses'); ?>"></div>
                                            <input type="hidden" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($option); ?>" value="<?php echo esc_attr($value); ?>" data-brand>
                                            <button type="button" class="button gps-logo-pick"><?php esc_html_e('Change logo', 'gps-courses'); ?></button>
                                        </div>
                                        <p class="gps-logo-note description" data-svg="<?php echo preg_match('/\.svg(\?|$)/i', $logo) ? '1' : '0'; ?>">
                                            <?php
                                            echo preg_match('/\.svg(\?|$)/i', $logo)
                                                ? esc_html__('SVG logos do not show in Gmail or Outlook. The white PNG logo is used instead.', 'gps-courses')
                                                : esc_html__('Use a light (white) logo in PNG: it sits on the header color.', 'gps-courses');
                                            ?>
                                        </p>
                                    <?php elseif ('color' === $field['type']) : ?>
                                        <div class="gps-color-row">
                                            <label for="<?php echo esc_attr($id); ?>"><strong><?php echo esc_html($field['label']); ?></strong><span class="gps-sub"><?php echo esc_html($field['help']); ?></span></label>
                                            <input type="text" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($option); ?>" value="<?php echo esc_attr($value ?: $field['default']); ?>" class="gps-color" data-default-color="<?php echo esc_attr($field['default']); ?>" data-brand>
                                        </div>
                                    <?php else : ?>
                                        <p class="gps-field">
                                            <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($field['label']); ?></label>
                                            <input type="<?php echo esc_attr($field['type']); ?>" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($option); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text" data-brand>
                                        </p>
                                    <?php endif;
                                endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <section class="gps-email-detail__preview gps-card" aria-label="<?php esc_attr_e('Live preview', 'gps-courses'); ?>">
                        <div class="gps-email-preview__bar">
                            <label class="gps-email-preview__pick"><?php esc_html_e('Preview', 'gps-courses'); ?>
                                <select id="gps-brand-preview-email">
                                    <?php foreach ($samples as $key => $name) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($key, $first); ?>><?php echo esc_html($name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <div class="gps-segmented" role="group" aria-label="<?php esc_attr_e('Preview width', 'gps-courses'); ?>">
                                <button type="button" class="is-on" data-width="700" aria-pressed="true"><?php esc_html_e('Desktop', 'gps-courses'); ?></button>
                                <button type="button" data-width="390" aria-pressed="false"><?php esc_html_e('Mobile', 'gps-courses'); ?></button>
                            </div>
                            <span class="gps-muted"><?php esc_html_e('Updates as you edit', 'gps-courses'); ?></span>
                        </div>
                        <?php if ($preview) : ?>
                            <div class="gps-email-preview__stage"><iframe class="gps-email-preview__frame" title="<?php esc_attr_e('Email preview', 'gps-courses'); ?>" sandbox="" srcdoc="<?php echo esc_attr($preview['html']); ?>"></iframe></div>
                        <?php else : ?>
                            <div class="gps-email-preview__empty"><p><?php esc_html_e('No real record to preview yet.', 'gps-courses'); ?></p></div>
                        <?php endif; ?>
                    </section>
                </div>
            </form>
        </div>
        <?php
    }

    /* ============================================================
     * Schedule
     * ============================================================ */

    public static function reminder_offsets() {
        $saved = get_option(self::OPT_OFFSETS, self::OFFSETS);
        return array_values(array_intersect(self::OFFSETS, array_map('intval', (array) $saved)));
    }

    public static function render_schedule() {
        self::check();
        global $wpdb;
        $offsets   = self::reminder_offsets();
        $next_cron = wp_next_scheduled('gps_seminar_daily_cron');
        $tf        = get_option('date_format') . ' ' . get_option('time_format');

        // Upcoming sessions (next 45 days) and the reminders they will get
        $today    = current_time('Y-m-d');
        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.id, s.seminar_id, s.session_number, s.session_date, s.topic,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}gps_seminar_registrations r WHERE r.seminar_id = s.seminar_id AND r.status = 'active') AS students
             FROM {$wpdb->prefix}gps_seminar_sessions s
             WHERE s.session_date BETWEEN %s AND DATE_ADD(%s, INTERVAL 45 DAY)
             ORDER BY s.session_date ASC",
            $today,
            $today
        ));
        $upcoming = [];
        foreach ((array) $sessions as $s) {
            foreach ($offsets as $days) {
                $send = gmdate('Y-m-d', strtotime($s->session_date . ' -' . (int) $days . ' days'));
                if ($send >= $today) {
                    $upcoming[] = [
                        'date'  => $send,
                        /* translators: %d: days */
                        'email' => sprintf(_n('Reminder %d day before', 'Reminder %d days before', $days, 'gps-courses'), $days),
                        /* translators: 1: session number, 2: topic */
                        'for'   => trim(sprintf(__('Session %1$d · %2$s', 'gps-courses'), (int) $s->session_number, (string) $s->topic), ' ·'),
                        'n'     => (int) $s->students,
                    ];
                }
            }
        }
        usort($upcoming, function ($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        $next_cert = self::next_certificate_date();
        ?>
        <div class="wrap gps-email-hub">
            <h1 class="wp-heading-inline"><?php esc_html_e('Schedule', 'gps-courses'); ?></h1>
            <p class="gps-email-hub__lead"><?php esc_html_e('Emails the site sends on its own, by date.', 'gps-courses'); ?></p>
            <hr class="wp-header-end">
            <?php self::notice(); ?>
            <div class="gps-email-stats">
                <div class="gps-card">
                    <span class="gps-muted"><?php esc_html_e('Daily check', 'gps-courses'); ?></span>
                    <?php if ($next_cron) : ?>
                        <strong class="gps-dot gps-dot--ok"><?php esc_html_e('Scheduled', 'gps-courses'); ?></strong>
                        <span class="gps-muted"><?php printf(esc_html__('Next run %s', 'gps-courses'), esc_html(get_date_from_gmt(gmdate('Y-m-d H:i:s', $next_cron), $tf))); ?></span>
                    <?php else : ?>
                        <strong class="gps-dot gps-dot--bad"><?php esc_html_e('Not scheduled', 'gps-courses'); ?></strong>
                        <span class="gps-muted"><?php esc_html_e('It is scheduled again on the next page load.', 'gps-courses'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="gps-card">
                    <span class="gps-muted"><?php esc_html_e('Next 45 days', 'gps-courses'); ?></span>
                    <strong><?php printf(esc_html(_n('%d reminder send', '%d reminder sends', count($upcoming), 'gps-courses')), count($upcoming)); ?></strong>
                    <span class="gps-muted"><?php esc_html_e('One send emails every active student of the seminar.', 'gps-courses'); ?></span>
                </div>
                <div class="gps-card">
                    <span class="gps-muted"><?php esc_html_e('Seminar certificates', 'gps-courses'); ?></span>
                    <strong><?php echo esc_html($next_cert ? date_i18n(get_option('date_format'), strtotime($next_cert)) : '—'); ?></strong>
                    <span class="gps-muted"><?php esc_html_e('Sent twice a year: June 30 and December 31.', 'gps-courses'); ?></span>
                </div>
            </div>
            <div class="gps-email-detail">
                <form class="gps-card gps-email-schedule__rules" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <h2><?php esc_html_e('Automatic emails', 'gps-courses'); ?></h2>
                    <?php wp_nonce_field('gps_email_schedule'); ?>
                    <input type="hidden" name="action" value="gps_email_schedule">
                    <?php foreach (self::OFFSETS as $days) : ?>
                        <label class="gps-rule">
                            <input type="checkbox" name="offsets[]" value="<?php echo (int) $days; ?>" <?php checked(in_array($days, $offsets, true)); ?>>
                            <span><?php printf(esc_html(_n('Reminder %d day before', 'Reminder %d days before', $days, 'gps-courses')), (int) $days); ?><span class="gps-sub"><?php esc_html_e('Each seminar session', 'gps-courses'); ?></span></span>
                            <a href="<?php echo esc_url(self::url(['email' => 'seminar_reminder'])); ?>"><?php esc_html_e('Edit texts', 'gps-courses'); ?></a>
                        </label>
                    <?php endforeach; ?>
                    <?php
                    foreach (['seminar_missed', 'interest_new_dates'] as $key) :
                        $def = Email_Catalog::get($key);
                        if (!$def) {
                            continue;
                        }
                        ?>
                        <label class="gps-rule">
                            <input type="checkbox" name="emails[]" value="<?php echo esc_attr($key); ?>" <?php checked(Email_Catalog::enabled($key)); ?>>
                            <span><?php echo esc_html($def['name']); ?><span class="gps-sub"><?php echo esc_html($def['when']); ?></span></span>
                            <a href="<?php echo esc_url(self::url(['email' => $key])); ?>"><?php esc_html_e('Edit texts', 'gps-courses'); ?></a>
                        </label>
                    <?php endforeach; ?>
                    <label class="gps-rule gps-rule--fixed">
                        <input type="checkbox" checked disabled>
                        <span><?php esc_html_e('Seminar certificates', 'gps-courses'); ?><span class="gps-sub"><?php esc_html_e('June 30 and December 31 (always on)', 'gps-courses'); ?></span></span>
                        <a href="<?php echo esc_url(self::url(['email' => 'seminar_certificate'])); ?>"><?php esc_html_e('Edit texts', 'gps-courses'); ?></a>
                    </label>
                    <p><?php submit_button(__('Save schedule', 'gps-courses'), 'primary', 'submit', false); ?></p>
                </form>
                <section class="gps-card gps-email-schedule__upcoming" aria-labelledby="gps-upcoming-title">
                    <h2 id="gps-upcoming-title"><?php esc_html_e('Upcoming reminder sends', 'gps-courses'); ?></h2>
                    <?php if (!$upcoming) : ?>
                        <p class="gps-muted"><?php esc_html_e('No seminar sessions in the next 45 days.', 'gps-courses'); ?></p>
                    <?php else : ?>
                        <div class="gps-email-table-wrap">
                        <table class="widefat striped">
                            <thead><tr><th scope="col"><?php esc_html_e('Date', 'gps-courses'); ?></th><th scope="col"><?php esc_html_e('Email', 'gps-courses'); ?></th><th scope="col"><?php esc_html_e('For', 'gps-courses'); ?></th><th scope="col" class="num"><?php esc_html_e('Students', 'gps-courses'); ?></th></tr></thead>
                            <tbody>
                                <?php foreach (array_slice($upcoming, 0, 20) as $u) : ?>
                                    <tr><td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($u['date']))); ?></td><td><?php echo esc_html($u['email']); ?></td><td><?php echo esc_html($u['for']); ?></td><td class="num"><?php echo (int) $u['n']; ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
        <?php
    }

    protected static function next_certificate_date() {
        $y = (int) current_time('Y');
        $today = current_time('Y-m-d');
        foreach ([$y . '-06-30', $y . '-12-31', ($y + 1) . '-06-30'] as $d) {
            if ($d >= $today) {
                return $d;
            }
        }
        return '';
    }

    public static function handle_schedule() {
        self::check();
        check_admin_referer('gps_email_schedule');
        $offsets = isset($_POST['offsets']) ? array_map('intval', (array) $_POST['offsets']) : [];
        $offsets = array_values(array_intersect(self::OFFSETS, $offsets));
        update_option(self::OPT_OFFSETS, $offsets, false);
        $on = isset($_POST['emails']) ? array_map('sanitize_key', (array) $_POST['emails']) : [];
        foreach (['seminar_missed', 'interest_new_dates'] as $key) {
            Email_Catalog::set_enabled($key, in_array($key, $on, true));
        }
        // All three reminders off = the reminder email is off
        Email_Catalog::set_enabled('seminar_reminder', (bool) $offsets);
        wp_safe_redirect(add_query_arg(['gps_msg' => 'schedule'], admin_url('admin.php?page=gps-email-schedule')));
        exit;
    }
}
