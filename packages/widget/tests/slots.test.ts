import { describe, expect, it } from 'vitest'
import { endpoint, groupByDay, localDateKey } from '../src/lib/slots'

describe('localDateKey', () => {
  it('puts a late-evening UTC slot on the next day for a visitor further east', () => {
    expect(localDateKey('2027-01-12T23:30:00Z', 'Europe/Madrid')).toBe('2027-01-13')
    expect(localDateKey('2027-01-12T23:30:00Z', 'America/New_York')).toBe('2027-01-12')
  })
})

describe('groupByDay', () => {
  const slots = ['2027-01-12T22:30:00Z', '2027-01-12T23:30:00Z']

  it('splits slots across midnight in the visitor zone', () => {
    expect([...groupByDay(slots, 'Europe/Madrid').keys()]).toEqual(['2027-01-12', '2027-01-13'])
  })

  it('keeps them on one day where midnight has not arrived', () => {
    expect([...groupByDay(slots, 'America/New_York').keys()]).toEqual(['2027-01-12'])
  })
})

describe('endpoint', () => {
  it('appends the path with pretty permalinks', () => {
    const url = new URL(endpoint('http://example.test/wp-json/hitzordu/v1', '/availability', { type: 'a' }))
    expect(url.pathname).toBe('/wp-json/hitzordu/v1/availability')
    expect(url.searchParams.get('type')).toBe('a')
  })

  it('extends rest_route with plain permalinks', () => {
    const url = new URL(endpoint('http://example.test/?rest_route=/hitzordu/v1', '/bookings'))
    expect(url.searchParams.get('rest_route')).toBe('/hitzordu/v1/bookings')
  })
})
