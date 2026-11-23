# Migrating from Perfecty Push

Best Web Push Notifications imports everything from Perfecty Push
Notifications 1.x, and existing subscribers keep receiving notifications
without subscribing again.

## Why subscribers keep working

- **Same VAPID keys.** A push subscription is bound to the public key it was
  created with. The import adopts Perfecty's key pair, so the push services
  accept our messages for existing subscriptions.
- **Same service worker scope.** The import copies Perfecty's scope (default
  `/perfecty/push`). When a subscriber next visits, our worker registers at
  the same scope and replaces Perfecty's, keeping the browser's subscription.
- **Perfecty's data is never changed.** The import only reads Perfecty's
  tables and option, so it can run while Perfecty is active, can be repeated,
  and Perfecty can be reactivated if needed.

## What is imported

| Perfecty | Best Web Push Notifications |
|----------|-----------------------------|
| `perfecty_push['vapid_public_key']`, `['vapid_private_key']` | VAPID keys (validated as a matching pair) |
| `service_worker_scope` | `service_worker_scope` |
| `widget_enabled`, `visits_to_display_prompt`, `widget_hide_bell_after_subscribe` | `widget_enabled`, `prompt_visits`, `hide_bell_after_subscribe` |
| Dialog and bell texts, welcome message settings | Same settings |
| `notifications_interaction_required`, `notifications_default_icon` | `require_interaction`, `default_icon` |
| `segmentation_tracking_utm`, `batch_size` | `utm`, `batch_size` |
| `server_url` (unless it points at Perfecty's own route) | `server_url` |
| `{prefix}perfecty_push_users` rows | Subscribers with `source = perfecty`, same `uuid` and `wp_user_id` |

Not imported: notification history and logs.

Each subscriber goes through the same validation as a new subscription
(HTTPS, allowlisted push service, valid keys). Rows that fail are skipped and
counted. Perfecty never stored the content encoding, so imported subscribers
use `aes128gcm`, which every browser that still receives push supports.

## Steps

1. Back up the database.
2. Install and activate Best Web Push Notifications **while Perfecty Push is
   still active**. On first activation the import runs automatically and a
   notice reports the result.
3. Check **Push Notifications > Tools > Import from Perfecty Push** for the
   counts, or run `wp bwpn migrate-perfecty`.
4. Deactivate Perfecty Push, otherwise subscribers receive every notification
   twice. Do not delete it for a few weeks.
5. Send yourself a test (**Tools > Test**) from a browser you subscribed with.

Large lists are copied in batches. If a request runs out of time, the rest
continues on a background event (`bwpn_migrate_perfecty`); WP-CLI runs the
whole import in one go.

## If this site already has different keys

When Best Web Push Notifications already generated its own keys (for example
it was activated before Perfecty data existed), the import stops with
"This site already has different VAPID keys". Choose one:

- **Replace keys** (Tools checkbox or `--overwrite-keys`): Perfecty
  subscribers work; anyone who subscribed with the newer keys must subscribe
  again.
- **Keep keys**: imported Perfecty subscribers will not receive anything.

If keys are pinned in `wp-config.php`, the import never replaces them and
logs a warning when they differ.

## Rolling back

Deactivate Best Web Push Notifications and reactivate Perfecty Push. Perfecty's
data was never modified. Subscribers who visited in between have our worker
at Perfecty's scope; Perfecty re-registers its own worker on their next visit.

## Code that used Perfecty's PHP API

Replace `Perfecty_Push_Integration` with `BWPN_Integration`. The method
names are the same; see [developer.md](developer.md) for return values.
