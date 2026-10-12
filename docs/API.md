# Public API (v1)

Read-only JSON endpoints for our apps. No login; nothing private is exposed.

- **Base path:** `/api/v1/` (Apache rewrite in `.htaccess` → `api/v1/index.php`).
- **Methods:** `GET` and `HEAD` only. Anything else returns `405`.
- **Caching:** every response has an `ETag` and `Cache-Control: public, max-age=300`.
  Send `If-None-Match` with the last ETag to get `304 Not Modified`.
- **No session:** API requests don't start a PHP session or set cookies.

## Envelope

```json
{ "ok": true,  "data": { … } }
{ "ok": false, "error": { "code": "not_found", "message": "Unknown endpoint." } }
```

| HTTP | `error.code` |
|---|---|
| 404 | `not_found` |
| 405 | `method_not_allowed` |
| 500 | `server_error` (details go to the PHP error log only) |

## `GET /api/v1/schools`

The school app's directory.

```json
{
  "ok": true,
  "data": {
    "version": 3735928559,
    "updatedAt": "2026-10-11T08:00:00Z",
    "schools": [
      { "id": "mlebs", "schoolName": "Mind Light English Boarding School", "city": "Kathmandu",
        "accent": "#2563EB", "baseUrl": "https://ss.mlebs.edu.np", "status": "active", "expiryDate": "2027-04-13" }
    ]
  }
}
```

**Where the data comes from:** Sales → Client Projects → *App listing*. A client
project is listed once it has an **App id**.

| Field | Source |
|---|---|
| `id` | App id (`app_slug`): 2–32 chars, `a-z 0-9 -`, permanent once saved |
| `schoolName` | Client name, else the project title |
| `city`, `accent` | App listing fields (`accent` is `#RRGGBB` or `null`) |
| `baseUrl` | Project URL exactly as stored (`tbl_client_projects.url`, e.g. `https://ss.mlebs.edu.np`) |
| `status` | See below |
| `expiryDate` | Project end date (`Y-m-d`) or `null`; for demo/trials, the trial end |

**Status rules** (`school_api_status()` in `functions/api.php`):

| Client Project | `status` |
|---|---|
| Completed or Cancelled | not listed |
| End date in the past | `expired` |
| On Hold | `suspended` |
| Anything else (including demo/trial, not won yet) | `active` |

**`version`** is a checksum of `schools`. It changes whenever anything in the
list changes, including a school being removed or expiring. Compare it with
`!==`; it does not only go up. **`updatedAt`** is the latest edit time of a
listed project, in UTC.

## Local testing over http

Production only lists projects with an `https://` URL. On a dev machine, set

```php
'app_listing_allow_http' => true,
```

in `config/setup.php` (git-ignored; the committed sample keeps it `false`) to
allow `http://` URLs such as `http://localhost/school`. `baseUrl` carries the
scheme, so the app uses it as-is: `http://…` locally, `https://…` in production.
