<template>
  <el-dialog v-model="dialogVisible" width="min(960px, 96vw)" top="5vh" class="domain-dialog">
    <template #header>
      <div class="domain-dialog-title">
        <span class="dialog-title-icon"><Globe2 :size="19" /></span>
        <strong>{{ form.id ? '编辑主域' : '新增主域' }}</strong>
      </div>
    </template>
    <el-form class="domain-form" label-position="top">
      <div class="dialog-section">
        <div class="section-heading">
          <span class="step-badge">1</span>
          <h3>DNS 接入</h3>
        </div>
        <div class="form-grid two-columns">
          <el-form-item label="DNS 平台">
            <el-select v-model="form.provider_key" class="full-control" @change="handleProviderChange">
              <el-option v-for="provider in providers" :key="provider.key" :label="provider.label" :value="provider.key" />
            </el-select>
          </el-form-item>
          <el-form-item label="平台域名">
            <el-input v-if="form.id && form.domain" :model-value="form.domain" class="full-control" readonly />
            <el-select v-else v-model="selectedZone" class="full-control" filterable placeholder="先获取平台域名列表" :disabled="zoneOptions.length === 0" @change="selectZone">
              <el-option v-for="zone in zoneOptions" :key="zoneValue(zone)" :label="zone.domain" :value="zoneValue(zone)">
                <span>{{ zone.domain }}</span>
                <small class="zone-id">{{ zone.id }}</small>
              </el-option>
            </el-select>
          </el-form-item>
        </div>
        <div v-if="currentProvider" class="provider-config-panel">
          <div class="subsection-title">
            <strong>DNS 配置信息</strong>
            <span>{{ currentProvider.label }}</span>
          </div>
          <el-alert v-if="form.id" type="info" show-icon :closable="false" title="不填写新密钥时会保留当前域名已保存的 DNS 配置。" />
          <div class="config-grid">
            <el-form-item v-for="field in currentProvider.fields" :key="field.name" :label="fieldLabel(field)">
              <el-input
                v-model="form.provider_config[field.name]"
                class="full-control"
                :type="field.secret ? 'password' : 'text'"
                :show-password="field.secret"
                :placeholder="configPlaceholder(field)"
                autocomplete="off"
              />
              <p v-if="field.description" class="field-tip">{{ field.description }}</p>
            </el-form-item>
          </div>
          <div class="zone-actions">
            <el-button type="primary" :loading="zonesLoading" @click="loadZones">获取平台域名</el-button>
            <span v-if="zoneOptions.length">已获取 {{ zoneOptions.length }} 个域名</span>
            <span v-else>保存前必须从平台域名列表中选择主域。</span>
          </div>
        </div>
      </div>
      <div class="dialog-section">
        <div class="section-heading">
          <span class="step-badge">2</span>
          <h3>主域策略</h3>
        </div>
        <div class="selected-zone-summary">
          <span>已选主域</span>
          <strong>{{ form.domain || '未选择' }}</strong>
          <small>{{ form.remote_zone_id || '请先获取并选择平台域名' }}</small>
        </div>
        <el-form-item label="开放用户组">
          <el-select v-model="selectedGroups" class="full-control" multiple collapse-tags collapse-tags-tooltip placeholder="请选择开放用户组">
            <el-option label="全部用户组" :value="0" />
            <el-option v-for="group in groups" :key="group.id" :label="group.name" :value="group.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="记录类型">
          <el-select v-model="form.record_types" class="full-control" multiple>
            <el-option v-for="type in allRecordTypes" :key="type" :label="type" :value="type">
              <div class="record-type-option">
                <strong>{{ type }}</strong>
                <span>{{ recordTypeUsage(type) }}</span>
              </div>
            </el-option>
          </el-select>
        </el-form-item>
        <div class="form-grid">
          <el-form-item label="备案状态">
            <el-switch v-model="form.beian" :active-value="1" :inactive-value="0" />
          </el-form-item>
          <el-form-item label="注册积分">
            <el-input-number v-model="form.points_cost" :min="0" class="full-control" />
          </el-form-item>
        </div>
        <el-form-item label="说明">
          <el-input v-model="form.description" type="textarea" :rows="3" />
        </el-form-item>
      </div>
    </el-form>
    <template #footer>
      <el-button @click="dialogVisible = false">取消</el-button>
      <el-button type="primary" :loading="saving" @click="save">保存</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Globe2 } from 'lucide-vue-next'
