<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryValue } from 'vue-router'
import {
  getAgeRatingsRequest,
  getCategoriesRequest,
  getEventsRequest,
} from '@/api/events'
import type {
  AgeRating,
  Category,
  EventListFilters,
  EventSort,
  PaginatedResponse,
  PublicEvent,
} from '@/types/event'

const route = useRoute()
const router = useRouter()

const categories = ref<Category[]>([])
const ageRatings = ref<AgeRating[]>([])
const events = ref<PublicEvent[]>([])
const loading = ref(false)
const error = ref('')

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
  { value: 'oldest', label: 'Сначала старые' },
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
    error.value = 'Не удалось загрузить справочники для фильтров.'
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
    error.value = 'Не удалось загрузить список мероприятий.'
    events.value = []
  } finally {
    loading.value = false
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
  if (page < 1 || page > pagination.last_page) return

  await router.replace({
    name: 'events',
    query: buildQuery(page),
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

onMounted(loadLookups)
</script>

<template>
  <div class="space-y-6">
    <section class="app-panel p-8 sm:p-10">
      <div class="flex flex-col gap-8 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
          <span class="info-chip">Публичный каталог</span>
          <h2 class="mt-4 text-3xl font-semibold leading-tight text-slate-950">
            Афиша мероприятий с поиском, фильтрами и пагинацией
          </h2>
          <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
            Каталог использует `GET /api/events`, а категории и возрастные рейтинги подтягиваются из
            отдельных эндпоинтов для фильтров.
          </p>
        </div>

        <div class="rounded-[1.75rem] border border-white/85 bg-white/85 px-5 py-4 shadow-sm shadow-slate-900/5">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
            Найдено мероприятий
          </p>
          <p class="mt-2 text-3xl font-semibold text-slate-950">{{ pagination.total }}</p>
        </div>
      </div>

      <form class="mt-8 grid gap-4 lg:grid-cols-[1.2fr_0.9fr_0.9fr_0.9fr_auto_auto]" @submit.prevent="applyFilters">
        <div>
          <label class="field-label" for="event-search">Поиск по названию</label>
          <input
            id="event-search"
            v-model="filters.search"
            type="text"
            class="field-input"
            placeholder="Например, рок, театр или фестиваль"
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
          <label class="field-label" for="event-sort">Сортировка</label>
          <select id="event-sort" v-model="filters.sort" class="field-input">
            <option v-for="option in sortOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>

        <button type="submit" class="primary-button mt-auto">Применить</button>
        <button type="button" class="secondary-button mt-auto" @click="resetFilters">
          Сбросить
        </button>
      </form>
    </section>

    <div v-if="error" class="message-error">
      {{ error }}
    </div>

    <section v-if="loading" class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
      <article v-for="item in 6" :key="item" class="app-panel overflow-hidden">
        <div class="h-56 animate-pulse bg-slate-200"></div>
        <div class="space-y-4 p-6">
          <div class="h-4 w-28 animate-pulse rounded-full bg-slate-100"></div>
          <div class="h-7 w-3/4 animate-pulse rounded-full bg-slate-200"></div>
          <div class="h-4 w-full animate-pulse rounded-full bg-slate-100"></div>
          <div class="h-4 w-2/3 animate-pulse rounded-full bg-slate-100"></div>
        </div>
      </article>
    </section>

    <section v-else-if="events.length > 0" class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="event in events"
        :key="event.id"
        class="app-panel overflow-hidden transition duration-300 hover:-translate-y-1 hover:shadow-2xl hover:shadow-slate-900/15"
      >
        <div
          v-if="event.poster_url"
          class="h-56 bg-cover bg-center"
          :style="{ backgroundImage: `linear-gradient(rgba(15, 23, 42, 0.08), rgba(15, 23, 42, 0.35)), url(${event.poster_url})` }"
        ></div>
        <div
          v-else
          class="flex h-56 items-end bg-gradient-to-br from-slate-900 via-sky-900 to-emerald-700 p-6 text-white"
        >
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-white/70">
              {{ event.category?.name || 'Событие' }}
            </p>
            <h3 class="mt-3 text-3xl font-semibold leading-tight">{{ event.title }}</h3>
          </div>
        </div>

        <div class="space-y-5 p-6">
          <div class="flex flex-wrap gap-2">
            <span class="status-badge border-slate-200 bg-slate-100 text-slate-700">
              {{ event.category?.name || 'Без категории' }}
            </span>
            <span class="status-badge border-sky-200 bg-sky-50 text-sky-700">
              {{ event.age_rating?.label || 'Без рейтинга' }}
            </span>
          </div>

          <div>
            <h3 class="text-2xl font-semibold leading-tight text-slate-950">{{ event.title }}</h3>
            <p class="mt-3 text-sm leading-6 text-slate-500">
              Организатор:
              <span class="font-semibold text-slate-700">
                {{ event.organizer?.full_name || 'данные обновляются через RabbitMQ' }}
              </span>
            </p>
          </div>

          <div class="flex items-center justify-between gap-4">
            <RouterLink :to="`/events/${event.id}`" class="primary-button">
              Открыть карточку
            </RouterLink>

            <p class="text-right text-xs leading-5 text-slate-500">
              slug:
              <span class="font-semibold text-slate-700">{{ event.category?.slug || 'n/a' }}</span>
            </p>
          </div>
        </div>
      </article>
    </section>

    <section v-else class="app-panel p-8 sm:p-10">
      <span class="info-chip">Пока пусто</span>
      <h2 class="mt-4 text-3xl font-semibold text-slate-950">По текущим фильтрам ничего не найдено</h2>
      <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500 sm:text-base">
        Попробуй сбросить фильтры или изменить поисковый запрос. Публичный список показывает только
        опубликованные мероприятия.
      </p>
      <button type="button" class="secondary-button mt-6" @click="resetFilters">
        Вернуть все мероприятия
      </button>
    </section>

    <section v-if="pagination.last_page > 1" class="app-panel p-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-slate-500">
          Показаны
          <span class="font-semibold text-slate-900">{{ pagination.from ?? 0 }}-{{ pagination.to ?? 0 }}</span>
          из
          <span class="font-semibold text-slate-900">{{ pagination.total }}</span>
          мероприятий
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
            :class="page === pagination.current_page ? 'border-slate-900 bg-slate-900 text-white hover:bg-slate-900' : ''"
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
