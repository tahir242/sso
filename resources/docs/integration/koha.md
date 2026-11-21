# Koha

**Koha** (open-source library management system, Koha Community) supports OpenID Connect through **Administration → Identity providers** (Koha 23.05 or later), so SSO can sign people in to both the **staff interface** and the **OPAC**. Koha matches the signed-in person to a patron record and can create patrons on first sign-in.

Product documentation: [Koha manual](https://koha-community.org/manual/)

## How sign-in works

- **Protocol:** OpenID Connect. Koha reads SSO's endpoints from `{{discovery}}`.
- **Callback addresses** (from `Koha/REST/V1/OAuth/Client.pm`), where `SSO` is the provider code:
  - OPAC: `<OPACBaseURL>/api/v1/public/oauth/login/SSO/opac`
  - Staff interface: `<staffClientBaseURL>/api/v1/oauth/login/SSO/staff`
- **Matching patrons:** the *Matchpoint* is **email**, **userid** or **cardnumber**. Mapping `preferred_username` to `userid` lets staff keep their Windows username in Koha.
- **New patrons:** per domain, Koha can auto-register patrons (separately for OPAC and staff) with a default library and category, and update their details on each login.
- **PKCE:** Koha's OAuth/OIDC client does not send PKCE. Koha is a server-side client with a secret, so its registration in SSO has **Require PKCE** switched off.

## 1. Register Koha in SSO

In the admin console open **Identity → Applications → New application** and add both callback addresses above as **Redirect URIs**. Save, copy the **client ID** and **client secret**, then under *Single sign-on* switch **Require PKCE** off for this application.

## 2. Add the identity provider in Koha

**Administration → Identity providers → New identity provider** (needs the `manage_identity_providers` permission):

- **Code:** `SSO` (must match the redirect URIs)
- **Description:** `SSO sign-in`
- **Protocol:** `OIDC`
- **Configuration:**

```json
{
  "key": "{{client_id}}",
  "secret": "<client secret>",
  "well_known_url": "{{discovery}}",
  "scope": "openid email profile"
}
```

- **Mapping:**

```json
{
  "email": "email",
  "firstname": "given_name",
  "surname": "family_name",
  "userid": "preferred_username"
}
```

- **Matchpoint:** `email` (or `userid` if Koha user IDs equal Windows usernames)

## 3. Domain rules

For the provider, add a **domain** (the staff email domain) and set **Default library**, **Default category**, **Allow OPAC**, **Allow staff**, **Auto register** (OPAC and staff interface) and **Update on login**.

## 4. Restart Koha services

Identity provider changes take effect after Koha's services are restarted (ask your Koha hosting provider if you do not run the server).

## 5. Give access and test

Give the pilot users access to Koha in SSO. Open the OPAC and the staff interface, choose the SSO sign-in, and check that the right patron record opens.
