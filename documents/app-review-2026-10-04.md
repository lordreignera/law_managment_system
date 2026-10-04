# Application review — 4 October 2026

Reviewed commit: `dfaca2b5`.

Scope: repository review of authentication, client portal, permissions, branch isolation, attachments, HR, clients/intakes/files/matters, litigation/calendar, recovery accounts, letters, invoices/payments, expenses/petty cash, approval workflows, configuration, and existing tests. This is a code and automated-test review; production configuration, live integrations, and mobile/browser rendering were not verified.

## Findings

### 1. Critical: client email knowledge grants portal access

Sources: `app/Http/Controllers/ClientPortalAuthController.php:65`, `app/Http/Responses/LoginResponse.php:18`, `routes/web.php:51`.

An unauthenticated visitor can register using an existing active client's email and a password of their choice. Phone is optional; registration marks the email verified and immediately signs in. The public lookup also reveals the registered phone and client number without rate limiting on the route. LoginResponse independently marks client emails verified, so adding a registration verification screen alone will not fix the bypass.

Confirmed in an isolated HTTP test: registration without a phone or email challenge succeeded, produced a verified account, and opened the client dashboard.

Fix: prove mailbox ownership with a single-use invitation or verification challenge before activating the client relationship; remove automatic verification in both flows. Restrict and throttle lookup, and avoid returning client contact details to anonymous callers. Regenerate the session during custom registration and wrap related account creation in a transaction.

### 2. High: attachments bypass record authorization

Sources: `routes/web.php:84`, `app/Http/Controllers/AttachmentController.php:10`.

Both attachment endpoints require active staff but no module or parent-record authorization. Any active staff user can request an attachment ID belonging to another employee, conversation, matter, requisition, or leave request. Message participation checks do not protect the independent attachment URL.

Confirmed: a user with no module permissions downloaded another user's attachment successfully.

Fix: authorize against the parent record for every view/download request, including branch access, conversation membership, leave/requisition ownership, and module permission. Return 403/404 consistently for unauthorized records.

### 3. High: HR can reset a Super Admin's password

Source: `app/Http/Controllers/StaffController.php:168`.

`staff.update` permits changing any target user's password and email. Restricting selectable role names does not protect an existing administrator. A staff editor can reset a Super Admin's credentials and potentially take over the account if additional authentication does not prevent it. The update action also accepts a user with no existing staff profile and creates one.

Confirmed: an ordinary user holding only `staff.update` changed a Super Admin's password through the HTTP endpoint.

Fix: add target-user authorization and a privilege hierarchy. Protect administrator identity, credentials, roles, and status from lower-privileged editors; separate HR profile editing from account administration.

### 4. High: branch filtering can be bypassed with direct URLs

Sources: `app/Http/Controllers/ClientController.php:84`, `app/Http/Controllers/MatterController.php:159`, `app/Http/Controllers/RecoveryController.php:306`, `app/Http/Controllers/CalendarController.php:117`, `app/Models/Concerns/BranchScoped.php:16`.

BranchScoped is an explicitly invoked query scope, not a global scope or route-binding restriction. Several lists apply it while detail/edit/update handlers accept unrestricted route-bound models. Having the module permission permits opening another branch's record by ID. The main dashboard also queries firm-wide records without applying branch scopes.

Confirmed: a branch A user could not see branch B's client in the list but successfully opened its detail URL.

Fix: centralize record policies and enforce them on detail, write, export, attachment, and related-record selection paths. Apply branch restrictions to dashboard data too. Preserve the documented firm-wide records and privileged cross-branch access deliberately.

### 5. High: receipt uploads can execute HTML in the app origin

Sources: `app/Http/Controllers/ExpenseController.php:75`, `app/Models/Concerns/HasAttachments.php:41`, `app/Http/Controllers/AttachmentController.php:19`.

Some upload fields accept any file. HasAttachments stores the client-supplied MIME type, and the view endpoint serves bytes inline with that MIME type. An HTML attachment can therefore execute script when opened under the application origin.

Confirmed: an HTML receipt containing a script was accepted and served inline as `text/html` with the script intact. Script execution was not tested in a browser.

Fix: enforce content-derived MIME allowlists, permit inline display only for safe formats, force other files to download, and use suitable `nosniff`/sandbox protections or an isolated file-serving origin. Apply the rule consistently to all upload handlers.

### 6. High: matter billing bypasses the payment ledger

Source: `app/Http/Controllers/MatterBillingController.php:32`.

Invoice creation accepts arbitrary `paid_amount` without a maximum tied to the total and without creating InvoicePayment records. It bypasses the separate payment permission and the locked balance validation in FinanceController. Status is independently user-selected.

