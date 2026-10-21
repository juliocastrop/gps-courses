<?php
/**
 * About page, served by About_Page::template() when the GPS templates are on.
 * Course page design (gps-course.css) plus gps-about.css.
 */
defined('ABSPATH') || exit;

use GPSC\About_Page;
use GPSC\Course_Page;

if (have_posts()) {
    the_post();
}
$d = About_Page::data();
$i = function ($name, $size = 22) { return Course_Page::icon($name, $size); };
$loc = $d['location'];

get_header();
?>
<main id="content" class="gps-cp gps-ab">

    <section class="gps-cp-hero<?php echo $d['hero'] ? '' : ' gps-cp-hero--plain'; ?>" aria-labelledby="gps-cp-title">
        <?php if ($d['hero']) : ?>
            <div class="gps-cp-hero__media" aria-hidden="true">
                <?php echo wp_get_attachment_image($d['hero'], 'full', false, ['class' => 'gps-cp-hero__img', 'alt' => '', 'sizes' => '100vw', 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async']); ?>
            </div>
        <?php endif; ?>
        <div class="gps-cp-wrap gps-cp-hero__inner gps-ab-hero">
            <nav class="gps-cp-crumbs" aria-label="<?php esc_attr_e('Breadcrumb', 'gps-courses'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'gps-courses'); ?></a>
                <span aria-hidden="true">›</span>
                <span aria-current="page"><?php esc_html_e('About', 'gps-courses'); ?></span>
            </nav>
            <div class="gps-cp-hero__chips"><span class="gps-cp-tag"><?php echo esc_html($d['eyebrow']); ?></span></div>
            <h1 class="gps-cp-hero__title" id="gps-cp-title">
                <?php echo esc_html($d['title']); ?>
                <span class="gps-cp-hl gps-ab-hl"><?php echo esc_html($d['gold']); ?></span>
            </h1>
            <p class="gps-cp-hero__tagline"><?php echo esc_html($d['tagline']); ?></p>
            <div class="gps-ab-actions">
                <a class="gps-cp-btn gps-cp-btn--gold" href="<?php echo esc_url($d['courses']); ?>"><?php esc_html_e('Explore courses', 'gps-courses'); ?></a>
                <?php if ($d['seminar']['url']) : ?>
                    <a class="gps-cp-btn gps-ab-btn-line" href="<?php echo esc_url($d['seminar']['url']); ?>"><?php esc_html_e('Monthly seminars', 'gps-courses'); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="gps-cp-wrap gps-ab-mv">
        <section class="gps-cp-card gps-ab-mv__card gps-ab-mv__card--mission" aria-labelledby="gps-ab-mission-h">
            <h2 class="gps-ab-eyebrow" id="gps-ab-mission-h"><?php esc_html_e('Mission', 'gps-courses'); ?></h2>
            <p class="gps-ab-mission"><?php echo esc_html($d['mission']); ?></p>
        </section>
        <section class="gps-cp-card gps-ab-mv__card" aria-labelledby="gps-ab-vision-h">
            <h2 class="gps-ab-eyebrow" id="gps-ab-vision-h"><?php esc_html_e('Vision', 'gps-courses'); ?></h2>
            <p class="gps-ab-text"><?php echo esc_html($d['vision']); ?></p>
        </section>
    </div>

    <section class="gps-cp-wrap gps-ab-section gps-ab-split" aria-labelledby="gps-ab-facility-h">
        <?php if ($d['facility']['image']) : ?>
            <div class="gps-ab-split__media">
                <?php echo wp_get_attachment_image($d['facility']['image'], 'large', false, ['class' => 'gps-ab-photo', 'alt' => __('The GPS building, home of Georgia Prosthodontics and Prostho Dental Lab', 'gps-courses'), 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(max-width: 900px) 100vw, 560px']); ?>
            </div>
        <?php endif; ?>
        <div class="gps-ab-split__body">
            <p class="gps-ab-eyebrow"><?php esc_html_e('Unique facility', 'gps-courses'); ?></p>
            <h2 class="gps-ab-h2" id="gps-ab-facility-h"><?php echo esc_html($d['facility']['title']); ?></h2>
            <?php foreach ($d['facility']['text'] as $para) : ?>
                <p class="gps-ab-text"><?php echo esc_html($para); ?></p>
            <?php endforeach; ?>
            <p class="gps-ab-quote"><?php echo esc_html($d['facility']['quote']); ?></p>
        </div>
    </section>

    <section class="gps-cp-wrap gps-ab-section" aria-labelledby="gps-ab-programs-h">
        <div class="gps-ab-head">
            <p class="gps-ab-eyebrow"><?php esc_html_e('Training programs', 'gps-courses'); ?></p>
            <h2 class="gps-ab-h2" id="gps-ab-programs-h"><?php echo esc_html($d['programs']['title']); ?></h2>
            <p class="gps-ab-text"><?php echo esc_html($d['programs']['lead']); ?></p>
        </div>
        <ul class="gps-ab-pillars">
            <?php foreach ($d['programs']['items'] as $item) : ?>
                <li class="gps-cp-card gps-ab-pillar">
                    <span class="gps-ab-pillar__icon"><?php echo $i($item['icon'], 22); ?></span>
                    <h3 class="gps-ab-h3"><?php echo esc_html($item['title']); ?></h3>
                    <p class="gps-ab-text"><?php echo esc_html($item['text']); ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($d['programs']['image']) : ?>
            <?php echo wp_get_attachment_image($d['programs']['image'], 'full', false, ['class' => 'gps-ab-photo gps-ab-wide', 'alt' => __('Hands-on training at GPS', 'gps-courses'), 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(max-width: 1180px) 100vw, 1140px']); ?>
        <?php endif; ?>
    </section>

    <?php if ($d['team']) : ?>
        <section class="gps-cp-wrap gps-ab-section" id="team" aria-labelledby="gps-ab-team-h">
            <div class="gps-ab-head">
                <p class="gps-ab-eyebrow"><?php esc_html_e('The team', 'gps-courses'); ?></p>
                <h2 class="gps-ab-h2" id="gps-ab-team-h"><?php esc_html_e('Meet your instructors', 'gps-courses'); ?></h2>
            </div>
            <ul class="gps-ab-team">
                <?php foreach ($d['team'] as $s) : ?>
                    <li class="gps-ab-member">
                        <a class="gps-ab-member__link" href="<?php echo esc_url(About_Page::link($s['url'])); ?>">
                            <span class="gps-ab-member__photo">
                                <?php if ($s['photo']) : ?>
                                    <?php echo wp_get_attachment_image($s['photo'], 'medium_large', false, ['alt' => '', 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(max-width: 767px) 100vw, 380px']); ?>
                                <?php endif; ?>
                            </span>
                            <span class="gps-ab-member__body">
                                <span class="gps-ab-member__name"><?php echo esc_html($s['full']); ?></span>
                                <?php if ('' !== $s['summary']) : ?><span class="gps-ab-member__role"><?php echo esc_html($s['summary']); ?></span><?php endif; ?>
                                <span class="gps-ab-member__more"><?php esc_html_e('View profile', 'gps-courses'); ?> <span aria-hidden="true">→</span></span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($d['seminar']['url']) : ?>
        <section class="gps-cp-wrap gps-ab-section" aria-labelledby="gps-ab-seminar-h">
            <div class="gps-ab-club">
                <div class="gps-ab-club__body">
                    <p class="gps-ab-eyebrow gps-ab-eyebrow--gold"><?php esc_html_e('Monthly seminars', 'gps-courses'); ?></p>
                    <h2 class="gps-ab-h2 gps-ab-h2--light" id="gps-ab-seminar-h"><?php echo esc_html($d['seminar']['title']); ?></h2>
                    <p class="gps-ab-club__text"><?php echo esc_html($d['seminar']['text']); ?></p>
                    <ul class="gps-ab-club__chips">
                        <?php foreach ($d['seminar']['chips'] as $chip) : ?><li><?php echo esc_html($chip); ?></li><?php endforeach; ?>
                    </ul>
                </div>
                <a class="gps-cp-btn gps-cp-btn--gold" href="<?php echo esc_url($d['seminar']['url']); ?>"><?php esc_html_e('See the seminar', 'gps-courses'); ?></a>
            </div>
        </section>
    <?php endif; ?>

    <section class="gps-cp-wrap gps-ab-section gps-ab-loc" aria-labelledby="gps-ab-loc-h">
        <div class="gps-ab-loc__body">
            <p class="gps-ab-eyebrow"><?php esc_html_e('Location', 'gps-courses'); ?></p>
            <h2 class="gps-ab-h2" id="gps-ab-loc-h"><?php echo esc_html($loc['title']); ?></h2>
            <p class="gps-ab-text"><?php echo esc_html($loc['text']); ?></p>
            <div class="gps-cp-card gps-ab-contact">
                <ul class="gps-ab-lines">
                    <?php if ($loc['street'] || $loc['venue']) : ?>
                        <li><?php echo $i('pin', 20); ?><address><?php if ($loc['venue']) : ?><strong><?php echo esc_html($loc['venue']); ?></strong><br><?php endif; ?><?php echo esc_html($loc['street']); ?><?php if ($loc['cityline']) : ?><br><?php echo esc_html($loc['cityline']); ?><?php endif; ?></address></li>
                    <?php endif; ?>
                    <li><?php echo $i('mail', 20); ?><a href="mailto:<?php echo esc_attr($d['email']); ?>"><?php echo esc_html($d['email']); ?></a></li>
                </ul>
                <?php if ($loc['map_q']) : ?>
                    <a class="gps-cp-btn gps-cp-btn--outline" href="<?php echo esc_url('https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($loc['map_q'])); ?>" target="_blank" rel="noopener"><?php esc_html_e('Get directions', 'gps-courses'); ?></a>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($loc['map_q']) : ?>
            <div class="gps-cp-map gps-ab-map">
                <iframe title="<?php echo esc_attr(sprintf(__('Map: %s', 'gps-courses'), $loc['venue'] ?: $loc['city'])); ?>" src="<?php echo esc_url('https://maps.google.com/maps?output=embed&z=15&q=' . rawurlencode($loc['map_q'])); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php
get_footer();
