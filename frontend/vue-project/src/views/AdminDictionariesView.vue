<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import {
  createAdminAgeRatingRequest,
  createAdminCategoryRequest,
  createAdminTagRequest,
  deleteAdminAgeRatingRequest,
  deleteAdminCategoryRequest,
  deleteAdminTagRequest,
  getAdminAgeRatingsRequest,
  getAdminCategoriesRequest,
  getAdminTagsRequest,
  updateAdminAgeRatingRequest,
  updateAdminCategoryRequest,
  updateAdminTagRequest,
} from '@/api/admin'
import type { AgeRating, Category, EventTag } from '@/types/event'

type CategoryDraft = {
  name: string
  slug: string
}

type AgeRatingDraft = {
  label: string
  min_age: number
}

type TagDraft = {
  name: string
  slug: string
}

const categories = ref<Category[]>([])
const ageRatings = ref<AgeRating[]>([])
const tags = ref<EventTag[]>([])

const loading = ref(false)
const actionKey = ref('')
const error = ref('')
const feedback = ref('')

const categorySearch = ref('')
const ageRatingSearch = ref('')
const tagSearch = ref('')

const newCategory = reactive<CategoryDraft>({
  name: '',
  slug: '',
})

const newAgeRating = reactive<AgeRatingDraft>({
  label: '',
  min_age: 0,
})

const newTag = reactive<TagDraft>({
  name: '',
  slug: '',
})

const categoryDrafts = ref<Record<number, CategoryDraft>>({})
const ageRatingDrafts = ref<Record<number, AgeRatingDraft>>({})
const tagDrafts = ref<Record<number, TagDraft>>({})

const filteredCategories = computed(() => {
  const search = categorySearch.value.trim().toLowerCase()

  if (!search) {
    return categories.value
  }

  return categories.value.filter((item) => {
    return [item.name, item.slug].some((value) => value.toLowerCase().includes(search))
  })
})

const filteredAgeRatings = computed(() => {
  const search = ageRatingSearch.value.trim().toLowerCase()

  if (!search) {
    return ageRatings.value
  }

  return ageRatings.value.filter((item) => {
    return item.label.toLowerCase().includes(search) || String(item.min_age).includes(search)
  })
})

const filteredTags = computed(() => {
  const search = tagSearch.value.trim().toLowerCase()

  if (!search) {
    return tags.value
  }

  return tags.value.filter((item) => {
    return [item.name, item.slug].some((value) => value.toLowerCase().includes(search))
  })
})

const syncCategoryDrafts = () => {
  categoryDrafts.value = Object.fromEntries(
    categories.value.map((item) => [
      item.id,
      {
        name: item.name,
        slug: item.slug,
      },
    ]),
  )
}

const syncAgeRatingDrafts = () => {
  ageRatingDrafts.value = Object.fromEntries(
    ageRatings.value.map((item) => [
      item.id,
      {
        label: item.label,
        min_age: item.min_age,
      },
    ]),
  )
}

const syncTagDrafts = () => {
  tagDrafts.value = Object.fromEntries(
    tags.value.map((item) => [
      item.id,
      {
        name: item.name,
        slug: item.slug,
      },
    ]),
  )
}

const getCategoryDraft = (item: Category): CategoryDraft | null => categoryDrafts.value[item.id] ?? null
const getAgeRatingDraft = (item: AgeRating): AgeRatingDraft | null => ageRatingDrafts.value[item.id] ?? null
const getTagDraft = (item: EventTag): TagDraft | null => tagDrafts.value[item.id] ?? null

const loadDictionaries = async () => {
  loading.value = true
  error.value = ''

  try {
    const [categoriesResponse, ageRatingsResponse, tagsResponse] = await Promise.all([
      getAdminCategoriesRequest(),
      getAdminAgeRatingsRequest(),
      getAdminTagsRequest(),
    ])

    categories.value = categoriesResponse
    ageRatings.value = ageRatingsResponse
    tags.value = tagsResponse
    syncCategoryDrafts()
    syncAgeRatingDrafts()
    syncTagDrafts()
  } catch (requestError: any) {
    console.error(requestError)
    error.value = requestError?.response?.data?.message || 'Не удалось загрузить справочники.'
  } finally {
    loading.value = false
  }
}

