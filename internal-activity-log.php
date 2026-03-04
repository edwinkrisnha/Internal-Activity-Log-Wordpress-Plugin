<?php
/**
 * Plugin Name: 			Internal Activity Log
 * Plugin URI:        https://github.com/edwinkrisnha/Internal-Activity-Log-Wordpress-Plugin
 * Description: 			Tracks and visualizes WordPress user activity with charts and detailed logs.
 * Version:     			1.0.0
 * Author:            Edwin Krisnha
 * Author URI:        https://github.com/edwinkrisnha
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       internal-activity-log
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'IAL_VERSION',    '1.0.0' );
define( 'IAL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IAL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'IAL_TABLE_NAME', 'activity_log' ); // used without $wpdb->prefix inside classes

require_once IAL_PLUGIN_DIR . 'includes/class-installer.php';
require_once IAL_PLUGIN_DIR . 'includes/class-logger.php';
require_once IAL_PLUGIN_DIR . 'includes/class-query.php';
require_once IAL_PLUGIN_DIR . 'includes/class-admin.php';

register_activation_hook( __FILE__, [ 'IAL_Installer', 'install' ] );
register_deactivation_hook( __FILE__, [ 'IAL_Installer', 'deactivate' ] );
register_uninstall_hook( __FILE__, [ 'IAL_Installer', 'uninstall' ] );

add_action( 'plugins_loaded', static function () {
	IAL_Logger::init();
	IAL_Admin::init();
} );
