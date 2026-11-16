<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.giuz.io
 * @since             1.0.0
 * @package           Hitzordu
 *
 * @wordpress-plugin
 * Plugin Name:       Hitzordu
 * Plugin URI:        https://agencyga.es
 * Description:       Appointment and call booking for WordPress.
 * Version:           1.0.0
 * Author:            Alessandro Giuzio
 * Author URI:        https://www.giuz.io/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       hitzordu
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Composer autoloader: loads the Hitzordu core library.
 */
require_once __DIR__ . '/vendor/autoload.php';

/**
 * TEMPORARY smoke test: confirms the core library loads. Remove after checking.
 */
add_action('admin_notices', function () {
	echo '<div class="notice notice-info"><p>Hitzordu core '
		. esc_html(\Hitzordu\Core\Core::version())
		. ' loaded.</p></div>';
});

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('HITZORDU_VERSION', '1.0.0');

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-hitzordu-activator.php
 */
function activate_hitzordu()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-hitzordu-activator.php';
	Hitzordu_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-hitzordu-deactivator.php
 */
function deactivate_hitzordu()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-hitzordu-deactivator.php';
	Hitzordu_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_hitzordu');
register_deactivation_hook(__FILE__, 'deactivate_hitzordu');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-hitzordu.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_hitzordu()
{

	$plugin = new Hitzordu();
	$plugin->run();

}
run_hitzordu();
