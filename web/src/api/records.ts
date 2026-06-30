import { http, type ApiEnvelope } from './http'
import type { RecordItem } from '../types/record'

export function listRecords(params: Record<string, string | number | undefined>) {
  return http.get<unknown, ApiEnvelope<RecordItem[]>>('/records', { params })
}

export interface RecordPayload {
  did?: number
  subdomain_id: number
  name: string
  type: string
  value: string
  line_id: string
}

export function createRecord(payload: RecordPayload) {
  return http.post<unknown, ApiEnvelope<unknown>>('/records', payload)
}

export function updateRecord(id: number, payload: RecordPayload) {
  return http.put<unknown, ApiEnvelope<unknown>>(`/records/${id}`, payload)
}

export function deleteRecord(id: number) {
  return http.delete<unknown, ApiEnvelope<unknown>>(`/records/${id}`)
}
