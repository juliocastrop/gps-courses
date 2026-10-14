<?php
/**
 * GPS course page (single gps_event), served by Course_Page::template() when
 * the GPS template is on for the course. The site header and footer
 * (Elementor Theme Builder on Hello) come from get_header() / get_footer();
 * the course's own Elementor document is not rendered.
 */
defined('ABSPATH') || exit;

use GPSC\Course_Page;
use GPSC\Course_Stage;

if (have_posts()) {
    the_post();
}
$d        = Course_Page::data(get_the_ID());
$sections = Course_Page::sections($d);
$stage    = $d['stage'];
$on_sale  = in_array($stage, ['open', 'sold_out'], true);
$buyable  = 'open' === $stage && $d['buyable'];
$i        = function ($name, $size = 22) { return Course_Page::icon($name, $size); };
$instr    = $d['instructor'];

get_header();
?>
<main id="content" class="gps-cp gps-cp--<?php echo esc_attr($stage); ?>">
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
                <a href="<?php echo esc_url($d['archive']); ?>"><?php esc_html_e('Courses', 'gps-courses'); ?></a>
                <span aria-hidden="true">›</span>
                <?php if ($d['category']) : ?>
                    <?php $cat_link = get_term_link($d['category']); ?>
                    <a href="<?php echo esc_url(is_wp_error($cat_link) ? $d['archive'] : $cat_link); ?>"><?php echo esc_html($d['category']->name); ?></a>
                <?php else : ?>
                    <span aria-current="page"><?php echo esc_html($d['white'] ? rtrim($d['white'], ':') : $d['title']); ?></span>
                <?php endif; ?>
            </nav>

            <div class="gps-cp-hero__chips">
                <span class="gps-cp-tag"><?php echo esc_html($d['category'] ? $d['category']->name : __('Hands-on course', 'gps-courses')); ?></span>
                <?php if ('' !== $d['chip']['label']) : ?>
                    <span class="gps-cp-chip gps-cp-chip--<?php echo esc_attr($d['chip']['tone']); ?>"><?php echo esc_html($d['chip']['label']); ?></span>
                <?php endif; ?>
            </div>

            <?php $gps_len = function_exists('mb_strlen') ? mb_strlen($d['title'], 'UTF-8') : strlen($d['title']); ?>
            <h1 class="gps-cp-hero__title<?php echo $gps_len > 48 ? ' gps-cp-hero__title--long' : ''; ?>" id="gps-cp-title">
                <?php echo esc_html($d['white']); ?>
                <?php if ('' !== $d['gold']) : ?>
                    <span class="gps-cp-hl<?php echo $d['gold_keep'] ? ' gps-cp-hl--keep' : ''; ?><?php echo $d['gold_keep_sm'] ? ' gps-cp-hl--keep-sm' : ''; ?>"><?php echo esc_html($d['gold']); ?></span>
                <?php endif; ?>
            </h1>

            <?php if ('' !== $d['tagline']) : ?>
                <p class="gps-cp-hero__tagline"><?php echo esc_html($d['tagline']); ?></p>
            <?php endif; ?>

            <?php if ('past' === $stage) : ?>
                <p class="gps-cp-hero__past"><?php
                    $parts = array_filter([
                        /* translators: %s: course dates */
                        $d['when'] ? sprintf(__('Last held %s', 'gps-courses'), $d['when']) : '',
                        $d['city'],
                        /* translators: %s: number of CE credits */
                        $d['ce'] ? sprintf(__('%s CE credits', 'gps-courses'), $d['ce']) : '',
                        $instr ? $instr['name'] : '',
                    ]);
                    echo esc_html(implode(' · ', $parts));
                ?></p>
            <?php else : ?>
                <ul class="gps-cp-facts">
                    <?php if ($d['when']) : ?>
                        <li class="gps-cp-fact gps-cp-fact--dates">
                            <?php echo $i('calendar'); ?>
                            <span class="gps-cp-fact__body"><span class="gps-cp-fact__label"><?php esc_html_e('Dates', 'gps-courses'); ?></span><span class="gps-cp-fact__value"><?php echo esc_html($d['when']); ?></span><?php if ($d['times']) : ?><span class="gps-cp-fact__detail"><?php echo esc_html($d['times']); ?></span><?php endif; ?></span>
                        </li>
                    <?php endif; ?>
                    <?php if ($d['city'] || $d['venue']) : ?>
                        <li class="gps-cp-fact gps-cp-fact--wide">
                            <?php echo $i('pin'); ?>
                            <span class="gps-cp-fact__body"><span class="gps-cp-fact__label"><?php esc_html_e('Location', 'gps-courses'); ?></span><span class="gps-cp-fact__value"><?php echo esc_html($d['city'] ?: $d['venue']); ?></span><?php if ($d['city'] && $d['venue']) : ?><span class="gps-cp-fact__detail"><?php echo esc_html($d['venue']); ?></span><?php endif; ?></span>
                        </li>
                    <?php endif; ?>
                    <?php if ($d['ce']) : ?>
                        <li class="gps-cp-fact gps-cp-fact--ce">
                            <?php echo $i('award'); ?>
                            <span class="gps-cp-fact__body"><span class="gps-cp-fact__label"><?php esc_html_e('CE credits', 'gps-courses'); ?></span><span class="gps-cp-fact__value"><?php echo esc_html(sprintf(__('%s CE', 'gps-courses'), $d['ce'])); ?></span><span class="gps-cp-fact__detail"><?php esc_html_e('PACE approved', 'gps-courses'); ?></span></span>
                        </li>
                    <?php endif; ?>
                    <?php if ($instr) : ?>
                        <li class="gps-cp-fact gps-cp-fact--wide">
                            <?php echo $i('people'); ?>
                            <span class="gps-cp-fact__body"><span class="gps-cp-fact__label"><?php echo esc_html(_n('Instructor', 'Instructors', max(1, count($d['speakers'])), 'gps-courses')); ?></span><span class="gps-cp-fact__value"><?php echo esc_html($instr['name']); ?></span><?php
                                $detail = $instr['cred'] ?: $instr['title'];
                                if (count($d['speakers']) > 1) {
                                    /* translators: %d: number of other instructors */
                                    $detail = sprintf(_n('+ %d more instructor', '+ %d more instructors', count($d['speakers']) - 1, 'gps-courses'), count($d['speakers']) - 1);
                                }
                                if ($detail) : ?><span class="gps-cp-fact__detail"><?php echo esc_html($detail); ?></span><?php endif; ?></span>
                        </li>
                    <?php endif; ?>
                </ul>
                <?php
                // Phone: location and instructor on one line under the two tiles
                $line = array_filter([$d['city'] ?: $d['venue'], $instr ? $instr['full'] : '']);
                if ($line) : ?>
                    <p class="gps-cp-hero__line"><?php echo esc_html(implode(' · ', $line)); ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <?php if (count($sections) > 1) : ?>
    <!-- On this page -->
    <nav class="gps-cp-nav" aria-label="<?php esc_attr_e('On this page', 'gps-courses'); ?>">
        <div class="gps-cp-wrap gps-cp-nav__inner">
            <?php foreach ($sections as $anchor => $label) : ?>
                <a class="gps-cp-nav__link" href="#<?php echo esc_attr($anchor); ?>"><?php echo esc_html($label); ?></a>
            <?php endforeach; ?>
        </div>
    </nav>
    <?php endif; ?>

    <div class="gps-cp-wrap gps-cp-body">

        <!-- Registration (first in the source so phones show it right after the hero) -->
        <aside class="gps-cp-book gps-cp-book--<?php echo esc_attr($stage); ?>" id="registration" aria-labelledby="gps-cp-book-h">
            <?php if ($on_sale) : ?>
                <div class="gps-cp-book__head">
                    <h2 class="gps-cp-book__title" id="gps-cp-book-h"><?php esc_html_e('Register', 'gps-courses'); ?></h2>
                    <?php
                    if ('sold_out' === $stage) {
                        $tone = 'sold_out';
                        $label = __('Sold out', 'gps-courses');
                    } elseif ('few' === $d['chip']['tone']) {
                        $tone = 'few';
                        /* translators: %d: seats left */
                        $label = sprintf(_n('%d seat left', '%d seats left', (int) $d['seats'], 'gps-courses'), (int) $d['seats']);
                    } else {
                        $tone = 'open';
                        $label = __('Open', 'gps-courses');
                    }
                    ?>
                    <span class="gps-cp-chip gps-cp-chip--<?php echo esc_attr($tone); ?>"><?php echo esc_html($label); ?></span>
                </div>

                <?php if ($d['countdown']) : ?>
                    <div class="gps-cp-countdown" data-target="<?php echo esc_attr($d['countdown']['target']); ?>" role="timer" aria-label="<?php esc_attr_e('Time until the course starts', 'gps-courses'); ?>">
                        <?php foreach (['days' => __('days', 'gps-courses'), 'hours' => __('hours', 'gps-courses'), 'minutes' => __('min', 'gps-courses')] as $unit => $unit_label) : ?>
                            <span class="gps-cp-countdown__cell"><span class="gps-cp-countdown__num" data-unit="<?php echo esc_attr($unit); ?>"><?php echo (int) $d['countdown'][$unit]; ?></span><span class="gps-cp-countdown__unit"><?php echo esc_html($unit_label); ?></span></span>
                        <?php endforeach; ?>
                    </div>
                    <p class="gps-cp-countdown__line"><?php
                        /* translators: %d: days until the course starts */
                        echo esc_html($d['countdown']['days'] > 0 ? sprintf(_n('Starts in %d day', 'Starts in %d days', $d['countdown']['days'], 'gps-courses'), $d['countdown']['days']) : __('Starts within a day', 'gps-courses'));
                    ?></p>
                <?php endif; ?>

                <?php Course_Page::render_tickets($d); ?>

                <?php if ($buyable) : ?>
                    <p class="gps-cp-secure"><?php echo $i('lock', 16); ?><span><?php esc_html_e('Secure payment. Your ticket and QR code arrive by email.', 'gps-courses'); ?></span></p>
                <?php endif; ?>
                <?php if ($d['refund']) : ?>
                    <details class="gps-cp-refund">
                        <summary><?php esc_html_e('Refund policy', 'gps-courses'); ?></summary>
                        <ul>
                            <?php foreach ($d['refund'] as $rule) : ?>
                                <li><?php echo esc_html($rule); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>

                <?php
                $cal   = Course_Page::calendar_links($d);
                $share = Course_Page::share_links($d);
                ?>
                <div class="gps-cp-tools">
                    <?php if ($cal) : ?>
                        <details class="gps-cp-menu">
                            <summary class="gps-cp-btn gps-cp-btn--ghost"><?php esc_html_e('Add to calendar', 'gps-courses'); ?></summary>
                            <ul class="gps-cp-menu__list">
                                <?php foreach ($cal as $key => $c) : ?>
                                    <li><a href="<?php echo 'ics' === $key ? esc_attr($c['url']) : esc_url($c['url']); ?>"<?php echo isset($c['download']) ? ' download="' . esc_attr($c['download']) . '"' : ' target="_blank" rel="noopener"'; ?>><?php echo esc_html($c['label']); ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>
                    <details class="gps-cp-menu gps-cp-share" data-url="<?php echo esc_url($d['url']); ?>" data-title="<?php echo esc_attr($d['title']); ?>">
                        <summary class="gps-cp-btn gps-cp-btn--ghost"><?php esc_html_e('Share', 'gps-courses'); ?></summary>
                        <ul class="gps-cp-menu__list">
                            <li><button type="button" class="gps-cp-copy" data-url="<?php echo esc_url($d['url']); ?>"><?php esc_html_e('Copy link', 'gps-courses'); ?></button></li>
                            <?php foreach ($share as $s) : ?>
                                <li><a href="<?php echo esc_url($s['url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($s['label']); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                </div>

                <?php
                if ($d['others']) {
                    echo Course_Stage::render_other_dates($d['id'], ['editions' => $d['others'], 'class' => 'gps-cp-others']); // escaped inside
                }
                ?>
            <?php else : ?>
                <h2 class="gps-sr" id="gps-cp-book-h"><?php esc_html_e('Registration', 'gps-courses'); ?></h2>
                <?php echo Course_Stage::render_closed_box($d['id'], $stage, ['heading_tag' => 'h3', 'class' => 'gps-cp-stagebox']); // escaped inside ?>
            <?php endif; ?>
        </aside>

        <div class="gps-cp-main">

            <?php if (isset($sections['overview'])) : ?>
                <section class="gps-cp-card" id="overview" aria-labelledby="gps-cp-overview-h">
                    <h2 class="gps-cp-h2" id="gps-cp-overview-h"><?php esc_html_e('Overview', 'gps-courses'); ?></h2>
                    <?php if ('' !== $d['overview']) : ?>
                        <div class="gps-cp-prose gps-cp-clamp" id="gps-cp-overview-text"><?php echo $d['overview']; // wp_kses_post / esc_html in Course_Page::overview_html() ?></div>
                    <?php endif; ?>
                    <?php
                    $tiles = array_filter([
                        __('Format', 'gps-courses')            => $d['format'],
                        __('Who should attend', 'gps-courses') => $d['audience'],
                        __('Included', 'gps-courses')          => $d['included'],
                    ]);
                    if ($tiles) : ?>
                        <dl class="gps-cp-glance gps-cp-glance--<?php echo (int) count($tiles); ?>">
                            <?php foreach ($tiles as $label => $value) : ?>
                                <div class="gps-cp-glance__tile">
                                    <dt><?php echo esc_html($label); ?></dt>
                                    <?php if (is_array($value) && count($value) > 1) : ?>
                                        <dd><ul><?php foreach ($value as $item) : ?><li><?php echo esc_html($item); ?></li><?php endforeach; ?></ul></dd>
                                    <?php else : ?>
                                        <dd><?php echo esc_html(is_array($value) ? $value[0] : $value); ?></dd>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>
                    <?php if ('' !== $d['prereq']) : ?>
                        <p class="gps-cp-prereq"><strong><?php esc_html_e('Prerequisites:', 'gps-courses'); ?></strong> <?php echo nl2br(esc_html($d['prereq'])); ?></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($d['objectives']) : ?>
                <section class="gps-cp-card" id="learn" aria-labelledby="gps-cp-learn-h">
                    <h2 class="gps-cp-h2" id="gps-cp-learn-h"><?php esc_html_e("What you'll learn", 'gps-courses'); ?></h2>
                    <ul class="gps-cp-checks">
                        <?php foreach ($d['objectives'] as $objective) : ?>
                            <li><?php echo $i('check'); ?><span><?php echo esc_html($objective); ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if ($d['agenda']) : ?>
                <section class="gps-cp-card" id="agenda" aria-labelledby="gps-cp-agenda-h">
                    <h2 class="gps-cp-h2" id="gps-cp-agenda-h"><?php esc_html_e('Agenda', 'gps-courses'); ?></h2>
                    <?php $multi = count($d['agenda']) > 1; ?>
                    <?php if ($multi) : ?>
                        <div class="gps-cp-tabs" role="tablist" hidden aria-label="<?php esc_attr_e('Course days', 'gps-courses'); ?>">
                            <?php foreach ($d['agenda'] as $n => $day) : ?>
                                <button type="button" class="gps-cp-tab" role="tab" id="gps-cp-tab-<?php echo (int) $n; ?>" aria-controls="gps-cp-day-<?php echo (int) $n; ?>" aria-selected="<?php echo 0 === $n ? 'true' : 'false'; ?>" tabindex="<?php echo 0 === $n ? '0' : '-1'; ?>">
                                    <?php echo esc_html($day['label']); ?><?php if ($day['long']) : ?><span class="gps-cp-tab__date"> · <span class="gps-cp-tab__long"><?php echo esc_html($day['long']); ?></span><span class="gps-cp-tab__short"><?php echo esc_html($day['short']); ?></span></span><?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($d['agenda'] as $n => $day) : ?>
                        <div class="gps-cp-day" id="gps-cp-day-<?php echo (int) $n; ?>"<?php echo $multi ? ' role="tabpanel" aria-labelledby="gps-cp-tab-' . (int) $n . '" tabindex="0"' : ''; ?>>
                            <?php if ($multi) : ?>
                                <h3 class="gps-cp-day__date gps-cp-day__label"><?php echo esc_html(trim($day['label'] . ($day['long'] ? ' · ' . $day['long'] : ''))); ?></h3>
                            <?php elseif ($day['long']) : ?>
                                <p class="gps-cp-day__date"><?php echo esc_html($day['label'] . ' · ' . $day['long']); ?></p>
                            <?php endif; ?>
                            <ol class="gps-cp-timeline">
                                <?php foreach ($day['rows'] as $row) : ?>
                                    <?php if ('break' === $row['kind']) : ?>
                                        <li class="gps-cp-tl gps-cp-tl--break">
                                            <span class="gps-cp-tl__time"><?php echo esc_html($row['start']); ?></span>
                                            <span class="gps-cp-tl__break"><?php echo $i($row['icon'], 16); ?><span><?php echo esc_html($row['title']); ?><?php if ($row['until']) : ?> <span class="gps-cp-tl__until">· <?php echo esc_html(sprintf(__('until %s', 'gps-courses'), $row['until'])); ?></span><?php endif; ?></span></span>
                                        </li>
                                    <?php else : ?>
                                        <li class="gps-cp-tl gps-cp-tl--session">
                                            <span class="gps-cp-tl__when">
                                                <span class="gps-cp-tl__start"><?php echo esc_html($row['start']); ?></span>
                                                <?php if ($row['until']) : ?>
                                                    <span class="gps-cp-tl__meta"><?php echo esc_html(sprintf(__('until %s', 'gps-courses'), $row['until'])); ?><?php if ($row['duration']) : ?> <span class="gps-cp-tl__dur">· <?php echo esc_html($row['duration']); ?></span><?php endif; ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <div class="gps-cp-tl__card">
                                                <p class="gps-cp-tl__title"><?php echo esc_html($row['title']); ?></p>
                                                <?php if ('' !== $row['description']) : ?>
                                                    <div class="gps-cp-tl__desc"><?php echo $row['description']; // wp_kses_post ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

            <?php if ($d['speakers']) : ?>
                <section class="gps-cp-card" id="instructor" aria-labelledby="gps-cp-instructor-h">
                    <h2 class="gps-cp-h2" id="gps-cp-instructor-h"><?php echo esc_html(_n('Instructor', 'Instructors', count($d['speakers']), 'gps-courses')); ?></h2>
                    <div class="gps-cp-people<?php echo count($d['speakers']) > 1 ? ' gps-cp-people--grid' : ''; ?>">
                        <?php foreach ($d['speakers'] as $s) : ?>
                            <article class="gps-cp-person">
                                <?php if ($s['photo']) : ?>
                                    <?php echo wp_get_attachment_image($s['photo'], 'medium', false, ['class' => 'gps-cp-person__photo', 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(max-width: 767px) 84px, 140px']); ?>
                                <?php endif; ?>
                                <div class="gps-cp-person__body">
                                    <h3 class="gps-cp-person__name"><?php echo esc_html($s['full']); ?></h3>
                                    <?php if ('' !== $s['title']) : ?>
                                        <p class="gps-cp-person__title"><?php echo esc_html($s['title']); ?></p>
                                    <?php endif; ?>
                                    <?php if ('' !== $s['short']) : ?>
                                        <p class="gps-cp-person__bio"><?php echo esc_html($s['short']); ?></p>
                                    <?php endif; ?>
                                    <?php if ('' !== $s['bio']) : ?>
                                        <details class="gps-cp-person__more">
                                            <summary><?php esc_html_e('Read full bio', 'gps-courses'); ?></summary>
                                            <div class="gps-cp-prose"><?php echo $s['bio']; // wp_kses_post ?></div>
                                        </details>
                                    <?php endif; ?>
                                    <?php if ('' !== $s['url']) : ?>
                                        <a class="gps-cp-link" href="<?php echo esc_url($s['url']); ?>"><?php esc_html_e('View full profile', 'gps-courses'); ?> <span aria-hidden="true">→</span></a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ('' !== $d['extra']) : ?>
                <section class="gps-cp-card" id="more" aria-labelledby="gps-cp-extra-h">
                    <h2 class="gps-cp-h2" id="gps-cp-extra-h"><?php echo esc_html($d['extra_title'] ?: __('About this course', 'gps-courses')); ?></h2>
                    <div class="gps-cp-prose"><?php echo $d['extra']; // wp_kses_post ?></div>
                </section>
            <?php endif; ?>

            <?php if ($d['gallery']) : ?>
                <section class="gps-cp-card" id="gallery" aria-labelledby="gps-cp-gallery-h">
                    <h2 class="gps-cp-h2" id="gps-cp-gallery-h"><?php esc_html_e('Gallery', 'gps-courses'); ?></h2>
                    <ul class="gps-cp-gallery">
                        <?php foreach ($d['gallery'] as $n => $img) :
                            $full = wp_get_attachment_image_url($img, 'full');
                            $alt  = trim((string) get_post_meta($img, '_wp_attachment_image_alt', true));
                            ?>
                            <li>
                                <a href="<?php echo esc_url($full); ?>" class="gps-cp-gallery__link" data-index="<?php echo (int) $n; ?>">
                                    <?php echo wp_get_attachment_image($img, 'medium_large', false, ['loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(max-width: 767px) 50vw, 240px', 'alt' => $alt]); ?>
                                    <?php if ('' === $alt) : ?><span class="gps-sr"><?php echo esc_html(sprintf(__('Course photo %d', 'gps-courses'), $n + 1)); ?></span><?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if (isset($sections['location'])) : ?>
                <section class="gps-cp-card" id="location" aria-labelledby="gps-cp-location-h">
                    <h2 class="gps-cp-h2" id="gps-cp-location-h"><?php esc_html_e('Location', 'gps-courses'); ?></h2>
                    <div class="gps-cp-location">
                        <div class="gps-cp-location__text">
                            <?php if ($d['venue']) : ?><strong class="gps-cp-location__venue"><?php echo esc_html($d['venue']); ?></strong><?php endif; ?>
                            <address><?php echo esc_html($d['address']); ?><?php if ($d['line2']) : ?><br><?php echo esc_html($d['line2']); ?><?php endif; ?></address>
                            <?php if ($d['contact']) : ?>
                                <p><?php esc_html_e('Questions:', 'gps-courses'); ?> <a href="mailto:<?php echo esc_attr($d['contact']); ?>"><?php echo esc_html($d['contact']); ?></a></p>
                            <?php endif; ?>
                            <a class="gps-cp-btn gps-cp-btn--outline" href="<?php echo esc_url('https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($d['map_q'])); ?>" target="_blank" rel="noopener"><?php esc_html_e('Get directions', 'gps-courses'); ?></a>
                        </div>
                        <?php if ($d['map_key']) : ?>
                            <div class="gps-cp-map">
                                <iframe title="<?php echo esc_attr(sprintf(__('Map: %s', 'gps-courses'), $d['venue'] ?: $d['city'])); ?>" src="<?php echo esc_url('https://www.google.com/maps/embed/v1/place?key=' . rawurlencode($d['map_key']) . '&q=' . rawurlencode($d['map_q'])); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                            </div>
                        <?php elseif ($d['map_q']) : ?>
                            <div class="gps-cp-map">
                                <iframe title="<?php echo esc_attr(sprintf(__('Map: %s', 'gps-courses'), $d['venue'] ?: $d['city'])); ?>" src="<?php echo esc_url('https://maps.google.com/maps?output=embed&z=15&q=' . rawurlencode($d['map_q'])); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                            </div>
                        <?php else : ?>
                            <a class="gps-cp-map gps-cp-map--static" href="<?php echo esc_url('https://www.google.com/maps/search/?api=1&query=' . rawurlencode($d['map_q'])); ?>" target="_blank" rel="noopener">
                                <?php echo $i('map', 32); ?>
                                <span><?php esc_html_e('View on Google Maps', 'gps-courses'); ?></span>
                                <small><?php echo esc_html($d['city'] ?: $d['venue']); ?></small>
                            </a>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($d['faqs']) : ?>
                <section class="gps-cp-card" id="faq" aria-labelledby="gps-cp-faq-h">
                    <h2 class="gps-cp-h2" id="gps-cp-faq-h"><?php esc_html_e('Frequently asked questions', 'gps-courses'); ?></h2>
                    <div class="gps-cp-faq">
                        <?php foreach ($d['faqs'] as $n => $f) : ?>
                            <details class="gps-cp-faq__item"<?php echo 0 === $n ? ' open' : ''; ?>>
                                <summary><?php echo esc_html($f['q']); ?><?php echo $i('chevron', 20); ?></summary>
                                <div class="gps-cp-faq__a"><?php echo wpautop($f['a']); // kses'd in Course_Page::clean_faqs() ?></div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($d['sponsors']) : ?>
        <section class="gps-cp-sponsors" aria-labelledby="gps-cp-sponsors-h">
            <div class="gps-cp-wrap gps-cp-sponsors__inner">
                <h2 class="gps-cp-sponsors__title" id="gps-cp-sponsors-h"><?php echo esc_html(_n('Course sponsor', 'Course sponsors', count($d['sponsors']), 'gps-courses')); ?></h2>
                <ul class="gps-cp-sponsors__list">
                    <?php foreach ($d['sponsors'] as $sp) :
                        $logo = $sp['logo'] ? wp_get_attachment_image($sp['logo'], 'medium', false, ['alt' => $sp['name'], 'loading' => 'lazy', 'decoding' => 'async', 'class' => 'gps-cp-sponsors__logo']) : '<span class="gps-cp-sponsors__name">' . esc_html($sp['name']) . '</span>';
                        ?>
                        <li>
                            <?php if ($sp['url']) : ?>
                                <a href="<?php echo esc_url($sp['url']); ?>" target="_blank" rel="noopener sponsored"><?php echo $logo; // core output or escaped ?></a>
                            <?php else : ?>
                                <?php echo $logo; // core output or escaped ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    <?php endif; ?>

    <?php
    // Closing call to action (stage-aware)
    $band = null;
    switch ($stage) {
        case 'open':
            if ($buyable) {
                $sub = array_filter([
                    $d['ce'] ? sprintf(__('%s CE credits', 'gps-courses'), $d['ce']) : '',
                    $d['city'],
                    /* translators: %d: seats left */
                    null !== $d['seats'] ? sprintf(_n('%d seat left', '%d seats left', (int) $d['seats'], 'gps-courses'), (int) $d['seats']) : '',
                ]);
                $band = [
                    /* translators: %s: course dates */
                    'title' => $d['when'] ? sprintf(__('Ready to join us on %s?', 'gps-courses'), $d['when']) : __('Ready to join us?', 'gps-courses'),
                    'sub'   => implode(' · ', $sub),
                    'label' => $d['cta'],
                ];
            }
            break;
        case 'sold_out':
            $band = ['title' => __('This course is sold out', 'gps-courses'), 'sub' => __("Join the waitlist and we'll email you if a seat opens.", 'gps-courses'), 'label' => __('Join the waitlist', 'gps-courses')];
            break;
        case 'soon':
            $band = ['title' => $d['chip']['label'], 'sub' => $d['when'] ? sprintf(__('Course dates: %s', 'gps-courses'), $d['when']) : '', 'label' => __('Get notified', 'gps-courses')];
            break;
        case 'in_progress':
            if ($d['others']) {
                $band = ['title' => __('This course is happening now', 'gps-courses'), 'sub' => __('Registration is open for other dates.', 'gps-courses'), 'label' => __('See other dates', 'gps-courses')];
            }
            break;
        default:
            $band = ['title' => __('Want to join the next edition?', 'gps-courses'), 'sub' => __("We'll email you as soon as new dates are published.", 'gps-courses'), 'label' => __('Notify me about the next dates', 'gps-courses')];
    }
    if ($band) : ?>
        <section class="gps-cp-band" aria-labelledby="gps-cp-band-h">
            <div class="gps-cp-wrap gps-cp-band__inner">
                <div>
                    <h2 class="gps-cp-band__title" id="gps-cp-band-h"><?php echo esc_html($band['title']); ?></h2>
                    <?php if ($band['sub']) : ?><p class="gps-cp-band__sub"><?php echo esc_html($band['sub']); ?></p><?php endif; ?>
                </div>
                <a class="gps-cp-btn gps-cp-btn--gold" href="#registration"><?php echo esc_html($band['label']); ?></a>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($buyable) : ?>
        <div class="gps-cp-bar" hidden>
            <div class="gps-cp-bar__text">
                <strong><?php echo $d['price_multi'] ? sprintf(esc_html__('From %s', 'gps-courses'), Course_Page::price($d['price_min'])) : Course_Page::price($d['price_min']); // wc_price() output ?></strong>
                <span><?php echo esc_html(implode(' · ', array_filter([$d['when'], $d['ce'] ? sprintf(__('%s CE', 'gps-courses'), $d['ce']) : '']))); ?></span>
            </div>
            <a class="gps-cp-btn gps-cp-btn--primary" href="#registration"><?php echo esc_html($d['cta']); ?></a>
        </div>
    <?php endif; ?>
</main>
<?php
get_footer();
