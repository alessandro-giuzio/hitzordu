<?php
/**
 * Main plugin class: registers runtime hooks.
 *
 * @package Hitzordu
 */

namespace Hitzordu\Plugin;

/**
 * Wires the plugin into WordPress.
 */
class Plugin {

	/**
	 * Register all runtime hooks. Called on plugins_loaded.
	 */
	public function boot(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		$this->maybe_upgrade();
	}

	/**
	 * Load translations from /languages.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'hitzordu',
			false,
			dirname( plugin_basename( HITZORDU_FILE ) ) . '/languages'
		);
	}

	/**
	 * Re-run the schema when the stored version differs from the code.
	 *
	 * The activation hook does not run on plugin updates, so this covers them.
	 */
	public function maybe_upgrade(): void {
		if ( get_option( 'hitzordu_db_version' ) !== Activator::DB_VERSION ) {
			Activator::create_tables();
			update_option( 'hitzordu_db_version', Activator::DB_VERSION );
		}
	}

	/**
	 * Register the plugin's REST routes. Runs on rest_api_init.
	 */
	public function register_rest_routes(): void
	{
		(new Rest\AvailabilityController())->register_routes();
		(new Rest\BookingsController())->register_routes();
	}

	/**
	 * TODO: register and enqueue scripts and styles here.
	 */
	public function register_assets(): void {
	}
}
