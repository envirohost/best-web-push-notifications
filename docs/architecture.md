# Architecture

How Best Web Push Notifications is put together, for contributors.

## Overview

```
Browser (SDK + service worker)
   |  POST /wp-json/bwpn/v1/subscriptions        (subscribe, no nonce)
   v
BWPN_Subscribers_Rest -> BWPN_Subscription_Validator -> wp_bwpn_subscribers
                                                             |
Admin Send / post publish / WP-CLI / BWPN_Integration        |
   |  BWPN_Lib_Push_Server::schedule_broadcast()             |
   v                                                         |
wp_bwpn_notifications (job) -> WP-Cron bwpn_process_job      |
   |  execute_job(): claim lock, batches by id  <------------+
   v
sender (web-push 11 via BWPN_Http, or a test double)
   |  encrypted POST to the push service (FCM, Mozilla, WNS, Apple)
   v
Browser service worker shows the notification
```

## Files

| Path | Responsibility |
|------|----------------|
| `best-web-push-notifications.php` | Header, constants, PHP version gate, early service worker hook, activation hooks |
| `includes/class-bwpn-plugin.php` | Loads files, registers every hook, maps capabilities, text domain |
| `includes/class-bwpn-settings.php` | Settings defaults and accessors, translatable default texts, scope, VAPID keys (option or wp-config constants), VAPID subject |
| `includes/class-bwpn-activator.php` | Tables, default settings, Perfecty import on first activation, key generation, cron |
| `includes/class-bwpn-deactivator.php` | Clears cron events and notices |
| `includes/class-bwpn-upgrader.php` | Runs `db_create()` when `BWPN_DB_VERSION` changes |
| `includes/class-bwpn-cron.php` | `bwpn_process_job`, `bwpn_watchdog` (5 min), `bwpn_daily`; cron health |
| `includes/class-bwpn-notices.php` | One-time admin notices stored in a transient |
| `includes/class-bwpn-client-ip.php` | Client IP (direct, or Cloudflare `CF-Connecting-IP` only from Cloudflare ranges) |
| `includes/class-bwpn-rate-limiter.php` | Fixed-window rate limiter on transients |
| `includes/class-bwpn-subscription-validator.php` | Endpoint allowlist (SSRF guard), key validation, base64url, endpoint hash, service labels |
| `includes/class-bwpn-service-worker.php` | Serves `/?bwpn_sw=` and `/?bwpn_manifest=1`, prints the manifest link |
| `includes/class-bwpn-post-publisher.php` | Post meta, send on first publish, block editor panel, classic meta box |
| `includes/class-bwpn-migrator.php` | Perfecty Push import |
| `includes/class-bwpn-transfer.php` | JSON/CSV export and import |
| `includes/class-bwpn-cli.php` | `wp bwpn` commands |
| `lib/class-bwpn-lib-db.php` | All SQL (prepared, `%i` identifiers, UTC) |
| `lib/class-bwpn-lib-payload.php` | Payload building and size-safe encoding |
| `lib/class-bwpn-lib-push-server.php` | Scheduling, job runner, watchdog, result handling, web-push sender |
| `lib/class-bwpn-lib-log.php`, `lib/log/*` | Logger with error_log or database writer |
| `lib/class-bwpn-lib-utils.php` | Disabled check, health checks |
| `external/class-bwpn-external-webpush.php` | Lazy loader for the prefixed vendor libraries, key generation and validation |
| `external/class-bwpn-http.php` | PSR-18 / HTTPlug adapter over the WordPress HTTP API (parallel requests) |
| `public/class-bwpn-public.php` | Enqueues the SDK and prints `window.BWPNOptions` |
| `public/class-bwpn-subscribers-rest.php` | REST routes |
| `admin/class-bwpn-admin.php` | Menu, pages, Settings API, admin-post handlers |
| `admin/class-bwpn-admin-tables.php` | List tables (notifications, subscribers, logs) |
| `admin/js/bwpn-editor.js` | Block editor panel (no build step) |
| `admin/js/bwpn-admin.js` | Media picker, confirmations (no jQuery) |
| `integration/class-bwpn-integration.php` | Public PHP API |
| `uninstall.php` | Removes data only when opted in |
| `sdk/` | Browser SDK and service worker sources (MIT, subtree of the Perfecty SDK) |
| `vendor-prefixed/` | Strauss output, namespaces under `BWPN\Vendor\` (generated, not committed) |

## Database

All tables use the site prefix. All datetimes are UTC, written by PHP.

`{prefix}bwpn_subscribers`

| Column | Notes |
|--------|-------|
| `id` | Keyset cursor for sending |
| `uuid` | Public id (kept from Perfecty when imported) |
| `wp_user_id` | Set when the visitor was logged in |
| `endpoint` | Up to 2,048 chars |
| `endpoint_hash` | SHA-256 of the endpoint, unique; used for lookups |
| `key_p256dh`, `key_auth` | Normalised base64url |
| `content_encoding` | `aes128gcm` (default) or `aesgcm` |
| `source` | `subscribe`, `perfecty`, `import` |
| `failure_count` | Consecutive failures; reset on success |
| `created_at`, `updated_at`, `last_success_at` | UTC |

`{prefix}bwpn_notifications` (jobs)

| Column | Notes |
|--------|-------|
| `payload` | Encoded JSON (`text`) |
| `source`, `post_id` | `manual`, `post`, `cli`, `api` |
| `status` | `scheduled`, `running`, `completed`, `failed`, `canceled` |
| `total` | Subscriber count when the job started |
| `succeeded`, `failed`, `expired` | Counters, incremented atomically per batch |
| `last_cursor` | Last subscriber id processed |
| `batch_size` | Copied from settings at creation |
| `is_taken`, `taken_at` | Lock and lock heartbeat |
| `attempts`, `last_error` | Retry count and last error |
| `created_at`, `scheduled_at`, `last_execution_at`, `finished_at` | UTC |

`{prefix}bwpn_logs`: `level`, `message`, `created_at`; capped at 14 days and
5,000 rows by the daily event.

## Options

| Option | Content |
|--------|---------|
| `bwpn_settings` | All settings (see `BWPN_Settings::defaults()`) |
| `bwpn_vapid_public_key` | Autoloaded |
| `bwpn_vapid_private_key` | Not autoloaded |
| `bwpn_version`, `bwpn_db_version` | Installed versions |
| `bwpn_watchdog_last_run` | Cron health |
| `bwpn_perfecty_migration` | Import state: status, cursor, counts |

The keys can instead be defined in `wp-config.php` as
`BWPN_VAPID_PUBLIC_KEY` and `BWPN_VAPID_PRIVATE_KEY`; they then take priority
and cannot be changed from the admin. `BWPN_DISABLED` set to true stops the
front-end SDK.

## Job lifecycle

1. `schedule_broadcast()` encodes the payload, creates a `scheduled` job and a
   single cron event at `scheduled_at`.
2. `execute_job()` (cron, `wp bwpn process`, or the watchdog's re-queue):
   - returns early if the job is not due yet (re-schedules the event);
   - claims the lock atomically (or takes over a stale one);
   - switches `scheduled` to `running` and records `total`;
   - loops: read `batch_size` subscribers after `last_cursor`, send, apply
     results, add progress, refresh the lock;
   - stops when no subscribers remain (`completed`), when a whole batch is
     rejected with 401/403 (`failed`), or when the next batch would not fit
     the time budget (lock released, next run scheduled immediately).
3. Exceptions increment `attempts`; the job is retried after 1, then 2
   minutes, and marked `failed` after 3 attempts.
4. The watchdog runs every 5 minutes and re-queues anything due, paused or
   abandoned.
5. Failed or canceled jobs can be resumed from the Notifications screen;
   they continue from `last_cursor`.

Time budget: 60% of `max_execution_time`, capped at 60 seconds when unlimited
or higher, minimum 5 seconds (`bwpn_time_budget` filter). WP-CLI runs use 300
seconds per run.

## Subscriber lifecycle

- Created by `POST /subscriptions`; an existing endpoint is refreshed, not
  duplicated. `old_endpoint` lets a rotated subscription update its row.
- A welcome notification is sent immediately if enabled.
- 404/410 from the push service deletes the row immediately.
- Other failures increment `failure_count`; the daily event deletes rows at
  `max_failures` (default 5). 401/403 never count.
- `POST /subscriptions/unsubscribe` deletes by endpoint.

## Security

- Every SQL statement is prepared; table names use `%i`.
- Subscription endpoints must be HTTPS on an allowlisted push host
  (`bwpn_allowed_push_hosts`), on the default port unless
  `bwpn_allow_push_port` allows it. This stops the server being used to send
  requests to arbitrary URLs (SSRF).
- Keys are validated by length and format (65-byte uncompressed P-256 point,
  16-byte auth secret).
- Per-IP (setting `rate_limit_per_ip`, default 10/min) and global (600/min,
  `bwpn_global_rate_limit`) limits on the public routes.
- The client IP trusts `CF-Connecting-IP` only when `REMOTE_ADDR` is in
  Cloudflare's published ranges.
- Admin actions use capabilities plus nonces and POST/redirect/GET.
- The SDK configuration is JSON-encoded with `JSON_HEX_TAG`, so settings
  cannot break out of the inline script.
- The JSON backup contains the private key; the Tools screen says so.
- Vendor libraries are namespace-prefixed so other plugins' copies of
  web-push or PSR packages cannot conflict.

## Capabilities

No roles are modified. `map_meta_cap` maps:

| Plugin capability | Core capability | Used for |
|-------------------|-----------------|----------|
| `bwpn_send_notifications` | `publish_posts` (filter `bwpn_send_capability`) | Dashboard, Send, Notifications, editor panel, test send |
| `bwpn_manage` | `manage_options` | Subscribers, Settings, Tools, Logs |
