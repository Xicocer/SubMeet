<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import {
  cancelBookingRequest,
  cancelGuestBookingRequest,
  downloadTicketRequest,
  downloadGuestTicketRequest,
  getGuestBookingRequest,
  getMyBookingRequest,
  refreshGuestBookingPaymentRequest,
  refreshBookingPaymentRequest,
} from '@/api/booking'
import type { UserBooking } from '@/types/booking'
import { formatDateTime, formatPrice } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const CHECKOUT_CONTEXT_KEY = 'submeet_checkout_context'

const booking = ref<UserBooking | null>(null)
const loading = ref(false)
const actionLoading = ref(false)
const ticketLoading = ref(false)
const error = ref('')
const successMessage = ref('')

const bookingId = computed(() => Number(route.params.id))
const isConfirmed = computed(() => booking.value?.status === 'confirmed')
const isCancelledLike = computed(() => booking.value?.status === 'cancelled' || booking.value?.status === 'expired')
const queryGuestToken = computed(() => {
  const token = route.query.guest_token

  return Array.isArray(token) ? token[0] ?? '' : String(token ?? '')
})

const readCheckoutContext = () => {
  try {
    const raw = sessionStorage.getItem(CHECKOUT_CONTEXT_KEY)

    if (!raw) {
      return null
    }

    const parsed = JSON.parse(raw) as {
      bookingId?: number
      userId?: number
      guestToken?: string | null
      createdAt?: string
    }

    if (parsed.bookingId !== bookingId.value) {
      return null
    }

    return parsed
  } catch {
    return null
  }
}

const clearCheckoutContext = () => {
  const context = readCheckoutContext()

  if (!context) {
    return
  }

  try {
    sessionStorage.removeItem(CHECKOUT_CONTEXT_KEY)
  } catch {
  }
}

const resolveGuestToken = () => {
  if (queryGuestToken.value) {
    return queryGuestToken.value
  }

  return readCheckoutContext()?.guestToken || ''
}

const resolveLoadErrorMessage = (requestError: unknown) => {
  const errorCandidate = requestError as {
    response?: {
      status?: number
      data?: {
        message?: string
      }
    }
  }

  const status = errorCandidate.response?.status
  const serverMessage = errorCandidate.response?.data?.message
  const checkoutContext = readCheckoutContext()
  const currentUserId = authStore.user?.id ?? null

  if (status === 404) {
    if (checkoutContext?.userId && currentUserId && checkoutContext.userId !== currentUserId) {
      return 'Страница оплаты открыта под другим аккаунтом. Покупка была начата в одном профиле, а checkout сейчас открыт уже в другом. Войдите тем пользователем, который начал покупку.'
    }

    return 'Бронь для этой страницы оплаты не найдена. Возможно, она принадлежит другому аккаунту, была отменена или уже недоступна.'
  }

  if (status === 401) {
    return 'Сессия оплаты больше не действует. Войдите в тот же аккаунт или откройте гостевую ссылку из письма/страницы оплаты заново.'
  }

  return serverMessage || 'Не удалось загрузить страницу оплаты.'
}

const lineItemsLabel = computed(() => {
  if (!booking.value || booking.value.items.length === 0) {
    return 'Состав билета уточняется'
  }

  return booking.value.items
    .map((item) => (item.quantity > 1 ? `${item.label} ×${item.quantity}` : item.label))
    .join(', ')
})

const loadBooking = async () => {
  if (Number.isNaN(bookingId.value) || bookingId.value < 1) {
    error.value = 'Некорректный идентификатор оплаты.'
    return
  }

  loading.value = true
  error.value = ''

  try {
    const guestToken = resolveGuestToken()

    booking.value = guestToken
      ? await getGuestBookingRequest(bookingId.value, guestToken)
      : await getMyBookingRequest(bookingId.value)
    clearCheckoutContext()
  } catch (requestError) {
    console.error(requestError)
    error.value = resolveLoadErrorMessage(requestError)
  } finally {
    loading.value = false
  }
}

