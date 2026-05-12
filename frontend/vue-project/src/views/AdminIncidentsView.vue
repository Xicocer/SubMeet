<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getAdminIncidentsRequest } from '@/api/admin'
import type { AdminIncidentStatusFilter, AdminProblemPayment } from '@/types/admin'
import { formatDateTime, formatPrice } from '@/utils/format'

const route = useRoute()
const router = useRouter()

const incidents = ref<AdminProblemPayment[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const loading = ref(false)
const error = ref('')
const statusFilter = ref<AdminIncidentStatusFilter>('all')
const searchInput = ref('')

const filterOptions: Array<{ value: AdminIncidentStatusFilter; label: string }> = [
  { value: 'all', label: 'Все' },
  { value: 'failed', label: 'Failed' },
  { value: 'cancelled', label: 'Cancelled' },
]

const totalLabel = computed(() => new Intl.NumberFormat('ru-RU').format(total.value))
const hasFilters = computed(() => statusFilter.value !== 'all' || searchInput.value.trim().length > 0)

const statusClasses = (status: string) => {
  if (status === 'failed') {
    return 'border-rose-200 bg-rose-50 text-rose-700'
  }

  if (status === 'cancelled') {
    return 'border-amber-200 bg-amber-50 text-amber-700'
  }

  return 'border-slate-200 bg-slate-50 text-slate-600'
}

const syncRoute = async (page: number) => {
  await router.replace({
    query: {
      status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
      search: searchInput.value.trim() || undefined,
      page: page > 1 ? String(page) : undefined,
    },
  })
}

const hydrateFromRoute = () => {
  const routeStatus = route.query.status
  const routeSearch = route.query.search

  if (typeof routeStatus === 'string' && filterOptions.some((option) => option.value === routeStatus)) {
    statusFilter.value = routeStatus as AdminIncidentStatusFilter
  }

  if (typeof routeSearch === 'string') {
    searchInput.value = routeSearch
  }
}

const loadIncidents = async (page = 1, updateRoute = true) => {
  loading.value = true
  error.value = ''

  if (updateRoute) {
    await syncRoute(page)
  }

  try {
    const response = await getAdminIncidentsRequest({
      page,
      per_page: 12,
      status: statusFilter.value,
      search: searchInput.value.trim() || undefined,
    })

    incidents.value = response.data
    currentPage.value = response.current_page
    lastPage.value = response.last_page
    total.value = response.total
  } catch (requestError: any) {
    console.error(requestError)
    error.value = requestError?.response?.data?.message || 'Не удалось загрузить список инцидентов.'
  } finally {
    loading.value = false
  }
}

const submitSearch = async () => {
  await loadIncidents(1)
}

const clearFilters = async () => {
  statusFilter.value = 'all'
  searchInput.value = ''
  await loadIncidents(1)
}

onMounted(async () => {
  hydrateFromRoute()
  const page = typeof route.query.page === 'string' ? Number(route.query.page) || 1 : 1
  await loadIncidents(page, false)
})
</script>

<template>
  <div class="space-y-6">
    <section class="app-panel relative overflow-hidden p-8 sm:p-10">
      <div class="absolute inset-x-0 top-0 h-40 bg-[radial-gradient(circle_at_top_left,rgba(37,99,235,0.18),transparent_48%),linear-gradient(120deg,rgba(15,23,42,0.06),transparent_55%)]"></div>
      <div class="relative flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
          <span class="info-chip">Payment Incidents</span>
          <h1 class="mt-4 text-3xl font-semibold leading-tight text-slate-950 sm:text-4xl">
            Operational-лента проблемных оплат
          </h1>
          <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
            Здесь собраны failed и cancelled платежи, по которым администратору нужно быстро понимать масштаб, контекст и причину.
          </p>
        </div>

        <div class="rounded-[1.5rem] border border-rose-100 bg-rose-50 px-5 py-4 text-right">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-600">Всего найдено</p>
          <p class="mt-2 text-2xl font-semibold text-rose-950">{{ totalLabel }}</p>
        </div>
      </div>
    </section>

    <section class="app-panel p-6">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-center">
        <form class="flex flex-1 flex-col gap-3 sm:flex-row" @submit.prevent="submitSearch">
          <input
            v-model="searchInput"
            type="text"
            class="w-full rounded-[1.2rem] border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
            placeholder="Поиск по booking id, external ref, event title или причине ошибки"
          />
          <button type="submit" class="primary-button justify-center">
            Найти
          </button>
        </form>

        <div class="flex flex-wrap items-center gap-3">
          <button
            v-for="option in filterOptions"
            :key="option.value"
            type="button"
            class="store-link"
            :class="statusFilter === option.value ? 'store-link-active' : ''"
            @click="statusFilter = option.value; loadIncidents(1)"
          >
            {{ option.label }}
          </button>

          <button v-if="hasFilters" type="button" class="secondary-button" @click="clearFilters">
            Сбросить
          </button>
        </div>
      </div>
    </section>

    <div v-if="error" class="message-error">
      {{ error }}
    </div>

    <section v-if="loading" class="grid gap-6 xl:grid-cols-2">
      <article
        v-for="item in 4"
        :key="item"
        class="app-panel p-6"
      >
        <div class="h-4 w-28 animate-pulse rounded-full bg-slate-100"></div>
        <div class="mt-6 h-8 w-3/4 animate-pulse rounded-2xl bg-slate-200"></div>
        <div class="mt-4 h-4 w-1/2 animate-pulse rounded-full bg-slate-100"></div>
      </article>
    </section>

    <section v-else-if="incidents.length" class="grid gap-6 xl:grid-cols-2">
      <article
        v-for="incident in incidents"
        :key="incident.id"
        class="app-panel p-7"
      >
        <div class="flex flex-col gap-5">
          <div class="flex flex-wrap items-center gap-3">
            <span class="status-badge" :class="statusClasses(incident.status)">
              {{ incident.status }}
            </span>
            <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
              payment #{{ incident.id }}
            </span>
          </div>

          <div>
            <h2 class="text-2xl font-semibold text-slate-950">
              {{ incident.snapshot?.event_title || 'Событие не определено' }}
            </h2>
            <p class="mt-2 text-sm text-slate-500">
              {{ incident.snapshot?.hall_name || 'Площадка не определена' }}
            </p>
          </div>

          <div class="grid gap-3 text-sm text-slate-500 sm:grid-cols-2">
            <p><span class="font-medium text-slate-700">Booking:</span> {{ incident.booking_id ?? '—' }}</p>
            <p><span class="font-medium text-slate-700">Provider:</span> {{ incident.provider || 'mock' }}</p>
            <p><span class="font-medium text-slate-700">Поток:</span> {{ incident.booking?.flow_type || '—' }}</p>
            <p><span class="font-medium text-slate-700">Статус брони:</span> {{ incident.booking?.status || '—' }}</p>
            <p class="sm:col-span-2 break-all">
              <span class="font-medium text-slate-700">External ref:</span> {{ incident.external_reference || incident.external_payment_id || '—' }}
            </p>
          </div>

          <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50/80 px-5 py-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
              Причина / комментарий
            </p>
            <p class="mt-3 text-sm leading-6 text-slate-600">
              {{ incident.failure_reason || 'Причина не была передана провайдером.' }}
            </p>
          </div>

          <div class="flex items-end justify-between gap-4">
            <div class="text-sm text-slate-500">
              <p>Обновлено: {{ formatDateTime(incident.updated_at || incident.created_at) }}</p>
              <p v-if="incident.booking?.confirmed_at">Confirmed: {{ formatDateTime(incident.booking.confirmed_at) }}</p>
            </div>

            <p class="text-right text-xl font-semibold text-slate-950">
              {{ formatPrice(Number(incident.amount ?? 0)) }}
            </p>
          </div>
        </div>
      </article>
    </section>

    <section v-else class="app-panel p-8">
      <div class="rounded-[1.75rem] border border-dashed border-slate-200 bg-slate-50/70 px-6 py-8 text-center">
        <p class="text-lg font-semibold text-slate-900">
          Инциденты не найдены
        </p>
        <p class="mt-3 text-sm leading-6 text-slate-500">
          Либо проблемных оплат сейчас нет, либо фильтр слишком узкий.
        </p>
        <button v-if="hasFilters" type="button" class="secondary-button mt-5" @click="clearFilters">
          Сбросить фильтры
        </button>
      </div>
    </section>

    <div class="flex items-center justify-between gap-3">
      <button
        type="button"
        class="secondary-button"
        :disabled="currentPage <= 1 || loading"
        @click="loadIncidents(currentPage - 1)"
      >
        Назад
      </button>

      <p class="text-sm text-slate-500">
        Страница {{ currentPage }} из {{ lastPage }}
      </p>

      <button
        type="button"
        class="secondary-button"
        :disabled="currentPage >= lastPage || loading"
        @click="loadIncidents(currentPage + 1)"
      >
        Дальше
      </button>
    </div>
  </div>
</template>
