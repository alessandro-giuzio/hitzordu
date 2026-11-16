<?php
/**
 * REST endpoint: free slots for a meeting type.
 *
 * @package Hitzordu
 */

namespace Hitzordu\Plugin\Rest;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeZone;
use Hitzordu\Core\SlotGenerator;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class AvailabilityController
{

  /**
   * Register GET /hitzordu/v1/availability.
   */
  public function register_routes(): void
  {
    register_rest_route(
      'hitzordu/v1',
      '/availability',
      array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => array($this, 'get_availability'),
        'permission_callback' => '__return_true',
        'args' => array(
          'type' => array(
            'required' => true,
            'sanitize_callback' => 'sanitize_key',
          ),
          'from' => array(
            'required' => true,
            'validate_callback' => array($this, 'is_date'),
          ),
          'to' => array(
            'required' => true,
            'validate_callback' => array($this, 'is_date'),
          ),
        ),
      )
    );
  }

  /**
   * Accept only real calendar dates written as YYYY-MM-DD.
   *
   * @param mixed $value The raw request value.
   */
  public function is_date($value): bool
  {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);

    return $date && $date->format('Y-m-d') === $value;
  }

  /**
   * Build the list of free slots.
   *
   * @param WP_REST_Request $request The validated request.
   * @return WP_REST_Response|WP_Error
   */
  public function get_availability(WP_REST_Request $request)
  {
    global $wpdb;

    $prefix = $wpdb->prefix . 'hitzordu_';

    $type = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT id, duration_minutes FROM {$prefix}meeting_types WHERE slug = %s AND active = 1",
        $request['type']
      )
    );

    if (!$type) {
      return new WP_Error(
        'hitzordu_unknown_type',
        __('Unknown meeting type.', 'hitzordu'),
        array('status' => 404)
      );
    }

    $zone = wp_timezone();
    $from = new DateTimeImmutable($request['from'], $zone);
    $to = new DateTimeImmutable($request['to'], $zone);

    if ($to < $from || $from->diff($to)->days > 62) {
      return new WP_Error(
        'hitzordu_bad_range',
        __('The date range is invalid or longer than 62 days.', 'hitzordu'),
        array('status' => 400)
      );
    }

    $rules = $wpdb->get_results(
      "SELECT weekday, start_time, end_time FROM {$prefix}availability_rules"
    );

    $generator = new SlotGenerator();
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $slots = array();
    $days = new DatePeriod($from, new DateInterval('P1D'), $to->modify('+1 day'));

    foreach ($days as $day) {
      foreach ($rules as $rule) {
        // 'N' is the ISO weekday: 1 = Monday ... 7 = Sunday.
        if ((int) $rule->weekday !== (int) $day->format('N')) {
          continue;
        }

        $day_slots = $generator->forDay(
          $day->format('Y-m-d'),
          $rule->start_time,
          $rule->end_time,
          (int) $type->duration_minutes,
          $zone
        );

        foreach ($day_slots as $slot) {
          if ($slot > $now) {
            $slots[] = $slot->format('Y-m-d\TH:i:s\Z');
          }
        }
      }
    }

    $response = new WP_REST_Response(
      array(
        'now' => $now->format('Y-m-d\TH:i:s\Z'),
        'hostTimeZone' => $zone->getName(),
        'durationMinutes' => (int) $type->duration_minutes,
        'slots' => $slots,
      )
    );

    // Availability must never be served from a cache.
    $response->header('Cache-Control', 'no-store');

    return $response;
  }
}
