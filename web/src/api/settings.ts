import { http, type ApiEnvelope } from './http'

export interface DNSPolicy {
  unlimited_subdomain_records: boolean
}

export interface TurnstilePublicSettings {
  site_key: string
  register_enabled: boolean
  login_enabled: boolean
}

export function getDNSPolicy() {
  return http.get<unknown, ApiEnvelope<DNSPolicy>>('/settings/dns-policy')
}

export function getTurnstileSettings() {
  return http.get<unknown, ApiEnvelope<TurnstilePublicSettings>>('/settings/turnstile')
}
