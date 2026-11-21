# Node.js (Express)

[express-openid-connect](https://github.com/auth0/express-openid-connect) works with any OpenID Connect provider, including SSO. It handles sign-in, sign-out, token refresh and back-channel logout through configuration only.

```
npm install express express-openid-connect
```

```js
const express = require('express');
const { auth, requiresAuth, claimCheck } = require('express-openid-connect');

const app = express();

app.use(auth({
  issuerBaseURL: '{{issuer}}',
  baseURL: '{{app_url}}',
  clientID: '{{client_id}}',
  clientSecret: process.env.SSO_CLIENT_SECRET,
  secret: process.env.SESSION_SECRET,     // long random string that encrypts the session cookie
  authRequired: true,                     // every page needs sign-in
  idpLogout: true,                        // /logout also signs out of the SSO
  backchannelLogout: true,                // the SSO can end sessions here
  authorizationParams: {
    response_type: 'code',                // code flow; PKCE is sent automatically
    scope: '{{scopes}}',
  },
}));

app.get('/', (req, res) => {
  const user = req.oidc.user;             // the ID token's claims
  res.send(`Hello ${user.name} (UserID ${user.user_id}, roles ${(user.roles || []).join(', ')})`);
});

// Only users with the "approver" role in this application.
app.get('/approvals', claimCheck((req, claims) => (claims.roles || []).includes('approver')), (req, res) => {
  res.send('Approvals');
});

app.listen(3000);
```

The library adds `/login`, `/logout`, `/callback` and `/backchannel-logout` routes. Sign out with a link to `/logout`.

`backchannelLogout` needs version 2.17 or later. With several server processes, give it a shared session store (see the library's documentation); a single process works as is.

## Register at the SSO

| | |
|---|---|
| Redirect URI | `{{node_redirect_uri}}` |
| Post-logout redirect URI | `{{app_url}}` |
| Back-channel logout URI | `{{node_backchannel_uri}}` |

## Other Node frameworks

For NestJS, Fastify or plain `http`, use [openid-client](https://github.com/panva/openid-client) with discovery from `{{issuer}}`, `client_secret_post` or `client_secret_basic`, and a PKCE `code_verifier`/`code_challenge` (S256) on every sign-in.

## Calling the API from Node.js

```js
const tokenRes = await fetch('{{issuer}}/oauth/token', {
  method: 'POST',
  body: new URLSearchParams({
    grant_type: 'client_credentials',
    client_id: '{{client_id}}',
    client_secret: process.env.SSO_CLIENT_SECRET,
    scope: 'users.read',
  }),
});
const { access_token } = await tokenRes.json();

const users = await (await fetch('{{issuer}}/api/v1/users?search=ahmed', {
  headers: { Authorization: `Bearer ${access_token}`, Accept: 'application/json' },
})).json();
```

More in the [API guide](api).
