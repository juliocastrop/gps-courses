<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Home hero slider: per-course "Home hero" settings (a tab on the course edit
 * screen), the slide data builder and the markup shared by the
 * "GPS Hero Slider" Elementor widget, and its CSS/JS registration.
 *
 * Course and seminar slides come from gpsc_catalog() (cached, refreshed on any
 * course/ticket/seminar/order change), so nobody maintains them by hand.
 */
class Hero_Slider {

    const META_SHOW     = '_gps_hero_show';      // 'yes' | 'no' (missing = yes)
    const META_IMAGE    = '_gps_hero_image';     // attachment id
    const META_HEADLINE = '_gps_hero_headline';  // optional override of the title
    const META_LINE     = '_gps_hero_line';      // optional short line
    const META_PRIORITY = '_gps_hero_priority';  // featured | normal | low

    /** This many seats or fewer (all tickets on sale) shows "Only N seats left". */
    const FEW_SEATS = 5;

    /** A gold highlight this short never wraps inside (white-space: nowrap) on tablet/desktop. */
    const KEEP_TOGETHER_CHARS = 22;

    /** On phones (26px headline) only this short a highlight is kept on one line. */
    const KEEP_TOGETHER_CHARS_PHONE = 14;

    public static function init() {
        add_action('init', [__CLASS__, 'register_meta']);
        add_action('save_post_gps_event', [__CLASS__, 'save_meta']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);

        // Handles must exist before Elementor resolves the widget's depends
        add_action('wp_enqueue_scripts', [__CLASS__, 'register_assets'], 5);
        add_action('elementor/frontend/after_register_scripts', [__CLASS__, 'register_assets']);
        add_action('elementor/frontend/after_register_styles', [__CLASS__, 'register_assets']);
    }

    /* ============================================================
     * Per-course settings
     * ============================================================ */

