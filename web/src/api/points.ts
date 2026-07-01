import { http, type ApiEnvelope } from './http'

export interface PointRecord {
  id: number
  action: string
  points: number
  rest: number
  remark: string
  created_at: number
}

export interface PointsOverview {
  balance: number
  month_spent: number
  total_spent: number
  actions: string[]
  recent_records: PointRecord[]
}

export interface PointRecordQuery {
  action?: string
  keyword?: string
  range?: string
}

export function pointsOverview(params: PointRecordQuery = {}) {
  return http.get<unknown, ApiEnvelope<PointsOverview>>('/points', { params })
}
