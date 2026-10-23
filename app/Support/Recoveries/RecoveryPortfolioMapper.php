<?php

namespace App\Support\Recoveries;

class RecoveryPortfolioMapper
{
    private array $customMap = [];

    private const FIELD_ALIASES = [
        'debtor_name' => ['debtor_name', 'acct_name', 'account_name', 'customer_name', 'nam', 'name'],
        'account_number' => ['foracid', 'account_number', 'loan_account_number', 'account_no'],
        'customer_number' => ['cif_id', 'customer_number', 'customer_id', 'cif'],
        'operative_account' => ['operative_accounts', 'operative_account', 'operative_account_number'],
        'phone' => ['contacts', 'contact', 'phone', 'preferred_phone_number', 'telephone'],
        'alternate_phone' => ['alternate_phone', 'alternative_phone', 'next_of_kin_phone'],
        'whatsapp_number' => ['whatsapp', 'whatsapp_number'],
        'email' => ['email'],
        'nin' => ['nin', 'national_id', 'nin_number'],
        'nssf_number' => ['nssf', 'nssf_number'],
        'crb_reference' => ['crb', 'crb_reference', 'crb_number'],
        'employer' => ['employer', 'employer_name', 'business_employer_name'],
        'branch_name' => ['branch', 'branch_name', 'operative_account_branch_name'],
        'region' => ['region', 'region_name'],
        'collector_name' => ['february_collector', 'collector'],
        'principal_amount' => ['principal_amount', 'principal', 'principal_balance_base', 'chrge_off_principal_base', 'disbursement_amount_base', 'exposure'],
        'interest_amount' => ['interest_amount', 'interest', 'chrge_off_interest_base', 'interest_in_suspense_base'],
        'arrears_amount' => ['total_arrears', 'arrears_balance_base', 'arrears_amount'],
        'outstanding_amount' => ['outstanding_amount', 'net_exposure', 'gross_balance_base', 'outstanding_amount_base', 'book_balance_lcy', 'total_as_at_10_11_25', 'chrge_off_amnt_base'],
        'source_recovered_amount' => ['source_recovered_amount', 'amount_recovered', 'total_recovery_base', 'principal_recovery', 'amount_recovered_in_october', 'amount_recovered_in_jan'],
        'currency' => ['acct_crncy_code', 'currency_code', 'currency'],
        'bucket' => ['bucket', 'year_category', 'aging', 'product_group_description', 'type_of_advance_description'],
        'days_past_due' => ['dpd_count', 'days_past_due'],
        'collateral_held' => ['collateral_held'],
        'cause_of_default' => ['cause_of_default'],
        'pipeline_stage' => ['pipeline_stage', 'stage'],
        'priority' => ['priority', 'risk_priority'],
    ];

    private const PORTFOLIO_OVERRIDES = [
        'stanbic_write_off' => [
            'account_number' => ['foracid'],
            'debtor_name' => ['acct_name'],
            'outstanding_amount' => ['net_exposure'],
            'bucket' => ['year_category'],
        ],
        'stanbic_npl' => [
            'account_number' => ['account_number'],
            'debtor_name' => ['customer_name'],
            'outstanding_amount' => ['book_balance_lcy'],
        ],
        'dfcu_npl' => [
            'account_number' => ['account_number'],
            'debtor_name' => ['account_name'],
            'outstanding_amount' => ['gross_balance_base'],
            'arrears_amount' => ['arrears_balance_base'],
            'principal_amount' => ['disbursement_amount_base'],
        ],
        'dfcu_write_off' => [
            'debtor_name' => ['account_name'],
            'outstanding_amount' => ['outstanding_amount_base'],
            'principal_amount' => ['chrge_off_principal_base', 'principal_balance_base'],
            'interest_amount' => ['chrge_off_interest_base'],
        ],
        'udb' => [
            'debtor_name' => ['account_name'],
            'outstanding_amount' => ['exposure'],
            'principal_amount' => ['exposure'],
            'source_recovered_amount' => ['amount_recovered_in_october', 'amount_recovered_in_jan'],
        ],
        'boa' => [
            'customer_number' => ['client'],
            'debtor_name' => ['nam'],
            'outstanding_amount' => ['total_as_at_10_11_25'],
        ],
    ];

