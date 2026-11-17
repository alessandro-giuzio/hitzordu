<?php
/**
 * Shows a confirm page when a visitor opens the cancel link from their email.
 *
 * @package Hitzordu
 */

namespace Hitzordu\Plugin;

use DateTimeImmutable;
use DateTimeZone;

class CancelPage {

	/**
	 * Take over the request when the URL carries a cancel token.
	 */
	public function maybe_render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The token itself is the proof of permission.
		if ( ! isset( $_GET['hitzordu_cancel'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token        = sanitize_text_field( wp_unslash( $_GET['hitzordu_cancel'] ) );
		$cancellation = new Cancellation();
		$posted       = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'];

		// Only a POST, sent by the confirm button, changes anything.
		$status = $posted ? $cancellation->cancel( $token ) : $cancellation->status( $token );

		nocache_headers();

		switch ( $status ) {
			case 'cancelled':
				$this->page( __( 'Booking cancelled', 'hitzordu' ), __( 'Your booking has been cancelled. The time is free again.', 'hitzordu' ) );
				break;

			case 'already_cancelled':
				$this->page( __( 'Already cancelled', 'hitzordu' ), __( 'This booking was already cancelled.', 'hitzordu' ) );
				break;

			case 'too_late':
				$this->page( __( 'Too late to cancel', 'hitzordu' ), __( 'This booking has already started or passed.', 'hitzordu' ) );
				break;

			case 'open':
				$this->confirm_page( $cancellation->find( $token ) );
				break;

			default:
				$this->page( __( 'Booking not found', 'hitzordu' ), __( 'This cancel link is not valid.', 'hitzordu' ), 404 );
		}
	}

	/**
	 * Ask the visitor to confirm, with a button that posts back to this URL.
	 *
	 * @param object $booking The booking row.
	 */
	private function confirm_page( object $booking ): void {
		$start = new DateTimeImmutable( $booking->starts_at, new DateTimeZone( 'UTC' ) );
		$zone  = new DateTimeZone( $booking->timezone );
		$when  = wp_date( 'l j F Y, H:i', $start->getTimestamp(), $zone ) . ' (' . $zone->getName() . ')';

		$html  = '<h1>' . esc_html__( 'Cancel this booking?', 'hitzordu' ) . '</h1>';
		$html .= '<p>' . esc_html( $booking->type_name . ': ' . $when ) . '</p>';
		$html .= '<form method="post"><p><button type="submit">';
		$html .= esc_html__( 'Yes, cancel my booking', 'hitzordu' );
		$html .= '</button></p></form>';

		wp_die( $html, esc_html__( 'Cancel booking', 'hitzordu' ), array( 'response' => 200 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each part is escaped above.
	}

	/**
	 * Show a simple message page.
	 *
	 * @param string $title    Page title and heading.
	 * @param string $message  One line of explanation.
	 * @param int    $response HTTP status code.
	 */
	private function page( string $title, string $message, int $response = 200 ): void {
		wp_die(
			'<h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $message ) . '</p>',
			esc_html( $title ),
			array( 'response' => (int) $response )
		);
	}
}
