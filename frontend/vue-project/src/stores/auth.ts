import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import {
  loginRequest,
  logoutRequest,
  meRequest,
  registerRequest,
  updateProfileRequest,
} from '@/api/auth'
import type { LoginPayload, RegisterPayload, UpdateProfilePayload } from '@/types/auth'
import type { User } from '@/types/user'

const extractErrorMessage = (error: any, fallback: string) => {
  const validationErrors = error?.response?.data?.errors

  if (validationErrors && typeof validationErrors === 'object') {
    const firstField = Object.values(validationErrors)[0]

    if (Array.isArray(firstField) && firstField.length > 0) {
      return String(firstField[0])
    }
  }

  return error?.response?.data?.message || fallback
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('token'))
  const loading = ref(false)
  const error = ref('')

  const isAuthenticated = computed(() => Boolean(token.value))
  const roleName = computed(() => user.value?.role?.role ?? 'user')
  const isOrganizer = computed(() => roleName.value === 'organizer')

  const setToken = (value: string | null) => {
    token.value = value

    if (value) {
      localStorage.setItem('token', value)
      return
    }

    localStorage.removeItem('token')
  }

  const clearError = () => {
    error.value = ''
  }

  const register = async (payload: RegisterPayload) => {
    loading.value = true
    clearError()

    try {
      const data = await registerRequest(payload)
      setToken(data.token)
      user.value = data.user
      return data
    } catch (e: any) {
      error.value = extractErrorMessage(e, 'Не удалось зарегистрировать аккаунт.')
      throw e
    } finally {
      loading.value = false
    }
  }

  const login = async (payload: LoginPayload) => {
    loading.value = true
    clearError()

    try {
      const data = await loginRequest(payload)
      setToken(data.token)
      user.value = data.user
      return data
    } catch (e: any) {
      error.value = extractErrorMessage(e, 'Не удалось выполнить вход.')
      throw e
    } finally {
      loading.value = false
    }
  }

  const fetchMe = async () => {
    if (!token.value) return null

    loading.value = true
    clearError()

    try {
      const data = await meRequest()
      user.value = data.user
      return data.user
    } catch (e) {
      setToken(null)
      user.value = null
      error.value = 'Сессия недействительна. Выполни вход заново.'
      return null
    } finally {
      loading.value = false
    }
  }

  const updateProfile = async (payload: UpdateProfilePayload) => {
    loading.value = true
    clearError()

    try {
      const data = await updateProfileRequest(payload)
      user.value = data.user
      return data
    } catch (e: any) {
      error.value = extractErrorMessage(e, 'Не удалось обновить профиль.')
      throw e
    } finally {
      loading.value = false
    }
  }

  const logout = async () => {
    try {
      await logoutRequest()
    } catch {
    } finally {
      setToken(null)
      user.value = null
      clearError()
    }
  }

  return {
    user,
    token,
    loading,
    error,
    isAuthenticated,
    isOrganizer,
    roleName,
    register,
    login,
    fetchMe,
    updateProfile,
    logout,
    clearError,
  }
})
