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

  /**
   * Drop slots that overlap any busy period.
   *
   * @param list<DateTimeImmutable>                                 $slots Slot starts.
   * @param list<array{0: DateTimeImmutable, 1: DateTimeImmutable}> $busy  Start and end pairs.
   *
   * @return list<DateTimeImmutable>
   */
  public function removeBusy(array $slots, int $durationMinutes, array $busy): array
  {
    $free = array_filter(
      $slots,
      function (DateTimeImmutable $slot) use ($durationMinutes, $busy): bool {
        $slotEnd = $slot->modify("+{$durationMinutes} minutes");

        foreach ($busy as [$busyStart, $busyEnd]) {
          if ($slot < $busyEnd && $slotEnd > $busyStart) {
            return false;
          }
        }

        return true;
      }
    );

    return array_values($free);
  }
}
