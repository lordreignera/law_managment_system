<?php

namespace App\Support\Recoveries;

class RecoveryPortfolioProfiles
{
    public const TEMPLATE_FIELDS = [
        'debtor_name' => 'Debtor name',
        'outstanding_amount' => 'Outstanding amount',
        'account_number' => 'Account number',
        'customer_number' => 'Customer number',
        'phone' => 'Phone',
        'alternate_phone' => 'Alternate phone',
        'whatsapp_number' => 'WhatsApp number',
        'email' => 'Email',
        'branch_name' => 'Source branch',
        'region' => 'Region',
        'collector_name' => 'Collector name',
        'principal_amount' => 'Principal amount',
        'interest_amount' => 'Interest amount',
        'arrears_amount' => 'Arrears amount',
        'source_recovered_amount' => 'Prior recovery in source file',
        'currency' => 'Currency',
        'bucket' => 'Bucket / category',
        'days_past_due' => 'Days past due',
        'collateral_held' => 'Collateral held',
        'cause_of_default' => 'Cause of default',
    ];

    public const CLIENT_PORTFOLIOS = [
        'Stanbic Bank Uganda' => ['Stanbic Write Off', 'Stanbic NPL'],
        'dfcu Bank' => ['DFCU NPL', 'DFCU Write Off'],
        'Uganda Development Bank' => ['UDB'],
        'Bank of Africa Uganda' => ['BOA'],
    ];

    private const SOURCE_HEADERS = [
        'stanbic_write_off' => ['cif_id', 'foracid', 'acct_name', 'net_exposure'],
        'stanbic_npl' => ['account_number', 'customer_number', 'customer_name', 'book_balance_lcy'],
        'dfcu_npl' => ['la_type', 'account_number', 'account_name', 'gross_balance_base'],
        'dfcu_write_off' => ['account_number', 'account_name', 'chrge_off_amnt_base', 'outstanding_amount_base'],
        'udb' => ['account_name', 'exposure', 'collateral_held', 'august_comments'],
        'boa' => ['client', 'nam', 'cause_of_default', 'total_as_at_10_11_25'],
    ];

    public static function key(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($value))) ?: '', '_');
    }

    public static function matchingSheet(string $portfolioType, array $sheetNames): ?string
    {
        $matches = array_values(array_filter(
            $sheetNames,
            fn ($name) => self::key((string) $name) === self::key($portfolioType)
        ));

        return count($matches) === 1 ? $matches[0] : null;
    }

    public static function matchesHeaders(string $portfolioType, array $headings, array $columnMap = []): bool
    {
        $headers = array_map(fn ($heading) => self::key((string) $heading), $headings);

        // The downloadable template is valid for every configured portfolio.
        if (in_array('debtor_name', $headers, true) && in_array('outstanding_amount', $headers, true)) {
            return true;
        }

        if ($columnMap) {
            return count(array_diff(array_map(fn ($heading) => self::key($heading), array_values($columnMap)), $headers)) === 0;
        }

        $required = self::SOURCE_HEADERS[self::key($portfolioType)] ?? null;

        if ($required) {
            return count(array_diff($required, $headers)) === 0;
        }

        // Custom portfolios can use the familiar generic source headings.
        return count(array_intersect(['acct_name', 'account_name', 'customer_name', 'nam', 'name'], $headers)) > 0
            && count(array_intersect(['net_exposure', 'gross_balance_base', 'book_balance_lcy', 'exposure', 'outstanding_amount_base'], $headers)) > 0;
    }
}
