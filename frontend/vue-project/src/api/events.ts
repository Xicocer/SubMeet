import { eventApi } from './axios'
import type { OrganizerEventDashboardResponse } from '@/types/analytics'
import type { EventAssistantResponse } from '@/types/assistant'
import type {
  AgeRating,
  Category,
  ChangeOrganizerEventStatusPayload,
  EventDetails,
  EventListFilters,
  EventSession,
  OrganizerEvent,
  OrganizerEventCopywriterPayload,
  OrganizerEventCopywriterResponse,
  OrganizerEventMutationResponse,
  OrganizerEventsFilters,
  OrganizerEventPayload,
  OrganizerEventTagSuggestionPayload,
  OrganizerEventTagSuggestionResponse,
  OrganizerSessionMutationResponse,
  OrganizerSessionPayload,
  PaginatedResponse,
  PublicEvent,
  WantToGoEvent,
  WantToGoMutationResponse,
} from '@/types/event'

export const getCategoriesRequest = async () => {
  const { data } = await eventApi.get<Category[]>('/categories')
  return data
}

export const getAgeRatingsRequest = async () => {
  const { data } = await eventApi.get<AgeRating[]>('/age-ratings')
  return data
}

export const getEventsRequest = async (params: EventListFilters) => {
  const { data } = await eventApi.get<PaginatedResponse<PublicEvent>>('/events', { params })
  return data
}

export const getEventAssistantRequest = async (query: string, limit = 4) => {
  const { data } = await eventApi.post<EventAssistantResponse>('/events/assistant', {
    query,
    limit,
  })

  return data
}

export const getEventRequest = async (id: number) => {
  const { data } = await eventApi.get<EventDetails>(`/events/${id}`)
  return data
}

export const getEventSessionsRequest = async (id: number) => {
  const { data } = await eventApi.get<EventSession[]>(`/events/${id}/sessions`)
  return data
}

export const getWantToGoEventsRequest = async () => {
  const { data } = await eventApi.get<WantToGoEvent[]>('/me/want-to-go')
  return data
}

export const addWantToGoRequest = async (eventId: number) => {
  const { data } = await eventApi.post<WantToGoMutationResponse>(`/events/${eventId}/want-to-go`)
  return data
}

export const removeWantToGoRequest = async (eventId: number) => {
  const { data } = await eventApi.delete<WantToGoMutationResponse>(`/events/${eventId}/want-to-go`)
  return data
}

export const getOrganizerEventsRequest = async (params: OrganizerEventsFilters) => {
  const { data } = await eventApi.get<PaginatedResponse<OrganizerEvent>>('/organizer/events', { params })
  return data
}

export const getOrganizerEventDashboardRequest = async () => {
  const { data } = await eventApi.get<OrganizerEventDashboardResponse>('/organizer/dashboard')
  return data
}

export const createOrganizerEventRequest = async (payload: OrganizerEventPayload) => {
  const { data } = await eventApi.post<OrganizerEventMutationResponse>('/organizer/events', payload)
  return data
}

export const updateOrganizerEventRequest = async (
  id: number,
  payload: OrganizerEventPayload,
) => {
  const { data } = await eventApi.put<OrganizerEventMutationResponse>(`/organizer/events/${id}`, payload)
  return data
}

export const rewriteOrganizerEventDescriptionRequest = async (
  payload: OrganizerEventCopywriterPayload,
) => {
  const { data } = await eventApi.post<OrganizerEventCopywriterResponse>(
    '/organizer/events/copywriter/rewrite',
    payload,
  )

  return data
}

export const suggestOrganizerEventTagsRequest = async (
  payload: OrganizerEventTagSuggestionPayload,
) => {
  const { data } = await eventApi.post<OrganizerEventTagSuggestionResponse>(
    '/organizer/events/suggest-tags',
    payload,
  )

  return data
}

export const changeOrganizerEventStatusRequest = async (
  id: number,
  payload: ChangeOrganizerEventStatusPayload,
) => {
  const { data } = await eventApi.delete<OrganizerEventMutationResponse>(`/organizer/events/${id}`, {
    data: payload,
  })
  return data
}

export const getOrganizerEventSessionsRequest = async (eventId: number) => {
  const { data } = await eventApi.get<EventSession[]>(`/organizer/events/${eventId}/sessions`)
  return data
}

export const createOrganizerSessionRequest = async (
  eventId: number,
  payload: OrganizerSessionPayload,
) => {
  const { data } = await eventApi.post<OrganizerSessionMutationResponse>(
    `/organizer/events/${eventId}/sessions`,
    payload,
  )
  return data
}

export const updateOrganizerSessionRequest = async (
  id: number,
  payload: OrganizerSessionPayload,
) => {
  const { data } = await eventApi.put<OrganizerSessionMutationResponse>(
    `/organizer/sessions/${id}`,
    payload,
  )
  return data
}

export const cancelOrganizerSessionRequest = async (id: number) => {
  const { data } = await eventApi.delete<OrganizerSessionMutationResponse>(`/organizer/sessions/${id}`)
  return data
}
