<?php
/**
 * Faculty profile (/team/<name>/), served by About_Page::template() when the
 * GPS templates are on. Course page design (gps-course.css), the /courses/
 * card (gps-catalog.css) and gps-about.css.
 */
defined('ABSPATH') || exit;

use GPSC\About_Page;
use GPSC\Course_Catalog;

if (have_posts()) {
    the_post();
}
$s = About_Page::speaker(get_the_ID());
if (!$s) {
    // Not published (an editor viewing a draft): nothing public to show
    wp_safe_redirect(home_url('/'), 302);
    exit;
}
$about   = About_Page::page_id();
$courses = About_Page::courses_for($s['id']);
$team    = About_Page::team($s['id'], 6);
$catalog = get_post_type_archive_link('gps_event') ?: home_url('/courses/');
$content = apply_filters('the_content', $s['content']);

get_header();
?>
<main id="content" class="gps-cp gps-ab gps-sp">

    <section class="gps-cp-hero gps-cp-hero--plain" aria-labelledby="gps-cp-title">
        <div class="gps-cp-wrap gps-cp-hero__inner gps-sp-hero">
            <nav class="gps-cp-crumbs" aria-label="<?php esc_attr_e('Breadcrumb', 'gps-courses'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'gps-courses'); ?></a>
                <span aria-hidden="true">›</span>
                <?php if ($about) : ?>
                    <a href="<?php echo esc_url(About_Page::link(get_permalink($about)) . '#team'); ?>"><?php esc_html_e('The team', 'gps-courses'); ?></a>
                    <span aria-hidden="true">›</span>
                <?php endif; ?>
                <span aria-current="page"><?php echo esc_html($s['name']); ?></span>
            </nav>
            <?php if ('' !== $s['headline']) : ?>
                <p class="gps-sp-headline"><?php echo esc_html($s['headline']); ?></p>
            <?php endif; ?>
            <h1 class="gps-cp-hero__title" id="gps-cp-title"><?php echo esc_html($s['full']); ?></h1>
            <?php if ('' !== $s['tagline']) : ?>
                <p class="gps-cp-hero__tagline"><?php echo esc_html($s['tagline']); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <div class="gps-cp-wrap gps-sp-body">
        <aside class="gps-sp-card" aria-label="<?php esc_attr_e('Profile summary', 'gps-courses'); ?>">
            <?php if ($s['photo']) : ?>
                <?php echo wp_get_attachment_image($s['photo'], 'medium_large', false, ['class' => 'gps-sp-card__photo', 'alt' => $s['name'], 'loading' => 'eager', 'decoding' => 'async', 'sizes' => '(max-width: 767px) 100vw, 360px']); ?>
            <?php endif; ?>
            <div class="gps-sp-card__body">
                <?php if ($s['facts']) : ?>
                    <dl class="gps-sp-facts">
                        <?php foreach ($s['facts'] as $f) : ?>
                            <div>
                                <?php if ('' !== $f['label']) : ?><dt><?php echo esc_html($f['label']); ?></dt><?php endif; ?>
                                <dd><?php echo esc_html($f['value']); ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
                <?php if ('' !== $s['linkedin']) : ?>
                    <a class="gps-cp-link" href="<?php echo esc_url($s['linkedin']); ?>" target="_blank" rel="noopener"><?php esc_html_e('LinkedIn profile', 'gps-courses'); ?> <span aria-hidden="true">→</span></a>
                <?php endif; ?>
                <a class="gps-cp-btn gps-cp-btn--primary gps-sp-card__cta" href="<?php echo esc_url($courses ? '#courses' : $catalog); ?>"><?php echo $courses ? esc_html__('See upcoming courses', 'gps-courses') : esc_html__('Explore all courses', 'gps-courses'); ?></a>
            </div>
        </aside>

        <div class="gps-sp-main">
            <?php if ('' !== trim(wp_strip_all_tags($content))) : ?>
                <section class="gps-cp-card" aria-labelledby="gps-sp-bio-h">
                    <h2 class="gps-cp-h2" id="gps-sp-bio-h"><?php esc_html_e('Biography', 'gps-courses'); ?></h2>
                    <div class="gps-cp-prose gps-sp-prose"><?php echo $content; // the_content ?></div>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <section class="gps-cp-wrap gps-ab-section gps-sp-courses" id="courses" aria-labelledby="gps-sp-courses-h">
        <div class="gps-ab-head">
            <p class="gps-ab-eyebrow"><?php esc_html_e('Upcoming', 'gps-courses'); ?></p>
            <h2 class="gps-ab-h2" id="gps-sp-courses-h"><?php
                /* translators: %s: speaker name */
                printf(esc_html__('Courses with %s', 'gps-courses'), esc_html($s['name']));
            ?></h2>
        </div>
        <?php if ($courses) : ?>
            <div class="gps-sp-grid">
                <?php foreach ($courses as $n => $c) { Course_Catalog::render_card($c, $n); } ?>
            </div>
        <?php else : ?>
            <div class="gps-cp-card gps-sp-empty">
                <p><?php esc_html_e('No upcoming dates right now. New courses are added regularly.', 'gps-courses'); ?></p>
                <a class="gps-cp-btn gps-cp-btn--outline" href="<?php echo esc_url($catalog); ?>"><?php esc_html_e('See all courses', 'gps-courses'); ?></a>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($team) : ?>
        <section class="gps-cp-wrap gps-ab-section gps-sp-team" aria-labelledby="gps-sp-team-h">
            <h2 class="gps-ab-h2 gps-ab-h2--sm" id="gps-sp-team-h"><?php echo '1' === (string) get_post_meta($s['id'], About_Page::META_TEAM, true) ? esc_html__('The rest of the team', 'gps-courses') : esc_html__('The GPS team', 'gps-courses'); ?></h2>
            <ul class="gps-sp-others">
                <?php foreach ($team as $o) : ?>
                    <li>
                        <a class="gps-sp-other" href="<?php echo esc_url(About_Page::link($o['url'])); ?>">
                            <?php if ($o['photo']) : ?>
                                <?php echo wp_get_attachment_image($o['photo'], 'thumbnail', false, ['class' => 'gps-sp-other__photo', 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async']); ?>
                            <?php endif; ?>
                            <span class="gps-sp-other__body"><span class="gps-sp-other__name"><?php echo esc_html($o['full']); ?></span><?php if ('' !== $o['summary']) : ?><span class="gps-sp-other__role"><?php echo esc_html($o['summary']); ?></span><?php endif; ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</main>
<?php
get_footer();
