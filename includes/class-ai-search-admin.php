<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * AI Search in the admin: the score box in the editor, the score column in
 * the course / page lists, the "AI Search" report (GPS Courses › AI Search),
 * a notice under the threshold, the live check after a save and the weekly
 * check that emails the pages that dropped.
 */
class AI_Search_Admin {

    const PAGE      = 'gps-ai-search';
    const NONCE     = 'gpsc_ais';
    const CRON      = 'gpsc_ais_weekly';
    const CRON_STEP = 'gpsc_ais_batch';
    const CRON_ONE  = 'gpsc_ais_check_one';
    const QUEUE     = 'gpsc_ais_queue';      // option: ids left in the weekly run
    const BEFORE    = 'gpsc_ais_before';     // option: scores when the weekly run started
    const BATCH     = 6;

    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_box'], 30, 2);
        add_action('admin_menu', [__CLASS__, 'menu'], 40);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_notices', [__CLASS__, 'notice']);
        add_action('wp_ajax_gpsc_ais_check', [__CLASS__, 'ajax_check']);

        foreach (['gps_event', 'page'] as $type) {
            add_filter("manage_{$type}_posts_columns", [__CLASS__, 'column']);
            add_action("manage_{$type}_posts_custom_column", [__CLASS__, 'column_cell'], 10, 2);
            add_filter("manage_edit-{$type}_sortable_columns", [__CLASS__, 'sortable']);
        }
        add_action('pre_get_posts', [__CLASS__, 'sort_query']);

        // A save gets a fresh score now and a live check a minute later (cache rebuilt by then)
        add_action('wp_insert_post', [__CLASS__, 'after_save'], 99, 2); // after Yoast saves its title / description
        add_action(self::CRON_ONE, [__CLASS__, 'check_one']);

