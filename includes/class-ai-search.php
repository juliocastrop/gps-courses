<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * AI Search: what AI assistants and AI search engines (ChatGPT, Gemini,
 * Perplexity, Claude, Google AI Overviews) need to find, read and cite the
 * site. Plan: "GPS AI Search Plan", phases F1 and F2.
 *
 *   - robots.txt: explicit rules for AI crawlers (search and training)
 *   - /llms.txt and /llms-full.txt built from gpsc_catalog(), the course
 *     pages' data and the seminar page's data
 *   - sitemap lastmod: a course (or the seminar product) is "modified" when
 *     its tickets, agenda or sessions change, not only when its post is saved
 *   - schema: the course and seminar pages' nodes join Yoast's @graph (one
 *     graph, shared Organization / WebPage ids) instead of separate scripts;
 *     the Organization becomes an EducationalOrganization with its address
 */
class AI_Search {

    /** Crawlers that fetch pages to answer and cite (never blocked). */
    const SEARCH_BOTS = [
        'OAI-SearchBot', 'ChatGPT-User', 'PerplexityBot', 'Perplexity-User',
        'Claude-SearchBot', 'Claude-User', 'DuckAssistBot', 'MistralAI-User',
    ];

    /** Crawlers that collect text for model training (allowed by default). */
    const TRAINING_BOTS = [
        'GPTBot', 'ClaudeBot', 'Google-Extended', 'Applebot-Extended',
        'CCBot', 'meta-externalagent', 'Amazonbot',
    ];

    const LLMS_TTL = HOUR_IN_SECONDS;

    /** @var bool Our nodes went into Yoast's graph on this request. */
    protected static $merged = false;

    /** @var array<int, true> Posts already touched in this request. */
    protected static $touched = [];

    public static function init() {
        add_filter('robots_txt', [__CLASS__, 'robots_txt'], 100000, 2);
        add_action('wp_loaded', [__CLASS__, 'serve_llms'], 1);

        // Sitemap lastmod follows the course data
        add_action('save_post_gps_ticket', [__CLASS__, 'touch_ticket_event'], 50);
        add_action('save_post_gps_schedule', [__CLASS__, 'touch_schedule_event'], 50);
        add_action('gpsc_ticket_activated', [__CLASS__, 'touch_ticket_event'], 50);
        add_action('save_post_gps_seminar', [__CLASS__, 'touch_seminar_product'], 50);

        // One schema graph with Yoast
        add_filter('wpseo_schema_graph', [__CLASS__, 'merge_graph'], 20, 2);
        add_filter('wpseo_schema_organization', [__CLASS__, 'organization'], 20);
        add_filter('wpseo_schema_webpage', [__CLASS__, 'webpage'], 20);
    }

    /* ============================================================
     * robots.txt
     * ============================================================ */

    public static function allow_training() {
        return (bool) apply_filters('gpsc_ai_allow_training', true);
    }

    /** Paths no crawler needs (same spirit as the WooCommerce / WordPress defaults). */
    protected static function private_paths() {
        return apply_filters('gpsc_ai_robots_disallow', [
            '/wp-admin/',
            '/cart/',
            '/checkout/',
            '/my-account/',
            '/*?add-to-cart=',
            '/*?*add-to-cart=',
            '/wp-content/uploads/wc-logs/',
            '/wp-content/uploads/woocommerce_uploads/',
        ]);
    }

    public static function robots_txt($output, $public) {
        if (!$public) {
            return $output; // "Discourage search engines" is on: leave WordPress' rules alone
        }
        $bots = self::SEARCH_BOTS;
        if (self::allow_training()) {
            $bots = array_merge($bots, self::TRAINING_BOTS);
        }
        $lines   = ['', '# START GPS AI SEARCH BLOCK', '# AI assistants and AI search: welcome. Site summary for language models: ' . home_url('/llms.txt')];
        foreach ($bots as $bot) {
            $lines[] = 'User-agent: ' . $bot;
        }
        foreach (self::private_paths() as $path) {
            $lines[] = 'Disallow: ' . $path;
        }
        $lines[] = 'Allow: /wp-admin/admin-ajax.php';
        $lines[] = 'Allow: /';
        if (!self::allow_training()) {
            $lines[] = '';
            foreach (self::TRAINING_BOTS as $bot) {
                $lines[] = 'User-agent: ' . $bot;
            }
            $lines[] = 'Disallow: /';
        }
        $lines[] = '# END GPS AI SEARCH BLOCK';
        return rtrim((string) $output) . "\n" . implode("\n", $lines) . "\n";
    }

