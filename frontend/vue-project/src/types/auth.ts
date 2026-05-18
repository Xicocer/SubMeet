import type { User } from './user'

export interface RegisterPayload {
  full_name: string
  email: string
  phone: string
  birth_date: string
  password: string
  password_confirmation: string
}

export interface OrganizerRegisterPayload {
  company_name: string
  full_name: string
  email: string
  phone: string
  password: string
  password_confirmation: string
}

export interface VenueOwnerRegisterPayload {
  company_name: string
  full_name: string
  email: string
  phone: string
  password: string
  password_confirmation: string
}

export interface LoginPayload {
  email: string
  password: string
}

export interface UpdateProfilePayload {
  full_name: string
  phone: string
  birth_date: string
  company_name?: string
}

export interface AuthResponse {
  message: string
  token: string
  user: User
}

export interface MeResponse {
  user: User
}

export interface MessageResponse {
  message: string
}
