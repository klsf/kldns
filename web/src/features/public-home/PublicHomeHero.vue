<template>
  <section class="landing-hero">
    <nav class="public-nav">
      <RouterLink class="public-brand" to="/">
        <span>KD</span>
        <strong>KLDNS</strong>
      </RouterLink>
      <div class="public-links">
        <a href="#capabilities">平台功能</a>
        <a href="#workflow">注册步骤</a>
        <RouterLink v-if="isLoggedIn" class="account-link" :to="accountLink">{{ username || '用户中心' }}</RouterLink>
        <template v-else>
          <RouterLink to="/login">登录</RouterLink>
          <RouterLink class="nav-cta" to="/register">注册账号</RouterLink>
        </template>
      </div>
    </nav>

    <section class="home-hero">
      <div class="hero-copy">
        <h1>二级域名分发与解析平台</h1>
        <p class="hero-text">
          用户自助注册二级域名、维护解析记录、生成开放 API Token；管理员统一配置主域、DNS 平台、注册审核、Turnstile 和操作审计。
        </p>
        <div class="hero-actions">
          <RouterLink class="primary-action" :to="primaryAction.to">{{ primaryAction.label }}</RouterLink>
          <RouterLink class="secondary-action" :to="secondaryAction.to">{{ secondaryAction.label }}</RouterLink>
        </div>
      </div>

      <div class="console-preview" aria-label="KLDNS 可注册主域预览">
        <div class="preview-header">
          <span>可注册主域</span>
          <b><i />开放申请</b>
        </div>

        <div class="domain-availability-list">
          <article v-for="domain in availableDomains" :key="domain.id" class="domain-availability-item">
            <strong>{{ domain.domain }}</strong>
            <span class="beian-chip" :class="{ warning: domain.beian !== 1 }">{{ domain.beian_text }}</span>
            <span class="domain-types">
              <em v-for="type in domain.record_types" :key="type">{{ type }}</em>
            </span>
          </article>
          <div v-if="!availableDomains.length" class="domain-empty">暂无开放主域</div>
        </div>
      </div>
    </section>
  </section>
</template>

<script setup lang="ts">
import type { Domain } from '../../types/domain'

defineProps<{
  accountLink: string
  availableDomains: Domain[]
  isLoggedIn: boolean
  primaryAction: { to: string; label: string }
  secondaryAction: { to: string; label: string }
  username?: string
}>()
</script>

<style scoped>
.landing-hero {
  position: relative;
  overflow: hidden;
  color: #eef9f7;
  background:
    linear-gradient(120deg, rgba(54, 168, 255, 0.16), transparent 35%),
    linear-gradient(145deg, #071b20 0%, #0a2a2d 48%, #101b19 100%);
  isolation: isolate;
}

.landing-hero::before,
.landing-hero::after {
  content: "";
  position: absolute;
  pointer-events: none;
  z-index: -1;
}

.landing-hero::before {
  inset: 0;
  background:
    linear-gradient(rgba(123, 217, 218, 0.075) 1px, transparent 1px),
    linear-gradient(90deg, rgba(123, 217, 218, 0.075) 1px, transparent 1px);
  background-size: 36px 36px;
  mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.82), rgba(0, 0, 0, 0.18));
}

.landing-hero::after {
  right: -22vw;
  top: 94px;
  width: 72vw;
  height: 360px;
  border: 1px solid rgba(87, 229, 219, 0.2);
  transform: rotate(-12deg);
  background:
    repeating-linear-gradient(90deg, rgba(45, 230, 178, 0.16) 0 2px, transparent 2px 74px),
    linear-gradient(90deg, rgba(42, 202, 196, 0.14), rgba(28, 57, 57, 0.04));
  box-shadow: inset 0 0 48px rgba(52, 213, 203, 0.08);
}

.public-nav {
  position: relative;
  z-index: 2;
  min-height: 76px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  width: min(1180px, calc(100% - 32px));
  margin: 0 auto;
}

.public-brand,
.public-links,
.hero-actions {
  display: flex;
  align-items: center;
}

.public-brand {
  gap: 12px;
  color: #f5fffc;
  text-decoration: none;
}

