import type { HallLayout, HallLayoutElement } from '@/types/hall'

export type BookingSeatState = 'free' | 'reserved' | 'booked'
export type BookingStatus = 'reserved' | 'payment_pending' | 'confirmed' | 'cancelled' | 'expired'
export type BookingFlowType = 'reservation' | 'purchase'
export type BookingItemType = 'seat' | 'standing'
export type PaymentStatus = 'pending' | 'paid' | 'cancelled' | 'failed'
export type TicketVerificationStatus = 'validated' | 'already_used' | 'invalid' | 'not_found'

export interface BookingLayoutElement extends HallLayoutElement {
  booking_state?: BookingSeatState
  price?: number | string | null
  capacity_total?: number | null
  capacity_available?: number | null
}

export interface BookingHallLayout extends Omit<HallLayout, 'elements'> {
  elements: BookingLayoutElement[]
}

export interface BookingSessionSummary {
  event_session_id: number
  event_id: number
  event_title: string
  category: {
    name: string | null
    slug: string | null
  }
  age_rating: {
    label: string | null
    min_age: number
  }
  hall_id: number
  hall_name: string
  hall_address: string | null
  base_price: number | string
  currency: string
  status: string
  start_time: string | null
  end_time: string | null
}

export interface BookingAvailabilitySummary {
  seats_total: number
  seats_free: number
  seats_reserved: number
  seats_booked: number
  standing_total: number
  standing_available: number
}

export interface SessionAvailabilityResponse {
  session: BookingSessionSummary
  layout: BookingHallLayout
  summary: BookingAvailabilitySummary
}

export interface BookingPayload {
  session_id: number
  seat_ids?: string[]
  standing?: Array<{
    element_id: string
    quantity: number
  }>
  loyalty_points_to_spend?: number
  customer_email?: string
  guest_birth_date?: string
}

export interface BookingItem {
  id: number
  type: BookingItemType
  element_id: string
  label: string
  quantity: number
  unit_price: number | string
  total_price: number | string
  meta: Record<string, unknown> | null
}

export interface BookingPayment {
  id: number
  provider: string
  status: PaymentStatus
  amount: number | string
  currency: string
  external_reference: string | null
  confirmation_url: string | null
  failure_reason: string | null
  paid_at: string | null
  cancelled_at: string | null
  last_synced_at: string | null
  payload: Record<string, unknown> | null
  created_at: string | null
  updated_at: string | null
}

export interface BookingTicketDocument {
  code: string | null
  issued_at: string | null
  used_at: string | null
  used_by_organizer_id: number | null
  download_url: string
}

export interface UserBooking {
  id: number
  user_id: number | null
  customer_email: string | null
  is_guest: boolean
  guest_access_token?: string
  status: BookingStatus
  flow_type: BookingFlowType
  subtotal_amount: number | string
  discount_amount: number | string
  loyalty_points_spent: number
  loyalty_points_earned: number
  loyalty_points_awarded_at: string | null
  total_amount: number | string
  currency: string
  reserved_until: string | null
  confirmed_at: string | null
  ticket_issued_at: string | null
  ticket_sent_at: string | null
  ticket_used_at: string | null
  ticket_used_by_organizer_id: number | null
  cancelled_at: string | null
  can_pay: boolean
  can_cancel: boolean
  session: BookingSessionSummary | null
  items: BookingItem[]
  payment: BookingPayment | null
  ticket: BookingTicketDocument | null
  created_at: string | null
  updated_at: string | null
}

export interface BookingMutationResponse {
  message: string
  checkout_required?: boolean
  booking: UserBooking
}

export interface LoyaltyAccountResponse {
  balance: number
  earned_total: number
  spent_total: number
  earn_percent: number
  max_discount_percent: number
}

export interface TicketVerificationResponse {
  status: TicketVerificationStatus
  message: string
  booking: UserBooking | null
}
