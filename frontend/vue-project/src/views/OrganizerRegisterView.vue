<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { OrganizerRegisterPayload } from '@/types/auth'
import { formatPhoneMask, isPhoneMaskComplete } from '@/utils/phone'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const form = reactive<OrganizerRegisterPayload>({
  company_name: '',
  full_name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
})

const successMessage = ref('')

const passwordMismatch = computed(() => {
  return form.password_confirmation.trim() !== '' && form.password !== form.password_confirmation
})

const canSubmit = computed(() => {
  return (
    form.company_name.trim() !== '' &&
    form.full_name.trim() !== '' &&
    form.email.trim() !== '' &&
    isPhoneMaskComplete(form.phone) &&
    form.password.trim() !== '' &&
    form.password_confirmation.trim() !== '' &&
    !passwordMismatch.value
  )
})

const resolveTargetRoute = () => {
  const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : ''
  return redirect || '/organizer/events'
}

const registerOrganizer = async () => {
  successMessage.value = ''

  try {
    const response = await authStore.registerOrganizer({ ...form })
    successMessage.value = response.message
    router.push(resolveTargetRoute())
  } catch (error) {
    console.error(error)
  }
}

const handlePhoneInput = (event: Event) => {
  const input = event.target as HTMLInputElement
  form.phone = formatPhoneMask(input.value)
}
</script>

<template>
  <section class="app-panel overflow-hidden">
    <div class="grid lg:grid-cols-[0.96fr_1.04fr]">
      <div class="border-b border-white/10 bg-slate-950 px-8 py-10 text-white lg:border-b-0 lg:border-r lg:px-10">
        <h2 class="mt-5 text-4xl font-semibold leading-tight">
          Регистрация организатора для команд, продюсеров и авторов событий.
        </h2>
        <p class="mt-4 max-w-xl text-sm leading-6 text-white/70 sm:text-base">
          Этот аккаунт нужен тому, кто создает само мероприятие: собирает карточку события,
          описывает программу и отправляет заявки на аренду площадок под нужные даты.
        </p>
      </div>

      <div class="px-8 py-10 lg:px-10">
        <div class="mb-8">
          <h3 class="text-2xl font-semibold text-slate-950">Зарегистрировать организатора</h3>
          <p class="mt-2 text-sm leading-6 text-slate-500">
            Укажи название команды или бренда и контактное лицо. После одобрения такой аккаунт
            сможет публиковать события и запрашивать площадки для проведения.
          </p>
        </div>

        <div v-if="successMessage" class="message-success mb-4">
          {{ successMessage }}
        </div>

        <div v-if="authStore.error" class="message-error mb-4">
          {{ authStore.error }}
        </div>

        <form class="grid gap-5 sm:grid-cols-2" @submit.prevent="registerOrganizer">
          <div class="sm:col-span-2">
            <label class="field-label" for="organizer-company-name">Название команды или бренда</label>
            <input
              id="organizer-company-name"
              v-model="form.company_name"
              type="text"
              class="field-input"
              placeholder="Milo Concert Team"
            />
          </div>

          <div class="sm:col-span-2">
            <label class="field-label" for="organizer-contact-name">Контактное лицо</label>
            <input
              id="organizer-contact-name"
              v-model="form.full_name"
              type="text"
              autocomplete="name"
              class="field-input"
              placeholder="Мария Петрова"
            />
          </div>

          <div>
            <label class="field-label" for="organizer-email">Email</label>
            <input
              id="organizer-email"
              v-model="form.email"
              type="email"
              autocomplete="email"
              class="field-input"
              placeholder="team@example.com"
            />
          </div>

          <div>
            <label class="field-label" for="organizer-phone">Телефон</label>
            <input
              id="organizer-phone"
              v-model="form.phone"
              type="tel"
              autocomplete="tel"
              class="field-input"
              placeholder="+7 (999) 123-45-67"
              @input="handlePhoneInput"
            />
          </div>

          <div>
            <label class="field-label" for="organizer-password">Пароль</label>
            <input
              id="organizer-password"
              v-model="form.password"
              type="password"
              autocomplete="new-password"
              class="field-input"
              placeholder="Минимум 8 символов"
            />
          </div>

          <div>
            <label class="field-label" for="organizer-password-confirmation">Подтверждение пароля</label>
            <input
              id="organizer-password-confirmation"
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
            <div class="flex flex-col gap-2 text-sm">
              <RouterLink class="font-semibold text-sky-700 hover:text-sky-800" to="/login">
                Уже есть аккаунт? Войти
              </RouterLink>
              <RouterLink class="font-semibold text-slate-500 hover:text-slate-700" to="/register/venue">
                Есть своя площадка? Зарегистрировать владельца зала
              </RouterLink>
            </div>

            <button
              :disabled="authStore.loading || !canSubmit"
              type="submit"
              class="primary-button w-full sm:w-auto sm:min-w-64"
            >
              {{ authStore.loading ? 'Создаем кабинет...' : 'Зарегистрировать организатора' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </section>
</template>
