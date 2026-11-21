# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/tahir242/sso/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/tahir242/sso/releases/tag/v1.0.0
