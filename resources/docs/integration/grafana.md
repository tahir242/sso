# Grafana

**Grafana** (open-source dashboards and monitoring, Grafana Labs) signs people in through SSO with its **Generic OAuth** provider, using OpenID Connect and PKCE. SSO application roles can decide whether a person is a Grafana **Viewer**, **Editor** or **Admin**.

Product documentation: [Configure generic OAuth2 authentication](https://grafana.com/docs/grafana/latest/setup-grafana/configure-access/configure-authentication/generic-oauth/)

## How sign-in works

- **Protocol:** OpenID Connect via `[auth.generic_oauth]` with `use_pkce = true` (S256). Keep **Require PKCE** on.
- **Endpoints** are set explicitly: `{{issuer}}/oauth/authorize`, `{{issuer}}/oauth/token`, `{{issuer}}/oauth/userinfo`.
- **Roles:** request the `roles` scope; `role_attribute_path` maps SSO roles to Grafana roles. Without a matching role the user is a Viewer.
- **Email:** Grafana requires an email address to sign in; every SSO account has one.

## 1. Register Grafana in SSO

In the admin console open **Identity → Applications → New application** with **Redirect URI** `https://<grafana>/login/generic_oauth`. Copy the **client ID** and **client secret**. Leave **Require PKCE** on.

## 2. Create application roles

On the Grafana application add roles with the keys `admin` and `editor` (the default `user` role becomes Viewer), and give people the role they need.

## 3. Configure Grafana

In `grafana.ini` (or the matching environment variables):

```ini
[auth.generic_oauth]
enabled = true
name = SSO
client_id = {{client_id}}
client_secret = <client secret>
scopes = openid profile email roles
auth_url = {{issuer}}/oauth/authorize
token_url = {{issuer}}/oauth/token
api_url = {{issuer}}/oauth/userinfo
use_pkce = true
login_attribute_path = preferred_username
name_attribute_path = name
email_attribute_path = email
role_attribute_path = contains(roles[*], 'admin') && 'Admin' || contains(roles[*], 'editor') && 'Editor' || 'Viewer'
allow_sign_up = true
```

If Grafana runs behind a proxy, set `root_url` in `[server]` so the callback address is correct. Restart Grafana.

## 4. Test

Sign in with the SSO button using accounts with each role, and check the Grafana role on the user's profile.
