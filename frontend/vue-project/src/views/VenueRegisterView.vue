<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { VenueOwnerRegisterPayload } from '@/types/auth'
import { formatPhoneMask, isPhoneMaskComplete } from '@/utils/phone'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const form = reactive<VenueOwnerRegisterPayload>({
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
  return redirect || '/venue/halls'
}

const registerVenueOwner = async () => {
  successMessage.value = ''

  try {
    const response = await authStore.registerVenueOwner({ ...form })
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
          Регистрация площадки для аренды залов под мероприятия.
        </h2>
        <p class="mt-4 max-w-xl text-sm leading-6 text-white/70 sm:text-base">
          Этот аккаунт нужен владельцу площадки. Здесь создаются залы, указывается почасовая
          стоимость аренды и принимаются заявки от организаторов на нужные даты.
        </p>
      </div>

      <div class="px-8 py-10 lg:px-10">
        <div class="mb-8">
          <h3 class="text-2xl font-semibold text-slate-950">Зарегистрировать площадку</h3>
          <p class="mt-2 text-sm leading-6 text-slate-500">
            Укажи название площадки и контактное лицо. После одобрения можно будет собирать залы,
            выставлять стоимость аренды и обрабатывать входящие заявки на даты.
          </p>
        </div>

        <div v-if="successMessage" class="message-success mb-4">
          {{ successMessage }}
        </div>

        <div v-if="authStore.error" class="message-error mb-4">
          {{ authStore.error }}
        </div>

        <form class="grid gap-5 sm:grid-cols-2" @submit.prevent="registerVenueOwner">
          <div class="sm:col-span-2">
            <label class="field-label" for="venue-company-name">Название площадки или компании</label>
            <input
              id="venue-company-name"
              v-model="form.company_name"
              type="text"
              class="field-input"
              placeholder="ДКХ, Milo Concert Hall, Арена"
            />
          </div>

          <div class="sm:col-span-2">
            <label class="field-label" for="venue-contact-name">Контактное лицо</label>
            <input
              id="venue-contact-name"
              v-model="form.full_name"
              type="text"
              autocomplete="name"
              class="field-input"
              placeholder="Андрей Смирнов"
            />
          </div>

          <div>
            <label class="field-label" for="venue-email">Email</label>
            <input
              id="venue-email"
              v-model="form.email"
              type="email"
              autocomplete="email"
              class="field-input"
              placeholder="venue@example.com"
            />
          </div>

          <div>
            <label class="field-label" for="venue-phone">Телефон</label>
            <input
              id="venue-phone"
              v-model="form.phone"
              type="tel"
              autocomplete="tel"
              class="field-input"
              placeholder="+7 (999) 123-45-67"
              @input="handlePhoneInput"
            />
          </div>

          <div>
            <label class="field-label" for="venue-password">Пароль</label>
            <input
              id="venue-password"
              v-model="form.password"
              type="password"
              autocomplete="new-password"
              class="field-input"
              placeholder="Минимум 8 символов"
            />
          </div>

          <div>
            <label class="field-label" for="venue-password-confirmation">Подтверждение пароля</label>
            <input
              id="venue-password-confirmation"
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
              <RouterLink class="font-semibold text-slate-500 hover:text-slate-700" to="/register/organizer">
                Нужно создавать события? Регистрация организатора
              </RouterLink>
            </div>

            <button
              :disabled="authStore.loading || !canSubmit"
              type="submit"
              class="primary-button w-full sm:w-auto sm:min-w-64"
            >
              {{ authStore.loading ? 'Создаем кабинет...' : 'Зарегистрировать площадку' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </section>
</template>
