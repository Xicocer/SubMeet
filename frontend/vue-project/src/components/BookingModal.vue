<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import {
  createBookingRequest,
  createGuestPurchaseRequest,
  createPurchaseRequest,
  getLoyaltyAccountRequest,
  getSessionAvailabilityRequest,
} from '@/api/booking'
import BookingHallCanvas from '@/components/BookingHallCanvas.vue'
import { useAuthStore } from '@/stores/auth'
import type {
  BookingHallLayout,
  BookingLayoutElement,
  BookingPayload,
  LoyaltyAccountResponse,
  SessionAvailabilityResponse,
  UserBooking,
} from '@/types/booking'
import type { EventSession } from '@/types/event'
import { formatDateTime, formatPrice } from '@/utils/format'

const props = defineProps<{
  open: boolean
  session: EventSession | null
}>()

const emit = defineEmits<{
  (event: 'close'): void
  (event: 'booked', booking: UserBooking): void
}>()

const authStore = useAuthStore()
const CHECKOUT_CONTEXT_KEY = 'submeet_checkout_context'

const availability = ref<SessionAvailabilityResponse | null>(null)
const loading = ref(false)
const error = ref('')
const bookingError = ref('')
const bookingSuccess = ref('')
const reserveLoading = ref(false)
const purchaseLoading = ref(false)
const selectedElementId = ref<string | null>(null)
const selectedElementIds = ref<string[]>([])
const standingQuantities = ref<Record<string, number>>({})
const latestBooking = ref<UserBooking | null>(null)
const requestSequence = ref(0)
const guestEmail = ref('')
const guestBirthDate = ref('')
const loyaltyAccount = ref<LoyaltyAccountResponse | null>(null)
const loyaltyLoading = ref(false)
const loyaltyPointsToSpend = ref(0)

const AUTO_REFRESH_INTERVAL_MS = 5000
let autoRefreshTimer: number | null = null

const layout = computed<BookingHallLayout | null>(() => availability.value?.layout ?? null)

const selectedElements = computed(() => {
  if (!layout.value) {
    return []
  }

  return selectedElementIds.value
    .map((elementId) => layout.value?.elements.find((element) => element.id === elementId) ?? null)
    .filter((element): element is BookingLayoutElement => element !== null)
})

const isQuantityElement = (element: BookingLayoutElement | null) => {
  return element?.type === 'dancefloor' || element?.type === 'table'
}

const selectedSeatElements = computed(() => selectedElements.value.filter((element) => !isQuantityElement(element)))
const selectedStandingElements = computed(() => selectedElements.value.filter((element) => isQuantityElement(element)))

const elementPrice = (element: BookingLayoutElement) => Number(element.price ?? availability.value?.session.base_price ?? 0)

const selectedTotal = computed(() => {
  return selectedElements.value.reduce((total, element) => {
    const quantity = isQuantityElement(element)
      ? Math.max(1, Number(standingQuantities.value[element.id] ?? 1))
      : 1

    return total + elementPrice(element) * quantity
  }, 0)
})

const selectedTicketsCount = computed(() => {
  return selectedElements.value.reduce((total, element) => {
    return total + (isQuantityElement(element) ? Math.max(1, Number(standingQuantities.value[element.id] ?? 1)) : 1)
  }, 0)
})

const maxLoyaltySpend = computed(() => {
  if (!loyaltyAccount.value) {
    return 0
  }

  return Math.min(
    loyaltyAccount.value.balance,
    Math.floor(selectedTotal.value * (loyaltyAccount.value.max_discount_percent / 100)),
  )
})

const normalizedLoyaltySpend = computed(() => {
  return Math.max(0, Math.min(Number(loyaltyPointsToSpend.value || 0), maxLoyaltySpend.value))
})

const payableTotal = computed(() => Math.max(0, selectedTotal.value - normalizedLoyaltySpend.value))

