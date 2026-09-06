<?php

namespace App\Http\Controllers;

use Illuminate\Support\Collection;

class UserGuideController extends Controller
{
    public function __invoke()
    {
        $isAdministrator = auth()->user()?->hasAnyRole(['Super Admin', 'Administrator']) ?? false;
        $modules = $this->modulesForUser();
        $sections = $this->sectionsFor($modules);

        return view('help.user-guide', [
            'guideTitle' => $isAdministrator
                ? 'System User Guide'
                : ($modules->count() > 1 ? 'Your User Guide' : ($modules->first()['title'] ?? 'User Guide')),
            'guideSubtitle' => $modules->count() > 1
                ? 'Quick operating guide for the workspaces available to your account.'
                : ($modules->first()['subtitle'] ?? 'Quick operating guide for your workspace.'),
            'guideModules' => $modules,
            'guideSections' => $sections,
            'dashboardUrl' => $this->dashboardUrlFor($modules),
        ]);
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
            ->pluck('sections')
            ->flatten(1)
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
                'route' => 'land-titles.dashboard',
                'route_permission' => 'land-titles.dashboard',
                'screen' => $this->screenPreview('Securities Dashboard', 'Custody queue, institution tracking, MZO movement, and returns.', ['In Custody', 'Pending', 'Returned'], 'securities-dashboard.svg'),
                'permissions' => ['land-titles.dashboard', 'land-titles.index', 'land-titles.create'],
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
        ];
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
                'Create a client intake from Client Management.',
                'Capture mandatory details, preferred advocate, referral details, and conflict parties.',
                'Review pending intakes and approve or reject each request with a reason.',
                'Approved intakes become client records where engagements, files, and matters can be added.',
            ],
        ];
    }

    private function matterSection(): array
    {
        return [
            'id' => 'matters',
            'title' => 'Matter Flow',
            'subtitle' => 'From approved client to active file.',
            'steps' => [
                'Create a matter from the approved client or Matter Management.',
                'Add practice area, matter category, responsible advocates, date opened, privacy status, and file summary.',
                'Use the matter workspace to add instructions, documents, billing, costs, or litigation activity.',
                'Close or archive matters only after work, billing, and documents are complete.',
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
                'Open or link a matter before recording court work.',
                'Add the cause list or court file with case number, court, judge, event type, and next date.',
                'Update filing, service, hearings, rulings, judgment, taxation, and execution steps.',
                'Use the litigation dashboard to monitor active court work and pending lifecycle stages.',
            ],
        ];
    }

    private function lettersSection(): array
    {
        return [
            'id' => 'letters',
            'title' => 'Letters & Opinions',
            'subtitle' => 'Creating branded correspondence.',
            'steps' => [
                'Choose a letter template such as demand notice, legal opinion, engagement letter, or general letter.',
                'Link the letter to a client, matter, or recovery account where applicable.',
                'Use the live preview to confirm the logo, reference, recipient, subject, body, and signature.',
                'Submit drafts for review, approve them, mark them as sent, then upload received copies when returned.',
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
                'Add banks or portfolio categories before importing recovery accounts.',
                'Import portfolios, review batches, and assign accounts to recovery officers.',
                'Monitor net outstanding balances, recovered totals, and officer workload from the dashboard.',
                'Use reports to filter, export, and compare daily, weekly, and monthly collections.',
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
                'When a client pays, enter the amount paid and upload the receipt so the outstanding balance reduces.',
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
                'Capture financial institution, branch, MZO or zonal office, handler, and received date.',
                'Dispatch details should be recorded only when the security is actually dispatched.',
                'Use return actions and the dashboard to track pending, dispatched, returned, and custody records.',
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
}
