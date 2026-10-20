<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Tools › Error log: the last lines of PHP's error log, for administrators.
 *
 * The log should live outside the public folder (WP_DEBUG_LOG set to a path
 * such as /home/<account>/gps-logs/wp-debug.log in wp-config.php); this
 * screen is how it is read then. Read only: it never changes or deletes it.
 */
class Error_Log {

    const LINES = 300;

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu'], 40);
    }

    public static function menu() {
        add_submenu_page('gps-dashboard', __('Error log', 'gps-courses'), __('Error log', 'gps-courses'), 'manage_options', 'gps-error-log', [__CLASS__, 'render']);
    }

    /** The file PHP writes to, '' when there is none. */
    public static function path() {
        $path = (string) ini_get('error_log');
        return ('' !== $path && @is_file($path) && @is_readable($path)) ? $path : '';
    }

    /** Whether the log can be downloaded from the web (it is under the site's folder). */
    public static function is_public($path) {
        $path = wp_normalize_path($path);
        return '' !== $path && 0 === strpos($path, wp_normalize_path(ABSPATH));
    }

    /** Last $n lines without reading a large file whole. */
    protected static function tail($path, $n) {
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            return [];
        }
        $size  = filesize($path);
        $chunk = 8192;
        $data  = '';
        $pos   = $size;
        while ($pos > 0 && substr_count($data, "\n") <= $n) {
            $read = (int) min($chunk, $pos);
            $pos -= $read;
            fseek($fh, $pos);
            $data = fread($fh, $read) . $data;
        }
        fclose($fh);
        $lines = preg_split('/\r?\n/', rtrim($data));
        return array_slice($lines, -$n);
    }

    public static function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'gps-courses'));
        }
        $only_gps = !isset($_GET['all']);
        $path     = self::path();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Error log', 'gps-courses'); ?></h1>
            <?php if ('' === $path) : ?>
                <div class="notice notice-info inline"><p><?php esc_html_e('No error log file is configured or it cannot be read.', 'gps-courses'); ?></p></div>
            <?php else :
                if (self::is_public($path)) : ?>
                    <div class="notice notice-error inline"><p><strong><?php esc_html_e('This log is inside the public site folder: anyone who knows the URL can download it.', 'gps-courses'); ?></strong> <?php esc_html_e('Set WP_DEBUG_LOG to a path outside public_html in wp-config.php and delete the old file.', 'gps-courses'); ?></p></div>
                <?php endif;
                $lines = self::tail($path, $only_gps ? self::LINES * 10 : self::LINES);
                if ($only_gps) {
                    $lines = array_slice(array_values(array_filter($lines, function ($l) {
                        return false !== stripos($l, 'gps-courses') || false !== stripos($l, 'GPS ');
                    })), -self::LINES);
                }
                ?>
                <p>
                    <?php printf(esc_html__('File: %1$s (%2$s)', 'gps-courses'), '<code>' . esc_html($path) . '</code>', esc_html(size_format(filesize($path)))); ?>
                    &nbsp;·&nbsp;
                    <?php if ($only_gps) : ?>
                        <a href="<?php echo esc_url(add_query_arg('all', '1')); ?>"><?php esc_html_e('Show every line', 'gps-courses'); ?></a>
                    <?php else : ?>
                        <a href="<?php echo esc_url(remove_query_arg('all')); ?>"><?php esc_html_e('Only GPS Courses lines', 'gps-courses'); ?></a>
                    <?php endif; ?>
                </p>
                <p class="description"><?php printf(esc_html__('Newest at the bottom. Showing up to %d lines.', 'gps-courses'), self::LINES); ?></p>
                <pre style="max-height:70vh;overflow:auto;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px;font-size:12px;line-height:1.5;white-space:pre-wrap;word-break:break-word;"><?php
                foreach ($lines as $line) {
                    $style = preg_match('/Fatal|Uncaught/', $line) ? 'color:#b32d2e;font-weight:600;' : (preg_match('/Warning/', $line) ? 'color:#8a4b00;' : '');
                    echo '<span style="' . esc_attr($style) . '">' . esc_html($line) . "</span>\n";
                }
                if (!$lines) {
                    esc_html_e('No lines to show.', 'gps-courses');
                }
                ?></pre>
            <?php endif; ?>
        </div>
        <?php
    }
}
