export interface Role {
  id: number
  role: string
  created_at?: string
  updated_at?: string
}

export interface OrganizerProfile {
  company_name: string
  created_at?: string
  updated_at?: string
}

export interface User {
  id: number
  full_name: string
  email: string
  phone: string
  birth_date: string | null
  role_id: number
  status: number
  role?: Role
  organizer_profile?: OrganizerProfile | null
  created_at?: string
  updated_at?: string
}
