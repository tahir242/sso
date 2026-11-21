# PHP

The **SSO kit** puts a PHP application behind single sign-on with one config file and one line of code. It works with plain PHP pages and any framework, needs **PHP 8.1+** with the `curl` and `openssl` extensions, and does not need Composer.

## 1. Get the kit

On the application's **Connect** page in the admin console, click **Download PHP kit**. The zip contains:

- `sso-client/`: the client library (copy it as it is; it refuses web access via `.htaccess`)
- `sso.php`: your settings, already filled in except the client secret
- `SSO-README.txt`: this page, short

Copy `sso-client/` and `sso.php` into the application's root folder and paste the **client secret** into `sso.php`. Keep `sso.php` out of version control.

> Using Composer? `composer require tahir242/sso-client` instead of the `sso-client` folder, and `require 'vendor/autoload.php'`. Everything else is the same.

## 2. Protect your pages

At the very top of every page, or of the file every page already includes (`_init.php`, `config.php`, `header.php`, ...):

```php
<?php
require __DIR__ . '/sso-client/autoload.php';
$user = \SsoClient\Gate::protect(__DIR__ . '/sso.php');
```

Anyone not signed in is sent to the SSO and comes back to the page they asked for. The same line also handles the SSO's callback, sign-out and back-channel logout, so you write no other files.

`sso.php`:

```php
<?php
return [
    'issuer' => '{{issuer}}',
    'client_id' => '{{client_id}}',
    'client_secret' => 'PASTE-THE-CLIENT-SECRET-HERE',
    'redirect_uri' => '{{php_redirect_uri}}',
    'post_logout_redirect_uri' => '{{post_logout_uri}}',
    'cache_dir' => __DIR__ . '/storage/sso',   // writable, not web-readable
    'roles' => [],                              // e.g. ['clerk', 'admin'] to let only those in
];
```

`redirect_uri` is the page that runs `Gate::protect()` (usually `index.php`) followed by `?sso=callback`.

## 3. Use the signed-in user

```php
$user->userId;       // 42: same number as the old SSO's UserID
$user->username;     // ahmed.khan
$user->name;         // Ahmed Khan
$user->empId;        // 20456
$user->empInitial;   // AK
$user->department;   // Nephrology
$user->designation;  // Medical Officer
$user->email;
$user->roles;        // ['clerk']: this application's roles only
$user->hasRole('clerk');
$user->usedTwoStepVerification();
```

Typical change in an existing application: wherever it read the old session (`$_SESSION['UserID']`, `user_id()`, ...), read `$user->userId` instead, and replace the old role lookup with `$user->hasRole(...)`.

Sign-out link:

```php
<a href="<?= \SsoClient\Gate::logoutUrl() ?>">Sign out</a>
```

Public pages that only want to know *whether* someone is signed in:

```php
$user = \SsoClient\Gate::user(__DIR__ . '/sso.php');   // null if not signed in; no redirect
```

## 4. Register the URLs at the SSO

The administrator can do this in one click (**Register PHP gate URLs** on the Connect page):

| | |
|---|---|
| Redirect URI | `{{php_redirect_uri}}` |
| Back-channel logout URI | `{{php_backchannel_uri}}` |
| Post-logout redirect URI | `{{post_logout_uri}}` |

## Calling the API

```php
$api = \SsoClient\Gate::client()->api();
$api->users(['search' => 'ahmed']);      // users.read
$api->roles();                           // roles.read
$api->requestAccess([                    // access-requests.write
    'username' => 'ayesha.khan',
    'roles' => ['clerk'],
    'justification' => 'Joining the payroll team.',
]);
```

See the [API guide](api) for scopes and responses.

## Troubleshooting

| You see | Cause |
|---|---|
| "Sign-in request expired or was not started here" | The PHP session was lost between leaving and coming back: sessions not starting, a different session cookie path, or the user took longer than 10 minutes. |
| SSO says the redirect URI is invalid | `redirect_uri` in `sso.php` doesn't exactly match a registered one (`http` vs `https`, `index.php` missing, trailing slash). |
| "Cannot create SSO cache directory" | `cache_dir` is not writable by the web server. |
| Users get "No access" from your app | `roles` in `sso.php` lists roles they don't have. |

## Worked example: HR

The HR application (`htdocs/hr`) is connected exactly this way; use it as the model for other applications.

| File | What changed |
|---|---|
| `sso-client/`, `SSO-README.txt` | Unpacked from the kit, unchanged. |
| `sso.php` | From the kit, client secret pasted in. Not committed (`.gitignore`); `sso.example.php` is the committed copy without the secret. |
| `_init.php` (included by every page) | `require_once ROOT . '/sso-client/autoload.php';` and, for web requests, `$registry->set('sso', SsoClient\Gate::protect(ROOT . '/sso.php'));`. The old token check against the legacy SSO was removed. |
| `index.php` | Just redirects to the dashboard: the gate has signed the user in by then. |
| `app/logout.php` | `redirect(SsoClient\Gate::logoutUrl());` |
| `_inc/helper/user.php`, `_inc/lib/user.php` | `user_id()` and friends read the signed-in user (`userId`, `name`, ...) instead of the old session token. HR's own roles stay keyed by the same user ID. |
| `_inc/helper/sso.php` | Small helpers: `sso()` (the client, for the API), `sso_issuer()`, links to the SSO's account and "My applications" pages, and the user list sync through the API. |
| Removed | `sso/callback.php`, `sso/backchannel-logout.php`, the `SSO_*` constants in `config.php`, the old `requests` HTTP library and the `APPID`/HMAC handshake. |

Registered at the SSO: redirect URI `https://hr.example.com/index.php?sso=callback`, back-channel logout URI `https://hr.example.com/index.php?sso=backchannel-logout`, post-logout URI `https://hr.example.com/`.

Optional hardening in the application's `.htaccess` (PHP already runs `sso.php` without printing anything, so this only matters if PHP were ever switched off):

```apache
<Files "sso.php">
    Require all denied
</Files>
```

## Without the gate

Applications that need full control (custom callback page, several entry points with different rules) can use `SsoClient\Client` directly: `authorizationUrl()`, `handleCallback($_GET)`, `identity()`, `logoutUrl()`, `handleBackChannelLogout($token)`.
