<?php
/**
 * REST endpoint: create a booking.
 *
 * @package Hitzordu
 */

namespace Hitzordu\Plugin\Rest;

use DateTimeImmutable;
use DateTimeZone;
use Hitzordu\Core\SlotGenerator;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class BookingsController
{

  /**
   * Register POST /hitzordu/v1/bookings.
   */
  public function register_routes(): void
  {
    register_rest_route(
      'hitzordu/v1',
      '/bookings',
      array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => array($this, 'create_booking'),
        'permission_callback' => '__return_true',
        'args' => array(
          'type' => array(
            'required' => true,
            'sanitize_callback' => 'sanitize_key',
          ),
          'start' => array(
            'required' => true,
            'validate_callback' => array($this, 'is_utc_instant'),
          ),
          'name' => array(
            'required' => true,
            'validate_callback' => array($this, 'is_short_text'),
            'sanitize_callback' => 'sanitize_text_field',
          ),
          'email' => array(
            'required' => true,
            'validate_callback' => array($this, 'is_valid_email'),
            'sanitize_callback' => 'sanitize_email',
          ),
          'notes' => array(
            'default' => '',
            'sanitize_callback' => 'sanitize_textarea_field',
          ),
          'timezone' => array(
            'required' => true,
            'validate_callback' => array($this, 'is_timezone'),
          ),
          'locale' => array(
            'default' => 'en',
            'sanitize_callback' => 'sanitize_text_field',
          ),
          'idempotencyKey' => array(
            'required' => true,
            'validate_callback' => array($this, 'is_request_key'),
            'sanitize_callback' => 'sanitize_key',
          ),
        ),
      )
    );
  }

  /**
   * A UTC instant written exactly as 2027-01-12T08:00:00Z.
   *
   * @param mixed $value Raw value.
   */
  public function is_utc_instant($value): bool
  {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', (string) $value, new DateTimeZone('UTC'));

    return $date && $date->format('Y-m-d\TH:i:s\Z') === $value;
  }

  /**
   * Non-empty text that fits the varchar(190) column.
   *
   * @param mixed $value Raw value.
   */
  public function is_short_text($value): bool
  {
    return is_string($value) && '' !== trim($value) && mb_strlen($value) <= 190;
  }

  /**
   * A syntactically valid email address.
   *
   * @param mixed $value Raw value.
   */
  public function is_valid_email($value): bool
  {
    return is_string($value) && false !== is_email($value);
  }

  /**
   * A real IANA time zone name, such as Europe/Madrid.
   *
   * @param mixed $value Raw value.
   */
  public function is_timezone($value): bool
  {
    return in_array($value, timezone_identifiers_list(), true);
  }

  /**
   * A client-generated key, 8 to 64 safe characters.
   *
   * @param mixed $value Raw value.
   */
  public function is_request_key($value): bool
  {
    return is_string($value) && 1 === preg_match('/^[a-z0-9_-]{8,64}$/i', $value);
  }

  /**
   * Create the booking if the slot is real and still free.
   *
   * @param WP_REST_Request $request The validated request.
   * @return WP_REST_Response|WP_Error
   */
  public function create_booking(WP_REST_Request $request)
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
      return new WP_Error('hitzordu_unknown_type', __('Unknown meeting type.', 'hitzordu'), array('status' => 404));
    }

    // A repeated request (double click, retry) returns the booking already made.
    $existing = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT id FROM {$prefix}bookings WHERE idempotency_key = %s",
        $request['idempotencyKey']
      )
    );

    if ($existing) {
      return new WP_REST_Response(
        array(
          'status' => 'confirmed',
          'id' => (int) $existing,
        ),
        200
      );
    }

    $duration = (int) $type->duration_minutes;
    $start = new DateTimeImmutable($request['start'], new DateTimeZone('UTC'));
    $end = $start->modify("+{$duration} minutes");

    // The client may only book a slot the server would have offered.
    if (!$this->is_offered_slot($start, $duration)) {
      return new WP_Error('hitzordu_not_a_slot', __('That time is not available.', 'hitzordu'), array('status' => 400));
    }

    // One booking at a time: simultaneous requests wait here in line.
    $lock = 'hitzordu_' . md5(DB_NAME . $wpdb->prefix);

    if ('1' !== (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock))) {
      return new WP_Error('hitzordu_busy', __('Please try again in a moment.', 'hitzordu'), array('status' => 503));
    }

    $overlaps = (int) $wpdb->get_var(
      $wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}bookings WHERE status = 'confirmed' AND starts_at < %s AND ends_at > %s",
        $end->format('Y-m-d H:i:s'),
        $start->format('Y-m-d H:i:s')
      )
    );

    $booking_id = 0;

    if (0 === $overlaps) {
      $inserted = $wpdb->insert(
        "{$prefix}bookings",
        array(
          'meeting_type_id' => (int) $type->id,
          'starts_at' => $start->format('Y-m-d H:i:s'),
          'ends_at' => $end->format('Y-m-d H:i:s'),
          'name' => $request['name'],
          'email' => $request['email'],
          'notes' => $request['notes'],
          'timezone' => $request['timezone'],
          'locale' => $request['locale'],
          'status' => 'confirmed',
          'cancel_token' => bin2hex(random_bytes(32)),
          'idempotency_key' => $request['idempotencyKey'],
          'created_at' => gmdate('Y-m-d H:i:s'),
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
      );

      if ($inserted) {
        $booking_id = (int) $wpdb->insert_id;
      }
    }

    $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));

    if ($overlaps > 0) {
      return new WP_REST_Response(array('status' => 'conflict'), 409);
    }

    if (0 === $booking_id) {
      return new WP_Error('hitzordu_not_saved', __('The booking could not be saved.', 'hitzordu'), array('status' => 500));
    }

    return new WP_REST_Response(
      array(
        'status' => 'confirmed',
        'id' => $booking_id,
      ),
      201
    );
  }

  /**
   * Whether the instant is a future slot generated from the working hours.
   *
   * @param DateTimeImmutable $start    Requested start, in UTC.
   * @param int               $duration Meeting length in minutes.
   */
  private function is_offered_slot(DateTimeImmutable $start, int $duration): bool
  {
    global $wpdb;

    if ($start <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
      return false;
    }

    $zone = wp_timezone();
    $local = $start->setTimezone($zone);

    $rules = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT start_time, end_time FROM {$wpdb->prefix}hitzordu_availability_rules WHERE weekday = %d",
        (int) $local->format('N')
      )
    );

    $generator = new SlotGenerator();

    foreach ($rules as $rule) {
      $slots = $generator->forDay($local->format('Y-m-d'), $rule->start_time, $rule->end_time, $duration, $zone);

      foreach ($slots as $slot) {
        if ($slot->getTimestamp() === $start->getTimestamp()) {
          return true;
        }
      }
    }

    return false;
  }
}
