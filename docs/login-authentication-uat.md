# Login And Authentication UAT Record

## Scope

This record covers Login / Authentication UAT readiness for Gorilla Mutantz Gym MACS.

## Status

System-side Login / Authentication UAT is complete.

Final client sign-off remains tracked under the main Deployment & UAT checklist because it must be performed on the actual front-desk machine with the client present.

## Accepted Login Decisions

- Password reset remains a support-assisted process.
- The login page `Forgot password?` flow opens the support modal.
- Users contact support through WhatsApp at `https://wa.me/601128520309`.
- Self-service password reset is intentionally not enabled for the local offline environment.
- Administrator-managed password reset can be added later only if approved.

## Verified Behaviors

| Area | Result |
| --- | --- |
| Valid login | User is authenticated and redirected to dashboard. |
| Invalid login | Request is rejected and recorded in Audit Trail without password values. |
| Rate limiting | Repeated failed login attempts are blocked temporarily. |
| Inactive user | Inactive users cannot login. |
| Logout | Logout ends the session and records an audit event. |
| Session security | Session lifetime is 30 minutes, expires on browser close, and is encrypted. |
| Security headers | Login page sends frame, content-type, and referrer protection headers. |
| Door status | Login page shows configured door online/offline labels from access controller settings. |
| Last sync | Login page reads the latest `last_sync_at` from door controller settings. |
| Pending syncs | Login page counts pending records from `access_sync_logs`. |
| Forgot password | Support modal is available and does not expose self-service reset. |

## Automated Coverage

Relevant feature tests:

```bash
php artisan test --filter="login|configured door devices|login security policy|failed login"
```

The test suite verifies:

- guest redirect to login
- login page rendering
- door status capsules
- last sync display
- pending sync display
- security headers
- valid login and logout
- failed login audit records
- secure session policy
- offline door display before successful sync

## Front-Desk UAT Notes

The following must still be witnessed during final deployment UAT:

- actual front-desk browser opens the local HTTPS URL
- administrator and staff can login from the installed machine
- session timeout behavior is acceptable for operations
- displayed door labels match the installed doors
- client accepts wording and operational metrics

These are not application-code blockers; they are final deployment sign-off items.