import { apiErrorMessage } from '../../api/errors'
import {
  listProviderZones,
  saveAdminDomain,
  type AdminDomain,
  type AdminGroup,
  type ProviderField,
  type ProviderSummary,
  type ProviderZone,
} from '../../api/admin'
import { RECORD_TYPES, recordTypeUsage } from '../../utils/recordTypes'

const emit = defineEmits<{
  saved: []
}>()

const allRecordTypes = RECORD_TYPES
const defaultRecordTypes = ['A', 'CNAME', 'MX', 'TXT']
const saving = ref(false)
const zonesLoading = ref(false)
const dialogVisible = ref(false)
const selectedZone = ref('')
const zoneOptions = ref<ProviderZone[]>([])
const selectedGroups = ref<number[]>([0])
const editingProviderKey = ref('')
const editingConfigStored = ref(false)
const form = reactive({
  id: 0,
  provider_key: '',
  provider_config: {} as Record<string, string>,
  remote_zone_id: '',
  domain: '',
  group_policy: '0',
  record_types: [...defaultRecordTypes] as string[],
  beian: 0,
  points_cost: 0,
  description: '',
})

const props = defineProps<{
  providers: ProviderSummary[]
  groups: AdminGroup[]
}>()

const currentProvider = computed(() => props.providers.find((provider) => provider.key === form.provider_key))
const providerConfigRequired = computed(() => {
  if (!form.id) return true
  if (form.provider_key !== editingProviderKey.value) return true
  if (!editingConfigStored.value) return true
  return hasProviderConfigInput()
})

watch(selectedGroups, syncGroupPolicyFromSelection)

function openCreate() {
  editingProviderKey.value = ''
  editingConfigStored.value = false
  Object.assign(form, {
    id: 0,
    provider_key: props.providers[0]?.key || '',
    provider_config: {},
    remote_zone_id: '',
    domain: '',
    group_policy: '0',
    record_types: [...defaultRecordTypes],
    beian: 0,
    points_cost: 0,
    description: '',
  })
  selectedGroups.value = [0]
  resetProviderConfig()
  resetZones()
  dialogVisible.value = true
}

function openEdit(row: AdminDomain) {
  editingProviderKey.value = row.provider_key
  editingConfigStored.value = row.provider_config_stored
  Object.assign(form, { ...row, provider_config: {}, record_types: row.record_types.split(',').filter(Boolean) })
  selectedGroups.value = parseGroupPolicy(row.group_policy)
  resetProviderConfig()
  setCurrentZone(row.remote_zone_id, row.domain)
  dialogVisible.value = true
}

async function save() {
  syncGroupPolicyFromSelection()
  if (!form.provider_key || !form.remote_zone_id || !form.domain) {
    ElMessage.warning('请完整填写主域信息')
    return
  }
  if (!selectedZone.value) {
    ElMessage.warning('请从平台域名列表中选择主域')
    return
  }
  if (!currentProvider.value) {
    ElMessage.warning('请选择支持的 DNS 平台')
    return
  }
  if (providerConfigRequired.value) {
    const missing = currentProvider.value.fields.find((field) => field.required && !form.provider_config[field.name]?.trim())
    if (missing) {
      ElMessage.warning(`请填写 ${missing.label}`)
      return
    }
  }
  saving.value = true
  try {
    await saveAdminDomain({ ...form, provider_config: { ...form.provider_config } })
    ElMessage.success('主域已保存')
    dialogVisible.value = false
    emit('saved')
  } catch (error) {
    ElMessage.error(apiErrorMessage(error, '保存主域失败'))
  } finally {
    saving.value = false
  }
}

function resetProviderConfig() {
  for (const key of Object.keys(form.provider_config)) delete form.provider_config[key]
  for (const field of currentProvider.value?.fields || []) {
    form.provider_config[field.name] = ''
  }
}

function handleProviderChange() {
  resetProviderConfig()
  resetZones()
}

function resetZones() {
  selectedZone.value = ''
  zoneOptions.value = []
  form.remote_zone_id = ''
  form.domain = ''
}

