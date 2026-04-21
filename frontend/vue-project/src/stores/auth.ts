import { defineStore } from 'pinia'
import { ref } from 'vue'
import {
  loginRequest,
  logoutRequest,
  meRequest,
  registerRequest,
  updateProfileRequest,
} from '@/api/auth'
import type {
  LoginPayload,
  RegisterPayload,
  UpdateProfilePayload,
} from '@/types/auth'
import type { User } from '@/types/user'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('token'))
  const loading = ref(false)
  const error = ref<string>('')

  const setToken = (value: string | null) => {
    token.value = value

    if (value) {
      localStorage.setItem('token', value)
    } else {
      localStorage.removeItem('token')
    }
  }

  const register = async (payload: RegisterPayload) => {
    loading.value = true
    error.value = ''

    try {
      const data = await registerRequest(payload)
      setToken(data.token)
      user.value = data.user
      return data
    } catch (e: any) {
      error.value = e.response?.data?.message || 'Ошибка регистрации'
      throw e
    } finally {
      loading.value = false
    }
  }

  const login = async (payload: LoginPayload) => {
    loading.value = true
    error.value = ''

    try {
      const data = await loginRequest(payload)
      setToken(data.token)
      user.value = data.user
      return data
    } catch (e: any) {
      error.value = e.response?.data?.message || 'Ошибка входа'
      throw e
    } finally {
      loading.value = false
    }
  }

  const fetchMe = async () => {
    if (!token.value) return null

    loading.value = true
    error.value = ''

    try {
      const data = await meRequest()
      user.value = data.user
      return data.user
    } catch (e: any) {
      setToken(null)
      user.value = null
      error.value = 'Сессия недействительна'
      return null
    } finally {
      loading.value = false
    }
  }

  const updateProfile = async (payload: UpdateProfilePayload) => {
    loading.value = true
    error.value = ''

    try {
      const data = await updateProfileRequest(payload)
      user.value = data.user
      return data
    } catch (e: any) {
      error.value = e.response?.data?.message || 'Ошибка обновления профиля'
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
    }
  }

  return {
    user,
    token,
    loading,
    error,
    register,
    login,
    fetchMe,
    updateProfile,
    logout,
  }
})