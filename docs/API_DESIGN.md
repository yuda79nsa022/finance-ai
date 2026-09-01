# Mobile API Design Proposal

**Status:** Proposal — not implemented. This document is for review before any
code gets written. See "Open decisions" at the end for the choices that need
a product answer, not just a technical one.

## Why a separate API

The app today is entirely server-rendered PHP + Bootstrap: every page is
HTML, and the only two JSON-returning endpoints (`/wizard/answer`,
`/advisor/ask`) exist to support inline AJAX on those specific pages, not as
a general-purpose API. A native iOS/Android app cannot drive the app through
302-redirect-based HTML forms — it needs a JSON request/response contract.
This proposal adds that as an additive layer alongside the existing pages,
not a replacement for them.

## Auth scheme: bearer tokens, not sessions, not JWT

**Not PHP sessions.** Sessions are cookie-based, and while a mobile HTTP
client *can* carry a cookie jar, it's an awkward fit: no clean way to name
or revoke one device's access without logging out every device, and no
"remember this device" semantics beyond whatever the cookie's lifetime is
set to.

**Not JWT.** JWT's main value is stateless verification — skipping a DB
lookup on every request because the token carries its own signed claims.
That trade only pays off when verification needs to happen somewhere that
can't easily reach the database (a separate auth service, a CDN edge
function, multiple independent services). This app is one PHP monolith that
already hits MySQL for the actual data on every single request — the "skip
a DB lookup" benefit doesn't exist here. Worse, a JWT can't be un-issued
before it expires without maintaining a revocation denylist, which
reintroduces the exact DB lookup you were trying to avoid, so it buys
nothing.

**Opaque bearer tokens**, the same pattern as Laravel Sanctum / GitHub
personal access tokens:

- `POST /api/v1/auth/login` (email, password, optional `device_label`)
  returns a random 40+ byte token, shown once.
- The server stores only a SHA-256 hash of it (`personal_access_tokens`
  table below) — like a password, the raw token is never persisted, so a
  database leak doesn't hand out live credentials.
- Every subsequent request carries `Authorization: Bearer <token>`.
- `DELETE /api/v1/auth/devices/{id}` revokes one token — lose a phone,
  revoke just that device, everything else keeps working. This is the
  capability session cookies structurally can't give you cleanly.
- `GET /api/v1/auth/devices` lists a user's own active tokens (label,
  created/last-used timestamps) so they can see and prune old ones.

```sql
CREATE TABLE personal_access_tokens (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  device_label  VARCHAR(100) NULL,        -- "Sarah's iPhone", set by the client at login
  token_hash    CHAR(64) NOT NULL UNIQUE, -- sha256(raw token) — raw token is never stored
  last_used_at  TIMESTAMP NULL,
  expires_at    TIMESTAMP NULL,           -- NULL = no expiry until explicitly revoked
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_pat_user (user_id)
) ENGINE=InnoDB;
```

Login reuses the *existing* `LoginAttempt`/`Auth::isLockedOut()` throttle —
same 5-attempts/15-minute lockout, keyed the same way, no new rate-limiting
code needed.

## CSRF and CORS

**CSRF protection is dropped for `/api/v1/*`.** CSRF exists because
browsers auto-attach cookies to cross-site requests; a bearer token is never
attached automatically by anything — the client code has to deliberately
read it and set the header. That structurally defeats CSRF, so requiring a
`_token` field on API routes would just be friction with no security
benefit. `Router::csrfValid()` needs one change: skip the check when the
matched route is under `/api/`.

**CORS is not needed for a native app** — CORS is a browser-enforced
mechanism; a native HTTP client ignores it entirely. Only add CORS headers
if a browser-based client (a PWA, an admin dashboard on another origin)
shows up later, and scope it to that specific origin — never `*`, given
requests carry bearer tokens.

## Versioning

URL-prefixed: `/api/v1/...`. Simple, visible in every log line and every
support conversation, no content-negotiation header parsing required. A
`v2` prefix can be introduced later without disturbing `v1` clients still
in the field (mobile app releases can't be forced to update instantly the
way a web deploy can).

## Endpoint surface

Thin `App\Controllers\Api\*` controllers, one per existing web controller's
domain, calling the **same** `App\Models\*` / `App\Core\*` classes the web
app already uses (`MonthCalculator`, `YearSummaryCalculator`,
`LoanLedgerModel`, `ReceiptScanner`, `AIClient`, every `*Model` class) —
this is a new presentation layer over existing business logic, not a
reimplementation of it. Every ownership check already built this session
(`requireMonth()`-style scoping, `ExpenseModel::find($id, $monthId)`, etc.)
carries over unchanged; the API layer just needs its own equivalent of
`Auth::user()` backed by the token instead of the session.