function setCurrentZone(remoteZoneID: string, domain: string) {
  const zone = { id: remoteZoneID, domain: domain.toLowerCase() }
  zoneOptions.value = [zone]
  selectZone(zoneValue(zone))
}

async function loadZones() {
  if (!currentProvider.value) {
    ElMessage.warning('请选择 DNS 平台')
    return
  }
  if (providerConfigRequired.value) {
    const missing = currentProvider.value.fields.find((field) => field.required && !form.provider_config[field.name]?.trim())
    if (missing) {
      ElMessage.warning(`请填写 ${missing.label}`)
      return
    }
  }
  const configSnapshot = { ...form.provider_config }
  const selectedSnapshot = {
    zone: selectedZone.value,
    options: [...zoneOptions.value],
    remoteZoneID: form.remote_zone_id,
    domain: form.domain,
  }
  zonesLoading.value = true
  try {
    const response = await listProviderZones({
      key: form.provider_key,
      config: { ...form.provider_config },
      domain_id: form.id || undefined,
    })
    zoneOptions.value = response.data
    if (zoneOptions.value.length === 0) {
      restoreZoneSelection(selectedSnapshot)
      restoreProviderConfig(configSnapshot)
      ElMessage.warning('平台账号下没有可选择的域名')
      return
    }
    const current = zoneOptions.value.find((zone) => zone.id === form.remote_zone_id || zone.domain === form.domain)
    selectZone(zoneValue(current || zoneOptions.value[0]))
  } catch (error) {
    restoreZoneSelection(selectedSnapshot)
    restoreProviderConfig(configSnapshot)
    ElMessage.error(apiErrorMessage(error, '获取平台域名失败，请检查 DNS 配置'))
  } finally {
    zonesLoading.value = false
  }
}

function restoreZoneSelection(snapshot: { zone: string; options: ProviderZone[]; remoteZoneID: string; domain: string }) {
  selectedZone.value = snapshot.zone
  zoneOptions.value = snapshot.options
  form.remote_zone_id = snapshot.remoteZoneID
  form.domain = snapshot.domain
}

function restoreProviderConfig(snapshot: Record<string, string>) {
  for (const key of Object.keys(form.provider_config)) delete form.provider_config[key]
  for (const [key, value] of Object.entries(snapshot)) form.provider_config[key] = value
}

function selectZone(value: string) {
  selectedZone.value = value
  const zone = zoneOptions.value.find((item) => zoneValue(item) === value)
  if (!zone) return
  form.remote_zone_id = zone.id
  form.domain = zone.domain.toLowerCase()
}

function zoneValue(zone: ProviderZone) {
  return `${zone.id}@@${zone.domain}`
}

function hasProviderConfigInput() {
  return Object.values(form.provider_config).some((value) => value.trim() !== '')
}

function fieldLabel(field: ProviderField) {
  return field.required ? `${field.label} *` : field.label
}

function configPlaceholder(field: ProviderField) {
  if (form.id) return field.secret ? '留空保留已保存密钥' : '留空保留已保存配置'
  return field.description || field.label
}

function parseGroupPolicy(policy: string) {
  const ids = policy
    .split(',')
    .map((item) => Number(item.trim()))
    .filter((item) => Number.isInteger(item) && item >= 0)
  if (ids.length === 0 || ids.includes(0)) return [0]
  return [...new Set(ids)]
}

function syncGroupPolicyFromSelection(nextValue?: number[], previousValue?: number[]) {
  const next = nextValue || selectedGroups.value
  const previous = previousValue || []
  let normalized = next.filter((item) => Number.isInteger(item) && item >= 0)

  if (normalized.length === 0) {
    normalized = [0]
  } else if (normalized.includes(0) && normalized.length > 1) {
    normalized = previous.includes(0) ? normalized.filter((item) => item !== 0) : [0]
  }

  normalized = [...new Set(normalized)]
  if (normalized.join(',') !== selectedGroups.value.join(',')) selectedGroups.value = normalized
  form.group_policy = normalized.includes(0) ? '0' : normalized.join(',')
}

defineExpose({ openCreate, openEdit })
</script>

<style scoped>
.domain-form {
  display: grid;
  gap: 22px;
}

