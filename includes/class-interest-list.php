<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * "Next dates" interest list: people who want to hear when a course that has
 * taken place (or whose registration has not opened yet) gets new dates.
 *
 * Stored in {prefix}gps_waitlist with ticket_type_id = 0 (no schema change);
 * the sold-out waitlist always has a real ticket ID, and Waitlist skips these
 * rows. Status: waiting -> notified (or failed when wp_mail fails).
 *
 * When a course is 'open' (Course_Stage), its waiting rows are emailed once,
 * plus the waiting rows of past / in-progress editions of the same program
 * (they want the next date of the same course). Triggered by saving a course
 * or ticket, a ticket's sale starting, a program change, and an hourly cron.
 */
class Interest_List {

    /** ticket_type_id that marks a "next dates" row */
    const TICKET = 0;

    /** Emails per run */
    const BATCH = 20;

    /** Automatic attempts before a failed row waits for an admin "Resend" */
    const MAX_ATTEMPTS = 3;

    /** Post meta: when the course was first seen open (Y-m-d H:i:s, site time) */
    const META_OPEN_SINCE = '_gpsc_open_since';

    const CRON_HOURLY = 'gpsc_interest_notify_hourly';
    const CRON_COURSE = 'gpsc_interest_notify_course';

    const NONCE = 'gpsc_interest';

    public static function init() {
        // Front-end form (logged in or not)
        add_action('wp_ajax_gpsc_interest_join', [__CLASS__, 'ajax_join']);
        add_action('wp_ajax_nopriv_gpsc_interest_join', [__CLASS__, 'ajax_join']);
        // Fresh nonce for forms on cached pages (FlyingPress serves the HTML for hours)
        add_action('wp_ajax_gpsc_form_nonce', [__CLASS__, 'ajax_nonce']);
        add_action('wp_ajax_nopriv_gpsc_form_nonce', [__CLASS__, 'ajax_nonce']);

        // Triggers: a course can become 'open'
        add_action('save_post_gps_event', [__CLASS__, 'on_save_event'], 30, 2);
        add_action('save_post_gps_ticket', [__CLASS__, 'on_save_ticket'], 30);
        add_action('gpsc_ticket_activated', [__CLASS__, 'on_ticket_activated'], 10, 2);
        add_action('gpsc_course_program_changed', [__CLASS__, 'queue']);

        add_action(self::CRON_COURSE, [__CLASS__, 'notify_for_course']);
        add_action(self::CRON_HOURLY, [__CLASS__, 'run']);
        add_action('init', [__CLASS__, 'schedule_hourly']);
    }

    public static function schedule_hourly() {
        if (!wp_next_scheduled(self::CRON_HOURLY)) {
            wp_schedule_event(time() + 300, 'hourly', self::CRON_HOURLY);
        }
    }

    protected static function table() {
        global $wpdb;
        return $wpdb->prefix . 'gps_waitlist';
    }

    /* ============================================================
     * Sign-up
     * ============================================================ */

