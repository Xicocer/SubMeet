<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  cancelBookingRequest,
  downloadTicketRequest,
  getLoyaltyAccountRequest,
  getMyBookingsRequest,
  payBookingRequest,
  refreshBookingPaymentRequest,
} from '@/api/booking'
import { getWantToGoEventsRequest, removeWantToGoRequest } from '@/api/events'
import { getUserRecommendationsRequest } from '@/api/recommendations'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import type { UpdateProfilePayload } from '@/types/auth'
import type { LoyaltyAccountResponse, UserBooking } from '@/types/booking'
import type { WantToGoEvent } from '@/types/event'
import type { RecommendationItem } from '@/types/recommendation'
import {
  downloadBookingCalendarFile,
  getRecentlyViewedEvents,
  shareEvent,
  type RecentlyViewedEvent,
} from '@/utils/eventUx'
import {
  formatDate,
  formatDateForInput,
  formatDateTime,
  formatPrice,
  getInitials,
} from '@/utils/format'
import { formatPhoneMask, isPhoneMaskComplete, normalizePhoneComparable } from '@/utils/phone'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { showToast } = useToast()

const successMessage = ref('')
const cabinetMessage = ref('')
const cabinetError = ref('')
const bookingsLoading = ref(false)
const wantToGoLoading = ref(false)
const recommendationsLoading = ref(false)
const bookingActionIds = ref<number[]>([])
const ticketDownloadIds = ref<number[]>([])
const wantToGoActionIds = ref<number[]>([])
const bookings = ref<UserBooking[]>([])
const wantToGoEvents = ref<WantToGoEvent[]>([])
const recommendations = ref<RecommendationItem[]>([])
const recentlyViewedEvents = ref<RecentlyViewedEvent[]>([])
const loyaltyAccount = ref<LoyaltyAccountResponse | null>(null)

const form = reactive<UpdateProfilePayload>({
  full_name: '',
  phone: '',
  birth_date: '',
  company_name: '',
})

const user = computed(() => authStore.user)
const isBusinessUser = computed(() => authStore.isOrganizer || authStore.isVenueOwner)
const profileTitle = computed(() => {
  if (!user.value) {
    return 'Пользователь'
  }

  return user.value.organizer_profile?.company_name || user.value.full_name
})
const initials = computed(() => getInitials(profileTitle.value))
const businessCabinetRoute = computed(() => (authStore.isVenueOwner ? '/venue/halls' : '/organizer/events'))
const businessCabinetLabel = computed(() => (authStore.isVenueOwner ? 'Открыть кабинет площадки' : 'Открыть кабинет организатора'))
const roleLabel = computed(() => (authStore.isOrganizer ? 'Организатор' : 'Пользователь'))
const statusLabel = computed(() => (user.value?.status === 1 ? 'Активен' : 'Ограничен'))
const memberSinceLabel = computed(() => formatDate(user.value?.created_at))

const statusClasses = computed(() => (
  user.value?.status === 1
    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
    : 'border-amber-200 bg-amber-50 text-amber-700'
))

const reservationBookings = computed(() => bookings.value.filter((booking) => booking.status !== 'confirmed'))
const ticketBookings = computed(() => bookings.value.filter((booking) => booking.status === 'confirmed'))
const pendingBookings = computed(() => bookings.value.filter((booking) => ['reserved', 'payment_pending'].includes(booking.status)))
const loyaltyBalance = computed(() => loyaltyAccount.value?.balance ?? 0)
const sortedCabinetBookings = computed(() => {
  return [...bookings.value].sort((left, right) => {
    const leftTime = left.session?.start_time ? new Date(left.session.start_time).getTime() : Number.MAX_SAFE_INTEGER
    const rightTime = right.session?.start_time ? new Date(right.session.start_time).getTime() : Number.MAX_SAFE_INTEGER

    return leftTime - rightTime
  })
})
const nextPlannedBooking = computed(() => (
  sortedCabinetBookings.value.find((booking) => ['reserved', 'payment_pending', 'confirmed'].includes(booking.status)) ?? null
))
const nextPlannedBookingStatus = computed(() => {
  if (!nextPlannedBooking.value) {
    return ''
  }

  return nextPlannedBooking.value.status === 'confirmed' ? 'Оплачено' : bookingStatusLabel(nextPlannedBooking.value)
})

const hasChanges = computed(() => {
  if (!user.value) {
    return false
  }

  return (
    form.full_name !== user.value.full_name ||
    normalizePhoneComparable(form.phone) !== normalizePhoneComparable(user.value.phone) ||
    form.birth_date !== formatDateForInput(user.value.birth_date) ||
    (form.company_name || '') !== (user.value.organizer_profile?.company_name || '')
  )
})

const syncForm = () => {
  if (!authStore.user) {
    return
  }

  form.full_name = authStore.user.full_name
  form.phone = formatPhoneMask(authStore.user.phone)
  form.birth_date = formatDateForInput(authStore.user.birth_date)
  form.company_name = authStore.user.organizer_profile?.company_name || ''
}

const handlePhoneInput = (event: Event) => {
  const input = event.target as HTMLInputElement
  form.phone = formatPhoneMask(input.value)
}

const bookingStatusLabel = (booking: UserBooking) => {
  switch (booking.status) {
    case 'reserved':
      return 'Забронировано'
    case 'payment_pending':
      return 'Ожидает оплаты'
    case 'confirmed':
      return 'Оплачено'
    case 'cancelled':
      return 'Отменено'
    case 'expired':
      return 'Истекло'
    default:
      return booking.status
  }
}

