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
