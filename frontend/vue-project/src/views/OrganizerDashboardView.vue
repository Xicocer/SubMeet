<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { getOrganizerBookingDashboardRequest } from '@/api/booking'
import { getOrganizerEventDashboardRequest } from '@/api/events'
import { getOrganizerHallDashboardRequest } from '@/api/halls'
import { useAuthStore } from '@/stores/auth'
import type {
  OrganizerBookingDashboardResponse,
  OrganizerDashboardRecentBooking,
  OrganizerDashboardSalesDay,
  OrganizerDashboardTopEvent,
  OrganizerEventDashboardResponse,
  OrganizerHallDashboardResponse,
} from '@/types/analytics'
import type { BookingStatus } from '@/types/booking'
import type { OrganizerEventStatus } from '@/types/event'
import type { HallStatus } from '@/types/hall'
import { formatDate, formatDateTime, formatPrice } from '@/utils/format'

const authStore = useAuthStore()

const eventDashboard = ref<OrganizerEventDashboardResponse | null>(null)
const hallDashboard = ref<OrganizerHallDashboardResponse | null>(null)
const bookingDashboard = ref<OrganizerBookingDashboardResponse | null>(null)

const loading = ref(false)
const error = ref('')

const organizerName = computed(() => {
  return authStore.user?.organizer_profile?.company_name || authStore.user?.full_name || 'Organizer'
})

const primaryMetrics = computed(() => {
  const bookingMetrics = bookingDashboard.value?.metrics
  const hallMetrics = hallDashboard.value?.metrics
  const eventMetrics = eventDashboard.value?.metrics

  return [
    {
      label: 'Выручка',
      value: formatPrice(Number(bookingMetrics?.revenue_total ?? 0)),
      tone: 'from-slate-950 via-slate-900 to-blue-900 text-white',
      hint: `За 30 дней: ${formatPrice(Number(bookingMetrics?.revenue_last_30_days ?? 0))}`,
    },
    {
      label: 'Продано билетов',
      value: formatInteger(bookingMetrics?.tickets_sold ?? 0),
      tone: 'border border-emerald-200 bg-emerald-50 text-emerald-900',
      hint: `Проверено на входе: ${formatInteger(bookingMetrics?.tickets_used ?? 0)}`,
    },
    {
      label: 'Активные резервы',
      value: formatInteger(bookingMetrics?.active_reservations ?? 0),
      tone: 'border border-amber-200 bg-amber-50 text-amber-900',
      hint: `Ожидают оплату: ${formatInteger((bookingMetrics?.bookings_reserved ?? 0) + (bookingMetrics?.bookings_payment_pending ?? 0))}`,
    },
    {
      label: 'Ресурс площадок',
      value: formatInteger(hallMetrics?.capacity_total ?? 0),
      tone: 'border border-blue-200 bg-blue-50 text-blue-900',
      hint: `Публикуемых событий: ${formatInteger(eventMetrics?.events_published ?? 0)}`,
    },
  ]
})

const secondaryMetrics = computed(() => {
  const eventMetrics = eventDashboard.value?.metrics
  const hallMetrics = hallDashboard.value?.metrics
  const bookingMetrics = bookingDashboard.value?.metrics

  return [
    {
      label: 'События',
      value: formatInteger(eventMetrics?.events_total ?? 0),
      hint: `Черновики: ${formatInteger(eventMetrics?.events_draft ?? 0)}`,
    },
    {
      label: 'Сеансы впереди',
      value: formatInteger(eventMetrics?.sessions_upcoming ?? 0),
      hint: `Всего в расписании: ${formatInteger(eventMetrics?.sessions_total ?? 0)}`,
    },
    {
      label: 'Активные залы',
      value: formatInteger(hallMetrics?.halls_active ?? 0),
      hint: `Средняя вместимость: ${formatInteger(hallMetrics?.capacity_average ?? 0)}`,
    },
    {
      label: 'Средний чек',
      value: formatPrice(Number(bookingMetrics?.average_order_value ?? 0)),
      hint: `Подтвержденных заказов: ${formatInteger(bookingMetrics?.bookings_confirmed ?? 0)}`,
    },
  ]
})

