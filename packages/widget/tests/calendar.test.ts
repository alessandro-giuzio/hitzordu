import { describe, expect, it } from 'vitest';
import {
  addDays,
  addMonths,
  monthGrid,
  moveByKey,
  weekStartForLocale,
} from '../src/lib/calendar';

const MONDAY = 1;

describe('date arithmetic', () => {
  it('crosses month ends and the spring clock change', () => {
    expect(addDays('2027-01-31', 1)).toBe('2027-02-01');
    expect(addDays('2027-03-28', 1)).toBe('2027-03-29');
  });

  it('clamps to the last day of a shorter month', () => {
    expect(addMonths('2027-01-31', 1)).toBe('2027-02-28');
    expect(addMonths('2028-01-31', 1)).toBe('2028-02-29');
  });
});

describe('monthGrid', () => {
  it('fits February 2027 into four Monday-first weeks', () => {
    const weeks = monthGrid('2027-02-01', MONDAY);
    expect(weeks).toHaveLength(4);
    expect(weeks[0][0]).toBe('2027-02-01');
    expect(weeks[3][6]).toBe('2027-02-28');
  });

  it('pads the first week when weeks start on Sunday', () => {
    const weeks = monthGrid('2027-02-01', 0);
    expect(weeks).toHaveLength(5);
    expect(weeks[0][0]).toBeNull();
    expect(weeks[0][1]).toBe('2027-02-01');
  });
});

describe('moveByKey', () => {
  it('moves by day and by week', () => {
    expect(moveByKey('2027-01-31', 'ArrowRight', false, MONDAY)).toBe(
      '2027-02-01',
    );
    expect(moveByKey('2027-01-13', 'ArrowUp', false, MONDAY)).toBe(
      '2027-01-06',
    );
  });

  it('jumps to the start and end of the week', () => {
    expect(moveByKey('2027-01-13', 'Home', false, MONDAY)).toBe('2027-01-11');
    expect(moveByKey('2027-01-13', 'End', false, MONDAY)).toBe('2027-01-17');
  });

  it('moves by month, and by year with Shift', () => {
    expect(moveByKey('2027-01-31', 'PageDown', false, MONDAY)).toBe(
      '2027-02-28',
    );
    expect(moveByKey('2027-02-15', 'PageUp', true, MONDAY)).toBe('2026-02-15');
  });

  it('ignores keys the grid does not handle', () => {
    expect(moveByKey('2027-01-13', 'a', false, MONDAY)).toBeNull();
  });
});

describe('weekStartForLocale', () => {
  it('starts the week on Sunday in the US and Monday in Spain', () => {
    expect(weekStartForLocale('en-US')).toBe(0);
    expect(weekStartForLocale('es-ES')).toBe(1);
  });
});
