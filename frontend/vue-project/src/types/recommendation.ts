export interface RecommendationItem {
  id: number
  title: string
  category: string
  category_name?: string | null
  tags: string
  description: string
  poster_url?: string | null
  price: number
  age_rating: number
  event_date: string
  score: number
  matrix_score?: number | null
  content_score: number
  popularity_score: number
  freshness_score: number
  source: string
  city?: string | null
  status?: string | null
  available_tickets?: number | null
  venue_address?: string | null
  hall_id?: number | null
  hall_name?: string | null
  next_session_id?: number | null
}

export interface RecommendationResponse {
  user_id: number
  limit: number
  items: RecommendationItem[]
  used_context: {
    preferred_categories: string[]
    preferred_tags: string[]
    city: string | null
    max_age_rating: number | null
  }
}