const loyaltyToEarn = computed(() => {
  if (!authStore.isAuthenticated || !loyaltyAccount.value) {
    return 0
  }

  return Math.floor(selectedTotal.value * (loyaltyAccount.value.earn_percent / 100))
})

const isElementAvailable = (element: BookingLayoutElement | null) => {
  if (!element) {
    return false
  }

  if (isQuantityElement(element)) {
    return Number(element.capacity_available ?? 0) > 0
  }

  return (element.booking_state ?? 'free') === 'free'
}

const actionLoading = computed(() => reserveLoading.value || purchaseLoading.value)

const canCreateBooking = computed(() => {
  return authStore.isAuthenticated && selectedElements.value.length > 0 && !actionLoading.value
})

const isGuestPurchaseReady = computed(() => {
  return guestEmail.value.trim() !== '' && guestBirthDate.value.trim() !== ''
})

const canPurchaseBooking = computed(() => {
  return selectedElements.value.length > 0 && !actionLoading.value && (authStore.isAuthenticated || isGuestPurchaseReady.value)
})

const close = () => {
  emit('close')
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

const stopAutoRefresh = () => {
  if (autoRefreshTimer !== null) {
    window.clearInterval(autoRefreshTimer)
    autoRefreshTimer = null
  }
}

const syncSelection = (nextAvailability: SessionAvailabilityResponse, previousSelectionId: string | null) => {
  selectedElementIds.value = selectedElementIds.value.filter((elementId) => {
    const nextElement = nextAvailability.layout.elements.find((element) => element.id === elementId) ?? null

    if (nextElement === null) {
      delete standingQuantities.value[elementId]
      return false
    }

    if (!isElementAvailable(nextElement)) {
      delete standingQuantities.value[elementId]
      return false
    }

    if (isQuantityElement(nextElement)) {
      const currentQuantity = Math.max(1, Number(standingQuantities.value[elementId] ?? 1))
      standingQuantities.value[elementId] = Math.min(currentQuantity, Number(nextElement.capacity_available ?? 1))
    }

    return true
  })

  selectedElementId.value = previousSelectionId && selectedElementIds.value.includes(previousSelectionId)
    ? previousSelectionId
    : (selectedElementIds.value[selectedElementIds.value.length - 1] ?? null)
}

const loadAvailability = async (options?: {
  preserveFeedback?: boolean
  preserveSelection?: boolean
  silent?: boolean
}) => {
  if (!props.session?.id || !props.open) {
    return
  }

  const requestId = ++requestSequence.value
  const previousSelectionId = options?.preserveSelection ? selectedElementId.value : null

  if (!options?.silent) {
    loading.value = true
    error.value = ''
  }

  if (!options?.preserveFeedback) {
    bookingError.value = ''
    bookingSuccess.value = ''
    latestBooking.value = null
    selectedElementId.value = null
    selectedElementIds.value = []
    standingQuantities.value = {}
  }

  try {
    const data = await getSessionAvailabilityRequest(props.session.id)

    if (requestId !== requestSequence.value) {
      return
    }

    availability.value = data
    syncSelection(data, previousSelectionId)
  } catch (requestError) {
    if (requestId !== requestSequence.value) {
      return
    }

    if (!options?.silent) {
      availability.value = null
      error.value = extractErrorMessage(requestError, 'Не удалось загрузить схему зала для бронирования.')
    }
  } finally {
    if (requestId === requestSequence.value && !options?.silent) {
      loading.value = false
    }
  }
}

const loadLoyalty = async () => {
  if (!authStore.isAuthenticated) {
    loyaltyAccount.value = null
    loyaltyPointsToSpend.value = 0
    return
  }

  loyaltyLoading.value = true

  try {
    loyaltyAccount.value = await getLoyaltyAccountRequest()
  } catch (requestError) {
    console.error(requestError)
    loyaltyAccount.value = null
  } finally {
    loyaltyLoading.value = false
  }
}

const startAutoRefresh = () => {
  stopAutoRefresh()

  if (!props.open || !props.session?.id) {
    return
  }

  autoRefreshTimer = window.setInterval(() => {
    void loadAvailability({
      preserveFeedback: true,
      preserveSelection: true,
      silent: true,
    })
  }, AUTO_REFRESH_INTERVAL_MS)
}

const buildBookingPayload = () => {
  if (!props.session?.id || selectedElements.value.length === 0) {
    return null
  }

  const payload: BookingPayload = {
    session_id: props.session.id,
  }

  const seatIds = selectedSeatElements.value.map((element) => element.id)
  const standing = selectedStandingElements.value.map((element) => ({
    element_id: element.id,
    quantity: Math.max(1, Number(standingQuantities.value[element.id] ?? 1)),
  }))

  if (seatIds.length > 0) {
    payload.seat_ids = seatIds
  }

  if (standing.length > 0) {
    payload.standing = standing
  }

  if (authStore.isAuthenticated && normalizedLoyaltySpend.value > 0) {
    payload.loyalty_points_to_spend = normalizedLoyaltySpend.value
  }

  if (!authStore.isAuthenticated) {
    payload.customer_email = guestEmail.value.trim()
    payload.guest_birth_date = guestBirthDate.value
  }

  return payload
}

const selectElement = (elementId: string | null) => {
  if (!elementId || !layout.value) {
    selectedElementId.value = null
    selectedElementIds.value = []
    standingQuantities.value = {}
    loyaltyPointsToSpend.value = 0
    return
  }

  const element = layout.value.elements.find((candidate) => candidate.id === elementId) ?? null

  if (!isElementAvailable(element)) {
    return
  }

  selectedElementId.value = elementId

  if (selectedElementIds.value.includes(elementId)) {
    selectedElementIds.value = selectedElementIds.value.filter((id) => id !== elementId)
    delete standingQuantities.value[elementId]
  } else {
    selectedElementIds.value = [...selectedElementIds.value, elementId]

    if (isQuantityElement(element)) {
      standingQuantities.value[elementId] = 1
    }
  }

  loyaltyPointsToSpend.value = Math.min(loyaltyPointsToSpend.value, maxLoyaltySpend.value)
}

const setStandingQuantity = (element: BookingLayoutElement, value: number | string) => {
  const available = Math.max(1, Number(element.capacity_available ?? 1))
  const quantity = Math.max(1, Math.min(Number(value || 1), available))

  standingQuantities.value[element.id] = quantity
  loyaltyPointsToSpend.value = Math.min(loyaltyPointsToSpend.value, maxLoyaltySpend.value)
}

const setStandingQuantityFromEvent = (element: BookingLayoutElement, event: Event) => {
  setStandingQuantity(element, (event.target as HTMLInputElement | null)?.value ?? 1)
}

const redirectToCheckout = (booking: UserBooking) => {
  const confirmationUrl = booking.payment?.confirmation_url

  if (!confirmationUrl) {
    bookingError.value = 'Платежная ссылка пока не готова. Попробуйте еще раз чуть позже.'
    return
  }

  try {
    sessionStorage.setItem(
      CHECKOUT_CONTEXT_KEY,
      JSON.stringify({
        bookingId: booking.id,
        userId: booking.user_id,
        guestToken: booking.guest_access_token ?? null,
        createdAt: new Date().toISOString(),
      }),
    )
  } catch (storageError) {
    console.warn('Unable to persist checkout context.', storageError)
  }

  window.location.href = confirmationUrl
}

const submitBooking = async () => {
  const payload = buildBookingPayload()

  if (!payload) {
    return
  }

  reserveLoading.value = true
  bookingError.value = ''
  bookingSuccess.value = ''

  try {
    const response = await createBookingRequest(payload)

    latestBooking.value = response.booking
    bookingSuccess.value =
      response.booking.reserved_until
        ? `Бронь создана. Место удерживается за вами до ${formatDateTime(response.booking.reserved_until)}.`
        : 'Бронь успешно создана.'

    emit('booked', response.booking)

    selectedElementId.value = null
    selectedElementIds.value = []
    standingQuantities.value = {}
    await loadAvailability({ preserveFeedback: true, preserveSelection: false })
  } catch (requestError) {
    bookingError.value = extractErrorMessage(requestError, 'Не удалось создать бронь.')
  } finally {
    reserveLoading.value = false
  }
}

const submitPurchase = async () => {
  const payload = buildBookingPayload()

  if (!payload) {
    return
  }

  purchaseLoading.value = true
  bookingError.value = ''
  bookingSuccess.value = ''

  try {
    const response = authStore.isAuthenticated
      ? await createPurchaseRequest(payload)
      : await createGuestPurchaseRequest(payload)

    latestBooking.value = response.booking
    bookingSuccess.value = 'Переадресуем на защищенную страницу оплаты...'
    emit('booked', response.booking)
    redirectToCheckout(response.booking)
  } catch (requestError) {
    bookingError.value = extractErrorMessage(requestError, 'Не удалось перейти к оплате.')
  } finally {
    purchaseLoading.value = false
  }
}

const onWindowKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape' && props.open) {
    close()
  }
}