const salesTrend = computed(() => bookingDashboard.value?.sales_last_7_days ?? [])
const salesTrendMax = computed(() => {
  return Math.max(1, ...salesTrend.value.map((item) => Number(item.revenue ?? 0)))
})

const topEvents = computed(() => bookingDashboard.value?.top_events ?? [])
const topEventsMax = computed(() => {
  return Math.max(1, ...topEvents.value.map((item) => Number(item.revenue ?? 0)))
})

const loadDashboard = async () => {
  loading.value = true
  error.value = ''

  try {
    const [events, halls, bookings] = await Promise.all([
      getOrganizerEventDashboardRequest(),
      getOrganizerHallDashboardRequest(),
      getOrganizerBookingDashboardRequest(),
    ])

    eventDashboard.value = events
    hallDashboard.value = halls
    bookingDashboard.value = bookings
  } catch (requestError: any) {
    console.error(requestError)
    error.value = requestError?.response?.data?.message || 'Не удалось загрузить аналитику организатора.'
  } finally {
    loading.value = false
  }
}

const formatInteger = (value: number) => {
  return new Intl.NumberFormat('ru-RU').format(value)
}

const formatShortDay = (value: string) => {
  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: 'short',
  }).format(date)
}

const barHeight = (item: OrganizerDashboardSalesDay | OrganizerDashboardTopEvent) => {
  const value = Number(item.revenue ?? 0)

  if (value <= 0) {
    return '8%'
  }

  return `${Math.max(18, Math.round((value / salesTrendMax.value) * 100))}%`
}

const topBarHeight = (item: OrganizerDashboardTopEvent) => {
  const value = Number(item.revenue ?? 0)

  if (value <= 0) {
    return '8%'
  }

  return `${Math.max(14, Math.round((value / topEventsMax.value) * 100))}%`
}

const bookingStatusLabel = (status: BookingStatus) => {
  switch (status) {
    case 'confirmed':
      return 'Оплачен'
    case 'payment_pending':
      return 'Ожидает оплаты'
    case 'cancelled':
      return 'Отменен'
    case 'expired':
      return 'Истек'
    default:
      return 'Резерв'
  }
}

const bookingStatusClasses = (status: BookingStatus) => {
  switch (status) {
    case 'confirmed':
      return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    case 'payment_pending':
      return 'border-blue-200 bg-blue-50 text-blue-700'
    case 'cancelled':
      return 'border-rose-200 bg-rose-50 text-rose-700'
    case 'expired':
      return 'border-slate-200 bg-slate-100 text-slate-600'
    default:
      return 'border-amber-200 bg-amber-50 text-amber-700'
  }
}

const bookingFlowLabel = (flowType: OrganizerDashboardRecentBooking['flow_type']) => {
  return flowType === 'purchase' ? 'Покупка' : 'Резерв'
}

const eventStatusLabel = (status: OrganizerEventStatus) => {
  switch (status) {
    case 'published':
      return 'Опубликовано'
    case 'cancelled':
      return 'Отменено'
    case 'archived':
      return 'Архив'
    default:
      return 'Черновик'
  }
}

const eventStatusClasses = (status: OrganizerEventStatus) => {
  switch (status) {
    case 'published':
      return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    case 'cancelled':
      return 'border-rose-200 bg-rose-50 text-rose-700'
    case 'archived':
      return 'border-slate-200 bg-slate-100 text-slate-600'
    default:
      return 'border-amber-200 bg-amber-50 text-amber-700'
  }
}

const hallStatusLabel = (status: HallStatus) => {
  switch (status) {
    case 'active':
      return 'Активен'
    case 'archived':
      return 'Архив'
    default:
      return 'Черновик'
  }
}

const hallStatusClasses = (status: HallStatus) => {
  switch (status) {
    case 'active':
      return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    case 'archived':
      return 'border-slate-200 bg-slate-100 text-slate-600'
    default:
      return 'border-amber-200 bg-amber-50 text-amber-700'
  }
}

