# High Level Design: ActiveCollab SMS Notification System

**Date:** 2026-03-20

**Stack:** Laravel · React + Inertia.js · PostgreSQL · Redis · Laravel Cloud · Cloudflare

---

## 1. System Overview

A multi-tenant SaaS tool that listens for ActiveCollab project activity via webhooks, stores updates per tenant, and sends SMS summaries to each client at up to 3 scheduled windows per day based on the client's local timezone.

**Tenancy Model:** Single domain · Single PostgreSQL database · Row-level isolation via `tenant_id`
All tenants share one database. Every table carries `tenant_id`. Queries are scoped automatically via Eloquent global scopes enforced in middleware.

**Core Actors:**

| Actor                 | Role                                                   |
| --------------------- | ------------------------------------------------------ |
| **Super Admin**       | Provisions and manages tenants                         |
| **Tenant Admin**      | Manages clients, projects, views logs                  |
| **Client**            | Receives SMS (passive, no system access)               |
| **ActiveCollab**      | Sends webhook events on comment/task activity          |
| **Laravel Scheduler** | Runs every minute, dispatches due notification windows |
| **Queue Worker**      | Processes webhook jobs and SMS jobs asynchronously     |

---

## 2. System Architecture

```
┌──────────────────────────────────────────────────────────────────────────┐
│                          EXTERNAL LAYER                                  │
│                                                                          │
│  ┌─────────────────┐   ┌──────────────────┐   ┌──────────────────────┐  │
│  │  ActiveCollab   │   │  Tenant Admin    │   │  Super Admin         │  │
│  │  (Webhook src)  │   │  (Browser)       │   │  (Browser)           │  │
│  └────────┬────────┘   └────────┬─────────┘   └──────────┬───────────┘  │
└───────────┼─────────────────────┼────────────────────────┼──────────────┘
            │                     │                        │
            ▼                     ▼                        ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                    CLOUDFLARE (SSL + CDN + DDoS)                         │
│                    Single Domain: app.domain.com                         │
└───────────────────────────────────┬──────────────────────────────────────┘
                                    │ HTTPS
                                    ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                         LARAVEL CLOUD                                    │
│                                                                          │
│  ┌────────────────────────────────────────────────────────────────────┐  │
│  │  Application Servers (auto-scaling)                                │  │
│  │                                                                    │  │
│  │  ┌─────────────────────────────────────────────────────────────┐  │  │
│  │  │  HTTP LAYER (Routes)                                        │  │  │
│  │  │  routes/web.php          routes/api.php                    │  │  │
│  │  │  Inertia SSR pages       POST /api/webhook/{tenantId}      │  │  │
│  │  │  /admin/*                                                   │  │  │
│  │  │  /super-admin/*                                             │  │  │
│  │  └─────────────────────────────────────────────────────────────┘  │  │
│  │                                                                    │  │
│  │  ┌─────────────────────────────────────────────────────────────┐  │  │
│  │  │  MIDDLEWARE LAYER                                           │  │  │
│  │  │  SetTenantFromSession   ← admin UI routes                  │  │  │
│  │  │  SetTenantFromPath      ← webhook routes                   │  │  │
│  │  │  EnforceTenantScope     ← applies Eloquent global scope    │  │  │
│  │  │  RoleMiddleware         ← super_admin / tenant_admin       │  │  │
│  │  └─────────────────────────────────────────────────────────────┘  │  │
│  │                                                                    │  │
│  │  ┌─────────────────────────────────────────────────────────────┐  │  │
│  │  │  SERVICE LAYER (app/Services/)                              │  │  │
│  │  │  WebhookService         NotificationService                │  │  │
│  │  │  - validateSignature()  - dispatch(client, message)        │  │  │
│  │  │  - logAndQueue()        - resolveChannels()                │  │  │
│  │  │                                                             │  │  │
│  │  │  UpdateService          ClientSchedulerService             │  │  │
│  │  │  - parsePayload()       - getDueWindows()                  │  │  │
│  │  │  - storeUpdate()        - computeNextRunAt(client, slot)   │  │  │
│  │  │  - shortenUrl()         - getSincePeriod(client, slot)     │  │  │
│  │  └─────────────────────────────────────────────────────────────┘  │  │
│  │                                                                    │  │
│  │  ┌─────────────────────────────────────────────────────────────┐  │  │
│  │  │  NOTIFICATION CHANNELS (app/Channels/)                      │  │  │
│  │  │  NotificationChannelInterface                               │  │  │
│  │  │       ├── SmsChannel (Twilio)         ← active             │  │  │
│  │  │       ├── SlackChannel                ← future             │  │  │
│  │  │       └── EmailChannel                ← future             │  │  │
│  │  └─────────────────────────────────────────────────────────────┘  │  │
│  │                                                                    │  │
│  │  ┌─────────────────────────────────────────────────────────────┐  │  │
│  │  │  JOB LAYER (app/Jobs/)                                      │  │  │
│  │  │  ProcessWebhookJob     → queue: webhooks                   │  │  │
│  │  │  SendWindowSummaryJob  → queue: notifications              │  │  │
│  │  └─────────────────────────────────────────────────────────────┘  │  │
│  └────────────────────────────────────────────────────────────────────┘  │
│                                                                          │
│  ┌────────────────────┐  ┌─────────────────────────────────────────┐    │
│  │  Queue Workers     │  │  Laravel Scheduler                      │    │
│  │  (auto-scaling)    │  │  Runs every minute                      │    │
│  │  webhooks queue    │  │  Queries due client notification windows │    │
│  │  notifications     │  └─────────────────────────────────────────┘    │
│  │  queue             │                                                  │
│  └────────────────────┘                                                  │
│                                                                          │
│  ┌──────────────────────────┐   ┌──────────────────────────────────┐    │
│  │  Managed Redis           │   │  Managed PostgreSQL              │    │
│  │  Sessions, Cache         │   │  Single DB, all tenants          │    │
│  │  Queue backend           │   │  Row-level isolation             │    │
│  └──────────────────────────┘   └──────────────────────────────────┘    │
└──────────────────────────────────────────────────────────────────────────┘
           │                                      │
           ▼                                      ▼
┌─────────────────────┐              ┌────────────────────────────────┐
│  Twilio SMS API     │              │  Bit.ly API                    │
│  → Client phones    │              │  Task URL shortening           │
└─────────────────────┘              └────────────────────────────────┘
```

