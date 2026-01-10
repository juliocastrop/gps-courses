<?php
/**
 * Plugin Name: GPS Courses
 * Description: Stable event/course management with CE Credits + WooCommerce.
 * Version: 1.0.2
 * Author: WebMinds (Julio Castro)
 * Text Domain: gps-courses
 */

if (!defined('ABSPATH')) exit;

define('GPSC_VERSION', '1.0.2');
define('GPSC_PATH', plugin_dir_path(__FILE__));
define('GPSC_URL', plugin_dir_url(__FILE__));

/**
 * Load Composer autoloader
 */
if (file_exists(GPSC_PATH . 'vendor/autoload.php')) {
    require_once GPSC_PATH . 'vendor/autoload.php';
}

/**
 * Cargar primero los archivos necesarios antes de registrar hooks
 */
require_once GPSC_PATH . 'includes/class-activator.php';
require_once GPSC_PATH . 'includes/class-plugin.php';

/**
 * Hooks principales
 */
register_activation_hook(__FILE__, ['GPSC\\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['GPSC\\Activator', 'deactivate']);
add_action('plugins_loaded', ['GPSC\\Plugin', 'init']);
