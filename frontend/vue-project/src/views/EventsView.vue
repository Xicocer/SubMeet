<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryValue } from 'vue-router'
import { getUserRecommendationsRequest } from '@/api/recommendations'
import {
  addWantToGoRequest,
  getAgeRatingsRequest,
  getCategoriesRequest,
  getEventsRequest,
  removeWantToGoRequest,
} from '@/api/events'
import WantToGoButton from '@/components/WantToGoButton.vue'
import { useAuthStore } from '@/stores/auth'
import type {
  AgeRating,
  Category,
  EventListFilters,
  EventSort,
  PaginatedResponse,
  PublicEvent,
} from '@/types/event'
import type { RecommendationItem } from '@/types/recommendation'
import { formatDate, formatPrice } from '@/utils/format'

interface CatalogSection {
  key: string
  title: string
  subtitle: string
  events: PublicEvent[]
  accent: 'blue' | 'graphite' | 'amber'
}

interface EventBadge {
  label: string
  classes: string
}

const SEARCH_HISTORY_KEY = 'submeet.catalogSearchHistory'
const SEARCH_HISTORY_LIMIT = 8
const DAY_IN_MS = 24 * 60 * 60 * 1000

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const categories = ref<Category[]>([])
const ageRatings = ref<AgeRating[]>([])
const events = ref<PublicEvent[]>([])
const recommendations = ref<RecommendationItem[]>([])
const loading = ref(false)
const error = ref('')
const filtersPanelOpen = ref(false)
const wantToGoUpdatingIds = ref<number[]>([])
const searchHistory = ref<string[]>([])

const filters = reactive({
  search: '',
  category: '',
  tag: '',
  age: '',
  sort: 'newest' as EventSort,
  page: 1,
  perPage: 24,
})

const pagination = reactive<PaginatedResponse<PublicEvent>>({
  current_page: 1,
  data: [],
  last_page: 1,
  per_page: 24,
  total: 0,
  from: null,
  to: null,
})

const sortOptions: Array<{ value: EventSort; label: string }> = [
  { value: 'newest', label: 'Сначала новые' },
  { value: 'oldest', label: 'Сначала ранние' },
  { value: 'title_asc', label: 'Название А-Я' },
  { value: 'title_desc', label: 'Название Я-А' },
]

const pageNumbers = computed(() => {
  const pages: number[] = []
  const start = Math.max(1, pagination.current_page - 2)
  const end = Math.min(pagination.last_page, pagination.current_page + 2)

  for (let page = start; page <= end; page += 1) {
    pages.push(page)
  }

  return pages
})

const activeFilterCount = computed(() => {
  let count = 0

  if (filters.search.trim()) count += 1
  if (filters.category) count += 1
  if (filters.tag) count += 1
  if (filters.age) count += 1
  if (filters.sort !== 'newest') count += 1

  return count
})

const popularCategories = computed(() => categories.value.slice(0, 7))
const featuredEvent = computed(() => events.value.find((event) => hasTicketSales(event)) ?? events.value[0] ?? null)
const recommendedEventIds = computed(() => new Set(recommendations.value.map((item) => Number(item.id))))

const isTeaserEvent = (event: PublicEvent) => {
  return event.is_teaser || !event.has_available_sessions || (event.available_sessions_count ?? 0) === 0
}

const hasTicketSales = (event: PublicEvent) => !isTeaserEvent(event)

const eventTimestamp = (event: PublicEvent) => {
  const value = event.next_session?.start_time

  if (!value) {
    return Number.MAX_SAFE_INTEGER
  }

  const timestamp = new Date(value).getTime()
  return Number.isNaN(timestamp) ? Number.MAX_SAFE_INTEGER : timestamp
}

const uniqueEvents = (items: PublicEvent[]) => {
  const seen = new Set<number>()

  return items.filter((event) => {
    if (seen.has(event.id)) {
      return false
    }

    seen.add(event.id)
    return true
  })
}

const byCategory = (slug: string) => {
  return events.value.filter((event) => event.category?.slug === slug)
}

const popularEvents = computed(() => {
  const recommended = events.value.filter((event) => recommendedEventIds.value.has(event.id))
  const fallback = [...events.value]
    .sort((left, right) => {
      const leftScore = Number(hasTicketSales(left)) * 10 + (left.available_sessions_count ?? 0)
      const rightScore = Number(hasTicketSales(right)) * 10 + (right.available_sessions_count ?? 0)

      return rightScore - leftScore
    })

  return uniqueEvents([...recommended, ...fallback]).slice(0, 8)
})

