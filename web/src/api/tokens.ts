import { http, type ApiEnvelope } from './http'

export interface TokenItem {
  id: number
  name: string
  token_hint: string
  last_used_at: number
  expires_at: number
  created_at: number
}

export interface TokenCreated {
  id: number
  token: string
  token_hint: string
  expires_at: number
}

export function listTokens() {
  return http.get<unknown, ApiEnvelope<TokenItem[]>>('/tokens')
}

export function createToken(payload: { name: string; days: number }) {
  return http.post<unknown, ApiEnvelope<TokenCreated>>('/tokens', payload)
}

export function deleteToken(id: number) {
  return http.delete<unknown, ApiEnvelope<{ deleted: boolean }>>(`/tokens/${id}`)
}
