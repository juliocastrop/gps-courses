<?php
/**
 * Monthly seminars product page, served by Seminar_Page::template() for
 * WooCommerce products linked to a gps_seminar. Uses the course page design
 * (gps-course.css / gps-course.js) plus gps-seminar.css. The product's own
 * Elementor template is not rendered.
 */
defined('ABSPATH') || exit;

use GPSC\Course_Page;
use GPSC\Seminar_Page;

if (have_posts()) {
    the_post();
}
$d     = Seminar_Page::data(get_the_ID());
$i     = function ($name, $size = 22) { return Course_Page::icon($name, $size); };
$mod   = $d['moderator'];
$next  = $d['next'];
$cycle = $d['cycle'];
$price = Course_Page::price($d['price']); // wc_price() output
$user  = is_user_logged_in() ? wp_get_current_user() : null;

/* translators: %d: number of CE credits */
$ce_label = sprintf(__('%d CE credits', 'gps-courses'), $d['ce_total']);
/* translators: %d: number of sessions */
$sessions_label = sprintf(_n('%d session', '%d sessions', $d['sessions'], 'gps-courses'), $d['sessions']);

$sections = ['how' => __('How it works', 'gps-courses')];
if ($cycle['sessions'] || $d['later']) {
    $sections['sessions'] = __('Sessions', 'gps-courses');
}
if ($d['topics']) {
    $sections['topics'] = __('Topics', 'gps-courses');
}
if ($mod) {
    $sections['moderator'] = __('Moderator', 'gps-courses');
}
if ($d['faqs']) {
    $sections['faq'] = __('Policies', 'gps-courses');
}
if ($d['map_q']) {
    $sections['location'] = __('Location', 'gps-courses');
}

/** Enroll form: posts add-to-cart to the cart, like the course page. */
$enroll_form = function ($label, $class = 'gps-cp-btn gps-cp-btn--primary gps-cp-buy__submit') use ($d) {
    echo '<form class="gps-cp-buy gps-sp-buy" method="post" action="' . esc_url($d['cart']) . '">';
    echo '<input type="hidden" name="gps_cp_add" value="1">';
    echo '<input type="hidden" name="add-to-cart" value="' . (int) $d['id'] . '">';
    echo '<input type="hidden" name="quantity" value="1">';
    echo '<button type="submit" class="' . esc_attr($class) . '">' . esc_html($label) . '</button>';
    echo '</form>';
};

