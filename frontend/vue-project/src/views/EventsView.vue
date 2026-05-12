<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryValue } from 'vue-router'
import { getRecommendationPreviewRequest, getUserRecommendationsRequest } from '@/api/recommendations'
import {
  addWantToGoRequest,
  getEventAssistantRequest,
  getAgeRatingsRequest,
  getCategoriesRequest,
  getEventsRequest,
  removeWantToGoRequest,
} from '@/api/events'
import WantToGoButton from '@/components/WantToGoButton.vue'
import { useAuthStore } from '@/stores/auth'
import type { EventAssistantResponse } from '@/types/assistant'
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
import { renderMarkdown } from '@/utils/markdown'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const categories = ref<Category[]>([])
const ageRatings = ref<AgeRating[]>([])
const events = ref<PublicEvent[]>([])
const recommendations = ref<RecommendationItem[]>([])
const assistantResponse = ref<EventAssistantResponse | null>(null)
const loading = ref(false)
const recommendationsLoading = ref(false)
const assistantLoading = ref(false)
const error = ref('')
const recommendationsError = ref('')
const assistantError = ref('')
const wantToGoUpdatingIds = ref<number[]>([])
const assistantQuery = ref('')

const filters = reactive({
  search: '',
  category: '',
  age: '',
  sort: 'newest' as EventSort,
  page: 1,
  perPage: 6,
})

