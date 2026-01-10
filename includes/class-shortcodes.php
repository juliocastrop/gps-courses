<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Shortcodes
 * Handles all plugin shortcodes
 */
class Shortcodes {

    public static function init() {
        // Register shortcodes
        add_shortcode('gps_course_credits_plain', [__CLASS__, 'course_credits_plain']);
        add_shortcode('gps_ce_credits_profile', [__CLASS__, 'ce_credits_profile']);
        add_shortcode('gps_ce_credits_total', [__CLASS__, 'ce_credits_total']);
        add_shortcode('gps_events', [__CLASS__, 'events_list']);
        add_shortcode('gps_event_calendar', [__CLASS__, 'event_calendar']);
        add_shortcode('gps_my_tickets', [__CLASS__, 'my_tickets']);
        add_shortcode('gps_event_countdown', [__CLASS__, 'event_countdown']);
        add_shortcode('gps_speakers', [__CLASS__, 'speakers_grid']);
    }

    /**
     * [gps_course_credits_plain id="123"]
     * Display CE credits for a specific event (plain text)
     */
    public static function course_credits_plain($atts) {
        $atts = shortcode_atts([
            'id' => get_the_ID(),
        ], $atts);

        $event_id = (int) $atts['id'];

        if (!$event_id || get_post_type($event_id) !== 'gps_event') {
            return '';
        }

        $credits = get_post_meta($event_id, '_gps_ce_credits', true);

        if (!$credits) {
            return '';
        }

        return sprintf(
            '<span class="gps-ce-credits-plain">%d %s</span>',
            (int) $credits,
            _n('CE Credit', 'CE Credits', $credits, 'gps-courses')
        );
    }

