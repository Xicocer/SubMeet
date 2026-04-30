<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getInitials } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const isImmersiveRoute = computed(() => route.meta.immersive === true)

const isCatalogLinkActive = computed(() => route.path === '/events' || route.path.startsWith('/events/'))
const isProfileLinkActive = computed(() => route.path.startsWith('/profile'))
const isOrganizerDashboardLinkActive = computed(() => route.path.startsWith('/organizer/dashboard'))
const isOrganizerEventsLinkActive = computed(() => route.path.startsWith('/organizer/events'))
const isOrganizerHallsLinkActive = computed(() => route.path.startsWith('/organizer/halls'))
const isOrganizerTicketsLinkActive = computed(() => route.path.startsWith('/organizer/tickets'))
const isLoginLinkActive = computed(() => route.path.startsWith('/login'))
const isRegisterLinkActive = computed(() => route.path.startsWith('/register'))

const userLabel = computed(() => {
  if (!authStore.user) {
    return 'Гость'
  }

  return authStore.user.organizer_profile?.company_name || authStore.user.full_name
})

const userInitials = computed(() => getInitials(userLabel.value))

const logout = async () => {
  await authStore.logout()

  if (route.meta.requiresAuth) {
    await router.push('/events')
  }
}

onMounted(async () => {
  if (authStore.token && !authStore.user) {
    await authStore.fetchMe()
  }
})
</script>

<template>
  <div class="relative min-h-screen overflow-hidden bg-[#f4f8fc]">
    <div class="pointer-events-none absolute inset-x-0 top-0 h-[34rem] bg-[radial-gradient(circle_at_top_left,rgba(37,99,235,0.24),transparent_24%),radial-gradient(circle_at_top_right,rgba(15,23,42,0.14),transparent_22%),linear-gradient(180deg,rgba(15,23,42,0.08),transparent)]"></div>
    <div class="pointer-events-none absolute inset-x-0 top-0 h-64 bg-[linear-gradient(120deg,rgba(255,255,255,0.52),transparent_45%,rgba(37,99,235,0.08)_100%)]"></div>

    <div
      class="relative mx-auto flex min-h-screen flex-col"
      :class="isImmersiveRoute ? 'max-w-none px-2 py-2 sm:px-3 lg:px-4 2xl:px-5' : 'max-w-[1600px] px-4 pb-10 pt-4 sm:px-6 lg:px-8'"
    >
      <header v-if="!isImmersiveRoute" class="sticky top-4 z-50">
        <nav class="store-nav px-4 py-4 sm:px-5 lg:px-6">
          <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex flex-wrap items-center gap-4 sm:gap-6">
              <RouterLink to="/events" class="flex items-center gap-3">
                <span class="brand-mark">SM</span>
                <div>
                  <p class="text-[0.68rem] font-semibold uppercase tracking-[0.28em] text-blue-600">
                    SubMeet
                  </p>
                  <p class="text-sm font-semibold text-slate-900">
                    Tickets & live booking
                  </p>
                </div>
              </RouterLink>

              <div class="hidden items-center gap-2 lg:flex">
                <RouterLink
                  to="/events"
                  class="store-link"
                  :class="isCatalogLinkActive ? 'store-link-active' : ''"
                >
                  Каталог
                </RouterLink>

                <RouterLink
                  v-if="authStore.isAuthenticated"
                  to="/profile"
                  class="store-link"
                  :class="isProfileLinkActive ? 'store-link-active' : ''"
                >
                  Профиль
                </RouterLink>

                <RouterLink
                  v-if="authStore.isOrganizer"
                  to="/organizer/dashboard"
                  class="store-link"
                  :class="isOrganizerDashboardLinkActive ? 'store-link-active' : ''"
                >
                  Dashboard
                </RouterLink>

                <RouterLink
                  v-if="authStore.isOrganizer"
                  to="/organizer/events"
                  class="store-link"
                  :class="isOrganizerEventsLinkActive ? 'store-link-active' : ''"
                >
                  События
                </RouterLink>

                <RouterLink
                  v-if="authStore.isOrganizer"
                  to="/organizer/halls"
                  class="store-link"
                  :class="isOrganizerHallsLinkActive ? 'store-link-active' : ''"
                >
                  Залы
                </RouterLink>

                <RouterLink
                  v-if="authStore.isOrganizer"
                  to="/organizer/tickets"
                  class="store-link"
                  :class="isOrganizerTicketsLinkActive ? 'store-link-active' : ''"
                >
                  Ticket Check
                </RouterLink>
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
              <template v-if="authStore.isAuthenticated">
                <RouterLink
                  to="/profile"
                  class="secondary-button gap-3 px-4 py-2.5"
                >
                  <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-950 text-sm font-semibold text-white">
                    {{ userInitials }}
                  </span>
                  <span class="hidden text-left sm:block">
                    <span class="block text-xs uppercase tracking-[0.18em] text-slate-400">Аккаунт</span>
                    <span class="block text-sm font-semibold text-slate-900">{{ userLabel }}</span>
                  </span>
                </RouterLink>

                <button type="button" class="secondary-button px-4 py-3" @click="logout">
                  Выйти
                </button>
              </template>

              <template v-else>
                <RouterLink
                  to="/login"
                  class="secondary-button px-4 py-3"
                  :class="isLoginLinkActive ? 'border-blue-200 text-blue-700' : ''"
                >
                  Войти
                </RouterLink>

                <RouterLink
                  to="/register"
                  class="primary-button px-4 py-3"
                  :class="isRegisterLinkActive ? 'ring-4 ring-blue-500/15' : ''"
                >
                  Регистрация
                </RouterLink>
              </template>
            </div>
          </div>

          <div class="mt-4 flex flex-wrap items-center gap-2 lg:hidden">
            <RouterLink
              to="/events"
              class="store-link"
              :class="isCatalogLinkActive ? 'store-link-active' : ''"
            >
              Каталог
            </RouterLink>

            <RouterLink
              v-if="authStore.isAuthenticated"
              to="/profile"
              class="store-link"
              :class="isProfileLinkActive ? 'store-link-active' : ''"
            >
              Профиль
            </RouterLink>

            <RouterLink
              v-if="authStore.isOrganizer"
              to="/organizer/dashboard"
              class="store-link"
              :class="isOrganizerDashboardLinkActive ? 'store-link-active' : ''"
            >
              Dashboard
            </RouterLink>

            <RouterLink
              v-if="authStore.isOrganizer"
              to="/organizer/events"
              class="store-link"
              :class="isOrganizerEventsLinkActive ? 'store-link-active' : ''"
            >
              События
            </RouterLink>

            <RouterLink
              v-if="authStore.isOrganizer"
              to="/organizer/halls"
              class="store-link"
              :class="isOrganizerHallsLinkActive ? 'store-link-active' : ''"
            >
              Залы
            </RouterLink>

            <RouterLink
              v-if="authStore.isOrganizer"
              to="/organizer/tickets"
              class="store-link"
              :class="isOrganizerTicketsLinkActive ? 'store-link-active' : ''"
            >
              Ticket Check
            </RouterLink>
          </div>
        </nav>
      </header>

      <main :class="isImmersiveRoute ? 'flex-1' : 'pt-6 sm:pt-7 lg:pt-8'">
        <RouterView />
      </main>
    </div>
  </div>
</template>
