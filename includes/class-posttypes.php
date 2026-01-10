<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

class Posttypes {

    /* ============================================================
     * Bootstrap
     * ============================================================ */
    /**
     * Inicializa hooks de registro (frontend) y menús (admin).
     */
    public static function init() {
        add_action('init',            [__CLASS__, 'register_cpts']);
        add_action('admin_menu',      [__CLASS__, 'register_menus']);
        add_action('admin_init',      [__CLASS__, 'register_metaboxes']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_enqueue_scripts']);
        add_action('edit_form_after_title', [__CLASS__, 'custom_event_edit_screen']);
        add_filter('screen_layout_columns', [__CLASS__, 'event_screen_layout_columns']);
        add_filter('get_user_option_screen_layout_gps_event', [__CLASS__, 'event_screen_layout']);

        // Disable block editor for classic editor post types
        add_filter('use_block_editor_for_post_type', [__CLASS__, 'disable_block_editor'], 10, 2);

        // AJAX handlers for adding categories and tags
        add_action('wp_ajax_gps_add_category', [__CLASS__, 'ajax_add_category']);
        add_action('wp_ajax_gps_add_tag', [__CLASS__, 'ajax_add_tag']);

        // AJAX handlers for thumbnail operations
        add_action('wp_ajax_gps_set_course_thumbnail', [__CLASS__, 'ajax_set_course_thumbnail']);
        add_action('wp_ajax_gps_remove_course_thumbnail', [__CLASS__, 'ajax_remove_course_thumbnail']);

        // Clear calendar cache when events are saved/updated
        add_action('save_post_gps_event', [__CLASS__, 'clear_calendar_cache']);
        add_action('delete_post', [__CLASS__, 'clear_calendar_cache']);

        // One-time migration for old meta keys
        add_action('admin_init', [__CLASS__, 'migrate_old_meta_keys']);
    }

    /**
     * Compat: usado por el Activator durante la activación para poder hacer flush.
     * No registra menús (sólo CPTs) para correr en contexto de activación.
     */
    public static function register() {
        self::register_cpts();
    }

    /* ============================================================
     * CPTs
     * ============================================================ */
    public static function register_cpts() {
        $slug = get_option('gps_courses_slug', 'courses');
        $slug = sanitize_title($slug ?: 'courses');

        // Register taxonomies first
        register_taxonomy('gps_event_category', 'gps_event', [
            'label'              => __('Course Categories', 'gps-courses'),
            'public'             => true,
            'hierarchical'       => true,
            'show_ui'            => true,
            'show_admin_column'  => true,
            'show_in_nav_menus'  => true,
            'show_in_rest'       => true,
            'rewrite'            => ['slug' => 'course-category'],
        ]);

        register_taxonomy('gps_event_tag', 'gps_event', [
            'label'              => __('Course Tags', 'gps-courses'),
            'public'             => true,
            'hierarchical'       => false,
            'show_ui'            => true,
            'show_admin_column'  => true,
            'show_in_nav_menus'  => true,
            'show_in_rest'       => true,
            'rewrite'            => ['slug' => 'course-tag'],
        ]);

        // CPT: Courses (antes 'Events')
        register_post_type('gps_event', [
            'label'         => __('Courses', 'gps-courses'),
            'labels'        => [
                'name'               => __('Courses', 'gps-courses'),
                'singular_name'      => __('Course', 'gps-courses'),
                'add_new'            => __('Create a Course', 'gps-courses'),
                'add_new_item'       => __('Create a New Course', 'gps-courses'),
                'edit_item'          => __('Edit Course', 'gps-courses'),
                'new_item'           => __('New Course', 'gps-courses'),
                'view_item'          => __('View Course', 'gps-courses'),
                'search_items'       => __('Search Courses', 'gps-courses'),
                'not_found'          => __('No courses found', 'gps-courses'),
                'not_found_in_trash' => __('No courses found in trash', 'gps-courses'),
                'all_items'          => __('All Courses', 'gps-courses'),
                'menu_name'          => __('Courses', 'gps-courses'),
            ],
            'description'   => __('GPS Dental Training courses', 'gps-courses'),
            'public'        => true,
            'show_in_menu'  => 'gps-dashboard',
            'menu_icon'     => 'dashicons-welcome-learn-more',
            'supports'      => ['title', 'thumbnail'],
            'has_archive'   => true,
            'rewrite'       => ['slug' => $slug, 'with_front' => false],
            'show_in_rest'  => false,
        ]);

        // CPT: Sessions
        register_post_type('gps_session', [
            'label'         => __('Sessions', 'gps-courses'),
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => 'gps-dashboard',
            'menu_icon'     => 'dashicons-clock',
            'supports'      => ['title'],
            'show_in_rest'  => true,
        ]);

        // CPT: Organizers
        register_post_type('gps_organizer', [
            'label'         => __('Organizers', 'gps-courses'),
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => 'gps-dashboard',
            'menu_icon'     => 'dashicons-groups',
            'supports'      => ['title','editor','thumbnail'],
            'show_in_rest'  => true,
        ]);

        // CPT: Sponsors
        register_post_type('gps_sponsor', [
            'label'         => __('Sponsors', 'gps-courses'),
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => 'gps-dashboard',
            'menu_icon'     => 'dashicons-megaphone',
            'supports'      => ['title','thumbnail','excerpt'],
            'show_in_rest'  => true,
        ]);

        // CPT: Speakers
        register_post_type('gps_speaker', [
            'label'         => __('Speakers', 'gps-courses'),
            'description'   => __('Speakers and instructors for courses', 'gps-courses'),
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => 'gps-dashboard',
            'menu_icon'     => 'dashicons-businessman',
            'supports'      => ['title','editor','thumbnail'],
            'show_in_rest'  => true,
        ]);

        // CPT: Monthly Seminars
        register_post_type('gps_seminar', [
            'label'         => __('Monthly Seminars', 'gps-courses'),
            'labels'        => [
                'name'               => __('Monthly Seminars', 'gps-courses'),
                'singular_name'      => __('Monthly Seminar', 'gps-courses'),
                'add_new'            => __('Create Seminar', 'gps-courses'),
                'add_new_item'       => __('Create New Seminar', 'gps-courses'),
                'edit_item'          => __('Edit Seminar', 'gps-courses'),
                'new_item'           => __('New Seminar', 'gps-courses'),
                'view_item'          => __('View Seminar', 'gps-courses'),
                'search_items'       => __('Search Seminars', 'gps-courses'),
                'not_found'          => __('No seminars found', 'gps-courses'),
                'not_found_in_trash' => __('No seminars found in trash', 'gps-courses'),
                'all_items'          => __('All Seminars', 'gps-courses'),
                'menu_name'          => __('Monthly Seminars', 'gps-courses'),
            ],
            'description'   => __('GPS Monthly Seminars - 10-session program', 'gps-courses'),
            'public'        => true,
            'show_ui'       => true,
            'show_in_menu'  => 'gps-dashboard',
            'menu_icon'     => 'dashicons-calendar-alt',
            'supports'      => ['title', 'editor', 'thumbnail'],
            'has_archive'   => true,
            'rewrite'       => ['slug' => 'monthly-seminars', 'with_front' => false],
            'show_in_rest'  => false,
        ]);

        // ====== Metadatos (REST + sanitización server-side) ======
        // Event/Course
        register_post_meta('gps_event','_gps_ce_credits',[
            'type'         => 'integer',
            'single'       => true,
            'default'      => 0,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);
        register_post_meta('gps_event','_gps_start_date',[
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);
        register_post_meta('gps_event','_gps_end_date',[
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);
        register_post_meta('gps_event','_gps_start_time',[
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);
        register_post_meta('gps_event','_gps_end_time',[
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);
        register_post_meta('gps_event','_gps_venue',[
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);
        register_post_meta('gps_event','_gps_objectives',[
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);

        // Session
        register_post_meta('gps_session','_gps_event_id',[
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);
        register_post_meta('gps_session','_gps_start',[
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);
        register_post_meta('gps_session','_gps_end',[
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);
        register_post_meta('gps_session','_gps_wc_product_id',[
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);

        // WooCommerce Product - bidirectional link to session
        register_post_meta('product','_gps_session_id',[
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);

        // Monthly Seminar
        register_post_meta('gps_seminar','_gps_seminar_year',[
            'type'         => 'integer',
            'single'       => true,
            'default'      => date('Y'),
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);
        register_post_meta('gps_seminar','_gps_seminar_capacity',[
            'type'         => 'integer',
            'single'       => true,
            'default'      => 50,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);
        register_post_meta('gps_seminar','_gps_seminar_product_id',[
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);
        register_post_meta('gps_seminar','_gps_seminar_status',[
            'type'         => 'string',
            'single'       => true,
            'default'      => 'upcoming',
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);
        register_post_meta('gps_seminar','_gps_seminar_tuition',[
            'type'         => 'number',
            'single'       => true,
            'default'      => 750,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);

        // Speaker
        register_post_meta('gps_speaker','_gps_designation',[
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);
        register_post_meta('gps_speaker','_gps_company',[
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);
        register_post_meta('gps_speaker','_gps_email',[
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);
        register_post_meta('gps_speaker','_gps_phone',[
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);
        register_post_meta('gps_speaker','_gps_social_twitter',[
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);
        register_post_meta('gps_speaker','_gps_social_linkedin',[
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);
        register_post_meta('gps_speaker','_gps_social_facebook',[
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);

        // Event-Speaker relationship (many-to-many via serialized array)
        register_post_meta('gps_event','_gps_speaker_ids',[
            'type'         => 'array',
            'single'       => true,
            'show_in_rest' => [
                'schema' => [
                    'type'  => 'array',
                    'items' => ['type' => 'integer'],
                ],
            ],
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);

        // Session-Speaker relationship
        register_post_meta('gps_session','_gps_speaker_ids',[
            'type'         => 'array',
            'single'       => true,
            'show_in_rest' => [
                'schema' => [
                    'type'  => 'array',
                    'items' => ['type' => 'integer'],
                ],
            ],
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        ]);
    }

    /* ============================================================
     * Menú principal (admin)
     * ============================================================ */
    public static function register_menus() {
        // Menú principal
        add_menu_page(
            __('GPS Courses', 'gps-courses'),
            __('GPS Courses', 'gps-courses'),
            'manage_options',
            'gps-dashboard',
            [__CLASS__, 'render_dashboard_page'],
            'dashicons-welcome-learn-more',
            6
        );

        // Add submenu for Categories
        add_submenu_page(
            'gps-dashboard',
            __('Categories', 'gps-courses'),
            __('Categories', 'gps-courses'),
            'manage_categories',
            'edit-tags.php?taxonomy=gps_event_category&post_type=gps_event'
        );

        // Add submenu for Tags
        add_submenu_page(
            'gps-dashboard',
            __('Tags', 'gps-courses'),
            __('Tags', 'gps-courses'),
            'manage_categories',
            'edit-tags.php?taxonomy=gps_event_tag&post_type=gps_event'
        );
    }

    public static function render_dashboard_page() {
        echo '<div class="wrap gps-dashboard">';
        echo '<h1>📘 GPS Courses</h1>';
        echo '<p>'.esc_html__('Welcome to GPS Dental Training Course Management.', 'gps-courses').'</p>';
        echo '<style>
            .gps-links{margin-top:18px}
            .gps-links ul{margin:0;padding:0;list-style:none;display:grid;gap:8px;max-width:560px}
            .gps-links li a{display:block;padding:10px 12px;border-radius:6px;background:#f3f4f6;text-decoration:none}
            .gps-links li a:hover{background:#e6eefc}
        </style>';
        echo '<div class="gps-links"><ul>';
        echo '<li>📅 <a href="'.admin_url('edit.php?post_type=gps_event').'">'.esc_html__('Manage Courses','gps-courses').'</a></li>';
        echo '<li>🎫 <a href="'.admin_url('edit.php?post_type=gps_ticket').'">'.esc_html__('Manage Tickets','gps-courses').'</a></li>';
        echo '<li>🎟️ <a href="'.admin_url('admin.php?page=gps-purchased-tickets').'">'.esc_html__('Purchased Tickets','gps-courses').'</a></li>';
        echo '<li>📱 <a href="'.admin_url('admin.php?page=gps-attendance').'">'.esc_html__('Attendance Scanner','gps-courses').'</a></li>';
        echo '<li>📊 <a href="'.admin_url('admin.php?page=gps-attendance-report').'">'.esc_html__('Attendance Report','gps-courses').'</a></li>';
        echo '<li>🕒 <a href="'.admin_url('edit.php?post_type=gps_session').'">'.esc_html__('Manage Sessions','gps-courses').'</a></li>';
        echo '<li>🎤 <a href="'.admin_url('edit.php?post_type=gps_speaker').'">'.esc_html__('Manage Speakers','gps-courses').'</a></li>';
        echo '<li>👥 <a href="'.admin_url('edit.php?post_type=gps_organizer').'">'.esc_html__('Manage Organizers','gps-courses').'</a></li>';
        echo '<li>💼 <a href="'.admin_url('edit.php?post_type=gps_sponsor').'">'.esc_html__('Manage Sponsors','gps-courses').'</a></li>';
        echo '<li>⚙️ <a href="'.admin_url('admin.php?page=gps-settings').'">'.esc_html__('Settings','gps-courses').'</a></li>';
        echo '</ul></div>';
        echo '</div>';
    }

    /* ============================================================
     * Metaboxes (admin) y guardado
     * ============================================================ */
    public static function register_metaboxes() {
        // NOTE: Event metaboxes are NOT registered here anymore
        // Events use the custom tabbed edit screen (custom_event_edit_screen)
        // Only register metaboxes for other post types

        add_meta_box(
            'gps_session_meta',
            __('Session Details','gps-courses'),
            [__CLASS__, 'render_session_meta'],
            'gps_session', 'side', 'default'
        );

        add_meta_box(
            'gps_speaker_meta',
            __('Speaker Details','gps-courses'),
            [__CLASS__, 'render_speaker_meta'],
            'gps_speaker', 'side', 'default'
        );

        add_meta_box(
            'gps_seminar_config',
            __('Seminar Configuration','gps-courses'),
            [__CLASS__, 'render_seminar_config_meta'],
            'gps_seminar', 'normal', 'high'
        );

        add_meta_box(
            'gps_seminar_sessions',
            __('Session Schedule (10 Sessions)','gps-courses'),
            [__CLASS__, 'render_seminar_sessions_meta'],
            'gps_seminar', 'normal', 'default'
        );

        add_action('save_post_gps_event',   [__CLASS__, 'save_event_meta']);
        add_action('save_post_gps_session', [__CLASS__, 'save_session_meta']);
        add_action('save_post_gps_speaker', [__CLASS__, 'save_speaker_meta']);
        add_action('save_post_gps_seminar', [__CLASS__, 'save_seminar_meta']);
    }

    /* -------- Event/Course Metabox -------- */
    public static function render_event_meta($post) {
        wp_nonce_field('gps_event_meta', 'gps_event_nonce');

        $start = get_post_meta($post->ID, '_gps_date_start', true);
        $end   = get_post_meta($post->ID, '_gps_date_end',   true);
        $ce    = (int) get_post_meta($post->ID, '_gps_ce_credits', true);
        $venue = get_post_meta($post->ID, '_gps_venue',      true);
        ?>
        <p><label><strong><?php _e('Start (YYYY-MM-DD HH:MM)','gps-courses'); ?></strong></label>
        <input type="text" name="gps_date_start" value="<?php echo esc_attr($start); ?>" class="widefat" placeholder="2025-11-21 08:00"></p>

        <p><label><strong><?php _e('End (YYYY-MM-DD HH:MM)','gps-courses'); ?></strong></label>
        <input type="text" name="gps_date_end" value="<?php echo esc_attr($end); ?>" class="widefat" placeholder="2025-11-22 17:00"></p>

        <p><label><strong><?php _e('CE Credits','gps-courses'); ?></strong></label>
        <input type="number" min="0" step="1" name="gps_ce_credits" value="<?php echo esc_attr($ce); ?>" class="widefat"></p>

        <p><label><strong><?php _e('Venue / Address','gps-courses'); ?></strong></label>
        <input type="text" name="gps_venue" value="<?php echo esc_attr($venue); ?>" class="widefat" placeholder="6320 Sugarloaf Pkwy, Duluth, GA"></p>
        <?php
    }

    public static function save_event_meta($post_id) {
        if (!isset($_POST['gps_event_meta_nonce']) || !wp_verify_nonce($_POST['gps_event_meta_nonce'], 'gps_event_meta_nonce')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // Migrate old meta keys to new ones (one-time migration)
        $old_start = get_post_meta($post_id, '_gps_date_start', true);
        $old_end = get_post_meta($post_id, '_gps_date_end', true);
        if (!empty($old_start) && empty(get_post_meta($post_id, '_gps_start_date', true))) {
            update_post_meta($post_id, '_gps_start_date', $old_start);
        }
        if (!empty($old_end) && empty(get_post_meta($post_id, '_gps_end_date', true))) {
            update_post_meta($post_id, '_gps_end_date', $old_end);
        }

        // Date and time fields
        $start_date = sanitize_text_field($_POST['gps_start_date'] ?? '');
        $end_date   = sanitize_text_field($_POST['gps_end_date'] ?? '');
        $start_time = sanitize_text_field($_POST['gps_start_time'] ?? '');
        $end_time   = sanitize_text_field($_POST['gps_end_time'] ?? '');

        // Location fields
        $venue   = sanitize_text_field($_POST['gps_venue'] ?? 'GPS Dental Training Center');
        $address = sanitize_text_field($_POST['gps_address'] ?? '');
        $city    = sanitize_text_field($_POST['gps_city'] ?? '');
        $state   = sanitize_text_field($_POST['gps_state'] ?? '');
        $zip     = sanitize_text_field($_POST['gps_zip'] ?? '');
        $country = sanitize_text_field($_POST['gps_country'] ?? '');

        // Other fields
        $ce_credits = floatval($_POST['gps_ce_credits'] ?? 0);
        $description = wp_kses_post($_POST['gps_description'] ?? '');
        $course_description = sanitize_textarea_field($_POST['gps_course_description'] ?? '');
        $objectives = sanitize_textarea_field($_POST['gps_objectives'] ?? '');

        // Update all metadata
        update_post_meta($post_id, '_gps_start_date', $start_date);
        update_post_meta($post_id, '_gps_end_date', $end_date);
        update_post_meta($post_id, '_gps_start_time', $start_time);
        update_post_meta($post_id, '_gps_end_time', $end_time);
        update_post_meta($post_id, '_gps_venue', $venue);
        update_post_meta($post_id, '_gps_address', $address);
        update_post_meta($post_id, '_gps_city', $city);
        update_post_meta($post_id, '_gps_state', $state);
        update_post_meta($post_id, '_gps_zip', $zip);
        update_post_meta($post_id, '_gps_country', $country);
        update_post_meta($post_id, '_gps_ce_credits', $ce_credits);
        update_post_meta($post_id, '_gps_description', $description);
        update_post_meta($post_id, '_gps_course_description', $course_description);
        update_post_meta($post_id, '_gps_objectives', $objectives);

        // Handle category
        if (isset($_POST['gps_category']) && !empty($_POST['gps_category'])) {
            wp_set_post_terms($post_id, [(int)$_POST['gps_category']], 'gps_event_category');
        } else {
            wp_set_post_terms($post_id, [], 'gps_event_category');
        }

        // Handle tags
        if (isset($_POST['gps_tags']) && is_array($_POST['gps_tags'])) {
            $tag_ids = array_map('intval', $_POST['gps_tags']);
            wp_set_post_terms($post_id, $tag_ids, 'gps_event_tag');
        } else {
            wp_set_post_terms($post_id, [], 'gps_event_tag');
        }
    }

    /* -------- Course Objectives Metabox -------- */
    public static function render_objectives_meta($post) {
        $objectives = get_post_meta($post->ID, '_gps_objectives', true);
        ?>
        <div class="gps-objectives-editor">
            <p>
                <label><strong><?php _e('Course Objectives','gps-courses'); ?></strong></label>
                <small style="display: block; margin-top: 5px; color: #646970;">
                    <?php _e('Enter one objective per line. These will be displayed with custom icons on the frontend.', 'gps-courses'); ?>
                </small>
            </p>
            <textarea name="gps_objectives" id="gps_objectives" rows="10" class="large-text code" style="width: 100%; font-family: inherit;"><?php echo esc_textarea($objectives); ?></textarea>
            <p class="description">
                <?php _e('Example:', 'gps-courses'); ?><br>
                <?php _e('Learn how to integrate a digital workflow into any type of dental practice', 'gps-courses'); ?><br>
                <?php _e('Gain detailed hands-on experience in the entire digital process', 'gps-courses'); ?>
            </p>
        </div>
        <?php
    }

    /* -------- Session Metabox -------- */
    public static function render_session_meta($post) {
        wp_nonce_field('gps_session_meta', 'gps_session_nonce');

        $event_id   = (int) get_post_meta($post->ID, '_gps_event_id', true);
        $start      = get_post_meta($post->ID, '_gps_start', true);
        $end        = get_post_meta($post->ID, '_gps_end',   true);
        $product_id = (int) get_post_meta($post->ID, '_gps_wc_product_id', true);

        $events = get_posts([
            'post_type'   => 'gps_event',
            'numberposts' => -1,
            'post_status' => 'any',
            'orderby'     => 'title',
            'order'       => 'ASC'
        ]);
        ?>
        <p><label><strong><?php _e('Course','gps-courses'); ?></strong></label>
        <select name="gps_event_id" class="widefat">
            <option value="0"><?php _e('— Select —','gps-courses'); ?></option>
            <?php foreach($events as $e): ?>
                <option value="<?php echo (int) $e->ID; ?>" <?php selected($event_id, $e->ID); ?>>
                    <?php echo esc_html($e->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select></p>

        <p><label><strong><?php _e('Start (YYYY-MM-DD HH:MM)','gps-courses'); ?></strong></label>
        <input type="text" name="gps_session_start" value="<?php echo esc_attr($start); ?>" class="widefat" placeholder="2025-11-21 08:00"></p>

        <p><label><strong><?php _e('End (YYYY-MM-DD HH:MM)','gps-courses'); ?></strong></label>
        <input type="text" name="gps_session_end" value="<?php echo esc_attr($end); ?>" class="widefat" placeholder="2025-11-21 17:00"></p>

        <p><label><strong><?php _e('Woo Product ID (optional)','gps-courses'); ?></strong></label>
        <input type="number" min="0" step="1" name="gps_wc_product_id" value="<?php echo esc_attr($product_id); ?>" class="widefat"></p>
        <?php
    }

    public static function save_session_meta($post_id) {
        if (!isset($_POST['gps_session_nonce']) || !wp_verify_nonce($_POST['gps_session_nonce'], 'gps_session_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $event_id  = (int) ($_POST['gps_event_id'] ?? 0);
        $start     = sanitize_text_field($_POST['gps_session_start'] ?? '');
        $end       = sanitize_text_field($_POST['gps_session_end']   ?? '');
        $productId = (int) ($_POST['gps_wc_product_id'] ?? 0);

        update_post_meta($post_id, '_gps_event_id',      $event_id);
        update_post_meta($post_id, '_gps_start',         $start);
        update_post_meta($post_id, '_gps_end',           $end);
        update_post_meta($post_id, '_gps_wc_product_id', $productId);

        // Bidirectional link: update product with session ID
        if ($productId > 0 && get_post_type($productId) === 'product') {
            update_post_meta($productId, '_gps_session_id', $post_id);
        }
    }

    /* -------- Speaker Metabox -------- */
    public static function render_speaker_meta($post) {
        wp_nonce_field('gps_speaker_meta', 'gps_speaker_nonce');

        $designation = get_post_meta($post->ID, '_gps_designation', true);
        $company     = get_post_meta($post->ID, '_gps_company',     true);
        $email       = get_post_meta($post->ID, '_gps_email',       true);
        $phone       = get_post_meta($post->ID, '_gps_phone',       true);
        $twitter     = get_post_meta($post->ID, '_gps_social_twitter',  true);
        $linkedin    = get_post_meta($post->ID, '_gps_social_linkedin', true);
        $facebook    = get_post_meta($post->ID, '_gps_social_facebook', true);
        ?>
        <p><label><strong><?php _e('Designation / Title','gps-courses'); ?></strong></label>
        <input type="text" name="gps_designation" value="<?php echo esc_attr($designation); ?>" class="widefat" placeholder="DDS, PhD"></p>

        <p><label><strong><?php _e('Company / Institution','gps-courses'); ?></strong></label>
        <input type="text" name="gps_company" value="<?php echo esc_attr($company); ?>" class="widefat" placeholder="GPS Dental Training"></p>

        <p><label><strong><?php _e('Email','gps-courses'); ?></strong></label>
        <input type="email" name="gps_email" value="<?php echo esc_attr($email); ?>" class="widefat" placeholder="speaker@example.com"></p>

        <p><label><strong><?php _e('Phone','gps-courses'); ?></strong></label>
        <input type="text" name="gps_phone" value="<?php echo esc_attr($phone); ?>" class="widefat" placeholder="+1 (555) 123-4567"></p>

        <hr>
        <h4><?php _e('Social Media','gps-courses'); ?></h4>

        <p><label><strong><?php _e('Twitter','gps-courses'); ?></strong></label>
        <input type="url" name="gps_social_twitter" value="<?php echo esc_attr($twitter); ?>" class="widefat" placeholder="https://twitter.com/username"></p>

        <p><label><strong><?php _e('LinkedIn','gps-courses'); ?></strong></label>
        <input type="url" name="gps_social_linkedin" value="<?php echo esc_attr($linkedin); ?>" class="widefat" placeholder="https://linkedin.com/in/username"></p>

        <p><label><strong><?php _e('Facebook','gps-courses'); ?></strong></label>
        <input type="url" name="gps_social_facebook" value="<?php echo esc_attr($facebook); ?>" class="widefat" placeholder="https://facebook.com/username"></p>
        <?php
    }

    public static function save_speaker_meta($post_id) {
        if (!isset($_POST['gps_speaker_nonce']) || !wp_verify_nonce($_POST['gps_speaker_nonce'], 'gps_speaker_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $designation = sanitize_text_field($_POST['gps_designation'] ?? '');
        $company     = sanitize_text_field($_POST['gps_company']     ?? '');
        $email       = sanitize_email($_POST['gps_email']            ?? '');
        $phone       = sanitize_text_field($_POST['gps_phone']       ?? '');
        $twitter     = esc_url_raw($_POST['gps_social_twitter']      ?? '');
        $linkedin    = esc_url_raw($_POST['gps_social_linkedin']     ?? '');
        $facebook    = esc_url_raw($_POST['gps_social_facebook']     ?? '');

        update_post_meta($post_id, '_gps_designation',      $designation);
        update_post_meta($post_id, '_gps_company',          $company);
        update_post_meta($post_id, '_gps_email',            $email);
        update_post_meta($post_id, '_gps_phone',            $phone);
        update_post_meta($post_id, '_gps_social_twitter',   $twitter);
        update_post_meta($post_id, '_gps_social_linkedin',  $linkedin);
        update_post_meta($post_id, '_gps_social_facebook',  $facebook);
    }

    /* -------- Monthly Seminar Metaboxes -------- */
    public static function render_seminar_config_meta($post) {
        wp_nonce_field('gps_seminar_meta', 'gps_seminar_nonce');

        $year = get_post_meta($post->ID, '_gps_seminar_year', true) ?: date('Y');
        $capacity = get_post_meta($post->ID, '_gps_seminar_capacity', true) ?: 50;
        $product_id = get_post_meta($post->ID, '_gps_seminar_product_id', true) ?: '';
        $status = get_post_meta($post->ID, '_gps_seminar_status', true) ?: 'upcoming';
        $tuition = get_post_meta($post->ID, '_gps_seminar_tuition', true) ?: 750;

        // Get WooCommerce products for dropdown
        $products = wc_get_products(['limit' => -1, 'status' => 'publish', 'orderby' => 'title', 'order' => 'ASC']);
        ?>
        <style>
            .gps-seminar-config { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
            .gps-seminar-config p { margin: 0 0 15px 0; }
            .gps-seminar-config label { display: block; font-weight: 600; margin-bottom: 5px; }
            .gps-seminar-config input, .gps-seminar-config select { width: 100%; }
        </style>
        <div class="gps-seminar-config">
            <div>
                <p>
                    <label><?php _e('Seminar Year','gps-courses'); ?></label>
                    <input type="number" name="gps_seminar_year" value="<?php echo esc_attr($year); ?>" min="2020" max="2050" step="1" required>
                </p>

                <p>
                    <label><?php _e('Maximum Capacity','gps-courses'); ?></label>
                    <input type="number" name="gps_seminar_capacity" value="<?php echo esc_attr($capacity); ?>" min="1" max="100" step="1" required>
                    <small><?php _e('Default: 50 participants','gps-courses'); ?></small>
                </p>

                <p>
                    <label><?php _e('Tuition Fee ($)','gps-courses'); ?></label>
                    <input type="number" name="gps_seminar_tuition" value="<?php echo esc_attr($tuition); ?>" min="0" step="0.01" required>
                    <small><?php _e('Default: $750','gps-courses'); ?></small>
                </p>
            </div>

            <div>
                <p>
                    <label><?php _e('WooCommerce Product','gps-courses'); ?></label>
                    <select name="gps_seminar_product_id">
                        <option value=""><?php _e('Select Product...','gps-courses'); ?></option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo esc_attr($product->get_id()); ?>" <?php selected($product_id, $product->get_id()); ?>>
                                <?php echo esc_html($product->get_name()); ?> (ID: <?php echo $product->get_id(); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small><?php _e('Link this seminar to a WooCommerce product for registration','gps-courses'); ?></small>
                </p>

                <p>
                    <label><?php _e('Status','gps-courses'); ?></label>
                    <select name="gps_seminar_status">
                        <option value="upcoming" <?php selected($status, 'upcoming'); ?>><?php _e('Upcoming','gps-courses'); ?></option>
                        <option value="active" <?php selected($status, 'active'); ?>><?php _e('Active','gps-courses'); ?></option>
                        <option value="completed" <?php selected($status, 'completed'); ?>><?php _e('Completed','gps-courses'); ?></option>
                        <option value="cancelled" <?php selected($status, 'cancelled'); ?>><?php _e('Cancelled','gps-courses'); ?></option>
                    </select>
                </p>
            </div>
        </div>
        <?php
    }

    public static function render_seminar_sessions_meta($post) {
        global $wpdb;

        // Get existing sessions from database
        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_sessions WHERE seminar_id = %d ORDER BY session_number ASC",
            $post->ID
        ));

        // Convert to array indexed by session number
        $sessions_data = [];
        foreach ($sessions as $session) {
            $sessions_data[$session->session_number] = $session;
        }
        ?>
        <style>
            .gps-sessions-table { width: 100%; border-collapse: collapse; }
            .gps-sessions-table th, .gps-sessions-table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
            .gps-sessions-table th { background: #f5f5f5; font-weight: 600; }
            .gps-sessions-table input[type="date"], .gps-sessions-table input[type="time"], .gps-sessions-table input[type="text"] { width: 100%; }
            .gps-sessions-help { background: #f0f0f1; padding: 15px; margin-bottom: 20px; border-left: 4px solid #2271b1; }
        </style>

        <div class="gps-sessions-help">
            <strong><?php _e('Session Schedule Instructions:','gps-courses'); ?></strong>
            <ul style="margin: 10px 0 0 20px;">
                <li><?php _e('Configure all 10 sessions for this seminar cycle','gps-courses'); ?></li>
                <li><?php _e('Each session awards 2 CE credits automatically upon check-in','gps-courses'); ?></li>
                <li><?php _e('Sessions run from 6:00 PM - 8:00 PM at GPS Dental Training Center','gps-courses'); ?></li>
                <li><?php _e('Certificates are issued bi-annually on June 30 and December 31','gps-courses'); ?></li>
            </ul>
        </div>

        <table class="gps-sessions-table">
            <thead>
                <tr>
                    <th style="width: 80px;"><?php _e('Session #','gps-courses'); ?></th>
                    <th style="width: 150px;"><?php _e('Date','gps-courses'); ?></th>
                    <th style="width: 100px;"><?php _e('Start Time','gps-courses'); ?></th>
                    <th style="width: 100px;"><?php _e('End Time','gps-courses'); ?></th>
                    <th><?php _e('Topic','gps-courses'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 1; $i <= 10; $i++):
                    $session = $sessions_data[$i] ?? null;
                ?>
                <tr>
                    <td><strong><?php echo $i; ?></strong></td>
                    <td>
                        <input type="date"
                               name="gps_sessions[<?php echo $i; ?>][date]"
                               value="<?php echo $session ? esc_attr($session->session_date) : ''; ?>"
                               required>
                    </td>
                    <td>
                        <input type="time"
                               name="gps_sessions[<?php echo $i; ?>][time_start]"
                               value="<?php echo $session ? esc_attr($session->session_time_start) : '18:00'; ?>">
                    </td>
                    <td>
                        <input type="time"
                               name="gps_sessions[<?php echo $i; ?>][time_end]"
                               value="<?php echo $session ? esc_attr($session->session_time_end) : '20:00'; ?>">
                    </td>
                    <td>
                        <input type="text"
                               name="gps_sessions[<?php echo $i; ?>][topic]"
                               value="<?php echo $session ? esc_attr($session->topic) : ''; ?>"
                               placeholder="<?php _e('Session topic...','gps-courses'); ?>">
                        <input type="hidden"
                               name="gps_sessions[<?php echo $i; ?>][id]"
                               value="<?php echo $session ? esc_attr($session->id) : ''; ?>">
                    </td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
        <?php
    }

    public static function save_seminar_meta($post_id) {
        if (!isset($_POST['gps_seminar_nonce']) || !wp_verify_nonce($_POST['gps_seminar_nonce'], 'gps_seminar_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        global $wpdb;

        // Save seminar configuration
        $year = (int) ($_POST['gps_seminar_year'] ?? date('Y'));
        $capacity = (int) ($_POST['gps_seminar_capacity'] ?? 50);
        $product_id = (int) ($_POST['gps_seminar_product_id'] ?? 0);
        $status = sanitize_text_field($_POST['gps_seminar_status'] ?? 'upcoming');
        $tuition = floatval($_POST['gps_seminar_tuition'] ?? 750);

        update_post_meta($post_id, '_gps_seminar_year', $year);
        update_post_meta($post_id, '_gps_seminar_capacity', $capacity);
        update_post_meta($post_id, '_gps_seminar_product_id', $product_id);
        update_post_meta($post_id, '_gps_seminar_status', $status);
        update_post_meta($post_id, '_gps_seminar_tuition', $tuition);

        // Update product meta to link back to seminar
        if ($product_id) {
            update_post_meta($product_id, '_gps_seminar_id', $post_id);
        }

        // Save sessions
        if (isset($_POST['gps_sessions']) && is_array($_POST['gps_sessions'])) {
            foreach ($_POST['gps_sessions'] as $session_number => $session_data) {
                $session_number = (int) $session_number;
                $session_id = (int) ($session_data['id'] ?? 0);
                $date = sanitize_text_field($session_data['date'] ?? '');
                $time_start = sanitize_text_field($session_data['time_start'] ?? '18:00');
                $time_end = sanitize_text_field($session_data['time_end'] ?? '20:00');
                $topic = sanitize_text_field($session_data['topic'] ?? '');

                // Skip if no date provided
                if (empty($date)) {
                    continue;
                }

                $data = [
                    'seminar_id' => $post_id,
                    'session_number' => $session_number,
                    'session_date' => $date,
                    'session_time_start' => $time_start,
                    'session_time_end' => $time_end,
                    'topic' => $topic,
                    'capacity' => $capacity,
                ];

                if ($session_id > 0) {
                    // Update existing session
                    $wpdb->update(
                        $wpdb->prefix . 'gps_seminar_sessions',
                        $data,
                        ['id' => $session_id],
                        ['%d', '%d', '%s', '%s', '%s', '%s', '%d'],
                        ['%d']
                    );
                } else {
                    // Insert new session
                    $wpdb->insert(
                        $wpdb->prefix . 'gps_seminar_sessions',
                        $data,
                        ['%d', '%d', '%s', '%s', '%s', '%s', '%d']
                    );
                }
            }
        }
    }

    /**
     * Enqueue admin styles and scripts
     */
    public static function admin_enqueue_scripts($hook) {
        $screen = get_current_screen();

        // Load general admin styles for all GPS pages
        if ($screen && (
            strpos($screen->id, 'gps') !== false ||
            in_array($screen->post_type, ['gps_event', 'gps_session', 'gps_organizer', 'gps_sponsor', 'gps_speaker', 'gps_seminar'])
        )) {
            wp_enqueue_style(
                'gps-courses-admin',
                plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin.css',
                [],
                '1.0.1'
            );
        }

        // Only load event-specific scripts on event post type pages
        if ($screen && $screen->post_type === 'gps_event') {
            wp_enqueue_script(
                'gps-event-admin',
                plugin_dir_url(dirname(__FILE__)) . 'assets/js/event-admin.js',
                ['jquery', 'jquery-ui-tabs', 'wp-color-picker', 'media-upload', 'thickbox'],
                '1.0.4',
                true
            );

            // Localize script with thumbnail nonce
            // For new posts, use global post ID, for existing posts use $_GET['post']
            global $post;
            $post_id = isset($_GET['post']) ? intval($_GET['post']) : (isset($post->ID) ? $post->ID : 0);

            // If still no post ID, we need to save the post first before setting thumbnail
            wp_localize_script('gps-event-admin', 'gpsEventAdmin', [
                'thumbnailNonce' => $post_id ? wp_create_nonce('gps_course_thumbnail_' . $post_id) : '',
                'postId' => $post_id
            ]);

            wp_enqueue_style('wp-color-picker');
            wp_enqueue_style('thickbox');
            wp_enqueue_media();
        }
    }

    /**
     * Force single column layout for events
     */
    public static function event_screen_layout_columns($columns) {
        $columns['gps_event'] = 1;
        return $columns;
    }

    public static function event_screen_layout() {
        return 1;
    }

    /**
     * Custom event edit screen with tabbed interface
     */
    public static function custom_event_edit_screen($post) {
        if ($post->post_type !== 'gps_event') {
            return;
        }

        // Get current values with GPS Dental Training defaults for new events
        $is_new_post = ($post->post_status === 'auto-draft');

        $start_date = get_post_meta($post->ID, '_gps_start_date', true);
        $end_date = get_post_meta($post->ID, '_gps_end_date', true);
        $start_time = get_post_meta($post->ID, '_gps_start_time', true);
        $end_time = get_post_meta($post->ID, '_gps_end_time', true);
        $venue = get_post_meta($post->ID, '_gps_venue', true) ?: 'GPS Dental Training Center';
        $address = get_post_meta($post->ID, '_gps_address', true) ?: ($is_new_post ? '6320 Sugarloaf Parkway' : '');
        $city = get_post_meta($post->ID, '_gps_city', true) ?: ($is_new_post ? 'Duluth' : '');
        $state = get_post_meta($post->ID, '_gps_state', true) ?: ($is_new_post ? 'GA' : '');
        $zip = get_post_meta($post->ID, '_gps_zip', true) ?: ($is_new_post ? '30097' : '');
        $country = get_post_meta($post->ID, '_gps_country', true) ?: ($is_new_post ? 'USA' : '');
        $description = get_post_meta($post->ID, '_gps_description', true);
        $course_description = get_post_meta($post->ID, '_gps_course_description', true);
        $ce_credits = get_post_meta($post->ID, '_gps_ce_credits', true);
        $objectives = get_post_meta($post->ID, '_gps_objectives', true);

        // Get taxonomies - handle new posts and errors
        $category_id = '';
        $tag_ids = [];
        if ($post->ID) {
            $category = wp_get_post_terms($post->ID, 'gps_event_category', ['fields' => 'ids']);
            if (!is_wp_error($category) && !empty($category)) {
                $category_id = $category[0];
            }

            $tags = wp_get_post_terms($post->ID, 'gps_event_tag', ['fields' => 'ids']);
            if (!is_wp_error($tags) && !empty($tags)) {
                $tag_ids = $tags;
            }
        }

        wp_nonce_field('gps_event_meta_nonce', 'gps_event_meta_nonce');
        ?>
        <div class="gps-event-edit-container">
            <div class="gps-event-tabs-wrapper">
                <ul class="gps-event-tabs">
                    <li><a href="#gps-tab-basic"><span class="dashicons dashicons-admin-generic"></span> <?php _e('Basic Info', 'gps-courses'); ?></a></li>
                    <li><a href="#gps-tab-details"><span class="dashicons dashicons-info"></span> <?php _e('Details', 'gps-courses'); ?></a></li>
                    <li><a href="#gps-tab-location"><span class="dashicons dashicons-location"></span> <?php _e('Location', 'gps-courses'); ?></a></li>
                    <li><a href="#gps-tab-content"><span class="dashicons dashicons-edit"></span> <?php _e('Description', 'gps-courses'); ?></a></li>
                </ul>

                <!-- Basic Info Tab -->
                <div id="gps-tab-basic" class="gps-tab-content">
                    <!-- Featured Image -->
                    <div class="gps-form-field">
                        <label><?php _e('Course Image', 'gps-courses'); ?></label>
                        <div id="gps-featured-image-container">
                            <?php
                            $thumbnail_id = get_post_thumbnail_id($post->ID);
                            if ($thumbnail_id) {
                                echo wp_get_attachment_image($thumbnail_id, 'medium', false, ['id' => 'gps-featured-image-preview']);
                            } else {
                                echo '<div id="gps-featured-image-placeholder" style="background: #f0f0f1; padding: 60px 20px; text-align: center; border: 2px dashed #dcdcde; border-radius: 6px;">';
                                echo '<span class="dashicons dashicons-format-image" style="font-size: 48px; color: #c0c0c0;"></span><br>';
                                echo '<p style="color: #646970; margin: 10px 0 0 0;">No image selected</p>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                        <p style="margin-top: 10px;">
                            <button type="button" class="button" id="gps-set-featured-image">
                                <?php echo $thumbnail_id ? __('Change Image', 'gps-courses') : __('Set Course Image', 'gps-courses'); ?>
                            </button>
                            <?php if ($thumbnail_id): ?>
                            <button type="button" class="button" id="gps-remove-featured-image"><?php _e('Remove Image', 'gps-courses'); ?></button>
                            <?php endif; ?>
                        </p>
                        <p class="description"><?php _e('Recommended size: 1600x720px', 'gps-courses'); ?></p>
                    </div>

                    <div class="gps-form-grid">
                        <div class="gps-form-field gps-form-field-half">
                            <label for="gps_start_date">
                                <?php _e('Start Date', 'gps-courses'); ?> <span class="required">*</span>
                            </label>
                            <input type="date" id="gps_start_date" name="gps_start_date"
                                   value="<?php echo esc_attr($start_date); ?>" required />
                        </div>

                        <div class="gps-form-field gps-form-field-half">
                            <label for="gps_end_date">
                                <?php _e('End Date', 'gps-courses'); ?> <span class="required">*</span>
                            </label>
                            <input type="date" id="gps_end_date" name="gps_end_date"
                                   value="<?php echo esc_attr($end_date); ?>" required />
                        </div>

                        <div class="gps-form-field gps-form-field-half">
                            <label for="gps_start_time">
                                <?php _e('Start Time', 'gps-courses'); ?> <span class="required">*</span>
                            </label>
                            <input type="time" id="gps_start_time" name="gps_start_time"
                                   value="<?php echo esc_attr($start_time); ?>" required />
                        </div>

                        <div class="gps-form-field gps-form-field-half">
                            <label for="gps_end_time">
                                <?php _e('End Time', 'gps-courses'); ?> <span class="required">*</span>
                            </label>
                            <input type="time" id="gps_end_time" name="gps_end_time"
                                   value="<?php echo esc_attr($end_time); ?>" required />
                        </div>

                        <div class="gps-form-field gps-form-field-half">
                            <label for="gps_category">
                                <?php _e('Category', 'gps-courses'); ?>
                            </label>
                            <?php
                            wp_dropdown_categories([
                                'taxonomy' => 'gps_event_category',
                                'name' => 'gps_category',
                                'id' => 'gps_category',
                                'selected' => $category_id,
                                'show_option_none' => __('Select Category', 'gps-courses'),
                                'option_none_value' => '',
                                'hide_empty' => false,
                                'class' => 'widefat',
                            ]);
                            ?>
                            <p class="description">
                                <a href="#" id="gps-add-new-category" class="gps-add-new-term">
                                    <span class="dashicons dashicons-plus-alt"></span> <?php _e('Add New Category', 'gps-courses'); ?>
                                </a>
                            </p>
                            <div id="gps-new-category-form" style="display: none; margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #dcdcde; border-radius: 4px;">
                                <input type="text" id="gps_new_category_name" placeholder="<?php esc_attr_e('Category Name', 'gps-courses'); ?>" class="widefat" style="margin-bottom: 8px;" />
                                <button type="button" class="button" id="gps-save-new-category">
                                    <?php _e('Add Category', 'gps-courses'); ?>
                                </button>
                                <button type="button" class="button" id="gps-cancel-new-category">
                                    <?php _e('Cancel', 'gps-courses'); ?>
                                </button>
                                <span class="spinner" style="float: none; margin: 0 0 0 10px;"></span>
                            </div>
                        </div>

                        <div class="gps-form-field gps-form-field-half">
                            <label for="gps_tags">
                                <?php _e('Tags', 'gps-courses'); ?>
                            </label>
                            <?php
                            $all_tags = get_terms([
                                'taxonomy' => 'gps_event_tag',
                                'hide_empty' => false,
                            ]);
                            ?>
                            <select name="gps_tags[]" id="gps_tags" multiple class="widefat" style="height: 100px;">
                                <?php
                                if (!empty($all_tags) && !is_wp_error($all_tags)) {
                                    foreach ($all_tags as $tag) {
                                        $selected = in_array($tag->term_id, $tag_ids) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($tag->term_id) . '" ' . $selected . '>' . esc_html($tag->name) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                            <p class="description">
                                <?php _e('Hold Ctrl/Cmd to select multiple tags', 'gps-courses'); ?><br>
                                <a href="#" id="gps-add-new-tag" class="gps-add-new-term">
                                    <span class="dashicons dashicons-plus-alt"></span> <?php _e('Add New Tag', 'gps-courses'); ?>
                                </a>
                            </p>
                            <div id="gps-new-tag-form" style="display: none; margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #dcdcde; border-radius: 4px;">
                                <input type="text" id="gps_new_tag_name" placeholder="<?php esc_attr_e('Tag Name', 'gps-courses'); ?>" class="widefat" style="margin-bottom: 8px;" />
                                <button type="button" class="button" id="gps-save-new-tag">
                                    <?php _e('Add Tag', 'gps-courses'); ?>
                                </button>
                                <button type="button" class="button" id="gps-cancel-new-tag">
                                    <?php _e('Cancel', 'gps-courses'); ?>
                                </button>
                                <span class="spinner" style="float: none; margin: 0 0 0 10px;"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Details Tab -->
                <div id="gps-tab-details" class="gps-tab-content">
                    <div class="gps-form-field">
                        <label for="gps_ce_credits">
                            <?php _e('CE Credits', 'gps-courses'); ?>
                        </label>
                        <input type="number" id="gps_ce_credits" name="gps_ce_credits"
                               value="<?php echo esc_attr($ce_credits); ?>" min="0" step="0.5" class="widefat" />
                        <p class="description"><?php _e('Number of CE credits offered', 'gps-courses'); ?></p>
                    </div>

                    <div class="gps-form-field">
                        <label for="gps_course_description">
                            <?php _e('Course Description', 'gps-courses'); ?>
                        </label>
                        <textarea id="gps_course_description" name="gps_course_description" rows="6"
                                  class="large-text"><?php echo esc_textarea($course_description); ?></textarea>
                        <p class="description"><?php _e('Short course description (can be displayed via widget)', 'gps-courses'); ?></p>
                    </div>

                    <div class="gps-form-field">
                        <label for="gps_objectives">
                            <?php _e('Course Objectives', 'gps-courses'); ?>
                        </label>
                        <textarea id="gps_objectives" name="gps_objectives" rows="8"
                                  class="large-text"><?php echo esc_textarea($objectives); ?></textarea>
                        <p class="description"><?php _e('Enter one objective per line', 'gps-courses'); ?></p>
                    </div>
                </div>

                <!-- Location Tab -->
                <div id="gps-tab-location" class="gps-tab-content">
                    <div class="gps-form-field">
                        <label for="gps_venue">
                            <?php _e('Venue Name', 'gps-courses'); ?>
                        </label>
                        <input type="text" id="gps_venue" name="gps_venue"
                               value="<?php echo esc_attr($venue); ?>" class="widefat" />
                    </div>

                    <div class="gps-form-field">
                        <label for="gps_address">
                            <?php _e('Address', 'gps-courses'); ?>
                        </label>
                        <input type="text" id="gps_address" name="gps_address"
                               value="<?php echo esc_attr($address); ?>" class="widefat" />
                    </div>

                    <div class="gps-form-grid">
                        <div class="gps-form-field gps-form-field-half">
                            <label for="gps_city">
                                <?php _e('City', 'gps-courses'); ?>
                            </label>
                            <input type="text" id="gps_city" name="gps_city"
                                   value="<?php echo esc_attr($city); ?>" />
                        </div>

                        <div class="gps-form-field gps-form-field-half">
                            <label for="gps_state">
                                <?php _e('State/Province', 'gps-courses'); ?>
                            </label>
                            <input type="text" id="gps_state" name="gps_state"
                                   value="<?php echo esc_attr($state); ?>" />
                        </div>

                        <div class="gps-form-field gps-form-field-half">
                            <label for="gps_zip">
                                <?php _e('ZIP/Postal Code', 'gps-courses'); ?>
                            </label>
                            <input type="text" id="gps_zip" name="gps_zip"
                                   value="<?php echo esc_attr($zip); ?>" />
                        </div>

                        <div class="gps-form-field gps-form-field-half">
                            <label for="gps_country">
                                <?php _e('Country', 'gps-courses'); ?>
                            </label>
                            <input type="text" id="gps_country" name="gps_country"
                                   value="<?php echo esc_attr($country); ?>" />
                        </div>
                    </div>
                </div>

                <!-- Description Tab -->
                <div id="gps-tab-content" class="gps-tab-content">
                    <div class="gps-form-field">
                        <label for="gps_description">
                            <?php _e('Event Description', 'gps-courses'); ?>
                        </label>
                        <?php
                        wp_editor($description, 'gps_description', [
                            'textarea_name' => 'gps_description',
                            'textarea_rows' => 15,
                            'media_buttons' => true,
                            'teeny' => false,
                            'tinymce' => true,
                        ]);
                        ?>
                        <p class="description"><?php _e('Full event description and details', 'gps-courses'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for adding a new category
     */
    public static function ajax_add_category() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gps_event_meta_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_categories')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Get category name
        $name = sanitize_text_field($_POST['name'] ?? '');
        if (empty($name)) {
            wp_send_json_error('Category name is required');
            return;
        }

        // Create the category
        $result = wp_insert_term($name, 'gps_event_category');

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }

        // Get the term object
        $term = get_term($result['term_id'], 'gps_event_category');

        wp_send_json_success([
            'term_id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
        ]);
    }

    /**
     * AJAX handler for adding a new tag
     */
    public static function ajax_add_tag() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gps_event_meta_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_categories')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Get tag name
        $name = sanitize_text_field($_POST['name'] ?? '');
        if (empty($name)) {
            wp_send_json_error('Tag name is required');
            return;
        }

        // Create the tag
        $result = wp_insert_term($name, 'gps_event_tag');

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }

        // Get the term object
        $term = get_term($result['term_id'], 'gps_event_tag');

        wp_send_json_success([
            'term_id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
        ]);
    }

    /**
     * AJAX handler for setting course thumbnail
     */
    public static function ajax_set_course_thumbnail() {
        try {
            // Verify nonce
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';

            if (!$post_id || !wp_verify_nonce($nonce, 'gps_course_thumbnail_' . $post_id)) {
                wp_send_json_error('Invalid security token');
                return;
            }

            // Check permissions
            if (!current_user_can('edit_post', $post_id)) {
                wp_send_json_error('You do not have permission to edit this course');
                return;
            }

            // Get thumbnail ID
            $thumbnail_id = isset($_POST['thumbnail_id']) ? intval($_POST['thumbnail_id']) : 0;
            if (!$thumbnail_id) {
                wp_send_json_error('No image selected');
                return;
            }

            // Verify the attachment exists
            if (!wp_attachment_is_image($thumbnail_id)) {
                wp_send_json_error('Invalid image');
                return;
            }

            // Set the thumbnail
            $result = set_post_thumbnail($post_id, $thumbnail_id);

            if ($result) {
                wp_send_json_success([
                    'message' => 'Course image set successfully',
                    'thumbnail_id' => $thumbnail_id
                ]);
            } else {
                wp_send_json_error('Failed to set course image');
            }
        } catch (Exception $e) {
            error_log('GPS Courses - Set Thumbnail Error: ' . $e->getMessage());
            wp_send_json_error('An unexpected error occurred: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler for removing course thumbnail
     */
    public static function ajax_remove_course_thumbnail() {
        try {
            // Verify nonce
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';

            if (!$post_id || !wp_verify_nonce($nonce, 'gps_course_thumbnail_' . $post_id)) {
                wp_send_json_error('Invalid security token');
                return;
            }

            // Check permissions
            if (!current_user_can('edit_post', $post_id)) {
                wp_send_json_error('You do not have permission to edit this course');
                return;
            }

            // Remove the thumbnail
            $result = delete_post_thumbnail($post_id);

            if ($result) {
                wp_send_json_success([
                    'message' => 'Course image removed successfully'
                ]);
            } else {
                wp_send_json_error('Failed to remove course image');
            }
        } catch (Exception $e) {
            error_log('GPS Courses - Remove Thumbnail Error: ' . $e->getMessage());
            wp_send_json_error('An unexpected error occurred: ' . $e->getMessage());
        }
    }

    /**
     * One-time migration from old meta keys to new standardized keys
     */
    public static function migrate_old_meta_keys() {
        // Check if migration already completed
        if (get_option('gps_date_meta_migrated')) {
            return;
        }

        // Get all event posts
        $events = get_posts([
            'post_type' => 'gps_event',
            'posts_per_page' => -1,
            'post_status' => 'any',
        ]);

        foreach ($events as $event) {
            // Migrate start date
            $old_start = get_post_meta($event->ID, '_gps_date_start', true);
            if (!empty($old_start) && empty(get_post_meta($event->ID, '_gps_start_date', true))) {
                update_post_meta($event->ID, '_gps_start_date', $old_start);
            }

            // Migrate end date
            $old_end = get_post_meta($event->ID, '_gps_date_end', true);
            if (!empty($old_end) && empty(get_post_meta($event->ID, '_gps_end_date', true))) {
                update_post_meta($event->ID, '_gps_end_date', $old_end);
            }
        }

        // Mark migration as complete
        update_option('gps_date_meta_migrated', true);
    }

    /**
     * Clear all calendar transient caches
     * Called when events are saved, updated, or deleted
     */
    public static function clear_calendar_cache($post_id = 0) {
        global $wpdb;

        // Delete all calendar transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_gps_calendar_%'
             OR option_name LIKE '_transient_timeout_gps_calendar_%'"
        );

        // Clear object cache if using persistent caching
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('gps_calendar');
        }
    }

    /**
     * Disable block editor for GPS post types that use classic editor
     */
    public static function disable_block_editor($use_block_editor, $post_type) {
        // Force classic editor for GPS post types
        if (in_array($post_type, ['gps_event', 'gps_seminar'])) {
            return false;
        }
        return $use_block_editor;
    }
}
