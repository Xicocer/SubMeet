import { eventApi } from './axios'
import type {
  AgeRating,
  Category,
  ChangeOrganizerEventStatusPayload,
  EventDetails,
  EventListFilters,
  EventSession,
  OrganizerEvent,
  OrganizerEventMutationResponse,
  OrganizerEventsFilters,
  OrganizerEventPayload,
  OrganizerSessionMutationResponse,
  OrganizerSessionPayload,
  PaginatedResponse,
  PublicEvent,
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

export const getEventRequest = async (id: number) => {
  const { data } = await eventApi.get<EventDetails>(`/events/${id}`)
  return data
}

export const getEventSessionsRequest = async (id: number) => {
  const { data } = await eventApi.get<EventSession[]>(`/events/${id}/sessions`)
  return data
}

export const getOrganizerEventsRequest = async (params: OrganizerEventsFilters) => {
  const { data } = await eventApi.get<PaginatedResponse<OrganizerEvent>>('/organizer/events', { params })
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
