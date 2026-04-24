<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import {
  cancelOrganizerSessionRequest,
  changeOrganizerEventStatusRequest,
  createOrganizerEventRequest,
  createOrganizerSessionRequest,
  getAgeRatingsRequest,
  getCategoriesRequest,
  getOrganizerEventsRequest,
  getOrganizerEventSessionsRequest,
  updateOrganizerEventRequest,
  updateOrganizerSessionRequest,
} from '@/api/events'
import { useAuthStore } from '@/stores/auth'
import type {
  AgeRating,
  Category,
  EventSession,
  OrganizerEvent,
  OrganizerEventPayload,
  OrganizerEventStatus,
  OrganizerSessionPayload,
} from '@/types/event'
import { formatDate, formatDateTime, formatDateTimeForInput, formatPrice } from '@/utils/format'

const authStore = useAuthStore()

const categories = ref<Category[]>([])
const ageRatings = ref<AgeRating[]>([])
const events = ref<OrganizerEvent[]>([])
const sessions = ref<EventSession[]>([])

const loading = ref(false)
const lookupsLoading = ref(false)
const sessionsLoading = ref(false)
const eventSaving = ref(false)
const sessionSaving = ref(false)
const error = ref('')
const success = ref('')

const statusFilter = ref<OrganizerEventStatus | ''>('')
const selectedEventId = ref<number | null>(null)
const eventFormMode = ref<'create' | 'edit'>('create')

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  total: 0,
  per_page: 8,
})

const createEventDraft = () => ({
  title: '',
  description: '',
  poster_url: '',
  category_id: '',
  age_rating_id: '',
  status: 'draft' as Extract<OrganizerEventStatus, 'draft' | 'published'>,
})

const createSessionDraft = () => ({
  id: null as number | null,
  hall_id: '',
  start_time: '',
  end_time: '',
  base_price: '',
})

const eventForm = reactive(createEventDraft())
const sessionForm = reactive(createSessionDraft())

const activeEvent = computed(() => {
  return events.value.find((event) => event.id === selectedEventId.value) ?? null
})

const draftCount = computed(() => events.value.filter((event) => event.status === 'draft').length)
const publishedCount = computed(() => events.value.filter((event) => event.status === 'published').length)

const canSubmitEvent = computed(() => {
  return (
    eventForm.title.trim() !== '' &&
    eventForm.category_id !== '' &&
    eventForm.age_rating_id !== ''
  )
})

const canSubmitSession = computed(() => {
  return (
    activeEvent.value !== null &&
    sessionForm.hall_id.trim() !== '' &&
    sessionForm.start_time.trim() !== '' &&
    sessionForm.end_time.trim() !== '' &&
    sessionForm.base_price.trim() !== ''
  )
})

const eventStatusLabel = (status: OrganizerEventStatus) => {
  switch (status) {
    case 'published':
      return 'Опубликовано'
    case 'draft':
      return 'Черновик'
    case 'cancelled':
      return 'Отменено'
    case 'archived':
      return 'Архив'
    default:
      return status
  }
}

const eventStatusClasses = (status: OrganizerEventStatus) => {
  switch (status) {
    case 'published':
      return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    case 'draft':
      return 'border-amber-200 bg-amber-50 text-amber-700'
    case 'cancelled':
      return 'border-rose-200 bg-rose-50 text-rose-700'
    case 'archived':
      return 'border-slate-200 bg-slate-100 text-slate-700'
    default:
      return 'border-slate-200 bg-slate-100 text-slate-700'
  }
}

const sessionStatusClasses = (status: EventSession['status']) => {
  switch (status) {
    case 'scheduled':
      return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    case 'cancelled':
      return 'border-rose-200 bg-rose-50 text-rose-700'
    case 'completed':
      return 'border-slate-200 bg-slate-100 text-slate-700'
    default:
      return 'border-slate-200 bg-slate-100 text-slate-700'
  }
}

const resetSessionForm = () => {
  Object.assign(sessionForm, createSessionDraft())
}

const startCreateEvent = () => {
  eventFormMode.value = 'create'
  selectedEventId.value = null
  sessions.value = []
  error.value = ''
  success.value = ''
  Object.assign(eventForm, createEventDraft())
  resetSessionForm()
}

