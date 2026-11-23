# Changelog

All notable changes. Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased] - 2.0.0

First release of the fork of Perfecty Push Notifications 1.6.5.

### Fixed (upstream send failures, see docs/upstream-triage.md)

- Jobs no longer stop when one batch has zero successes.
- Jobs no longer stay locked forever after a crash; stale locks are taken
  over and a watchdog re-queues stuck jobs (#180, #210).
- All times are UTC computed in PHP; no more hours-off schedules (#158, #190).
- Non-English notifications are encoded without `\u` escapes and truncated
  multibyte-safely.
- Subscribing works on page-cached sites (no REST nonce needed).
- Subscribers are paged by id, so none are skipped or notified twice.
- 401/403 responses no longer delete subscribers (#162).
- Firefox Android 413 errors (#132) via web-push 11.
- Publishing sends once; updating a published post does not re-send
  (#101, #167).

### Added

- Per-subscriber content encoding (`aes128gcm` default).
- Service worker and web app manifest served from the site root with
  no-store headers.
- REST API `bwpn/v1/subscriptions`, `/unsubscribe`, `/status` with per-IP and
  global rate limits, push-service allowlist and endpoint rotation.
- Block editor panel and classic meta box with optional custom title.
- Perfecty Push import (keys, settings, scope, subscribers), automatic on
  first activation.
- JSON backup/restore including VAPID keys, CSV export/import.
- Admin: dashboard with health checks, scheduling in the site time zone,
  cancel/resume jobs, Tools page, test send.
- WP-CLI `wp bwpn status|send|process|export|import|migrate-perfecty|keys`.
- Cloudflare-aware client IP; VAPID keys can be pinned in `wp-config.php`.
- Filters and actions, see docs/developer.md.

### Changed

- Requires PHP 8.2 and WordPress 6.6.
- Plugin slug, text domain, prefixes and REST namespace renamed to
  `best-web-push-notifications` / `bwpn`.
- minishlink/web-push 6.0.7 -> 11.0.0; vendor code namespace-prefixed
  (`BWPN\Vendor\`) so it can run next to Perfecty Push.
- Capabilities mapped to core ones; no roles are modified.
- Uninstall keeps data unless "Delete all data" is enabled.
- `BWPN_Integration::notify()` returns delivery counts; `broadcast()`
  accepts an optional timestamp.

### Removed

- jQuery UI and timepicker, Chart.js 2.9.4, About page, Perfecty branding,
  ramsey/uuid, Docker and SVN tooling, "unregister conflicting workers"
  option.