const completePayment = async () => {
  if (!booking.value) {
    return
  }

  actionLoading.value = true
  error.value = ''

  try {
    const guestToken = resolveGuestToken()

    booking.value = guestToken
      ? (await refreshGuestBookingPaymentRequest(booking.value.id, guestToken)).booking
      : (await refreshBookingPaymentRequest(booking.value.id)).booking
    successMessage.value = booking.value.ticket
      ? (booking.value.is_guest ? 'Оплата подтверждена. PDF-билет отправлен на указанную почту и доступен для скачивания.' : 'Оплата подтверждена. Билет уже готов к скачиванию.')
      : 'Оплата подтверждена. PDF-билет генерируется в фоне и скоро станет доступен.'
  } catch (requestError) {
    console.error(requestError)
    error.value = 'Не удалось подтвердить оплату.'
  } finally {
    actionLoading.value = false
  }
}

const cancelPayment = async () => {
  if (!booking.value) {
    return
  }

  actionLoading.value = true
  error.value = ''

  try {
    const guestToken = resolveGuestToken()

    booking.value = guestToken
      ? (await cancelGuestBookingRequest(booking.value.id, guestToken)).booking
      : (await cancelBookingRequest(booking.value.id)).booking
    await router.push(guestToken ? '/events' : '/profile')
  } catch (requestError) {
    console.error(requestError)
    error.value = 'Не удалось отменить оплату.'
  } finally {
    actionLoading.value = false
  }
}

