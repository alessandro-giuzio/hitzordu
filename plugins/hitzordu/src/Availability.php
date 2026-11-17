<?php
/**
 * Works out slots from the stored hours, overrides, rules and bookings.
 *
 * @package Hitzordu
 */

namespace Hitzordu\Plugin;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeZone;
use Hitzordu\Core\SlotGenerator;

class Availability
{

	/**
	 * The shared slot logic.
	 *
	 * @var SlotGenerator
	 */
	private SlotGenerator $generator;

	public function __construct()
	{
		$this->generator = new SlotGenerator();
	}

	/**
	 * Every slot the hours allow within the notice and window rules, booked or not.
	 *
	 * @param object            $type Meeting type row: duration, notice and window columns.
	 * @param DateTimeImmutable $from First day, midnight in the site's zone.
	 * @param DateTimeImmutable $to   Last day, midnight in the site's zone.
	 * @return DateTimeImmutable[] Slot starts in UTC.
	 */
	public function grid_slots(object $type, DateTimeImmutable $from, DateTimeImmutable $to): array
	{
		global $wpdb;

		$zone = wp_timezone();
		$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
		$duration = (int) $type->duration_minutes;
		$rules = $wpdb->get_results(
			"SELECT weekday, start_time, end_time FROM {$wpdb->prefix}hitzordu_availability_rules"
		);
		$overrides = $this->overrides($from, $to);
		$slots = array();
		$days = new DatePeriod($from, new DateInterval('P1D'), $to->modify('+1 day'));

		foreach ($days as $day) {
			$date = $day->format('Y-m-d');

			// 'N' is the ISO weekday: 1 = Monday ... 7 = Sunday.
			foreach ($this->hours_for($date, (int) $day->format('N'), $rules, $overrides) as $hours) {
				$slots = array_merge(
					$slots,
					$this->generator->forDay($date, $hours->start_time, $hours->end_time, $duration, $zone)
				);
			}
		}

		// Minimum notice and booking window, both counted from now.
		$earliest = $now->modify('+' . (int) $type->min_notice_minutes . ' minutes');
		$latest = $now->modify('+' . (int) $type->window_days . ' days');

		return $this->generator->withinWindow($slots, $earliest, $latest);
	}

	/**
	 * Slots no confirmed booking overlaps, keeping the meeting type's buffer clear.
	 *
	 * @param object            $type Meeting type row.
	 * @param DateTimeImmutable $from First day, midnight in the site's zone.
	 * @param DateTimeImmutable $to   Last day, midnight in the site's zone.
	 * @return DateTimeImmutable[] Slot starts in UTC.
	 */
	public function free_slots(object $type, DateTimeImmutable $from, DateTimeImmutable $to): array
	{
		return $this->generator->removeBusy(
			$this->grid_slots($type, $from, $to),
			(int) $type->duration_minutes,
			$this->busy_periods($from, $to),
			(int) $type->buffer_minutes
		);
	}

	/**
	 * Opening hours for one date: its overrides if it has any, otherwise the weekly hours.
	 *
	 * @param string   $date      The date, YYYY-MM-DD.
	 * @param int      $weekday   ISO weekday of the date.
	 * @param object[] $rules     Weekly hours rows.
	 * @param array    $overrides Override rows grouped by date.
	 * @return object[] Rows with start_time and end_time.
	 */
	private function hours_for(string $date, int $weekday, array $rules, array $overrides): array
	{
		if (isset($overrides[$date])) {
			foreach ($overrides[$date] as $override) {
				if (!(int) $override->is_available) {
					return array(); // A day off wins over any special hours on the same date.
				}
			}

			return array_values(
				array_filter(
					$overrides[$date],
					fn($override) => $override->start_time && $override->end_time
				)
			);
		}

		return array_values(
			array_filter(
				$rules,
				fn($rule) => (int) $rule->weekday === $weekday
			)
		);
	}

	/**
	 * Date overrides in the range, grouped by date.
	 *
	 * @param DateTimeImmutable $from First day.
	 * @param DateTimeImmutable $to   Last day.
	 * @return array<string, object[]>
	 */
	private function overrides(DateTimeImmutable $from, DateTimeImmutable $to): array
	{
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT override_date, is_available, start_time, end_time FROM {$wpdb->prefix}hitzordu_date_overrides WHERE override_date BETWEEN %s AND %s",
				$from->format('Y-m-d'),
				$to->format('Y-m-d')
			)
		);

		$by_date = array();

		foreach ($rows as $row) {
			$by_date[$row->override_date][] = $row;
		}

		return $by_date;
	}

	/**
	 * Confirmed bookings in the range, as start and end pairs in UTC.
	 *
	 * @param DateTimeImmutable $from First day, midnight in the site's zone.
	 * @param DateTimeImmutable $to   Last day, midnight in the site's zone.
	 * @return array[]
	 */
	private function busy_periods(DateTimeImmutable $from, DateTimeImmutable $to): array
	{
		global $wpdb;

		$utc = new DateTimeZone('UTC');
		$range_start = $from->setTimezone($utc)->format('Y-m-d H:i:s');
		$range_end = $to->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s');

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT starts_at, ends_at FROM {$wpdb->prefix}hitzordu_bookings WHERE status = 'confirmed' AND starts_at < %s AND ends_at > %s",
				$range_end,
				$range_start
			)
		);

		$busy = array();

		foreach ($rows as $row) {
			$busy[] = array(
				new DateTimeImmutable($row->starts_at, $utc),
				new DateTimeImmutable($row->ends_at, $utc),
			);
		}

		return $busy;
	}
}
