<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Schedule management class
 * Handles event schedules with multiple topics/sessions per day
 */
class Schedules {

    public static function init() {
        add_action('init', [__CLASS__, 'register_cpt']);
        add_action('admin_init', [__CLASS__, 'register_metaboxes']);
        add_action('save_post_gps_schedule', [__CLASS__, 'save_schedule_meta']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);

        // Admin columns
        add_filter('manage_gps_schedule_posts_columns', [__CLASS__, 'add_schedule_columns']);
        add_action('manage_gps_schedule_posts_custom_column', [__CLASS__, 'render_schedule_columns'], 10, 2);
    }

    /**
     * Register Schedule CPT
     */
    public static function register_cpt() {
        register_post_type('gps_schedule', [
            'label'         => __('Schedules', 'gps-courses'),
            'description'   => __('Event schedules with topics and time slots', 'gps-courses'),
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => 'gps-dashboard',
            'menu_icon'     => 'dashicons-calendar-alt',
            'supports'      => ['title'],
            'show_in_rest'  => true,
        ]);

        // Schedule metadata
        register_post_meta('gps_schedule', '_gps_event_id', [
            'type'          => 'integer',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        register_post_meta('gps_schedule', '_gps_schedule_date', [
            'type'          => 'string',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        register_post_meta('gps_schedule', '_gps_schedule_topics', [
            'type'          => 'string', // JSON encoded
            'single'        => true,
            'show_in_rest'  => false,
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        register_post_meta('gps_schedule', '_gps_tab_label', [
            'type'          => 'string',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ]);
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_admin_assets($hook) {
        global $post_type;

        if (('post.php' === $hook || 'post-new.php' === $hook) && 'gps_schedule' === $post_type) {
            wp_enqueue_style('gps-schedule-admin', plugin_dir_url(dirname(__FILE__)) . 'assets/css/schedule-admin.css', [], '1.0.0');
            wp_enqueue_script('gps-schedule-admin', plugin_dir_url(dirname(__FILE__)) . 'assets/js/schedule-admin.js', ['jquery', 'jquery-ui-sortable'], '1.0.9', true);

            wp_localize_script('gps-schedule-admin', 'gpsSchedule', [
                'addTopic'      => __('Add Topic', 'gps-courses'),
                'removeTopic'   => __('Remove Topic', 'gps-courses'),
                'topicName'     => __('Topic Name', 'gps-courses'),
                'startTime'     => __('Start Time', 'gps-courses'),
                'endTime'       => __('End Time', 'gps-courses'),
                'speaker'       => __('Speaker', 'gps-courses'),
                'location'      => __('Location', 'gps-courses'),
                'description'   => __('Description', 'gps-courses'),
                'confirmDelete' => __('Are you sure you want to remove this topic?', 'gps-courses'),
            ]);
        }
    }

    /**
     * Register metaboxes
     */
    public static function register_metaboxes() {
        add_meta_box(
            'gps_schedule_meta',
            __('Schedule Configuration', 'gps-courses'),
            [__CLASS__, 'render_schedule_meta'],
            'gps_schedule',
            'normal',
            'high'
        );
    }

    /**
     * Render schedule metabox
     */
    public static function render_schedule_meta($post) {
        wp_nonce_field('gps_schedule_meta', 'gps_schedule_nonce');

        $event_id = (int) get_post_meta($post->ID, '_gps_event_id', true);
        $schedule_date = get_post_meta($post->ID, '_gps_schedule_date', true);
        $tab_label = get_post_meta($post->ID, '_gps_tab_label', true);
        $topics_json = get_post_meta($post->ID, '_gps_schedule_topics', true);
        $topics = !empty($topics_json) ? json_decode($topics_json, true) : [];

        // Get all events
        $events = get_posts([
            'post_type'   => 'gps_event',
            'numberposts' => -1,
            'post_status' => 'publish',
            'orderby'     => 'title',
            'order'       => 'ASC'
        ]);

        // Get all speakers
        $speakers = get_posts([
            'post_type'   => 'gps_speaker',
            'numberposts' => -1,
            'post_status' => 'publish',
            'orderby'     => 'title',
            'order'       => 'ASC'
        ]);

        ?>
        <div class="gps-schedule-builder">
            <div class="gps-schedule-header">
                <div class="gps-schedule-field">
                    <label for="gps_event_id">
                        <span class="required">*</span> <?php _e('Course/Event', 'gps-courses'); ?>
                        <span class="tooltip" title="<?php esc_attr_e('Select the event this schedule belongs to', 'gps-courses'); ?>">?</span>
                    </label>
                    <select name="gps_event_id" id="gps_event_id" required>
                        <option value=""><?php _e('— Select Event —', 'gps-courses'); ?></option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo (int) $event->ID; ?>" <?php selected($event_id, $event->ID); ?>>
                                <?php echo esc_html($event->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="gps-schedule-field">
                    <label for="gps_schedule_date">
                        <span class="required">*</span> <?php _e('Schedule Date', 'gps-courses'); ?>
                        <span class="tooltip" title="<?php esc_attr_e('Select the date for this schedule', 'gps-courses'); ?>">?</span>
                    </label>
                    <input type="date" name="gps_schedule_date" id="gps_schedule_date"
                           value="<?php echo esc_attr($schedule_date); ?>" required>
                </div>

                <div class="gps-schedule-field">
                    <label for="gps_tab_label">
                        <?php _e('Tab Label', 'gps-courses'); ?>
                        <span class="tooltip" title="<?php esc_attr_e('Short label for the tab (e.g., Day 1). Leave empty to use the full title.', 'gps-courses'); ?>">?</span>
                    </label>
                    <input type="text" name="gps_tab_label" id="gps_tab_label"
                           value="<?php echo esc_attr($tab_label); ?>"
                           placeholder="<?php esc_attr_e('e.g., Day 1', 'gps-courses'); ?>"
                           style="max-width: 200px;">
                </div>
            </div>

            <div class="gps-schedule-topics-section">
                <div class="section-header">
                    <h3>
                        <?php _e('Schedule Topics', 'gps-courses'); ?>
                        <span class="tooltip" title="<?php esc_attr_e('Add topics/sessions for this schedule. Drag to reorder.', 'gps-courses'); ?>">?</span>
                    </h3>
                </div>

                <div id="gps-topics-container" class="gps-topics-list">
                    <?php if (!empty($topics)): ?>
                        <?php foreach ($topics as $index => $topic): ?>
                            <?php self::render_topic_item($index, $topic, $speakers); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <button type="button" id="gps-add-topic" class="button button-secondary gps-add-topic-btn">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Add Schedule Topic', 'gps-courses'); ?>
                </button>
            </div>

            <!-- Topic template for JavaScript -->
            <script type="text/template" id="gps-topic-template">
                <?php self::render_topic_item('{{INDEX}}', [], $speakers, true); ?>
            </script>

            <!-- Speaker options template -->
            <script type="text/template" id="gps-speaker-options">
                <option value=""><?php _e('— Select Speaker —', 'gps-courses'); ?></option>
                <?php foreach ($speakers as $speaker): ?>
                    <option value="<?php echo (int) $speaker->ID; ?>">
                        <?php echo esc_html($speaker->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </script>
        </div>
        <?php
    }

    /**
     * Render single topic item
     */
    private static function render_topic_item($index, $topic = [], $speakers = [], $is_template = false) {
        $topic_name = $topic['name'] ?? '';
        $start_time = $topic['start_time'] ?? '';
        $end_time = $topic['end_time'] ?? '';
        $speaker_ids = $topic['speakers'] ?? [];
        $location = $topic['location'] ?? '';
        $description = $topic['description'] ?? '';

        if (!is_array($speaker_ids)) {
            $speaker_ids = !empty($speaker_ids) ? [$speaker_ids] : [];
        }
        ?>
        <div class="gps-topic-item" data-index="<?php echo esc_attr($index); ?>">
            <div class="topic-header">
                <span class="drag-handle dashicons dashicons-menu"></span>
                <h4 class="topic-title">
                    <?php echo $is_template ? __('New Schedule Topic', 'gps-courses') : ($topic_name ?: __('New Schedule Topic', 'gps-courses')); ?>
                </h4>
                <button type="button" class="toggle-topic dashicons dashicons-arrow-down-alt2"></button>
            </div>

            <div class="topic-content">
                <div class="topic-row">
                    <div class="topic-field">
                        <label>
                            <?php _e('Topic Name', 'gps-courses'); ?>
                            <span class="tooltip" title="<?php esc_attr_e('A short, clear title that attendees will understand', 'gps-courses'); ?>">?</span>
                        </label>
                        <input type="text"
                               name="gps_topics[<?php echo esc_attr($index); ?>][name]"
                               value="<?php echo esc_attr($topic_name); ?>"
                               placeholder="<?php esc_attr_e('A short, clear title that attendees will understand.', 'gps-courses'); ?>"
                               class="topic-name-input widefat">
                    </div>
                </div>

                <div class="topic-row topic-row-half">
                    <div class="topic-field">
                        <label>
                            <?php _e('Start Time', 'gps-courses'); ?>
                            <span class="tooltip" title="<?php esc_attr_e('Topic start time', 'gps-courses'); ?>">?</span>
                        </label>
                        <input type="time"
                               name="gps_topics[<?php echo esc_attr($index); ?>][start_time]"
                               value="<?php echo esc_attr($start_time); ?>"
                               class="widefat">
                    </div>

                    <div class="topic-field">
                        <label>
                            <?php _e('End Time', 'gps-courses'); ?>
                            <span class="tooltip" title="<?php esc_attr_e('Topic end time', 'gps-courses'); ?>">?</span>
                        </label>
                        <input type="time"
                               name="gps_topics[<?php echo esc_attr($index); ?>][end_time]"
                               value="<?php echo esc_attr($end_time); ?>"
                               class="widefat">
                    </div>
                </div>

                <div class="topic-row topic-row-half">
                    <div class="topic-field">
                        <label>
                            <?php _e('Speaker(s)', 'gps-courses'); ?>
                            <span class="tooltip" title="<?php esc_attr_e('Select the speakers for this topic', 'gps-courses'); ?>">?</span>
                        </label>
                        <select name="gps_topics[<?php echo esc_attr($index); ?>][speakers][]"
                                class="widefat" multiple style="height: 100px;">
                            <option value=""><?php _e('— Select Speaker —', 'gps-courses'); ?></option>
                            <?php foreach ($speakers as $speaker): ?>
                                <option value="<?php echo (int) $speaker->ID; ?>"
                                        <?php echo in_array($speaker->ID, $speaker_ids) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($speaker->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="description"><?php _e('Hold Ctrl/Cmd to select multiple speakers', 'gps-courses'); ?></small>
                    </div>

                    <div class="topic-field">
                        <label>
                            <?php _e('Location', 'gps-courses'); ?>
                            <span class="tooltip" title="<?php esc_attr_e('Enter the room or location for this session', 'gps-courses'); ?>">?</span>
                        </label>
                        <input type="text"
                               name="gps_topics[<?php echo esc_attr($index); ?>][location]"
                               value="<?php echo esc_attr($location); ?>"
                               placeholder="<?php esc_attr_e('e.g., Main Hall, Room 101', 'gps-courses'); ?>"
                               class="widefat">
                    </div>
                </div>

                <div class="topic-row">
                    <div class="topic-field">
                        <label>
                            <?php _e('Description', 'gps-courses'); ?>
                            <span class="tooltip" title="<?php esc_attr_e('Optional description for this topic. Supports formatting, lists, and line breaks.', 'gps-courses'); ?>">?</span>
                        </label>
                        <?php
                        $editor_id = 'gps_topic_description_' . esc_attr($index);
                        $editor_name = 'gps_topics[' . esc_attr($index) . '][description]';

                        // For template, disable TinyMCE to prevent initialization errors
                        if ($is_template) {
                            wp_editor($description, $editor_id, [
                                'textarea_name' => $editor_name,
                                'textarea_rows' => 5,
                                'media_buttons' => false,
                                'teeny' => false,
                                'quicktags' => false,
                                'tinymce' => false, // Disable TinyMCE for template
                            ]);
                        } else {
                            wp_editor($description, $editor_id, [
                                'textarea_name' => $editor_name,
                                'textarea_rows' => 5,
                                'media_buttons' => false,
                                'teeny' => false,
                                'quicktags' => true,
                                'tinymce' => [
                                    'toolbar1' => 'bold,italic,bullist,numlist,link,unlink',
                                    'toolbar2' => '',
                                    'wpautop' => true,
                                    'forced_root_block' => 'p',
                                    'force_p_newlines' => true,
                                    'force_br_newlines' => false,
                                    'convert_newlines_to_brs' => false,
                                    'remove_linebreaks' => false,
                                    'keep_styles' => true,
                                    'valid_elements' => '*[*]',
                                    'extended_valid_elements' => 'ul[*],ol[*],li[*],p[*],strong,em,a[href|target],br',
                                ],
                            ]);
                        }
                        ?>
                    </div>
                </div>

                <div class="topic-actions">
                    <button type="button" class="button button-link-delete remove-topic">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Remove Topic', 'gps-courses'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Save schedule metadata
     */
    public static function save_schedule_meta($post_id) {
        // Debug: Log that save function was called
        error_log("GPS Schedule: save_schedule_meta called for post_id: $post_id");

        if (!isset($_POST['gps_schedule_nonce']) || !wp_verify_nonce($_POST['gps_schedule_nonce'], 'gps_schedule_meta')) {
            error_log("GPS Schedule: Nonce verification failed");
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            error_log("GPS Schedule: Skipping save - autosave");
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            error_log("GPS Schedule: User cannot edit post");
            return;
        }

        $event_id = (int) ($_POST['gps_event_id'] ?? 0);
        $schedule_date = sanitize_text_field($_POST['gps_schedule_date'] ?? '');
        $tab_label = sanitize_text_field($_POST['gps_tab_label'] ?? '');
        $topics = $_POST['gps_topics'] ?? [];

        // Debug: Log raw POST data
        error_log("GPS Schedule: Raw topics count: " . count($topics));
        error_log("GPS Schedule: Raw topics data: " . print_r($topics, true));

        // Sanitize topics
        $sanitized_topics = [];
        foreach ($topics as $index => $topic) {
            // Get description - it should be in the POST data
            $description = isset($topic['description']) ? $topic['description'] : '';

            // Debug: Log each topic's description
            error_log("GPS Schedule: Topic $index description length: " . strlen($description));
            error_log("GPS Schedule: Topic $index description raw: " . substr($description, 0, 200));

            // WordPress may add slashes, remove them
            $description = wp_unslash($description);

            // Remove literal "rn t" and "rn" text that appears between HTML tags
            $description = preg_replace('/>\s*rn\s*t\s*</i', '><', $description);
            $description = preg_replace('/>\s*rn\s*</i', '><', $description);

            // Remove empty list items with no content
            $description = preg_replace('/<li[^>]*>\s*<\/li>/i', '', $description);

            // Remove nested ul with list-style-type: none that wraps single items
            $description = preg_replace('/<ul>\s*<li style="list-style-type:\s*none;">\s*<ul>/i', '<ul>', $description);
            $description = preg_replace('/<\/ul>\s*<\/li>\s*<\/ul>/i', '</ul>', $description);

            // Clean up extra whitespace between tags
            $description = preg_replace('/>\s+</i', '><', $description);

            $description = trim($description);

            // Debug: Log sanitized description
            error_log("GPS Schedule: Topic $index after sanitization: " . substr($description, 0, 200));

            $sanitized_topics[] = [
                'name'        => sanitize_text_field($topic['name'] ?? ''),
                'start_time'  => sanitize_text_field($topic['start_time'] ?? ''),
                'end_time'    => sanitize_text_field($topic['end_time'] ?? ''),
                'speakers'    => array_map('intval', (array)($topic['speakers'] ?? [])),
                'location'    => sanitize_text_field($topic['location'] ?? ''),
                'description' => wp_kses_post($description),
            ];
        }

        // Debug: Log final sanitized topics
        error_log("GPS Schedule: Final sanitized topics: " . print_r($sanitized_topics, true));

        update_post_meta($post_id, '_gps_event_id', $event_id);
        update_post_meta($post_id, '_gps_schedule_date', $schedule_date);
        update_post_meta($post_id, '_gps_tab_label', $tab_label);
        update_post_meta($post_id, '_gps_schedule_topics', wp_json_encode($sanitized_topics, JSON_UNESCAPED_UNICODE));

        error_log("GPS Schedule: Save completed successfully");
    }

    /**
     * Add custom columns
     */
    public static function add_schedule_columns($columns) {
        $new_columns = [];

        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;
        }

        $date_column = $new_columns['date'] ?? null;
        unset($new_columns['date']);

        $new_columns['event'] = __('Event', 'gps-courses');
        $new_columns['schedule_date'] = __('Date', 'gps-courses');
        $new_columns['topics_count'] = __('Topics', 'gps-courses');

        if ($date_column) {
            $new_columns['date'] = $date_column;
        }

        return $new_columns;
    }

    /**
     * Render custom column content
     */
    public static function render_schedule_columns($column, $post_id) {
        switch ($column) {
            case 'event':
                $event_id = get_post_meta($post_id, '_gps_event_id', true);
                if ($event_id) {
                    $event = get_post($event_id);
                    if ($event) {
                        echo '<a href="' . get_edit_post_link($event_id) . '">' . esc_html($event->post_title) . '</a>';
                    }
                } else {
                    echo '<span style="color: #999;">—</span>';
                }
                break;

            case 'schedule_date':
                $date = get_post_meta($post_id, '_gps_schedule_date', true);
                if ($date) {
                    $formatted = date_i18n(get_option('date_format'), strtotime($date));
                    echo '<strong>' . esc_html($formatted) . '</strong>';
                } else {
                    echo '<span style="color: #999;">—</span>';
                }
                break;

            case 'topics_count':
                $topics_json = get_post_meta($post_id, '_gps_schedule_topics', true);
                $topics = !empty($topics_json) ? json_decode($topics_json, true) : [];
                $count = count($topics);

                if ($count > 0) {
                    echo '<span style="background: #2271b1; color: #fff; padding: 3px 10px; border-radius: 10px; font-size: 12px; font-weight: 600;">';
                    echo esc_html(sprintf(_n('%d Topic', '%d Topics', $count, 'gps-courses'), $count));
                    echo '</span>';
                } else {
                    echo '<span style="color: #999;">' . __('No topics', 'gps-courses') . '</span>';
                }
                break;
        }
    }

    /**
     * Get schedules for an event
     */
    public static function get_event_schedules($event_id) {
        return get_posts([
            'post_type'   => 'gps_schedule',
            'numberposts' => -1,
            'post_status' => 'publish',
            'meta_key'    => '_gps_schedule_date',
            'orderby'     => 'meta_value',
            'order'       => 'ASC',
            'meta_query'  => [
                [
                    'key'   => '_gps_event_id',
                    'value' => $event_id,
                    'type'  => 'NUMERIC',
                ],
            ],
        ]);
    }

    /**
     * Get topics for a schedule
     */
    public static function get_schedule_topics($schedule_id) {
        $topics_json = get_post_meta($schedule_id, '_gps_schedule_topics', true);
        return !empty($topics_json) ? json_decode($topics_json, true) : [];
    }
}
