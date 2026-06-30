<template>
  <section id="workflow" class="workflow-strip">
    <span>选择开放主域</span>
    <span>注册二级域名</span>
    <span>维护解析记录</span>
    <span>API 与日志追踪</span>
  </section>
</template>

<style scoped>
.workflow-strip {
  width: min(1180px, calc(100% - 32px));
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  margin: 0 auto;
  padding: 0 0 42px;
  color: #253941;
}

.workflow-strip span {
  position: relative;
  padding: 18px 14px 14px 48px;
  border-top: 1px solid #dfe9e9;
  font-weight: 800;
}

.workflow-strip span::before {
  content: "01";
  position: absolute;
  left: 0;
  top: 12px;
  width: 30px;
  height: 30px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  color: #065142;
  background: #91f2d3;
  font-size: 12px;
}

.workflow-strip span:nth-child(2)::before {
  content: "02";
}

.workflow-strip span:nth-child(3)::before {
  content: "03";
}

.workflow-strip span:nth-child(4)::before {
  content: "04";
}

@media (max-width: 880px) {
  .workflow-strip {
    grid-template-columns: 1fr;
  }
}
</style>