const bookingStatusClasses = (booking: UserBooking) => {
  switch (booking.status) {
    case 'reserved':
      return 'border-blue-200 bg-blue-50 text-blue-700'
    case 'payment_pending':
      return 'border-amber-200 bg-amber-50 text-amber-700'
    case 'confirmed':
      return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    case 'cancelled':
      return 'border-rose-200 bg-rose-50 text-rose-700'
    default:
      return 'border-slate-200 bg-slate-100 text-slate-600'
  }
}

const bookingItemsLabel = (booking: UserBooking) => {
  if (booking.items.length === 0) {
    return 'Состав брони уточняется'
  }

  return booking.items
    .map((item) => (item.quantity > 1 ? `${item.label} ×${item.quantity}` : item.label))
    .join(', ')
}

const recommendationLocation = (item: RecommendationItem) => {
  return item.venue_address || item.hall_name || item.city || 'Площадка уточняется'
}

const recentlyViewedDateLabel = (eventItem: RecentlyViewedEvent) => {
  if (eventItem.next_session_start) {
    return formatDateTime(eventItem.next_session_start)
  }

  return eventItem.is_teaser ? 'Продажи скоро' : 'Дата уточняется'
}

const recentlyViewedPriceLabel = (eventItem: RecentlyViewedEvent) => {
  return eventItem.minimum_price !== null
    ? formatPrice(eventItem.minimum_price)
    : 'Цена уточняется'
}

const paymentActionLabel = (booking: UserBooking) => {
  return booking.status === 'payment_pending' ? 'Продолжить оплату' : 'Оплатить'
}

const extractErrorMessage = (requestError: unknown, fallback: string) => {
  const errorCandidate = requestError as {
    response?: {
      data?: {
        message?: string
        errors?: Record<string, string[]>
      }
    }
  }

  const validationErrors = errorCandidate.response?.data?.errors

  if (validationErrors && typeof validationErrors === 'object') {
    const firstEntry = Object.values(validationErrors)[0]

    if (Array.isArray(firstEntry) && firstEntry.length > 0) {
      return String(firstEntry[0])
    }
  }

  return errorCandidate.response?.data?.message || fallback
}

const isBookingActionLoading = (bookingId: number) => bookingActionIds.value.includes(bookingId)
const isTicketDownloading = (bookingId: number) => ticketDownloadIds.value.includes(bookingId)
const isWantToGoActionLoading = (eventId: number) => wantToGoActionIds.value.includes(eventId)

const replaceBooking = (updatedBooking: UserBooking) => {
  const exists = bookings.value.some((booking) => booking.id === updatedBooking.id)

  bookings.value = exists
    ? bookings.value.map((booking) => (booking.id === updatedBooking.id ? updatedBooking : booking))
    : [updatedBooking, ...bookings.value]
}

const loadBookings = async () => {
  if (isBusinessUser.value) {
    bookings.value = []
    return
  }

  bookingsLoading.value = true

  try {
    const response = await getMyBookingsRequest(30)
    bookings.value = response.data
  } catch (requestError) {
    console.error(requestError)
    cabinetError.value = 'Не удалось загрузить брони пользователя.'
    bookings.value = []
  } finally {
    bookingsLoading.value = false
  }
}

const loadWantToGo = async () => {
  if (isBusinessUser.value) {
    wantToGoEvents.value = []
    return
  }

  wantToGoLoading.value = true

  try {
    wantToGoEvents.value = await getWantToGoEventsRequest()
  } catch (requestError) {
    console.error(requestError)
    cabinetError.value = 'Не удалось загрузить список «Хочу сходить».'
    wantToGoEvents.value = []
  } finally {
    wantToGoLoading.value = false
  }
}

const loadLoyalty = async () => {
  if (isBusinessUser.value) {
    loyaltyAccount.value = null
    return
  }

  try {
    loyaltyAccount.value = await getLoyaltyAccountRequest()
  } catch (requestError) {
    console.error(requestError)
    loyaltyAccount.value = null
  }
}

const loadRecommendations = async () => {
  if (isBusinessUser.value || !authStore.user) {
    recommendations.value = []
    return
  }

  recommendationsLoading.value = true

  try {
    const response = await getUserRecommendationsRequest(authStore.user.id, 4)
    recommendations.value = response.items
  } catch (requestError) {
    console.error(requestError)
    recommendations.value = []
  } finally {
    recommendationsLoading.value = false
  }
}

const loadCabinetData = async () => {
  cabinetError.value = ''
  recentlyViewedEvents.value = getRecentlyViewedEvents()

  await Promise.all([
    loadBookings(),
    loadWantToGo(),
    loadLoyalty(),
    loadRecommendations(),
  ])
}

const syncReturnedPayment = async () => {
  if (isBusinessUser.value) {
    return
  }

  const bookingQuery = Array.isArray(route.query.booking) ? route.query.booking[0] : route.query.booking
  const paymentQuery = Array.isArray(route.query.payment) ? route.query.payment[0] : route.query.payment
  const bookingId = Number(bookingQuery)

  if (paymentQuery !== 'return' || Number.isNaN(bookingId) || bookingId < 1) {
    return
  }

  bookingActionIds.value = [...bookingActionIds.value, bookingId]

  try {
    const response = await refreshBookingPaymentRequest(bookingId)
    replaceBooking(response.booking)

    if (response.booking.status === 'confirmed') {
      cabinetMessage.value = response.booking.ticket
        ? 'Оплата подтверждена. Билет уже доступен для скачивания.'
        : 'Оплата подтверждена. PDF-билет еще генерируется в фоне и скоро появится в кабинете.'
      await loadLoyalty()
      showToast({
        kind: 'success',
        title: 'Оплата подтверждена',
        message: 'Билет можно открыть или скачать в личном кабинете.',
      })
    } else if (response.booking.status === 'payment_pending') {
      cabinetMessage.value = 'Платеж еще обрабатывается. Обнови страницу чуть позже.'
      showToast({
        kind: 'info',
        title: 'Платеж еще обрабатывается',
        message: 'Обнови статус чуть позже.',
      })
    } else {
      cabinetMessage.value = 'Статус платежа обновлен.'
      showToast('Статус платежа обновлен')
    }
  } catch (requestError) {
    console.error(requestError)
    cabinetError.value = extractErrorMessage(requestError, 'Не удалось обновить статус оплаты.')
  } finally {
    bookingActionIds.value = bookingActionIds.value.filter((id) => id !== bookingId)

    await router.replace({
      path: route.path,
      query: {
        ...route.query,
        booking: undefined,
        payment: undefined,
      },
    })
  }
}

