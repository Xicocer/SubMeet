<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import {
  getEventAssistantConversationRequest,
  getEventAssistantConversationsRequest,
  startEventAssistantStreamRequest,
} from '@/api/assistant'
import { useAuthStore } from '@/stores/auth'
import type {
  EventAssistantContext,
  EventAssistantConversationMessage,
  EventAssistantConversationSummary,
} from '@/types/assistant'
import { formatDate, formatPrice } from '@/utils/format'
import { renderMarkdown } from '@/utils/markdown'

const authStore = useAuthStore()

const draft = ref('')
const loadingHistory = ref(false)
const conversations = ref<EventAssistantConversationSummary[]>([])
const messages = ref<EventAssistantConversationMessage[]>([])
const activeConversationId = ref<string | null>(null)
const activeConversationTitle = ref<string>('Новый диалог')
const streamContext = ref<EventAssistantContext | null>(null)
const chatError = ref('')
const sending = ref(false)

const suggestions = [
  'Куда сходить сегодня вечером?',
  'На следующей неделе планирую свидание, что подойдет?',
  'Хочу с друзьями что-то энергичное на выходных.',
]

const historyEnabled = computed(() => authStore.isAuthenticated)
const hasMessages = computed(() => messages.value.length > 0)
const conversationLabel = computed(() => {
  if (!streamContext.value) {
    return historyEnabled.value
      ? 'История переписки с Митей сохраняется автоматически.'
      : 'Без входа Митя работает в гостевом режиме.'
  }

  const parts = [
    historyEnabled.value ? 'история включена' : 'гостевой режим',
    streamContext.value.intent_label,
    streamContext.value.timeframe_label,
  ].filter((item): item is string => Boolean(item))

  return parts.join(' · ')
})

const buildClientId = (prefix: string) => `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`
const buildConversationTitle = (message: string) =>
  message.length > 72 ? `${message.slice(0, 69).trimEnd()}...` : message
const renderAssistantMessage = (message: string) => renderMarkdown(message)
const resolveStorageKey = () => `mitya-chat.active-conversation.${authStore.user?.id ?? 'guest'}`

const persistActiveConversationId = (conversationId: string | null) => {
  if (!historyEnabled.value) {
    return
  }

  if (conversationId) {
    localStorage.setItem(resolveStorageKey(), conversationId)
    return
  }

  localStorage.removeItem(resolveStorageKey())
}

const readStoredConversationId = () => {
  if (!historyEnabled.value) {
    return null
  }

  return localStorage.getItem(resolveStorageKey())
}

const loadConversations = async () => {
  if (!historyEnabled.value) {
    conversations.value = []
    return
  }

  loadingHistory.value = true

  try {
    conversations.value = await getEventAssistantConversationsRequest()

    if (activeConversationId.value) {
      const activeConversation = conversations.value.find(
        (conversation) => conversation.id === activeConversationId.value,
      )

      if (activeConversation) {
        activeConversationTitle.value = activeConversation.title
      }
    }
  } catch (requestError) {
    console.error(requestError)
  } finally {
    loadingHistory.value = false
  }
}

const openConversation = async (conversationId: string) => {
  if (!historyEnabled.value) {
    return
  }

  chatError.value = ''
  sending.value = false

  try {
    const conversation = await getEventAssistantConversationRequest(conversationId)
    activeConversationId.value = conversation.id
    activeConversationTitle.value = conversation.title
    messages.value = conversation.messages
    streamContext.value = null
    persistActiveConversationId(conversation.id)
  } catch (requestError) {
    console.error(requestError)
    chatError.value = 'Не удалось открыть историю этого диалога с Митей.'
  }
}

const startNewConversation = () => {
  activeConversationId.value = null
  activeConversationTitle.value = 'Новый диалог'
  messages.value = []
  streamContext.value = null
  chatError.value = ''
  persistActiveConversationId(null)
}

