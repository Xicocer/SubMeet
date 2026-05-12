import { adminApi } from './axios'
import type { AgeRating, Category, EventTag, PaginatedResponse } from '@/types/event'
import type {
  AdminAgeRatingPayload,
  AdminCategoryPayload,
  AdminDashboardResponse,
  AdminEventModerationPayload,
  AdminEventModerationResponse,
  AdminIncidentsPage,
  AdminIncidentsQuery,
  AdminEventSummary,
  AdminModerationPaginationParams,
  AdminOrganizerModerationPayload,
  AdminOrganizerModerationResponse,
  AdminOrganizerSummary,
  AdminTagPayload,
} from '@/types/admin'

export const getAdminDashboardRequest = async () => {
  const { data } = await adminApi.get<AdminDashboardResponse>('/dashboard')
  return data
}

export const getAdminOrganizersRequest = async (params: AdminModerationPaginationParams) => {
  const { data } = await adminApi.get<PaginatedResponse<AdminOrganizerSummary>>('/organizers', { params })
  return data
}

export const updateAdminOrganizerModerationRequest = async (
  userId: number,
  payload: AdminOrganizerModerationPayload,
) => {
  const { data } = await adminApi.patch<AdminOrganizerModerationResponse>(
    `/organizers/${userId}/moderation`,
    payload,
  )

  return data
}

export const getAdminIncidentsRequest = async (params: AdminIncidentsQuery) => {
  const { data } = await adminApi.get<AdminIncidentsPage>('/incidents', { params })
  return data
}

export const getAdminEventsRequest = async (params: AdminModerationPaginationParams) => {
  const { data } = await adminApi.get<PaginatedResponse<AdminEventSummary>>('/events', { params })
  return data
}

export const updateAdminEventModerationRequest = async (
  eventId: number,
  payload: AdminEventModerationPayload,
) => {
  const { data } = await adminApi.patch<AdminEventModerationResponse>(
    `/events/${eventId}/moderation`,
    payload,
  )

  return data
}

export const getAdminCategoriesRequest = async () => {
  const { data } = await adminApi.get<Category[]>('/dictionaries/categories')
  return data
}

export const createAdminCategoryRequest = async (payload: AdminCategoryPayload) => {
  const { data } = await adminApi.post<Category>('/dictionaries/categories', payload)
  return data
}

export const updateAdminCategoryRequest = async (id: number, payload: AdminCategoryPayload) => {
  const { data } = await adminApi.put<Category>(`/dictionaries/categories/${id}`, payload)
  return data
}

export const deleteAdminCategoryRequest = async (id: number) => {
  const { data } = await adminApi.delete<{ message: string }>(`/dictionaries/categories/${id}`)
  return data
}

export const getAdminAgeRatingsRequest = async () => {
  const { data } = await adminApi.get<AgeRating[]>('/dictionaries/age-ratings')
  return data
}

export const createAdminAgeRatingRequest = async (payload: AdminAgeRatingPayload) => {
  const { data } = await adminApi.post<AgeRating>('/dictionaries/age-ratings', payload)
  return data
}

export const updateAdminAgeRatingRequest = async (id: number, payload: AdminAgeRatingPayload) => {
  const { data } = await adminApi.put<AgeRating>(`/dictionaries/age-ratings/${id}`, payload)
  return data
}

export const deleteAdminAgeRatingRequest = async (id: number) => {
  const { data } = await adminApi.delete<{ message: string }>(`/dictionaries/age-ratings/${id}`)
  return data
}

export const getAdminTagsRequest = async () => {
  const { data } = await adminApi.get<EventTag[]>('/dictionaries/tags')
  return data
}

export const createAdminTagRequest = async (payload: AdminTagPayload) => {
  const { data } = await adminApi.post<EventTag>('/dictionaries/tags', payload)
  return data
}

export const updateAdminTagRequest = async (id: number, payload: AdminTagPayload) => {
  const { data } = await adminApi.put<EventTag>(`/dictionaries/tags/${id}`, payload)
  return data
}

export const deleteAdminTagRequest = async (id: number) => {
  const { data } = await adminApi.delete<{ message: string }>(`/dictionaries/tags/${id}`)
  return data
}
