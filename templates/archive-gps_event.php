<?php
/**
 * GPS course catalog (/courses/ and course category/tag archives).
 */
defined('ABSPATH') || exit;

use GPSC\Course_Catalog;
use GPSC\Credits;

$gps_upcoming = Course_Catalog::courses('upcoming');
$gps_past     = Course_Catalog::courses('past', 12);
$gps_term     = is_tax(Course_Catalog::TAXONOMIES) ? get_queried_object() : null;
$gps_title    = $gps_term ? $gps_term->name : __('Courses', 'gps-courses');
$gps_status   = [
    'open'     => __('Registration open', 'gps-courses'),
    'sold_out' => __('Sold out · Join the waitlist', 'gps-courses'),
    'soon'     => __('Registration opens soon', 'gps-courses'),
];

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
                        <article class="gps-ccard">
                            <a class="gps-ccard__cover gps-ccard__cover--<?php echo (int) $i % 3; ?>" href="<?php echo esc_url($c['url']); ?>" tabindex="-1" aria-hidden="true"<?php echo $c['image'] ? ' style="--gps-cover:url(\'' . esc_url(str_replace(["'", '(', ')'], ['%27', '%28', '%29'], $c['image'])) . '\')"' : ''; ?>>
                                <?php if ($c['category']) : ?><span class="gps-ccard__cat"><?php echo esc_html($c['category']); ?></span><?php endif; ?>
                                <?php if ($c['ce'] > 0) : ?><span class="gps-ccard__ce"><?php echo esc_html(Credits::format($c['ce'])); ?> CE</span><?php endif; ?>
                            </a>
                            <div class="gps-ccard__body">
                                <p class="gps-ccard__when"><?php echo esc_html(implode(' · ', array_filter([$c['when'], $c['city']]))); ?></p>
                                <h3><a href="<?php echo esc_url($c['url']); ?>"><?php echo esc_html($c['title']); ?></a></h3>
                                <?php if ($c['excerpt']) : ?><p class="gps-ccard__excerpt"><?php echo esc_html($c['excerpt']); ?></p><?php endif; ?>
                                <div class="gps-ccard__foot">
                                    <span class="gps-ccard__status gps-ccard__status--<?php echo esc_attr($c['status']); ?>"><?php echo esc_html($gps_status[$c['status']]); ?></span>
                                    <?php if (null !== $c['price'] && 'sold_out' !== $c['status']) : ?>
                                        <span class="gps-ccard__price"><?php
                                            /* translators: %s: price */
                                            if ($c['price'] <= 0) {
                                                esc_html_e('Free', 'gps-courses');
                                            } else {
                                                printf(esc_html__('From %s', 'gps-courses'), wp_kses_post(function_exists('wc_price') ? wc_price($c['price']) : '$' . number_format_i18n($c['price'], 2)));
                                            }
                                        ?></span>
                                    <?php endif; ?>
                                </div>
                                <a class="gps-ccard__btn" href="<?php echo esc_url($c['url']); ?>"><?php echo 'sold_out' === $c['status'] ? esc_html__('Join the waitlist', 'gps-courses') : esc_html__('View course', 'gps-courses'); ?></a>
                            </div>
                        </article>
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
