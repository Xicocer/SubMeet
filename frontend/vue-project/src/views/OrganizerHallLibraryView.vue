<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { archiveOrganizerHallRequest, getOrganizerHallsRequest } from '@/api/halls'
import type { HallStatus, HallSummary } from '@/types/hall'
import { formatDate } from '@/utils/format'

const router = useRouter()

const halls = ref<HallSummary[]>([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const success = ref('')

const filters = reactive({
  search: '',
  status: '' as HallStatus | '',
})

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  total: 0,
  per_page: 9,
})

const draftCount = computed(() => halls.value.filter((hall) => hall.status === 'draft').length)
const activeCount = computed(() => halls.value.filter((hall) => hall.status === 'active').length)

const hallStatusLabel = (status: HallStatus) => {
  switch (status) {
    case 'active':
      return 'Активен'
    case 'archived':
      return 'Архив'
    default:
      return 'Черновик'
  }
}

const hallStatusClasses = (status: HallStatus) => {
  switch (status) {
    case 'active':
      return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    case 'archived':
      return 'border-slate-200 bg-slate-100 text-slate-700'
    default:
      return 'border-amber-200 bg-amber-50 text-amber-700'
  }
}

const extractErrorMessage = (requestError: any, fallback: string) => {
  const validationErrors = requestError?.response?.data?.errors

  if (validationErrors && typeof validationErrors === 'object') {
    const firstField = Object.values(validationErrors)[0]

    if (Array.isArray(firstField) && firstField.length > 0) {
      return String(firstField[0])
    }
  }

  return requestError?.response?.data?.message || fallback
}

const loadHalls = async (page = 1) => {
  loading.value = true
  error.value = ''

  try {
    const response = await getOrganizerHallsRequest({
      page,
      per_page: pagination.per_page,
      search: filters.search.trim() || undefined,
      status: filters.status || undefined,
    })

    halls.value = response.data
    pagination.current_page = response.current_page
    pagination.last_page = response.last_page
    pagination.total = response.total
  } catch (requestError) {
    console.error(requestError)
    error.value = extractErrorMessage(requestError, 'Не удалось загрузить список залов.')
  } finally {
    loading.value = false
  }
}

const goToCreate = async () => {
  await router.push({ name: 'organizer-hall-create' })
}

const goToEdit = async (hallId: number) => {
  await router.push({ name: 'organizer-hall-edit', params: { id: hallId } })
}

const archiveHall = async (hall: HallSummary) => {
  const confirmed = window.confirm(`Отправить зал "${hall.name}" в архив?`)

  if (!confirmed) {
    return
  }

  saving.value = true
  error.value = ''
  success.value = ''

  try {
    const response = await archiveOrganizerHallRequest(hall.id)
    success.value = response.message
    await loadHalls(pagination.current_page)
  } catch (requestError) {
    console.error(requestError)
    error.value = extractErrorMessage(requestError, 'Не удалось отправить зал в архив.')
  } finally {
    saving.value = false
  }
}

const changePage = async (page: number) => {
  if (page < 1 || page > pagination.last_page) {
    return
  }

  await loadHalls(page)
}

onMounted(async () => {
  await loadHalls()
})
</script>

