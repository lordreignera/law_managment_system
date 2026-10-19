<?php

use App\Models\Bank;
use App\Models\BankBranch;
use App\Models\BillableRate;
use App\Models\BillingType;
use App\Models\BudgetCategory;
use App\Models\BusinessIndustry;
use App\Models\CentenaryBranch;
use App\Models\CentenaryRegion;
use App\Models\ContactPosition;
use App\Models\Country;
use App\Models\Court;
use App\Models\CurrencyType;
use App\Models\Department;
use App\Models\ExpenseCategory;
use App\Models\InstructionType;
use App\Models\JobTitle;
use App\Models\JudicialOfficer;
use App\Models\LeaveType;
use App\Models\Letterhead;
use App\Models\MatterCategory;
use App\Models\NotificationTemplate;
use App\Models\OpposingCounsel;
use App\Models\PaymentMode;
use App\Models\PracticeArea;
use App\Models\RecoveryClient;
use App\Models\RelationshipType;
use App\Models\RequisitionCategory;
use App\Models\Salutation;
use App\Models\Shelf;
use App\Models\TaxSetting;
use App\Models\ZonalOffice;

return [
    'salutations' => [
        'title' => 'Salutations',
        'singular' => 'Salutation',
        'model' => Salutation::class,
        'description' => 'Client and next-of-kin title options such as Mr, Ms, Dr, and Counsel.',
    ],
    'countries' => [
        'title' => 'Countries',
        'singular' => 'Country',
        'model' => Country::class,
        'description' => 'Country of origin options used on client and next-of-kin records.',
    ],
    'contact-positions' => [
        'title' => 'Contact Positions',
        'singular' => 'Contact Position',
        'model' => ContactPosition::class,
        'description' => 'Client contact roles such as Director, Primary Contact, Normal Contact, and Other.',
    ],
    'relationship-types' => [
        'title' => 'Relationship Types',
        'singular' => 'Relationship Type',
        'model' => RelationshipType::class,
        'description' => 'Next-of-kin relationship options used when registering individual clients.',
    ],
    'practice-areas' => [
        'title' => 'Practice Areas',
        'singular' => 'Practice Area',
        'model' => PracticeArea::class,
        'description' => 'Areas of legal service used when opening matters.',
    ],
    'business-industries' => [
        'title' => 'Business Industries',
        'singular' => 'Business Industry',
        'model' => BusinessIndustry::class,
        'description' => 'Client industries used when creating and reporting on matters.',
    ],
    'matter-categories' => [
        'title' => 'Matter Categories',
        'singular' => 'Matter Category',
        'model' => MatterCategory::class,
        'description' => 'Matter classifications shown on the matter opening form.',
    ],
    'shelves' => [
        'title' => 'Shelves',
        'singular' => 'Shelf',
        'model' => Shelf::class,
        'description' => 'File shelves or workflow buckets used to organize matters.',
    ],
    'instruction-types' => [
        'title' => 'Instruction Types',
        'singular' => 'Instruction Type',
        'model' => InstructionType::class,
        'description' => 'Types of client instructions received by the firm.',
    ],
    'job-titles' => [
        'title' => 'Jobs',
        'singular' => 'Job',
        'model' => JobTitle::class,
        'description' => 'Staff job titles and positions.',
    ],
    'budget-categories' => [
        'title' => 'Budget Categories',
        'singular' => 'Budget Category',
        'model' => BudgetCategory::class,
        'description' => 'Budget headings used for planning and controls.',
    ],
    'courts' => [
        'title' => 'Courts',
        'singular' => 'Court',
        'model' => Court::class,
        'description' => 'Court stations and divisions used for litigation work.',
        'extra_fields' => ['court_level', 'station'],
    ],
    'judicial-officers' => [
        'title' => 'Judicial Officers',
        'singular' => 'Judicial Officer',
        'model' => JudicialOfficer::class,
        'description' => 'Judicial officers assigned to courts and court events.',
        'extra_fields' => ['court_id', 'title', 'chambers', 'email', 'phone'],
    ],
    'opposing-counsels' => [
        'title' => 'Opposing Counsel',
        'singular' => 'Opposing Counsel',
        'model' => OpposingCounsel::class,
        'description' => 'External advocates and firms linked to matters.',
        'extra_fields' => ['firm_name', 'email', 'phone'],
    ],
    'banks' => [
        'title' => 'Banks',
        'singular' => 'Bank',
        'model' => Bank::class,
        'description' => 'Banks used for recoveries, securities, payments, and clients.',
    ],
    'recovery-clients' => [
        'title' => 'Recovery Clients',
        'singular' => 'Recovery Client',
        'model' => RecoveryClient::class,
        'description' => 'Banks and institutional clients that send recovery portfolios to the firm.',
        'extra_fields' => ['contact_person', 'email', 'phone', 'portfolio_types'],
    ],
    'bank-branches' => [
        'title' => 'Bank Branches / Financial Institutions',
        'singular' => 'Bank Branch',
        'model' => BankBranch::class,
        'description' => 'Bank branches or financial institution offices that send and receive securities.',
        'extra_fields' => ['bank_id', 'office_location'],
    ],
    'zonal-offices' => [
        'title' => 'MZO / Zonal Offices',
        'singular' => 'Zonal Office',
        'model' => ZonalOffice::class,
        'description' => 'Ministry zonal offices, office locations, and districts covered for securities work.',
        'extra_fields' => ['office_location', 'districts_covered'],
    ],
    'centenary-regions' => [
        'title' => 'Centenary Regions',
        'singular' => 'Centenary Region',
        'model' => CentenaryRegion::class,
        'description' => 'Centenary Bank recovery regions.',
        'extra_fields' => ['office_location'],
    ],
    'centenary-branches' => [
        'title' => 'Centenary Branches',
        'singular' => 'Centenary Branch',
        'model' => CentenaryBranch::class,
        'description' => 'Centenary Bank branches linked to a region.',
        'extra_fields' => ['centenary_region_id', 'office_location'],
    ],
    'leave-types' => [
        'title' => 'Leave Types',
        'singular' => 'Leave Type',
        'model' => LeaveType::class,
        'description' => 'Human resource leave categories.',
        'extra_fields' => ['annual_entitlement_days', 'carry_forward', 'requires_attachment'],
    ],
    'payment-modes' => [
        'title' => 'Payment Modes',
        'singular' => 'Payment Mode',
        'model' => PaymentMode::class,
        'description' => 'Ways the firm receives or makes payments.',
    ],
    'billing-types' => [
        'title' => 'Billing Types',
        'singular' => 'Billing Type',
        'model' => BillingType::class,
        'description' => 'Billing arrangements applied to client files.',
    ],
    'currency-types' => [
        'title' => 'Currency Types',
        'singular' => 'Currency Type',
        'model' => CurrencyType::class,
        'description' => 'Currencies used for billing, recoveries, and expenses.',
        'extra_fields' => ['iso_code', 'symbol'],
    ],
    'tax-settings' => [
        'title' => 'Tax Settings',
        'singular' => 'Tax Setting',
        'model' => TaxSetting::class,
        'description' => 'Tax rates and treatment used by billing and finance.',
        'extra_fields' => ['tax_type', 'rate', 'is_inclusive'],
    ],
    'notification-templates' => [
        'title' => 'Notification Templates',
        'singular' => 'Notification Template',
        'model' => NotificationTemplate::class,
        'description' => 'Reusable messages for system notifications.',
        'extra_fields' => ['channel', 'event_key', 'subject', 'body'],
    ],
    'requisition-categories' => [
        'title' => 'Requisition Categories',
        'singular' => 'Requisition Category',
        'model' => RequisitionCategory::class,
        'description' => 'Categories used when staff request money or resources.',
    ],
    'expense-categories' => [
        'title' => 'Expense Categories',
        'singular' => 'Expense Category',
        'model' => ExpenseCategory::class,
        'description' => 'Expense classifications for finance and reporting.',
    ],
    'letterheads' => [
        'title' => 'Letterheads',
        'singular' => 'Letterhead',
        'model' => Letterhead::class,
        'description' => 'Letterhead templates used for firm correspondence.',
        'extra_fields' => ['header_text', 'footer_text', 'is_default'],
    ],
    'billable-rates' => [
        'title' => 'Billable Rates',
        'singular' => 'Billable Rate',
        'model' => BillableRate::class,
        'description' => 'Hourly rates used for professional fee billing.',
        'extra_fields' => ['hourly_rate', 'currency_type_id'],
    ],
    'departments' => [
        'title' => 'Departments',
        'singular' => 'Department',
        'model' => Department::class,
        'description' => 'Organizational departments within branches.',
        'extra_fields' => ['branch_id'],
    ],
];
