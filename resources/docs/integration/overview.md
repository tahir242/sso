# Connecting an application to SSO

SSO is a standard **OpenID Connect** provider. Any language with an OpenID Connect library can use it; nothing product-specific is needed except the values on your application's **Connect** page in the admin console.

| | |
|---|---|
| Issuer | `{{issuer}}` |
| Discovery | `{{discovery}}` |
| Client ID | `{{client_id}}` |
| Client secret | Given to you once by the SSO administrator. Keep it out of version control. |
| Scopes | `{{scopes}}` |

Most libraries need only the **issuer** (or discovery URL), the client ID and secret, and your redirect URI; they read every endpoint from discovery.

## Pick your guide

- **PHP** (most applications): download the kit from the Connect page and add one line per page. No Composer, no callback files. → [PHP guide](php)
- **.NET** (ASP.NET Core, MVC 5, Web Forms) → [.NET guide](dotnet)
- **Java** (Spring Boot) → [Java guide](java)
- **Python** (Django, Flask, FastAPI) → [Python guide](python)
- **Node.js** (Express) → [Node.js guide](node)
- Reading users and roles, or requesting access, from your server → [API guide](api)

## How sign-in works

1. Your application sends the browser to the SSO (*authorization code flow*).
2. The person signs in once (Windows password, or a local SSO account) and, where required, completes two-step verification. If they are already signed in to the SSO, this is instant.
3. The SSO checks that they **have access to your application**. People without access never reach you; they are offered a "request access" page that goes to their head of department and to you as the application owner.
4. The browser comes back to your **redirect URI** with a code; your library exchanges it (with the client secret) for tokens and an **ID token** describing the user.

Rules your library must follow (all the libraries in these guides do):

- **PKCE with S256 is required.** Sign-ins without a `code_challenge` are refused.
- Redirect URIs must match **exactly** what is registered, including the query string.
- ID tokens are signed with **RS256**; keys are published at the JWKS URL in discovery and may rotate.
- Access tokens last **15 minutes**; refresh tokens let you renew them during the working day. A refresh is refused once the person's access is removed or their account is disabled, so treat a failed refresh as "sign out".

## What you learn about the user

Ask only for the scopes you need. Claims come in the ID token (and from the userinfo endpoint).

| Scope | Claims | Notes |
|---|---|---|
| `openid` | `sub`, `sid`, `amr`, `acr`, `auth_time` | `sub` is permanent and unique. `sid` identifies the SSO session (used by back-channel logout). `acr` is `urn:sso:acr:mfa` after two-step verification. |
| `profile` | `name`, `preferred_username`, `updated_at` | `preferred_username` is the Windows logon name for staff. |
| `email` | `email`, `email_verified` | `email_verified` is always `false`; don't use email as a login key. |
| `employee` | `user_id`, `emp_id`, `emp_initial`, `department`, `designation` | `user_id` is the number the old SSO called **UserID**; existing applications can keep using it in their own tables. |
| `roles` | `roles` | List of **this application's** role keys, e.g. `["clerk", "approver"]`. Never another application's roles. |

**Which ID to store:** existing applications should keep using `user_id`. New applications can use `sub`.

**Roles** are managed in the SSO by the application owner (Applications → Roles) and granted through access requests. Map each role key to whatever fine-grained permissions your application has; the SSO does not know your screens or buttons. Role changes reach your application at the next sign-in or token refresh.

## Signing out

**Your sign-out button** should end your own session and then send the browser to the SSO's `end_session_endpoint` with `id_token_hint` (the ID token you received), `client_id` and `post_logout_redirect_uri`. That signs the person out of the SSO too. The post-logout URI must be registered. All the libraries in these guides do this for you.

**Back-channel logout** (recommended): when someone signs out anywhere, the SSO sends a server-to-server `POST` to your *back-channel logout URI* with a form field `logout_token` (a signed JWT). Verify it like an ID token (signature, `iss` = the issuer, `aud` = your client ID), check it has the `events` member `http://schemas.openid.net/event/backchannel-logout` and no `nonce`, then end every local session with that `sid`. Answer `200`. The guides include code for this.

## What to send the SSO administrator

- The **redirect URI(s)** your library uses (each guide says which).
- The **post-logout redirect URI**.
- The **back-channel logout URI**, if you implemented it.
- The **roles** your application needs (key and description), or add them yourself as the application owner.

## Before going live: test checklist

1. Sign in, then open another connected application: no second sign-in.
2. Roles: a user with a role sees the right screens; a user without it does not.
3. Sign out from your application: you are also signed out of the SSO.
4. Sign out from another application: your session ends too (back-channel logout).
5. Remove the user's access in the SSO: they are signed out at the next token refresh (within 15 minutes) and cannot sign in again.
6. A user without access is shown the SSO's "request access" page, not an error.
