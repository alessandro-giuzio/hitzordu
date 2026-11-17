<?php

use Hitzordu\Core\SlotGenerator;

it('splits working hours into slots', function () {
  $slots = (new SlotGenerator())->forDay(
    '2027-01-12',
    '09:00:00',
    '11:00:00',
    30,
    new DateTimeZone('Europe/Madrid')
  );

  expect($slots)->toHaveCount(4);
  expect($slots[0]->format('c'))->toBe('2027-01-12T08:00:00+00:00');
  expect($slots[3]->format('c'))->toBe('2027-01-12T09:30:00+00:00');
});

it('drops a slot that does not fit before closing time', function () {
  $slots = (new SlotGenerator())->forDay(
    '2027-01-12',
    '09:00:00',
    '10:15:00',
    30,
    new DateTimeZone('Europe/Madrid')
  );

  expect($slots)->toHaveCount(2);
});

it('uses the summer offset in July', function () {
  $slots = (new SlotGenerator())->forDay(
    '2027-07-13',
    '09:00:00',
    '10:00:00',
    30,
    new DateTimeZone('Europe/Madrid')
  );

  expect($slots[0]->format('c'))->toBe('2027-07-13T07:00:00+00:00');
});

it('removes slots that overlap a busy period', function () {
  $generator = new SlotGenerator();
  $slots = $generator->forDay('2027-01-12', '09:00:00', '11:00:00', 30, new DateTimeZone('Europe/Madrid'));

  $busy = [
    [new DateTimeImmutable('2027-01-12T08:30:00Z'), new DateTimeImmutable('2027-01-12T09:00:00Z')],
  ];

  $free = $generator->removeBusy($slots, 30, $busy);

  expect(array_map(fn($slot) => $slot->format('H:i'), $free))
    ->toBe(['08:00', '09:00', '09:30']);
});

it('removes every slot touched by a longer busy period', function () {
  $generator = new SlotGenerator();
  $slots = $generator->forDay('2027-01-12', '09:00:00', '11:00:00', 30, new DateTimeZone('Europe/Madrid'));

  $busy = [
    [new DateTimeImmutable('2027-01-12T08:15:00Z'), new DateTimeImmutable('2027-01-12T09:15:00Z')],
  ];

  $free = $generator->removeBusy($slots, 30, $busy);

  expect(array_map(fn($slot) => $slot->format('H:i'), $free))->toBe(['09:30']);
});

it('keeps a buffer clear on both sides of a busy period', function () {
  $generator = new SlotGenerator();
  $slots = $generator->forDay('2027-01-12', '09:00:00', '12:00:00', 30, new DateTimeZone('Europe/Madrid'));

  // Busy 10:00 to 10:30 in Madrid, which is 09:00 to 09:30 UTC.
  $busy = [
    [new DateTimeImmutable('2027-01-12T09:00:00Z'), new DateTimeImmutable('2027-01-12T09:30:00Z')],
  ];

  $free = $generator->removeBusy($slots, 30, $busy, 15);

  expect(array_map(fn($slot) => $slot->format('H:i'), $free))
    ->toBe(['08:00', '10:00', '10:30']);
});

it('keeps only slots between the earliest and latest allowed times', function () {
  $generator = new SlotGenerator();
  $slots = $generator->forDay('2027-01-12', '09:00:00', '12:00:00', 30, new DateTimeZone('Europe/Madrid'));

  $kept = $generator->withinWindow(
    $slots,
    new DateTimeImmutable('2027-01-12T09:00:00Z'),
    new DateTimeImmutable('2027-01-12T10:00:00Z')
  );

  expect(array_map(fn($slot) => $slot->format('H:i'), $kept))->toBe(['09:00', '09:30']);
});
