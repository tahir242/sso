# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-11-24

### Added

- Settings → Cache, queue & Redis: Redis connection (Predis built in, phpredis when installed),
  cache and queue store selection, connection test with server advice, recommended preset,
  and a fail-safe that falls back to the database while Redis is unreachable.
- Settings → Maintenance commands: a fixed list of artisan commands (optimize, cache, queue,
  housekeeping, diagnostics) for super-admins, with output and an audit trail.
- `sso:check` reports on Redis.

### Changed

- The scheduler also drains the queue connection that is not in use, so no job is stranded
  after switching between the database and Redis.
- Settings are always cached in the `.env` cache store (`SETTINGS_CACHE_STORE`).
- New dependency: `predis/predis`, so Redis works without the phpredis extension.

## [1.0.0] - 2026-11-21

First public release.

### Added

- OpenID Connect provider: authorization code with PKCE, refresh tokens, client credentials,
  discovery, JWKS, RP-initiated logout and back-channel logout.
- Sign-in with Active Directory or local accounts, remembered accounts, lockout, password
  rules and session limits.
- Two-step verification with authenticator apps, recovery codes, trusted devices and step-up
  verification.
- Application registry with per-application roles, access modes and a scoped REST API.
- Access requests with approvals and escalation, access reviews and new-staff requests.
- IT helpdesk with SLAs, suggestions, and email, in-app, push and WhatsApp notifications.
- Audit log, sign-in activity and security alerts.
- Public Help & FAQ page linked from the sign-in screen.
- Readiness check (`sso:check`), encrypted backups (`sso:backup`), `sso:create-admin`.
- Windows deployment without internet: release zip, `install.cmd`, `update.cmd` and
  `scheduled-task.cmd`.
- Integration guides (PHP, .NET, Java, Node.js, Python, Moodle, Koha, Nextcloud, Grafana) and
  the `tahir242/sso-client` PHP library.
- SQL Server 2012 and 2014 compatibility.

[Unreleased]: https://github.com/tahir242/sso/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/tahir242/sso/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/tahir242/sso/releases/tag/v1.0.0
