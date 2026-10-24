# Security and runtime review — 24 October 2026

This review covers the application code, authentication flows, private documents, dependency state, and the production checks required on Laravel Cloud. It records what is enforced by this repository and what must still be confirmed in the deployment environment.

## Code controls now enforced

- Laravel, Composer, and npm dependencies are checked in with the current lock files. Composer and npm audits are part of CI and Dependabot opens weekly update pull requests.
- Production password rules require at least 12 characters with mixed case, numbers, and symbols. The rule is used by staff registration, administrator-created staff, access-control edits, master-data user imports, client portal registration, password changes, and password resets.
- Staff who are authenticated but do not have an active staff profile are sent to the access-pending screen and cannot open staff modules. Client accounts are kept on the client portal.
- Self-registered staff remain unverified and pending until they verify their email and are approved. Approval and bulk approval do not silently verify email addresses.
- Client lookup and client portal registration are throttled by IP and email/IP combinations. The lookup returns only a masked phone hint and never returns the full phone number.
- Session cookies are HTTP-only, secure in production, SameSite=Lax, and encrypted in production. Sanctum tokens have a finite lifetime and a project-specific prefix.
- Every response receives clickjacking, MIME-sniffing, referrer, permissions, cross-domain, and baseline CSP headers. HSTS is added for secure production requests; Laravel Cloud must also enforce it at the edge.
- Client documents, matter files, messages, securities, and recovery source files remain on private disks and are served through authorization-aware application routes. The original filename is rendered as text in the message attachment UI.
- Admin-created staff notifications never contain a plaintext password. They direct the user to the approved onboarding channel or password reset flow.
- Security-sensitive model actions and access-control changes remain auditable. The recovery workflow keeps pending payment and closure decisions separate from approved balances.

## Verification evidence

Run these commands from the exact commit being deployed:

```text
composer validate --no-check-publish
composer audit --locked --no-interaction
npm ci --ignore-scripts
npm audit --audit-level=high
npm audit --omit=dev --audit-level=high
npm run build
php artisan route:list --except-vendor --json
php -d memory_limit=-1 artisan test --do-not-cache-result
```

The review run completed with:

- Composer validation: valid.
- Composer audit: no security vulnerability advisories found.
- npm audit (including production packages): zero high-severity vulnerabilities.
- Vite production build: passed.
- PHPUnit: **290 tests, 2,359 assertions, 4 intentionally skipped, 0 failures**.
- Focused hardening tests: **3 tests, 21 assertions, 0 failures**.
- `git diff --check`: passed.

The local workstation uses PHP 8.2.18. Production must use the newest PHP 8.4 patch offered by Laravel Cloud; local PHP is not evidence that the production runtime is patched.

## Laravel Cloud launch gate

Before the first production deploy, an administrator must verify each item in the Cloud dashboard:

| Check | Required setting or evidence |
| --- | --- |
| Runtime | PHP 8.4, newest patch available; deploy from the protected production branch. |
| Application | `APP_ENV=production`, `APP_DEBUG=false`, correct HTTPS `APP_URL`; keep the existing `APP_KEY`. |
| Secrets | Database, mail, SMS, broadcast, Redis, and object-storage credentials are Cloud Secrets, never repository variables or committed files. |
| State | Redis-backed sessions, cache, queues, and rate limits when more than one replica is running. |
| Edge | WAF, TLS, HSTS, DDoS protection, and managed package scanning enabled. |
| Storage | Client and firm documents on private object storage; no public bucket or public upload path. |
| Operations | Queue worker and scheduler are running; `/up`, verification mail, reminders, exports, and private downloads are tested after deploy. |
| Recovery | Automated database backups are enabled and a restore has been rehearsed. |
| Privileged access | Individual accounts only; two-factor authentication or passkeys required for Super Admin, Administrator, finance, payroll, HR, recoveries, and securities managers. |
| Monitoring | Alerts for repeated login failures, throttling, privilege changes, exports, document downloads, queue failures, and unusual request volume. |

These deployment checks cannot be proven from source code alone. Record the Cloud setting, date, and reviewer in the release ticket before enabling real client data.

## Accepted residual decisions

The application intentionally keeps a throttled, masked client lookup because the portal registration flow must confirm an approved client and prefill the registered phone hint. The firm should still monitor lookup abuse and can disable portal registration if the client onboarding policy changes.

The firm must define the evidence that Finance accepts for payment verification, write-off, and external full settlement. The application requires a manager decision and an audit reason, but it cannot independently verify a bank statement or lender system.

## Post-deploy smoke test

1. Open `/up` and confirm a healthy response.
2. Sign in as a staff user, verify email and access-pending behaviour, then sign out using the POST logout form.
3. Sign in as a client, open only that client's matters and private files, and confirm another client's URL returns 403/404.
4. Submit a client lookup repeatedly and confirm throttling; confirm no full phone number is returned.
5. Upload and download a private document through the authorized route; confirm the storage object is not publicly readable.
6. Approve one staff request, run one queued notification, and verify the audit entry.
7. Check response headers for HSTS, `X-Frame-Options`, `X-Content-Type-Options`, CSP, Referrer Policy, and Permissions Policy.

## References

- [PHP supported versions](https://www.php.net/supported-versions.php)
- [Laravel Cloud security defaults](https://laravel.com/blog/laravel-cloud-security-defaults-behind-every-deploy)
- [Laravel Cloud deployment configuration](https://laravel.com/cloud/deploy-a-laravel-app)
- [Laravel TokenGuard security advisory](https://github.com/laravel/framework/security/advisories/GHSA-f9x3-7vq9-cph6)
- [Laravel signed URL path-confusion advisory](https://github.com/laravel/framework/security/advisories/GHSA-crmm-hgp2-wgrp)
