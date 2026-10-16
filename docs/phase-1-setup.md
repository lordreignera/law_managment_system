# Phase 1 setup

Run `php artisan migrate:fresh --seed` only against the unused installation database. Schema changes for Phase 1 are in the initial create-table migrations; no alter migrations or backfill jobs are required.

The fresh seed creates the bootstrap administrators, a default Kampala office and departments, role permissions, standard classifications, currencies, and notification templates. Unconfirmed banks, bank branches, Centenary regions/branches, judicial officers, opposing counsel, and recovery-client portfolios are left empty. Demo clients, intakes, and matters are not seeded. Confirm the default office and departments before registering real staff.

## Load approved lists

Open **System Settings → Import Master Data**. Download the CSV template for each list. CSV, XLSX and XLS uploads are accepted, with at most 2,000 master-data rows per import.

1. Load firm branches, departments and job titles before users.
2. Load courts before judicial officers, banks before bank branches, and Centenary regions before Centenary branches.
3. Configure each recovery client's portfolio types before importing recovery accounts.
4. Confirm tax rates and treatment, then activate the relevant tax records. The seeded tax examples remain inactive until confirmed.
5. Review notification subjects, bodies, channels and active status before enabling scheduled reminders.

Use the exact parent name requested by a template, rather than database IDs. `currency_iso_code` refers to a configured three-letter code such as UGX. Use `1`/`0` or `true`/`false` for boolean columns. Separate portfolio types and role permissions with `|`. Company reference codes are generated automatically.

An invalid master-data row rejects and rolls back the file, reporting the row number. Reference imports update a record matching its name and parent. Notification templates match their event key. Users and roles are create-only: existing emails and role names are rejected. Protected administrator roles cannot be imported or assigned through user imports. User/role imports require the corresponding access-control permissions as well as import access. Staff passwords are hashed; the upload does not send account emails.

## Controlled workflows

- Staff registration, approval and editing use the active job-title list. Current inactive titles can remain on existing staff. Renaming a title updates its assigned staff profiles.
- Recovery portfolios must belong to the selected client. Centenary branch selections must match the chosen region. Other bank branches must belong to the selected bank. Currencies must be active configured ISO codes. Recovery import failures identify invalid rows and skip those accounts.
- Invoices calculate the selected active VAT or withholding treatment on the server. Inclusive VAT is split from the entered amount; exclusive VAT is added; withholding is deducted from the amount payable. The rate, treatment and amounts are saved with the invoice, so later setting changes do not rewrite issued amounts. Invoices and payments retain the existing UGX reporting currency; configured currencies are also available to recovery accounts and billable rates.
- Notification templates drive court/calendar reminders, staff account notices, leave notices and requisition notices. The event list and supported variables are shown in the settings form. Disabling a template stops its deliveries. Database-channel messages appear under the real notification bell and **My Notifications**. Email and SMS require the deployment's existing mail/SMS configuration.
