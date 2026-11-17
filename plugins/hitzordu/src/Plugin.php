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
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'template_redirect', array( new CancelPage(), 'maybe_render' ) );
		add_action('admin_menu', array(new Admin\BookingsPage(), 'register'));
		$settings_page = new Admin\SettingsPage();
		add_action('admin_menu', array($settings_page, 'register_menu'));
		add_action('admin_init', array($settings_page, 'register_settings'));
		$hours_page = new Admin\HoursPage();
		add_action('admin_menu', array($hours_page, 'register_menu'));
		add_action('admin_post_' . Admin\HoursPage::ACTION, array($hours_page, 'save'));
		add_action('init', array($this, 'register_block'));
		add_action('enqueue_block_editor_assets', array($this, 'editor_data'));

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
	 * Register the widget script. It is only loaded on pages that use the shortcode.
	 */
	public function register_assets(): void {
		$file = plugin_dir_path( HITZORDU_FILE ) . 'public/js/hitzordu-widget.js';

		wp_register_script_module(
			'hitzordu-widget',
			plugins_url( 'public/js/hitzordu-widget.js', HITZORDU_FILE ),
			array(),
			file_exists( $file ) ? (string) filemtime( $file ) : HITZORDU_VERSION
		);
	}

	/**
	 * Register the [hitzordu] shortcode.
	 */
	public function register_shortcode(): void {
		add_shortcode( 'hitzordu', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Print the widget element and ask WordPress to load the script.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	/**
	 * The [hitzordu] shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function render_shortcode($atts): string
	{
		$atts = shortcode_atts(
			array(
				'type' => 'discovery-call',
				'contact' => '',
			),
			$atts,
			'hitzordu'
		);

		return $this->render_widget((string) $atts['type'], (string) $atts['contact']);
	}

	/**
	 * Print the widget element and ask WordPress to load the script.
	 * Used by both the shortcode and the block.
	 *
	 * @param string $type    Meeting type slug.
	 * @param string $contact Optional URL shown when JavaScript is off.
	 */
	public function render_widget(string $type, string $contact = ''): string
	{
		wp_enqueue_script_module('hitzordu-widget');

		$fallback = esc_html__('The booking calendar needs JavaScript to work.', 'hitzordu');

		if ('' !== $contact) {
			$fallback .= ' <a href="' . esc_url($contact) . '">'
				. esc_html__('Contact us to arrange a call instead.', 'hitzordu')
				. '</a>';
		}

		return sprintf(
			'<hitzordu-widget api-url="%1$s" meeting-type="%2$s"><noscript><p>%3$s</p></noscript></hitzordu-widget>',
			esc_url(rest_url('hitzordu/v1')),
			esc_attr(sanitize_key($type)),
			$fallback // Each part is escaped above.
		);
	}

	/**
	 * Register the booking block from its block.json.
	 */
	public function register_block(): void
	{
		register_block_type(HITZORDU_DIR . 'blocks/booking');
	}

	/**
	 * Give the block editor the list of active meeting types.
	 */
	public function editor_data(): void
	{
		global $wpdb;

		$types = $wpdb->get_results(
			"SELECT slug, name, duration_minutes FROM {$wpdb->prefix}hitzordu_meeting_types WHERE active = 1 ORDER BY name"
		);

		wp_add_inline_script(
			generate_block_asset_handle('hitzordu/booking', 'editorScript'),
			'window.hitzorduMeetingTypes = ' . wp_json_encode($types) . ';',
			'before'
		);
	}
}
