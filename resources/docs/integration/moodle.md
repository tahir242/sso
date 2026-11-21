# Moodle

**Moodle** (open-source learning platform, Moodle HQ) can use SSO as its sign-in through its built-in **OAuth 2 services**, which speak OpenID Connect. Learners click *Sign in with SSO*, sign in once, and land in their courses. Moodle keeps its own course roles and enrolments; SSO decides who can sign in at all.

Product documentation: [OAuth 2 services](https://docs.moodle.org/en/OAuth_2_services) · [OAuth 2 authentication](https://docs.moodle.org/en/OAuth_2_authentication)

## How sign-in works

- **Protocol:** OpenID Connect, authorisation code flow. Moodle reads SSO's endpoints from `{{discovery}}`.
- **Scopes:** `openid profile email` give Moodle `given_name`, `family_name`, `preferred_username` and `email`.
- **Matching accounts:** Moodle links an SSO sign-in to an existing Moodle account **with the same email address**. New users get an account automatically unless *Prevent account creation when authenticating* is on.
- **PKCE:** Moodle's core OAuth 2 client (`lib/oauthlib.php`, `lib/classes/oauth2/client.php`) does not send PKCE. Moodle is a server-side client with a secret, so its registration in SSO has **Require PKCE** switched off.
- **Email verification:** SSO reports `email_verified: false` (addresses are maintained by administrators, not confirmed by the user). If your Moodle version offers *Require email verification* on the service, decide whether new accounts should receive Moodle's confirmation email.

## 1. Register Moodle in SSO

In the admin console open **Identity → Applications → New application**:

- **Redirect URI:** `https://<moodle-site>/admin/oauth2callback.php`
- **Who can open it:** *Assigned users only* (recommended) or *Everyone*

Save, copy the **client ID** and **client secret** (the secret is shown only once), then under *Single sign-on* switch **Require PKCE** off for this application.

## 2. Create the OAuth 2 service in Moodle

**Site administration → Server → OAuth 2 services** → create a custom service:

- **Name:** `SSO`
- **Client ID:** `{{client_id}}` and **Client secret:** from step 1
- **Service base URL:** `{{issuer}}`
- **Scopes included in a login request:** `openid profile email`
- Use the service **on the login page**, with a clear name such as *Sign in with SSO*

Leave *Authenticate token requests via HTTP headers* unticked. Optionally set **Login domains** to the staff email domain.

## 3. Check endpoints and user field mappings

Beside the service, **Configure endpoints** should list `authorization_endpoint`, `token_endpoint` and `userinfo_endpoint` under `{{issuer}}/oauth/`. In **Configure user field mappings** make sure:

| SSO claim | Moodle field |
|---|---|
| `given_name` | First name |
| `family_name` | Last name |
| `email` | Email address |

Unmapped fields send the user to *Edit profile* after the first sign-in.

## 4. Enable OAuth 2 authentication

**Site administration → Plugins → Authentication → Manage authentication** → enable **OAuth 2**. To allow only existing Moodle accounts, turn on *Prevent account creation when authenticating*.

## 5. Give access and test

Give the pilot users access to Moodle in SSO (or set it to *Everyone*). Sign out of Moodle, choose *Sign in with SSO*, and check the new or linked account under **Site administration → Users**.
