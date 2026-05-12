<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { getAdminDashboardRequest } from '@/api/admin'
import type { AdminDashboardResponse } from '@/types/admin'
import { formatDateTime, formatPrice } from '@/utils/format'

const dashboard = ref<AdminDashboardResponse | null>(null)
const loading = ref(false)
const error = ref('')

const metrics = computed(() => dashboard.value?.metrics ?? null)
const recentProblemPayments = computed(() => dashboard.value?.booking.recent_problem_payments ?? [])

const kpiCards = computed(() => {
  const value = metrics.value

  return [
    {
      label: 'Пользователи',
      value: formatInteger(value?.users_total ?? 0),
      hint: `Организаторов: ${formatInteger(value?.organizers_total ?? 0)}`,
      to: '/admin/organizers',
    },
    {
      label: 'События',
      value: formatInteger(value?.events_total ?? 0),
      hint: `На модерации: ${formatInteger(value?.events_pending_review ?? 0)}`,
      to: '/admin/events?status=pending_review',
    },
    {
      label: 'Продажи',
      value: formatInteger(value?.tickets_sold ?? 0),
      hint: `Подтверждено заказов: ${formatInteger(value?.bookings_confirmed ?? 0)}`,
      to: '/admin/incidents',
    },
    {
      label: 'Выручка',
      value: formatPrice(Number(value?.revenue_total ?? 0)),
      hint: `Проблемных кейсов: ${formatInteger(value?.problem_cases_total ?? 0)}`,
      to: '/admin/incidents?status=all',
    },
  ]
})

const quickActions = computed(() => {
  const value = metrics.value

  return [
    {
      title: 'Очередь организаторов',
      description: 'Быстрый вход в модерацию компаний, которые ждут решение администратора.',
      value: formatInteger(value?.organizers_pending ?? 0),
      to: '/admin/organizers?status=pending',
    },
    {
      title: 'События на проверке',
      description: 'Проверка карточек, которые организаторы отправили на публикацию.',
      value: formatInteger(value?.events_pending_review ?? 0),
      to: '/admin/events?status=pending_review',
    },
    {
      title: 'Инциденты по оплатам',
      description: 'Отдельная operational-лента failed и cancelled платежей.',
      value: formatInteger(value?.problem_cases_total ?? 0),
      to: '/admin/incidents?status=all',
    },
    {
      title: 'Справочники каталога',
      description: 'Категории, возрастные рейтинги и глобальные теги платформы.',
      value: formatInteger((value?.events_published ?? 0) + (value?.events_draft ?? 0)),
      to: '/admin/dictionaries',
    },
  ]
})

const moderationSnapshot = computed(() => {
  const value = metrics.value

  return [
    { label: 'Организаторы pending', value: formatInteger(value?.organizers_pending ?? 0) },
    { label: 'Организаторы blocked', value: formatInteger(value?.organizers_blocked ?? 0) },
    { label: 'События pending_review', value: formatInteger(value?.events_pending_review ?? 0) },
    { label: 'Будущие сеансы', value: formatInteger(value?.sessions_future ?? 0) },
  ]
})

const commerceSnapshot = computed(() => {
  const value = metrics.value

  return [
    { label: 'Резервы', value: formatInteger(value?.bookings_reserved ?? 0) },
    { label: 'Ожидают оплату', value: formatInteger(value?.bookings_payment_pending ?? 0) },
    { label: 'Failed', value: formatInteger(value?.payments_failed ?? 0) },
    { label: 'Cancelled', value: formatInteger(value?.payments_cancelled ?? 0) },
  ]
})

const formatInteger = (value: number) => new Intl.NumberFormat('ru-RU').format(value)

const loadDashboard = async () => {
  loading.value = true
  error.value = ''

  try {
    dashboard.value = await getAdminDashboardRequest()
  } catch (requestError: any) {
    console.error(requestError)
    error.value = requestError?.response?.data?.message || 'Не удалось загрузить dashboard администратора.'
  } finally {
    loading.value = false
  }
}

onMounted(loadDashboard)
</script>