    public function map(array $row, string $portfolioType, int $rowNumber, array $columnMap = []): ?array
    {
        $normalized = $this->normalizeRow($row);
        $portfolioKey = $this->key($portfolioType);
        $customMap = collect($columnMap)
            ->map(fn ($heading) => $this->key((string) $heading))
            ->all();
        $this->customMap = $customMap;
        $sourceOutstanding = $this->value($normalized, 'outstanding_amount', $portfolioKey);
        $sourceRecovered = $this->money($this->value($normalized, 'source_recovered_amount', $portfolioKey));

        if (! isset($customMap['source_recovered_amount']) && $portfolioKey === 'dfcu_write_off' && $this->blank($normalized['total_recovery_base'] ?? null)) {
            $sourceRecovered = $this->money($normalized['principal_recovery'] ?? null)
                + $this->money($normalized['interest_recovery'] ?? null);
        } elseif (! isset($customMap['source_recovered_amount']) && $portfolioKey === 'udb') {
            $sourceRecovered = $this->money($normalized['amount_recovered_in_october'] ?? null)
                + $this->money($normalized['amount_recovered_in_jan'] ?? null);
        }

        $data = [
            'portfolio_type' => $portfolioType,
            'import_row_number' => $rowNumber,
            'account_number' => $this->text($this->value($normalized, 'account_number', $portfolioKey)),
            'customer_number' => $this->text($this->value($normalized, 'customer_number', $portfolioKey)),
            'debtor_name' => $this->text($this->value($normalized, 'debtor_name', $portfolioKey)),
            'phone' => $this->text($this->value($normalized, 'phone', $portfolioKey)),
            'alternate_phone' => $this->text($this->value($normalized, 'alternate_phone', $portfolioKey)),
            'whatsapp_number' => $this->text($this->value($normalized, 'whatsapp_number', $portfolioKey)),
            'email' => $this->email($this->value($normalized, 'email', $portfolioKey)),
            'nin' => $this->text($this->value($normalized, 'nin', $portfolioKey)),
            'nssf_number' => $this->text($this->value($normalized, 'nssf_number', $portfolioKey)),
            'crb_reference' => $this->text($this->value($normalized, 'crb_reference', $portfolioKey)),
            'employer' => $this->text($this->value($normalized, 'employer', $portfolioKey)),
            'branch_name' => $this->text($this->value($normalized, 'branch_name', $portfolioKey)),
            'region' => $this->text($this->value($normalized, 'region', $portfolioKey)),
            'collector_name' => $this->text($this->value($normalized, 'collector_name', $portfolioKey)),
            'operative_account' => $this->text($this->value($normalized, 'operative_account', $portfolioKey)),
            'days_past_due' => $this->integer($this->value($normalized, 'days_past_due', $portfolioKey)),
            'principal_amount' => $this->money($this->value($normalized, 'principal_amount', $portfolioKey)),
            'interest_amount' => $this->money($this->value($normalized, 'interest_amount', $portfolioKey)),
            'arrears_amount' => $this->money($this->value($normalized, 'arrears_amount', $portfolioKey)),
            'outstanding_amount' => $this->money($sourceOutstanding),
            'amount_recovered' => 0,
            'currency' => $this->text($this->value($normalized, 'currency', $portfolioKey)) ?: 'UGX',
            'bucket' => $this->text($this->value($normalized, 'bucket', $portfolioKey)),
            'pipeline_stage' => $this->enum($this->value($normalized, 'pipeline_stage', $portfolioKey), ['new', 'contact_pending', 'engaged', 'promise_to_pay', 'field_visit', 'legal_review', 'recovered', 'untraceable', 'closed'], 'new'),
            'priority' => $this->enum($this->value($normalized, 'priority', $portfolioKey), ['low', 'normal', 'high', 'urgent'], 'normal'),
            'collateral_held' => $this->text($this->value($normalized, 'collateral_held', $portfolioKey)),
            'cause_of_default' => $this->text($this->value($normalized, 'cause_of_default', $portfolioKey)),
            'status' => 'active',
            'raw_payload' => array_merge($normalized, ['_source_reported_recovery' => $sourceRecovered]),
        ];

        if (! $data['debtor_name'] && ! $data['account_number'] && ! $data['customer_number']) {
            return null;
        }

        if (! $data['debtor_name']) {
            $data['debtor_name'] = $data['account_number'] ?: $data['customer_number'];
        }

        if ($sourceOutstanding === null) {
            $data['outstanding_amount'] = $data['principal_amount'] + $data['interest_amount'] + $data['arrears_amount'];
        }

        return $data;
    }

    private function value(array $row, string $field, string $portfolioKey)
    {
        if (isset($this->customMap[$field])) {
            return $row[$this->customMap[$field]] ?? null;
        }

        foreach (self::PORTFOLIO_OVERRIDES[$portfolioKey][$field] ?? [] as $alias) {
            if (array_key_exists($alias, $row) && ! $this->blank($row[$alias])) {
                return $row[$alias];
            }
        }

        foreach (self::FIELD_ALIASES[$field] ?? [] as $alias) {
            if (array_key_exists($alias, $row) && ! $this->blank($row[$alias])) {
                return $row[$alias];
            }
        }

        return null;
    }

    private function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalized[$this->key((string) $key)] = is_string($value) ? trim($value) : $value;
        }

        return $normalized;
    }

    private function key(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?: '';

        return trim($value, '_');
    }

    private function text($value): ?string
    {
        if ($this->blank($value)) {
            return null;
        }

        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        $value = trim((string) $value);

        return in_array(strtolower($value), ['null', 'n/a', 'na', '.'], true) ? null : $value;
    }

    private function email($value): ?string
    {
        $value = $this->text($value);

        return $value && filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    private function integer($value): ?int
    {
        $money = $this->money($value);

        return $money > 0 ? (int) $money : null;
    }

    private function enum($value, array $allowed, string $default): string
    {
        $value = $this->text($value);

        return $value && in_array(strtolower($value), $allowed, true) ? strtolower($value) : $default;
    }

    private function money($value): float
    {
        if ($this->blank($value)) {
            return 0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);
        $negative = str_starts_with($value, '(') && str_ends_with($value, ')');
        $value = preg_replace('/[^0-9.\-]/', '', $value) ?: '0';
        $amount = is_numeric($value) ? (float) $value : 0;

        return $negative ? $amount * -1 : $amount;
    }

    private function blank($value): bool
    {
        return $value === null || trim((string) $value) === '';
    }
}
