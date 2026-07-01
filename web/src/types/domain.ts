export interface RecordLine {
  id: string
  name: string
}

export interface Domain {
  id: number
  domain: string
  points_cost: number
  registration_cost?: number
  description: string
  record_types: string[]
  beian: number
  beian_text: string
  require_review: number
  line?: RecordLine[]
}

export interface Subdomain {
  id: number
  uid?: number
  did: number
  name: string
  full_domain: string
  status: number
  purpose: string
  reject_reason: string
  reviewed_by: number
  reviewed_at: number
  domain: string
  registration_cost: number
  record_types: string[]
  record_count: number
  created_at: number
}