        // Weekly run
        add_action(self::CRON, [__CLASS__, 'weekly_start']);
        add_action(self::CRON_STEP, [__CLASS__, 'weekly_step']);
        add_action('init', [__CLASS__, 'schedule']);
    }

    public static function schedule() {
        if (!wp_next_scheduled(self::CRON)) {
            // Mondays 06:30 site time
            $tz   = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
            $next = new \DateTime('next monday 06:30', $tz);
            wp_schedule_event($next->getTimestamp(), 'weekly', self::CRON);
        }
    }

    /* ============================================================
     * Editor box
     * ============================================================ */

    public static function add_box($post_type, $post = null) {
        if (!$post instanceof \WP_Post) {
            return;
        }
        $show = in_array($post_type, ['gps_event', 'page'], true)
            || ('product' === $post_type && class_exists(__NAMESPACE__ . '\\Seminar_Page') && Seminar_Page::seminar_for_product($post->ID));
        if ($show) {
            add_meta_box('gpsc-ai-search', __('AI Search', 'gps-courses'), [__CLASS__, 'render_box'], $post_type, 'side', 'high');
        }
    }

    public static function render_box($post) {
        echo '<div class="gpsc-ais" data-id="' . (int) $post->ID . '">';
        if ('publish' !== $post->post_status) {
            echo '<p class="gpsc-ais__muted">' . esc_html__('The AI Search score appears once the page is published.', 'gps-courses') . '</p></div>';
            return;
        }
        $r = AI_Search_Score::get($post->ID, 60);
        if (!$r) {
            echo '<p class="gpsc-ais__muted">' . esc_html__('This page is not scored (password protected, hidden from search or a utility page).', 'gps-courses') . '</p></div>';
            return;
        }
        echo '<div class="gpsc-ais__inner">' . self::box_html($r) . '</div>'; // escaped inside
        echo '<span class="gpsc-ais__status" role="status" aria-live="polite"></span>';
        echo '</div>';
    }

    /** Score + checklist (also returned by the AJAX check). */
    public static function box_html(array $r) {
        $tone = AI_Search_Score::tone($r['score']);
        $icon = ['ok' => '✓', 'warn' => '!', 'fail' => '✕', 'pending' => '…'];
        $sr   = ['ok' => __('Passed', 'gps-courses'), 'warn' => __('Partly', 'gps-courses'), 'fail' => __('Missing', 'gps-courses'), 'pending' => __('Not checked', 'gps-courses')];
        $html  = '<div class="gpsc-ais__score gpsc-ais__score--' . esc_attr($tone) . '"><strong>' . (int) $r['score'] . '</strong><span>/100</span></div>';
        /* translators: %d: threshold */
        $html .= '<p class="gpsc-ais__muted">' . esc_html(sprintf(__('GPS AI Search Standard v1 · target %d+', 'gps-courses'), AI_Search_Score::threshold())) . '</p>';
        $html .= '<ul class="gpsc-ais__list">';
        foreach ($r['items'] as $it) {
            if ('na' === $it['status']) {
                continue;
            }
            $html .= '<li class="gpsc-ais__item gpsc-ais__item--' . esc_attr($it['status']) . '">';
            $html .= '<span class="gpsc-ais__icon" aria-hidden="true">' . esc_html($icon[$it['status']]) . '</span>';
            $html .= '<span class="screen-reader-text">' . esc_html($sr[$it['status']]) . ': </span>';
            $html .= '<span class="gpsc-ais__body"><span class="gpsc-ais__label">' . esc_html($it['label']) . '</span>';
            if ('pending' !== $it['status']) {
                $html .= ' <span class="gpsc-ais__pts">' . esc_html(rtrim(rtrim(number_format($it['points'], 1), '0'), '.') . '/' . $it['max']) . '</span>';
            }
            if ('' !== $it['fix']) {
                $html .= '<span class="gpsc-ais__fix">' . esc_html($it['fix']) . '</span>';
            }
            $html .= '</span></li>';
        }
        $html .= '</ul>';
        $html .= '<p class="gpsc-ais__crawl">';
        if (!empty($r['crawl_at'])) {
            /* translators: %s: time ago */
            $html .= esc_html(sprintf(__('Last check as AI crawlers: %s ago', 'gps-courses'), human_time_diff((int) $r['crawl_at'])));
        } else {
            $html .= esc_html__('Not checked as AI crawlers yet.', 'gps-courses');
        }
        $html .= '</p>';
        $html .= '<button type="button" class="button gpsc-ais__check">' . esc_html__('Check as AI crawlers', 'gps-courses') . '</button>';
        $html .= '<p class="gpsc-ais__muted">' . esc_html__('The check fetches the page from this server with each crawler\'s name; Cloudflare may treat the real crawlers differently.', 'gps-courses') . '</p>';
        return $html;
    }

    public static function ajax_check() {
        check_ajax_referer(self::NONCE, 'nonce');
        $id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!$id || !current_user_can('edit_post', $id)) {
            wp_send_json_error(['message' => __('Not allowed.', 'gps-courses')], 403);
        }
        if (!AI_Search_Score::type_of($id)) {
            wp_send_json_error(['message' => __('This page is not scored.', 'gps-courses')]);
        }
        AI_Search_Score::crawl($id);
        $r = AI_Search_Score::score($id);
        wp_send_json_success([
            'html'  => self::box_html($r),
            'score' => (int) $r['score'],
            'tone'  => AI_Search_Score::tone($r['score']),
            'row'   => self::row_issues($r),
            'when'  => __('just now', 'gps-courses'),
        ]);
    }

    /* ============================================================
     * List column
     * ============================================================ */

    public static function column($cols) {
        $out = [];
        foreach ($cols as $k => $v) {
            if ('date' === $k) {
                $out['gpsc_ais'] = '<span title="' . esc_attr__('AI Search score', 'gps-courses') . '">AI</span>';
            }
            $out[$k] = $v;
        }
        if (!isset($out['gpsc_ais'])) {
            $out['gpsc_ais'] = 'AI';
        }
        return $out;
    }

    public static function column_cell($col, $post_id) {
        if ('gpsc_ais' !== $col) {
            return;
        }
        if (!AI_Search_Score::type_of($post_id)) {
            echo '<span class="gpsc-ais-badge gpsc-ais-badge--none" title="' . esc_attr__('Not scored', 'gps-courses') . '">—</span>';
            return;
        }
        $r = get_post_meta($post_id, AI_Search_Score::META_DETAIL, true);
        if (!is_array($r) || !isset($r['score'])) {
            echo '<span class="gpsc-ais-badge gpsc-ais-badge--none" title="' . esc_attr__('Not scored yet: open the page or the AI Search report.', 'gps-courses') . '">…</span>';
            return;
        }
        $title = $r['pending'] ? __('Some checks are pending: open the page and click "Check as AI crawlers".', 'gps-courses') : __('AI Search score', 'gps-courses');
        echo '<span class="gpsc-ais-badge gpsc-ais-badge--' . esc_attr(AI_Search_Score::tone($r['score'])) . '" title="' . esc_attr($title) . '">' . (int) $r['score'] . ($r['pending'] ? '*' : '') . '</span>';
    }

    public static function sortable($cols) {
        $cols['gpsc_ais'] = 'gpsc_ais';
        return $cols;
    }

    public static function sort_query($q) {
        if (is_admin() && $q->is_main_query() && 'gpsc_ais' === $q->get('orderby')) {
            // Posts without a score stay in the list (a plain meta_key would drop them)
            $q->set('meta_query', [
                'relation' => 'OR',
                'gpsc_ais' => ['key' => AI_Search_Score::META_SCORE, 'type' => 'NUMERIC', 'compare' => 'EXISTS'],
                ['key' => AI_Search_Score::META_SCORE, 'compare' => 'NOT EXISTS'],
            ]);
            $q->set('orderby', 'gpsc_ais');
        }
    }

    /* ============================================================
     * Notice under the threshold
     * ============================================================ */

    public static function notice() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || 'post' !== $screen->base || empty($_GET['post'])) {
            return;
        }
        $id = absint($_GET['post']);
        if (!$id || !AI_Search_Score::type_of($id) || !current_user_can('edit_post', $id)) {
            return;
        }
        $r = AI_Search_Score::get($id, 60);
        if (!$r || $r['score'] >= AI_Search_Score::threshold()) {
            return;
        }
        $todo = count(array_filter($r['items'], function ($i) { return in_array($i['status'], ['warn', 'fail'], true); }));
        echo '<div class="notice notice-warning"><p>';
        /* translators: 1: score, 2: threshold, 3: items to fix */
        echo esc_html(sprintf(_n('AI Search score: %1$d/100 (target %2$d). %3$d item to improve: see the AI Search box.', 'AI Search score: %1$d/100 (target %2$d). %3$d items to improve: see the AI Search box.', $todo, 'gps-courses'), $r['score'], AI_Search_Score::threshold(), $todo));
        echo '</p></div>';
    }

    /* ============================================================
     * After a save
     * ============================================================ */

    public static function after_save($post_id, $post) {
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !$post instanceof \WP_Post) {
            return;
        }
        if (!in_array($post->post_type, ['gps_event', 'page', 'product'], true) || !AI_Search_Score::type_of($post_id)) {
            return;
        }
        AI_Search_Score::score($post_id);
        if (!wp_next_scheduled(self::CRON_ONE, [(int) $post_id])) {
            wp_schedule_single_event(time() + 90, self::CRON_ONE, [(int) $post_id]);
        }
    }

    public static function check_one($post_id) {
        if (AI_Search_Score::type_of($post_id)) {
            AI_Search_Score::crawl($post_id);
            AI_Search_Score::score($post_id);
        }
    }

    /* ============================================================
     * Weekly check
     * ============================================================ */

    public static function weekly_start() {
        $ids    = AI_Search_Score::targets();
        $before = [];
        foreach ($ids as $id) {
            $d = get_post_meta($id, AI_Search_Score::META_DETAIL, true);
            if (is_array($d) && !empty($d['crawl_at']) && empty($d['pending'])) {
                $before[$id] = (int) $d['score']; // only scores from a full live check compare fairly
            }
        }
        update_option(self::BEFORE, $before, false);
        update_option(self::QUEUE, $ids, false);
        self::weekly_step();
    }

    public static function weekly_step() {
        $queue = (array) get_option(self::QUEUE, []);
        if (!$queue) {
            self::weekly_report();
            return;
        }
        $batch = array_splice($queue, 0, self::BATCH);
        // Saved and scheduled first: a batch cut short by the time limit can't stop the run
        update_option(self::QUEUE, $queue, false);
        if (!wp_next_scheduled(self::CRON_STEP)) {
            wp_schedule_single_event(time() + 60, self::CRON_STEP);
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        foreach ($batch as $id) {
            self::check_one((int) $id);
        }
    }

    /** Email the pages that dropped or are under the threshold for the first time. */
    public static function weekly_report() {
        $before = (array) get_option(self::BEFORE, []);
        $rows   = [];
        foreach (AI_Search_Score::targets() as $id) {
            $now  = (int) get_post_meta($id, AI_Search_Score::META_SCORE, true);
            $prev = isset($before[$id]) ? (int) $before[$id] : null;
            $dropped = null !== $prev && $now < $prev;
            $newly   = $now < AI_Search_Score::threshold() && (null === $prev || $prev >= AI_Search_Score::threshold());
            if ($dropped || $newly) {
                $rows[] = ['id' => $id, 'now' => $now, 'prev' => $prev];
            }
        }
        update_option('gpsc_ais_last_run', time(), false);
        if (!$rows) {
            return;
        }
        $to = class_exists(__NAMESPACE__ . '\\Settings') && method_exists(__NAMESPACE__ . '\\Settings', 'get_notification_emails')
            ? Settings::get_notification_emails()
            : [get_option('admin_email')];
        if (!$to) {
            return;
        }
        $body = '<p style="margin:0 0 16px;">' . esc_html__('The weekly AI Search check found pages that need attention:', 'gps-courses') . '</p>';
        $body .= '<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:14px;">';
        foreach ($rows as $row) {
            $r      = get_post_meta($row['id'], AI_Search_Score::META_DETAIL, true);
            $issues = is_array($r) ? self::row_issues($r) : '';
            $body  .= '<tr><td style="border-bottom:1px solid #e5e7eb;"><a href="' . esc_url(admin_url('post.php?post=' . (int) $row['id'] . '&action=edit')) . '" style="color:#0B52AC;font-weight:bold;">' . esc_html(get_the_title($row['id'])) . '</a>'
                    . ($issues ? '<br><span style="color:#5b6880;font-size:13px;">' . esc_html($issues) . '</span>' : '') . '</td>'
                    . '<td style="border-bottom:1px solid #e5e7eb;white-space:nowrap;text-align:right;"><strong>' . (int) $row['now'] . '</strong>' . (null !== $row['prev'] ? ' <span style="color:#5b6880;">(' . esc_html__('was', 'gps-courses') . ' ' . (int) $row['prev'] . ')</span>' : '') . '</td></tr>';
        }
        $body .= '</table>';
        $body .= '<p style="margin:20px 0 0;"><a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE)) . '" style="color:#0B52AC;">' . esc_html__('Open the AI Search report', 'gps-courses') . '</a></p>';
        $heading = __('AI Search: weekly check', 'gps-courses');
        $html    = class_exists(__NAMESPACE__ . '\\Emails') ? Emails::frame_open($heading) . $body . Emails::frame_close() : $body;
        /* translators: %d: pages */
        $subject = sprintf(_n('AI Search: %d page needs attention', 'AI Search: %d pages need attention', count($rows), 'gps-courses'), count($rows));
        wp_mail($to, $subject, $html, ['Content-Type: text/html; charset=UTF-8']);
    }

    /* ============================================================
     * Report page
     * ============================================================ */

    public static function menu() {
        add_submenu_page('gps-dashboard', __('AI Search', 'gps-courses'), __('AI Search', 'gps-courses'), 'edit_pages', self::PAGE, [__CLASS__, 'render_page']);
    }

    public static function assets($hook) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $ours   = (isset($_GET['page']) && self::PAGE === $_GET['page'])
            || ($screen && in_array($screen->base, ['post', 'edit'], true) && in_array($screen->post_type, ['gps_event', 'page'], true))
            || ($screen && 'post' === $screen->base && 'product' === $screen->post_type && !empty($_GET['post']) && AI_Search_Score::type_of(absint($_GET['post'])));
        if (!$ours) {
            return;
        }
        $css = GPSC_PATH . 'assets/css/admin-ai-search.css';
        $js  = GPSC_PATH . 'assets/js/admin-ai-search.js';
        wp_enqueue_style('gpsc-ai-search', GPSC_URL . 'assets/css/admin-ai-search.css', [], file_exists($css) ? (string) filemtime($css) : GPSC_VERSION);
        wp_enqueue_script('gpsc-ai-search', GPSC_URL . 'assets/js/admin-ai-search.js', [], file_exists($js) ? (string) filemtime($js) : GPSC_VERSION, true);
        wp_localize_script('gpsc-ai-search', 'gpscAis', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE),
            'i18n'  => [
                'checking' => __('Checking…', 'gps-courses'),
                'done'     => __('Checked.', 'gps-courses'),
                'error'    => __('The check failed. Try again.', 'gps-courses'),
                'all'      => __('Checking %1$d of %2$d…', 'gps-courses'),
                'allDone'  => __('All pages checked.', 'gps-courses'),
            ],
        ]);
    }

    /** Short list of what's missing, for a table row or the email. */
    public static function row_issues(array $r) {
        $out = [];
        foreach ($r['items'] as $it) {
            if (in_array($it['status'], ['warn', 'fail'], true)) {
                $out[] = $it['label'];
            } elseif ('pending' === $it['status']) {
                $out[] = __('live check pending', 'gps-courses');
            }
        }
        return implode(' · ', array_unique($out));
    }

    /** Site-wide checks: robots block, llms.txt, Organization schema. */
    protected static function site_checks() {
        $cached = get_transient('gpsc_ais_site_checks');
        if (is_array($cached) && empty($_GET['refresh'])) {
            return $cached;
        }
        $checks = [];
        $robots = wp_remote_get(home_url('/robots.txt'), ['timeout' => 15]);
        $rb     = is_wp_error($robots) ? '' : (string) wp_remote_retrieve_body($robots);
        $checks[] = [
            'ok'    => false !== strpos($rb, 'START GPS AI SEARCH BLOCK'),
            'label' => __('robots.txt welcomes AI crawlers', 'gps-courses'),
            'fix'   => __('The GPS block is missing from robots.txt: another plugin or a physical robots.txt file may be replacing it.', 'gps-courses'),
        ];
        $llms = wp_remote_get(home_url('/llms.txt'), ['timeout' => 15]);
        $lb   = is_wp_error($llms) ? '' : (string) wp_remote_retrieve_body($llms);
        $checks[] = [
            'ok'    => 200 === (int) wp_remote_retrieve_response_code($llms) && false !== strpos($lb, '## Upcoming courses'),
            'label' => __('/llms.txt is the GPS summary', 'gps-courses'),
            'fix'   => __('/llms.txt is missing or comes from another plugin. In Yoast › Site features, keep "llms.txt" turned off.', 'gps-courses'),
        ];
        $checks[] = [
            'ok'    => defined('WPSEO_VERSION'),
            'label' => __('Yoast SEO active (shared schema graph)', 'gps-courses'),
            'fix'   => __('Yoast SEO is not active: course schema prints on its own.', 'gps-courses'),
        ];
        set_transient('gpsc_ais_site_checks', $checks, HOUR_IN_SECONDS);
        return $checks;
    }

    public static function render_page() {
        $filter  = isset($_GET['show']) ? sanitize_key(wp_unslash($_GET['show'])) : 'all';
        $ids     = AI_Search_Score::targets();
        $rows    = [];
        $sum     = 0;
        $below   = 0;
        foreach ($ids as $id) {
            $r = AI_Search_Score::get($id, DAY_IN_SECONDS); // cron and saves keep them current
            if (!$r) {
                continue;
            }
            $sum += $r['score'];
            if ($r['score'] < AI_Search_Score::threshold()) {
                $below++;
            }
            if ('below' === $filter && $r['score'] >= AI_Search_Score::threshold()) {
                continue;
            }
            $rows[] = ['id' => $id, 'r' => $r];
        }
        usort($rows, function ($a, $b) { return (int) $a['r']['score'] < (int) $b['r']['score'] ? -1 : ((int) $a['r']['score'] > (int) $b['r']['score'] ? 1 : 0); });
        $avg   = $ids ? (int) round($sum / count($ids)) : 0;
        $types = ['course' => __('Course', 'gps-courses'), 'seminar' => __('Seminars', 'gps-courses'), 'page' => __('Page', 'gps-courses')];
        $last  = (int) get_option('gpsc_ais_last_run', 0);
        ?>
        <div class="wrap gpsc-ais-report">
            <h1><?php esc_html_e('AI Search', 'gps-courses'); ?></h1>
            <p class="gpsc-ais__muted"><?php esc_html_e('How ready each course and page is to be found, understood and cited by AI assistants (ChatGPT, Gemini, Perplexity, Claude, Google AI Overviews). Scored with the GPS AI Search Standard v1.', 'gps-courses'); ?></p>

            <div class="gpsc-ais-summary">
                <div class="gpsc-ais__score gpsc-ais__score--<?php echo esc_attr(AI_Search_Score::tone($avg)); ?>"><strong><?php echo (int) $avg; ?></strong><span><?php esc_html_e('site average', 'gps-courses'); ?></span></div>
                <div><strong><?php echo (int) count($ids); ?></strong> <?php esc_html_e('pages scored', 'gps-courses'); ?></div>
                <div><strong><?php echo (int) $below; ?></strong> <?php /* translators: %d: threshold */ echo esc_html(sprintf(__('under %d', 'gps-courses'), AI_Search_Score::threshold())); ?></div>
                <div class="gpsc-ais__muted"><?php echo $last ? esc_html(sprintf(__('Weekly check: %s ago', 'gps-courses'), human_time_diff($last))) : esc_html__('Weekly check: Mondays 6:30 AM', 'gps-courses'); ?></div>
            </div>

            <h2><?php esc_html_e('Site checks', 'gps-courses'); ?></h2>
            <ul class="gpsc-ais__list gpsc-ais__list--site">
                <?php foreach (self::site_checks() as $c) : ?>
                    <li class="gpsc-ais__item gpsc-ais__item--<?php echo $c['ok'] ? 'ok' : 'fail'; ?>">
                        <span class="gpsc-ais__icon" aria-hidden="true"><?php echo $c['ok'] ? '✓' : '✕'; ?></span>
                        <span class="gpsc-ais__body"><span class="gpsc-ais__label"><?php echo esc_html($c['label']); ?></span><?php if (!$c['ok']) : ?><span class="gpsc-ais__fix"><?php echo esc_html($c['fix']); ?></span><?php endif; ?></span>
                    </li>
                <?php endforeach; ?>
                <li class="gpsc-ais__item gpsc-ais__item--ok"><span class="gpsc-ais__icon" aria-hidden="true">↗</span><span class="gpsc-ais__body"><a href="<?php echo esc_url(home_url('/llms.txt')); ?>" target="_blank" rel="noopener">llms.txt</a> · <a href="<?php echo esc_url(home_url('/llms-full.txt')); ?>" target="_blank" rel="noopener">llms-full.txt</a> · <a href="<?php echo esc_url(home_url('/robots.txt')); ?>" target="_blank" rel="noopener">robots.txt</a></span></li>
            </ul>

            <div class="gpsc-ais-tools">
                <nav class="subsubsub">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE)); ?>" <?php echo 'below' !== $filter ? 'class="current"' : ''; ?>><?php esc_html_e('All', 'gps-courses'); ?></a> |
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE . '&show=below')); ?>" <?php echo 'below' === $filter ? 'class="current"' : ''; ?>><?php /* translators: %d: threshold */ echo esc_html(sprintf(__('Under %d', 'gps-courses'), AI_Search_Score::threshold())); ?></a>
                </nav>
                <button type="button" class="button button-primary gpsc-ais__check-all"><?php esc_html_e('Check all as AI crawlers', 'gps-courses'); ?></button>
                <span class="gpsc-ais__status" role="status" aria-live="polite"></span>
            </div>

            <table class="widefat striped gpsc-ais-table">
                <thead><tr>
                    <th scope="col"><?php esc_html_e('Score', 'gps-courses'); ?></th>
                    <th scope="col"><?php esc_html_e('Page', 'gps-courses'); ?></th>
                    <th scope="col"><?php esc_html_e('To improve', 'gps-courses'); ?></th>
                    <th scope="col"><?php esc_html_e('Live check', 'gps-courses'); ?></th>
                    <th scope="col"><span class="screen-reader-text"><?php esc_html_e('Actions', 'gps-courses'); ?></span></th>
                </tr></thead>
                <tbody>
                <?php if (!$rows) : ?>
                    <tr><td colspan="5"><?php esc_html_e('Nothing to show.', 'gps-courses'); ?></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row) : $r = $row['r']; $id = $row['id']; ?>
                    <tr data-id="<?php echo (int) $id; ?>">
                        <td><span class="gpsc-ais-badge gpsc-ais-badge--<?php echo esc_attr(AI_Search_Score::tone($r['score'])); ?>"><?php echo (int) $r['score']; ?></span></td>
                        <td>
                            <a href="<?php echo esc_url(get_edit_post_link($id)); ?>"><strong><?php echo esc_html(get_the_title($id)); ?></strong></a>
                            <span class="gpsc-ais__type"><?php echo esc_html($types[$r['type']]); ?></span>
                            <div class="row-actions"><a href="<?php echo esc_url(get_permalink($id)); ?>" target="_blank" rel="noopener"><?php esc_html_e('View', 'gps-courses'); ?></a></div>
                        </td>
                        <td class="gpsc-ais__issues"><?php echo esc_html(self::row_issues($r) ?: '—'); ?></td>
                        <td class="gpsc-ais__when"><?php echo $r['crawl_at'] ? esc_html(sprintf(__('%s ago', 'gps-courses'), human_time_diff((int) $r['crawl_at']))) : esc_html__('never', 'gps-courses'); ?></td>
                        <td><button type="button" class="button button-small gpsc-ais__row-check"><?php esc_html_e('Check', 'gps-courses'); ?></button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
