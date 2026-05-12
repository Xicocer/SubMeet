<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getAdminOrganizersRequest, updateAdminOrganizerModerationRequest } from '@/api/admin'
import type {
  AdminOrganizerModerationStatus,
  AdminOrganizerSummary,
} from '@/types/admin'
import { formatDateTime } from '@/utils/format'

type OrganizerDraft = {
  status: AdminOrganizerModerationStatus
  note: string
}

const route = useRoute()
const router = useRouter()

const organizers = ref<AdminOrganizerSummary[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const loading = ref(false)
const error = ref('')
const feedback = ref('')
const actionUserId = ref<number | null>(null)
const statusFilter = ref<'all' | AdminOrganizerModerationStatus>('pending')
const searchInput = ref('')
const drafts = ref<Record<number, OrganizerDraft>>({})

const filterOptions: Array<{ value: 'all' | AdminOrganizerModerationStatus; label: string }> = [
  { value: 'all', label: 'Все' },
  { value: 'pending', label: 'Pending' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'blocked', label: 'Blocked' },
]

const totalLabel = computed(() => new Intl.NumberFormat('ru-RU').format(total.value))
const hasFilters = computed(() => statusFilter.value !== 'all' || searchInput.value.trim().length > 0)

const statusLabel = (status: AdminOrganizerModerationStatus) => {
  switch (status) {
    case 'approved':
      return 'Одобрен'
    case 'rejected':
      return 'Отклонен'
    case 'blocked':
      return 'Заблокирован'
    default:
      return 'На проверке'
  }
}

const statusClasses = (status: AdminOrganizerModerationStatus) => {
  switch (status) {
    case 'approved':
      return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    case 'rejected':
      return 'border-amber-200 bg-amber-50 text-amber-700'
    case 'blocked':
      return 'border-rose-200 bg-rose-50 text-rose-700'
    default:
      return 'border-blue-200 bg-blue-50 text-blue-700'
  }
}

const syncDrafts = (items: AdminOrganizerSummary[]) => {
  const nextDrafts: Record<number, OrganizerDraft> = {}

  items.forEach((item) => {
    nextDrafts[item.user_id] = drafts.value[item.user_id] ?? {
      status: item.moderation_status,
      note: item.moderation_note ?? '',
    }
  })

  drafts.value = nextDrafts
}

const getDraft = (item: AdminOrganizerSummary): OrganizerDraft | null => {
  return drafts.value[item.user_id] ?? null
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
    statusFilter.value = routeStatus as 'all' | AdminOrganizerModerationStatus
  }

  if (typeof routeSearch === 'string') {
    searchInput.value = routeSearch
  }
}

const loadOrganizers = async (page = 1, updateRoute = true) => {
  loading.value = true
  error.value = ''

  if (updateRoute) {
    await syncRoute(page)
  }

  try {
    const response = await getAdminOrganizersRequest({
      page,
      per_page: 12,
      status: statusFilter.value === 'all' ? undefined : statusFilter.value,
      search: searchInput.value.trim() || undefined,
    })

    organizers.value = response.data
    currentPage.value = response.current_page
    lastPage.value = response.last_page
    total.value = response.total
    syncDrafts(response.data)
  } catch (requestError: any) {
    console.error(requestError)
    error.value = requestError?.response?.data?.message || 'Не удалось загрузить список организаторов.'
  } finally {
    loading.value = false
  }
}

const submitSearch = async () => {
  await loadOrganizers(1)
}

const clearFilters = async () => {
  statusFilter.value = 'all'
  searchInput.value = ''
  await loadOrganizers(1)
}

const applyModeration = async (
  item: AdminOrganizerSummary,
  statusOverride?: AdminOrganizerModerationStatus,
) => {
  const draft = getDraft(item)

  if (!draft) {
    return
  }

  actionUserId.value = item.user_id
  feedback.value = ''
  error.value = ''

  try {
    const response = await updateAdminOrganizerModerationRequest(item.user_id, {
      status: statusOverride ?? draft.status,
      note: draft.note.trim() || null,
    })

    const updatedOrganizer = response.organizer
    const index = organizers.value.findIndex((organizer) => organizer.user_id === item.user_id)

    if (index !== -1) {
      organizers.value[index] = updatedOrganizer
    }

    drafts.value[item.user_id] = {
      status: updatedOrganizer.moderation_status,
      note: updatedOrganizer.moderation_note ?? '',
    }

    feedback.value = response.message
  } catch (requestError: any) {
    console.error(requestError)
    error.value = requestError?.response?.data?.message || 'Не удалось обновить статус организатора.'
  } finally {
    actionUserId.value = null
  }
}

onMounted(async () => {
  hydrateFromRoute()
  const page = typeof route.query.page === 'string' ? Number(route.query.page) || 1 : 1
  await loadOrganizers(page, false)
})
</script>

