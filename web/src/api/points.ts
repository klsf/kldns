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
  recent_records: PointRecord[]
}

export function pointsOverview() {
  return http.get<unknown, ApiEnvelope<PointsOverview>>('/points')
}