const loadProfile = async () => {
  const currentUser = await authStore.fetchMe()

  if (!currentUser) {
    await router.push('/login')
    return
  }

  syncForm()
  await loadCabinetData()
  await syncReturnedPayment()
}

const updateProfile = async () => {
  successMessage.value = ''

  if (!isPhoneMaskComplete(form.phone)) {
    authStore.error = 'Укажи телефон в формате +7/8 (xxx) xxx-xx-xx.'
    return
  }

  try {
    const payload: UpdateProfilePayload = {
      full_name: form.full_name,
      phone: form.phone,
      birth_date: isBusinessUser.value ? '' : form.birth_date,
      ...(isBusinessUser.value ? { company_name: form.company_name?.trim() || '' } : {}),
    }

    const response = await authStore.updateProfile(payload)
    successMessage.value = response.message
    showToast({
      kind: 'success',
      title: 'Профиль сохранен',
      message: 'Данные аккаунта обновлены.',
    })
    syncForm()
  } catch (requestError) {
    console.error(requestError)
  }
}

const payBooking = async (bookingId: number) => {
  bookingActionIds.value = [...bookingActionIds.value, bookingId]
  cabinetMessage.value = ''
  cabinetError.value = ''

  try {
    const response = await payBookingRequest(bookingId)
    replaceBooking(response.booking)
    await loadLoyalty()

    const confirmationUrl = response.booking.payment?.confirmation_url

    if (!confirmationUrl) {
      cabinetError.value = 'Платежная ссылка пока не сформирована.'
      showToast({
        kind: 'warning',
        title: 'Платежная ссылка не готова',
        message: 'Попробуй повторить действие чуть позже.',
      })
      return
    }

    showToast({
      kind: 'info',
      title: 'Открываем оплату',
      message: 'После оплаты билет появится в профиле.',
    })
    window.location.href = confirmationUrl
  } catch (requestError) {
    console.error(requestError)
    cabinetError.value = extractErrorMessage(requestError, 'Не удалось перейти к оплате.')
    showToast({
      kind: 'error',
      title: 'Не удалось перейти к оплате',
      message: cabinetError.value,
    })
  } finally {
    bookingActionIds.value = bookingActionIds.value.filter((id) => id !== bookingId)
  }
}

const cancelBooking = async (bookingId: number) => {
  bookingActionIds.value = [...bookingActionIds.value, bookingId]
  cabinetMessage.value = ''
  cabinetError.value = ''

  try {
    const response = await cancelBookingRequest(bookingId)
    replaceBooking(response.booking)
    await loadLoyalty()
    cabinetMessage.value = 'Бронь отменена, места снова доступны в продаже.'
    showToast({
      kind: 'success',
      title: 'Бронь отменена',
      message: 'Места снова доступны другим пользователям.',
    })
  } catch (requestError) {
    console.error(requestError)
    cabinetError.value = extractErrorMessage(requestError, 'Не удалось отменить бронь.')
    showToast({
      kind: 'error',
      title: 'Не удалось отменить бронь',
      message: cabinetError.value,
    })
  } finally {
    bookingActionIds.value = bookingActionIds.value.filter((id) => id !== bookingId)
  }
}

