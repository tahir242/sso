# Nextcloud

**Nextcloud** (open-source file sharing and collaboration) signs people in through SSO with the official **OpenID Connect user backend** app (`user_oidc`). Accounts are created on first sign-in, and **back-channel logout** ends the Nextcloud session when the user signs out of SSO.

Product documentation: [nextcloud/user_oidc](https://github.com/nextcloud/user_oidc)

## How sign-in works

- **Protocol:** OpenID Connect with **PKCE**, which `user_oidc` uses by default when the provider supports S256 (SSO does). Keep **Require PKCE** on.
- **Accounts:** created automatically on first sign-in (`auto_provision`, default on), or only existing accounts are updated (`disable_account_creation`).
- **Single sign-out:** SSO calls `https://<nextcloud>/index.php/apps/user_oidc/backchannel-logout/SSO`.
- **Groups (optional):** `user_oidc` can provision groups from a claim; the `roles` claim can serve this purpose.

## 1. Register Nextcloud in SSO

In the admin console open **Identity → Applications → New application**:

- **Redirect URI:** the one Nextcloud shows for the provider on its *OpenID Connect* admin settings page
- **Back-channel logout URI:** `https://<nextcloud>/index.php/apps/user_oidc/backchannel-logout/SSO`

Save and copy the **client ID** and **client secret**. Leave **Require PKCE** on.

## 2. Install the app

In Nextcloud, install and enable **OpenID Connect user backend** (`user_oidc`) from the Apps page.

## 3. Create the provider

On the Nextcloud server:

```bash
sudo -u www-data php occ user_oidc:provider SSO \
  --clientid="{{client_id}}" \
  --clientsecret="<client secret>" \
  --discoveryuri="{{discovery}}"
```

Run `occ user_oidc:provider --help` for the attribute-mapping options of your version: map the user ID to `preferred_username`, the display name to `name` and email to `email`.

## 4. Provisioning options

The defaults create accounts on first sign-in and use PKCE. To set them explicitly, add to `config/config.php`:

```php
'user_oidc' => [
    'use_pkce' => true,
    'auto_provision' => true,
],
```

## 5. Give access and test

Give the pilot users access in SSO, sign in with the SSO button, then sign out of SSO and confirm the Nextcloud session ends.
