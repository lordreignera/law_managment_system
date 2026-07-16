<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LetterTemplate extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'general' => 'General Letter',
        'opinion' => 'Legal Opinion',
        'demand_notice' => 'Demand Notice',
        'instruction' => 'Instruction Letter',
        'engagement' => 'Engagement Letter',
        'litigation' => 'Litigation Letter',
        'recovery' => 'Recovery Letter',
    ];

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function letterhead()
    {
        return $this->belongsTo(Letterhead::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function letters()
    {
        return $this->hasMany(LegalLetter::class);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? str($this->category)->headline()->toString();
    }
}
