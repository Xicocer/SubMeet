<script setup lang="ts">
const navLinks = [
  { to: '/register', label: 'Регистрация' },
  { to: '/login', label: 'Вход' },
  { to: '/profile', label: 'Профиль' },
]
</script>

<template>
  <div class="relative overflow-hidden">
    <div class="pointer-events-none absolute -left-24 top-20 h-64 w-64 rounded-full bg-amber-300/30 blur-3xl"></div>
    <div class="pointer-events-none absolute right-0 top-0 h-72 w-72 rounded-full bg-emerald-300/25 blur-3xl"></div>
    <div
      class="pointer-events-none absolute bottom-12 left-1/2 h-72 w-72 -translate-x-1/2 rounded-full bg-sky-200/30 blur-3xl"
    ></div>

    <div class="relative mx-auto flex min-h-screen max-w-7xl flex-col px-4 py-5 sm:px-6 lg:px-8 lg:py-8">
      <header class="app-panel px-6 py-5 sm:px-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
          <div class="max-w-2xl">
            <span class="info-chip">SubMeet Auth</span>
            <h1 class="mt-4 text-3xl font-semibold leading-tight text-slate-900 sm:text-4xl">
              Аккуратный старт для дипломного auth-модуля
            </h1>
            <p class="mt-3 max-w-xl text-sm leading-6 text-slate-600 sm:text-base">
              Vue 3, TypeScript и понятный интерфейс: вход, регистрация и профиль в
              одном живом стиле.
            </p>
          </div>

          <nav class="flex flex-wrap items-center gap-3 text-sm">
            <RouterLink
              v-for="link in navLinks"
              :key="link.to"
              :to="link.to"
              class="rounded-full border border-white/80 bg-white/80 px-4 py-2.5 font-medium text-slate-700 shadow-sm shadow-slate-900/5 transition duration-200 hover:border-slate-200 hover:text-slate-950"
              active-class="border-slate-900 bg-slate-900 text-white shadow-lg shadow-slate-900/10"
            >
              {{ link.label }}
            </RouterLink>
          </nav>
        </div>
      </header>

      <main class="mt-6 flex-1">
        <RouterView />
      </main>
    </div>
  </div>
</template>
