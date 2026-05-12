import type { RecommendationItem } from '@/types/recommendation'

export interface AssistantEventItem extends RecommendationItem {
  assistant_note: string
}

export interface EventAssistantResponse {
  query: string
  answer: string
  mode: 'ai' | 'fallback'
  candidate_source: 'recommendation_service' | 'catalog_fallback'
  personalized: boolean
  context: {
    intent_label: string | null
    timeframe_label: string | null
  }
  items: AssistantEventItem[]
}

export interface EventAssistantContext {
  intent_label: string | null
  timeframe_label: string | null
}

export interface EventAssistantConversationSummary {
  id: string
  title: string
  created_at: string | null
  updated_at: string | null
}

export interface EventAssistantConversationMessage {
  id: string
  role: 'user' | 'assistant'
  content: string
  created_at: string | null
  recommendations: RecommendationItem[]
}

export interface EventAssistantConversationDetail extends EventAssistantConversationSummary {
  messages: EventAssistantConversationMessage[]
}
