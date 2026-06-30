# KLDNS

KLDNS is a DNS management system for second-level domain distribution. This refactor uses:

- Backend: Go, Beego, SQLite3.
- Frontend: Vite, Vue 3, TypeScript, Element Plus.
- DNS providers: Cloudflare, DNSPod, Aliyun, DNS.com, DNSLA, DnsDun, West, HuaweiCloud, BaiduCloud, Route53, GoogleCloudDns.

## Layout

```text
main.go                  # Beego startup and database/migration bootstrap
conf/app.conf            # Runtime configuration
controllers/             # HTTP controllers
middlewares/             # Bearer auth and admin guard
models/                  # Domain models
repositories/            # SQLite access and migrations
services/                # Business rules and DNS write flow
pkg/dns/                 # Provider interface and adapters
migrations/              # SQLite migration scripts
web/                     # Vite + Vue 3 frontend
```

## Configuration

Default config is in `conf/app.conf`.

```ini
appname = kldns
httpport = 8080
runmode = dev
copyrequestbody = true

db_path = data/kldns.db
db_busy_timeout_ms = 5000
db_wal = true
secret_key = change-me-before-production-kldns-secret
```

Change `secret_key` before production. It is used for protected settings such as DNS provider credentials and Turnstile secrets. Do not commit database files, private keys, tokens, or provider secrets.

SQLite startup explicitly enables:

- `PRAGMA foreign_keys = ON`
- `PRAGMA busy_timeout = <db_busy_timeout_ms>`
- `PRAGMA journal_mode = WAL` when `db_wal = true`

## Database Migrations

Migrations run automatically from the embedded `migrations/` files during `main.go` startup and are tracked in `schema_migrations`.

Current migrations:

- `20260616190000_initial_schema.sql` initializes the current schema for fresh databases.

Any schema change must add a new timestamped migration file. Do not rely on model-only changes.

## Development

Backend:

```powershell
go test ./...
go run .
```

Frontend:

```powershell
cd web
npm run typecheck
npm run lint
npm run build
npm run dev
```

The frontend dev server proxies are not configured in code; for integrated local use, run Beego on `/api/v1` and configure deployment or Vite proxy as needed.

## Packaging

The production binary embeds both SQLite migrations and the built frontend. Build the frontend first, then compile Go:

```powershell
cd web
npm run build
cd ..
go build -o kldns.exe .
```

The resulting binary can be deployed without copying `migrations/` or `web/dist/`. Runtime configuration such as `conf/app.conf` and writable data such as `data/kldns.db` still remain external.

## API Summary

All business APIs use `/api/v1`.

Public:

- `GET /health`
- `POST /install/admin` (empty username/password defaults to `admin` / `123456` on a fresh database)
- `POST /auth/register`
- `POST /auth/login`
- `POST /admin/auth/login`
- `GET /settings/turnstile`

Authenticated with `Authorization: Bearer <token>`:

- `GET /auth/me`
- `PUT /auth/password`
- `GET /domains`
- `GET /subdomains`
- `POST /subdomains`
- `GET /records`
- `POST /records`
- `PUT /records/:id`
- `DELETE /records/:id`
- `GET /tokens`
- `POST /tokens`
- `DELETE /tokens/:id`

Admin only:

- `GET /admin/users`
- `PUT /admin/users/:id`
- `GET|POST /admin/groups`
- `DELETE /admin/groups/:id`
- `GET|POST /admin/domains`
- `PUT|DELETE /admin/domains/:id`
- `GET /admin/dns-providers` (provider metadata for domain forms)
- `GET /admin/records`
- `POST /admin/records`
- `PUT|DELETE /admin/records/:id`
- `GET /admin/subdomains`
- `GET /admin/logs`
- `GET|PUT /admin/settings`

Responses use the common envelope:

```json
{
  "code": "ok",
  "message": "ok",
  "data": {}
}
```

Users spend points once to register a second-level domain under an open main domain. Record create, update, and delete operations under that registered domain are direct DNS writes and do not create review records.

## DNS Write Consistency

DNS write paths are service-layer operations. Controllers parse input and delegate to services; repositories persist local state; provider adapters only handle vendor API details.

For record create, update, delete, the flow must preserve these rules:

- Local validation failure does not call remote DNS.
- Remote DNS failure does not write local success state or deduct points.
- Remote success followed by local failure must enqueue or attempt compensation.
- Points, registered subdomains, records, and operation logs are kept in the same local transaction where applicable.
- Retry paths must not duplicate records or points deductions.
- Logs must include traceable operator/source/domain/action/provider/remote record id context without secrets.

## DNS Provider Config Keys

Provider credentials are stored encrypted in SQLite through `secret_key`. Each managed domain stores its own provider configuration so the same DNS platform can be used with different accounts or zones. The `dns_providers` table is retained for provider metadata and migration compatibility; operators configure credentials from the admin domain form.

- `Cloudflare`: `ApiToken`
- `Dnspod`: `ID`, `Token`
- `Aliyun`: `AccessKeyId`, `AccessKeySecret`
- `DnsCom`: provider-specific API credentials from the adapter metadata
- `DnsLa`: provider-specific API credentials from the adapter metadata
- `DnsDun`: provider-specific API credentials from the adapter metadata
- `West`: provider-specific API credentials from the adapter metadata
- `HuaweiCloud`: `AccessKeyId`, `SecretAccessKey`
- `BaiduCloud`: `AccessKeyId`, `SecretAccessKey`
- `Route53`: `AccessKeyId`, `SecretAccessKey`, optional `SessionToken`
- `GoogleCloudDns`: optional `ProjectId`, `ServiceAccountJson`

Never log or expose raw provider configuration. Adapter tests assert that common authentication errors do not leak signatures, Authorization headers, private keys, or secret values.
