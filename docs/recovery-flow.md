# Recovery module: operating flow and gap review

Reviewed against the routes, models, controllers, views, seeders, and feature tests on 23 October 2026. This describes implemented behaviour. It is not a claim that every lender operating rule has been agreed.

## Who does what

| Actor | Current access |
| --- | --- |
| Recoveries Manager | Set up lender clients and portfolios, manage import templates, preview and confirm imports, assign accounts, review payments and closures, reverse approvals with a reason, send due reminders, and use recovery reports. Access to account records remains branch-scoped unless the user can see all branches. |
| Recovery Officer | Open **My Recoveries**, view assigned accounts, record activities and skip-trace checks, and export their own collection report. Direct links to another officer's account are rejected. |
| Administrator / Managing Partner | Recovery manager routes through their role permissions; Super Admin has the global permission bypass. |

The sidebar links to **Recovery Dashboard**, **Accounts Register**, **Import Portfolios**, **Payment Reviews**, **My Recoveries**, and **Reports**. Route-name permissions guard the controller endpoints, so a hidden button alone is not the access control.

## End-to-end path

| Step | Screen / route | Implemented result and next step |
| --- | --- | --- |
| 1. Set up a lender | Recovery Dashboard or Import Portfolios; `recoveries.clients.store` | Create a recovery client and its portfolio names. Fresh seeding supplies Stanbic, dfcu, UDB, and BOA portfolio choices, but no borrower accounts. Existing client/portfolio records can also be managed in System Settings. |
| 2. Add a portfolio | Import Portfolios; `recoveries.clients.portfolios.store` | Add a distinct portfolio name to an active client, then configure the relevant layout. |
| 3. Define the source layout | Import Portfolios; `recoveries.templates.store`, `.download`, `.destroy` | Map debtor name and outstanding balance plus optional source columns to a client/portfolio template. Download its CSV headings. The standard CSV and built-in lender mappings also work. One template is stored per client/portfolio. |
| 4. Preview import | Import Portfolios; `recoveries.import.store` | Choose an active client, portfolio, and Excel/CSV file. The matching worksheet is mapped into a preview; no accounts are created yet. The source file is kept on the private local disk and its SHA-256 fingerprint blocks an exact repeat for the same client and portfolio. |
| 5. Confirm or correct | `/recoveries/imports/{batch}`; `recoveries.batches.commit` | Review new, matching, and invalid counts plus the first 100 valid rows. Choose **skip existing** or **update non-financial details** and confirm. Existing balances, payments, status, and assignee are preserved. Name-based matches are skipped for manual review. A unique account identity also prevents two imports from creating the same client/portfolio account. Correct invalid rows in the source file. |
| 6. Allocate work | Import can assign one officer or match a collector name; batch screen `recoveries.batches.assign`; individual account assignment screen | Assign checked accounts, all unassigned visible accounts, or all visible accounts in a batch. The table's **Select page** checkbox covers only the current page; the scope menu covers the whole visible batch. Branch-hidden account IDs are rejected. Assignment changes on each account now generate account audit entries. |
| 7. Work the account | Accounts Register / My Recoveries → `/recoveries/{recovery}` | The officer sees assigned debtors, source information, priority, stage, contact details, net balance, next follow-up, and activity history. Managers can change account details or officer assignment. Skip-trace checks preserve checker, date, result, and notes. A demand notice can be started through Letters where permitted. |
| 8. Record contact or collection | **Report Activity** modal; `recoveries.activities.store` | Record call/visit/message/legal action, response, notes, and follow-up. A promise requires amount and date. Payment, fully repaid, and write-off requests require evidence and remain pending. Pending money does not enter collections or reduce the balance. |
| 9. Verify and approve | **Payment Reviews**; `recoveries.reviews.index`, `.decision`, `.reverse` | A manager checks the receipt or lender evidence against an external record, enters the verification reference or approval basis, and approves or rejects. Approval applies payment to the balance or closes a fully repaid/written-off account. Rejection keeps the request in history and permits a corrected new entry. A manager may reverse an approved entry with a reason; totals and status are recalculated. |
| 10. Remind and follow up | Recovery Dashboard **Send Due SMS**, or scheduled `kfms:recovery-reminders` daily at 08:00 | Due promises for active accounts with a phone number and SMS reminders enabled are sent through the configured gateway. New accounts start with SMS reminders off. The Laravel scheduler must be running in deployment for automatic sending. |
| 11. Monitor and export | Recovery Dashboard, Accounts Register, My Recoveries, `/recoveries/reports`, Reporting & Analytics | Branch-scoped workload, balances, pipeline, collections, client and officer tables, account Excel export, officer Excel/PDF collection export, and manager Excel/PDF report exports. Collection events only include approved payments. |
| 12. Close or reopen | Payment Reviews and account activity history | Full payment, externally confirmed repayment, and write-off each have a distinct reviewed outcome. Generic account edits cannot set closed or written-off status. A manager reversal with a recorded reason can reopen an account; financial and closure history remains audited. |

