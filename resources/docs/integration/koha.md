# Koha

**Koha** (the open-source library system) can sign people in through SSO with OpenID Connect, on both the **staff interface** and the **OPAC** (the public catalogue). It needs Koha **23.05 or later**, where the setting lives under **Administration → Identity providers**.

Product documentation: [Koha manual](https://koha-community.org/manual/)

## How sign-in works

People click **Sign in with SSO** on the Koha sign-in page, sign in on the SSO page, and come back to Koha. Koha reads the person's details from SSO's ID token, finds (or creates) their patron record, and signs them in. In Koha the connection is called an **identity provider**; in SSO, Koha is an **application**.

You set things up in this order:

- **Before you start:** check Koha's two base URLs.
- **Step 1:** create the identity provider in Koha (this is where you choose its **code**).
- **Step 2:** register Koha as an application in SSO, using that code in the redirect URIs.
- **Step 3:** put SSO's client ID and secret into the Koha identity provider.
- **Steps 4–5:** add a domain rule, restart Koha, and test.
- **Step 6:** make Koha's **Log out** sign people out of SSO too.
- **Step 7 (optional):** skip Koha's own login form and go straight to SSO.

## Before you start: Koha's base URLs

Koha builds its sign-in return addresses from two **system preferences** (Administration → System preferences, search for them):

| System preference | What it is | Example |
|---|---|---|
| `OPACBaseURL` | Address of the OPAC | `https://library.example.org` |
| `staffClientBaseURL` | Address of the staff interface | `https://library-staff.example.org` |

**Both must be filled in and correct.** The exact values (`https` or `http`, host name, port, no trailing `/`) are what you register in SSO in step 2. If they are empty or different from the address people really use, SSO refuses the sign-in with an *invalid redirect URI* error.

## 1. Create the identity provider in Koha

In Koha open **Administration → Identity providers → New identity provider** (you need the `manage_identity_providers` permission) and fill in:

| Field | Value | Notes |
|---|---|---|
| **Code** | `SSO` | A short name with no spaces. **It becomes part of the redirect URIs**, so remember it exactly (it is case-sensitive). |
| **Description** | `Sign in with SSO` | The text people see on the sign-in button. |
| **Protocol** | `OIDC` | OpenID Connect. |
| **Matchpoint** | `email` | How Koha finds the existing patron for the person signing in. See *Matching people to patrons* below. |
| **Icon URL** | *(optional)* | A small logo for the button. |

Leave the **Configuration** (client ID and secret) for step 3; you get those values from SSO in step 2. If Koha will not save without them, type a placeholder such as `pending` and replace it in step 3. Fill in the **Mapping** now (below), then save.

### Mapping: which SSO claim fills which patron field

Koha copies values from SSO's ID token (*claims*) into the patron record. Every claim below is a standard OpenID Connect claim; SSO sends it from the user's profile in the admin console (**Identity → Users**).

| Koha patron field | SSO claim | Scope | Filled from (SSO user form) |
|---|---|---|---|
| `email` | `email` | `email` | Email address |
| `firstname` | `given_name` | `profile` | First name |
| `surname` | `family_name` | `profile` | Last name |
| `othernames` | `nickname` | `profile` | Known as |
| `userid` | `preferred_username` | `profile` | Username (the Windows logon name for staff) |
| `dateofbirth` | `birthdate` | `profile` | Date of birth (sent as `YYYY-MM-DD`) |
| `mobile` | `phone_number` | `phone` | Phone number |
| `cardnumber` | `emp_id` | `employee` | Employee ID *(optional, SSO-specific claim)* |

As JSON (the form Koha shows in older versions):

```json
{
  "email": "email",
  "firstname": "given_name",
  "surname": "family_name",
  "othernames": "nickname",
  "userid": "preferred_username",
  "dateofbirth": "birthdate",
  "mobile": "phone_number"
}
```

Map only what you need. Two things to keep in mind:

- **Koha requires a surname.** Make sure the people who use Koha have a **Last name** in SSO, or Koha cannot create their patron record.
- SSO sends `gender` as `female` / `male` (the OpenID Connect values), but Koha's `sex` field expects its own codes, so do not map it.

### Matching people to patrons

The **Matchpoint** is the patron field Koha compares to find the existing record:

- `email` (simplest): works when every patron's email in Koha is the same as in SSO.
- `userid`: use when Koha user IDs are the Windows usernames; map `userid` → `preferred_username`.
- `cardnumber`: use when library cards carry the employee ID; map `cardnumber` → `emp_id` and add the `employee` scope.

## 2. Register Koha in SSO

In the SSO admin console open **Identity → Applications → New application**:

| Field | Value |
|---|---|
| **Name** | `Koha` (or `Library`) |
| **Home page URL** | Your `OPACBaseURL` (or `staffClientBaseURL` if only staff use Koha) |
| **Redirect URIs** | The two addresses below; press Enter after each. |
| **Post-logout redirect URIs** | `<OPACBaseURL>/cgi-bin/koha/opac-main.pl` and `<staffClientBaseURL>/cgi-bin/koha/mainpage.pl` (see step 6) |
| **Require PKCE** | **Off** (see below) |

### Redirect URIs

Each redirect URI is made of a Koha base URL, a fixed path, and the **Code** you chose in step 1:

```text
OPAC:           <OPACBaseURL>/api/v1/public/oauth/login/<Code>/opac
Staff interface: <staffClientBaseURL>/api/v1/oauth/login/<Code>/staff
```

With the example values above and the code `SSO`:

```text
https://library.example.org/api/v1/public/oauth/login/SSO/opac
https://library-staff.example.org/api/v1/oauth/login/SSO/staff
```

- `<Code>` is **exactly** the code typed in Koha (`SSO` here). If you chose `siut-sso` in Koha, the URI must say `.../login/siut-sso/opac`.
- Register only the interfaces you use. If people sign in to the OPAC only, the OPAC address is enough.
- If you later change the code in Koha, or either base URL, update these URIs in SSO too.

### Require PKCE: off

Koha does not send PKCE. It is a server-side application that keeps a client secret, so switch **Require PKCE** off for this application only. The secret protects the sign-in instead.

Save, then copy the **Client ID** and the **client secret** (the secret is shown only once).

## 3. Finish the identity provider in Koha

Edit the identity provider from step 1 and fill in its **Configuration**:

| Field | Value |
|---|---|
| **Client ID** (`key`) | `{{client_id}}` |
| **Client secret** (`secret`) | The secret SSO showed you |
| **Well-known URL** (`well_known_url`) | `{{discovery}}` |
| **Scope** (`scope`) | `openid email profile` (add `phone` and/or `employee` if your mapping uses them) |

As JSON:

```json
{
  "key": "{{client_id}}",
  "secret": "<client secret>",
  "well_known_url": "{{discovery}}",
  "scope": "openid email profile"
}
```

Koha reads every SSO endpoint from the well-known (discovery) URL, so nothing else is needed.

## 4. Add a domain rule

Koha only lets people in from email domains you list. Open the identity provider's **Manage domains** and add a domain:

| Field | Suggested value |
|---|---|
| **Domain** | Your staff email domain (e.g. `example.org`), or `*` for all |
| **Auto register (OPAC / staff interface)** | Yes, to create a patron on first sign-in |
| **Update on login** | Yes, to keep names and email in step with SSO |
| **Default library** / **Default category** | Used for new patrons |
| **Allow OPAC** / **Allow staff** | Which interfaces may use SSO sign-in |

A new staff-interface patron still needs staff **permissions** in Koha before they can do anything there.

## 5. Restart Koha and test

1. Restart Koha's services (Plack and memcached) so the new provider is loaded. If someone else hosts your Koha, ask them.
2. In SSO, give the pilot users **access to Koha** (Identity → Users → Applications, or an access request).
3. Open the OPAC and the staff interface, click **Sign in with SSO**, and check that the right patron record opens.

## 6. Sign out of SSO as well (recommended)

### Why this is needed

When someone clicks **Log out** in Koha, Koha only ends its **own** session (the `CGISESSID` cookie). Koha's OpenID Connect module does not support single logout, so the person stays **signed in to SSO**. The next person at the same computer could click **Sign in with SSO** and get straight in as them, with no password asked. This matters most on shared computers such as library counters and OPAC kiosks.

The fix is a few lines of JavaScript in Koha: right after Koha signs someone out, it sends the browser to SSO's sign-out page, and SSO then sends the browser back to Koha.

### What the person sees

1. They click **Log out** in Koha. Koha ends its session.
2. The browser goes to SSO, which asks **"Sign out everywhere?"** with a **Sign out** button. Koha cannot prove who is signing out, so SSO asks for one confirmation click. This stops other websites from signing people out without their knowledge.
3. SSO signs them out and sends them back to the Koha home page.

Signing out of SSO also signs them out of SSO for **every other application** in that browser. That is intended on a shared computer.

### How it works

When Koha finishes signing someone out, it opens its home page with `logout.x=1` in the address. The script below runs only on that page and redirects to SSO's sign-out address (`end_session_endpoint`) with two values:

- `client_id`: tells SSO which application is asking.
- `post_logout_redirect_uri`: where SSO sends the browser afterwards. It must be registered in SSO (below), or SSO shows its own sign-in page instead.

### For the OPAC (`OPACUserJS`)

In Koha go to **Administration → Global system preferences**, search for `OPACUserJS`, add this to whatever is already there, and save:

```javascript
$(document).ready(function () {
  // Runs only on the page Koha opens right after signing out of the OPAC.
  if (window.location.search.indexOf('logout.x=1') === -1) {
    return;
  }

  var ssoLogout = '{{issuer}}/oauth/logout';
  var params = new URLSearchParams({
    client_id: '{{client_id}}',
    post_logout_redirect_uri: window.location.origin + '/cgi-bin/koha/opac-main.pl'
  });

  window.location.replace(ssoLogout + '?' + params.toString());
});
```

### For the staff interface (`IntranetUserJS`)

In the same place, search for `IntranetUserJS`, add this, and save:

```javascript
$(document).ready(function () {
  // Runs only on the page Koha opens right after signing out of the staff interface.
  if (window.location.search.indexOf('logout.x=1') === -1) {
    return;
  }

  var ssoLogout = '{{issuer}}/oauth/logout';
  var params = new URLSearchParams({
    client_id: '{{client_id}}',
    post_logout_redirect_uri: window.location.origin + '/cgi-bin/koha/mainpage.pl'
  });

  window.location.replace(ssoLogout + '?' + params.toString());
});
```

The only differences between the two are the preference name and the return page (`opac-main.pl` for the OPAC, `mainpage.pl` for staff).

### Register the return pages in SSO

SSO only sends people back to addresses registered for the application. In **Identity → Applications → Koha**, under **Post-logout redirect URIs**, add one per interface you use:

| Interface | Post-logout redirect URI | Example |
|---|---|---|
| OPAC | `<OPACBaseURL>/cgi-bin/koha/opac-main.pl` | `https://library.example.org/cgi-bin/koha/opac-main.pl` |
| Staff interface | `<staffClientBaseURL>/cgi-bin/koha/mainpage.pl` | `https://library-staff.example.org/cgi-bin/koha/mainpage.pl` |

The script builds this address from the address in the browser, so it must match the registered one exactly (`http`/`https`, host name, port).

### Good to know

- Signing out **elsewhere** (in SSO or another application) does not end an open Koha session, because Koha does not support back-channel logout either. Koha's session ends on its own after the time set in the `timeout` system preference, so keep that short on shared computers.
- Test after Koha upgrades: the scripts rely on Koha's `logout.x=1` sign-out page.

## 7. Go straight to SSO, skipping Koha's login form (optional)

### Why

Koha keeps showing its own username and password form next to the **Sign in with SSO** button, and it has no setting to hide it. Many people will type their password into the Koha form by mistake. A short script can instead send people **straight to SSO** whenever Koha's login form appears, as if they had clicked the SSO button themselves.

> **Keep a way in if SSO is down.** Do this on the staff interface only together with the `?local=1` bypass below. Otherwise, if SSO is unavailable, nobody (not even a `superlibrarian`) can reach Koha's own login form. Make sure at least one `superlibrarian` has a working **local** Koha password before you switch this on.

### What the person sees

- **OPAC:** clicking **Log in to your account** (the `opac-user.pl` page) goes straight to the SSO sign-in page. Browsing the catalogue still works without signing in.
- **Staff interface:** opening any staff page while signed out goes straight to SSO.
- **Emergency local login:** adding `?local=1` to the address shows Koha's normal form, for example `https://library-staff.example.org/cgi-bin/koha/mainpage.pl?local=1`.

### For the OPAC (`OPACUserJS`)

In **Administration → Global system preferences → `OPACUserJS`**, add this **below** the sign-out script from step 6:

```javascript
$(document).ready(function () {
  var search = window.location.search;

  // Only on the OPAC login page; never on the sign-out page; ?local=1 shows Koha's own form.
  if (window.location.pathname.indexOf('opac-user.pl') === -1
      || search.indexOf('logout.x=1') > -1
      || search.indexOf('local=1') > -1) {
    return;
  }

  // Tried less than 30 seconds ago? Then sign-in failed: show the form and Koha's message instead of looping.
  try {
    if (Date.now() - Number(sessionStorage.getItem('ssoAutoLogin') || 0) < 30000) { return; }
    sessionStorage.setItem('ssoAutoLogin', String(Date.now()));
  } catch (e) { /* private browsing: just continue */ }

  // The SSO button Koha shows for the identity provider with code "SSO".
  var ssoButton = $('a[href*="/api/v1/public/oauth/login/SSO/"]').first();
  if (ssoButton.length) {
    window.location.replace(ssoButton.attr('href'));
  }
});
```

### For the staff interface (`IntranetUserJS`)

In **`IntranetUserJS`**, add this **below** the sign-out script from step 6:

```javascript
$(document).ready(function () {
  var search = window.location.search;

  // Only when Koha shows its login form; never on the sign-out page; ?local=1 shows Koha's own form.
  if (!$('#login').length
      || search.indexOf('logout.x=1') > -1
      || search.indexOf('local=1') > -1) {
    return;
  }

  // Tried less than 30 seconds ago? Then sign-in failed: show the form and Koha's message instead of looping.
  try {
    if (Date.now() - Number(sessionStorage.getItem('ssoAutoLogin') || 0) < 30000) { return; }
    sessionStorage.setItem('ssoAutoLogin', String(Date.now()));
  } catch (e) { /* private browsing: just continue */ }

  // The SSO button Koha shows for the identity provider with code "SSO".
  var ssoButton = $('a[href*="/api/v1/oauth/login/SSO/"]').first();
  if (ssoButton.length) {
    window.location.replace(ssoButton.attr('href'));
  }
});
```

If your identity provider's **Code** is not `SSO`, change `SSO` in the `ssoButton` line to your code.

### How the scripts protect against problems

| Safeguard | What it prevents |
|---|---|
| Skips the page with `logout.x=1` | After **Log out**, the step 6 script sends the browser to SSO's sign-out. Without this check both scripts would redirect at once and sign-out could be skipped. |
| Skips when the address has `local=1` | Gives administrators Koha's own form when SSO is down (emergency login). |
| Waits 30 seconds before trying again | If Koha refuses the sign-in (for example, no matching patron), Koha shows its login form with an error. Without this pause the browser would bounce between Koha and SSO forever; with it, the person sees Koha's message. |
| OPAC: only on `opac-user.pl` | The catalogue stays open to visitors who are not signed in. It is also why step 6 returns people to `opac-main.pl` (the OPAC home page): returning them to `opac-user.pl` would start a new SSO sign-in right after they signed out. |

On the staff interface every page needs a signed-in user, so after **Log out** the person comes back to Koha and is taken to the SSO sign-in page. Because they just signed out of SSO, SSO asks for their password. They are not signed straight back in.

### Emergency local login

When SSO is down, a `superlibrarian` opens the staff interface with `?local=1` at the end of the address, for example:

```text
https://library-staff.example.org/cgi-bin/koha/mainpage.pl?local=1
```

They then sign in with their **local Koha** username and password. If the password is typed wrongly, open the `?local=1` address again before retrying. Keep this address somewhere the library's administrators can find it.

### If something goes wrong

| Message | Usual cause |
|---|---|
| *invalid redirect URI* (on the SSO page) | The redirect URI does not match: compare the base URL (`http`/`https`, host, port) and the **Code** letter by letter. |
| *You do not have access to this application* | The person has no access to Koha in SSO. |
| Koha says no matching patron / cannot register | The matchpoint value differs between SSO and Koha, the domain rule is missing, or the person has no **Last name** in SSO. |
| The SSO button does not appear | Koha services were not restarted, or **Allow OPAC** / **Allow staff** is off. |
| After **Log out** the person can sign straight back in without a password | The sign-out script (step 6) is missing from `OPACUserJS` / `IntranetUserJS`. |
| After signing out of SSO the browser stays on the SSO sign-in page instead of returning to Koha | The post-logout redirect URI is not registered in SSO exactly as the script sends it, or the `client_id` in the script is wrong. |
| Koha's login form still shows (step 7) | The `ssoButton` line does not use your provider's **Code**, the address has `local=1`, or the page was opened again within 30 seconds of a failed sign-in. |
| SSO is down and nobody can get in | Open the staff interface with `?local=1` and sign in with a local `superlibrarian` account (step 7). |
