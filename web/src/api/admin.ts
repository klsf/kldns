import { http, type ApiEnvelope } from './http'
import type { PageQuery, PageResult } from './pagination'
import type { RecordItem } from '../types/record'

export interface AdminUser {
  id: number
  group_id: number
  status: number
  username: string
  email: string
  points: number
}

export interface AdminGroup {
  id: number
  name: string
}

export interface AdminDomain {
  id: number
  provider_key: string
  provider_config_stored: boolean
  remote_zone_id: string
  domain: string
  group_policy: string
  record_types: string
  beian: number
  points_cost: number
  require_review: number
  description: string
}

export interface AdminSubdomain {
  id: number
  uid: number
  did: number
  name: string
  full_domain: string
  status: number
  purpose: string
  reject_reason: string
  reviewed_by: number
  reviewed_at: number
  username: string
  domain: string
  record_count: number
  registration_cost: number
  created_at: number
}

export interface ProviderField {
  name: string
  label: string
  required: boolean
  secret: boolean
  description?: string
}

export interface ProviderSummary {
  key: string
  label: string
  fields: ProviderField[]
  stored: boolean
}

export interface ProviderZone {
  id: string
  domain: string
}

export interface LogItem {
  id: number
  uid: number
  admin_uid: number
  username: string
  admin_username: string
  source: string
  target_type: string
  target_id: string
  action: string
  message: string
  created_at: number
}

export interface AdminPointRecord {
  id: number
  uid: number
  admin_uid: number
  username: string
  admin_username: string
  action: string
  points: number
  rest: number
  remark: string
  created_at: number
}

export interface SettingItem {
  key: string
  value: string
}

export interface AdminDomainPayload {
  id?: number
  provider_key: string
  provider_config: Record<string, string>
  remote_zone_id: string
  domain: string
  group_policy: string
  record_types: string[]
  beian: number
  points_cost: number
  require_review: number
  description: string
}

export interface AdminUserQuery {
  keyword?: string
  status?: number
  group_id?: number
}

export interface AdminGroupQuery {
  keyword?: string
}

export interface AdminDomainQuery {
  provider?: string
  keyword?: string
}

export interface AdminRecordQuery {
  did?: number
  subdomain_id?: number
  uid?: number
  type?: string
  keyword?: string
}

export interface AdminSubdomainQuery {
  did?: number
  status?: number
  keyword?: string
}

export interface LogQuery {
  source?: string
  action?: string
  keyword?: string
}

export interface AdminPointQuery {
  uid?: number
  admin_uid?: number
  action?: string
  change?: 'increase' | 'decrease' | ''
  keyword?: string
}

export interface AdminPointAdjustResult {
  user_id: number
  username: string
  delta: number
  balance: number
  action: string
  remark: string
}

export function listAdminUsers(params: AdminUserQuery = {}) {
  return http.get<unknown, ApiEnvelope<AdminUser[]>>('/admin/users', { params })
}

export function listAdminUsersPage(params: AdminUserQuery & PageQuery) {
  return http.get<unknown, ApiEnvelope<PageResult<AdminUser>>>('/admin/users', { params })
}

export function saveAdminUser(
  id: number,
  payload: {
    username: string
    email: string
    group_id: number
    status: number
    password?: string
  },
) {
  return http.put<unknown, ApiEnvelope<{ updated: boolean }>>(`/admin/users/${id}`, payload)
}

export function deleteAdminUser(id: number, payload: { confirm_username: string }) {
  return http.delete<unknown, ApiEnvelope<{ deleted: boolean; records_deleted: number; subdomains_deleted: number }>>(`/admin/users/${id}`, { data: payload })
}

export function adjustAdminUserPoints(id: number, payload: { mode: 'increase' | 'decrease'; points: number; remark: string }) {
  return http.post<unknown, ApiEnvelope<AdminPointAdjustResult>>(`/admin/users/${id}/points`, payload)
}

export function listAdminGroups(params: AdminGroupQuery = {}) {
  return http.get<unknown, ApiEnvelope<AdminGroup[]>>('/admin/groups', { params })
}

export function saveAdminGroup(payload: { id?: number; name: string }) {
  return http.post<unknown, ApiEnvelope<{ id: number }>>('/admin/groups', payload)
}

export function deleteAdminGroup(id: number) {
  return http.delete<unknown, ApiEnvelope<{ deleted: boolean }>>(`/admin/groups/${id}`)
}

export function listAdminDomains(params: AdminDomainQuery = {}) {
  return http.get<unknown, ApiEnvelope<AdminDomain[]>>('/admin/domains', { params })
}

