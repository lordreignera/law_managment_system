<?php

namespace App\Support\Recoveries;

use App\Models\RecoveryAccount;

class RecoveryAccountIdentity
{
    /** @return array{key:string,source:string} */
    public static function fromData(array $data): array
    {
        $account = self::normalize((string) ($data['account_number'] ?? ''));
        $customer = self::normalize((string) ($data['customer_number'] ?? ''));
        $name = self::normalize((string) ($data['debtor_name'] ?? ''));
        $phone = preg_replace('/\D+/', '', (string) ($data['phone'] ?? '')) ?: '';

        [$source, $value] = match (true) {
            $account !== '' => ['account', $account],
            $customer !== '' => ['customer', $customer],
            $name !== '' && $phone !== '' => ['name_phone', $name.'|'.$phone],
            default => ['name', $name],
        };

        return ['key' => hash('sha256', $source.':'.$value), 'source' => $source];
    }

    public static function existing(int $clientId, string $portfolio, array $data): ?RecoveryAccount
    {
        $identity = self::fromData($data);
        $query = RecoveryAccount::withTrashed()
            ->where('recovery_client_id', $clientId)
            ->where('portfolio_type', $portfolio === '' ? null : $portfolio);

        $match = (clone $query)->where('identity_key', $identity['key'])->first();
        if ($match) {
            return $match;
        }

        // Older or manually inserted records may predate the identity key.
        $field = match ($identity['source']) {
            'account' => 'account_number',
            'customer' => 'customer_number',
            default => null,
        };

        if ($field) {
            return (clone $query)->whereNull('identity_key')->where($field, $data[$field])->first();
        }

        return (clone $query)->whereNull('identity_key')
            ->where('debtor_name', $data['debtor_name'])
            ->when($identity['source'] === 'name_phone', fn ($query) => $query->where('phone', $data['phone']))
            ->first();
    }

    private static function normalize(string $value): string
    {
        return strtoupper(preg_replace('/[^\pL\pN]+/u', '', trim($value)) ?: '');
    }
}
