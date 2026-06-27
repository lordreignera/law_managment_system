<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->writeAuditLog('created', [], $model->auditableAttributes());
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if (empty($changes)) {
                return;
            }

            $changes = array_diff_key($changes, array_flip($model->auditExclude()));

            if (empty($changes)) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), $changes);

            $model->writeAuditLog('updated', $old, $changes);
        });

        static::deleted(function ($model) {
            $model->writeAuditLog('deleted', $model->auditableAttributes(), []);
        });
    }

    public function audits(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest();
    }

    protected function auditableAttributes(): array
    {
        return array_diff_key($this->getAttributes(), array_flip($this->auditExclude()));
    }

    protected function auditExclude(): array
    {
        return ['created_at', 'updated_at', 'password', 'remember_token'];
    }

    protected function writeAuditLog(string $event, array $old, array $new): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'url' => $this->auditContext('fullUrl'),
            'ip_address' => $this->auditContext('ip'),
            'user_agent' => $this->auditContext('userAgent'),
        ]);
    }

    protected function auditContext(string $key): ?string
    {
        try {
            $request = request();

            return match ($key) {
                'fullUrl' => $request->fullUrl(),
                'ip' => $request->ip(),
                'userAgent' => $request->userAgent(),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }
}
