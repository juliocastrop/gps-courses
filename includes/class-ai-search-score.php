<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * AI Search score: GPS AI Search Standard v1 (10 criteria, 100 points) for
 * courses, the monthly seminars product page and site pages.
 *
 * Two sources:
 *   - the post's own data (dates, price, instructor, summary, FAQ, Yoast
 *     title / description, schema the page will print, sitemap / llms.txt)
 *   - a live check (crawl): the page fetched as GPTBot, ClaudeBot and
 *     PerplexityBot, read for status, noindex, H1 / heading order, image
 *     alt text and the JSON-LD actually printed. Stored per post; it runs
 *     from the editor box, the report, after a save and every week.
 *
 * A criterion that doesn't apply (no FAQ on a plain page, no price on a past
 * course) is left out and the score is scaled to 100. Until the first live
 * check, criteria that need it show as "not checked" and are left out too.
 *
 * Admin UI: AI_Search_Admin.
 */
class AI_Search_Score {

    const META_SCORE   = '_gpsc_ais_score';    // int 0-100 (list column sorting)
    const META_DETAIL  = '_gpsc_ais_detail';   // array: score, items, at
    const META_CRAWL   = '_gpsc_ais_crawl';    // array: last live check
    const THRESHOLD    = 90;

    const BOTS = [
        'GPTBot'        => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)',
        'ClaudeBot'     => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)',
        'PerplexityBot' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)',
    ];

    /** Utility pages that are not meant to be found (login, password reset…). */
    const SKIP_SLUGS = ['login', 'register', 'password-reset', 'registration-confirmation', 'certificate', 'access-account', 'booking', 'referrals'];

    public static function threshold() {
        return (int) apply_filters('gpsc_ais_threshold', self::THRESHOLD);
    }

    /* ============================================================
     * Targets
     * ============================================================ */

    /** 'course' | 'seminar' | 'page' | '' (not scored). */
    public static function type_of($post_id) {
        $post = get_post((int) $post_id);
        if (!$post || 'publish' !== $post->post_status || '' !== (string) $post->post_password) {
            return '';
        }
        if ('gps_event' === $post->post_type) {
            return 'course';
        }
        if ('product' === $post->post_type && class_exists(__NAMESPACE__ . '\\Seminar_Page') && Seminar_Page::seminar_for_product($post->ID)) {
            return 'seminar';
        }
        if ('page' === $post->post_type && !self::skip_page($post)) {
            return 'page';
        }
        return '';
    }

    protected static function skip_page($post) {
        if (function_exists('wc_get_page_id')) {
            foreach (['cart', 'checkout', 'myaccount'] as $p) {
                if ((int) wc_get_page_id($p) === (int) $post->ID) {
                    return true;
                }
            }
        }
        if (in_array($post->post_name, (array) apply_filters('gpsc_ais_skip_slugs', self::SKIP_SLUGS), true)) {
            return true;
        }
        // Legal pages: needed, but not what people ask AI assistants about
        $legal = [(int) get_option('wp_page_for_privacy_policy'), function_exists('wc_terms_and_conditions_page_id') ? (int) wc_terms_and_conditions_page_id() : 0];
        if (in_array((int) $post->ID, array_filter($legal), true) || preg_match('/^(privacy-policy|terms-and-conditions|terms|privacy)(-\d+)?$/', $post->post_name)) {
            return true;
        }
        // A page that already tells crawlers "noindex" (a plugin or the theme) is hidden on purpose
        $crawl = get_post_meta($post->ID, self::META_CRAWL, true);
        if (is_array($crawl) && !empty($crawl['html']['noindex'])) {
            return true;
        }
        return self::noindex($post->ID);
    }

    /** Yoast "Allow search engines to show this in results?" = No, per post or per type. */
    public static function noindex($post_id) {
        $own = (string) get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true); // '1' hide, '2' show, '' / '0' default
        if ('1' === $own) {
            return true;
        }
        if ('2' === $own) {
            return false;
        }
        $titles = get_option('wpseo_titles', []);
        return is_array($titles) && !empty($titles['noindex-' . get_post_type($post_id)]);
    }

    /** Every post the report scores: courses, seminar products, pages. */
    public static function targets() {
        $base = [
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
        $ids = array_merge(
            get_posts($base + ['post_type' => ['gps_event', 'page']]),
            get_posts($base + ['post_type' => 'product', 'meta_query' => [['key' => '_gps_seminar_id', 'compare' => 'EXISTS']]])
        );
        $ids = array_map('intval', $ids);
        if ($ids) {
            _prime_post_caches($ids, false, true);
        }
        return array_values(array_filter($ids, function ($id) {
            return '' !== self::type_of($id);
        }));
    }

    /* ============================================================
     * Live check
     * ============================================================ */

    /** Fetch the page as AI crawlers and read what they get. */
    public static function crawl($post_id) {
        $url = get_permalink($post_id);
        if (!$url) {
            return null;
        }
        $result = ['at' => time(), 'url' => $url, 'bots' => [], 'html' => null];
        $html   = '';
        foreach (self::BOTS as $bot => $ua) {
            $res = wp_remote_get($url, [
                'user-agent'          => $ua,
                'timeout'             => 20,
                'redirection'         => 3,
                'limit_response_size' => 2 * MB_IN_BYTES,
                'headers'             => ['Accept' => 'text/html'],
            ]);
            if (is_wp_error($res)) {
                $result['bots'][$bot] = ['code' => 0, 'error' => $res->get_error_message()];
                continue;
            }
            $code = (int) wp_remote_retrieve_response_code($res);
            $result['bots'][$bot] = [
                'code'    => $code,
                'noindex' => false !== stripos(implode(',', (array) wp_remote_retrieve_header($res, 'x-robots-tag')), 'noindex'),
            ];
            if ('' === $html && 200 === $code) {
                $html = (string) wp_remote_retrieve_body($res);
            }
        }
        if ('' !== $html) {
            $result['html'] = self::read_html($html);
        }
        update_post_meta($post_id, self::META_CRAWL, $result);
        return $result;
    }

    /** What matters in the HTML: robots meta, title, description, headings, images, JSON-LD. */
    public static function read_html($html) {
        $out = [
            'noindex' => false, 'title' => '', 'desc' => '', 'h1' => 0, 'order_ok' => true,
            'img' => 0, 'img_alt' => 0, 'img_noalt' => 0, 'types' => [], 'faq_q' => 0,
        ];
        if (!class_exists('\\DOMDocument')) {
            return $out;
        }
        $prev = libxml_use_internal_errors(true);
        $dom  = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $xp = new \DOMXPath($dom);

        foreach ($xp->query('//meta[@name="robots"]/@content') as $a) {
            if (false !== stripos($a->nodeValue, 'noindex')) {
                $out['noindex'] = true;
            }
        }
        $t = $xp->query('//title');
        if ($t->length) {
            $out['title'] = trim($t->item(0)->textContent);
        }
        $m = $xp->query('//meta[@name="description"]/@content');
        if ($m->length) {
            $out['desc'] = trim($m->item(0)->nodeValue);
        }
        // Headings of the page itself, in document order (the site header / footer
        // are the same everywhere; hidden headings still count: crawlers read them)
        $last  = 0;
        $chrome = 'ancestor::header or ancestor::footer or ancestor::*[@data-elementor-type="header" or @data-elementor-type="footer"]';
        foreach ($xp->query('//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6][not(' . $chrome . ')]') as $h) {
            $level = (int) substr($h->nodeName, 1);
            if (1 === $level) {
                $out['h1']++;
            }
            if ($last && $level > $last + 1) {
                $out['order_ok'] = false; // e.g. h2 → h4
            }
            $last = $level;
        }
        // Content images: an empty alt counts as missing (AI reads no description),
        // except icons (≤ 48 px) and tracking pixels
        foreach ($xp->query('//body//img[not(' . $chrome . ')]') as $img) {
            $src = (string) $img->getAttribute('src');
            if (0 === strpos($src, 'data:')) {
                $src = (string) ($img->getAttribute('data-src') ?: $img->getAttribute('data-lazy-src')); // lazy-loaded
            }
            if ('' === $src || preg_match('#/pixel|wp-emoji|\.gif(\?|$)#i', $src)) {
                continue;
            }
            $w = (int) $img->getAttribute('width');
            $h = (int) $img->getAttribute('height');
            if (($w && $w <= 48) || ($h && $h <= 48)) {
                continue;
            }
            $out['img']++;
            if ('' !== trim((string) $img->getAttribute('alt'))) {
                $out['img_alt']++;
            } else {
                $out['img_noalt']++;
            }
        }
        foreach ($xp->query('//script[@type="application/ld+json"]') as $s) {
            $j = json_decode($s->textContent, true);
            if (!is_array($j)) {
                continue;
            }
            $nodes = isset($j['@graph']) ? $j['@graph'] : (isset($j[0]) ? $j : [$j]);
            foreach ($nodes as $n) {
                if (!is_array($n) || empty($n['@type'])) {
                    continue;
                }
                foreach ((array) $n['@type'] as $type) {
                    $out['types'][$type] = true;
                }
                if (!empty($n['hasCourseInstance'])) {
                    $out['types']['CourseInstance'] = true;
                }
                if (!empty($n['offers'])) {
                    $out['types']['Offer'] = true;
                }
                if (in_array('FAQPage', (array) $n['@type'], true) && !empty($n['mainEntity'])) {
                    $q = $n['mainEntity'];
                    $out['faq_q'] = max($out['faq_q'], isset($q['@type']) ? 1 : count((array) $q));
                }
            }
        }
        $out['types'] = array_keys($out['types']);
        return $out;
    }

    /* ============================================================
     * Score
     * ============================================================ */

    /**
     * Score one post.
     *
     * @return array|null ['score', 'type', 'items' => [[key, label, points, max, status, fix]], 'crawl_at']
     */
    public static function score($post_id, $save = true) {
        $post_id = (int) $post_id;
        $type    = self::type_of($post_id);
        if ('' === $type) {
            return null;
        }
        $crawl = get_post_meta($post_id, self::META_CRAWL, true);
        $crawl = is_array($crawl) ? $crawl : null;
        $html  = $crawl && !empty($crawl['html']) ? $crawl['html'] : null;

        $ctx = ['id' => $post_id, 'type' => $type, 'crawl' => $crawl, 'html' => $html, 'd' => null];
        if ('course' === $type && class_exists(__NAMESPACE__ . '\\Course_Page')) {
            $ctx['d']        = Course_Page::data($post_id);
            $ctx['template'] = Course_Page::uses_template($post_id);
        } elseif ('seminar' === $type) {
            $ctx['d']        = Seminar_Page::data($post_id);
            $ctx['template'] = Seminar_Page::uses_template($post_id) || 'gps' === Seminar_Page::site_mode();
        }

        $items = [
            self::c_access($ctx),
            self::c_schema($ctx),
            self::c_key_data($ctx),
            self::c_consistency($ctx),
            self::c_summary($ctx),
            self::c_faq($ctx),
            self::c_headings($ctx),
            self::c_instructor($ctx),
            self::c_images($ctx),
            self::c_discovery($ctx),
        ];
        $got = 0;
        $max = 0;
        foreach ($items as $it) {
            if (in_array($it['status'], ['na', 'pending'], true)) {
                continue;
            }
            $got += $it['points'];
            $max += $it['max'];
        }
        $score  = $max ? (int) round($got / $max * 100) : 0;
        $result = [
            'score'    => $score,
            'type'     => $type,
            'items'    => $items,
            'at'       => time(),
            'crawl_at' => $crawl ? (int) $crawl['at'] : 0,
            'pending'  => (bool) array_filter($items, function ($i) { return 'pending' === $i['status']; }),
        ];
        $result = apply_filters('gpsc_ais_score', $result, $post_id);
        if ($save) {
            update_post_meta($post_id, self::META_SCORE, (int) $result['score']);
            update_post_meta($post_id, self::META_DETAIL, $result);
        }
        return $result;
    }

    /** One criterion row. $ratio 0..1 (null = not applicable / pending). */
    protected static function item($key, $label, $max, $ratio, $fix = '', $status = null) {
        if (null === $status) {
            if (null === $ratio) {
                $status = 'na';
            } elseif ($ratio >= 0.999) {
                $status = 'ok';
            } elseif ($ratio > 0) {
                $status = 'warn';
            } else {
                $status = 'fail';
            }
        }
        $points = null === $ratio ? 0 : round($max * max(0, min(1, $ratio)), 1);
        return [
            'key'    => $key,
            'label'  => $label,
            'points' => $points,
            'max'    => $max,
            'status' => $status,
            'fix'    => 'ok' === $status ? '' : $fix,
        ];
    }

    protected static function plain($s) {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags((string) $s), ENT_QUOTES, 'UTF-8')));
    }

    /** 1. AI crawlers can read the page. */
    protected static function c_access(array $c) {
        $label = __('AI crawlers can read the page', 'gps-courses');
        if (self::noindex($c['id'])) {
            return self::item('access', $label, 10, 0, __('Yoast is set to hide this page from search ("noindex"). Allow it in the Yoast box › Advanced.', 'gps-courses'));
        }
        if (!$c['crawl']) {
            return self::item('access', $label, 10, null, __('Not checked yet: click "Check as AI crawlers".', 'gps-courses'), 'pending');
        }
        $bots = $c['crawl']['bots'];
        $ok   = array_filter($bots, function ($b) { return 200 === (int) $b['code'] && empty($b['noindex']); });
        $bad  = array_diff_key($bots, $ok);
        $ratio = count($bots) ? count($ok) / count($bots) : 0;
        if ($c['html'] && !empty($c['html']['noindex'])) {
            $ratio = 0;
        }
        $fix = '';
        if ($bad) {
            $parts = [];
            foreach ($bad as $bot => $b) {
                $parts[] = $bot . ': ' . ((int) $b['code'] ?: (isset($b['error']) ? $b['error'] : 'no answer')) . (!empty($b['noindex']) ? ' (noindex)' : '');
            }
            /* translators: %s: bots and the answer they got */
            $fix = sprintf(__('Some crawlers were refused (%s). Check Cloudflare / FlyingPress bot rules.', 'gps-courses'), implode(', ', $parts));
        } elseif ($c['html'] && !empty($c['html']['noindex'])) {
            $fix = __('The page prints a "noindex" robots tag.', 'gps-courses');
        }
        return self::item('access', $label, 10, $ratio, $fix);
    }

    /** 2. Valid structured data for what the page is. */
    protected static function c_schema(array $c) {
        $label = __('Structured data (schema)', 'gps-courses');
        $types = $c['html'] ? $c['html']['types'] : null;
        if ('page' === $c['type']) {
            if (null === $types) {
                return self::item('schema', $label, 20, null, __('Not checked yet: click "Check as AI crawlers".', 'gps-courses'), 'pending');
            }
            $need = ['WebPage' => 8, 'Organization' => 6, 'BreadcrumbList' => 6];
            $got  = 0;
            $miss = [];
            foreach ($need as $t => $pts) {
                if (in_array($t, $types, true)) {
                    $got += $pts;
                } else {
                    $miss[] = $t;
                }
            }
            /* translators: %s: missing schema types */
            return self::item('schema', $label, 20, $got / 20, $miss ? sprintf(__('Missing: %s (Yoast prints these; check that Yoast schema is on for pages).', 'gps-courses'), implode(', ', $miss)) : '');
        }
        if (empty($c['template'])) {
            return self::item('schema', $label, 20, 0, 'course' === $c['type']
                ? __('This course uses its Elementor page, which has no course schema. Switch it to the GPS template (Page content tab › Page template).', 'gps-courses')
                : __('The seminar page uses Elementor. Turn on the GPS template in GPS Courses › Settings › Seminar page.', 'gps-courses'));
        }
        // What the page prints (live check), else what the template will print
        if (null === $types) {
            $types = [];
            $nodes = 'course' === $c['type'] ? Course_Page::schema_nodes($c['d']) : Seminar_Page::schema_nodes($c['d']);
            foreach ($nodes as $n) {
                foreach ((array) $n['@type'] as $t) {
                    $types[] = $t;
                }
                if (!empty($n['hasCourseInstance'])) {
                    $types[] = 'CourseInstance';
                }
                if (!empty($n['offers'])) {
                    $types[] = 'Offer';
                }
            }
            $types[] = 'Organization'; // Yoast's, merged into the same graph
        }
        $on_sale = 'seminar' === $c['type'] ? !empty($c['d']['can_enroll']) : in_array($c['d']['stage'], ['open', 'sold_out'], true);
        $need    = ['Course' => 8, 'CourseInstance' => 5, 'Organization' => 3, 'Offer' => 4];
        if (!$on_sale) {
            unset($need['Offer']);
        }
        if ('course' === $c['type'] && !$c['d']['start']) {
            unset($need['CourseInstance']);
        }
        $total = array_sum($need);
        $got   = 0;
        $miss  = [];
        foreach ($need as $t => $pts) {
            if (in_array($t, $types, true)) {
                $got += $pts;
            } else {
                $miss[] = $t;
            }
        }
        /* translators: %s: missing schema types */
        return self::item('schema', $label, 20, $total ? $got / $total : 1, $miss ? sprintf(__('Missing: %s.', 'gps-courses'), implode(', ', $miss)) : '');
    }

    /** 3. Key facts complete: dates, time, place, price, instructor, CE. */
    protected static function c_key_data(array $c) {
        $label = __('Key facts: dates, time, place, price, instructor, CE', 'gps-courses');
        if ('page' === $c['type']) {
            return self::item('facts', $label, 15, null);
        }
        $d    = $c['d'];
        $miss = [];
        $got  = 0;
        $max  = 0;
        $check = function ($ok, $pts, $what) use (&$got, &$max, &$miss) {
            $max += $pts;
            if ($ok) {
                $got += $pts;
            } else {
                $miss[] = $what;
            }
        };
        if ('course' === $c['type']) {
            $on_sale = in_array($d['stage'], ['open', 'sold_out'], true);
            $check((bool) $d['start'], 3, __('dates', 'gps-courses'));
            $check('' !== (string) $d['times'], 2, __('start / end time', 'gps-courses'));
            $check('' !== $d['venue'] && ('' !== $d['address'] || '' !== $d['city']), 3, __('venue and address', 'gps-courses'));
            if ($on_sale) { // 'soon' has no ticket on sale yet, by definition
                $check(null !== $d['price_min'] || (bool) $d['tickets'], 3, __('a ticket with its price', 'gps-courses'));
            }
            $check((bool) $d['speakers'], 2, __('instructor (Speaker profile)', 'gps-courses'));
            $check('' !== (string) $d['ce'] && (float) $d['ce'] > 0, 2, __('CE credits', 'gps-courses'));
        } else {
            $check(null !== $d['next'] || (bool) $d['later'], 3, __('the next session date', 'gps-courses'));
            $check('' !== $d['time'], 2, __('session times', 'gps-courses'));
            $check('' !== $d['venue'] && '' !== $d['street'], 3, __('location (Settings › Seminar page)', 'gps-courses'));
            $check($d['price'] > 0, 3, __('tuition price', 'gps-courses'));
            $check((bool) $d['moderator'], 2, __('moderator (Settings › Seminar page)', 'gps-courses'));
            $check($d['ce_total'] > 0, 2, __('CE credits', 'gps-courses'));
        }
        /* translators: %s: missing facts */
        return self::item('facts', $label, 15, $max ? $got / $max : 1, $miss ? sprintf(__('Missing: %s.', 'gps-courses'), implode(', ', $miss)) : '');
    }

    /** 4. The same facts everywhere (page, schema, Yoast title / description). */
    protected static function c_consistency(array $c) {
        $label = __('Same facts everywhere (page, schema, Yoast)', 'gps-courses');
        if ('page' === $c['type']) {
            return self::item('consistency', $label, 10, null);
        }
        $title = self::plain(get_post_meta($c['id'], '_yoast_wpseo_title', true));
        $desc  = self::plain(get_post_meta($c['id'], '_yoast_wpseo_metadesc', true));
        $text  = $title . ' ' . $desc;
        $issues = [];

        if ('course' === $c['type'] && $c['d']['start'] && in_array($c['d']['stage'], ['open', 'sold_out', 'soon'], true)) {
            $years = array_unique([(int) substr($c['d']['start'], 0, 4), (int) substr($c['d']['end'] ?: $c['d']['start'], 0, 4)]);
            preg_match_all('/\b(20\d\d)\b/', $text, $m);
            $other = array_diff(array_map('intval', $m[1]), $years);
            if ($other) {
                /* translators: 1: years found, 2: course year */
                $issues[] = sprintf(__('the Yoast title / description mention %1$s but the course is in %2$s', 'gps-courses'), implode(', ', array_unique($other)), implode('–', $years));
            }
        }
        // Prices in the description must be real prices
        if (preg_match_all('/\$\s?([\d,]+(?:\.\d\d)?)/', $desc, $pm)) {
            $real = [];
            if ('course' === $c['type']) {
                foreach ($c['d']['tickets'] as $t) {
                    if (null !== $t['price']) {
                        $real[] = (float) $t['price'];
                    }
                }
            } else {
                $real[] = (float) $c['d']['price'];
                foreach (array_merge([$c['d']['cycle']], $c['d']['later']) as $cy) {
                    foreach ($cy['sessions'] as $s) {
                        if ($s['single']) {
                            $real[] = (float) $s['single']['price'];
                        }
                    }
                }
            }
            foreach ($pm[1] as $p) {
                $p = (float) str_replace(',', '', $p);
                if ($real && !in_array($p, $real, true)) {
                    /* translators: %s: price */
                    $issues[] = sprintf(__('the Yoast description says $%s, which is not a current price', 'gps-courses'), number_format($p));
                }
            }
        }
        // Old CE numbers in the seminar's product text
        if ('seminar' === $c['type']) {
            $short = self::plain(get_post_field('post_excerpt', $c['id']));
            if (preg_match('/\b(\d+)\s*CE\b/i', $short . ' ' . $desc, $cm) && (int) $cm[1] !== (int) $c['d']['ce_total'] && (int) $cm[1] !== (int) $c['d']['ce_each']) {
                /* translators: 1: CE found, 2: CE of the program */
                $issues[] = sprintf(__('the product text says %1$s CE but the program gives %2$s', 'gps-courses'), $cm[1], $c['d']['ce_total']);
            }
        }
        $ratio = $issues ? max(0, 1 - 0.5 * count($issues)) : 1;
        /* translators: %s: problems found */
        return self::item('consistency', $label, 10, $ratio, $issues ? ucfirst(sprintf(__('%s. Update them in the Yoast box (or the product short description).', 'gps-courses'), implode('; ', $issues))) : '');
    }

    /** 5. A clear summary and who it is for. */
    protected static function c_summary(array $c) {
        $label = __('Clear summary and audience', 'gps-courses');
        if ('page' === $c['type']) {
            $desc = self::plain(get_post_meta($c['id'], '_yoast_wpseo_metadesc', true));
            $len  = function_exists('mb_strlen') ? mb_strlen($desc) : strlen($desc);
            return self::item('summary', $label, 10, $len >= 70 ? 1 : ($len ? 0.5 : 0), __('Write a 1–2 sentence summary in the Yoast meta description (70–160 characters).', 'gps-courses'));
        }
        $tag = self::plain($c['d']['tagline']); // what the page shows (tagline, else the hero line)
        $len = function_exists('mb_strlen') ? mb_strlen($tag) : strlen($tag);
        $got = 0;
        $max = 5;
        $fix = [];
        if ($len >= 40 && $len <= 300) {
            $got += 5;
        } else {
            $fix[] = 'course' === $c['type'] ? __('add a tagline of one or two sentences (Page content tab)', 'gps-courses') : __('write a short product description (one or two sentences)', 'gps-courses');
        }
        if ('course' === $c['type']) {
            $max += 5;
            if ('' !== $c['d']['audience']) {
                $got += 5;
            } else {
                $fix[] = __('fill in "Who should attend" (Page content tab)', 'gps-courses');
            }
        }
        return self::item('summary', $label, 10, $got / $max, $fix ? ucfirst(implode('; ', $fix)) . '.' : '');
    }

    /** 6. FAQ with at least 4 real questions (+ FAQPage). */
    protected static function c_faq(array $c) {
        $label = __('FAQ with at least 4 questions', 'gps-courses');
        if ('page' === $c['type']) {
            $n = $c['html'] ? (int) $c['html']['faq_q'] : 0;
            return $n ? self::item('faq', $label, 10, min(1, $n / 4), __('Add more questions (at least 4).', 'gps-courses')) : self::item('faq', $label, 10, null);
        }
        $n = count($c['d']['faqs']);
        return self::item('faq', $label, 10, $n >= 4 ? 1 : ($n ? 0.5 : 0), 'course' === $c['type']
            ? __('Add course questions in the Page content tab (the site-wide FAQs count too).', 'gps-courses')
            : __('Add policies in GPS Courses › Settings › Seminar page.', 'gps-courses'));
    }

    /** 7. Title, meta description, one H1, ordered headings. */
    protected static function c_headings(array $c) {
        $label = __('Title, meta description, one H1, ordered headings', 'gps-courses');
        $h     = $c['html'];
        $fix   = [];
        $got   = 0;

        // Title (what the page prints, else Yoast / post title)
        $yoast = self::plain(get_post_meta($c['id'], '_yoast_wpseo_title', true));
        if ('' === $yoast || false !== strpos($yoast, '%%')) {
            $yoast = self::plain(get_the_title($c['id'])) . ' - ' . self::plain(get_bloginfo('name')); // Yoast's default pattern
        }
        $title = $h ? $h['title'] : $yoast;
        $tl    = function_exists('mb_strlen') ? mb_strlen($title) : strlen($title);
        if ($tl >= 15 && $tl <= 75) {
            $got += 3;
        } else {
            $got += $tl > 75 && $tl <= 100 ? 2 : 0;
            /* translators: %d: characters */
            $fix[] = sprintf(__('SEO title is best at 15–75 characters (now %d): shorten it in the Yoast box', 'gps-courses'), $tl);
        }
        // Meta description (printed, else Yoast, else our fallback on GPS templates)
        $desc = $h ? $h['desc'] : self::plain(get_post_meta($c['id'], '_yoast_wpseo_metadesc', true));
        if ('' === $desc && !$h && in_array($c['type'], ['course', 'seminar'], true) && !empty($c['template'])) {
            $desc = self::plain($c['d']['tagline']);
        }
        $dl = function_exists('mb_strlen') ? mb_strlen($desc) : strlen($desc);
        if ($dl >= 70 && $dl <= 160) {
            $got += 3;
        } elseif ($dl) {
            $got += 1.5;
            /* translators: %d: characters */
            $fix[] = sprintf(__('meta description should be 70–160 characters (now %d)', 'gps-courses'), $dl);
        } else {
            $fix[] = __('write a meta description in Yoast', 'gps-courses');
        }
        // Headings: need the live check, except on the GPS templates (one H1, fixed order)
        if ($h) {
            if (1 === (int) $h['h1'] && $h['order_ok']) {
                $got += 4;
            } else {
                if (1 !== (int) $h['h1']) {
                    /* translators: %d: number of H1 */
                    $fix[] = sprintf(__('the page has %d H1 headings (should be exactly one)', 'gps-courses'), (int) $h['h1']);
                    $got += $h['order_ok'] ? 2 : 0;
                } else {
                    $got += 2;
                }
                if (!$h['order_ok']) {
                    $fix[] = __('headings skip a level (e.g. H2 → H4); adjust the heading tags in Elementor', 'gps-courses');
                }
            }
        } elseif (!empty($c['template'])) {
            $got += 4;
        } else {
            return self::item('headings', $label, 10, null, __('Not checked yet: click "Check as AI crawlers".', 'gps-courses'), 'pending');
        }
        return self::item('headings', $label, 10, $got / 10, $fix ? ucfirst(implode('; ', $fix)) . '.' : '');
    }

    /** 8. Instructor with credentials and a profile. */
    protected static function c_instructor(array $c) {
        $label = __('Instructor with credentials and profile', 'gps-courses');
        if ('page' === $c['type']) {
            return self::item('instructor', $label, 5, null);
        }
        $people = 'course' === $c['type'] ? $c['d']['speakers'] : array_filter([$c['d']['moderator']]);
        if (!$people) {
            return self::item('instructor', $label, 5, 0, __('Link a Speaker profile (Page content tab › Instructors).', 'gps-courses'));
        }
        $s   = reset($people);
        $got = 2;
        $fix = [];
        if ('' !== $s['cred']) {
            $got += 1;
        } else {
            $fix[] = __('credentials (e.g. DDS, FACP) in the Speaker profile', 'gps-courses');
        }
        if ($s['photo'] && '' !== $s['short']) {
            $got += 1;
        } else {
            $fix[] = __('photo and bio in the Speaker profile', 'gps-courses');
        }
        if ('' !== $s['url']) {
            $got += 1;
        } else {
            $fix[] = __('a profile page link in the Speaker profile', 'gps-courses');
        }
        /* translators: %s: what is missing */
        return self::item('instructor', $label, 5, $got / 5, $fix ? sprintf(__('Add %s.', 'gps-courses'), implode(', ', $fix)) : '');
    }

    /** 9. Images with alternative text. */
    protected static function c_images(array $c) {
        $label = __('Images with alternative text', 'gps-courses');
        if (in_array($c['type'], ['course', 'seminar'], true)) {
            $ids = [];
            $img = $c['d']['image'];
            if ($img) {
                $ids[] = (int) $img;
            }
            if ('course' === $c['type']) {
                $ids = array_merge($ids, array_map('intval', $c['d']['gallery']));
            }
            if (!$ids) {
                return self::item('images', $label, 5, null);
            }
            $with = array_filter($ids, function ($id) { return '' !== trim((string) get_post_meta($id, '_wp_attachment_image_alt', true)); });
            /* translators: 1: images with alt, 2: total */
            return self::item('images', $label, 5, count($with) / count($ids), sprintf(__('%1$d of %2$d images have alt text: add it in the Media Library (Alternative Text).', 'gps-courses'), count($with), count($ids)));
        }
        if (!$c['html']) {
            return self::item('images', $label, 5, null, __('Not checked yet: click "Check as AI crawlers".', 'gps-courses'), 'pending');
        }
        $h     = $c['html'];
        $count = (int) $h['img_alt'] + (int) $h['img_noalt'];
        if (!$count) {
            return self::item('images', $label, 5, null);
        }
        /* translators: 1: images without alt attribute */
        return self::item('images', $label, 5, $h['img_alt'] / $count, sprintf(_n('%d image has no alt text: set it in the Media Library or the Elementor image widget.', '%d images have no alt text: set it in the Media Library or the Elementor image widget.', (int) $h['img_noalt'], 'gps-courses'), (int) $h['img_noalt']));
    }

    /** 10. In the sitemap and llms.txt, with a current modified date. */
    protected static function c_discovery(array $c) {
        $label = __('In the sitemap and llms.txt', 'gps-courses');
        $got   = 0;
        $max   = 3;
        $fix   = [];
        if (!self::noindex($c['id'])) {
            $got += 3;
        } else {
            $fix[] = __('it is hidden from the sitemap (Yoast noindex)', 'gps-courses');
        }
        $in_llms = null;
        if (function_exists('gpsc_catalog')) {
            $cat = gpsc_catalog();
            if ('course' === $c['type']) {
                // Past courses leave llms.txt on purpose (it lists what can be booked)
                if ('past' !== $c['d']['stage']) {
                    $in_llms = (bool) array_filter($cat['courses'], function ($x) use ($c) { return (int) $x['id'] === $c['id']; });
                }
            } elseif ('seminar' === $c['type']) {
                $in_llms = (bool) array_filter($cat['seminars'], function ($x) use ($c) { return (int) get_post_meta($x['id'], '_gps_seminar_product_id', true) === $c['id']; });
            }
        }
        if (null !== $in_llms) {
            $max += 2;
            if ($in_llms) {
                $got += 2;
            } else {
                $fix[] = __('it is not listed in /llms.txt (check that it has dates and a ticket)', 'gps-courses');
            }
        }
        return self::item('discovery', $label, 5, $got / $max, $fix ? ucfirst(implode('; ', $fix)) . '.' : '');
    }

    /* ============================================================
     * Stored detail
     * ============================================================ */

    /** The stored score (recomputed when older than $max_age seconds). */
    public static function get($post_id, $max_age = 300) {
        $d = get_post_meta((int) $post_id, self::META_DETAIL, true);
        if (is_array($d) && isset($d['at']) && time() - (int) $d['at'] < $max_age) {
            return $d;
        }
        return self::score($post_id);
    }

    /** green / amber / red */
    public static function tone($score) {
        if ($score >= self::threshold()) {
            return 'good';
        }
        return $score >= 70 ? 'fair' : 'poor';
    }
}
