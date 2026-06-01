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
  rewriteOrganizerEventDescriptionRequest,
} from '@/api/events'
import {
  createOrganizerHallRentalRequest,
  getHallAvailabilityRequest,
  getOrganizerHallRentalRequestsRequest,
  getPublicHallsRequest,
} from '@/api/halls'
import { useAuthStore } from '@/stores/auth'
import type {
  AgeRating,
  Category,
  EventSession,
  OrganizerEvent,
  OrganizerEventPayload,
  OrganizerEventStatus,
  OrganizerSessionPayload,
  PaginatedResponse,
} from '@/types/event'
import type { HallAvailabilityDay, HallAvailabilityResponse, HallRentalRequest, HallRentalRequestStatus, HallSummary } from '@/types/hall'
import { formatDateTime, formatPrice } from '@/utils/format'

const authStore = useAuthStore()
const pad2 = (value: number) => String(value).padStart(2, '0')
const toDateKey = (date: Date) => `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`
const toMonthValue = (date: Date) => `${date.getFullYear()}-${pad2(date.getMonth() + 1)}`
const parseDateKey = (dateKey: string) => {
  const [year = 1970, month = 1, day = 1] = dateKey.split('-').map(Number)
  return new Date(year, month - 1, day)
}
const getMonthRange = (monthValue: string) => {
  const [year = new Date().getFullYear(), month = new Date().getMonth() + 1] = monthValue.split('-').map(Number)
  const firstDay = new Date(year, month - 1, 1)
  const lastDay = new Date(year, month, 0)

  return {
    firstDay,
    lastDay,
    from: toDateKey(firstDay),
    to: toDateKey(lastDay),
  }
}
const formatDateKey = (dateKey: string) => {
  return new Intl.DateTimeFormat('ru-RU', {
    day: 'numeric',
    month: 'long',
  }).format(parseDateKey(dateKey))
}
const formatCalendarMonth = (monthValue: string) => {
  const { firstDay } = getMonthRange(monthValue)

  return new Intl.DateTimeFormat('ru-RU', {
    month: 'long',
    year: 'numeric',
  }).format(firstDay)
}
const datesBetween = (leftDateKey: string, rightDateKey: string) => {
  const start = parseDateKey(leftDateKey)
  const end = parseDateKey(rightDateKey)
  const from = start <= end ? start : end
  const to = start <= end ? end : start
  const dates: string[] = []
  const cursor = new Date(from)

  while (cursor <= to) {
    dates.push(toDateKey(cursor))
    cursor.setDate(cursor.getDate() + 1)
  }

  return dates
}

const categories = ref<Category[]>([])
const ageRatings = ref<AgeRating[]>([])
const publicHalls = ref<HallSummary[]>([])
const rentalRequests = ref<HallRentalRequest[]>([])
const events = ref<OrganizerEvent[]>([])
const sessions = ref<EventSession[]>([])

const loading = ref(false)
const lookupsLoading = ref(false)
const hallsLoading = ref(false)
const rentalRequestsLoading = ref(false)
const sessionsLoading = ref(false)
const eventSaving = ref(false)
const rentalRequestSaving = ref(false)
const rentalCalendarOpen = ref(false)
const rentalCalendarLoading = ref(false)
const rentalCalendarError = ref('')
const sessionSaving = ref(false)
const copywriting = ref(false)
const copywriterTips = ref<string[]>([])
const copywriterPreview = ref('')
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

const venueFilters = reactive({
  search: '',
  address: '',
  min_hourly_rate: '',
  max_hourly_rate: '',
})

const createEventDraft = () => ({
  title: '',
  description: '',
  poster_url: '',
  category_id: '',
  age_rating_id: '',
  tags: '',
  status: 'draft' as Extract<OrganizerEventStatus, 'draft' | 'pending_review'>,
})

const createSessionDraft = () => ({
  id: null as number | null,
  hall_rental_request_id: '',
  base_price: '',
})

const createRentalRequestDraft = () => ({
  hall_id: '',
  requested_start: '',
  requested_end: '',
  organizer_message: '',
})

const eventForm = reactive(createEventDraft())
const sessionForm = reactive(createSessionDraft())
const rentalRequestForm = reactive(createRentalRequestDraft())
const hallAvailability = ref<HallAvailabilityResponse | null>(null)
const rentalCalendarMonth = ref(toMonthValue(new Date()))
const rentalRangeAnchor = ref<string | null>(null)
const selectedRentalDates = ref<string[]>([])
const rentalSlotStartTime = ref('19:00')
const rentalSlotEndTime = ref('22:00')
const weekDayLabels = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']

const activeEvent = computed(() => events.value.find((event) => event.id === selectedEventId.value) ?? null)
const canManageSessions = computed(() => activeEvent.value?.status === 'published')
const selectedPublicHall = computed(() => publicHalls.value.find((hall) => hall.id === Number(rentalRequestForm.hall_id)) ?? null)
const availableApprovedRequests = computed(() => rentalRequests.value.filter((request) => request.status === 'approved'))
const activeRentalRequest = computed(() => {
  return availableApprovedRequests.value.find((request) => request.id === Number(sessionForm.hall_rental_request_id)) ?? null
})
const availabilityByDate = computed(() => {
  return new Map((hallAvailability.value?.days ?? []).map((day) => [day.date, day]))
})
const selectedRentalDateSet = computed(() => new Set(selectedRentalDates.value))
const rentalCalendarCells = computed<Array<HallAvailabilityDay | null>>(() => {
  const { firstDay } = getMonthRange(rentalCalendarMonth.value)
  const leadingEmptyDays = (firstDay.getDay() + 6) % 7

  return [
    ...Array.from({ length: leadingEmptyDays }, () => null),
    ...(hallAvailability.value?.days ?? []),
  ]
})
const selectedRentalDatesLabel = computed(() => {
  if (selectedRentalDates.value.length === 0) {
    return 'Даты еще не выбраны'
  }

  if (selectedRentalDates.value.length === 1) {
    return formatDateKey(selectedRentalDates.value[0] ?? '')
  }

  return `${formatDateKey(selectedRentalDates.value[0] ?? '')} — ${formatDateKey(selectedRentalDates.value.at(-1) ?? selectedRentalDates.value[0] ?? '')}`
})
const hasValidRentalSlotTimes = computed(() => {
  return rentalSlotStartTime.value.trim() !== ''
    && rentalSlotEndTime.value.trim() !== ''
    && rentalSlotStartTime.value < rentalSlotEndTime.value
})
const hasBlockedSelectedDates = computed(() => {
  return selectedRentalDates.value.some((dateKey) => {
    const day = availabilityByDate.value.get(dateKey)

    return day !== undefined && day.status !== 'free'
  })
})
const rentalSlots = computed(() => {
  return selectedRentalDates.value.map((dateKey) => ({
    requested_start: `${dateKey}T${rentalSlotStartTime.value}`,
    requested_end: `${dateKey}T${rentalSlotEndTime.value}`,
  }))
})