const fillEventForm = (event: OrganizerEvent) => {
  eventForm.title = event.title
  eventForm.description = event.description || ''
  eventForm.poster_url = event.poster_url || ''
  eventForm.category_id = event.category?.id ? String(event.category.id) : ''
  eventForm.age_rating_id = event.age_rating?.id ? String(event.age_rating.id) : ''
  eventForm.status = event.status === 'published' ? 'published' : 'draft'
}

const loadSessions = async (eventId: number) => {
  sessionsLoading.value = true

  try {
    sessions.value = await getOrganizerEventSessionsRequest(eventId)
  } catch (requestError) {
    console.error(requestError)
    error.value = 'Не удалось загрузить список сеансов для выбранного мероприятия.'
    sessions.value = []
  } finally {
    sessionsLoading.value = false
  }
}

const selectEvent = async (event: OrganizerEvent) => {
  eventFormMode.value = 'edit'
  selectedEventId.value = event.id
  error.value = ''
  fillEventForm(event)
  resetSessionForm()
  await loadSessions(event.id)
}

const loadLookups = async () => {
  lookupsLoading.value = true

  try {
    const [loadedCategories, loadedAgeRatings] = await Promise.all([
      getCategoriesRequest(),
      getAgeRatingsRequest(),
    ])

    categories.value = loadedCategories
    ageRatings.value = loadedAgeRatings
  } catch (requestError) {
    console.error(requestError)
    error.value = 'Не удалось загрузить категории и возрастные рейтинги.'
  } finally {
    lookupsLoading.value = false
  }
}

const loadEvents = async (page = 1, preferredEventId?: number | null) => {
  loading.value = true
  error.value = ''

  try {
    const response = await getOrganizerEventsRequest({
      status: statusFilter.value || undefined,
      page,
      per_page: pagination.per_page,
    })

    events.value = response.data
    pagination.current_page = response.current_page
    pagination.last_page = response.last_page
    pagination.total = response.total

    const nextSelectedId = preferredEventId ?? selectedEventId.value

    if (nextSelectedId) {
      const matchedEvent = response.data.find((event) => event.id === nextSelectedId)

      if (matchedEvent) {
        await selectEvent(matchedEvent)
        return
      }
    }

    const firstEvent = response.data[0]

    if (firstEvent) {
      await selectEvent(firstEvent)
      return
    }

    startCreateEvent()
  } catch (requestError) {
    console.error(requestError)
    error.value = 'Не удалось загрузить список мероприятий организатора.'
  } finally {
    loading.value = false
  }
}

const submitEvent = async () => {
  if (!canSubmitEvent.value) {
    error.value = 'Заполни название, категорию и возрастной рейтинг.'
    return
  }

  error.value = ''
  success.value = ''
  eventSaving.value = true

  try {
    const payload: OrganizerEventPayload = {
      title: eventForm.title.trim(),
      description: eventForm.description.trim() || null,
      poster_url: eventForm.poster_url.trim() || null,
      category_id: Number(eventForm.category_id),
      age_rating_id: Number(eventForm.age_rating_id),
      status: eventForm.status,
    }

    const response =
      eventFormMode.value === 'create' || selectedEventId.value === null
        ? await createOrganizerEventRequest(payload)
        : await updateOrganizerEventRequest(selectedEventId.value, payload)

    success.value = response.message
    await loadEvents(1, response.event.id)
  } catch (requestError: any) {
    console.error(requestError)
    error.value = requestError?.response?.data?.message || 'Не удалось сохранить мероприятие.'
  } finally {
    eventSaving.value = false
  }
}

const changeEventStatus = async (status: Extract<OrganizerEventStatus, 'cancelled' | 'archived'>) => {
  if (!activeEvent.value) return

  const confirmed = window.confirm(
    status === 'cancelled'
      ? 'Отменить мероприятие? Запись останется в системе.'
      : 'Отправить мероприятие в архив?',
  )

  if (!confirmed) return

  error.value = ''
  success.value = ''
  eventSaving.value = true

  try {
    const response = await changeOrganizerEventStatusRequest(activeEvent.value.id, { status })
    success.value = response.message
    await loadEvents(1, response.event.id)
  } catch (requestError: any) {
    console.error(requestError)
    error.value = requestError?.response?.data?.message || 'Не удалось изменить статус мероприятия.'
  } finally {
    eventSaving.value = false
  }
}

