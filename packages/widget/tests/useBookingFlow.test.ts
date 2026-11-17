import { describe, expect, it, vi } from 'vitest'
import { useBookingFlow } from '../src/useBookingFlow'

const API = 'http://example.test/wp-json/hitzordu/v1'
const SLOTS = ['2027-01-12T08:00:00Z', '2027-01-12T08:30:00Z', '2027-01-13T08:00:00Z']

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

function setup(...responses: Response[]) {
  const fetchFn = vi.fn<typeof fetch>()
  for (const response of responses) fetchFn.mockResolvedValueOnce(response)

  const flow = useBookingFlow({
    apiUrl: API,
    meetingType: 'discovery-call',
    timeZone: 'Europe/Madrid',
    locale: 'es-ES',
    fetchFn,
    now: () => new Date('2027-01-11T12:00:00Z'),
  })

  return { flow, fetchFn }
}

async function readyToSubmit(flow: ReturnType<typeof useBookingFlow>) {
  await flow.load()
  flow.chooseDay('2027-01-12')
  flow.chooseSlot(SLOTS[0])
  flow.name.value = 'Ana'
  flow.email.value = 'ana@example.com'
}

function sentBody(fetchFn: ReturnType<typeof vi.fn<typeof fetch>>, call: number) {
  return JSON.parse(String(fetchFn.mock.calls[call][1]?.body))
}

describe('useBookingFlow', () => {
  it('asks for two weeks of availability and groups the slots by day', async () => {
    const { flow, fetchFn } = setup(json({ slots: SLOTS }))
    await flow.load()

    const url = new URL(String(fetchFn.mock.calls[0][0]))
    expect(url.searchParams.get('from')).toBe('2027-01-11')
    expect(url.searchParams.get('to')).toBe('2027-01-25')
    expect([...flow.days.value.keys()]).toEqual(['2027-01-12', '2027-01-13'])
  })

  it('confirms a booking and sends the visitor details', async () => {
    const { flow, fetchFn } = setup(json({ slots: SLOTS }), json({ status: 'confirmed', id: 7 }, 201))
    await readyToSubmit(flow)
    await flow.submit()

    expect(flow.step.value).toBe('confirmed')
    expect(String(fetchFn.mock.calls[1][0])).toBe(`${API}/bookings`)
    expect(sentBody(fetchFn, 1)).toMatchObject({
      type: 'discovery-call',
      start: SLOTS[0],
      name: 'Ana',
      email: 'ana@example.com',
      timezone: 'Europe/Madrid',
      locale: 'es-ES',
    })
  })

  it('reuses the same idempotency key when a failed submit is retried', async () => {
    const { flow, fetchFn } = setup(
      json({ slots: SLOTS }),
      json({ message: 'Please try again in a moment.' }, 503),
      json({ status: 'confirmed', id: 7 }, 201),
    )
    await readyToSubmit(flow)

    await flow.submit()
    expect(flow.step.value).toBe('details')
    expect(flow.message.value).toBe('Please try again in a moment.')

    await flow.submit()
    expect(flow.step.value).toBe('confirmed')
    expect(sentBody(fetchFn, 2).idempotencyKey).toBe(sentBody(fetchFn, 1).idempotencyKey)
    expect(sentBody(fetchFn, 1).idempotencyKey).toMatch(/^[a-f0-9]{32}$/)
  })

  it('reloads and returns to the days when the slot was just taken', async () => {
    const { flow, fetchFn } = setup(
      json({ slots: SLOTS }),
      json({ status: 'conflict' }, 409),
      json({ slots: SLOTS.slice(1) }),
    )
    await readyToSubmit(flow)
    await flow.submit()

    expect(flow.step.value).toBe('pickDate')
    expect(flow.message.value).toContain('just taken')
    expect(fetchFn).toHaveBeenCalledTimes(3)
    expect(flow.days.value.get('2027-01-12')).toEqual([SLOTS[1]])
  })

  it('steps back from details to times to days', async () => {
    const { flow } = setup(json({ slots: SLOTS }))
    await readyToSubmit(flow)

    flow.back()
    expect(flow.step.value).toBe('pickTime')
    flow.back()
    expect(flow.step.value).toBe('pickDate')
  })
})