const createCategory = async () => {
  actionKey.value = 'create-category'
  feedback.value = ''
  error.value = ''

  try {
    await createAdminCategoryRequest({
      name: newCategory.name,
      slug: newCategory.slug || null,
    })

    newCategory.name = ''
    newCategory.slug = ''
    feedback.value = 'Категория создана.'
    await loadDictionaries()
  } catch (requestError: any) {
    console.error(requestError)
    error.value = requestError?.response?.data?.message || 'Не удалось создать категорию.'
  } finally {
    actionKey.value = ''
  }
}

const saveCategory = async (item: Category) => {
  const draft = getCategoryDraft(item)

  if (!draft) {
    return
  }

  actionKey.value = `category-${item.id}`
  feedback.value = ''
  error.value = ''

  try {
    await updateAdminCategoryRequest(item.id, {
      name: draft.name,
      slug: draft.slug || null,
    })

    feedback.value = 'Категория обновлена.'
    await loadDictionaries()
  } catch (requestError: any) {
    console.error(requestError)
    error.value = requestError?.response?.data?.message || 'Не удалось обновить категорию.'
  } finally {
    actionKey.value = ''
  }
}

const removeCategory = async (item: Category) => {
  actionKey.value = `delete-category-${item.id}`
  feedback.value = ''
  error.value = ''

  try {
    await deleteAdminCategoryRequest(item.id)
    feedback.value = 'Категория удалена.'
    await loadDictionaries()
  } catch (requestError: any) {
    console.error(requestError)
    error.value = requestError?.response?.data?.message || 'Не удалось удалить категорию.'
  } finally {
    actionKey.value = ''
  }
}

const createAgeRating = async () => {
  actionKey.value = 'create-age-rating'
  feedback.value = ''
  error.value = ''

  try {
    await createAdminAgeRatingRequest({
      label: newAgeRating.label,
      min_age: Number(newAgeRating.min_age),
    })

    newAgeRating.label = ''
    newAgeRating.min_age = 0
    feedback.value = 'Возрастной рейтинг создан.'
    await loadDictionaries()
  } catch (requestError: any) {
    console.error(requestError)
    error.value = requestError?.response?.data?.message || 'Не удалось создать возрастной рейтинг.'
  } finally {
    actionKey.value = ''
  }
}

const saveAgeRating = async (item: AgeRating) => {
  const draft = getAgeRatingDraft(item)

  if (!draft) {
    return
  }

  actionKey.value = `age-rating-${item.id}`
  feedback.value = ''
  error.value = ''

  try {
    await updateAdminAgeRatingRequest(item.id, {
      label: draft.label,
      min_age: Number(draft.min_age),
    })

    feedback.value = 'Возрастной рейтинг обновлен.'
    await loadDictionaries()
  } catch (requestError: any) {
    console.error(requestError)
    error.value = requestError?.response?.data?.message || 'Не удалось обновить возрастной рейтинг.'
  } finally {
    actionKey.value = ''
  }
}

const removeAgeRating = async (item: AgeRating) => {
  actionKey.value = `delete-age-rating-${item.id}`
  feedback.value = ''
  error.value = ''

  try {
    await deleteAdminAgeRatingRequest(item.id)
    feedback.value = 'Возрастной рейтинг удален.'
    await loadDictionaries()
  } catch (requestError: any) {
    console.error(requestError)
    error.value = requestError?.response?.data?.message || 'Не удалось удалить возрастной рейтинг.'
  } finally {
    actionKey.value = ''
  }
}

const createTag = async () => {
  actionKey.value = 'create-tag'
  feedback.value = ''
  error.value = ''

  try {
    await createAdminTagRequest({
      name: newTag.name,
      slug: newTag.slug || null,
    })

    newTag.name = ''
    newTag.slug = ''
    feedback.value = 'Тег создан.'
    await loadDictionaries()
  } catch (requestError: any) {
    console.error(requestError)
    error.value = requestError?.response?.data?.message || 'Не удалось создать тег.'
  } finally {
    actionKey.value = ''
  }
}

const saveTag = async (item: EventTag) => {
  const draft = getTagDraft(item)

  if (!draft) {
    return
  }

  actionKey.value = `tag-${item.id}`
  feedback.value = ''
  error.value = ''

  try {
    await updateAdminTagRequest(item.id, {
      name: draft.name,
      slug: draft.slug || null,
    })

    feedback.value = 'Тег обновлен.'
    await loadDictionaries()
  } catch (requestError: any) {
    console.error(requestError)
    error.value = requestError?.response?.data?.message || 'Не удалось обновить тег.'
  } finally {
    actionKey.value = ''
  }
}

