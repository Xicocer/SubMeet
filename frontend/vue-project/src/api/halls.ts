import { hallApi } from './axios'
import type { OrganizerHallDashboardResponse } from '@/types/analytics'
import type { PaginatedResponse } from '@/types/event'
import type {
  HallDetails,
  HallFilters,
  HallMutationResponse,
  HallPayload,
  HallSummary,
} from '@/types/hall'

export const getOrganizerHallsRequest = async (params: HallFilters) => {
  const { data } = await hallApi.get<PaginatedResponse<HallSummary>>('/organizer/halls', { params })
  return data
}

export const getOrganizerHallDashboardRequest = async () => {
  const { data } = await hallApi.get<OrganizerHallDashboardResponse>('/organizer/dashboard')
  return data
}

export const getOrganizerHallRequest = async (id: number) => {
  const { data } = await hallApi.get<HallDetails>(`/organizer/halls/${id}`)
  return data
}

export const createOrganizerHallRequest = async (payload: HallPayload) => {
  const { data } = await hallApi.post<HallMutationResponse>('/organizer/halls', payload)
  return data
}

export const updateOrganizerHallRequest = async (id: number, payload: HallPayload) => {
  const { data } = await hallApi.put<HallMutationResponse>(`/organizer/halls/${id}`, payload)
  return data
}

export const archiveOrganizerHallRequest = async (id: number) => {
  const { data } = await hallApi.delete<HallMutationResponse>(`/organizer/halls/${id}`)
  return data
}
