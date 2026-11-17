<?php
/**
 * Looks up and cancels bookings by their cancel token.
 *
 * @package Hitzordu
 */

namespace Hitzordu\Plugin;

use DateTimeImmutable;
use DateTimeZone;

class Cancellation {

	/**
	 * The booking that owns the token, or null.
	 *
	 * @param string $token The cancel token.
	 */
	public function find( string $token ): ?object {
		global $wpdb;

		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return null;
		}

		$prefix = $wpdb->prefix . 'hitzordu_';

		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT b.id, b.status, b.starts_at, b.timezone, t.name AS type_name
				FROM {$prefix}bookings b
				JOIN {$prefix}meeting_types t ON t.id = b.meeting_type_id
				WHERE b.cancel_token = %s",
				$token
			)
		);

		return $booking ? $booking : null;
	}

	/**
	 * Where a booking stands: not_found, already_cancelled, too_late or open.
	 *
	 * @param string $token The cancel token.
	 */
	public function status( string $token ): string {
		$booking = $this->find( $token );

		if ( ! $booking ) {
			return 'not_found';
		}

		if ( 'cancelled' === $booking->status ) {
			return 'already_cancelled';
		}

		$utc = new DateTimeZone( 'UTC' );

		if ( new DateTimeImmutable( $booking->starts_at, $utc ) <= new DateTimeImmutable( 'now', $utc ) ) {
			return 'too_late';
		}

		return 'open';
	}

	/**
	 * Cancel the booking if it is still open.
	 *
	 * @param string $token The cancel token.
	 * @return string cancelled, or the status that prevented it.
	 */
	public function cancel( string $token ): string {
		global $wpdb;

		$status = $this->status( $token );

		if ( 'open' !== $status ) {
			return $status;
		}

		$wpdb->update(
			$wpdb->prefix . 'hitzordu_bookings',
			array( 'status' => 'cancelled' ),
			array( 'cancel_token' => $token ),
			array( '%s' ),
			array( '%s' )
		);

		return 'cancelled';
	}
}