const upcomingEvents = computed(() => {
  return events.value
    .filter(hasTicketSales)
    .sort((left, right) => eventTimestamp(left) - eventTimestamp(right))
    .slice(0, 8)
})

const concertEvents = computed(() => byCategory('concert').slice(0, 8))
const standupEvents = computed(() => byCategory('standup').slice(0, 8))
const teaserEvents = computed(() => events.value.filter(isTeaserEvent).slice(0, 8))

const catalogSections = computed<CatalogSection[]>(() => {
  const sections: CatalogSection[] = [
    {
      key: 'popular',
      title: 'Популярное',
      subtitle: 'События, которые стоит открыть первыми.',
      events: popularEvents.value,
      accent: 'blue',
    },
    {
      key: 'upcoming',
      title: 'Ближайшие',
      subtitle: 'Афиша с ближайшими доступными сеансами.',
      events: upcomingEvents.value,
      accent: 'graphite',
    },
    {
      key: 'concerts',
      title: 'Концерты',
      subtitle: 'Музыка, живой звук и быстрый переход к покупке.',
      events: concertEvents.value,
      accent: 'blue',
    },
    {
      key: 'standup',
      title: 'Стендап',
      subtitle: 'Вечера с юмором, камерными площадками и понятной ценой.',
      events: standupEvents.value,
      accent: 'graphite',
    },
    {
      key: 'teasers',
      title: 'Тизеры',
      subtitle: 'События уже опубликованы, но продажи откроются позже.',
      events: teaserEvents.value,
      accent: 'amber',
    },
  ]

  return sections.filter((section) => section.events.length > 0)
})

const heroDescription = computed(() => {
  const description = featuredEvent.value?.description?.trim()

  if (!description) {
    return 'Выбирай событие, открывай карточку и переходи к покупке без лишних технических шагов.'
  }

  return description.length > 150 ? `${description.slice(0, 150).trim()}...` : description
})

const eventDateLabel = (event: PublicEvent) => {
  if (isTeaserEvent(event)) {
    return 'Дата скоро'
  }

  return formatDate(event.next_session?.start_time)
}

const eventPriceLabel = (event: PublicEvent) => {
  if (isTeaserEvent(event)) {
    return 'Продажи скоро'
  }

  const price = event.minimum_price ?? event.next_session?.base_price
  return price === null || price === undefined ? 'Цена скоро' : `от ${formatPrice(price)}`
}

const eventStatusLabel = (event: PublicEvent) => {
  return isTeaserEvent(event) ? 'Скоро' : 'Билеты доступны'
}

const eventActionLabel = (event: PublicEvent) => {
  return isTeaserEvent(event) ? 'Подробнее' : 'Купить билет'
}

const eventPosterFallback = (event: PublicEvent) => {
  return event.category?.name || 'Submeet'
}

const parseDate = (value?: string | null) => {
  if (!value) {
    return null
  }

  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

const startOfUtcDay = (date: Date) => {
  return Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate())
}

const startOfUtcWeek = (date: Date) => {
  const day = date.getUTCDay() || 7
  return startOfUtcDay(date) - (day - 1) * DAY_IN_MS
}

const eventBadges = (event: PublicEvent): EventBadge[] => {
  const badges: EventBadge[] = []
  const now = new Date()
  const createdAt = parseDate(event.created_at)
  const sessionStart = parseDate(event.next_session?.start_time)

  if (createdAt && now.getTime() - createdAt.getTime() <= 7 * DAY_IN_MS) {
    badges.push({
      label: 'Новое',
      classes: 'bg-white text-blue-700 border-white/70',
    })
  }

  if (sessionStart) {
    if (startOfUtcDay(sessionStart) === startOfUtcDay(now)) {
      badges.push({
        label: 'Сегодня',
        classes: 'bg-blue-600 text-white border-blue-400/60',
      })
    } else {
      const weekStart = startOfUtcWeek(now)
      const nextWeekStart = weekStart + 7 * DAY_IN_MS
      const sessionDay = startOfUtcDay(sessionStart)

      if (sessionDay >= weekStart && sessionDay < nextWeekStart) {
        badges.push({
          label: 'На этой неделе',
          classes: 'bg-slate-950/72 text-white border-white/20',
        })
      }
    }
  }

  return badges
}

