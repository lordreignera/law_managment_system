<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'event' => trim((string) $request->input('event', '')),
            'user_id' => $request->integer('user_id') ?: null,
            'auditable_type' => trim((string) $request->input('auditable_type', '')),
            'from' => trim((string) $request->input('from', '')),
            'to' => trim((string) $request->input('to', '')),
        ];

        $query = AuditLog::query()
            ->with('user')
            ->latest('created_at')
            ->latest('id');

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('event', 'like', "%{$search}%")
                    ->orWhere('auditable_type', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('user_agent', 'like', "%{$search}%")
                    ->orWhere('auditable_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($filters['event'] !== '') {
            $query->where('event', $filters['event']);
        }

        if ($filters['user_id']) {
            $query->where('user_id', $filters['user_id']);
        }

        if ($filters['auditable_type'] !== '') {
            $query->where('auditable_type', $filters['auditable_type']);
        }

        if ($from = $this->parseDate($filters['from'])) {
            $query->where('created_at', '>=', $from->startOfDay());
        }

        if ($to = $this->parseDate($filters['to'])) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        $statsQuery = clone $query;
        $stats = [
            'matching' => $statsQuery->count(),
            'today' => (clone $query)->whereDate('created_at', today())->count(),
            'actors' => (clone $query)->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
        ];

        $audits = $query->paginate(25)->withQueryString();
        $events = AuditLog::query()->whereNotNull('event')->distinct()->orderBy('event')->pluck('event');
        $auditableTypes = AuditLog::query()
            ->whereNotNull('auditable_type')
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type');
        $users = User::query()->orderBy('name')->get(['id', 'name', 'email']);

        return view('modules.audit.index', compact(
            'audits',
            'auditableTypes',
            'events',
            'filters',
            'stats',
            'users',
        ));
    }

    public function show(AuditLog $auditLog)
    {
        $auditLog->load('user');

        return view('modules.audit.show', [
            'auditLog' => $auditLog,
            'oldValues' => $auditLog->safeValues($auditLog->old_values),
            'newValues' => $auditLog->safeValues($auditLog->new_values),
        ]);
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function recordLabel(?string $type): string
    {
        return $type ? Str::headline(str_replace(['\\', '_', '-'], ' ', Str::afterLast($type, '\\'))) : 'System event';
    }
}