const removeTag = async (item: EventTag) => {
  actionKey.value = `delete-tag-${item.id}`
  feedback.value = ''
  error.value = ''

  try {
    await deleteAdminTagRequest(item.id)
    feedback.value = 'Тег удален.'
    await loadDictionaries()
  } catch (requestError: any) {
    console.error(requestError)
    error.value = requestError?.response?.data?.message || 'Не удалось удалить тег.'
  } finally {
    actionKey.value = ''
  }
}

onMounted(loadDictionaries)
</script>

<template>
  <div class="space-y-6">
    <section class="app-panel relative overflow-hidden p-8 sm:p-10">
      <div class="absolute inset-x-0 top-0 h-40 bg-[radial-gradient(circle_at_top_left,rgba(37,99,235,0.18),transparent_48%),linear-gradient(120deg,rgba(15,23,42,0.06),transparent_55%)]"></div>
      <div class="relative flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
          <span class="info-chip">Dictionary Management</span>
          <h1 class="mt-4 text-3xl font-semibold leading-tight text-slate-950 sm:text-4xl">
            Справочники платформы
          </h1>
          <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
            Категории, возрастные рейтинги и теги управляются из одного экрана с быстрым поиском и локальной редактурой.
          </p>
        </div>

        <button type="button" class="secondary-button" @click="loadDictionaries">
          Обновить
        </button>
      </div>
    </section>

    <div v-if="feedback" class="rounded-[1.5rem] border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-700">
      {{ feedback }}
    </div>

    <div v-if="error" class="message-error">
      {{ error }}
    </div>

    <section v-if="loading" class="grid gap-6 xl:grid-cols-3">
      <article
        v-for="item in 3"
        :key="item"
        class="app-panel p-6"
      >
        <div class="h-4 w-28 animate-pulse rounded-full bg-slate-100"></div>
        <div class="mt-6 h-8 w-3/4 animate-pulse rounded-2xl bg-slate-200"></div>
        <div class="mt-4 h-4 w-1/2 animate-pulse rounded-full bg-slate-100"></div>
      </article>
    </section>

    <template v-else>
      <section class="grid gap-6 xl:grid-cols-3">
        <article class="app-panel p-7">
          <div class="flex items-center justify-between gap-4">
            <div>
              <span class="info-chip">Категории</span>
              <h2 class="mt-4 text-2xl font-semibold text-slate-950">
                Направления каталога
              </h2>
            </div>
            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-500">
              {{ filteredCategories.length }}
            </span>
          </div>

          <input
            v-model="categorySearch"
            type="text"
            class="mt-6 w-full rounded-[1.2rem] border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
            placeholder="Фильтр по названию или slug"
          />

          <div class="mt-4 space-y-3">
            <input
              v-model="newCategory.name"
              type="text"
              class="w-full rounded-[1.2rem] border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
              placeholder="Название категории"
            />
            <input
              v-model="newCategory.slug"
              type="text"
              class="w-full rounded-[1.2rem] border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
              placeholder="Slug"
            />
            <button
              type="button"
              class="primary-button w-full justify-center"
              :disabled="actionKey === 'create-category'"
              @click="createCategory"
            >
              {{ actionKey === 'create-category' ? 'Создаем...' : 'Создать категорию' }}
            </button>
          </div>

          <div v-if="filteredCategories.length" class="mt-6 space-y-4">
            <article
              v-for="category in filteredCategories"
              :key="category.id"
              class="rounded-[1.5rem] border border-slate-200 bg-slate-50/80 px-4 py-4"
            >
              <div v-if="getCategoryDraft(category)" class="space-y-3">
                <input
                  v-model="getCategoryDraft(category)!.name"
                  type="text"
                  class="w-full rounded-[1rem] border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                />
                <input
                  v-model="getCategoryDraft(category)!.slug"
                  type="text"
                  class="w-full rounded-[1rem] border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                />
                <div class="flex gap-3">
                  <button type="button" class="secondary-button flex-1 justify-center" @click="saveCategory(category)">
                    Сохранить
                  </button>
                  <button type="button" class="secondary-button flex-1 justify-center text-rose-600" @click="removeCategory(category)">
                    Удалить
                  </button>
                </div>
              </div>
            </article>
          </div>

          <div v-else class="mt-6 rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50/70 px-5 py-6 text-sm text-slate-500">
            По текущему фильтру категорий ничего не найдено.
          </div>
        </article>

        <article class="app-panel p-7">
          <div class="flex items-center justify-between gap-4">
            <div>
              <span class="info-chip">Возраст</span>
              <h2 class="mt-4 text-2xl font-semibold text-slate-950">
                Рейтинги допуска
              </h2>
            </div>
            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-500">
              {{ filteredAgeRatings.length }}
            </span>
          </div>

          <input
            v-model="ageRatingSearch"
            type="text"
            class="mt-6 w-full rounded-[1.2rem] border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
            placeholder="Фильтр по label или возрасту"
          />

          <div class="mt-4 space-y-3">
            <input
              v-model="newAgeRating.label"
              type="text"
              class="w-full rounded-[1.2rem] border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
              placeholder="Например 16+"
            />
            <input
              v-model.number="newAgeRating.min_age"
              type="number"
              min="0"
              max="21"
              class="w-full rounded-[1.2rem] border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
              placeholder="Минимальный возраст"
            />
            <button
              type="button"
              class="primary-button w-full justify-center"
              :disabled="actionKey === 'create-age-rating'"
              @click="createAgeRating"
            >
              {{ actionKey === 'create-age-rating' ? 'Создаем...' : 'Создать рейтинг' }}
            </button>
          </div>

          <div v-if="filteredAgeRatings.length" class="mt-6 space-y-4">
            <article
              v-for="rating in filteredAgeRatings"
              :key="rating.id"
              class="rounded-[1.5rem] border border-slate-200 bg-slate-50/80 px-4 py-4"
            >
              <div v-if="getAgeRatingDraft(rating)" class="space-y-3">
                <input
                  v-model="getAgeRatingDraft(rating)!.label"
                  type="text"
                  class="w-full rounded-[1rem] border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                />
                <input
                  v-model.number="getAgeRatingDraft(rating)!.min_age"
                  type="number"
                  min="0"
                  max="21"
                  class="w-full rounded-[1rem] border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                />
                <div class="flex gap-3">
                  <button type="button" class="secondary-button flex-1 justify-center" @click="saveAgeRating(rating)">
                    Сохранить
                  </button>
                  <button type="button" class="secondary-button flex-1 justify-center text-rose-600" @click="removeAgeRating(rating)">
                    Удалить
                  </button>
                </div>
              </div>
            </article>
          </div>

          <div v-else class="mt-6 rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50/70 px-5 py-6 text-sm text-slate-500">
            По текущему фильтру рейтингов ничего не найдено.
          </div>
        </article>

        <article class="app-panel p-7">
          <div class="flex items-center justify-between gap-4">
            <div>
              <span class="info-chip">Теги</span>
              <h2 class="mt-4 text-2xl font-semibold text-slate-950">
                Глобальные ключевые слова
              </h2>
            </div>
            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-500">
              {{ filteredTags.length }}
            </span>
          </div>

          <input
            v-model="tagSearch"
            type="text"
            class="mt-6 w-full rounded-[1.2rem] border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
            placeholder="Фильтр по названию или slug"
          />

          <div class="mt-4 space-y-3">
            <input
              v-model="newTag.name"
              type="text"
              class="w-full rounded-[1.2rem] border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
              placeholder="Название тега"
            />
            <input
              v-model="newTag.slug"
              type="text"
              class="w-full rounded-[1.2rem] border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
              placeholder="Slug"
            />
            <button
              type="button"
              class="primary-button w-full justify-center"
              :disabled="actionKey === 'create-tag'"
              @click="createTag"
            >
              {{ actionKey === 'create-tag' ? 'Создаем...' : 'Создать тег' }}
            </button>
          </div>

          <div v-if="filteredTags.length" class="mt-6 space-y-4">
            <article
              v-for="tag in filteredTags"
              :key="tag.id"
              class="rounded-[1.5rem] border border-slate-200 bg-slate-50/80 px-4 py-4"
            >
              <div v-if="getTagDraft(tag)" class="space-y-3">
                <input
                  v-model="getTagDraft(tag)!.name"
                  type="text"
                  class="w-full rounded-[1rem] border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                />
                <input
                  v-model="getTagDraft(tag)!.slug"
                  type="text"
                  class="w-full rounded-[1rem] border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                />
                <div class="flex gap-3">
                  <button type="button" class="secondary-button flex-1 justify-center" @click="saveTag(tag)">
                    Сохранить
                  </button>
                  <button type="button" class="secondary-button flex-1 justify-center text-rose-600" @click="removeTag(tag)">
                    Удалить
                  </button>
                </div>
              </div>
            </article>
          </div>

          <div v-else class="mt-6 rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50/70 px-5 py-6 text-sm text-slate-500">
            По текущему фильтру тегов ничего не найдено.
          </div>
        </article>
      </section>
    </template>
  </div>
</template>
