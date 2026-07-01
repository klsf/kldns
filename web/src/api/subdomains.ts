import { http, type ApiEnvelope } from './http'
import type { Subdomain } from '../types/domain'

export interface SubdomainQuery {
  status?: number
  keyword?: string
}

export function listSubdomains(params: SubdomainQuery = {}) {
  return http.get<unknown, ApiEnvelope<Subdomain[]>>('/subdomains', { params })
}

export function registerSubdomain(payload: { did: number; name: string; purpose?: string }) {
  return http.post<unknown, ApiEnvelope<Subdomain>>('/subdomains', payload)
}

export function deleteSubdomain(id: number, payload: { confirm_full_domain: string }) {
  return http.delete<unknown, ApiEnvelope<{ deleted: boolean }>>(`/subdomains/${id}`, { data: payload })
}
