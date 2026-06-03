import type { HallCapacities, HallLayoutMeta } from '@/types/hall'

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

export interface EventTag {
  id: number
  name: string
  slug: string
  system?: boolean
}

export interface EventOrganizer {
  id: number | null
  full_name: string | null
  company_name: string | null
  display_name: string | null
  email: string | null
}

export interface EventSummaryBase {
  id: number
  title: string
  description?: string | null
  poster_url: string | null
  category: Category | null
  age_rating: AgeRating | null
  tags: EventTag[]
  organizer: EventOrganizer | null
  is_wanted: boolean
  is_teaser: boolean
  has_available_sessions: boolean
  teaser_reason: string | null
  available_sessions_count?: number | null
  minimum_price?: number | string | null
  next_session?: EventSession | null
  created_at?: string | null
}

export interface PublicEvent extends EventSummaryBase {}

export interface EventTentativeDate {
  id: number | null
  status: 'pending' | 'approved' | string | null
  requested_start: string | null
  requested_end: string | null
  hall?: {
    id: number | null
    name: string | null
    address: string | null
  } | null
}

export interface EventDetails extends EventSummaryBase {
  description: string | null
  organizer_id: number
  status: OrganizerEventStatus
  moderation_note?: string | null
  moderated_at?: string | null
  tentative_dates?: EventTentativeDate[]
  created_at: string | null
  updated_at: string | null
}

export interface EventSessionHallSummary {
  id: number | null
  name: string | null
  address: string | null
  description: string | null
  photo_urls?: string[]
  venue_owner_id: number | null
  status: string | null
  hourly_rate?: number | string | null
  capacities: HallCapacities | null
  layout_meta: HallLayoutMeta | null
}

export interface EventSession {
  id: number
  event_id: number
  hall_id: number
  hall_rental_request_id?: number | null
  hall?: EventSessionHallSummary | null
  start_time: string | null
  end_time: string | null
  base_price: number | string
  status: OrganizerSessionStatus
  created_at?: string | null
  updated_at?: string | null
}

export interface WantToGoEvent extends EventSummaryBase {
  description: string | null
  wanted_at: string | null
  minimum_price: number | string | null
  next_session: EventSession | null
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
  tag?: string
  age?: number
  sort?: EventSort
  page?: number
  per_page?: number
}

export type OrganizerEventStatus = 'draft' | 'pending_review' | 'published' | 'cancelled' | 'archived'

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
  tags: string[]
  status: Extract<OrganizerEventStatus, 'draft' | 'pending_review' | 'published'>
}

export interface ChangeOrganizerEventStatusPayload {
  status: Extract<OrganizerEventStatus, 'cancelled' | 'archived'>
}

export interface OrganizerEventMutationResponse {
  message: string
  event: OrganizerEvent
}

export interface OrganizerEventCopywriterPayload {
  title: string
  description: string | null
  category_id: number | null
  age_rating_id: number | null
  tags: string[]
}

export interface OrganizerEventCopywriterResponse {
  message: string
  description: string
  tips: string[]
  mode: 'ai' | 'fallback'
}

export interface OrganizerEventTagSuggestionPayload {
  title: string | null
  description: string | null
  category_id: number | null
  category_name?: string | null
  age_rating_id: number | null
  age_rating_label?: string | null
  already_selected_tags: string[]
}

export interface OrganizerEventTagSuggestion {
  name: string
  exists: boolean
}

export interface OrganizerEventTagSuggestionResponse {
  message: string
  tags: OrganizerEventTagSuggestion[]
  mode: 'ai' | 'fallback'
}

export type OrganizerSessionStatus = 'scheduled' | 'cancelled' | 'completed'

export interface OrganizerSessionPayload {
  hall_rental_request_id: number
  base_price: number
}

export interface OrganizerSessionMutationResponse {
  message: string
  session: EventSession
}

export interface WantToGoMutationResponse {
  message: string
  event_id: number
  is_wanted: boolean
}
