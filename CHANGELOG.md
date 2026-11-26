# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.0] - 2026-11-26

### Added

- Nightly clean-up so tables stay small with many users: `sso:prune` (01:00) deletes
  application sessions 30 days after sign-out, sign-in events and change history after a year,
  read notifications after 90 days, expired remembered computers and failed jobs after 30 days,
  in batches; `passport:purge` (01:15) deletes expired and revoked tokens. The maintenance
  console's "Prune old records" now runs `sso:prune` (the old `model:prune` found nothing).
- Indexes on every foreign-key column (SQL Server does not create them by itself) and on
  `client_sessions.logged_out_at` and `users.last_login_at`.

### Changed

- Two-step verification is each user's own choice: they turn it on or off under
  Account → Security. Nothing forces it on any more.
- The token endpoint limits only failed client authentication (20 a minute per client and
  address, 60 per address) instead of every request (600 a minute per application), so a large
  application can exchange codes and refresh tokens for all its users.
- Back-channel logout: an application whose logout address does not answer is skipped for a
  minute (its logouts wait in the queue instead of holding up other jobs), with a 2-second
  connection timeout; deliveries are retried for an hour instead of three times.
- `sso:end-expired-sessions` works in batches of 1,000 with one session query per batch.

### Fixed

- Deleting an application role failed: it still cleared a table that the portfolio removal
  had dropped.

- Applications are signed out (back-channel logout) when the SSO session expires, not only
  when the user clicks "Sign out": idle timeout, maximum session length, or the browser session
  removed by "sign out other sessions" or a password change. The scheduled
  `sso:end-expired-sessions` command checks every minute; `client_sessions.session_id`
  (new column) links each application session to its browser session. Previously, signing in
  again after an idle timeout and then signing out left applications opened earlier signed in.

### Removed

- The application setting "Require two-step verification to open", the "Verify it's you"
  step-up screen, and the `applications.requires_mfa` column (dropped by a migration).
- The Settings → Two-step verification policy (always for administrators, outside the
  hospital network, hospital network ranges), forced set-up during sign-in, and the
  `sso:check` item about it.

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

[Unreleased]: https://github.com/tahir242/sso/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/tahir242/sso/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/tahir242/sso/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/tahir242/sso/releases/tag/v1.0.0
