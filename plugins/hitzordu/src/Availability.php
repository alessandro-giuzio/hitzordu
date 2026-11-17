<?php
/**
 * Works out slots from the stored working hours and bookings.
 *
 * @package Hitzordu
 */

namespace Hitzordu\Plugin;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeZone;
use Hitzordu\Core\SlotGenerator;

class Availability {

	/**
	 * The shared slot logic.
	 *
	 * @var SlotGenerator
	 */
	private SlotGenerator $generator;

	public function __construct() {
		$this->generator = new SlotGenerator();
	}

	/**
	 * Every future slot the working hours produce, booked or not.
	 *
	 * @param int               $duration Meeting length in minutes.
	 * @param DateTimeImmutable $from     First day, midnight in the site's zone.
	 * @param DateTimeImmutable $to       Last day, midnight in the site's zone.
	 * @return DateTimeImmutable[] Slot starts in UTC.
	 */
	public function grid_slots( int $duration, DateTimeImmutable $from, DateTimeImmutable $to ): array {
		global $wpdb;

		$zone  = wp_timezone();
		$now   = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$rules = $wpdb->get_results(
			"SELECT weekday, start_time, end_time FROM {$wpdb->prefix}hitzordu_availability_rules"
		);
		$slots = array();
		$days  = new DatePeriod( $from, new DateInterval( 'P1D' ), $to->modify( '+1 day' ) );

		foreach ( $days as $day ) {
			foreach ( $rules as $rule ) {
				// 'N' is the ISO weekday: 1 = Monday ... 7 = Sunday.
				if ( (int) $rule->weekday !== (int) $day->format( 'N' ) ) {
					continue;
				}

				$day_slots = $this->generator->forDay(
					$day->format( 'Y-m-d' ),
					$rule->start_time,
					$rule->end_time,
					$duration,
					$zone
				);

				foreach ( $day_slots as $slot ) {
					if ( $slot > $now ) {
						$slots[] = $slot;
					}
				}
			}
		}

		return $slots;
	}

	/**
	 * Future slots that no confirmed booking overlaps.
	 *
	 * @param int               $duration Meeting length in minutes.
	 * @param DateTimeImmutable $from     First day, midnight in the site's zone.
	 * @param DateTimeImmutable $to       Last day, midnight in the site's zone.
	 * @return DateTimeImmutable[] Slot starts in UTC.
	 */
	public function free_slots( int $duration, DateTimeImmutable $from, DateTimeImmutable $to ): array {
		return $this->generator->removeBusy(
			$this->grid_slots( $duration, $from, $to ),
			$duration,
			$this->busy_periods( $from, $to )
		);
	}

	/**
	 * Confirmed bookings in the range, as start and end pairs in UTC.
	 *
	 * @param DateTimeImmutable $from First day, midnight in the site's zone.
	 * @param DateTimeImmutable $to   Last day, midnight in the site's zone.
	 * @return array[]
	 */
	private function busy_periods( DateTimeImmutable $from, DateTimeImmutable $to ): array {
		global $wpdb;

		$utc         = new DateTimeZone( 'UTC' );
		$range_start = $from->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
		$range_end   = $to->modify( '+1 day' )->setTimezone( $utc )->format( 'Y-m-d H:i:s' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT starts_at, ends_at FROM {$wpdb->prefix}hitzordu_bookings WHERE status = 'confirmed' AND starts_at < %s AND ends_at > %s",
				$range_end,
				$range_start
			)
		);

		$busy = array();

		foreach ( $rows as $row ) {
			$busy[] = array(
				new DateTimeImmutable( $row->starts_at, $utc ),
				new DateTimeImmutable( $row->ends_at, $utc ),
			);
		}

		return $busy;
	}
}
