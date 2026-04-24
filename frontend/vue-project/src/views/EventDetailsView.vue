<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getEventRequest, getEventSessionsRequest } from '@/api/events'
import { useAuthStore } from '@/stores/auth'
import type { EventDetails, EventSession } from '@/types/event'
import { formatDate, formatDateTime, formatPrice } from '@/utils/format'

const route = useRoute()
const authStore = useAuthStore()

const event = ref<EventDetails | null>(null)
const sessions = ref<EventSession[]>([])
const loading = ref(false)
const error = ref('')

const eventId = computed(() => Number(route.params.id))

const canManageEvent = computed(() => {
  return authStore.isOrganizer && authStore.user?.id === event.value?.organizer_id
})

const loadEventDetails = async () => {
  if (Number.isNaN(eventId.value)) {
    error.value = 'Некорректный идентификатор мероприятия.'
    return
  }

  loading.value = true
  error.value = ''

  try {
    const [loadedEvent, loadedSessions] = await Promise.all([
      getEventRequest(eventId.value),
      getEventSessionsRequest(eventId.value),
    ])

    event.value = loadedEvent
    sessions.value = loadedSessions
  } catch (requestError) {
    console.error(requestError)
    error.value = 'Не удалось загрузить карточку мероприятия.'
    event.value = null
    sessions.value = []
  } finally {
    loading.value = false
  }
}

watch(eventId, loadEventDetails, { immediate: true })
</script>

<template>
  <section v-if="loading" class="app-panel p-8 sm:p-10">
    <div class="h-72 animate-pulse rounded-[1.75rem] bg-slate-200"></div>
    <div class="mt-6 space-y-4">
      <div class="h-5 w-32 animate-pulse rounded-full bg-slate-100"></div>
      <div class="h-8 w-2/3 animate-pulse rounded-full bg-slate-200"></div>
      <div class="h-4 w-full animate-pulse rounded-full bg-slate-100"></div>
      <div class="h-4 w-5/6 animate-pulse rounded-full bg-slate-100"></div>
    </div>
  </section>

  <div v-else class="space-y-6">
    <div v-if="error" class="message-error">
      {{ error }}
    </div>

    <template v-if="event">
      <section class="app-panel overflow-hidden">
        <div class="grid xl:grid-cols-[0.95fr_1.05fr]">
          <div
            v-if="event.poster_url"
            class="min-h-[360px] bg-cover bg-center"
            :style="{ backgroundImage: `linear-gradient(rgba(15, 23, 42, 0.12), rgba(15, 23, 42, 0.48)), url(${event.poster_url})` }"
          ></div>
          <div
            v-else
            class="flex min-h-[360px] items-end bg-gradient-to-br from-slate-950 via-sky-900 to-emerald-700 p-8 text-white"
          >
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-white/70">
                {{ event.category?.name || 'Мероприятие' }}
              </p>
              <h2 class="mt-3 text-4xl font-semibold leading-tight">
                {{ event.title }}
              </h2>
            </div>
          </div>

          <div class="px-8 py-8 sm:px-10 sm:py-10">
            <div class="flex flex-wrap items-center gap-2">
              <span class="status-badge border-slate-200 bg-slate-100 text-slate-700">
                {{ event.category?.name || 'Без категории' }}
              </span>
              <span class="status-badge border-sky-200 bg-sky-50 text-sky-700">
                {{ event.age_rating?.label || 'Без рейтинга' }}
              </span>
              <span class="status-badge border-emerald-200 bg-emerald-50 text-emerald-700">
                {{ event.status }}
              </span>
            </div>

            <h1 class="mt-5 text-4xl font-semibold leading-tight text-slate-950">
              {{ event.title }}
            </h1>

            <p class="mt-4 text-sm leading-7 text-slate-600 sm:text-base">
              {{ event.description || 'Описание будет добавлено позже.' }}
            </p>

            <div class="mt-8 grid gap-4 sm:grid-cols-2">
              <article class="soft-card">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                  Организатор
                </p>
                <p class="mt-3 text-lg font-semibold text-slate-900">
                  {{ event.organizer?.full_name || `ID ${event.organizer_id}` }}
                </p>
                <p class="mt-2 text-sm text-slate-500">
                  {{ event.organizer?.email || 'Данные синхронизируются из auth-service' }}
                </p>
              </article>

              <article class="soft-card">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                  Опубликовано
                </p>
                <p class="mt-3 text-lg font-semibold text-slate-900">
                  {{ formatDate(event.created_at) }}
                </p>
                <p class="mt-2 text-sm text-slate-500">Обновлено: {{ formatDate(event.updated_at) }}</p>
              </article>
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
              <RouterLink to="/events" class="secondary-button">
                Вернуться в афишу
              </RouterLink>

              <RouterLink
                v-if="canManageEvent"
                to="/organizer/events"
                class="primary-button"
              >
                Управлять этим событием
              </RouterLink>
            </div>
          </div>
        </div>
      </section>

      <section class="app-panel p-8 sm:p-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <span class="info-chip">Сеансы</span>
            <h2 class="mt-4 text-3xl font-semibold text-slate-950">
              Доступные сеансы для бронирования
            </h2>
            <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
              Публичный список показывает только актуальные сеансы, которые не отменены и еще не
              закончились.
            </p>
          </div>

          <div class="rounded-[1.75rem] border border-white/85 bg-white/85 px-5 py-4 shadow-sm shadow-slate-900/5">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
              Количество сеансов
            </p>
            <p class="mt-2 text-3xl font-semibold text-slate-950">{{ sessions.length }}</p>
          </div>
        </div>

        <div v-if="sessions.length > 0" class="mt-8 grid gap-4 lg:grid-cols-2">
          <article
            v-for="session in sessions"
            :key="session.id"
            class="soft-card"
          >
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                  Зал {{ session.hall_id }}
                </p>
                <h3 class="mt-2 text-2xl font-semibold text-slate-950">
                  {{ formatDateTime(session.start_time) }}
                </h3>
                <p class="mt-2 text-sm text-slate-500">
                  До {{ formatDateTime(session.end_time) }}
                </p>
              </div>

              <span class="status-badge border-emerald-200 bg-emerald-50 text-emerald-700">
                {{ session.status }}
              </span>
            </div>

            <div class="mt-6 flex items-center justify-between gap-3">
              <p class="text-sm text-slate-500">Базовая цена</p>
              <p class="text-lg font-semibold text-slate-950">{{ formatPrice(session.base_price) }}</p>
            </div>
          </article>
        </div>

        <div v-else class="mt-8 rounded-[1.75rem] border border-dashed border-slate-200 bg-slate-50/70 px-6 py-8">
          <p class="text-lg font-semibold text-slate-900">Сеансы пока не добавлены</p>
          <p class="mt-2 text-sm leading-6 text-slate-500">
            Когда организатор создаст расписание, доступные даты и цены появятся здесь автоматически.
          </p>
        </div>
      </section>
    </template>
  </div>
</template>
