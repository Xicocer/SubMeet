<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import {
  createBookingRequest,
  createPurchaseRequest,
  getSessionAvailabilityRequest,
} from '@/api/booking'
import BookingHallCanvas from '@/components/BookingHallCanvas.vue'
import { useAuthStore } from '@/stores/auth'
import type {
  BookingHallLayout,
  BookingLayoutElement,
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

const availability = ref<SessionAvailabilityResponse | null>(null)
const loading = ref(false)
const error = ref('')
const bookingError = ref('')
const bookingSuccess = ref('')
const reserveLoading = ref(false)
const purchaseLoading = ref(false)
const selectedElementId = ref<string | null>(null)
const latestBooking = ref<UserBooking | null>(null)
const requestSequence = ref(0)

const AUTO_REFRESH_INTERVAL_MS = 5000
let autoRefreshTimer: number | null = null

const layout = computed<BookingHallLayout | null>(() => availability.value?.layout ?? null)

const selectedElement = computed<BookingLayoutElement | null>(() => {
  if (!layout.value || !selectedElementId.value) {
    return null
  }

  return layout.value.elements.find((element) => element.id === selectedElementId.value) ?? null
})

const selectedPrice = computed(() => {
  if (!selectedElement.value) {
    return null
  }

  return selectedElement.value.price ?? availability.value?.session.base_price ?? null
})

const isElementAvailable = (element: BookingLayoutElement | null) => {
  if (!element) {
    return false
  }

  if (element.type === 'dancefloor') {
    return Number(element.capacity_available ?? 0) > 0
  }

  return (element.booking_state ?? 'free') === 'free'
}

const isSelectedElementAvailable = computed(() => isElementAvailable(selectedElement.value))
const actionLoading = computed(() => reserveLoading.value || purchaseLoading.value)

const canCreateBooking = computed(() => {
  return authStore.isAuthenticated && selectedElement.value !== null && isSelectedElementAvailable.value && !actionLoading.value
})

const canPurchaseBooking = computed(() => canCreateBooking.value)

const selectionLabel = computed(() => {
  if (!selectedElement.value) {
    return 'Выберите место на схеме'
  }

  if (selectedElement.value.type === 'dancefloor') {
    return selectedElement.value.label || 'Танцпол'
  }

  return selectedElement.value.label || 'Выбранное место'
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
  if (!previousSelectionId) {
    return
  }

  const nextElement =
    nextAvailability.layout.elements.find((element) => element.id === previousSelectionId) ?? null

  selectedElementId.value = isElementAvailable(nextElement) ? previousSelectionId : null
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
  if (!props.session?.id || !selectedElement.value) {
    return null
  }

  return selectedElement.value.type === 'dancefloor'
    ? {
        session_id: props.session.id,
        standing: [
          {
            element_id: selectedElement.value.id,
            quantity: 1,
          },
        ],
      }
    : {
        session_id: props.session.id,
        seat_ids: [selectedElement.value.id],
      }
}

const redirectToCheckout = (booking: UserBooking) => {
  const confirmationUrl = booking.payment?.confirmation_url

  if (!confirmationUrl) {
    bookingError.value = 'Платежная ссылка пока не готова. Попробуйте еще раз чуть позже.'
    return
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
    const response = await createPurchaseRequest(payload)

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
      latestBooking.value = null
      return
    }

    void loadAvailability()
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
                @select="selectedElementId = $event"
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

                <template v-if="selectedElement">
                  <p class="mt-3 text-xl font-semibold text-slate-950">
                    {{ selectionLabel }}
                  </p>
                  <p class="mt-2 text-sm text-slate-500">
                    {{ selectedElement.type === 'dancefloor' ? 'Один билет на танцпол' : 'Одно конкретное место на схеме' }}
                  </p>
                  <p v-if="selectedElement.type === 'dancefloor'" class="mt-2 text-sm text-slate-500">
                    Осталось {{ selectedElement.capacity_available ?? 0 }} из {{ selectedElement.capacity_total ?? 0 }}
                  </p>
                  <p class="mt-4 text-3xl font-semibold text-slate-950">
                    {{ selectedPrice !== null ? formatPrice(selectedPrice) : 'Цена уточняется' }}
                  </p>
                </template>

                <template v-else>
                  <p class="mt-3 text-lg font-semibold text-slate-950">Место пока не выбрано</p>
                  <p class="mt-2 text-sm leading-6 text-slate-500">
                    Нажмите на синее место на схеме, и рядом сразу появятся цена и действия для бронирования или покупки.
                  </p>
                </template>
              </article>

              <div v-if="bookingSuccess" class="message-success">
                {{ bookingSuccess }}
              </div>

              <div v-if="bookingError" class="message-error">
                {{ bookingError }}
              </div>

              <div v-if="!authStore.isAuthenticated" class="message-error">
                Чтобы оформить бронь или перейти к оплате, сначала войдите в аккаунт.
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
                      : selectedPrice !== null
                        ? `Купить сейчас за ${formatPrice(selectedPrice)}`
                        : 'Купить сейчас'
                  }}
                </button>

                <button
                  type="button"
                  class="secondary-button w-full"
                  :disabled="!canCreateBooking"
                  @click="submitBooking"
                >
                  {{
                    reserveLoading
                      ? 'Создаем бронь...'
                      : selectedPrice !== null
                        ? `Забронировать за ${formatPrice(selectedPrice)}`
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
