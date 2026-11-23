# Developer reference

Everything other plugins, themes and scripts can use. Names here are part of
the public API from v2.0.0 on.

## PHP API

```php
$push = new BWPN_Integration();

// Queue a broadcast to every subscriber. Returns the job id or WP_Error.
$job_id = $push->broadcast( 'Sale starts now', 'My Shop', $image_url, 'https://example.com/sale' );

// Schedule it (Unix timestamp).
$job_id = $push->broadcast( 'Doors open', '', '', '', strtotime( '+2 hours' ) );

// Send now to every browser of one WordPress user.
// Returns ['ok' => int, 'failed' => int, 'expired' => int, 'auth' => int] or WP_Error.
$result = $push->notify( $user_id, 'Your order shipped', '', '', $order_url );
```

Method names match Perfecty Push's `Perfecty_Push_Integration`, so existing
integrations port by renaming the class. The return values differ: `notify()`
returns the counts above, `broadcast()` returns a job id or `WP_Error`.

Lower level: `BWPN_Lib_Payload::build()`,
`BWPN_Lib_Push_Server::schedule_broadcast()`, `send_now()`, `notify_user()`,
`cancel_job()`, `resume_job()`.

## Filters

| Filter | Arguments | Default / purpose |
|--------|-----------|-------------------|
| `bwpn_payload` | `array $payload` | Change any notification before encoding |
| `bwpn_post_payload` | `array $payload, WP_Post $post` | Change the payload sent for a post |
| `bwpn_post_types` | `string[] $types` | Public post types with `show_in_rest`, minus attachments |
| `bwpn_send_capability` | `string $cap` | `publish_posts`: who may send |
| `bwpn_sender` | `callable $sender` | Replace the transport (see below) |
| `bwpn_time_budget` | `int $seconds` | 60% of `max_execution_time`, max 60 |
| `bwpn_allowed_push_hosts` | `string[] $hosts` | FCM, Mozilla, WNS, Apple; `*.` prefix allowed |
| `bwpn_allow_push_port` | `bool $allow, int $port` | `false`: only port 443 |
| `bwpn_global_rate_limit` | `int $per_minute` | 600 subscribe calls per minute site-wide |
| `bwpn_client_ip` | `string $ip, string $remote_addr` | Resolved client IP |
| `bwpn_cloudflare_ranges` | `string[] $cidrs` | Bundled Cloudflare IP ranges |
| `bwpn_load_sdk` | `bool $load` | Whether the SDK loads on this page |
| `bwpn_sdk_config` | `array $config` | `window.BWPNOptions` |
| `bwpn_manifest` | `array $manifest` | Web app manifest |
| `bwpn_print_manifest_link` | `bool $print` | Return false if a PWA plugin prints its own |

## Actions

| Action | Arguments | When |
|--------|-----------|------|
| `bwpn_job_scheduled` | `int $job_id` | A broadcast job was created |
| `bwpn_job_completed` | `int $job_id` | A job finished sending |
| `bwpn_subscribed` | `object $subscriber` | A new subscription was stored |

Cron hooks (internal, do not call directly): `bwpn_process_job` (job id),
`bwpn_watchdog`, `bwpn_daily`, `bwpn_migrate_perfecty`.

## Custom sender

A sender receives a batch and returns a result per subscriber id:

```php
add_filter( 'bwpn_sender', function () {
	return function ( array $subscribers, string $payload, array $options ) {
		$results = array();
		foreach ( $subscribers as $s ) {
			// $s->id, $s->endpoint, $s->key_p256dh, $s->key_auth, $s->content_encoding
			$results[ (int) $s->id ] = BWPN_Lib_Push_Server::RESULT_OK;
		}
		return $results;
	};
} );
```

Results: `RESULT_OK`, `RESULT_EXPIRED` (deleted), `RESULT_AUTH` (credential
problem, subscriber kept), `RESULT_INVALID`, `RESULT_RETRY`. A missing id
counts as `RESULT_RETRY`. Tests use `BWPN_Lib_Push_Server::set_sender()`.

## REST API

Namespace `bwpn/v1`. No authentication or nonce. Rate limited per IP and
site-wide (HTTP 429 with `retry_after`).

### `POST /subscriptions`

```json
{
  "endpoint": "https://fcm.googleapis.com/fcm/send/...",
  "keys": { "p256dh": "B...", "auth": "..." },
  "content_encoding": "aes128gcm",
  "old_endpoint": "https://... (optional, after pushsubscriptionchange)"
}
```

- `201 {"uuid": "...", "created": true}`: new subscriber (welcome message
  sent if enabled).
- `200 {"uuid": "...", "created": false}`: existing endpoint refreshed, or
  the row of `old_endpoint` moved to the new endpoint.
- `400`: invalid endpoint, unknown push service or bad keys.
- `403`: subscriptions disabled.

The logged-in user, if any, is linked to the subscription.

### `POST /subscriptions/unsubscribe`

`{"endpoint": "..."}` -> `{"deleted": true|false}`

### `POST /subscriptions/status`

`{"endpoint": "..."}` -> `{"subscribed": bool, "uuid": "..."|null}`

## Service worker and manifest

| URL | Response |
|-----|----------|
| `/?bwpn_sw=<tag>` | `public/js/bwpn-sw.min.js`, `Cache-Control: no-store`, `Service-Worker-Allowed: <scope>` |
| `/?bwpn_manifest=1` | Web app manifest (404 if disabled) |

`<tag>` changes with the plugin version and the worker file time, so
browsers update the worker after an upgrade. Both are answered before other
plugins can redirect the request.

## SDK configuration (`window.BWPNOptions`)

`serverUrl`, `vapidPublicKey`, `serviceWorkerUrl`, `serviceWorkerScope`,
`dialogTitle`, `dialogSubmit`, `dialogCancel`, `settingsTitle`,
`settingsOptIn`, `settingsUpdateError`, `promptIconUrl`, `promptVisits`,
`promptDelay` (seconds), `promptScroll` (percent, 0 = off),
`hideBellAfterSubscribe`, `iosHint` (text, empty = off), `adoptLegacy`,
`loggerLevel`. There is no nonce in it, so cached pages are safe.

## WP-CLI

| Command | Purpose |
|---------|---------|
| `wp bwpn status [--format=json]` | Subscribers, jobs, delivery totals, health checks |
| `wp bwpn send <message> [--title] [--url] [--image] [--at="Y-m-d H:i"] [--now]` | Queue a broadcast; `--now` sends in this process |
| `wp bwpn process [<id>...]` | Run due/unfinished jobs to completion (system cron) |
| `wp bwpn export <file.json|file.csv|-> [--no-keys]` | Backup |
| `wp bwpn import <file> [--overwrite-keys] [--settings]` | Restore |
| `wp bwpn migrate-perfecty [--overwrite-keys]` | Import from Perfecty Push |
| `wp bwpn keys show` / `wp bwpn keys generate [--yes]` | VAPID keys |

## wp-config.php constants

```php
define( 'BWPN_VAPID_PUBLIC_KEY', '...' );  // Pin the keys (both required).
define( 'BWPN_VAPID_PRIVATE_KEY', '...' );
define( 'BWPN_DISABLED', true );           // Stop the front-end SDK.
```