const refreshLiveAvailability = () => {
  if (!props.open || !props.session?.id) {
    return
  }

  void loadAvailability({
    preserveFeedback: true,
    preserveSelection: true,
    silent: true,
  })
}

const onWindowFocus = () => {
  refreshLiveAvailability()
}

const onVisibilityChange = () => {
  if (document.visibilityState === 'visible') {
    refreshLiveAvailability()
  }
}

watch(
  () => [props.open, props.session?.id] as const,
  ([isOpen, sessionId]) => {
    if (!isOpen || !sessionId) {
      stopAutoRefresh()
      availability.value = null
      loading.value = false
      error.value = ''
      bookingError.value = ''
      bookingSuccess.value = ''
      selectedElementId.value = null
      selectedElementIds.value = []
      standingQuantities.value = {}
      latestBooking.value = null
      guestEmail.value = ''
      guestBirthDate.value = ''
      loyaltyPointsToSpend.value = 0
      return
    }

    void loadAvailability()
    void loadLoyalty()
    startAutoRefresh()
  },
  { immediate: true },
)

watch(
  () => props.open,
  (isOpen) => {
    document.body.classList.toggle('overflow-hidden', isOpen)
  },
  { immediate: true },
)

window.addEventListener('keydown', onWindowKeydown)
window.addEventListener('focus', onWindowFocus)
document.addEventListener('visibilitychange', onVisibilityChange)

