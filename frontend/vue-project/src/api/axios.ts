import axios from 'axios'

const defaultHeaders = {
  Accept: 'application/json',
  'Content-Type': 'application/json',
}

const createApiClient = (baseURL: string) => {
  const client = axios.create({
    baseURL,
    headers: defaultHeaders,
  })

  client.interceptors.request.use((config) => {
    const token = localStorage.getItem('token')

    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    return config
  })

  return client
}

export const authApi = createApiClient(
  import.meta.env.VITE_AUTH_API_URL ?? 'http://127.0.0.1:8000/api'
)

export const eventApi = createApiClient(
  import.meta.env.VITE_EVENT_API_URL ?? 'http://127.0.0.1:8001/api'
)

export const hallApi = createApiClient(
  import.meta.env.VITE_HALLS_API_URL ?? 'http://127.0.0.1:8002/api'
)

export const bookingApi = createApiClient(
  import.meta.env.VITE_BOOKING_API_URL ?? 'http://127.0.0.1:8003/api'
)

export const recommendationApi = createApiClient(
  import.meta.env.VITE_RECOMMENDATION_API_URL ?? 'http://127.0.0.1:8004/api'
)

export const adminApi = createApiClient(
  import.meta.env.VITE_ADMIN_API_URL ?? 'http://127.0.0.1:8005/api'
)
