# Security Report — Inventaris UNDIRA

**Date:** 2026-07-23  
**Overall:** Medium risk for a campus-internal ERP; harden before public internet exposure.

## Severity findings

| Sev | Finding | Action |
|-----|---------|--------|
| Critical | `database.sqlite` was not ignored — risk of committing live data | Added `*.sqlite` to `.gitignore`; remove from git history if ever pushed |
| High | API tokens stored as plaintext cache keys (30d), not Sanctum | Migrate to hashed personal access tokens / Sanctum |
| High | Public `/scan/{number}` exposes location + mutation/maintenance history | Reduce guest payload to identity + campus/building only |
| Medium | Policies underused; Spatie route middleware is primary | Keep for single-tenant; add authorize() on sensitive actions |
| Medium | `verified` middleware aliased but unused on web routes | Attach to authenticated group if email proof required |
| Medium | Settings update accepts arbitrary keys | Allowlist setting keys |
| Low | Wide `$fillable` on InventoryItem/User | Continue stripping in FormRequests; prefer `$guarded` for sensitive cols |
| Low | API CRUD lacks extra throttle after login | Add `throttle:api` on mutating API routes |

## Positive controls

- Spatie permission groups on most mutating routes
- Login / OTP / scanner / public scan rate limits
- CSRF enabled (no global exceptions found)
- Upload mime + size limits for photos/attachments
- Raw SQL expressions are static (no user concat found)
- Public scan hides serial (covered by tests)

## Auth model

| Surface | Mechanism |
|---------|-----------|
| Web | Session + Spatie `permission` / `role` |
| API | Custom Bearer in cache (`AuthenticateApiToken`) |

## Recommendations (priority)

1. Stop tracking SQLite DB; rotate any leaked credentials.
2. Replace cache API tokens with Sanctum (hashed, revocable).
3. Minimize public scan disclosure.
4. Throttle authenticated API writes.
5. Allowlist settings keys; keep backup restore basename-locked (verify still in place).
