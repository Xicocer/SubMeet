<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { LoginPayload } from '@/types/auth'

const router = useRouter()
const authStore = useAuthStore()

const form = reactive<LoginPayload>({
  email: '',
  password: '',
})

const successMessage = ref('')
const canSubmit = computed(() => form.email.trim() !== '' && form.password.trim() !== '')

const login = async () => {
  successMessage.value = ''

  try {
    const response = await authStore.login({ ...form })
    successMessage.value = response.message
    router.push('/profile')
  } catch (error) {
    console.error(error)
  }
}
</script>

<template>
  <section class="app-panel overflow-hidden">
    <div class="grid lg:grid-cols-[1.05fr_0.95fr]">
      <div class="border-b border-white/60 p-8 sm:p-10 lg:border-b-0 lg:border-r">
        <span class="info-chip">С возвращением</span>
        <h2 class="mt-5 text-4xl font-semibold leading-tight text-slate-900">
          Вход в систему без лишнего шума
        </h2>
        <p class="mt-4 max-w-lg text-sm leading-6 text-slate-600 sm:text-base">
          Авторизуйся, чтобы открыть личный кабинет, проверить статус учетной записи
          и продолжить работу с проектом.
        </p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
          <article class="soft-card">
            <p class="text-sm font-semibold text-slate-900">Быстрый доступ</p>
            <p class="mt-2 text-sm leading-6 text-slate-600">
              После входа ты сразу попадаешь в профиль и можешь редактировать личные
              данные.
            </p>
          </article>

          <article class="soft-card">
            <p class="text-sm font-semibold text-slate-900">Живые состояния</p>
            <p class="mt-2 text-sm leading-6 text-slate-600">
              Ошибки, загрузка и успешные действия подсвечиваются аккуратно и читаемо.
            </p>
          </article>
        </div>
      </div>

      <div class="p-8 sm:p-10">
        <div class="mb-8">
          <h3 class="text-2xl font-semibold text-slate-900">Войти в аккаунт</h3>
          <p class="mt-2 text-sm leading-6 text-slate-500">
            Используй email и пароль, которые уже зарегистрированы в системе.
          </p>
        </div>

        <div v-if="successMessage" class="message-success mb-4">
          {{ successMessage }}
        </div>

        <div v-if="authStore.error" class="message-error mb-4">
          {{ authStore.error }}
        </div>

        <form class="space-y-5" @submit.prevent="login">
          <div>
            <label class="field-label" for="login-email">Email</label>
            <input
              id="login-email"
              v-model="form.email"
              type="email"
              autocomplete="email"
              class="field-input"
              placeholder="you@example.com"
            />
          </div>

          <div>
            <label class="field-label" for="login-password">Пароль</label>
            <input
              id="login-password"
              v-model="form.password"
              type="password"
              autocomplete="current-password"
              class="field-input"
              placeholder="Введите пароль"
            />
          </div>

          <button
            :disabled="authStore.loading || !canSubmit"
            type="submit"
            class="primary-button w-full"
          >
            {{ authStore.loading ? 'Входим...' : 'Войти' }}
          </button>
        </form>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm">
          <span class="text-slate-500">Еще нет аккаунта?</span>
          <RouterLink
            class="font-semibold text-emerald-700 hover:text-emerald-800"
            to="/register"
          >
            Создать аккаунт
          </RouterLink>
        </div>
      </div>
    </div>
  </section>
</template>