const draftCount = computed(() => events.value.filter((event) => event.status === 'draft').length)
const reviewCount = computed(() => events.value.filter((event) => event.status === 'pending_review').length)
const publishedCount = computed(() => events.value.filter((event) => event.status === 'published').length)

const normalizedSessionPrice = computed(() => String(sessionForm.base_price ?? '').trim().replace(',', '.'))
const hasValidSessionPrice = computed(() => {
  if (normalizedSessionPrice.value === '') {
    return false
  }

  const parsedPrice = Number(normalizedSessionPrice.value)
  return Number.isFinite(parsedPrice) && parsedPrice >= 0
})

const hasValidLookupId = (value: string | number | null | undefined) => {
  const parsedValue = Number(value)

  return Number.isInteger(parsedValue) && parsedValue > 0
}

const eventValidationIssues = computed(() => {
  const issues: string[] = []

  if (eventForm.title.trim() === '') {
    issues.push('укажи название')
  }

  if (!hasValidLookupId(eventForm.category_id)) {
    issues.push('выбери категорию')
  }

  if (!hasValidLookupId(eventForm.age_rating_id)) {
    issues.push('выбери возрастной рейтинг')
  }

  return issues
})

const canSubmitEvent = computed(() => {
  return eventValidationIssues.value.length === 0
})

const normalizedEventTitle = computed(() => eventForm.title.trim())
const normalizedEventDescription = computed(() => eventForm.description.trim())
const copywriterRequestTitle = computed(() => {
  if (normalizedEventTitle.value.length >= 3) {
    return normalizedEventTitle.value
  }

  return normalizedEventDescription.value.slice(0, 80)
})

const canRewriteDescription = computed(() => {
  return (
    !copywriting.value &&
    (normalizedEventTitle.value.length >= 3 || normalizedEventDescription.value.length >= 10)
  )
})

const canSubmitRentalRequest = computed(() => {
  return (
    activeEvent.value !== null &&
    selectedPublicHall.value !== null &&
    selectedRentalDates.value.length > 0 &&
    hasValidRentalSlotTimes.value &&
    !hasBlockedSelectedDates.value
  )
})

const canSubmitSession = computed(() => {
  return (
    activeEvent.value !== null &&
    canManageSessions.value &&
    String(sessionForm.hall_rental_request_id).trim() !== '' &&
    hasValidSessionPrice.value &&
    Number(sessionForm.hall_rental_request_id) > 0
  )
})

const organizerDisplayName = computed(() => {
  return authStore.user?.organizer_profile?.company_name || authStore.user?.full_name || 'Организатор'
})

