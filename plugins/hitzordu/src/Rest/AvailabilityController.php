<?php
/**
 * REST endpoint: free slots for a meeting type.
 *
 * @package Hitzordu
 */

namespace Hitzordu\Plugin\Rest;

use DateTimeImmutable;
use DateTimeZone;
use Hitzordu\Plugin\Availability;
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
        "SELECT id, duration_minutes, buffer_minutes, min_notice_minutes, window_days FROM {$prefix}meeting_types WHERE slug = %s AND active = 1",
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

    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $slots = array();
    $free = (new Availability())->free_slots($type, $from, $to);

    foreach ($free as $slot) {
      $slots[] = $slot->format('Y-m-d\TH:i:s\Z');
    }

    $response = new WP_REST_Response(
      array(
        'now' => $now->format('Y-m-d\TH:i:s\Z'),
        'hostTimeZone' => $zone->getName(),
        'durationMinutes' => (int) $type->duration_minutes,
        'bufferMinutes' => (int) $type->buffer_minutes,
        'minNoticeMinutes' => (int) $type->min_notice_minutes,
        'windowDays' => (int) $type->window_days,
        'slots' => $slots,
      )
    );

    // Availability must never be served from a cache.
    $response->header('Cache-Control', 'no-store');

    return $response;
  }
}
