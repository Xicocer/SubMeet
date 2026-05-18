<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BookingModal from '@/components/BookingModal.vue'
import WantToGoButton from '@/components/WantToGoButton.vue'
import {
  addWantToGoRequest,
  getEventRequest,
  getEventSessionsRequest,
  removeWantToGoRequest,
} from '@/api/events'
import { useAuthStore } from '@/stores/auth'
import type { UserBooking } from '@/types/booking'
import type { EventDetails, EventSession } from '@/types/event'
import { formatDate, formatDateTime, formatPrice } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const event = ref<EventDetails | null>(null)
const sessions = ref<EventSession[]>([])
const loading = ref(false)
const error = ref('')
const bookingModalOpen = ref(false)
const bookingSession = ref<EventSession | null>(null)
const bookingNotice = ref('')
const wantToGoLoading = ref(false)

const eventId = computed(() => Number(route.params.id))

const canManageEvent = computed(() => {
  return authStore.isOrganizer && authStore.user?.id === event.value?.organizer_id
})

const nextSession = computed(() => sessions.value[0] ?? null)
const isTeaser = computed(() => Boolean(event.value?.is_teaser) || (
  event.value?.has_available_sessions === false && sessions.value.length === 0
))

const minimumPrice = computed(() => {
  if (sessions.value.length === 0) {
    return null
  }

  return sessions.value.reduce<number | null>((lowestPrice, session) => {
    const currentPrice = Number(session.base_price)

    if (Number.isNaN(currentPrice)) {
      return lowestPrice
    }

    if (lowestPrice === null || currentPrice < lowestPrice) {
      return currentPrice
    }

    return lowestPrice
  }, null)
})