const parseSseEvent = (rawChunk: string): { event: string; data: any } | null => {
  const normalized = rawChunk.replace(/\r\n/g, '\n').trim()

  if (!normalized) {
    return null
  }

  let event = 'message'
  const dataParts: string[] = []

  for (const line of normalized.split('\n')) {
    if (line.startsWith('event:')) {
      event = line.slice(6).trim()
    }

    if (line.startsWith('data:')) {
      dataParts.push(line.slice(5).trim())
    }
  }

  if (dataParts.length === 0) {
    return null
  }

  try {
    return {
      event,
      data: JSON.parse(dataParts.join('\n')),
    }
  } catch (parseError) {
    console.warn('Failed to parse SSE chunk', rawChunk, parseError)
    return null
  }
}

const consumeAssistantStream = async (
  response: Response,
  handleEvent: (payload: { event: string; data: any }) => void,
) => {
  const reader = response.body?.getReader()

  if (!reader) {
    throw new Error('Поток ответа от Мити недоступен.')
  }

  const decoder = new TextDecoder()
  let buffer = ''

  while (true) {
    const { done, value } = await reader.read()

    if (done) {
      buffer += decoder.decode()
      break
    }

    buffer += decoder.decode(value, { stream: true })
    buffer = buffer.replace(/\r\n/g, '\n')

    let boundaryIndex = buffer.indexOf('\n\n')

    while (boundaryIndex !== -1) {
      const chunk = buffer.slice(0, boundaryIndex)
      buffer = buffer.slice(boundaryIndex + 2)

      const parsed = parseSseEvent(chunk)

      if (parsed) {
        handleEvent(parsed)
      }

      boundaryIndex = buffer.indexOf('\n\n')
    }
  }

  const tail = parseSseEvent(buffer)

  if (tail) {
    handleEvent(tail)
  }
}

const sendMessage = async (prefilled?: string) => {
  const nextMessage = (prefilled ?? draft.value).trim()

  draft.value = nextMessage
  chatError.value = ''

  if (!nextMessage || sending.value) {
    return
  }

  const userMessage: EventAssistantConversationMessage = {
    id: buildClientId('user'),
    role: 'user',
    content: nextMessage,
    created_at: new Date().toISOString(),
    recommendations: [],
  }

  const assistantMessage: EventAssistantConversationMessage = {
    id: buildClientId('assistant'),
    role: 'assistant',
    content: '',
    created_at: new Date().toISOString(),
    recommendations: [],
  }

  messages.value = [...messages.value, userMessage, assistantMessage]
  if (!activeConversationId.value) {
    activeConversationTitle.value = buildConversationTitle(nextMessage)
  }
  draft.value = ''
  sending.value = true

  try {
    const response = await startEventAssistantStreamRequest(nextMessage, activeConversationId.value)

    await consumeAssistantStream(response, ({ event, data }) => {
      if (event === 'meta') {
        streamContext.value = data.context ?? null
        return
      }

      if (event === 'text-delta') {
        assistantMessage.content += String(data.delta ?? '')
        messages.value = [...messages.value]
        return
      }

      if (event === 'recommendations') {
        assistantMessage.recommendations = Array.isArray(data.items) ? data.items : []
        messages.value = [...messages.value]
        return
      }

      if (event === 'done') {
        if (typeof data.conversation_id === 'string' && data.conversation_id.length > 0) {
          activeConversationId.value = data.conversation_id
          persistActiveConversationId(data.conversation_id)
        }

        if (historyEnabled.value) {
          void loadConversations()
        }

        return
      }

      if (event === 'error') {
        chatError.value = data.message ?? 'Не удалось получить ответ от Мити.'
      }
    })
  } catch (requestError) {
    console.error(requestError)
    chatError.value = 'Не удалось отправить сообщение Мите.'
  } finally {
    sending.value = false
  }
}

onMounted(async () => {
  if (authStore.token && !authStore.user) {
    await authStore.fetchMe()
  }

  await loadConversations()

  if (!historyEnabled.value || hasMessages.value || conversations.value.length === 0) {
    return
  }

  const storedConversationId = readStoredConversationId()
  const conversationToOpen = storedConversationId
    && conversations.value.some((conversation) => conversation.id === storedConversationId)
      ? storedConversationId
      : conversations.value[0]?.id

  if (conversationToOpen) {
    await openConversation(conversationToOpen)
  }
})
</script>

