<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { RegisterPayload } from '@/types/auth'

const router = useRouter()
const authStore = useAuthStore()

const form = reactive<RegisterPayload>({
  full_name: '',
  email: '',
  phone: '',
  birth_date: '',
  password: '',
  password_confirmation: '',
})

const successMessage = ref('')
const passwordMismatch = computed(
  () =>
    form.password_confirmation.trim() !== '' &&
    form.password !== form.password_confirmation
)
const canSubmit = computed(
  () =>
    form.full_name.trim() !== '' &&
    form.email.trim() !== '' &&
    form.phone.trim() !== '' &&
    form.birth_date.trim() !== '' &&
    form.password.trim() !== '' &&
    form.password_confirmation.trim() !== '' &&
    !passwordMismatch.value
)

const register = async () => {
  successMessage.value = ''

  try {
    const response = await authStore.register({ ...form })
    successMessage.value = response.message
    router.push('/profile')
  } catch (error) {
    console.error(error)
  }
}
</script>

<template>
  <section class="app-panel overflow-hidden">
    <div class="grid lg:grid-cols-[0.95fr_1.05fr]">
      <div class="border-b border-white/60 bg-slate-900 px-8 py-10 text-white lg:border-b-0 lg:border-r lg:border-white/10">
        <span
          class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-widest text-white/75"
        >
          Новый аккаунт
        </span>
        <h2 class="mt-5 text-4xl font-semibold leading-tight text-white">
          Регистрация с аккуратной структурой и понятными полями
        </h2>
        <p class="mt-4 max-w-lg text-sm leading-6 text-white/70 sm:text-base">
          Создай учетную запись пользователя, чтобы получить доступ к системе и
          сразу перейти к редактированию профиля.
        </p>

        <div class="mt-8 space-y-4">
          <article class="rounded-3xl border border-white/10 bg-white/5 p-6">
            <p class="text-sm font-semibold text-white">Что уже продумано</p>
            <p class="mt-2 text-sm leading-6 text-white/70">
              Поля собраны в логичные блоки, форма хорошо читается и на ноутбуке, и
              на телефоне.
            </p>
          </article>

          <article class="rounded-3xl border border-white/10 bg-white/5 p-6">
            <p class="text-sm font-semibold text-white">Небольшая защита от ошибок</p>
            <p class="mt-2 text-sm leading-6 text-white/70">
              Кнопка неактивна, пока форма не заполнена полностью и пароли не
              совпадают.
            </p>
          </article>
        </div>
      </div>

      <div class="p-8 sm:p-10">
        <div class="mb-8">
          <h3 class="text-2xl font-semibold text-slate-900">Создать аккаунт</h3>
          <p class="mt-2 text-sm leading-6 text-slate-500">
            Заполни основные данные пользователя. Все поля ниже обязательные.
          </p>
        </div>

        <div v-if="successMessage" class="message-success mb-4">
          {{ successMessage }}
        </div>

        <div v-if="authStore.error" class="message-error mb-4">
          {{ authStore.error }}
        </div>

        <form class="grid gap-5 sm:grid-cols-2" @submit.prevent="register">
          <div class="sm:col-span-2">
            <label class="field-label" for="register-name">ФИО</label>
            <input
              id="register-name"
              v-model="form.full_name"
              type="text"
              autocomplete="name"
              class="field-input"
              placeholder="Иванов Иван Иванович"
            />
          </div>

          <div>
            <label class="field-label" for="register-email">Email</label>
            <input
              id="register-email"
              v-model="form.email"
              type="email"
              autocomplete="email"
              class="field-input"
              placeholder="you@example.com"
            />
          </div>

          <div>
            <label class="field-label" for="register-phone">Телефон</label>
            <input
              id="register-phone"
              v-model="form.phone"
              type="tel"
              autocomplete="tel"
              class="field-input"
              placeholder="+7 999 123-45-67"
            />
          </div>

          <div class="sm:col-span-2">
            <label class="field-label" for="register-birth-date">Дата рождения</label>
            <input
              id="register-birth-date"
              v-model="form.birth_date"
              type="date"
              class="field-input"
            />
          </div>

          <div>
            <label class="field-label" for="register-password">Пароль</label>
            <input
              id="register-password"
              v-model="form.password"
              type="password"
              autocomplete="new-password"
              class="field-input"
              placeholder="Минимум 8 символов"
            />
          </div>

          <div>
            <label class="field-label" for="register-password-confirmation">
              Подтверждение пароля
            </label>
            <input
              id="register-password-confirmation"
              v-model="form.password_confirmation"
              type="password"
              autocomplete="new-password"
              class="field-input"
              placeholder="Повтори пароль"
            />
          </div>

          <p v-if="passwordMismatch" class="sm:col-span-2 text-sm font-medium text-rose-600">
            Пароли пока не совпадают.
          </p>

          <div class="sm:col-span-2 flex flex-col gap-4 pt-2 sm:flex-row sm:items-center sm:justify-between">
            <RouterLink
              class="text-sm font-semibold text-emerald-700 hover:text-emerald-800"
              to="/login"
            >
              Уже есть аккаунт? Войти
            </RouterLink>

            <button
              :disabled="authStore.loading || !canSubmit"
              type="submit"
              class="primary-button w-full sm:w-auto sm:min-w-56"
            >
              {{ authStore.loading ? 'Регистрируем...' : 'Зарегистрироваться' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </section>
</template>