get_header();
?>
<main id="content" class="gps-cp gps-sp">
<script>document.documentElement.classList.add('gps-js');</script>

    <!-- Hero -->
    <section class="gps-cp-hero<?php echo $d['image'] ? '' : ' gps-cp-hero--plain'; ?>" aria-labelledby="gps-cp-title">
        <?php if ($d['image']) : ?>
            <div class="gps-cp-hero__media" aria-hidden="true">
                <?php
                echo wp_get_attachment_image($d['image'], 'full', false, [
                    'class'         => 'gps-cp-hero__img',
                    'alt'           => '',
                    'sizes'         => '100vw',
                    'loading'       => 'eager',
                    'fetchpriority' => 'high',
                    'decoding'      => 'async',
                ]);
                ?>
            </div>
        <?php endif; ?>
        <div class="gps-cp-wrap gps-cp-hero__inner">
            <nav class="gps-cp-crumbs" aria-label="<?php esc_attr_e('Breadcrumb', 'gps-courses'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'gps-courses'); ?></a>
                <span aria-hidden="true">›</span>
                <span aria-current="page"><?php esc_html_e('Monthly seminars', 'gps-courses'); ?></span>
            </nav>

            <div class="gps-cp-hero__chips">
                <span class="gps-cp-tag"><?php esc_html_e('Monthly seminars', 'gps-courses'); ?></span>
                <?php if ($d['can_enroll']) : ?>
                    <span class="gps-cp-chip gps-cp-chip--open"><?php esc_html_e('Enrollment open · join anytime', 'gps-courses'); ?></span>
                <?php elseif ($d['full']) : ?>
                    <span class="gps-cp-chip gps-cp-chip--sold_out"><?php esc_html_e('Program full', 'gps-courses'); ?></span>
                <?php endif; ?>
            </div>

            <h1 class="gps-cp-hero__title" id="gps-cp-title">
                <?php echo esc_html($d['white']); ?>
                <?php if ('' !== $d['gold']) : ?>
                    <span class="gps-cp-hl gps-sp-hl"><?php echo esc_html($d['gold']); ?></span>
                <?php endif; ?>
            </h1>

            <p class="gps-cp-hero__tagline"><?php echo esc_html($d['tagline']); ?></p>

            <ul class="gps-cp-facts">
                <li class="gps-cp-fact gps-cp-fact--dates">
                    <?php echo $i('calendar'); ?>
                    <span class="gps-cp-fact__body"><span class="gps-cp-fact__label"><?php esc_html_e('Schedule', 'gps-courses'); ?></span><span class="gps-cp-fact__value"><?php
                        /* translators: %s: session length in hours */
                        echo esc_html(sprintf(__('Once a month · %s h', 'gps-courses'), $d['hours']));
                    ?></span><?php if ($d['time']) : ?><span class="gps-cp-fact__detail"><?php
                        /* translators: %s: time range, e.g. 6–8 PM */
                        echo esc_html($d['time_all'] ? $d['time'] : sprintf(__('Most sessions %s', 'gps-courses'), $d['time']));
                    ?></span><?php endif; ?></span>
                </li>
                <?php if ($d['city'] || $d['venue']) : ?>
                    <li class="gps-cp-fact gps-cp-fact--wide">
                        <?php echo $i('pin'); ?>
                        <span class="gps-cp-fact__body"><span class="gps-cp-fact__label"><?php esc_html_e('Location', 'gps-courses'); ?></span><span class="gps-cp-fact__value"><?php echo esc_html($d['city'] ?: $d['venue']); ?></span><span class="gps-cp-fact__detail"><?php esc_html_e('In person', 'gps-courses'); ?></span></span>
                    </li>
                <?php endif; ?>
                <li class="gps-cp-fact gps-cp-fact--ce">
                    <?php echo $i('award'); ?>
                    <span class="gps-cp-fact__body"><span class="gps-cp-fact__label"><?php esc_html_e('CE credits', 'gps-courses'); ?></span><span class="gps-cp-fact__value"><?php echo esc_html(sprintf(__('%d CE', 'gps-courses'), $d['ce_total'])); ?></span><span class="gps-cp-fact__detail"><?php
                        /* translators: %d: CE credits per session */
                        echo esc_html(sprintf(__('%d per session', 'gps-courses'), $d['ce_each']));
                    ?></span></span>
                </li>
                <?php if ($mod) : ?>
                    <li class="gps-cp-fact gps-cp-fact--wide">
                        <?php echo $i('people'); ?>
                        <span class="gps-cp-fact__body"><span class="gps-cp-fact__label"><?php esc_html_e('Moderator', 'gps-courses'); ?></span><span class="gps-cp-fact__value"><?php echo esc_html($mod['name']); ?></span><?php if ($mod['cred'] || $mod['title']) : ?><span class="gps-cp-fact__detail"><?php echo esc_html($mod['title'] ?: $mod['cred']); ?></span><?php endif; ?></span>
                    </li>
                <?php endif; ?>
            </ul>
            <?php
            $line = array_filter([$d['city'] ?: $d['venue'], $mod ? $mod['full'] : '']);
            if ($line) : ?>
                <p class="gps-cp-hero__line"><?php echo esc_html(implode(' · ', $line)); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <!-- On this page -->
    <nav class="gps-cp-nav" aria-label="<?php esc_attr_e('On this page', 'gps-courses'); ?>">
        <div class="gps-cp-wrap gps-cp-nav__inner">
            <?php foreach ($sections as $anchor => $label) : ?>
                <a class="gps-cp-nav__link" href="#<?php echo esc_attr($anchor); ?>"><?php echo esc_html($label); ?></a>
            <?php endforeach; ?>
        </div>
    </nav>

    <div class="gps-cp-wrap gps-cp-body">

        <!-- Enrollment (first in the source so phones show it right after the hero) -->
        <aside class="gps-cp-book gps-cp-book--open gps-sp-book" id="registration" aria-labelledby="gps-cp-book-h">
            <div class="gps-cp-book__head">
                <h2 class="gps-cp-book__title" id="gps-cp-book-h"><?php esc_html_e('Enroll', 'gps-courses'); ?></h2>
                <?php if ($d['full']) : ?>
                    <span class="gps-cp-chip gps-cp-chip--sold_out"><?php esc_html_e('Full', 'gps-courses'); ?></span>
                <?php elseif ($d['few']) : ?>
                    <span class="gps-cp-chip gps-cp-chip--few"><?php echo esc_html(sprintf(_n('%d spot left', '%d spots left', $d['spots'], 'gps-courses'), $d['spots'])); ?></span>
                <?php elseif ($d['can_enroll']) : ?>
                    <span class="gps-cp-chip gps-cp-chip--open"><?php esc_html_e('Open', 'gps-courses'); ?></span>
                <?php endif; ?>
            </div>

            <div class="gps-sp-price">
                <span class="gps-sp-price__label"><?php esc_html_e('Tuition', 'gps-courses'); ?></span>
                <span class="gps-sp-price__amount"><?php echo $price; // wc_price() output ?></span>
                <span class="gps-sp-price__sub"><?php echo esc_html($sessions_label . ' · ' . $ce_label); ?></span>
            </div>

            <div class="gps-sp-first">
                <span class="gps-sp-first__label"><?php echo esc_html($d['can_enroll'] ? __('Your first session', 'gps-courses') : __('Next session', 'gps-courses')); ?></span>
                <?php if ($next) : ?>
                    <span class="gps-sp-first__date"><?php echo esc_html(trim($next['long'] . ($next['time'] ? ' · ' . $next['time'] : ''))); ?></span>
                    <?php if ('' !== $next['topic']) : ?><span class="gps-sp-first__topic"><?php echo esc_html($next['topic']); ?></span><?php endif; ?>
                <?php else : ?>
                    <span class="gps-sp-first__date"><?php esc_html_e('Dates for the next cycle are coming soon', 'gps-courses'); ?></span>
                    <span class="gps-sp-first__topic"><?php esc_html_e('Your membership starts at the first session of the new cycle.', 'gps-courses'); ?></span>
                <?php endif; ?>
            </div>

            <?php if ($d['can_enroll']) : ?>
                <?php $enroll_form(__('Enroll now', 'gps-courses')); ?>
                <?php if ($next && $next['single']) : ?>
                    <form class="gps-cp-buy gps-sp-buy" method="post" action="<?php echo esc_url($d['cart']); ?>">
                        <input type="hidden" name="gps_cp_add" value="1">
                        <input type="hidden" name="add-to-cart" value="<?php echo (int) $next['single']['product']; ?>">
                        <input type="hidden" name="quantity" value="1">
                        <button type="submit" class="gps-cp-btn gps-cp-btn--ghost gps-cp-buy__submit gps-sp-single"><?php
                            /* translators: %s: single-session price */
                            echo wp_kses_post(sprintf(__('Attend only this session · %s', 'gps-courses'), Course_Page::price($next['single']['price'])));
                        ?></button>
                    </form>
                <?php endif; ?>
                <p class="gps-cp-secure"><?php echo $i('lock', 16); ?><span><?php esc_html_e('Secure payment. Your QR code arrives by email.', 'gps-courses'); ?></span></p>
            <?php elseif ($d['full']) : ?>
                <?php $uid = 'gps-sp-wl'; ?>
                <form method="post" class="gps-waitlist-form gps-cp-waitlist gps-sp-waitlist" data-action="gps_join_seminar_waitlist" data-nonce-for="gps_waitlist_nonce" data-nonce="<?php echo esc_attr(wp_create_nonce('gps_waitlist_nonce')); ?>" data-fields="<?php echo esc_attr(wp_json_encode(['seminar_id' => $d['seminar_id']])); ?>" aria-labelledby="<?php echo esc_attr($uid); ?>-h" novalidate>
                    <p class="gps-cp-waitlist__title" id="<?php echo esc_attr($uid); ?>-h"><?php esc_html_e('The program is full. Join the waitlist and we will email you when a spot opens.', 'gps-courses'); ?></p>
                    <div class="gps-waitlist-fields">
                        <div class="gps-waitlist-row">
                            <label class="gps-sr" for="<?php echo esc_attr($uid); ?>-f"><?php esc_html_e('First name', 'gps-courses'); ?></label>
                            <input type="text" id="<?php echo esc_attr($uid); ?>-f" name="first_name" class="gps-waitlist-input" autocomplete="given-name" placeholder="<?php esc_attr_e('First name', 'gps-courses'); ?>" value="<?php echo $user ? esc_attr($user->first_name) : ''; ?>">
                            <label class="gps-sr" for="<?php echo esc_attr($uid); ?>-l"><?php esc_html_e('Last name', 'gps-courses'); ?></label>
                            <input type="text" id="<?php echo esc_attr($uid); ?>-l" name="last_name" class="gps-waitlist-input" autocomplete="family-name" placeholder="<?php esc_attr_e('Last name', 'gps-courses'); ?>" value="<?php echo $user ? esc_attr($user->last_name) : ''; ?>">
                        </div>
                        <label class="gps-sr" for="<?php echo esc_attr($uid); ?>-e"><?php esc_html_e('Email address (required)', 'gps-courses'); ?></label>
                        <input type="email" id="<?php echo esc_attr($uid); ?>-e" name="email" class="gps-waitlist-input" autocomplete="email" required aria-required="true" placeholder="<?php esc_attr_e('Email address *', 'gps-courses'); ?>" value="<?php echo $user ? esc_attr($user->user_email) : ''; ?>">
                        <label class="gps-sr" for="<?php echo esc_attr($uid); ?>-p"><?php esc_html_e('Phone (optional)', 'gps-courses'); ?></label>
                        <input type="tel" id="<?php echo esc_attr($uid); ?>-p" name="phone" class="gps-waitlist-input" autocomplete="tel" placeholder="<?php esc_attr_e('Phone (optional)', 'gps-courses'); ?>">
                        <button type="submit" class="gps-waitlist-submit"><?php esc_html_e('Join the waitlist', 'gps-courses'); ?></button>
                    </div>
                    <div class="gps-waitlist-response" role="status" aria-live="polite" hidden></div>
                </form>
            <?php else : ?>
                <p class="gps-sp-closed"><?php
                    /* translators: %s: contact email */
                    printf(esc_html__('Enrollment is not available online right now. Write to us at %s.', 'gps-courses'), '<a href="mailto:' . esc_attr($d['contact']) . '">' . esc_html($d['contact']) . '</a>');
                ?></p>
            <?php endif; ?>

            <ul class="gps-sp-perks">
                <li><?php echo $i('check', 18); ?><span><?php esc_html_e('One make-up session per year', 'gps-courses'); ?></span></li>
                <li><?php echo $i('check', 18); ?><span><?php esc_html_e('Certificates in June and December', 'gps-courses'); ?></span></li>
                <li><?php echo $i('check', 18); ?><span><?php esc_html_e('Tuition is non-refundable and non-transferable', 'gps-courses'); ?></span></li>
            </ul>

            <div class="gps-cp-tools">
                <details class="gps-cp-menu gps-cp-share" data-url="<?php echo esc_url($d['url']); ?>" data-title="<?php echo esc_attr($d['title']); ?>">
                    <summary class="gps-cp-btn gps-cp-btn--ghost"><?php esc_html_e('Share', 'gps-courses'); ?></summary>
                    <ul class="gps-cp-menu__list">
                        <li><button type="button" class="gps-cp-copy" data-url="<?php echo esc_url($d['url']); ?>"><?php esc_html_e('Copy link', 'gps-courses'); ?></button></li>
                        <?php foreach (Course_Page::share_links(['url' => $d['url'], 'title' => $d['title']]) as $s) : ?>
                            <li><a href="<?php echo esc_url($s['url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($s['label']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            </div>
        </aside>

        <div class="gps-cp-main">

            <?php if (function_exists('wc_notice_count') && wc_notice_count() > 0) : ?>
                <div class="gps-sp-notices"><?php wc_print_notices(); ?></div>
            <?php endif; ?>

            <section class="gps-cp-card" id="how" aria-labelledby="gps-sp-how-h">
                <h2 class="gps-cp-h2" id="gps-sp-how-h"><?php esc_html_e('How it works', 'gps-courses'); ?></h2>
                <p class="gps-sp-lead"><?php
                    /* translators: %d: number of sessions */
                    echo esc_html(sprintf(__('You can join at any point in the year. Your membership covers your next %d sessions, wherever the calendar is.', 'gps-courses'), $d['sessions']));
                ?></p>
                <ol class="gps-sp-steps">
                    <li class="gps-sp-step">
                        <span class="gps-sp-step__n" aria-hidden="true">1</span>
                        <strong class="gps-sp-step__title"><?php esc_html_e('Enroll anytime', 'gps-courses'); ?></strong>
                        <span class="gps-sp-step__text"><?php
                            /* translators: %s: tuition */
                            echo wp_kses_post(sprintf(__('One tuition of %s covers the full cycle.', 'gps-courses'), $price));
                        ?></span>
                    </li>
                    <li class="gps-sp-step">
                        <span class="gps-sp-step__n" aria-hidden="true">2</span>
                        <strong class="gps-sp-step__title"><?php esc_html_e('Start at the next session', 'gps-courses'); ?></strong>
                        <span class="gps-sp-step__text"><?php esc_html_e('Your membership begins at the next scheduled seminar.', 'gps-courses'); ?></span>
                    </li>
                    <li class="gps-sp-step">
                        <span class="gps-sp-step__n" aria-hidden="true">3</span>
                        <strong class="gps-sp-step__title"><?php echo esc_html(sprintf(__('Attend %d sessions', 'gps-courses'), $d['sessions'])); ?></strong>
                        <span class="gps-sp-step__text"><?php
                            /* translators: 1: session length in hours, 2: CE credits per session */
                            echo esc_html(sprintf(__('%1$s hours each, %2$d CE per session. One make-up allowed.', 'gps-courses'), $d['hours'], $d['ce_each']));
                        ?></span>
                    </li>
                    <li class="gps-sp-step gps-sp-step--gold">
                        <span class="gps-sp-step__n" aria-hidden="true">4</span>
                        <strong class="gps-sp-step__title"><?php esc_html_e('Certificates twice a year', 'gps-courses'); ?></strong>
                        <span class="gps-sp-step__text"><?php esc_html_e('Issued at the end of June and December.', 'gps-courses'); ?></span>
                    </li>
                </ol>
            </section>

            <?php
            $render_cycle = function ($c, $heading_id) use ($i) {
                $pct = $c['total'] ? (int) round($c['done'] / $c['total'] * 100) : 0;
                ?>
                <div class="gps-sp-cycle">
                    <div class="gps-sp-cycle__head">
                        <h3 class="gps-sp-cycle__title" id="<?php echo esc_attr($heading_id); ?>"><?php echo esc_html($c['title']); ?></h3>
                        <?php if ($c['done']) : ?>
                            <span class="gps-sp-cycle__count"><?php
                                /* translators: 1: completed sessions, 2: total sessions */
                                echo esc_html(sprintf(__('%1$d of %2$d completed', 'gps-courses'), $c['done'], $c['total']));
                            ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($c['done']) : ?>
                        <div class="gps-sp-progress" aria-hidden="true"><span style="width: <?php echo (int) $pct; ?>%"></span></div>
                    <?php endif; ?>
                    <ol class="gps-sp-sessions" aria-labelledby="<?php echo esc_attr($heading_id); ?>">
                        <?php foreach ($c['sessions'] as $s) : ?>
                            <li class="gps-sp-session gps-sp-session--<?php echo esc_attr($s['status']); ?>">
                                <span class="gps-sp-session__date" aria-hidden="true"><span class="gps-sp-session__mon"><?php echo esc_html($s['mon']); ?></span><span class="gps-sp-session__day"><?php echo esc_html($s['day']); ?></span></span>
                                <span class="gps-sp-session__body">
                                    <span class="gps-sp-session__meta"><span class="gps-sr"><?php echo esc_html($s['full']); ?> · </span><?php
                                        /* translators: %d: session number */
                                        echo esc_html(sprintf(__('Session %d', 'gps-courses'), $s['number']) . ($s['time'] ? ' · ' . $s['time'] : ''));
                                    ?></span>
                                    <span class="gps-sp-session__topic"><?php echo esc_html('' !== $s['topic'] ? $s['topic'] : __('Topic to be announced', 'gps-courses')); ?></span>
                                </span>
                                <span class="gps-sp-session__side">
                                    <?php if ('done' === $s['status']) : ?>
                                        <span class="gps-sp-badge gps-sp-badge--done"><?php echo $i('check', 14); ?><span class="gps-sp-badge__text"><?php esc_html_e('Completed', 'gps-courses'); ?></span></span>
                                    <?php elseif ('next' === $s['status']) : ?>
                                        <span class="gps-sp-badge gps-sp-badge--next"><?php esc_html_e('Next session', 'gps-courses'); ?></span>
                                    <?php else : ?>
                                        <span class="gps-sp-badge"><?php esc_html_e('Upcoming', 'gps-courses'); ?></span>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
                <?php
            };
            ?>

            <?php if (isset($sections['sessions'])) : ?>
                <section class="gps-cp-card" id="sessions" aria-labelledby="gps-sp-sessions-h">
                    <h2 class="gps-cp-h2" id="gps-sp-sessions-h"><?php esc_html_e('Sessions', 'gps-courses'); ?></h2>
                    <?php
                    if ($cycle['sessions']) {
                        $render_cycle($cycle, 'gps-sp-cycle-0');
                    }
                    foreach ($d['later'] as $n => $later) {
                        $render_cycle($later, 'gps-sp-cycle-' . ((int) $n + 1));
                    }
                    if ($next && !$d['later'] && $cycle['total'] - $cycle['done'] < $d['sessions']) : ?>
                        <p class="gps-sp-note"><?php
                            $rest = $d['sessions'] - ($cycle['total'] - $cycle['done']);
                            /* translators: 1: date and topic of the first session, 2: remaining sessions */
                            echo wp_kses_post(sprintf(
                                __('Joining now? Your first session is <strong>%1$s</strong>. Your other %2$d sessions continue in the next cycle, published here as soon as its dates are set.', 'gps-courses'),
                                esc_html(trim($next['long'] . ('' !== $next['topic'] ? ' — ' . $next['topic'] : ''))),
                                (int) $rest
                            ));
                        ?></p>
                    <?php elseif (!$next) : ?>
                        <p class="gps-sp-note"><?php esc_html_e('The dates for the next cycle will be published here soon. Enroll now and your membership starts at its first session.', 'gps-courses'); ?></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($d['topics']) : ?>
                <section class="gps-cp-card" id="topics" aria-labelledby="gps-sp-topics-h">
                    <h2 class="gps-cp-h2" id="gps-sp-topics-h"><?php esc_html_e('Seminar topics', 'gps-courses'); ?></h2>
                    <ul class="gps-sp-topics">
                        <?php foreach ($d['topics'] as $t) : ?>
                            <li class="gps-sp-topic">
                                <strong class="gps-sp-topic__title"><?php echo esc_html($t['title']); ?></strong>
                                <?php if ('' !== $t['text']) : ?><span class="gps-sp-topic__text"><?php echo esc_html($t['text']); ?></span><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if ($mod) : ?>
                <section class="gps-cp-card" id="moderator" aria-labelledby="gps-sp-mod-h">
                    <h2 class="gps-cp-h2" id="gps-sp-mod-h"><?php esc_html_e('Moderator', 'gps-courses'); ?></h2>
                    <div class="gps-cp-people">
                        <article class="gps-cp-person">
                            <?php if ($mod['photo']) : ?>
                                <?php echo wp_get_attachment_image($mod['photo'], 'medium', false, ['class' => 'gps-cp-person__photo', 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(max-width: 767px) 84px, 140px']); ?>
                            <?php endif; ?>
                            <div class="gps-cp-person__body">
                                <h3 class="gps-cp-person__name"><?php echo esc_html($mod['full']); ?></h3>
                                <?php if ('' !== $mod['title']) : ?>
                                    <p class="gps-cp-person__title"><?php echo esc_html($mod['title']); ?></p>
                                <?php endif; ?>
                                <?php if ('' !== $mod['short']) : ?>
                                    <p class="gps-cp-person__bio"><?php echo esc_html($mod['short']); ?></p>
                                <?php endif; ?>
                                <?php if ('' !== $mod['bio']) : ?>
                                    <details class="gps-cp-person__more">
                                        <summary><?php esc_html_e('Read full bio', 'gps-courses'); ?></summary>
                                        <div class="gps-cp-prose"><?php echo $mod['bio']; // wp_kses_post ?></div>
                                    </details>
                                <?php endif; ?>
                                <?php if ('' !== $mod['url']) : ?>
                                    <a class="gps-cp-link" href="<?php echo esc_url($mod['url']); ?>"><?php esc_html_e('View full profile', 'gps-courses'); ?> <span aria-hidden="true">→</span></a>
                                <?php endif; ?>
                            </div>
                        </article>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($d['faqs']) : ?>
                <section class="gps-cp-card" id="faq" aria-labelledby="gps-cp-faq-h">
                    <h2 class="gps-cp-h2" id="gps-cp-faq-h"><?php esc_html_e('Program policies', 'gps-courses'); ?></h2>
                    <div class="gps-cp-faq">
                        <?php foreach ($d['faqs'] as $n => $f) : ?>
                            <details class="gps-cp-faq__item"<?php echo 0 === $n ? ' open' : ''; ?>>
                                <summary><?php echo esc_html($f['q']); ?><?php echo $i('chevron', 20); ?></summary>
                                <div class="gps-cp-faq__a"><?php echo wpautop(wp_kses_post($f['a'])); ?></div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($d['map_q']) : ?>
                <section class="gps-cp-card" id="location" aria-labelledby="gps-cp-location-h">
                    <h2 class="gps-cp-h2" id="gps-cp-location-h"><?php esc_html_e('Location', 'gps-courses'); ?></h2>
                    <div class="gps-cp-location">
                        <div class="gps-cp-location__text">
                            <?php if ($d['venue']) : ?><strong class="gps-cp-location__venue"><?php echo esc_html($d['venue']); ?></strong><?php endif; ?>
                            <address><?php echo esc_html($d['street']); ?><?php if ($d['cityline']) : ?><br><?php echo esc_html($d['cityline']); ?><?php endif; ?></address>
                            <?php if ($d['contact']) : ?>
                                <p><?php esc_html_e('Questions:', 'gps-courses'); ?> <a href="mailto:<?php echo esc_attr($d['contact']); ?>"><?php echo esc_html($d['contact']); ?></a></p>
                            <?php endif; ?>
                            <a class="gps-cp-btn gps-cp-btn--outline" href="<?php echo esc_url('https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($d['map_q'])); ?>" target="_blank" rel="noopener"><?php esc_html_e('Get directions', 'gps-courses'); ?></a>
                        </div>
                        <div class="gps-cp-map">
                            <iframe title="<?php echo esc_attr(sprintf(__('Map: %s', 'gps-courses'), $d['venue'] ?: $d['city'])); ?>" src="<?php echo esc_url('https://maps.google.com/maps?output=embed&z=15&q=' . rawurlencode($d['map_q'])); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($d['can_enroll']) : ?>
        <section class="gps-cp-band" aria-labelledby="gps-cp-band-h">
            <div class="gps-cp-wrap gps-cp-band__inner">
                <div>
                    <h2 class="gps-cp-band__title" id="gps-cp-band-h"><?php
                        /* translators: %s: date of the next session */
                        echo esc_html($next ? sprintf(__('Start with the next session on %s', 'gps-courses'), $next['long']) : __('Join the next cycle', 'gps-courses'));
                    ?></h2>
                    <p class="gps-cp-band__sub"><?php echo esc_html($sessions_label . ' · ' . $ce_label . ' · ') . $price; // wc_price() output ?></p>
                </div>
                <?php $enroll_form(__('Enroll now', 'gps-courses'), 'gps-cp-btn gps-cp-btn--gold gps-cp-buy__submit'); ?>
            </div>
        </section>

        <div class="gps-cp-bar" hidden>
            <div class="gps-cp-bar__text">
                <strong><?php echo $price; // wc_price() output ?></strong>
                <span><?php
                    /* translators: %s: date of the first session */
                    echo esc_html($next ? sprintf(__('Starts %s', 'gps-courses'), $next['long']) : $sessions_label . ' · ' . $ce_label);
                ?></span>
            </div>
            <a class="gps-cp-btn gps-cp-btn--primary" href="#registration"><?php esc_html_e('Enroll now', 'gps-courses'); ?></a>
        </div>
    <?php endif; ?>
</main>
<?php
get_footer();