export function listAdminDomainsPage(params: AdminDomainQuery & PageQuery) {
  return http.get<unknown, ApiEnvelope<PageResult<AdminDomain>>>('/admin/domains', { params })
}

export function saveAdminDomain(payload: AdminDomainPayload) {
  const path = payload.id ? `/admin/domains/${payload.id}` : '/admin/domains'
  return payload.id
    ? http.put<unknown, ApiEnvelope<{ id: number }>>(path, payload)
    : http.post<unknown, ApiEnvelope<{ id: number }>>(path, payload)
}

export function deleteAdminDomain(id: number, payload: { delete_mode: 'local_subdomains' | 'platform_records' }) {
  return http.delete<unknown, ApiEnvelope<{ deleted: boolean; mode: string; records_deleted: number; subdomains_deleted: number }>>(`/admin/domains/${id}`, { data: payload })
}

export function syncAdminDomainRecords(id: number) {
  return http.post<unknown, ApiEnvelope<{ total: number; created: number; skipped: number }>>(`/admin/domains/${id}/sync-records`)
}

export function listProviders() {
  return http.get<unknown, ApiEnvelope<ProviderSummary[]>>('/admin/dns-providers')
}

export function listProviderZones(payload: { key: string; config: Record<string, string>; domain_id?: number }) {
  return http.post<unknown, ApiEnvelope<ProviderZone[]>>('/admin/dns-providers/zones', payload)
}

export function listAdminRecords(params: AdminRecordQuery = {}) {
  return http.get<unknown, ApiEnvelope<RecordItem[]>>('/admin/records', { params })
}

export function listAdminRecordsPage(params: AdminRecordQuery & PageQuery) {
  return http.get<unknown, ApiEnvelope<PageResult<RecordItem>>>('/admin/records', { params })
}

export function listAdminSubdomains(params: AdminSubdomainQuery = {}) {
  return http.get<unknown, ApiEnvelope<AdminSubdomain[]>>('/admin/subdomains', { params })
}

export function listAdminSubdomainsPage(params: AdminSubdomainQuery & PageQuery) {
  return http.get<unknown, ApiEnvelope<PageResult<AdminSubdomain>>>('/admin/subdomains', { params })
}

export function deleteAdminSubdomain(id: number) {
  return http.delete<unknown, ApiEnvelope<{ deleted: boolean; records_deleted: number; subdomains_deleted: number }>>(`/admin/subdomains/${id}`)
}

export function approveAdminSubdomain(id: number) {
  return http.post<unknown, ApiEnvelope<{ reviewed: boolean; action: string }>>(`/admin/subdomains/${id}/approve`)
}

export function rejectAdminSubdomain(id: number, payload: { reason: string }) {
  return http.post<unknown, ApiEnvelope<{ reviewed: boolean; action: string; refund?: number }>>(`/admin/subdomains/${id}/reject`, payload)
}

export function saveAdminRecord(
  payload: {
    id?: number
    uid: number
    did: number
    name: string
    type: string
    value: string
    line_id: string
  },
) {
  const path = payload.id ? `/admin/records/${payload.id}` : '/admin/records'
  return payload.id
    ? http.put<unknown, ApiEnvelope<unknown>>(path, payload)
    : http.post<unknown, ApiEnvelope<unknown>>(path, payload)
}

export function deleteAdminRecord(id: number) {
  return http.delete<unknown, ApiEnvelope<unknown>>(`/admin/records/${id}`)
}

export function listLogs(params: LogQuery = {}) {
  return http.get<unknown, ApiEnvelope<LogItem[]>>('/admin/logs', { params })
}

export function listLogsPage(params: LogQuery & PageQuery) {
  return http.get<unknown, ApiEnvelope<PageResult<LogItem>>>('/admin/logs', { params })
}

export function listAdminPoints(params: AdminPointQuery = {}) {
  return http.get<unknown, ApiEnvelope<AdminPointRecord[]>>('/admin/points', { params })
}

export function listAdminPointsPage(params: AdminPointQuery & PageQuery) {
  return http.get<unknown, ApiEnvelope<PageResult<AdminPointRecord>>>('/admin/points', { params })
}

export function listSettings() {
  return http.get<unknown, ApiEnvelope<SettingItem[]>>('/admin/settings')
}

export function saveSettings(payload: Record<string, string>) {
  return http.put<unknown, ApiEnvelope<{ saved: boolean }>>('/admin/settings', payload)
}
