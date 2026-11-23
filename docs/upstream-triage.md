# Upstream triage: why Perfecty Push 1.6.5 fails to send

This fork starts from Perfecty Push Notifications v1.6.5 (tag `fork-point`,
commit `c28bf94`). At the fork point the upstream repository had 66 open
items: 63 issues and 3 pull requests (#204, #170, #92).

Most "notifications are scheduled but never delivered" reports trace back to
the ten root causes below. Each entry lists the upstream symptom, the cause
found in the 1.6.5 code, and where this fork fixes it.

| # | Root cause | Upstream issues | Status | Fixed in |
|---|------------|-----------------|--------|----------|
| 1 | Zero-success batch fails the whole job | - | Fixed | `BWPN_Lib_Push_Server::execute_job()`, `finish()` |
| 2 | Crashed worker leaves job locked forever | #180, #210 | Fixed | `BWPN_Lib_Db::claim_notification()`, `get_jobs_to_resume()`, `BWPN_Cron` watchdog |
| 3 | Mixed MySQL and PHP time zones | #158, #190 | Fixed | `BWPN_Lib_Db::now()`, schema without `CURRENT_TIMESTAMP` |
| 4 | Non-English payloads overflow / break JSON | - | Fixed | `BWPN_Lib_Payload::encode()`, `truncate()`, `payload text` column |
| 5 | Cached pages serve expired REST nonces | - | Fixed | `BWPN_Subscribers_Rest` (no nonce, `ignore_stale_nonce()`) |
| 6 | OFFSET pagination skips/duplicates subscribers | - | Fixed | `BWPN_Lib_Db::get_subscribers_after()` (keyset) |
| 7 | 403 responses delete subscribers | #162 | Fixed | `BWPN_Lib_Push_Server::classify()`, `apply_results()` |
| 8 | Padding too large for Firefox Android | #132 | Fixed | minishlink/web-push 6.0.7 -> 11.0.0 |
| 9 | Legacy `aesgcm` encoding by default | - | Server fixed, SDK pending | `content_encoding` column; SDK must send the browser's supported encoding |
| 10 | Block editor re-sends on every update | #101, #167 | Fixed | `BWPN_Post_Publisher::after_insert_post()` |

## 1. A batch with zero successes stops the job

**Symptom:** a broadcast stops partway and is marked failed, even though most
subscribers were never tried.

**Cause:** after each batch, upstream marked the whole job `failed` if that
batch had no successful deliveries. A batch full of expired subscriptions
(common for old lists) stopped the send for everyone after it.

**Fix:** the result of a batch never decides the job's status. The job runs
until no subscribers are left and is then `completed`. If nothing at all was
delivered, `last_error` says so, but the job is not stopped early. The only
early stop is root cause 7's credential check.

## 2. A crashed worker leaves the job locked forever

**Symptom:** "scheduled but never delivered", jobs stuck in "running" (#180,
#210).

**Cause:** the job lock was a boolean `is_taken` with no timestamp. A PHP
fatal error, timeout or killed process left `is_taken = 1`, and no later run
could take the job.

**Fix:**

- `taken_at` is stored with the lock and refreshed after every batch
  (`add_progress()`).
- `claim_notification()` takes the lock atomically with one `UPDATE ... WHERE`
  and also takes over locks older than `BWPN_Lib_Push_Server::STALE_AFTER`
  (10 minutes).
- The watchdog (`bwpn_watchdog`, every 5 minutes) re-queues jobs that are
  due but never started, paused between runs, or locked by a dead worker.
- Unexpected exceptions are retried with back-off up to `MAX_ATTEMPTS` (3)
  before the job is marked failed.

## 3. Mixed MySQL and PHP time zones ("9 hour" bug)

**Symptom:** scheduled notifications go out hours early or late (#158, #190).

**Cause:** some columns defaulted to MySQL `CURRENT_TIMESTAMP` / `NOW()`
(database server time zone) while PHP compared them with UTC times.

**Fix:** every datetime is computed in PHP as UTC with
`BWPN_Lib_Db::now()` (`gmdate()`); the schema has no `CURRENT_TIMESTAMP`
defaults. Admin screens convert to the site time zone only for display
(`bwpn_admin_date()`), and the Send form reads the date in the site time
zone (`wp_timezone()`).

## 4. Non-English payloads overflow or break

**Symptom:** notifications with non-Latin text fail or are cut off.

**Cause:** `json_encode()` without `JSON_UNESCAPED_UNICODE` turns every
non-ASCII character into a 6-byte `\uXXXX` escape, so text in Japanese,
Russian or Arabic overflowed the `varchar(2000)` payload column. Truncation
used `substr()`, which can cut a UTF-8 character in half and produce invalid
JSON.

**Fix:** `BWPN_Lib_Payload::encode()` uses `JSON_UNESCAPED_UNICODE`, shortens
only the body with multibyte-safe `mb_substr()` until the payload fits
`MAX_BYTES` (2,820 bytes, below the push service limit after encryption),
and returns a `WP_Error` instead of sending a broken payload. The column is
now `text`.

## 5. Cached pages serve expired REST nonces

**Symptom:** subscribing fails with HTTP 403 on sites with page caching.

**Cause:** the subscribe call sent a `wp_rest` nonce that was printed into
the page HTML. Page caches (Cloudflare, WP Rocket, LiteSpeed) served that HTML
long after the nonce expired, and WordPress rejects a request with an invalid
nonce before the route runs.

**Fix:** the subscription routes need no nonce. The subscription endpoint URL
is an unguessable capability URL issued by the push service, so knowing it is
the credential for updating or removing it. `ignore_stale_nonce()` clears
`rest_cookie_invalid_nonce` on these routes only, so older cached SDK code
still works. Abuse is limited by rate limits and the push-host allowlist.

## 6. OFFSET pagination skips or duplicates subscribers

**Symptom:** some subscribers never receive a broadcast; others get it twice.

**Cause:** batches were read with `ORDER BY created_at DESC LIMIT n OFFSET m`.
Deleting expired subscribers (or new sign-ups) during the send shifted every
later row, so the next page skipped or repeated rows.

**Fix:** keyset pagination by primary key: `WHERE id > last_cursor ORDER BY
id LIMIT n`. The cursor is stored per job (`last_cursor`), so a resumed job
continues exactly where it stopped.

## 7. 403 responses delete subscribers

**Symptom:** after a configuration mistake the whole subscriber list
disappears (#162).

**Cause:** every non-2xx response was treated as "subscription gone" and the
subscriber was deleted. Push services answer 401/403 when *our* VAPID keys
or subject are wrong, so one misconfiguration wiped the list.

**Fix:** `classify()` maps responses to results:

| Response | Result | Effect |
|----------|--------|--------|
| 2xx | `ok` | failure counter reset, `last_success_at` set |
| 404, 410 | `expired` | subscriber deleted |
| 401, 403 | `auth` | nothing changes for the subscriber |
| 400, 413 | `invalid` | failure counter +1 |
| other, network error | `retry` | failure counter +1 |

If a whole batch is rejected with 401/403, the job stops as `failed` with an
explanation and no subscriber is removed. Subscribers are only removed for
repeated failures by the daily cleanup (`max_failures`, default 5 in a row).

## 8. Firefox Android rejects payloads (413)

**Symptom:** Firefox on Android never receives notifications (#132).

**Cause:** web-push 6.x padded payloads past what Mozilla's service accepts.

**Fix:** minishlink/web-push 11.0.0, which pads within limits.

## 9. Legacy `aesgcm` encoding

**Symptom:** Safari and some newer browsers fail to decrypt.

**Cause:** subscriptions were always sent with the legacy `aesgcm` encoding;
RFC 8291 `aes128gcm` is the standard and the only one Safari supports.

**Fix (server, done):** each subscriber stores `content_encoding`, defaulting
to `aes128gcm`; both encodings were tested end to end. Subscribers imported
from Perfecty use `aes128gcm`.
**Pending (SDK):** the SDK must send the encoding from
`PushManager.supportedContentEncodings`.

## 10. Block editor re-sends on every update

**Symptom:** every "Update" of a published post sends the notification again
(#101, #167).

**Cause:** upstream read a "send on publish" checkbox from post meta on
`transition_post_status`. The block editor keeps the box ticked, and the meta
was saved after the hook ran.

**Fix:** sending runs on `wp_after_insert_post` (meta is saved by then for
both editors), only on the first transition into `publish`, only if the post
has no job yet, and the flag is cleared before the job is created. The block
editor panel hides the option once the post is published.