---

## 3. Multi-Tenancy Design

### Model: Single Domain + Single Database + Row-Level Isolation

```
app.domain.com  ─── one domain, one database
                         │
                         ├── tenant_id: aaa  (all records scoped)
                         ├── tenant_id: bbb
                         └── tenant_id: ccc
```

Tenant identification:

- **Admin UI routes** — resolved from authenticated user's `tenant_id` in session
- **Webhook routes** — resolved from `{tenantId}` path parameter

### Isolation Enforcement

```
Every request sets tenant context in middleware
        │
        ▼
TenantScope (Eloquent Global Scope)
  → Automatically appends WHERE tenant_id = ? to every query
  → Applied to: Client, Update, NotificationLog, WebhookLog,
                ClientNotificationWindow

Manual override requires explicit ->withoutGlobalScope() call
Super admin bypasses scope only on designated super-admin routes
```

### Database Schema

**All tables in a single PostgreSQL database.**

```
tenants
  id                  uuid PK
  name                string
  ac_api_key          string (AES-256 encrypted)
  ac_webhook_secret   string (AES-256 encrypted)
  timezone            string  ← default timezone for new clients
  bitly_token         string (AES-256 encrypted, optional per-tenant)
  sms_enabled         boolean default true
  created_at

users
  id, tenant_id (FK, nullable for super_admin), email,
  password, role enum(super_admin | tenant_admin | member)

clients
  id
  tenant_id           FK → tenants
  name
  phone_number        string (E.164 format)
  project_id          string (ActiveCollab project ID)
  timezone            string (defaults to tenant.timezone on creation)
  sms_enabled         boolean default true
  created_at

client_notification_windows
  id
  tenant_id           FK → tenants  (for scoped queries)
  client_id           FK → clients
  window_slot         enum: morning | afternoon | evening
  scheduled_time      time (08:00 | 13:00 | 15:30)
  next_run_at         timestamptz (UTC, precomputed)
  last_sent_at        timestamptz nullable

updates
  id
  tenant_id           FK → tenants
  project_id          string
  task_name           string
  task_url            string (original ActiveCollab task URL)
  short_url           string nullable (Bit.ly shortened)
  content             text
  author              string
  source              string default: activecollab
  created_at

webhook_logs
  id
  tenant_id           FK → tenants
  source              string (activecollab)
  event_type          string
  payload             jsonb
  status              enum: received | queued | processed | failed
  ip_address          string
  received_at         timestamptz

notification_logs
  id
  tenant_id           FK → tenants
  client_id           FK → clients
  window_slot         enum: morning | afternoon | evening
  channel             enum: sms | slack | email
  status              enum: sent | failed | skipped
  message             text
  sent_at             timestamptz
  error_message       text nullable
```