<template>
  <div class="space-y-6">
    <section class="app-panel relative overflow-hidden p-8 sm:p-10">
      <div class="absolute inset-x-0 top-0 h-40 bg-[radial-gradient(circle_at_top_left,rgba(37,99,235,0.18),transparent_48%),linear-gradient(120deg,rgba(15,23,42,0.06),transparent_55%)]"></div>
      <div class="relative flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
          <span class="info-chip">Organizer Moderation</span>
          <h1 class="mt-4 text-3xl font-semibold leading-tight text-slate-950 sm:text-4xl">
            Компании на проверке
          </h1>
          <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
            Поиск, быстрая модерация и история решения для организаторов, которые хотят выйти на платформу.
          </p>
        </div>

        <div class="rounded-[1.5rem] border border-blue-100 bg-blue-50 px-5 py-4 text-right">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-600">Всего найдено</p>
          <p class="mt-2 text-2xl font-semibold text-blue-950">{{ totalLabel }}</p>
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
            placeholder="Поиск по компании, email, телефону или контактному лицу"
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
            @click="statusFilter = option.value; loadOrganizers(1)"
          >
            {{ option.label }}
          </button>

          <button v-if="hasFilters" type="button" class="secondary-button" @click="clearFilters">
            Сбросить
          </button>
        </div>
      </div>
    </section>

    <div v-if="feedback" class="rounded-[1.5rem] border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-700">
      {{ feedback }}
    </div>

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

    <section v-else-if="organizers.length" class="grid gap-6 xl:grid-cols-2">
      <article
        v-for="organizer in organizers"
        :key="organizer.user_id"
        class="app-panel p-7"
      >
        <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-3">
              <span class="status-badge" :class="statusClasses(organizer.moderation_status)">
                {{ statusLabel(organizer.moderation_status) }}
              </span>
              <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                user #{{ organizer.user_id }}
              </span>
            </div>

            <h2 class="mt-4 text-2xl font-semibold text-slate-950">
              {{ organizer.company_name }}
            </h2>

            <div class="mt-4 grid gap-3 text-sm text-slate-500 sm:grid-cols-2">
              <p><span class="font-medium text-slate-700">Контакт:</span> {{ organizer.user.full_name || '—' }}</p>
              <p><span class="font-medium text-slate-700">Email:</span> {{ organizer.user.email || '—' }}</p>
              <p><span class="font-medium text-slate-700">Телефон:</span> {{ organizer.user.phone || '—' }}</p>
              <p><span class="font-medium text-slate-700">Обновлено:</span> {{ formatDateTime(organizer.updated_at) }}</p>
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
              <button
                type="button"
                class="secondary-button border-emerald-200 bg-emerald-50 text-emerald-700"
                :disabled="actionUserId === organizer.user_id"
                @click="applyModeration(organizer, 'approved')"
              >
                Одобрить
              </button>
              <button
                type="button"
                class="secondary-button border-amber-200 bg-amber-50 text-amber-700"
                :disabled="actionUserId === organizer.user_id"
                @click="applyModeration(organizer, 'rejected')"
              >
                Отклонить
              </button>
              <button
                type="button"
                class="secondary-button border-rose-200 bg-rose-50 text-rose-700"
                :disabled="actionUserId === organizer.user_id"
                @click="applyModeration(organizer, 'blocked')"
              >
                Блокировать
              </button>
            </div>

            <div
              v-if="organizer.moderation_note"
              class="mt-5 rounded-[1.25rem] border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600"
            >
              {{ organizer.moderation_note }}
            </div>
          </div>

          <div v-if="getDraft(organizer)" class="w-full xl:max-w-[19rem]">
            <label class="block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
              Сложное решение
            </label>
            <select
              v-model="getDraft(organizer)!.status"
              class="mt-2 w-full rounded-[1.2rem] border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
            >
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
              <option value="blocked">Blocked</option>
            </select>

            <label class="mt-4 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
              Комментарий
            </label>
            <textarea
              v-model="getDraft(organizer)!.note"
              rows="4"
              class="mt-2 w-full rounded-[1.2rem] border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
              placeholder="Причина решения для истории модерации"
            ></textarea>

            <button
              type="button"
              class="primary-button mt-4 w-full justify-center"
              :disabled="actionUserId === organizer.user_id"
              @click="applyModeration(organizer)"
            >
              {{ actionUserId === organizer.user_id ? 'Сохраняем...' : 'Применить решение' }}
            </button>
          </div>
        </div>
      </article>
    </section>

    <section v-else class="app-panel p-8">
      <div class="rounded-[1.75rem] border border-dashed border-slate-200 bg-slate-50/70 px-6 py-8 text-center">
        <p class="text-lg font-semibold text-slate-900">
          Ничего не найдено
        </p>
        <p class="mt-3 text-sm leading-6 text-slate-500">
          Попробуй убрать часть фильтров или очистить поисковый запрос.
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
        @click="loadOrganizers(currentPage - 1)"
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
        @click="loadOrganizers(currentPage + 1)"
      >
        Дальше
      </button>
    </div>
  </div>
</template>
