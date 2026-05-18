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
import { useAuthStore } from '@/stores/auth'
import type { UpdateProfilePayload } from '@/types/auth'
import type { LoyaltyAccountResponse, UserBooking } from '@/types/booking'
import type { WantToGoEvent } from '@/types/event'
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

const successMessage = ref('')
const cabinetMessage = ref('')
const cabinetError = ref('')
const bookingsLoading = ref(false)
const wantToGoLoading = ref(false)
const bookingActionIds = ref<number[]>([])
const ticketDownloadIds = ref<number[]>([])
const wantToGoActionIds = ref<number[]>([])
const bookings = ref<UserBooking[]>([])
const wantToGoEvents = ref<WantToGoEvent[]>([])
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

const loadCabinetData = async () => {
  cabinetError.value = ''

  await Promise.all([
    loadBookings(),
    loadWantToGo(),
    loadLoyalty(),
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
    } else if (response.booking.status === 'payment_pending') {
      cabinetMessage.value = 'Платеж еще обрабатывается. Обнови страницу чуть позже.'
    } else {
      cabinetMessage.value = 'Статус платежа обновлен.'
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
      return
    }

    window.location.href = confirmationUrl
  } catch (requestError) {
    console.error(requestError)
    cabinetError.value = extractErrorMessage(requestError, 'Не удалось перейти к оплате.')
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
  } catch (requestError) {
    console.error(requestError)
    cabinetError.value = extractErrorMessage(requestError, 'Не удалось отменить бронь.')
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
  } catch (requestError) {
    console.error(requestError)
    cabinetError.value = extractErrorMessage(requestError, 'Не удалось скачать билет.')
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
  } catch (requestError) {
    console.error(requestError)
    cabinetError.value = 'Не удалось обновить список «Хочу сходить».'
  } finally {
    wantToGoActionIds.value = wantToGoActionIds.value.filter((id) => id !== eventId)
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
    <p class="mt-6 text-sm text-slate-500">Загружаем данные кабинета...</p>
  </section>

  <div v-else class="grid gap-6 xl:grid-cols-[340px_1fr]">
    <aside class="app-panel overflow-hidden">
      <div class="bg-gradient-to-br from-slate-950 via-slate-900 to-sky-900 px-8 py-8 text-white">
        <div class="flex items-start justify-between gap-4">
          <div class="flex h-20 w-20 items-center justify-center rounded-[1.75rem] bg-white/10 text-2xl font-semibold">
            {{ initials }}
          </div>

          <div class="status-badge border-white/15 bg-white/10 text-white/85">
            {{ statusLabel }}
          </div>
        </div>

        <h2 class="mt-6 text-3xl font-semibold leading-tight">
          {{ profileTitle }}
        </h2>
        <p class="mt-2 break-words text-sm leading-6 text-white/70">
          {{ user?.email || 'Email не указан' }}
        </p>
        <p v-if="isBusinessUser" class="mt-3 text-sm leading-6 text-white/70">
          Контактное лицо: {{ user?.full_name || 'Не указано' }}
        </p>
      </div>

      <div class="space-y-4 p-6">
        <article class="soft-card">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Роль</p>
          <p class="mt-3 text-lg font-semibold text-slate-900">{{ roleLabel }}</p>
        </article>

        <article class="soft-card">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Статус</p>
          <div class="status-badge mt-3" :class="statusClasses">
            {{ statusLabel }}
          </div>
        </article>

        <article class="soft-card">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">В системе с</p>
          <p class="mt-3 text-sm leading-6 text-slate-600">{{ memberSinceLabel }}</p>
        </article>

        <article v-if="!isBusinessUser" class="soft-card">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Хочу сходить</p>
          <p class="mt-3 text-2xl font-semibold text-slate-950">{{ wantToGoEvents.length }}</p>
        </article>

        <article v-if="!isBusinessUser" class="soft-card border-blue-100 bg-blue-50/70">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-blue-700">Бонусные баллы</p>
          <p class="mt-3 text-3xl font-semibold text-slate-950">{{ loyaltyBalance }}</p>
          <p class="mt-2 text-sm leading-6 text-blue-900">
            Ими можно оплатить до {{ loyaltyAccount?.max_discount_percent ?? 80 }}% следующего заказа.
          </p>
        </article>

        <article v-if="!isBusinessUser" class="soft-card">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Активные брони</p>
          <p class="mt-3 text-2xl font-semibold text-slate-950">{{ pendingBookings.length }}</p>
        </article>

        <article v-if="!isBusinessUser" class="soft-card">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Купленные билеты</p>
          <p class="mt-3 text-2xl font-semibold text-slate-950">{{ ticketBookings.length }}</p>
        </article>

        <RouterLink
          v-if="isBusinessUser"
          :to="businessCabinetRoute"
          class="secondary-button w-full"
        >
          Открыть кабинет организатора
        </RouterLink>

        <RouterLink v-else to="/events" class="secondary-button w-full">
          Перейти в афишу
        </RouterLink>
      </div>
    </aside>

    <div class="space-y-6">
      <section class="app-panel p-8 sm:p-10">
        <div class="flex flex-col gap-6 border-b border-slate-200/70 pb-8 lg:flex-row lg:items-end lg:justify-between">
          <div class="max-w-2xl">
            <span class="info-chip">Личный кабинет</span>
            <h2 class="mt-4 text-3xl font-semibold leading-tight text-slate-950">
              Профиль пользователя
            </h2>
            <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
              Здесь можно обновить данные аккаунта, следить за бронированиями и скачивать уже оплаченные билеты.
            </p>
          </div>

          <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <article
              v-if="isBusinessUser"
              class="min-w-0 rounded-2xl border border-slate-200 bg-white/85 px-4 py-4 shadow-sm shadow-slate-900/5"
            >
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Компания</p>
              <p class="mt-2 break-words text-sm font-semibold leading-6 text-slate-900">
                {{ user?.organizer_profile?.company_name || 'Не указана' }}
              </p>
            </article>

            <article class="min-w-0 rounded-2xl border border-slate-200 bg-white/85 px-4 py-4 shadow-sm shadow-slate-900/5">
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Email</p>
              <p class="mt-2 break-all text-sm font-semibold leading-6 text-slate-900">
                {{ user?.email || 'Не указан' }}
              </p>
            </article>

            <article
              v-if="!isBusinessUser"
              class="min-w-0 rounded-2xl border border-slate-200 bg-white/85 px-4 py-4 shadow-sm shadow-slate-900/5"
            >
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Дата рождения</p>
              <p class="mt-2 text-sm font-semibold leading-6 text-slate-900">
                {{ formatDate(user?.birth_date) }}
              </p>
            </article>

            <article class="min-w-0 rounded-2xl border border-slate-200 bg-white/85 px-4 py-4 shadow-sm shadow-slate-900/5">
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Роль</p>
              <p class="mt-2 text-sm font-semibold leading-6 text-slate-900">{{ roleLabel }}</p>
            </article>
          </div>
        </div>

        <div v-if="successMessage" class="message-success mt-6">
          {{ successMessage }}
        </div>

        <div v-if="authStore.error" class="message-error mt-6">
          {{ authStore.error }}
        </div>

        <form class="mt-8 grid gap-5 sm:grid-cols-2" @submit.prevent="updateProfile">
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

          <div class="sm:col-span-2 flex flex-col gap-4 pt-2 lg:flex-row lg:items-center lg:justify-between">
            <p class="text-sm" :class="hasChanges ? 'text-amber-700' : 'text-slate-500'">
              {{
                hasChanges
                  ? 'Есть несохраненные изменения.'
                  : 'Форма синхронизирована с текущими данными профиля.'
              }}
            </p>

            <button
              :disabled="authStore.loading || !hasChanges"
              type="submit"
              class="primary-button w-full lg:w-auto lg:min-w-64"
            >
              {{ authStore.loading ? 'Сохраняем...' : 'Сохранить изменения' }}
            </button>
          </div>
        </form>
      </section>

      <template v-if="!isBusinessUser">
        <div v-if="cabinetMessage" class="message-success">
          {{ cabinetMessage }}
        </div>

        <div v-if="cabinetError" class="message-error">
          {{ cabinetError }}
        </div>

        <section class="app-panel p-8 sm:p-10">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <span class="info-chip">Хочу сходить</span>
              <h3 class="mt-4 text-3xl font-semibold text-slate-950">
                События, которые хочется не потерять
              </h3>
              <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
                Список автоматически очищается от прошедших или уже недоступных мероприятий.
              </p>
            </div>

            <div class="rounded-[1.6rem] border border-slate-200 bg-slate-50 px-5 py-4">
              <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Сейчас в списке</p>
              <p class="mt-2 text-3xl font-semibold text-slate-950">{{ wantToGoEvents.length }}</p>
            </div>
          </div>

          <div v-if="wantToGoLoading" class="mt-8 grid gap-4 lg:grid-cols-2">
            <article
              v-for="item in 2"
              :key="item"
              class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-900/5"
            >
              <div class="h-5 w-28 animate-pulse rounded-full bg-slate-100"></div>
              <div class="mt-4 h-8 w-2/3 animate-pulse rounded-full bg-slate-200"></div>
              <div class="mt-4 h-4 w-full animate-pulse rounded-full bg-slate-100"></div>
            </article>
          </div>

          <div v-else-if="wantToGoEvents.length > 0" class="mt-8 grid gap-4 lg:grid-cols-2">
            <article
              v-for="eventItem in wantToGoEvents"
              :key="eventItem.id"
              class="rounded-[1.9rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-900/5"
            >
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-700">
                    {{ eventItem.category?.name || 'Событие' }}
                  </p>
                  <h4 class="mt-3 text-2xl font-semibold leading-tight text-slate-950">
                    {{ eventItem.title }}
                  </h4>
                </div>

                <div class="flex shrink-0 flex-col items-end gap-2">
                  <span class="status-badge border-blue-100 bg-blue-50 text-blue-700">
                    {{ eventItem.age_rating?.label || '0+' }}
                  </span>
                  <span
                    class="status-badge"
                    :class="eventItem.is_teaser
                      ? 'border-amber-200 bg-amber-50 text-amber-800'
                      : 'border-emerald-200 bg-emerald-50 text-emerald-700'"
                  >
                    {{ eventItem.is_teaser ? 'Тизер' : 'Билеты открыты' }}
                  </span>
                </div>
              </div>

              <div
                v-if="eventItem.is_teaser"
                class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900"
              >
                Это тизер. Мы держим событие в списке, а когда появятся сеансы, здесь автоматически появятся дата, площадка и цена.
              </div>

              <p class="mt-4 text-sm leading-6 text-slate-500">
                {{ eventItem.description || 'Карточка события уже доступна, можно открыть расписание и перейти к бронированию.' }}
              </p>

              <div class="mt-5 flex flex-wrap gap-2">
                <span
                  v-for="tag in eventItem.tags"
                  :key="tag.id"
                  class="rounded-full border px-2.5 py-1 text-xs font-medium"
                  :class="tag.slug === 'teaser'
                    ? 'border-amber-200 bg-amber-300 text-slate-950'
                    : 'border-slate-200 bg-slate-50 text-slate-500'"
                >
                  #{{ tag.name }}
                </span>
              </div>

              <div class="mt-6 grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Ближайший сеанс</p>
                  <p class="mt-2 text-sm font-semibold text-slate-900">
                    {{ eventItem.next_session ? formatDateTime(eventItem.next_session.start_time) : (eventItem.is_teaser ? 'Ожидаем расписание' : 'Скоро появится') }}
                  </p>
                  <p class="mt-2 text-sm text-slate-500">
                    {{ eventItem.next_session?.hall?.address || eventItem.next_session?.hall?.name || (eventItem.is_teaser ? 'Площадка еще подтверждается' : 'Площадка уточняется') }}
                  </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Цена от</p>
                  <p class="mt-2 text-sm font-semibold text-slate-900">
                    {{ eventItem.minimum_price !== null ? formatPrice(eventItem.minimum_price) : (eventItem.is_teaser ? 'После открытия продаж' : 'Уточняется') }}
                  </p>
                  <p class="mt-2 text-sm text-slate-500">
                    Добавлено {{ formatDate(eventItem.wanted_at) }}
                  </p>
                </div>
              </div>

              <div class="mt-6 flex flex-wrap items-center gap-3">
                <RouterLink :to="`/events/${eventItem.id}`" class="primary-button">
                  Открыть событие
                </RouterLink>

                <button
                  type="button"
                  class="secondary-button"
                  :disabled="isWantToGoActionLoading(eventItem.id)"
                  @click="removeWantToGo(eventItem.id)"
                >
                  {{ isWantToGoActionLoading(eventItem.id) ? 'Обновляем...' : 'Убрать из списка' }}
                </button>
              </div>
            </article>
          </div>

          <div
            v-else
            class="mt-8 rounded-[1.7rem] border border-dashed border-slate-200 bg-slate-50/75 px-5 py-6 text-sm leading-6 text-slate-500"
          >
            Пока здесь пусто. На карточках событий можно нажать «Хочу сходить», и они сразу появятся в этом разделе.
          </div>
        </section>

        <section class="app-panel p-8 sm:p-10">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <span class="info-chip">Мои брони</span>
              <h3 class="mt-4 text-3xl font-semibold text-slate-950">
                Резервы и оплаты
              </h3>
              <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
                Отсюда можно перейти к оплате, вернуться в карточку события или снять резерв.
              </p>
            </div>

            <div class="rounded-[1.6rem] border border-slate-200 bg-slate-50 px-5 py-4">
              <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Всего записей</p>
              <p class="mt-2 text-3xl font-semibold text-slate-950">{{ reservationBookings.length }}</p>
            </div>
          </div>

          <div v-if="bookingsLoading" class="mt-8 grid gap-4 lg:grid-cols-2">
            <article
              v-for="item in 2"
              :key="item"
              class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-900/5"
            >
              <div class="h-5 w-28 animate-pulse rounded-full bg-slate-100"></div>
              <div class="mt-4 h-8 w-2/3 animate-pulse rounded-full bg-slate-200"></div>
              <div class="mt-4 h-4 w-full animate-pulse rounded-full bg-slate-100"></div>
            </article>
          </div>

          <div v-else-if="reservationBookings.length > 0" class="mt-8 grid gap-4 lg:grid-cols-2">
            <article
              v-for="booking in reservationBookings"
              :key="booking.id"
              class="rounded-[1.9rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-900/5"
            >
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">
                    Бронь #{{ booking.id }}
                  </p>
                  <h4 class="mt-3 text-2xl font-semibold leading-tight text-slate-950">
                    {{ booking.session?.event_title || 'Событие загружается' }}
                  </h4>
                </div>

                <span class="status-badge" :class="bookingStatusClasses(booking)">
                  {{ bookingStatusLabel(booking) }}
                </span>
              </div>

              <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Сеанс</p>
                  <p class="mt-2 text-sm font-semibold text-slate-900">
                    {{ formatDateTime(booking.session?.start_time) }}
                  </p>
                  <p class="mt-2 text-sm text-slate-500">
                    {{ booking.session?.hall_address || booking.session?.hall_name || 'Площадка уточняется' }}
                  </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Сумма</p>
                  <p class="mt-2 text-sm font-semibold text-slate-900">
                    {{ formatPrice(booking.total_amount) }}
                  </p>
                  <p class="mt-2 text-sm text-slate-500">
                    {{ booking.reserved_until ? `Резерв до ${formatDateTime(booking.reserved_until)}` : 'Статус обновится после действия' }}
                  </p>
                </div>
              </div>

              <div class="mt-5 rounded-2xl border border-slate-200 bg-white px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Состав</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                  {{ bookingItemsLabel(booking) }}
                </p>
                <p v-if="booking.payment?.failure_reason" class="mt-3 text-sm text-rose-600">
                  Причина последней неуспешной оплаты: {{ booking.payment.failure_reason }}
                </p>
              </div>

              <div class="mt-6 flex flex-wrap items-center gap-3">
                <RouterLink
                  v-if="booking.session?.event_id"
                  :to="`/events/${booking.session.event_id}`"
                  class="secondary-button"
                >
                  Открыть событие
                </RouterLink>

                <button
                  v-if="booking.can_pay"
                  type="button"
                  class="primary-button"
                  :disabled="isBookingActionLoading(booking.id)"
                  @click="payBooking(booking.id)"
                >
                  {{ isBookingActionLoading(booking.id) ? 'Переходим к оплате...' : paymentActionLabel(booking) }}
                </button>

                <button
                  v-if="booking.can_cancel"
                  type="button"
                  class="secondary-button"
                  :disabled="isBookingActionLoading(booking.id)"
                  @click="cancelBooking(booking.id)"
                >
                  Отменить бронь
                </button>
              </div>
            </article>
          </div>

          <div
            v-else
            class="mt-8 rounded-[1.7rem] border border-dashed border-slate-200 bg-slate-50/75 px-5 py-6 text-sm leading-6 text-slate-500"
          >
            Активных броней пока нет. После выбора мест на событии резерв сразу появится здесь.
          </div>
        </section>

        <section class="app-panel p-8 sm:p-10">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <span class="info-chip">Купленные билеты</span>
              <h3 class="mt-4 text-3xl font-semibold text-slate-950">
                Все подтвержденные покупки
              </h3>
              <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
                После успешной оплаты здесь появляется PDF-билет с QR-кодом для входа.
              </p>
            </div>

            <div class="rounded-[1.6rem] border border-slate-200 bg-slate-50 px-5 py-4">
              <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Оплачено</p>
              <p class="mt-2 text-3xl font-semibold text-slate-950">{{ ticketBookings.length }}</p>
            </div>
          </div>

          <div v-if="ticketBookings.length > 0" class="mt-8 grid gap-4 lg:grid-cols-2">
            <article
              v-for="booking in ticketBookings"
              :key="booking.id"
              class="rounded-[1.9rem] border border-emerald-200 bg-emerald-50/40 p-6 shadow-sm shadow-slate-900/5"
            >
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700">
                    Билет #{{ booking.id }}
                  </p>
                  <h4 class="mt-3 text-2xl font-semibold leading-tight text-slate-950">
                    {{ booking.session?.event_title || 'Событие загружается' }}
                  </h4>
                </div>

                <span class="status-badge border-emerald-200 bg-white text-emerald-700">
                  Оплачено
                </span>
              </div>

              <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-emerald-100 bg-white px-4 py-4">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Сеанс</p>
                  <p class="mt-2 text-sm font-semibold text-slate-900">
                    {{ formatDateTime(booking.session?.start_time) }}
                  </p>
                  <p class="mt-2 text-sm text-slate-500">
                    {{ booking.session?.hall_address || booking.session?.hall_name || 'Площадка уточняется' }}
                  </p>
                </div>

                <div class="rounded-2xl border border-emerald-100 bg-white px-4 py-4">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Код билета</p>
                  <p class="mt-2 text-sm font-semibold text-slate-900">
                    {{ booking.ticket?.code || 'Формируется' }}
                  </p>
                  <p class="mt-2 text-sm text-slate-500">
                    Оплачено {{ formatDateTime(booking.confirmed_at) }}
                  </p>
                </div>
              </div>

              <div class="mt-5 rounded-2xl border border-emerald-100 bg-white px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Состав билета</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                  {{ bookingItemsLabel(booking) }}
                </p>
              </div>

              <div class="mt-6 flex flex-wrap items-center gap-3">
                <RouterLink
                  v-if="booking.session?.event_id"
                  :to="`/events/${booking.session.event_id}`"
                  class="secondary-button"
                >
                  Открыть событие
                </RouterLink>

                <button
                  type="button"
                  class="primary-button"
                  :disabled="isTicketDownloading(booking.id)"
                  @click="downloadTicket(booking.id)"
                >
                  {{ isTicketDownloading(booking.id) ? 'Готовим PDF...' : 'Скачать билет PDF' }}
                </button>
              </div>
            </article>
          </div>

          <div
            v-else
            class="mt-8 rounded-[1.7rem] border border-dashed border-slate-200 bg-slate-50/75 px-5 py-6 text-sm leading-6 text-slate-500"
          >
            Оплаченных билетов пока нет. После успешной оплаты они автоматически появятся здесь.
          </div>
        </section>
      </template>
    </div>
  </div>
</template>