---

## 4. Data Flow

### 4.1 Webhook Ingestion (Fully Async)

```
ActiveCollab         Cloudflare         Laravel App            Queue Worker          DB
     │                    │                  │                       │                │
     │  POST              │                  │                       │                │
     │  /api/webhook/     │                  │                       │                │
     │  {tenantId}        │                  │                       │                │
     │ ──────────────────►│                  │                       │                │
     │                    │  SSL + Forward ──►│                       │                │
     │                    │                  │                       │                │
     │                    │        Resolve tenant from path          │                │
     │                    │        Validate HMAC signature           │                │
     │                    │        → 401 if invalid                  │                │
     │                    │                  │                       │                │
     │                    │        INSERT webhook_log ──────────────────────────────►│
     │                    │        status: received                  │                │
     │                    │                  │                       │                │
     │                    │        Dispatch ProcessWebhookJob ──────►│                │
     │◄───────────────────────────── 200 OK  │                       │                │
     │   (immediate)      │                  │                       │                │
     │                    │                  │            Parse payload               │
     │                    │                  │            Store update ──────────────►│
     │                    │                  │            Shorten URL (Bit.ly) ──────►│ (async)
     │                    │                  │            Mark webhook_log: processed │
```

**Key point:** Webhook endpoint returns 200 immediately after queuing. Zero heavy processing on the request thread.

---

### 4.2 Three-Window SMS Flow

```
Laravel Scheduler        DB                   Queue Worker          Twilio
(every minute)           │                        │                    │
       │                 │                        │                    │
       │  SELECT         │                        │                    │
       │  client_notification_windows             │                    │
       │  WHERE next_run_at <= NOW()              │                    │
       │  AND clients.sms_enabled = true ────────►│                    │
       │◄────────────────│                        │                    │
       │                 │                        │                    │
       │  For each due window:                    │                    │
       │  Dispatch SendWindowSummaryJob ─────────►│                    │
       │                 │                        │                    │
       │                 │             Resolve client + window slot    │
       │                 │             Get "since" timestamp           │
       │                 │             SELECT updates WHERE            │
       │                 │             project_id = client.project_id  │
       │                 │             AND created_at > since ────────►│ (DB)
       │                 │◄────────────────────────────────────────────│
       │                 │                        │                    │
       │                 │             (skip if 0 updates)            │
       │                 │                        │                    │
       │                 │             Format + truncate message      │
       │                 │             SmsChannel.send() ────────────────────────────►
       │                 │◄───────────────────────────────────────────────────────── SID
       │                 │                        │                    │
       │                 │             INSERT notification_log ───────►│ (DB)
       │                 │             UPDATE window last_sent_at      │
       │                 │             UPDATE window next_run_at ─────►│ (DB)
```

---

### 4.3 Three Notification Windows

Each client has 3 scheduled windows per day, based on their own timezone:

```
Window         Local Time    Description
─────────────────────────────────────────────────────────────
morning        08:00         Comments since previous evening window
afternoon      13:00         Comments since morning window
evening        15:30         Comments since afternoon window
─────────────────────────────────────────────────────────────
```

**"Since" period logic per window:**

```
morning   window sends  →  includes updates since last evening send
afternoon window sends  →  includes updates since last morning send
evening   window sends  →  includes updates since last afternoon send

If a window has never been sent → look back 24 hours
If the previous window was skipped (no updates) → use that window's next_run_at as the since boundary
```

**Timezone resolution:**

```
Client.timezone is set on creation (defaults to Tenant.timezone)
Can be updated per-client in admin UI

Scheduler stores all next_run_at values in UTC
computeNextRunAt(client, slot):
  → Take scheduled_time (e.g. 08:00) in client.timezone
  → Convert to UTC
  → Store as next_run_at
```

**Example (client in IST, UTC+5:30):**

```
window_slot    local time    stored as UTC
───────────────────────────────────────────
morning        08:00 IST  →  02:30 UTC
afternoon      13:00 IST  →  07:30 UTC
evening        15:30 IST  →  10:00 UTC
```

---

## 5. SMS Message Format

### Structure

```
[Project: Alpha Redesign]
• Homepage layout: New comment by John
  bit.ly/3xK9mZ2
• API integration: Status changed by Sarah
  bit.ly/4mP1nQ8

[2 of 5 updates shown]
```

