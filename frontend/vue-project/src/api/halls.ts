import { hallApi } from './axios'
import type { OrganizerHallDashboardResponse } from '@/types/analytics'
import type { PaginatedResponse } from '@/types/event'
import type {
  HallDetails,
  HallFilters,
  HallAvailabilityResponse,
  HallRentalRequest,
  HallRentalRequestPayload,
  HallRentalRequestStatusPayload,
  HallMutationResponse,
  HallPayload,
  PublicHallFilters,
  HallSummary,
  HallUnavailablePeriod,
  HallUnavailablePeriodPayload,
} from '@/types/hall'

export const getPublicHallsRequest = async (params: PublicHallFilters) => {
  const { data } = await hallApi.get<PaginatedResponse<HallSummary>>('/halls', { params })
  return data
}

export const getHallAvailabilityRequest = async (
  id: number,
  params?: { from?: string; to?: string },
) => {
  const { data } = await hallApi.get<HallAvailabilityResponse>(`/halls/${id}/availability`, { params })
  return data
}

export const getVenueHallAvailabilityRequest = async (
  id: number,
  params?: { from?: string; to?: string },
) => {
  const { data } = await hallApi.get<HallAvailabilityResponse>(`/venue/halls/${id}/availability`, { params })
  return data
}

export const getVenueHallsRequest = async (params: HallFilters) => {
  const { data } = await hallApi.get<PaginatedResponse<HallSummary>>('/venue/halls', { params })
  return data
}

export const getVenueHallDashboardRequest = async () => {
  const { data } = await hallApi.get<OrganizerHallDashboardResponse>('/venue/dashboard')
  return data
}

export const getVenueHallRequest = async (id: number) => {
  const { data } = await hallApi.get<HallDetails>(`/venue/halls/${id}`)
  return data
}

export const createVenueHallRequest = async (payload: HallPayload) => {
  const { data } = await hallApi.post<HallMutationResponse>('/venue/halls', payload)
  return data
}

export const updateVenueHallRequest = async (id: number, payload: HallPayload) => {
  const { data } = await hallApi.put<HallMutationResponse>(`/venue/halls/${id}`, payload)
  return data
}

export const archiveVenueHallRequest = async (id: number) => {
  const { data } = await hallApi.delete<HallMutationResponse>(`/venue/halls/${id}`)
  return data
}

export const getOrganizerHallRentalRequestsRequest = async (params?: Record<string, unknown>) => {
  const { data } = await hallApi.get<PaginatedResponse<HallRentalRequest>>('/organizer/hall-rental-requests', {
    params,
  })
  return data
}

export const createOrganizerHallRentalRequest = async (payload: HallRentalRequestPayload) => {
  const { data } = await hallApi.post<{
    message: string
    rental_request: HallRentalRequest | null
    rental_requests?: HallRentalRequest[]
  }>(
    '/organizer/hall-rental-requests',
    payload,
  )
  return data
}

export const getVenueHallRentalRequestsRequest = async (params?: Record<string, unknown>) => {
  const { data } = await hallApi.get<PaginatedResponse<HallRentalRequest>>('/venue/hall-rental-requests', {
    params,
  })
  return data
}

export const updateVenueHallRentalRequestStatus = async (
  id: number,
  payload: HallRentalRequestStatusPayload,
) => {
  const { data } = await hallApi.patch<{ message: string; rental_request: HallRentalRequest }>(
    `/venue/hall-rental-requests/${id}`,
    payload,
  )
  return data
}

export const getVenueHallUnavailablePeriodsRequest = async (
  id: number,
  params?: { from?: string; to?: string },
) => {
  const { data } = await hallApi.get<{ data: HallUnavailablePeriod[] }>(
    `/venue/halls/${id}/unavailable-periods`,
    { params },
  )
  return data
}

export const createVenueHallUnavailablePeriodRequest = async (
  id: number,
  payload: HallUnavailablePeriodPayload,
) => {
  const { data } = await hallApi.post<{ message: string; period: HallUnavailablePeriod }>(
    `/venue/halls/${id}/unavailable-periods`,
    payload,
  )
  return data
}

export const deleteVenueHallUnavailablePeriodRequest = async (id: number) => {
  const { data } = await hallApi.delete<{ message: string }>(`/venue/hall-unavailable-periods/${id}`)
  return data
}

// Temporary aliases while the UI transitions from organizer-owned halls to venue-owned halls.
export const getOrganizerHallsRequest = getVenueHallsRequest
export const getOrganizerHallDashboardRequest = getVenueHallDashboardRequest
export const getOrganizerHallRequest = getVenueHallRequest
export const createOrganizerHallRequest = createVenueHallRequest
export const updateOrganizerHallRequest = updateVenueHallRequest
export const archiveOrganizerHallRequest = archiveVenueHallRequest
