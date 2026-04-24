<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { UpdateProfilePayload } from '@/types/auth'
import { formatDate, formatDateForInput, getInitials } from '@/utils/format'

const router = useRouter()
const authStore = useAuthStore()

const successMessage = ref('')

const form = reactive<UpdateProfilePayload>({
  full_name: '',
  phone: '',
  birth_date: '',
})

const user = computed(() => authStore.user)
const initials = computed(() => getInitials(user.value?.full_name))
const roleLabel = computed(() => (authStore.isOrganizer ? 'Организатор' : 'Пользователь'))
const statusLabel = computed(() => (user.value?.status === 1 ? 'Активен' : 'Ограничен'))
const memberSinceLabel = computed(() => formatDate(user.value?.created_at))

const statusClasses = computed(() => {
  return user.value?.status === 1
    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
    : 'border-amber-200 bg-amber-50 text-amber-700'
})

const hasChanges = computed(() => {
  if (!user.value) return false

  return (
    form.full_name !== user.value.full_name ||
    form.phone !== user.value.phone ||
    form.birth_date !== formatDateForInput(user.value.birth_date)
  )
})

const syncForm = () => {
  if (!authStore.user) return

  form.full_name = authStore.user.full_name
  form.phone = authStore.user.phone
  form.birth_date = formatDateForInput(authStore.user.birth_date)
}

const loadProfile = async () => {
  const currentUser = await authStore.fetchMe()

  if (!currentUser) {
    router.push('/login')
    return
  }

  syncForm()
}

const updateProfile = async () => {
  successMessage.value = ''

  try {
    const response = await authStore.updateProfile({ ...form })
    successMessage.value = response.message
    syncForm()
  } catch (error) {
    console.error(error)
  }
}

watch(
  () => authStore.user,
  () => {
    syncForm()
  },
)

onMounted(loadProfile)
</script>

<template>
  <section v-if="authStore.loading && !user" class="app-panel p-8 sm:p-10">
    <div class="flex items-center gap-4">
      <div class="h-16 w-16 animate-pulse rounded-[1.5rem] bg-slate-200"></div>
      <div class="space-y-3">
        <div class="h-4 w-40 animate-pulse rounded-full bg-slate-200"></div>
        <div class="h-4 w-60 animate-pulse rounded-full bg-slate-100"></div>
      </div>
    </div>
    <p class="mt-6 text-sm text-slate-500">Загружаем данные профиля...</p>
  </section>

  <div v-else class="grid gap-6 xl:grid-cols-[340px_1fr]">
    <aside class="app-panel overflow-hidden">
      <div class="bg-gradient-to-br from-slate-950 via-slate-900 to-sky-900 px-8 py-8 text-white">
        <div class="flex items-start justify-between gap-4">
          <div class="flex h-20 w-20 items-center justify-center rounded-[1.75rem] bg-white/10 text-2xl font-semibold">
            {{ initials }}
          </div>

          <div class="status-badge border-white/15 bg-white/10 text-white/85">
            {{ statusLabel }}
          </div>
        </div>

        <h2 class="mt-6 text-3xl font-semibold leading-tight">
          {{ user?.full_name || 'Пользователь' }}
        </h2>
        <p class="mt-2 text-sm leading-6 text-white/70">
          {{ user?.email || 'Email не указан' }}
        </p>
      </div>

      <div class="space-y-4 p-6">
        <article class="soft-card">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Роль</p>
          <p class="mt-3 text-lg font-semibold text-slate-900">{{ roleLabel }}</p>
        </article>

        <article class="soft-card">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Статус</p>
          <div class="status-badge mt-3" :class="statusClasses">
            {{ statusLabel }}
          </div>
        </article>

        <article class="soft-card">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">В системе с</p>
          <p class="mt-3 text-sm leading-6 text-slate-600">{{ memberSinceLabel }}</p>
        </article>

        <RouterLink
          v-if="authStore.isOrganizer"
          to="/organizer/events"
          class="secondary-button w-full"
        >
          Открыть кабинет организатора
        </RouterLink>

        <RouterLink v-else to="/events" class="secondary-button w-full">
          Перейти в афишу
        </RouterLink>
      </div>
    </aside>

    <section class="app-panel p-8 sm:p-10">
      <div class="flex flex-col gap-6 border-b border-slate-200/70 pb-8 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-2xl">
          <span class="info-chip">Личный кабинет</span>
          <h2 class="mt-4 text-3xl font-semibold leading-tight text-slate-950">
            Профиль пользователя, связанный с auth-service
          </h2>
          <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
            Здесь можно обновить базовые данные аккаунта и быстро проверить роль, статус и дату
            регистрации.
          </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
          <article class="rounded-2xl border border-slate-200 bg-white/85 px-4 py-4 shadow-sm shadow-slate-900/5">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Email</p>
            <p class="mt-2 text-sm font-semibold text-slate-900">
              {{ user?.email || 'Не указан' }}
            </p>
          </article>

          <article class="rounded-2xl border border-slate-200 bg-white/85 px-4 py-4 shadow-sm shadow-slate-900/5">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
              Дата рождения
            </p>
            <p class="mt-2 text-sm font-semibold text-slate-900">
              {{ formatDate(user?.birth_date) }}
            </p>
          </article>

          <article class="rounded-2xl border border-slate-200 bg-white/85 px-4 py-4 shadow-sm shadow-slate-900/5">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Роль</p>
            <p class="mt-2 text-sm font-semibold text-slate-900">{{ roleLabel }}</p>
          </article>
        </div>
      </div>

      <div v-if="successMessage" class="message-success mt-6">
        {{ successMessage }}
      </div>

      <div v-if="authStore.error" class="message-error mt-6">
        {{ authStore.error }}
      </div>

      <form class="mt-8 grid gap-5 sm:grid-cols-2" @submit.prevent="updateProfile">
        <div class="sm:col-span-2">
          <label class="field-label" for="profile-full-name">ФИО</label>
          <input
            id="profile-full-name"
            v-model="form.full_name"
            type="text"
            autocomplete="name"
            class="field-input"
            placeholder="Иванов Иван Иванович"
          />
        </div>

        <div>
          <label class="field-label" for="profile-phone">Телефон</label>
          <input
            id="profile-phone"
            v-model="form.phone"
            type="tel"
            autocomplete="tel"
            class="field-input"
            placeholder="+7 999 123-45-67"
          />
        </div>

        <div>
          <label class="field-label" for="profile-birth-date">Дата рождения</label>
          <input
            id="profile-birth-date"
            v-model="form.birth_date"
            type="date"
            class="field-input"
          />
        </div>

        <div class="sm:col-span-2 flex flex-col gap-4 pt-2 lg:flex-row lg:items-center lg:justify-between">
          <p class="text-sm" :class="hasChanges ? 'text-amber-700' : 'text-slate-500'">
            {{
              hasChanges
                ? 'Есть несохраненные изменения.'
                : 'Форма синхронизирована с текущими данными профиля.'
            }}
          </p>

          <button
            :disabled="authStore.loading || !hasChanges"
            type="submit"
            class="primary-button w-full lg:w-auto lg:min-w-64"
          >
            {{ authStore.loading ? 'Сохраняем...' : 'Сохранить изменения' }}
          </button>
        </div>
      </form>
    </section>
  </div>
</template>
