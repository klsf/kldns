<template>
  <div class="resource-card">
    <el-table v-loading="loading" :data="domains" class="responsive-table">
      <el-table-column prop="domain" label="主域" min-width="180" />
      <el-table-column label="平台" min-width="130">
        <template #default="{ row }">
          <el-tag class="compact-tag" effect="plain">{{ providerLabel(row.provider_key) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="记录类型" min-width="220" show-overflow-tooltip>
        <template #default="{ row }">{{ row.record_types }}</template>
      </el-table-column>
      <el-table-column prop="points_cost" label="注册积分" width="110" />
      <el-table-column label="操作" width="154" fixed="right">
        <template #default="{ row }">
          <div class="table-actions">
            <el-button text type="primary" @click="$emit('edit', row)">编辑</el-button>
            <el-button text type="primary" :loading="syncingId === row.id" @click="$emit('sync', row)">同步</el-button>
            <el-button text type="danger" @click="$emit('remove', row)">删除</el-button>
          </div>
        </template>
      </el-table-column>
    </el-table>
    <div v-if="total > pageSize" class="resource-pagination">
      <span>共 {{ total }} 条</span>
      <el-pagination v-model:current-page="currentPage" :page-size="pageSize" layout="prev, pager, next" :total="total" />
      <el-select v-model="currentPageSize" class="page-size-select">
        <el-option label="10 条/页" :value="10" />
        <el-option label="20 条/页" :value="20" />
      </el-select>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { AdminDomain } from '../../api/admin'

const props = defineProps<{
  domains: AdminDomain[]
  loading: boolean
  page: number
  pageSize: number
  total: number
  syncingId: number
  providerLabel: (key: string) => string
}>()

const emit = defineEmits<{
  edit: [row: AdminDomain]
  remove: [row: AdminDomain]
  sync: [row: AdminDomain]
  'update:page': [value: number]
  'update:pageSize': [value: number]
}>()

const currentPage = computed({
  get: () => props.page,
  set: (value: number) => emit('update:page', value),
})

const currentPageSize = computed({
  get: () => props.pageSize,
  set: (value: number) => emit('update:pageSize', value),
})
</script>