const pagination = reactive<PaginatedResponse<PublicEvent>>({
  current_page: 1,
  data: [],
  last_page: 1,
  per_page: 6,
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

const assistantSuggestions = [
  'Куда сходить сегодня вечером?',
  'На следующей неделе планирую свидание, что ты посоветуешь?',
  'Хочу с друзьями что-то энергичное на выходных.',
]

const eventCopyByCategory: Record<string, string> = {
  concert: 'Живой звук, свет и удобное бронирование мест онлайн.',
  theater: 'Тихий зал, точная рассадка и билет без лишней суеты.',
  festival: 'Большое событие с быстрым входом в сеансы и покупку билетов.',
  cinema: 'Сеансы, места и бронь в одном понятном пользовательском потоке.',
  standup: 'Быстрый выбор места и билета на вечер без лишних шагов.',
  lecture: 'Аккуратная регистрация на событие с понятной схемой зала.',
}

const pageNumbers = computed(() => {
  const pages: number[] = []
  const start = Math.max(1, pagination.current_page - 2)
  const end = Math.min(pagination.last_page, pagination.current_page + 2)

  for (let page = start; page <= end; page += 1) {
    pages.push(page)
  }

  return pages
})

const featuredEvent = computed(() => events.value[0] ?? null)
const spotlightEvents = computed(() => events.value.slice(1, 4))
const activeFilterCount = computed(() => {
  let count = 0

  if (filters.search.trim()) count += 1
  if (filters.category) count += 1
  if (filters.age) count += 1
  if (filters.sort !== 'newest') count += 1

  return count
})

const featuredCategoryName = computed(() => featuredEvent.value?.category?.name || 'События месяца')
const popularCategories = computed(() => categories.value.slice(0, 6))
const recommendationTitle = computed(() => authStore.user ? 'Рекомендуем вам' : 'Что может понравиться уже сейчас')
const recommendationSubtitle = computed(() => authStore.user
  ? 'Лента постепенно подстраивается под твои просмотры, бронирования и интересы, поэтому с каждым визитом подборка становится точнее.'
  : 'Даже без авторизации витрина собирает сильные стартовые варианты, чтобы можно было быстро выбрать событие и перейти к покупке билета.')
const assistantContextLabel = computed(() => {
  if (!assistantResponse.value) {
    return ''
  }

  const labels = [
    assistantResponse.value.personalized ? 'персональный контекст' : 'гостевой сценарий',
    assistantResponse.value.context.intent_label,
    assistantResponse.value.context.timeframe_label,
  ].filter((label): label is string => Boolean(label))

  return labels.join(' · ')
})
const assistantAnswerHtml = computed(() => renderMarkdown(assistantResponse.value?.answer ?? ''))

const getQueryValue = (value?: LocationQueryValue | LocationQueryValue[] | null) => {
  if (Array.isArray(value)) {
    return value[0] ?? ''
  }

  return value ?? ''
}

const syncFiltersFromQuery = () => {
  filters.search = getQueryValue(route.query.search)
  filters.category = getQueryValue(route.query.category)
  filters.age = getQueryValue(route.query.age)
  filters.sort = (getQueryValue(route.query.sort) || 'newest') as EventSort
  filters.page = Number(getQueryValue(route.query.page) || 1)
}

const buildQuery = (page = 1) => {
  const query: Record<string, string> = {}

  if (filters.search.trim()) query.search = filters.search.trim()
  if (filters.category) query.category = filters.category
  if (filters.age) query.age = filters.age
  if (filters.sort !== 'newest') query.sort = filters.sort
  if (page > 1) query.page = String(page)

  return query
}

const buildEventCopy = (event: PublicEvent) => {
  const categorySlug = event.category?.slug || ''
  return eventCopyByCategory[categorySlug] || 'Современная карточка события с быстрым переходом к выбору сеанса и бронированию.'
}

const recommendationTags = (item: RecommendationItem) => {
  return item.tags
    .split(/\s+/)
    .map((tag) => tag.trim())
    .filter((tag) => tag.length >= 2)
    .slice(0, 4)
}

const buildRecommendationLocation = (item: RecommendationItem) => {
  return item.venue_address || item.hall_name || item.city || 'Площадка уточняется'
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
      age: filters.age ? Number(filters.age) : undefined,
      sort: filters.sort,
      page: filters.page,
      per_page: filters.perPage,
    }

    const response = await getEventsRequest(params)

    events.value = response.data
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
  recommendationsLoading.value = true
  recommendationsError.value = ''

  try {
    const response = authStore.user
      ? await getUserRecommendationsRequest(authStore.user.id, 4)
      : await getRecommendationPreviewRequest(4)

    recommendations.value = response.items
  } catch (requestError) {
    console.error(requestError)
    recommendations.value = []
    recommendationsError.value = 'Персональная подборка временно недоступна.'
  } finally {
    recommendationsLoading.value = false
  }
}

const askAssistant = async (prefilledQuery?: string) => {
  const nextQuery = (prefilledQuery ?? assistantQuery.value).trim()

  assistantQuery.value = nextQuery
  assistantError.value = ''

  if (!nextQuery) {
    assistantResponse.value = null
    assistantError.value = 'Сначала напиши, что именно ты хочешь найти.'
    return
  }

  assistantLoading.value = true

  try {
    assistantResponse.value = await getEventAssistantRequest(nextQuery, 4)
  } catch (requestError) {
    console.error(requestError)
    assistantResponse.value = null
    assistantError.value = 'Митя временно недоступен. Ниже все равно можно выбрать событие через обычную подборку.'
  } finally {
    assistantLoading.value = false
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
  if (authStore.token && !authStore.user) {
    await authStore.fetchMe()
  }

  await loadLookups()
})
</script>

<template>
  <div class="space-y-8 lg:space-y-10">
    <section class="relative overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-slate-950 via-slate-900 to-blue-900 px-6 py-8 text-white shadow-[0_45px_120px_-70px_rgba(15,23,42,0.95)] sm:px-8 sm:py-10 lg:px-10 lg:py-12">
      <div class="pointer-events-none absolute inset-y-0 right-0 w-[45%] bg-[radial-gradient(circle_at_top_right,rgba(96,165,250,0.28),transparent_32%),radial-gradient(circle_at_bottom_right,rgba(255,255,255,0.14),transparent_26%)]"></div>
      <div class="pointer-events-none absolute inset-x-0 top-0 h-28 bg-[linear-gradient(120deg,rgba(255,255,255,0.15),transparent_30%,transparent)]"></div>

      <div class="relative grid gap-8 xl:grid-cols-[1.08fr_0.92fr] xl:gap-10">
        <div class="max-w-3xl">
          <span class="inline-flex items-center rounded-full border border-white/15 bg-white/8 px-3 py-1 text-[0.68rem] font-semibold uppercase tracking-[0.28em] text-blue-100">
            New ticket experience
          </span>

          <h1 class="mt-5 max-w-2xl text-4xl font-semibold leading-[1.02] sm:text-5xl lg:text-[3.6rem]">
            Билеты на события без шума, с живой схемой зала и понятной бронью.
          </h1>

          <p class="mt-5 max-w-2xl text-sm leading-7 text-blue-50/78 sm:text-base">
            Каталог построен как современная витрина: сначала ты видишь сильные события, потом быстро фильтруешь афишу и сразу переходишь к выбору мест в зале.
          </p>

          <div class="mt-8 flex flex-wrap gap-3">
            <a href="#catalog" class="primary-button">
              Смотреть афишу
            </a>

            <template v-if="authStore.isAuthenticated">
              <RouterLink
                v-if="authStore.isAdmin"
                to="/admin/dashboard"
                class="secondary-button border-white/20 bg-white/10 text-white hover:border-white/35 hover:bg-white/16 hover:text-white"
              >
                Панель администратора
              </RouterLink>

              <RouterLink
                v-else-if="authStore.isOrganizer"
                to="/organizer/dashboard"
                class="secondary-button border-white/20 bg-white/10 text-white hover:border-white/35 hover:bg-white/16 hover:text-white"
              >
                Панель организатора
              </RouterLink>

              <RouterLink
                v-else
                to="/profile"
                class="secondary-button border-white/20 bg-white/10 text-white hover:border-white/35 hover:bg-white/16 hover:text-white"
              >
                Мой профиль
              </RouterLink>

              <RouterLink
                to="/assistant"
                class="secondary-button border-white/20 bg-transparent text-white hover:border-white/35 hover:bg-white/10 hover:text-white"
              >
                Спросить Митю
              </RouterLink>
            </template>

            <template v-else>
              <RouterLink to="/register" class="secondary-button border-white/20 bg-white/10 text-white hover:border-white/35 hover:bg-white/16 hover:text-white">
                Создать аккаунт
              </RouterLink>

              <RouterLink to="/register/organizer" class="secondary-button border-white/20 bg-transparent text-white hover:border-white/35 hover:bg-white/10 hover:text-white">
                Стать организатором
              </RouterLink>
            </template>
          </div>

          <div class="mt-8 flex flex-wrap gap-2">
            <button
              v-for="category in popularCategories"
              :key="category.id"
              type="button"
              class="rounded-full border px-4 py-2 text-sm font-medium transition duration-200"
              :class="filters.category === category.slug
                ? 'border-blue-300 bg-blue-400/20 text-white'
                : 'border-white/15 bg-white/8 text-blue-50/78 hover:border-white/35 hover:bg-white/12 hover:text-white'"
              @click="selectCategory(category.slug)"
            >
              {{ category.name }}
            </button>
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <article class="rounded-[2rem] border border-white/12 bg-white/8 p-5 shadow-lg shadow-slate-950/20 backdrop-blur">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-100/70">
              Доступно сейчас
            </p>
            <p class="mt-4 text-4xl font-semibold text-white">
              {{ pagination.total }}
            </p>
            <p class="mt-3 text-sm leading-6 text-blue-50/70">
              событий уже в каталоге и готовы к переходу в карточку с бронированием.
            </p>
          </article>

          <article class="rounded-[2rem] border border-white/12 bg-white/8 p-5 shadow-lg shadow-slate-950/20 backdrop-blur">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-100/70">
              Навигация
            </p>
            <p class="mt-4 text-4xl font-semibold text-white">
              {{ categories.length }}
            </p>
            <p class="mt-3 text-sm leading-6 text-blue-50/70">
              категорий помогают быстрее найти концерт, театр, фестиваль или лекцию.
            </p>
          </article>

          <article class="sm:col-span-2 overflow-hidden rounded-[2rem] border border-white/12 bg-[linear-gradient(145deg,rgba(255,255,255,0.14),rgba(255,255,255,0.06))] p-6 shadow-[0_25px_60px_-35px_rgba(37,99,235,0.65)] backdrop-blur">
            <div class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
              <div class="max-w-xl">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-100/70">
                  В центре внимания
                </p>
                <h2 class="mt-3 text-2xl font-semibold text-white sm:text-[2rem]">
                  {{ featuredEvent?.title || 'Каталог уже готов к запуску продаж и бронирования' }}
                </h2>
                <p class="mt-3 text-sm leading-6 text-blue-50/72">
                  {{ featuredEvent ? buildEventCopy(featuredEvent) : 'Скоро здесь появится главное событие недели с быстрым переходом к покупке билета.' }}
                </p>
              </div>

              <RouterLink
                v-if="featuredEvent"
                :to="`/events/${featuredEvent.id}`"
                class="secondary-button border-white/20 bg-white text-slate-950 hover:border-white hover:bg-blue-50 hover:text-slate-950"
              >
                Купить билет
              </RouterLink>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
              <span class="status-badge border-white/15 bg-white/10 text-blue-50">
                {{ featuredCategoryName }}
              </span>
              <span class="status-badge border-white/15 bg-white/10 text-blue-50">
                {{ ageRatings.length }} возрастных рейтингов
              </span>
              <span class="status-badge border-white/15 bg-white/10 text-blue-50">
                {{ activeFilterCount > 0 ? `${activeFilterCount} активных фильтра` : 'Все события открыты' }}
              </span>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="app-panel overflow-hidden p-5 sm:p-6 lg:p-7">
      <div class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr] xl:items-start">
        <div>
          <span class="info-chip">Митя</span>
          <h2 class="mt-4 text-3xl font-semibold leading-tight text-slate-950 sm:text-[2.2rem]">
            Спроси по-человечески, и витрина сама соберет готовую подборку.
          </h2>
          <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-500 sm:text-base">
            Митя берет живые события, усиливает их умной подборкой и сверху добавляет короткий ответ, чтобы не приходилось вручную перебирать весь каталог.
          </p>

          <form class="mt-6 space-y-4" @submit.prevent="askAssistant()">
            <label class="field-label" for="assistant-query">Что спросить у Мити</label>
            <div class="flex flex-col gap-3 lg:flex-row">
              <input
                id="assistant-query"
                v-model="assistantQuery"
                type="text"
                class="field-input flex-1"
                placeholder="Например: Куда сходить сегодня вечером?"
              />
              <button type="submit" class="primary-button min-w-[12rem]" :disabled="assistantLoading">
                {{ assistantLoading ? 'Подбираем...' : 'Подобрать события' }}
              </button>
            </div>
          </form>

          <div class="mt-4 flex flex-wrap gap-2">
            <button
              v-for="suggestion in assistantSuggestions"
              :key="suggestion"
              type="button"
              class="catalog-chip"
              @click="askAssistant(suggestion)"
            >
              {{ suggestion }}
            </button>

            <RouterLink to="/assistant" class="secondary-button">
              Открыть чат с Митей
            </RouterLink>
          </div>

          <div v-if="assistantError" class="message-error mt-5">
            {{ assistantError }}
          </div>
        </div>

        <div class="rounded-[2rem] border border-slate-200/80 bg-slate-50/75 p-5 shadow-[0_24px_70px_-55px_rgba(15,23,42,0.25)]">
          <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-700">
            Как это работает
          </p>
          <div class="mt-4 space-y-4 text-sm leading-7 text-slate-500">
            <p>Сначала сервис берет живые карточки событий и поведенческие сигналы витрины.</p>
            <p>Потом Митя объясняет, почему именно эти события подходят под твой сценарий.</p>
            <p>В ответе сразу появляются готовые карточки, так что можно без лишнего поиска перейти к покупке билета.</p>
          </div>
        </div>
      </div>

      <div v-if="assistantLoading" class="mt-7 grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
        <article
          v-for="item in 4"
          :key="`assistant-skeleton-${item}`"
          class="overflow-hidden rounded-[1.8rem] border border-slate-200/80 bg-white shadow-[0_24px_70px_-55px_rgba(15,23,42,0.35)]"
        >
          <div class="h-44 animate-pulse bg-slate-200"></div>
          <div class="space-y-3 p-5">
            <div class="h-4 w-28 animate-pulse rounded-full bg-slate-100"></div>
            <div class="h-7 w-3/4 animate-pulse rounded-full bg-slate-200"></div>
            <div class="h-4 w-full animate-pulse rounded-full bg-slate-100"></div>
            <div class="h-4 w-2/3 animate-pulse rounded-full bg-slate-100"></div>
          </div>
        </article>
      </div>

      <div v-else-if="assistantResponse" class="mt-7 space-y-6">
        <div class="rounded-[2rem] border border-blue-100 bg-[linear-gradient(145deg,rgba(37,99,235,0.08),rgba(15,23,42,0.02))] p-5 sm:p-6">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="max-w-3xl">
              <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-700">
                Ответ Мити
              </p>
              <div
                class="markdown-content mt-3 text-base leading-8 text-slate-700 sm:text-lg"
                v-html="assistantAnswerHtml"
              ></div>
            </div>

            <div class="rounded-[1.3rem] border border-white/70 bg-white px-4 py-3 shadow-sm">
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                Контекст
              </p>
              <p class="mt-2 text-sm font-medium text-slate-700">
                {{ assistantContextLabel || 'живая подборка' }}
              </p>
            </div>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
          <RouterLink
            v-for="item in assistantResponse.items"
            :key="`assistant-item-${item.id}`"
            :to="`/events/${item.id}`"
            class="group overflow-hidden rounded-[1.8rem] border border-slate-200/80 bg-white shadow-[0_24px_70px_-55px_rgba(15,23,42,0.35)] transition duration-300 hover:-translate-y-1 hover:border-blue-200 hover:shadow-[0_35px_90px_-60px_rgba(37,99,235,0.32)]"
          >
            <div class="relative h-44 overflow-hidden bg-gradient-to-br from-slate-950 via-slate-900 to-blue-900">
              <img
                v-if="item.poster_url"
                :src="item.poster_url"
                :alt="item.title"
                class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]"
              />
              <div v-else class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(96,165,250,0.35),transparent_30%),linear-gradient(145deg,rgba(15,23,42,0.92),rgba(30,64,175,0.88))]"></div>

              <div class="absolute inset-x-0 top-0 flex items-start justify-between gap-3 p-4">
                <span class="rounded-full border border-white/15 bg-slate-950/55 px-3 py-1 text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-white/90 backdrop-blur">
                  {{ item.category_name || item.category }}
                </span>
                <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-white backdrop-blur">
                  {{ item.age_rating }}+
                </span>
              </div>
            </div>

            <div class="space-y-4 p-5">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-700">
                  {{ assistantResponse.mode === 'ai' ? 'Подборка Мити' : 'Резервный сценарий' }}
                </p>
                <h3 class="mt-2 text-xl font-semibold leading-tight text-slate-950">
                  {{ item.title }}
                </h3>
              </div>

              <p class="text-sm leading-6 text-slate-600">
                {{ item.assistant_note }}
              </p>

              <div class="space-y-2 rounded-[1.4rem] border border-slate-200/80 bg-slate-50/80 px-4 py-4">
                <div class="flex items-center justify-between gap-3">
                  <span class="text-sm text-slate-500">Ближайшая дата</span>
                  <span class="text-sm font-semibold text-slate-950">{{ formatDate(item.event_date) }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                  <span class="text-sm text-slate-500">Цена от</span>
                  <span class="text-sm font-semibold text-blue-700">{{ formatPrice(item.price) }}</span>
                </div>
                <p class="text-sm leading-6 text-slate-500">
                  {{ buildRecommendationLocation(item) }}
                </p>
              </div>

              <div class="flex items-center justify-between gap-4">
                <span class="text-sm text-slate-400">
                  {{ item.available_tickets ? `Доступно: ${item.available_tickets}` : 'Наличие уточняется' }}
                </span>
                <span class="text-sm font-semibold text-blue-700 transition duration-200 group-hover:text-blue-800">
                  Открыть
                </span>
              </div>
            </div>
          </RouterLink>
        </div>
      </div>
    </section>

    <section class="app-panel p-5 sm:p-6 lg:p-7">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-3xl">
          <span class="info-chip">Smart picks</span>
          <h2 class="mt-4 text-3xl font-semibold leading-tight text-slate-950 sm:text-[2.2rem]">
            {{ recommendationTitle }}
          </h2>
          <p class="mt-3 text-sm leading-7 text-slate-500 sm:text-base">
            {{ recommendationSubtitle }}
          </p>
        </div>

        <div class="rounded-[1.6rem] border border-blue-100 bg-blue-50/70 px-5 py-4">
          <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-700">
            Сейчас в подборке
          </p>
          <p class="mt-2 text-3xl font-semibold text-slate-950">
            {{ recommendations.length }}
          </p>
        </div>
      </div>

      <div v-if="recommendationsLoading" class="mt-7 grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
        <article
          v-for="item in 4"
          :key="item"
          class="overflow-hidden rounded-[1.8rem] border border-slate-200/80 bg-white shadow-[0_24px_70px_-55px_rgba(15,23,42,0.35)]"
        >
          <div class="h-44 animate-pulse bg-slate-200"></div>
          <div class="space-y-3 p-5">
            <div class="h-4 w-24 animate-pulse rounded-full bg-slate-100"></div>
            <div class="h-7 w-3/4 animate-pulse rounded-full bg-slate-200"></div>
            <div class="h-4 w-full animate-pulse rounded-full bg-slate-100"></div>
            <div class="h-4 w-2/3 animate-pulse rounded-full bg-slate-100"></div>
          </div>
        </article>
      </div>

      <div v-else-if="recommendations.length > 0" class="mt-7 grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
        <RouterLink
          v-for="item in recommendations"
          :key="item.id"
          :to="`/events/${item.id}`"
          class="group overflow-hidden rounded-[1.8rem] border border-slate-200/80 bg-white shadow-[0_24px_70px_-55px_rgba(15,23,42,0.35)] transition duration-300 hover:-translate-y-1 hover:border-blue-200 hover:shadow-[0_35px_90px_-60px_rgba(37,99,235,0.32)]"
        >
          <div
            class="relative h-44 overflow-hidden bg-gradient-to-br from-slate-950 via-slate-900 to-blue-900"
          >
            <img
              v-if="item.poster_url"
              :src="item.poster_url"
              :alt="item.title"
              class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]"
            />
            <div v-else class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(96,165,250,0.35),transparent_30%),linear-gradient(145deg,rgba(15,23,42,0.92),rgba(30,64,175,0.88))]"></div>

            <div class="absolute inset-x-0 top-0 flex items-start justify-between gap-3 p-4">
              <span class="rounded-full border border-white/15 bg-slate-950/55 px-3 py-1 text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-white/90 backdrop-blur">
                {{ item.category_name || item.category }}
              </span>
              <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-white backdrop-blur">
                {{ item.age_rating }}+
              </span>
            </div>
          </div>

          <div class="space-y-4 p-5">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-700">
                {{ item.source === 'cold_start' ? 'Model preview' : 'Персональный сигнал' }}
              </p>
              <h3 class="mt-2 text-xl font-semibold leading-tight text-slate-950">
                {{ item.title }}
              </h3>
            </div>

            <p class="line-clamp-3 text-sm leading-6 text-slate-500">
              {{ item.description || 'Описание скоро появится в карточке события.' }}
            </p>

            <div class="flex flex-wrap gap-2">
              <span
                v-for="tag in recommendationTags(item)"
                :key="`${item.id}-${tag}`"
                class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[0.72rem] font-medium text-slate-500"
              >
                #{{ tag }}
              </span>
            </div>

            <div class="space-y-2 rounded-[1.4rem] border border-slate-200/80 bg-slate-50/80 px-4 py-4">
              <div class="flex items-center justify-between gap-3">
                <span class="text-sm text-slate-500">Ближайшая дата</span>
                <span class="text-sm font-semibold text-slate-950">{{ formatDate(item.event_date) }}</span>
              </div>
              <div class="flex items-center justify-between gap-3">
                <span class="text-sm text-slate-500">Цена от</span>
                <span class="text-sm font-semibold text-blue-700">{{ formatPrice(item.price) }}</span>
              </div>
              <p class="text-sm leading-6 text-slate-500">
                {{ buildRecommendationLocation(item) }}
              </p>
            </div>

            <div class="flex items-center justify-between gap-4">
              <span class="text-sm text-slate-400">
                {{ item.available_tickets ? `Доступно: ${item.available_tickets}` : 'Наличие уточняется' }}
              </span>
              <span class="text-sm font-semibold text-blue-700 transition duration-200 group-hover:text-blue-800">
                Открыть
              </span>
            </div>
          </div>
        </RouterLink>
      </div>

      <div
        v-else
        class="mt-7 rounded-[1.7rem] border border-dashed border-slate-200 bg-slate-50/75 px-5 py-6 text-sm leading-6 text-slate-500"
      >
        {{ recommendationsError || 'Рекомендации появятся, как только recommendation-service соберет достаточно живых данных.' }}
      </div>
    </section>

    <section id="catalog" class="app-panel p-5 sm:p-6 lg:p-7">
      <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-2xl">
          <span class="info-chip">Каталог</span>
          <h2 class="mt-4 text-3xl font-semibold leading-tight text-slate-950 sm:text-[2.35rem]">
            Выбирай событие, открывай карточку и переходи к живой схеме мест.
          </h2>
          <p class="mt-3 text-sm leading-7 text-slate-500 sm:text-base">
            Витрина заточена под обычного пользователя: меньше технического шума, больше понятных действий, чистая навигация и быстрый путь к бронированию.
          </p>
        </div>

        <div class="rounded-[1.6rem] border border-slate-200 bg-slate-50 px-5 py-4">
          <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">
            Найдено
          </p>
          <p class="mt-2 text-3xl font-semibold text-slate-950">
            {{ pagination.total }}
          </p>
        </div>
      </div>

      <form class="mt-7 grid gap-4 xl:grid-cols-[1.25fr_0.85fr_0.75fr_0.85fr_auto_auto]" @submit.prevent="applyFilters">
        <div>
          <label class="field-label" for="event-search">Поиск события</label>
          <input
            id="event-search"
            v-model="filters.search"
            type="text"
            class="field-input"
            placeholder="Рок, фестиваль, театр, stand-up"
          />
        </div>

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
          <label class="field-label" for="event-sort">Порядок</label>
          <select id="event-sort" v-model="filters.sort" class="field-input">
            <option v-for="option in sortOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>

        <button type="submit" class="primary-button mt-auto">
          Показать
        </button>

        <button type="button" class="secondary-button mt-auto" @click="resetFilters">
          Сбросить
        </button>
      </form>

      <div class="mt-5 flex flex-wrap items-center gap-2">
        <button
          type="button"
          class="catalog-chip"
          :class="filters.category === '' ? 'catalog-chip-active' : ''"
          @click="selectCategory('')"
        >
          Все события
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
      </div>
    </section>

    <div v-if="error" class="message-error">
      {{ error }}
    </div>

    <section v-if="loading" class="grid gap-6 lg:grid-cols-2 2xl:grid-cols-3">
      <article v-for="item in 6" :key="item" class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-[0_30px_80px_-55px_rgba(15,23,42,0.45)]">
        <div class="h-64 animate-pulse bg-slate-200"></div>
        <div class="space-y-4 p-6">
          <div class="h-4 w-28 animate-pulse rounded-full bg-slate-100"></div>
          <div class="h-8 w-3/4 animate-pulse rounded-full bg-slate-200"></div>
          <div class="h-4 w-full animate-pulse rounded-full bg-slate-100"></div>
          <div class="h-4 w-2/3 animate-pulse rounded-full bg-slate-100"></div>
        </div>
      </article>
    </section>

    <section v-else-if="events.length > 0" class="space-y-6">
      <div v-if="spotlightEvents.length > 0" class="grid gap-4 xl:grid-cols-3">
        <RouterLink
          v-for="event in spotlightEvents"
          :key="event.id"
          :to="`/events/${event.id}`"
          class="group rounded-[2rem] border border-slate-200/80 bg-white/88 p-5 shadow-[0_24px_70px_-55px_rgba(15,23,42,0.4)] transition duration-300 hover:-translate-y-1 hover:border-blue-200 hover:shadow-[0_35px_90px_-60px_rgba(37,99,235,0.45)]"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-600">
                {{ event.category?.name || 'Событие' }}
              </p>
              <h3 class="mt-3 text-2xl font-semibold leading-tight text-slate-950">
                {{ event.title }}
              </h3>
            </div>

            <span class="status-badge border-blue-100 bg-blue-50 text-blue-700">
              {{ event.age_rating?.label || '0+' }}
            </span>
          </div>

          <p class="mt-4 text-sm leading-6 text-slate-500">
            {{ buildEventCopy(event) }}
          </p>

          <div class="mt-5 flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500">
              {{ event.organizer?.display_name || event.organizer?.full_name || 'Организатор подключится позже' }}
            </span>
            <span class="text-sm font-semibold text-blue-700 transition duration-200 group-hover:text-blue-800">
              Открыть
            </span>
          </div>
        </RouterLink>
      </div>

      <div class="grid gap-6 lg:grid-cols-2 2xl:grid-cols-3">
        <article
          v-for="event in events"
          :key="event.id"
          class="group overflow-hidden rounded-[2.2rem] border border-slate-200/80 bg-white shadow-[0_30px_90px_-60px_rgba(15,23,42,0.42)] transition duration-300 hover:-translate-y-1.5 hover:border-blue-200 hover:shadow-[0_40px_110px_-60px_rgba(37,99,235,0.38)]"
        >
          <div class="relative h-72 overflow-hidden">
            <div
              v-if="event.poster_url"
              class="h-full w-full bg-cover bg-center transition duration-500 group-hover:scale-[1.04]"
              :style="{ backgroundImage: `linear-gradient(rgba(15, 23, 42, 0.06), rgba(15, 23, 42, 0.42)), url(${event.poster_url})` }"
            ></div>

            <div
              v-else
              class="flex h-full items-end bg-[linear-gradient(145deg,#0f172a_0%,#172554_60%,#2563eb_100%)] p-6 text-white"
            >
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-blue-100/80">
                  {{ event.category?.name || 'Событие' }}
                </p>
                <h3 class="mt-3 text-3xl font-semibold leading-tight">
                  {{ event.title }}
                </h3>
              </div>
            </div>

            <div class="absolute inset-x-0 top-0 flex items-center justify-between p-5">
              <span class="rounded-full border border-white/18 bg-slate-950/58 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-white backdrop-blur">
                {{ event.category?.name || 'Событие' }}
              </span>

              <span class="rounded-full border border-white/18 bg-white/14 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-white backdrop-blur">
                {{ event.age_rating?.label || '0+' }}
              </span>
            </div>

            <div class="absolute inset-x-0 bottom-0 p-5">
              <div class="rounded-[1.5rem] border border-white/12 bg-slate-950/56 px-4 py-4 backdrop-blur">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-blue-100/72">
                  Организатор
                </p>
                <p class="mt-2 text-sm font-semibold text-white">
                  {{ event.organizer?.display_name || event.organizer?.full_name || 'Имя появится после синхронизации профиля' }}
                </p>
              </div>
            </div>
          </div>

          <div class="space-y-5 p-6">
            <div>
              <h3 class="text-[1.65rem] font-semibold leading-tight text-slate-950">
                {{ event.title }}
              </h3>
              <p class="mt-3 text-sm leading-7 text-slate-500">
                {{ buildEventCopy(event) }}
              </p>
            </div>

            <div class="flex flex-wrap gap-2">
              <span class="status-badge border-slate-200 bg-slate-50 text-slate-600">
                Онлайн-бронь мест
              </span>
              <span class="status-badge border-blue-100 bg-blue-50 text-blue-700">
                Живые сеансы
              </span>
            </div>

            <div class="flex items-center justify-between gap-4">
              <div class="flex flex-wrap items-center gap-3">
                <RouterLink :to="`/events/${event.id}`" class="primary-button">
                  Купить билет
                </RouterLink>

                <WantToGoButton
                  v-if="!authStore.isOrganizer"
                  :active="event.is_wanted"
                  :loading="isWantToGoLoading(event.id)"
                  compact
                  @toggle="toggleWantToGo(event)"
                />
              </div>

              <div class="text-right">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                  Следующий шаг
                </p>
                <p class="mt-1 text-sm font-medium text-slate-700">
                  Выбрать сеанс
                </p>
              </div>
            </div>
          </div>
        </article>
      </div>
    </section>

    <section v-else class="app-panel p-8 sm:p-10">
      <span class="info-chip">Пока пусто</span>
      <h2 class="mt-4 text-3xl font-semibold text-slate-950">
        По текущим фильтрам событий не найдено
      </h2>
      <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-500 sm:text-base">
        Попробуй снять часть ограничений или сбросить поиск. В каталоге показываются только опубликованные события, поэтому пустой экран не выглядит как ошибка, а как честный результат фильтрации.
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