const sectionAccentClass = (accent: CatalogSection['accent']) => {
  if (accent === 'amber') {
    return 'border-amber-200 bg-amber-50 text-amber-800'
  }

  if (accent === 'graphite') {
    return 'border-slate-200 bg-slate-100 text-slate-700'
  }

  return 'border-blue-100 bg-blue-50 text-blue-700'
}

const getQueryValue = (value?: LocationQueryValue | LocationQueryValue[] | null) => {
  if (Array.isArray(value)) {
    return value[0] ?? ''
  }

  return value ?? ''
}

const loadSearchHistory = () => {
  try {
    const rawHistory = window.localStorage.getItem(SEARCH_HISTORY_KEY)
    const parsedHistory = rawHistory ? JSON.parse(rawHistory) as unknown : []

    searchHistory.value = Array.isArray(parsedHistory)
      ? parsedHistory.filter((item): item is string => typeof item === 'string' && item.trim() !== '').slice(0, SEARCH_HISTORY_LIMIT)
      : []
  } catch {
    searchHistory.value = []
  }
}

const persistSearchHistory = () => {
  window.localStorage.setItem(SEARCH_HISTORY_KEY, JSON.stringify(searchHistory.value))
}

const rememberSearchQuery = (query: string) => {
  const normalizedQuery = query.trim()

  if (normalizedQuery.length < 2) {
    return
  }

  searchHistory.value = [
    normalizedQuery,
    ...searchHistory.value.filter((item) => item.toLowerCase() !== normalizedQuery.toLowerCase()),
  ].slice(0, SEARCH_HISTORY_LIMIT)

  persistSearchHistory()
}

const clearSearchHistory = () => {
  searchHistory.value = []
  persistSearchHistory()
}

const applySearchFromHistory = async (query: string) => {
  filters.search = query
  filters.page = 1

  await router.replace({
    name: 'events',
    query: buildQuery(1),
  })
}

const syncFiltersFromQuery = () => {
  filters.search = getQueryValue(route.query.search)
  filters.category = getQueryValue(route.query.category)
  filters.tag = getQueryValue(route.query.tag)
  filters.age = getQueryValue(route.query.age)
  filters.sort = (getQueryValue(route.query.sort) || 'newest') as EventSort
  filters.page = Number(getQueryValue(route.query.page) || 1)
}

const buildQuery = (page = 1) => {
  const query: Record<string, string> = {}

  if (filters.search.trim()) query.search = filters.search.trim()
  if (filters.category) query.category = filters.category
  if (filters.tag) query.tag = filters.tag
  if (filters.age) query.age = filters.age
  if (filters.sort !== 'newest') query.sort = filters.sort
  if (page > 1) query.page = String(page)

  return query
}

const failedPosterKeys = ref<string[]>([])

const posterKey = (scope: string, id: number | string) => `${scope}:${id}`

const isPosterAvailable = (scope: string, id: number | string, posterUrl?: string | null) => {
  return Boolean(posterUrl) && !failedPosterKeys.value.includes(posterKey(scope, id))
}

const markPosterFailed = (scope: string, id: number | string) => {
  const key = posterKey(scope, id)

  if (!failedPosterKeys.value.includes(key)) {
    failedPosterKeys.value = [...failedPosterKeys.value, key]
  }
}

const isWantToGoLoading = (eventId: number) => wantToGoUpdatingIds.value.includes(eventId)

const setWantToGoState = (eventId: number, nextState: boolean) => {
  events.value = events.value.map((event) => (
    event.id === eventId
      ? {
        ...event,
        is_wanted: nextState,
      }
      : event
  ))
}

const toggleWantToGo = async (event: PublicEvent) => {
  if (!authStore.isAuthenticated) {
    await router.push({
      name: 'login',
      query: {
        redirect: route.fullPath,
      },
    })

    return
  }

  if (authStore.isOrganizer || isWantToGoLoading(event.id)) {
    return
  }

  wantToGoUpdatingIds.value = [...wantToGoUpdatingIds.value, event.id]

  try {
    if (event.is_wanted) {
      await removeWantToGoRequest(event.id)
      setWantToGoState(event.id, false)
    } else {
      await addWantToGoRequest(event.id)
      setWantToGoState(event.id, true)
    }
  } catch (requestError) {
    console.error(requestError)
  } finally {
    wantToGoUpdatingIds.value = wantToGoUpdatingIds.value.filter((id) => id !== event.id)
  }
}

