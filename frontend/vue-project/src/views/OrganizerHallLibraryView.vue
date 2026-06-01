<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  archiveVenueHallRequest,
  createVenueHallUnavailablePeriodRequest,
  deleteVenueHallUnavailablePeriodRequest,
  getVenueHallAvailabilityRequest,
  getVenueHallRentalRequestsRequest,
  getVenueHallsRequest,
  updateVenueHallRentalRequestStatus,
} from '@/api/halls'
import type {
  HallAvailabilityDay,
  HallAvailabilityPeriod,
  HallAvailabilityResponse,
  HallRentalRequest,
  HallRentalRequestStatus,
  HallStatus,
  HallSummary,
} from '@/types/hall'
import { formatDate, formatDateTime, formatPrice } from '@/utils/format'

const router = useRouter()
const pad2 = (value: number) => String(value).padStart(2, '0')
const toMonthValue = (date: Date) => `${date.getFullYear()}-${pad2(date.getMonth() + 1)}`
const getMonthRange = (monthValue: string) => {
  const [yearValue, monthIndexValue] = monthValue.split('-').map(Number)
  const safeYear = Number.isFinite(yearValue) ? Number(yearValue) : new Date().getFullYear()
  const safeMonth = Number.isFinite(monthIndexValue) ? Number(monthIndexValue) : 1
  const firstDay = new Date(safeYear, safeMonth - 1, 1)
  const lastDay = new Date(safeYear, safeMonth, 0)

  return {
    firstDay,
    from: `${firstDay.getFullYear()}-${pad2(firstDay.getMonth() + 1)}-01`,
    to: `${lastDay.getFullYear()}-${pad2(lastDay.getMonth() + 1)}-${pad2(lastDay.getDate())}`,
  }
}
const formatCalendarMonth = (monthValue: string) => {
  const { firstDay } = getMonthRange(monthValue)

  return new Intl.DateTimeFormat('ru-RU', {
    month: 'long',
    year: 'numeric',
  }).format(firstDay)
}
const formatDateKey = (dateKey: string) => {
  if (!dateKey) {
    return 'Дата не выбрана'
  }

  return new Intl.DateTimeFormat('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(new Date(`${dateKey}T00:00:00`))
}
const addDaysToDateKey = (dateKey: string, days: number) => {
  const date = new Date(`${dateKey}T00:00:00`)
  date.setDate(date.getDate() + days)

  return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`
}
const todayDateKey = () => {
  const today = new Date()

  return `${today.getFullYear()}-${pad2(today.getMonth() + 1)}-${pad2(today.getDate())}`
}

const halls = ref<HallSummary[]>([])
const rentalRequests = ref<HallRentalRequest[]>([])
const loading = ref(false)
const requestsLoading = ref(false)
const saving = ref(false)
const technicalCalendarOpen = ref(false)
const technicalCalendarLoading = ref(false)
const technicalCalendarSaving = ref(false)
const requestActionIds = ref<number[]>([])
const error = ref('')
const success = ref('')
const technicalCalendarError = ref('')
const selectedTechnicalHall = ref<HallSummary | null>(null)
const hallAvailability = ref<HallAvailabilityResponse | null>(null)
const technicalCalendarMonth = ref(toMonthValue(new Date()))
const technicalReason = ref('Технические работы')
const weekDayLabels = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']

const filters = reactive({
  search: '',
  status: '' as HallStatus | '',
})

const requestFilters = reactive({
  status: '' as HallRentalRequestStatus | '',
})

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  total: 0,
  per_page: 9,
})

const draftCount = computed(() => halls.value.filter((hall) => hall.status === 'draft').length)
const activeCount = computed(() => halls.value.filter((hall) => hall.status === 'active').length)
const pendingRequestsCount = computed(() => rentalRequests.value.filter((request) => request.status === 'pending').length)
const approvedRequestsCount = computed(() => rentalRequests.value.filter((request) => request.status === 'approved').length)
const technicalCalendarCells = computed<Array<HallAvailabilityDay | null>>(() => {
  const { firstDay } = getMonthRange(technicalCalendarMonth.value)
  const leadingEmptyDays = (firstDay.getDay() + 6) % 7

  return [
    ...Array.from({ length: leadingEmptyDays }, () => null),
    ...(hallAvailability.value?.days ?? []),
  ]
})
const unavailablePeriodsForCalendar = computed(() => hallAvailability.value?.unavailable_periods ?? [])

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
      return 'border-slate-200 bg-slate-100 text-slate-700'
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
      return 'border-slate-200 bg-slate-100 text-slate-700'
    default:
      return 'border-blue-200 bg-blue-50 text-blue-700'
  }
}

const extractErrorMessage = (requestError: any, fallback: string) => {
  const validationErrors = requestError?.response?.data?.errors

  if (validationErrors && typeof validationErrors === 'object') {
    const firstField = Object.values(validationErrors)[0]

    if (Array.isArray(firstField) && firstField.length > 0) {
      return String(firstField[0])
    }
  }

  return requestError?.response?.data?.message || fallback
}

const loadHalls = async (page = 1) => {
  loading.value = true
  error.value = ''

  try {
    const response = await getVenueHallsRequest({
      page,
      per_page: pagination.per_page,
      search: filters.search.trim() || undefined,
      status: filters.status || undefined,
    })

    halls.value = response.data
    pagination.current_page = response.current_page
    pagination.last_page = response.last_page
    pagination.total = response.total
  } catch (requestError) {
    console.error(requestError)
    error.value = extractErrorMessage(requestError, 'Не удалось загрузить список залов.')
  } finally {
    loading.value = false
  }
}

const loadRentalRequests = async () => {
  requestsLoading.value = true

  try {
    const response = await getVenueHallRentalRequestsRequest({
      per_page: 30,
      status: requestFilters.status || undefined,
    })

    rentalRequests.value = response.data
  } catch (requestError) {
    console.error(requestError)
    error.value = extractErrorMessage(requestError, 'Не удалось загрузить входящие заявки на аренду.')
    rentalRequests.value = []
  } finally {
    requestsLoading.value = false
  }
}

const isFutureTechnicalDay = (dateKey: string) => dateKey > todayDateKey()

const availabilityPeriodOverlapsDay = (period: HallAvailabilityPeriod, dateKey: string) => {
  if (!period.start || !period.end) {
    return false
  }

  const dayStart = new Date(`${dateKey}T00:00:00`).getTime()
  const dayEnd = new Date(`${dateKey}T23:59:59`).getTime()
  const periodStart = new Date(period.start).getTime()
  const periodEnd = new Date(period.end).getTime()

  return Number.isFinite(periodStart)
    && Number.isFinite(periodEnd)
    && periodStart <= dayEnd
    && periodEnd >= dayStart
}

const findUnavailablePeriodForDay = (dateKey: string) => {
  return unavailablePeriodsForCalendar.value.find((period) => availabilityPeriodOverlapsDay(period, dateKey)) ?? null
}

const technicalDayStatusLabel = (day: HallAvailabilityDay | null) => {
  if (!day) {
    return ''
  }

  switch (day.status) {
    case 'booked':
      return 'Занято'
    case 'unavailable':
      return 'Техработы'
    default:
      return isFutureTechnicalDay(day.date) ? 'Свободно' : 'Прошло'
  }
}

const technicalDayButtonClasses = (day: HallAvailabilityDay | null) => {
  if (!day) {
    return 'invisible'
  }

  if (day.status === 'booked') {
    return 'border-blue-200 bg-blue-100 text-blue-800 cursor-not-allowed'
  }

  if (day.status === 'unavailable') {
    return 'border-emerald-200 bg-emerald-100 text-emerald-800 hover:border-emerald-400 hover:bg-emerald-50'
  }

  if (!isFutureTechnicalDay(day.date)) {
    return 'border-slate-200 bg-slate-100 text-slate-400 cursor-not-allowed'
  }

  return 'border-slate-200 bg-white text-slate-900 hover:border-emerald-400 hover:bg-emerald-50'
}

const isTechnicalDayDisabled = (day: HallAvailabilityDay | null) => {
  if (!day) {
    return true
  }

  return day.status === 'booked' || (day.status === 'free' && !isFutureTechnicalDay(day.date))
}

const loadTechnicalAvailability = async () => {
  if (!selectedTechnicalHall.value) {
    return
  }

  technicalCalendarLoading.value = true
  technicalCalendarError.value = ''

  try {
    const range = getMonthRange(technicalCalendarMonth.value)
    hallAvailability.value = await getVenueHallAvailabilityRequest(selectedTechnicalHall.value.id, {
      from: range.from,
      to: range.to,
    })
  } catch (requestError) {
    console.error(requestError)
    hallAvailability.value = null
    technicalCalendarError.value = extractErrorMessage(requestError, 'Не удалось загрузить календарь площадки.')
  } finally {
    technicalCalendarLoading.value = false
  }
}

const openTechnicalCalendar = async (hall: HallSummary) => {
  selectedTechnicalHall.value = hall
  technicalCalendarMonth.value = toMonthValue(new Date())
  technicalReason.value = 'Технические работы'
  technicalCalendarError.value = ''
  success.value = ''
  error.value = ''
  technicalCalendarOpen.value = true
  await loadTechnicalAvailability()
}

const closeTechnicalCalendar = () => {
  technicalCalendarOpen.value = false
  selectedTechnicalHall.value = null
  hallAvailability.value = null
  technicalCalendarError.value = ''
}

const changeTechnicalCalendarMonth = async (offset: number) => {
  const { firstDay } = getMonthRange(technicalCalendarMonth.value)
  firstDay.setMonth(firstDay.getMonth() + offset)
  technicalCalendarMonth.value = toMonthValue(firstDay)
  await loadTechnicalAvailability()
}

const selectTechnicalCalendarDay = async (day: HallAvailabilityDay | null) => {
  if (!day || !selectedTechnicalHall.value || technicalCalendarSaving.value) {
    return
  }

  if (day.status === 'booked') {
    technicalCalendarError.value = 'На этот день уже есть подтвержденная аренда, техработы поставить нельзя.'
    return
  }

  technicalCalendarSaving.value = true
  technicalCalendarError.value = ''
  success.value = ''

  try {
    if (day.status === 'unavailable') {
      const period = findUnavailablePeriodForDay(day.date)

      if (!period) {
        technicalCalendarError.value = 'Не удалось найти период недоступности для удаления.'
        return
      }

      const response = await deleteVenueHallUnavailablePeriodRequest(period.id)
      success.value = response.message
      await loadTechnicalAvailability()
      return
    }

    if (!isFutureTechnicalDay(day.date)) {
      technicalCalendarError.value = 'Технические работы можно поставить только на будущую дату.'
      return
    }

    const response = await createVenueHallUnavailablePeriodRequest(selectedTechnicalHall.value.id, {
      unavailable_start: `${day.date}T00:00:00`,
      unavailable_end: `${addDaysToDateKey(day.date, 1)}T00:00:00`,
      reason: technicalReason.value.trim() || 'Технические работы',
    })

    success.value = response.message
    await loadTechnicalAvailability()
  } catch (requestError) {
    console.error(requestError)
    technicalCalendarError.value = extractErrorMessage(requestError, 'Не удалось обновить график недоступности.')
  } finally {
    technicalCalendarSaving.value = false
  }
}

const goToCreate = async () => {
  await router.push({ name: 'venue-hall-create' })
}

const goToEdit = async (hallId: number) => {
  await router.push({ name: 'venue-hall-edit', params: { id: hallId } })
}

const archiveHall = async (hall: HallSummary) => {
  const confirmed = window.confirm(`Отправить зал "${hall.name}" в архив?`)

  if (!confirmed) {
    return
  }

  saving.value = true
  error.value = ''
  success.value = ''

  try {
    const response = await archiveVenueHallRequest(hall.id)
    success.value = response.message
    await loadHalls(pagination.current_page)
  } catch (requestError) {
    console.error(requestError)
    error.value = extractErrorMessage(requestError, 'Не удалось отправить зал в архив.')
  } finally {
    saving.value = false
  }
}

const updateRentalRequestStatus = async (
  requestId: number,
  status: Extract<HallRentalRequestStatus, 'approved' | 'rejected'>,
) => {
  requestActionIds.value = [...requestActionIds.value, requestId]
  error.value = ''
  success.value = ''

  try {
    const response = await updateVenueHallRentalRequestStatus(requestId, {
      status,
      response_note: null,
    })

    success.value = response.message
    rentalRequests.value = rentalRequests.value.map((request) => {
      return request.id === requestId ? response.rental_request : request
    })
  } catch (requestError) {
    console.error(requestError)
    error.value = extractErrorMessage(requestError, 'Не удалось обновить статус заявки.')
  } finally {
    requestActionIds.value = requestActionIds.value.filter((id) => id !== requestId)
  }
}

const isRequestActionLoading = (requestId: number) => requestActionIds.value.includes(requestId)

const changePage = async (page: number) => {
  if (page < 1 || page > pagination.last_page) {
    return
  }

  await loadHalls(page)
}

onMounted(async () => {
  await Promise.all([loadHalls(), loadRentalRequests()])
})
</script>

<template>
  <div class="space-y-6">
    <section class="app-panel p-8 sm:p-10">
      <div class="flex flex-col gap-8 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
          <span class="info-chip">Venue service</span>
          <h2 class="mt-4 text-3xl font-semibold leading-tight text-slate-950">
            Кабинет площадки: залы, ставки аренды и входящие заявки
          </h2>
          <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
            Здесь владелец площадки собирает схемы залов, назначает почасовую стоимость аренды и
            подтверждает или отклоняет запросы от организаторов на конкретные даты.
          </p>
        </div>

        <div class="flex flex-wrap gap-3">
          <RouterLink to="/profile" class="secondary-button">
            В профиль
          </RouterLink>

          <button type="button" class="primary-button" @click="goToCreate">
            Создать новый зал
          </button>
        </div>
      </div>
    </section>

    <div v-if="success" class="message-success">
      {{ success }}
    </div>

    <div v-if="error" class="message-error">
      {{ error }}
    </div>

    <section class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
      <div class="space-y-6">
        <section class="app-panel p-8">
          <div class="grid gap-4 lg:grid-cols-[1.15fr_0.95fr_auto]">
            <div>
              <label class="field-label" for="hall-search">Поиск по названию</label>
              <input
                id="hall-search"
                v-model="filters.search"
                type="text"
                class="field-input"
                placeholder="Например, главная арена или малая сцена"
              />
            </div>

            <div>
              <label class="field-label" for="hall-status-filter">Статус</label>
              <select id="hall-status-filter" v-model="filters.status" class="field-input">
                <option value="">Все статусы</option>
                <option value="draft">Черновики</option>
                <option value="active">Активные</option>
                <option value="archived">Архив</option>
              </select>
            </div>

            <button type="button" class="secondary-button mt-auto" @click="loadHalls(1)">
              Обновить список
            </button>
          </div>

          <div class="mt-6 grid gap-3 sm:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white/85 px-4 py-4 shadow-sm shadow-slate-900/5">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Всего</p>
              <p class="mt-2 text-3xl font-semibold text-slate-950">{{ pagination.total }}</p>
            </article>

            <article class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 shadow-sm shadow-amber-900/5">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">Черновики</p>
              <p class="mt-2 text-3xl font-semibold text-amber-950">{{ draftCount }}</p>
            </article>

            <article class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 shadow-sm shadow-emerald-900/5">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">Активные</p>
              <p class="mt-2 text-3xl font-semibold text-emerald-950">{{ activeCount }}</p>
            </article>
          </div>
        </section>

        <section v-if="loading" class="grid gap-6 md:grid-cols-2">
          <article v-for="item in 4" :key="item" class="app-panel p-6">
            <div class="h-4 w-28 animate-pulse rounded-full bg-slate-100"></div>
            <div class="mt-4 h-7 w-3/4 animate-pulse rounded-full bg-slate-200"></div>
            <div class="mt-6 h-24 animate-pulse rounded-[1.5rem] bg-slate-100"></div>
          </article>
        </section>

        <section v-else-if="halls.length > 0" class="grid gap-6 md:grid-cols-2">
          <article v-for="hall in halls" :key="hall.id" class="app-panel p-6">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                  обновлен {{ formatDate(hall.updated_at) }}
                </p>
                <h3 class="mt-2 text-2xl font-semibold leading-tight text-slate-950">
                  {{ hall.name }}
                </h3>
              </div>

              <span class="status-badge" :class="hallStatusClasses(hall.status)">
                {{ hallStatusLabel(hall.status) }}
              </span>
            </div>

            <p class="mt-4 text-sm leading-6 text-slate-500">
              {{ hall.description || 'Описание пока не добавлено.' }}
            </p>

            <p class="mt-3 text-sm font-medium text-slate-700">
              {{ hall.address || 'Адрес пока не указан' }}
            </p>

            <div class="mt-5 grid grid-cols-2 gap-3">
              <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Вместимость</p>
                <p class="mt-2 text-lg font-semibold text-slate-950">{{ hall.capacities?.total ?? 0 }}</p>
              </div>
              <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Аренда / час</p>
                <p class="mt-2 text-lg font-semibold text-slate-950">{{ formatPrice(hall.hourly_rate) }}</p>
              </div>
            </div>

            <div class="mt-5 rounded-[1.5rem] border border-slate-200 bg-slate-50/80 px-4 py-4 text-sm leading-6 text-slate-600">
              <p>Мест: {{ hall.capacities?.seat ?? 0 }}, за столиками: {{ hall.capacities?.table ?? 0 }}, VIP: {{ hall.capacities?.vip ?? 0 }}, танцпол: {{ hall.capacities?.dancefloor ?? 0 }}</p>
              <p>Уровней: {{ hall.layout_meta?.levels_count ?? 0 }}, элементов: {{ hall.layout_meta?.elements_count ?? 0 }}</p>
            </div>

            <div class="mt-6 flex flex-col gap-3">
              <button type="button" class="secondary-button" @click="openTechnicalCalendar(hall)">
                Календарь техработ
              </button>

              <button type="button" class="primary-button" @click="goToEdit(hall.id)">
                Открыть редактор
              </button>

              <button type="button" class="secondary-button" :disabled="saving" @click="archiveHall(hall)">
                В архив
              </button>
            </div>
          </article>
        </section>

        <section v-else class="app-panel p-8 sm:p-10">
          <span class="info-chip">Пока пусто</span>
          <h2 class="mt-4 text-3xl font-semibold text-slate-950">Залов еще нет</h2>
          <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500 sm:text-base">
            Начни с первого зала. После этого его можно будет открыть в отдельном editor-view и
            принимать под него заявки от организаторов.
          </p>
          <button type="button" class="primary-button mt-6" @click="goToCreate">
            Создать зал
          </button>
        </section>

        <section v-if="pagination.last_page > 1" class="app-panel p-6">
          <div class="flex items-center justify-between gap-4">
            <button type="button" class="secondary-button" :disabled="pagination.current_page === 1" @click="changePage(pagination.current_page - 1)">
              Назад
            </button>

            <div class="text-sm text-slate-500">
              {{ pagination.current_page }} / {{ pagination.last_page }}
            </div>

            <button type="button" class="secondary-button" :disabled="pagination.current_page === pagination.last_page" @click="changePage(pagination.current_page + 1)">
              Дальше
            </button>
          </div>
        </section>
      </div>

      <section class="app-panel p-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <span class="info-chip">Входящие заявки</span>
            <h3 class="mt-4 text-3xl font-semibold text-slate-950">
              Аренда на даты от организаторов
            </h3>
            <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
              Здесь площадка подтверждает или отклоняет запросы на конкретные интервалы аренды.
            </p>
          </div>

          <div class="rounded-[1.6rem] border border-slate-200 bg-slate-50 px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Ждут ответа</p>
            <p class="mt-2 text-3xl font-semibold text-slate-950">{{ pendingRequestsCount }}</p>
          </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-[1fr_auto]">
          <div>
            <label class="field-label" for="venue-request-status-filter">Статус заявки</label>
            <select id="venue-request-status-filter" v-model="requestFilters.status" class="field-input">
              <option value="">Все статусы</option>
              <option value="pending">Ожидают ответа</option>
              <option value="approved">Подтвержденные</option>
              <option value="rejected">Отклоненные</option>
              <option value="cancelled">Отмененные</option>
            </select>
          </div>

          <button type="button" class="secondary-button mt-auto" @click="loadRentalRequests">
            Обновить заявки
          </button>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2">
          <article class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-4 shadow-sm shadow-blue-900/5">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-blue-700">Ожидают</p>
            <p class="mt-2 text-3xl font-semibold text-blue-950">{{ pendingRequestsCount }}</p>
          </article>
          <article class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 shadow-sm shadow-emerald-900/5">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">Подтверждены</p>
            <p class="mt-2 text-3xl font-semibold text-emerald-950">{{ approvedRequestsCount }}</p>
          </article>
        </div>

        <div v-if="requestsLoading" class="mt-6 space-y-4">
          <div v-for="item in 4" :key="item" class="h-28 animate-pulse rounded-[1.5rem] bg-slate-100"></div>
        </div>

        <div v-else-if="rentalRequests.length > 0" class="mt-6 space-y-4">
          <article v-for="request in rentalRequests" :key="request.id" class="rounded-[1.5rem] border border-slate-200 bg-white px-5 py-5 shadow-sm shadow-slate-900/5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                  {{ request.hall?.name || `Зал #${request.hall_id}` }}
                </p>
                <h4 class="mt-2 text-xl font-semibold text-slate-950">
                  {{ formatDateTime(request.requested_start) }}
                </h4>
                <p class="mt-2 text-sm text-slate-500">
                  До {{ formatDateTime(request.requested_end) }}
                </p>
                <p class="mt-2 text-sm text-slate-500">
                  {{ request.hall?.address || 'Адрес уточняется' }}
                </p>
                <p class="mt-3 text-sm font-semibold text-slate-900">
                  {{ formatPrice(request.total_amount) }}
                </p>
                <p v-if="request.organizer_message" class="mt-2 text-sm leading-6 text-slate-500">
                  Комментарий организатора: {{ request.organizer_message }}
                </p>
                <p v-if="request.response_note" class="mt-2 text-sm leading-6 text-slate-500">
                  Текущий ответ: {{ request.response_note }}
                </p>
              </div>

              <div class="flex flex-col gap-3 sm:items-end">
                <span class="status-badge" :class="rentalRequestStatusClasses(request.status)">
                  {{ rentalRequestStatusLabel(request.status) }}
                </span>

                <div v-if="request.status === 'pending'" class="flex flex-wrap gap-2">
                  <button
                    type="button"
                    class="primary-button px-4 py-2.5"
                    :disabled="isRequestActionLoading(request.id)"
                    @click="updateRentalRequestStatus(request.id, 'approved')"
                  >
                    {{ isRequestActionLoading(request.id) ? 'Сохраняем...' : 'Подтвердить' }}
                  </button>
                  <button
                    type="button"
                    class="danger-button px-4 py-2.5"
                    :disabled="isRequestActionLoading(request.id)"
                    @click="updateRentalRequestStatus(request.id, 'rejected')"
                  >
                    Отклонить
                  </button>
                </div>
              </div>
            </div>
          </article>
        </div>

        <div v-else class="mt-6 rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50/70 px-5 py-6 text-sm leading-6 text-slate-500">
          Входящих заявок пока нет. Как только организаторы начнут запрашивать площадку на нужные даты,
          они появятся в этом списке.
        </div>
      </section>
    </section>

    <div
      v-if="technicalCalendarOpen"
      class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-950/60 px-4 py-6 backdrop-blur-sm"
      @click.self="closeTechnicalCalendar"
    >
      <section class="max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-[2rem] border border-white/70 bg-white p-6 shadow-2xl shadow-slate-950/25 sm:p-8">
        <div class="flex flex-col gap-5 border-b border-slate-200 pb-6 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <span class="info-chip">График площадки</span>
            <h3 class="mt-4 text-3xl font-semibold text-slate-950">
              Технические работы
            </h3>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500">
              Выбери свободный день, чтобы закрыть площадку для аренды. Зеленый день уже отмечен как недоступный, повторный клик снимет отметку.
            </p>
          </div>

          <button type="button" class="secondary-button px-4 py-2.5" @click="closeTechnicalCalendar">
            Закрыть
          </button>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
          <div class="rounded-[1.75rem] border border-slate-200 bg-slate-50/70 p-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                  {{ selectedTechnicalHall?.name || 'Площадка' }}
                </p>
                <h4 class="mt-2 text-2xl font-semibold capitalize text-slate-950">
                  {{ formatCalendarMonth(technicalCalendarMonth) }}
                </h4>
              </div>

              <div class="flex gap-2">
                <button type="button" class="secondary-button px-4 py-2.5" @click="changeTechnicalCalendarMonth(-1)">
                  Назад
                </button>
                <button type="button" class="secondary-button px-4 py-2.5" @click="changeTechnicalCalendarMonth(1)">
                  Вперед
                </button>
              </div>
            </div>

            <div class="mt-5 grid grid-cols-3 gap-2 text-[0.72rem] font-semibold text-slate-600 lg:grid-cols-4">
              <div class="rounded-2xl border border-blue-200 bg-blue-100 px-3 py-2 text-blue-800">Синий: занят</div>
              <div class="rounded-2xl border border-emerald-200 bg-emerald-100 px-3 py-2 text-emerald-800">Зеленый: техработы</div>
              <div class="rounded-2xl border border-slate-200 bg-white px-3 py-2 text-slate-700">Белый: свободен</div>
              <div class="rounded-2xl border border-slate-200 bg-slate-100 px-3 py-2 text-slate-500">Серый: прошел</div>
            </div>

            <div v-if="technicalCalendarError" class="message-error mt-5">
              {{ technicalCalendarError }}
            </div>

            <div v-if="technicalCalendarLoading" class="mt-6 grid grid-cols-7 gap-2">
              <div v-for="item in 35" :key="item" class="h-20 animate-pulse rounded-2xl bg-slate-100"></div>
            </div>

            <template v-else>
              <div class="mt-6 grid grid-cols-7 gap-2 text-center text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                <span v-for="label in weekDayLabels" :key="label">{{ label }}</span>
              </div>

              <div class="mt-3 grid grid-cols-7 gap-2">
                <button
                  v-for="(day, index) in technicalCalendarCells"
                  :key="day?.date || `technical-empty-${index}`"
                  type="button"
                  class="min-h-[4.75rem] overflow-hidden rounded-2xl border p-2 text-left transition"
                  :class="technicalDayButtonClasses(day)"
                  :disabled="isTechnicalDayDisabled(day)"
                  @click="selectTechnicalCalendarDay(day)"
                >
                  <template v-if="day">
                    <span class="text-base font-semibold">{{ Number(day.date.slice(-2)) }}</span>
                    <span class="mt-1 block max-w-full truncate text-[0.56rem] font-bold uppercase tracking-[0.08em] opacity-75 sm:text-[0.6rem]">
                      {{ technicalDayStatusLabel(day) }}
                    </span>
                  </template>
                </button>
              </div>
            </template>
          </div>

          <aside class="space-y-4">
            <div class="rounded-[1.75rem] border border-slate-200 bg-white px-5 py-5 shadow-sm shadow-slate-900/5">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Как работает</p>
              <p class="mt-3 text-sm leading-6 text-slate-500">
                Технический день сразу станет зеленым в календаре организатора, и он не сможет отправить заявку на эту дату.
              </p>
            </div>

            <div class="rounded-[1.75rem] border border-slate-200 bg-white px-5 py-5 shadow-sm shadow-slate-900/5">
              <label class="field-label" for="technical-reason">Причина недоступности</label>
              <input
                id="technical-reason"
                v-model="technicalReason"
                type="text"
                class="field-input mt-3"
                placeholder="Например: генеральная уборка"
              />
            </div>

            <div class="rounded-[1.75rem] border border-emerald-200 bg-emerald-50 px-5 py-5 text-sm leading-6 text-emerald-800">
              Клик по белому будущему дню добавляет техработы. Клик по зеленому дню удаляет отметку.
            </div>

            <button
              type="button"
              class="secondary-button w-full justify-center"
              :disabled="technicalCalendarSaving"
              @click="loadTechnicalAvailability"
            >
              {{ technicalCalendarSaving ? 'Сохраняем...' : 'Обновить календарь' }}
            </button>
          </aside>
        </div>
      </section>
    </div>
  </div>
</template>
