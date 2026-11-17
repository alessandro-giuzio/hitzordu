<?php
/**
 * Sends booking emails.
 *
 * @package Hitzordu
 */

namespace Hitzordu\Plugin;

use DateTimeImmutable;
use DateTimeZone;

class Notifier
{

  /**
   * Email the visitor and the site owner about a new booking.
   *
   * @param int $booking_id The booking's ID.
   */
  public function booking_confirmed(int $booking_id): void
  {
    global $wpdb;

    $prefix = $wpdb->prefix . 'hitzordu_';

    $booking = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT b.*, t.name AS type_name, t.duration_minutes
				FROM {$prefix}bookings b
				JOIN {$prefix}meeting_types t ON t.id = b.meeting_type_id
				WHERE b.id = %d",
        $booking_id
      )
    );

    if (!$booking) {
      return;
    }

    $start = new DateTimeImmutable($booking->starts_at, new DateTimeZone('UTC'));
    $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $cancel_url = add_query_arg('hitzordu_cancel', $booking->cancel_token, home_url('/'));

    // To the visitor, in the visitor's time zone.
    $visitor_lines = array(
      /* translators: %s: visitor's name. */
      sprintf(__('Hi %s,', 'hitzordu'), $booking->name),
      '',
      /* translators: 1: meeting type name, 2: site name. */
      sprintf(__('Your %1$s with %2$s is confirmed.', 'hitzordu'), $booking->type_name, $site_name),
      '',
      /* translators: %s: date and time with time zone. */
      sprintf(__('When: %s', 'hitzordu'), $this->format_when($start, new DateTimeZone($booking->timezone))),
      /* translators: %d: meeting length in minutes. */
      sprintf(__('Length: %d minutes', 'hitzordu'), (int) $booking->duration_minutes),
      '',
      __('Need to cancel? Use this link:', 'hitzordu'),
      $cancel_url,
    );

    wp_mail(
      $booking->email,
      /* translators: 1: meeting type name, 2: site name. */
      sprintf(__('Confirmed: %1$s with %2$s', 'hitzordu'), $booking->type_name, $site_name),
      implode("\n", $visitor_lines)
    );

    // To the site owner, in the site's time zone.
    $host_lines = array(
      /* translators: %s: meeting type name. */
      sprintf(__('New booking: %s', 'hitzordu'), $booking->type_name),
      '',
      /* translators: %s: date and time with time zone. */
      sprintf(__('When: %s', 'hitzordu'), $this->format_when($start, wp_timezone())),
      /* translators: 1: visitor's name, 2: visitor's email address. */
      sprintf(__('Who: %1$s (%2$s)', 'hitzordu'), $booking->name, $booking->email),
      /* translators: %s: notes left by the visitor. */
      sprintf(__('Notes: %s', 'hitzordu'), '' !== $booking->notes ? $booking->notes : '-'),
    );

    wp_mail(
      get_option('admin_email'),
      /* translators: 1: meeting type name, 2: visitor's name. */
      sprintf(__('New booking: %1$s with %2$s', 'hitzordu'), $booking->type_name, $booking->name),
      implode("\n", $host_lines)
    );
  }

  /**
   * A readable date and time in the given zone, with the zone named.
   *
   * @param DateTimeImmutable $start The instant, in UTC.
   * @param DateTimeZone      $zone  The zone to display it in.
   */
  private function format_when(DateTimeImmutable $start, DateTimeZone $zone): string
  {
    return wp_date('l j F Y, H:i', $start->getTimestamp(), $zone) . ' (' . $zone->getName() . ')';
  }
}
