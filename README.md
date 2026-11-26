# SSO

[![CI](https://github.com/tahir242/sso/actions/workflows/ci.yml/badge.svg)](https://github.com/tahir242/sso/actions/workflows/ci.yml)
[![Latest release](https://img.shields.io/github/v/release/tahir242/sso?sort=semver)](https://github.com/tahir242/sso/releases)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777bb4.svg)

A self-hosted **single sign-on and identity portal** for organisations that run a mix of
in-house and off-the-shelf web applications. Staff sign in once (with their Windows /
Active Directory account or a local account) and every connected application opens without
another password.

Built with Laravel 13, Filament 5 and Livewire. Runs on Windows + Apache + SQL Server, and
installs on servers **without internet access** from a single release zip.

## Features

- **OpenID Connect provider:** authorization code + PKCE, refresh tokens, client credentials,
  discovery (`/.well-known/openid-configuration`), JWKS, RP-initiated and back-channel logout.
- **Sign-in:** Active Directory (LDAP) or local accounts, remembered accounts on shared
  computers, lockout and password rules, idle and absolute session limits.
- **Two-step verification:** each user turns it on or off themselves; authenticator apps, recovery codes, trusted devices
  verification for sensitive actions.
- **Applications:** register OIDC clients, per-application roles, open-to-everyone or
  assigned-users-only access, server-to-server REST API with scopes.
- **Access governance:** access requests with approvals and escalation, periodic access
  reviews, new-staff account requests.
- **Helpdesk and suggestions:** IT tickets with SLAs and attachments.
- **Notifications:** email, in-app, browser push and WhatsApp (Green-API).
- **Audit:** sign-in activity, security alerts, full change log.
- **Operations:** readiness check, encrypted nightly backups, Windows scheduled task, cmd-only
  install and update scripts.
- **Integration guides** built in: PHP, .NET, Java, Node.js, Python, Moodle, Koha, Nextcloud,
  Grafana, plus a ready-to-use PHP client ([`packages/sso-client`](packages/sso-client)).

## Requirements

| | |
|---|---|
| PHP | 8.4 with `pdo_sqlsrv`, `ldap`, `sodium`, `openssl`, `curl`, `mbstring`, `zip`, `fileinfo`, `gd`, `intl` |
| Database | SQL Server 2017 or later (2012 and 2014 work) |
| Web server | Apache with `mod_rewrite` (for example XAMPP) |
| Build only | Composer 2, Node.js 20+ |

## Quick start (development)

```bash
git clone https://github.com/tahir242/sso.git
cd sso
composer install
cp .env.example .env        # set APP_ENV=local, APP_DEBUG=true and your DB_* values
php artisan key:generate
php artisan passport:keys
php artisan migrate --seed
php artisan sso:create-admin
npm ci && npm run dev
```

Open `APP_URL/login`. The admin console is at `/admin`.

## Production

Download `sso-vX.Y.Z.zip` from [Releases](https://github.com/tahir242/sso/releases) and follow
the **[deployment guide](docs/deployment.md)**: first installation with `deploy\install.cmd`,
updates with `deploy\update.cmd <zip>`, background jobs with one Windows scheduled task.

## Commands

| Command | Use |
|---|---|
| `php artisan sso:check [--http]` | Readiness and health report |
| `php artisan sso:create-admin` | Create an administrator |
| `php artisan sso:backup [--verify=<archive>]` | Encrypted backup (also scheduled nightly) |

Scheduled jobs (notifications, escalations, access reviews, helpdesk SLAs, backups) need
`php artisan schedule:run` every minute; it also works the queue.

## Project layout

| Folder | What is in it |
|---|---|
| `app/Domain` | Business logic by area: Identity, Federation (OIDC, applications), Access, Requests, Helpdesk, Audit, Engagement, Notifications, Settings |
| `app/Filament` | Admin console |
| `app/Livewire`, `resources/views` | Staff portal and sign-in pages |
| `app/Console/Commands` | `sso:*` commands |
| `database` | Migrations, settings migrations, seeders |
| `deploy` | `build-release.ps1` and the server scripts `install.cmd`, `update.cmd`, `scheduled-task.cmd` |
| `docs` | [Deployment guide](docs/deployment.md) |
| `packages/sso-client` | PHP client library and no-Composer kit for connected applications |
| `resources/docs/integration` | Integration guides shown in the app at `/docs/integration` |
| `tests` | Pest feature tests; `tests/load` load test |

## Testing

```bash
vendor/bin/pest            # SQLite in memory, no database server needed
vendor/bin/pint --test     # code style
```

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) and the
[Code of Conduct](CODE_OF_CONDUCT.md). Report security issues privately as described in
[SECURITY.md](SECURITY.md), never in a public issue.

## License

[MIT](LICENSE)