### Message Length & Truncation

```
Hard cap: 1,550 characters (leaves buffer below Twilio's 1,600 max)

Truncation rules:
  1. Show as many complete update entries as fit within 1,550 chars
  2. Append "[X of Y updates shown]" if truncated
  3. Never cut an entry mid-line — drop the whole entry if it doesn't fit
  4. Bit.ly URLs always included per entry (they are short by design)
```

**Twilio SMS Limits:**

| Limit            | Value                                   |
| ---------------- | --------------------------------------- |
| Single segment   | 160 characters (GSM-7 encoding)         |
| Max concatenated | 1,600 characters (~10 segments)         |
| Hard cap applied | 1,550 characters (safety buffer)        |
| Billing          | Each 160-char segment billed separately |

### Bit.ly URL Shortening

- Every update stored in DB includes `task_url` (original ActiveCollab link)
- `short_url` is generated via Bit.ly API inside `ProcessWebhookJob` (async, not on webhook receipt thread)
- SMS formatter uses `short_url` if available, falls back to `task_url` if Bit.ly failed
- Bit.ly token stored per tenant (encrypted), or a global platform token if tenant has none

---

## 6. Cron + Queue Implementation

### Laravel Scheduler (every minute)

```
Console/Kernel.php
└── schedule every minute
      └── ClientSchedulerService.dispatchDueWindows()
            │
            ├── SELECT client_notification_windows
            │   JOIN clients ON clients.id = window.client_id
            │   WHERE next_run_at <= NOW()
            │   AND clients.sms_enabled = true
            │
            └── For each due window row
                  └── Dispatch SendWindowSummaryJob(window_id)
                        → queue: notifications
```

### ClientSchedulerService

```
getDueWindows()
  Query DB: client_notification_windows
  WHERE next_run_at <= now() AND client.sms_enabled = true
  Returns collection of window records

computeNextRunAt(client, window_slot)
  Get scheduled_time for slot (08:00 / 13:00 / 15:30)
  Convert from client.timezone to UTC for tomorrow
  Return UTC timestamp

getSincePeriod(client, window_slot)
  Look up previous window's last_sent_at
  morning   → previous evening last_sent_at
  afternoon → morning last_sent_at
  evening   → afternoon last_sent_at
  Fallback  → 24 hours ago
```

### ProcessWebhookJob (queue: webhooks)

```
ProcessWebhookJob
├── Config: tries = 3, backoff = 30s
│
├── Step 1: Parse ActiveCollab payload
│     Extract: project_id, task_name, task_url, content, author, event_type
│
├── Step 2: Store update row (with tenant_id)
│
├── Step 3: Shorten task_url via Bit.ly API
│     Store short_url on update row
│     (On Bit.ly failure: log warning, leave short_url null, continue)
│
└── Step 4: Mark webhook_log status = processed
```

### SendWindowSummaryJob (queue: notifications)

```
SendWindowSummaryJob
├── Config: tries = 3, backoff = 60s
│
├── Step 1: Load client + window record
│
├── Step 2: Determine "since" timestamp (getSincePeriod)
│
├── Step 3: SELECT updates WHERE project_id = client.project_id
│           AND created_at > since_timestamp
│
├── Step 4: Skip if zero updates → advance next_run_at only
│
├── Step 5: Format message
│     - Build entries with short_url (or task_url fallback)
│     - Truncate to 1,550 chars, drop whole entries that don't fit
│     - Append [X of Y updates shown] if truncated
│
├── Step 6: NotificationService.dispatch(client, message)
│     └── SmsChannel → Twilio API
│         NotificationLog.create(window_slot, status, message, sent_at, error)
│
└── Step 7: Update window record
      last_sent_at = now()
      next_run_at  = computeNextRunAt(client, window_slot)
```

---

## 7. API & Route Design

### Webhook Route

```
POST /api/webhook/{tenantId}

Middleware: SetTenantFromPath, VerifyWebhookSignature

Headers:
  X-AC-Signature: sha256={hmac}

Behaviour:
  1. Verify HMAC → 401 if invalid
  2. INSERT webhook_log (status: received)
  3. Dispatch ProcessWebhookJob
  4. Return 200 immediately

Responses:
  200  → queued
  401  → invalid signature
  404  → tenant not found
  422  → event type not relevant (still log, do not queue)
```

### Admin Routes (Tenant-scoped)

