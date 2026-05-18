<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { getOrganizerBookingDashboardRequest } from '@/api/booking'
import { getOrganizerEventDashboardRequest } from '@/api/events'
import { getOrganizerHallRentalRequestsRequest } from '@/api/halls'
import { useAuthStore } from '@/stores/auth'
import type {
  OrganizerBookingDashboardResponse,
  OrganizerDashboardRecentBooking,
  OrganizerDashboardSalesDay,
  OrganizerDashboardTopEvent,
  OrganizerEventDashboardResponse,
} from '@/types/analytics'
import type { BookingStatus } from '@/types/booking'
import type { OrganizerEventStatus } from '@/types/event'
import type { HallRentalRequest, HallRentalRequestStatus } from '@/types/hall'
import { formatDate, formatDateTime, formatPrice } from '@/utils/format'

const authStore = useAuthStore()

const eventDashboard = ref<OrganizerEventDashboardResponse | null>(null)
const bookingDashboard = ref<OrganizerBookingDashboardResponse | null>(null)
const rentalRequests = ref<HallRentalRequest[]>([])

const loading = ref(false)
const error = ref('')

const organizerName = computed(() => {
  return authStore.user?.organizer_profile?.company_name || authStore.user?.full_name || 'Organizer'
})

const pendingRentalRequests = computed(() => rentalRequests.value.filter((request) => request.status === 'pending'))
const approvedRentalRequests = computed(() => rentalRequests.value.filter((request) => request.status === 'approved'))
const rejectedRentalRequests = computed(() => rentalRequests.value.filter((request) => request.status === 'rejected'))
const primaryMetrics = computed(() => {
  const bookingMetrics = bookingDashboard.value?.metrics
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
      hint: `Ждут оплаты: ${formatInteger((bookingMetrics?.bookings_reserved ?? 0) + (bookingMetrics?.bookings_payment_pending ?? 0))}`,
    },
    {
      label: 'Заявки на площадки',
      value: formatInteger(rentalRequests.value.length),
      tone: 'border border-blue-200 bg-blue-50 text-blue-900',
      hint: `Ожидают ответа: ${formatInteger(pendingRentalRequests.value.length)}`,
    },
    {
      label: 'События',
      value: formatInteger(eventMetrics?.events_total ?? 0),
      tone: 'border border-slate-200 bg-white text-slate-900',
      hint: `Опубликовано: ${formatInteger(eventMetrics?.events_published ?? 0)}`,
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
    const [events, bookings, requests] = await Promise.all([
      getOrganizerEventDashboardRequest(),
      getOrganizerBookingDashboardRequest(),
      getOrganizerHallRentalRequestsRequest({ per_page: 50 }),
    ])

    eventDashboard.value = events
    bookingDashboard.value = bookings
    rentalRequests.value = requests.data
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
    case 'pending_review':
      return 'На модерации'
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
    case 'pending_review':
      return 'border-blue-200 bg-blue-50 text-blue-700'
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

const rentalRequestStatusLabel = (status: HallRentalRequestStatus) => {
  switch (status) {
    case 'approved':
      return 'Подтверждена'
    case 'rejected':
      return 'Отклонена'
    case 'cancelled':
      return 'Отменена'
    default:
      return 'Ожидает ответа'
  }
}

const rentalRequestStatusClasses = (status: HallRentalRequestStatus) => {
  switch (status) {
    case 'approved':
      return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    case 'rejected':
      return 'border-rose-200 bg-rose-50 text-rose-700'
    case 'cancelled':
      return 'border-slate-200 bg-slate-100 text-slate-600'
    default:
      return 'border-blue-200 bg-blue-50 text-blue-700'
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
          <span class="info-chip">Organizer analytics</span>
          <h2 class="mt-4 text-3xl font-semibold leading-tight text-slate-950 sm:text-4xl">
            Дашборд {{ organizerName }}
          </h2>
          <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
            Здесь собраны события, продажи, активные резервы и статус заявок на площадки.
            Это уже рабочий экран, из которого организатор видит не только контент, но и весь
            путь подготовки мероприятия к продаже билетов.
          </p>
        </div>

        <div class="flex flex-wrap gap-3">
          <RouterLink to="/organizer/events" class="secondary-button">
            События
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

    <section v-if="loading" class="grid gap-6 xl:grid-cols-5">
      <article v-for="item in 5" :key="item" class="app-panel p-6">
        <div class="h-4 w-28 animate-pulse rounded-full bg-slate-100"></div>
        <div class="mt-6 h-10 w-3/4 animate-pulse rounded-2xl bg-slate-200"></div>
        <div class="mt-4 h-4 w-1/2 animate-pulse rounded-full bg-slate-100"></div>
      </article>
    </section>

    <template v-else>
      <section class="grid gap-6 xl:grid-cols-5">
        <article v-for="metric in primaryMetrics" :key="metric.label" class="app-panel overflow-hidden p-0">
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

      <section class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
        <article class="app-panel p-7">
          <div class="flex items-center justify-between gap-4">
            <div>
              <span class="info-chip">Последние 7 дней</span>
              <h3 class="mt-4 text-2xl font-semibold text-slate-950">Динамика продаж</h3>
            </div>

            <div class="rounded-[1.5rem] border border-blue-100 bg-blue-50 px-4 py-3 text-right">
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-600">Итого за период</p>
              <p class="mt-2 text-2xl font-semibold text-blue-950">
                {{ formatPrice(Number(bookingDashboard?.metrics.revenue_last_30_days ?? 0)) }}
              </p>
            </div>
          </div>

          <div v-if="salesTrend.length > 0" class="mt-8 grid gap-3 sm:grid-cols-7">
            <article v-for="day in salesTrend" :key="day.date" class="rounded-[1.5rem] border border-slate-200 bg-slate-50/80 px-3 py-4">
              <div class="flex h-40 items-end">
                <div class="w-full rounded-t-[1.1rem] bg-gradient-to-t from-blue-600 via-blue-500 to-slate-900 transition-all duration-300" :style="{ height: barHeight(day) }"></div>
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
          <h3 class="mt-4 text-2xl font-semibold text-slate-950">Лидеры по выручке</h3>

          <div v-if="topEvents.length > 0" class="mt-7 space-y-4">
            <article v-for="event in topEvents" :key="`${event.event_id}-${event.event_title}`" class="rounded-[1.5rem] border border-slate-200 bg-slate-50/80 px-4 py-4">
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
                <div class="h-full rounded-full bg-gradient-to-r from-blue-500 to-slate-900" :style="{ width: topBarHeight(event) }"></div>
              </div>
            </article>
          </div>
        </article>
      </section>

      <section class="grid gap-6 xl:grid-cols-2">
        <article class="app-panel p-7">
          <div class="flex items-center justify-between gap-4">
            <div>
              <span class="info-chip">Площадки</span>
              <h3 class="mt-4 text-2xl font-semibold text-slate-950">Заявки на аренду</h3>
            </div>

            <RouterLink to="/organizer/events" class="secondary-button">
              Открыть события
            </RouterLink>
          </div>

          <div v-if="rentalRequests.length > 0" class="mt-7 space-y-4">
            <article v-for="request in rentalRequests.slice(0, 6)" :key="request.id" class="rounded-[1.5rem] border border-slate-200 bg-white px-5 py-5 shadow-sm shadow-slate-900/5">
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                    {{ request.hall?.name || `Площадка #${request.hall_id}` }}
                  </p>
                  <p class="mt-2 text-lg font-semibold text-slate-950">
                    {{ formatDateTime(request.requested_start) }}
                  </p>
                  <p class="mt-2 text-sm text-slate-500">
                    До {{ formatDateTime(request.requested_end) }}
                  </p>
                  <p class="mt-2 text-sm text-slate-500">
                    {{ request.hall?.address || 'Адрес уточняется' }}
                  </p>
                </div>

                <span class="status-badge" :class="rentalRequestStatusClasses(request.status)">
                  {{ rentalRequestStatusLabel(request.status) }}
                </span>
              </div>

              <p class="mt-4 text-sm font-semibold text-slate-900">
                {{ formatPrice(request.total_amount) }}
              </p>
            </article>
          </div>
        </article>

        <article class="app-panel p-7">
          <div class="flex items-center justify-between gap-4">
            <div>
              <span class="info-chip">Живое расписание</span>
              <h3 class="mt-4 text-2xl font-semibold text-slate-950">Ближайшие сеансы</h3>
            </div>

            <RouterLink to="/organizer/events" class="secondary-button">
              Управлять
            </RouterLink>
          </div>

          <div v-if="eventDashboard?.upcoming_sessions.length" class="mt-7 space-y-4">
            <article v-for="session in eventDashboard.upcoming_sessions" :key="session.id" class="rounded-[1.5rem] border border-slate-200 bg-white px-5 py-5 shadow-sm shadow-slate-900/5">
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
        </article>
      </section>

      <section class="grid gap-6 xl:grid-cols-2">
        <article class="app-panel p-7">
          <div class="flex items-center justify-between gap-4">
            <div>
              <span class="info-chip">Заказы</span>
              <h3 class="mt-4 text-2xl font-semibold text-slate-950">Последние брони и покупки</h3>
            </div>
          </div>

          <div v-if="bookingDashboard?.recent_bookings.length" class="mt-7 space-y-4">
            <article v-for="booking in bookingDashboard.recent_bookings" :key="booking.id" class="rounded-[1.5rem] border border-slate-200 bg-white px-5 py-5 shadow-sm shadow-slate-900/5">
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
            </article>
          </div>
        </article>

        <article class="app-panel p-7">
          <div class="flex items-center justify-between gap-4">
            <div>
              <span class="info-chip">Контент</span>
              <h3 class="mt-4 text-2xl font-semibold text-slate-950">Последние изменения по событиям</h3>
            </div>
          </div>

          <div v-if="eventDashboard?.recent_events.length" class="mt-7 space-y-4">
            <article v-for="event in eventDashboard.recent_events" :key="event.id" class="rounded-[1.5rem] border border-slate-200 bg-slate-50/80 px-5 py-5">
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

          <div class="mt-7 grid gap-4 sm:grid-cols-3">
            <article class="rounded-[1.5rem] border border-blue-200 bg-blue-50/80 px-5 py-5">
              <p class="text-sm font-semibold uppercase tracking-[0.18em] text-blue-700">Ждут ответа</p>
              <p class="mt-3 text-3xl font-semibold text-blue-950">{{ formatInteger(pendingRentalRequests.length) }}</p>
            </article>

            <article class="rounded-[1.5rem] border border-emerald-200 bg-emerald-50/80 px-5 py-5">
              <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-700">Подтверждены</p>
              <p class="mt-3 text-3xl font-semibold text-emerald-950">{{ formatInteger(approvedRentalRequests.length) }}</p>
            </article>

            <article class="rounded-[1.5rem] border border-rose-200 bg-rose-50/80 px-5 py-5">
              <p class="text-sm font-semibold uppercase tracking-[0.18em] text-rose-700">Отклонены</p>
              <p class="mt-3 text-3xl font-semibold text-rose-950">{{ formatInteger(rejectedRentalRequests.length) }}</p>
            </article>
          </div>
        </article>
      </section>
    </template>
  </div>
</template>