<template>
  <div class="space-y-6">
    <section class="app-panel p-8 sm:p-10">
      <div class="flex flex-col gap-8 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
          <span class="info-chip">Hall Service</span>
          <h2 class="mt-4 text-3xl font-semibold leading-tight text-slate-950">
            Библиотека залов и быстрый вход в полноразмерный редактор
          </h2>
          <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
            Здесь хранятся все схемы залов организатора. Сам конструктор теперь вынесен на отдельную страницу, чтобы canvas не сжимался и не ломал рабочее пространство.
          </p>
        </div>

        <div class="flex flex-wrap gap-3">
          <RouterLink to="/profile" class="secondary-button">
            В профиль
          </RouterLink>

          <button type="button" class="primary-button" @click="goToCreate">
            Создать новый зал
          </button>
        </div>
      </div>
    </section>

    <div v-if="success" class="message-success">
      {{ success }}
    </div>

    <div v-if="error" class="message-error">
      {{ error }}
    </div>

    <section class="app-panel p-8">
      <div class="grid gap-4 lg:grid-cols-[1.15fr_0.95fr_auto]">
        <div>
          <label class="field-label" for="hall-search">Поиск по названию</label>
          <input
            id="hall-search"
            v-model="filters.search"
            type="text"
            class="field-input"
            placeholder="Например, главная арена или малая сцена"
          />
        </div>

        <div>
          <label class="field-label" for="hall-status-filter">Статус</label>
          <select id="hall-status-filter" v-model="filters.status" class="field-input">
            <option value="">Все статусы</option>
            <option value="draft">Черновики</option>
            <option value="active">Активные</option>
            <option value="archived">Архив</option>
          </select>
        </div>

        <button type="button" class="secondary-button mt-auto" @click="loadHalls(1)">
          Обновить список
        </button>
      </div>

      <div class="mt-6 grid gap-3 sm:grid-cols-3">
        <article class="rounded-2xl border border-slate-200 bg-white/85 px-4 py-4 shadow-sm shadow-slate-900/5">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Всего</p>
          <p class="mt-2 text-3xl font-semibold text-slate-950">{{ pagination.total }}</p>
        </article>

        <article class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 shadow-sm shadow-amber-900/5">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">Черновики</p>
          <p class="mt-2 text-3xl font-semibold text-amber-950">{{ draftCount }}</p>
        </article>

        <article class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 shadow-sm shadow-emerald-900/5">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">Активные</p>
          <p class="mt-2 text-3xl font-semibold text-emerald-950">{{ activeCount }}</p>
        </article>
      </div>
    </section>

    <section v-if="loading" class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
      <article v-for="item in 6" :key="item" class="app-panel p-6">
        <div class="h-4 w-28 animate-pulse rounded-full bg-slate-100"></div>
        <div class="mt-4 h-7 w-3/4 animate-pulse rounded-full bg-slate-200"></div>
        <div class="mt-6 h-24 animate-pulse rounded-[1.5rem] bg-slate-100"></div>
      </article>
    </section>

    <section v-else-if="halls.length > 0" class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="hall in halls"
        :key="hall.id"
        class="app-panel p-6"
      >
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
              обновлен {{ formatDate(hall.updated_at) }}
            </p>
            <h3 class="mt-2 text-2xl font-semibold leading-tight text-slate-950">
              {{ hall.name }}
            </h3>
          </div>

          <span class="status-badge" :class="hallStatusClasses(hall.status)">
            {{ hallStatusLabel(hall.status) }}
          </span>
        </div>

        <p class="mt-4 text-sm leading-6 text-slate-500">
          {{ hall.description || 'Описание пока не добавлено. Его можно уточнить уже в отдельной странице редактора.' }}
        </p>

        <p class="mt-3 text-sm font-medium text-slate-700">
          {{ hall.address || 'Адрес пока не указан' }}
        </p>

        <div class="mt-5 grid grid-cols-2 gap-3">
          <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Места</p>
            <p class="mt-2 text-lg font-semibold text-slate-950">{{ hall.capacities.seat }}</p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">VIP</p>
            <p class="mt-2 text-lg font-semibold text-slate-950">{{ hall.capacities.vip }}</p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Танцпол</p>
            <p class="mt-2 text-lg font-semibold text-slate-950">{{ hall.capacities.dancefloor }}</p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Всего</p>
            <p class="mt-2 text-lg font-semibold text-slate-950">{{ hall.capacities.total }}</p>
          </div>
        </div>

        <div class="mt-5 rounded-[1.5rem] border border-slate-200 bg-slate-50/80 px-4 py-4 text-sm leading-6 text-slate-600">
          <p>Уровней: {{ hall.layout_meta.levels_count }}</p>
          <p>Элементов: {{ hall.layout_meta.elements_count }}</p>
          <p>{{ hall.layout_meta.has_dancefloor ? 'Танцпол добавлен' : 'Танцпола пока нет' }}</p>
        </div>

        <div class="mt-6 flex flex-col gap-3">
          <button type="button" class="primary-button" @click="goToEdit(hall.id)">
            Открыть редактор
          </button>

          <button
            type="button"
            class="secondary-button"
            :disabled="saving"
            @click="archiveHall(hall)"
          >
            В архив
          </button>
        </div>
      </article>
    </section>

    <section v-else class="app-panel p-8 sm:p-10">
      <span class="info-chip">Пока пусто</span>
      <h2 class="mt-4 text-3xl font-semibold text-slate-950">Залов еще нет</h2>
      <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500 sm:text-base">
        Начни с первого зала. После этого его можно будет открыть в отдельном editor-view и использовать в расписании сеансов.
      </p>
      <button type="button" class="primary-button mt-6" @click="goToCreate">
        Создать зал
      </button>
    </section>

    <section v-if="pagination.last_page > 1" class="app-panel p-6">
      <div class="flex items-center justify-between gap-4">
        <button
          type="button"
          class="secondary-button"
          :disabled="pagination.current_page === 1"
          @click="changePage(pagination.current_page - 1)"
        >
          Назад
        </button>

        <div class="text-sm text-slate-500">
          {{ pagination.current_page }} / {{ pagination.last_page }}
        </div>

        <button
          type="button"
          class="secondary-button"
          :disabled="pagination.current_page === pagination.last_page"
          @click="changePage(pagination.current_page + 1)"
        >
          Дальше
        </button>
      </div>
    </section>
  </div>
</template>
