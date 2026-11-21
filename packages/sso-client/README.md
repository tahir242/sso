# tahir242/sso-client

Signs PHP applications in through the [SSO](../../README.md) OpenID Connect
provider (authorization code + PKCE). PHP 8.1+, depends only on `firebase/php-jwt`.

## Quickest: the one-line gate

Download the **PHP kit** from the application's Connect page in the SSO admin
console (works without Composer), or install it with Composer (section 2). Then,
at the top of every page:

```php
require __DIR__ . '/sso-client/autoload.php';            // or vendor/autoload.php
$user = \SsoClient\Gate::protect(__DIR__ . '/sso.php'); // sends guests to the SSO
echo $user->name, $user->userId, implode(',', $user->roles);
```

`sso.php` (filled in by the kit; add the client secret):

```php
return [
    'issuer' => 'https://sso.example.com',
    'client_id' => '...',
    'client_secret' => '...',
    'redirect_uri' => 'https://apps.example.com/lab/index.php?sso=callback',
    'post_logout_redirect_uri' => 'https://apps.example.com/lab/',
    'cache_dir' => __DIR__ . '/storage/sso',
    'roles' => [],   // optional: only these roles get in
];
```

The gate page also answers `?sso=callback`, `?sso=logout` and
`?sso=backchannel-logout` (register `PAGE?sso=callback` and
`PAGE?sso=backchannel-logout` at the SSO). Sign-out link: `Gate::logoutUrl()`.
Full guide: `<issuer>/docs/integration/php`.

The rest of this README covers the lower-level `Client` for applications that
need their own callback pages.

## 1. Register the application in the SSO

Admin console → **Identity → Applications → Register application**:

| Field | Example |
|---|---|
| Redirect URI | `https://hr.example.com/hr/sso/callback.php` |
| Post-logout redirect URI | `https://hr.example.com/hr/` |
| Back-channel logout URI | `https://hr.example.com/hr/sso/backchannel-logout.php` |

Copy the client ID and the client secret (shown once) into the application's
untracked config file. Give users access on the application's **Users** tab.

## 2. Install

Copy this folder into your project (for example `packages/sso-client`) and add:

```json
"repositories": [{ "type": "path", "url": "packages/sso-client" }],
"require": { "tahir242/sso-client": "^1.2" }
```

## 3. Use

```php
$sso = new SsoClient\Client(new SsoClient\Config(
    issuer: 'https://sso.example.com',
    clientId: SSO_CLIENT_ID,
    clientSecret: SSO_CLIENT_SECRET,
    redirectUri: 'https://hr.example.com/hr/sso/callback.php',
    postLogoutRedirectUri: 'https://hr.example.com/hr/',
    cacheDir: __DIR__.'/storage/sso',   // writable, never web-served
));

// every request (after session_start())
$user = $sso->identity();            // SsoClient\Identity or null
if (! $user) $sso->redirectToLogin($_SERVER['REQUEST_URI']);
$user->userId; $user->empId; $user->name; $user->hasRole('admin');

// callback.php
$result = $sso->handleCallback($_GET);          // throws SsoException on failure
header('Location: '.($result['return_to'] ?? '/'));

// logout
header('Location: '.$sso->logoutUrl());

// backchannel-logout.php (POST, no session needed)
$sso->handleBackChannelLogout($_POST['logout_token']);

// server-to-server (client credentials); each call needs its scope enabled for
// the application in the SSO admin console (Applications → Single sign-on).
$sso->api()->users(['search' => 'ahmed']);             // users.read
$sso->api()->user(42);                                  // users.read
$sso->api()->roles();                                   // roles.read
$sso->api()->accessRequests(['status' => 'pending']);   // access-requests.read
$sso->api()->requestAccess([                            // access-requests.write (normal approvals apply)
    'username' => 'ayesha.khan',
    'roles' => ['clerk'],
    'justification' => 'Joining the payroll team.',
]);
```

Full API reference (sign in as an application owner): `<issuer>/docs/api`.

What it does for you: PKCE/state/nonce, ID token signature and claim checks
(keys from JWKS, cached; key rotation handled), silent token refresh (roles
update within the access-token lifetime), sign-out when the SSO refuses a
refresh (access removed, account disabled), single logout, and back-channel
logout that works with any PHP session storage.

## Claims

`sub` (permanent id), `user_id` (numeric, stable),
`preferred_username`, `name`, `email`, `emp_id`, `emp_initial`, `department`,
`designation`, `roles` (this application's roles only), `sid`, `amr`.

## .NET and Java applications

Use the platform's built-in OpenID Connect support with the issuer URL above
(discovery at `/.well-known/openid-configuration`), response type `code`,
PKCE S256, scopes `openid profile email employee roles`, and the client ID
and secret from the SSO.

- ASP.NET Core: `AddOpenIdConnect(o => { o.Authority = issuer; o.ResponseType = "code"; o.UsePkce = true; o.SaveTokens = true; o.MapInboundClaims = false; ... })`
- Spring Boot: `spring.security.oauth2.client.provider.sso.issuer-uri=<issuer>` with `oauth2Login()`

## Tests

```bash
composer install
vendor/bin/phpunit
```