Confirmed: a user holding only `matters.billing.invoices.store` created an invoice with total 100 and paid amount 1,000, with zero payment records.

Fix: share one invoice/payment service between both flows. Record collections through authorized payment records; cap amounts, reconcile totals, and derive paid/part-paid status from actual payments.

### 7. Medium: draft/void invoices distort finance totals and accept payments

Sources: `app/Http/Controllers/FinanceController.php:164`, `app/Http/Controllers/FinanceController.php:287`, `app/Http/Controllers/FinanceController.php:132`.

Total invoiced and outstanding aggregate all invoice statuses, including draft and void. The open-invoice query checks only paid amount versus total; storePayment does not reject void/draft invoices. A void invoice can appear as collectible and be changed to part-paid/paid by a payment.

Fix: define the eligible invoice lifecycle explicitly and apply it to statistics, payment choices, and payment validation. Keep draft/void values separate from issued receivables.

### 8. Medium: petty cash can be overspent by concurrent requests

Source: `app/Http/Controllers/PettyCashController.php:66`.

The balance is checked before creating the transaction, without a database transaction or shared lock. Two withdrawals can both observe sufficient funds and exceed the float together. For example, two requests for 60 against a balance of 100 can both pass the balance check.

Fix: serialize balance validation and insertion using a locked cash-account/ledger row inside a transaction. Test simultaneous withdrawals against MySQL, since the SQLite test suite does not model production locking.

### 9. Medium: reference allocation is unsafe under concurrency and soft deletion

Source: `app/Support/MonthlyReferenceNumber.php:15`.

The generator reads the largest suffix and adds one without reserving it. Concurrent requests can produce the same reference and hit the unique constraint. For soft-deleting models, deletion of the latest record hides it from this query while the database still retains its unique reference, so the next creation can try to reuse it. The generator also loads every matching reference into PHP.

Fix: use a persistent, locked sequence keyed by prefix and month, with uniqueness and retry protection; never derive reservations solely from visible business records.

### 10. Medium: approval decisions and expense creation are not atomic

Sources: `app/Http/Controllers/RequisitionController.php:147`, `app/Services/ApprovalService.php:73`.

A requisition decision updates approval, audit, requisition, and generated expense independently. An expense insertion failure leaves a requisition approved without its expense, and retrying approval is then rejected as already decided. Concurrent approve/reject requests can also overwrite a decision or duplicate work. Expense.requisition_id is not unique in its creation migration.

Fix: lock and recheck the current request/approval inside one transaction; make downstream expense creation idempotent with a database uniqueness rule appropriate to the workflow. Apply the same decision serialization to leave approvals.

### 11. Medium: calendar and court reminders are not scheduled by the app

Sources: `bootstrap/app.php:33`, `app/Console/Commands/SendCalendarReminders.php:12`, `app/Console/Commands/SendCourtReminders.php:12`.

The commands exist, but only annual leave reminders are registered in the application schedule. Running Laravel's scheduler alone will not send court or calendar reminders. An external cron configuration could change this conclusion; none was established in this review.

Fix: schedule the commands at an agreed local time with overlap protection, document the scheduler/queue setup, and verify delivery and retry behavior.

## Verification

- Six isolated HTTP probes reproduced findings 1–6: **6 passed, 21 assertions**. These probes asserted the existing vulnerable behavior to establish evidence; they are temporary diagnostic tests, not regression tests to retain.
- Full suite completed with a 512 MB PHP limit: **154 tests, 944 assertions; 142 passed, 6 errors, 2 failures, 4 skipped**. The errors/failures concern company-logo and profile/signature file writes, plus Livewire temporary-upload metadata. Most explicitly report local permission/directory-creation problems; the temporary-upload error remains unresolved. These results do not establish equivalent production failures.
- The initial run stopped at the default 128 MB PHP memory limit during PDF generation. The larger-limit rerun completed with a reported peak of 116 MB; this does not establish a production memory leak.
- Test environment: SQLite in memory, array sessions/mail/cache, broadcasting disabled, logging sent to the null channel, Xdebug disabled. MySQL concurrency, live mail/SMS/storage/broadcasting, production security headers/cookies, dependency advisories, and visual browser/mobile behavior were not verified.
- No production code was changed during this review.

## Recommended order

Address client identity verification, attachment authorization, administrator protection, branch isolation, and safe file serving first. Then unify invoice/payment handling, repair ledger concurrency and idempotency, and activate reminders. Add negative authorization tests for each actor/record/action combination as part of the fixes.

Reference guidance: [OWASP Authorization Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html), [OWASP Email Validation and Verification](https://cheatsheetseries.owasp.org/cheatsheets/Email_Validation_and_Verification_Cheat_Sheet.html).
