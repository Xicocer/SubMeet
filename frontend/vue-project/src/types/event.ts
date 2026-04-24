export interface Category {
  id: number
  name: string
  slug: string
}

export interface AgeRating {
  id: number
  label: string
  min_age: number
}

export interface EventOrganizer {
  id: number | null
  full_name: string | null
  email: string | null
}

export interface EventSummaryBase {
  id: number
  title: string
  poster_url: string | null
  category: Category | null
  age_rating: AgeRating | null
  organizer: EventOrganizer | null
}

export interface PublicEvent extends EventSummaryBase {}

export interface EventDetails extends EventSummaryBase {
  description: string | null
  organizer_id: number
  status: OrganizerEventStatus
  created_at: string | null
  updated_at: string | null
}

export interface EventSession {
  id: number
  event_id: number
  hall_id: number
  start_time: string | null
  end_time: string | null
  base_price: number | string
  status: OrganizerSessionStatus
  created_at?: string | null
  updated_at?: string | null
}

export interface PaginatedResponse<T> {
  current_page: number
  data: T[]
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export type EventSort = 'newest' | 'oldest' | 'title_asc' | 'title_desc'

export interface EventListFilters {
  search?: string
  category?: string
  age?: number
  sort?: EventSort
  page?: number
  per_page?: number
}

export type OrganizerEventStatus = 'draft' | 'published' | 'cancelled' | 'archived'

export interface OrganizerEvent extends EventDetails {}

export interface OrganizerEventsFilters {
  status?: OrganizerEventStatus
  page?: number
  per_page?: number
}

export interface OrganizerEventPayload {
  title: string
  description: string | null
  poster_url: string | null
  category_id: number
  age_rating_id: number
  status: Extract<OrganizerEventStatus, 'draft' | 'published'>
}

export interface ChangeOrganizerEventStatusPayload {
  status: Extract<OrganizerEventStatus, 'cancelled' | 'archived'>
}

export interface OrganizerEventMutationResponse {
  message: string
  event: OrganizerEvent
}

export type OrganizerSessionStatus = 'scheduled' | 'cancelled' | 'completed'

export interface OrganizerSessionPayload {
  hall_id: number
  start_time: string
  end_time: string
  base_price: number
}

export interface OrganizerSessionMutationResponse {
  message: string
  session: EventSession
}
