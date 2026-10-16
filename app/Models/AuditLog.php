<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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
}
