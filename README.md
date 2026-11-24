# Best Web Push Notifications

Self-hosted web push notifications for WordPress. Your VAPID keys and your
subscriber list stay on your own server: no SaaS, no per-subscriber fees.

A maintained fork of [Perfecty Push Notifications](https://github.com/perfectyorg/perfecty-push-wp)
1.6.5 by Rowinson Gallego, rebuilt to fix the reasons notifications were
scheduled but never delivered.

> **Status:** 2.0.0 in development. The server side works; the browser SDK
> is being updated. Not ready for production yet. See [docs/status.md](docs/status.md).

## Features

- Send to every subscriber now or at a scheduled time, from the admin,
  WP-CLI or PHP.
- Send automatically when a post is published (once, never on updates).
- Reliable delivery: resumable batched jobs, crash recovery, watchdog,
  correct time zones, subscribers kept on configuration errors.
- Works behind Cloudflare and page caches (no nonce in cached pages).
- Chrome, Edge, Firefox, Safari on macOS, and iPhone/iPad home-screen apps
  (web app manifest included).
- One-click import from Perfecty Push, keeping existing subscribers.
- Backup and restore (JSON with keys, CSV).
- Health checks on the dashboard.

## Requirements

- WordPress 6.6 or newer (tested on 7.1)
- PHP 8.2 or newer with `openssl` and `mbstring`
- HTTPS
- A working WP-CLI or system cron on cached sites ([docs/cloudflare.md](docs/cloudflare.md))

## Documentation

| Document | For |
|----------|-----|
| [docs/migration.md](docs/migration.md) | Moving from Perfecty Push |
| [docs/cloudflare.md](docs/cloudflare.md) | Cloudflare, caching, cron |
| [docs/developer.md](docs/developer.md) | PHP API, hooks, REST API, WP-CLI |
| [docs/architecture.md](docs/architecture.md) | How it works inside |
| [docs/upstream-triage.md](docs/upstream-triage.md) | The upstream bugs and their fixes |
| [docs/development.md](docs/development.md) | Building and testing |
| [docs/status.md](docs/status.md) | What is done and what is left |
| [CHANGELOG.md](CHANGELOG.md) | Changes |

## Quick start (development)

```
git clone https://github.com/envirohost/best-web-push-notifications.git
cd best-web-push-notifications
composer install
```

Symlink or copy the folder into `wp-content/plugins/` and activate. Keys
are generated on activation (or adopted from Perfecty Push).

```
wp bwpn status
wp bwpn send "Hello from WP-CLI" --url=https://example.com/ --now
```

## Licence and credits

- Plugin: GPL-2.0-or-later ([LICENSE](LICENSE)).
- Browser SDK in `sdk/`: MIT ([sdk/LICENSE](sdk/LICENSE)).
- Based on Perfecty Push Notifications, copyright 2020-2025 Rowinson Gallego.
- Uses [web-push-php](https://github.com/web-push-libs/web-push-php) (MIT).
