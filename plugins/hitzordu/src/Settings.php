<?php
/**
 * Reads the plugin's settings, with defaults.
 *
 * @package Hitzordu
 */

namespace Hitzordu\Plugin;

class Settings
{

  /**
   * The single option that holds every setting, as an array.
   */
  const OPTION = 'hitzordu_settings';

  /**
   * All settings, with defaults filled in for anything not saved yet.
   */
  public static function all(): array
  {
    $saved = get_option(self::OPTION, array());

    return wp_parse_args(
      is_array($saved) ? $saved : array(),
      array(
        'notification_email' => '',
        'delete_data' => false,
      )
    );
  }

  /**
   * Where new booking emails go: the saved address, or the site admin email.
   */
  public static function notification_email(): string
  {
    $email = self::all()['notification_email'];

    return is_email($email) ? $email : (string) get_option('admin_email');
  }
}
