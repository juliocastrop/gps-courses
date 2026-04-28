<?php
namespace GPSC;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

if (!defined('ABSPATH')) exit;

/**
 * Promotional QR code generator: metabox UI + SVG/PNG downloads.
 *
 * Phase 2 of the promotional QR feature. Relies on the data layer and
 * /qr/{short_code} redirect set up in QR_Tracker (Phase 1).
 */
class QR_Promotional {

    const PNG_SIZE   = 1024;
    const SVG_SIZE   = 1024; // logical viewBox; SVG is vector so scales freely
    const POST_TYPES = ['gps_event', 'gps_seminar'];

    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'register_metabox']);

        add_action('wp_ajax_gps_qr_save',     [__CLASS__, 'ajax_save']);
        add_action('wp_ajax_gps_qr_download', [__CLASS__, 'ajax_download']);
        add_action('wp_ajax_gps_qr_preview',  [__CLASS__, 'ajax_preview']);
    }

    /* ====================================================================
     * Metabox
     * ==================================================================== */

    public static function register_metabox() {
        foreach (self::POST_TYPES as $type) {
            add_meta_box(
                'gps_qr_promotional',
                __('Promotional QR Code', 'gps-courses'),
                [__CLASS__, 'render_metabox'],
                $type,
                'side',
                'default'
            );
        }
    }

    public static function render_metabox($post) {
        if ($post->post_status === 'auto-draft') {
            echo '<p style="color: #777;">' . esc_html__('Save the post first to generate a QR code.', 'gps-courses') . '</p>';
            return;
        }

        $qr = QR_Tracker::get_or_create_for_post($post->ID);
        if (!$qr) {
            echo '<p>' . esc_html__('Could not initialize QR code.', 'gps-courses') . '</p>';
            return;
        }

        $qr_url = QR_Tracker::get_qr_url($qr);
        $logo_available = (bool) self::get_logo_path();

        wp_nonce_field('gps_qr_metabox', 'gps_qr_nonce');
        ?>
        <div class="gps-qr-metabox" data-qr-id="<?php echo (int) $qr->id; ?>">
            <div class="gps-qr-preview" style="text-align:center; margin-bottom:12px; min-height:200px; background:#f8fafc; border-radius:8px; padding:12px;">
                <img src="<?php echo esc_url(self::preview_url($qr->id)); ?>" alt="QR" style="max-width:200px; height:auto;">
            </div>

            <p style="font-size:11px; color:#64748b; margin:0 0 12px; word-break:break-all;">
                <strong><?php esc_html_e('Scan URL:', 'gps-courses'); ?></strong><br>
                <code style="font-size:11px;"><?php echo esc_html($qr_url); ?></code>
            </p>

            <p style="font-size:11px; color:#64748b; margin:0 0 12px;">
                <strong><?php esc_html_e('Total scans:', 'gps-courses'); ?></strong>
                <span class="gps-qr-scan-count"><?php echo (int) $qr->scan_count; ?></span>
                <?php if ($qr->last_scanned_at): ?>
                    <br><strong><?php esc_html_e('Last:', 'gps-courses'); ?></strong>
                    <?php echo esc_html(human_time_diff(strtotime($qr->last_scanned_at), current_time('timestamp')) . ' ' . __('ago', 'gps-courses')); ?>
                <?php endif; ?>
            </p>

            <p>
                <label style="display:flex; align-items:center; gap:6px; font-size:12px;">
                    <input type="checkbox" class="gps-qr-has-logo" <?php checked($qr->has_logo, 1); ?> <?php disabled(!$logo_available); ?>>
                    <?php esc_html_e('Include logo at center', 'gps-courses'); ?>
                </label>
                <?php if (!$logo_available): ?>
                    <span style="font-size:11px; color:#b45309; display:block; margin-top:4px;">
                        <?php
                        printf(
                            /* translators: %s: settings page link */
                            esc_html__('No logo available. Upload one in %s.', 'gps-courses'),
                            '<a href="' . esc_url(admin_url('admin.php?page=gps-email-settings')) . '">' . esc_html__('Email Settings', 'gps-courses') . '</a>'
                        );
                        ?>
                    </span>
                <?php endif; ?>
            </p>

            <details style="margin: 8px 0 12px;">
                <summary style="cursor:pointer; font-size:12px; font-weight:600;"><?php esc_html_e('UTM Parameters', 'gps-courses'); ?></summary>
                <p style="margin:8px 0 4px;">
                    <label style="font-size:11px; display:block; margin-bottom:2px;"><?php esc_html_e('Source', 'gps-courses'); ?></label>
                    <input type="text" class="gps-qr-utm widefat" data-key="utm_source" value="<?php echo esc_attr($qr->utm_source); ?>">
                </p>
                <p style="margin:6px 0;">
                    <label style="font-size:11px; display:block; margin-bottom:2px;"><?php esc_html_e('Medium', 'gps-courses'); ?></label>
                    <input type="text" class="gps-qr-utm widefat" data-key="utm_medium" value="<?php echo esc_attr($qr->utm_medium); ?>">
                </p>
                <p style="margin:6px 0;">
                    <label style="font-size:11px; display:block; margin-bottom:2px;"><?php esc_html_e('Campaign', 'gps-courses'); ?></label>
                    <input type="text" class="gps-qr-utm widefat" data-key="utm_campaign" value="<?php echo esc_attr($qr->utm_campaign); ?>">
                </p>
                <p style="margin:6px 0;">
                    <label style="font-size:11px; display:block; margin-bottom:2px;"><?php esc_html_e('Content (optional)', 'gps-courses'); ?></label>
                    <input type="text" class="gps-qr-utm widefat" data-key="utm_content" value="<?php echo esc_attr($qr->utm_content); ?>">
                </p>
            </details>

            <p style="margin:8px 0 4px;">
                <button type="button" class="button button-secondary gps-qr-save-btn" style="width:100%;">
                    <?php esc_html_e('Save Settings', 'gps-courses'); ?>
                </button>
            </p>

            <p style="display:flex; gap:6px; margin:8px 0 0;">
                <a href="<?php echo esc_url(self::download_url($qr->id, 'png')); ?>" class="button button-primary" style="flex:1; text-align:center;">
                    <?php esc_html_e('Download PNG', 'gps-courses'); ?>
                </a>
                <a href="<?php echo esc_url(self::download_url($qr->id, 'svg')); ?>" class="button button-primary" style="flex:1; text-align:center;">
                    <?php esc_html_e('Download SVG', 'gps-courses'); ?>
                </a>
            </p>
        </div>

        <script>
        (function() {
            const box = document.querySelector('.gps-qr-metabox');
            if (!box) return;
            const qrId = box.dataset.qrId;
            const nonce = document.getElementById('gps_qr_nonce').value;

            box.querySelector('.gps-qr-save-btn').addEventListener('click', async function() {
                const data = new FormData();
                data.append('action', 'gps_qr_save');
                data.append('qr_id', qrId);
                data.append('_wpnonce', nonce);
                data.append('has_logo', box.querySelector('.gps-qr-has-logo').checked ? '1' : '0');
                box.querySelectorAll('.gps-qr-utm').forEach(el => {
                    data.append(el.dataset.key, el.value);
                });
                this.disabled = true;
                this.textContent = '<?php echo esc_js(__('Saving...', 'gps-courses')); ?>';
                try {
                    const res = await fetch(ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' });
                    const json = await res.json();
                    if (json.success) {
                        // Refresh preview by busting cache
                        const img = box.querySelector('.gps-qr-preview img');
                        img.src = img.src.split('&_t=')[0] + '&_t=' + Date.now();
                        this.textContent = '<?php echo esc_js(__('Saved ✓', 'gps-courses')); ?>';
                        setTimeout(() => { this.textContent = '<?php echo esc_js(__('Save Settings', 'gps-courses')); ?>'; }, 1500);
                    } else {
                        this.textContent = '<?php echo esc_js(__('Error', 'gps-courses')); ?>';
                    }
                } catch (e) {
                    this.textContent = '<?php echo esc_js(__('Error', 'gps-courses')); ?>';
                }
                this.disabled = false;
            });
        })();
        </script>
        <?php
    }

    /* ====================================================================
     * AJAX endpoints
     * ==================================================================== */

    public static function ajax_save() {
        if (!current_user_can('edit_posts') || !check_ajax_referer('gps_qr_metabox', '_wpnonce', false)) {
            wp_send_json_error(['message' => 'unauthorized'], 403);
        }

        $qr_id = (int) ($_POST['qr_id'] ?? 0);
        if (!$qr_id) {
            wp_send_json_error(['message' => 'missing qr_id']);
        }

        QR_Tracker::update_qr($qr_id, [
            'utm_source'   => $_POST['utm_source']   ?? 'qr',
            'utm_medium'   => $_POST['utm_medium']   ?? 'print',
            'utm_campaign' => $_POST['utm_campaign'] ?? '',
            'utm_content'  => $_POST['utm_content']  ?? '',
            'has_logo'     => !empty($_POST['has_logo']) ? 1 : 0,
        ]);

        wp_send_json_success();
    }

    public static function ajax_preview() {
        $qr_id = (int) ($_GET['qr_id'] ?? 0);
        if (!current_user_can('edit_posts') || !$qr_id) {
            status_header(403);
            exit;
        }

        $qr = self::get_qr_row($qr_id);
        if (!$qr) {
            status_header(404);
            exit;
        }

        $result = self::generate($qr, 'png', 400);
        nocache_headers();
        header('Content-Type: image/png');
        echo $result;
        exit;
    }

    public static function ajax_download() {
        $qr_id  = (int) ($_GET['qr_id'] ?? 0);
        $format = strtolower($_GET['format'] ?? 'png');

        if (!current_user_can('edit_posts') || !$qr_id || !in_array($format, ['png', 'svg'], true)) {
            wp_die(__('Invalid request', 'gps-courses'), 400);
        }

        $qr = self::get_qr_row($qr_id);
        if (!$qr) {
            wp_die(__('QR not found', 'gps-courses'), 404);
        }

        $size = $format === 'png' ? self::PNG_SIZE : self::SVG_SIZE;
        $data = self::generate($qr, $format, $size);

        $post = get_post($qr->post_id);
        $filename = sanitize_title(($post ? $post->post_name : 'qr') . '-qr') . '.' . $format;

        nocache_headers();
        header('Content-Type: ' . ($format === 'png' ? 'image/png' : 'image/svg+xml'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $data;
        exit;
    }

    /* ====================================================================
     * Generation
     * ==================================================================== */

    /**
     * Build a QR code image as raw bytes.
     *
     * @param object $qr     Row from wp_gps_qr_codes
     * @param string $format 'png' | 'svg'
     * @param int    $size   pixels (PNG) / viewBox units (SVG)
     */
    public static function generate($qr, $format = 'png', $size = 1024) {
        $url = QR_Tracker::get_qr_url($qr);

        $writer = $format === 'svg' ? new SvgWriter() : new PngWriter();

        $builder = Builder::create()
            ->writer($writer)
            ->writerOptions([])
            ->data($url)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->size($size)
            ->margin((int) round($size * 0.04))
            ->roundBlockSizeMode(new RoundBlockSizeModeMargin());

        if ($qr->has_logo) {
            $logo_path = self::get_logo_path();
            if ($logo_path) {
                $builder = $builder
                    ->logoPath($logo_path)
                    ->logoResizeToWidth((int) round($size * 0.20))
                    ->logoPunchoutBackground(true);
            }
        }

        return $builder->build()->getString();
    }

    /* ====================================================================
     * Helpers
     * ==================================================================== */

    protected static function get_qr_row($qr_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_qr_codes WHERE id = %d AND deleted_at IS NULL LIMIT 1",
            $qr_id
        ));
    }

    /**
     * Locate a usable logo file. Order:
     * 1. gps_qr_logo_url (dedicated setting, if added later)
     * 2. gps_email_header_image (existing email logo)
     *
     * Returns absolute file path, or null if none usable.
     */
    public static function get_logo_path() {
        $candidates = [
            get_option('gps_qr_logo_url'),
            get_option('gps_email_header_image'),
        ];

        foreach ($candidates as $url) {
            if (!$url) continue;
            $path = self::url_to_path($url);
            if ($path && file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    protected static function url_to_path($url) {
        $uploads = wp_get_upload_dir();
        if (strpos($url, $uploads['baseurl']) === 0) {
            return str_replace($uploads['baseurl'], $uploads['basedir'], $url);
        }
        // Site-relative or absolute fallback
        $site_url = home_url();
        if (strpos($url, $site_url) === 0) {
            return ABSPATH . ltrim(str_replace($site_url, '', $url), '/');
        }
        return null;
    }

    protected static function preview_url($qr_id) {
        return add_query_arg([
            'action' => 'gps_qr_preview',
            'qr_id'  => $qr_id,
        ], admin_url('admin-ajax.php'));
    }

    protected static function download_url($qr_id, $format) {
        return add_query_arg([
            'action' => 'gps_qr_download',
            'qr_id'  => $qr_id,
            'format' => $format,
        ], admin_url('admin-ajax.php'));
    }
}