const editSession = (session: EventSession) => {
  sessionForm.id = session.id
  sessionForm.hall_id = String(session.hall_id)
  sessionForm.start_time = formatDateTimeForInput(session.start_time)
  sessionForm.end_time = formatDateTimeForInput(session.end_time)
  sessionForm.base_price = String(session.base_price)
  success.value = ''
  error.value = ''
}

const submitSession = async () => {
  if (!activeEvent.value || !canSubmitSession.value) {
    error.value = 'Сначала выбери мероприятие и заполни все поля сеанса.'
    return
  }

  error.value = ''
  success.value = ''
  sessionSaving.value = true

  try {
    const payload: OrganizerSessionPayload = {
      hall_id: Number(sessionForm.hall_id),
      start_time: sessionForm.start_time,
      end_time: sessionForm.end_time,
      base_price: Number(sessionForm.base_price),
    }

    const response = sessionForm.id
      ? await updateOrganizerSessionRequest(sessionForm.id, payload)
      : await createOrganizerSessionRequest(activeEvent.value.id, payload)

    success.value = response.message
    resetSessionForm()
    await loadSessions(activeEvent.value.id)
  } catch (requestError: any) {
    console.error(requestError)
    error.value = requestError?.response?.data?.message || 'Не удалось сохранить сеанс.'
  } finally {
    sessionSaving.value = false
  }
}

const cancelSession = async (session: EventSession) => {
  if (!activeEvent.value) return

  const confirmed = window.confirm('Отменить выбранный сеанс?')

  if (!confirmed) return

  error.value = ''
  success.value = ''
  sessionSaving.value = true

  try {
    const response = await cancelOrganizerSessionRequest(session.id)
    success.value = response.message
    resetSessionForm()
    await loadSessions(activeEvent.value.id)
  } catch (requestError: any) {
    console.error(requestError)
    error.value = requestError?.response?.data?.message || 'Не удалось отменить сеанс.'
  } finally {
    sessionSaving.value = false
  }
}

const changePage = async (page: number) => {
  if (page < 1 || page > pagination.last_page) return
  await loadEvents(page, selectedEventId.value)
}

onMounted(async () => {
  if (!authStore.user) {
    await authStore.fetchMe()
  }

  await Promise.all([loadLookups(), loadEvents()])
})
</script>

