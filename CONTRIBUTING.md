# Contributing

Thanks for taking the time to contribute! Bug reports, fixes, documentation and new
integration guides are all welcome.

## Before you start

- **Bugs:** search the [existing issues](https://github.com/tahir242/sso/issues) first, then
  open a bug report with the steps to reproduce it.
- **New features:** open a feature request first so we can agree on the approach before you
  spend time on code.
- **Security issues:** never in a public issue. See [SECURITY.md](SECURITY.md).

## Development setup

Requirements: PHP 8.4 (with `ldap`, `sodium`, `gd`, `intl`, `zip`, `pdo_sqlite`), Composer 2,
Node.js 20+. SQL Server is only needed to run the application, not the tests.

```bash
git clone https://github.com/<you>/sso.git
cd sso
composer install
cp .env.example .env
php artisan key:generate
npm ci
```

Run the checks that CI runs:

```bash
vendor/bin/pint --test        # code style (vendor/bin/pint fixes it)
vendor/bin/pest               # feature tests, SQLite in memory
npm run build                 # front-end builds

cd packages/sso-client        # only if you changed the PHP client
composer install && vendor/bin/phpunit
```

## Pull requests

1. Fork the repository and create a branch from `main` (`fix/lockout-message`,
   `feat/saml-guide`, ...).
2. Keep each pull request focused on one change. Add or update tests for behaviour changes.
3. Use [Conventional Commits](https://www.conventionalcommits.org/) for commit messages, for
   example `fix: keep the lockout timer after a password reset` or
   `docs: add a GitLab integration guide`.
4. Add a line under **Unreleased** in [CHANGELOG.md](CHANGELOG.md) for anything users notice.
5. Make sure CI is green, then ask for a review.

## Coding guidelines

- Follow the existing structure: business logic in `app/Domain/<Area>`, admin screens in
  `app/Filament`, portal pages in `app/Livewire`.
- Database changes go in new migrations; never edit a migration that has been released.
- Settings that administrators change belong in a settings class (`app/Domain/Settings`),
  not in `.env`.
- Keep the SQL Server compatibility in mind (2012+): avoid database-specific SQL in queries.
- User-facing text should be short, plain and free of jargon.

## Releases

Maintainers release by updating `CHANGELOG.md`, then tagging `vX.Y.Z` on `main`. The
[release workflow](.github/workflows/release.yml) runs the tests, builds the release zip and
publishes the GitHub release. Versions follow [Semantic Versioning](https://semver.org/).

By contributing you agree that your contributions are licensed under the [MIT License](LICENSE).
