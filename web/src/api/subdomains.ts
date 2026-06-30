import { http, type ApiEnvelope } from './http'
import type { Subdomain } from '../types/domain'

export function listSubdomains() {
  return http.get<unknown, ApiEnvelope<Subdomain[]>>('/subdomains')
}

export function registerSubdomain(payload: { did: number; name: string }) {
  return http.post<unknown, ApiEnvelope<Subdomain>>('/subdomains', payload)
}

export function deleteSubdomain(id: number, payload: { confirm_full_domain: string }) {
  return http.delete<unknown, ApiEnvelope<{ deleted: boolean }>>(`/subdomains/${id}`, { data: payload })
}
