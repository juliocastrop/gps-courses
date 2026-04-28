<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Promotional QR analytics dashboard.
 *
 * Reads from wp_gps_qr_codes / wp_gps_qr_scans (populated by
 * QR_Tracker on each scan) and renders KPIs, charts and a sortable
 * detail table under the gps-dashboard admin menu.
 *
 * Bots (is_bot=1) are excluded from all aggregations.
 */
class QR_Dashboard {

    const MENU_SLUG = 'gps-qr-analytics';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 30);
    }

    public static function register_menu() {
        add_submenu_page(
            'gps-dashboard',
            __('QR Analytics', 'gps-courses'),
            __('QR Analytics', 'gps-courses'),
            'manage_options',
            self::MENU_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'gps-courses'));
        }

        $range = isset($_GET['range']) ? sanitize_text_field($_GET['range']) : '30d';
        $allowed = ['7d' => 7, '30d' => 30, '90d' => 90, '365d' => 365, 'all' => null];
        if (!array_key_exists($range, $allowed)) {
            $range = '30d';
        }
        $days = $allowed[$range];

        $kpis        = self::query_kpis($days);
        $daily       = self::query_daily_scans($days);
        $devices     = self::query_devices($days);
        $top_qrs     = self::query_top_qrs($days, 10);
        $countries   = self::query_countries($days, 10);
        $details     = self::query_qr_details();

        ?>
        <div class="wrap gps-qr-analytics">
            <h1 style="margin-bottom: 8px;"><?php esc_html_e('QR Code Analytics', 'gps-courses'); ?></h1>
            <p style="color:#64748b; margin-top:0;"><?php esc_html_e('Promotional QR scan metrics. Bot traffic excluded.', 'gps-courses'); ?></p>

            <div style="margin: 16px 0;">
                <?php
                $base = remove_query_arg('range');
                foreach ($allowed as $key => $_) {
                    $url = add_query_arg('range', $key, $base);
                    $is_active = $key === $range;
                    $label = $key === 'all' ? __('All time', 'gps-courses') : sprintf(__('Last %s', 'gps-courses'), str_replace('d', ' days', $key));
                    printf(
                        '<a href="%s" class="button %s" style="margin-right:6px;">%s</a>',
                        esc_url($url),
                        $is_active ? 'button-primary' : '',
                        esc_html($label)
                    );
                }
                ?>
            </div>

            <!-- KPIs -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:24px;">
                <?php
                self::render_kpi(__('Total Scans', 'gps-courses'),    number_format_i18n($kpis['total_scans']),  '#0B52AC');
                self::render_kpi(__('Unique Scans', 'gps-courses'),   number_format_i18n($kpis['unique_scans']), '#10b981');
                self::render_kpi(__('Active QRs', 'gps-courses'),     number_format_i18n($kpis['active_qrs']),   '#f59e0b');
                self::render_kpi(__('Avg / QR', 'gps-courses'),       number_format_i18n($kpis['avg_per_qr'], 1), '#8b5cf6');
                ?>
            </div>

            <!-- Charts row 1 -->
            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:16px; margin-bottom:24px;">
                <?php self::render_chart_card(__('Scans Over Time', 'gps-courses'), 'gpsQrDaily', 360); ?>
                <?php self::render_chart_card(__('Device Breakdown', 'gps-courses'), 'gpsQrDevices', 360); ?>
            </div>

            <!-- Charts row 2 -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:24px;">
                <?php self::render_chart_card(__('Top QR Codes', 'gps-courses'), 'gpsQrTop', 360); ?>
                <?php self::render_chart_card(__('Top Countries', 'gps-courses'), 'gpsQrCountries', 360); ?>
            </div>

            <!-- Detail table -->
            <h2 style="margin-top:32px;"><?php esc_html_e('All QR Codes', 'gps-courses'); ?></h2>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Post', 'gps-courses'); ?></th>
                        <th><?php esc_html_e('Type', 'gps-courses'); ?></th>
                        <th><?php esc_html_e('Short Code', 'gps-courses'); ?></th>
                        <th><?php esc_html_e('Scans', 'gps-courses'); ?></th>
                        <th><?php esc_html_e('Unique', 'gps-courses'); ?></th>
                        <th><?php esc_html_e('Last Scan', 'gps-courses'); ?></th>
                        <th><?php esc_html_e('Created', 'gps-courses'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($details)): ?>
                        <tr><td colspan="7" style="text-align:center; padding:24px; color:#64748b;">
                            <?php esc_html_e('No QR codes generated yet. Edit an event or seminar to create one.', 'gps-courses'); ?>
                        </td></tr>
                    <?php else: foreach ($details as $row): ?>
                        <tr>
                            <td>
                                <?php if ($row->edit_url): ?>
                                    <a href="<?php echo esc_url($row->edit_url); ?>"><strong><?php echo esc_html($row->title); ?></strong></a>
                                <?php else: ?>
                                    <em><?php esc_html_e('(deleted post)', 'gps-courses'); ?></em>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(self::pretty_post_type($row->post_type)); ?></td>
                            <td><code><?php echo esc_html($row->short_code); ?></code></td>
                            <td><strong><?php echo number_format_i18n($row->scan_count); ?></strong></td>
                            <td><?php echo number_format_i18n($row->unique_scans); ?></td>
                            <td>
                                <?php
                                if ($row->last_scanned_at) {
                                    echo esc_html(human_time_diff(strtotime($row->last_scanned_at), current_time('timestamp')) . ' ' . __('ago', 'gps-courses'));
                                } else {
                                    echo '<span style="color:#94a3b8;">—</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($row->created_at))); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
        (function() {
            const palette = ['#0B52AC', '#DDC89D', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4', '#ec4899', '#84cc16', '#64748b'];

            // Daily scans (line)
            const daily = <?php echo wp_json_encode($daily); ?>;
            new Chart(document.getElementById('gpsQrDaily'), {
                type: 'line',
                data: {
                    labels: daily.map(d => d.date),
                    datasets: [{
                        label: '<?php echo esc_js(__('Scans', 'gps-courses')); ?>',
                        data: daily.map(d => parseInt(d.scans, 10)),
                        borderColor: '#0B52AC',
                        backgroundColor: 'rgba(11,82,172,0.1)',
                        fill: true,
                        tension: 0.3,
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });

            // Devices (doughnut)
            const devices = <?php echo wp_json_encode($devices); ?>;
            new Chart(document.getElementById('gpsQrDevices'), {
                type: 'doughnut',
                data: {
                    labels: devices.map(d => d.device_type || 'unknown'),
                    datasets: [{
                        data: devices.map(d => parseInt(d.cnt, 10)),
                        backgroundColor: palette,
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });

            // Top QRs (horizontal bar)
            const topQrs = <?php echo wp_json_encode($top_qrs); ?>;
            new Chart(document.getElementById('gpsQrTop'), {
                type: 'bar',
                data: {
                    labels: topQrs.map(q => q.title || q.short_code),
                    datasets: [{
                        label: '<?php echo esc_js(__('Scans', 'gps-courses')); ?>',
                        data: topQrs.map(q => parseInt(q.scans, 10)),
                        backgroundColor: '#0B52AC',
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } }
                }
            });

            // Top countries (horizontal bar)
            const countries = <?php echo wp_json_encode($countries); ?>;
            new Chart(document.getElementById('gpsQrCountries'), {
                type: 'bar',
                data: {
                    labels: countries.map(c => c.country || '<?php echo esc_js(__('Unknown', 'gps-courses')); ?>'),
                    datasets: [{
                        label: '<?php echo esc_js(__('Scans', 'gps-courses')); ?>',
                        data: countries.map(c => parseInt(c.cnt, 10)),
                        backgroundColor: '#DDC89D',
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } }
                }
            });
        })();
        </script>
        <?php
    }

    /* ====================================================================
     * Rendering helpers
     * ==================================================================== */

    protected static function render_kpi($label, $value, $color) {
        ?>
        <div style="background:#fff; border-left:4px solid <?php echo esc_attr($color); ?>; padding:16px 20px; border-radius:6px; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
            <div style="font-size:12px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;"><?php echo esc_html($label); ?></div>
            <div style="font-size:28px; font-weight:700; color:#0f172a;"><?php echo esc_html($value); ?></div>
        </div>
        <?php
    }

    protected static function render_chart_card($title, $canvas_id, $height) {
        ?>
        <div style="background:#fff; padding:16px 20px; border-radius:6px; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
            <h3 style="margin:0 0 12px; font-size:14px; color:#0f172a;"><?php echo esc_html($title); ?></h3>
            <div style="height:<?php echo (int) $height; ?>px;">
                <canvas id="<?php echo esc_attr($canvas_id); ?>"></canvas>
            </div>
        </div>
        <?php
    }

    protected static function pretty_post_type($pt) {
        $map = [
            'gps_event'   => __('Event', 'gps-courses'),
            'gps_seminar' => __('Seminar', 'gps-courses'),
        ];
        return $map[$pt] ?? $pt;
    }

    /* ====================================================================
     * Queries
     * ==================================================================== */

    protected static function date_clause($days, $alias = 's') {
        if ($days === null) return '1=1';
        global $wpdb;
        return $wpdb->prepare("$alias.scanned_at >= DATE_SUB(NOW(), INTERVAL %d DAY)", $days);
    }

    protected static function query_kpis($days) {
        global $wpdb;
        $dc = self::date_clause($days);

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gps_qr_scans s WHERE $dc AND s.is_bot = 0"
        );
        $unique = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT s.ip_hash) FROM {$wpdb->prefix}gps_qr_scans s WHERE $dc AND s.is_bot = 0 AND s.ip_hash <> ''"
        );
        $active = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT s.qr_id) FROM {$wpdb->prefix}gps_qr_scans s WHERE $dc AND s.is_bot = 0"
        );
        $total_qrs = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gps_qr_codes WHERE deleted_at IS NULL"
        );

        return [
            'total_scans'  => $total,
            'unique_scans' => $unique,
            'active_qrs'   => $active,
            'avg_per_qr'   => $total_qrs > 0 ? round($total / $total_qrs, 1) : 0,
        ];
    }

    protected static function query_daily_scans($days) {
        global $wpdb;
        $window = $days === null ? 365 : $days;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(scanned_at) AS date, COUNT(*) AS scans
             FROM {$wpdb->prefix}gps_qr_scans
             WHERE scanned_at >= DATE_SUB(NOW(), INTERVAL %d DAY) AND is_bot = 0
             GROUP BY DATE(scanned_at)
             ORDER BY date ASC",
            $window
        ));

        // Fill missing days with zero so the chart line is continuous
        $by_date = [];
        foreach ($rows as $r) $by_date[$r->date] = (int) $r->scans;

        $out = [];
        $start = (new \DateTime())->modify('-' . ($window - 1) . ' days');
        for ($i = 0; $i < $window; $i++) {
            $d = $start->format('Y-m-d');
            $out[] = ['date' => $d, 'scans' => $by_date[$d] ?? 0];
            $start->modify('+1 day');
        }
        return $out;
    }

    protected static function query_devices($days) {
        global $wpdb;
        $dc = self::date_clause($days);
        return $wpdb->get_results(
            "SELECT COALESCE(NULLIF(device_type, ''), 'unknown') AS device_type, COUNT(*) AS cnt
             FROM {$wpdb->prefix}gps_qr_scans s
             WHERE $dc AND s.is_bot = 0
             GROUP BY device_type
             ORDER BY cnt DESC"
        );
    }

    protected static function query_top_qrs($days, $limit = 10) {
        global $wpdb;
        $dc = self::date_clause($days);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.qr_id, c.short_code, c.post_id, COUNT(*) AS scans
             FROM {$wpdb->prefix}gps_qr_scans s
             JOIN {$wpdb->prefix}gps_qr_codes c ON c.id = s.qr_id
             WHERE $dc AND s.is_bot = 0
             GROUP BY s.qr_id
             ORDER BY scans DESC
             LIMIT %d",
            $limit
        ));
        foreach ($rows as $r) {
            $post = get_post($r->post_id);
            $r->title = $post ? $post->post_title : ('#' . $r->post_id);
        }
        return $rows;
    }

    protected static function query_countries($days, $limit = 10) {
        global $wpdb;
        $dc = self::date_clause($days);
        return $wpdb->get_results($wpdb->prepare(
            "SELECT country, COUNT(*) AS cnt
             FROM {$wpdb->prefix}gps_qr_scans s
             WHERE $dc AND s.is_bot = 0 AND country IS NOT NULL AND country <> ''
             GROUP BY country
             ORDER BY cnt DESC
             LIMIT %d",
            $limit
        ));
    }

    protected static function query_qr_details() {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT c.*, COUNT(DISTINCT s.ip_hash) AS unique_scans
             FROM {$wpdb->prefix}gps_qr_codes c
             LEFT JOIN {$wpdb->prefix}gps_qr_scans s ON s.qr_id = c.id AND s.is_bot = 0
             WHERE c.deleted_at IS NULL
             GROUP BY c.id
             ORDER BY c.scan_count DESC, c.created_at DESC"
        );
        foreach ($rows as $r) {
            $post = get_post($r->post_id);
            $r->title    = $post ? $post->post_title : null;
            $r->edit_url = $post ? get_edit_post_link($r->post_id) : null;
        }
        return $rows;
    }
}
