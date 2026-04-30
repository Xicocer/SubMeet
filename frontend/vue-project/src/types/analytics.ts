import type { BookingFlowType, BookingStatus } from '@/types/booking'
import type { OrganizerEventStatus, OrganizerSessionStatus } from '@/types/event'
import type { HallStatus } from '@/types/hall'

export interface OrganizerEventDashboardMetrics {
  events_total: number
  events_published: number
  events_draft: number
  events_cancelled: number
  events_archived: number
  sessions_total: number
  sessions_upcoming: number
  sessions_scheduled: number
  sessions_cancelled: number
  sessions_completed: number
}

export interface OrganizerDashboardUpcomingSession {
  id: number
  event_id: number
  event_title: string | null
  hall_id: number
  status: OrganizerSessionStatus
  start_time: string | null
  end_time: string | null
  base_price: number | string
}

export interface OrganizerDashboardRecentEvent {
  id: number
  title: string
  status: OrganizerEventStatus
  created_at: string | null
  updated_at: string | null
}

export interface OrganizerEventDashboardResponse {
  metrics: OrganizerEventDashboardMetrics
  upcoming_sessions: OrganizerDashboardUpcomingSession[]
  recent_events: OrganizerDashboardRecentEvent[]
}

export interface OrganizerHallDashboardMetrics {
  halls_total: number
  halls_active: number
  halls_draft: number
  halls_archived: number
  capacity_total: number
  capacity_largest: number
  capacity_average: number
}

export interface OrganizerDashboardRecentHall {
  id: number
  name: string
  address: string | null
  status: HallStatus
  total_capacity: number
  updated_at: string | null
}

export interface OrganizerHallDashboardResponse {
  metrics: OrganizerHallDashboardMetrics
  recent_halls: OrganizerDashboardRecentHall[]
}

export interface OrganizerBookingDashboardMetrics {
  bookings_total: number
  bookings_confirmed: number
  bookings_reserved: number
  bookings_payment_pending: number
  bookings_cancelled: number
  bookings_expired: number
  tickets_sold: number
  tickets_used: number
  revenue_total: number | string
  revenue_last_30_days: number | string
  average_order_value: number | string
  active_reservations: number
}

export interface OrganizerDashboardRecentBooking {
  id: number
  status: BookingStatus
  flow_type: BookingFlowType
  event_title: string | null
  hall_name: string | null
  tickets_count: number
  total_amount: number | string
  confirmed_at: string | null
  created_at: string | null
}

export interface OrganizerDashboardRecentCheckIn {
  booking_id: number
  event_title: string | null
  hall_name: string | null
  ticket_code: string | null
  tickets_used: number
  used_at: string | null
}

export interface OrganizerDashboardTopEvent {
  event_id: number | null
  event_title: string | null
  bookings_confirmed: number
  tickets_sold: number
  revenue: number | string
}

export interface OrganizerDashboardSalesDay {
  date: string
  bookings_confirmed: number
  tickets_sold: number
  revenue: number | string
}

export interface OrganizerBookingDashboardResponse {
  metrics: OrganizerBookingDashboardMetrics
  recent_bookings: OrganizerDashboardRecentBooking[]
  recent_check_ins: OrganizerDashboardRecentCheckIn[]
  top_events: OrganizerDashboardTopEvent[]
  sales_last_7_days: OrganizerDashboardSalesDay[]
}
