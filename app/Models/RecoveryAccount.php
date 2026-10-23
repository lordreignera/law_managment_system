<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecoveryAccount extends Model
{
    use Auditable;
    use BranchScoped;
    use HasFactory;
    use SoftDeletes;

    public const STATUSES = [
        'active' => 'Active',
        'closed' => 'Closed',
        'written_off' => 'Written Off',
    ];

    public const PIPELINE_STAGES = [
        'new' => 'New',
        'contact_pending' => 'Contact Pending',
        'engaged' => 'Engaged',
        'promise_to_pay' => 'Promise to Pay',
        'field_visit' => 'Field Visit',
        'legal_review' => 'Legal Review',
        'recovered' => 'Recovered',
        'untraceable' => 'Untraceable',
        'closed' => 'Closed',
    ];

    public const PRIORITIES = [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    public const SKIP_TRACE_STATUSES = [
        'not_started' => 'Not Started',
        'pending' => 'Pending',
        'in_progress' => 'In Progress',
        'found' => 'Found',
        'not_found' => 'Not Found',
        'failed' => 'Failed',
    ];

    protected $guarded = [];

    protected $casts = [
        'raw_payload' => 'array',
        'assigned_at' => 'datetime',
        'days_past_due' => 'integer',
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'arrears_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
        'amount_recovered' => 'decimal:2',
        'opening_recovered_amount' => 'decimal:2',
        'settled_externally_at' => 'datetime',
        'last_contact_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'skip_trace_checked_at' => 'datetime',
        'sms_opt_in' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(RecoveryClient::class, 'recovery_client_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function importBatch()
    {
        return $this->belongsTo(RecoveryImportBatch::class, 'recovery_import_batch_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function activities()
    {
        return $this->hasMany(RecoveryActivity::class)->latest('activity_at');
    }

    public function skipTraceChecks()
    {
        return $this->hasMany(RecoverySkipTraceCheck::class)->latest('checked_at');
    }

    public function promiseReminderLogs()
    {
        return $this->hasMany(RecoveryPromiseReminderLog::class)->latest('scheduled_for');
    }

    public function getNetOutstandingBalanceAttribute(): float
    {
        if ($this->settled_externally_at) {
            return 0;
        }

        return max(((float) $this->outstanding_amount) - ((float) $this->amount_recovered), 0);
    }

    /**
     * Recompute recovered totals and each activity's running balance.
     */
    public function recomputeRecovered(): void
    {
        $runningRecovered = (float) $this->opening_recovered_amount;
        $openingOutstanding = (float) $this->outstanding_amount;
        $settledExternally = false;

        RecoveryActivity::query()
            ->where('recovery_account_id', $this->id)
            ->orderBy('activity_at')
            ->orderBy('id')
            ->get()
            ->each(function (RecoveryActivity $activity) use (&$runningRecovered, $openingOutstanding, &$settledExternally) {
                if ($activity->review_status === 'approved') {
                    $runningRecovered += (float) $activity->amount_paid;
                }

                if ($activity->review_status === 'approved' && $activity->response_outcome === 'fully_repaid') {
                    $settledExternally = true;
                }

                $balanceAfter = $activity->review_status !== 'approved'
                    ? null
                    : ($settledExternally ? 0 : max($openingOutstanding - $runningRecovered, 0));

                if (($activity->outstanding_balance_after === null && $balanceAfter !== null)
                    || ($activity->outstanding_balance_after !== null && $balanceAfter === null)
                    || ($balanceAfter !== null && (float) $activity->outstanding_balance_after !== (float) $balanceAfter)) {
                    $activity->forceFill(['outstanding_balance_after' => $balanceAfter])->save();
                }
            });

        $this->amount_recovered = $runningRecovered;
        $this->save();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function pipelineStageLabel(): string
    {
        return self::PIPELINE_STAGES[$this->pipeline_stage] ?? str($this->pipeline_stage)->headline()->toString();
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? str($this->priority)->headline()->toString();
    }

    public function skipTraceStatusLabel(): string
    {
        return self::SKIP_TRACE_STATUSES[$this->skip_trace_status] ?? str($this->skip_trace_status)->headline()->toString();
    }

    public function sourceReportedRecovery(): float
    {
        return (float) (($this->raw_payload ?? [])['_source_reported_recovery'] ?? 0);
    }

    public function importedNotes(): array
    {
        $notes = [];

        foreach ($this->raw_payload ?? [] as $field => $value) {
            if (! preg_match('/(^|_)comments?($|_)/', (string) $field) || ! is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);
            if ($value !== '' && ! in_array(strtolower($value), ['null', 'n/a', 'na', '.'], true)) {
                $notes[str($field)->replace('_', ' ')->headline()->toString()] = $value;
            }
        }

        return $notes;
    }
}