onMounted(async () => {
  if (!authStore.user) {
    await authStore.fetchMe()
  }

  await loadDashboard()
})
</script>

<template>
  <div class="space-y-6">
    <section class="app-panel relative overflow-hidden p-8 sm:p-10">
      <div class="absolute inset-x-0 top-0 h-40 bg-[radial-gradient(circle_at_top_left,rgba(37,99,235,0.18),transparent_48%),linear-gradient(120deg,rgba(15,23,42,0.06),transparent_55%)]"></div>
      <div class="relative flex flex-col gap-8 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
          <span class="info-chip">Organizer Analytics</span>
          <h2 class="mt-4 text-3xl font-semibold leading-tight text-slate-950 sm:text-4xl">
            Дашборд {{ organizerName }}
          </h2>
          <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
            Здесь собраны продажи, ближайшие сеансы, статус площадок и результат проверки билетов. Это уже тот экран, из которого организатор видит не только контент, но и живую динамику проекта.
          </p>
        </div>

        <div class="flex flex-wrap gap-3">
          <RouterLink to="/organizer/events" class="secondary-button">
            События
          </RouterLink>
          <RouterLink to="/organizer/halls" class="secondary-button">
            Залы
          </RouterLink>
          <RouterLink to="/organizer/tickets" class="secondary-button">
            Проверка билетов
          </RouterLink>
          <button type="button" class="primary-button" @click="loadDashboard">
            Обновить метрики
          </button>
        </div>
      </div>
    </section>

    <div v-if="error" class="message-error">
      {{ error }}
    </div>

    <section v-if="loading" class="grid gap-6 xl:grid-cols-4">
      <article
        v-for="item in 4"
        :key="item"
        class="app-panel p-6"
      >
        <div class="h-4 w-28 animate-pulse rounded-full bg-slate-100"></div>
        <div class="mt-6 h-10 w-3/4 animate-pulse rounded-2xl bg-slate-200"></div>
        <div class="mt-4 h-4 w-1/2 animate-pulse rounded-full bg-slate-100"></div>
      </article>
    </section>

    <template v-else>
      <section class="grid gap-6 xl:grid-cols-4">
        <article
          v-for="metric in primaryMetrics"
          :key="metric.label"
          class="app-panel overflow-hidden p-0"
        >
          <div class="h-full rounded-[1.75rem] px-6 py-6" :class="metric.tone">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] opacity-70">
              {{ metric.label }}
            </p>
            <p class="mt-5 text-3xl font-semibold tracking-tight">
              {{ metric.value }}
            </p>
            <p class="mt-4 text-sm opacity-80">
              {{ metric.hint }}
            </p>
          </div>
        </article>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article
          v-for="metric in secondaryMetrics"
          :key="metric.label"
          class="app-panel p-5"
        >
          <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
            {{ metric.label }}
          </p>
          <p class="mt-3 text-2xl font-semibold text-slate-950">
            {{ metric.value }}
          </p>
          <p class="mt-2 text-sm text-slate-500">
            {{ metric.hint }}
          </p>
        </article>
      </section>

      <section class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
        <article class="app-panel p-7">
          <div class="flex items-center justify-between gap-4">
            <div>
              <span class="info-chip">Последние 7 дней</span>
              <h3 class="mt-4 text-2xl font-semibold text-slate-950">
                Динамика продаж
              </h3>
            </div>

            <div class="rounded-[1.5rem] border border-blue-100 bg-blue-50 px-4 py-3 text-right">
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-600">Итого за период</p>
              <p class="mt-2 text-2xl font-semibold text-blue-950">
                {{ formatPrice(Number(bookingDashboard?.metrics.revenue_last_30_days ?? 0)) }}
              </p>
            </div>
          </div>

          <div
            v-if="salesTrend.length > 0"
            class="mt-8 grid gap-3 sm:grid-cols-7"
          >
            <article
              v-for="day in salesTrend"
              :key="day.date"
              class="rounded-[1.5rem] border border-slate-200 bg-slate-50/80 px-3 py-4"
            >
              <div class="flex h-40 items-end">
                <div
                  class="w-full rounded-t-[1.1rem] bg-gradient-to-t from-blue-600 via-blue-500 to-slate-900 transition-all duration-300"
                  :style="{ height: barHeight(day) }"
                ></div>
              </div>
              <p class="mt-4 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                {{ formatShortDay(day.date) }}
              </p>
              <p class="mt-2 text-lg font-semibold text-slate-950">
                {{ formatPrice(Number(day.revenue)) }}
              </p>
              <p class="mt-1 text-sm text-slate-500">
                Билетов: {{ formatInteger(day.tickets_sold) }}
              </p>
            </article>
          </div>
        </article>

        <article class="app-panel p-7">
          <span class="info-chip">Топ событий</span>
          <h3 class="mt-4 text-2xl font-semibold text-slate-950">
            Лидеры по выручке
          </h3>

          <div v-if="topEvents.length > 0" class="mt-7 space-y-4">
            <article
              v-for="event in topEvents"
              :key="`${event.event_id}-${event.event_title}`"
              class="rounded-[1.5rem] border border-slate-200 bg-slate-50/80 px-4 py-4"
            >
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <p class="truncate text-lg font-semibold text-slate-950">
                    {{ event.event_title || 'Событие без названия' }}
                  </p>
                  <p class="mt-1 text-sm text-slate-500">
                    Заказов: {{ formatInteger(event.bookings_confirmed) }} · Билетов: {{ formatInteger(event.tickets_sold) }}
                  </p>
                </div>

                <p class="text-right text-lg font-semibold text-blue-700">
                  {{ formatPrice(Number(event.revenue)) }}
                </p>
              </div>

              <div class="mt-4 h-3 overflow-hidden rounded-full bg-slate-200">
                <div
                  class="h-full rounded-full bg-gradient-to-r from-blue-500 to-slate-900"
                  :style="{ width: topBarHeight(event) }"
                ></div>
              </div>
            </article>
          </div>

          <div v-else class="mt-7 rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50/70 px-5 py-6 text-sm text-slate-500">
            После первых оплаченных билетов здесь появятся самые сильные события по продажам.
          </div>
        </article>
      </section>

      <section class="grid gap-6 xl:grid-cols-2">
        <article class="app-panel p-7">
          <div class="flex items-center justify-between gap-4">
            <div>
              <span class="info-chip">Живое расписание</span>
              <h3 class="mt-4 text-2xl font-semibold text-slate-950">
                Ближайшие сеансы
              </h3>
            </div>

            <RouterLink to="/organizer/events" class="secondary-button">
              Управлять
            </RouterLink>
          </div>

          <div v-if="eventDashboard?.upcoming_sessions.length" class="mt-7 space-y-4">
            <article
              v-for="session in eventDashboard.upcoming_sessions"
              :key="session.id"
              class="rounded-[1.5rem] border border-slate-200 bg-white px-5 py-5 shadow-sm shadow-slate-900/5"
            >
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                    {{ formatDateTime(session.start_time) }}
                  </p>
                  <p class="mt-2 text-lg font-semibold text-slate-950">
                    {{ session.event_title || `Событие #${session.event_id}` }}
                  </p>
                  <p class="mt-2 text-sm text-slate-500">
                    До {{ formatDateTime(session.end_time) }}
                  </p>
                </div>

                <p class="text-right text-lg font-semibold text-blue-700">
                  {{ formatPrice(Number(session.base_price)) }}
                </p>
              </div>
            </article>
          </div>

          <div v-else class="mt-7 rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50/70 px-5 py-6 text-sm text-slate-500">
            Пока нет будущих сеансов. Как только в расписании появятся новые даты, они отобразятся здесь.
          </div>
        </article>

        <article class="app-panel p-7">
          <div class="flex items-center justify-between gap-4">
            <div>
              <span class="info-chip">Инвентарь площадок</span>
              <h3 class="mt-4 text-2xl font-semibold text-slate-950">
                Последние обновления залов
              </h3>
            </div>

            <RouterLink to="/organizer/halls" class="secondary-button">
              Открыть
            </RouterLink>
          </div>

          <div v-if="hallDashboard?.recent_halls.length" class="mt-7 space-y-4">
            <article
              v-for="hall in hallDashboard.recent_halls"
              :key="hall.id"
              class="rounded-[1.5rem] border border-slate-200 bg-slate-50/80 px-5 py-5"
            >
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <p class="truncate text-lg font-semibold text-slate-950">
                    {{ hall.name }}
                  </p>
                  <p class="mt-2 text-sm text-slate-500">
                    {{ hall.address || 'Адрес пока не указан' }}
                  </p>
                </div>

                <span class="status-badge" :class="hallStatusClasses(hall.status)">
                  {{ hallStatusLabel(hall.status) }}
                </span>
              </div>

              <div class="mt-4 flex items-center justify-between gap-4 text-sm text-slate-500">
                <span>Вместимость: {{ formatInteger(hall.total_capacity) }}</span>
                <span>{{ formatDate(hall.updated_at) }}</span>
              </div>
            </article>
          </div>
        </article>
      </section>

      <section class="grid gap-6 xl:grid-cols-2">
        <article class="app-panel p-7">
          <div class="flex items-center justify-between gap-4">
            <div>
              <span class="info-chip">Заказы</span>
              <h3 class="mt-4 text-2xl font-semibold text-slate-950">
                Последние бронирования и покупки
              </h3>
            </div>

            <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 px-4 py-3 text-right">
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Всего заказов</p>
              <p class="mt-2 text-xl font-semibold text-slate-950">
                {{ formatInteger(bookingDashboard?.metrics.bookings_total ?? 0) }}
              </p>
            </div>
          </div>

          <div v-if="bookingDashboard?.recent_bookings.length" class="mt-7 space-y-4">
            <article
              v-for="booking in bookingDashboard.recent_bookings"
              :key="booking.id"
              class="rounded-[1.5rem] border border-slate-200 bg-white px-5 py-5 shadow-sm shadow-slate-900/5"
            >
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <p class="truncate text-lg font-semibold text-slate-950">
                    {{ booking.event_title || `Заказ #${booking.id}` }}
                  </p>
                  <p class="mt-2 text-sm text-slate-500">
                    {{ booking.hall_name || 'Площадка не указана' }}
                  </p>
                  <p class="mt-2 text-sm text-slate-500">
                    {{ bookingFlowLabel(booking.flow_type) }} · Билетов: {{ formatInteger(booking.tickets_count) }}
                  </p>
                </div>

                <div class="text-right">
                  <span class="status-badge" :class="bookingStatusClasses(booking.status)">
                    {{ bookingStatusLabel(booking.status) }}
                  </span>
                  <p class="mt-3 text-lg font-semibold text-blue-700">
                    {{ formatPrice(Number(booking.total_amount)) }}
                  </p>
                </div>
              </div>

              <p class="mt-4 text-sm text-slate-500">
                {{
                  booking.confirmed_at
                    ? `Оплачен ${formatDateTime(booking.confirmed_at)}`
                    : `Создан ${formatDateTime(booking.created_at)}`
                }}
              </p>
            </article>
          </div>
        </article>

        <article class="app-panel p-7">
          <div class="flex items-center justify-between gap-4">
            <div>
              <span class="info-chip">Входной контроль</span>
              <h3 class="mt-4 text-2xl font-semibold text-slate-950">
                Последние проходы по билетам
              </h3>
            </div>

            <RouterLink to="/organizer/tickets" class="secondary-button">
              Сканировать
            </RouterLink>
          </div>

          <div v-if="bookingDashboard?.recent_check_ins.length" class="mt-7 space-y-4">
            <article
              v-for="checkIn in bookingDashboard.recent_check_ins"
              :key="`${checkIn.booking_id}-${checkIn.ticket_code}`"
              class="rounded-[1.5rem] border border-emerald-200 bg-emerald-50/70 px-5 py-5"
            >
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <p class="truncate text-lg font-semibold text-emerald-950">
                    {{ checkIn.event_title || `Билет #${checkIn.booking_id}` }}
                  </p>
                  <p class="mt-2 text-sm text-emerald-700/80">
                    {{ checkIn.hall_name || 'Площадка не указана' }}
                  </p>
                  <p class="mt-2 text-sm text-emerald-700/80">
                    Код: {{ checkIn.ticket_code || '—' }}
                  </p>
                </div>

                <div class="text-right text-emerald-950">
                  <p class="text-sm font-semibold uppercase tracking-[0.16em]">
                    Прошло
                  </p>
                  <p class="mt-2 text-2xl font-semibold">
                    {{ formatInteger(checkIn.tickets_used) }}
                  </p>
                </div>
              </div>

              <p class="mt-4 text-sm text-emerald-700/80">
                {{ formatDateTime(checkIn.used_at) }}
              </p>
            </article>
          </div>

          <div v-else class="mt-7 rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50/70 px-5 py-6 text-sm text-slate-500">
            Пока нет отмеченных проходов. После первых проверок билетами здесь появится живая лента входного контроля.
          </div>
        </article>
      </section>

      <section class="grid gap-6 xl:grid-cols-2">
        <article class="app-panel p-7">
          <span class="info-chip">Контент</span>
          <h3 class="mt-4 text-2xl font-semibold text-slate-950">
            Последние изменения по событиям
          </h3>

          <div v-if="eventDashboard?.recent_events.length" class="mt-7 space-y-4">
            <article
              v-for="event in eventDashboard.recent_events"
              :key="event.id"
              class="rounded-[1.5rem] border border-slate-200 bg-slate-50/80 px-5 py-5"
            >
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <p class="truncate text-lg font-semibold text-slate-950">
                    {{ event.title }}
                  </p>
                  <p class="mt-2 text-sm text-slate-500">
                    Изменено {{ formatDate(event.updated_at) }}
                  </p>
                </div>

                <span class="status-badge" :class="eventStatusClasses(event.status)">
                  {{ eventStatusLabel(event.status) }}
                </span>
              </div>
            </article>
          </div>
        </article>

        <article class="app-panel p-7">
          <span class="info-chip">Что уже работает</span>
          <h3 class="mt-4 text-2xl font-semibold text-slate-950">
            MVP по организатору почти замкнут
          </h3>

          <div class="mt-7 grid gap-4 sm:grid-cols-2">
            <article class="rounded-[1.5rem] border border-slate-200 bg-slate-50/80 px-5 py-5">
              <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Контент</p>
              <p class="mt-3 text-sm leading-6 text-slate-600">
                Создание событий, тегов, карточек и расписания сеансов уже замкнуто на отдельный контур организатора.
              </p>
            </article>

            <article class="rounded-[1.5rem] border border-slate-200 bg-slate-50/80 px-5 py-5">
              <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Площадки</p>
              <p class="mt-3 text-sm leading-6 text-slate-600">
                Залы редактируются в отдельном конструкторе, связаны с сеансами и уже попадают в бронирование и карточки событий.
              </p>
            </article>

            <article class="rounded-[1.5rem] border border-slate-200 bg-slate-50/80 px-5 py-5">
              <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Продажи</p>
              <p class="mt-3 text-sm leading-6 text-slate-600">
                Бронирование, demo-checkout, PDF-билет и контроль гонок на местах уже работают как единый пользовательский сценарий.
              </p>
            </article>

            <article class="rounded-[1.5rem] border border-slate-200 bg-slate-50/80 px-5 py-5">
              <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Вход</p>
              <p class="mt-3 text-sm leading-6 text-slate-600">
                Проверка QR и отметка использованного билета уже собраны, а теперь сверху на это лег и общий аналитический дашборд.
              </p>
            </article>
          </div>
        </article>
      </section>
    </template>
  </div>
</template>
