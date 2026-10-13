<?php
/**
 * My Account dashboard: welcome, alerts, summary, next course, recommended
 * courses, recent activity and course-day steps.
 * Replaces woocommerce/templates/myaccount/dashboard.php (keeps its hooks).
 *
 * @var WP_User $current_user
 */
defined('ABSPATH') || exit;

use GPSC\Account_Dashboard;

$gps = Account_Dashboard::data(get_current_user_id());
$num = [Account_Dashboard::class, 'num'];
$today = current_time('Y-m-d');
$next  = $gps['next'];
?>
<div class="gps-dash" id="gps-dash">

    <section class="gps-dash__hello">
        <div>
            <h2><?php
                /* translators: %s: first name */
                printf(esc_html__('Welcome back, %s', 'gps-courses'), esc_html($gps['first_name']));
            ?></h2>
            <p><?php
                $bits = array_filter([
                    $gps['profession'],
                    /* translators: %s: month and year */
                    $gps['member_since'] ? sprintf(__('Member since %s', 'gps-courses'), $gps['member_since']) : '',
                ]);
                echo esc_html(implode(' · ', $bits));
            ?></p>
        </div>
        <div class="gps-dash__hello-acts">
            <a class="gps-dbtn gps-dbtn--gold" href="<?php echo esc_url($gps['courses_url']); ?>"><?php esc_html_e('Browse courses', 'gps-courses'); ?></a>
            <a class="gps-dbtn gps-dbtn--ghost" href="<?php echo esc_url($gps['tickets_url']); ?>"><?php esc_html_e('My tickets', 'gps-courses'); ?></a>
        </div>
    </section>

    <?php if ($gps['alerts']) : ?>
        <div class="gps-dash__alerts">
            <?php foreach ($gps['alerts'] as $a) : ?>
                <div class="gps-dalert gps-dalert--<?php echo esc_attr($a['tone']); ?>">
                    <span class="gps-dalert__ic"><?php echo Account_Dashboard::icon($a['icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
                    <span><b><?php echo esc_html($a['title']); ?></b> <?php echo esc_html($a['text']); ?></span>
                    <a href="<?php echo esc_url($a['link']); ?>"><?php echo esc_html($a['cta']); ?></a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="gps-dash__stats" aria-label="<?php esc_attr_e('Summary', 'gps-courses'); ?>">
        <a class="gps-stat" href="<?php echo esc_url(wc_get_account_endpoint_url('gps-courses')); ?>">
            <span class="gps-stat__k"><?php esc_html_e('Next course', 'gps-courses'); ?></span>
            <?php if ($next) :
                $days = (int) floor((strtotime($next['start_date']) - strtotime($today)) / DAY_IN_SECONDS); ?>
                <span class="gps-stat__v"><?php echo esc_html(date_i18n('M j', strtotime($next['start_date']))); ?></span>
                <span class="gps-stat__s"><?php
                    if ($days <= 0) {
                        esc_html_e('Today', 'gps-courses');
                    } elseif (1 === $days) {
                        esc_html_e('Tomorrow', 'gps-courses');
                    } else {
                        /* translators: %d: days */
                        printf(esc_html__('in %d days', 'gps-courses'), $days);
                    }
                ?></span>
            <?php else : ?>
                <span class="gps-stat__v">—</span>
                <span class="gps-stat__s"><?php esc_html_e('No upcoming courses', 'gps-courses'); ?></span>
            <?php endif; ?>
        </a>

        <a class="gps-stat" href="<?php echo esc_url($gps['credits']['url']); ?>">
            <span class="gps-stat__k"><?php
                /* translators: %d: year */
                printf(esc_html__('CE credits %d', 'gps-courses'), (int) $gps['credits']['year']);
            ?></span>
            <span class="gps-stat__v"><?php echo esc_html(call_user_func($num, $gps['credits']['this_year'])); ?> <small><?php esc_html_e('credits', 'gps-courses'); ?></small></span>
            <span class="gps-stat__s"><?php
                /* translators: %s: total credits */
                printf(esc_html__('Total on record: %s', 'gps-courses'), esc_html(call_user_func($num, $gps['credits']['total'])));
            ?></span>
        </a>

        <?php $sem = $gps['seminar']; ?>
        <a class="gps-stat" href="<?php echo esc_url($sem ? $sem['url'] : wc_get_account_endpoint_url('gps-seminars')); ?>">
            <span class="gps-stat__k"><?php esc_html_e('Monthly seminars', 'gps-courses'); ?></span>
            <?php if ($sem) : ?>
                <span class="gps-stat__v"><?php echo (int) $sem['completed']; ?><small>/<?php echo (int) $sem['total']; ?></small></span>
                <span class="gps-dots" role="img" aria-label="<?php echo esc_attr(sprintf(__('%1$d of %2$d sessions attended', 'gps-courses'), (int) $sem['completed'], (int) $sem['total'])); ?>">
                    <?php for ($i = 1; $i <= (int) $sem['total']; $i++) : ?><i class="<?php echo $i <= (int) $sem['completed'] ? 'is-on' : ''; ?>"></i><?php endfor; ?>
                </span>
                <span class="gps-stat__s"><?php
                    printf(
                        /* translators: 1: credits, 2: makeup status */
                        esc_html__('%1$s CE earned · %2$s', 'gps-courses'),
                        esc_html(call_user_func($num, $sem['credits'])),
                        $sem['makeup_used'] ? esc_html__('makeup used', 'gps-courses') : esc_html__('1 makeup left', 'gps-courses')
                    );
                ?></span>
            <?php else : ?>
                <span class="gps-stat__v">—</span>
                <span class="gps-stat__s"><?php esc_html_e('Not enrolled yet', 'gps-courses'); ?></span>
            <?php endif; ?>
        </a>

        <a class="gps-stat" href="<?php echo esc_url($gps['credits']['url']); ?>">
            <span class="gps-stat__k"><?php esc_html_e('Certificates', 'gps-courses'); ?></span>
            <span class="gps-stat__v"><?php echo (int) count($gps['certificates']); ?></span>
            <span class="gps-stat__s"><?php echo $gps['certificates'] ? esc_html__('ready to download', 'gps-courses') : esc_html__('none yet', 'gps-courses'); ?></span>
        </a>
    </section>

    <?php if ($next) :
        $s = strtotime($next['start_date']);
        $e = strtotime($next['end_date'] ?: $next['start_date']);
        $multi = $e > $s; ?>
        <section class="gps-panel" aria-labelledby="gps-next-h">
            <div class="gps-panel__h">
                <h3 id="gps-next-h"><?php echo 'seminar' === $next['kind'] ? esc_html__('Your next session', 'gps-courses') : esc_html__('Your next course', 'gps-courses'); ?></h3>
                <a href="<?php echo esc_url(wc_get_account_endpoint_url('seminar' === $next['kind'] ? 'gps-seminars' : 'gps-courses')); ?>"><?php esc_html_e('See all', 'gps-courses'); ?></a>
            </div>
            <div class="gps-next">
                <div class="gps-next__date">
                    <span class="gps-next__m"><?php echo esc_html(strtoupper(date_i18n('M', $s))); ?></span>
                    <span class="gps-next__d"><?php echo esc_html(date_i18n('j', $s) . ($multi ? '–' . date_i18n(date('m', $s) === date('m', $e) ? 'j' : 'M j', $e) : '')); ?></span>
                    <span class="gps-next__w"><?php echo esc_html(date_i18n('D', $s) . ($multi ? ' – ' . date_i18n('D', $e) : '')); ?></span>
                </div>
                <div class="gps-next__info">
                    <h4><a href="<?php echo esc_url($next['url']); ?>"><?php echo esc_html($next['title']); ?></a></h4>
                    <div class="gps-next__meta">
                        <?php if ($next['start_time']) : ?>
                            <span><?php echo Account_Dashboard::icon('clock'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?>
                                <?php
                                $t1 = date_i18n(get_option('time_format'), strtotime($next['start_date'] . ' ' . $next['start_time']));
                                $t2 = $next['end_time'] ? date_i18n(get_option('time_format'), strtotime($next['start_date'] . ' ' . $next['end_time'])) : '';
                                echo esc_html($t1 . ($t2 ? ' – ' . $t2 : '') . ($multi ? ' ' . __('each day', 'gps-courses') : ''));
                                ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($next['location']) : ?>
                            <span><?php echo Account_Dashboard::icon('pin'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?><?php echo esc_html($next['location']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="gps-chips gps-chips--static">
                        <?php if ($next['ce_credits'] > 0) : ?>
                            <span class="gps-tag gps-tag--gold"><?php
                                /* translators: %s: credits */
                                printf(esc_html__('%s CE credits', 'gps-courses'), esc_html(call_user_func($num, $next['ce_credits'])));
                            ?></span>
                        <?php endif; ?>
                        <span class="gps-tag gps-tag--ok"><?php echo $next['checked_in'] ? esc_html__('Checked in', 'gps-courses') : esc_html__('Ticket confirmed', 'gps-courses'); ?></span>
                    </div>
                </div>
                <?php if ($next['qr_url']) : ?>
                    <a class="gps-next__qr" href="<?php echo esc_url($next['ticket_url']); ?>">
                        <img src="<?php echo esc_url($next['qr_url']); ?>" alt="<?php esc_attr_e('Ticket QR code', 'gps-courses'); ?>" width="112" height="112" loading="lazy">
                        <span><?php echo esc_html($next['ticket_code']); ?></span>
                    </a>
                <?php endif; ?>
            </div>
            <div class="gps-next__acts">
                <a class="gps-dbtn gps-dbtn--navy" href="<?php echo esc_url($next['ticket_url']); ?>"><?php echo 'seminar' === $next['kind'] ? esc_html__('My seminar pass', 'gps-courses') : esc_html__('Download my ticket', 'gps-courses'); ?></a>
                <a class="gps-dbtn gps-dbtn--line" href="<?php echo esc_url($next['calendar']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Add to calendar', 'gps-courses'); ?></a>
                <?php if ($next['directions']) : ?>
                    <a class="gps-dbtn gps-dbtn--line" href="<?php echo esc_url($next['directions']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Get directions', 'gps-courses'); ?></a>
                <?php endif; ?>
            </div>
        </section>
    <?php else : ?>
        <section class="gps-panel gps-panel--empty">
            <div>
                <h3><?php esc_html_e('No upcoming courses yet', 'gps-courses'); ?></h3>
                <p><?php esc_html_e('When you register for a course, your ticket, schedule and directions show up here.', 'gps-courses'); ?></p>
            </div>
            <a class="gps-dbtn gps-dbtn--navy" href="<?php echo esc_url($gps['courses_url']); ?>"><?php esc_html_e('Find a course', 'gps-courses'); ?></a>
        </section>
    <?php endif; ?>

    <?php if ($gps['recommended']) :
        $has_match = (bool) array_filter(wp_list_pluck($gps['recommended'], 'match'));
        $picked = (array) get_user_meta(get_current_user_id(), 'gps_interests', true); ?>
        <section class="gps-panel" aria-labelledby="gps-rec-h">
            <div class="gps-panel__h">
                <h3 id="gps-rec-h"><?php echo $has_match ? esc_html__('Recommended for you', 'gps-courses') : esc_html__('Upcoming courses', 'gps-courses'); ?></h3>
                <a href="<?php echo esc_url($gps['courses_url']); ?>"><?php esc_html_e('See all courses', 'gps-courses'); ?></a>
                <?php if ($has_match && $picked) : ?>
                    <p><?php
                        /* translators: %s: list of topics */
                        printf(esc_html__('Picked from the topics you chose: %s.', 'gps-courses'), esc_html(implode(', ', array_slice($picked, 0, 3))));
                    ?></p>
                <?php endif; ?>
            </div>
            <div class="gps-courses">
                <?php foreach ($gps['recommended'] as $i => $c) : ?>
                    <article class="gps-course">
                        <a class="gps-course__cover gps-course__cover--<?php echo (int) $i % 3; ?>" href="<?php echo esc_url($c['url']); ?>" tabindex="-1" aria-hidden="true"<?php echo $c['image'] ? ' style="--gps-cover:url(\'' . esc_url(str_replace(["'", '(', ')'], ['%27', '%28', '%29'], $c['image'])) . '\')"' : ''; ?>>
                            <?php if ($c['category']) : ?><span class="gps-course__cat"><?php echo esc_html($c['category']); ?></span><?php endif; ?>
                            <?php if ($c['ce'] > 0) : ?><span class="gps-course__ce"><?php echo esc_html(call_user_func($num, $c['ce'])); ?> CE</span><?php endif; ?>
                        </a>
                        <div class="gps-course__body">
                            <h5><a href="<?php echo esc_url($c['url']); ?>"><?php echo esc_html($c['title']); ?></a></h5>
                            <span class="gps-course__when"><?php echo esc_html(implode(' · ', array_filter([$c['when'], $c['city']]))); ?></span>
                            <?php if ($c['match']) : ?>
                                <span class="gps-course__why"><?php
                                    /* translators: %s: topic */
                                    printf(esc_html__('Matches: %s', 'gps-courses'), esc_html($c['match']));
                                ?></span>
                            <?php endif; ?>
                            <div class="gps-course__row">
                                <?php if (!empty($c['soon'])) : ?>
                                    <span class="gps-tag gps-tag--gold"><?php esc_html_e('Registration opens soon', 'gps-courses'); ?></span>
                                <?php elseif (null !== $c['seats'] && $c['seats'] <= 10) : ?>
                                    <span class="gps-tag"><?php
                                        /* translators: %d: seats */
                                        printf(esc_html(_n('%d seat left', '%d seats left', $c['seats'], 'gps-courses')), (int) $c['seats']);
                                    ?></span>
                                <?php else : ?>
                                    <span class="gps-tag"><?php esc_html_e('Open', 'gps-courses'); ?></span>
                                <?php endif; ?>
                                <a href="<?php echo esc_url($c['url']); ?>"><?php esc_html_e('View course', 'gps-courses'); ?></a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="gps-dash__two">
        <section class="gps-panel" aria-labelledby="gps-act-h">
            <div class="gps-panel__h">
                <h3 id="gps-act-h"><?php esc_html_e('Recent activity', 'gps-courses'); ?></h3>
                <a href="<?php echo esc_url(wc_get_account_endpoint_url('gps-attendance')); ?>"><?php esc_html_e('Attendance history', 'gps-courses'); ?></a>
            </div>
            <?php if ($gps['activity']) : ?>
                <ul class="gps-feed">
                    <?php foreach ($gps['activity'] as $a) : ?>
                        <li>
                            <span class="gps-feed__ic"><?php echo Account_Dashboard::icon($a['icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
                            <span><b><?php echo esc_html($a['title']); ?></b><?php if ($a['text']) : ?><br><span><?php echo esc_html($a['text']); ?></span><?php endif; ?></span>
                            <time datetime="<?php echo esc_attr($a['date']); ?>"><?php echo $a['date'] ? esc_html(date_i18n('M j', strtotime($a['date']))) : ''; ?></time>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="gps-panel__empty"><?php esc_html_e('Your check-ins, CE credits and orders will show up here.', 'gps-courses'); ?></p>
            <?php endif; ?>
        </section>

        <section class="gps-panel" aria-labelledby="gps-how-h">
            <div class="gps-panel__h"><h3 id="gps-how-h"><?php esc_html_e('On course day', 'gps-courses'); ?></h3></div>
            <ol class="gps-steps-list">
                <li><span><b><?php esc_html_e('Bring your QR ticket', 'gps-courses'); ?></b><?php esc_html_e('On your phone or printed. It is in My Tickets and in your confirmation email.', 'gps-courses'); ?></span></li>
                <li><span><b><?php esc_html_e('Check in at the front desk', 'gps-courses'); ?></b><?php esc_html_e('We scan your code when you arrive and record your attendance.', 'gps-courses'); ?></span></li>
                <li><span><b><?php esc_html_e('Get your certificate', 'gps-courses'); ?></b><?php esc_html_e('After the course we issue your certificate and your CE credits appear here.', 'gps-courses'); ?></span></li>
            </ol>
            <div class="gps-help">
                <span><?php esc_html_e('Questions about a course?', 'gps-courses'); ?></span>
                <a href="mailto:<?php echo esc_attr($gps['help_email']); ?>"><?php echo esc_html($gps['help_email']); ?></a>
            </div>
        </section>
    </div>

    <?php
    // WooCommerce / plugin hooks from the original dashboard template
    do_action('woocommerce_account_dashboard');
    do_action_deprecated('woocommerce_before_my_account', [], '2.6.0');
    do_action_deprecated('woocommerce_after_my_account', [], '2.6.0');
    ?>
</div>
