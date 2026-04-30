<script setup lang="ts">
import { computed, ref } from 'vue'
import { verifyTicketRequest } from '@/api/booking'
import type { TicketVerificationResponse } from '@/types/booking'
import { formatDateTime, formatPrice } from '@/utils/format'

const ticketInput = ref('')
const loading = ref(false)
const error = ref('')
const verification = ref<TicketVerificationResponse | null>(null)

const statusTone = computed(() => {
  switch (verification.value?.status) {
    case 'validated':
      return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    case 'already_used':
      return 'border-amber-200 bg-amber-50 text-amber-700'
    case 'invalid':
      return 'border-rose-200 bg-rose-50 text-rose-700'
    case 'not_found':
      return 'border-slate-200 bg-slate-100 text-slate-600'
    default:
      return 'border-slate-200 bg-slate-100 text-slate-600'
  }
})

const statusTitle = computed(() => {
  switch (verification.value?.status) {
    case 'validated':
      return 'Билет подтвержден'
    case 'already_used':
      return 'Билет уже использован'
    case 'invalid':
      return 'Билет недействителен'
    case 'not_found':
      return 'Билет не найден'
    default:
      return 'Проверка билета'
  }
})

const itemsLabel = computed(() => {
  if (!verification.value?.booking || verification.value.booking.items.length === 0) {
    return 'Состав билета уточняется'
  }

  return verification.value.booking.items
    .map((item) => (item.quantity > 1 ? `${item.label} ×${item.quantity}` : item.label))
    .join(', ')
})

const submitVerification = async () => {
  if (!ticketInput.value.trim()) {
    error.value = 'Вставь код билета или payload из QR.'
    verification.value = null
    return
  }

  loading.value = true
  error.value = ''

  try {
    verification.value = await verifyTicketRequest(ticketInput.value.trim())
  } catch (requestError) {
    console.error(requestError)

    const responseError = requestError as {
      response?: {
        data?: {
          message?: string
          errors?: Record<string, string[]>
        }
      }
    }

    const validationErrors = responseError.response?.data?.errors
    const firstError = validationErrors ? Object.values(validationErrors)[0]?.[0] : null

    error.value = String(firstError || responseError.response?.data?.message || 'Не удалось проверить билет.')
    verification.value = null
  } finally {
    loading.value = false
  }
}

const clearVerification = () => {
  ticketInput.value = ''
  error.value = ''
  verification.value = null
}
</script>

<template>
  <section class="space-y-6">
    <div class="app-panel overflow-hidden">
      <div class="bg-gradient-to-br from-slate-950 via-slate-900 to-sky-800 px-8 py-8 text-white sm:px-10">
        <p class="text-xs font-semibold uppercase tracking-[0.26em] text-white/70">
          Ticket Verification
        </p>
        <h1 class="mt-4 text-4xl font-semibold leading-tight">
          Проверка билетов на входе
        </h1>
        <p class="mt-3 max-w-3xl text-sm leading-7 text-white/72 sm:text-base">
          Этот экран только для организатора. Вставь код билета или JSON из QR, и сервис либо подтвердит проход, либо покажет, что билет уже использован или не найден.
        </p>
      </div>

      <div class="grid gap-6 px-8 py-8 xl:grid-cols-[1.05fr_0.95fr] sm:px-10">
        <div class="space-y-5">
          <div>
            <label for="ticket-payload" class="field-label">Код билета или QR payload</label>
            <textarea
              id="ticket-payload"
              v-model="ticketInput"
              rows="8"
              class="field-input min-h-[14rem] resize-y"
              placeholder='Например: ABCD1234EFGH или {"ticket_code":"ABCD1234EFGH"}'
            ></textarea>
          </div>

          <div class="flex flex-wrap gap-3">
            <button
              type="button"
              class="primary-button"
              :disabled="loading"
              @click="submitVerification"
            >
              {{ loading ? 'Проверяем билет...' : 'Проверить билет' }}
            </button>

            <button type="button" class="secondary-button" @click="clearVerification">
              Очистить
            </button>
          </div>

          <div class="rounded-[1.7rem] border border-dashed border-slate-200 bg-slate-50/75 px-5 py-5 text-sm leading-6 text-slate-500">
            Для MVP можно вставлять код вручную. Позже сюда легко докручивается работа с камерой и реальным сканированием QR прямо на входе.
          </div>
        </div>

        <div class="space-y-4">
          <div v-if="error" class="message-error">
            {{ error }}
          </div>

          <article v-if="verification" class="app-panel p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">
                  Результат проверки
                </p>
                <h2 class="mt-3 text-3xl font-semibold text-slate-950">
                  {{ statusTitle }}
                </h2>
              </div>

              <span class="status-badge" :class="statusTone">
                {{ verification.status }}
              </span>
            </div>

            <p class="mt-4 text-sm leading-6 text-slate-600">
              {{ verification.message }}
            </p>

            <div v-if="verification.booking" class="mt-6 space-y-4">
              <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Событие</p>
                  <p class="mt-2 text-sm font-semibold text-slate-900">
                    {{ verification.booking.session?.event_title || 'Событие' }}
                  </p>
                  <p class="mt-2 text-sm text-slate-500">
                    {{ formatDateTime(verification.booking.session?.start_time) }}
                  </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Площадка</p>
                  <p class="mt-2 text-sm font-semibold text-slate-900">
                    {{ verification.booking.session?.hall_name || 'Площадка' }}
                  </p>
                  <p class="mt-2 text-sm text-slate-500">
                    {{ verification.booking.session?.hall_address || 'Адрес уточняется' }}
                  </p>
                </div>
              </div>

              <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Состав билета</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                  {{ itemsLabel }}
                </p>
              </div>

              <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Код билета</p>
                  <p class="mt-2 text-sm font-semibold text-slate-900">
                    {{ verification.booking.ticket?.code || 'Не найден' }}
                  </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Сумма</p>
                  <p class="mt-2 text-sm font-semibold text-slate-900">
                    {{ formatPrice(verification.booking.total_amount) }}
                  </p>
                </div>
              </div>

              <div
                v-if="verification.booking.ticket?.used_at"
                class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-700"
              >
                Билет уже отмечен на входе: {{ formatDateTime(verification.booking.ticket.used_at) }}
              </div>
            </div>
          </article>

          <article v-else class="rounded-[1.7rem] border border-dashed border-slate-200 bg-slate-50/75 px-5 py-6 text-sm leading-6 text-slate-500">
            После проверки здесь появится результат: действителен ли билет, для какого события он был куплен и был ли уже использован на входе.
          </article>
        </div>
      </div>
    </div>
  </section>
</template>
