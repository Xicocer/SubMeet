<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getAdminEventsRequest, updateAdminEventModerationRequest } from '@/api/admin'
import type { AdminEventDecision, AdminEventStatus, AdminEventSummary } from '@/types/admin'
import { formatDateTime } from '@/utils/format'

type EventDraft = {
  decision: AdminEventDecision
  note: string
}

const route = useRoute()
const router = useRouter()

const events = ref<AdminEventSummary[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const loading = ref(false)
const error = ref('')
const feedback = ref('')
const actionEventId = ref<number | null>(null)
const statusFilter = ref<'all' | AdminEventStatus>('pending_review')
const searchInput = ref('')
const drafts = ref<Record<number, EventDraft>>({})

const filterOptions: Array<{ value: 'all' | AdminEventStatus; label: string }> = [
  { value: 'all', label: 'Все' },
  { value: 'pending_review', label: 'На модерации' },
  { value: 'draft', label: 'Draft' },
  { value: 'published', label: 'Published' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'archived', label: 'Archived' },
]

const totalLabel = computed(() => new Intl.NumberFormat('ru-RU').format(total.value))
const hasFilters = computed(() => statusFilter.value !== 'all' || searchInput.value.trim().length > 0)

const statusLabel = (status: AdminEventStatus) => {
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

const statusClasses = (status: AdminEventStatus) => {
  switch (status) {
    case 'pending_review':
      return 'border-blue-200 bg-blue-50 text-blue-700'
    case 'published':
      return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    case 'cancelled':
      return 'border-rose-200 bg-rose-50 text-rose-700'
    case 'archived':
      return 'border-slate-200 bg-slate-100 text-slate-600'
    default:
      return 'border-amber-200 bg-amber-50 text-amber-700'
  }
}

const syncDrafts = (items: AdminEventSummary[]) => {
  const nextDrafts: Record<number, EventDraft> = {}

  items.forEach((item) => {
    nextDrafts[item.id] = drafts.value[item.id] ?? {
      decision: item.status === 'published' ? 'unpublish' : 'approve',
      note: item.moderation_note ?? '',
    }
  })

  drafts.value = nextDrafts
}

const getDraft = (item: AdminEventSummary): EventDraft | null => {
  return drafts.value[item.id] ?? null
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
    statusFilter.value = routeStatus as 'all' | AdminEventStatus
  }

  if (typeof routeSearch === 'string') {
    searchInput.value = routeSearch
  }
}

const loadEvents = async (page = 1, updateRoute = true) => {
  loading.value = true
  error.value = ''

  if (updateRoute) {
    await syncRoute(page)
  }

  try {
    const response = await getAdminEventsRequest({
      page,
      per_page: 12,
      status: statusFilter.value === 'all' ? undefined : statusFilter.value,
      search: searchInput.value.trim() || undefined,
    })

    events.value = response.data
    currentPage.value = response.current_page
    lastPage.value = response.last_page
    total.value = response.total
    syncDrafts(response.data)
  } catch (requestError: any) {
    console.error(requestError)
    error.value = requestError?.response?.data?.message || 'Не удалось загрузить очередь событий.'
  } finally {
    loading.value = false
  }
}

const submitSearch = async () => {
  await loadEvents(1)
}

const clearFilters = async () => {
  statusFilter.value = 'all'
  searchInput.value = ''
  await loadEvents(1)
}

const applyModeration = async (
  item: AdminEventSummary,
  decisionOverride?: AdminEventDecision,
) => {
  const draft = getDraft(item)

  if (!draft) {
    return
  }

  actionEventId.value = item.id
  feedback.value = ''
  error.value = ''

  try {
    const response = await updateAdminEventModerationRequest(item.id, {
      decision: decisionOverride ?? draft.decision,
      note: draft.note.trim() || null,
    })

    const updatedEvent = response.event
    const index = events.value.findIndex((event) => event.id === item.id)

    if (index !== -1) {
      events.value[index] = updatedEvent
    }

    drafts.value[item.id] = {
      decision: updatedEvent.status === 'published' ? 'unpublish' : 'approve',
      note: updatedEvent.moderation_note ?? '',
    }

    feedback.value = response.message
  } catch (requestError: any) {
    console.error(requestError)
    error.value = requestError?.response?.data?.message || 'Не удалось применить решение по событию.'
  } finally {
    actionEventId.value = null
  }
}

onMounted(async () => {
  hydrateFromRoute()
  const page = typeof route.query.page === 'string' ? Number(route.query.page) || 1 : 1
  await loadEvents(page, false)
})
</script>

<template>
  <div class="space-y-6">
    <section class="app-panel relative overflow-hidden p-8 sm:p-10">
      <div class="absolute inset-x-0 top-0 h-40 bg-[radial-gradient(circle_at_top_left,rgba(37,99,235,0.18),transparent_48%),linear-gradient(120deg,rgba(15,23,42,0.06),transparent_55%)]"></div>
      <div class="relative flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
          <span class="info-chip">Event Moderation</span>
          <h1 class="mt-4 text-3xl font-semibold leading-tight text-slate-950 sm:text-4xl">
            Очередь событий
          </h1>
          <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
            Здесь администратор быстро находит нужную карточку и одним действием решает, можно ли пускать событие в витрину.
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
            placeholder="Поиск по событию, организатору, категории, тегу или описанию"
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
            @click="statusFilter = option.value; loadEvents(1)"
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

    <section v-else-if="events.length" class="grid gap-6 xl:grid-cols-2">
      <article
        v-for="event in events"
        :key="event.id"
        class="app-panel p-7"
      >
        <div class="flex flex-col gap-5">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-3">
              <span class="status-badge" :class="statusClasses(event.status)">
                {{ statusLabel(event.status) }}
              </span>
              <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                event #{{ event.id }}
              </span>
            </div>

            <h2 class="mt-4 text-2xl font-semibold text-slate-950">
              {{ event.title }}
            </h2>

            <p class="mt-3 text-sm leading-6 text-slate-500">
              {{ event.description || 'Описание пока не добавлено.' }}
            </p>

            <div class="mt-4 flex flex-wrap gap-2">
              <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                {{ event.organizer?.display_name || 'Организатор не указан' }}
              </span>
              <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                {{ event.category?.name || 'Категория не указана' }}
              </span>
              <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                {{ event.age_rating?.label || 'Рейтинг не указан' }}
              </span>
              <span
                v-for="tag in event.tags"
                :key="tag.id"
                class="rounded-full border border-blue-100 bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700"
              >
                #{{ tag.name }}
              </span>
            </div>

            <div class="mt-4 grid gap-2 text-sm text-slate-500">
              <p>Обновлено: {{ formatDateTime(event.updated_at) }}</p>
              <p v-if="event.moderation_note">Последняя заметка: {{ event.moderation_note }}</p>
            </div>
          </div>

          <div class="flex flex-wrap gap-3">
            <button
              type="button"
              class="secondary-button border-emerald-200 bg-emerald-50 text-emerald-700"
              :disabled="actionEventId === event.id"
              @click="applyModeration(event, 'approve')"
            >
              Одобрить
            </button>
            <button
              type="button"
              class="secondary-button border-amber-200 bg-amber-50 text-amber-700"
              :disabled="actionEventId === event.id"
              @click="applyModeration(event, 'needs_revision')"
            >
              На доработку
            </button>
            <button
              type="button"
              class="secondary-button border-rose-200 bg-rose-50 text-rose-700"
              :disabled="actionEventId === event.id"
              @click="applyModeration(event, 'unpublish')"
            >
              Снять с витрины
            </button>
          </div>

          <div v-if="getDraft(event)" class="rounded-[1.5rem] border border-slate-200 bg-slate-50/80 px-5 py-5">
            <label class="block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
              Комментарий для организатора
            </label>
            <textarea
              v-model="getDraft(event)!.note"
              rows="4"
              class="mt-3 w-full rounded-[1.2rem] border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
              placeholder="Что сообщить организатору"
            ></textarea>

            <div class="mt-4 flex flex-wrap gap-3">
              <select
                v-model="getDraft(event)!.decision"
                class="min-w-[14rem] rounded-[1.2rem] border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
              >
                <option value="approve">Одобрить</option>
                <option value="needs_revision">На доработку</option>
                <option value="unpublish">Снять с витрины</option>
              </select>

              <button
                type="button"
                class="primary-button"
                :disabled="actionEventId === event.id"
                @click="applyModeration(event)"
              >
                {{ actionEventId === event.id ? 'Сохраняем...' : 'Применить решение' }}
              </button>
            </div>
          </div>
        </div>
      </article>
    </section>

    <section v-else class="app-panel p-8">
      <div class="rounded-[1.75rem] border border-dashed border-slate-200 bg-slate-50/70 px-6 py-8 text-center">
        <p class="text-lg font-semibold text-slate-900">
          События не найдены
        </p>
        <p class="mt-3 text-sm leading-6 text-slate-500">
          Попробуй убрать часть фильтров или изменить поисковый запрос.
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
        @click="loadEvents(currentPage - 1)"
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
        @click="loadEvents(currentPage + 1)"
      >
        Дальше
      </button>
    </div>
  </div>
</template>
