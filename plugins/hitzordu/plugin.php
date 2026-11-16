<?php
/**
 * Plugin Name:       Hitzordu
 * Plugin URI:        https://agencyga.es
 * Description:       Appointment and call booking for WordPress.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            Alessandro Giuzio
 * Author URI:        https://www.giuz.io/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       hitzordu
 * Domain Path:       /languages
 *
 * @package Hitzordu
 */

use Hitzordu\Plugin\Activator;
use Hitzordu\Plugin\Deactivator;
use Hitzordu\Plugin\Plugin;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HITZORDU_VERSION', '1.0.0' );
define( 'HITZORDU_FILE', __FILE__ );
define( 'HITZORDU_DIR', plugin_dir_path( __FILE__ ) );

$hitzordu_autoload = __DIR__ . '/vendor/autoload.php';

if ( ! file_exists( $hitzordu_autoload ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'Hitzordu: dependencies are missing. Run "composer install" in the plugin folder.', 'hitzordu' )
				. '</p></div>';
		}
	);
	return;
}

require_once $hitzordu_autoload;

register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Deactivator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		( new Plugin() )->boot();
	}
);
