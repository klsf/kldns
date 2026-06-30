<template>
  <section id="capabilities" class="capability-band">
    <article>
      <Globe2 :size="22" />
      <strong>二级域名自助注册</strong>
      <span>按用户组开放主域，支持积分成本、备案说明和可用记录类型限制。</span>
    </article>
    <article>
      <ShieldCheck :size="22" />
      <strong>解析直写与补偿</strong>
      <span>解析记录直接写入 DNS 平台，本地失败会进入补偿任务，便于追踪修复。</span>
    </article>
    <article>
      <KeyRound :size="22" />
      <strong>开放 API 与审计</strong>
      <span>会员可创建 Bearer Token，后台统一查看用户、域名、解析和操作日志。</span>
    </article>
  </section>
</template>

<script setup lang="ts">
import { Globe2, KeyRound, ShieldCheck } from 'lucide-vue-next'
</script>

<style scoped>
.capability-band {
  width: min(1180px, calc(100% - 32px));
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
  margin: 0 auto;
  padding: 36px 0 24px;
  color: #182b31;
}

.capability-band article {
  min-height: 160px;
  display: grid;
  align-content: start;
  gap: 10px;
  padding: 20px;
  border: 1px solid #e1ebea;
  border-radius: 8px;
  background: #ffffff;
  box-shadow: 0 14px 36px rgba(20, 42, 48, 0.06);
}

.capability-band svg {
  box-sizing: content-box;
  padding: 8px;
  border-radius: 50%;
  color: #0ea57e;
  background: #dff9f0;
}

.capability-band span {
  color: #61757b;
}

@media (max-width: 880px) {
  .capability-band {
    grid-template-columns: 1fr;
  }
}
</style>