```
Middleware: auth, SetTenantFromSession, EnforceTenantScope, role:tenant_admin

GET    /admin/clients                    → Inertia page (client list)
POST   /admin/clients                    → Create client
PUT    /admin/clients/{id}               → Update client (incl. timezone)
DELETE /admin/clients/{id}               → Delete client

GET    /admin/updates                    → Inertia page (update log)
GET    /admin/notification-logs          → Inertia page (SMS history)
GET    /admin/webhook-logs               → Inertia page (webhook log)
```

### Super Admin Routes

```
Middleware: auth, role:super_admin  (no tenant scope applied)

GET    /super-admin/tenants              → List all tenants
POST   /super-admin/tenants             → Create tenant
PATCH  /super-admin/tenants/{id}         → Update tenant config
DELETE /super-admin/tenants/{id}         → Suspend/delete tenant
```

---

## 8. Notification Logs & Webhook Logs

Both stored in the **same single PostgreSQL database**, scoped by `tenant_id`.

**Webhook Logs — track every inbound event:**

```
webhook_logs
  tenant_id, source, event_type, payload (jsonb),
  status (received → queued → processed | failed),
  ip_address, received_at
```

**Notification Logs — track every SMS attempt:**

```
notification_logs
  tenant_id, client_id, window_slot, channel,
  status (sent | failed | skipped),
  message (full text sent),
  sent_at, error_message
```

**Admin views:**

- Webhook logs: filter by status, date, event type — useful for debugging missed updates
- Notification logs: filter by client, window, date, status — shows delivery history per client

---

## 9. Infrastructure (Laravel Cloud)

```
┌─────────────────────────────────────────────────────────────────────┐
│                       LARAVEL CLOUD                                 │
│                                                                     │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  Application Servers (auto-scaling)                           │  │
│  │  ├── Web process (Laravel + Inertia)                         │  │
│  │  └── Cron process (Laravel Scheduler, every minute)          │  │
│  └───────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  Queue Workers (auto-scaling by queue depth)                  │  │
│  │  ├── webhooks queue    → ProcessWebhookJob                   │  │
│  │  └── notifications queue → SendWindowSummaryJob              │  │
│  └───────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ┌─────────────────────────┐   ┌──────────────────────────────┐    │
│  │  Managed Redis          │   │  Managed PostgreSQL          │    │
│  │  Sessions               │   │  Single database             │    │
│  │  Queue backend          │   │  All tenants, row-isolated   │    │
│  │  Cache                  │   │  via tenant_id               │    │
│  └─────────────────────────┘   └──────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────┘
           │                                      │
           ▼                                      ▼
┌─────────────────────┐              ┌─────────────────────────────┐
│  Twilio SMS API     │              │  Bit.ly API                 │
│  → Client phones    │              │  URL shortening per update  │
└─────────────────────┘              └─────────────────────────────┘
```

### Environment Variables

| Variable             | Purpose                                 |
| -------------------- | --------------------------------------- |
| `DB_HOST`            | Managed PostgreSQL host                 |
| `DB_DATABASE`        | Single shared database name             |
| `REDIS_HOST`         | Managed Redis host                      |
| `QUEUE_CONNECTION`   | `redis`                                 |
| `TWILIO_ACCOUNT_SID` | Twilio auth                             |
| `TWILIO_AUTH_TOKEN`  | Twilio auth                             |
| `TWILIO_FROM_NUMBER` | Sender number                           |
| `BITLY_ACCESS_TOKEN` | Default platform Bit.ly token           |
| `ENCRYPTION_KEY`     | AES-256 key for AC keys + Bit.ly tokens |
| `APP_KEY`            | Laravel session encryption              |

---

## 10. Security

| Concern                          | Approach                                                                            |
| -------------------------------- | ----------------------------------------------------------------------------------- |
| **SSL**                          | Cloudflare terminates TLS, forwards HTTPS to Laravel Cloud                          |
| **Webhook validation**           | HMAC-SHA256 on `X-AC-Signature` header; 401 + logged on failure                     |
| **Async webhook processing**     | Webhook stored and queued immediately; no sync processing                           |
| **Tenant row isolation**         | Eloquent global scope appends `WHERE tenant_id = ?` on every query                  |
| **Cross-tenant leak prevention** | Scope enforced in middleware on every request; super-admin routes bypass explicitly |
| **AC API key storage**           | AES-256 encrypted in DB; decrypted only in-process                                  |
| **Session security**             | Redis-backed sessions, `secure` + `httponly` cookies via Cloudflare                 |
| **SQL injection**                | Eloquent ORM parameterized queries throughout                                       |
| **Queue security**               | Redis queues on private Laravel Cloud network; not publicly accessible              |
| **Admin route protection**       | `auth` + `role` middleware; tenant scope enforced                                   |
| **Phone number exposure**        | Never returned in Inertia props; server-only queries                                |

