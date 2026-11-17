<?php
/**
 * Counts attempts per visitor using transients.
 *
 * @package Hitzordu
 */

namespace Hitzordu\Plugin;

class RateLimiter
{

  /**
   * Record an attempt and report whether the visitor is over the limit.
   *
   * @param string $action A short name for what is being limited.
   * @param int    $limit  Attempts allowed within the window.
   * @param int    $window Window length in seconds.
   */
  public function too_many(string $action, int $limit, int $window): bool
  {
    $ip = isset($_SERVER['REMOTE_ADDR'])
      ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
      : 'unknown';

    $key = 'hitzordu_rl_' . md5($action . '|' . $ip);
    $count = (int) get_transient($key);

    if ($count >= $limit) {
      return true;
    }

    set_transient($key, $count + 1, $window);

    return false;
  }
}