    /**
     * [gps_ce_credits_profile]
     * Display complete CE credits profile for logged-in user
     */
    public static function ce_credits_profile($atts) {
        if (!is_user_logged_in()) {
            return '<p class="gps-login-required">' . __('Please log in to view your CE credits.', 'gps-courses') . '</p>';
        }

        $user_id = get_current_user_id();
        $total_credits = Credits::get_total($user_id);
        $ledger = Credits::get_ledger($user_id);

        ob_start();
        ?>
        <div class="gps-ce-profile">
            <div class="gps-ce-profile-header">
                <h3><?php _e('CE Credits Profile', 'gps-courses'); ?></h3>
                <div class="gps-total-credits">
                    <span class="gps-credits-number"><?php echo (int) $total_credits; ?></span>
                    <span class="gps-credits-label"><?php _e('Total Credits', 'gps-courses'); ?></span>
                </div>
            </div>

            <?php if (empty($ledger)): ?>
                <p class="gps-no-credits"><?php _e('You have not earned any CE credits yet.', 'gps-courses'); ?></p>
            <?php else: ?>
                <div class="gps-ce-ledger">
                    <table class="gps-ce-table">
                        <thead>
                            <tr>
                                <th><?php _e('Date', 'gps-courses'); ?></th>
                                <th><?php _e('Event', 'gps-courses'); ?></th>
                                <th><?php _e('Credits', 'gps-courses'); ?></th>
                                <th><?php _e('Type', 'gps-courses'); ?></th>
                                <th><?php _e('Notes', 'gps-courses'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ledger as $entry):
                                $event_title = get_the_title($entry->event_id);
                            ?>
                            <tr>
                                <td><?php echo date_i18n(get_option('date_format'), strtotime($entry->awarded_at)); ?></td>
                                <td>
                                    <?php if ($entry->event_id): ?>
                                        <a href="<?php echo get_permalink($entry->event_id); ?>"><?php echo esc_html($event_title); ?></a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo (int) $entry->credits; ?></strong></td>
                                <td><span class="gps-credit-type <?php echo esc_attr($entry->transaction_type); ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $entry->transaction_type))); ?></span></td>
                                <td><?php echo esc_html($entry->notes); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [gps_ce_credits_total user_id="123"]
     * Display total CE credits (defaults to current user)
     */
    public static function ce_credits_total($atts) {
        $atts = shortcode_atts([
            'user_id' => get_current_user_id(),
            'label' => __('Total CE Credits', 'gps-courses'),
            'show_label' => 'yes',
        ], $atts);

        $user_id = (int) $atts['user_id'];

        if (!$user_id) {
            return '<p class="gps-login-required">' . __('Please log in to view CE credits.', 'gps-courses') . '</p>';
        }

        $total_credits = Credits::get_total($user_id);

        ob_start();
        ?>
        <div class="gps-ce-credits-total">
            <span class="gps-credits-number"><?php echo (int) $total_credits; ?></span>
            <?php if ($atts['show_label'] === 'yes'): ?>
                <span class="gps-credits-label"><?php echo esc_html($atts['label']); ?></span>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [gps_events posts_per_page="6" layout="grid" columns="3"]
     * Display list of events
     */
    public static function events_list($atts) {
        $atts = shortcode_atts([
            'posts_per_page' => 6,
            'layout' => 'grid', // grid, list
            'columns' => 3,
            'order' => 'ASC',
            'orderby' => 'meta_value',
            'show_past' => 'no',
        ], $atts);

        $args = [
            'post_type' => 'gps_event',
            'post_status' => 'publish',
            'posts_per_page' => (int) $atts['posts_per_page'],
            'order' => $atts['order'],
            'orderby' => $atts['orderby'],
        ];

        // Filter out past events
        if ($atts['show_past'] === 'no') {
            $args['meta_query'] = [
                [
                    'key' => '_gps_start_date',
                    'value' => current_time('mysql'),
                    'compare' => '>=',
                    'type' => 'DATETIME',
                ],
            ];
        }

        if ($atts['orderby'] === 'meta_value') {
            $args['meta_key'] = '_gps_start_date';
        }

        $query = new \WP_Query($args);

        if (!$query->have_posts()) {
            return '<p class="gps-no-events">' . __('No events found.', 'gps-courses') . '</p>';
        }

        $layout_class = 'gps-events-' . $atts['layout'];
        $columns_class = 'gps-columns-' . $atts['columns'];

        ob_start();
        ?>
        <div class="gps-events-shortcode <?php echo esc_attr($layout_class); ?> <?php echo esc_attr($columns_class); ?>">
            <?php while ($query->have_posts()): $query->the_post();
                $event_id = get_the_ID();
                $start_date = get_post_meta($event_id, '_gps_start_date', true);
                $end_date = get_post_meta($event_id, '_gps_end_date', true);
                $venue = get_post_meta($event_id, '_gps_venue', true);
                $ce_credits = get_post_meta($event_id, '_gps_ce_credits', true);
            ?>
            <article class="gps-event-item">
                <?php if (has_post_thumbnail()): ?>
                <div class="gps-event-thumbnail">
                    <a href="<?php the_permalink(); ?>">
                        <?php the_post_thumbnail('medium'); ?>
                    </a>
                </div>
                <?php endif; ?>

                <div class="gps-event-content">
                    <h3 class="gps-event-title">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h3>

                    <?php if ($start_date): ?>
                    <div class="gps-event-date">
                        <i class="far fa-calendar"></i>
                        <?php echo date_i18n(get_option('date_format'), strtotime($start_date)); ?>
                        <?php if ($end_date && $end_date !== $start_date): ?>
                            - <?php echo date_i18n(get_option('date_format'), strtotime($end_date)); ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($venue): ?>
                    <div class="gps-event-venue">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo esc_html($venue); ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($ce_credits): ?>
                    <div class="gps-event-credits">
                        <i class="fas fa-award"></i>
                        <?php printf(__('%d CE Credits', 'gps-courses'), (int) $ce_credits); ?>
                    </div>
                    <?php endif; ?>

                    <div class="gps-event-excerpt">
                        <?php the_excerpt(); ?>
                    </div>

                    <a href="<?php the_permalink(); ?>" class="gps-event-link">
                        <?php _e('View Details', 'gps-courses'); ?>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </article>
            <?php endwhile; ?>
        </div>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }

    /**
     * [gps_event_calendar view="month"]
     * Display events calendar
     */
    public static function event_calendar($atts) {
        $atts = shortcode_atts([
            'view' => 'month', // month, week, list
        ], $atts);

        wp_enqueue_script('gps-calendar');
        wp_enqueue_style('gps-calendar');

        ob_start();
        ?>
        <div class="gps-calendar-shortcode" data-view="<?php echo esc_attr($atts['view']); ?>">
            <div class="gps-calendar-header">
                <button class="gps-calendar-prev">&laquo;</button>
                <h3 class="gps-calendar-title"></h3>
                <button class="gps-calendar-next">&raquo;</button>
            </div>
            <div class="gps-calendar-view-switcher">
                <button class="gps-view-btn" data-view="month"><?php _e('Month', 'gps-courses'); ?></button>
                <button class="gps-view-btn" data-view="week"><?php _e('Week', 'gps-courses'); ?></button>
                <button class="gps-view-btn" data-view="list"><?php _e('List', 'gps-courses'); ?></button>
            </div>
            <div class="gps-calendar-body"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [gps_my_tickets]
     * Display user's tickets
     */
    public static function my_tickets($atts) {
        if (!is_user_logged_in()) {
            return '<p class="gps-login-required">' . __('Please log in to view your tickets.', 'gps-courses') . '</p>';
        }

        $user_id = get_current_user_id();

        global $wpdb;
        $tickets = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, p.post_title as event_title, p.guid as event_url
             FROM {$wpdb->prefix}gps_tickets t
             INNER JOIN {$wpdb->posts} p ON t.event_id = p.ID
             WHERE t.user_id = %d
             ORDER BY t.created_at DESC",
            $user_id
        ));

        if (empty($tickets)) {
            return '<p class="gps-no-tickets">' . __('You have no tickets yet.', 'gps-courses') . '</p>';
        }

        ob_start();
        ?>
        <div class="gps-my-tickets">
            <table class="gps-tickets-table">
                <thead>
                    <tr>
                        <th><?php _e('Event', 'gps-courses'); ?></th>
                        <th><?php _e('Ticket Code', 'gps-courses'); ?></th>
                        <th><?php _e('Attendee', 'gps-courses'); ?></th>
                        <th><?php _e('Status', 'gps-courses'); ?></th>
                        <th><?php _e('Actions', 'gps-courses'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket):
                        $checked_in = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}gps_attendance WHERE ticket_id = %d",
                            $ticket->id
                        ));
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($ticket->event_title); ?></strong><br>
                            <small><?php echo date_i18n(get_option('date_format'), strtotime($ticket->created_at)); ?></small>
                        </td>
                        <td><code><?php echo esc_html($ticket->ticket_code); ?></code></td>
                        <td><?php echo esc_html($ticket->attendee_name); ?></td>
                        <td>
                            <?php if ($checked_in): ?>
                                <span class="gps-status-badge checked-in"><?php _e('Checked In', 'gps-courses'); ?></span>
                            <?php else: ?>
                                <span class="gps-status-badge pending"><?php _e('Valid', 'gps-courses'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg('download_ticket', $ticket->id, get_permalink($ticket->event_id))); ?>" class="button button-small">
                                <i class="fas fa-download"></i> <?php _e('Download', 'gps-courses'); ?>
                            </a>
                            <a href="<?php echo esc_url(get_permalink($ticket->event_id)); ?>" class="button button-small">
                                <?php _e('View Event', 'gps-courses'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [gps_event_countdown id="123"]
     * Display countdown to event start
     */
    public static function event_countdown($atts) {
        $atts = shortcode_atts([
            'id' => get_the_ID(),
            'message' => __('Event starts in:', 'gps-courses'),
        ], $atts);

        $event_id = (int) $atts['id'];

        if (!$event_id || get_post_type($event_id) !== 'gps_event') {
            return '';
        }

        $start_date = get_post_meta($event_id, '_gps_start_date', true);

        if (!$start_date) {
            return '';
        }

        wp_enqueue_script('gps-countdown');

        ob_start();
        ?>
        <div class="gps-countdown-shortcode" data-date="<?php echo esc_attr($start_date); ?>">
            <?php if ($atts['message']): ?>
                <div class="gps-countdown-message"><?php echo esc_html($atts['message']); ?></div>
            <?php endif; ?>
            <div class="gps-countdown-timer">
                <div class="gps-countdown-item">
                    <span class="gps-countdown-value gps-days">0</span>
                    <span class="gps-countdown-label"><?php _e('Days', 'gps-courses'); ?></span>
                </div>
                <div class="gps-countdown-item">
                    <span class="gps-countdown-value gps-hours">0</span>
                    <span class="gps-countdown-label"><?php _e('Hours', 'gps-courses'); ?></span>
                </div>
                <div class="gps-countdown-item">
                    <span class="gps-countdown-value gps-minutes">0</span>
                    <span class="gps-countdown-label"><?php _e('Minutes', 'gps-courses'); ?></span>
                </div>
                <div class="gps-countdown-item">
                    <span class="gps-countdown-value gps-seconds">0</span>
                    <span class="gps-countdown-label"><?php _e('Seconds', 'gps-courses'); ?></span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [gps_speakers posts_per_page="6" columns="3"]
     * Display speakers grid
     */
    public static function speakers_grid($atts) {
        $atts = shortcode_atts([
            'posts_per_page' => 6,
            'columns' => 3,
            'orderby' => 'title',
            'order' => 'ASC',
        ], $atts);

        $args = [
            'post_type' => 'gps_speaker',
            'post_status' => 'publish',
            'posts_per_page' => (int) $atts['posts_per_page'],
            'orderby' => $atts['orderby'],
            'order' => $atts['order'],
        ];

        $query = new \WP_Query($args);

        if (!$query->have_posts()) {
            return '<p class="gps-no-speakers">' . __('No speakers found.', 'gps-courses') . '</p>';
        }

        $columns_class = 'gps-columns-' . $atts['columns'];

        ob_start();
        ?>
        <div class="gps-speakers-shortcode <?php echo esc_attr($columns_class); ?>">
            <?php while ($query->have_posts()): $query->the_post();
                $speaker_id = get_the_ID();
                $designation = get_post_meta($speaker_id, '_gps_designation', true);
                $company = get_post_meta($speaker_id, '_gps_company', true);
            ?>
            <div class="gps-speaker-item">
                <?php if (has_post_thumbnail()): ?>
                <div class="gps-speaker-photo">
                    <?php the_post_thumbnail('medium'); ?>
                </div>
                <?php endif; ?>

                <div class="gps-speaker-content">
                    <h3 class="gps-speaker-name"><?php the_title(); ?></h3>

                    <?php if ($designation): ?>
                    <p class="gps-speaker-designation"><?php echo esc_html($designation); ?></p>
                    <?php endif; ?>

                    <?php if ($company): ?>
                    <p class="gps-speaker-company"><?php echo esc_html($company); ?></p>
                    <?php endif; ?>

                    <?php if (get_the_content()): ?>
                    <div class="gps-speaker-bio">
                        <?php echo wp_trim_words(get_the_content(), 20, '...'); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }
}
