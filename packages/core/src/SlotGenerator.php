<?php

declare(strict_types=1);

namespace Hitzordu\Core;

use DateTimeImmutable;
use DateTimeZone;

final class SlotGenerator
{
  /**
   * Slot start times for one day, as UTC instants.
   *
   * @param string $date      Calendar date in the host's zone, 'YYYY-MM-DD'.
   * @param string $startTime Opening time in the host's zone, 'HH:MM:SS'.
   * @param string $endTime   Closing time in the host's zone, 'HH:MM:SS'.
   *
   * @return list<DateTimeImmutable>
   */
  public function forDay(
    string $date,
    string $startTime,
    string $endTime,
    int $durationMinutes,
    DateTimeZone $hostZone
  ): array {
    $utc = new DateTimeZone('UTC');
    $cursor = new DateTimeImmutable("{$date} {$startTime}", $hostZone);
    $close = new DateTimeImmutable("{$date} {$endTime}", $hostZone);
    $slots = [];

    while ($cursor->modify("+{$durationMinutes} minutes") <= $close) {
      $slots[] = $cursor->setTimezone($utc);
      $cursor = $cursor->modify("+{$durationMinutes} minutes");
    }

    return $slots;
  }
}
