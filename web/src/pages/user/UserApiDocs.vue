<template>
  <section class="page-stack api-docs-page">
    <header class="page-header page-header-with-action">
      <div>
        <h1>API 文档</h1>
        <p class="resource-note">开放 API 仅支持查询已注册二级域名，以及新增、编辑、删除解析记录。</p>
      </div>
      <el-button @click="$router.push('/home/tokens')"><KeyRound :size="16" />管理令牌</el-button>
    </header>

    <section class="doc-grid">
      <article class="resource-card doc-panel">
        <div class="panel-title">
          <ShieldCheck :size="20" />
          <h2>鉴权</h2>
        </div>
        <p>所有开放 API 都使用用户中心创建的 API Token，放在请求头中。</p>
        <pre><code>Authorization: Bearer &lt;API_TOKEN&gt;</code></pre>
        <p>API Token 不能访问用户资料、积分、令牌管理、域名注册和后台接口。</p>
      </article>

      <article class="resource-card doc-panel">
        <div class="panel-title">
          <Braces :size="20" />
          <h2>响应格式</h2>
        </div>
        <pre><code>{
  "code": "OK",
  "message": "",
  "data": {}
}</code></pre>
        <p>失败时 HTTP 状态码和 <code>code</code> 会同时表达错误类型。</p>
      </article>
    </section>

    <section class="resource-card endpoint-panel">
      <div class="endpoint-head">
        <span class="method get">GET</span>
        <strong>/api/subdomains</strong>
      </div>
      <p>查询当前账号已注册的二级域名列表。解析记录写入前，先从这里获取 <code>subdomain_id</code> 和支持的 <code>record_types</code>。</p>
      <div class="doc-table">
        <div><strong>status</strong><span>可选。传 1 只返回正常可解析的二级域名。</span></div>
        <div><strong>keyword</strong><span>可选。按二级域名、主域、用途或驳回原因搜索。</span></div>
      </div>
      <pre><code>curl -H "Authorization: Bearer $KLDNS_TOKEN" \
  "https://example.com/api/subdomains?status=1"</code></pre>
      <pre><code>{
  "code": "OK",
  "data": [
    {
      "id": 12,
      "did": 3,
      "name": "demo",
      "full_domain": "demo.example.com",
      "status": 1,
      "domain": "example.com",
      "record_types": ["A", "AAAA", "CNAME", "TXT"],
      "record_count": 2
    }
  ]
}</code></pre>
    </section>

    <section class="resource-card endpoint-panel">
      <div class="endpoint-head">
        <span class="method post">POST</span>
        <strong>/api/records</strong>
      </div>
      <p>为已注册且审核通过的二级域名新增解析记录。</p>
      <div class="doc-table">
        <div><strong>subdomain_id</strong><span>必填。来自二级域名列表的 <code>id</code>。</span></div>
        <div><strong>name</strong><span>必填。主机记录，支持 <code>@</code> 或相对前缀如 <code>www</code>。</span></div>
        <div><strong>type</strong><span>必填。必须在该主域支持的 <code>record_types</code> 内。</span></div>
        <div><strong>value</strong><span>必填。解析值，会按记录类型做后端校验。</span></div>
        <div><strong>line_id</strong><span>可选。默认传 <code>0</code>。</span></div>
      </div>
      <pre><code>curl -X POST "https://example.com/api/records" \
  -H "Authorization: Bearer $KLDNS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"subdomain_id":12,"name":"www","type":"A","value":"203.0.113.10","line_id":"0"}'</code></pre>
      <pre><code>{
  "code": "OK",
  "data": { "mode": "direct" }
}</code></pre>
    </section>

    <section class="resource-card endpoint-panel">
      <div class="endpoint-head">
        <span class="method put">PUT</span>
        <strong>/api/records/:id</strong>
      </div>
      <p>编辑当前账号名下的解析记录，不能跨二级域名修改归属。</p>
      <pre><code>curl -X PUT "https://example.com/api/records/25" \
  -H "Authorization: Bearer $KLDNS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"subdomain_id":12,"name":"www","type":"A","value":"203.0.113.20","line_id":"0"}'</code></pre>
      <pre><code>{
  "code": "OK",
  "data": { "mode": "direct" }
}</code></pre>
    </section>

    <section class="resource-card endpoint-panel">
      <div class="endpoint-head">
        <span class="method delete">DELETE</span>
        <strong>/api/records/:id</strong>
      </div>
      <p>删除当前账号名下的解析记录。删除会同步调用 DNS 平台，失败时不会写入本地成功状态。</p>
      <pre><code>curl -X DELETE "https://example.com/api/records/25" \
  -H "Authorization: Bearer $KLDNS_TOKEN"</code></pre>
      <pre><code>{
  "code": "OK",
  "data": { "mode": "direct" }
}</code></pre>
    </section>

    <section class="resource-card doc-panel">
      <div class="panel-title">
        <Terminal :size="20" />
        <h2>常见错误码</h2>
      </div>
      <div class="error-grid">
        <span>UNAUTHORIZED</span><p>缺少 Token、Token 不存在或已过期。</p>
        <span>FORBIDDEN</span><p>账号未审核、API Token 访问了不允许的接口，或记录类型不被主域支持。</p>
        <span>INVALID_ARGUMENT</span><p>请求 JSON、主机记录、记录值或参数格式不正确。</p>
        <span>NOT_FOUND</span><p>二级域名或解析记录不存在，或不属于当前账号。</p>
        <span>CONFLICT</span><p>记录冲突，例如同名 CNAME 与其他类型冲突。</p>
        <span>DNS_PROVIDER_FAILED</span><p>DNS 平台写入失败。</p>
      </div>
    </section>
  </section>
