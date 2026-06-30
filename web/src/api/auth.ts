import { http, type ApiEnvelope } from './http'

export interface User {
  id: number
  group_id: number
  status: number
  username: string
  email: string
  points: number
}

export interface LoginResponse {
  token_id: number
  token: string
  user: User
}

export interface RegisterResponse {
  id: number
  status: number
  review_required: boolean
}

export function login(payload: { login: string; password: string; turnstile_token?: string }, admin = false) {
  return http.post<unknown, ApiEnvelope<LoginResponse>>(admin ? '/admin/auth/login' : '/auth/login', payload)
}

export function register(payload: { username: string; email?: string; password: string; turnstile_token?: string }) {
  return http.post<unknown, ApiEnvelope<RegisterResponse>>('/auth/register', payload)
}

export function me() {
  return http.get<unknown, ApiEnvelope<User>>('/auth/me')
}

export function changePassword(payload: { old_password: string; new_password: string }) {
  return http.put<unknown, ApiEnvelope<{ changed: boolean }>>('/auth/password', payload)
}
