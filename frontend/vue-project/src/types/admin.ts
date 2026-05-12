import type { AgeRating, Category, EventTag, PaginatedResponse } from '@/types/event'

export type AdminOrganizerModerationStatus = 'pending' | 'approved' | 'rejected' | 'blocked'

export interface AdminOrganizerSummary {
  user_id: number
  company_name: string
  moderation_status: AdminOrganizerModerationStatus
  moderation_note: string | null
  moderated_at: string | null
  created_at: string | null
  updated_at: string | null
  user: {
    id: number | null
    full_name: string | null
    email: string | null
    phone: string | null
    status: number | null
    role: string | null
  }
}

export interface AdminOrganizerModerationResponse {
  message: string
  organizer: AdminOrganizerSummary
}

export type AdminEventStatus =
  | 'draft'
  | 'pending_review'
  | 'published'
  | 'cancelled'
  | 'archived'

export type AdminEventDecision = 'approve' | 'needs_revision' | 'unpublish'

export interface AdminEventCategorySummary {
  id: number | null
  name: string | null
  slug: string | null
}

export interface AdminEventAgeRatingSummary {
  id: number | null
  label: string | null
  min_age: number | null
}

export interface AdminEventOrganizerSummary {
  id: number | null
  full_name: string | null
  company_name: string | null
  display_name: string | null
  email: string | null
}

export interface AdminEventSummary {
  id: number
  title: string
  description: string | null
  poster_url: string | null
  status: AdminEventStatus
  moderation_note: string | null
  moderated_at: string | null
  created_at: string | null
  updated_at: string | null
  category: AdminEventCategorySummary | null
  age_rating: AdminEventAgeRatingSummary | null
  organizer: AdminEventOrganizerSummary | null
  tags: EventTag[]
}

export interface AdminEventModerationResponse {
  message: string
  event: AdminEventSummary
}

export interface AdminDashboardMetrics {
  users_total: number
  organizers_total: number
  organizers_pending: number
  organizers_approved: number
  organizers_rejected: number
  organizers_blocked: number
  accounts_blocked_total: number
  events_total: number
  events_draft: number
  events_pending_review: number
  events_published: number
  events_cancelled: number
  events_archived: number
  sessions_total: number
  sessions_future: number
  bookings_total: number
  bookings_confirmed: number
  bookings_reserved: number
  bookings_payment_pending: number
  bookings_cancelled: number
  bookings_expired: number
  tickets_sold: number
  revenue_total: number | string
  payments_total: number
  payments_pending: number
  payments_paid: number
  payments_failed: number
  payments_cancelled: number
  problem_cases_total: number
}

export interface AdminProblemPayment {
  id: number
  booking_id: number | null
  provider: string | null
  status: string
  amount: number | string | null
  currency: string | null
  external_reference?: string | null
  external_payment_id?: string | null
  failure_reason?: string | null
  booking?: {
    status: string | null
    flow_type: string | null
    confirmed_at: string | null
  }
  snapshot?: {
    event_title: string | null
    hall_name: string | null
  }
  created_at: string | null
  updated_at: string | null
}

export interface AdminDashboardResponse {
  metrics: AdminDashboardMetrics
  booking: {
    recent_problem_payments: AdminProblemPayment[]
  }
}

export type AdminIncidentStatusFilter = 'all' | 'failed' | 'cancelled'

export interface AdminModerationPaginationParams {
  status?: string
  search?: string
  page?: number
  per_page?: number
}

export interface AdminIncidentsQuery {
  status?: AdminIncidentStatusFilter
  search?: string
  page?: number
  per_page?: number
}

export interface AdminOrganizerModerationPayload {
  status: AdminOrganizerModerationStatus
  note?: string | null
}

export interface AdminEventModerationPayload {
  decision: AdminEventDecision
  note?: string | null
}

export interface AdminCategoryPayload {
  name: string
  slug?: string | null
}

export interface AdminAgeRatingPayload {
  label: string
  min_age: number
}

export interface AdminTagPayload {
  name: string
  slug?: string | null
}

export type AdminOrganizersPage = PaginatedResponse<AdminOrganizerSummary>
export type AdminEventsPage = PaginatedResponse<AdminEventSummary>
export type AdminIncidentsPage = PaginatedResponse<AdminProblemPayment>

export interface AdminDictionariesSnapshot {
  categories: Category[]
  ageRatings: AgeRating[]
  tags: EventTag[]
}
