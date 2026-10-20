<?php
/**
 * GPS course catalog (/courses/ and course category/tag archives).
 */
defined('ABSPATH') || exit;

use GPSC\Course_Catalog;
use GPSC\Credits;

$gps_term     = is_tax(Course_Catalog::TAXONOMIES) ? get_queried_object() : null;
$gps_upcoming = Course_Catalog::courses('upcoming', 50, $gps_term);
$gps_past     = Course_Catalog::courses('past', 12, $gps_term);
$gps_title    = $gps_term ? $gps_term->name : __('Courses', 'gps-courses');

get_header();
?>
<main id="content" class="gps-catalog-main">
<div id="gps-catalog" class="gps-catalog">
    <section class="gps-catalog__hero">
        <div class="gps-catalog__wrap">
            <p class="gps-catalog__eyebrow"><?php esc_html_e('Hands-on continuing education · Duluth, GA', 'gps-courses'); ?></p>
            <h1><?php echo esc_html($gps_title); ?></h1>
            <p class="gps-catalog__lead">
                <?php
                echo $gps_term && $gps_term->description
                    ? esc_html(wp_strip_all_tags($gps_term->description))
                    : esc_html__('Advanced education in implant, restorative and digital dentistry. Every course awards CE credits through a Nationally Approved PACE Program Provider for FAGD/MAGD credit.', 'gps-courses');
                ?>
            </p>
        </div>
    </section>

    <div class="gps-catalog__wrap gps-catalog__body">
        <section aria-labelledby="gps-up-h">
            <h2 id="gps-up-h" class="gps-catalog__h2"><?php esc_html_e('Upcoming courses', 'gps-courses'); ?></h2>
            <?php if ($gps_upcoming) : ?>
                <div class="gps-catalog__grid">
                    <?php foreach ($gps_upcoming as $i => $c) : ?>
                        <?php Course_Catalog::render_card($c, $i); ?>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="gps-catalog__empty">
                    <p><strong><?php esc_html_e('New dates are being scheduled.', 'gps-courses'); ?></strong> <?php esc_html_e('Follow our monthly seminars or contact us to hear about the next course first.', 'gps-courses'); ?></p>
                    <p>
                        <a class="gps-ccard__btn gps-ccard__btn--inline" href="<?php echo esc_url(get_post_type_archive_link('gps_seminar') ?: home_url('/monthly-seminars/')); ?>"><?php esc_html_e('Monthly seminars', 'gps-courses'); ?></a>
                        <a class="gps-catalog__link" href="<?php echo esc_url(home_url('/contact/')); ?>"><?php esc_html_e('Contact us', 'gps-courses'); ?></a>
                    </p>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($gps_past) : ?>
            <section aria-labelledby="gps-past-h" class="gps-catalog__past">
                <h2 id="gps-past-h" class="gps-catalog__h2"><?php esc_html_e('Past courses', 'gps-courses'); ?></h2>
                <ul class="gps-catalog__list">
                    <?php foreach ($gps_past as $c) : ?>
                        <li>
                            <a href="<?php echo esc_url($c['url']); ?>"><?php echo esc_html($c['title']); ?></a>
                            <span><?php echo esc_html(implode(' · ', array_filter([$c['when'], $c['ce'] > 0 ? Credits::format($c['ce']) . ' CE' : '']))); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </div>
    <?php echo Course_Catalog::json_ld($gps_upcoming); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded ?>
</div>
</main>
<?php
get_footer();