    /* ============================================================
     * /llms.txt and /llms-full.txt
     * ============================================================ */

    /** 'llms' | 'llms-full' | '' for the current request path. */
    protected static function llms_request() {
        if (empty($_SERVER['REQUEST_URI'])) {
            return '';
        }
        $path = (string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH);
        $base = rtrim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');
        if (0 === strpos($path, $base)) {
            $path = substr($path, strlen($base));
        }
        if ('/llms.txt' === $path) {
            return 'llms';
        }
        if ('/llms-full.txt' === $path) {
            return 'llms-full';
        }
        return '';
    }

    public static function serve_llms() {
        $which = self::llms_request();
        if ('' === $which || is_admin()) {
            return;
        }
        $key  = 'gpsc_' . str_replace('-', '_', $which) . '_' . md5(current_time('Y-m-d') . '|' . (int) get_option('gpsc_catalog_ver', 0) . '|' . GPSC_VERSION);
        $body = get_transient($key);
        if (!is_string($body) || '' === $body) {
            $body = 'llms' === $which ? self::llms_txt() : self::llms_full_txt();
            set_transient($key, $body, self::LLMS_TTL);
        }
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true); // always current: built from the live catalog
        }
        status_header(200);
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex');
        header('Cache-Control: public, max-age=900');
        echo $body; // plain text
        exit;
    }

    /** Text for a plain-text file: no tags, entities decoded, one line. */
    protected static function plain($s) {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags((string) $s), ENT_QUOTES, 'UTF-8')));
    }

    protected static function money($amount) {
        if (null === $amount || '' === $amount) {
            return '';
        }
        $amount = (float) $amount;
        return '$' . number_format($amount, floor($amount) == $amount ? 0 : 2);
    }

    protected static function status_label($status) {
        $map = [
            'open'        => 'Registration open',
            'sold_out'    => 'Sold out (waitlist available)',
            'soon'        => 'Registration opens soon',
            'closed'      => 'Registration closed',
            'in_progress' => 'Happening now',
        ];
        return isset($map[$status]) ? $map[$status] : '';
    }

    /** The product page that sells a seminar (the seminar post is not public). */
    protected static function seminar_url($seminar_id) {
        $product = (int) get_post_meta($seminar_id, '_gps_seminar_product_id', true);
        if ($product && 'publish' === get_post_status($product)) {
            return get_permalink($product);
        }
        return get_permalink($seminar_id);
    }

    protected static function site_lines() {
        $loc = class_exists(__NAMESPACE__ . '\\Seminar_Page')
            ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) get_option(Seminar_Page::OPT_LOCATION, Seminar_Page::default_location())))))
            : [];
        $email = class_exists(__NAMESPACE__ . '\\Course_Page') ? Course_Page::contact_email() : get_option('admin_email');
        $out   = [];
        if ($loc) {
            $out[] = '- Location: ' . implode(', ', $loc);
        }
        $out[] = '- Contact: ' . $email;
        $out[] = '- Website: ' . home_url('/');
        return $out;
    }

    protected static function key_pages() {
        $pages = [];
        $archive = get_post_type_archive_link('gps_event');
        if ($archive) {
            $pages[] = '- [All courses](' . $archive . '): upcoming hands-on courses with dates, CE credits and prices';
        }
        foreach ([
            'about-us' => 'About GPS: the training center, its team and its study club',
            'contact'  => 'Contact GPS Dental Training',
        ] as $slug => $label) {
            $p = get_page_by_path($slug);
            if ($p && 'publish' === $p->post_status) {
                $pages[] = '- [' . self::plain(get_the_title($p)) . '](' . get_permalink($p) . '): ' . $label;
            }
        }
        return apply_filters('gpsc_llms_key_pages', $pages);
    }

    protected static function intro() {
        $name = self::plain(get_bloginfo('name'));
        $desc = apply_filters('gpsc_llms_description', 'Hands-on continuing education (CE) courses and monthly study-club seminars for dentists in implant, restorative and digital dentistry, in Duluth, Georgia (USA). Founded by Dr. Carlos Castro, DDS, FACP, board-certified prosthodontist.');
        return ['# ' . $name, '', '> ' . $desc, ''];
    }

    public static function llms_txt() {
        $cat   = function_exists('gpsc_catalog') ? gpsc_catalog() : ['courses' => [], 'seminars' => []];
        $lines = self::intro();
        $lines = array_merge($lines, self::site_lines());
        $lines[] = '- Updated: ' . current_time('Y-m-d');
        $lines[] = '';

        $lines[] = '## Upcoming courses';
        $lines[] = '';
        if (!$cat['courses']) {
            $lines[] = '- No course dates are published right now. See ' . (get_post_type_archive_link('gps_event') ?: home_url('/')) . ' for updates.';
        }
        foreach ($cat['courses'] as $c) {
            $bits = array_filter([
                self::plain($c['when']),
                trim(self::plain($c['venue']) . ($c['city'] ? ', ' . self::plain($c['city']) : ''), ', '),
                $c['ce'] ? rtrim(rtrim(number_format((float) $c['ce'], 2), '0'), '.') . ' CE credits' : '',
                null !== $c['price'] ? 'from ' . self::money($c['price']) : '',
                self::status_label($c['status']),
            ]);
            $line = '- [' . self::plain($c['title']) . '](' . $c['url'] . '): ' . implode(' · ', $bits);
            $summary = self::plain(get_post_meta($c['id'], '_gps_tagline', true) ?: $c['summary']);
            if ('' !== $summary) {
                $line .= '. ' . rtrim($summary, '.') . '.';
            }
            $lines[] = $line;
        }
        $lines[] = '';

        if (!empty($cat['seminars'])) {
            $lines[] = '## Monthly seminars (study club)';
            $lines[] = '';
            foreach ($cat['seminars'] as $s) {
                $count = count($s['sessions']);
                $next  = $s['sessions'] ? $s['sessions'][0] : null;
                $bits  = array_filter([
                    '10-session program, one evening a month, 2 CE credits per session (20 CE)',
                    'tuition ' . self::money($s['tuition']),
                    'join anytime: membership starts at the next session',
                    $next ? 'next session ' . date_i18n('M j, Y', strtotime($next['date'])) . ('' !== $next['topic'] ? ' (' . self::plain($next['topic']) . ')' : '') : '',
                    $count ? '' : 'next cycle dates coming soon',
                ]);
                $product = (int) get_post_meta($s['id'], '_gps_seminar_product_id', true);
                $name    = $product && 'publish' === get_post_status($product) ? get_the_title($product) : $s['title'];
                $lines[] = '- [' . self::plain($name) . '](' . self::seminar_url($s['id']) . '): ' . implode(' · ', $bits);
            }
            $lines[] = '';
        }

        $pages = self::key_pages();
        if ($pages) {
            $lines[] = '## Key pages';
            $lines[] = '';
            $lines   = array_merge($lines, $pages);
            $lines[] = '';
        }

        $lines[] = '## Optional';
        $lines[] = '';
        $lines[] = '- [Full details](' . home_url('/llms-full.txt') . '): every course with schedule, instructors, learning objectives and FAQs';
        return apply_filters('gpsc_llms_txt', implode("\n", $lines) . "\n");
    }

    public static function llms_full_txt() {
        $cat   = function_exists('gpsc_catalog') ? gpsc_catalog() : ['courses' => [], 'seminars' => []];
        $lines = self::intro();
        $lines = array_merge($lines, self::site_lines());
        $lines[] = '- Updated: ' . current_time('Y-m-d');
        $lines[] = '';

        foreach ($cat['courses'] as $c) {
            $d = class_exists(__NAMESPACE__ . '\\Course_Page') ? Course_Page::data((int) $c['id']) : null;
            $lines[] = '## ' . self::plain($c['title']);
            $lines[] = '';
            $lines[] = '- URL: ' . $c['url'];
            if ($c['when']) {
                $lines[] = '- Dates: ' . self::plain($c['when']) . ($d && $d['times'] ? ' (' . self::plain($d['times']) . ')' : '');
            }
            $where = $d ? array_filter([$d['venue'], $d['address'], $d['line2']]) : array_filter([$c['venue'], $c['city']]);
            if ($where) {
                $lines[] = '- Location: ' . self::plain(implode(', ', $where));
            }
            if ($c['ce']) {
                $lines[] = '- CE credits: ' . rtrim(rtrim(number_format((float) $c['ce'], 2), '0'), '.');
            }
            foreach ($c['tickets'] as $t) {
                $lines[] = '- Ticket: ' . self::plain($t['name']) . (null !== $t['price'] ? ' — ' . self::money($t['price']) : '') . ($t['sold_out'] ? ' (sold out)' : (null !== $t['seats_left'] ? ' (' . (int) $t['seats_left'] . ' seats left)' : ''));
            }
            if ($label = self::status_label($c['status'])) {
                $lines[] = '- Status: ' . $label;
            }
            if ($d) {
                foreach (['format' => 'Format', 'audience' => 'Who should attend'] as $k => $label) {
                    if (!empty($d[$k])) {
                        $lines[] = '- ' . $label . ': ' . self::plain($d[$k]);
                    }
                }
                if (!empty($d['included'])) {
                    $lines[] = '- Included: ' . self::plain(implode(', ', (array) $d['included']));
                }
                foreach ($d['speakers'] as $s) {
                    $lines[] = '- Instructor: ' . self::plain($s['full']) . ('' !== $s['title'] ? ' — ' . self::plain($s['title']) : '');
                }
            }
            $lines[] = '';
            $about = $d && '' !== $d['tagline'] ? $d['tagline'] : $c['description'];
            if ('' !== self::plain($about)) {
                $lines[] = self::plain($about);
                $lines[] = '';
            }
            if ('' !== self::plain($c['description']) && $about !== $c['description']) {
                $lines[] = self::plain($c['description']);
                $lines[] = '';
            }
            $objectives = $d ? $d['objectives'] : array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $c['objectives'])));
            if ($objectives) {
                $lines[] = '### Learning objectives';
                $lines[] = '';
                foreach ($objectives as $o) {
                    $lines[] = '- ' . self::plain($o);
                }
                $lines[] = '';
            }
            if ($d && $d['agenda']) {
                $lines[] = '### Agenda';
                $lines[] = '';
                foreach ($d['agenda'] as $day) {
                    $lines[] = '- ' . self::plain(trim($day['label'] . ($day['long'] ? ' · ' . $day['long'] : ''))) . ':';
                    foreach ($day['rows'] as $row) {
                        $lines[] = '  - ' . self::plain($row['start'] . ($row['until'] ? '–' . $row['until'] : '') . ' ' . $row['title']);
                    }
                }
                $lines[] = '';
            }
            if ($d && $d['faqs']) {
                $lines[] = '### FAQ';
                $lines[] = '';
                foreach ($d['faqs'] as $f) {
                    $lines[] = '- Q: ' . self::plain($f['q']);
                    $lines[] = '  A: ' . self::plain($f['a']);
                }
                $lines[] = '';
            }
        }

        // Monthly seminars: the product page data
        if (!empty($cat['seminars']) && class_exists(__NAMESPACE__ . '\\Seminar_Page')) {
            $seen = [];
            foreach ($cat['seminars'] as $s) {
                $product = (int) get_post_meta($s['id'], '_gps_seminar_product_id', true);
                if (!$product || isset($seen[$product]) || !Seminar_Page::seminar_for_product($product)) {
                    continue;
                }
                $seen[$product] = true;
                $d = Seminar_Page::data($product);
                $lines[] = '## ' . self::plain($d['title']);
                $lines[] = '';
                $lines[] = '- URL: ' . $d['url'];
                $lines[] = '- Program: ' . $d['sessions'] . ' sessions, once a month, ' . $d['ce_each'] . ' CE credits per session (' . $d['ce_total'] . ' CE)';
                $lines[] = '- Tuition: ' . self::money($d['price']) . ' (not refundable or transferable)';
                $lines[] = '- Enrollment: open all year; membership starts at the next session and ends after the ' . $d['sessions'] . 'th';
                if ($d['venue'] || $d['street']) {
                    $lines[] = '- Location: ' . self::plain(implode(', ', array_filter([$d['venue'], $d['street'], $d['cityline']])));
                }
                if ($d['moderator']) {
                    $lines[] = '- Moderator: ' . self::plain($d['moderator']['full']) . ('' !== $d['moderator']['title'] ? ' — ' . self::plain($d['moderator']['title']) : '');
                }
                $lines[] = '';
                $lines[] = self::plain($d['tagline']);
                $lines[] = '';
                foreach (array_merge([$d['cycle']], $d['later']) as $cycle) {
                    $upcoming = array_filter($cycle['sessions'], function ($x) { return 'done' !== $x['status']; });
                    if (!$upcoming) {
                        continue;
                    }
                    $lines[] = '### ' . self::plain($cycle['title']) . ' — upcoming sessions';
                    $lines[] = '';
                    foreach ($upcoming as $x) {
                        $lines[] = '- ' . $x['full'] . ($x['time'] ? ', ' . $x['time'] : '') . ': ' . ('' !== $x['topic'] ? self::plain($x['topic']) : 'topic to be announced') . ($x['single'] ? ' (single-session ticket ' . self::money($x['single']['price']) . ')' : '');
                    }
                    $lines[] = '';
                }
                if ($d['topics']) {
                    $lines[] = '### Topics';
                    $lines[] = '';
                    foreach ($d['topics'] as $t) {
                        $lines[] = '- ' . self::plain($t['title']) . ('' !== $t['text'] ? ': ' . self::plain($t['text']) : '');
                    }
                    $lines[] = '';
                }
                if ($d['faqs']) {
                    $lines[] = '### Policies';
                    $lines[] = '';
                    foreach ($d['faqs'] as $f) {
                        $lines[] = '- Q: ' . self::plain($f['q']);
                        $lines[] = '  A: ' . self::plain($f['a']);
                    }
                    $lines[] = '';
                }
            }
        }

        $pages = self::key_pages();
        if ($pages) {
            $lines[] = '## Key pages';
            $lines[] = '';
            $lines   = array_merge($lines, $pages);
            $lines[] = '';
        }
        return apply_filters('gpsc_llms_full_txt', implode("\n", $lines) . "\n");
    }

    /* ============================================================
     * Sitemap lastmod
     * ============================================================ */

    /**
     * Bump a post's modified date (and Yoast's indexable, which feeds the
     * sitemap) without changing anything else. Save handlers that read form
     * fields check their own nonces, so nothing else is written.
     */
    public static function touch($post_id) {
        $post_id = (int) $post_id;
        if (!$post_id || isset(self::$touched[$post_id]) || 'publish' !== get_post_status($post_id)) {
            return;
        }
        self::$touched[$post_id] = true;
        wp_update_post(['ID' => $post_id]);
    }

    public static function touch_ticket_event($ticket_id) {
        if (wp_is_post_revision($ticket_id)) {
            return;
        }
        self::touch((int) get_post_meta((int) $ticket_id, '_gps_event_id', true));
    }

    public static function touch_schedule_event($schedule_id) {
        if (wp_is_post_revision($schedule_id)) {
            return;
        }
        self::touch((int) get_post_meta((int) $schedule_id, '_gps_event_id', true));
    }

    public static function touch_seminar_product($seminar_id) {
        if (wp_is_post_revision($seminar_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        self::touch((int) get_post_meta((int) $seminar_id, '_gps_seminar_product_id', true));
    }

    /* ============================================================
     * Schema: one graph with Yoast
     * ============================================================ */

    public static function org_id() {
        return home_url('/#organization');
    }

    /** The Organization: a reference into Yoast's graph, or a full node without Yoast. */
    public static function org_ref() {
        if (defined('WPSEO_VERSION')) {
            return ['@id' => self::org_id()];
        }
        return ['@type' => 'EducationalOrganization', 'name' => self::plain(get_bloginfo('name')), 'url' => home_url('/')];
    }

    /** Stable @id for a Speaker profile. */
    public static function person_id($speaker_id) {
        return home_url('/#/schema/gps-speaker/' . (int) $speaker_id);
    }

    /** A Person node from Course_Page::speaker() data. */
    public static function person_node(array $s) {
        $p = [
            '@type' => 'Person',
            '@id'   => self::person_id($s['id']),
            'name'  => self::plain($s['full']),
        ];
        if ('' !== $s['title']) {
            $p['jobTitle'] = self::plain($s['title']);
        }
        if ('' !== $s['cred']) {
            $p['honorificSuffix'] = self::plain($s['cred']);
        }
        if ($s['photo'] && ($u = wp_get_attachment_image_url($s['photo'], 'medium'))) {
            $p['image'] = $u;
        }
        if ('' !== $s['short']) {
            $p['description'] = self::plain($s['short']);
        }
        if ('' !== $s['url']) {
            $p['url'] = $s['url'];
        }
        if ('' !== $s['linkedin']) {
            $p['sameAs'] = [$s['linkedin']];
        }
        $p['affiliation'] = self::org_ref();
        return $p;
    }

    /** Course / seminar nodes for the current page, or []. */
    protected static function page_nodes() {
        $id = (int) get_queried_object_id();
        if (class_exists(__NAMESPACE__ . '\\Course_Page') && Course_Page::is_active()) {
            return Course_Page::schema_nodes(Course_Page::data($id));
        }
        if (class_exists(__NAMESPACE__ . '\\Seminar_Page') && Seminar_Page::is_active()) {
            return Seminar_Page::schema_nodes(Seminar_Page::data($id));
        }
        return [];
    }

    public static function merge_graph($graph, $context = null) {
        $nodes = self::page_nodes();
        if (!$nodes || !is_array($graph)) {
            return $graph;
        }
        self::$merged = true;
        foreach ($nodes as $node) {
            $graph[] = $node;
        }
        return $graph;
    }

    /** Did Yoast print our nodes on this request? (Yoast prints at wp_head priority 1.) */
    public static function merged() {
        return self::$merged;
    }

    /** Fallback when Yoast isn't there: one script per node. */
    public static function scripts(array $nodes) {
        $out = '';
        foreach ($nodes as $node) {
            $node = ['@context' => 'https://schema.org'] + $node;
            $out .= '<script type="application/ld+json">' . wp_json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' . "\n";
        }
        return $out;
    }

    /** Yoast's Organization → also an EducationalOrganization, with address and contact. */
    public static function organization($data) {
        if (!is_array($data)) {
            return $data;
        }
        $types = isset($data['@type']) ? (array) $data['@type'] : ['Organization'];
        if (!in_array('EducationalOrganization', $types, true)) {
            $types[] = 'EducationalOrganization';
        }
        $data['@type'] = $types;
        if (class_exists(__NAMESPACE__ . '\\Seminar_Page')) {
            $loc = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) get_option(Seminar_Page::OPT_LOCATION, Seminar_Page::default_location())))));
            if (count($loc) >= 3 && empty($data['address'])) {
                $city = $loc[2];
                $addr = ['@type' => 'PostalAddress', 'streetAddress' => $loc[1], 'addressCountry' => 'US'];
                if (preg_match('/^(.+?),\s*([A-Z]{2})\s+(\d{5}(?:-\d{4})?)$/', $city, $m)) {
                    $addr['addressLocality'] = $m[1];
                    $addr['addressRegion']   = $m[2];
                    $addr['postalCode']      = $m[3];
                } else {
                    $addr['addressLocality'] = $city;
                }
                $data['address'] = $addr;
            }
        }
        if (empty($data['email']) && class_exists(__NAMESPACE__ . '\\Course_Page')) {
            $data['email'] = Course_Page::contact_email();
        }
        if (empty($data['description'])) {
            $data['description'] = apply_filters('gpsc_llms_description', 'Hands-on continuing education (CE) courses and monthly study-club seminars for dentists in implant, restorative and digital dentistry, in Duluth, Georgia (USA). Founded by Dr. Carlos Castro, DDS, FACP, board-certified prosthodontist.');
        }
        if (empty($data['knowsAbout'])) {
            $data['knowsAbout'] = ['Dental implants', 'Prosthodontics', 'Restorative dentistry', 'Digital dentistry', 'Continuing dental education'];
        }
        return $data;
    }

    /** The course / seminar is the page's main entity. */
    public static function webpage($data) {
        if (!is_array($data)) {
            return $data;
        }
        $id = (int) get_queried_object_id();
        if ((class_exists(__NAMESPACE__ . '\\Course_Page') && Course_Page::is_active()) || (class_exists(__NAMESPACE__ . '\\Seminar_Page') && Seminar_Page::is_active())) {
            $data['mainEntity'] = ['@id' => get_permalink($id) . '#course'];
        }
        return $data;
    }
}