    public static function register_meta() {
        $auth = function () { return current_user_can('edit_posts'); };
        register_post_meta('gps_event', self::META_SHOW, [
            'type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => $auth,
            'sanitize_callback' => function ($v) { return 'no' === $v ? 'no' : 'yes'; },
        ]);
        register_post_meta('gps_event', self::META_IMAGE, [
            'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'auth_callback' => $auth,
            'sanitize_callback' => 'absint',
        ]);
        register_post_meta('gps_event', self::META_HEADLINE, [
            'type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => $auth,
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_post_meta('gps_event', self::META_LINE, [
            'type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => $auth,
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_post_meta('gps_event', self::META_PRIORITY, [
            'type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => $auth,
            'sanitize_callback' => [__CLASS__, 'sanitize_priority'],
        ]);
    }

    public static function sanitize_priority($value) {
        return in_array($value, ['featured', 'low'], true) ? $value : 'normal';
    }

    /** The media modal for the hero image picker (course edit screen only). */
    public static function admin_assets($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && 'gps_event' === $screen->post_type) {
            wp_enqueue_media();
        }
    }

    /**
     * "Home hero" tab of the course edit screen. gps_event uses a custom tabbed
     * screen with the metabox columns hidden, so this renders as a tab (like the
     * Promotional QR tab) rather than through add_meta_box().
     */
    public static function render_event_tab($post) {
        $show     = 'no' !== get_post_meta($post->ID, self::META_SHOW, true);
        $image_id = (int) get_post_meta($post->ID, self::META_IMAGE, true);
        $headline = (string) get_post_meta($post->ID, self::META_HEADLINE, true);
        $line     = (string) get_post_meta($post->ID, self::META_LINE, true);
        $priority = self::sanitize_priority((string) get_post_meta($post->ID, self::META_PRIORITY, true));
        $preview  = $image_id ? wp_get_attachment_image($image_id, 'medium', false, ['style' => 'max-width:320px;height:auto;border-radius:6px;display:block;']) : '';

        wp_nonce_field('gps_hero_meta', 'gps_hero_nonce');
        ?>
        <div class="gps-hero-admin" style="max-width:760px;">
            <div style="background:#f0f6fc;border-left:4px solid #193463;padding:12px 14px;margin:0 0 18px;border-radius:4px;">
                <?php echo esc_html(self::admin_status_text($post)); ?>
            </div>

            <div class="gps-form-field">
                <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
                    <input type="checkbox" name="gps_hero_show" value="yes" <?php checked($show); ?>>
                    <?php esc_html_e('Show this course in the home hero', 'gps-courses'); ?>
                </label>
                <p class="description"><?php esc_html_e('Upcoming courses appear automatically while registration is open, almost full or opening soon.', 'gps-courses'); ?></p>
            </div>

            <div class="gps-form-field">
                <label><?php esc_html_e('Hero image', 'gps-courses'); ?></label>
                <input type="hidden" name="gps_hero_image" id="gps-hero-image" value="<?php echo esc_attr($image_id ?: ''); ?>">
                <div id="gps-hero-image-preview" style="margin:6px 0 10px;"><?php echo $preview; // wp_get_attachment_image() output ?></div>
                <p>
                    <button type="button" class="button" id="gps-hero-image-select"><?php esc_html_e('Choose image', 'gps-courses'); ?></button>
                    <button type="button" class="button" id="gps-hero-image-remove" <?php echo $image_id ? '' : 'style="display:none"'; ?>><?php esc_html_e('Remove image', 'gps-courses'); ?></button>
                </p>
                <p class="description"><?php esc_html_e('Wide photo, at least 1920 × 900 px. Empty = the course image.', 'gps-courses'); ?></p>
            </div>

            <div class="gps-form-field">
                <label for="gps-hero-headline"><?php esc_html_e('Headline (optional)', 'gps-courses'); ?></label>
                <input type="text" class="widefat" name="gps_hero_headline" id="gps-hero-headline" value="<?php echo esc_attr($headline); ?>" placeholder="<?php echo esc_attr(html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8')); ?>">
                <p class="description"><?php esc_html_e('The part after the colon is drawn in gold. Empty = the course title.', 'gps-courses'); ?></p>
            </div>

            <div class="gps-form-field">
                <label for="gps-hero-line"><?php esc_html_e('Short line under the headline (optional)', 'gps-courses'); ?></label>
                <textarea class="widefat" rows="2" name="gps_hero_line" id="gps-hero-line"><?php echo esc_textarea($line); ?></textarea>
                <p class="description"><?php esc_html_e('One or two sentences. Empty = the course summary, shortened.', 'gps-courses'); ?></p>
            </div>

            <div class="gps-form-field">
                <label for="gps-hero-priority"><?php esc_html_e('Priority', 'gps-courses'); ?></label>
                <select name="gps_hero_priority" id="gps-hero-priority">
                    <option value="featured" <?php selected($priority, 'featured'); ?>><?php esc_html_e('Featured (first)', 'gps-courses'); ?></option>
                    <option value="normal" <?php selected($priority, 'normal'); ?>><?php esc_html_e('Normal (by date)', 'gps-courses'); ?></option>
                    <option value="low" <?php selected($priority, 'low'); ?>><?php esc_html_e('Low (last)', 'gps-courses'); ?></option>
                </select>
            </div>
        </div>
        <script>
        (function () {
            var input = document.getElementById('gps-hero-image');
            var preview = document.getElementById('gps-hero-image-preview');
            var select = document.getElementById('gps-hero-image-select');
            var remove = document.getElementById('gps-hero-image-remove');
            if (!input || !select) { return; }
            var frame;
            select.addEventListener('click', function (e) {
                e.preventDefault();
                if (!window.wp || !wp.media) { return; }
                if (!frame) {
                    frame = wp.media({
                        title: <?php echo wp_json_encode(__('Hero image', 'gps-courses')); ?>,
                        button: { text: <?php echo wp_json_encode(__('Use this image', 'gps-courses')); ?> },
                        library: { type: 'image' },
                        multiple: false
                    });
                    frame.on('select', function () {
                        var a = frame.state().get('selection').first().toJSON();
                        var src = (a.sizes && a.sizes.medium) ? a.sizes.medium.url : a.url;
                        input.value = a.id;
                        preview.innerHTML = '';
                        var img = document.createElement('img');
                        img.src = src;
                        img.alt = '';
                        img.style.cssText = 'max-width:320px;height:auto;border-radius:6px;display:block;';
                        preview.appendChild(img);
                        remove.style.display = '';
                    });
                }
                frame.open();
            });
            remove.addEventListener('click', function (e) {
                e.preventDefault();
                input.value = '';
                preview.innerHTML = '';
                remove.style.display = 'none';
            });
        })();
        </script>
        <?php
    }

    /** "Status now: …" line for the edit screen. */
    protected static function admin_status_text($post) {
        if ('publish' !== $post->post_status) {
            return __('Status now: not published. The course joins the home hero once it is published and has upcoming dates.', 'gps-courses');
        }
        $course = null;
        if (function_exists('gpsc_catalog')) {
            foreach ((array) gpsc_catalog()['courses'] as $c) {
                if ((int) $c['id'] === (int) $post->ID) {
                    $course = $c;
                    break;
                }
            }
        }
        if (!$course) {
            return __('Status now: not upcoming (no future dates), so it is not in the home hero.', 'gps-courses');
        }

        list($state, $seats) = self::course_state($course);
        $end   = $course['end'] ?: $course['start'];
        $until = $end ? date_i18n('M j, Y', strtotime($end)) : '';
        $hidden = 'no' === get_post_meta($post->ID, self::META_SHOW, true);

        switch ($state) {
            case 'few':
                /* translators: %d: seats left */
                $label = sprintf(_n('Only %d seat left', 'Only %d seats left', $seats, 'gps-courses'), $seats);
                break;
            case 'sold_out':
                $label = __('Sold out (shown only when the widget includes sold-out courses)', 'gps-courses');
                break;
            case 'soon':
                $label = __('Registration opens soon', 'gps-courses');
                break;
            case 'closed':
                $label = __('Registration closed (not shown)', 'gps-courses');
                break;
            default:
                $label = __('Registration open', 'gps-courses');
        }

        $text = sprintf(__('Status now: %s.', 'gps-courses'), $label);
        if ($hidden) {
            $text .= ' ' . __('Hidden from the home hero by the checkbox below.', 'gps-courses');
        } elseif ('closed' !== $state && $until) {
            /* translators: %s: course end date */
            $text .= ' ' . sprintf(__('It leaves the slider after %s.', 'gps-courses'), $until);
        }
        return $text;
    }

    public static function save_meta($post_id) {
        if (!isset($_POST['gps_hero_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['gps_hero_nonce'])), 'gps_hero_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (!current_user_can('edit_post', $post_id)) return;

        update_post_meta($post_id, self::META_SHOW, !empty($_POST['gps_hero_show']) ? 'yes' : 'no');

        $image = isset($_POST['gps_hero_image']) ? absint($_POST['gps_hero_image']) : 0;
        if ($image && wp_attachment_is_image($image)) {
            update_post_meta($post_id, self::META_IMAGE, $image);
        } else {
            delete_post_meta($post_id, self::META_IMAGE);
        }

        $headline = isset($_POST['gps_hero_headline']) ? sanitize_text_field(wp_unslash($_POST['gps_hero_headline'])) : '';
        $line     = isset($_POST['gps_hero_line']) ? sanitize_textarea_field(wp_unslash($_POST['gps_hero_line'])) : '';
        foreach ([self::META_HEADLINE => $headline, self::META_LINE => $line] as $key => $value) {
            if ('' === $value) {
                delete_post_meta($post_id, $key);
            } else {
                update_post_meta($post_id, $key, $value);
            }
        }

        $priority = isset($_POST['gps_hero_priority']) ? sanitize_key(wp_unslash($_POST['gps_hero_priority'])) : 'normal';
        update_post_meta($post_id, self::META_PRIORITY, self::sanitize_priority($priority));
    }

    /* ============================================================
     * Assets
     * ============================================================ */

    public static function register_assets() {
        if (wp_style_is('gps-hero', 'registered')) {
            return;
        }
        $css = GPSC_PATH . 'assets/css/gps-hero.css';
        $js  = GPSC_PATH . 'assets/js/gps-hero.js';
        wp_register_style('gps-hero', GPSC_URL . 'assets/css/gps-hero.css', [], file_exists($css) ? (string) filemtime($css) : GPSC_VERSION);
        wp_register_script('gps-hero', GPSC_URL . 'assets/js/gps-hero.js', [], file_exists($js) ? (string) filemtime($js) : GPSC_VERSION, ['in_footer' => true, 'strategy' => 'defer']);
    }

    /* ============================================================
     * Slide data
     * ============================================================ */

    /**
     * Course status for the hero: open | few | sold_out | soon | closed.
     *
     * @return array [state, seats_left|null]
     */
    public static function course_state(array $course) {
        $status = isset($course['status']) ? (string) $course['status'] : 'soon';
        if ('open' !== $status) {
            return [$status, null];
        }
        $sum = 0;
        $any = false;
        foreach ((array) ($course['tickets'] ?? []) as $t) {
            if (!empty($t['sold_out'])) {
                continue;
            }
            $any = true;
            if (!isset($t['seats_left']) || null === $t['seats_left']) {
                return ['open', null]; // unlimited ticket on sale
            }
            $sum += max(0, (int) $t['seats_left']);
        }
        if ($any && $sum > 0 && $sum <= self::FEW_SEATS) {
            return ['few', $sum];
        }
        return ['open', null];
    }

    /**
     * Every slide to render, in order, from the widget's repeater items.
     *
     * @param array $items     repeater rows (type, headline, …)
     * @param bool  $is_editor Elementor editor/preview: sample data when nothing is upcoming
     */
    public static function build_slides(array $items, $is_editor = false) {
        $catalog = function_exists('gpsc_catalog') ? gpsc_catalog() : ['courses' => [], 'seminars' => []];
        $now     = current_time('Y-m-d H:i');
        $slides  = [];

        foreach ($items as $item) {
            $type = isset($item['type']) ? $item['type'] : 'manual';
            if ('courses' === $type) {
                $courses = (array) ($catalog['courses'] ?? []);
                $built   = self::course_slides($courses, $item);
                if (!$built && $is_editor) {
                    $built = self::course_slides(self::sample_courses(), $item);
                }
                $slides = array_merge($slides, $built);
            } elseif ('seminar' === $type) {
                $slide = self::seminar_slide((array) ($catalog['seminars'] ?? []), $item);
                if (!$slide && $is_editor) {
                    $slide = self::seminar_slide(self::sample_seminars(), $item);
                }
                if ($slide) {
                    $slides[] = $slide;
                }
            } else {
                $slide = self::manual_slide($item, $now);
                if ($slide) {
                    $slides[] = $slide;
                }
            }
        }
        return $slides;
    }

    protected static function manual_slide(array $item, $now) {
        $from  = self::schedule_value($item['show_from'] ?? '', false);
        $until = self::schedule_value($item['show_until'] ?? '', true);
        if (($from && $now < $from) || ($until && $now > $until)) {
            return null;
        }
        $headline = trim((string) ($item['headline'] ?? ''));
        $gold     = trim((string) ($item['highlight'] ?? ''));
        if ('' === $headline && '' === $gold) {
            return null;
        }
        $label = trim((string) ($item['tab_label'] ?? '')) ?: ($headline ?: $gold);

        return [
            'type'      => 'manual',
            'eyebrow'   => trim((string) ($item['eyebrow'] ?? '')),
            'chips'     => [],
            'headline'  => $headline,
            'gold'      => $gold,
            'line'      => trim((string) ($item['line'] ?? '')),
            'image'     => self::media_value($item['image'] ?? null),
            'primary'   => self::button($item['primary_label'] ?? '', $item['primary_link'] ?? null),
            'secondary' => self::button($item['secondary_label'] ?? '', $item['secondary_link'] ?? null),
            'facts'     => [],
            'note'      => '',
            'aside'     => null,
            'tab'       => $label,
        ];
    }

    /** Elementor DATE_TIME value ("Y-m-d" or "Y-m-d H:i") to a comparable string. */
    protected static function schedule_value($value, $end_of_day) {
        $value = trim((string) $value);
        if ('' === $value || !preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return '';
        }
        if (10 === strlen($value)) {
            return $value . ($end_of_day ? ' 23:59' : ' 00:00');
        }
        return substr($value, 0, 16);
    }

    protected static function course_slides(array $courses, array $item) {
        $max         = max(1, min(12, (int) ($item['max_courses'] ?? 4) ?: 4));
        $sold_out_ok = 'yes' === ($item['include_sold_out'] ?? '');
        $fallback    = self::media_value($item['image'] ?? null);
        $rank        = ['featured' => 0, 'normal' => 1, 'low' => 2];

        // One query for the hero settings of every course
        $ids = array_filter(array_map(function ($c) { return (int) ($c['id'] ?? 0); }, $courses));
        if ($ids && function_exists('update_meta_cache')) {
            update_meta_cache('post', $ids);
        }

        $rows = [];
        foreach ($courses as $i => $c) {
            $id = (int) ($c['id'] ?? 0);
            list($state, $seats) = self::course_state($c);
            if ('closed' === $state || ('sold_out' === $state && !$sold_out_ok)) {
                continue;
            }
            if ($id && 'no' === get_post_meta($id, self::META_SHOW, true)) {
                continue;
            }
            $priority = $id ? self::sanitize_priority((string) get_post_meta($id, self::META_PRIORITY, true)) : 'normal';
            $rows[] = ['c' => $c, 'state' => $state, 'seats' => $seats, 'rank' => $rank[$priority], 'i' => $i];
        }
        usort($rows, function ($a, $b) {
            if ($a['rank'] !== $b['rank']) {
                return $a['rank'] - $b['rank'];
            }
            $cmp = strcmp((string) $a['c']['start'], (string) $b['c']['start']);
            return $cmp ?: $a['i'] - $b['i'];
        });

        $slides = [];
        foreach (array_slice($rows, 0, $max) as $row) {
            $slides[] = self::course_slide($row['c'], $row['state'], $row['seats'], $fallback);
        }
        return $slides;
    }

    protected static function course_slide(array $c, $state, $seats, array $fallback) {
        $id    = (int) ($c['id'] ?? 0);
        $title = (string) ($c['title'] ?? '');
        $url   = (string) ($c['url'] ?? '');
        $start = (string) ($c['start'] ?? '');
        $end   = (string) (($c['end'] ?? '') ?: $start);

        $headline = $id ? trim((string) get_post_meta($id, self::META_HEADLINE, true)) : '';
        list($white, $gold) = self::split_title($headline ?: $title);

        $line = $id ? trim((string) get_post_meta($id, self::META_LINE, true)) : '';
        if ('' === $line) {
            $line = wp_trim_words((string) ($c['summary'] ?? ''), 22, '…');
        }

        // Image: hero image > course image > widget fallback
        $image = ['id' => 0, 'url' => ''];
        if ($id) {
            $image_id = (int) get_post_meta($id, self::META_IMAGE, true);
            if (!$image_id || !wp_attachment_is_image($image_id)) {
                $image_id = (int) get_post_thumbnail_id($id);
            }
            if ($image_id) {
                $image = ['id' => $image_id, 'url' => ''];
            }
        }
        if (!$image['id'] && isset($c['_image_url'])) {
            $image = ['id' => 0, 'url' => (string) $c['_image_url']]; // editor sample
        }
        if (!$image['id'] && '' === $image['url']) {
            $image = $fallback;
        }

        $register = $url ? $url . '#registration' : '';
        switch ($state) {
            case 'few':
                /* translators: %d: seats left */
                $chip = ['label' => sprintf(_n('Only %d seat left', 'Only %d seats left', (int) $seats, 'gps-courses'), (int) $seats), 'tone' => 'few'];
                $cta  = ['label' => __('Register now', 'gps-courses'), 'url' => $register];
                $note = '';
                break;
            case 'sold_out':
                $chip = ['label' => __('Sold out', 'gps-courses'), 'tone' => 'sold'];
                $cta  = ['label' => __('Join the waitlist', 'gps-courses'), 'url' => $register];
                $note = __('We notify the waitlist in order as soon as a seat opens.', 'gps-courses');
                break;
            case 'soon':
                $chip = ['label' => __('Registration opens soon', 'gps-courses'), 'tone' => 'soon'];
                $cta  = ['label' => __('Get notified', 'gps-courses'), 'url' => home_url('/contact/')];
                $note = __('Dates are confirmed; tickets go on sale soon.', 'gps-courses');
                break;
            default:
                $chip = ['label' => __('Registration open', 'gps-courses'), 'tone' => 'open'];
                $cta  = ['label' => __('Register now', 'gps-courses'), 'url' => $register];
                $note = '';
        }

        $when  = (string) ($c['when'] ?? '');
        if ('' === $when && $start && class_exists(__NAMESPACE__ . '\\Account_Dashboard')) {
            $when = Account_Dashboard::date_range($start, $end);
        }
        $facts = [];
        if ('' !== $when) {
            $facts[] = ['icon' => 'calendar', 'label' => __('Dates', 'gps-courses'), 'value' => $when];
        }
        $city = (string) ($c['city'] ?? '');
        if ('' !== $city) {
            $facts[] = ['icon' => 'pin', 'label' => __('Location', 'gps-courses'), 'value' => $city, 'desktop_only' => true];
        }
        $ce = (float) ($c['ce'] ?? 0);
        if ($ce > 0) {
            $facts[] = ['icon' => 'award', 'label' => __('CE credits', 'gps-courses'), 'value' => sprintf(
                /* translators: %s: number of CE credits */
                _n('%s CE credit', '%s CE credits', (int) ceil($ce), 'gps-courses'),
                self::credits($ce)
            )];
        }

        // Tab: title before the colon + " · Sep 2027"
        $short = trim(strtok($title, ':') ?: $title);
        $tab   = $short . ($start ? ' · ' . date_i18n('M Y', strtotime($start)) : '');

        $category = trim((string) ($c['category'] ?? ''));

        return [
            'type'      => 'course',
            'eyebrow'   => '',
            'chips'     => [
                ['label' => $category ?: __('Hands-on course', 'gps-courses'), 'tone' => 'gold'],
                $chip,
            ],
            'headline'  => $white,
            'gold'      => $gold,
            'line'      => $line,
            'image'     => $image,
            'primary'   => $cta['url'] ? $cta : null,
            'secondary' => $url ? ['label' => __('Course details', 'gps-courses'), 'url' => $url] : null,
            'facts'     => $facts,
            'note'      => $note,
            'aside'     => $start ? ['kind' => 'date'] + self::date_card($start, $end) : null,
            'tab'       => $tab,
        ];
    }

    protected static function seminar_slide(array $seminars, array $item) {
        // The program whose next session comes first. Full programs are skipped
        // (no waitlist for programs here); a session that already ended today is past.
        $today = current_time('Y-m-d');
        $clock = current_time('H:i');
        $pick  = null;
        $next  = null;
        foreach ($seminars as $s) {
            if (isset($s['spots_left']) && (int) $s['spots_left'] <= 0) {
                continue;
            }
            $upcoming = null;
            foreach ((array) ($s['sessions'] ?? []) as $session) {
                $date = (string) ($session['date'] ?? '');
                if ('' === $date || $date < $today) {
                    continue;
                }
                $ends = (string) (($session['end_time'] ?? '') ?: ($session['start_time'] ?? ''));
                if ($date === $today && '' !== $ends && substr($ends, 0, 5) <= $clock) {
                    continue;
                }
                $upcoming = $session;
                break;
            }
            if ($upcoming && (!$next || strcmp($upcoming['date'] . ' ' . ($upcoming['start_time'] ?? ''), $next['date'] . ' ' . ($next['start_time'] ?? '')) < 0)) {
                $pick = $s;
                $next = $upcoming;
            }
        }
        if (!$pick || !$next) {
            return null;
        }
        $ts   = strtotime($next['date']);
        $url  = (string) ($pick['url'] ?? '');

        $headline = trim((string) ($item['headline'] ?? '')) ?: __('One evening a month.', 'gps-courses');
        $gold     = trim((string) ($item['highlight'] ?? ''));
        $line     = trim((string) ($item['line'] ?? ''))
            ?: __('Ten two-hour sessions with GPS faculty, 2 CE credits each, one makeup session included.', 'gps-courses');
        $place    = trim((string) ($item['location'] ?? ''));

        $time = self::time_range((string) ($next['start_time'] ?? ''), (string) ($next['end_time'] ?? ''));
        $meta = implode(' · ', array_filter([$time, $place]));
        /* translators: 1: session number, 2: CE credits */
        $detail = sprintf(__('Session %1$d · %2$s CE', 'gps-courses'), (int) ($next['number'] ?? 0), self::credits((float) ($next['ce'] ?? 2)));

        return [
            'type'      => 'seminar',
            'eyebrow'   => '',
            'chips'     => [['label' => __('Monthly seminars', 'gps-courses'), 'tone' => 'gold']],
            'headline'  => $headline,
            'gold'      => $gold,
            'line'      => $line,
            'program'   => (string) ($pick['title'] ?? ''),
            'image'     => self::media_value($item['image'] ?? null),
            'primary'   => $url ? ['label' => __('Enroll now', 'gps-courses'), 'url' => $url] : null,
            'secondary' => $url ? ['label' => __('See all sessions', 'gps-courses'), 'url' => $url] : null,
            'facts'     => [],
            'note'      => '',
            'aside'     => [
                'kind'   => 'session',
                'month'  => date_i18n('M', $ts),
                'day'    => date_i18n('j', $ts),
                'date'   => date_i18n('l, F j, Y', $ts),
                'topic'  => trim((string) ($next['topic'] ?? '')) ?: sprintf(__('Session %d', 'gps-courses'), (int) ($next['number'] ?? 0)),
                'meta'   => $meta,
                'detail' => $detail,
                'makeup' => __('Makeup session available', 'gps-courses'),
            ],
            /* translators: %s: next session date, e.g. Oct 26 */
            'tab'       => sprintf(__('Monthly Seminars · Next: %s', 'gps-courses'), date_i18n('M j', $ts)),
        ];
    }

    /* ============================================================
     * Helpers
     * ============================================================ */

    /** "Title: Subtitle" -> ["Title:", "Subtitle"]; no colon -> [title, ""]. */
    public static function split_title($text) {
        $text = trim((string) $text);
        $pos  = strpos($text, ':');
        if (false === $pos || $pos === strlen($text) - 1) {
            return [$text, ''];
        }
        return [trim(substr($text, 0, $pos + 1)), trim(substr($text, $pos + 1))];
    }

    /** Month, day range, year and length for the date card. */
    public static function date_card($start, $end) {
        $s = strtotime($start);
        $e = $end ? strtotime($end) : $s;
        if (!$e || $e < $s) {
            $e = $s;
        }
        $days = (int) round(($e - $s) / DAY_IN_SECONDS) + 1;
        if (date('Y-m', $s) === date('Y-m', $e)) {
            $month = date_i18n('M', $s);
            $range = $days > 1 ? date_i18n('j', $s) . '–' . date_i18n('j', $e) : date_i18n('j', $s);
        } else {
            $month = date_i18n('M', $s) . '–' . date_i18n('M', $e);
            $range = date_i18n('j', $s) . '–' . date_i18n('j', $e);
        }
        $year = date('Y', $s) === date('Y', $e) ? date_i18n('Y', $s) : date_i18n('Y', $s) . '–' . date_i18n('y', $e);
        /* translators: %d: number of days */
        $length = sprintf(_n('%d day', '%d days', $days, 'gps-courses'), $days) . ' · ' . __('Hands-on', 'gps-courses');

        return ['month' => $month, 'range' => $range, 'year' => $year, 'length' => $length];
    }

    /** "18:00","20:00" -> "6:00–8:00 PM"; "11:00","13:00" -> "11:00 AM–1:00 PM". */
    public static function time_range($start, $end) {
        $s = $start ? strtotime('2000-01-01 ' . $start) : 0;
        $e = $end ? strtotime('2000-01-01 ' . $end) : 0;
        if (!$s) {
            return '';
        }
        if (!$e) {
            return date_i18n('g:i A', $s);
        }
        if (date('A', $s) === date('A', $e)) {
            return date_i18n('g:i', $s) . '–' . date_i18n('g:i A', $e);
        }
        return date_i18n('g:i A', $s) . '–' . date_i18n('g:i A', $e);
    }

    protected static function credits($n) {
        return class_exists(__NAMESPACE__ . '\\Credits') ? Credits::format($n) : (string) (0 + round((float) $n, 2));
    }

    /** Elementor MEDIA value -> ['id', 'url']. */
    protected static function media_value($media) {
        $id  = is_array($media) && !empty($media['id']) ? (int) $media['id'] : 0;
        $url = is_array($media) && !empty($media['url']) ? (string) $media['url'] : '';
        // Elementor's placeholder image is not a real choice
        if (!$id && false !== strpos($url, 'placeholder.png')) {
            $url = '';
        }
        return ['id' => $id, 'url' => $id ? '' : $url];
    }

    /** Label + Elementor URL value -> button, or null when incomplete. */
    protected static function button($label, $link) {
        $label = trim((string) $label);
        $url   = is_array($link) ? trim((string) ($link['url'] ?? '')) : trim((string) $link);
        if ('' === $label || '' === $url) {
            return null;
        }
        return [
            'label'    => $label,
            'url'      => $url,
            'external' => is_array($link) && !empty($link['is_external']),
            'nofollow' => is_array($link) && !empty($link['nofollow']),
        ];
    }

    /** Course shown in the Elementor editor when nothing is upcoming. */
    protected static function sample_courses() {
        $start = date('Y-m-d', strtotime(current_time('Y-m-d') . ' +60 days'));
        $end   = date('Y-m-d', strtotime($start . ' +1 day'));
        return [[
            'id'       => 0,
            'title'    => __('Immediate Implant Placement: From Extraction to Final Restoration', 'gps-courses'),
            'url'      => home_url('/courses/'),
            'start'    => $start,
            'end'      => $end,
            'when'     => class_exists(__NAMESPACE__ . '\\Account_Dashboard') ? Account_Dashboard::date_range($start, $end) : $start,
            'ce'       => 16,
            'city'     => 'Duluth, GA',
            'category' => '',
            'status'   => 'open',
            'summary'  => __('Sample course shown in the editor only: live-patient surgery and restoration of immediate implants, with GPS faculty.', 'gps-courses'),
            'tickets'  => [],
        ]];
    }

    /** Seminar shown in the Elementor editor when none has an upcoming session. */
    protected static function sample_seminars() {
        $date = date('Y-m-d', strtotime(current_time('Y-m-d') . ' +14 days'));
        return [[
            'id'       => 0,
            'title'    => sprintf(__('GPS Monthly Seminars %s', 'gps-courses'), date('Y', strtotime($date))),
            'url'      => home_url('/monthly-seminars/'),
            'sessions' => [['number' => 1, 'date' => $date, 'start_time' => '18:00', 'end_time' => '20:00', 'topic' => __('Sample session topic', 'gps-courses'), 'ce' => 2.0]],
        ]];
    }

    /* ============================================================
     * Markup
     * ============================================================ */

    /**
     * Server-rendered carousel. Every slide is in the HTML; the script only
     * switches between them. Slide 1 is visible without JavaScript.
     *
     * @param array  $slides   from build_slides()
     * @param array  $opts     autoplay (seconds), use_h1 (bool), label (region name)
     * @param string $uid      unique id prefix
     */
    public static function render(array $slides, array $opts, $uid) {
        $count = count($slides);
        if (!$count) {
            return;
        }
        $uid      = sanitize_html_class($uid) ?: 'gps-hero';
        $autoplay = $count > 1 ? max(0, (int) ($opts['autoplay'] ?? 7)) : 0;
        $use_h1   = !empty($opts['use_h1']);
        $label    = (string) ($opts['label'] ?? '') ?: __('Featured courses and programs', 'gps-courses');

        printf(
            '<section class="gps-hero%s" id="%s" aria-roledescription="%s" aria-label="%s" data-autoplay="%d">',
            $count > 1 ? '' : ' gps-hero--single',
            esc_attr($uid),
            esc_attr__('carousel', 'gps-courses'),
            esc_attr($label),
            (int) $autoplay
        );
        echo '<div class="gps-hero__slides" aria-live="' . ($autoplay ? 'off' : 'polite') . '">';
        foreach ($slides as $i => $slide) {
            self::render_slide($slide, $i, $count, $uid, $use_h1 && 0 === $i);
        }
        echo '</div>';

        if ($count > 1) {
            self::render_nav($slides, $uid, $autoplay);
        }
        echo '</section>';
    }

    protected static function render_slide(array $slide, $i, $count, $uid, $h1) {
        $active = 0 === $i;
        printf(
            '<div class="gps-hero__slide gps-hero__slide--%s%s" id="%s" role="group" aria-roledescription="%s" aria-label="%s"%s>',
            esc_attr($slide['type']),
            $active ? ' is-active' : '',
            esc_attr($uid . '-slide-' . ($i + 1)),
            esc_attr__('slide', 'gps-courses'),
            /* translators: 1: slide number, 2: number of slides */
            esc_attr(sprintf(__('%1$d of %2$d', 'gps-courses'), $i + 1, $count)),
            $active ? '' : ' aria-hidden="true" inert'
        );

        // Image (an <img> with srcset for LCP; the first one loads eagerly)
        echo '<div class="gps-hero__media">';
        $img_attr = [
            'class'         => 'gps-hero__img',
            'alt'           => '',
            'sizes'         => '100vw',
            'decoding'      => 'async',
            'loading'       => $active ? 'eager' : 'lazy',
            'fetchpriority' => $active ? 'high' : 'low',
        ];
        $image = $slide['image'] ?? ['id' => 0, 'url' => ''];
        $html = '';
        if (!empty($image['id'])) {
            $html = wp_get_attachment_image((int) $image['id'], 'full', false, $img_attr);
        } elseif (!empty($image['url'])) {
            $html = sprintf(
                '<img class="gps-hero__img" src="%s" alt="" decoding="async" loading="%s" fetchpriority="%s">',
                esc_url($image['url']),
                esc_attr($img_attr['loading']),
                esc_attr($img_attr['fetchpriority'])
            );
        }
        if ($html && !$active) {
            // Hidden slides sit inside the viewport, so "lazy" alone would still
            // download them at once: the script sets the real src when needed.
            $html = preg_replace('/\s(src|srcset|sizes)="/', ' data-$1="', $html);
        }
        echo $html; // wp_get_attachment_image() output or escaped above
        echo '</div>';

        echo '<div class="gps-hero__panel"><div class="gps-hero__wrap"><div class="gps-hero__content">';

        if (!empty($slide['chips'])) {
            echo '<div class="gps-hero__chips">';
            foreach ($slide['chips'] as $chip) {
                printf('<span class="gps-hero__chip gps-hero__chip--%s">%s</span>', esc_attr($chip['tone']), esc_html($chip['label']));
            }
            echo '</div>';
        } elseif (!empty($slide['eyebrow'])) {
            echo '<p class="gps-hero__eyebrow">' . esc_html($slide['eyebrow']) . '</p>';
        }

        $tag  = $h1 ? 'h1' : 'h2';
        $gold = (string) $slide['gold'];
        echo '<' . $tag . ' class="gps-hero__title">';
        if ('' !== $slide['headline']) {
            echo '<span class="gps-hero__title-main">' . esc_html($slide['headline']) . '</span>';
        }
        if ('' !== $gold) {
            $len  = self::strlen($gold);
            $keep = ($len <= self::KEEP_TOGETHER_CHARS ? ' gps-hero__hl--keep' : '')
                . ($len <= self::KEEP_TOGETHER_CHARS_PHONE ? ' gps-hero__hl--keep-sm' : '');
            echo ' <span class="gps-hero__hl' . $keep . '">' . esc_html($gold) . '</span>';
        }
        echo '</' . $tag . '>';

        if ('' !== $slide['line']) {
            echo '<p class="gps-hero__line">' . esc_html($slide['line']) . '</p>';
        }
        if (!empty($slide['program'])) {
            echo '<p class="gps-hero__program">' . esc_html($slide['program']) . '</p>';
        }

        if ($slide['facts']) {
            echo '<ul class="gps-hero__facts">';
            foreach ($slide['facts'] as $fact) {
                printf(
                    '<li class="gps-hero__fact%s">%s<span class="gps-hero__fact-label">%s</span><span class="gps-hero__fact-value">%s</span></li>',
                    !empty($fact['desktop_only']) ? ' gps-hero__fact--wide' : '',
                    self::icon($fact['icon']),
                    esc_html($fact['label']),
                    esc_html($fact['value'])
                );
            }
            echo '</ul>';
        }

        if ($slide['primary'] || $slide['secondary']) {
            echo '<div class="gps-hero__actions">';
            foreach (['primary' => 'gps-hero__btn--primary', 'secondary' => 'gps-hero__btn--ghost'] as $key => $class) {
                $btn = $slide[$key];
                if (!$btn) {
                    continue;
                }
                $rel = [];
                if (!empty($btn['external'])) {
                    $rel[] = 'noopener';
                }
                if (!empty($btn['nofollow'])) {
                    $rel[] = 'nofollow';
                }
                printf(
                    '<a class="gps-hero__btn %s" href="%s"%s%s>%s</a>',
                    esc_attr($class),
                    esc_url($btn['url']),
                    !empty($btn['external']) ? ' target="_blank"' : '',
                    $rel ? ' rel="' . esc_attr(implode(' ', $rel)) . '"' : '',
                    esc_html($btn['label'])
                );
            }
            echo '</div>';
        }

        if ('' !== $slide['note']) {
            echo '<p class="gps-hero__note">' . esc_html($slide['note']) . '</p>';
        }
        echo '</div>'; // content

        $aside = $slide['aside'];
        if ($aside && 'date' === $aside['kind']) {
            // Same dates as the facts row: visual only
            printf(
                '<div class="gps-hero__datecard" aria-hidden="true"><span class="gps-hero__dc-month">%s</span><span class="gps-hero__dc-range">%s</span><span class="gps-hero__dc-year">%s</span><span class="gps-hero__dc-length">%s</span></div>',
                esc_html($aside['month']),
                esc_html($aside['range']),
                esc_html($aside['year']),
                esc_html($aside['length'])
            );
        } elseif ($aside && 'session' === $aside['kind']) {
            echo '<aside class="gps-hero__session" aria-label="' . esc_attr__('Next session', 'gps-courses') . '">';
            echo '<p class="gps-hero__session-kicker">' . esc_html__('Next session', 'gps-courses') . '</p>';
            echo '<div class="gps-hero__session-row">';
            printf(
                '<span class="gps-hero__tile"><span class="gps-hero__tile-month" aria-hidden="true">%s</span><span class="gps-hero__tile-day" aria-hidden="true">%s</span><span class="screen-reader-text gps-hero__sr">%s</span></span>',
                esc_html($aside['month']),
                esc_html($aside['day']),
                esc_html($aside['date'])
            );
            echo '<div class="gps-hero__session-text">';
            echo '<p class="gps-hero__session-topic">' . esc_html($aside['topic']) . '</p>';
            if ('' !== $aside['meta']) {
                echo '<p class="gps-hero__session-meta">' . esc_html($aside['meta']) . '</p>';
            }
            echo '</div></div>';
            printf(
                '<p class="gps-hero__session-foot"><span>%s</span><span>%s</span></p>',
                esc_html($aside['detail']),
                esc_html($aside['makeup'])
            );
            echo '</aside>';
        }

        echo '</div></div></div>'; // wrap, panel, slide
    }

    protected static function render_nav(array $slides, $uid, $autoplay) {
        $count = count($slides);
        // Hidden inline until the script runs (no JS: slide 1 only); inline so no
        // CSS optimizer can strip the rule and leave the nav hidden for good.
        echo '<div class="gps-hero__nav" style="visibility:hidden"><div class="gps-hero__wrap gps-hero__nav-wrap">';
        echo '<div class="gps-hero__tabs" role="group" aria-label="' . esc_attr__('Choose a slide', 'gps-courses') . '">';
        foreach ($slides as $i => $slide) {
            $text = sprintf('%02d · %s', $i + 1, $slide['tab']);
            printf(
                '<button type="button" class="gps-hero__tab%s" data-slide="%d" aria-controls="%s"%s><span class="gps-hero__track" aria-hidden="true"><span class="gps-hero__fill"></span></span><span class="gps-hero__tab-label">%s</span></button>',
                0 === $i ? ' is-active' : '',
                (int) $i,
                esc_attr($uid . '-slide-' . ($i + 1)),
                0 === $i ? ' aria-current="true"' : '',
                esc_html($text)
            );
        }
        echo '</div>';
        printf('<span class="gps-hero__count" aria-hidden="true"><span class="gps-hero__count-now">1</span> / %d</span>', (int) $count);

        echo '<div class="gps-hero__controls">';
        if ($autoplay) {
            printf(
                '<button type="button" class="gps-hero__ctl gps-hero__ctl--toggle" data-label-pause="%1$s" data-label-play="%2$s" aria-label="%1$s">%3$s%4$s</button>',
                esc_attr__('Pause slides', 'gps-courses'),
                esc_attr__('Play slides', 'gps-courses'),
                self::icon('pause'),
                self::icon('play')
            );
        }
        printf(
            '<button type="button" class="gps-hero__ctl gps-hero__ctl--prev" aria-label="%s">%s</button><button type="button" class="gps-hero__ctl gps-hero__ctl--next" aria-label="%s">%s</button>',
            esc_attr__('Previous slide', 'gps-courses'),
            self::icon('prev'),
            esc_attr__('Next slide', 'gps-courses'),
            self::icon('next')
        );
        echo '</div></div></div>';
    }

    protected static function strlen($s) {
        return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
    }

    /** Inline stroke icons (fixed markup, no user input). */
    protected static function icon($name) {
        $paths = [
            'calendar' => '<rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M16 2.5v4M8 2.5v4M3 9.5h18"/>',
            'pin'      => '<path d="M12 21s-7-6.1-7-11.5a7 7 0 0 1 14 0C19 14.9 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
            'award'    => '<circle cx="12" cy="9" r="6"/><path d="M8.5 13.9 7 22l5-3 5 3-1.5-8.1"/>',
            'pause'    => '<path d="M9 5v14M15 5v14"/>',
            'play'     => '<path d="M7 4.5v15l12-7.5z"/>',
            'prev'     => '<path d="M15 5l-7 7 7 7"/>',
            'next'     => '<path d="M9 5l7 7-7 7"/>',
        ];
        if (!isset($paths[$name])) {
            return '';
        }
        return '<svg class="gps-hero__icon gps-hero__icon--' . $name . '" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
    }
}
