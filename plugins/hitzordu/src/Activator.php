<?php
/**
 * Runs on plugin activation and creates the database tables.
 *
 * @package Hitzordu
 */

namespace Hitzordu\Plugin;

/**
 * Activation tasks.
 */
class Activator {

	/**
	 * Current database schema version. Bump when the tables change.
	 */
	public const DB_VERSION = '1';

	/**
	 * Called by register_activation_hook().
	 */
	public static function activate(): void {
		self::create_tables();
    self::seed_defaults();
		update_option( 'hitzordu_db_version', self::DB_VERSION );
	}

	/**
	 * Create or update the plugin tables with dbDelta().
	 */
	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$prefix  = $wpdb->prefix . 'hitzordu_';
		$charset = $wpdb->get_charset_collate();

		$sql = array();

		$sql[] = "CREATE TABLE {$prefix}meeting_types (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  slug varchar(100) NOT NULL,
  name varchar(190) NOT NULL,
  duration_minutes smallint(5) unsigned NOT NULL,
  buffer_minutes smallint(5) unsigned NOT NULL DEFAULT 0,
  min_notice_minutes int(10) unsigned NOT NULL DEFAULT 0,
  window_days smallint(5) unsigned NOT NULL DEFAULT 60,
  active tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  UNIQUE KEY slug (slug)
) $charset;";

		$sql[] = "CREATE TABLE {$prefix}availability_rules (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  weekday tinyint(3) unsigned NOT NULL,
  start_time time NOT NULL,
  end_time time NOT NULL,
  PRIMARY KEY  (id),
  KEY weekday (weekday)
) $charset;";

		$sql[] = "CREATE TABLE {$prefix}date_overrides (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  override_date date NOT NULL,
  is_available tinyint(1) NOT NULL DEFAULT 0,
  start_time time NULL,
  end_time time NULL,
  PRIMARY KEY  (id),
  KEY override_date (override_date)
) $charset;";

		$sql[] = "CREATE TABLE {$prefix}bookings (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  meeting_type_id bigint(20) unsigned NOT NULL,
  starts_at datetime NOT NULL,
  ends_at datetime NOT NULL,
  name varchar(190) NOT NULL,
  email varchar(190) NOT NULL,
  notes text NULL,
  timezone varchar(64) NOT NULL,
  locale varchar(20) NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'confirmed',
  cancel_token char(64) NOT NULL,
  idempotency_key varchar(64) NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY cancel_token (cancel_token),
  UNIQUE KEY idempotency_key (idempotency_key),
  KEY starts_at (starts_at),
  KEY status (status)
) $charset;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

  /**
   * Insert a default meeting type and weekly hours on a fresh install.
   */
  public static function seed_defaults(): void
  {
    global $wpdb;

    $prefix = $wpdb->prefix . 'hitzordu_';

    // Only seed an empty install, so reactivating never duplicates rows.
    $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}meeting_types");

    if ($count > 0) {
      return;
    }

    $wpdb->insert(
      "{$prefix}meeting_types",
      array(
        'slug' => 'discovery-call',
        'name' => 'Discovery call',
        'duration_minutes' => 30,
      ),
      array('%s', '%s', '%d')
    );

    // Monday (1) to Friday (5), 09:00 to 17:00.
    for ($weekday = 1; $weekday <= 5; $weekday++) {
      $wpdb->insert(
        "{$prefix}availability_rules",
        array(
          'weekday' => $weekday,
          'start_time' => '09:00:00',
          'end_time' => '17:00:00',
        ),
        array('%d', '%s', '%s')
      );
    }
  }
}