Net outstanding on an account is `max(opening outstanding − approved recovered, 0)`, or zero after a manager-approved external full settlement. `amount_recovered` includes the opening recovered figure plus approved payment activities. The lender's source-reported prior recovery is stored separately in the imported payload and shown as context. Report totals currently add account currencies without conversion.

## Exception paths and controls already covered

- An unknown client/portfolio, wrong worksheet/headings, or invalid upload is rejected before accounts are committed.
- Row-level balance and reference-data errors are skipped and counted; up to 20 messages are shown immediately after import.
- The manager's batch view and assignment query are branch-scoped; a Recovery Officer cannot import, assign, or view another officer's direct account link.
- Exact repeated files and matching account identities are blocked or skipped. Soft-deleted accounts also count as existing identities.
- Closed or externally settled accounts cannot receive another activity. Payments above the account's displayed net balance are rejected, including at manager approval time.
- Evidence is mandatory for financial or closure requests; promise amount/date are mandatory for `promised`. Approval requires a recorded verification basis; reversal requires a reason.
- Account edits, activities, and individual or batch officer changes are captured by the account/activity audit traits. Reminder attempts have a separate delivery log.

## Missing loops and decisions before operational sign-off

| Priority | Gap observed | Why it matters / recommended completion |
| --- | --- | --- |
| Operational decision | Bank/lender verification policy | The app requires a manager to record a verification basis, but cannot independently confirm receipt with a bank. Finance and each lender must agree which statements or confirmations qualify and who may approve a write-off before live use. |
| Operational decision | Account identity rules | Current priority is account number, then customer number, then debtor name and phone/name. Confirm stable identifiers per lender. Name-based matches are always skipped for manual review because names can represent separate facilities. |
| High | SMS instruction record and existing accounts | Newly created accounts default to reminders off, but previously imported records may still be enabled. There is no recorded source/date for the lender's SMS instruction or debtor preference. Review existing account flags and add a recorded authorization/opt-out workflow before live sends. |
| High | Promise lifecycle | Any due `promised` activity on an active opted-in account remains eligible until its one successful reminder is logged, even if a later payment satisfied that particular promise. Add promise states (open, kept, broken, cancelled/rescheduled), connect payments to promises, and exclude fulfilled/cancelled promises from reminders and promise reports. |
| High | Historical reporting semantics | Period filters limit the collection activity rows and monthly graph, but officer/client/pipeline and summary totals are current all-time account snapshots. Activity rows attribute the officer using the account's **current** assignee, so reassignment can rewrite historical performance. Label snapshot data separately and attribute collection events to the activity actor or immutable assignment history. |
| High | Mixed currencies | Dashboard cards, graphs, and report totals sum raw amounts across currencies; one chart labels all values UGX. Group by currency or apply an approved, dated conversion rate before showing a combined monetary total. |
| Medium | Import reconciliation evidence | The source file is now kept privately, but skipped-row reasons are still only flashed to the browser, capped at 20, and are not saved on the batch. Persist a downloadable row-error report for review/reprocessing. |
| Medium | Master-data audit | Recovery clients, portfolio names, import templates, batches, and skip-trace checks record some actor fields but do not use the central audit trait. Add change history for setup and template edits, source imports, and skipped-row review. |
| Medium | Officer eligibility and branch handoff | Assignment choices use anyone with the Recovery Officer role, including users who may be inactive or based in another branch. Clarify when cross-branch transfer is allowed, then filter/validate eligible officers and record a handoff event. |
| Medium | Recovery lifecycle testing | Feature tests now cover duplicate upload, account identity matching, pending and approved payments, reversal, write-off review, branch access, bulk assignment, one-time SMS, and exports. Add partial/kept promises, mixed currencies, and report attribution after reassignment. |

## Recommended release sequence

1. Confirm account identity and evidence rules with each lender and Finance; assign approval authority for write-offs.
2. Record SMS authorization and reconcile existing reminder flags before enabling scheduled live delivery.
3. Correct reporting labels/attribution and currency treatment before using the charts for management or client statements.
4. Persist a row-error report, then test remaining exception loops with real sample files and role-specific users.

Related code: `routes/web.php`, `app/Http/Controllers/RecoveryController.php`, `app/Http/Controllers/RecoveryActivityController.php`, `app/Http/Controllers/RecoveryReportController.php`, `app/Imports/RecoveryAccountsImport.php`, `app/Support/Recoveries/RecoveryPromiseReminderService.php`, and `resources/views/modules/recoveries/`.
