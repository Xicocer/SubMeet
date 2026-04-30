import { recommendationApi } from './axios'
import type { RecommendationResponse } from '@/types/recommendation'

export const getUserRecommendationsRequest = async (userId: number, limit = 4) => {
  const { data } = await recommendationApi.get<RecommendationResponse>(`/recommendations/users/${userId}`, {
    params: { limit },
  })

  return data
}

export const getRecommendationPreviewRequest = async (limit = 4) => {
  const { data } = await recommendationApi.post<RecommendationResponse>('/recommendations/preview', {
    limit,
  })

  return data
}