</template>

<script setup lang="ts">
import { Braces, KeyRound, ShieldCheck, Terminal } from 'lucide-vue-next'
</script>

<style scoped>
.api-docs-page {
  width: 100%;
}

.doc-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.doc-panel,
.endpoint-panel {
  display: grid;
  gap: 14px;
  padding: 20px;
}

.panel-title,
.endpoint-head {
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}

.panel-title {
  color: var(--accent-strong);
}

.panel-title h2 {
  margin: 0;
  color: #17282d;
  font-size: 18px;
}

.doc-panel p,
.endpoint-panel p {
  margin: 0;
  color: var(--muted);
  line-height: 1.7;
}

pre {
  min-width: 0;
  margin: 0;
  overflow-x: auto;
  padding: 14px;
  border: 1px solid #d8e4e4;
  border-radius: 8px;
  color: #d8f7ef;
  background: #0b2028;
  line-height: 1.6;
}

code {
  font-family: "Cascadia Mono", "Consolas", monospace;
  font-size: 13px;
}

.endpoint-head {
  padding-bottom: 2px;
}

.endpoint-head strong {
  min-width: 0;
  overflow-wrap: anywhere;
  color: #14252a;
  font-family: "Cascadia Mono", "Consolas", monospace;
  font-size: 18px;
}

.method {
  min-width: 64px;
  padding: 4px 8px;
  border-radius: 8px;
  color: #ffffff;
  font-size: 12px;
  font-weight: 900;
  text-align: center;
}

.method.get {
  background: #1674d1;
}

.method.post {
  background: var(--accent-strong);
}

.method.put {
  background: #a15c00;
}

.method.delete {
  background: #b4232a;
}

.doc-table {
  display: grid;
  gap: 8px;
}

.doc-table div {
  display: grid;
  grid-template-columns: 128px minmax(0, 1fr);
  gap: 12px;
  padding: 10px 12px;
  border: 1px solid var(--line);
  border-radius: 8px;
  background: #f7fbfb;
}

.doc-table strong,
.error-grid span {
  color: #17282d;
  font-family: "Cascadia Mono", "Consolas", monospace;
}

.doc-table span {
  min-width: 0;
  color: var(--muted);
  line-height: 1.6;
}

.error-grid {
  display: grid;
  grid-template-columns: 190px minmax(0, 1fr);
  gap: 10px 14px;
}

.error-grid p {
  margin: 0;
}

@media (max-width: 780px) {
  .doc-grid {
    grid-template-columns: 1fr;
  }

  .doc-panel,
  .endpoint-panel {
    padding: 16px;
  }

  .doc-table div,
  .error-grid {
    grid-template-columns: 1fr;
  }
}
</style>
