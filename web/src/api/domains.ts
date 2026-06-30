import { http, type ApiEnvelope } from './http'
import type { Domain } from '../types/domain'

export function listDomains(params: { keyword?: string } = {}) {
  return http.get<unknown, ApiEnvelope<Domain[]>>('/domains', { params })
}

export function listPublicDomains() {
  return http.get<unknown, ApiEnvelope<Domain[]>>('/public/domains')
}