onBeforeUnmount(() => {
  stopAutoRefresh()
  window.removeEventListener('keydown', onWindowKeydown)
  window.removeEventListener('focus', onWindowFocus)
  document.removeEventListener('visibilitychange', onVisibilityChange)
  document.body.classList.remove('overflow-hidden')
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/58 p-4 sm:p-6"
      @click.self="close"
    >
      <section class="app-panel flex max-h-[92vh] w-full max-w-[1480px] flex-col overflow-hidden">
        <header class="border-b border-slate-200/80 px-6 py-5 sm:px-8">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                Бронирование
              </p>
              <h2 class="mt-2 text-3xl font-semibold text-slate-950">
                {{ availability?.session.event_title || 'Схема зала' }}
              </h2>
              <p class="mt-3 text-sm text-slate-500 sm:text-base">
                {{ availability ? `${availability.session.hall_name} · ${formatDateTime(availability.session.start_time)}` : 'Подготавливаем схему и доступные места' }}
              </p>
              <p v-if="availability?.session.hall_address" class="mt-2 text-sm text-slate-500">
                {{ availability.session.hall_address }}
              </p>
            </div>

            <button type="button" class="secondary-button px-4 py-3" @click="close">
              Закрыть
            </button>
          </div>
        </header>

        <div class="grid min-h-0 flex-1 gap-0 xl:grid-cols-[1fr_360px]">
          <div class="min-h-0 border-b border-slate-200/80 xl:border-b-0 xl:border-r">
            <div v-if="loading" class="flex h-full min-h-[620px] items-center justify-center px-6">
              <div class="text-center">
                <div class="mx-auto h-14 w-14 animate-spin rounded-full border-4 border-slate-200 border-t-slate-900"></div>
                <p class="mt-4 text-sm text-slate-500">Загружаем схему зала и актуальные места...</p>
              </div>
            </div>

            <div v-else-if="error" class="p-6 sm:p-8">
              <div class="message-error">
                {{ error }}
              </div>
            </div>

            <div v-else-if="layout && availability" class="p-4 sm:p-5">
              <div class="mb-4 flex flex-wrap gap-3 px-1">
                <span class="status-badge border-blue-200 bg-blue-50 text-blue-700">
                  Доступные
                </span>
                <span class="status-badge border-slate-300 bg-slate-100 text-slate-600">
                  Уже заняты
                </span>
                <span class="status-badge border-amber-200 bg-amber-50 text-amber-700">
                  Выбор
                </span>
              </div>

              <BookingHallCanvas
                :layout="layout"
                :selected-element-id="selectedElementId"
                :selected-element-ids="selectedElementIds"
                @select="selectElement"
              />
            </div>
          </div>

          <aside class="min-h-0 overflow-y-auto px-6 py-6 sm:px-8">
            <div class="space-y-4">
              <article class="soft-card">
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Сеанс</p>
                    <p class="mt-3 text-lg font-semibold text-slate-950">
                      {{ availability ? formatDateTime(availability.session.start_time) : 'Скоро загрузится' }}
                    </p>
                    <p class="mt-2 text-sm text-slate-500">
                      {{ availability?.session.hall_name || 'Зал уточняется' }}
                    </p>
                    <p v-if="availability?.session.hall_address" class="mt-2 text-sm text-slate-500">
                      {{ availability.session.hall_address }}
                    </p>
                  </div>

                  <button type="button" class="secondary-button px-4 py-2 text-sm" @click="refreshLiveAvailability">
                    Обновить
                  </button>
                </div>
              </article>

              <article v-if="availability" class="soft-card">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Доступность</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                  <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Свободные места</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ availability.summary.seats_free }}</p>
                  </div>
                  <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Танцпол</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ availability.summary.standing_available }}</p>
                    <p class="mt-2 text-xs text-slate-500">
                      из {{ availability.summary.standing_total }} доступных билетов
                    </p>
                  </div>
                </div>
              </article>

              <article class="soft-card">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Выбор</p>

                <template v-if="selectedElements.length > 0">
                  <div class="mt-4 space-y-3">
                    <div
                      v-for="element in selectedElements"
                      :key="element.id"
                      class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4"
                    >
                      <div class="flex items-start justify-between gap-3">
                        <div>
                          <p class="font-semibold text-slate-950">
                            {{ element.label || (element.type === 'dancefloor' ? 'Танцпол' : element.type === 'table' ? 'Столик' : 'Место') }}
                          </p>
                          <p class="mt-1 text-sm text-slate-500">
                            {{ element.type === 'dancefloor' ? 'Билеты без места' : element.type === 'table' ? 'Места за выбранным столиком' : 'Конкретное место на схеме' }}
                          </p>
                        </div>
                        <p class="text-sm font-semibold text-blue-700">
                          {{ formatPrice(elementPrice(element)) }}
                        </p>
                      </div>

                      <div v-if="element.type === 'dancefloor' || element.type === 'table'" class="mt-3">
                        <label class="field-label" :for="`standing-${element.id}`">
                          {{ element.type === 'table' ? 'Количество мест за столиком' : 'Количество билетов' }}, доступно {{ element.capacity_available ?? 0 }}
                        </label>
                        <input
                          :id="`standing-${element.id}`"
                          :value="standingQuantities[element.id] ?? 1"
                          type="number"
                          min="1"
                          :max="Number(element.capacity_available ?? 1)"
                          class="field-input"
                          @input="setStandingQuantityFromEvent(element, $event)"
                        />
                      </div>
                    </div>
                  </div>

                  <div class="mt-5 rounded-[1.5rem] border border-blue-100 bg-blue-50/70 px-4 py-4">
                    <p class="text-sm text-blue-900">
                      Выбрано билетов: <span class="font-semibold">{{ selectedTicketsCount }}</span>
                    </p>
                    <p class="mt-2 text-3xl font-semibold text-slate-950">
                      {{ formatPrice(payableTotal) }}
                    </p>
                    <p v-if="normalizedLoyaltySpend > 0" class="mt-2 text-sm text-blue-900">
                      Скидка баллами: {{ formatPrice(normalizedLoyaltySpend) }} из {{ formatPrice(selectedTotal) }}
                    </p>
                  </div>
                </template>

                <template v-else>
                  <p class="mt-3 text-lg font-semibold text-slate-950">Место пока не выбрано</p>
                  <p class="mt-2 text-sm leading-6 text-slate-500">
                    Нажмите на синее место на схеме, и рядом сразу появятся цена и действия для бронирования или покупки.
                  </p>
                </template>
              </article>

              <article v-if="authStore.isAuthenticated" class="soft-card">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Бонусные баллы</p>

                <div v-if="loyaltyLoading" class="mt-3 text-sm text-slate-500">
                  Загружаем баланс...
                </div>
                <template v-else>
                  <p class="mt-3 text-sm leading-6 text-slate-500">
                    Доступно: <span class="font-semibold text-slate-950">{{ loyaltyAccount?.balance ?? 0 }}</span> баллов.
                    Можно оплатить до {{ loyaltyAccount?.max_discount_percent ?? 80 }}% заказа.
                  </p>
                  <input
                    v-model.number="loyaltyPointsToSpend"
                    type="number"
                    min="0"
                    :max="maxLoyaltySpend"
                    class="field-input mt-4"
                    placeholder="Сколько баллов списать"
                  />
                  <p class="mt-2 text-xs leading-5 text-slate-500">
                    Максимум для текущего выбора: {{ maxLoyaltySpend }}. После оплаты начислим {{ loyaltyToEarn }} баллов.
                  </p>
                </template>
              </article>

              <article v-else class="soft-card">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Покупка без аккаунта</p>
                <p class="mt-3 text-sm leading-6 text-slate-500">
                  Укажи email и дату рождения. После оплаты PDF-билет отправится на почту, а страница оплаты откроется по защищенному гостевому токену.
                </p>

                <div class="mt-4 grid gap-3">
                  <input
                    v-model="guestEmail"
                    type="email"
                    class="field-input"
                    placeholder="Email для билета"
                  />
                  <input
                    v-model="guestBirthDate"
                    type="date"
                    class="field-input"
                    placeholder="Дата рождения"
                  />
                </div>
              </article>

              <div v-if="bookingSuccess" class="message-success">
                {{ bookingSuccess }}
              </div>

              <div v-if="bookingError" class="message-error">
                {{ bookingError }}
              </div>

              <div class="grid gap-3">
                <button
                  type="button"
                  class="primary-button w-full"
                  :disabled="!canPurchaseBooking"
                  @click="submitPurchase"
                >
                  {{
                    purchaseLoading
                      ? 'Переходим к оплате...'
                      : selectedElements.length > 0
                        ? `Купить сейчас за ${formatPrice(payableTotal)}`
                        : 'Купить сейчас'
                  }}
                </button>

                <button
                  v-if="authStore.isAuthenticated"
                  type="button"
                  class="secondary-button w-full"
                  :disabled="!canCreateBooking"
                  @click="submitBooking"
                >
                  {{
                    reserveLoading
                      ? 'Создаем бронь...'
                      : selectedElements.length > 0
                        ? `Забронировать за ${formatPrice(selectedTotal)}`
                        : 'Забронировать'
                  }}
                </button>
              </div>
            </div>
          </aside>
        </div>
      </section>
    </div>
  </Teleport>
</template>