---

## 11. Phased Rollout

### Phase 1: MVP

- Single tenant provisioned via seeder
- 3-window SMS per client (morning, afternoon, evening)
- Per-client timezone (defaults to tenant timezone)
- Webhook queuing + webhook logs
- Notification logs
- Bit.ly URL shortening per update
- Hard cap at 1,550 chars

**Milestone:** Full end-to-end for one tenant

### Phase 2: Multi-Tenant

- Super-admin panel: create/manage tenants
- Per-tenant login and user management
- Per-tenant AC API key, webhook secret, Bit.ly token
- Tenant-scoped admin UI and logs

**Milestone:** Multiple independent tenants on one deployment

## 12. Key Design Decisions

| Decision                              | Choice                                | Rationale                                                                                  |
| ------------------------------------- | ------------------------------------- | ------------------------------------------------------------------------------------------ |
| **Single DB over multi-DB**           | Row-level isolation via `tenant_id`   | Simpler ops, no DB provisioning per tenant, sufficient isolation for this data sensitivity |
| **3 notification windows**            | Per-client, timezone-aware            | Sends updates when they're most relevant; avoids batching 8h of activity into one message  |
| **Per-client timezone**               | Defaults to tenant TZ                 | Clients may be in different regions from the tenant team                                   |
| **Async webhook processing**          | Queue immediately, process via worker | Fast response to ActiveCollab, resilient to processing failures                            |
| **Bit.ly per update**                 | Shortened on webhook receipt          | URL fits in SMS budget; shortened in background, not blocking notification send            |
| **Hard cap 1,550 chars**              | Truncate whole entries                | Stays below Twilio 1,600 limit; clean truncation with count shown                          |
| **client_notification_windows table** | Separate from clients                 | Extensible — can add/remove windows per client without schema changes                      |
| **Inertia.js**                        | Over separate SPA                     | No separate API for UI; server-side data with React component feel                         |

---

## 13. Folder Structure

```
/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/
│   │   │   │   ├── ClientController.php
│   │   │   │   ├── UpdateController.php
│   │   │   │   ├── NotificationLogController.php
│   │   │   │   └── WebhookLogController.php
│   │   │   ├── SuperAdmin/
│   │   │   │   └── TenantController.php
│   │   │   └── Webhook/
│   │   │       └── ActiveCollabController.php
│   │   └── Middleware/
│   │       ├── SetTenantFromSession.php
│   │       ├── SetTenantFromPath.php
│   │       ├── EnforceTenantScope.php
│   │       ├── VerifyWebhookSignature.php
│   │       └── RoleMiddleware.php
│   │
│   ├── Jobs/
│   │   ├── ProcessWebhookJob.php
│   │   └── SendWindowSummaryJob.php
│   │
│   ├── Services/
│   │   ├── WebhookService.php
│   │   ├── UpdateService.php
│   │   ├── NotificationService.php
│   │   └── ClientSchedulerService.php
│   │
│   ├── Channels/
│   │   ├── NotificationChannelInterface.php
│   │   ├── SmsChannel.php
│   │   ├── SlackChannel.php          ← stub for future
│   │   └── EmailChannel.php          ← stub for future
│   │
│   └── Models/
│       ├── Tenant.php
│       ├── User.php
│       ├── Client.php
│       ├── ClientNotificationWindow.php
│       ├── Update.php
│       ├── WebhookLog.php
│       └── NotificationLog.php
│
├── resources/js/Pages/
│   ├── Admin/
│   │   ├── Clients.tsx
│   │   ├── Updates.tsx
│   │   ├── NotificationLogs.tsx
│   │   └── WebhookLogs.tsx
│   ├── SuperAdmin/
│   │   └── Tenants.tsx
│   └── Auth/Login.tsx
│
├── routes/
│   ├── web.php          ← Inertia + auth routes
│   ├── api.php          ← Webhook route only
│   └── console.php      ← Scheduler definition
│
├── database/migrations/ ← single set of migrations (no central/tenant split)
│
└── config/
    ├── queue.php         ← Redis queue, two named queues
    └── tenancy.php       ← tenant context config
```

---