    /**
     * Add someone to a course's "next dates" list.
     *
     * Accepts courses that are past, in progress or not yet open. An open
     * course is registered for directly; a sold-out one has its own waitlist.
     *
     * @param array $data event_id, email (required); first_name, last_name, user_id
     * @return array|\WP_Error ['id', 'status', 'already', 'event_id', 'event_title', 'stage']
     */
    public static function add(array $data) {
        global $wpdb;

        foreach (['email', 'first_name', 'last_name'] as $key) {
            if (isset($data[$key]) && !is_scalar($data[$key])) {
                $data[$key] = '';
            }
        }
        $event_id = isset($data['event_id']) ? absint($data['event_id']) : 0;
        $email    = isset($data['email']) ? sanitize_email((string) $data['email']) : '';
        $first    = isset($data['first_name']) ? self::clip(sanitize_text_field((string) $data['first_name']), 100) : '';
        $last     = isset($data['last_name']) ? self::clip(sanitize_text_field((string) $data['last_name']), 100) : '';
        $user_id  = isset($data['user_id']) ? (int) $data['user_id'] : 0;

        if (!$email || !is_email($email)) {
            return new \WP_Error('invalid_email', __('Please enter a valid email address.', 'gps-courses'));
        }

        $event = $event_id ? get_post($event_id) : null;
        if (!$event || 'gps_event' !== $event->post_type || 'publish' !== $event->post_status) {
            return new \WP_Error('invalid_event', __('The course was not found.', 'gps-courses'));
        }

        $stage = Course_Stage::get($event_id);
        if ('open' === $stage) {
            return new \WP_Error('registration_open', __('Registration for this course is open. You can register now.', 'gps-courses'));
        }
        if ('sold_out' === $stage) {
            return new \WP_Error('sold_out', __('This course is sold out. Please join its waitlist instead.', 'gps-courses'));
        }

        // Abuse guard: sign-ups per email (5) and per IP (20) per hour; only stored sign-ups count
        $ip     = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $limits = [];
        foreach (['e' => [strtolower($email), 5], 'i' => [$ip, 20]] as $kind => $who) {
            if ('' === $who[0]) {
                continue;
            }
            $key  = 'gpsc_il_' . $kind . '_' . md5($who[0] . '|' . (int) floor(time() / HOUR_IN_SECONDS));
            $hits = (int) get_transient($key);
            if ($hits >= $who[1]) {
                return new \WP_Error('rate_limited', __('Too many requests. Please try again later.', 'gps-courses'));
            }
            $limits[$key] = $hits;
        }
        $count_signup = function () use ($limits) {
            foreach ($limits as $key => $hits) {
                set_transient($key, $hits + 1, HOUR_IN_SECONDS);
            }
        };

        $table  = self::table();
        $result = [
            'id'          => 0,
            'status'      => 'waiting',
            'already'     => false,
            'event_id'    => $event_id,
            'event_title' => html_entity_decode(wp_strip_all_tags(get_the_title($event_id)), ENT_QUOTES, 'UTF-8'),
            'stage'       => $stage,
        ];

        // One row per email and course
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status FROM $table WHERE ticket_type_id = 0 AND event_id = %d AND email = %s ORDER BY id DESC LIMIT 1",
            $event_id,
            $email
        ));

        if ($row && 'waiting' === $row->status) {
            $result['id']      = (int) $row->id;
            $result['already'] = true;
            return $result;
        }

        if ($row) {
            // Notified before (or removed): back on the list for the next dates
            $wpdb->query($wpdb->prepare(
                "UPDATE $table SET status = 'waiting', notified_at = NULL, expires_at = NULL,
                    first_name = CASE WHEN %s = '' THEN first_name ELSE %s END,
                    last_name = CASE WHEN %s = '' THEN last_name ELSE %s END,
                    notes = CONCAT_WS(%s, NULLIF(notes, ''), %s)
                 WHERE id = %d AND ticket_type_id = 0",
                $first, $first, $last, $last, "\n",
                sprintf('[%s] re-joined (was %s)', current_time('mysql'), $row->status),
                (int) $row->id
            ));
            $count_signup();
            $result['id'] = (int) $row->id;
            return $result;
        }

        $insert = [
            'email'          => $email,
            'first_name'     => $first,
            'last_name'      => $last,
            'phone'          => '',
            'ticket_type_id' => self::TICKET,
            'event_id'       => $event_id,
            'position'       => 0,
            'status'         => 'waiting',
            'created_at'     => current_time('mysql'),
        ];
        $format = ['%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s'];
        if ($user_id > 0) {
            $insert['user_id'] = $user_id;
            $format[] = '%d';
        }
        if (!$wpdb->insert($table, $insert, $format)) {
            error_log('GPS Courses: interest list insert failed: ' . $wpdb->last_error);
            return new \WP_Error('insert_failed', __('Something went wrong. Please try again.', 'gps-courses'));
        }
        $count_signup();
        $result['id'] = (int) $wpdb->insert_id;
        return $result;
    }

    protected static function clip($text, $max) {
        return function_exists('mb_substr') ? mb_substr($text, 0, $max) : substr($text, 0, $max);
    }

    /** admin-ajax: front-end form. */
    public static function ajax_join() {
        nocache_headers();
        $is_ajax  = !empty($_POST['ajax']);
        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        $nonce    = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

        if (!wp_verify_nonce($nonce, self::NONCE)) {
            self::respond(false, __('Your session expired. Please try again.', 'gps-courses'), $is_ajax, $event_id, 'bad_nonce');
        }

        // Honeypot: bots fill every field. Pretend it worked, store nothing.
        if (!empty($_POST['website'])) {
            self::respond(true, self::success_message(false), $is_ajax, $event_id);
        }

        $result = self::add([
            'event_id'   => $event_id,
            'email'      => isset($_POST['email']) && is_scalar($_POST['email']) ? wp_unslash($_POST['email']) : '',
            'first_name' => isset($_POST['first_name']) && is_scalar($_POST['first_name']) ? wp_unslash($_POST['first_name']) : '',
            'last_name'  => isset($_POST['last_name']) && is_scalar($_POST['last_name']) ? wp_unslash($_POST['last_name']) : '',
            'user_id'    => get_current_user_id(),
        ]);

        if (is_wp_error($result)) {
            self::respond(false, $result->get_error_message(), $is_ajax, $event_id, $result->get_error_code());
        }
        self::respond(true, self::success_message($result['already']), $is_ajax, $event_id);
    }

    protected static function success_message($already) {
        return $already
            ? __("You're already on the list. We'll email you when new dates are published.", 'gps-courses')
            : __("Thank you! We'll email you when new dates are published.", 'gps-courses');
    }

    /** JSON for the script; without JavaScript, back to the course page with a flag. */
    protected static function respond($ok, $message, $is_ajax, $event_id, $code = '') {
        if ($is_ajax) {
            if ($ok) {
                wp_send_json_success(['message' => $message]);
            }
            wp_send_json_error(['message' => $message, 'code' => $code]);
        }
        $back = $event_id ? get_permalink($event_id) : '';
        $back = $back ?: (wp_get_referer() ?: home_url('/'));
        wp_safe_redirect(add_query_arg('gps_interest', $ok ? 'ok' : 'error', $back) . '#gps-interest-' . (int) $event_id);
        exit;
    }

    /**
     * admin-ajax: a fresh nonce for a public form. Pages are cached for hours
     * and a nonce printed in cached HTML expires; the scripts ask for a new one
     * right before submitting. Abuse is limited by the honeypot, rate limits
     * and one row per email and course, not by the nonce.
     */
    public static function ajax_nonce() {
        nocache_headers();
        $for = isset($_POST['for']) ? sanitize_key(wp_unslash($_POST['for'])) : '';
        if (!in_array($for, [self::NONCE, 'gps_waitlist'], true)) {
            wp_send_json_error(['message' => 'unknown form'], 400);
        }
        wp_send_json_success(['nonce' => wp_create_nonce($for)]);
    }

    /* ============================================================
     * Form
     * ============================================================ */

    /**
     * The "notify me" form (posts to admin-ajax; assets/js/gps-interest.js
     * submits it inline).
     *
     * @param array $args title, button, intro, heading_tag
     */
    public static function render_form($event_id, array $args = []) {
        $event_id = (int) $event_id;
        $args = wp_parse_args($args, [
            'title'       => __('Notify me about the next dates', 'gps-courses'),
            'button'      => __('Notify me', 'gps-courses'),
            'intro'       => '',
            'heading_tag' => 'h4',
        ]);
        static $n = 0;
        $uid = 'gps-interest-' . $event_id . '-' . (++$n);
        $tag = in_array($args['heading_tag'], ['h2', 'h3', 'h4', 'h5', 'p'], true) ? $args['heading_tag'] : 'h4';

        $user  = is_user_logged_in() ? wp_get_current_user() : null;
        $flag  = isset($_GET['gps_interest']) ? sanitize_key(wp_unslash($_GET['gps_interest'])) : '';
        $first = $n === 1 ? 'id="gps-interest-' . $event_id . '"' : ''; // anchor for the no-JS redirect

        ob_start();
        ?>
        <form class="gps-waitlist-form gps-interest-form" <?php echo $first; // literal ?> method="post"
              action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" aria-labelledby="<?php echo esc_attr($uid); ?>-title" novalidate>
            <<?php echo $tag; ?> class="gps-interest-form__title" id="<?php echo esc_attr($uid); ?>-title"><?php echo esc_html($args['title']); ?></<?php echo $tag; ?>>
            <?php if ('' !== $args['intro']): ?>
                <p class="gps-interest-form__intro"><?php echo esc_html($args['intro']); ?></p>
            <?php endif; ?>
            <div class="gps-waitlist-fields">
                <div class="gps-waitlist-row">
                    <label class="gps-sr" for="<?php echo esc_attr($uid); ?>-first"><?php esc_html_e('First name', 'gps-courses'); ?></label>
                    <input type="text" id="<?php echo esc_attr($uid); ?>-first" name="first_name" class="gps-waitlist-input" autocomplete="given-name" maxlength="100"
                           placeholder="<?php esc_attr_e('First name', 'gps-courses'); ?>" value="<?php echo $user ? esc_attr($user->first_name) : ''; ?>">
                    <label class="gps-sr" for="<?php echo esc_attr($uid); ?>-last"><?php esc_html_e('Last name', 'gps-courses'); ?></label>
                    <input type="text" id="<?php echo esc_attr($uid); ?>-last" name="last_name" class="gps-waitlist-input" autocomplete="family-name" maxlength="100"
                           placeholder="<?php esc_attr_e('Last name', 'gps-courses'); ?>" value="<?php echo $user ? esc_attr($user->last_name) : ''; ?>">
                </div>
                <label class="gps-sr" for="<?php echo esc_attr($uid); ?>-email"><?php esc_html_e('Email address (required)', 'gps-courses'); ?></label>
                <input type="email" id="<?php echo esc_attr($uid); ?>-email" name="email" class="gps-waitlist-input" autocomplete="email" required aria-required="true"
                       aria-describedby="<?php echo esc_attr($uid); ?>-consent" placeholder="<?php esc_attr_e('Email address *', 'gps-courses'); ?>"
                       value="<?php echo $user ? esc_attr($user->user_email) : ''; ?>">
                <div class="gps-hp" aria-hidden="true">
                    <label for="<?php echo esc_attr($uid); ?>-website"><?php esc_html_e('Leave this field empty', 'gps-courses'); ?></label>
                    <input type="text" id="<?php echo esc_attr($uid); ?>-website" name="website" value="" tabindex="-1" autocomplete="off">
                </div>
                <input type="hidden" name="action" value="gpsc_interest_join">
                <input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>">
                <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce(self::NONCE)); ?>">
                <p class="gps-interest-form__consent" id="<?php echo esc_attr($uid); ?>-consent"><?php esc_html_e("We'll email you when new dates are published.", 'gps-courses'); ?></p>
                <button type="submit" class="gps-waitlist-submit"><?php echo esc_html($args['button']); ?></button>
            </div>
            <div class="gps-waitlist-response<?php echo 'ok' === $flag ? ' success' : ('error' === $flag ? ' error' : ''); ?>" role="status" aria-live="polite" <?php echo $flag && 1 === $n ? '' : 'hidden'; ?>>
                <?php
                if (1 === $n && 'ok' === $flag) {
                    echo esc_html(self::success_message(false));
                } elseif (1 === $n && 'error' === $flag) {
                    esc_html_e('We could not add you to the list. Please try again.', 'gps-courses');
                }
                ?>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    /* ============================================================
     * Notifications
     * ============================================================ */

    public static function on_save_event($post_id, $post = null) {
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        self::queue($post_id);
    }

    public static function on_save_ticket($ticket_id) {
        if (wp_is_post_revision($ticket_id)) {
            return;
        }
        self::queue((int) get_post_meta($ticket_id, '_gps_event_id', true));
    }

    public static function on_ticket_activated($ticket_id, $event_id = 0) {
        self::queue($event_id ?: (int) get_post_meta($ticket_id, '_gps_event_id', true));
    }

    /**
     * The course may have just become 'open': send its emails in a cron
     * request right after this one (not inside the editor's save request).
     */
    public static function queue($event_id) {
        $event_id = (int) $event_id;
        if (!$event_id || 'gps_event' !== get_post_type($event_id) || 'publish' !== get_post_status($event_id)) {
            return;
        }
        Course_Stage::reset();
        $stage = Course_Stage::get($event_id);
        self::track_open($event_id, $stage);
        $targets = [];
        if ('open' === $stage) {
            $targets[] = $event_id;
        } elseif (in_array($stage, ['past', 'in_progress'], true)) {
            // E.g. a past edition just joined a program with open editions
            $targets = self::open_edition_ids($event_id);
        }
        foreach ($targets as $target) {
            if (self::count_waiting(self::scope_ids($target)) && !wp_next_scheduled(self::CRON_COURSE, [$target])) {
                wp_schedule_single_event(time(), self::CRON_COURSE, [$target]);
            }
        }
    }

    /** Open editions of the course's program (itself excluded), soonest first. */
    protected static function open_edition_ids($event_id) {
        $ids = [];
        foreach (Course_Stage::other_editions($event_id) as $row) {
            if ('open' === $row['status']) {
                $ids[] = (int) $row['id'];
            }
        }
        return $ids;
    }

    /**
     * Remember when a course was first seen open: people who signed up on
     * another edition after that already saw its dates on the page, so its
     * "new dates" email is not for them (they wait for the next edition).
     */
    public static function track_open($event_id, $stage) {
        $since = (string) get_post_meta($event_id, self::META_OPEN_SINCE, true);
        if ('open' === $stage && '' === $since) {
            update_post_meta($event_id, self::META_OPEN_SINCE, current_time('mysql'));
        } elseif (!in_array($stage, ['open', 'sold_out'], true) && '' !== $since) {
            delete_post_meta($event_id, self::META_OPEN_SINCE);
        }
    }

    /** When the course was first seen open ('' = unknown). */
    public static function open_since($event_id) {
        return (string) get_post_meta($event_id, self::META_OPEN_SINCE, true);
    }

    /**
     * Rows an open course answers: its own, plus past / in-progress editions
     * of its program.
     *
     * @return int[] event IDs
     */
    public static function scope_ids($event_id) {
        $event_id = (int) $event_id;
        $ids = [$event_id];
        $term = Course_Stage::program($event_id);
        if ($term) {
            foreach (Course_Stage::program_event_ids($term->term_id) as $id) {
                if ($id !== $event_id && in_array(Course_Stage::get($id), ['past', 'in_progress'], true)) {
                    $ids[] = $id;
                }
            }
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** Waiting "next dates" rows on these courses. */
    public static function count_waiting(array $event_ids) {
        return self::count($event_ids, 'waiting');
    }

    public static function count(array $event_ids, $status = '') {
        global $wpdb;
        $event_ids = array_filter(array_map('intval', $event_ids));
        if (!$event_ids) {
            return 0;
        }
        $table = self::table();
        $in    = implode(',', array_fill(0, count($event_ids), '%d'));
        $sql   = "SELECT COUNT(*) FROM $table WHERE ticket_type_id = 0 AND event_id IN ($in)";
        $args  = $event_ids;
        if ('' !== $status) {
            $sql   .= ' AND status = %s';
            $args[] = $status;
        }
        return (int) $wpdb->get_var($wpdb->prepare($sql, $args));
    }

    /**
     * Email the waiting rows this open course answers (at most $limit).
     *
     * @return int emails sent
     */
    public static function notify_for_course($event_id, $limit = self::BATCH) {
        global $wpdb;
        $event_id = (int) $event_id;
        $limit    = max(1, (int) $limit);

        Course_Stage::reset();
        if ('publish' !== get_post_status($event_id) || 'open' !== Course_Stage::get($event_id)) {
            return 0;
        }
        self::track_open($event_id, 'open');
        if (function_exists('set_time_limit') && function_exists('wp_doing_cron') && wp_doing_cron()) {
            @set_time_limit(120);
        }

        $ids   = self::scope_ids($event_id);
        $table = self::table();
        $in    = implode(',', array_fill(0, count($ids), '%d'));
        // Rows on other editions only if they signed up before this one opened
        $since = self::open_since($event_id) ?: current_time('mysql');
        $rows  = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE ticket_type_id = 0 AND status = 'waiting' AND event_id IN ($in)
               AND (event_id = %d OR created_at < %s)
             ORDER BY created_at ASC, id ASC LIMIT %d",
            array_merge($ids, [$event_id, $since, $limit])
        ));

        $sent   = 0;
        $failed = false;
        foreach ((array) $rows as $row) {
            if (!self::claim((int) $row->id, ['waiting'], $event_id)) {
                continue;
            }
            if (!self::send($row, $event_id)) {
                // Mail is probably down: stop here instead of marking the whole list failed
                $failed = true;
                break;
            }
            $sent++;
            // Same person waiting on another edition in scope: this email covers it
            $wpdb->query($wpdb->prepare(
                "UPDATE $table SET status = 'notified', notified_at = %s, notes = CONCAT_WS(%s, NULLIF(notes, ''), %s)
                 WHERE ticket_type_id = 0 AND status = 'waiting' AND LOWER(email) = LOWER(%s) AND id <> %d AND event_id IN ($in)
                   AND (event_id = %d OR created_at < %s)",
                array_merge(
                    [current_time('mysql'), "\n", sprintf('[%s] covered by the email to #%d for course #%d', current_time('mysql'), (int) $row->id, $event_id), (string) $row->email, (int) $row->id],
                    $ids,
                    [$event_id, $since]
                )
            ));
        }

        // More waiting: next batch in a minute (after a mail failure, the hourly run puts
        // 'failed' rows back in the queue, up to MAX_ATTEMPTS times)
        if (!$failed && count((array) $rows) >= $limit && !wp_next_scheduled(self::CRON_COURSE, [$event_id])) {
            wp_schedule_single_event(time() + MINUTE_IN_SECONDS, self::CRON_COURSE, [$event_id]);
        }
        return $sent;
    }

    /**
     * Hourly fallback: every course with waiting rows that is open now, or
     * whose program has an open edition now.
     *
     * @return int emails sent
     */
    public static function run() {
        global $wpdb;
        $table  = self::table();
        if (function_exists('set_time_limit') && function_exists('wp_doing_cron') && wp_doing_cron()) {
            @set_time_limit(120);
        }

        // Failed sends go back in the queue, up to MAX_ATTEMPTS failures per row
        $marker = 'email failed';
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET status = 'waiting', notes = CONCAT_WS(%s, NULLIF(notes, ''), %s)
             WHERE ticket_type_id = 0 AND status = 'failed'
               AND (LENGTH(IFNULL(notes, '')) - LENGTH(REPLACE(IFNULL(notes, ''), %s, ''))) < %d",
            "\n",
            sprintf('[%s] retry', current_time('mysql')),
            $marker,
            self::MAX_ATTEMPTS * strlen($marker)
        ));

        $events = $wpdb->get_col("SELECT DISTINCT event_id FROM $table WHERE ticket_type_id = 0 AND status = 'waiting' LIMIT 500");

        Course_Stage::reset();
        $budget = self::BATCH;
        $sent   = 0;
        $done   = [];
        foreach ((array) $events as $event_id) {
            $event_id = (int) $event_id;
            $targets  = [];
            $stage    = Course_Stage::get($event_id);
            self::track_open($event_id, $stage);
            if ('open' === $stage) {
                $targets[] = $event_id;
            } elseif (in_array($stage, ['past', 'in_progress'], true)) {
                // Every open edition: one opened before the sign-up is skipped for it
                $targets = self::open_edition_ids($event_id);
            }
            foreach ($targets as $target) {
                if (isset($done[$target])) {
                    continue;
                }
                $done[$target] = true;
                $n       = self::notify_for_course($target, $budget);
                $sent   += $n;
                $budget -= $n;
                if ($budget <= 0) {
                    return $sent;
                }
            }
        }
        return $sent;
    }

    /**
     * Atomically move a row to 'notified' before emailing it, so two runs
     * (cron + editor save, two cron workers) can never both send it.
     */
    protected static function claim($id, array $from, $target_id, $note = '') {
        global $wpdb;
        $table = self::table();
        $in    = implode(',', array_fill(0, count($from), '%s'));
        $text  = sprintf('[%s] next-dates email for course #%d', current_time('mysql'), (int) $target_id) . ('' !== $note ? ' (' . $note . ')' : '');
        $args  = array_merge(
            [current_time('mysql'), "\n", $text, (int) $id],
            $from
        );
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET status = 'notified', notified_at = %s, expires_at = NULL, notes = CONCAT_WS(%s, NULLIF(notes, ''), %s)
             WHERE id = %d AND ticket_type_id = 0 AND status IN ($in)",
            $args
        ));
        return 1 === (int) $updated;
    }

    /** Course to announce to a row: its own course if open, else the next open edition of its program. */
    public static function target_for($event_id) {
        $event_id = (int) $event_id;
        Course_Stage::reset();
        if ('publish' === get_post_status($event_id) && 'open' === Course_Stage::get($event_id)) {
            return $event_id;
        }
        $next = Course_Stage::next_open_edition($event_id);
        return $next ? (int) $next['id'] : 0;
    }

    /**
     * Waitlist admin page: send (or resend) the next-dates email to one row.
     *
     * @return true|\WP_Error
     */
    public static function admin_send($waitlist_id, $allow_resend = true) {
        $row = Waitlist::get_waitlist_entry((int) $waitlist_id);
        if (!$row || self::TICKET !== (int) $row->ticket_type_id) {
            return new \WP_Error('not_found', __('Entry not found.', 'gps-courses'));
        }
        $target = self::target_for((int) $row->event_id);
        if (!$target) {
            return new \WP_Error('no_open_dates', __('There are no open dates to announce for this course yet. Publish a new edition (same program) with a ticket on sale first.', 'gps-courses'));
        }
        $from = $allow_resend ? ['waiting', 'notified', 'failed', 'expired'] : ['waiting', 'failed'];
        if (!self::claim((int) $row->id, $from, $target, 'sent from the Waitlist page')) {
            return new \WP_Error('not_sendable', __('This entry cannot be emailed (removed or converted).', 'gps-courses'));
        }
        return self::send($row, $target) ? true : new \WP_Error('mail_failed', __('The email could not be sent. Check the site email settings.', 'gps-courses'));
    }

    /** Email one row about $target_id (already claimed). Failures are marked 'failed'. */
    protected static function send($row, $target_id) {
        global $wpdb;
        $subject = sprintf(
            /* translators: %s: course title */
            __('New dates for %s', 'gps-courses'),
            html_entity_decode(wp_strip_all_tags(get_the_title($target_id)), ENT_QUOTES, 'UTF-8')
        );
        $html = self::email_html($row, $target_id);
        $sent = wp_mail($row->email, $subject, $html, ['Content-Type: text/html; charset=UTF-8']);

        if ($sent) {
            error_log(sprintf('GPS Courses: next-dates email sent to %s for course #%d (interest #%d, signed up on course #%d)', $row->email, $target_id, $row->id, $row->event_id));
        } else {
            $wpdb->query($wpdb->prepare(
                "UPDATE " . self::table() . " SET status = 'failed', notes = CONCAT_WS(%s, NULLIF(notes, ''), %s) WHERE id = %d AND ticket_type_id = 0",
                "\n",
                sprintf('[%s] email failed', current_time('mysql')),
                (int) $row->id
            ));
            error_log(sprintf('GPS Courses: next-dates email FAILED to %s for course #%d (interest #%d)', $row->email, $target_id, $row->id));
        }
        do_action('gpsc_interest_notified', (int) $row->id, (int) $target_id, (bool) $sent);
        return (bool) $sent;
    }

    /** "New dates" email in the shared GPS frame. */
    public static function email_html($row, $target_id) {
        $title = html_entity_decode(wp_strip_all_tags(get_the_title($target_id)), ENT_QUOTES, 'UTF-8');
        $when  = html_entity_decode(Course_Stage::date_range($target_id), ENT_QUOTES, 'UTF-8');
        $ce    = (float) get_post_meta($target_id, '_gps_ce_credits', true);
        $venue = trim((string) get_post_meta($target_id, '_gps_venue', true));
        $city  = trim((string) get_post_meta($target_id, '_gps_city', true));
        $state = trim((string) get_post_meta($target_id, '_gps_state', true));
        $place = implode(', ', array_filter([$venue, $city, $state], 'strlen'));
        $url   = (string) get_permalink($target_id) . '#registration';
        $name  = trim((string) $row->first_name);

        $details = [];
        if ('' !== $when) {
            $details[__('Dates', 'gps-courses')] = $when;
        }
        if ($ce > 0) {
            $credits = class_exists(__NAMESPACE__ . '\\Credits') && method_exists(__NAMESPACE__ . '\\Credits', 'format') ? Credits::format($ce) : (string) $ce;
            $details[__('CE credits', 'gps-courses')] = $credits;
        }
        if ('' !== $place) {
            $details[__('Location', 'gps-courses')] = $place;
        }

        $other = (int) $row->event_id !== (int) $target_id;
        $intro = $other
            ? __('You asked us to let you know when this course was scheduled again. New dates are now open for registration:', 'gps-courses')
            : __('You asked us to let you know about the dates of this course. Registration is now open:', 'gps-courses');

        $body  = '<p style="margin:0 0 16px;">' . esc_html('' !== $name ? sprintf(__('Hi %s,', 'gps-courses'), $name) : __('Hello,', 'gps-courses')) . '</p>';
        $body .= '<p style="margin:0 0 20px;">' . esc_html($intro) . '</p>';
        $body .= '<p style="margin:0 0 12px;font-size:19px;line-height:1.35;font-weight:bold;color:#193463;">' . esc_html($title) . '</p>';
        if ($details) {
            $body .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 26px;border-collapse:collapse;background:#f7f9fc;border-left:4px solid #DDC89D;">';
            foreach ($details as $label => $value) {
                $body .= '<tr><td style="padding:10px 14px;font-size:13px;color:#5b6880;width:110px;vertical-align:top;">' . esc_html($label) . '</td>'
                       . '<td style="padding:10px 14px 10px 0;font-size:15px;color:#1b2436;font-weight:bold;">' . esc_html($value) . '</td></tr>';
            }
            $body .= '</table>';
        }
        $body .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 26px;"><tr>'
               . '<td align="center" bgcolor="#DDC89D" style="border-radius:8px;">'
               . '<a href="' . esc_url($url) . '" style="display:inline-block;padding:14px 34px;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;color:#193463;text-decoration:none;border-radius:8px;background-color:#DDC89D;">'
               . esc_html__('Register now', 'gps-courses') . '</a></td></tr></table>';
        $body .= '<p style="margin:0;font-size:12.5px;color:#5b6880;">' . esc_html__('You are receiving this one-time email because you asked to be notified about new dates for this course on our website.', 'gps-courses') . '</p>';

        $heading = __('New dates are open', 'gps-courses');
        if (class_exists(__NAMESPACE__ . '\\Emails') && method_exists(__NAMESPACE__ . '\\Emails', 'frame_open')) {
            return Emails::frame_open($heading) . $body . Emails::frame_close();
        }
        return '<html><body style="font-family:Arial,sans-serif;">' . $body . '</body></html>';
    }

    /* ============================================================
     * Course editor tab
     * ============================================================ */

    /**
     * "Interest list" tab of the course edit screen: the course's program
     * (editions of the same course) and who is waiting for new dates.
     */
    public static function render_event_tab($post) {
        $post_id  = (int) $post->ID;
        $program  = Course_Stage::program($post_id);
        $terms    = get_terms(['taxonomy' => Course_Stage::TAXONOMY, 'hide_empty' => false, 'orderby' => 'name']);
        $terms    = is_wp_error($terms) ? [] : (array) $terms;
        $stage    = $post_id ? Course_Stage::get($post_id) : 'soon';
        $editions = $program ? Course_Stage::editions($post_id) : [];
        $prog_ids = $program ? array_map(function ($e) { return (int) $e['id']; }, $editions) : [];

        $mine_waiting  = self::count([$post_id], 'waiting');
        $mine_notified = self::count([$post_id], 'notified');
        $prog_waiting  = $prog_ids ? self::count($prog_ids, 'waiting') : 0;
        $prog_notified = $prog_ids ? self::count($prog_ids, 'notified') : 0;

        $list_url = admin_url('admin.php?page=gps-waitlist&list=interest&event_id=' . $post_id);
        $prog_url = $program ? admin_url('admin.php?page=gps-waitlist&list=interest&program=' . (int) $program->term_id) : '';
        $manage   = admin_url('edit-tags.php?taxonomy=' . Course_Stage::TAXONOMY . '&post_type=gps_event');

        wp_nonce_field('gps_program_meta', 'gps_program_nonce');
        ?>
        <div class="gps-interest-admin" style="max-width:760px;">
            <div style="background:#f0f6fc;border-left:4px solid #193463;padding:12px 14px;margin:0 0 18px;border-radius:4px;">
                <?php
                /* translators: %s: course stage label */
                printf(esc_html__('Stage now: %s.', 'gps-courses'), '<strong>' . esc_html(self::admin_stage_label($stage)) . '</strong>');
                ?>
            </div>

            <h3 style="margin:0 0 6px;"><?php esc_html_e('Course program', 'gps-courses'); ?></h3>
            <p class="description" style="margin:0 0 12px;"><?php esc_html_e('Give every edition (date) of the same course the same program. Each course page then lists the other dates, and people waiting for the next dates of a past edition are emailed when a new edition opens for registration.', 'gps-courses'); ?></p>
            <div class="gps-form-field">
                <label for="gps-course-program"><?php esc_html_e('Program', 'gps-courses'); ?></label>
                <select name="gps_course_program" id="gps-course-program">
                    <option value="0"><?php esc_html_e('— None —', 'gps-courses'); ?></option>
                    <?php foreach ($terms as $term): ?>
                        <option value="<?php echo (int) $term->term_id; ?>" <?php selected($program ? (int) $program->term_id : 0, (int) $term->term_id); ?>>
                            <?php echo esc_html(sprintf('%s (%d)', $term->name, (int) $term->count)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (current_user_can('manage_categories')): ?>
            <div class="gps-form-field">
                <label for="gps-course-program-new"><?php esc_html_e('Or create a new program', 'gps-courses'); ?></label>
                <input type="text" class="regular-text" name="gps_course_program_new" id="gps-course-program-new" value=""
                       placeholder="<?php echo esc_attr(html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8')); ?>">
                <p class="description">
                    <?php esc_html_e('Usually the course name without the year. Saved with the course.', 'gps-courses'); ?>
                    <a href="<?php echo esc_url($manage); ?>"><?php esc_html_e('Manage programs', 'gps-courses'); ?></a>
                </p>
            </div>
            <?php endif; ?>

            <?php if ($editions): ?>
            <table class="widefat striped" style="margin:6px 0 22px;">
                <thead><tr>
                    <th><?php esc_html_e('Editions in this program', 'gps-courses'); ?></th>
                    <th><?php esc_html_e('Dates', 'gps-courses'); ?></th>
                    <th><?php esc_html_e('Stage', 'gps-courses'); ?></th>
                    <th><?php esc_html_e('Waiting', 'gps-courses'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($editions as $e): ?>
                    <tr>
                        <td>
                            <?php if ((int) $e['id'] === $post_id): ?>
                                <strong><?php echo esc_html($e['title']); ?></strong> <em>(<?php esc_html_e('this course', 'gps-courses'); ?>)</em>
                            <?php else: ?>
                                <a href="<?php echo esc_url(get_edit_post_link($e['id'])); ?>"><?php echo esc_html($e['title']); ?></a>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($e['when'] ?: '—'); ?></td>
                        <td><?php echo esc_html(self::admin_stage_label($e['status'])); ?></td>
                        <td><?php echo (int) self::count([(int) $e['id']], 'waiting'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <h3 style="margin:18px 0 6px;"><?php esc_html_e('Interest list (“Next dates”)', 'gps-courses'); ?></h3>
            <p class="description" style="margin:0 0 12px;"><?php esc_html_e('People who asked to be emailed when new dates are published (form shown on the course page when it has taken place or registration has not opened yet). Each person is emailed once, automatically, when this course — or a new edition of its program — opens for registration.', 'gps-courses'); ?></p>
            <table class="widefat" style="max-width:520px;">
                <tbody>
                    <tr>
                        <td><?php esc_html_e('This course', 'gps-courses'); ?></td>
                        <td><strong><?php echo (int) $mine_waiting; ?></strong> <?php esc_html_e('waiting', 'gps-courses'); ?></td>
                        <td><strong><?php echo (int) $mine_notified; ?></strong> <?php esc_html_e('notified', 'gps-courses'); ?></td>
                        <td><a href="<?php echo esc_url($list_url); ?>"><?php esc_html_e('View list', 'gps-courses'); ?></a></td>
                    </tr>
                    <?php if ($program): ?>
                    <tr>
                        <td><?php echo esc_html(sprintf(__('Program “%s”', 'gps-courses'), $program->name)); ?></td>
                        <td><strong><?php echo (int) $prog_waiting; ?></strong> <?php esc_html_e('waiting', 'gps-courses'); ?></td>
                        <td><strong><?php echo (int) $prog_notified; ?></strong> <?php esc_html_e('notified', 'gps-courses'); ?></td>
                        <td><a href="<?php echo esc_url($prog_url); ?>"><?php esc_html_e('View list', 'gps-courses'); ?></a></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    protected static function admin_stage_label($stage) {
        switch ($stage) {
            case 'open':
                return __('Registration open', 'gps-courses');
            case 'sold_out':
                return __('Sold out (sold-out waitlist applies)', 'gps-courses');
            case 'soon':
                return __('Upcoming, no ticket on sale yet (interest form shown)', 'gps-courses');
            case 'in_progress':
                return __('In progress (registration closed)', 'gps-courses');
            case 'past':
                return __('Took place (interest form shown)', 'gps-courses');
        }
        return (string) $stage;
    }
}
