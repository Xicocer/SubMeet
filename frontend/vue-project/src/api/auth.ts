import api from './axios'
import type {
  AuthResponse,
  LoginPayload,
  MeResponse,
  MessageResponse,
  RegisterPayload,
  UpdateProfilePayload,
} from '@/types/auth'

export const registerRequest = async (payload: RegisterPayload) => {
  const { data } = await api.post<AuthResponse>('/register', payload)
  return data
}

export const loginRequest = async (payload: LoginPayload) => {
  const { data } = await api.post<AuthResponse>('/login', payload)
  return data
}

export const meRequest = async () => {
  const { data } = await api.get<MeResponse>('/me')
  return data
}

export const updateProfileRequest = async (payload: UpdateProfilePayload) => {
  const { data } = await api.put<{ message: string; user: MeResponse['user'] }>('/me', payload)
  return data
}

export const logoutRequest = async () => {
  const { data } = await api.post<MessageResponse>('/logout')
  return data
}