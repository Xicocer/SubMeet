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