<template>
  <div class="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
    <aside class="app-panel p-5 sm:p-6">
      <div class="flex items-start justify-between gap-4">
        <div>
          <span class="info-chip">Митя</span>
          <h1 class="mt-4 text-2xl font-semibold text-slate-950 sm:text-[2rem]">
            Чат с Митей
          </h1>
          <p class="mt-3 text-sm leading-7 text-slate-500">
            Спрашивай про настроение, время и формат вечера, а Митя сразу подберет события и покажет готовые карточки.
          </p>
        </div>
      </div>

      <div class="mt-6 flex flex-wrap gap-3">
        <button type="button" class="primary-button" @click="startNewConversation">
          Новый диалог
        </button>
        <RouterLink to="/events" class="secondary-button">
          К каталогу
        </RouterLink>
      </div>

      <div class="mt-6 rounded-[1.5rem] border border-slate-200 bg-slate-50/80 p-4 text-sm leading-6 text-slate-500">
        {{ conversationLabel }}
      </div>

      <div class="mt-6">
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">
          Быстрые сценарии
        </p>
        <div class="mt-3 flex flex-wrap gap-2">
          <button
            v-for="suggestion in suggestions"
            :key="suggestion"
            type="button"
            class="catalog-chip text-left"
            @click="sendMessage(suggestion)"
          >
            {{ suggestion }}
          </button>
        </div>
      </div>

      <div class="mt-8">
        <div class="flex items-center justify-between gap-3">
          <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">
            История
          </p>
          <span v-if="loadingHistory" class="text-xs text-slate-400">Загружаем...</span>
        </div>

        <div v-if="!historyEnabled" class="mt-3 rounded-[1.4rem] border border-dashed border-slate-200 bg-slate-50/80 p-4 text-sm leading-6 text-slate-500">
          Войди в аккаунт, чтобы сохранять историю диалогов с Митей и возвращаться к прошлым подборкам.
        </div>

        <div v-else-if="conversations.length > 0" class="mt-3 space-y-2">
          <button
            v-for="conversation in conversations"
            :key="conversation.id"
            type="button"
            class="w-full rounded-[1.4rem] border px-4 py-4 text-left transition duration-200"
            :class="conversation.id === activeConversationId
              ? 'border-blue-200 bg-blue-50/70'
              : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/80'"
            @click="openConversation(conversation.id)"
          >
            <p class="text-sm font-semibold text-slate-900">
              {{ conversation.title }}
            </p>
            <p class="mt-1 text-xs text-slate-400">
              {{ formatDate(conversation.updated_at || conversation.created_at || '') }}
            </p>
          </button>
        </div>

        <div v-else-if="historyEnabled" class="mt-3 rounded-[1.4rem] border border-dashed border-slate-200 bg-slate-50/80 p-4 text-sm leading-6 text-slate-500">
          Пока нет сохраненных диалогов. Начни новый, и он появится здесь автоматически.
        </div>
      </div>
    </aside>

    <section class="app-panel flex min-h-[720px] flex-col overflow-hidden p-0">
      <div class="border-b border-slate-200/80 px-5 py-5 sm:px-6">
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-700">
          Митя на связи
        </p>
        <div class="mt-3 flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
          <h2 class="text-2xl font-semibold text-slate-950">
            {{ activeConversationTitle }}
          </h2>
          <p class="text-sm text-slate-500">
            Ответ Мити и карточки событий появляются прямо по ходу диалога.
          </p>
        </div>
      </div>

      <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5 sm:px-6">
        <div v-if="!hasMessages" class="rounded-[2rem] border border-dashed border-slate-200 bg-slate-50/80 p-8">
          <h3 class="text-2xl font-semibold text-slate-950">
            Спроси, куда сходить
          </h3>
          <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-500 sm:text-base">
            Например: “Куда сходить сегодня вечером?”, “На следующей неделе планирую свидание” или “Хочу на выходных что-то энергичное с друзьями”.
          </p>
        </div>

        <div
          v-for="message in messages"
          :key="message.id"
          class="space-y-3"
          :class="message.role === 'user' ? 'items-end' : ''"
        >
          <div
            class="max-w-3xl rounded-[1.8rem] px-5 py-4 shadow-[0_24px_70px_-55px_rgba(15,23,42,0.25)]"
            :class="message.role === 'user'
              ? 'ml-auto bg-[linear-gradient(145deg,#2563eb,#1e3a8a)] text-white'
              : 'border border-slate-200 bg-white text-slate-800'"
          >
            <p class="text-xs font-semibold uppercase tracking-[0.18em]" :class="message.role === 'user' ? 'text-blue-100/80' : 'text-slate-400'">
              {{ message.role === 'user' ? 'Ты' : 'Митя' }}
            </p>
            <div
              v-if="message.role === 'assistant'"
              class="markdown-content mt-2 text-sm leading-7 sm:text-base"
              v-html="renderAssistantMessage(message.content || (sending ? 'Подбираю ответ...' : ''))"
            ></div>
            <p v-else class="mt-2 whitespace-pre-wrap text-sm leading-7 sm:text-base">
              {{ message.content }}
            </p>
          </div>

          <div
            v-if="message.role === 'assistant' && message.recommendations.length > 0"
            class="grid max-w-6xl gap-4 xl:grid-cols-2 2xl:grid-cols-3"
          >
            <RouterLink
              v-for="item in message.recommendations"
              :key="`${message.id}-${item.id}`"
              :to="`/events/${item.id}`"
              class="group overflow-hidden rounded-[1.7rem] border border-slate-200/80 bg-white shadow-[0_24px_70px_-55px_rgba(15,23,42,0.3)] transition duration-300 hover:-translate-y-1 hover:border-blue-200 hover:shadow-[0_35px_90px_-60px_rgba(37,99,235,0.32)]"
            >
              <div class="relative h-40 overflow-hidden bg-gradient-to-br from-slate-950 via-slate-900 to-blue-900">
                <img
                  v-if="item.poster_url"
                  :src="item.poster_url"
                  :alt="item.title"
                  class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]"
                />
                <div v-else class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(96,165,250,0.35),transparent_30%),linear-gradient(145deg,rgba(15,23,42,0.92),rgba(30,64,175,0.88))]"></div>

                <div class="absolute inset-x-0 top-0 flex items-start justify-between gap-3 p-4">
                  <span class="rounded-full border border-white/15 bg-slate-950/55 px-3 py-1 text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-white/90 backdrop-blur">
                    {{ item.category_name || item.category }}
                  </span>
                  <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-white backdrop-blur">
                    {{ item.age_rating }}+
                  </span>
                </div>
              </div>

              <div class="space-y-3 p-5">
                <h3 class="text-lg font-semibold leading-tight text-slate-950">
                  {{ item.title }}
                </h3>
                <div class="space-y-2 rounded-[1.3rem] border border-slate-200/80 bg-slate-50/80 px-4 py-4">
                  <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-slate-500">Ближайшая дата</span>
                    <span class="text-sm font-semibold text-slate-950">{{ formatDate(item.event_date) }}</span>
                  </div>
                  <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-slate-500">Цена от</span>
                    <span class="text-sm font-semibold text-blue-700">{{ formatPrice(item.price) }}</span>
                  </div>
                  <p class="text-sm leading-6 text-slate-500">
                    {{ item.venue_address || item.hall_name || item.city || 'Площадка уточняется' }}
                  </p>
                </div>
              </div>
            </RouterLink>
          </div>
        </div>
      </div>

      <div class="border-t border-slate-200/80 px-5 py-5 sm:px-6">
        <div v-if="chatError" class="message-error mb-4">
          {{ chatError }}
        </div>

        <form class="space-y-4" @submit.prevent="sendMessage()">
          <label class="field-label" for="assistant-chat-input">Сообщение для Мити</label>
          <div class="flex flex-col gap-3 lg:flex-row">
            <textarea
              id="assistant-chat-input"
              v-model="draft"
              rows="3"
              class="field-input min-h-[112px] flex-1 resize-none"
              placeholder="Например: Хочу что-то атмосферное на свидание на следующей неделе."
            ></textarea>
            <button type="submit" class="primary-button min-w-[12rem] self-end" :disabled="sending">
              {{ sending ? 'Отвечаем...' : 'Отправить' }}
            </button>
          </div>
        </form>
      </div>
    </section>
  </div>
</template>