const sessionCountLabel = computed(() => {
  if (isTeaser.value) {
    return 'Тизер'
  }

  if (sessions.value.length === 1) {
    return '1 открытый сеанс'
  }

  if (sessions.value.length > 1 && sessions.value.length < 5) {
    return `${sessions.value.length} открытых сеанса`
  }

  return `${sessions.value.length} открытых сеансов`
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

const openBookingModal = (session: EventSession) => {
  bookingNotice.value = ''
  bookingSession.value = session
  bookingModalOpen.value = true
}

const closeBookingModal = () => {
  bookingModalOpen.value = false
}

const handleBookingCreated = (booking: UserBooking) => {
  const eventTitle = booking.session?.event_title || event.value?.title || 'мероприятие'

  bookingNotice.value =
    booking.reserved_until
      ? `Бронь на «${eventTitle}» создана. Место удерживается до ${formatDateTime(booking.reserved_until)}.`
      : `Бронь на «${eventTitle}» успешно создана.`
}

const toggleWantToGo = async () => {
  if (!event.value) {
    return
  }

  if (!authStore.isAuthenticated) {
    await router.push({
      name: 'login',
      query: {
        redirect: route.fullPath,
      },
    })

    return
  }

  if (authStore.isOrganizer || wantToGoLoading.value) {
    return
  }

  wantToGoLoading.value = true

  try {
    if (event.value.is_wanted) {
      await removeWantToGoRequest(event.value.id)
      event.value = {
        ...event.value,
        is_wanted: false,
      }
    } else {
      await addWantToGoRequest(event.value.id)
      event.value = {
        ...event.value,
        is_wanted: true,
      }
    }
  } catch (requestError) {
    console.error(requestError)
  } finally {
    wantToGoLoading.value = false
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
        <div class="grid xl:grid-cols-[0.94fr_1.06fr]">
          <div
            v-if="event.poster_url"
            class="min-h-[400px] bg-cover bg-center"
            :style="{ backgroundImage: `linear-gradient(rgba(15, 23, 42, 0.08), rgba(15, 23, 42, 0.58)), url(${event.poster_url})` }"
          ></div>
          <div
            v-else
            class="flex min-h-[400px] items-end bg-gradient-to-br from-slate-950 via-sky-900 to-emerald-700 p-8 text-white"
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
              <span
                class="status-badge"
                :class="isTeaser
                  ? 'border-amber-200 bg-amber-50 text-amber-800'
                  : 'border-emerald-200 bg-emerald-50 text-emerald-700'"
              >
                {{ sessionCountLabel }}
              </span>
            </div>

            <div v-if="event.tags.length > 0" class="mt-5 flex flex-wrap gap-2">
              <span
                v-for="tag in event.tags"
                :key="tag.id"
                class="rounded-full border px-3 py-1 text-xs font-semibold"
                :class="tag.slug === 'teaser'
                  ? 'border-amber-200 bg-amber-300 text-slate-950'
                  : 'border-blue-100 bg-blue-50 text-blue-700'"
              >
                #{{ tag.name }}
              </span>
            </div>

            <h1 class="mt-5 text-4xl font-semibold leading-tight text-slate-950">
              {{ event.title }}
            </h1>

            <p class="mt-4 text-sm leading-7 text-slate-600 sm:text-base">
              {{ event.description || 'Описание пока короткое, но уже можно выбрать сеанс, открыть схему зала и сразу забронировать место.' }}
            </p>

            <div class="mt-8 grid gap-4 sm:grid-cols-2">
              <article class="soft-card">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                  Организатор
                </p>
                <p class="mt-3 text-lg font-semibold text-slate-900">
                  {{ event.organizer?.display_name || event.organizer?.full_name || `ID ${event.organizer_id}` }}
                </p>
                <p class="mt-2 text-sm text-slate-500">
                  {{ event.organizer?.email || 'Контакты появятся позже' }}
                </p>
              </article>

              <article class="soft-card">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                  Ближайший сеанс
                </p>
                <p class="mt-3 text-lg font-semibold text-slate-900">
                  {{ nextSession ? formatDateTime(nextSession.start_time) : (isTeaser ? 'Расписание скоро' : 'Пока нет в расписании') }}
                </p>
                <p class="mt-2 text-sm text-slate-500">
                  {{ nextSession ? `от ${formatPrice(nextSession.base_price)}` : (isTeaser ? 'Добавь в “Хочу сходить”, чтобы не потерять анонс' : 'Организатор еще не открыл продажу') }}
                </p>
              </article>

              <article class="soft-card">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                  Минимальная цена
                </p>
                <p class="mt-3 text-lg font-semibold text-slate-900">
                  {{ minimumPrice !== null ? formatPrice(minimumPrice) : (isTeaser ? 'После открытия продаж' : 'Скоро появится') }}
                </p>
                <p class="mt-2 text-sm text-slate-500">
                  Точная стоимость зависит от конкретного места или танцпола в выбранном сеансе.
                </p>
              </article>

              <article class="soft-card">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                  Обновлено
                </p>
                <p class="mt-3 text-lg font-semibold text-slate-900">
                  {{ formatDate(event.updated_at) }}
                </p>
                <p class="mt-2 text-sm text-slate-500">
                  Карточка опубликована {{ formatDate(event.created_at) }}
                </p>
              </article>
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
              <RouterLink to="/events" class="secondary-button">
                Вернуться в афишу
              </RouterLink>

              <a v-if="!isTeaser" href="#event-sessions" class="primary-button">
                Выбрать сеанс
              </a>

              <span
                v-else
                class="inline-flex items-center rounded-2xl border border-amber-200 bg-amber-50 px-5 py-3 text-sm font-semibold text-amber-800"
              >
                Расписание появится позже
              </span>

              <WantToGoButton
                v-if="!authStore.isOrganizer"
                :active="event.is_wanted"
                :loading="wantToGoLoading"
                @toggle="toggleWantToGo"
              />

              <RouterLink
                v-if="canManageEvent"
                to="/organizer/events"
                class="secondary-button"
              >
                Управлять событием
              </RouterLink>
            </div>
          </div>
        </div>
      </section>

      <section class="app-panel p-8 sm:p-10">
        <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
          <div>
            <span class="info-chip">О событии</span>
            <h2 class="mt-4 text-3xl font-semibold text-slate-950">
              Что важно знать перед бронированием
            </h2>
            <div class="mt-6 rounded-[1.75rem] border border-slate-200 bg-slate-50/80 p-6">
              <p class="text-base leading-8 text-slate-700">
                {{ event.description || 'Выбери сеанс, открой схему зала и забронируй подходящее место прямо из карточки события.' }}
              </p>
            </div>
          </div>

          <div class="space-y-4">
            <article v-if="event.tags.length > 0" class="soft-card">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Теги</p>
              <div class="mt-3 flex flex-wrap gap-2">
                <span
                  v-for="tag in event.tags"
                  :key="tag.id"
                  class="rounded-full border px-3 py-1 text-sm font-medium"
                  :class="tag.slug === 'teaser'
                    ? 'border-amber-200 bg-amber-300 text-slate-950'
                    : 'border-slate-200 bg-slate-50 text-slate-600'"
                >
                  #{{ tag.name }}
                </span>
              </div>
            </article>

            <article class="soft-card">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Категория</p>
              <p class="mt-3 text-xl font-semibold text-slate-950">{{ event.category?.name || 'Не указана' }}</p>
            </article>

            <article class="soft-card">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Возрастной рейтинг</p>
              <p class="mt-3 text-xl font-semibold text-slate-950">
                {{ event.age_rating?.label || 'Не указан' }}
              </p>
              <p class="mt-2 text-sm text-slate-500">
                {{ event.age_rating ? `Минимальный возраст: ${event.age_rating.min_age}+` : 'Возрастная маркировка будет уточнена.' }}
              </p>
            </article>
          </div>
        </div>
      </section>

      <section id="event-sessions" class="app-panel p-8 sm:p-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <span class="info-chip">Сеансы</span>
            <h2 class="mt-4 text-3xl font-semibold text-slate-950">
              {{ isTeaser ? 'Событие опубликовано как тизер' : 'Доступные сеансы для выбора и бронирования' }}
            </h2>
            <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
              {{ isTeaser ? 'Организатор уже анонсировал мероприятие, а точные даты, площадка и билеты появятся после подтверждения сеансов.' : 'Открой любой актуальный сеанс и сразу перейди к финальной схеме зала с доступными местами.' }}
            </p>
          </div>

          <div class="rounded-[1.75rem] border border-white/85 bg-white/85 px-5 py-4 shadow-sm shadow-slate-900/5">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
              {{ isTeaser ? 'Статус' : 'Сеансов открыто' }}
            </p>
            <p class="mt-2 text-3xl font-semibold text-slate-950">{{ isTeaser ? 'Тизер' : sessions.length }}</p>
          </div>
        </div>

        <div
          v-if="isTeaser"
          class="mt-8 rounded-[1.75rem] border border-amber-200 bg-amber-50 px-6 py-5 text-sm leading-6 text-amber-900"
        >
          Это тизер: событие уже прошло публикацию, но организатор еще не открыл сеансы. Добавь его в «Хочу сходить», и в профиле будет видно, когда появится ближайшая дата и цена.
        </div>

        <div v-if="sessions.length > 0" class="mt-8 grid gap-4 lg:grid-cols-2">
          <div v-if="bookingNotice" class="message-success lg:col-span-2">
            {{ bookingNotice }}
          </div>

          <article
            v-for="session in sessions"
            :key="session.id"
            class="soft-card"
          >
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                  {{ session.hall?.name || `Зал #${session.hall_id}` }}
                </p>
                <h3 class="mt-2 text-2xl font-semibold text-slate-950">
                  {{ formatDateTime(session.start_time) }}
                </h3>
                <p class="mt-2 text-sm text-slate-500">
                  До {{ formatDateTime(session.end_time) }}
                </p>
                <p v-if="session.hall?.address" class="mt-2 text-sm text-slate-500">
                  {{ session.hall.address }}
                </p>
              </div>

              <span class="status-badge border-emerald-200 bg-emerald-50 text-emerald-700">
                {{ session.status }}
              </span>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
              <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Цена</p>
                <p class="mt-2 text-lg font-semibold text-slate-950">{{ formatPrice(session.base_price) }}</p>
              </div>

              <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Зал</p>
                <p class="mt-2 text-lg font-semibold text-slate-950">
                  {{ session.hall?.capacities?.total ? `${session.hall.capacities.total} мест` : `ID ${session.hall_id}` }}
                </p>
              </div>
            </div>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <p class="text-sm leading-6 text-slate-500">
                Открой схему зала в модальном окне, выбери свободное место и сразу увидишь его стоимость перед бронью.
              </p>

              <button
                type="button"
                class="primary-button sm:min-w-56"
                @click="openBookingModal(session)"
              >
                Забронировать
              </button>
            </div>
          </article>
        </div>

        <div v-else class="mt-8 rounded-[1.75rem] border border-dashed border-slate-200 bg-slate-50/70 px-6 py-8">
          <p class="text-lg font-semibold text-slate-900">
            {{ isTeaser ? 'Расписание готовится' : 'Сеансы пока не добавлены' }}
          </p>
          <p class="mt-2 text-sm leading-6 text-slate-500">
            {{ isTeaser ? 'Как только появится подтвержденный сеанс, карточка перестанет быть тизером и откроет покупку билетов.' : 'Когда организатор создаст расписание, доступные даты и цены появятся здесь автоматически.' }}
          </p>
        </div>
      </section>
    </template>
  </div>

  <BookingModal
    :open="bookingModalOpen"
    :session="bookingSession"
    @close="closeBookingModal"
    @booked="handleBookingCreated"
  />
</template>