<template>
  <div class="space-y-6">
    <section class="app-panel p-8 sm:p-10">
      <div class="flex flex-col gap-8 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
          <span class="info-chip">Organizer API</span>
          <h2 class="mt-4 text-3xl font-semibold leading-tight text-slate-950">
            Управление своими событиями и расписанием сеансов
          </h2>
          <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
            Эта панель работает поверх защищенных organizer-контроллеров. `organizer_id` берется из
            токена через auth-service, а не передается с фронта вручную.
          </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
          <article class="rounded-2xl border border-slate-200 bg-white/85 px-4 py-4 shadow-sm shadow-slate-900/5">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Всего</p>
            <p class="mt-2 text-3xl font-semibold text-slate-950">{{ pagination.total }}</p>
          </article>

          <article class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 shadow-sm shadow-amber-900/5">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">Черновики</p>
            <p class="mt-2 text-3xl font-semibold text-amber-950">{{ draftCount }}</p>
          </article>

          <article class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 shadow-sm shadow-emerald-900/5">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">
              Опубликовано
            </p>
            <p class="mt-2 text-3xl font-semibold text-emerald-950">{{ publishedCount }}</p>
          </article>
        </div>
      </div>
    </section>

    <div v-if="success" class="message-success">
      {{ success }}
    </div>

    <div v-if="error" class="message-error">
      {{ error }}
    </div>

    <div class="grid gap-6 xl:grid-cols-[360px_1fr]">
      <aside class="app-panel p-6">
        <div class="flex items-center justify-between gap-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
              Мои мероприятия
            </p>
            <h3 class="mt-2 text-2xl font-semibold text-slate-950">
              {{ authStore.user?.full_name || 'Организатор' }}
            </h3>
          </div>

          <button type="button" class="primary-button px-4 py-3" @click="startCreateEvent">
            Новое
          </button>
        </div>

        <div class="mt-6 grid gap-4">
          <div>
            <label class="field-label" for="organizer-status-filter">Фильтр по статусу</label>
            <select
              id="organizer-status-filter"
              v-model="statusFilter"
              class="field-input"
              @change="loadEvents(1)"
            >
              <option value="">Все статусы</option>
              <option value="draft">Черновики</option>
              <option value="published">Опубликованные</option>
              <option value="cancelled">Отмененные</option>
              <option value="archived">Архив</option>
            </select>
          </div>
        </div>

        <div v-if="loading" class="mt-6 space-y-4">
          <div
            v-for="item in 4"
            :key="item"
            class="h-28 animate-pulse rounded-[1.5rem] bg-slate-100"
          ></div>
        </div>

        <div v-else-if="events.length > 0" class="mt-6 space-y-4">
          <article
            v-for="event in events"
            :key="event.id"
            class="rounded-[1.5rem] border px-4 py-4 transition duration-200"
            :class="
              event.id === selectedEventId
                ? 'border-slate-900 bg-slate-900 text-white shadow-lg shadow-slate-900/10'
                : 'border-slate-200 bg-white hover:border-slate-300'
            "
          >
            <button type="button" class="w-full text-left" @click="selectEvent(event)">
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p
                    class="text-xs font-semibold uppercase tracking-[0.24em]"
                    :class="event.id === selectedEventId ? 'text-white/65' : 'text-slate-500'"
                  >
                    {{ event.category?.name || 'Без категории' }}
                  </p>
                  <h4 class="mt-2 text-lg font-semibold leading-tight">
                    {{ event.title }}
                  </h4>
                </div>

                <span
                  class="status-badge"
                  :class="
                    event.id === selectedEventId
                      ? 'border-white/20 bg-white/10 text-white'
                      : eventStatusClasses(event.status)
                  "
                >
                  {{ eventStatusLabel(event.status) }}
                </span>
              </div>

              <p
                class="mt-3 text-sm leading-6"
                :class="event.id === selectedEventId ? 'text-white/70' : 'text-slate-500'"
              >
                {{ event.description || 'Описание пока не добавлено.' }}
              </p>

              <p
                class="mt-3 text-xs"
                :class="event.id === selectedEventId ? 'text-white/60' : 'text-slate-400'"
              >
                Создано {{ formatDate(event.created_at) }}
              </p>
            </button>
          </article>
        </div>

        <div v-else class="mt-6 rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50/70 px-5 py-6">
          <p class="text-lg font-semibold text-slate-900">Мероприятий пока нет</p>
          <p class="mt-2 text-sm leading-6 text-slate-500">
            Начни с создания первого события. После этого тут появится список для управления.
          </p>
        </div>

        <div v-if="pagination.last_page > 1" class="mt-6 flex items-center justify-between gap-3">
          <button
            type="button"
            class="secondary-button flex-1 px-4 py-3"
            :disabled="pagination.current_page === 1"
            @click="changePage(pagination.current_page - 1)"
          >
            Назад
          </button>

          <div class="text-sm text-slate-500">
            {{ pagination.current_page }} / {{ pagination.last_page }}
          </div>

          <button
            type="button"
            class="secondary-button flex-1 px-4 py-3"
            :disabled="pagination.current_page === pagination.last_page"
            @click="changePage(pagination.current_page + 1)"
          >
            Дальше
          </button>
        </div>
      </aside>

      <div class="space-y-6">
        <section class="app-panel p-8">
          <div class="flex flex-col gap-4 border-b border-slate-200/70 pb-6 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <span class="info-chip">
                {{ eventFormMode === 'create' ? 'Новое мероприятие' : 'Редактирование мероприятия' }}
              </span>
              <h3 class="mt-4 text-3xl font-semibold text-slate-950">
                {{
                  activeEvent && eventFormMode === 'edit'
                    ? activeEvent.title
                    : 'Создание нового мероприятия'
                }}
              </h3>
              <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
                Категория и возрастной рейтинг подгружаются из `event-service`, а организатор
                определяется на сервере по токену.
              </p>
            </div>

            <div v-if="activeEvent" class="flex flex-wrap gap-3">
              <button
                type="button"
                class="danger-button"
                :disabled="eventSaving"
                @click="changeEventStatus('cancelled')"
              >
                Отменить
              </button>

              <button
                type="button"
                class="secondary-button"
                :disabled="eventSaving"
                @click="changeEventStatus('archived')"
              >
                В архив
              </button>
            </div>
          </div>

          <form class="mt-8 grid gap-5 sm:grid-cols-2" @submit.prevent="submitEvent">
            <div class="sm:col-span-2">
              <label class="field-label" for="organizer-event-title">Название</label>
              <input
                id="organizer-event-title"
                v-model="eventForm.title"
                type="text"
                class="field-input"
                placeholder="Большой летний концерт"
              />
            </div>

            <div>
              <label class="field-label" for="organizer-event-category">Категория</label>
              <select id="organizer-event-category" v-model="eventForm.category_id" class="field-input">
                <option value="">Выбери категорию</option>
                <option v-for="category in categories" :key="category.id" :value="String(category.id)">
                  {{ category.name }}
                </option>
              </select>
            </div>

            <div>
              <label class="field-label" for="organizer-event-age">Возрастной рейтинг</label>
              <select id="organizer-event-age" v-model="eventForm.age_rating_id" class="field-input">
                <option value="">Выбери возрастной рейтинг</option>
                <option v-for="ageRating in ageRatings" :key="ageRating.id" :value="String(ageRating.id)">
                  {{ ageRating.label }}
                </option>
              </select>
            </div>

            <div class="sm:col-span-2">
              <label class="field-label" for="organizer-event-poster">Ссылка на постер</label>
              <input
                id="organizer-event-poster"
                v-model="eventForm.poster_url"
                type="url"
                class="field-input"
                placeholder="https://example.com/poster.jpg"
              />
            </div>

            <div class="sm:col-span-2">
              <label class="field-label" for="organizer-event-description">Описание</label>
              <textarea
                id="organizer-event-description"
                v-model="eventForm.description"
                rows="5"
                class="field-input resize-none"
                placeholder="Подробное описание мероприятия для карточки события"
              ></textarea>
            </div>

            <div>
              <label class="field-label" for="organizer-event-status">Статус публикации</label>
              <select id="organizer-event-status" v-model="eventForm.status" class="field-input">
                <option value="draft">Черновик</option>
                <option value="published">Опубликовать</option>
              </select>
            </div>

            <div class="rounded-[1.5rem] border border-sky-100 bg-sky-50/80 px-5 py-4">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-700">Важно</p>
              <p class="mt-2 text-sm leading-6 text-sky-900">
                Организатор не выбирается вручную. Сервер сам берет его из авторизованного
                пользователя.
              </p>
            </div>

            <div class="sm:col-span-2 flex flex-col gap-4 pt-2 lg:flex-row lg:items-center lg:justify-between">
              <div class="text-sm text-slate-500">
                <span v-if="lookupsLoading">Загружаем категории и возрастные рейтинги...</span>
                <span v-else>
                  Категорий: {{ categories.length }}, возрастных рейтингов: {{ ageRatings.length }}
                </span>
              </div>

              <div class="flex flex-col gap-3 sm:flex-row">
                <button type="button" class="secondary-button" @click="startCreateEvent">
                  Очистить форму
                </button>

                <button
                  :disabled="eventSaving || !canSubmitEvent"
                  type="submit"
                  class="primary-button min-w-56"
                >
                  {{
                    eventSaving
                      ? 'Сохраняем...'
                      : eventFormMode === 'create'
                        ? 'Создать мероприятие'
                        : 'Сохранить изменения'
                  }}
                </button>
              </div>
            </div>
          </form>
        </section>

        <section class="app-panel p-8">
          <div class="flex flex-col gap-4 border-b border-slate-200/70 pb-6 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <span class="info-chip">Сеансы мероприятия</span>
              <h3 class="mt-4 text-3xl font-semibold text-slate-950">
                {{ activeEvent ? 'Управление расписанием' : 'Сначала сохрани мероприятие' }}
              </h3>
              <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
                Сеанс можно добавить только к существующему мероприятию. `hall_id` пока вводится
                вручную, потому что hall-service у тебя еще не поднят.
              </p>
            </div>

            <div class="rounded-[1.75rem] border border-white/85 bg-white/85 px-5 py-4 shadow-sm shadow-slate-900/5">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Сеансов</p>
              <p class="mt-2 text-3xl font-semibold text-slate-950">{{ sessions.length }}</p>
            </div>
          </div>

          <div v-if="activeEvent" class="mt-8 grid gap-6 xl:grid-cols-[0.92fr_1.08fr]">
            <form class="space-y-5" @submit.prevent="submitSession">
              <div>
                <label class="field-label" for="session-hall-id">ID зала</label>
                <input
                  id="session-hall-id"
                  v-model="sessionForm.hall_id"
                  type="number"
                  class="field-input"
                  placeholder="101"
                />
              </div>

              <div>
                <label class="field-label" for="session-start-time">Начало сеанса</label>
                <input
                  id="session-start-time"
                  v-model="sessionForm.start_time"
                  type="datetime-local"
                  class="field-input"
                />
              </div>

              <div>
                <label class="field-label" for="session-end-time">Окончание сеанса</label>
                <input
                  id="session-end-time"
                  v-model="sessionForm.end_time"
                  type="datetime-local"
                  class="field-input"
                />
              </div>

              <div>
                <label class="field-label" for="session-base-price">Базовая цена</label>
                <input
                  id="session-base-price"
                  v-model="sessionForm.base_price"
                  type="number"
                  min="0"
                  step="0.01"
                  class="field-input"
                  placeholder="2500"
                />
              </div>

              <div class="flex flex-col gap-3 sm:flex-row">
                <button type="button" class="secondary-button" @click="resetSessionForm">
                  Сбросить форму
                </button>

                <button
                  :disabled="sessionSaving || !canSubmitSession"
                  type="submit"
                  class="primary-button sm:min-w-56"
                >
                  {{
                    sessionSaving
                      ? 'Сохраняем...'
                      : sessionForm.id
                        ? 'Обновить сеанс'
                        : 'Создать сеанс'
                  }}
                </button>
              </div>
            </form>

            <div>
              <div v-if="sessionsLoading" class="space-y-4">
                <div
                  v-for="item in 3"
                  :key="item"
                  class="h-28 animate-pulse rounded-[1.5rem] bg-slate-100"
                ></div>
              </div>

              <div v-else-if="sessions.length > 0" class="space-y-4">
                <article
                  v-for="session in sessions"
                  :key="session.id"
                  class="rounded-[1.5rem] border border-slate-200 bg-white px-5 py-5 shadow-sm shadow-slate-900/5"
                >
                  <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                      <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                        Зал {{ session.hall_id }}
                      </p>
                      <h4 class="mt-2 text-xl font-semibold text-slate-950">
                        {{ formatDateTime(session.start_time) }}
                      </h4>
                      <p class="mt-2 text-sm text-slate-500">
                        До {{ formatDateTime(session.end_time) }}
                      </p>
                      <p class="mt-3 text-sm font-semibold text-slate-900">
                        {{ formatPrice(session.base_price) }}
                      </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:items-end">
                      <span class="status-badge" :class="sessionStatusClasses(session.status)">
                        {{ session.status }}
                      </span>

                      <div class="flex flex-wrap gap-2">
                        <button type="button" class="secondary-button px-4 py-2.5" @click="editSession(session)">
                          Изменить
                        </button>
                        <button type="button" class="danger-button px-4 py-2.5" @click="cancelSession(session)">
                          Отменить
                        </button>
                      </div>
                    </div>
                  </div>
                </article>
              </div>

              <div v-else class="rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50/70 px-5 py-6">
                <p class="text-lg font-semibold text-slate-900">Сеансов пока нет</p>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                  После создания первого сеанса он появится в этом списке, и его можно будет
                  редактировать или отменять.
                </p>
              </div>
            </div>
          </div>

          <div v-else class="mt-8 rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50/70 px-6 py-8">
            <p class="text-lg font-semibold text-slate-900">Мероприятие еще не выбрано</p>
            <p class="mt-2 text-sm leading-6 text-slate-500">
              Создай новое мероприятие или выбери существующее слева, чтобы управлять его сеансами.
            </p>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>