```
Auth
  POST   /api/v1/auth/login
  POST   /api/v1/auth/logout              (revokes the calling token)
  GET    /api/v1/auth/me
  GET    /api/v1/auth/devices
  DELETE /api/v1/auth/devices/{id}

Financial years
  GET    /api/v1/financial-years
  GET    /api/v1/financial-years/active
  POST   /api/v1/financial-years/{id}/activate
  GET    /api/v1/financial-years/{id}/summary     (YearSummaryCalculator::build())

Months
  GET    /api/v1/months/{id}                       (income + fixed costs + expenses + loan ledger + summary)
  POST   /api/v1/months/{id}/lock
  POST   /api/v1/months/{id}/unlock
  POST   /api/v1/months/{id}/duplicate

Income / Fixed costs / Expenses  (same CRUD shape ×3)
  POST   /api/v1/months/{id}/income
  PATCH  /api/v1/months/{id}/income/{entryId}
  DELETE /api/v1/months/{id}/income/{entryId}
  ...same for /fixed-costs/... and /expenses/...

Receipt scanning
  POST   /api/v1/months/{id}/expenses/scan         (multipart, same ReceiptScanner flow)
  GET    /api/v1/months/{id}/expenses/{entryId}/receipt   (image; ownership-checked, same as web)

Loans
  POST   /api/v1/months/{id}/loans
  PATCH  /api/v1/months/{id}/loans/{lenderId}
  DELETE /api/v1/months/{id}/loans/{lenderId}

Reference data
  GET    /api/v1/categories
  GET    /api/v1/payment-methods

Advisor
  GET    /api/v1/advisor/conversations
  GET    /api/v1/advisor/conversations/{id}
  POST   /api/v1/advisor/ask
```

Reports (PDF/CSV/XLSX) stay behind the same `Authorization` header as
everything else — deliberately **not** a bare link with a token in the query
string, since a token in a URL ends up in server access logs, proxy logs,
and browser/download history. The client performs an authenticated fetch
and saves the response bytes itself.

## Response shape

All JSON (`Content-Type: application/json`) except the receipt-scan upload,
which stays `multipart/form-data` like the web version.

```jsonc
// success
{ "data": { ... } }          // single resource
{ "data": [ ... ] }          // list

// error
{
  "error": {
    "message": "That doesn't look like a valid email address.",
    "code": "validation_failed",
    "fields": { "amount": "must be greater than zero" }  // optional, for field-level UI
  }
}
```

Status codes used meaningfully: `200/201/204` success, `400` validation,
`401` no/invalid/expired token, `403` locked month, `404` not found *or*
not owned (identical to the web app's IDOR-safe behavior — never reveal
that a row exists by returning a different status for "belongs to someone
else" vs. "doesn't exist"), `429` rate-limited, `500` server error.

## Phasing

1. **Foundation** — token auth + device management + `GET /months/{id}` +
   `GET /financial-years/{id}/summary`. Enough for a read-only "view your
   finances" app.
2. **Writes** — income/fixed-cost/expense/loan CRUD.
3. **Receipt scan + Advisor chat.**
4. **Admin** — only if mobile actually needs it (see open decisions below;
   default recommendation is admin stays web-only for v1).

## Open decisions

These need a product answer, not a technical one — I'd rather ask than
guess and build the wrong thing:

1. **Native app, or would a mobile-optimized web view suffice for v1?** The
   existing pages could get a responsive/mobile CSS pass for a fraction of
   the effort of a full parallel API + native client. Worth confirming a
   real native app (App Store / Play Store presence, offline use, camera
   integration beyond what a mobile browser's file input already gives you
   via `capture="environment"`) is actually the goal before committing to
   this design.
2. **Does admin need to be reachable from mobile**, or is it web-only?
   Recommend web-only initially — smaller API surface, less to secure.
3. **Report exports (PDF/CSV/XLSX) via the API for v1, or web-only** for
   now, with the mobile app linking out to the browser for those?
4. **Token expiry policy** — no expiry until explicitly revoked (simplest,
   matches "remember this device"), or a rolling expiry that forces
   periodic re-login even on a device that's still trusted?
