<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportTemplateController extends Controller
{
    private const HEADINGS = [
        'clients' => ['name', 'client_type', 'first_name', 'last_name', 'organization_name', 'gender', 'nin_passport_no', 'email', 'phone', 'address', 'occupation', 'tin', 'status'],
        'matters' => ['client_no', 'title', 'practice_area', 'instruction_type', 'matter_category', 'opened_on', 'instruction_date', 'privacy_status', 'opposite_counsel', 'opposing_counsel_name', 'status', 'description', 'client_summary', 'next_action', 'next_action_due', 'file_numbers', 'partner_emails', 'associate_emails'],
        'litigation' => ['matter_ref', 'court_name', 'case_number', 'judicial_officer', 'event_type', 'starts_at', 'ends_at', 'status', 'assigned_to_email', 'notes', 'next_step', 'next_step_due', 'reminder_days_before', 'escalation_days_after', 'reminders_enabled'],
        'recoveries' => ['account_number', 'customer_number', 'debtor_name', 'phone', 'alternate_phone', 'whatsapp_number', 'email', 'nin', 'nssf_number', 'crb_reference', 'employer', 'branch_name', 'region', 'collector_name', 'principal', 'interest', 'arrears_amount', 'outstanding_amount', 'source_recovered_amount', 'currency', 'bucket', 'pipeline_stage', 'priority', 'days_past_due', 'collateral_held', 'cause_of_default'],
        'securities' => ['borrower_name', 'reference_no', 'security_type', 'movement_direction', 'borrower_contact', 'title_number', 'land_reference', 'tenure', 'district', 'facility_account_no', 'bank', 'bank_branch', 'mzo', 'client_no', 'matter_reference', 'handler', 'instruction_type', 'instruction_details', 'instruction_date', 'received_from', 'dispatched_to', 'returned_to', 'received_at', 'dispatched_at', 'dispatch_acknowledged_by', 'dispatch_acknowledged_at', 'returned_at', 'status', 'notes'],
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
