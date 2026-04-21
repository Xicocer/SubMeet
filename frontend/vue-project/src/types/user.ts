export interface Role {
  id: number
  role: string
  created_at?: string
  updated_at?: string
}

export interface User {
  id: number
  full_name: string
  email: string
  phone: string
  birth_date: string
  role_id: number
  status: number
  role?: Role
  created_at?: string
  updated_at?: string
}