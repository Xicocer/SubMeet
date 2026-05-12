import { eventApi } from './axios'
import type {
  EventAssistantConversationDetail,
  EventAssistantConversationSummary,
} from '@/types/assistant'

const resolveEventApiBaseUrl = () => {
  return (eventApi.defaults.baseURL ?? 'http://127.0.0.1:8001/api').replace(/\/$/, '')
}

export const getEventAssistantConversationsRequest = async () => {
  const { data } = await eventApi.get<{ items: EventAssistantConversationSummary[] }>('/events/assistant/conversations')
  return data.items
}

export const getEventAssistantConversationRequest = async (conversationId: string) => {
  const { data } = await eventApi.get<EventAssistantConversationDetail>(`/events/assistant/conversations/${conversationId}`)
  return data
}

export const startEventAssistantStreamRequest = async (
  message: string,
  conversationId?: string | null,
) => {
  const token = localStorage.getItem('token')
  const response = await fetch(`${resolveEventApiBaseUrl()}/events/assistant/chat/stream`, {
    method: 'POST',
    headers: {
      Accept: 'text/event-stream',
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify({
      message,
      conversation_id: conversationId ?? null,
    }),
  })

  if (!response.ok || !response.body) {
    throw new Error('Не удалось запустить поток ответа Мити.')
  }

  return response
}
