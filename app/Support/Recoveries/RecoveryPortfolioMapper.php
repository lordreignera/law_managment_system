<?php

namespace App\Support\Recoveries;

use Illuminate\Support\Arr;

class RecoveryPortfolioMapper
{
    public const DEFAULT_PORTFOLIOS = [
        'Stanbic Write Off',
        'Stanbic NPL',
        'DFCU NPL',
        'DFCU Write Off',
        'UDB',
        'BOA',
    ];

    private const FIELD_ALIASES = [
        'debtor_name' => ['acct_name', 'account_name', 'customer_name', 'nam', 'name'],
        'account_number' => ['foracid', 'account_number', 'loan_account_number', 'account_no', 'client'],
        'customer_number' => ['cif_id', 'customer_number', 'customer_id', 'cif'],
        'operative_account' => ['operative_accounts', 'operative_account', 'operative_account_number'],
        'phone' => ['contacts', 'contact', 'phone', 'preferred_phone_number', 'telephone'],
        'email' => ['email'],
        'employer' => ['employer', 'employer_name', 'business_employer_name'],
        'branch_name' => ['branch', 'branch_name', 'operative_account_branch_name'],
        'region' => ['region', 'region_name'],
        'collector_name' => ['february_collector', 'collector'],
        'principal_amount' => ['principal', 'principal_balance_base', 'charge_off_principal_base', 'disbursement_amount_base', 'exposure'],
        'interest_amount' => ['interest', 'charge_off_interest_base', 'interest_in_suspense_base'],
        'arrears_amount' => ['total_arrears', 'arrears_balance_base', 'arrears_amount'],
        'outstanding_amount' => ['net_exposure', 'gross_balance_base', 'outstanding_amount_base', 'book_balance_lcy', 'total_as_at_101125', 'charge_off_amnt_base'],
        'amount_recovered' => ['total_recovery_base', 'principal_recovery', 'amount_recovered_in_october', 'amount_recovered_in_jan'],
        'currency' => ['acct_crncy_code', 'currency_code', 'currency'],
        'bucket' => ['bucket', 'year_category', 'aging', 'product_group_description', 'type_of_advance_description'],
        'days_past_due' => ['dpd_count', 'days_past_due'],
        'collateral_held' => ['collateral_held'],
        'cause_of_default' => ['cause_of_default'],
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
            'interest_amount' => ['total_arrears'],
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
            'principal_amount' => ['charge_off_principal_base', 'principal_balance_base'],
            'interest_amount' => ['charge_off_interest_base'],
        ],
        'udb' => [
            'debtor_name' => ['account_name'],
            'outstanding_amount' => ['exposure'],
            'principal_amount' => ['exposure'],
            'amount_recovered' => ['amount_recovered_in_october', 'amount_recovered_in_jan'],
            'bucket' => ['collateral_held'],
        ],
        'boa' => [
            'account_number' => ['client'],
            'debtor_name' => ['nam'],
            'outstanding_amount' => ['total_as_at_101125'],
            'interest_amount' => ['arrears_amount'],
            'bucket' => ['cause_of_default'],
        ],
    ];

    public function map(array $row, string $portfolioType, int $rowNumber): ?array
    {
        $normalized = $this->normalizeRow($row);
        $portfolioKey = $this->key($portfolioType);

        $data = [
            'portfolio_type' => $portfolioType,
            'import_row_number' => $rowNumber,
            'account_number' => $this->text($this->value($normalized, 'account_number', $portfolioKey)),
            'customer_number' => $this->text($this->value($normalized, 'customer_number', $portfolioKey)),
            'debtor_name' => $this->text($this->value($normalized, 'debtor_name', $portfolioKey)),
            'phone' => $this->text($this->value($normalized, 'phone', $portfolioKey)),
            'email' => $this->email($this->value($normalized, 'email', $portfolioKey)),
            'employer' => $this->text($this->value($normalized, 'employer', $portfolioKey)),
            'branch_name' => $this->text($this->value($normalized, 'branch_name', $portfolioKey)),
            'region' => $this->text($this->value($normalized, 'region', $portfolioKey)),
            'collector_name' => $this->text($this->value($normalized, 'collector_name', $portfolioKey)),
            'operative_account' => $this->text($this->value($normalized, 'operative_account', $portfolioKey)),
            'days_past_due' => $this->integer($this->value($normalized, 'days_past_due', $portfolioKey)),
            'principal_amount' => $this->money($this->value($normalized, 'principal_amount', $portfolioKey)),
            'interest_amount' => $this->money($this->value($normalized, 'interest_amount', $portfolioKey)),
            'arrears_amount' => $this->money($this->value($normalized, 'arrears_amount', $portfolioKey)),
            'outstanding_amount' => $this->money($this->value($normalized, 'outstanding_amount', $portfolioKey)),
            'amount_recovered' => $this->money($this->value($normalized, 'amount_recovered', $portfolioKey)),
            'currency' => $this->text($this->value($normalized, 'currency', $portfolioKey)) ?: 'UGX',
            'bucket' => $this->text($this->value($normalized, 'bucket', $portfolioKey)),
            'collateral_held' => $this->text($this->value($normalized, 'collateral_held', $portfolioKey)),
            'cause_of_default' => $this->text($this->value($normalized, 'cause_of_default', $portfolioKey)),
            'status' => 'active',
            'raw_payload' => $normalized,
        ];

        if (! $data['debtor_name'] && ! $data['account_number'] && ! $data['customer_number']) {
            return null;
        }

        if (! $data['debtor_name']) {
            $data['debtor_name'] = $data['account_number'] ?: $data['customer_number'];
        }

        if (! $data['outstanding_amount']) {
            $data['outstanding_amount'] = $data['principal_amount'] + $data['interest_amount'] + $data['arrears_amount'];
        }

        if ($portfolioKey === 'udb') {
            $data['amount_recovered'] = $this->money(Arr::get($normalized, 'amount_recovered_in_october'))
                + $this->money(Arr::get($normalized, 'amount_recovered_in_jan'));
        }

        return $data;
    }

    private function value(array $row, string $field, string $portfolioKey)
    {
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
