import type { UserBooking } from '@/types/booking'
import type { EventDetails, EventSession, EventSummaryBase } from '@/types/event'

const RECENTLY_VIEWED_KEY = 'submeet.recentlyViewedEvents'
const RECENTLY_VIEWED_LIMIT = 6

export interface RecentlyViewedEvent {
  id: number
  title: string
  poster_url: string | null
  category_name: string | null
  age_rating_label: string | null
  minimum_price: number | string | null
  next_session_start: string | null
  is_teaser: boolean
}

const safeReadJson = <T>(key: string, fallback: T): T => {
  try {
    const rawValue = window.localStorage.getItem(key)
    return rawValue ? JSON.parse(rawValue) as T : fallback
  } catch {
    return fallback
  }
}

const eventUrl = (eventId: number) => `${window.location.origin}/events/${eventId}`

export const getRecentlyViewedEvents = () => {
  return safeReadJson<RecentlyViewedEvent[]>(RECENTLY_VIEWED_KEY, [])
}

export const saveRecentlyViewedEvent = (event: EventSummaryBase) => {
  const viewedEvent: RecentlyViewedEvent = {
    id: event.id,
    title: event.title,
    poster_url: event.poster_url,
    category_name: event.category?.name ?? null,
    age_rating_label: event.age_rating?.label ?? null,
    minimum_price: event.minimum_price ?? event.next_session?.base_price ?? null,
    next_session_start: event.next_session?.start_time ?? null,
    is_teaser: event.is_teaser,
  }

  const currentEvents = getRecentlyViewedEvents()
  const nextEvents = [
    viewedEvent,
    ...currentEvents.filter((item) => item.id !== viewedEvent.id),
  ].slice(0, RECENTLY_VIEWED_LIMIT)

  window.localStorage.setItem(RECENTLY_VIEWED_KEY, JSON.stringify(nextEvents))
}

export const shareEvent = async (event: Pick<EventDetails | EventSummaryBase | RecentlyViewedEvent, 'id' | 'title'> & {
  description?: string | null
}) => {
  const url = eventUrl(event.id)

  if (navigator.share) {
    try {
      await navigator.share({
        title: event.title,
        text: event.description ?? 'Посмотри событие на SubMeet',
        url,
      })
    } catch (shareError) {
      if (shareError instanceof DOMException && shareError.name === 'AbortError') {
        return 'cancelled'
      }

      throw shareError
    }

    return 'shared'
  }

  await navigator.clipboard.writeText(url)
  return 'copied'
}

const escapeIcsText = (value: string) => {
  return value
    .replace(/\\/g, '\\\\')
    .replace(/\n/g, '\\n')
    .replace(/,/g, '\\,')
    .replace(/;/g, '\\;')
}

const formatIcsDate = (date: Date) => {
  const year = date.getUTCFullYear()
  const month = `${date.getUTCMonth() + 1}`.padStart(2, '0')
  const day = `${date.getUTCDate()}`.padStart(2, '0')
  const hours = `${date.getUTCHours()}`.padStart(2, '0')
  const minutes = `${date.getUTCMinutes()}`.padStart(2, '0')
  const seconds = `${date.getUTCSeconds()}`.padStart(2, '0')

  return `${year}${month}${day}T${hours}${minutes}${seconds}Z`
}

const downloadIcs = (params: {
  id: string
  title: string
  description?: string | null
  location?: string | null
  start: string | null | undefined
  end?: string | null
  url?: string | null
}) => {
  if (!params.start) {
    return false
  }

  const startDate = new Date(params.start)

  if (Number.isNaN(startDate.getTime())) {
    return false
  }

  const endDate = params.end ? new Date(params.end) : new Date(startDate.getTime() + 2 * 60 * 60 * 1000)
  const validEndDate = Number.isNaN(endDate.getTime())
    ? new Date(startDate.getTime() + 2 * 60 * 60 * 1000)
    : endDate

  const lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//SubMeet//Event Calendar//RU',
    'BEGIN:VEVENT',
    `UID:${params.id}@submeet.local`,
    `DTSTAMP:${formatIcsDate(new Date())}`,
    `DTSTART:${formatIcsDate(startDate)}`,
    `DTEND:${formatIcsDate(validEndDate)}`,
    `SUMMARY:${escapeIcsText(params.title)}`,
    params.description ? `DESCRIPTION:${escapeIcsText(params.description)}` : null,
    params.location ? `LOCATION:${escapeIcsText(params.location)}` : null,
    params.url ? `URL:${params.url}` : null,
    'END:VEVENT',
    'END:VCALENDAR',
  ].filter((line): line is string => line !== null)

  const blob = new Blob([lines.join('\r\n')], { type: 'text/calendar;charset=utf-8' })
  const objectUrl = window.URL.createObjectURL(blob)
  const link = document.createElement('a')

  link.href = objectUrl
  link.download = `submeet-event-${params.id}.ics`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  window.URL.revokeObjectURL(objectUrl)

  return true
}

export const downloadSessionCalendarFile = (event: EventDetails | EventSummaryBase, session: EventSession) => {
  return downloadIcs({
    id: `${event.id}-${session.id}`,
    title: event.title,
    description: event.description,
    location: session.hall?.address || session.hall?.name || null,
    start: session.start_time,
    end: session.end_time,
    url: eventUrl(event.id),
  })
}

export const downloadBookingCalendarFile = (booking: UserBooking) => {
  if (!booking.session) {
    return false
  }

  return downloadIcs({
    id: `booking-${booking.id}`,
    title: booking.session.event_title,
    description: `Бронирование SubMeet: ${booking.items.map((item) => item.label).join(', ')}`,
    location: booking.session.hall_address || booking.session.hall_name,
    start: booking.session.start_time,
    end: booking.session.end_time,
    url: eventUrl(booking.session.event_id),
  })
}