:deep(.domain-dialog) {
  max-height: calc(100vh - 48px);
  display: flex;
  flex-direction: column;
  margin-top: 5vh;
  margin-bottom: 24px;
  border-radius: 8px;
  overflow: hidden;
}

:deep(.domain-dialog .el-dialog__body) {
  padding: 20px 28px 0;
  overflow: auto;
}

:deep(.domain-dialog .el-dialog__footer) {
  flex-shrink: 0;
  padding: 18px 28px;
  border-top: 1px solid #e6eeee;
  background: #ffffff;
}

:deep(.domain-dialog .el-dialog__header) {
  display: flex;
  align-items: center;
  min-height: 64px;
  padding: 0 28px;
  border-bottom: 1px solid #e6eeee;
  margin-right: 0;
}

:deep(.domain-dialog .el-dialog__headerbtn) {
  top: 15px;
  right: 18px;
}

.domain-dialog-title {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: #12212a;
}

.domain-dialog-title strong {
  font-size: 20px;
  line-height: 1.2;
}

.dialog-title-icon {
  width: 30px;
  height: 30px;
  display: inline-grid;
  place-items: center;
  border-radius: 8px;
  color: #087b63;
  background: #e3fbf3;
}

.dialog-section {
  display: grid;
  gap: 14px;
}

.section-heading {
  display: flex;
  align-items: center;
  gap: 10px;
}

.step-badge {
  width: 22px;
  height: 22px;
  display: inline-grid;
  place-items: center;
  border-radius: 999px;
  color: #ffffff;
  background: linear-gradient(135deg, #0db88d, #2196f3);
  font-size: 12px;
  font-weight: 900;
}

.section-heading h3 {
  margin: 0;
  color: #111827;
  font-size: 17px;
  font-weight: 800;
}

.provider-config-panel {
  display: grid;
  gap: 16px;
  border: 1px solid #d9e7ec;
  border-radius: 8px;
  padding: 18px;
  background:
    linear-gradient(180deg, rgba(246, 252, 252, 0.95), rgba(255, 255, 255, 0.98)),
    #ffffff;
  box-shadow: 0 12px 30px rgba(18, 34, 39, 0.05);
}

.subsection-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.subsection-title strong {
  color: #111827;
  font-size: 16px;
}

.subsection-title span {
  color: #087b63;
  font-size: 13px;
  font-weight: 800;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.config-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px 18px;
}

.two-columns {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.field-tip {
  margin: 6px 0 0;
  color: #64748b;
  font-size: 12px;
  line-height: 1.5;
}

.zone-id {
  float: right;
  max-width: 46%;
  overflow: hidden;
  color: #94a3b8;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.zone-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  padding-top: 2px;
}

.zone-actions span {
  color: #64748b;
  font-size: 13px;
}

.selected-zone-summary {
  position: relative;
  display: grid;
  gap: 5px;
  min-height: 88px;
  padding: 16px 18px;
  border: 1px solid #cde5f7;
  border-radius: 8px;
  background:
    linear-gradient(90deg, rgba(33, 150, 243, 0.08), rgba(13, 184, 141, 0.04)),
    #f8fcff;
  overflow: hidden;
}

.selected-zone-summary::after {
  content: "";
  position: absolute;
  right: 22px;
  top: 18px;
  width: 46px;
  height: 46px;
  border: 1px dashed rgba(33, 150, 243, 0.34);
  border-radius: 50%;
  opacity: 0.72;
}

.selected-zone-summary span {
  color: #64748b;
  font-size: 13px;
  font-weight: 700;
}

.selected-zone-summary strong {
  color: #111827;
  font-size: 18px;
}

.selected-zone-summary small {
  overflow-wrap: anywhere;
  color: #64748b;
}

@media (max-width: 720px) {
  .config-grid,
  .form-grid,
  .two-columns {
    grid-template-columns: 1fr;
  }

  :deep(.domain-dialog .el-dialog__body) {
    padding: 16px 16px 0;
  }

  :deep(.domain-dialog .el-dialog__footer) {
    padding: 14px 16px;
  }

  :deep(.domain-dialog .el-dialog__header) {
    padding: 0 16px;
  }
}
</style>
