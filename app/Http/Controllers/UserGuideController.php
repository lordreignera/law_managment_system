<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class UserGuideController extends Controller
{
    public function __invoke()
    {
        return view('help.user-guide', $this->guideData());
    }

    public function download()
    {
        $data = $this->guideData();
        $data['company'] = CompanySetting::current();
        $data['logoSource'] = $data['company']->logo_public_path;
        $data['generatedAt'] = now();

        return Pdf::loadView('help.user-guide-pdf', $data)
            ->setPaper('a4')
            ->download($this->downloadFilename($data['guideTitle']));
    }

    private function guideData(): array
    {
        $isAdministrator = auth()->user()?->hasAnyRole(['Super Admin', 'Administrator']) ?? false;
        $modules = $this->modulesForUser();

        return [
            'guideTitle' => $isAdministrator
                ? 'System User Guide'
                : ($modules->count() > 1 ? 'Your User Guide' : ($modules->first()['title'] ?? 'User Guide')),
            'guideSubtitle' => $modules->count() > 1
                ? 'Quick operating guide for the workspaces available to your account.'
                : ($modules->first()['subtitle'] ?? 'Quick operating guide for your workspace.'),
            'guideModules' => $modules,
            'guideSections' => $this->sectionsFor($modules),
            'dashboardUrl' => $this->dashboardUrlFor($modules),
        ];
    }

    private function modulesForUser(): Collection
    {
        $user = auth()->user();

        if ($user?->hasAnyRole(['Super Admin', 'Administrator'])) {
            return collect($this->moduleCatalogue());
        }

        return collect($this->moduleCatalogue())
            ->filter(fn (array $module) => collect($module['permissions'])->contains(fn (string $permission) => $user?->can($permission)))
            ->whenEmpty(fn () => collect([$this->generalModule()]))
            ->values();
    }

    private function sectionsFor(Collection $modules): Collection
    {
        return $modules
            ->flatMap(function (array $module) {
                return collect($module['sections'] ?? [])
                    ->map(fn (array $section) => $section + [
                        'screen' => $module['screen'] ?? null,
                        'route' => $module['route'] ?? null,
                        'route_permission' => $module['route_permission'] ?? null,
                    ]);
            })
            ->prepend($this->firstStepsSection())
            ->unique('id')
            ->values();
    }

    private function dashboardUrlFor(Collection $modules): string
    {
        $user = auth()->user();

        if ($user?->hasAnyRole(['Super Admin', 'Administrator']) || $user?->can('dashboard')) {
            return route('dashboard');
        }

        $route = $modules
            ->first(function (array $module) use ($user) {
                if (! isset($module['route'])) {
                    return false;
                }

                $permission = $module['route_permission'] ?? $module['route'];

                return $user?->can($permission) ?? false;
            })['route'] ?? null;

        return $route ? route($route) : route('profile.show');
    }

    private function moduleCatalogue(): array
    {
        return [
            [
                'title' => 'Client Guide',
                'subtitle' => 'Client intake, approved records, and engagement files.',
                'icon' => 'mdi-account-multiple',
                'route' => 'clients.index',
                'route_permission' => 'clients.index',
                'screen' => $this->screenPreview('Client Management', 'Intake queue, approved clients, and engagement records.', ['Pending Intakes', 'Approved Clients', 'Conflict Review'], 'clients-dashboard.svg'),
                'permissions' => ['intakes.index', 'clients.index'],
                'sections' => [$this->clientSection()],
            ],
            [
                'title' => 'Matter Guide',
                'subtitle' => 'Matter dashboard, register, workspaces, billing, and files.',
                'icon' => 'mdi-briefcase',
                'route' => 'matters.dashboard',
                'route_permission' => 'matters.dashboard',
                'screen' => $this->screenPreview('Matter Dashboard', 'Pipeline, active files, responsible teams, and recent matters.', ['Open Matters', 'Active Work', 'Billing Pending'], 'matter-dashboard.svg'),
                'permissions' => ['matters.dashboard', 'matters.index'],
                'sections' => [$this->matterSection()],
            ],
            [
                'title' => 'Litigation Guide',
                'subtitle' => 'Cause lists, court events, outcomes, and court work.',
                'icon' => 'mdi-gavel',
                'route' => 'litigation.dashboard',
                'route_permission' => 'litigation.dashboard',
                'screen' => $this->screenPreview('Litigation Dashboard', 'Court diary, lifecycle stages, next steps, and cause list.', ['Today', 'This Week', 'Overdue'], 'litigation-dashboard.svg'),
                'permissions' => ['litigation.dashboard', 'litigation.index', 'litigation.create'],
                'sections' => [$this->litigationSection()],
            ],
            [
                'title' => 'Calendar Guide',
                'subtitle' => 'Firm, department, personal, and court schedules.',
                'icon' => 'mdi-calendar-month',
                'route' => 'calendar.index',
                'route_permission' => 'calendar.index',
                'permissions' => ['calendar.index', 'calendar.create'],
                'sections' => [$this->calendarSection()],
            ],
            [
                'title' => 'Attendance Guide',
                'subtitle' => 'Check-in, corrections, approvals, and staff reports.',
                'icon' => 'mdi-clock-check-outline',
                'route' => 'attendance.index',
                'route_permission' => 'attendance.index',
                'permissions' => ['attendance.index'],
                'sections' => [$this->attendanceSection()],
            ],
            [
                'title' => 'Letters & Opinions Guide',
                'subtitle' => 'Drafting, review, approval, signatures, and dispatch.',
                'icon' => 'mdi-file-sign',
                'route' => 'letters.dashboard',
                'route_permission' => 'letters.dashboard',
                'screen' => $this->screenPreview('Letters Dashboard', 'Drafts, pending review, templates, sent letters, and received copies.', ['Drafts', 'Pending Review', 'Sent'], 'letters-dashboard.svg'),
                'permissions' => ['letters.dashboard', 'letters.index', 'letters.create'],
                'sections' => [$this->lettersSection()],
            ],
            [
                'title' => 'Recoveries Guide',
                'subtitle' => 'Portfolio imports, assignments, activity logs, and collections.',
                'icon' => 'mdi-bank',
                'route' => 'recoveries.dashboard',
                'route_permission' => 'recoveries.dashboard',
                'screen' => $this->screenPreview('Recovery Dashboard', 'Portfolio imports, assignment coverage, collections, and performance.', ['Active Accounts', 'Net Outstanding', 'Recovered'], 'recoveries-dashboard.svg'),
                'permissions' => ['recoveries.dashboard', 'recoveries.index', 'recoveries.import', 'recoveries.reports'],
                'sections' => [$this->recoveriesManagerSection()],
            ],
            [
                'title' => 'My Recoveries Guide',
                'subtitle' => 'Assigned accounts, follow-up reports, payments, and collection reports.',
                'icon' => 'mdi-account-cash-outline',
                'route' => 'recoveries.mine',
                'route_permission' => 'recoveries.mine',
                'screen' => $this->screenPreview('My Recoveries', 'Assigned accounts, activity reporting, receipts, and collection totals.', ['Assigned', 'Recovered', 'Outstanding'], 'my-recoveries-dashboard.svg'),
                'permissions' => ['recoveries.mine'],
                'sections' => [$this->recoveryOfficerSection()],
            ],
            [
                'title' => 'Securities Guide',
                'subtitle' => 'Security registration, custody movement, dispatch, and returns.',
                'icon' => 'mdi-file-document',
                'route' => 'securities.dashboard',
                'route_permission' => 'securities.dashboard',
                'screen' => $this->screenPreview('Securities Dashboard', 'Custody queue, institution tracking, MZO movement, and returns.', ['In Custody', 'Pending', 'Returned'], 'securities-dashboard.svg'),
                'permissions' => ['securities.dashboard', 'securities.index', 'securities.create'],
                'sections' => [$this->securitiesSection()],
            ],
            [
                'title' => 'Finance Guide',
                'subtitle' => 'Requisitions, invoices, payments, expenses, petty cash, and ledger work.',
                'icon' => 'mdi-cash-multiple',
                'route' => 'finance.dashboard',
                'route_permission' => 'finance.dashboard',
                'screen' => $this->screenPreview('Finance Dashboard', 'Quick actions, invoices, payments, expenses, requisitions, and ledger movement.', ['Total Invoiced', 'Collected', 'Outstanding'], 'finance-dashboard.svg'),
                'permissions' => ['finance.dashboard', 'finance.index', 'finance.payments.create', 'expenses.index', 'petty-cash.index', 'ledger.index'],
                'sections' => [$this->financeSection()],
            ],
            [
                'title' => 'Human Resources Guide',
                'subtitle' => 'Staff records, access setup, approvals, and leave management.',
                'icon' => 'mdi-account-tie',
                'route' => 'hr.dashboard',
                'route_permission' => 'hr.dashboard',
                'screen' => $this->screenPreview('HR Dashboard', 'Staff access, leave, department coverage, branch headcount, and recent staff.', ['Active Staff', 'Pending Access', 'Pending Leave'], 'hr-dashboard.svg'),
                'permissions' => ['hr.dashboard', 'staff.index', 'staff.create', 'leave.index'],
                'sections' => [$this->hrSection()],
            ],
            [
                'title' => 'Payroll Guide',
                'subtitle' => 'Configurable staff pay, approvals, payslips, and statutory exports.',
                'icon' => 'mdi-cash-register',
                'route' => 'payroll.dashboard',
                'route_permission' => 'payroll.dashboard',
                'permissions' => [
                    'payroll.dashboard',
                    'payroll.employees.index',
                    'payroll.components.index',
                    'payroll.rules.index',
                    'payroll.periods.index',
                    'payroll.reports',
                ],
                'sections' => [$this->payrollSection()],
            ],
            [
                'title' => 'Access Requests Guide',
                'subtitle' => 'Review staff registration details and approve requests in batches.',
                'icon' => 'mdi-shield-account',
                'route' => 'access.approvals.index',
                'route_permission' => 'access.approvals.index',
                'permissions' => ['access.users.index', 'access.approvals.index', 'access.users.approve'],
                'sections' => [[
                    'id' => 'access-requests',
                    'title' => 'Staff Access Approval',
                    'subtitle' => 'Approve complete requests using each staff member\'s requested role and office.',
                    'steps' => [
                        'Open User Management, then Approval Requests. Check the requested role, branch, department, job title, and phone.',
                        'Select individual rows, Select 5, Select 10, or the header checkbox to select complete requests on the current page.',
                        'Choose Approve Selected. Each user keeps their own requested role and receives an approval notice. Approval permission is required.',
                        'Requests marked Review first need missing or incorrect details corrected on their Review page before approval.',
                        'If any selected request is invalid, the whole batch stays unapproved until corrected. Repeated approval does not duplicate role grants or notices.',
                    ],
                ]],
            ],
            [
                'title' => 'System Configuration Guide',
                'subtitle' => 'Find and manage the firm\'s controlled reference data.',
                'icon' => 'mdi-tune',
                'route' => 'settings.system.overview',
                'route_permission' => 'settings.system.overview',
                'permissions' => ['settings.system.overview'],
                'sections' => [[
                    'id' => 'system-configuration',
                    'title' => 'Find a System Setting',
                    'subtitle' => 'Search configuration cards as you type.',
                    'steps' => [
                        'Open System Settings and start typing a name. Typing B immediately shows settings beginning with B, including Banks and Bank Branches.',
                        'Continue typing to narrow the results. Clear restores the full configuration list without a page reload.',
                        'The shared Back link returns to the previously visited page and retains its search filters.',
                    ],
                ]],
            ],
            [
                'title' => 'Reporting & Analytics Guide',
                'subtitle' => 'Controlled report definitions, management packs, exports, and audit rules.',
                'icon' => 'mdi-chart-box-outline',
                'route' => 'reports.index',
                'route_permission' => 'reports.index',
                'permissions' => ['reports.index'],
                'sections' => [$this->reportingSection()],
            ],
            [
                'title' => 'Messages Guide',
                'subtitle' => 'Internal and client communication available to your account.',
                'icon' => 'mdi-message-text-outline',
                'route' => 'messages.index',
                'route_permission' => 'messages.index',
                'screen' => $this->screenPreview('Messages', 'Direct messages, department notices, branch updates, and file communication.', ['Inbox', 'Unread', 'Recent Threads'], 'messages-dashboard.svg'),
                'permissions' => ['messages.index'],
                'sections' => [$this->messagesSection()],
            ],
        ];
    }

    private function generalModule(): array
    {
        return [
            'title' => 'Workspace Guide',
            'subtitle' => 'Basic account setup and navigation for your available workspace.',
            'icon' => 'mdi-view-dashboard-outline',
            'permissions' => [],
            'sections' => [],
        ];
    }

    private function screenPreview(string $title, string $description, array $stats, string $image): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'stats' => $stats,
            'image' => 'admin/assets/images/guides/'.$image,
            'image_path' => public_path('admin/assets/images/guides/'.$image),
        ];
    }

    private function downloadFilename(string $title): string
    {
        return Str::slug($title ?: 'user-guide').'-'.now()->format('Y-m-d').'.pdf';
    }

    private function firstStepsSection(): array
    {
        return [
            'id' => 'first-steps',
            'title' => 'First Steps',
            'subtitle' => 'Set up your account before using modules.',
            'steps' => [
                'Login with the email approved by the administrator.',
                'Open your profile from the top-right menu and upload your profile photo.',
                'Upload your signature if you will sign letters, opinions, or other documents.',
                'Use the left sidebar to open the modules available to your role.',
                'Use the Back link above workspace pages to return to the previous page with its filters. Forms use centered, rounded sections for easier reading.',
                'If a menu is missing, ask the system administrator to review your role permissions.',
            ],
        ];
    }

    private function clientSection(): array
    {
        return [
            'id' => 'clients',
            'title' => 'Client Flow',
            'subtitle' => 'How new clients move into the system.',
            'steps' => [
                'Create a client intake from Client Management and capture the client details, legal issue, referral source, and related parties for conflict screening.',
                'A reviewer records the conflict check result and notes. An intake can be approved only after the check is recorded as cleared.',
                'The intake reviewer can approve, reject with a reason, or return it for more information. Editing and resubmitting a returned intake requires a fresh conflict check before approval.',
                'Approval creates the client record. Open that client to add files or ADRs and to create more than one matter when the instructions cover separate pieces of work.',
            ],
        ];
    }

    private function matterSection(): array
    {
        return [
            'id' => 'matters',
            'title' => 'Matter Flow',
            'subtitle' => 'From an approved client to a distinct, managed legal file.',
            'steps' => [
                'Create a matter for an approved client from the client record or Matter Management. One client can have several separate matters.',
                'The matter starts as Open. Add its practice area, category, responsible advocates, instruction date, privacy, and work summary; do not set a closure date when opening it.',
                'Select only the existing client files that belong to this matter, or upload its documents. The matter workspace then holds its instructions, document bundles, billing, costs, and any court work.',
                'Move the matter through planning, active work, waiting, billing, and review as appropriate. Close it only when the legal work is complete, then archive it if required.',
            ],
        ];
    }

    private function litigationSection(): array
    {
        return [
            'id' => 'litigation',
            'title' => 'Litigation Flow',
            'subtitle' => 'How court work is tracked.',
            'steps' => [
                'Start from an open matter and choose Schedule Court Event from the matter workspace or Litigation menu.',
                'Link the event to that matter, then record the case number, court, judicial officer, event type, date, responsible advocate, and any supporting court document.',
                'Saving a court event moves an Open or Planning matter to Active. After the event, record its result, next step, and due date so the team can follow through.',
                'Use the Cause List and Litigation Dashboard to monitor hearings, filing deadlines, overdue events, outcomes, and next steps. Reopen a closed or archived matter before scheduling new court work.',
            ],
        ];
    }

    private function calendarSection(): array
    {
        return [
            'id' => 'calendar',
            'title' => 'Calendar Flow',
            'subtitle' => 'Keep firm, department, personal, and court dates in one view.',
            'steps' => [
                'Use the Firm Calendar to schedule meetings, appointments, tasks, and deadlines.',
                'Select one or more branches, then optionally select a department and assigned staff using the searchable checkbox dropdown.',
                'Use My calendar to see events assigned to you; court events follow the matter team assignment.',
                'Public holidays and court events appear alongside ordinary calendar events.',
                'Scheduled reminders are sent through the configured notification template and calendar scheduler.',
            ],
        ];
    }

    private function attendanceSection(): array
    {
        return [
            'id' => 'attendance',
            'title' => 'Attendance Flow',
            'subtitle' => 'Record attendance with an auditable approval trail.',
            'steps' => [
                'Check in when you start work and check out when you finish; the system records time, device, IP address, and user agent.',
                'A successful staff login also records a check-in, and logout records a check-out when the session is closed.',
                'If a time is missing, submit a correction for the date with the corrected time and reason.',
                'The assigned manager must approve or reject the correction; the original record remains auditable.',
                'Managers use the daily report for late or absent staff and the monthly report for present, late, incomplete, leave, and absence totals.',
            ],
        ];
    }

    private function lettersSection(): array
    {
        return [
            'id' => 'letters',
            'title' => 'Letters & Opinions',
            'subtitle' => 'Draft, review, approve, send, and file correspondence.',
            'steps' => [
                'Choose a letter template such as demand notice, legal opinion, engagement letter, or general letter.',
                'Link the letter to a client, matter, or recovery account where applicable.',
                'Save a draft at any time; recipient and content can be completed later. Complete the recipient, subject, date, and body before submitting for review.',
                'Drafts and letters awaiting review are visible to their creator, the creator’s supervisor in the same department, and staff explicitly shared on the letter. Set staff department and supervisor details correctly for this access to work.',
                'Use the live preview to check the firm branding, reference, recipient, subject, body, and signature. Supporting documents and signatures can be added from the optional section.',
                'Submit the completed draft for review. An authorised supervisor or reviewer approves it before it can be marked as sent.',
                'After sending, choose whether the client should see the letter in the portal. Upload the stamped or acknowledged copy when it returns.',
            ],
        ];
    }

    private function recoveriesManagerSection(): array
    {
        return [
            'id' => 'recoveries',
            'title' => 'Recoveries Manager Flow',
            'subtitle' => 'Portfolio control and recovery performance.',
            'steps' => [
                'Select a seeded lender and portfolio: Stanbic Write Off or NPL, DFCU NPL or Write Off, UDB, or BOA. Add a new client or add a portfolio to an existing client on Import Portfolios.',
                'For a new lender layout, create a client import template by mapping its debtor name and outstanding amount headings, plus any optional headings. Download the client CSV or use the standard CSV.',
                'Upload the lender workbook or CSV and inspect the preview before confirming. A multi-sheet workbook reads only the matching portfolio worksheet. Repeated files are blocked; matching account identifiers can be skipped or have non-financial details updated. Correct invalid rows in the source file.',
                'On the batch page, tick individual accounts or Select page to assign that page. Choose all unassigned or all visible accounts in the batch when work should go to one officer across pages.',
                'Check the imported opening balance, source branch, and any source follow-up notes. Past recoveries reported by the lender are context; new payments remain pending until you review their receipts against a bank or lender record.',
                'Open Payment Reviews to approve or reject a payment, full repayment, or write-off request. Record the verification reference or approval basis. Reject incorrect entries so the officer can submit a corrected one; reverse a previously approved entry with a reason if the bank later rejects it.',
                'Monitor net outstanding balances, recovered totals, officer workload, and the recovery pipeline from the dashboard.',
                'Use reports to filter, export, and compare daily, weekly, and monthly collections by bank, client, and officer.',
                'Record promise dates for follow-up. New accounts start with SMS reminders off; enable them only after confirming the instruction to send messages. The scheduled reminder job processes due promises when enabled.',
                'Review skip-trace results for NIN, NSSF, CRB, and WhatsApp checks on each account.',
            ],
        ];
    }

    private function recoveryOfficerSection(): array
    {
        return [
            'id' => 'my-recoveries',
            'title' => 'Recovery Officer Flow',
            'subtitle' => 'Reporting assigned account follow-up.',
            'steps' => [
                'Open My Recoveries to see only the accounts assigned to you.',
                'Use Report Activity after a call, visit, promise, payment, or other follow-up.',
                'When a client pays, enter the amount and upload the receipt. The amount remains pending until a manager verifies and approves it; only then does the outstanding balance reduce.',
                'Report full repayment with supporting evidence when the lender confirms it. A manager must approve the closure. Rejected entries remain in history and can be corrected with a new report.',
                'Keep the pipeline stage, priority, and next follow-up date current after every contact.',
                'Use the daily, weekly, and monthly reports to review your collection performance.',
            ],
        ];
    }

    private function securitiesSection(): array
    {
        return [
            'id' => 'securities',
            'title' => 'Securities Flow',
            'subtitle' => 'Custody movement from receipt to return.',
            'steps' => [
                'Register each security when it is received and attach the supporting document.',
                'Capture the linked client or matter, security type, title or property reference, borrower, financial institution, branch, MZO or zonal office, handler, and instruction details.',
                'Record every receipt, dispatch, return, or transfer in the custody movement history with acknowledgement details.',
                'Use the documentation checklist to sign off required documents before closing a security.',
                'Add due milestones; the system reminds the handler before a due date and flags overdue work.',
                'Use return actions and the dashboard to track pending, dispatched, returned, closed, and custody records. Archive only returned or closed records; archiving preserves the register, movement history, documents, and audit trail.',
            ],
        ];
    }

    private function financeSection(): array
    {
        return [
            'id' => 'finance',
            'title' => 'Finance Flow',
            'subtitle' => 'Spending, billing, payments, and account coding.',
            'steps' => [
                'Start with the finance dashboard for quick actions and current KPI totals.',
                'Create requisitions for spending requests and record approved expenses or petty cash movements.',
                'Create invoices from the finance overview and select the correct chart of account.',
                'Record payments against invoices, select where the money was received, and review outstanding balances.',
            ],
        ];
    }

    private function hrSection(): array
    {
        return [
            'id' => 'human-resources',
            'title' => 'Human Resources Flow',
            'subtitle' => 'Staff setup, staff records, and leave management.',
            'steps' => [
                'Use New Staff to create approved staff accounts from the dashboard.',
                'Confirm role, branch, department, phone, temporary password, and generated staff number.',
                'Use Staff Register to review active and pending staff records.',
                'Review submitted leave requests and keep upcoming leave visible from the HR dashboard.',
            ],
        ];
    }

    private function payrollSection(): array
    {
        return [
            'id' => 'payroll',
            'title' => 'Payroll Flow',
            'subtitle' => 'Protected, configurable payroll from setup to filing.',
            'steps' => [
                'Payroll access is restricted to approved payroll permissions. Start from the Payroll Dashboard to see setup gaps, open periods, and pending approvals.',
                'Configure Components with the Component flags dropdown. Select Taxable, NSSF liable, Employer cost, Recurring, and Active as appropriate for each pay item.',
                'Configure Calculation Rules for PAYE, NSSF, and custom rules. Use effective dates and the Rule flags dropdown; custom formulas use the documented payroll variables.',
                'Create an employee payroll profile with statutory identifiers, bank or payment details, an effective-dated salary, and the searchable Recurring components dropdown.',
                'Open a payroll period, calculate its run, review the component snapshots and payslips, then submit it for approval. The person who prepared a run cannot approve the same run.',
                'After approval, prepare the payment batch, export the bank register, payslips, and statutory files, then record the payment and PAYE or NSSF filing references.',
                'For any multi-select field, open the field, search or scroll the list, use Select all where appropriate, tick the items, and close the menu. Selected items are summarized in the closed field; this pattern is also used for matter teams, calendar assignments, document bundles, and letter sharing.',
            ],
        ];
    }

    private function messagesSection(): array
    {
        return [
            'id' => 'messages',
            'title' => 'Messages',
            'subtitle' => 'Internal and client communication.',
            'steps' => [
                'Use Messages to communicate with individual users, departments, branches, or the whole firm.',
                'Client matter messages are visible only to the client and the assigned matter team.',
                'Uploaded chat files are stored using the configured document storage bucket.',
            ],
        ];
    }

    private function reportingSection(): array
    {
        return [
            'id' => 'reporting-analytics',
            'title' => 'Reporting & Analytics Flow',
            'subtitle' => 'Use one controlled definition for every management report.',
            'steps' => [
                'Open Reports & Analytics to browse the catalogue by group or search for a report by name, formula, or module.',
                'The sidebar combines reports into Clients & Legal Work, Recoveries & Securities, Finance & Payroll, HR & Attendance, and Management. All Reports shows the complete catalogue.',
                'Use Previous report and Next report to move through a category. The report counter shows your position and the selected time period follows you to the next report.',
                'Open a report definition before exporting so that the included statuses, date rule, branch rule, permission rule, and draft or void treatment are clear.',
                'Use the Weekly, Monthly, Quarterly, or Yearly selector to apply the selected periods to period-based KPIs, charts, and the decision table. Cards labeled now or current are live queue snapshots.',
                'Download any chart as a PNG, or export the decision table as CSV, Excel, or PDF. Read the management insight below each chart before making a decision, then open the linked operational report when you need to work on the underlying records.',
                'Use the linked operational report for live module data where one is available. Recovery and payroll reports remain protected by their own module permissions.',
                'Use branch and department comparison reports only within the scope granted to your role. Firm-wide management packs are restricted to authorized administrators and management users.',
                'Opening a report records the report, actor, period, permitted data scope, and displayed row count. Exporting records the report, actor, period, format, and exported row count in the audit trail.',
            ],
        ];
    }
}
