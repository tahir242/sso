# Security policy

SSO controls access to other applications, so security reports are taken seriously and
handled before anything else.

## Supported versions

| Version | Supported |
|---|---|
| Latest release (`1.x`) | ✅ |
| Older releases | ❌ Please update to the latest release |

## Reporting a vulnerability

**Please do not open a public issue, discussion or pull request for security problems.**

Report it privately through GitHub:
**[Security → Report a vulnerability](https://github.com/tahir242/sso/security/advisories/new)**.

Please include:

- what is affected (sign-in, OIDC endpoints, admin console, API, ...) and the version;
- steps to reproduce, or a proof of concept;
- the impact as you see it.

What to expect:

- an acknowledgement within **3 working days**;
- an assessment and, if confirmed, a planned fix date within **10 working days**;
- a fixed release and a published security advisory, crediting you unless you prefer
  otherwise.

Please give us a reasonable time to release a fix before disclosing the issue publicly.

## Hardening tips for operators

- Serve the site over HTTPS only, with `APP_DEBUG=false`.
- Point the web server's document root at `public/`, never the project folder.
- Keep `.env`, `storage/oauth-private.key` and the backup password out of the web root and
  in a password safe.
- Require two-step verification for administrators (Settings → Two-step verification).
- Run `php artisan sso:check` after every change and keep PHP and SQL Server patched.
