import axios from 'axios'

export interface ApiEnvelope<T> {
  code: string
  message: string
  data: T
}

export const TOKEN_STORAGE_KEY = 'kldns:token'
export const USER_STORAGE_KEY = 'kldns:user'

export const http = axios.create({
  baseURL: '/api/v1',
  timeout: 15000,
})

http.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_STORAGE_KEY)
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

http.interceptors.response.use((response) => response.data)
