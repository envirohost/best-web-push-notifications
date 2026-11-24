# Project status

Last updated 2026-11-24. Branch `wip/core-rewrite`.

## Done

| Area | State |
|------|-------|
| Fork, history, licence, rename, trim | Done |
| Dependencies (web-push 11, Strauss prefixing, `composer audit` clean) | Done |
| `composer.lock` phpstan entry | Fixed |
| Settings, activation, upgrader, cron, notices, client IP, rate limiter, validator, DB layer, payload, HTTP adapter | Done |
| Push server (jobs, watchdog, send pipeline) | Done |
| Service worker and manifest endpoints | Done (needs SDK build output) |
| REST subscribe API | Done |
| Post publisher and block editor panel | Done |
| Perfecty migrator | Done |
| Export / import | Done |
| Admin pages | Done |
| WP-CLI | Done |
| Documentation (`docs/`, CHANGELOG, README) | Done for the work so far |

## Verified manually

Environment: WordPress 7.1, PHP 8.3.6, MariaDB, WP-CLI 2.12.

| Check | Result |
|-------|--------|
| Plugin activation, tables, keys, cron | Pass |
| `wp bwpn status`, `keys show` | Pass |
| REST subscribe: 10 accepted, then HTTP 429 (limit 10/min) | Pass |
| Payload with Japanese/Cyrillic and 600 extra chars: valid JSON, 1,115 bytes | Pass |
| Batched job with fake sender (batch 4): 5 ok, 2 failed, 3 expired removed | Pass |
| All-403 batch: job failed with explanation, no subscriber removed | Pass |
| Stale lock (1 hour old): watchdog re-queued, job completed | Pass |
| Real web-push to local fake push service: aes128gcm and aesgcm encrypted with VAPID; 201 ok, 410 removed, 403 kept | Pass |
| Service worker URL: 200, JS type, no-store, Service-Worker-Allowed | Pass |
| Manifest URL and `<link rel="manifest">` | Pass |
| Front-end `window.BWPNOptions` printed, SDK enqueued | Pass |
| All seven admin pages render without PHP errors | Pass |
| Settings sanitizer clamps values, rejects bad subject and select values | Pass |
| Internal settings updates keep other values (bug found and fixed) | Pass |
| Publish post with flag, then update: one job | Pass |
| Perfecty import: 1,203 rows, 1 invalid host skipped; keys adopted only with overwrite when different; scope kept | Pass |

Not yet verified: real browsers (Chrome, Firefox, Edge, Safari macOS/iOS),
classic editor in a browser, block editor panel in a browser, CSV/JSON
import through the admin upload form.

## Remaining work (in order)

1. **SDK** (`sdk/`): rename build entries to `bwpn-sdk` / `bwpn-sw` and copy
   the output to `public/js/`; use the new REST contract (no nonce,
   `content_encoding` from `PushManager.supportedContentEncodings`,
   `old_endpoint`); register `serviceWorkerUrl` at `serviceWorkerScope`;
   prompt delay / visits / scroll; iOS home-screen hint; adopt legacy
   Perfecty registrations (`adoptLegacy`); `pushsubscriptionchange` in the
   worker; `npm audit`.
2. **Tests and static analysis**: rewrite the upstream PHPUnit tests in
   `tests/` for the new API (subscriptions, send pipeline with fake sender,
   migrator, transfer); add `phpcs.xml.dist` (WPCS 3.4) and `phpstan.neon.dist`.
3. **Docs and repo files**: README.txt (wp.org format), CONTRIBUTING,
   SECURITY, issue/PR templates, GitHub Actions (update `tests.yml`).
4. **Release**: replace `envirohost` in the plugin header, build the
   v2.0.0 zip (with `vendor-prefixed/` and built SDK, without `vendor/`,
   `sdk/node_modules`, tests), create the public repo under Michael's personal
   GitHub account, push, tag, release; manual browser checklist.

## Known gaps and decisions

- Plugin header still has the `envirohost` placeholder.
- Old upstream tests in `tests/` target the removed API and will fail.
- `.github/workflows/tests.yml` is upstream's and not updated.
- README.txt is still upstream's text.
- Notification history and logs are not imported from Perfecty (by design).
- Segmentation/targeting is out of scope for 2.0.0.
