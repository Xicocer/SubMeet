export type HallStatus = 'draft' | 'active' | 'archived'
export type EditableHallStatus = Extract<HallStatus, 'draft' | 'active'>
export type HallElementType = 'stage' | 'seat' | 'vip_seat' | 'dancefloor' | 'table'

export interface HallCanvasSize {
  width: number
  height: number
}

export interface HallLevel {
  id: string
  name: string
  order?: number | null
}

export interface HallLayoutElement {
  id: string
  type: HallElementType
  label?: string | null
  level_id?: string | null
  row?: string | null
  number?: string | null
  capacity?: number | null
  x: number
  y: number
  width?: number | null
  height?: number | null
}

export interface HallLayout {
  canvas: HallCanvasSize
  levels: HallLevel[]
  elements: HallLayoutElement[]
}

export interface HallCapacities {
  seat: number
  table?: number
  vip: number
  dancefloor: number
  total: number
}

export interface HallLayoutMeta {
  levels_count: number
  elements_count: number
  has_dancefloor: boolean
  has_stage?: boolean
  tables_count?: number
}

export interface HallSummary {
  id: number
  name: string
  address: string | null
  description: string | null
  photo_urls: string[]
  venue_owner_id: number | null
  status: HallStatus
  hourly_rate: number | string
  capacities: HallCapacities
  layout_meta: HallLayoutMeta
  created_at: string | null
  updated_at: string | null
}

export interface HallDetails extends HallSummary {
  layout: HallLayout
}

export interface HallFilters {
  status?: HallStatus
  search?: string
  page?: number
  per_page?: number
}

export interface PublicHallFilters {
  search?: string
  address?: string
  min_hourly_rate?: number
  max_hourly_rate?: number
  page?: number
  per_page?: number
}

export interface HallPayload {
  name: string
  address: string
  description: string | null
  photo_urls: string[]
  hourly_rate: number
  status: EditableHallStatus
  layout: HallLayout
}

export interface HallMutationResponse {
  message: string
  hall: HallDetails
}

export type HallRentalRequestStatus = 'pending' | 'approved' | 'rejected' | 'cancelled'

export interface HallRentalRequest {
  id: number
  hall_id: number
  event_id: number | null
  organizer_id: number
  status: HallRentalRequestStatus
  requested_start: string | null
  requested_end: string | null
  hourly_rate: number | string
  total_amount: number | string
  duration_minutes?: number | null
  organizer_message: string | null
  response_note: string | null
  responded_at: string | null
  created_at: string | null
  updated_at: string | null
  hall: HallSummary | null
}

export interface HallRentalRequestSlotPayload {
  requested_start: string
  requested_end: string
}

export interface HallRentalRequestPayload {
  hall_id: number
  event_id: number
  requested_start?: string
  requested_end?: string
  requested_slots?: HallRentalRequestSlotPayload[]
  organizer_message: string | null
}

export type HallAvailabilityDayStatus = 'free' | 'booked' | 'unavailable'

export interface HallAvailabilityDay {
  date: string
  status: HallAvailabilityDayStatus
  booked_count: number
  unavailable_count: number
}

export interface HallAvailabilityPeriod {
  id: number
  event_id?: number | null
  start: string | null
  end: string | null
  status?: string | null
  reason?: string | null
}

export interface HallAvailabilityResponse {
  hall_id: number
  from: string
  to: string
  days: HallAvailabilityDay[]
  booked_periods: HallAvailabilityPeriod[]
  unavailable_periods: HallAvailabilityPeriod[]
}

export interface HallUnavailablePeriod {
  id: number
  hall_id: number
  unavailable_start: string | null
  unavailable_end: string | null
  reason: string | null
  created_at: string | null
  updated_at: string | null
}

export interface HallUnavailablePeriodPayload {
  unavailable_start: string
  unavailable_end: string
  reason: string | null
}

export interface HallRentalRequestStatusPayload {
  status: Extract<HallRentalRequestStatus, 'approved' | 'rejected'>
  response_note: string | null
}

export interface HallCanvasDropPayload {
  mode: 'create' | 'move'
  x: number
  y: number
  type?: HallElementType
  elementId?: string
}

export interface HallCanvasMovePayload {
  elementId: string
  x: number
  y: number
}

export interface HallCanvasSelectionPayload {
  elementId: string | null
  additive?: boolean
}

export interface HallCanvasResizePayload {
  elementId: string
  x: number
  y: number
  width: number
  height: number
}
