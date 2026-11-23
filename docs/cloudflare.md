# Cloudflare, page caching and reliable sending

## Background sending needs a real cron

Notifications are sent by WP-Cron. WP-Cron only runs when a request reaches
WordPress. With full-page caching (Cloudflare APO or Cache Everything, WP
Rocket, LiteSpeed, Varnish) most visits never reach PHP, so scheduled
notifications can be late or never start.

The Dashboard health check "Background sending (WP-Cron)" turns red when the
watchdog has not run for 20 minutes.

Fix: disable the visitor-triggered cron and run it from the system.

```php
// wp-config.php
define( 'DISABLE_WP_CRON', true );
```

Then one of:

```
# crontab: run due jobs directly (preferred)
* * * * * wp --path=/var/www/example.com bwpn process --quiet

# or: trigger WP-Cron every minute
* * * * * wp --path=/var/www/example.com cron event run --due-now --quiet
# or, without WP-CLI
* * * * * curl -fsS "https://example.com/wp-cron.php?doing_wp_cron" >/dev/null
```

Managed hosts usually have a "server cron" option that does the same.

## What is safe to cache

| URL | Cache? |
|-----|--------|
| Pages with the SDK | Yes. `window.BWPNOptions` holds no nonce or per-user data. |
| `/wp-json/bwpn/v1/*` | **No** (POST only; Cloudflare does not cache POST by default) |
| `/?bwpn_sw=...` | **No**. Sent with `Cache-Control: no-store` and `CDN-Cache-Control: no-store`. |
| `/?bwpn_manifest=1` | **No**. Same headers. |
| `public/js/bwpn-sdk.min.js` | Yes (versioned URL) |

If a Cache Rule caches everything including query strings, add a bypass rule:

- **When:** URI query string contains `bwpn_sw` **or** `bwpn_manifest`, **or**
  URI path starts with `/wp-json/bwpn/`
- **Then:** Bypass cache

A cached or redirected service worker fails to install, and an old worker
can keep running after updates.

## Rocket Loader, minification, "delay JavaScript"

Exclude `bwpn-sdk.min.js` and the inline `window.BWPNOptions` script from
Rocket Loader and "delay JS until interaction" features, or the prompt only
appears after the visitor interacts. The SDK is already minified and loaded
with `defer`.

## Client IP for rate limiting

Behind Cloudflare, every request comes from a Cloudflare IP, so per-IP rate
limits would apply to everyone at once. **Settings > Security > Client IP
source** defaults to **Cloudflare**: `CF-Connecting-IP` is trusted only when
the connection comes from Cloudflare's published ranges, so it cannot be
spoofed by direct requests. Choose **X-Forwarded-For** only behind your own
reverse proxy that overwrites the header. The bundled ranges can be updated
with the `bwpn_cloudflare_ranges` filter.

## WAF and bot rules

Allow `POST /wp-json/bwpn/v1/subscriptions*` for browsers. "Bot Fight Mode"
and challenges on `/wp-json/` block subscriptions because the SDK cannot
solve a challenge.

## HTTPS

Browsers only allow push on HTTPS. Use Cloudflare SSL mode **Full (strict)**;
**Flexible** makes WordPress think it is on HTTP and can cause redirect loops
on the service worker URL.
