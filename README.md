# KLDNS 4.0.2

KLDNS 是一个面向二级域名分发场景的 DNS 管理系统，适用于主域开放、用户自助申请前缀、解析记录维护、积分控制、审核流和统一运维管理。

当前版本已经完成前后台 `Vue 3 + Element Plus` 重构，并补齐了主域解析类型限制、备案状态、审核流、开放 API、操作日志和多平台 DNS 接入能力。

## 核心能力

- 多用户、多主域、多解析平台统一管理
- 用户前台自助新增、修改、删除解析记录
- 主域级别控制开放用户组、消耗积分、备案状态和说明文案
- 主域级别限制可用解析类型
- 主域级别支持自动通过或人工审核
- 支持开放 API、API Token 管理和审核状态查询
- 支持全站解析操作日志审计
- 支持自动检测、异常内容巡检和记录清理
- 前后台统一深色主题，适配桌面端和移动端

## 当前支持的解析平台

- DNSPod
- 阿里云 DNS
- DNS.com
- DNSLA
- DnsDun
- 西部数码
- 华为云 DNS
- 百度智能云 DNS
- Amazon Route 53
- Google Cloud DNS

## 技术栈

- Laravel
- Vue 3
- Element Plus
- MySQL

## 运行要求

- PHP >= 8.2
- MySQL 5.7+ 或兼容版本
- 需要开启伪静态
- 建议启用扩展：
  - OpenSSL
  - PDO
  - Mbstring
  - Tokenizer
  - XML
  - Ctype
  - JSON
  - BCMath

## 安装说明

1. 将项目部署到站点目录。
2. 确保入口为 `public/index.php`，或按当前项目部署方式正确指向 `public/`。
3. 配置伪静态。
4. 访问 `/install` 完成环境检测、数据库配置和初始化安装。
5. 安装完成后，先进入后台配置 DNS 接口，再导入主域并设置开放策略。

### Nginx

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### Apache

确认启用 `mod_rewrite`，并允许 `.htaccess` 生效。

## 升级说明

如果你是从旧版本升级到 `4.0.2`，建议至少确认以下内容：

1. 已执行对应升级 SQL。
2. `storage/install/version.php` 已更新到 `4.0.2`。
3. `kldns_domains` 表已存在以下字段：
   - `record_types`
   - `review_mode`
   - `beian`
4. 已创建以下新表：
   - `kldns_domain_record_reviews`
   - `kldns_api_tokens`
   - `kldns_operation_logs`
5. 已清理浏览器静态资源缓存。

如需手动补字段，可参考：

```sql
ALTER TABLE `kldns_domains`
ADD COLUMN `record_types` varchar(255) NOT NULL DEFAULT 'A,CNAME' AFTER `groups`,
ADD COLUMN `review_mode` tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `record_types`,
ADD COLUMN `beian` tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `review_mode`;
```

## 推荐初始化顺序

1. 在“接口配置”中接入 DNS 平台
2. 在“域名列表”中导入主域
3. 设置用户组、可解析类型、备案状态、审核模式和积分策略
4. 按需配置系统参数和自动检测
5. 最后开放注册和前台自助解析

## 平台配置说明

- 华为云 DNS：填写 `AccessKeyId` 和 `SecretAccessKey`
- 百度智能云 DNS：填写 `AccessKeyId` 和 `SecretAccessKey`
- Amazon Route 53：填写 `AccessKeyId` 和 `SecretAccessKey`，临时凭证再补 `SessionToken`
- Google Cloud DNS：可选填写 `ProjectId`，并粘贴完整服务账号 JSON 到 `ServiceAccountJson`
- 西部数码：填写 `Username` 和 `ApiPassword`

## 开放 API

用户可以在“用户中心 -> 开放 API”中创建令牌，并通过 `Authorization: Bearer <token>` 调用接口。

### API 列表

- `GET /api/v1/domains` 获取当前账号可用主域
- `GET /api/v1/records` 获取当前账号解析记录
- `GET /api/v1/reviews` 获取当前账号审核记录
- `POST /api/v1/records` 新增解析记录
- `PUT /api/v1/records/{id}` 修改解析记录
- `DELETE /api/v1/records/{id}` 删除解析记录

如果主域开启人工审核，API 发起的新增、修改、删除也会先进入审核流程。

## 目录结构

- `app/`：控制器、模型、服务和 DNS 平台适配器
- `resources/views/`：前后台 Blade 页面
- `public/js/main.js`：Vue 3 / Element Plus 公共运行层
- `public/css/style.css`：全站主题与组件样式
- `install/`：安装 SQL 与升级 SQL
- `storage/install/`：安装后的数据库配置与版本信息

## 4.0.2 版本更新

- 前后台统一重构到 Vue 3 + Element Plus
- 移除 jQuery 和 layer 依赖
- 全站表格、按钮、弹窗、表单样式统一
- 移动端布局、侧栏和弹窗交互优化
- 新增主域可用解析类型限制
- 新增主域备案状态配置与前后台展示
- 新增解析值前后端格式校验
- 新增主域人工审核流
- 新增用户审核记录页和后台审核处理页
- 新增用户 API Token 管理与开放 API
- 新增全站解析操作日志
- 新增西部数码接入支持
- 新增华为云 DNS、百度智能云 DNS、Amazon Route 53、Google Cloud DNS 接入支持
- Google Cloud DNS 支持直接粘贴服务账号 JSON

## 反馈

- GitHub: https://github.com/klsf/kldns
