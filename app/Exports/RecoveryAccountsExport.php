<?php

namespace App\Exports;

use App\Models\RecoveryAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class RecoveryAccountsExport implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private User $user, private array $filters = [])
    {
    }

    public function query()
    {
        return RecoveryAccount::query()
            ->with(['client', 'assignee', 'branch', 'importBatch'])
            ->forBranchOf($this->user)
            ->when($this->filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('debtor_name', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%")
                        ->orWhere('customer_number', 'like', "%{$search}%")
                        ->orWhereHas('client', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($this->filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($this->filters['client'] ?? null, fn (Builder $query, $client) => $query->where('recovery_client_id', $client))
            ->when($this->filters['officer'] ?? null, fn (Builder $query, $officer) => $query->where('assigned_to', $officer))
            ->when($this->filters['portfolio_type'] ?? null, fn (Builder $query, string $portfolio) => $query->where('portfolio_type', $portfolio))
            ->latest();
    }

    public function headings(): array
    {
        return [
            'Bank/Client',
            'Portfolio Type',
            'Debtor',
            'Account Number',
            'Customer Number',
            'Phone',
            'Email',
            'Employer',
            'Branch Name',
            'Region',
            'Collector',
            'Officer',
            'Principal',
            'Interest',
            'Arrears',
            'Outstanding',
            'Recovered',
            'Currency',
            'Bucket',
            'Status',
            'Import Batch',
            'Created On',
        ];
    }

    /**
     * @param  RecoveryAccount  $account
     */
    public function map($account): array
    {
        return [
            $account->client?->name,
            $account->portfolio_type,
            $account->debtor_name,
            $account->account_number,
            $account->customer_number,
            $account->phone,
            $account->email,
            $account->employer,
            $account->branch_name,
            $account->region,
            $account->collector_name,
            $account->assignee?->name,
            (float) $account->principal_amount,
            (float) $account->interest_amount,
            (float) $account->arrears_amount,
            (float) $account->outstanding_amount,
            (float) $account->amount_recovered,
            $account->currency,
            $account->bucket,
            $account->statusLabel(),
            $account->importBatch?->source_file,
            optional($account->created_at)->format('Y-m-d'),
        ];
    }

    public function title(): string
    {
        return 'Recovery Accounts';
    }
}
