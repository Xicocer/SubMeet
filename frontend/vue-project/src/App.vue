<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getInitials } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const navLinks = computed(() => {
  const links = [{ to: '/events', label: 'Афиша' }]

  if (authStore.isOrganizer) {
    links.push({ to: '/organizer/events', label: 'Кабинет организатора' })
  }

  if (authStore.isAuthenticated) {
    links.push({ to: '/profile', label: 'Профиль' })
  } else {
    links.push({ to: '/login', label: 'Вход' })
    links.push({ to: '/register', label: 'Регистрация' })
  }

  return links
})

const pageEyebrow = computed(() => {
  if (route.name === 'organizer-events') return 'Organizer Workspace'
  if (route.name === 'profile') return 'Account Center'
  if (route.name === 'event-details') return 'Event Details'
  if (route.name === 'login' || route.name === 'register') return 'Auth Service'

  return 'Public Event Service'
})

const pageTitle = computed(() => {
  if (route.name === 'organizer-events') {
    return 'Управление событиями и сеансами в одном рабочем пространстве'
  }

  if (route.name === 'profile') {
    return 'Личный кабинет с быстрым доступом к профилю и ролям'
  }

  if (route.name === 'event-details') {
    return 'Карточка события с живыми сеансами и данными организатора'
  }

  if (route.name === 'login' || route.name === 'register') {
    return 'Авторизация и профиль в связке с микросервисной архитектурой'
  }

  return 'Публичная афиша мероприятий с фильтрами, поиском и рабочей панелью организатора'
})

const pageDescription = computed(() => {
  if (route.name === 'organizer-events') {
    return 'Здесь можно создавать мероприятия, публиковать их, управлять статусами и сразу вести расписание сеансов.'
  }

  if (route.name === 'profile') {
    return 'Профиль подтягивается из auth-service, а доступ к organizer-разделу зависит от роли пользователя.'
  }

  if (route.name === 'event-details') {
    return 'Карточка события показывает данные из event-service, а имя организатора может приходить из локальной RabbitMQ-проекции.'
  }

  if (route.name === 'login' || route.name === 'register') {
    return 'Фронтенд работает поверх auth-service и event-service отдельно, как и должно быть в твоем дипломном проекте.'
  }

  return 'Каталог построен вокруг публичных контроллеров, а organizer-панель уже подключена к защищенным API для создания и редактирования событий.'
})

const userLabel = computed(() => authStore.user?.full_name || 'Гость')
const roleLabel = computed(() => {
  if (!authStore.isAuthenticated) return 'Публичный режим'
  if (authStore.isOrganizer) return 'Организатор'
  return 'Пользователь'
})

const logout = async () => {
  await authStore.logout()

  if (route.meta.requiresAuth) {
    router.push('/events')
  }
}

onMounted(async () => {
  if (authStore.token && !authStore.user) {
    await authStore.fetchMe()
  }
})
</script>

<template>
  <div class="relative overflow-hidden">
    <div class="aurora aurora-left"></div>
    <div class="aurora aurora-right"></div>
    <div class="aurora aurora-bottom"></div>

    <div class="relative mx-auto flex min-h-screen max-w-7xl flex-col px-4 py-5 sm:px-6 lg:px-8 lg:py-8">
      <header class="app-panel px-6 py-5 sm:px-8">
        <div class="flex flex-col gap-6 xl:flex-row xl:items-center xl:justify-between">
          <div class="max-w-3xl">
            <span class="info-chip">{{ pageEyebrow }}</span>
            <h1 class="mt-4 text-3xl font-semibold leading-tight text-slate-950 sm:text-4xl">
              {{ pageTitle }}
            </h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
              {{ pageDescription }}
            </p>
          </div>

          <div class="flex flex-col gap-4 xl:items-end">
            <nav class="flex flex-wrap items-center gap-3 text-sm">
              <RouterLink
                v-for="link in navLinks"
                :key="link.to"
                :to="link.to"
                class="nav-pill"
                active-class="nav-pill-active"
              >
                {{ link.label }}
              </RouterLink>
            </nav>

            <div class="flex flex-wrap items-center gap-3">
              <div class="rounded-2xl border border-white/80 bg-white/80 px-4 py-3 shadow-sm shadow-slate-900/5">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                  Режим
                </p>
                <div class="mt-2 flex items-center gap-3">
                  <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-900 text-sm font-semibold text-white">
                    {{ getInitials(userLabel) }}
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-slate-900">{{ userLabel }}</p>
                    <p class="text-xs text-slate-500">{{ roleLabel }}</p>
                  </div>
                </div>
              </div>

              <button
                v-if="authStore.isAuthenticated"
                type="button"
                class="secondary-button"
                @click="logout"
              >
                Выйти
              </button>
            </div>
          </div>
        </div>
      </header>

      <main class="mt-6 flex-1">
        <RouterView />
      </main>
    </div>
  </div>
</template>