const downloadTicket = async () => {
  if (!booking.value) {
    return
  }

  ticketLoading.value = true

  try {
    const guestToken = resolveGuestToken()
    const { blob, contentDisposition } = guestToken
      ? await downloadGuestTicketRequest(booking.value.id, guestToken)
      : await downloadTicketRequest(booking.value.id)
    const objectUrl = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    const fileNameMatch = contentDisposition?.match(/filename=\"?([^\";]+)\"?/)

    link.href = objectUrl
    link.download = fileNameMatch?.[1] || `submeet-ticket-${booking.value.id}.pdf`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(objectUrl)
  } catch (requestError) {
    console.error(requestError)
    error.value = 'Не удалось скачать билет.'
  } finally {
    ticketLoading.value = false
  }
}

onMounted(loadBooking)
</script>

<template>
  <section v-if="loading" class="app-panel mx-auto max-w-5xl p-8 sm:p-10">
    <div class="h-8 w-56 animate-pulse rounded-full bg-slate-200"></div>
    <div class="mt-6 h-40 animate-pulse rounded-[2rem] bg-slate-100"></div>
  </section>

  <section v-else class="mx-auto max-w-5xl space-y-6">
    <div v-if="error" class="message-error">
      {{ error }}
    </div>

    <div v-if="successMessage" class="message-success">
      {{ successMessage }}
    </div>

    <div v-if="booking" class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
      <article class="app-panel overflow-hidden">
        <div class="bg-gradient-to-br from-slate-950 via-blue-900 to-sky-700 px-8 py-8 text-white sm:px-10">
          <p class="text-xs font-semibold uppercase tracking-[0.26em] text-white/70">
            Secure Demo Checkout
          </p>
          <h1 class="mt-4 text-4xl font-semibold leading-tight">
            {{ booking.session?.event_title || 'Оплата билета' }}
          </h1>
          <p class="mt-3 text-sm leading-6 text-white/72 sm:text-base">
            Демонстрационный платежный шаг для защиты диплома: поток оплаты отдельный, билет генерируется после подтверждения.
          </p>
        </div>

        <div class="space-y-6 px-8 py-8 sm:px-10">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="soft-card">
              <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Заказ</p>
              <p class="mt-3 text-xl font-semibold text-slate-950">#{{ booking.id }}</p>
              <p class="mt-2 text-sm text-slate-500">
                {{ booking.flow_type === 'purchase' ? 'Покупка билета' : 'Оплата резерва' }}
              </p>
            </div>

            <div class="soft-card">
              <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Статус</p>
              <p class="mt-3 text-xl font-semibold text-slate-950">
                {{
                  isConfirmed
                    ? 'Оплачено'
                    : isCancelledLike
                      ? 'Недоступно'
                      : 'Ожидает подтверждения'
                }}
              </p>
              <p class="mt-2 text-sm text-slate-500">
                {{ booking.payment?.provider === 'mock' ? 'Demo gateway' : booking.payment?.provider || 'Провайдер оплаты' }}
              </p>
            </div>
          </div>

          <div class="rounded-[1.7rem] border border-slate-200 bg-slate-50/80 p-6">
            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Сеанс</p>
                <p class="mt-2 text-sm font-semibold text-slate-950">
                  {{ formatDateTime(booking.session?.start_time) }}
                </p>
                <p class="mt-2 text-sm text-slate-500">
                  {{ booking.session?.hall_name || 'Площадка уточняется' }}
                </p>
                <p v-if="booking.session?.hall_address" class="mt-2 text-sm text-slate-500">
                  {{ booking.session.hall_address }}
                </p>
              </div>

              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Состав</p>
                <p class="mt-2 text-sm leading-6 text-slate-700">
                  {{ lineItemsLabel }}
                </p>
              </div>
            </div>
          </div>

          <div class="rounded-[1.7rem] border border-dashed border-slate-200 bg-white px-6 py-5">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Как это показать комиссии</p>
            <p class="mt-3 text-sm leading-6 text-slate-600">
              Здесь видно, что поток оплаты вынесен в отдельный checkout. После нажатия на кнопку подтверждения сервис завершает оплату, выпускает PDF-билет и возвращает его в личный кабинет пользователя.
            </p>
          </div>
        </div>
      </article>

      <aside class="app-panel p-8 sm:p-10">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Итого к оплате</p>
        <p class="mt-4 text-5xl font-semibold text-slate-950">
          {{ formatPrice(booking.total_amount) }}
        </p>
        <div
          v-if="Number(booking.discount_amount) > 0"
          class="mt-5 rounded-[1.4rem] border border-blue-100 bg-blue-50/70 px-4 py-4 text-sm leading-6 text-blue-900"
        >
          <p>Стоимость заказа: {{ formatPrice(booking.subtotal_amount) }}</p>
          <p>Списано баллами: {{ booking.loyalty_points_spent }} баллов</p>
          <p>Начислится после оплаты: {{ booking.loyalty_points_earned }} баллов</p>
        </div>
        <p class="mt-3 text-sm leading-6 text-slate-500">
          {{
            booking.payment?.status === 'paid'
              ? (booking.ticket ? (booking.is_guest ? 'Платеж уже подтвержден, билет готов и отправляется на email.' : 'Платеж уже подтвержден, билет готов.') : 'Платеж подтвержден, PDF-билет еще генерируется.')
              : 'После подтверждения оплаты билет создастся в фоновой очереди без лишней задержки для пользователя.'
          }}
        </p>

        <div class="mt-8 space-y-3">
          <button
            v-if="!isConfirmed && !isCancelledLike"
            type="button"
            class="primary-button w-full"
            :disabled="actionLoading"
            @click="completePayment"
          >
            {{ actionLoading ? 'Подтверждаем оплату...' : 'Оплатить' }}
          </button>

          <button
            v-if="!isConfirmed && !isCancelledLike"
            type="button"
            class="secondary-button w-full"
            :disabled="actionLoading"
            @click="cancelPayment"
          >
            Отменить
          </button>

          <button
            v-if="isConfirmed"
            type="button"
            class="primary-button w-full"
            :disabled="ticketLoading"
            @click="downloadTicket"
          >
            {{ ticketLoading ? 'Готовим PDF...' : 'Скачать билет PDF' }}
          </button>

          <RouterLink v-if="!booking.is_guest" to="/profile" class="secondary-button w-full text-center">
            Перейти в кабинет
          </RouterLink>

          <RouterLink v-else to="/events" class="secondary-button w-full text-center">
            Вернуться в каталог
          </RouterLink>
        </div>

        <div class="mt-8 rounded-[1.6rem] border border-slate-200 bg-slate-50 px-5 py-5">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Провайдер</p>
          <p class="mt-2 text-sm font-semibold text-slate-950">
            {{ booking.payment?.provider === 'mock' ? 'Submeet Demo Gateway' : booking.payment?.provider || 'Оплата' }}
          </p>
          <p class="mt-3 text-sm text-slate-500">
            В этом режиме реальные деньги не списываются, но вся серверная логика оплаты, подтверждения и выпуска билета остается настоящей.
          </p>
        </div>
      </aside>
    </div>
  </section>
</template>
