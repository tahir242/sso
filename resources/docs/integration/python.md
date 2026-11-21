# Python

These examples use [Authlib](https://docs.authlib.org/), which supports OpenID Connect discovery, PKCE and ID-token validation for Flask, Django and FastAPI.

```
pip install Authlib requests
```

- [Flask](#flask)
- [Django](#django)
- [FastAPI](#fastapi)
- [Back-channel logout (any framework)](#back-channel-logout-any-framework)

Keep the client secret in an environment variable (`SSO_CLIENT_SECRET`), never in code.

## Flask

```python
import os
from functools import wraps
from urllib.parse import urlencode
from flask import Flask, redirect, request, session, url_for
from authlib.integrations.flask_client import OAuth

app = Flask(__name__)
app.secret_key = os.environ["FLASK_SECRET_KEY"]

oauth = OAuth(app)
oauth.register(
    "sso",
    server_metadata_url="{{discovery}}",
    client_id="{{client_id}}",
    client_secret=os.environ["SSO_CLIENT_SECRET"],
    client_kwargs={"scope": "{{scopes}}", "code_challenge_method": "S256"},  # PKCE is required
)


def login_required(view):
    @wraps(view)
    def wrapper(*args, **kwargs):
        if "user" not in session:
            session["next"] = request.full_path
            return redirect(url_for("login"))
        return view(*args, **kwargs)
    return wrapper


def role_required(role):
    def decorator(view):
        @wraps(view)
        @login_required
        def wrapper(*args, **kwargs):
            if role not in session["user"].get("roles", []):
                return "You don't have access to this page.", 403
            return view(*args, **kwargs)
        return wrapper
    return decorator


@app.route("/login")
def login():
    return oauth.sso.authorize_redirect(url_for("auth_callback", _external=True))


@app.route("/auth/callback")
def auth_callback():
    token = oauth.sso.authorize_access_token()   # checks state, PKCE, ID token signature and nonce
    session["user"] = dict(token["userinfo"])     # the ID token's claims
    session["id_token"] = token["id_token"]
    return redirect(session.pop("next", None) or "/")


@app.route("/logout")
def logout():
    id_token = session.get("id_token")
    session.clear()
    end_session = oauth.sso.load_server_metadata()["end_session_endpoint"]
    return redirect(end_session + "?" + urlencode({
        "id_token_hint": id_token,
        "client_id": "{{client_id}}",
        "post_logout_redirect_uri": "{{post_logout_uri}}",
    }))


@app.route("/")
@login_required
def home():
    user = session["user"]
    return f"Hello {user['name']} (UserID {user.get('user_id')}, roles {user.get('roles', [])})"
```

## Django

`settings.py`:

```python
AUTHLIB_OAUTH_CLIENTS = {
    "sso": {
        "client_id": "{{client_id}}",
        "client_secret": os.environ["SSO_CLIENT_SECRET"],
    }
}
LOGIN_URL = "/auth/login"
```

`auth_views.py`:

```python
from urllib.parse import urlencode
from django.contrib.auth import get_user_model, login as django_login, logout as django_logout
from django.contrib.auth.models import Group
from django.shortcuts import redirect
from django.urls import reverse
from authlib.integrations.django_client import OAuth

oauth = OAuth()
oauth.register(
    "sso",
    server_metadata_url="{{discovery}}",
    client_kwargs={"scope": "{{scopes}}", "code_challenge_method": "S256"},
)


def login(request):
    request.session["next"] = request.GET.get("next", "/")
    return oauth.sso.authorize_redirect(request, request.build_absolute_uri(reverse("auth_callback")))


def callback(request):
    token = oauth.sso.authorize_access_token(request)
    claims = token["userinfo"]

    user, _ = get_user_model().objects.update_or_create(
        username=claims["preferred_username"],
        defaults={"first_name": claims.get("name", ""), "email": claims.get("email") or ""},
    )
    # The SSO's roles become Django groups.
    user.groups.set([Group.objects.get_or_create(name=r)[0] for r in claims.get("roles", [])])

    django_login(request, user)
    request.session["id_token"] = token["id_token"]
    request.session["sso_sid"] = claims.get("sid")
    request.session["sso_user_id"] = claims.get("user_id")
    return redirect(request.session.pop("next", "/"))


def logout(request):
    id_token = request.session.get("id_token")
    django_logout(request)
    end_session = oauth.sso.load_server_metadata()["end_session_endpoint"]
    return redirect(end_session + "?" + urlencode({
        "id_token_hint": id_token,
        "client_id": "{{client_id}}",
        "post_logout_redirect_uri": "{{post_logout_uri}}",
    }))
```

`urls.py`:

```python
path("auth/login", auth_views.login, name="auth_login"),
path("auth/callback", auth_views.callback, name="auth_callback"),
path("auth/logout", auth_views.logout, name="auth_logout"),
```

Then use Django as usual: `@login_required`, `request.user.groups.filter(name="clerk").exists()`.

## FastAPI

```python
import os
from fastapi import FastAPI, Request
from fastapi.responses import RedirectResponse
from starlette.middleware.sessions import SessionMiddleware
from authlib.integrations.starlette_client import OAuth

app = FastAPI()
app.add_middleware(SessionMiddleware, secret_key=os.environ["SESSION_SECRET"])

oauth = OAuth()
oauth.register(
    "sso",
    server_metadata_url="{{discovery}}",
    client_id="{{client_id}}",
    client_secret=os.environ["SSO_CLIENT_SECRET"],
    client_kwargs={"scope": "{{scopes}}", "code_challenge_method": "S256"},
)


@app.get("/login")
async def login(request: Request):
    return await oauth.sso.authorize_redirect(request, request.url_for("auth_callback"))


@app.get("/auth/callback")
async def auth_callback(request: Request):
    token = await oauth.sso.authorize_access_token(request)
    request.session["user"] = dict(token["userinfo"])
    request.session["id_token"] = token["id_token"]
    return RedirectResponse("/")
```

## Register at the SSO

| | |
|---|---|
| Redirect URI | `{{python_redirect_uri}}` |
| Post-logout redirect URI | `{{post_logout_uri}}` |
| Back-channel logout URI | `{{python_backchannel_uri}}` |

## Back-channel logout (any framework)

The SSO POSTs a signed `logout_token` when the person signs out anywhere. Verify it, remember its `sid`, and end sessions with that `sid` on their next request.

```python
import requests
from authlib.jose import JsonWebKey, jwt

BACKCHANNEL_EVENT = "http://schemas.openid.net/event/backchannel-logout"


def verified_logout_sid(logout_token: str) -> str:
    """Returns the ended SSO session id, or raises if the token is not genuine."""
    metadata = oauth.sso.load_server_metadata()
    keys = JsonWebKey.import_key_set(requests.get(metadata["jwks_uri"], timeout=10).json())
    claims = jwt.decode(logout_token, keys, claims_options={
        "iss": {"essential": True, "value": metadata["issuer"]},
        "aud": {"essential": True, "value": "{{client_id}}"},
    })
    claims.validate()
    if BACKCHANNEL_EVENT not in (claims.get("events") or {}) or "nonce" in claims or not claims.get("sid"):
        raise ValueError("not a back-channel logout token")
    return claims["sid"]
```

Flask example (use Redis or your database for `ended_sessions` if you run several workers):

```python
ended_sessions = set()

@app.post("/auth/backchannel-logout")
def backchannel_logout():
    try:
        ended_sessions.add(verified_logout_sid(request.form.get("logout_token", "")))
    except Exception:
        return "", 400
    return "", 200

@app.before_request
def end_signed_out_sessions():
    user = session.get("user")
    if user and user.get("sid") in ended_sessions:
        session.clear()
```

Exempt the endpoint from CSRF protection (in Django, `@csrf_exempt`); it is called by the SSO server, not a browser.

## Calling the API from Python

```python
import os, requests

token = requests.post("{{issuer}}/oauth/token", data={
    "grant_type": "client_credentials",
    "client_id": "{{client_id}}",
    "client_secret": os.environ["SSO_CLIENT_SECRET"],
    "scope": "users.read roles.read",
}, timeout=10).json()["access_token"]

users = requests.get("{{issuer}}/api/v1/users", params={"search": "ahmed"},
                     headers={"Authorization": f"Bearer {token}"}, timeout=10).json()
```

More in the [API guide](api).