.public-brand span {
  width: 42px;
  height: 42px;
  display: grid;
  place-items: center;
  border-radius: 8px;
  color: #06201a;
  background: linear-gradient(135deg, #41f4b8, #43c8ff);
  font-weight: 900;
  box-shadow: 0 12px 30px rgba(47, 240, 178, 0.24);
}

.public-brand strong {
  font-size: 20px;
}

.public-links {
  gap: 10px;
}

.public-links a {
  min-height: 38px;
  display: inline-flex;
  align-items: center;
  padding: 0 12px;
  border-radius: 8px;
  color: #d8ebe8;
  text-decoration: none;
  font-weight: 800;
}

.public-links a:hover {
  background: rgba(255, 255, 255, 0.08);
}

.public-links .nav-cta,
.public-links .account-link {
  color: #06211b;
  background: #36e9ad;
  font-weight: 900;
}

.home-hero {
  position: relative;
  z-index: 1;
  width: min(1180px, calc(100% - 32px));
  display: grid;
  align-content: center;
  gap: 28px;
  margin: 0 auto;
  padding: 72px 0 58px;
}

.hero-copy {
  max-width: 880px;
}

.hero-copy h1 {
  max-width: 900px;
  margin: 0;
  color: #f8fffd;
  font-size: clamp(34px, 5.2vw, 58px);
  line-height: 1.08;
  letter-spacing: 0;
}

.hero-text {
  max-width: 760px;
  margin: 20px 0 0;
  color: #bdd4d1;
  font-size: 17px;
  line-height: 1.75;
}

.hero-actions {
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 28px;
}

.primary-action,
.secondary-action {
  min-height: 52px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 24px;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 800;
}

.primary-action {
  color: #05221b;
  background: linear-gradient(135deg, #41f4b8, #1bd09d);
  box-shadow: 0 16px 34px rgba(22, 214, 164, 0.22);
}

.secondary-action {
  color: #e8f6f4;
  border: 1px solid rgba(96, 222, 228, 0.42);
  background: rgba(8, 31, 42, 0.58);
}

.console-preview {
  position: relative;
  padding: 22px;
  border: 1px solid rgba(215, 234, 232, 0.86);
  border-radius: 8px;
  color: #13282d;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(249, 253, 252, 0.96));
  box-shadow:
    0 28px 70px rgba(3, 18, 22, 0.24),
    inset 0 1px 0 rgba(255, 255, 255, 0.78);
  backdrop-filter: blur(18px);
}

.preview-header {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 14px;
  color: #31454a;
  font-weight: 800;
  padding-bottom: 14px;
  border-bottom: 1px solid #e2ecea;
}

.preview-header span {
  font-size: 26px;
  color: #13242a;
}

.preview-header b {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  justify-self: end;
  min-height: 36px;
  padding: 0 12px;
  border-radius: 8px;
  color: #0fb98b;
  background: #e6fbf4;
}

.preview-header i {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #11c795;
}

.domain-availability-list {
  display: grid;
  gap: 10px;
  margin-top: 14px;
}

.domain-availability-item {
  min-height: 76px;
  display: grid;
  grid-template-columns: minmax(210px, 1fr) minmax(92px, auto) minmax(320px, 1.4fr);
  align-items: center;
  gap: 18px;
  padding: 13px 14px;
  border: 1px solid #e2ecea;
  border-radius: 8px;
  background:
    linear-gradient(90deg, rgba(233, 251, 245, 0.68), transparent 42%),
    #ffffff;
}

.domain-availability-item strong {
  color: #13242a;
  font-size: 18px;
}

.domain-empty {
  min-height: 82px;
  display: grid;
  place-items: center;
  border: 1px dashed #cddcde;
  border-radius: 8px;
  color: #61757b;
  background: rgba(255, 255, 255, 0.68);
  font-weight: 800;
}

.beian-chip,
.domain-types em {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  font-style: normal;
  font-weight: 900;
  white-space: nowrap;
}

.beian-chip {
  justify-self: start;
  min-height: 30px;
  padding: 0 12px;
  color: #0fb98b;
  border: 1px solid rgba(15, 185, 139, 0.3);
  background: #e9fbf5;
}

.beian-chip.warning {
  color: #dc8c00;
  border-color: rgba(255, 189, 74, 0.46);
  background: #fff6e7;
}

.domain-types {
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
  justify-content: flex-end;
}

.domain-types em {
  min-height: 30px;
  padding: 0 10px;
  color: #40545b;
  background: #eef4f4;
}

@media (max-width: 880px) {
  .public-nav {
    align-items: flex-start;
    flex-direction: column;
    padding: 16px 0;
  }

  .public-links {
    width: 100%;
    overflow-x: auto;
  }

  .home-hero {
    grid-template-columns: 1fr;
    padding: 42px 0 40px;
  }

  .hero-copy h1 {
    font-size: 40px;
  }

  .landing-hero::after {
    right: -52vw;
    top: 156px;
    width: 120vw;
    opacity: 0.42;
  }

  .domain-availability-item {
    grid-template-columns: 1fr;
    align-items: start;
    gap: 8px;
  }

  .domain-types {
    justify-content: flex-start;
  }
}

@media (max-width: 520px) {
  .public-links a {
    min-height: 34px;
    padding: 0 10px;
  }

  .console-preview {
    padding: 14px;
  }

  .preview-header span {
    font-size: 22px;
  }

  .hero-copy h1 {
    font-size: 30px;
  }

  .hero-text {
    font-size: 15px;
  }

  .primary-action,
  .secondary-action {
    width: 100%;
  }
}

@media (max-width: 360px) {
  .hero-copy h1 {
    font-size: 27px;
  }
}
</style>
