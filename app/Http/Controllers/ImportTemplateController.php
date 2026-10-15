<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportTemplateController extends Controller
{
    private const HEADINGS = [
        'clients' => ['name', 'client_type', 'first_name', 'last_name', 'organization_name', 'gender', 'nin_passport_no', 'email', 'phone', 'address', 'occupation', 'tin', 'status'],
        'matters' => ['client_no', 'title', 'practice_area', 'instruction_type', 'matter_category', 'opened_on', 'privacy_status', 'opposite_counsel', 'opposing_counsel_name', 'status', 'description'],
        'litigation' => ['matter_ref', 'court_name', 'case_number', 'judicial_officer', 'event_type', 'starts_at', 'ends_at', 'status', 'assigned_to_email', 'notes'],
        'recoveries' => ['account_number', 'customer_number', 'debtor_name', 'phone', 'email', 'employer', 'branch_name', 'region', 'collector_name', 'principal', 'interest', 'arrears_amount', 'outstanding_amount', 'amount_recovered', 'currency', 'bucket', 'days_past_due', 'collateral_held', 'cause_of_default'],
        'land-titles' => ['borrower_name', 'reference_no', 'bank', 'bank_branch', 'mzo', 'matter_reference', 'handler', 'instruction_type', 'instruction_date', 'received_from', 'returned_to', 'received_at', 'dispatched_at', 'returned_at', 'status', 'notes'],
    ];

    public function download(string $template): StreamedResponse
    {
        abort_unless(isset(self::HEADINGS[$template]), 404);

        return response()->streamDownload(function () use ($template): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, self::HEADINGS[$template]);
            fclose($handle);
        }, $template.'-import-template.csv', ['Content-Type' => 'text/csv']);
    }
}