const parseEventTags = (value: string) => {
  return Array.from(
    new Set(
      value
        .split(/[,\n]/)
        .map((tag) => tag.trim())
        .filter((tag) => tag.length >= 2),
    ),
  ).slice(0, 12)
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

const formatRentalDuration = (minutes?: number | null) => {
  const numericMinutes = Number(minutes)

  if (!Number.isFinite(numericMinutes) || numericMinutes <= 0) {
    return 'длительность уточняется'
  }

  const hours = numericMinutes / 60
  const formattedHours = new Intl.NumberFormat('ru-RU', {
    maximumFractionDigits: Number.isInteger(hours) ? 0 : 1,
  }).format(hours)

  return `${formattedHours} ч`
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
      return 'border-slate-200 bg-slate-100 text-slate-700'
    default:
      return 'border-amber-200 bg-amber-50 text-amber-700'
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

const resetSessionForm = () => {
  Object.assign(sessionForm, createSessionDraft())
}

const resetCopywriterPreview = () => {
  copywriterPreview.value = ''
  copywriterTips.value = []
}

const resetRentalRequestForm = () => {
  Object.assign(rentalRequestForm, createRentalRequestDraft())
  selectedRentalDates.value = []
  rentalRangeAnchor.value = null
  hallAvailability.value = null
  rentalCalendarError.value = ''
}

const selectHallForRental = (hallId: number) => {
  rentalRequestForm.hall_id = String(hallId)
  selectedRentalDates.value = []
  rentalRangeAnchor.value = null
  hallAvailability.value = null
  rentalCalendarError.value = ''
}

const loadHallAvailability = async () => {
  if (!selectedPublicHall.value) {
    return
  }

  rentalCalendarLoading.value = true
  rentalCalendarError.value = ''

  try {
    const range = getMonthRange(rentalCalendarMonth.value)
    hallAvailability.value = await getHallAvailabilityRequest(selectedPublicHall.value.id, {
      from: range.from,
      to: range.to,
    })
  } catch (requestError) {
    console.error(requestError)
    rentalCalendarError.value = extractErrorMessage(requestError, 'Не удалось загрузить календарь доступности площадки.')
    hallAvailability.value = null
  } finally {
    rentalCalendarLoading.value = false
  }
}

const openRentalCalendar = async () => {
  if (!activeEvent.value) {
    error.value = 'Сначала выбери или сохрани мероприятие.'
    return
  }

  if (!selectedPublicHall.value) {
    error.value = 'Сначала выбери площадку из списка слева.'
    return
  }

  error.value = ''
  success.value = ''
  rentalCalendarOpen.value = true
  await loadHallAvailability()
}

const closeRentalCalendar = () => {
  rentalCalendarOpen.value = false
  rentalCalendarError.value = ''
}

const changeRentalCalendarMonth = async (offset: number) => {
  const { firstDay } = getMonthRange(rentalCalendarMonth.value)
  firstDay.setMonth(firstDay.getMonth() + offset)
  rentalCalendarMonth.value = toMonthValue(firstDay)
  await loadHallAvailability()
}

const dayStatusLabel = (day: HallAvailabilityDay | null) => {
  if (!day) {
    return ''
  }

  switch (day.status) {
    case 'booked':
      return 'Занято'
    case 'unavailable':
      return 'Недоступно'
    default:
      return 'Свободно'
  }
}

const dayButtonClasses = (day: HallAvailabilityDay | null) => {
  if (!day) {
    return 'invisible'
  }

  const isSelected = selectedRentalDateSet.value.has(day.date)

  if (isSelected) {
    return 'border-slate-950 bg-slate-950 text-white shadow-lg shadow-slate-900/20'
  }

  switch (day.status) {
    case 'booked':
      return 'border-blue-200 bg-blue-100 text-blue-800 cursor-not-allowed'
    case 'unavailable':
      return 'border-emerald-200 bg-emerald-100 text-emerald-800 cursor-not-allowed'
    default:
      return 'border-slate-200 bg-white text-slate-900 hover:border-slate-950 hover:bg-slate-50'
  }
}

const selectRentalCalendarDay = (day: HallAvailabilityDay | null) => {
  if (!day) {
    return
  }

  if (day.status !== 'free') {
    rentalCalendarError.value = day.status === 'booked'
      ? 'Этот день уже занят подтвержденной арендой площадки.'
      : 'Этот день площадка отметила недоступным.'
    return
  }

  rentalCalendarError.value = ''

  if (rentalRangeAnchor.value === null || selectedRentalDates.value.length > 1) {
    rentalRangeAnchor.value = day.date
    selectedRentalDates.value = [day.date]
    return
  }

  const nextRange = datesBetween(rentalRangeAnchor.value, day.date)
  const blockedDay = nextRange.find((dateKey) => availabilityByDate.value.get(dateKey)?.status !== 'free')

  if (blockedDay) {
    rentalCalendarError.value = `В выбранном диапазоне есть занятый или недоступный день: ${formatDateKey(blockedDay)}.`
    selectedRentalDates.value = [day.date]
    rentalRangeAnchor.value = day.date
    return
  }

  selectedRentalDates.value = nextRange
  rentalRangeAnchor.value = null
}

const startCreateEvent = () => {
  eventFormMode.value = 'create'
  selectedEventId.value = null
  sessions.value = []
  rentalRequests.value = []
  sessionsLoading.value = false
  rentalRequestsLoading.value = false
  error.value = ''
  success.value = ''
  resetCopywriterPreview()
  Object.assign(eventForm, createEventDraft())
  resetSessionForm()
  resetRentalRequestForm()
}

const fillEventForm = (event: OrganizerEvent) => {
  eventForm.title = event.title
  eventForm.description = event.description || ''
  eventForm.poster_url = event.poster_url || ''
  eventForm.category_id = event.category?.id ? String(event.category.id) : ''
  eventForm.age_rating_id = event.age_rating?.id ? String(event.age_rating.id) : ''
  eventForm.tags = Array.isArray(event.tags) ? event.tags.map((tag) => tag.name).join(', ') : ''
  eventForm.status = event.status === 'published' || event.status === 'pending_review' ? 'pending_review' : 'draft'
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

const loadPublicHalls = async () => {
  hallsLoading.value = true

  try {
    const response = await getPublicHallsRequest({
      per_page: 50,
      search: venueFilters.search.trim() || undefined,
      address: venueFilters.address.trim() || undefined,
      min_hourly_rate: venueFilters.min_hourly_rate ? Number(venueFilters.min_hourly_rate) : undefined,
      max_hourly_rate: venueFilters.max_hourly_rate ? Number(venueFilters.max_hourly_rate) : undefined,
    })

    publicHalls.value = response.data
  } catch (requestError) {
    console.error(requestError)
    error.value = 'Не удалось загрузить список площадок.'
    publicHalls.value = []
  } finally {
    hallsLoading.value = false
  }
}

const loadRentalRequests = async (eventId: number) => {
  rentalRequestsLoading.value = true

  try {
    const response = await getOrganizerHallRentalRequestsRequest({
      event_id: eventId,
      per_page: 50,
    })

    if (selectedEventId.value === eventId) {
      rentalRequests.value = response.data
    }
  } catch (requestError) {
    console.error(requestError)
    error.value = 'Не удалось загрузить заявки на аренду площадок.'
    if (selectedEventId.value === eventId) {
      rentalRequests.value = []
    }
  } finally {
    if (selectedEventId.value === eventId) {
      rentalRequestsLoading.value = false
    }
  }
}

const loadSessions = async (eventId: number) => {
  sessionsLoading.value = true

  try {
    const loadedSessions = await getOrganizerEventSessionsRequest(eventId)

    if (selectedEventId.value === eventId) {
      sessions.value = loadedSessions
    }
  } catch (requestError) {
    console.error(requestError)
    error.value = 'Не удалось загрузить список сеансов.'
    if (selectedEventId.value === eventId) {
      sessions.value = []
    }
  } finally {
    if (selectedEventId.value === eventId) {
      sessionsLoading.value = false
    }
  }
}

const selectEvent = (event: OrganizerEvent) => {
  eventFormMode.value = 'edit'
  selectedEventId.value = event.id
  error.value = ''
  fillEventForm(event)
  resetCopywriterPreview()
  resetSessionForm()
  resetRentalRequestForm()

  void Promise.all([
    loadSessions(event.id),
    loadRentalRequests(event.id),
  ])
}

const loadEvents = async (page = 1, preferredEventId?: number | null) => {
  loading.value = true
  error.value = ''

  try {
    const response: PaginatedResponse<OrganizerEvent> = await getOrganizerEventsRequest({
      status: statusFilter.value || undefined,
      page,
      per_page: pagination.per_page,
    })

    events.value = response.data
    pagination.current_page = response.current_page
    pagination.last_page = response.last_page
    pagination.total = response.total

    const nextSelectedId = preferredEventId ?? selectedEventId.value

    let eventToSelect: OrganizerEvent | null = null

    if (nextSelectedId) {
      const matchedEvent = response.data.find((event) => event.id === nextSelectedId)

      if (matchedEvent) {
        eventToSelect = matchedEvent
      }
    }

    loading.value = false

    if (eventToSelect) {
      await selectEvent(eventToSelect)
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
    error.value = `Чтобы сохранить мероприятие, ${eventValidationIssues.value.join(', ')}.`
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
      tags: parseEventTags(eventForm.tags),
      status: eventForm.status,
    }

    const response =
      eventFormMode.value === 'create' || selectedEventId.value === null
        ? await createOrganizerEventRequest(payload)
        : await updateOrganizerEventRequest(selectedEventId.value, payload)

    success.value = response.message
    eventSaving.value = false

    try {
      await loadEvents(1, response.event.id)
    } catch (refreshError) {
      console.error(refreshError)
      error.value = 'Мероприятие сохранено, но список не обновился автоматически. Обнови страницу, если карточка не появилась.'
    }
  } catch (requestError) {
    console.error(requestError)
    error.value = extractErrorMessage(requestError, 'Не удалось сохранить мероприятие.')
  } finally {
    eventSaving.value = false
  }
}

const rewriteDescription = async () => {
  if (!canRewriteDescription.value) {
    error.value = 'Напиши название или хотя бы короткое описание, чтобы Митя понял контекст.'
    return
  }

  error.value = ''
  success.value = ''
  resetCopywriterPreview()
  copywriting.value = true

  try {
    const response = await rewriteOrganizerEventDescriptionRequest({
      title: copywriterRequestTitle.value,
      description: normalizedEventDescription.value || null,
      category_id: eventForm.category_id ? Number(eventForm.category_id) : null,
      age_rating_id: eventForm.age_rating_id ? Number(eventForm.age_rating_id) : null,
      tags: parseEventTags(eventForm.tags),
    })

    copywriterPreview.value = response.description
    copywriterTips.value = response.tips
    success.value = `${response.message} Проверь вариант перед заменой.`
  } catch (requestError) {
    console.error(requestError)
    error.value = extractErrorMessage(requestError, 'Не удалось улучшить описание через AI.')
  } finally {
    copywriting.value = false
  }
}

const applyCopywriterPreview = () => {
  if (copywriterPreview.value.trim() === '') {
    return
  }

  eventForm.description = copywriterPreview.value
  success.value = 'Описание заменено AI-вариантом. Перед публикацией можно еще отредактировать текст вручную.'
  resetCopywriterPreview()
}

const rejectCopywriterPreview = () => {
  resetCopywriterPreview()
  success.value = 'AI-вариант отклонен. В форме осталось описание, написанное организатором.'
}

const changeEventStatus = async (status: Extract<OrganizerEventStatus, 'cancelled' | 'archived'>) => {
  if (!activeEvent.value) {
    return
  }

  const confirmed = window.confirm(
    status === 'cancelled'
      ? 'Отменить мероприятие?'
      : 'Отправить мероприятие в архив?',
  )

  if (!confirmed) {
    return
  }

  error.value = ''
  success.value = ''
  eventSaving.value = true

  try {
    const response = await changeOrganizerEventStatusRequest(activeEvent.value.id, { status })
    success.value = response.message
    await loadEvents(1, response.event.id)
  } catch (requestError) {
    console.error(requestError)
    error.value = extractErrorMessage(requestError, 'Не удалось изменить статус мероприятия.')
  } finally {
    eventSaving.value = false
  }
}

const submitRentalRequest = async () => {
  if (!activeEvent.value || !canSubmitRentalRequest.value) {
    rentalCalendarError.value = 'Выбери свободные даты и корректное время аренды.'
    return
  }

  error.value = ''
  success.value = ''
  rentalRequestSaving.value = true

  try {
    const response = await createOrganizerHallRentalRequest({
      hall_id: Number(rentalRequestForm.hall_id),
      event_id: activeEvent.value.id,
      requested_slots: rentalSlots.value,
      organizer_message: rentalRequestForm.organizer_message.trim() || null,
    })

    success.value = response.message
    rentalCalendarOpen.value = false
    resetRentalRequestForm()
    await loadRentalRequests(activeEvent.value.id)
  } catch (requestError) {
    console.error(requestError)
    rentalCalendarError.value = extractErrorMessage(requestError, 'Не удалось отправить заявку на аренду площадки.')
  } finally {
    rentalRequestSaving.value = false
  }
}

const editSession = (session: EventSession) => {
  sessionForm.id = session.id
  sessionForm.hall_rental_request_id = String(session.hall_rental_request_id ?? '')
  sessionForm.base_price = String(session.base_price)
  success.value = ''
  error.value = ''
}

const submitSession = async () => {
  if (activeEvent.value && !canManageSessions.value) {
    error.value = 'Сеансы можно создавать только после публикации события администратором. До этого карточка может стать тизером без расписания.'
    return
  }

  if (!activeEvent.value || !canSubmitSession.value) {
    error.value = 'Для сеанса нужна подтвержденная аренда площадки и базовая цена билета.'
    return
  }

  error.value = ''
  success.value = ''
  sessionSaving.value = true

  try {
    const payload: OrganizerSessionPayload = {
      hall_rental_request_id: Number(sessionForm.hall_rental_request_id),
      base_price: Number(normalizedSessionPrice.value),
    }

    const response = sessionForm.id
      ? await updateOrganizerSessionRequest(sessionForm.id, payload)
      : await createOrganizerSessionRequest(activeEvent.value.id, payload)

    success.value = response.message
    resetSessionForm()
    await loadSessions(activeEvent.value.id)
  } catch (requestError) {
    console.error(requestError)
    error.value = extractErrorMessage(requestError, 'Не удалось сохранить сеанс.')
  } finally {
    sessionSaving.value = false
  }
}

const cancelSession = async (session: EventSession) => {
  if (!activeEvent.value) {
    return
  }

  const confirmed = window.confirm('Отменить выбранный сеанс?')

  if (!confirmed) {
    return
  }

  error.value = ''
  success.value = ''
  sessionSaving.value = true

  try {
    const response = await cancelOrganizerSessionRequest(session.id)
    success.value = response.message
    resetSessionForm()
    await loadSessions(activeEvent.value.id)
  } catch (requestError) {
    console.error(requestError)
    error.value = extractErrorMessage(requestError, 'Не удалось отменить сеанс.')
  } finally {
    sessionSaving.value = false
  }
}

const changePage = async (page: number) => {
  if (page < 1 || page > pagination.last_page) {
    return
  }

  await loadEvents(page, selectedEventId.value)
}

onMounted(async () => {
  if (!authStore.user) {
    await authStore.fetchMe()
  }

  await Promise.all([loadLookups(), loadPublicHalls(), loadEvents()])
})
</script>

<template>
  <div class="space-y-6">
    <div
      v-if="copywriterPreview"
      class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/55 px-4 py-6 backdrop-blur-sm"
      @click.self="rejectCopywriterPreview"
    >
      <section class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-[2rem] border border-white/70 bg-white p-6 shadow-2xl shadow-slate-950/20 sm:p-8">
        <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <span class="info-chip">AI copywriter</span>
            <h3 class="mt-4 text-2xl font-semibold text-slate-950">
              Митя предложил новый текст
            </h3>
            <p class="mt-2 text-sm leading-6 text-slate-500">
              Проверь, подходит ли вариант под твою задумку. Исходное описание пока не изменено.
            </p>
          </div>

          <button type="button" class="secondary-button px-4 py-2.5" @click="rejectCopywriterPreview">
            Отмена
          </button>
        </div>

        <div class="mt-6 rounded-[1.5rem] border border-slate-200 bg-slate-50/80 px-5 py-5">
          <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
            Предложенный вариант
          </p>
          <p class="mt-4 whitespace-pre-line text-base leading-8 text-slate-800">
            {{ copywriterPreview }}
          </p>
        </div>

        <div
          v-if="copywriterTips.length > 0"
          class="mt-5 rounded-[1.5rem] border border-blue-100 bg-blue-50/70 px-5 py-4 text-sm leading-6 text-blue-900"
        >
          <p class="font-semibold">Подсказки AI:</p>
          <ul class="mt-2 space-y-1">
            <li v-for="tip in copywriterTips" :key="tip">
              {{ tip }}
            </li>
          </ul>
        </div>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
          <button type="button" class="secondary-button justify-center" @click="rejectCopywriterPreview">
            Отмена
          </button>
          <button type="button" class="primary-button justify-center" @click="applyCopywriterPreview">
            Заменить описание
          </button>
        </div>
      </section>
    </div>

    <div
      v-if="rentalCalendarOpen"
      class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-950/60 px-4 py-6 backdrop-blur-sm"
      @click.self="closeRentalCalendar"
    >
      <section class="max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-[2rem] border border-white/70 bg-white p-6 shadow-2xl shadow-slate-950/25 sm:p-8">
        <div class="flex flex-col gap-5 border-b border-slate-200 pb-6 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <span class="info-chip">Календарь площадки</span>
            <h3 class="mt-4 text-3xl font-semibold text-slate-950">
              Выбор дат для заявки
            </h3>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500">
              Выбери один день или диапазон дней. Для каждого выбранного дня будет создана отдельная заявка, а владелец площадки сможет подтвердить или отклонить ее.
            </p>
          </div>

          <button type="button" class="secondary-button px-4 py-2.5" @click="closeRentalCalendar">
            Закрыть
          </button>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
          <div class="rounded-[1.75rem] border border-slate-200 bg-slate-50/70 p-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                  {{ selectedPublicHall?.name || 'Площадка' }}
                </p>
                <h4 class="mt-2 text-2xl font-semibold capitalize text-slate-950">
                  {{ formatCalendarMonth(rentalCalendarMonth) }}
                </h4>
              </div>

              <div class="flex gap-2">
                <button type="button" class="secondary-button px-4 py-2.5" @click="changeRentalCalendarMonth(-1)">
                  Назад
                </button>
                <button type="button" class="secondary-button px-4 py-2.5" @click="changeRentalCalendarMonth(1)">
                  Вперед
                </button>
              </div>
            </div>

            <div class="mt-5 grid grid-cols-3 gap-2 text-xs font-semibold text-slate-600 sm:grid-cols-4 lg:grid-cols-7">
              <div class="rounded-2xl border border-blue-200 bg-blue-100 px-3 py-2 text-blue-800">Синий: занято</div>
              <div class="rounded-2xl border border-emerald-200 bg-emerald-100 px-3 py-2 text-emerald-800">Зеленый: недоступно</div>
              <div class="rounded-2xl border border-slate-200 bg-white px-3 py-2 text-slate-700">Белый: свободно</div>
            </div>

            <div v-if="rentalCalendarError" class="message-error mt-5">
              {{ rentalCalendarError }}
            </div>

            <div v-if="rentalCalendarLoading" class="mt-6 grid grid-cols-7 gap-2">
              <div v-for="item in 35" :key="item" class="h-20 animate-pulse rounded-2xl bg-slate-100"></div>
            </div>

            <template v-else>
              <div class="mt-6 grid grid-cols-7 gap-2 text-center text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                <span v-for="label in weekDayLabels" :key="label">{{ label }}</span>
              </div>

              <div class="mt-3 grid grid-cols-7 gap-2">
                <button
                  v-for="(day, index) in rentalCalendarCells"
                  :key="day?.date || `empty-${index}`"
                  type="button"
                  class="min-h-[4.75rem] overflow-hidden rounded-2xl border p-2 text-left transition"
                  :class="dayButtonClasses(day)"
                  :disabled="!day || day.status !== 'free'"
                  @click="selectRentalCalendarDay(day)"
                >
                  <template v-if="day">
                    <span class="text-base font-semibold">{{ Number(day.date.slice(-2)) }}</span>
                    <span class="mt-1 block max-w-full truncate text-[0.56rem] font-bold uppercase tracking-[0.08em] opacity-75 sm:text-[0.6rem]">
                      {{ dayStatusLabel(day) }}
                    </span>
                  </template>
                </button>
              </div>
            </template>
          </div>

          <aside class="space-y-4">
            <div class="rounded-[1.75rem] border border-slate-200 bg-white px-5 py-5 shadow-sm shadow-slate-900/5">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Выбранные даты</p>
              <p class="mt-3 text-lg font-semibold text-slate-950">
                {{ selectedRentalDatesLabel }}
              </p>
              <p class="mt-2 text-sm leading-6 text-slate-500">
                Первый клик выбирает старт, второй клик собирает диапазон. Если нужен новый диапазон, выбери дату еще раз.
              </p>
            </div>

            <div class="rounded-[1.75rem] border border-slate-200 bg-white px-5 py-5 shadow-sm shadow-slate-900/5">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Время аренды</p>
              <div class="mt-4 grid grid-cols-2 gap-3">
                <div>
                  <label class="field-label" for="rental-slot-start">Начало</label>
                  <input id="rental-slot-start" v-model="rentalSlotStartTime" type="time" class="field-input" />
                </div>
                <div>
                  <label class="field-label" for="rental-slot-end">Конец</label>
                  <input id="rental-slot-end" v-model="rentalSlotEndTime" type="time" class="field-input" />
                </div>
              </div>
              <p v-if="!hasValidRentalSlotTimes" class="mt-3 text-sm text-rose-600">
                Время окончания должно быть позже времени начала.
              </p>
            </div>

            <div class="rounded-[1.75rem] border border-slate-200 bg-white px-5 py-5 shadow-sm shadow-slate-900/5">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Комментарий</p>
              <textarea
                v-model="rentalRequestForm.organizer_message"
                rows="5"
                class="field-input mt-4 resize-none"
                placeholder="Например: нужен монтаж за час до старта, ожидаем камерный концерт на 150 гостей."
              ></textarea>
            </div>

            <button
              type="button"
              class="primary-button w-full justify-center"
              :disabled="rentalRequestSaving || !canSubmitRentalRequest"
              @click="submitRentalRequest"
            >
              {{ rentalRequestSaving ? 'Отправляем...' : `Отправить ${selectedRentalDates.length || ''} заявок` }}
            </button>
          </aside>
        </div>
      </section>
    </div>

    <section class="app-panel p-8 sm:p-10">
      <div class="flex flex-col gap-8 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
          <span class="info-chip">Кабинет организатора</span>
          <h2 class="mt-4 text-3xl font-semibold leading-tight text-slate-950">
            Управление мероприятиями, заявками на площадки и расписанием
          </h2>
          <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
            Сначала собирается событие, потом под него запрашивается площадка, и только после
            подтвержденной аренды создается сеанс для продажи билетов.
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

          <article class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-4 shadow-sm shadow-blue-900/5">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-blue-700">На модерации</p>
            <p class="mt-2 text-3xl font-semibold text-blue-950">{{ reviewCount }}</p>
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
              {{ organizerDisplayName }}
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
              <option value="pending_review">На модерации</option>
              <option value="published">Опубликованные</option>
              <option value="cancelled">Отмененные</option>
              <option value="archived">Архив</option>
            </select>
          </div>
        </div>

        <div v-if="loading" class="mt-6 space-y-3">
          <div v-for="item in 4" :key="item" class="h-24 animate-pulse rounded-[1.5rem] bg-slate-100"></div>
        </div>

        <div v-else-if="events.length > 0" class="mt-6 space-y-3">
          <button
            v-for="event in events"
            :key="event.id"
            type="button"
            class="w-full rounded-[1.5rem] border px-4 py-4 text-left transition"
            :class="event.id === selectedEventId ? 'border-blue-200 bg-blue-50/80 shadow-sm shadow-blue-900/5' : 'border-slate-200 bg-white/80 hover:border-slate-300'"
            @click="selectEvent(event)"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="truncate text-lg font-semibold text-slate-950">
                  {{ event.title }}
                </p>
                <p class="mt-2 text-sm text-slate-500">
                  {{ event.category?.name || 'Категория уточняется' }}
                </p>
              </div>

              <span class="status-badge shrink-0" :class="eventStatusClasses(event.status)">
                {{ eventStatusLabel(event.status) }}
              </span>
            </div>
          </button>
        </div>

        <div v-else class="mt-6 rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50/70 px-5 py-6">
          <p class="text-lg font-semibold text-slate-900">Мероприятий пока нет</p>
          <p class="mt-2 text-sm leading-6 text-slate-500">
            Начни с первого события. После этого здесь появится список для редактирования,
            подачи заявок на площадки и сборки сеансов.
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
                {{ activeEvent && eventFormMode === 'edit' ? activeEvent.title : 'Создание нового мероприятия' }}
              </h3>
              <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
                Собери карточку события и при необходимости отправь ее на модерацию.
              </p>
            </div>

            <div v-if="activeEvent" class="flex flex-wrap gap-3">
              <button type="button" class="danger-button" :disabled="eventSaving" @click="changeEventStatus('cancelled')">
                Отменить
              </button>
              <button type="button" class="secondary-button" :disabled="eventSaving" @click="changeEventStatus('archived')">
                В архив
              </button>
            </div>
          </div>

          <form class="mt-8 grid gap-5 sm:grid-cols-2" @submit.prevent="submitEvent">
            <div class="sm:col-span-2">
              <label class="field-label" for="organizer-event-title">Название</label>
              <input id="organizer-event-title" v-model="eventForm.title" type="text" class="field-input" placeholder="Большой летний концерт" />
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
              <input id="organizer-event-poster" v-model="eventForm.poster_url" type="url" class="field-input" placeholder="https://example.com/poster.jpg" />
            </div>

            <div class="sm:col-span-2">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <label class="field-label" for="organizer-event-description">Описание</label>
                <button
                  type="button"
                  class="secondary-button px-4 py-2.5 text-sm"
                  :disabled="!canRewriteDescription"
                  @click="rewriteDescription"
                >
                  {{ copywriting ? 'Митя переписывает...' : 'Улучшить через AI' }}
                </button>
              </div>
              <textarea id="organizer-event-description" v-model="eventForm.description" rows="5" class="field-input resize-none" placeholder="Подробно опиши мероприятие для карточки события"></textarea>
            </div>

            <div class="sm:col-span-2">
              <label class="field-label" for="organizer-event-tags">Теги</label>
              <input id="organizer-event-tags" v-model="eventForm.tags" type="text" class="field-input" placeholder="рок, open air, живая музыка" />
              <p class="mt-2 text-xs leading-5 text-slate-400">
                Через запятую. Они помогут в поиске и рекомендациях.
              </p>
            </div>

            <div>
              <label class="field-label" for="organizer-event-status">Статус</label>
              <select id="organizer-event-status" v-model="eventForm.status" class="field-input">
                <option value="draft">Черновик</option>
                <option value="pending_review">Отправить на модерацию</option>
              </select>
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

                <button :disabled="eventSaving" type="submit" class="primary-button min-w-56">
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

        <section v-if="activeEvent" class="app-panel p-8">
          <div class="flex flex-col gap-4 border-b border-slate-200/70 pb-6 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <span class="info-chip">Подбор площадки</span>
              <h3 class="mt-4 text-3xl font-semibold text-slate-950">
                Аренда зала под мероприятие
              </h3>
              <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
                Организатор выбирает площадку по адресу и ставке аренды, а затем отправляет заявку на нужный интервал.
              </p>
            </div>

            <div class="rounded-[1.75rem] border border-white/85 bg-white/85 px-5 py-4 shadow-sm shadow-slate-900/5">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Заявок</p>
              <p class="mt-2 text-3xl font-semibold text-slate-950">{{ rentalRequests.length }}</p>
            </div>
          </div>

          <div class="mt-8 grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
            <div class="space-y-5">
              <div class="grid gap-4 md:grid-cols-2">
                <div>
                  <label class="field-label" for="venue-search">Поиск площадки</label>
                  <input id="venue-search" v-model="venueFilters.search" type="text" class="field-input" placeholder="Название зала или площадки" />
                </div>
                <div>
                  <label class="field-label" for="venue-address">Адрес</label>
                  <input id="venue-address" v-model="venueFilters.address" type="text" class="field-input" placeholder="Нижний Новгород, центр" />
                </div>
                <div>
                  <label class="field-label" for="venue-min-price">Ставка от</label>
                  <input id="venue-min-price" v-model="venueFilters.min_hourly_rate" type="number" min="0" class="field-input" placeholder="1000" />
                </div>
                <div>
                  <label class="field-label" for="venue-max-price">Ставка до</label>
                  <input id="venue-max-price" v-model="venueFilters.max_hourly_rate" type="number" min="0" class="field-input" placeholder="5000" />
                </div>
              </div>

              <div class="flex justify-end">
                <button type="button" class="secondary-button" @click="loadPublicHalls">
                  Обновить список площадок
                </button>
              </div>

              <div v-if="hallsLoading" class="space-y-4">
                <div v-for="item in 3" :key="item" class="h-32 animate-pulse rounded-[1.5rem] bg-slate-100"></div>
              </div>

              <div v-else-if="publicHalls.length > 0" class="space-y-4">
                <article
                  v-for="hall in publicHalls"
                  :key="hall.id"
                  class="rounded-[1.5rem] border px-5 py-5 transition"
                  :class="Number(rentalRequestForm.hall_id) === hall.id ? 'border-blue-200 bg-blue-50/80 shadow-sm shadow-blue-900/5' : 'border-slate-200 bg-white hover:border-slate-300'"
                >
                  <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                      <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                        {{ hall.address || 'Адрес уточняется' }}
                      </p>
                      <h4 class="mt-2 text-xl font-semibold text-slate-950">
                        {{ hall.name }}
                      </h4>
                      <p class="mt-2 text-sm leading-6 text-slate-500">
                        {{ hall.description || 'Описание площадки пока не добавлено.' }}
                      </p>
                      <p class="mt-3 text-sm text-slate-500">
                        Вместимость: {{ hall.capacities?.total ?? 0 }}, уровней: {{ hall.layout_meta?.levels_count ?? 0 }}, элементов: {{ hall.layout_meta?.elements_count ?? 0 }}
                      </p>
                    </div>

                    <div class="rounded-[1.35rem] border border-slate-200 bg-slate-50 px-4 py-4 text-right">
                      <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Аренда / час</p>
                      <p class="mt-2 text-2xl font-semibold text-slate-950">
                        {{ formatPrice(hall.hourly_rate) }}
                      </p>
                    </div>
                  </div>

                  <div class="mt-5 flex justify-end">
                    <button
                      type="button"
                      class="secondary-button"
                      @click="selectHallForRental(hall.id)"
                    >
                      {{ Number(rentalRequestForm.hall_id) === hall.id ? 'Площадка выбрана' : 'Выбрать для заявки' }}
                    </button>
                  </div>
                </article>
              </div>

              <div v-else class="rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50/70 px-5 py-6 text-sm leading-6 text-slate-500">
                Подходящих площадок пока не найдено. Попробуй ослабить фильтры по адресу или цене.
              </div>
            </div>

            <div class="space-y-6">
              <form class="rounded-[1.6rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-900/5" @submit.prevent="openRentalCalendar">
                <div>
                  <span class="info-chip">Заявка на аренду</span>
                  <h4 class="mt-4 text-2xl font-semibold text-slate-950">
                    Выбранная площадка
                  </h4>
                </div>

                <div class="mt-5 rounded-[1.5rem] border border-slate-200 bg-slate-50 px-5 py-4 text-sm leading-6 text-slate-600">
                  <template v-if="selectedPublicHall">
                    <p class="font-semibold text-slate-950">{{ selectedPublicHall.name }}</p>
                    <p class="mt-1">{{ selectedPublicHall.address || 'Адрес уточняется' }}</p>
                    <p class="mt-1">Почасовая ставка: {{ formatPrice(selectedPublicHall.hourly_rate) }}</p>
                    <p class="mt-1">Вместимость: {{ selectedPublicHall.capacities?.total ?? 0 }}</p>
                  </template>
                  <template v-else>
                    Сначала выбери площадку слева, и здесь появится сводка для подачи заявки.
                  </template>
                </div>

                <div class="mt-5 grid gap-4">
                  <div class="rounded-[1.5rem] border border-dashed border-slate-200 bg-white px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                      Даты выбираются в календаре
                    </p>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                      Синий день уже занят подтвержденной арендой, зеленый недоступен по графику площадки, белый можно выбрать для заявки.
                    </p>
                  </div>

                  <div>
                    <label class="field-label" for="request-message">Комментарий для площадки</label>
                    <textarea
                      id="request-message"
                      v-model="rentalRequestForm.organizer_message"
                      rows="4"
                      class="field-input resize-none"
                      placeholder="Например: нужен монтаж за час до старта, ожидаем камерный концерт на 150 гостей."
                    ></textarea>
                  </div>
                </div>

                <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                  <button type="button" class="secondary-button" @click="resetRentalRequestForm">
                    Сбросить
                  </button>
                  <button :disabled="!activeEvent || !selectedPublicHall" type="submit" class="primary-button sm:min-w-56">
                    Открыть календарь и отправить заявку
                  </button>
                </div>
              </form>

              <div class="rounded-[1.6rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-900/5">
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <span class="info-chip">Статусы заявок</span>
                    <h4 class="mt-4 text-2xl font-semibold text-slate-950">История по событию</h4>
                  </div>

                  <button type="button" class="secondary-button" @click="loadRentalRequests(activeEvent.id)">
                    Обновить
                  </button>
                </div>

                <div v-if="rentalRequestsLoading" class="mt-5 space-y-3">
                  <div v-for="item in 3" :key="item" class="h-24 animate-pulse rounded-[1.25rem] bg-slate-100"></div>
                </div>

                <div v-else-if="rentalRequests.length > 0" class="mt-5 space-y-3">
                  <article
                    v-for="request in rentalRequests"
                    :key="request.id"
                    class="rounded-[1.35rem] border border-slate-200 bg-slate-50 px-4 py-4"
                  >
                    <div class="flex items-start justify-between gap-3">
                      <div class="min-w-0">
                        <p class="truncate text-lg font-semibold text-slate-950">
                          {{ request.hall?.name || `Площадка #${request.hall_id}` }}
                        </p>
                        <p class="mt-2 text-sm text-slate-500">
                          {{ formatDateTime(request.requested_start) }} — {{ formatDateTime(request.requested_end) }}
                        </p>
                        <p class="mt-1 text-sm text-slate-500">
                          {{ request.hall?.address || 'Адрес уточняется' }}
                        </p>
                      </div>

                      <span class="status-badge shrink-0" :class="rentalRequestStatusClasses(request.status)">
                        {{ rentalRequestStatusLabel(request.status) }}
                      </span>
                    </div>

                    <p class="mt-3 text-sm font-semibold text-slate-900">
                      Итого за аренду: {{ formatPrice(request.total_amount) }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                      {{ formatRentalDuration(request.duration_minutes) }} · {{ formatPrice(request.hourly_rate) }}/час
                    </p>
                    <p v-if="request.response_note" class="mt-2 text-sm leading-6 text-slate-500">
                      Ответ площадки: {{ request.response_note }}
                    </p>
                  </article>
                </div>

                <div v-else class="mt-5 rounded-[1.35rem] border border-dashed border-slate-200 bg-slate-50/70 px-4 py-5 text-sm leading-6 text-slate-500">
                  По этому событию пока нет заявок на аренду. Выбери площадку слева и отправь первую заявку.
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="app-panel p-8">
          <div class="flex flex-col gap-4 border-b border-slate-200/70 pb-6 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <span class="info-chip">Сеансы мероприятия</span>
              <h3 class="mt-4 text-3xl font-semibold text-slate-950">
                {{ activeEvent ? 'Расписание по одобренным арендам' : 'Сначала сохрани мероприятие' }}
              </h3>
              <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
                Сеанс можно собрать только из заявки, которую площадка уже подтвердила.
              </p>
            </div>

            <div class="rounded-[1.75rem] border border-white/85 bg-white/85 px-5 py-4 shadow-sm shadow-slate-900/5">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Сеансов</p>
              <p class="mt-2 text-3xl font-semibold text-slate-950">{{ sessions.length }}</p>
            </div>
          </div>

          <div v-if="activeEvent" class="mt-8 grid gap-6 xl:grid-cols-[0.92fr_1.08fr]">
            <form class="space-y-5" @submit.prevent="submitSession">
              <div
                v-if="!canManageSessions"
                class="rounded-[1.5rem] border border-amber-200 bg-amber-50 px-5 py-4 text-sm leading-6 text-amber-900"
              >
                Сеансы откроются после публикации события администратором. Если событие уже опубликовано, но сеансов нет, оно автоматически показывается в каталоге как тизер.
              </div>

              <div>
                <label class="field-label" for="session-rental-request-id">Подтвержденная заявка</label>
                <select id="session-rental-request-id" v-model="sessionForm.hall_rental_request_id" class="field-input" :disabled="!canManageSessions">
                  <option value="">Выбери одобренную заявку</option>
                  <option v-for="request in availableApprovedRequests" :key="request.id" :value="String(request.id)">
                    {{ request.hall?.name || `Площадка #${request.hall_id}` }} — {{ formatDateTime(request.requested_start) }}
                  </option>
                </select>
              </div>

              <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50/80 px-5 py-4 text-sm leading-6 text-slate-600">
                <template v-if="activeRentalRequest">
                  <p class="font-semibold text-slate-900">
                    {{ activeRentalRequest.hall?.name || `Площадка #${activeRentalRequest.hall_id}` }}
                  </p>
                  <p class="mt-1 text-slate-500">
                    {{ activeRentalRequest.hall?.address || 'Адрес уточняется' }}
                  </p>
                  <p class="mt-1">
                    {{ formatDateTime(activeRentalRequest.requested_start) }} — {{ formatDateTime(activeRentalRequest.requested_end) }}
                  </p>
                  <p class="mt-1">
                    Аренда: {{ formatPrice(activeRentalRequest.total_amount) }} за {{ formatRentalDuration(activeRentalRequest.duration_minutes) }}, ставка: {{ formatPrice(activeRentalRequest.hourly_rate) }}/час
                  </p>
                </template>
                <template v-else>
                  Выбери одобренную заявку. Время и площадка для сеанса будут взяты именно из нее.
                </template>
              </div>

              <div>
                <label class="field-label" for="session-base-price">Базовая цена билета</label>
                <input
                  id="session-base-price"
                  v-model="sessionForm.base_price"
                  type="text"
                  inputmode="decimal"
                  class="field-input"
                  :disabled="!canManageSessions"
                  placeholder="2500 или 2500.50"
                />
                <p class="mt-2 text-xs leading-5 text-slate-500">
                  Можно вводить цену через точку или запятую.
                </p>
              </div>

              <div class="flex flex-col gap-3 sm:flex-row">
                <button type="button" class="secondary-button" @click="resetSessionForm">
                  Сбросить форму
                </button>

                <button :disabled="sessionSaving || !canSubmitSession" type="submit" class="primary-button sm:min-w-56">
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
                <div v-for="item in 3" :key="item" class="h-28 animate-pulse rounded-[1.5rem] bg-slate-100"></div>
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
                        {{ session.hall?.name || `Площадка #${session.hall_id}` }}
                      </p>
                      <h4 class="mt-2 text-xl font-semibold text-slate-950">
                        {{ formatDateTime(session.start_time) }}
                      </h4>
                      <p class="mt-2 text-sm text-slate-500">
                        До {{ formatDateTime(session.end_time) }}
                      </p>
                      <p v-if="session.hall?.address" class="mt-2 text-sm text-slate-500">
                        {{ session.hall.address }}
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
                  После подтверждения аренды площадки можно создать первый сеанс и открыть продажу билетов.
                </p>
              </div>
            </div>
          </div>

          <div v-else class="mt-8 rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50/70 px-6 py-8">
            <p class="text-lg font-semibold text-slate-900">Мероприятие еще не выбрано</p>
            <p class="mt-2 text-sm leading-6 text-slate-500">
              Создай новое мероприятие или выбери существующее слева, чтобы управлять площадками и сеансами.
            </p>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>
