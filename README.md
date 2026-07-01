# KLDNS

KLDNS is a DNS management system for second-level domain distribution. This refactor uses:

- Backend: Go, Gin, GORM, SQLite3.
- Frontend: Vite, Vue 3, TypeScript, Element Plus.
- DNS providers: Cloudflare, DNSPod, Aliyun, DNS.com, DNSLA, DnsDun, West, HuaweiCloud, BaiduCloud, Route53, GoogleCloudDns.

## Layout

```text
main.go                  # Gin startup and database/migration bootstrap
config.yaml              # Runtime configuration
config.example.yaml      # Example runtime configuration
routes/                  # Gin route registration
controllers/             # HTTP controllers
middleware/              # Bearer auth and admin guard
models/                  # Domain models
repositories/            # GORM-backed SQLite access and migrations
services/                # Business rules and DNS write flow
pkg/dns/                 # Provider interface and adapters
migrations/              # SQLite migration scripts
web/                     # Vite + Vue 3 frontend
```

## Configuration

Default config is in `config.yaml` at the repository or deployment root. `config.example.yaml` provides a starter template. Set `KLDNS_CONFIG` to load a different file.

```yaml
app:
  name: kldns
  port: 8004
  mode: dev

database:
  path: data/kldns.db
  busy_timeout_ms: 5000
  wal: true

security:
  secret_key: change-me-before-production-kldns-secret
```

Change `security.secret_key` before production. It is used for protected settings such as DNS provider credentials and Turnstile secrets. Do not commit database files, private keys, tokens, or provider secrets.

SQLite startup explicitly enables:

- `PRAGMA foreign_keys = ON`
- `PRAGMA busy_timeout = <database.busy_timeout_ms>`
- `PRAGMA journal_mode = WAL` when `database.wal = true`

The runtime database handle is opened through GORM with a pure Go SQLite driver. Existing complex repository queries may keep raw SQL through the GORM-managed connection; new data access code should prefer GORM APIs unless raw SQL is clearer for joins, constraints, or transactional DNS consistency flows.

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

The frontend dev server proxies `/api` to the Gin server on `127.0.0.1:8004`.

## Packaging

The production binary embeds both SQLite migrations and the built frontend. Build the frontend first, then compile Go:

```powershell
cd web
npm run build
cd ..
go build -o kldns.exe .
```

The resulting binary can be deployed without copying `migrations/` or `web/dist/`. Runtime configuration such as `config.yaml` and writable data such as `data/kldns.db` still remain external.

## API Summary

All business APIs use `/api/v1`.

Public:

- `GET /api/v1/health`
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
- `POST /subdomains` (when the selected domain requires review, include `purpose`)
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
- `POST /admin/subdomains/:id/approve`
- `POST /admin/subdomains/:id/reject`
- `GET /admin/logs`
- `GET|PUT /admin/settings`

Responses use the common envelope:

```json
{
  "code": "OK",
  "message": "",
  "data": {}
}
```

Users spend points once to register a second-level domain under an open main domain. A main domain may require registration review; in that mode the user must submit a purpose, points are held at submission time, and a rejected or user-cancelled pending application refunds the held points. Record create, update, and delete operations under an approved registered domain are direct DNS writes and do not create review records.

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
