import { authApi } from './axios'
import type {
  AuthResponse,
  LoginPayload,
  MeResponse,
  MessageResponse,
  RegisterPayload,
  UpdateProfilePayload,
} from '@/types/auth'

export const registerRequest = async (payload: RegisterPayload) => {
  const { data } = await authApi.post<AuthResponse>('/register', payload)
  return data
}

export const loginRequest = async (payload: LoginPayload) => {
  const { data } = await authApi.post<AuthResponse>('/login', payload)
  return data
}

export const meRequest = async () => {
  const { data } = await authApi.get<MeResponse>('/me')
  return data
}

export const updateProfileRequest = async (payload: UpdateProfilePayload) => {
  const { data } = await authApi.put<{ message: string; user: MeResponse['user'] }>('/me', payload)
  return data
}

export const logoutRequest = async () => {
  const { data } = await authApi.post<MessageResponse>('/logout')
  return data
}