const loadLookups = async () => {
  try {
    const [loadedCategories, loadedAgeRatings] = await Promise.all([
      getCategoriesRequest(),
      getAgeRatingsRequest(),
    ])

    categories.value = loadedCategories
    ageRatings.value = loadedAgeRatings
  } catch (requestError) {
    console.error(requestError)
    error.value = 'Не удалось загрузить справочники для каталога.'
  }
}

const loadEvents = async () => {
  loading.value = true
  error.value = ''

  try {
    const params: EventListFilters = {
      search: filters.search.trim() || undefined,
      category: filters.category || undefined,
      tag: filters.tag || undefined,
      age: filters.age ? Number(filters.age) : undefined,
      sort: filters.sort,
      page: filters.page,
      per_page: filters.perPage,
    }

    const response = await getEventsRequest(params)

    events.value = response.data
    if (params.search) {
      rememberSearchQuery(params.search)
    }
    pagination.current_page = response.current_page
    pagination.last_page = response.last_page
    pagination.per_page = response.per_page
    pagination.total = response.total
    pagination.from = response.from
    pagination.to = response.to
  } catch (requestError) {
    console.error(requestError)
    error.value = 'Не удалось загрузить список событий.'
    events.value = []
  } finally {
    loading.value = false
  }
}

const loadRecommendations = async () => {
  if (!authStore.user) {
    recommendations.value = []
    return
  }

  try {
    const response = await getUserRecommendationsRequest(authStore.user.id, 8)

    recommendations.value = response.items
  } catch (requestError) {
    console.error(requestError)
    recommendations.value = []
  }
}

const applyFilters = async () => {
  await router.replace({
    name: 'events',
    query: buildQuery(1),
  })
}

const resetFilters = async () => {
  filters.search = ''
  filters.category = ''
  filters.tag = ''
  filters.age = ''
  filters.sort = 'newest'
  filters.page = 1

  await router.replace({ name: 'events' })
}

const goToPage = async (page: number) => {
  if (page < 1 || page > pagination.last_page) {
    return
  }

  await router.replace({
    name: 'events',
    query: buildQuery(page),
  })
}

const selectCategory = async (slug: string) => {
  filters.category = filters.category === slug ? '' : slug
  filters.tag = ''

  await router.replace({
    name: 'events',
    query: buildQuery(1),
  })
}

const selectTeaser = async () => {
  filters.tag = filters.tag === 'teaser' ? '' : 'teaser'
  filters.category = ''

  await router.replace({
    name: 'events',
    query: buildQuery(1),
  })
}

watch(
  () => route.query,
  async () => {
    syncFiltersFromQuery()
    await loadEvents()
  },
  { immediate: true },
)

watch(
  () => authStore.user?.id ?? null,
  async () => {
    await loadRecommendations()
  },
  { immediate: true },
)

onMounted(async () => {
  loadSearchHistory()

  if (authStore.token && !authStore.user) {
    await authStore.fetchMe()
  }

  await loadLookups()
})
</script>

