<?php
/**
 * Contact page, served by Contact_Page::template() when the GPS template is
 * on. Course page design (gps-course.css) plus gps-contact.css; the form is
 * the Fluent Forms contact form, styled by gps-forms.css.
 */
defined('ABSPATH') || exit;

use GPSC\Contact_Page;
use GPSC\Course_Page;

if (have_posts()) {
    the_post();
}
$d = Contact_Page::data();
$i = function ($name, $size = 22) { return Course_Page::icon($name, $size); };

get_header();
?>
<main id="content" class="gps-cp gps-ct">
<script>document.documentElement.classList.add('gps-js');</script>

    <section class="gps-cp-hero<?php echo $d['image'] ? '' : ' gps-cp-hero--plain'; ?>" aria-labelledby="gps-cp-title">
        <?php if ($d['image']) : ?>
            <div class="gps-cp-hero__media" aria-hidden="true">
                <?php echo wp_get_attachment_image($d['image'], 'full', false, ['class' => 'gps-cp-hero__img', 'alt' => '', 'sizes' => '100vw', 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async']); ?>
            </div>
        <?php endif; ?>
        <div class="gps-cp-wrap gps-cp-hero__inner">
            <nav class="gps-cp-crumbs" aria-label="<?php esc_attr_e('Breadcrumb', 'gps-courses'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'gps-courses'); ?></a>
                <span aria-hidden="true">›</span>
                <span aria-current="page"><?php esc_html_e('Contact', 'gps-courses'); ?></span>
            </nav>
            <div class="gps-cp-hero__chips"><span class="gps-cp-tag"><?php esc_html_e('Contact', 'gps-courses'); ?></span></div>
            <h1 class="gps-cp-hero__title" id="gps-cp-title">
                <?php echo esc_html($d['title']); ?>
                <span class="gps-cp-hl gps-ct-hl"><?php echo esc_html($d['gold']); ?></span>
            </h1>
            <p class="gps-cp-hero__tagline"><?php echo esc_html($d['tagline']); ?></p>

            <ul class="gps-cp-facts gps-ct-facts">
                <li class="gps-cp-fact">
                    <?php echo $i('mail'); ?>
                    <span class="gps-cp-fact__body"><span class="gps-cp-fact__label"><?php esc_html_e('Email', 'gps-courses'); ?></span><a class="gps-cp-fact__value gps-ct-mail" href="mailto:<?php echo esc_attr($d['email']); ?>"><?php echo esc_html($d['email']); ?></a></span>
                </li>
                <?php if ($d['city'] || $d['street']) : ?>
                    <li class="gps-cp-fact">
                        <?php echo $i('pin'); ?>
                        <span class="gps-cp-fact__body"><span class="gps-cp-fact__label"><?php esc_html_e('Visit us', 'gps-courses'); ?></span><span class="gps-cp-fact__value"><?php echo esc_html($d['city'] ?: $d['venue']); ?></span><?php if ($d['street']) : ?><span class="gps-cp-fact__detail"><?php echo esc_html($d['street']); ?></span><?php endif; ?></span>
                    </li>
                <?php endif; ?>
                <?php if ($d['gia']) : ?>
                    <li class="gps-cp-fact gps-ct-fact--gia">
                        <?php echo $i('people'); ?>
                        <span class="gps-cp-fact__body"><span class="gps-cp-fact__label"><?php esc_html_e('Quick help', 'gps-courses'); ?></span><span class="gps-cp-fact__value"><?php esc_html_e('GIA, our AI assistant', 'gps-courses'); ?></span><span class="gps-cp-fact__detail"><?php esc_html_e('Answers about courses, any time', 'gps-courses'); ?></span></span>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </section>

    <div class="gps-cp-wrap gps-cp-body gps-ct-body">

        <div class="gps-cp-main">
            <section class="gps-cp-card gps-ct-form" id="message" aria-labelledby="gps-ct-form-h">
                <h2 class="gps-cp-h2" id="gps-ct-form-h"><?php esc_html_e('Send us a message', 'gps-courses'); ?></h2>
                <p class="gps-ct-lead"><?php esc_html_e('Fields marked * are required.', 'gps-courses'); ?></p>
                <?php
                if ($d['form_id'] && shortcode_exists('fluentform')) {
                    echo do_shortcode('[fluentform id="' . (int) $d['form_id'] . '"]'); // Fluent Forms output
                } else {
                    /* translators: %s: contact email */
                    echo '<p>' . sprintf(esc_html__('Write to us at %s.', 'gps-courses'), '<a href="mailto:' . esc_attr($d['email']) . '">' . esc_html($d['email']) . '</a>') . '</p>';
                }
                ?>
            </section>
        </div>

        <aside class="gps-ct-aside" aria-label="<?php esc_attr_e('Contact details and links', 'gps-courses'); ?>">
            <section class="gps-cp-card gps-ct-card gps-ct-details" aria-labelledby="gps-ct-det-h">
                <h2 class="gps-ct-h3" id="gps-ct-det-h"><?php esc_html_e('Contact details', 'gps-courses'); ?></h2>
                <ul class="gps-ct-lines">
                    <li><?php echo $i('mail', 20); ?><a href="mailto:<?php echo esc_attr($d['email']); ?>"><?php echo esc_html($d['email']); ?></a></li>
                    <?php if ($d['street'] || $d['venue']) : ?>
                        <li><?php echo $i('pin', 20); ?><address><?php if ($d['venue']) : ?><strong><?php echo esc_html($d['venue']); ?></strong><br><?php endif; ?><?php echo esc_html($d['street']); ?><?php if ($d['cityline']) : ?><br><?php echo esc_html($d['cityline']); ?><?php endif; ?></address></li>
                    <?php endif; ?>
                </ul>
                <?php if ($d['map_q']) : ?>
                    <div class="gps-cp-map gps-ct-map">
                        <iframe title="<?php echo esc_attr(sprintf(__('Map: %s', 'gps-courses'), $d['venue'] ?: $d['city'])); ?>" src="<?php echo esc_url('https://maps.google.com/maps?output=embed&z=15&q=' . rawurlencode($d['map_q'])); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                    </div>
                    <a class="gps-cp-btn gps-cp-btn--outline gps-ct-directions" href="<?php echo esc_url('https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($d['map_q'])); ?>" target="_blank" rel="noopener"><?php esc_html_e('Get directions', 'gps-courses'); ?></a>
                <?php endif; ?>
            </section>

            <?php if ($d['links']) : ?>
                <nav class="gps-cp-card gps-ct-card gps-ct-links" aria-labelledby="gps-ct-links-h">
                    <h2 class="gps-ct-h3" id="gps-ct-links-h"><?php esc_html_e('Looking for something?', 'gps-courses'); ?></h2>
                    <p class="gps-ct-lead"><?php esc_html_e('You may find it faster here.', 'gps-courses'); ?></p>
                    <ul>
                        <?php foreach ($d['links'] as $l) : ?>
                            <li><a href="<?php echo esc_url($l['url']); ?>"><?php echo esc_html($l['label']); ?> <span aria-hidden="true">→</span></a></li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </aside>
    </div>

    <?php if ($d['gia']) : ?>
        <section class="gps-cp-band" aria-labelledby="gps-cp-band-h">
            <div class="gps-cp-wrap gps-cp-band__inner">
                <div>
                    <h2 class="gps-cp-band__title" id="gps-cp-band-h"><?php esc_html_e('Need an answer right now?', 'gps-courses'); ?></h2>
                    <p class="gps-cp-band__sub"><?php esc_html_e('GIA, our AI assistant, knows our courses, dates and seminars.', 'gps-courses'); ?></p>
                </div>
                <a class="gps-cp-btn gps-cp-btn--gold gps-ct-gia" href="#message"><?php esc_html_e('Chat with GIA', 'gps-courses'); ?></a>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php
get_footer();
