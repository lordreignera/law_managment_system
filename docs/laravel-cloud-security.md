# Laravel Cloud production security baseline

Apply this checklist to the production environment before launch and review it after every security release.

The dated implementation review and command evidence are in [`security-review-2026-10-24.md`](security-review-2026-10-24.md). This checklist separates controls enforced by the repository from settings that must be verified in Laravel Cloud.

## Runtime and deployment

- Select PHP 8.4 in Laravel Cloud and keep it on the newest patch offered by Cloud. Do not deploy with PHP 8.2.18.
- Deploy only from the protected production branch after tests, `composer audit --locked`, and `npm audit --omit=dev` pass.
- Keep Laravel Cloud's managed WAF, TLS, DDoS protection, package scanning, and deployment audit logs enabled.
- Use a staging environment for dependency and runtime upgrades before promoting the same commit to production.
- Keep the Cloud deployment record with the PHP patch, commit SHA, reviewer, and post-deploy smoke-test result.

## Production variables

Laravel Cloud generates `APP_KEY`; never replace it during routine deployments because existing encrypted data and sessions depend on it. Store credentials in Cloud Secrets and never commit a production `.env` file.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://kalikumutima.net
LOG_LEVEL=warning

SESSION_DRIVER=redis
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_LIFETIME=120

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SANCTUM_EXPIRATION=1440
SANCTUM_TOKEN_PREFIX=kfms_
```

Use Laravel Cloud's managed KV store for shared sessions, cache, queues, and rate limits when the application has more than one replica. Use private object storage for client documents and configure the application disk through Cloud-injected credentials.

Treat database, mail, SMS, broadcast, and object-storage credentials as secrets. Rotate them immediately if they appear in source control, logs, tickets, screenshots, or chat messages.

## Access protection

- Require email verification for self-registered users and administrator approval before staff access becomes active.
- Require two-factor authentication or a passkey for Super Admin, Administrator, finance, payroll, HR, recoveries managers, and securities managers.
- Give every person their own account. Do not share administrator credentials.
- Review active users, roles, branch access, and privileged audit events monthly. Disable departed or suspended staff immediately.
- Keep recovery officer, client portal, payroll, finance, and document access restricted by role and record ownership.

## Data and operations

- Enable automated database backups and test a restore at least quarterly.
- Keep uploaded documents private and serve them only through authorized download routes or short-lived signed URLs.
- Run queue workers and the Laravel scheduler in Cloud so verification messages, reminders, and security workflows execute reliably.
- Monitor repeated login failures, client lookup throttles, privilege changes, exports, document downloads, and unusual volumes of requests.
- Preserve application and deployment audit logs according to the firm's retention policy and legal obligations.

The client lookup is deliberately throttled and returns only a masked phone hint so the portal can prefill a known client safely. Treat lookup abuse as a monitored event; disable self-service portal registration if the firm's onboarding policy no longer permits it.

## Release check

Run these commands against the exact commit being deployed:

```shell
composer install --no-dev --prefer-dist --optimize-autoloader
composer audit --locked
npm ci
npm audit --omit=dev
npm run build
php artisan test
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

After deployment, verify `/up`, staff login, client login, email verification, logout, private document access, queue processing, and scheduler activity. Confirm the response headers include HSTS, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, the configured Content Security Policy, Referrer Policy, and Permissions Policy.

Do not mark the release complete until the Cloud launch gate and post-deploy smoke test in [`security-review-2026-10-24.md`](security-review-2026-10-24.md) have been signed off.