<template>
  <div class="space-y-6 lg:space-y-8">
    <section class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-slate-950 via-slate-900 to-blue-900 px-5 py-6 text-white shadow-[0_35px_95px_-70px_rgba(15,23,42,0.95)] sm:px-7 lg:px-9">
      <div class="pointer-events-none absolute inset-y-0 right-0 w-1/2 bg-[radial-gradient(circle_at_top_right,rgba(96,165,250,0.32),transparent_32%),radial-gradient(circle_at_bottom_right,rgba(255,255,255,0.14),transparent_28%)]"></div>
      <div class="relative grid gap-5 lg:grid-cols-[1.08fr_0.92fr] lg:items-center">
        <div>
          <span class="inline-flex items-center rounded-full border border-white/15 bg-white/8 px-3 py-1 text-[0.68rem] font-semibold uppercase tracking-[0.28em] text-blue-100">
            Submeet афиша
          </span>
          <h1 class="mt-4 max-w-3xl text-3xl font-semibold leading-[1.04] sm:text-4xl lg:text-5xl">
            События рядом: выбери афишу, место и билет без лишнего шума.
          </h1>
          <p class="mt-4 max-w-2xl text-sm leading-7 text-blue-50/76 sm:text-base">
            Современный каталог с постерами, быстрыми категориями и понятным статусом продаж.
          </p>
        </div>

        <RouterLink
          v-if="featuredEvent"
          :to="`/events/${featuredEvent.id}`"
          class="group overflow-hidden rounded-[1.7rem] border border-white/12 bg-white/10 p-3 backdrop-blur-xl transition duration-300 hover:-translate-y-1 hover:bg-white/14"
        >
          <div class="grid gap-4 sm:grid-cols-[8.5rem_1fr] sm:items-center">
            <div class="relative h-36 overflow-hidden rounded-[1.35rem] bg-slate-950">
              <img
                v-if="isPosterAvailable('hero', featuredEvent.id, featuredEvent.poster_url)"
                :src="featuredEvent.poster_url || undefined"
                :alt="featuredEvent.title"
                class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.05]"
                @error="markPosterFailed('hero', featuredEvent.id)"
              />
              <div v-else class="flex h-full items-center justify-center bg-[linear-gradient(145deg,#0f172a,#1d4ed8)] px-4 text-center text-xs font-semibold uppercase tracking-[0.2em] text-blue-50">
                {{ eventPosterFallback(featuredEvent) }}
              </div>
            </div>

            <div class="min-w-0">
              <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-100/72">
                В центре внимания
              </p>
              <h2 class="mt-2 line-clamp-2 text-2xl font-semibold leading-tight text-white">
                {{ featuredEvent.title }}
              </h2>
              <p class="mt-2 line-clamp-2 text-sm leading-6 text-blue-50/72">
                {{ heroDescription }}
              </p>
              <div class="mt-4 flex flex-wrap gap-2">
                <span
                  v-for="badge in eventBadges(featuredEvent)"
                  :key="badge.label"
                  class="rounded-full border px-3 py-1 text-xs font-semibold"
                  :class="badge.classes"
                >
                  {{ badge.label }}
                </span>
                <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-white/90">
                  {{ eventDateLabel(featuredEvent) }}
                </span>
                <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-white/90">
                  {{ eventPriceLabel(featuredEvent) }}
                </span>
              </div>
            </div>
          </div>
        </RouterLink>
      </div>
    </section>

    <section id="catalog" class="app-panel p-4 sm:p-5 lg:p-6">
      <form class="grid gap-3 lg:grid-cols-[1fr_auto]" @submit.prevent="applyFilters">
        <div>
          <label class="sr-only" for="event-search">Поиск события</label>
          <input
            id="event-search"
            v-model="filters.search"
            type="text"
            class="field-input"
            placeholder="Найти концерт, стендап, театр или фестиваль"
          />
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
          <button type="submit" class="primary-button">
            Найти
          </button>

          <button
            type="button"
            class="secondary-button"
            :aria-expanded="filtersPanelOpen"
            @click="filtersPanelOpen = !filtersPanelOpen"
          >
            Фильтры
            <span v-if="activeFilterCount > 0" class="ml-2 rounded-full bg-blue-600 px-2 py-0.5 text-xs text-white">
              {{ activeFilterCount }}
            </span>
          </button>
        </div>
      </form>

      <div v-if="searchHistory.length > 0" class="mt-3 flex flex-wrap items-center gap-2">
        <span class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
          Недавно искали
        </span>
        <button
          v-for="query in searchHistory"
          :key="query"
          type="button"
          class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm font-semibold text-slate-600 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700"
          @click="applySearchFromHistory(query)"
        >
          {{ query }}
        </button>
        <button
          type="button"
          class="rounded-full px-3 py-1.5 text-sm font-semibold text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
          @click="clearSearchHistory"
        >
          Очистить
        </button>
      </div>

      <div class="mt-4 flex flex-wrap items-center gap-2">
        <button
          type="button"
          class="catalog-chip"
          :class="filters.category === '' && filters.tag === '' ? 'catalog-chip-active' : ''"
          @click="selectCategory('')"
        >
          Все
        </button>

        <button
          v-for="category in popularCategories"
          :key="category.id"
          type="button"
          class="catalog-chip"
          :class="filters.category === category.slug ? 'catalog-chip-active' : ''"
          @click="selectCategory(category.slug)"
        >
          {{ category.name }}
        </button>

        <button
          type="button"
          class="rounded-full border px-4 py-2 text-sm font-semibold uppercase tracking-[0.18em] transition"
          :class="filters.tag === 'teaser'
            ? 'border-amber-300 bg-amber-300 text-slate-950 shadow-sm shadow-amber-500/25'
            : 'border-amber-200 bg-amber-50 text-amber-800 hover:border-amber-300 hover:bg-amber-100'"
          @click="selectTeaser"
        >
          Тизеры
        </button>

        <span class="ml-auto hidden text-sm text-slate-500 md:inline">
          {{ pagination.total }} событий в афише
        </span>
      </div>

      <div v-if="filtersPanelOpen" class="mt-5 rounded-[1.6rem] border border-slate-200 bg-slate-50/80 p-4">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-[1fr_0.8fr_0.9fr_1fr_auto] xl:items-end">
          <div>
            <label class="field-label" for="event-category">Категория</label>
            <select id="event-category" v-model="filters.category" class="field-input">
              <option value="">Все категории</option>
              <option v-for="category in categories" :key="category.id" :value="category.slug">
                {{ category.name }}
              </option>
            </select>
          </div>

          <div>
            <label class="field-label" for="event-age">Возраст</label>
            <select id="event-age" v-model="filters.age" class="field-input">
              <option value="">Любой рейтинг</option>
              <option v-for="ageRating in ageRatings" :key="ageRating.id" :value="String(ageRating.min_age)">
                {{ ageRating.label }}
              </option>
            </select>
          </div>

          <div>
            <label class="field-label" for="event-sort">Сортировка</label>
            <select id="event-sort" v-model="filters.sort" class="field-input">
              <option v-for="option in sortOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>

          <div>
            <label class="field-label" for="event-tag">Тег</label>
            <input
              id="event-tag"
              v-model="filters.tag"
              type="text"
              class="field-input"
              placeholder="Например: jazz"
            />
          </div>

          <div class="flex gap-2 md:col-span-2 xl:col-span-1">
            <button type="button" class="primary-button flex-1 xl:flex-none" @click="applyFilters">
              Применить
            </button>
            <button type="button" class="secondary-button flex-1 xl:flex-none" @click="resetFilters">
              Сбросить
            </button>
          </div>
        </div>
      </div>
    </section>

    <div v-if="error" class="message-error">
      {{ error }}
    </div>

    <section v-if="loading" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <article v-for="item in 8" :key="item" class="overflow-hidden rounded-[1.6rem] border border-slate-200/80 bg-white shadow-[0_28px_80px_-58px_rgba(15,23,42,0.4)]">
        <div class="aspect-[4/5] animate-pulse bg-slate-200"></div>
        <div class="space-y-3 p-4">
          <div class="h-4 w-24 animate-pulse rounded-full bg-slate-100"></div>
          <div class="h-7 w-3/4 animate-pulse rounded-full bg-slate-200"></div>
          <div class="h-4 w-full animate-pulse rounded-full bg-slate-100"></div>
        </div>
      </article>
    </section>

    <section v-else-if="events.length > 0" class="space-y-10">
      <section v-for="section in catalogSections" :key="section.key" class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <span class="status-badge" :class="sectionAccentClass(section.accent)">
              {{ section.events.length }} событий
            </span>
            <h2 class="mt-3 text-3xl font-semibold tracking-[-0.03em] text-slate-950 sm:text-[2.35rem]">
              {{ section.title }}
            </h2>
            <p class="mt-2 text-sm leading-6 text-slate-500">
              {{ section.subtitle }}
            </p>
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <article
            v-for="event in section.events"
            :key="`${section.key}-${event.id}`"
            class="group overflow-hidden rounded-[1.6rem] border border-slate-200/80 bg-white shadow-[0_28px_90px_-62px_rgba(15,23,42,0.42)] transition duration-300 hover:-translate-y-1 hover:border-blue-200 hover:shadow-[0_35px_100px_-62px_rgba(37,99,235,0.36)]"
          >
            <RouterLink :to="`/events/${event.id}`" class="block">
              <div class="relative aspect-[4/5] overflow-hidden bg-gradient-to-br from-slate-950 via-slate-900 to-blue-900">
                <img
                  v-if="isPosterAvailable('catalog', event.id, event.poster_url)"
                  :src="event.poster_url || undefined"
                  :alt="event.title"
                  class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.045]"
                  @error="markPosterFailed('catalog', event.id)"
                />
                <div v-else class="flex h-full items-center justify-center px-6 text-center text-sm font-semibold uppercase tracking-[0.22em] text-blue-50">
                  {{ eventPosterFallback(event) }}
                </div>

                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/74 via-transparent to-slate-950/20"></div>

                <div class="absolute inset-x-0 top-0 flex items-start justify-between gap-2 p-4">
                  <span class="rounded-full border border-white/18 bg-slate-950/58 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.22em] text-white backdrop-blur">
                    {{ event.category?.name || 'Событие' }}
                  </span>
                  <span class="rounded-full border border-white/18 bg-white/14 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-white backdrop-blur">
                    {{ event.age_rating?.label || '0+' }}
                  </span>
                </div>

                <div v-if="eventBadges(event).length > 0" class="absolute left-4 top-14 flex flex-wrap gap-2">
                  <span
                    v-for="badge in eventBadges(event)"
                    :key="badge.label"
                    class="rounded-full border px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] shadow-sm shadow-slate-950/20 backdrop-blur"
                    :class="badge.classes"
                  >
                    {{ badge.label }}
                  </span>
                </div>

                <div class="absolute inset-x-0 bottom-0 p-4">
                  <span
                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                    :class="isTeaserEvent(event) ? 'bg-amber-300 text-slate-950' : 'bg-blue-600 text-white'"
                  >
                    {{ eventStatusLabel(event) }}
                  </span>
                </div>
              </div>
            </RouterLink>

            <div class="space-y-4 p-4">
              <RouterLink :to="`/events/${event.id}`" class="block">
                <h3 class="line-clamp-2 min-h-[3.2rem] text-xl font-semibold leading-tight text-slate-950 transition duration-200 group-hover:text-blue-700">
                  {{ event.title }}
                </h3>
              </RouterLink>

              <div class="grid gap-2 rounded-[1.25rem] border border-slate-200 bg-slate-50/80 p-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                  <span class="text-slate-500">Дата</span>
                  <span class="text-right font-semibold text-slate-950">{{ eventDateLabel(event) }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                  <span class="text-slate-500">Цена</span>
                  <span class="text-right font-semibold text-blue-700">{{ eventPriceLabel(event) }}</span>
                </div>
              </div>

              <div class="flex items-center gap-2">
                <RouterLink :to="`/events/${event.id}`" class="primary-button min-h-[3rem] flex-1 px-4 py-3 text-sm">
                  {{ eventActionLabel(event) }}
                </RouterLink>

                <WantToGoButton
                  v-if="!authStore.isOrganizer"
                  :active="event.is_wanted"
                  :loading="isWantToGoLoading(event.id)"
                  compact
                  @toggle="toggleWantToGo(event)"
                />
              </div>
            </div>
          </article>
        </div>
      </section>
    </section>

    <section v-else class="app-panel p-8 sm:p-10">
      <span class="info-chip">Пока пусто</span>
      <h2 class="mt-4 text-3xl font-semibold text-slate-950">
        По текущим фильтрам событий не найдено
      </h2>
      <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-500 sm:text-base">
        Попробуй снять часть ограничений или сбросить поиск. В каталоге показываются только опубликованные события.
      </p>
      <button type="button" class="secondary-button mt-6" @click="resetFilters">
        Вернуть всю афишу
      </button>
    </section>

    <section v-if="pagination.last_page > 1" class="app-panel p-5 sm:p-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-slate-500">
          Показаны
          <span class="font-semibold text-slate-900">{{ pagination.from ?? 0 }}-{{ pagination.to ?? 0 }}</span>
          из
          <span class="font-semibold text-slate-900">{{ pagination.total }}</span>
          событий
        </p>

        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="secondary-button px-4 py-2.5"
            :disabled="pagination.current_page === 1"
            @click="goToPage(pagination.current_page - 1)"
          >
            Назад
          </button>

          <button
            v-for="page in pageNumbers"
            :key="page"
            type="button"
            class="secondary-button px-4 py-2.5"
            :class="page === pagination.current_page ? 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-50 hover:text-blue-700' : ''"
            @click="goToPage(page)"
          >
            {{ page }}
          </button>

          <button
            type="button"
            class="secondary-button px-4 py-2.5"
            :disabled="pagination.current_page === pagination.last_page"
            @click="goToPage(pagination.current_page + 1)"
          >
            Дальше
          </button>
        </div>
      </div>
    </section>
  </div>
</template>
