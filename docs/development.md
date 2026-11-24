# Development

## Setup

Requirements: PHP 8.2+ with `openssl`, `mbstring`, `json`; Composer 2;
Node 20+ for the SDK; MySQL/MariaDB for tests.

```
git clone https://github.com/envirohost/best-web-push-notifications.git
cd best-web-push-notifications
composer install          # also runs Strauss -> vendor-prefixed/
```

From a bundle file instead of a remote:

```
git clone best-web-push-notifications.bundle best-web-push-notifications
cd best-web-push-notifications
git checkout wip/core-rewrite
git remote add upstream https://github.com/perfectyorg/perfecty-push-wp.git
composer install
```

## Branches and tags

| Ref | Content |
|-----|---------|
| `fork-point` | Upstream Perfecty Push v1.6.5 (`c28bf94`) |
| `main` | Rename and trim commits |
| `wip/core-rewrite` | Current work (core rewrite) |
| `upstream` remote | `perfectyorg/perfecty-push-wp` |

The SDK in `sdk/` is a git subtree of the Perfecty JS SDK with its history.

## Vendor prefixing

Runtime libraries (minishlink/web-push and its dependencies, nyholm/psr7)
are copied by Strauss into `vendor-prefixed/` under the `BWPN\Vendor\`
namespace (`composer prefix-namespaces`, run automatically after install).
The plugin only loads `vendor-prefixed/autoload.php`, lazily, when it sends
or generates keys. `vendor/` holds dev tools only and is not shipped.

## Scripts

| Command | Runs |
|---------|------|
| `composer lint` / `lint:fix` | PHPCS / PHPCBF (WPCS) - config still to add |
| `composer analyse` | PHPStan - config still to add |
| `composer test` | PHPUnit |

## Local WordPress for manual testing

What was used to verify v2.0.0 work so far:

```
# MariaDB
mysql -uroot -e "CREATE DATABASE wp; CREATE USER 'wp'@'localhost' IDENTIFIED BY 'wp'; GRANT ALL ON *.* TO 'wp'@'localhost';"

# WordPress 7.1
mkdir /srv/wp && cd /srv/wp
wp core download --version=7.1
wp config create --dbname=wp --dbuser=wp --dbpass=wp
wp core install --url=https://bwpn.test --title="BWPN Test" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email
ln -s /path/to/best-web-push-notifications wp-content/plugins/
wp plugin activate best-web-push-notifications
wp bwpn status
```

End-to-end sending without a browser: run a fake push service that answers
201/403/410 by path (`php -S 127.0.0.1:8099 router.php`), insert subscribers
whose endpoint points at it with `BWPN_Lib_Db::create_subscriber()` (this
bypasses the HTTPS/allowlist check on purpose), add
`add_filter( 'http_request_host_is_external', '__return_true' )`, then
schedule and `execute_job()`. Requests arrive encrypted with VAPID headers,
so this exercises the real web-push path.

For logic-only tests, replace the transport with
`BWPN_Lib_Push_Server::set_sender()` (see [developer.md](developer.md)).

## Cloud sandbox quirk (Claude sessions)

In the Anthropic cloud sandbox, GitHub zip downloads through
`api.github.com` are blocked; git clones work. Every package except
`phpstan/phpstan` falls back to git automatically. PHPStan publishes no git
source, so:

1. `git clone --depth 1 -b <version> https://github.com/phpstan/phpstan /tmp/pst`
2. `git archive --format=zip --prefix=phpstan/ HEAD -o /tmp/phpstan.zip`
3. Point the `phpstan/phpstan` dist `url` in `composer.lock` at that file,
   `composer install`, then **restore `composer.lock`** before committing.

On a normal machine none of this is needed.

## Commit conventions

Commits made in Claude sessions use `Claude <noreply@anthropic.com>` as
author and committer so GitHub shows them as verified, and end with the
`Co-Authored-By` / `Claude-Session` trailers.
