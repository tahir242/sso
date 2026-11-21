# Server-to-server API

Your application's **server** can call the SSO with its own client ID and secret (OAuth 2.0 *client credentials*), with no user involved. Every call only sees **your application's** users and requests.

The full reference, with every field and a "try it" console, is at [{{issuer}}/docs/api]({{issuer}}/docs/api).

## What you can do

| Scope | Endpoint | |
|---|---|---|
| `users.read` | `GET /api/v1/users`, `GET /api/v1/users/{user_id}` | Who has (or had) access, with their roles |
| `roles.read` | `GET /api/v1/roles` | Your application's roles |
| `access-requests.read` | `GET /api/v1/access-requests`, `GET /api/v1/access-requests/{id}` | Requests and where each is in the approvals |
| `access-requests.write` | `POST /api/v1/access-requests` | Ask for access on someone's behalf |

An administrator chooses which scopes your application may use (Applications → Single sign-on). Asking for others gives `403`.

The API **cannot create users or grant access directly**: requests go through the same approvals as in the portal (head of department, then application owner).

## 1. Get a token

```
POST {{issuer}}/oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials&client_id={{client_id}}&client_secret=SECRET&scope=users.read roles.read
```

```json
{ "token_type": "Bearer", "expires_in": 900, "access_token": "eyJ0eXAi..." }
```

Reuse the token until it expires (15 minutes); ask for a new one when you get `401`.

## 2. Call the API

```
GET {{issuer}}/api/v1/users?search=ahmed&active=1&per_page=50
Authorization: Bearer eyJ0eXAi...
Accept: application/json
```

```json
{
  "data": [
    {
      "user_id": 42,
      "sub": "01JC9...",
      "username": "ahmed.khan",
      "name": "Ahmed Khan",
      "emp_id": "20456",
      "emp_initial": "AK",
      "email": "ahmed.khan@example.com",
      "mobile": "0300...",
      "department": "Nephrology",
      "designation": "Medical Officer",
      "account_status": "active",
      "last_login_at": "2026-11-12T08:15:00+05:00",
      "access": { "active": true, "expires_at": null, "roles": ["clerk"], "since": "2024-03-01T09:00:00+05:00" }
    }
  ],
  "meta": { "total": 1, "page": 1, "per_page": 50, "last_page": 1 }
}
```

Lists are paged: keep requesting `page=2, 3, ...` until `page` equals `last_page`.

## 3. Request access for a user

```
POST {{issuer}}/api/v1/access-requests
Authorization: Bearer ...
Content-Type: application/json

{
  "username": "ayesha.khan",
  "roles": ["clerk"],
  "justification": "Joining the payroll team on Monday.",
  "access_until": "2027-06-30",
  "requested_by": "ahmed.khan"
}
```

Identify the user with exactly **one** of `user_id`, `username` or `emp_id`. `requested_by` (optional) is the username of the person asking in your application. The answer is `201` with the request and its approval steps; follow it with `GET /api/v1/access-requests/{id}`.

## Errors

| Status | Meaning |
|---|---|
| `401` | Missing, invalid or expired token: get a new one. |
| `403` | Your application may not use that scope, or is deactivated. |
| `404` | Not one of your application's users or requests. |
| `422` | Validation failed; `errors` says why (unknown user, already has access, request already pending, unknown role, ...). |
| `429` | More than 120 calls a minute: slow down. |

## Examples

Each language guide ends with a token-and-call example: [PHP](php#calling-the-api), [.NET](dotnet#calling-the-api-from-net), [Java](java#calling-the-api-from-java), [Python](python#calling-the-api-from-python), [Node.js](node#calling-the-api-from-nodejs).

With curl:

```
TOKEN=$(curl -s -d grant_type=client_credentials -d client_id={{client_id}} \
  -d client_secret="$SSO_CLIENT_SECRET" -d scope=users.read \
  {{issuer}}/oauth/token | jq -r .access_token)

curl -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" "{{issuer}}/api/v1/users?search=ahmed"
```
