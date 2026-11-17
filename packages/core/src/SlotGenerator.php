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
  /**
   * Drop slots that overlap any busy period, keeping a buffer clear on both sides.
   *
   * @param list<DateTimeImmutable>                                 $slots Slot starts.
   * @param list<array{0: DateTimeImmutable, 1: DateTimeImmutable}> $busy  Start and end pairs.
   *
   * @return list<DateTimeImmutable>
   */
  public function removeBusy(array $slots, int $durationMinutes, array $busy, int $bufferMinutes = 0): array
  {
    $free = array_filter(
      $slots,
      function (DateTimeImmutable $slot) use ($durationMinutes, $busy, $bufferMinutes): bool {
        $slotEnd = $slot->modify("+{$durationMinutes} minutes");

        foreach ($busy as [$busyStart, $busyEnd]) {
          $blockedFrom = $busyStart->modify("-{$bufferMinutes} minutes");
          $blockedUntil = $busyEnd->modify("+{$bufferMinutes} minutes");

          if ($slot < $blockedUntil && $slotEnd > $blockedFrom) {
            return false;
          }
        }

        return true;
      }
    );

    return array_values($free);
  }

  /**
   * Keep slots that start at or after $earliest and before $latest.
   *
   * @param list<DateTimeImmutable> $slots Slot starts.
   *
   * @return list<DateTimeImmutable>
   */
  public function withinWindow(array $slots, DateTimeImmutable $earliest, DateTimeImmutable $latest): array
  {
    return array_values(
      array_filter(
        $slots,
        fn(DateTimeImmutable $slot): bool => $slot >= $earliest && $slot < $latest
      )
    );
  }
}