const downloadTicket = async (bookingId: number) => {
  ticketDownloadIds.value = [...ticketDownloadIds.value, bookingId]
  cabinetMessage.value = ''
  cabinetError.value = ''

  try {
    const { blob, contentDisposition } = await downloadTicketRequest(bookingId)
    const objectUrl = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    const fileNameMatch = contentDisposition?.match(/filename=\"?([^\";]+)\"?/)

    link.href = objectUrl
    link.download = fileNameMatch?.[1] || `submeet-ticket-${bookingId}.pdf`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(objectUrl)
    showToast({
      kind: 'success',
      title: 'PDF скачан',
      message: 'Билет готов к печати или показу на входе.',
    })
  } catch (requestError) {
    console.error(requestError)
    cabinetError.value = extractErrorMessage(requestError, 'Не удалось скачать билет.')
    showToast({
      kind: 'error',
      title: 'Не удалось скачать билет',
      message: cabinetError.value,
    })
  } finally {
    ticketDownloadIds.value = ticketDownloadIds.value.filter((id) => id !== bookingId)
  }
}

const removeWantToGo = async (eventId: number) => {
  wantToGoActionIds.value = [...wantToGoActionIds.value, eventId]
  cabinetMessage.value = ''
  cabinetError.value = ''

  try {
    await removeWantToGoRequest(eventId)
    wantToGoEvents.value = wantToGoEvents.value.filter((event) => event.id !== eventId)
    cabinetMessage.value = 'Событие убрано из списка «Хочу сходить».'
    showToast({
      kind: 'success',
      title: 'Убрали из списка',
      message: 'Событие больше не отображается в “Хочу сходить”.',
    })
  } catch (requestError) {
    console.error(requestError)
    cabinetError.value = 'Не удалось обновить список «Хочу сходить».'
    showToast({
      kind: 'error',
      title: 'Не удалось обновить список',
      message: cabinetError.value,
    })
  } finally {
    wantToGoActionIds.value = wantToGoActionIds.value.filter((id) => id !== eventId)
  }
}

const addBookingToCalendar = (booking: UserBooking) => {
  const created = downloadBookingCalendarFile(booking)

  showToast({
    kind: created ? 'success' : 'warning',
    title: created ? 'Файл календаря скачан' : 'Дата события не указана',
    message: created
      ? 'Открой .ics-файл, чтобы добавить событие в календарь.'
      : 'Для календаря нужна точная дата и время сеанса.',
  })
}

const shareRecentlyViewed = async (eventItem: RecentlyViewedEvent) => {
  try {
    const result = await shareEvent(eventItem)

    if (result === 'cancelled') {
      return
    }

    showToast({
      kind: 'success',
      title: result === 'copied' ? 'Ссылка скопирована' : 'Событие готово к отправке',
      message: 'Можно быстро вернуться к событию или отправить его друзьям.',
    })
  } catch (shareError) {
    console.error(shareError)
    showToast({
      kind: 'error',
      title: 'Не удалось поделиться',
      message: 'Попробуй открыть событие и скопировать ссылку из адресной строки.',
    })
  }
}

watch(
  () => authStore.user,
  () => {
    syncForm()
  },
)

onMounted(loadProfile)
</script>

<template>
  <section v-if="authStore.loading && !user" class="app-panel p-8 sm:p-10">
    <div class="flex items-center gap-4">
      <div class="h-16 w-16 animate-pulse rounded-[1.5rem] bg-slate-200"></div>
      <div class="space-y-3">
        <div class="h-4 w-40 animate-pulse rounded-full bg-slate-200"></div>
        <div class="h-4 w-60 animate-pulse rounded-full bg-slate-100"></div>
      </div>
    </div>
    <p class="mt-6 text-sm text-slate-500">Загружаем личный кабинет...</p>
  </section>

  <div v-else class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
    <aside class="space-y-6">
      <section class="app-panel overflow-hidden">
        <div class="bg-gradient-to-br from-slate-950 via-slate-900 to-blue-900 px-6 py-7 text-white">
          <div class="flex items-start justify-between gap-4">
            <div class="flex h-[4.5rem] w-[4.5rem] items-center justify-center rounded-[1.55rem] bg-white/10 text-2xl font-semibold sm:h-20 sm:w-20">
              {{ initials }}
            </div>

            <div class="status-badge border-white/15 bg-white/10 text-white/85">
              {{ statusLabel }}
            </div>
          </div>

          <h1 class="mt-5 text-3xl font-semibold leading-tight tracking-[-0.04em]">
            {{ profileTitle }}
          </h1>
          <p class="mt-2 break-words text-sm leading-6 text-white/72">
            {{ user?.email || 'Email не указан' }}
          </p>
          <p v-if="isBusinessUser" class="mt-2 text-sm leading-6 text-white/72">
            Контактное лицо: {{ user?.full_name || 'Не указано' }}
          </p>
        </div>

        <div class="space-y-4 p-5">
          <RouterLink
            v-if="isBusinessUser"
            :to="businessCabinetRoute"
            class="primary-button w-full"
          >
            {{ businessCabinetLabel }}
          </RouterLink>

          <RouterLink v-else to="/events" class="primary-button w-full">
            Найти событие
          </RouterLink>

          <div v-if="!isBusinessUser" class="grid grid-cols-3 gap-2">
            <article class="rounded-[1.25rem] border border-slate-200 bg-slate-50 px-3 py-4 text-center">
              <p class="text-2xl font-semibold text-slate-950">{{ ticketBookings.length }}</p>
              <p class="mt-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Билеты</p>
            </article>
            <article class="rounded-[1.25rem] border border-slate-200 bg-slate-50 px-3 py-4 text-center">
              <p class="text-2xl font-semibold text-slate-950">{{ pendingBookings.length }}</p>
              <p class="mt-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Брони</p>
            </article>
            <article class="rounded-[1.25rem] border border-slate-200 bg-slate-50 px-3 py-4 text-center">
              <p class="text-2xl font-semibold text-slate-950">{{ wantToGoEvents.length }}</p>
              <p class="mt-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Хочу</p>
            </article>
          </div>
        </div>
      </section>

      <section v-if="!isBusinessUser" class="app-panel border-blue-100 bg-blue-50/70 p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-blue-700">Бонусные баллы</p>
        <p class="mt-4 text-5xl font-semibold tracking-[-0.06em] text-slate-950">{{ loyaltyBalance }}</p>
        <p class="mt-3 text-sm leading-6 text-blue-950/72">
          Баллами можно оплатить до {{ loyaltyAccount?.max_discount_percent ?? 80 }}% следующего заказа.
          После покупки начисляется {{ loyaltyAccount?.earn_percent ?? 15 }}% от суммы.
        </p>
      </section>

      <section class="app-panel p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Данные аккаунта</p>
        <div class="mt-5 space-y-4 text-sm">
          <div class="flex items-center justify-between gap-4">
            <span class="text-slate-500">Роль</span>
            <span class="font-semibold text-slate-950">{{ roleLabel }}</span>
          </div>
          <div class="flex items-center justify-between gap-4">
            <span class="text-slate-500">Статус</span>
            <span class="status-badge" :class="statusClasses">{{ statusLabel }}</span>
          </div>
          <div class="flex items-center justify-between gap-4">
            <span class="text-slate-500">В системе</span>
            <span class="font-semibold text-slate-950">{{ memberSinceLabel }}</span>
          </div>
          <div class="flex items-center justify-between gap-4">
            <span class="text-slate-500">Телефон</span>
            <span class="break-all text-right font-semibold text-slate-950">{{ user?.phone || 'Не указан' }}</span>
          </div>
          <div v-if="!isBusinessUser" class="flex items-center justify-between gap-4">
            <span class="text-slate-500">Дата рождения</span>
            <span class="font-semibold text-slate-950">{{ formatDate(user?.birth_date) }}</span>
          </div>
          <div v-if="isBusinessUser" class="flex items-center justify-between gap-4">
            <span class="text-slate-500">Компания</span>
            <span class="break-words text-right font-semibold text-slate-950">{{ user?.organizer_profile?.company_name || 'Не указана' }}</span>
          </div>
        </div>
      </section>
    </aside>

    <div class="space-y-6">
      <template v-if="!isBusinessUser">
        <div v-if="cabinetMessage" class="message-success">
          {{ cabinetMessage }}
        </div>

        <div v-if="cabinetError" class="message-error">
          {{ cabinetError }}
        </div>

        <section class="app-panel overflow-hidden">
          <div class="grid gap-0 lg:grid-cols-[1fr_18rem]">
            <div class="p-6 sm:p-8">
              <span class="info-chip">Следующее мероприятие</span>

              <template v-if="nextPlannedBooking">
                <div class="mt-5 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                  <div>
                    <h2 class="text-3xl font-semibold tracking-[-0.04em] text-slate-950 sm:text-4xl">
                      {{ nextPlannedBooking.session?.event_title || 'Событие загружается' }}
                    </h2>
                    <div class="mt-4 flex flex-wrap gap-2">
                      <span class="status-badge" :class="bookingStatusClasses(nextPlannedBooking)">
                        {{ nextPlannedBookingStatus }}
                      </span>
                      <span class="status-badge border-blue-100 bg-blue-50 text-blue-700">
                        {{ formatPrice(nextPlannedBooking.total_amount) }}
                      </span>
                    </div>
                  </div>
                </div>

                <div class="mt-6 grid gap-3 md:grid-cols-3">
                  <article class="rounded-[1.4rem] border border-slate-200 bg-slate-50/80 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Дата</p>
                    <p class="mt-2 text-sm font-semibold leading-6 text-slate-950">
                      {{ formatDateTime(nextPlannedBooking.session?.start_time) }}
                    </p>
                  </article>
                  <article class="rounded-[1.4rem] border border-slate-200 bg-slate-50/80 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Площадка</p>
                    <p class="mt-2 text-sm font-semibold leading-6 text-slate-950">
                      {{ nextPlannedBooking.session?.hall_address || nextPlannedBooking.session?.hall_name || 'Площадка уточняется' }}
                    </p>
                  </article>
                  <article class="rounded-[1.4rem] border border-slate-200 bg-slate-50/80 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Места</p>
                    <p class="mt-2 text-sm font-semibold leading-6 text-slate-950">
                      {{ bookingItemsLabel(nextPlannedBooking) }}
                    </p>
                  </article>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                  <RouterLink
                    v-if="nextPlannedBooking.session?.event_id"
                    :to="`/events/${nextPlannedBooking.session.event_id}`"
                    class="secondary-button"
                  >
                    Перейти к событию
                  </RouterLink>
                  <button
                    v-if="nextPlannedBooking.status === 'confirmed'"
                    type="button"
                    class="primary-button"
                    :disabled="isTicketDownloading(nextPlannedBooking.id)"
                    @click="downloadTicket(nextPlannedBooking.id)"
                  >
                    {{ isTicketDownloading(nextPlannedBooking.id) ? 'Готовим PDF...' : 'Скачать PDF' }}
                  </button>
                  <button
                    type="button"
                    class="secondary-button"
                    @click="addBookingToCalendar(nextPlannedBooking)"
                  >
                    В календарь
                  </button>
                  <button
                    v-if="nextPlannedBooking.can_pay"
                    type="button"
                    class="primary-button"
                    :disabled="isBookingActionLoading(nextPlannedBooking.id)"
                    @click="payBooking(nextPlannedBooking.id)"
                  >
                    {{ isBookingActionLoading(nextPlannedBooking.id) ? 'Переходим...' : paymentActionLabel(nextPlannedBooking) }}
                  </button>
                </div>
              </template>

              <template v-else>
                <h2 class="mt-5 text-3xl font-semibold tracking-[-0.04em] text-slate-950 sm:text-4xl">
                  Пока нет запланированных событий
                </h2>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-500 sm:text-base">
                  Как только ты забронируешь место или купишь билет, ближайшее мероприятие появится здесь первым.
                </p>
                <RouterLink to="/events" class="primary-button mt-6">
                  Перейти в каталог
                </RouterLink>
              </template>
            </div>

            <div class="flex items-end bg-gradient-to-br from-blue-50 via-slate-50 to-white p-6">
              <div class="w-full rounded-[1.7rem] border border-white bg-white/80 p-5 shadow-[0_28px_90px_-70px_rgba(37,99,235,0.6)]">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-700">Центр активности</p>
                <p class="mt-3 text-sm leading-6 text-slate-500">
                  Здесь собраны билеты, брони, избранное и персональные подсказки.
                </p>
              </div>
            </div>
          </div>
        </section>

        <section v-if="recommendationsLoading || recommendations.length > 0" class="app-panel p-6 sm:p-8">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <span class="info-chip">Подборка для вас</span>
              <h3 class="mt-4 text-3xl font-semibold tracking-[-0.04em] text-slate-950">
                Можно сходить дальше
              </h3>
            </div>
            <RouterLink to="/events" class="secondary-button">
              Вся афиша
            </RouterLink>
          </div>

          <div v-if="recommendationsLoading" class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article v-for="item in 4" :key="item" class="overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white">
              <div class="h-36 animate-pulse bg-slate-200"></div>
              <div class="space-y-3 p-4">
                <div class="h-4 w-24 animate-pulse rounded-full bg-slate-100"></div>
                <div class="h-6 w-3/4 animate-pulse rounded-full bg-slate-200"></div>
              </div>
            </article>
          </div>

          <div v-else class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <RouterLink
              v-for="item in recommendations"
              :key="item.id"
              :to="`/events/${item.id}`"
              class="group overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-sm shadow-slate-900/5 transition duration-200 hover:-translate-y-1 hover:border-blue-200"
            >
              <div class="relative h-36 overflow-hidden bg-gradient-to-br from-slate-950 via-slate-900 to-blue-900">
                <img
                  v-if="item.poster_url"
                  :src="item.poster_url"
                  :alt="item.title"
                  class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]"
                />
                <div class="absolute inset-x-0 top-0 flex justify-between p-3">
                  <span class="rounded-full bg-slate-950/60 px-3 py-1 text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-white">
                    {{ item.category_name || item.category }}
                  </span>
                  <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white">
                    {{ item.age_rating }}+
                  </span>
                </div>
              </div>
              <div class="p-4">
                <h4 class="line-clamp-2 text-base font-semibold leading-6 text-slate-950">
                  {{ item.title }}
                </h4>
                <p class="mt-3 text-sm text-slate-500">{{ formatDate(item.event_date) }}</p>
                <p class="mt-1 text-sm font-semibold text-blue-700">{{ formatPrice(item.price) }}</p>
                <p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-500">{{ recommendationLocation(item) }}</p>
              </div>
            </RouterLink>
          </div>
        </section>

        <section class="app-panel p-6 sm:p-8">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <span class="info-chip">Недавно просмотренные</span>
              <h3 class="mt-4 text-3xl font-semibold tracking-[-0.04em] text-slate-950">
                Можно вернуться к выбору
              </h3>
            </div>
            <RouterLink to="/events" class="secondary-button">
              Вся афиша
            </RouterLink>
          </div>

          <div v-if="recentlyViewedEvents.length > 0" class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
              v-for="eventItem in recentlyViewedEvents"
              :key="eventItem.id"
              class="group overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm shadow-slate-900/5"
            >
              <RouterLink :to="`/events/${eventItem.id}`" class="block">
                <div class="relative h-40 overflow-hidden bg-gradient-to-br from-slate-950 via-slate-900 to-blue-900">
                  <img
                    v-if="eventItem.poster_url"
                    :src="eventItem.poster_url"
                    :alt="eventItem.title"
                    class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]"
                    loading="lazy"
                  />
                  <div class="absolute inset-x-0 top-0 flex justify-between p-4">
                    <span class="rounded-full bg-slate-950/60 px-3 py-1 text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-white">
                      {{ eventItem.category_name || 'Событие' }}
                    </span>
                    <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white">
                      {{ eventItem.age_rating_label || '0+' }}
                    </span>
                  </div>
                </div>
              </RouterLink>

              <div class="p-4">
                <h4 class="line-clamp-2 text-xl font-semibold leading-tight text-slate-950">
                  {{ eventItem.title }}
                </h4>
                <div class="mt-4 rounded-[1.2rem] border border-slate-200 bg-slate-50 px-4 py-3">
                  <p class="text-sm font-semibold text-slate-950">
                    {{ recentlyViewedDateLabel(eventItem) }}
                  </p>
                  <p class="mt-1 text-sm font-semibold text-blue-700">
                    {{ recentlyViewedPriceLabel(eventItem) }}
                  </p>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                  <RouterLink :to="`/events/${eventItem.id}`" class="primary-button px-4 py-2.5 text-sm">
                    Открыть
                  </RouterLink>
                  <button
                    type="button"
                    class="secondary-button px-4 py-2.5 text-sm"
                    @click="shareRecentlyViewed(eventItem)"
                  >
                    Поделиться
                  </button>
                </div>
              </div>
            </article>
          </div>

          <div v-else class="mt-6 rounded-[1.6rem] border border-dashed border-slate-200 bg-slate-50/75 p-6 text-sm leading-6 text-slate-500">
            Когда ты откроешь карточку события, она появится здесь. Удобно, если хочется сравнить несколько вариантов и вернуться к ним позже.
          </div>
        </section>

        <section class="app-panel p-6 sm:p-8">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <span class="info-chip">Мои билеты</span>
              <h3 class="mt-4 text-3xl font-semibold tracking-[-0.04em] text-slate-950">Купленные билеты</h3>
            </div>
            <span class="status-badge border-emerald-200 bg-emerald-50 text-emerald-700">
              {{ ticketBookings.length }} оплачено
            </span>
          </div>

          <div v-if="bookingsLoading" class="mt-6 grid gap-4 lg:grid-cols-2">
            <article v-for="item in 2" :key="item" class="rounded-[1.75rem] border border-slate-200 bg-white p-6">
              <div class="h-5 w-28 animate-pulse rounded-full bg-slate-100"></div>
              <div class="mt-4 h-8 w-2/3 animate-pulse rounded-full bg-slate-200"></div>
            </article>
          </div>

          <div v-else-if="ticketBookings.length > 0" class="mt-6 grid gap-4 lg:grid-cols-2">
            <article v-for="booking in ticketBookings" :key="booking.id" class="rounded-[1.7rem] border border-emerald-200 bg-emerald-50/35 p-5">
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Билет #{{ booking.id }}</p>
                  <h4 class="mt-3 text-2xl font-semibold leading-tight text-slate-950">{{ booking.session?.event_title || 'Событие загружается' }}</h4>
                </div>
                <span class="status-badge border-emerald-200 bg-white text-emerald-700">Оплачено</span>
              </div>

              <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <div class="rounded-[1.25rem] border border-emerald-100 bg-white p-4">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Дата</p>
                  <p class="mt-2 text-sm font-semibold text-slate-950">{{ formatDateTime(booking.session?.start_time) }}</p>
                  <p class="mt-2 text-sm text-slate-500">{{ booking.session?.hall_address || booking.session?.hall_name || 'Площадка уточняется' }}</p>
                </div>
                <div class="rounded-[1.25rem] border border-emerald-100 bg-white p-4">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Место</p>
                  <p class="mt-2 text-sm font-semibold text-slate-950">{{ bookingItemsLabel(booking) }}</p>
                  <p class="mt-2 text-sm text-slate-500">Код: {{ booking.ticket?.code || 'Формируется' }}</p>
                </div>
              </div>

              <div class="mt-5 flex flex-wrap gap-3">
                <RouterLink v-if="booking.session?.event_id" :to="`/events/${booking.session.event_id}`" class="secondary-button">
                  Перейти к событию
                </RouterLink>
                <button type="button" class="secondary-button" @click="addBookingToCalendar(booking)">
                  В календарь
                </button>
                <button type="button" class="primary-button" :disabled="isTicketDownloading(booking.id)" @click="downloadTicket(booking.id)">
                  {{ isTicketDownloading(booking.id) ? 'Готовим PDF...' : 'Скачать PDF' }}
                </button>
              </div>
            </article>
          </div>

          <div v-else class="mt-6 rounded-[1.6rem] border border-dashed border-slate-200 bg-slate-50/75 p-6">
            <h4 class="text-xl font-semibold text-slate-950">Билетов пока нет</h4>
            <p class="mt-2 text-sm leading-6 text-slate-500">После покупки билет с QR-кодом появится здесь.</p>
            <RouterLink to="/events" class="secondary-button mt-4">Перейти в каталог</RouterLink>
          </div>
        </section>

        <section class="app-panel p-6 sm:p-8">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <span class="info-chip">Мои брони</span>
              <h3 class="mt-4 text-3xl font-semibold tracking-[-0.04em] text-slate-950">Активные бронирования</h3>
            </div>
            <span class="status-badge border-blue-100 bg-blue-50 text-blue-700">
              {{ pendingBookings.length }} активных
            </span>
          </div>

          <div v-if="bookingsLoading" class="mt-6 grid gap-4 lg:grid-cols-2">
            <article v-for="item in 2" :key="item" class="rounded-[1.75rem] border border-slate-200 bg-white p-6">
              <div class="h-5 w-28 animate-pulse rounded-full bg-slate-100"></div>
              <div class="mt-4 h-8 w-2/3 animate-pulse rounded-full bg-slate-200"></div>
            </article>
          </div>

          <div v-else-if="pendingBookings.length > 0" class="mt-6 grid gap-4 lg:grid-cols-2">
            <article v-for="booking in pendingBookings" :key="booking.id" class="rounded-[1.7rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-900/5">
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-700">Бронь #{{ booking.id }}</p>
                  <h4 class="mt-3 text-2xl font-semibold leading-tight text-slate-950">{{ booking.session?.event_title || 'Событие загружается' }}</h4>
                </div>
                <span class="status-badge" :class="bookingStatusClasses(booking)">{{ bookingStatusLabel(booking) }}</span>
              </div>

              <div class="mt-5 rounded-[1.25rem] border border-slate-200 bg-slate-50/80 p-4">
                <p class="text-sm font-semibold text-slate-950">{{ formatDateTime(booking.session?.start_time) }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ booking.session?.hall_address || booking.session?.hall_name || 'Площадка уточняется' }}</p>
                <p class="mt-2 text-sm text-slate-500">Места: {{ bookingItemsLabel(booking) }}</p>
                <p class="mt-2 text-sm text-slate-500">
                  {{ booking.reserved_until ? `Резерв до ${formatDateTime(booking.reserved_until)}` : 'Срок резерва уточняется' }}
                </p>
              </div>

              <div class="mt-5 flex flex-wrap gap-3">
                <button v-if="booking.can_pay" type="button" class="primary-button" :disabled="isBookingActionLoading(booking.id)" @click="payBooking(booking.id)">
                  {{ isBookingActionLoading(booking.id) ? 'Переходим...' : paymentActionLabel(booking) }}
                </button>
                <button v-if="booking.can_cancel" type="button" class="secondary-button" :disabled="isBookingActionLoading(booking.id)" @click="cancelBooking(booking.id)">
                  Отменить бронь
                </button>
                <button type="button" class="secondary-button" @click="addBookingToCalendar(booking)">
                  В календарь
                </button>
                <RouterLink v-if="booking.session?.event_id" :to="`/events/${booking.session.event_id}`" class="secondary-button">
                  К событию
                </RouterLink>
              </div>
            </article>
          </div>

          <div v-else class="mt-6 rounded-[1.6rem] border border-dashed border-slate-200 bg-slate-50/75 p-6 text-sm leading-6 text-slate-500">
            Активных броней пока нет. Выбери место на схеме зала, и бронь появится здесь.
          </div>
        </section>

        <section class="app-panel p-6 sm:p-8">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <span class="info-chip">Хочу сходить</span>
              <h3 class="mt-4 text-3xl font-semibold tracking-[-0.04em] text-slate-950">Мини-афиша желаний</h3>
            </div>
            <span class="status-badge border-slate-200 bg-slate-50 text-slate-600">
              {{ wantToGoEvents.length }} событий
            </span>
          </div>

          <div v-if="wantToGoLoading" class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="item in 3" :key="item" class="overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white">
              <div class="h-40 animate-pulse bg-slate-200"></div>
              <div class="space-y-3 p-4">
                <div class="h-6 w-3/4 animate-pulse rounded-full bg-slate-200"></div>
              </div>
            </article>
          </div>

          <div v-else-if="wantToGoEvents.length > 0" class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="eventItem in wantToGoEvents" :key="eventItem.id" class="group overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm shadow-slate-900/5">
              <div class="relative h-44 overflow-hidden bg-gradient-to-br from-slate-950 via-slate-900 to-blue-900">
                <img v-if="eventItem.poster_url" :src="eventItem.poster_url" :alt="eventItem.title" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]" />
                <div class="absolute inset-x-0 top-0 flex justify-between p-4">
                  <span class="rounded-full bg-slate-950/60 px-3 py-1 text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-white">{{ eventItem.category?.name || 'Событие' }}</span>
                  <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white">{{ eventItem.age_rating?.label || '0+' }}</span>
                </div>
              </div>
              <div class="p-4">
                <h4 class="line-clamp-2 text-xl font-semibold leading-tight text-slate-950">{{ eventItem.title }}</h4>
                <p class="mt-3 text-sm text-slate-500">
                  {{ eventItem.next_session ? formatDateTime(eventItem.next_session.start_time) : (eventItem.is_teaser ? 'Продажи скоро' : 'Дата уточняется') }}
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                  <RouterLink :to="`/events/${eventItem.id}`" class="primary-button px-4 py-2.5 text-sm">Открыть</RouterLink>
                  <button type="button" class="secondary-button px-4 py-2.5 text-sm" :disabled="isWantToGoActionLoading(eventItem.id)" @click="removeWantToGo(eventItem.id)">
                    {{ isWantToGoActionLoading(eventItem.id) ? 'Убираем...' : 'Убрать' }}
                  </button>
                </div>
              </div>
            </article>
          </div>

          <div v-else class="mt-6 rounded-[1.6rem] border border-dashed border-slate-200 bg-slate-50/75 p-6 text-sm leading-6 text-slate-500">
            На карточках событий можно нажать «Хочу сходить», и они появятся здесь.
          </div>
        </section>
      </template>

      <section class="app-panel p-6 sm:p-8">
        <div class="flex flex-col gap-3 border-b border-slate-200/70 pb-6 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <span class="info-chip">Профиль</span>
            <h3 class="mt-4 text-3xl font-semibold tracking-[-0.04em] text-slate-950">
              Редактирование данных
            </h3>
            <p class="mt-2 text-sm leading-6 text-slate-500">
              Эти данные используются для билетов, связи и корректной работы аккаунта.
            </p>
          </div>
          <span class="text-sm" :class="hasChanges ? 'text-amber-700' : 'text-slate-500'">
            {{ hasChanges ? 'Есть несохраненные изменения' : 'Данные актуальны' }}
          </span>
        </div>

        <div v-if="successMessage" class="message-success mt-6">
          {{ successMessage }}
        </div>

        <div v-if="authStore.error" class="message-error mt-6">
          {{ authStore.error }}
        </div>

        <form class="mt-6 grid gap-5 sm:grid-cols-2" @submit.prevent="updateProfile">
          <div class="sm:col-span-2">
            <label class="field-label" for="profile-full-name">
              {{ isBusinessUser ? 'Контактное лицо' : 'ФИО' }}
            </label>
            <input
              id="profile-full-name"
              v-model="form.full_name"
              type="text"
              autocomplete="name"
              class="field-input"
              :placeholder="isBusinessUser ? 'Мария Петрова' : 'Иванов Иван Иванович'"
            />
          </div>

          <div v-if="isBusinessUser" class="sm:col-span-2">
            <label class="field-label" for="profile-company-name">Название компании</label>
            <input
              id="profile-company-name"
              v-model="form.company_name"
              type="text"
              class="field-input"
              placeholder="Milo Concert Hall"
            />
          </div>

          <div>
            <label class="field-label" for="profile-phone">Телефон</label>
            <input
              id="profile-phone"
              v-model="form.phone"
              type="tel"
              autocomplete="tel"
              class="field-input"
              placeholder="+7 (999) 123-45-67"
              @input="handlePhoneInput"
            />
          </div>

          <div v-if="!isBusinessUser">
            <label class="field-label" for="profile-birth-date">Дата рождения</label>
            <input
              id="profile-birth-date"
              v-model="form.birth_date"
              type="date"
              class="field-input"
            />
          </div>

          <div class="sm:col-span-2 flex justify-end pt-2">
            <button
              :disabled="authStore.loading || !hasChanges"
              type="submit"
              class="primary-button w-full sm:w-auto sm:min-w-64"
            >
              {{ authStore.loading ? 'Сохраняем...' : 'Сохранить изменения' }}
            </button>
          </div>
        </form>
      </section>
    </div>
  </div>
</template>
