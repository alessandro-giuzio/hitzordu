<?php
/**
 * Admin screen: upcoming bookings.
 *
 * @package Hitzordu
 */

namespace Hitzordu\Plugin\Admin;

use DateTimeImmutable;
use DateTimeZone;

class BookingsPage {

	/**
	 * Slug of the top-level Hitzordu menu.
	 */
	const SLUG = 'hitzordu';

	/**
	 * Add the Hitzordu menu and its Bookings page. Runs on admin_menu.
	 */
	public function register(): void {
		add_menu_page(
			__( 'Hitzordu Bookings', 'hitzordu' ),
			__( 'Hitzordu', 'hitzordu' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-calendar-alt',
			26
		);

		// Same slug as the parent: renames the automatic first item to "Bookings".
		add_submenu_page(
			self::SLUG,
			__( 'Hitzordu Bookings', 'hitzordu' ),
			__( 'Bookings', 'hitzordu' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Print the list of upcoming bookings.
	 */
	public function render(): void {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$prefix = $wpdb->prefix . 'hitzordu_';

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.starts_at, b.name, b.email, b.notes, b.status, t.name AS type_name
				FROM {$prefix}bookings b
				JOIN {$prefix}meeting_types t ON t.id = b.meeting_type_id
				WHERE b.starts_at >= %s
				ORDER BY b.starts_at ASC
				LIMIT 100",
				gmdate( 'Y-m-d H:i:s' )
			)
		);

		$utc      = new DateTimeZone( 'UTC' );
		$statuses = array(
			'confirmed' => __( 'Confirmed', 'hitzordu' ),
			'cancelled' => __( 'Cancelled', 'hitzordu' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Upcoming bookings', 'hitzordu' ); ?></h1>
			<p>
				<?php
				/* translators: %s: the site's time zone, e.g. Europe/Madrid. */
				printf( esc_html__( 'Times are shown in %s.', 'hitzordu' ), esc_html( wp_timezone_string() ) );
				?>
			</p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'When', 'hitzordu' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Meeting', 'hitzordu' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Name', 'hitzordu' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Email', 'hitzordu' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Notes', 'hitzordu' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'hitzordu' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $bookings ) ) : ?>
						<tr>
							<td colspan="6"><?php esc_html_e( 'No upcoming bookings.', 'hitzordu' ); ?></td>
						</tr>
					<?php endif; ?>

					<?php foreach ( $bookings as $booking ) : ?>
						<?php $start = new DateTimeImmutable( $booking->starts_at, $utc ); ?>
						<tr>
							<td><?php echo esc_html( wp_date( 'D j M Y, H:i', $start->getTimestamp() ) ); ?></td>
							<td><?php echo esc_html( $booking->type_name ); ?></td>
							<td><?php echo esc_html( $booking->name ); ?></td>
							<td><a href="<?php echo esc_url( 'mailto:' . $booking->email ); ?>"><?php echo esc_html( $booking->email ); ?></a></td>
							<td><?php echo esc_html( $booking->notes ); ?></td>
							<td><?php echo esc_html( $statuses[ $booking->status ] ?? $booking->status ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