<template>
  <div class="space-y-6">
    <section class="app-panel relative overflow-hidden p-8 sm:p-10">
      <div class="absolute inset-x-0 top-0 h-40 bg-[radial-gradient(circle_at_top_left,rgba(37,99,235,0.18),transparent_48%),linear-gradient(120deg,rgba(15,23,42,0.06),transparent_55%)]"></div>
      <div class="relative flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
          <span class="info-chip">Admin Control Plane</span>
          <h1 class="mt-4 text-3xl font-semibold leading-tight text-slate-950 sm:text-4xl">
            Платформенная сводка
          </h1>
          <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
            Центральная точка контроля для модерации организаторов, событий, платежных инцидентов и состояния витрины.
          </p>
        </div>

        <div class="flex flex-wrap gap-3">
          <RouterLink to="/admin/organizers?status=pending" class="secondary-button">
            Очередь компаний
          </RouterLink>
          <RouterLink to="/admin/events?status=pending_review" class="secondary-button">
            События на проверке
          </RouterLink>
          <RouterLink to="/admin/incidents?status=all" class="secondary-button">
            Инциденты
          </RouterLink>
          <button type="button" class="primary-button" @click="loadDashboard">
            Обновить
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
        <RouterLink
          v-for="card in kpiCards"
          :key="card.label"
          :to="card.to"
          class="app-panel p-6 transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-blue-100/60"
        >
          <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
            {{ card.label }}
          </p>
          <p class="mt-4 text-3xl font-semibold tracking-tight text-slate-950">
            {{ card.value }}
          </p>
          <p class="mt-3 text-sm text-slate-500">
            {{ card.hint }}
          </p>
        </RouterLink>
      </section>

      <section class="grid gap-6 xl:grid-cols-2">
        <article class="app-panel p-7">
          <div class="flex items-center justify-between gap-4">
            <div>
              <span class="info-chip">Быстрые действия</span>
              <h2 class="mt-4 text-2xl font-semibold text-slate-950">
                Что требует внимания сейчас
              </h2>
            </div>
          </div>

          <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <RouterLink
              v-for="item in quickActions"
              :key="item.title"
              :to="item.to"
              class="rounded-[1.5rem] border border-slate-200 bg-slate-50/80 px-5 py-5 transition hover:border-blue-200 hover:bg-blue-50/70"
            >
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                {{ item.title }}
              </p>
              <p class="mt-3 text-2xl font-semibold text-slate-950">
                {{ item.value }}
              </p>
              <p class="mt-3 text-sm leading-6 text-slate-500">
                {{ item.description }}
              </p>
            </RouterLink>
          </div>
        </article>

        <article class="app-panel p-7">
          <div class="flex items-center justify-between gap-4">
            <div>
              <span class="info-chip">Срез платформы</span>
              <h2 class="mt-4 text-2xl font-semibold text-slate-950">
                Модерация и коммерция
              </h2>
            </div>
          </div>

          <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <article
              v-for="item in moderationSnapshot"
              :key="item.label"
              class="rounded-[1.5rem] border border-slate-200 bg-white px-5 py-5 shadow-sm shadow-slate-900/5"
            >
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                {{ item.label }}
              </p>
              <p class="mt-3 text-2xl font-semibold text-slate-950">
                {{ item.value }}
              </p>
            </article>

            <article
              v-for="item in commerceSnapshot"
              :key="item.label"
              class="rounded-[1.5rem] border border-slate-200 bg-white px-5 py-5 shadow-sm shadow-slate-900/5"
            >
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                {{ item.label }}
              </p>
              <p class="mt-3 text-2xl font-semibold text-slate-950">
                {{ item.value }}
              </p>
            </article>
          </div>
        </article>
      </section>

      <section class="app-panel p-7">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <span class="info-chip">Инциденты</span>
            <h2 class="mt-4 text-2xl font-semibold text-slate-950">
              Последние проблемные платежи
            </h2>
          </div>

          <RouterLink to="/admin/incidents?status=all" class="secondary-button">
            Открыть все инциденты
          </RouterLink>
        </div>

        <div v-if="recentProblemPayments.length" class="mt-7 grid gap-4 xl:grid-cols-2">
          <article
            v-for="payment in recentProblemPayments"
            :key="payment.id"
            class="rounded-[1.5rem] border border-rose-200 bg-rose-50/70 px-5 py-5"
          >
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-rose-700">
                  {{ payment.status }}
                </p>
                <p class="mt-2 text-lg font-semibold text-rose-950">
                  {{ payment.snapshot?.event_title || `Платеж #${payment.id}` }}
                </p>
                <p class="mt-2 text-sm text-rose-800/80">
                  Booking: {{ payment.booking_id ?? '—' }} · Provider: {{ payment.provider || 'mock' }}
                </p>
                <p v-if="payment.failure_reason" class="mt-2 text-sm text-rose-800/80">
                  Причина: {{ payment.failure_reason }}
                </p>
              </div>

              <p class="text-right text-lg font-semibold text-rose-900">
                {{ formatPrice(Number(payment.amount ?? 0)) }}
              </p>
            </div>

            <p class="mt-4 text-sm text-rose-800/80">
              {{ formatDateTime(payment.updated_at || payment.created_at) }}
            </p>
          </article>
        </div>

        <div v-else class="mt-7 rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50/70 px-5 py-6 text-sm text-slate-500">
          Сейчас нет failed или cancelled платежей. Когда инциденты появятся, они будут видны здесь и на отдельной operational-странице.
        </div>
      </section>
    </template>
  </div>
</template>
