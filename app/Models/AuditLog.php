<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public static function recordFor(Model $auditable, string $event, array $old = [], array $new = []): self
    {
        return self::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'url' => self::requestValue(fn ($request) => $request->fullUrl()),
            'ip_address' => self::requestValue(fn ($request) => $request->ip()),
            'user_agent' => self::requestValue(fn ($request) => $request->userAgent()),
        ]);
    }

    private static function requestValue(\Closure $callback): ?string
    {
        try {
            return $callback(request());
        } catch (\Throwable) {
            return null;
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    /**
     * Return values suitable for display in the maintenance audit viewer.
     * Audit entries may contain configuration or identity data, so secrets
     * are redacted without changing the original audit record.
     */
    public function safeValues(?array $values): array
    {
        return self::redactValues($values ?: []);
    }

    public static function redactValues(array $values): array
    {
        $sensitive = '/password|passwd|secret|token|private[_ -]?key|api[_ -]?key|authorization|cookie|remember|signature/i';

        return collect($values)->mapWithKeys(function ($value, $key) use ($sensitive) {
            $key = (string) $key;

            if (preg_match($sensitive, $key)) {
                return [$key => '[redacted]'];
            }

            if (is_array($value)) {
                return [$key => self::redactValues($value)];
            }

            if (is_string($value) && (Str::contains($value, '-----BEGIN ') || Str::contains($value, 'Bearer '))) {
                return [$key => '[redacted]'];
            }

            return [$key => $value];
        })->all();
    }
}
