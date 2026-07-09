<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecoveryClient extends Model
{
    use HasCompanyCode;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'portfolio_types' => 'array',
        'is_active' => 'boolean',
    ];

    public static function codePrefix(): string
    {
        return 'RC';
    }

    public function accounts()
    {
        return $this->hasMany(RecoveryAccount::class);
    }

    public function importBatches()
    {
        return $this->hasMany(RecoveryImportBatch::class);
    }

    public function portfolioOptions(): array
    {
        $types = $this->portfolio_types ?: [];

        return collect($types)
            ->mapWithKeys(fn ($label, $key) => is_string($key) ? [$key => $label] : [$label => $label])
            ->all();
    }
}
