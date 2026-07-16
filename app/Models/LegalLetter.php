<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class LegalLetter extends Model
{
    use HasAttachments;
    use HasFactory;
    use SoftDeletes;

    public const STATUSES = [
        'draft' => 'Draft',
        'pending_review' => 'Pending Review',
        'approved' => 'Approved',
        'sent' => 'Sent',
        'received' => 'Received Copy Uploaded',
        'closed' => 'Closed',
    ];

    public const SIGNATURE_MODES = [
        'none' => 'Unsigned Draft',
        'profile' => 'Use Profile Signature',
        'upload' => 'Upload Signature',
        'drawn' => 'Draw Signature',
    ];

    protected $guarded = [];

    protected $casts = [
        'letter_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
        'client_visible' => 'boolean',
    ];

    public function template()
    {
        return $this->belongsTo(LetterTemplate::class, 'letter_template_id');
    }

    public function letterhead()
    {
        return $this->belongsTo(Letterhead::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function matter()
    {
        return $this->belongsTo(Matter::class);
    }

    public function recoveryAccount()
    {
        return $this->belongsTo(RecoveryAccount::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function signer()
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function shares()
    {
        return $this->hasMany(LetterShare::class);
    }

    public function sharedUsers()
    {
        return $this->belongsToMany(User::class, 'letter_shares')->withTimestamps();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function typeLabel(): string
    {
        return LetterTemplate::CATEGORIES[$this->letter_type] ?? str($this->letter_type)->headline()->toString();
    }

    public function signatureUrl(): ?string
    {
        if (! $this->signature_path) {
            return null;
        }

        return Storage::disk('public')->url($this->signature_path);
    }

    public function renderedBody(): string
    {
        $clientName = $this->client?->display_name
            ?: $this->matter?->client?->display_name
            ?: $this->recoveryAccount?->debtor_name
            ?: '';

        $replacements = [
            '{client_name}' => $clientName,
            '{recipient_name}' => $this->recipient_name,
            '{recipient_contact}' => $this->recipient_contact,
            '{letter_reference}' => $this->reference_no,
            '{letter_date}' => optional($this->letter_date)->format('jS F Y'),
            '{matter_number}' => $this->matter?->reference_no,
            '{matter_title}' => $this->matter?->title,
            '{recovery_account}' => $this->recoveryAccount?->account_number,
            '{advocate_name}' => $this->signer?->name ?: $this->creator?->name,
            '{firm_name}' => CompanySetting::current()->company_name,
        ];

        return strtr($this->body, array_map(fn ($value) => (string) ($value ?? ''), $replacements));
    }

    public static function nextReference(string $type = 'general'): string
    {
        $setting = CompanySetting::current();
        $firmCode = strtoupper($setting->initials ?: 'KA');

        if (strlen($firmCode) < 2) {
            $firmCode .= 'A';
        }

        $firmCode = substr(preg_replace('/[^A-Z]/', '', $firmCode), 0, 3) ?: 'KA';
        $typeCode = strtoupper(match ($type) {
            'opinion' => 'OPN',
            'demand_notice' => 'DN',
            'instruction' => 'INS',
            'engagement' => 'ENG',
            'litigation' => 'LIT',
            'recovery' => 'REC',
            default => 'GEN',
        });

        $month = now()->format('m');
        $year = now()->format('Y');
        $prefix = "{$firmCode}/{$typeCode}/";
        $suffix = "/{$month}/{$year}";

        $lastNumber = self::where('reference_no', 'like', "{$prefix}%{$suffix}")
            ->pluck('reference_no')
            ->map(function ($reference) use ($prefix, $suffix) {
                return (int) str_replace([$prefix, $suffix], '', $reference);
            })
            ->max() ?? 0;

        return $prefix.str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT).$suffix;
    }

    protected function attachmentDirectory(): string
    {
        return 'attachments/letters/'.$this->id;
    }
}
